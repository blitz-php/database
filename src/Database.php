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
use BlitzPHP\Database\Connection\BaseConnection;
use BlitzPHP\Database\Creator\BaseCreator;
use InvalidArgumentException;

/**
 * Usine de connexion de base de données
 *
 * Crée et renvoie une instance de la DatabaseConnection appropriée
 */
class Database
{
    /**
     * La seule instance d'utilisation de la classe
     *
     * @var object
     */
    protected static $_instance;

    /**
     * Maintient un tableau des instances de toutes les connexions qui ont été créé.
     *
     * Aide à garder une trace de toutes les connexions ouvertes pour les performances, surveillance, journalisation, etc.
     *
     * @var array<string, ConnectionInterface>
     */
    protected $connections = [];

    /**
     * Vérifie, instancie et renvoie la seule instance de la classe appelée.
     *
     * @return static
     */
    public static function instance()
    {
        if (! (static::$_instance instanceof static)) {
            $params            = func_get_args();
            static::$_instance = new static(...$params);
        }

        return static::$_instance;
    }

    /**
     * Renvoie une instance du pilote prêt à l'emploi.
     *
     * @throws InvalidArgumentException
     *
     * @uses self::load
     */
    public static function connection(array $params = [], string $alias = '', ...$arguments): ConnectionInterface
    {
        return self::instance()->load($params, $alias, ...$arguments);
    }

    /**
     * Analyse les liaisons de connexion et renvoie une instance du pilote prêt à l'emploi.
     *
     * @throws InvalidArgumentException
     */
    public function load(array $params = [], string $alias = '', ...$arguments): ConnectionInterface
    {
        if ($alias === '') {
            throw new InvalidArgumentException('You must supply the parameter: alias.');
        }

        if (! empty($params['dsn']) && strpos($params['dsn'], '://') !== false) {
            $params = $this->parseDSN($params);
        }

        if (empty($params['driver'])) {
            throw new InvalidArgumentException('You have not selected a database type to connect to.');
        }

        $this->connections[$alias] = $this->initDriver($params['driver'], 'Connection', $params, ...$arguments);

        return $this->connections[$alias];
    }

    /**
     * Renvoie une instance Creator adaptee au pilote et prêt à l'emploi.
     *
     * @throws InvalidArgumentException
     *
     * @uses self::loadCreator
     */
    public static function creator(ConnectionInterface $db): BaseCreator
    {
        return self::instance()->loadCreator($db);
    }

    /**
     * Crée une instance Creator pour le type de base de données actuel.
     */
    public function loadCreator(ConnectionInterface $db): BaseCreator
    {
        if (! $db->conn) {
            $db->initialize();
        }

        return $this->initDriver($db->driver, 'Creator', $db);
    }

    /**
     * Crée une instance Utils pour le type de base de données actuel.
     */
    public function loadUtils(ConnectionInterface $db): object
    {
        if (! $db->conn) {
            $db->initialize();
        }

        return $this->initDriver($db->driver, 'Utils', $db);
    }

    /**
     * Analyser la chaîne DSN universelle
     *
     * @throws InvalidArgumentException
     */
    protected function parseDSN(array $params): array
    {
        $dsn = parse_url($params['dsn']);

        if (! $dsn) {
            throw new InvalidArgumentException('Your DSN connection string is invalid.');
        }

        $dsnParams = [
            'dsn'      => '',
            'driver'   => $dsn['scheme'],
            'hostname' => isset($dsn['host']) ? rawurldecode($dsn['host']) : '',
            'port'     => isset($dsn['port']) ? rawurldecode((string) $dsn['port']) : '',
            'username' => isset($dsn['user']) ? rawurldecode($dsn['user']) : '',
            'password' => isset($dsn['pass']) ? rawurldecode($dsn['pass']) : '',
            'database' => isset($dsn['path']) ? rawurldecode(substr($dsn['path'], 1)) : '',
        ];

        if (! empty($dsn['query'])) {
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

    /**
     * Initialiser le pilote de base de données.
     *
     * @param array|object $argument
     * @param mixed        $params
     *
     * @return BaseConnection|BaseCreator|BaseUtils
     */
    protected function initDriver(string $driver, string $class, $params, ...$arguments): object
    {
        $driver = str_ireplace('pdo', '', $driver);
        $driver = str_ireplace(
            ['mysql', 'pgsql', 'sqlite'],
            ['MySQL', 'Postgre', 'SQLite'],
            $driver
        );
        
        $class = $class . '\\' . $driver;

        if (strpos($driver, '\\') === false) {
            $class = "\\BlitzPHP\\Database\\{$class}";
        }

        if (is_array($params)) {
            if (isset($params['host']) && ! isset($params['hostname'])) {
                $params['hostname'] = $params['host'];
            }
        }
        
        return new $class($params, ...$arguments);
    }
}
