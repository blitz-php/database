<?php

/**
 * This file is part of Blitz PHP framework - Database Layer.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Database\Builder\Concerns;

use BadMethodCallException;
use BlitzPHP\Contracts\Database\ConnectionInterface;
use BlitzPHP\Traits\Macroable;

/**
 * Gère les appels aux méthodes alias via un système de proxy
 *
 * @method ConnectionInterface getConnection() Alias de db() - Récupère la connexion à la base de données
 * @method static                latest(\Closure|\BlitzPHP\Database\Builder\BaseBuilder|\BlitzPHP\Database\Query\Expression|string $column = 'created_at') Alias de orderBy() avec direction DESC - Ajoute un tri par date décroissante
 * @method static                oldest(\Closure|\BlitzPHP\Database\Builder\BaseBuilder|\BlitzPHP\Database\Query\Expression|string $column = 'created_at') Alias de orderBy() avec direction ASC - Ajoute un tri par date croissante
 * 
 * // Récupération de résultats
 * @method mixed               one(int|string $type = \PDO::FETCH_OBJ) Alias de first() - Récupère le premier résultat
 * @method array               all(int|string $type = \PDO::FETCH_OBJ) Alias de result() - Récupère tous les résultats sous forme de tableau
 * @method \BlitzPHP\Utilities\Iterable\Collection get(int|string $type = \PDO::FETCH_OBJ) Alias de collect() - Récupère tous les résultats sous forme de Collection
 * 
 * // Commandes SQL
 * @method static                order(array|string $columns, string $direction = 'ASC') Alias de orderBy() - Ajoute une clause ORDER BY
 * @method static                group(array|string $columns) Alias de groupBy() - Ajoute une clause GROUP BY
 * @method static                addSelect(array|string $columns) Alias de select() - Ajoute des colonnes à la sélection
 * @method static                selectSub(\BlitzPHP\Contracts\Database\BuilderInterface $subquery, string $as) Alias de selectSubquery() - Ajoute une sous-requête dans la sélection
 * @method static                skip(int $offset) Alias de offset() - Ajoute une clause OFFSET
 * @method static                take(int $limit) Alias de limit() - Ajoute une clause LIMIT
 * 
 * // Conditions WHERE (basiques)
 * @method static                notWhere(array|string $column, mixed $operator = null, mixed $value = null, string $boolean = 'and') Alias de whereNot() - Ajoute une clause WHERE NOT
 * @method static                orNotWhere(array|string $column, mixed $operator = null, mixed $value = null) Alias de orWhereNot() - Ajoute une clause WHERE NOT avec OR
 * 
 * // Conditions WHERE IN
 * @method static                in(string $column, array|\Closure $values, string $boolean = 'and', bool $not = false) Alias de whereIn() - Ajoute une clause WHERE IN
 * @method static                notIn(string $column, array|\Closure $values, string $boolean = 'and') Alias de whereNotIn() - Ajoute une clause WHERE NOT IN
 * @method static                orIn(string $column, array|\Closure $values) Alias de orWhereIn() - Ajoute une clause WHERE IN avec OR
 * @method static                orNotIn(string $column, array|\Closure $values) Alias de orWhereNotIn() - Ajoute une clause WHERE NOT IN avec OR
 * 
 * // Conditions WHERE LIKE
 * @method static                like(string|array $column, string $value = '', string $side = 'both', string $boolean = 'and', bool $not = false, bool $caseSensitive = false) Alias de whereLike() - Ajoute une clause WHERE LIKE
 * @method static                notLike(string|array $column, string $value = '', string $side = 'both', string $boolean = 'and', bool $caseSensitive = false) Alias de whereNotLike() - Ajoute une clause WHERE NOT LIKE
 * @method static                orLike(string|array $column, string $value = '', string $side = 'both', bool $caseSensitive = false) Alias de orWhereLike() - Ajoute une clause WHERE LIKE avec OR
 * @method static                orNotLike(string|array $column, string $value = '', string $side = 'both', bool $caseSensitive = false) Alias de orWhereNotLike() - Ajoute une clause WHERE NOT LIKE avec OR
 * 
 * // Conditions WHERE BETWEEN
 * @method static                between(string $column, mixed $value1, mixed $value2, string $boolean = 'and', bool $not = false) Alias de whereBetween() - Ajoute une clause WHERE BETWEEN
 * @method static                notBetween(string $column, mixed $value1, mixed $value2, string $boolean = 'and') Alias de whereNotBetween() - Ajoute une clause WHERE NOT BETWEEN
 * @method static                orBetween(string $column, mixed $value1, mixed $value2) Alias de orWhereBetween() - Ajoute une clause WHERE BETWEEN avec OR
 * @method static                orNotBetween(string $column, mixed $value1, mixed $value2) Alias de orWhereNotBetween() - Ajoute une clause WHERE NOT BETWEEN avec OR
 * 
 * // Conditions WHERE COLUMN
 * @method static                notWhereColumn(array|string $first, string $operator = null, string $second = null, string $boolean = 'and') Alias de whereNotColumn() - Ajoute une clause WHERE avec comparaison de colonnes inversée
 * @method static                orNotWhereColumn(array|string $first, string $operator = null, string $second = null) Alias de orWhereNotColumn() - Ajoute une clause WHERE avec comparaison de colonnes inversée avec OR
 * 
 * // Tri
 * @method static                sortAsc(string|array $column) Alias de orderBy() avec direction ASC - Ajoute un tri croissant
 * @method static                sortDesc(string|array $column) Alias de orderBy() avec direction DESC - Ajoute un tri décroissant
 * @method static                sortRand(?int $digit = null) Alias de rand() - Ajoute un tri aléatoire
 * @method static                inRandomOrder(?int $digit = null) Alias de rand() - Ajoute un tri aléatoire
 * @method static                reorderDesc(?string $column = null) Alias de reorder() avec direction DESC - Réinitialise et ajoute un tri décroissant
 * 
 * // Insertions
 * @method int|string          bulkInsert(array $data, bool $ignore = false, int $chunkSize = 100) Alias de bulkInsert() - Insertion multiple
 * @method int|string          bulkInsertIgnore(array $data, int $chunkSize = 100) Alias de bulkInsertIgnore() - Insertion multiple avec IGNORE
 *
 * @mixin \BlitzPHP\Database\Builder\BaseBuilder
 */
