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

use BlitzPHP\Database\Migration\Structure;
use BlitzPHP\Utilities\String\Text;
use BlitzPHP\Utilities\Support\Fluent;

/**
 * @credit <a href="https://laravel.com">Laravel Framework - Illuminate\Database\Schema\ForeignIdDefinition</a>
 */
class ForeignId extends Column
{
    /**
     * L'instance du constructeure de structure.
     */
    protected Structure $structure;

    /**
     * Creation d'une nouvelle definition d'une colone ID etrangere.
     *
     * @return void
     */
    public function __construct(Structure $structure, array $attributes = [])
    {
        parent::__construct($attributes);

        $this->structure = $structure;
    }

    /**
     * Cree une contrainte de cle etrangere sur cette colonne "id" conventionellement a la table referencee.
     *
     * @return ForeignKey
     */
    public function constrained(?string $table = null, string $column = 'id'): Fluent
    {
        return $this->references($column)->on($table ?? Text::of($this->name)->beforeLast('_' . $column)->plural());
    }

    /**
     * Specifie quelle colone cet ID etrangere reference danson another table.
     *
     * @return ForeignKey
     */
    public function references(string $column): Fluent
    {
        return $this->structure->foreign($this->name)->references($column);
    }
}
