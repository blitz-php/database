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
 * @method $this deferrable(bool $value = true) Specifie que l'index unique est deferrable (PostgreSQL)
 * @method $this algorithm(string $algorithm) Specifie un algorithme pour l'indexe (MySQL/PostgreSQL)
 * @method $this initiallyImmediate(bool $value = true) Specifie si verifie la contrainte d'indexe unique immediatement ou pas (PostgreSQL)
 * @method $this language(string $language) Specifie un langage pour l'indexe full text (PostgreSQL)
 *
 * @credit <a href="https://laravel.com">Laravel Framework - Illuminate\Database\Schema\IndexDefinition</a>
 */
class Index extends Fluent
{
}
