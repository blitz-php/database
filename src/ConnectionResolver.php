<?php

/**
 * This file is part of Blitz PHP framework - Database Layer.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Database;

use BlitzPHP\Contracts\Database\ConnectionInterface;
use BlitzPHP\Contracts\Database\ConnectionResolverInterface;
use BlitzPHP\Database\Config\Services;
use BlitzPHP\Database\Creator\BaseCreator;
use InvalidArgumentException;

class ConnectionResolver implements ConnectionResolverInterface
{
    protected string $defaultConnection = 'default';

    /**
     * Cache pour les instances de toutes les connections
     * qui ont été requetées en tant que instance partagées
     *
     * @var array<string, ConnectionInterface>
     */
    protected static $instances = [];

    /**
     * L'instance principale utilisée pour gérer toutes les ouvertures à la base de données.
     *
     * @var Database|null
     */
    protected static $factory;

    /**
     * {@inheritDoc}
     */
    public function connection(?string $name = null): ConnectionInterface
    {
        return $this->connect($name ?: $this->defaultConnection);
    }

    /**
     * {@inheritDoc}
     */
    public function connect($group = null, bool $shared = true): ConnectionInterface
    {
        // Si on a deja passer une connection, pas la peine de continuer
        if ($group instanceof ConnectionInterface) {
            return $group;
        }

        [$group, $config] = $this->connectionInfo($group);

        if ($shared && isset(static::$instances[$group])) {
            return static::$instances[$group];
        }

        static::ensureFactory();

        $connection = static::$factory->load(
            $config,
            $group,
            Services::logger(),
            Services::event()
        );

        static::$instances[$group] = &$connection;

        return $connection;
    }

    /**
     * {@inheritDoc}
     */
    public function connectionInfo(null|array|string $group = null): array
    {
        if (is_array($group)) {
            $config = $group;
            $group  = 'custom-' . md5(json_encode($config));
        }

        $config ??= config('database');

        if (empty($group)) {
            $group = $config['connection'] ?? 'auto';
        }
        if ($group === 'auto') {
            $group = on_test() ? 'test' : (on_prod() ? 'production' : 'development');
        }

        if (! isset($config[$group]) && ! str_contains($group, 'custom-')) {
            $group = 'default';
        }

        if (is_string($group) && ! isset($config[$group]) && ! str_starts_with($group, 'custom-')) {
            throw new InvalidArgumentException($group . ' is not a valid database connection group.');
        }

        if (str_contains($group, 'custom-')) {
            $config = [$group => $config];
        }

        $config = $config[$group];

        if (str_contains($config['driver'], 'sqlite') && $config['database'] !== ':memory:' && ! str_contains($config['database'], DIRECTORY_SEPARATOR)) {
            $config['database'] = APP_STORAGE_PATH . $config['database'];
        }

        return [$group, $config];
    }

    /**
     * {@inheritDoc}
     */
    public function getDefaultConnection(): string
    {
        return $this->defaultConnection;
    }

    /**
     * {@inheritDoc}
     */
    public function setDefaultConnection(string $name): void
    {
        $this->defaultConnection = $name;
    }

    /**
     * Renvoie un tableau contenant toute les connxions deja etablies.
     */
    public static function getConnections(): array
    {
        return static::$instances;
    }

    /**
     * Charge et retourne une instance du Creator specifique au groupe de la base de donnees
     * et charge le groupe s'il n'est pas encore chargé.
     *
     * @param array|ConnectionInterface|string|null $group
     */
    public function creator($group = null, bool $shared = true): BaseCreator
    {
        $db = $this->connect($group, $shared);

        return static::$factory->loadCreator($db);
    }

    /**
     * Retourne une nouvelle de la classe Database Utilities.
     *
     * @param array|ConnectionInterface|string|null $group
     */
    public function utils($group = null): BaseUtils
    {
        $db = $this->connect($group);

        return static::$factory->loadUtils($db);
    }

    /**
     * S'assure que le gestionnaire de la base de données est chargé et prêt à être utiliser.
     */
    protected static function ensureFactory()
    {
        if (static::$factory instanceof Database) {
            return;
        }

        static::$factory = new Database();
    }
}
