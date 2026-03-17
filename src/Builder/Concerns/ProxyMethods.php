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
 * @method ConnectionInterface getConnection()
 * @method self latest(\Closure|\BlitzPHP\Database\Builder\BaseBuilder|\BlitzPHP\Database\Query\Expression|string $column = 'created_at') Ajoute une clause "order by" pour un timestamp à la requête.
 * @method self oldest(\Closure|\BlitzPHP\Database\Builder\BaseBuilder|\BlitzPHP\Database\Query\Expression|string $column = 'created_at') Ajoute une clause "order by" pour un timestamp à la requête.
 * 
 * @mixin \BlitzPHP\Database\Builder\BaseBuilder
 */
trait ProxyMethods
{
    use Macroable { __call as macroCall; }

    /**
     * Mapping des méthodes alias vers leurs méthodes cibles
     */
    protected array $methodAliases = [   
        'getConnection'     => 'db',

        // Récupération de résultats
        'one'               => 'first',
        
        // Requêtes
        'all'               => 'result',
        'get'               => 'result',
        
        // Commandes SQL
        'order'             => 'orderBy',
        'group'             => 'groupBy',
        'addSelect'         => 'select',
        'selectSub'         => 'selectSubquery',
        'skip'              => 'offset',
        'take'              => 'limit',
        
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
        
        // Conditions HAVING
        'notHavingLike'     => 'havingNotLike',
        'orHavingLike'      => 'orHavingLike',
        'orHavingNotLike'   => 'orHavingNotLike',
        
        // Tri
        'sortAsc'           => 'orderBy',
        'sortDesc'          => 'orderBy',
        'sortRand'          => 'rand',
        'inRandomOrder'     => 'rand',
        'latest'            => 'orderBy',
        'oldest'            => 'orderBy',
        'reorderDesc'       => 'reorder',
        
        // Insertions
        'bulckInsert'       => 'bulkInsert',
        'bulckInsertIgnore' => 'bulkInsertIgnore',
    ];

    /**
     * Gère les appels aux méthodes alias
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
     */
    protected function adaptParameters(string $alias, string $target, array $params): array
    {
        return match($alias) {
            // Pour sortAsc/sortDesc, on ajoute la direction
            'sortAsc'   => [$params[0], 'ASC'],
            'sortDesc'  => [$params[0], 'DESC'],
            
            // Pour latest/oldest, direction par défaut + paramètre optionnel
            'latest'    => [$params[0] ?? 'created_at', 'DESC'],
            'oldest'    => [$params[0] ?? 'created_at', 'ASC'],
            
            'reorderDesc'    => [$params[0] ?? null, 'DESC'],
            
            // Pour les alias simples, pas de modification
            default     => $params,
        };
    }
}
