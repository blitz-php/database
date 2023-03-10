<?php

namespace BlitzPHP\Database\Migration\Definitions;

use BlitzPHP\Database\Migration\Structure;
use BlitzPHP\Utilities\Str;

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
     */
    public function constrained(?string $table = null, string $column = 'id'): ForeignKey
    {
        return $this->references($column)->on($table ?? Str::of($this->name)->beforeLast('_'.$column)->plural());
    }

    /**
     * Specifie quelle colone cet ID etrangere reference danson another table.
     */
    public function references(string $column): ForeignKey
    {
        return $this->structure->foreign($this->name)->references($column);
    }
}
