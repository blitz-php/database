<?php

/**
 * This file is part of Blitz PHP framework - Database Layer.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Database\Exceptions;

class MigrationException extends DatabaseException
{
    public static function disabledMigrations()
    {
        return new static('Migrations have been loaded but are disabled or setup incorrectly.');
    }
}
