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

class MySQL extends QueryCompiler
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
    public function compileInsertion(string $table, string $columns, string $values, bool $ignore, ?string $returning = null): string
    {
        $ignored  = $ignore ? ' IGNORE' : '';
        $returned = $returning ? " RETURNING {$returning}" : '';

        return "INSERT{$ignored} INTO {$table} ({$columns}) VALUES {$values}{$returned}";
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
    protected function compileReplacement(string $table, string $columns, string $values): string
    {
        return "REPLACE INTO {$table} ({$columns}) VALUES {$values}";
    }

    /**
     * {@inheritDoc}
     */
    protected function compileUpsertment(string $table, string $columns, string $values, BaseBuilder $builder): string
    {
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
    protected function compileJsonContains(string $column, $value, bool $not = false): string
    {
        $column = $this->db->escapeIdentifiers($column);
        $notStr = $not ? 'NOT ' : '';
        
        return "{$notStr}JSON_CONTAINS({$column}, ?)";
    }

    /**
     * {@inheritDoc}
     */
    protected function compileJsonContainsKey(string $column, bool $not = false): string
    {
        $column = $this->db->escapeIdentifiers($column);
        $notStr = $not ? 'NOT ' : '';
        
        return "JSON_CONTAINS_PATH({$column}, 'one', ?) {$notStr}= 1";
    }

    /**
     * {@inheritDoc}
     */
    protected function compileJsonLength(string $column, string $operator, int $value): string
    {
        $column = $this->db->escapeIdentifiers($column);
        
        return "JSON_LENGTH({$column}) {$operator} ?";
    }

    /**
     * {@inheritDoc}
     */
    public function compileJsonSearch(string $column, string $value, bool $not = false): string
    {
        $column = $this->db->escapeIdentifiers($column);
        $notStr = $not ? 'NOT ' : '';
        
        return "JSON_SEARCH({$column}, 'one', ?) IS {$notStr}NULL";
    }

    /**
     * {@inheritDoc}
     */
    protected function compileAnyAll(string $type, string $column, string $operator, array $values): string
    {
        $column = $this->db->escapeIdentifiers($column);
        $placeholders = implode(', ', array_fill(0, count($values), '?'));
        
        return "{$column} {$operator} {$type} ({$placeholders})";
    }
}