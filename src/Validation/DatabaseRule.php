<?php

/**
 * This file is part of Blitz PHP framework - Database Layer.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Database\Validation;

use BackedEnum;
use BlitzPHP\Contracts\Database\BuilderInterface;
use BlitzPHP\Contracts\Database\ConnectionInterface;
use BlitzPHP\Contracts\Support\Arrayable;
use BlitzPHP\Database\Builder\BaseBuilder;
use BlitzPHP\Wolke\Model;
use Closure;

trait DatabaseRule
{
    /**
     * Les clauses Where supplémentaires pour la requête.
     */
    protected array $wheres = [];

    /**
     * Tableau de callback de requêtes personnalisés.
     *
     * @var Closure[]
     */
    protected array $using = [];

    /**
     * Créez une nouvelle instance de règle.
     */
    public function __construct(protected ConnectionInterface $db)
    {
    }

    /**
     * Résout le nom de la table à partir de la chaîne donnée.
     */
    public function resolveTableName(string $table): string
    {
        if (! str_contains($table, '\\') || ! class_exists($table)) {
            return $table;
        }

        if (class_exists(Model::class) && is_subclass_of($table, Model::class)) {
            $model = new $table();

            if (str_contains($model->getTable(), '.')) {
                return $table;
            }

            return implode('.', array_map(static fn (string $part) => trim($part, '.'), array_filter([$model->getConnectionName(), $model->getTable()])));
        }

        return $table;
    }

    /**
     * Définissez une contrainte « where » sur la requête.
     *
     * @param array|Arrayable|BackedEnum|bool|Closure|int|string|null $value
     */
    public function where(Closure|string $column, $value = null): self
    {
        if ($value instanceof Arrayable || is_array($value)) {
            return $this->whereIn($column, $value);
        }

        if ($column instanceof Closure) {
            return $this->using($column);
        }

        if (null === $value) {
            return $this->whereNull($column);
        }

        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        $this->wheres[] = compact('column', 'value');

        return $this;
    }

    /**
     * Définissez une contrainte "where not" sur la requête.
     *
     * @param array|Arrayable|BackedEnum|string $value
     */
    public function whereNot(string $column, $value): self
    {
        if ($value instanceof Arrayable || is_array($value)) {
            return $this->whereNotIn($column, $value);
        }

        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        return $this->where($column . ' !=', $value);
    }

    /**
     * Définissez une contrainte "where null" sur la requête.
     */
    public function whereNull(string $column): self
    {
        return $this->where($column, 'NULL');
    }

    /**
     * Définissez une contrainte « where not null » sur la requête.
     */
    public function whereNotNull(string $column): self
    {
        return $this->where($column, 'NOT_NULL');
    }

    /**
     * Définissez une contrainte « where in » sur la requête.
     *
     * @param array|Arrayable|BackedEnum $values
     */
    public function whereIn(string $column, $values): self
    {
        return $this->where(static function ($query) use ($column, $values) {
            $query->whereIn($column, $values);
        });
    }

    /**
     * Définissez une contrainte « where not in » sur la requête.
     *
     * @param array|Arrayable|BackedEnum $values
     */
    public function whereNotIn(string $column, $values): self
    {
        return $this->where(static function ($query) use ($column, $values) {
            $query->whereNotIn($column, $values);
        });
    }

    /**
     * Ignorez les modèles supprimés de manière logicielle lors de la vérification de l'existence.
     */
    public function withoutTrashed(string $deletedAtColumn = 'deleted_at'): self
    {
        return $this->whereNull($deletedAtColumn);
    }

    /**
     * Incluez uniquement les modèles supprimés de manière logicielle lors de la vérification de l'existence.
     */
    public function onlyTrashed(string $deletedAtColumn = 'deleted_at'): self
    {
        return $this->whereNotNull($deletedAtColumn);
    }

    /**
     * Enregistrez un callback de requête personnalisé.
     */
    public function using(Closure $callback): self
    {
        $this->using[] = $callback;

        return $this;
    }

    /**
     * Obtenez les callback de requêtes personnalisés pour la règle.
     */
    public function queryCallbacks(): array
    {
        return $this->using;
    }

    /**
     * Recuperez les clauses wheres
     */
    protected function getWheres(): array
    {
        return array_column($this->wheres, 'value', 'column');
    }

    /**
     * Construction du querybuilder
     */
    protected function makeBuilder(string $table, string $column, mixed $value): BuilderInterface
    {
        /** @var BaseBuilder $builder */
        $builder = $this->db->table($this->resolveTableName($table))->where($column, $value);

        foreach ($this->getWheres() as $field => $value) {
            if ($value === 'NULL') {
                $builder = $builder->whereNull($field);
            } elseif ($value === 'NOT_NULL') {
                $builder = $builder->whereNotNull($field);
            } else {
                $builder = $builder->where($field, $value);
            }
        }

        foreach ($this->queryCallbacks() as $callback) {
            $builder = $builder->where($callback);
        }

        return $builder;
    }
}
