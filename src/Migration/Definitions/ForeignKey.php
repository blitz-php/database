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
 * @method $this initiallyImmediate(bool $value = true) Définit le moment par défaut pour vérifier la contrainte (PostgreSQL)
 * @method $this deferrable(bool $value = true) Définit la clé étrangère comme différable (PostgreSQL)
 * @method $this on(string $table)                                                                                               Spécifie la table référencée
 * @method $this onDelete(string $action)                                                                                        Ajoute une action ON DELETE
 * @method $this onUpdate(string $action)                                                                                        Ajoute une action ON UPDATE
 * @method $this references(array|string $columns)                                                                               Spécifie la ou les colonnes référencées
 *
 * @credit <a href="https://laravel.com">Laravel Framework - Illuminate\Database\Schema\ForeignKeyDefinition</a>
 */
class ForeignKey extends Fluent
{
    /**
     * Indique que les mises à jour doivent être en cascade.
     */
    public function cascadeOnUpdate(): static
    {
        return $this->onUpdate('cascade');
    }

    /**
     * Indique que les mises à jour doivent être restreintes.
     */
    public function restrictOnUpdate(): static
    {
        return $this->onUpdate('restrict');
    }

    /**
     * Indique que les mises à jour doivent être sans action.
     */
    public function noActionOnUpdate(): static
    {
        return $this->onUpdate('no action');
    }

    /**
     * Indique que les suppressions doivent être en cascade.
     */
    public function cascadeOnDelete(): static
    {
        return $this->onDelete('cascade');
    }

    /**
     * Indique que les suppressions doivent être restreintes.
     */
    public function restrictOnDelete(): static
    {
        return $this->onDelete('restrict');
    }

    /**
     * Indique que les suppressions doivent définir la valeur de la clé étrangère à null.
     */
    public function nullOnDelete(): static
    {
        return $this->onDelete('set null');
    }

    /**
     * Indique que les suppressions doivent être sans action.
     */
    public function noActionOnDelete(): static
    {
        return $this->onDelete('no action');
    }
}
