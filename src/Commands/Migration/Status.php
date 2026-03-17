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

/**
 * Affiche le statut des migrations.
 */
class Status extends DatabaseCommand
{
    /**
     * {@inheritDoc}
     */
    protected string $name = 'migrate:status';

    /**
     * {@inheritDoc}
     */
    protected string $description = 'Affiche le statut de toutes les migrations.';

    /**
     * {@inheritDoc}
     */
    protected array $options = [
        '-g, --group' => 'Groupe de base de données à utiliser',
    ];

    /**
     * {@inheritDoc}
     */
    public function handle()
    {
        $this->eol()->info('Récupération du statut des migrations...');

        $group = $this->option('group', 'default');
        $runner = $this->runner('ALL', $group);
        
        $history = $runner->getHistory($group);
        $files = $runner->findMigrationFiles();
        
        if (empty($files)) {
            $this->warning('Aucun fichier de migration trouvé.');
            return EXIT_SUCCESS;
        }
        
        $executedMap = [];
        foreach ($history as $item) {
            $key = $item->migration . '_' . $item->version;
            $executedMap[$key] = $item;
        }

        $tbody = [];

        foreach ($files as $file) {
            $key = $file->migration . '_' . $file->version;
            $executed = isset($executedMap[$key]);
            
            $date = $executed ? date('Y-m-d H:i', $executedMap[$key]->time) : '---';
            $batch = $executed ? $executedMap[$key]->batch : '---';
            $status = $executed 
                ? $this->color->ok('EXÉCUTÉE')
                : $this->color->warn('EN ATTENTE');
            
            $tbody[] = [
                $this->getMigrationName($file),
                $date,
                $batch,
                $status,
            ];
        }

        $this->table(['MIGRATION', 'EXÉCUTÉE', 'LOT', 'STATUT'], $tbody);

        // Statistiques
        $total = count($files);
        $executedCount = count($history);
        $pendingCount = $total - $executedCount;

        $this->newLine()->info('RÉSUMÉ');
        $this->justify('Total migrations', (string) $total);
        $this->justify('Exécutées', (string) $executedCount, ['second' => ['fg' => Color::GREEN]]);
        $this->justify('En attente', (string) $pendingCount, ['second' => $pendingCount > 0 ? ['fg' => Color::YELLOW] : []]);
        $this->justify('Dernier lot', (string) $runner->getLastBatch());

        return EXIT_SUCCESS;
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
            $migration->migration
        );
    }
}
