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
 * Execute toutes les nouvelles migrations.
 */
class Refresh extends DatabaseCommand
{
    /**
     * @var string Nom
     */
    protected $name = 'migrate:refresh';

    /**
     * {@inheritDoc}
     */
    protected $description = 'Effectue une restauration suivie d\'une migration pour actualiser l\'état actuel de la base de données.';

    /**
     * {@inheritDoc}
     */
    protected $options = [
        '-n, --namespace' => 'Défini le namespace de la migration',
        '-g, --group'     => 'Défini le groupe de la base de données',
        '--all'           => 'Défini pour tous les namespaces, ignore l\'option (-n)',
        '-f, --force'     => 'Forcer la commande - cette option vous permet de contourner la question de confirmation lors de l\'exécution de cette commande dans un environnement de production',
    ];

    /**
     * {@inheritDoc}
     */
    public function execute(array $params)
    {
        $params['batch'] = 0;

        if (on_prod()) {
            // @codeCoverageIgnoreStart
            $force = $this->option('force');

            if (! $force && ! $this->confirm(lang('Migrations.refreshConfirm'))) {
                return;
            }

            $params['force'] = null;
            // @codeCoverageIgnoreEnd
        }

        $this->call('migrate:rollback', [], $params);
        $this->newLine();
        $this->call('migrate', [], $params);
    }
}
