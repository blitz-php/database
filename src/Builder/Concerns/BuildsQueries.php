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

use BlitzPHP\Database\Exceptions\RecordsNotFoundException;
use BlitzPHP\Database\Exceptions\MultipleRecordsFoundException;
use BlitzPHP\Traits\Conditionable;
use BlitzPHP\Utilities\Helpers;
use BlitzPHP\Utilities\Iterable\Collection;
use BlitzPHP\Utilities\Iterable\LazyCollection;
use InvalidArgumentException;
use RuntimeException;

/**
 * @template TValue
 * 
 * @mixin \BlitzPHP\Database\Builder\BaseBuilder
 */
trait BuildsQueries
{
    use Conditionable;
    
    /**
     * Passe la requête à un callback donné puis la retourne.
     *
     * @param callable($this): mixed $callback
     */
    public function tap(callable $callback): static
    {
        $callback($this);

        return $this;
    }

    /**
     * Passe la requête à un callback donné et retourne le résultat.
     *
     * @template TReturn
     *
     * @param  (callable($this): TReturn)  $callback
     * 
     * @return (TReturn is null|void ? $this : TReturn)
     */
    public function pipe($callback)
    {
        return $callback($this) ?? $this;
    }

    /**
     * Pagination simple
     */
    public function forPage(int $page, int $perPage = 15): static
    {
        return $this->offset(($page - 1) * $perPage)->limit($perPage);
    }

    /**
     * Contraint la requête à la "page" précédente de résultats avant un ID donné.
     * 
     * Pagination avec curseur (pour les grandes tables)
     */
    public function forPageBeforeId(int $perPage = 15, ?int $lastId = 0, string $column = 'id'): self
    {
        $this->orders = $this->removeExistingOrdersFor($column);
        
        if ($lastId === null) {
            $this->whereNotNull($column);
        } else {
            $this->where($column, '<', $lastId);
        }

        return $this->orderBy($column, 'DESC')->limit($perPage);
    }
    
    /**
     * Contraint la requête à la "page" suivante de résultats après un ID donné.
     * 
     * Pagination avec curseur (pour les grandes tables)
     */
    public function forPageAfterId(int $perPage = 15, ?int $lastId = 0, string $column = 'id'): self
    {
        $this->orders = $this->removeExistingOrdersFor($column);

        if ($lastId === null) {
            $this->whereNotNull($column);
        } else {
            $this->where($column, '>', $lastId);
        }

        return $this->orderBy($column, 'ASC')->limit($perPage);
    }

    /**
     * Traitement par lots
     * 
     * @param  callable(Collection<int, TValue>, int): mixed  $callback
     */
    public function chunk(int $count, callable $callback): bool
    {
        $this->enforceOrderBy();

        $skip      = $this->offset;
        $remaining = $this->limit;
        $page      = 1;

        do {
            $offset = (($page - 1) * $count) + (int) $skip;
            $limit  = $remaining === null ? $count : min($count, $remaining);
            
            if ($limit == 0) {
                break;
            }

            $results = $this->clone()->limit($limit, $offset)->collect();
            
            if (0 == $countResults = $results->count()) {
                break;
            }

            if ($callback($results, $page) === false) {
                return false;
            }

            unset($results);

            $page++;
        } while ($countResults == $count);

        return true;
    }

    /**
     * Exécute une carte sur chaque élément tout en traitant par lots.
     *
     * @template TReturn
     *
     * @param callable(TValue): TReturn $callback
     * 
     * @return Collection<int, TReturn>
     */
    public function chunkMap(callable $callback, int $count = 1000): Collection
    {
        $collection = new Collection();

        $this->chunk($count, static function ($items) use ($collection, $callback) {
            $items->each(static function ($item) use ($collection, $callback) {
                $collection->push($callback($item));
            });
        });

        return $collection;
    }

    /**
     * Traite par lots les résultats d'une requête en comparant les IDs.
     *
     * @param callable(Collection<int, TValue>, int): mixed $callback
     */
    public function chunkById(int $count, callable $callback, string $column = 'id', ?string $alias = null): bool
    {
        return $this->orderedChunkById($count, $callback, $column, $alias);
    }

    /**
     * Traite par lots les résultats d'une requête en comparant les IDs en ordre décroissant.
     *
     * @param callable(Collection<int, TValue>, int): mixed $callback
     */
    public function chunkByIdDesc(int $count, callable $callback, string $column = 'id', ?string $alias = null): bool
    {
        return $this->orderedChunkById($count, $callback, $column, $alias, descending: true);
    }