trait ProxyMethods
{
    use Macroable { __call as macroCall; }

    /**
     * Mapping des méthodes alias vers leurs méthodes cibles
     *
     * @var array<string, string>
     */
    protected array $methodAliases = [
        'getConnection' => 'db',

        // Récupération de résultats
        'one' => 'first',
        'all' => 'result',
        'get' => 'collect',

        // Commandes SQL
        'order'      => 'orderBy',
        'group'      => 'groupBy',
        'addSelect'  => 'select',
        'selectSub'  => 'selectSubquery',
        'skip'       => 'offset',
        'take'       => 'limit',

        // Conditions WHERE
        'notWhere'          => 'whereNot',
        'orNotWhere'        => 'orWhereNot',
        'in'                => 'whereIn',
        'notIn'             => 'whereNotIn',
        'orIn'              => 'orWhereIn',
        'orNotIn'           => 'orWhereNotIn',
        'like'              => 'whereLike',
        'notLike'           => 'whereNotLike',
        'orLike'            => 'orWhereLike',
        'orNotLike'         => 'orWhereNotLike',
        'between'           => 'whereBetween',
        'notBetween'        => 'whereNotBetween',
        'orBetween'         => 'orWhereBetween',
        'orNotBetween'      => 'orWhereNotBetween',
        'notWhereColumn'    => 'whereNotColumn',
        'orNotWhereColumn'  => 'orWhereNotColumn',

        // Tri
        'sortAsc'           => 'orderBy',
        'sortDesc'          => 'orderBy',
        'sortRand'          => 'rand',
        'inRandomOrder'     => 'rand',
        'latest'            => 'orderBy',
        'oldest'            => 'orderBy',
        'reorderDesc'       => 'reorder',

        // Insertions
        'bulckInsert'        => 'bulkInsert',
        'bulckInsertIgnore'  => 'bulkInsertIgnore',
    ];

    /**
     * Gère les appels aux méthodes alias
     *
     * @param string $method Nom de la méthode appelée
     * @param array  $parameters Paramètres de la méthode
     *
     * @return mixed
     *
     * @throws BadMethodCallException
     */
    public function __call(string $method, array $parameters)
    {
        if (static::hasMacro($method)) {
            return $this->macroCall($method, $parameters);
        }

        if (isset($this->methodAliases[$method])) {
            $targetMethod = $this->methodAliases[$method];
            $parameters = $this->adaptParameters($method, $targetMethod, $parameters);

            return $this->{$targetMethod}(...$parameters);
        }

        static::throwBadMethodCallException($method);
    }

    /**
     * Adapte les paramètres d'une méthode alias vers sa méthode cible
     *
     * @param string $alias Nom de l'alias appelé
     * @param string $target Nom de la méthode cible
     * @param array  $params Paramètres originaux
     *
     * @return array Paramètres adaptés
     */
    protected function adaptParameters(string $alias, string $target, array $params): array
    {
        return match ($alias) {
            // Pour sortAsc/sortDesc, on ajoute la direction
            'sortAsc'  => [$params[0], 'ASC'],
            'sortDesc' => [$params[0], 'DESC'],

            // Pour latest/oldest, direction par défaut + paramètre optionnel
            'latest' => [$params[0] ?? 'created_at', 'DESC'],
            'oldest' => [$params[0] ?? 'created_at', 'ASC'],

            'reorderDesc' => [$params[0] ?? null, 'DESC'],

            // Pour les alias simples, pas de modification
            default => $params,
        };
    }
}
