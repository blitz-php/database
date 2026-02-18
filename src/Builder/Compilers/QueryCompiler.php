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
use BlitzPHP\Database\Query\Expression;
use InvalidArgumentException;

abstract class QueryCompiler
{
    protected array $operators = [
        '%'  => 'LIKE',
        '!%' => 'NOT LIKE',
        '@'  => 'IN',
        '!@' => 'NOT IN',
    ];

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

            if ($parts !== []) {
                $parts[] = strtoupper($boolean);
            }

            if ($having['value'] instanceof Expression) {
                $parts[] = "{$having['column']} {$having['operator']} {$having['value']}";
            } else {
                $parts[] = "{$having['column']} {$having['operator']} ?";
            }
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
                $compiled[] = (string) $order['column'] . ' ' . $order['direction'];
            } else {
                $compiled[] = $this->db->escapeIdentifiers($order['column']) . ' ' . $order['direction'];
            }
        }

        return implode(', ', $compiled);
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

            if (!empty($parts)) {
                $parts[] = strtoupper($boolean);
            }

            $parts[] = $this->compileWhere($where);
        }

        return implode(' ', $parts);
    }

    /**
     * Compile les valeurs pour INSERT/UPDATE
     */
    public function compileValues(array $values): string
    {
        return '(' . implode(', ', array_map([$this, 'wrapValue'], $values)) . ')';
    }

    /**
     * Compile une clause WHERE ANY/ALL
     */
    public function compileAnyAll(string $type, string $column, string $operator, array $values): string
    {
        $column = $this->db->escapeIdentifiers($column);
        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        
        return "{$column} {$operator} {$type} ({$placeholders})";
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
     * Compile une clause JSON CONTAINS
     */
    public function compileJsonContains(string $column, $value, bool $not = false): string
    {
        $column = $this->db->escapeIdentifiers($column);
        $notStr = $not ? 'NOT ' : '';
        
        return "{$notStr}JSON_CONTAINS({$column}, ?)";
    }

    /**
     * Compile une clause JSON CONTAINS KEY
     */
    public function compileJsonContainsKey(string $column, bool $not = false): string
    {
        $column = $this->db->escapeIdentifiers($column);
        $notStr = $not ? 'NOT ' : '';
        
        return "JSON_CONTAINS_PATH({$column}, 'one', ?) {$notStr}= 1";
    }

    /**
     * Compile une clause JSON LENGTH
     */
    public function compileJsonLength(string $column, string $operator, int $value): string
    {
        $column = $this->db->escapeIdentifiers($column);
        
        return "JSON_LENGTH({$column}) {$operator} ?";
    }

    /**
     * Compile une clause JSON SEARCH
     */
    public function compileJsonSearch(string $column, string $value, bool $not = false): string
    {
        $column = $this->db->escapeIdentifiers($column);
        $notStr = $not ? 'NOT ' : '';
        
        return "JSON_SEARCH({$column}, 'one', ?) IS {$notStr}NULL";
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
        return $this->operators[$operator] ?? $operator;
    }

    /**
     * Compile une requête SELECT
     */
    abstract public function compileSelect(BaseBuilder $builder): string;

    /**
     * Compile une requête INSERT
     */
    abstract public function compileInsert(BaseBuilder $builder): string;

    /**
     * Compile une requête INSERT USING (INSERT INTO ... SELECT)
     */
    abstract public function compileInsertUsing(BaseBuilder $builder): string;

    /**
     * Compile une requête UPDATE
     */
    abstract public function compileUpdate(BaseBuilder $builder): string;

    /**
     * Compile une requête DELETE
     */
    abstract public function compileDelete(BaseBuilder $builder): string;

    /**
     * Compile une requête TRUNCATE
     */
    abstract public function compileTruncate(BaseBuilder $builder): string;

    /**
     * Compile une requête REPLACE
     */
    abstract public function compileReplace(BaseBuilder $builder): string;

    /**
     * Compile une requête UPSERT
     */
    abstract public function compileUpsert(BaseBuilder $builder): string;

    /**
     * Compile une condition WHERE individuelle
     */
    abstract public function compileWhere(array $where): string;
}