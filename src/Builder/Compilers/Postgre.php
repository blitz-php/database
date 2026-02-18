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
use BlitzPHP\Database\Query\Expression;

class Postgre extends QueryCompiler
{
    /**
     * {@inheritDoc}
     */
    public function compileSelect(BaseBuilder $builder): string
    {
        $sql = ['SELECT'];

        if ($builder->distinct) {
            $sql[] = is_string($builder->distinct) ? $builder->distinct : 'DISTINCT';
        }

        $sql[] = $this->compileColumns($builder->columns ?: ['*']);

        if ([] !== $builder->tables) {
            $sql[] = 'FROM';
            $sql[] = $this->compileTables($builder->tables);
        }

        if ([] !== $builder->joins) {
            $sql[] = $this->compileJoins($builder->joins);
        }

        if ([] !== $builder->wheres) {
            $sql[] = 'WHERE';
            $sql[] = $this->compileWheres($builder->wheres);
        }

        if ([] !== $builder->groups) {
            $sql[] = 'GROUP BY';
            $sql[] = $this->compileGroups($builder->groups);
        }

        if ([] !== $builder->havings) {
            $sql[] = 'HAVING';
            $sql[] = $this->compileHavings($builder->havings);
        }

        if ([] !== $builder->orders) {
            $sql[] = 'ORDER BY';
            $sql[] = $this->compileOrders($builder->orders);
        }

        if ([] !== $builder->unions) {
            $sql[] = $this->compileUnions($builder->unions);
        }

        $limitSql = $this->compileLimit($builder->limit, $builder->offset);
        if ($limitSql !== '') {
            $sql[] = $limitSql;
        }

        if (null !== $builder->lock) {
            $sql[] = $builder->lock;
        }

        return implode(' ', array_filter($sql));
    }

    /**
     * {@inheritDoc}
     */
    public function compileInsert(BaseBuilder $builder): string
    {
        $table = $this->db->escapeIdentifiers($builder->getTable());
        $columns = implode(', ', array_map([$this->db, 'escapeIdentifiers'], array_keys($builder->values)));
        
        // Support des insertions multiples
        if (isset($builder->values[0]) && is_array($builder->values[0])) {
            $values = [];
            foreach ($builder->values as $row) {
                $rowValues = array_map([$this, 'wrapValue'], $row);
                $values[] = '(' . implode(', ', $rowValues) . ')';
            }
            $values = implode(', ', $values);
        } else {
            $values = '(' . implode(', ', array_map([$this, 'wrapValue'], $builder->values)) . ')';
        }

        return "INSERT INTO {$table} ({$columns}) VALUES {$values} RETURNING *";
    }

    /**
     * {@inheritDoc}
     */
    public function compileInsertUsing(BaseBuilder $builder): string
    {
        $table = $this->db->escapeIdentifiers($builder->getTable());
        $columns = implode(', ', array_map([$this->db, 'escapeIdentifiers'], $builder->columns));
        
        /** @var BaseBuilder $query */
        $query = $builder->values['query'];
        $subquery = $query->toSql();

        return "INSERT INTO {$table} ({$columns}) {$subquery} RETURNING *";
    }

    /**
     * {@inheritDoc}
     */
    public function compileUpdate(BaseBuilder $builder): string
    {
        $table = $this->db->escapeIdentifiers($builder->getTable());
        
        $sets = [];
        foreach ($builder->values as $column => $value) {
            $column = $this->db->escapeIdentifiers($column);
            $sets[] = "{$column} = " . $this->wrapValue($value);
        }

        $sql = ["UPDATE {$table} SET " . implode(', ', $sets)];

        if ([] !== $builder->joins) {
            $sql[] = $this->compileJoins($builder->joins);
        }

        if ([] !== $builder->wheres) {
            $sql[] = 'WHERE';
            $sql[] = $this->compileWheres($builder->wheres);
        }

        $limitSql = $this->compileLimit($builder->limit, null);
        if ($limitSql !== '') {
            $sql[] = $limitSql;
        }

        return implode(' ', array_filter($sql)) . ' RETURNING *';
    }