    /**
     * Traite par lots les résultats d'une requête en comparant les IDs dans un ordre donné.
     *
     * @param callable(Collection<int, TValue>, int): mixed $callback
     */
    public function orderedChunkById(int $count, callable $callback, string $column = 'id', ?string $alias = null, bool $descending = false): bool
    {
        $alias   ??= $column;
        $lastId    = null;
        $skip      = $this->offset;
        $remaining = $this->limit;

        $page = 1;

        do {
            $clone = $this->clone();

            if ($skip && $page > 1) {
                $clone->offset(0);
            }

            $limit = $remaining === null ? $count : min($count, $remaining);
            if ($limit == 0) {
                break;
            }

            if ($descending) {
                $results = $clone->forPageBeforeId($limit, $lastId, $column)->collect();
            } else {
                $results = $clone->forPageAfterId($limit, $lastId, $column)->collect();
            }

            if (0 === $countResults = $results->count()) {
                break;
            }

            if ($remaining !== null) {
                $remaining = max($remaining - $countResults, 0);
            }

            if ($callback($results, $page) === false) {
                return false;
            }

            $lastId = Helpers::dataGet($results->last(), $alias);

            if ($lastId === null) {
                throw new RuntimeException("The chunkById operation was aborted because the [{$alias}] column is not present in the query result.");
            }

            unset($results);

            $page++;
        } while ($countResults == $count);

        return true;
    }

    /**
     * Exécute un callback sur chaque élément tout en traitant par lots.
     *
     * @param callable(TValue, int): mixed $callback
     *
     * @throws RuntimeException
     */
    public function each(callable $callback, int $count = 1000): bool
    {
        return $this->chunk($count, function ($results) use ($callback) {
            foreach ($results as $key => $value) {
                if ($callback($value, $key) === false) {
                    return false;
                }
            }
        });
    }

    /**
     * Exécute un callback sur chaque élément tout en traitant par lots par ID.
     */
    public function eachById(callable $callback, int $count = 1000, string $column = 'id', ?string $alias = null): bool
    {
        return $this->chunkById($count, function ($results, $page) use ($callback, $count) {
            foreach ($results as $key => $value) {
                if ($callback($value, (($page - 1) * $count) + $key) === false) {
                    return false;
                }
            }
        }, $column, $alias);
    }

    /**
     * Interroge paresseusement, par lots de la taille donnée.
     *
     * @return LazyCollection<int, TValue>
     *
     * @throws InvalidArgumentException
     */
    public function lazy(int $chunkSize = 1000): LazyCollection
    {
        if ($chunkSize < 1) {
            throw new InvalidArgumentException('The chunk size should be at least 1');
        }

        $this->enforceOrderBy();

        return new LazyCollection(function () use ($chunkSize) {
            $page = 1;

            while (true) {
                $results = $this->forPage($page++, $chunkSize)->collect();

                foreach ($results as $result) {
                    yield $result;
                }

                if ($results->count() < $chunkSize) {
                    return;
                }
            }
        });
    }

    /**
     * Interroge paresseusement, en traitant par lots les résultats d'une requête en comparant les IDs.
     *
     * @return LazyCollection<int, TValue>
     *
     * @throws InvalidArgumentException
     */
    public function lazyById(int $chunkSize = 1000, string $column = 'id', ?string $alias = null): LazyCollection
    {
        return $this->orderedLazyById($chunkSize, $column, $alias);
    }

    /**
     * Interroge paresseusement, en traitant par lots les résultats d'une requête en comparant les IDs en ordre décroissant.
     *
     * @return LazyCollection<int, TValue>
     *
     * @throws InvalidArgumentException
     */
    public function lazyByIdDesc(int $chunkSize = 1000, string $column = 'id', ?string $alias = null): LazyCollection
    {
        return $this->orderedLazyById($chunkSize, $column, $alias, true);
    }

    /**
     * Interroge paresseusement, en traitant par lots les résultats d'une requête en comparant les IDs dans un ordre donné.
     */
    public function orderedLazyById(int $chunkSize = 1000, string $column = 'id', ?string $alias = null, bool $descending = false): LazyCollection
    {
        if ($chunkSize < 1) {
            throw new InvalidArgumentException('The chunk size should be at least 1');
        }

        $alias ??= $column;

        return new LazyCollection(function () use ($chunkSize, $column, $alias, $descending) {
            $lastId = null;

            while (true) {
                $clone = clone $this;

                if ($descending) {
                    $results = $clone->forPageBeforeId($chunkSize, $lastId, $column)->get();
                } else {
                    $results = $clone->forPageAfterId($chunkSize, $lastId, $column)->get();
                }

                foreach ($results as $result) {
                    yield $result;
                }

                if ($results->count() < $chunkSize) {
                    return;
                }

                $lastId = $results->last()->{$alias};

                if ($lastId === null) {
                    throw new RuntimeException("The lazyById operation was aborted because the [{$alias}] column is not present in the query result.");
                }
            }
        });
    }

    /**
     * Exécute la requête et obtient le premier résultat s'il est le seul enregistrement correspondant.
     *
     * @return TValue|null
     *
     * @throws RecordsNotFoundException
     * @throws MultipleRecordsFoundException
     */
    public function sole(array|string $columns = ['*'])
    {
        $result = $this->limit(2)->select($columns)->collect();

        $count = $result->count();

        if ($count === 0) {
            throw new RecordsNotFoundException();
        }

        if ($count > 1) {
            throw new MultipleRecordsFoundException($count);
        }

        return $result->first();
    }
}
