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

use BlitzPHP\Utilities\Support\Fluent;

/**
 * @method $this references(array|string $columns) Specifie la/les colone(s)                                                                de reference
 * @method $this deferrable(bool $value = true) Specifie que l'index unique est deferrable (PostgreSQL)
 * @method $this on(string $table)                                                                                                          Specifie la table de reference
 * @method $this onDelete(string $action)                                                                                                   Ajoute une action ON DELETE
 * @method $this onUpdate(string $action)                                                                                                   Ajoute une action ON UPDATE
 * @method $this initiallyImmediate(bool $value = true) Specifie si verifie la contrainte d'indexe unique immediatement ou pas (PostgreSQL)
 *
 * @credit <a href="https://laravel.com">Laravel Framework - Illuminate\Database\Schema\ForeignKeyDefinition</a>
 */
class ForeignKey extends Fluent
{
    /**
     * Indique que les updates doivent etre en cascade.
     */
    public function cascadeOnUpdate(): self
    {
        return $this->onUpdate('cascade');
    }

    /**
     * Indique que les updates doivent etre restreint.
     */
    public function restrictOnUpdate(): self
    {
        return $this->onUpdate('restrict');
    }

    /**
     * Indique que les deletes doivent etre en cascade.
     */
    public function cascadeOnDelete(): self
    {
        return $this->onDelete('cascade');
    }

    /**
     * Indique que les deletes doivent etre restreint.
     */
    public function restrictOnDelete(): self
    {
        return $this->onDelete('restrict');
    }

    /**
     * Indique que les deletes doivent mettre la valeur de la cle etrangere a null.
     */
    public function nullOnDelete(): self
    {
        return $this->onDelete('set null');
    }

    /**
     * Indique que les deletes doivent avoir "no action".
     */
    public function noActionOnDelete()
    {
        return $this->onDelete('no action');
    }
}
