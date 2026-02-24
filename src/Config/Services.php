<?php

/**
 * This file is part of Blitz PHP framework - Database Layer.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Database\Config;

use BlitzPHP\Container\Services as BaseServices;
use BlitzPHP\Database\Builder\BaseBuilder;
use BlitzPHP\Database\Connection\BaseConnection;
use BlitzPHP\Database\DatabaseManager;
use Dimtrovich\DbDumper\Exporter;
use Dimtrovich\DbDumper\Importer;
use InvalidArgumentException;

/**
 * Services de base de données
 */
class Services extends BaseServices
{
    /**
     * Instance du gestionnaire de base de données
     */
    protected static ?DatabaseManager $manager = null;

    /**
     * Récupère le gestionnaire de base de données
     */
    protected static function manager(): DatabaseManager
    {
        if (static::$manager === null) {
            static::$manager = new DatabaseManager(static::logger(), static::event());
        }

        return static::$manager;
    }

    /**
     * Récupère une connexion à la base de données
     */
    public static function database(?string $group = null, bool $shared = true): BaseConnection
    {
        $connection = static::manager()->connect($group, $shared);

        if (!$connection instanceof BaseConnection) {
            throw new InvalidArgumentException('La connexion retournée n\'est pas une instance de BaseConnection');
        }

        return $connection;
    }

    /**
     * Récupère un query builder
     */
    public static function builder(?string $group = null, bool $shared = true): BaseBuilder
    {
        $key = 'builder_' . ($group ?? 'default');

        if ($shared && isset(static::$instances[$key])) {
            return static::$instances[$key];
        }

        $builder = static::manager()->builder(static::database($group, $shared));

        if ($shared) {
            static::$instances[$key] = $builder;
        }

        return $builder;
    }

    /**
     * Récupère un exportateur de base de données
     */
    public static function dbExporter(?BaseConnection $db = null, array $config = [], bool $shared = true): Exporter
    {
        if ($shared) {
            return static::sharedInstance('dbExporter', $db, $config);
        }

        $db ??= static::database();
        $config = $config ?: (array) config('dump', []);

        // Initialiser la connexion si nécessaire
        $db->initialize();

        $exporter = new Exporter($db->getDatabase(), $db->getConnection(), $config);

        if ($shared) {
            static::$instances[Exporter::class] = $exporter;
        }

        return $exporter;
    }

    /**
     * Récupère un importateur de base de données
     */
    public static function dbImporter(?BaseConnection $db = null, array $config = [], bool $shared = true): Importer
    {
        if ($shared) {
            return static::sharedInstance('dbImporter', $db, $config);
        }

        $db ??= static::database();
        $config = $config ?: (array) config('dump', []);

        // Initialiser la connexion si nécessaire
        $db->initialize();

        $importer = new Importer($db->getDatabase(), $db->getConnection(), $config);

        if ($shared) {
            static::$instances[Importer::class] = $importer;
        }

        return $importer;
    }
}
