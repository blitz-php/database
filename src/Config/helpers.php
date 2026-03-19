<?php

use BlitzPHP\Contracts\Database\ConnectionInterface;
use BlitzPHP\Database\Config\Services;
use BlitzPHP\Database\Connection\BaseConnection;
use BlitzPHP\Loader\Load;

/**
 * This file is part of Blitz PHP framework - Schild.
 *
 * (c) 2023 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */



if (! function_exists('model')) {
    /**
     * Simple maniere d'obtenir un modele.
     *
     * @template TModel
     *
     * @param class-string<TModel>|list<class-string<TModel>> $name
     *
     * @return ($name is string ? TModel : list<TModel>)
     */
    function model(array|string $name, ?ConnectionInterface &$conn = null)
    {
        return Load::model($name, $conn);
    }
}


if (! function_exists('db')) {
    /**
     * Grabs a database connection and returns it to the user.
     *
     * This is a convenience wrapper for \BlitzPHP\Database\Config\Services::database()
     * and supports the same parameters. Namely:
     *
     * When passing in $db, you may pass any of the following to connect:
     * - group name
     * - existing connection instance
     * - array of database configuration values
     *
     * If $shared === false then a new connection instance will be provided,
     * otherwise it will all calls will return the same instance.
     *
     * @param array{
     *     dsn?: string,
     *     driver?: 'mysql'|'postgre'|'sqlite',
     *     port?: int,
     *     hostname?: string,
     *     username?: string,
     *     password?: string,
     *     database?: string,
     *     prefix?: string,
     *     debug?: bool,
     *     charset?: string,
     *     collation?: string,
     * }|ConnectionInterface|string|null $db
     *
     * @return BaseConnection
     */
    function db($db = null, bool $shared = true): ConnectionInterface
    {
        return Services::dbManager()->connect($db, $shared);
    }
}
