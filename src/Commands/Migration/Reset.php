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
 * Réinitialise toutes les migrations.
 */
class Reset extends DatabaseCommand
{
    /**
     * {@inheritDoc}
     */
    protected string $name = 'migrate:reset';

    /**
     * {@inheritDoc}
     */
    protected string $description = 'Annule toutes les migrations.';

    /**
     * {@inheritDoc}
     */
    protected array $options = [
        '-g, --group' => 'Groupe de base de données à utiliser',
        '-f, --force' => 'Forcer l\'exécution en production',
    ];

    /**
     * {@inheritDoc}
     */
    public function handle()
    {
        if (on_prod() && !$this->option('force')) {
            if (!$this->confirm('Êtes-vous sûr de vouloir réinitialiser TOUTES les migrations en production ?')) {
                return EXIT_SUCCESS;
            }
        }

        $this->eol()->info('Réinitialisation de toutes les migrations...');

        $group = $this->option('group', 'default');
        
        $result = $this->call('migrate:rollback', [
            '--group' => $group,
            '--all'   => true,
            '--force' => $this->option('force'),
        ]);

        return $result;
    }
}
