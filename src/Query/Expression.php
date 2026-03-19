<?php

/**
 * This file is part of Blitz PHP framework - Database Layer.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Database\Query;

use Stringable;

class Expression implements Stringable
{
    /**
     * @param string $value Chaîne SQL brute
     */
    public function __construct(protected string $value)
    {
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * Créer une nouvelle instance avec une nouvelle chaîne SQL
     */
    public function with(string $newSql): static
    {
        $new        = clone $this;
        $new->value = $newSql;

        return $new;
    }
}
