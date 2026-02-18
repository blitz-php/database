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

/**
 * Gère les appels aux méthodes alias via un système de proxy
 * 
 * @mixin \BlitzPHP\Database\Builder\BaseBuilder
 */
trait ProxyMethods
{
    /**
     * Mapping des méthodes alias vers leurs méthodes cibles
     */
    protected array $methodAliases = [        
        // Récupération de résultats
        'one'               => 'first',
        
        // Requêtes
        'all'               => 'result',
        'get'               => 'result',
        
        // Commandes SQL
        'order'             => 'orderBy',
        'group'             => 'groupBy',
        'addSelect'         => 'select',
        
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
     */
    public function __call(string $method, array $parameters = []): mixed
    {
        if (isset($this->methodAliases[$method])) {
            $targetMethod = $this->methodAliases[$method];
            
            $parameters = $this->adaptParameters($method, $targetMethod, $parameters);
            
            return $this->{$targetMethod}(...$parameters);
        }

        throw new BadMethodCallException(sprintf('Call to undefined method %s::%s()', static::class, $method));
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
