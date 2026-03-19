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
 * Definition des colonnes de la struture de migrations
 * /**
 * @method $this invisible() Spécifie que la colonne doit être invisible pour "SELECT *" (MySQL)
 * @method $this startingValue(int $startingValue) Définit la valeur de départ d'un champ auto-incrémenté (MySQL/PostgreSQL)
 * @method $this autoIncrement() Définit les colonnes INTEGER comme auto-incrémentées (clé primaire)
 * @method $this change()                                                                                                                             Modifie la colonne
 * @method $this comment(string $comment) Ajoute un commentaire à la colonne (MySQL/PostgreSQL)
 * @method $this default(mixed $value)                                                                                                                Spécifie une valeur "par défaut" pour la colonne
 * @method $this fulltext(string $indexName = null)                                                                                                   Ajoute un index FULLTEXT
 * @method $this always(bool $value = true) Utilisé comme modificateur pour generatedAs() (PostgreSQL)
 * @method $this index(string $indexName = null)                                                                                                      Ajoute un index
 * @method $this useCurrentOnUpdate() Définit la colonne TIMESTAMP pour utiliser CURRENT_TIMESTAMP lors de la mise à jour (MySQL)
 * @method $this nullable(bool $value = true)                                                                                                         Autorise l'insertion de valeurs NULL dans la colonne
 * @method $this persisted() Marque la colonne générée calculée comme persistante (SQL Server)
 * @method $this primary()                                                                                                                            Ajoute un index primaire
 * @method $this unsigned() Définit la colonne INTEGER comme NON SIGNÉE (MySQL)
 * @method $this spatialIndex(string $indexName = null)                                                                                               Ajoute un index spatial
 * @method $this generatedAs(string|\Illuminate\Database\Query\Expression $expression = null) Crée une colonne d'identité conforme SQL (PostgreSQL)
 * @method $this storedAs(string $expression) Crée une colonne générée stockée (MySQL/PostgreSQL/SQLite)
 * @method $this first() Place la colonne "en premier" dans la table (MySQL)
 * @method $this type(string $type)                                                                                                                   Spécifie un type pour la colonne
 * @method $this unique(string $indexName = null)                                                                                                     Ajoute un index unique
 * @method $this useCurrent()                                                                                                                         Définit la colonne TIMESTAMP pour utiliser CURRENT_TIMESTAMP comme valeur par défaut
 * @method $this virtualAs(string $expression) Crée une colonne générée virtuelle (MySQL/PostgreSQL/SQLite)
 *
 * @credit <a href="https://laravel.com">Laravel Framework - Illuminate\Database\Schema\ColumnDefinition</a>
 */
class Column extends Fluent
{
    protected array $_indexes = ['primary', 'unique', 'index', 'fulltext', 'spatialIndex'];

    /**
     * Vérifie si la colonne a des index fluides
     */
    public function hasFluentIndexes(): bool
    {
        foreach ($this->_indexes as $index) {
            if (isset($this->attributes[$index])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Récupère tous les index fluides
     */
    public function getFluentIndexes(): array
    {
        $indexes = [];

        foreach ($this->_indexes as $index) {
            if (isset($this->attributes[$index])) {
                $indexes[$index] = $this->attributes[$index];
            }
        }

        return $indexes;
    }
}
