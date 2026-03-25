<?php

/**
 * This file is part of Blitz PHP framework - Database Layer.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Database\Commands;

use BlitzPHP\Autoloader\Autoloader;
use BlitzPHP\Cli\Console\Command;
use BlitzPHP\Contracts\Autoloader\LocatorInterface;
use BlitzPHP\Contracts\Container\ContainerInterface;
use BlitzPHP\Contracts\Database\ConnectionResolverInterface;
use BlitzPHP\Database\Connection\BaseConnection;
use BlitzPHP\Database\DatabaseManager;
use BlitzPHP\Database\Migration\Runner;

abstract class DatabaseCommand extends Command
{
    /**
     * {@inheritDoc}
     */
    protected string $group = 'Base de données';

    /**
     * @deprecated
     */
    protected ConnectionResolverInterface $resolver;

    protected DatabaseManager $dbManager;

    public function __construct(protected ContainerInterface $container)
    {
        $this->dbManager = $this->container->get(DatabaseManager::class);
        $this->resolver  = $this->dbManager;
    }

    protected function db(array|string|null $group = null, bool $shared = true): BaseConnection
    {
        return $this->dbManager->connect($group, $shared);
    }

    /**
     * Recupere les informations a utiliser pour la connexion a la base de données
     *
     * @return array [group, configuration]
     */
    public function connectionInfo(array|string|null $group = null): array
    {
        return $this->dbManager->connectionInfo($group);
    }

    /**
     * Recupere une instance de l'executeur de migration
     */
    public function runner(string $namespace, ?string $group = null): Runner
    {
        $namespaces = match ($namespace) {
            'ALL'   => array_keys($this->container->get(Autoloader::class)->getNamespace()),
            default => [$namespace],
        };

        $locator = $this->container->get(LocatorInterface::class);
        $files   = [];

        foreach ($namespaces as $namespace) {
            $files[$namespace] = $locator->listNamespaceFiles($namespace, '/Database/Migrations/');
        }

        return new Runner(
            $this->dbManager,
            $group,
            $files,
            config('migrations'),
        );
    }
}
