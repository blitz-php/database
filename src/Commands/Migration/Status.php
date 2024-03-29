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
class Status extends DatabaseCommand
{
    /**
     * @var string Nom
     */
    protected $name = 'migrate:status';

    /**
     * {@inheritDoc}
     */
    protected $description = 'Affiche une liste de toutes les migrations et indique si elles ont été exécutées ou non.';

    /**
     * {@inheritDoc}
     */
    protected $options = [
        '-g, --group' => 'Défini le groupe de la base de données',
    ];

    /**
     * Namespaces à ignorer quand on regarde les migrations.
     *
     * @var string[]
     */
    protected array $ignoredNamespaces = [
        'BlitzPHP',
        'Config',
        'Kint',
        'Laminas\ZendFrameworkBridge',
        'Laminas\Escaper',
        'Psr\Log',
    ];

    /**
     * {@inheritDoc}
     */
    public function execute(array $params)
    {
        $group = $this->option('group');

        $runner = Helper::runner($group);

        // Collection des statuts de migrations
        $status = [];

        foreach (Helper::getMigrationFiles(true) as $namespace => $files) {
            if (! on_test()) {
                // Rendre Tests\\Support détectable pour les tests
                $this->ignoredNamespaces[] = 'Tests\Support'; // @codeCoverageIgnore
            }

            if (in_array($namespace, $this->ignoredNamespaces, true)) {
                continue;
            }

            if (APP_NAMESPACE !== 'App' && $namespace === 'App') {
                continue; // @codeCoverageIgnore
            }

            $migrations = $runner->findNamespaceMigrations($namespace, $files);

            if (empty($migrations)) {
                continue;
            }

            $history = $runner->getHistory();
            ksort($migrations);

            foreach ($migrations as $uid => $migration) {
                $migrations[$uid]->name = mb_substr($migration->name, mb_strpos($migration->name, $uid . '_'));

                $date  = '---';
                $group = '---';
                $batch = '---';

                foreach ($history as $row) {
                    // @codeCoverageIgnoreStart
                    if ($runner->getObjectUid($row) !== $migration->uid) {
                        continue;
                    }

                    $date  = date('Y-m-d H:i:s', $row->time);
                    $group = $row->group;
                    $batch = $row->batch;
                    // @codeCoverageIgnoreEnd
                }

                $status[] = [
                    'namespace' => $namespace,
                    'version'   => $migration->version,
                    'nom'       => $migration->name,
                    'groupe'    => $group,
                    'migré le'  => $date,
                    'batch'     => $batch,
                ];
            }
        }

        if (! $status) {
            // @codeCoverageIgnoreStart
            $this->error(lang('Migrations.noneFound'))->newLine();

            return;
            // @codeCoverageIgnoreEnd
        }

        $this->table($status, ['head' => 'boldYellow']);
    }
}
