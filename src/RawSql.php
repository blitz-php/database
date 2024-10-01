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

use Stringable;

class RawSql implements Stringable
{
    /**
     * @param string $sql Chaîne SQL brute
     */
    public function __construct(private string $sql)
    {
    }

    public function __toString(): string
    {
        return $this->sql;
    }

    /**
     * Créer une nouvelle instance avec une nouvelle chaîne SQL
     */
    public function with(string $newSql): self
    {
        $new      = clone $this;
        $new->sql = $newSql;

        return $new;
    }
}
