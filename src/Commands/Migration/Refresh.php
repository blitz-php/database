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

use BlitzPHP\Database\Commands\DatabaseCommand;

/**
 * Réinitialise et réexécute toutes les migrations.
 */
class Refresh extends DatabaseCommand
{
    /**
     * {@inheritDoc}
     */
    protected string $name = 'migrate:refresh';

    /**
     * {@inheritDoc}
     */
    protected string $description = 'Annule toutes les migrations puis les réexécute.';

    /**
     * {@inheritDoc}
     */
    protected array $options = [
        '-n, --namespace' => 'Défini le namespace de la migration',
        '--all'           => 'Défini pour tous les namespaces, ignore l\'option (-n)',
        '-g, --group'     => 'Groupe de base de données à utiliser',
        '-f, --force'     => 'Forcer l\'exécution en production',
        '--seed'          => 'Exécuter les seeders après le refresh',
    ];

    /**
     * {@inheritDoc}
     */
    public function handle()
    {
        if (on_prod() && ! $this->option('force')) {
            if (! $this->confirm('Êtes-vous sûr de vouloir réinitialiser toutes les migrations en production ?')) {
                return EXIT_SUCCESS;
            }
        }

        $this->eol()->info('Réinitialisation et réexécution des migrations...');

        $seed    = $this->option('seed') === true;
        $options = [
            '--group'     => $group = $this->option('group', config('database.connection', 'default')),
            '--all'       => $this->option('all')   === true,
            '--force'     => $this->option('force') === true,
            '--namespace' => $this->option('namespace'),
        ];

        $this->newLine()->comment('Étape 1/2: Annulation de toutes les migrations');

        $rollbackResult = $this->call('migrate:rollback', options: $options);

        if ($rollbackResult !== EXIT_SUCCESS) {
            $this->error('Échec de l\'annulation des migrations.');

            return $rollbackResult;
        }

        $this->newLine()->comment('Étape 2/2: Réexécution des migrations');

        $migrateResult = $this->call('migrate', options: $options);

        if ($migrateResult !== EXIT_SUCCESS) {
            $this->error('Échec de l\'exécution des migrations.');

            return $migrateResult;
        }

        if ($seed) {
            $this->newLine()->info('Exécution des seeders...');
            $this->call('db:seed', options: [
                '--group' => $group,
            ]);
        }

        $this->newLine()->success('Refresh terminé avec succès !');

        return EXIT_SUCCESS;
    }
}
