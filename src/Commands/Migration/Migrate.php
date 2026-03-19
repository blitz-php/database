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
 * Exécute toutes les nouvelles migrations.
 */
class Migrate extends DatabaseCommand
{
    /**
     * {@inheritDoc}
     */
    protected string $name = 'migrate';

    /**
     * {@inheritDoc}
     */
    protected string $description = 'Recherche et exécute toutes les nouvelles migrations.';

    /**
     * {@inheritDoc}
     */
    protected array $options = [
        '-g, --group'         => 'Groupe de base de données à utiliser',
        '-n, --namespace'     => 'Namespace spécifique à migrer',
        '--show-stats'        => 'Afficher les statistiques de l\'opération',
        '--continue-on-error' => 'Ne pas stopper le processus si une migration échoue',
        '--all'               => 'Migrer tous les namespaces. (Ignore l\option --namespace)',
        '--pretend'           => 'Simuler l\'exécution sans modifier la base (affiche les requêtes SQL)',
        '--seed'              => 'Exécuter les seeders après les migrations',
        '-f, --force'         => 'Forcer l\'exécution en production',
    ];

    /**
     * {@inheritDoc}
     */
    public function handle()
    {
        if (on_prod() && ! $this->option('force')) {
            if (! $this->confirm('Êtes-vous sûr de vouloir exécuter des migrations en production ?')) {
                return EXIT_SUCCESS;
            }
        }

        $this->eol()->info('Recherche des migrations en attente...');

        $group     = $this->option('group', 'default');
        $namespace = $this->option('namespace', APP_NAMESPACE);
        $all       = $this->option('all') === true;
        $pretend   = $this->option('pretend') === true;
        $seed      = $this->option('seed') === true;

        $runner = $this->runner($all ? 'ALL' : $namespace, $group);

        if ($pretend) {
            $this->pretendMode($runner);

            return EXIT_SUCCESS;
        }

        $errorCount      = 0;
        $migrationsCount = 0;

        $runner->on('process.empty-migrations', function () {
            $this->warning('Aucune migration en attente.');
        })
            ->on('process.migrations-disabled', function () {
                $this->badge()->info('Les migrations sont désactivées dans la configuration.');
            })
            ->on('migration.error', function ($payload) use (&$errorCount) {
                ['migration' => $migration, 'exception' => $e] = $payload;

                $this->justify(
                    $this->getMigrationName($migration),
                    $this->color->error('Échec'),
                );

                if (! $this->option('continue-on-error')) {
                    throw $e;
                }

                $errorCount++;
            })
            ->on('migration.ignored', function ($payload) {
                ['migration' => $migration] = $payload;

                $this->justify(
                    $this->getMigrationName($migration),
                    $this->color->warn('Ignoré'),
                );
            })
            ->on('migration.done', function ($payload) use (&$migrationsCount) {
                ['migration' => $migration, 'duration' => $duration] = $payload;

                $this->justify(
                    $this->getMigrationName($migration),
                    $this->color->comment($duration . ' ms') . ' ' . $this->color->ok('Exécuté'),
                );

                $migrationsCount++;
            });

        $executed = $runner->latest($group);

        if ($executed > 0) {
            $this->newLine()->success("{$executed} migration(s) exécutée(s) avec succès.");

            if ($this->option('show-stats')) {
                $this->displayStats($runner, $executed, $errorCount);
            }
        }

        if ($seed && $executed > 0) {
            $this->newLine()->info('Exécution des seeders...');
            $this->call('db:seed', options: ['--group' => $group]);
        }

        return EXIT_SUCCESS;
    }

    /**
     * Mode simulation - affiche les requêtes SQL sans les exécuter
     */
    protected function pretendMode(Runner $runner): void
    {
        $this->warning('Mode simulation activé - AUCUNE modification ne sera effectuée.');
        $this->newLine();

        // TODO: Implémenter l'affichage des requêtes SQL
        $this->info('Les requêtes SQL seraient affichées ici.');
    }

    /**
     * Formate le nom de la migration pour l'affichage
     */
    private function getMigrationName(object $migration): string
    {
        return sprintf(
            '[%s] %s_%s',
            $migration->namespace,
            $migration->version,
            $migration->migration,
        );
    }

    /**
     * Affiche les statistiques d'exécution
     */
    private function displayStats(Runner $runner, int $executed, int $errorCount): void
    {
        $batches = $runner->getLastBatch();
        $history = $runner->getHistory();

        $options = ['sep' => '-', 'second' => ['fg' => Color::GREEN]];
        $data    = [
            'Total dans l\'historique' => $total = count($history),
            'Migrations exécutées'     => $executed,
            'Migrations échouées'      => $errorCount,
            'Migrations ignorées'      => $total - $executed - $errorCount,
            'Dernier lot'              => $batches,
            'Groupe de connexion'      => $this->option('group', 'default'),
            'Namespace'                => $this->option('all') ? 'Tous' : $this->option('namespace', APP_NAMESPACE),
            // 'Durée totale d\'éxécution' => $duration . ' ms',
        ];

        $this->eol()->border(char: '*');

        foreach ($data as $k => $v) {
            $this->justify($k, (string) $v, $options);
        }

        $this->border(char: '*');
    }
}
