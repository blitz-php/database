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
use BlitzPHP\Contracts\Database\ConnectionResolverInterface;
use BlitzPHP\Database\Builder\BaseBuilder;
use BlitzPHP\Database\Connection\BaseConnection;
use BlitzPHP\Database\Database;
use Dimtrovich\DbDumper\Exceptions\Exception as DumperException;
use Dimtrovich\DbDumper\Exporter;
use Dimtrovich\DbDumper\Importer;

class Services extends BaseServices
{
    /**
     * Query Builder
     */
    public static function builder(?string $group = null, bool $shared = true): BaseBuilder
    {
        if (true === $shared && isset(static::$instances[BaseBuilder::class])) {
            return static::$instances[BaseBuilder::class];
        }

        return static::$instances[BaseBuilder::class] = Database::builder(static::database($group));
    }

    /**
     * Connexion a la base de données
     */
    public static function database(?string $group = null, bool $shared = true): BaseConnection
    {
        if (true === $shared && isset(static::$instances[Database::class])) {
            return static::$instances[Database::class];
        }

        return static::$instances[Database::class] = static::container()->get(ConnectionResolverInterface::class)->connect($group);
    }

    /**
     * Systeme d'exportation de la base de donnees
     */
    public static function dbExporter(?BaseConnection $db = null, array $config = [], bool $shared = true): Exporter
    {
        if (true === $shared && isset(static::$instances[Exporter::class])) {
            return static::$instances[Exporter::class];
        }

        $db ??= self::database();
        $config ??= config('dump', []);

        if (! $db->conn) {
            $db->initialize();
        }

        if (! $db->isPdo()) {
            throw new DumperException('Impossible de sauvegarder la base de données. Vous devez utiliser un pilote PDO', DumperException::PDO_EXCEPTION);
        }

        return static::$instances[Exporter::class] = new Exporter($db->database, $db->conn, $config);
    }

    /**
     * Systeme d'importation de la base de donnees
     */
    public static function dbImporter(?BaseConnection $db = null, array $config = [], bool $shared = true): Importer
    {
        if (true === $shared && isset(static::$instances[Importer::class])) {
            return static::$instances[Importer::class];
        }

        $db ??= self::database();
        $config ??= config('dump', []);

        if (! $db->conn) {
            $db->initialize();
        }

        if (! $db->isPdo()) {
            throw new DumperException('Impossible de restaurer la base de données. Vous devez utiliser un pilote PDO', DumperException::PDO_EXCEPTION);
        }

        return static::$instances[Importer::class] = new Importer($db->database, $db->conn, $config);
    }
}
