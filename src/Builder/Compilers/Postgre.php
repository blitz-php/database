<?php

/**
 * This file is part of Blitz PHP framework - Database Layer.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Database\Builder\Compilers;

use BlitzPHP\Database\Builder\BaseBuilder;
use BlitzPHP\Database\Builder\JoinClause;

class Postgre extends QueryCompiler
{
    /**
     * {@inheritDoc}
     */
    protected function compileDistinct(bool|string $distinct): string
    {
        if ($distinct) {
            return is_string($distinct) ? $distinct : 'DISTINCT';
        }

        return '';
    }

    /**
     * {@inheritDoc}
     */
    protected function compileLock(?string $lock): string
    {
        return $lock ?? '';
    }

    /**
     * {@inheritDoc}
     */
    public function compileInsertion(string $table, string $columns, string $values, bool $ignore, ?string $returning = '*'): string
    {
        $ignored  = $ignore ? ' ON CONFLICT DO NOTHING' : '';
        $returned = $returning ? " RETURNING {$returning}" : '';

        return "INSERT INTO {$table} ({$columns}) VALUES {$values}{$ignored}{$returned}";
    }

    /**
     * {@inheritDoc}
     */
    public function compileInsertUsing(BaseBuilder $builder): string
    {
        $sql = parent::compileInsertUsing($builder);

        return "{$sql} RETURNING *";
    }

    /**
     * {@inheritDoc}
     */
    public function compileUpdate(BaseBuilder $builder): string
    {
        if ($builder->joins === []) {
            $sql = $this->compileUpdateStandard($builder);

            return "{$sql} RETURNING *";
        }

        return $this->compileUpdateWithFrom($builder);
    }

    /**
     * {@inheritDoc}
     */
    public function compileTruncate(BaseBuilder $builder): string
    {
        $table = $this->db->escapeIdentifiers($builder->getTable());

        return "TRUNCATE TABLE {$table} RESTART IDENTITY";
    }

    /**
     * {@inheritDoc}
     */
    public function compileReplace(BaseBuilder $builder): string
    {
        // PostgreSQL n'a pas de REPLACE, on utilise INSERT ... ON CONFLICT

        $sql = parent::compileReplace($builder);

        return "{$sql} ON CONFLICT DO UPDATE SET " . $this->compileUpdateSet($builder) . ' RETURNING *';
    }

    /**
     * {@inheritDoc}
     */
    protected function compileReplacement(string $table, string $columns, string $values): string
    {
        // PostgreSQL n'a pas de REPLACE, on utilisera INSERT ... ON CONFLICT

        return "INSERT INTO {$table} ({$columns}) VALUES {$values}";
    }

    /**
     * Compile la clause SET pour UPDATE
     */
    protected function compileUpdateSet(BaseBuilder $builder): string
    {
        $sets = [];

        foreach ($builder->values as $column => $value) {
            $column = $this->db->escapeIdentifiers($column);
            $sets[] = "{$column} = EXCLUDED.{$column}";
        }

        return implode(', ', $sets);
    }

    /**
     * {@inheritDoc}
     */
    protected function compileUpsertment(string $table, string $columns, string $values, BaseBuilder $builder): string
    {
        // Construire la clause ON CONFLICT
        $uniqueBy   = array_map([$this->db, 'escapeIdentifiers'], $builder->uniqueBy);
        $constraint = 'ON CONFLICT (' . implode(', ', $uniqueBy) . ') DO UPDATE SET ';

        $updates = [];

        foreach ($builder->updateColumns as $column) {
            if (! in_array($column, $builder->uniqueBy, true)) {
                $col       = $this->db->escapeIdentifiers($column);
                $updates[] = $col . ' = EXCLUDED.' . $col;
            }
        }

        return "INSERT INTO {$table} ({$columns}) VALUES {$values} {$constraint}" . implode(', ', $updates) . ' RETURNING *';
    }

    /**
     * {@inheritDoc}
     */
    protected function compileJsonContains(string $column, $value, bool $not = false): string
    {
        $column = $this->db->escapeIdentifiers($column);
        $notStr = $not ? 'NOT ' : '';

        // PostgreSQL utilise l'opérateur @> pour JSON contains
        return "{$column} {$notStr}@> ?::jsonb";
    }

    /**
     * {@inheritDoc}
     */
    protected function compileJsonContainsKey(string $column, bool $not = false): string
    {
        $column = $this->db->escapeIdentifiers($column);
        $notStr = $not ? 'NOT ' : '';

        // PostgreSQL utilise l'opérateur ? pour vérifier l'existence d'une clé
        return "{$column} {$notStr}? ?";
    }

    /**
     * {@inheritDoc}
     */
    protected function compileJsonLength(string $column, string $operator, int $value): string
    {
        $column = $this->db->escapeIdentifiers($column);

        // PostgreSQL utilise jsonb_array_length() pour les tableaux JSON
        return "jsonb_array_length({$column}) {$operator} ?";
    }

    /**
     * {@inheritDoc}
     */
    protected function compileJsonSearch(string $column, string $value, bool $not = false): string
    {
        // PostgreSQL n'a pas d'équivalent direct à JSON_SEARCH de MySQL
        // On peut utiliser jsonb_path_exists() pour des recherches avancées
        $column = $this->db->escapeIdentifiers($column);
        $notStr = $not ? 'NOT ' : '';

        return "jsonb_path_exists({$column}, ?) IS {$notStr}TRUE";
    }

    /**
     * {@inheritDoc}
     */
    protected function compileAnyAll(string $type, string $column, string $operator, array $values): string
    {
        $column       = $this->db->escapeIdentifiers($column);
        $placeholders = implode(', ', array_fill(0, count($values), '?'));

        return "{$column} {$operator} {$type} ({$placeholders})";
    }

    /**
     * Compilation PostgreSQL avec FROM
     */
    protected function compileUpdateWithFrom(BaseBuilder $builder): string
    {
        $table = $this->db->makeTableName($builder->getTable());

        $sets = [];

        foreach ($builder->values as $column => $value) {
            $column = $this->db->escapeIdentifiers($column);
            $sets[] = "{$column} = " . $this->wrapValue($value);
        }

        $sql   = ["UPDATE {$table}"];
        $sql[] = 'SET ' . implode(', ', $sets);

        // Construction de la clause FROM
        $fromTables     = [];
        $joinConditions = [];

        foreach ($builder->joins as $join) {
            if ($join instanceof JoinClause) {
                $fromTables[] = $join->getTable();

                // Convertir les conditions ON en conditions WHERE
                foreach ($join->getConditions() as $condition) {
                    if ($condition['type'] === 'basic') {
                        $joinConditions[] = $condition['first'] . ' ' .
                                           $condition['operator'] . ' ' .
                                           $condition['second'];
                    }
                }
            }
        }

        if ($fromTables !== []) {
            $sql[] = 'FROM ' . implode(', ', $fromTables);
        }

        // Fusionner les conditions WHERE originales avec les conditions de jointure
        $allConditions = array_merge($joinConditions, $builder->wheres);

        if ($allConditions !== []) {
            $sql[] = 'WHERE';
            $sql[] = $this->compileWheres($allConditions);
        }

        return implode(' ', array_filter($sql));
    }
}
