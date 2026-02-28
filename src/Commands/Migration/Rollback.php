<?php

/**
 * This file is part of Blitz PHP framework - Database Layer.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Database\Commands\Migration;

use Ahc\Cli\Output\Color;
use BlitzPHP\Database\Commands\DatabaseCommand;
use BlitzPHP\Database\Migration\Runner;

/**
 * Annule les dernières migrations.
 */
class Rollback extends DatabaseCommand
{
    /**
     * {@inheritDoc}
     */
    protected string $name = 'migrate:rollback';

    /**
     * {@inheritDoc}
     */
    protected string $description = 'Annule les dernières migrations.';

    /**
     * {@inheritDoc}
     */
    protected array $options = [
        '-g, --group'         => 'Groupe de base de données à utiliser',
        '-b, --batch'         => 'Numéro de lot cible (ex: 3 pour revenir au lot #3, -2 pour revenir de 2 lots)',
        '--all'               => 'Annuler toutes les migrations',
        '--show-stats'        => 'Afficher les statistiques de l\'opération',
        '--continue-on-error' => 'Ne pas stopper le processus si une annulation échoue',
        '-f, --force'         => 'Forcer l\'exécution en production',
    ];

    /**
     * {@inheritDoc}
     */
    public function handle()
    {
        if (on_prod() && !$this->option('force')) {
            if (!$this->confirm('Êtes-vous sûr de vouloir annuler des migrations en production ?')) {
                return EXIT_SUCCESS;
            }
        }

        $this->eol()->info('Recherche des migrations à annuler...');

        $group = $this->option('group', 'default');
        $batch = $this->option('all') ? 0 : $this->option('batch', 1);

        if (is_string($batch) && !preg_match('/^-?\d+$/', $batch)) {
            $this->error('Le numéro de lot doit être un entier.');
            return EXIT_ERROR;
        }
        $batch = (int) $batch;

        $runner = $this->runner('ALL', $group);
        
        $rolledBack = 0;
        $errorCount = 0;

        $runner->on('process.empty-migrations', function() {
            $this->warning('Aucune migration à annuler');
        })
        ->on('migration.error', function($payload) use(&$errorCount) {
            ['migration' => $migration, 'exception' => $e] = $payload;

            $this->justify(
                $this->getMigrationName($migration), 
                $this->color->error('Échec')
            );
            
            if (!$this->option('continue-on-error')) {
                throw $e;
            }
            
            $errorCount++;
        })
        ->on('migration.skipped', function($payload) {
            ['migration' => $migration] = $payload;

            $this->justify(
                $this->getMigrationName($migration), 
                $this->color->warn('Fichier introuvable')
            );
        })
        ->on('migration.done', function($payload) use(&$rolledBack) {
            ['migration' => $migration, 'duration' => $duration] = $payload;
            
            $this->justify(
                $this->getMigrationName($migration), 
                $this->color->comment($duration . ' ms') . ' ' . $this->color->ok('Annulé')
            );
            
            $rolledBack++;
        });

        $runner->rollback($batch, $group);

        if ($rolledBack > 0) {
            $this->newLine()->success("{$rolledBack} migration(s) annulée(s) avec succès.");
            
            if ($this->option('show-stats')) {
                $this->displayStats($runner, $rolledBack, $errorCount, $batch);
            }
        }

        return EXIT_SUCCESS;
    }

    /**
     * Formate le nom de la migration pour l'affichage
     */
    private function getMigrationName(object $migration): string
    {
        return sprintf(
            '[%s] %s_%s (batch #%d)',
            $migration->namespace ?? $migration->history->namespace,
            $migration->version ?? $migration->history->version,
            $migration->migration ?? $migration->history->migration,
            $migration->history->batch ?? '?'
        );
    }

    /**
     * Affiche les statistiques
     */
    private function displayStats(Runner $runner, int $rolledBack, int $errorCount, int $targetBatch): void
    {
        $options = ['sep' => '-', 'second' => ['fg' => Color::GREEN]];
        $data = [
            'Migrations annulées' => $rolledBack,
            'Migrations échouées' => $errorCount,
            'Lot cible'           => $targetBatch,
            'Groupe de connexion' => $this->option('group', 'default'),
        ];
        
        $this->eol()->border(char: '*');
        
        foreach ($data as $k => $v) {
            $this->justify($k, (string) $v, $options);
        } 
        
        $this->border(char: '*');
    }
}