    /**
     * {@inheritDoc}
     */
    public function compileDelete(BaseBuilder $builder): string
    {
        $table = $this->db->escapeIdentifiers($builder->getTable());
        
        $sql = ["DELETE FROM {$table}"];

        if ([] !== $builder->joins) {
            $sql[] = $this->compileJoins($builder->joins);
        }

        if ([] !== $builder->wheres) {
            $sql[] = 'WHERE';
            $sql[] = $this->compileWheres($builder->wheres);
        }

        return implode(' ', array_filter($sql));
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
        $table = $this->db->escapeIdentifiers($builder->getTable());
        $columns = implode(', ', array_map([$this->db, 'escapeIdentifiers'], array_keys($builder->values)));
        
        // Support des insertions multiples pour REPLACE
        if (isset($builder->values[0]) && is_array($builder->values[0])) {
            $values = [];
            foreach ($builder->values as $row) {
                $rowValues = array_map([$this, 'wrapValue'], $row);
                $values[] = '(' . implode(', ', $rowValues) . ')';
            }
            $values = implode(', ', $values);
        } else {
            $values = '(' . implode(', ', array_map([$this, 'wrapValue'], $builder->values)) . ')';
        }

        return "INSERT INTO {$table} ({$columns}) VALUES {$values} ON CONFLICT DO UPDATE SET " . $this->compileUpdateSet($builder) . ' RETURNING *';
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
    public function compileUpsert(BaseBuilder $builder): string
    {
        $table = $this->db->escapeIdentifiers($builder->getTable());
        
        // Récupérer la première ligne pour les colonnes
        $firstRow = $builder->values[0] ?? $builder->values;
        $columns = implode(', ', array_map([$this->db, 'escapeIdentifiers'], array_keys($firstRow)));
        
        // Construire les valeurs (support multi-insert)
        if (isset($builder->values[0]) && is_array($builder->values[0])) {
            $valueRows = [];
            foreach ($builder->values as $row) {
                $rowValues = array_map([$this, 'wrapValue'], $row);
                $valueRows[] = '(' . implode(', ', $rowValues) . ')';
            }
            $values = implode(', ', $valueRows);
        } else {
            $values = '(' . implode(', ', array_map([$this, 'wrapValue'], $builder->values)) . ')';
        }

        // Construire la clause ON CONFLICT
        $uniqueBy = array_map([$this->db, 'escapeIdentifiers'], $builder->uniqueBy);
        $constraint = 'ON CONFLICT (' . implode(', ', $uniqueBy) . ') DO UPDATE SET ';
        
        $updates = [];
        foreach ($builder->updateColumns as $column) {
            if (!in_array($column, $builder->uniqueBy)) {
                $col = $this->db->escapeIdentifiers($column);
                $updates[] = $col . ' = EXCLUDED.' . $col;
            }
        }

        return "INSERT INTO {$table} ({$columns}) VALUES {$values} {$constraint}" . implode(', ', $updates) . ' RETURNING *';
    }

    /**
     * {@inheritDoc}
     */
    public function compileWhere(array $where): string
    {
        switch ($where['type']) {
            case 'basic':
                $column = $this->db->escapeIdentifiers($where['column']);
                $operator = $this->translateOperator($where['operator']);
                
                // Gestion de la sensibilité à la casse pour LIKE
                if ($operator === 'LIKE BINARY') {
                    $operator = 'LIKE';
                }
                
                if (isset($where['value']) && $where['value'] instanceof Expression) {
                    return "{$column} {$operator} {$where['value']}";
                }
                
                return "{$column} {$operator} ?";

            case 'in':
                $column = $this->db->escapeIdentifiers($where['column']);
                $placeholders = implode(', ', array_fill(0, count($where['values']), '?'));
                return "{$column} {$where['operator']} ({$placeholders})";

            case 'insub':
                $column = $this->db->escapeIdentifiers($where['column']);
                $subquery = $where['query']->toSql();
                $not = $where['not'] ? 'NOT ' : '';
                return "{$column} {$not}IN ({$subquery})";

            case 'null':
                $column = $this->db->escapeIdentifiers($where['column']);
                return "{$column} IS " . ($where['not'] ? 'NOT NULL' : 'NULL');

            case 'between':
                $column = $this->db->escapeIdentifiers($where['column']);
                $not = $where['not'] ? 'NOT ' : '';
                return "{$column} {$not}BETWEEN ? AND ?";

            case 'betweencolumns':
                $column = $this->db->escapeIdentifiers($where['column']);
                $col1 = $this->db->escapeIdentifiers($where['values'][0]);
                $col2 = $this->db->escapeIdentifiers($where['values'][1]);
                $not = $where['not'] ? 'NOT ' : '';
                return "{$column} {$not}BETWEEN {$col1} AND {$col2}";

            case 'valuebetween':
                $col1 = $this->db->escapeIdentifiers($where['column1']);
                $col2 = $this->db->escapeIdentifiers($where['column2']);
                $not = $where['not'] ? 'NOT ' : '';
                return "? {$not}BETWEEN {$col1} AND {$col2}";

            case 'any':
                return $this->compileAnyAll('ANY', $where['column'], $where['operator'], $where['values']);

            case 'all':
                return $this->compileAnyAll('ALL', $where['column'], $where['operator'], $where['values']);

            case 'json':
                // PostgreSQL utilise l'opérateur @> pour JSON contains
                if ($where['operator'] === 'JSON_CONTAINS') {
                    $column = $this->db->escapeIdentifiers($where['column']);
                    $not = $where['not'] ? 'NOT ' : '';
                    return "{$column} {$not}@> ?::jsonb";
                }
                return '';

            case 'jsonkey':
                $column = $this->db->escapeIdentifiers($where['column']);
                $not = $where['not'] ? 'NOT ' : '';
                return "{$column} {$not}? ?";

            case 'jsonlength':
                $column = $this->db->escapeIdentifiers($where['column']);
                return "jsonb_array_length({$column}) {$where['operator']} ?";

            case 'column':
                $first = $this->db->escapeIdentifiers($where['first']);
                $second = $this->db->escapeIdentifiers($where['second']);
                return "{$first} {$where['operator']} {$second}";

            case 'nested':
                return '(' . $this->compileWheres($where['query']->wheres) . ')';

            case 'exists':
                $subquery = $where['query']->toSql();
                $not = $where['not'] ? 'NOT ' : '';
                return "{$not}EXISTS ({$subquery})";

            case 'raw':
                return $where['sql'];

            default:
                return '';
        }
    }
}