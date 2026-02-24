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
use BlitzPHP\Contracts\Event\EventManagerInterface;
use BlitzPHP\Database\Builder\BaseBuilder;
use BlitzPHP\Database\Creator\BaseCreator;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Gestionnaire de bases de données
 * 
 * Responsabilités:
 * - Résolution des connexions
 * - Gestion des instances partagées
 * - Création des builders/creators
 * - Parsing DSN
 */
class DatabaseManager implements ConnectionResolverInterface
{
    /**
     * Connexion par défaut
     */
    protected string $defaultConnection = 'default';

    /**
     * Instances de connexions partagées
     *
     * @var array<string, ConnectionInterface>
     */
    protected array $connections = [];

    /**
     * Constructeur
     * 
     * @param ?LoggerInterface $logger Logger
     * @param ?EventManagerInterface $event Event Manager
     */
    public function __construct(protected ?LoggerInterface $logger = null, protected ?EventManagerInterface $event = null)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function connection(?string $name = null): ConnectionInterface
    {
        return $this->connect($name ?: $this->defaultConnection);
    }

    /**
     * {@inheritDoc}
     * 
     * @param array|ConnectionInterface|string|null $group
     */
    public function connect($group = null, bool $shared = true): ConnectionInterface
    {
        // Si on a déjà une connexion, on la retourne directement
        if ($group instanceof ConnectionInterface) {
            return $group;
        }

        [$groupName, $config] = $this->connectionInfo($group);

        if ($shared && isset($this->connections[$groupName])) {
            return $this->connections[$groupName];
        }

        $connection = $this->createConnection($config);

        if ($shared) {
            $this->connections[$groupName] = $connection;
        }

        return $connection;
    }

    /**
     * {@inheritDoc}
     * 
     * @return array{0: string, 1: array}  [nom_du_groupe, configuration]
     */
    public function connectionInfo(array|string|null $group = null): array
    {
        // Si c'est un tableau, c'est une configuration ad-hoc
        if (is_array($group)) {
            $config = $group;
            $groupName = 'custom-' . md5(json_encode($config));

            return [$groupName, $config];
        }

        $config = config('database');

        if (empty($group)) {
            $group = $config['connection'] ?? 'auto';
        }

        if ($group === 'auto') {
            $group = environment();
        }

        // Fallback vers default si le groupe n'existe pas
        if (!isset($config[$group]) && $group !== 'default' && !str_starts_with($group, 'custom-')) {
            $group = 'default';
        }

        if (!isset($config[$group])) {
            throw new InvalidArgumentException("Le groupe de connexion '{$group}' n'est pas configuré.");
        }

        $connectionConfig = $config[$group];

        // Traitement spécial pour SQLite
        if ($connectionConfig['driver'] === 'sqlite') {
            $connectionConfig = $this->resolveSqlitePath($connectionConfig);
        }

        return [$group, $connectionConfig];
    }

    /**
     * Crée un builder pour une connexion
     */
    public function builder(ConnectionInterface $db): BaseBuilder
    {
        return new BaseBuilder($db);
    }

    /**
     * Crée un creator pour une connexion
     */
    public function creator(ConnectionInterface $db): BaseCreator
    {
        $driver = $this->normalizeDriver($db->getDriver());
        $className = "BlitzPHP\\Database\\Creator\\{$driver}";
        
        return new $className($db);
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
     * Retourne toutes les connexions établies
     */
    public function getConnections(): array
    {
        return $this->connections;
    }

    /**
     * Ferme toutes les connexions
     */
    public function closeAll(): void
    {
        foreach ($this->connections as $connection) {
            $connection->close();
        }
        $this->connections = [];
    }

    /**
     * Résout le chemin pour SQLite
     */
    protected function resolveSqlitePath(array $config): array
    {
        if ($config['database'] === ':memory:') {
            return $config;
        }

        if (! str_contains($config['database'], DIRECTORY_SEPARATOR)) {
            $config['database'] = defined('APP_STORAGE_PATH') 
                ? APP_STORAGE_PATH . $config['database']
                : $config['database'];
        }

        return $config;
    }

    /**
     * Crée une instance de connexion
     */
    protected function createConnection(array $config): ConnectionInterface
    {
        // Parser le DSN si nécessaire
        if (!empty($config['dsn']) && str_contains($config['dsn'], '://')) {
            $config = $this->parseDSN($config);
        }

        $driver = $this->normalizeDriver($config['driver'] ?? '');

        $className = "BlitzPHP\\Database\\Connection\\{$driver}";

        return new $className($config, $this->logger, $this->event);
    }

    /**
     * Normalise le nom du driver
     */
    protected function normalizeDriver(string $driver): string
    {
        // Enlever 'pdo' du nom si présent
        $driver = str_ireplace('pdo', '', $driver);
        
        return match (strtolower($driver)) {
            'mysql' => 'MySQL',
            'pgsql', 'postgre', 'postgresql' => 'Postgre',
            'sqlite' => 'SQLite',
            default => throw new InvalidArgumentException("Driver non supporté : {$driver}")
        };
    }

    /**
     * Parse une chaîne DSN universelle
     */
    protected function parseDSN(array $params): array
    {
        $dsn = parse_url($params['dsn']);

        if (!$dsn) {
            throw new InvalidArgumentException('La chaîne DSN est invalide.');
        }

        $dsnParams = [
            'dsn'      => '',
            'driver'   => $dsn['scheme'],
            'hostname' => rawurldecode($dsn['host'] ?? ''),
            'port'     => rawurldecode((string) ($dsn['port'] ?? '')),
            'username' => rawurldecode($dsn['user'] ?? ''),
            'password' => rawurldecode($dsn['pass'] ?? ''),
            'database' => isset($dsn['path']) ? rawurldecode(substr($dsn['path'], 1)) : '',
        ];

        if (!empty($dsn['query'])) {
            parse_str($dsn['query'], $extra);
            foreach ($extra as $key => $val) {
                if (is_string($val) && in_array(strtolower($val), ['true', 'false', 'null'], true)) {
                    $val = $val === 'null' ? null : filter_var($val, FILTER_VALIDATE_BOOLEAN);
                }
                $dsnParams[$key] = $val;
            }
        }

        return array_merge($params, $dsnParams);
    }
}
