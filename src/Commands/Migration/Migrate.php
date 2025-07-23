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
use BlitzPHP\Database\Commands\Helper;

/**
 * Execute toutes les nouvelles migrations.
 */
class Migrate extends DatabaseCommand
{
    /**
     * @var string Nom
     */
    protected $name = 'migrate';

    /**
     * {@inheritDoc}
     */
    protected $description = 'Recherche et exécute toutes les nouvelles migrations dans la base de données.';

    /**
     * {@inheritDoc}
     */
    protected $options = [
        '-n, --namespace' => 'Défini le namespace de la migration',
        '-g, --group'     => 'Défini le groupe de la base de données',
        '--all'           => 'Défini pour tous les namespaces, ignore l\'option (-n)',
    ];

    /**
     * {@inheritDoc}
     */
    public function execute(array $params)
    {
        $this->colorize(lang('Migrations.latest'), 'yellow');

        $namespace = $this->option('namespace');
        $group     = $this->option('group', 'default');

        $runner = Helper::runner($group);

        $runner->clearMessages();
        $runner->setFiles(Helper::getMigrationFiles($all = $this->option('all') === true, $namespace));
        $runner->setNamespace($all ? null : $namespace);

        if (! $runner->latest($group)) {
            $this->fail(lang('Migrations.generalFault')); // @codeCoverageIgnore
        }

        $messages = $runner->getMessages();

        foreach ($messages as $message) {
            $this->colorize($message['message'], $message['color']);
        }

        $this->newLine()->success(lang('Migrations.migrated'));
    }
}
