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

use BlitzPHP\Utilities\Fluent;

/**
 * Definition des colonnes de la struture de migrations
 *
 * @method $this invisible() Specify that the column should be invisible to "SELECT *" (MySQL)
 * @method $this autoIncrement() Set INTEGER columns as auto-increment (primary key)
 * @method $this change()                                                                                                  Change the column
 * @method $this virtualAs(string $expression) Create a virtual generated column (MySQL/PostgreSQL/SQLite)
 * @method $this default(mixed $value)                                                                                     Specify a "default" value for the column
 * @method $this startingValue(int $startingValue) Set the starting value of an auto-incrementing field (MySQL/PostgreSQL)
 * @method $this fulltext(string $indexName = null)                                                                        Add a fulltext index
 * @method $this always(bool $value = true) Used as a modifier for generatedAs() (PostgreSQL)
 * @method $this index(string $indexName = null)                                                                           Add an index
 * @method $this nullable(bool $value = true)                                                                              Allow NULL values to be inserted into the column
 * @method $this persisted() Mark the computed generated column as persistent (SQL Server)
 * @method $this primary()                                                                                                 Add a primary index
 * @method $this spatialIndex(string $indexName = null)                                                                    Add a spatial index
 * @method $this first() Place the column "first" in the table (MySQL)
 * @method $this type(string $type)                                                                                        Specify a type for the column
 * @method $this unique(string $indexName = null)                                                                          Add a unique index
 * @method $this unsigned() Set the INTEGER column as UNSIGNED (MySQL)
 * @method $this useCurrentOnUpdate() Set the TIMESTAMP column to use CURRENT_TIMESTAMP when updating (MySQL)
 * @method $this useCurrent()                                                                                              Set the TIMESTAMP column to use CURRENT_TIMESTAMP as default value
 *
 * @credit <a href="https://laravel.com">Laravel Framework - Illuminate\Database\Schema\ColumnDefinition</a>
 */
class Column extends Fluent
{
}
