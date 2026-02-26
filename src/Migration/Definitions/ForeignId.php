<?php

/**
 * This file is part of Blitz PHP framework - Database Layer.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Database\Migration\Definitions;

use BlitzPHP\Database\Migration\Builder;
use BlitzPHP\Utilities\String\Text;

/**
 * @credit <a href="https://laravel.com">Laravel Framework - Illuminate\Database\Schema\ForeignIdDefinition</a>
 */
class ForeignId extends Column
{
    /**
     * Creation d'une nouvelle definition d'une colone ID etrangere.
     *
     * @param Builder $builder L'instance du constructeure de structure.
     */
    public function __construct(protected Builder $builder, array $attributes = [])
    {
        parent::__construct($attributes);
    }

    /**
     * Cree une contrainte de cle etrangere sur cette colonne "id" conventionellement a la table referencee.
     */
    public function constrained(?string $table = null, string $column = 'id', ?string $indexName = null): ForeignKey
    {
        return $this->references($column, $indexName)->on($table ?? Text::of($this->name)->beforeLast('_' . $column)->plural());
    }

    /**
     * Specifie quelle colone cet ID etrangere reference danson another table.
     */
    public function references(string $column, ?string $indexName = null): ForeignKey
    {
        return $this->builder->foreign($this->name, $indexName)->references($column);
    }
}
