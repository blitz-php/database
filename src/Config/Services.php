<?php

/**
 * This file is part of Blitz PHP framework - Databaase.
 *
 * (c) 2023 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Database\Config;

use BlitzPHP\Container\Services as BaseServices;
use BlitzPHP\Database\Builder\BaseBuilder;
use BlitzPHP\Contracts\Database\ConnectionResolverInterface;
use BlitzPHP\Database\Connection\BaseConnection;
use BlitzPHP\Database\Database;

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

        return static::$instances[BaseBuilder::class] = new BaseBuilder(static::database($group));
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
}
