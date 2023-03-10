<?php

namespace BlitzPHP\Database\Migration\Definitions;

use BlitzPHP\Utilities\Fluent;

/**
 * @method $this algorithm(string $algorithm) Specifie un algorithme pour l'indexe (MySQL/PostgreSQL)
 * @method $this language(string $language) Specifie un langage pour l'indexe full text (PostgreSQL)
 * @method $this deferrable(bool $value = true) Specifie que l'index unique est deferrable (PostgreSQL)
 * @method $this initiallyImmediate(bool $value = true) Specifie si verifie la contrainte d'indexe unique immediatement ou pas (PostgreSQL)
 * 
 * @credit <a href="https://laravel.com">Laravel Framework - Illuminate\Database\Schema\IndexDefinition</a>
 */
class Index extends Fluent
{
    //
}
