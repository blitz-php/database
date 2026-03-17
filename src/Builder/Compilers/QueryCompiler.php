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
use BlitzPHP\Database\Connection\BaseConnection;
use BlitzPHP\Database\Exceptions\DatabaseException;
use BlitzPHP\Database\Query\Expression;
use BlitzPHP\Database\Utils;
use InvalidArgumentException;

abstract class QueryCompiler
{
    public function __construct(protected BaseConnection $db)
    {
    }
    
    /**
     * Compile la requête en fonction du type CRUD
     */
    public function compile(BaseBuilder $builder): string
    {
        return match($builder->crud) {
            'select'   => $this->compileSelect($builder),
            'insert'   => $this->compileInsert($builder),
            'update'   => $this->compileUpdate($builder),
            'delete'   => $this->compileDelete($builder),
            'truncate' => $this->compileTruncate($builder),
            'replace'  => $this->compileReplace($builder),
            'upsert'   => $this->compileUpsert($builder),
            default    => throw new InvalidArgumentException(sprintf('Unsupported CRUD operation: %s', $builder->crud))
        };
    }

    /**
     * Compile une requête SELECT
     */
    public function compileSelect(BaseBuilder $builder): string
    {
        $sql = ['SELECT'];

        if ('' !== $distinct = $this->compileDistinct($builder->distinct)) {
            $sql[] = $distinct;
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

        if ([] !== $builder->unions) {
            $sql[] = $this->compileUnions($builder->unions);
        }

        if ([] !== $builder->orders) {
            $sql[] = 'ORDER BY';
            $sql[] = $this->compileOrders($builder->orders);
        }

        if ('' !== $limit = $this->compileLimit($builder->limit, $builder->offset)) {
            $sql[] = $limit;
        }

        if ('' !== $lock = $this->compileLock($builder->lock)) {
            $sql[] = $lock;
        }

        return implode(' ', array_filter($sql));
    }

    /**
     * Compile une requête INSERT
     */
    public function compileInsert(BaseBuilder $builder): string
    {
        $table = $this->db->escapeIdentifiers($builder->getTable());
        
        // Récupérer la première ligne pour les colonnes
        $firstRow = $builder->values[0] ?? $builder->values;
        $columns = implode(', ', array_map([$this->db, 'escapeIdentifiers'], array_keys($firstRow)));
        
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

        return $this->compileInsertion($table, $columns, $values, $builder->ignore);
    }

    /**
     * Compile une requête INSERT USING (INSERT INTO ... SELECT)
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
     * Compile une requête UPDATE
     */
    public function compileUpdate(BaseBuilder $builder): string
    {
        return $this->compileUpdateStandard($builder);
    }

    /**
     * Compilation standard sans jointure
     */
    protected function compileUpdateStandard(BaseBuilder $builder): string
    {
        $table = $this->db->makeTableName($builder->getTable());
        
        $sql = ["UPDATE {$table}"];
        
        $sets = [];
        foreach ($builder->values as $column => $value) {
            $column = $this->db->escapeIdentifiers($column);
            $sets[] = "{$column} = " . $this->wrapValue($value);
        }
        
        if ([] !== $builder->joins) {
            // Si des jointures sont présentes mais non supportées, on lève une exception.
            throw new DatabaseException(
                "Les jointures dans UPDATE ne sont pas supportées par " . $this->db->getDriver()
            );
        }

        $sql[] = "SET " . implode(', ', $sets);

        if ([] !== $builder->wheres) {
            $sql[] = 'WHERE';
            $sql[] = $this->compileWheres($builder->wheres);
        }

        if ('' !== $limit = $this->compileLimit($builder->limit, null)) {
            $sql[] = $limit;
        }

        return implode(' ', array_filter($sql));
    }

    /**
     * Compile une requête DELETE
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

        if ('' !== $limit = $this->compileLimit($builder->limit, null)) {
            $sql[] = $limit;
        }

        return implode(' ', array_filter($sql));
    }

    /**
     * Compile une requête REPLACE
     */
    public function compileReplace(BaseBuilder $builder): string
    {
        $table = $this->db->escapeIdentifiers($builder->getTable());
        
        // Récupérer la première ligne pour les colonnes
        $firstRow = $builder->values[0] ?? $builder->values;
        $columns = implode(', ', array_map([$this->db, 'escapeIdentifiers'], array_keys($firstRow)));
        
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

        return $this->compileReplacement($table, $columns, $values);
    }

    /**
     * Compile une requête UPSERT
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

        return $this->compileUpsertment($table, $columns, $values, $builder);
    }

    /**
     * Compile les colonnes à sélectionner
     */
    public function compileColumns(array $columns): string
    {
        $compiled = [];

        foreach ($columns as $column) {
            if ($column instanceof Expression) {
                $compiled[] = (string) $column;
            } else {
                $compiled[] = $this->db->escapeIdentifiers($column);
            }
        }

        return implode(', ', $compiled);
    }

    /**
     * Compile les tables FROM
     */
    public function compileTables(array $tables): string
    {
        return implode(', ', $tables);
    }

    /**
     * Compile les jointures
     */
    public function compileJoins(array $joins): string
    {
        $compiled = [];

        foreach ($joins as $join) {
            if ($join instanceof JoinClause) {
                $compiled[] = $this->compileJoin($join);
            } else {
                $compiled[] = $join;
            }
        }

        return implode(' ', $compiled);
    }

    /**
     * Compile une jointure individuelle
     */
    public function compileJoin(JoinClause $join): string
    {
        $type = $join->getType();
        $table = $join->getTable();

        $sql = "{$type} JOIN {$table}";

        if ([] !== $conditions = $join->getConditions()) {
            $sql .= ' ON ';
            $sql .= $this->compileJoinConditions($conditions);
        }

        return $sql;
    }

    /**
     * Compile les GROUP BY
     */
    public function compileGroups(array $groups): string
    {
        return implode(', ', array_map([$this->db, 'escapeIdentifiers'], $groups));
    }

    /**
     * Compile les HAVING
     */
    public function compileHavings(array $havings): string
    {
        $parts = [];

        foreach ($havings as $having) {
            $boolean = $having['boolean'] ?? 'and';

            if ([] !== $parts) {
                $parts[] = strtoupper($boolean);
            }

            $parts[] = $this->compileWhere($having);
        }

        return implode(' ', $parts);
    }

    /**
     * Compile les ORDER BY
     */
    public function compileOrders(array $orders): string
    {
        $compiled = [];

        foreach ($orders as $order) {
            if ($order['column'] instanceof Expression) {
                $compiled[] = (string) $order['column'] . ' ' . trim($order['direction']);
            } else {
                $compiled[] = $this->db->escapeIdentifiers($order['column']) . ' ' . trim($order['direction']);
            }
        }
        
        return implode(', ', array_map('trim', $compiled));
    }

    /**
     * Compile la clause LIMIT
     */
    public function compileLimit(?int $limit, ?int $offset): string
    {
        if ($limit === null) {
            return '';
        }

        if ($offset !== null) {
            return "LIMIT {$limit} OFFSET {$offset}";
        }

        return "LIMIT {$limit}";
    }

    /**
     * Compile une requête UNION
     */
    public function compileUnions(array $unions): string
    {
        $compiled = [];

        foreach ($unions as $union) {
            $type = $union['all'] ? 'UNION ALL' : 'UNION';
            $compiled[] = $type . ' ' . $union['query']->toSql();
        }

        return implode(' ', $compiled);
    }

    /**
     * Compile les conditions WHERE
     */
    public function compileWheres(array $wheres): string
    {
        $parts = [];

        foreach ($wheres as $where) {
            $boolean = $where['boolean'] ?? 'and';

            if ([] !== $parts) {
                $parts[] = strtoupper($boolean);
            }

            $parts[] = $this->compileWhere($where);
        }

        return implode(' ', $parts);
    }

    /**
     * Compile une condition WHERE individuelle
     */
    protected function compileWhere(array $where): string
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

            case 'json':
            case 'jsonkey':
            case 'jsonlength':
            case 'jsonsearch':
                return $this->compileJsonWhere($where);

            case 'any':
            case 'all':
                return $this->compileAnyAll(
                    strtoupper($where['type']),
                    $where['column'],
                    $where['operator'],
                    $where['values']
                );

            default:
                throw new InvalidArgumentException("Unknown where type: {$where['type']}");
        }
    }

    protected function compileJsonWhere(array $where): string
    {
        switch ($where['type']) {
            case 'json':
                if ($where['operator'] === 'JSON_CONTAINS') {
                    return $this->compileJsonContains($where['column'], $where['value'], $where['not']);
                }
                return '';

            case 'jsonkey':
                return $this->compileJsonContainsKey($where['column'], $where['not']);

            case 'jsonlength':
                return $this->compileJsonLength($where['column'], $where['operator'], $where['value']);

            case 'jsonsearch':
                return $this->compileJsonSearch($where['column'], $where['value'], $where['not']);
            default:
                return '';
        }
    }

    /**
     * Compile les valeurs pour INSERT/UPDATE
     */
    public function compileValues(array $values): string
    {
        return '(' . implode(', ', array_map([$this, 'wrapValue'], $values)) . ')';
    }

    /**
     * Compile une clause WHERE BETWEEN COLUMNS
     */
    public function compileBetweenColumns(string $column, array $values, bool $not = false): string
    {
        $column = $this->db->escapeIdentifiers($column);
        $col1 = $this->db->escapeIdentifiers($values[0]);
        $col2 = $this->db->escapeIdentifiers($values[1]);
        $notStr = $not ? 'NOT ' : '';
        
        return "{$column} {$notStr}BETWEEN {$col1} AND {$col2}";
    }

    /**
     * Compile une clause WHERE VALUE BETWEEN
     */
    public function compileValueBetween($value, string $column1, string $column2, bool $not = false): string
    {
        $col1 = $this->db->escapeIdentifiers($column1);
        $col2 = $this->db->escapeIdentifiers($column2);
        $notStr = $not ? 'NOT ' : '';
        
        return "? {$notStr}BETWEEN {$col1} AND {$col2}";
    }

    /**
     * Compile les conditions d'une jointure
     */
    protected function compileJoinConditions(array $conditions): string
    {
        $parts = [];

        foreach ($conditions as $condition) {
            $boolean = $condition['boolean'] ?? 'and';

            if ($parts !== []) {
                $parts[] = strtoupper($boolean);
            }

            switch ($condition['type']) {
                case 'basic':
                    $parts[] = $this->db->escapeIdentifiers($condition['first']) . 
                              ' ' . $condition['operator'] . ' ' . 
                              $this->db->escapeIdentifiers($condition['second']);
                    break;

                case 'where':
                    $parts[] = $this->db->escapeIdentifiers($condition['first']) . 
                              ' ' . $condition['operator'] . ' ?';
                    break;

                case 'in':
                    $placeholders = implode(', ', array_fill(0, count($condition['values']), '?'));
                    $parts[] = $this->db->escapeIdentifiers($condition['column']) . 
                              ($condition['not'] ? ' NOT IN ' : ' IN ') . 
                              '(' . $placeholders . ')';
                    break;

                case 'null':
                    $parts[] = $this->db->escapeIdentifiers($condition['column']) . 
                              ($condition['not'] ? ' IS NOT NULL' : ' IS NULL');
                    break;

                case 'nested':
                    $nestedConditions = $this->compileJoinConditions($condition['join']->getConditions());
                    $parts[] = '(' . $nestedConditions . ')';
                    break;
            }
        }

        return implode(' ', $parts);
    }

    /**
     * Enveloppe une valeur pour le SQL
     */
    protected function wrapValue($value): string
    {
        if ($value instanceof Expression) {
            return (string) $value;
        }

        return '?';
    }

    /**
     * Traduit les opérateurs personnalisés
     */
    protected function translateOperator(string $operator): string
    {
        return Utils::translateOperator($operator);
    }

    /**
     * Compile la clause DISTINCT
     */
    abstract protected function compileDistinct(bool|string $distinct): string;
     
    /**
     * Compile la clause LOCK
     */
    abstract protected function compileLock(?string $lock): string;

    /**
     * Compile la clause INSERT INTO
     */
    abstract public function compileInsertion(string $table, string $columns, string $values, bool $ignore, ?string $returning = null): string;

    /**
     * Compile la clause REPLACE
     */
    abstract protected function compileReplacement(string $table, string $columns, string $values): string;

    /**
     * Compile la clause UPSERT
     */
    abstract protected function compileUpsertment(string $table, string $columns, string $values, BaseBuilder $builder): string;

    /**
     * Compile une requête TRUNCATE
     */
    abstract public function compileTruncate(BaseBuilder $builder): string;

    /**
     * Compile une clause JSON CONTAINS
     */
    abstract protected function compileJsonContains(string $column, $value, bool $not = false): string;
    
    /**
     * Compile une clause JSON CONTAINS KEY
     */
    abstract protected function compileJsonContainsKey(string $column, bool $not = false): string;

    /**
     * Compile une clause JSON LENGTH
     */
    abstract protected function compileJsonLength(string $column, string $operator, int $value): string;

    /**
     * Compile une clause JSON SEARCH
     */
    abstract protected function compileJsonSearch(string $column, string $value, bool $not = false): string;

    /**
     * Compile une clause WHERE ANY/ALL
     */
    abstract protected function compileAnyAll(string $type, string $column, string $operator, array $values): string;
}