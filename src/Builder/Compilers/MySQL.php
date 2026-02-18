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
use InvalidArgumentException;

class MySQL extends QueryCompiler
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

        $ignore = $builder->ignore ? ' IGNORE' : '';

        return "INSERT{$ignore} INTO {$table} ({$columns}) VALUES {$values}";
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

        return "INSERT INTO {$table} ({$columns}) {$subquery}";
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

        return implode(' ', array_filter($sql));
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

        $limitSql = $this->compileLimit($builder->limit, null);
        if ($limitSql !== '') {
            $sql[] = $limitSql;
        }

        return implode(' ', array_filter($sql));
    }

    /**
     * {@inheritDoc}
     */
    public function compileTruncate(BaseBuilder $builder): string
    {
        $table = $this->db->escapeIdentifiers($builder->getTable());
        return "TRUNCATE TABLE {$table}";
    }

    /**
     * {@inheritDoc}
     */
    public function compileReplace(BaseBuilder $builder): string
    {
        $table = $this->db->escapeIdentifiers($builder->getTable());
        $columns = implode(', ', array_map([$this->db, 'escapeIdentifiers'], array_keys($builder->values)));
        $values = '(' . implode(', ', array_map([$this, 'wrapValue'], $builder->values)) . ')';

        return "REPLACE INTO {$table} ({$columns}) VALUES {$values}";
    }

    /**
     * {@inheritDoc}
     */
    public function compileUpsert(BaseBuilder $builder): string
    {
        $table = $this->db->escapeIdentifiers($builder->getTable());
        
        // Gérer les insertions multiples
        $firstRow = $builder->values[0] ?? $builder->values;
        $columns = implode(', ', array_map([$this->db, 'escapeIdentifiers'], array_keys($firstRow)));

        // Construire les valeurs
        if (isset($builder->values[0]) && is_array($builder->values[0])) {
            $valueRows = [];
            foreach ($builder->values as $row) {
                $valueRows[] = '(' . implode(', ', array_map([$this, 'wrapValue'], $row)) . ')';
            }
            $values = implode(', ', $valueRows);
        } else {
            $values = '(' . implode(', ', array_map([$this, 'wrapValue'], $builder->values)) . ')';
        }

        // Construire la partie ON DUPLICATE KEY UPDATE
        $updates = [];
        foreach ($builder->updateColumns as $column) {
            if (!in_array($column, $builder->uniqueBy)) {
                $escapedColumn = $this->db->escapeIdentifiers($column);
                $updates[] = "{$escapedColumn} = VALUES({$escapedColumn})";
            }
        }

        $updateSql = !empty($updates) ? ' ON DUPLICATE KEY UPDATE ' . implode(', ', $updates) : '';

        return "INSERT INTO {$table} ({$columns}) VALUES {$values}{$updateSql}";
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
                if ($where['operator'] === 'JSON_CONTAINS') {
                    $column = $this->db->escapeIdentifiers($where['column']);
                    $not = $where['not'] ? 'NOT ' : '';
                    return "{$not}JSON_CONTAINS({$column}, ?)";
                }
                return '';

            case 'jsonkey':
                $column = $this->db->escapeIdentifiers($where['column']);
                $not = $where['not'] ? 'NOT ' : '';
                return "JSON_CONTAINS_PATH({$column}, 'one', ?) {$not}= 1";

            case 'jsonlength':
                $column = $this->db->escapeIdentifiers($where['column']);
                return "JSON_LENGTH({$column}) {$where['operator']} ?";

            case 'jsonsearch':
                $column = $this->db->escapeIdentifiers($where['column']);
                $not = $where['not'] ? 'NOT ' : '';
                return "JSON_SEARCH({$column}, 'one', ?) IS {$not}NULL";

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
                throw new InvalidArgumentException("Unknown where type: {$where['type']}");
        }
    }
}