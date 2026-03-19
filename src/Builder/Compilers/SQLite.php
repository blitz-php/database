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
use BlitzPHP\Database\Exceptions\DatabaseException;

class SQLite extends QueryCompiler
{
    /**
     * {@inheritDoc}
     */
    public function compileUpdate(BaseBuilder $builder): string
    {
        if ($builder->joins !== []) {
            throw new DatabaseException(
                'SQLite ne supporte pas les jointures dans les requêtes UPDATE. ' .
                'Utilisez des sous-requêtes à la place.',
            );
        }

        return $this->compileUpdateStandard($builder);
    }

    /**
     * {@inheritDoc}
     */
    protected function compileDistinct(bool|string $distinct): string
    {
        return $distinct ? 'DISTINCT' : '';
    }

    /**
     * {@inheritDoc}
     */
    protected function compileLock(?string $lock): string
    {
        return '';
    }

    /**
     * {@inheritDoc}
     */
    public function compileInsertion(string $table, string $columns, string $values, bool $ignore, ?string $returning = null): string
    {
        $ignored  = $ignore ? ' OR IGNORE' : '';
        $returned = $returning ? " RETURNING {$returning}" : '';

        return "INSERT{$ignored} INTO {$table} ({$columns}) VALUES {$values}{$returned}";
    }

    /**
     * {@inheritDoc}
     */
    public function compileTruncate(BaseBuilder $builder): string
    {
        $table = $this->db->escapeIdentifiers($builder->getTable());

        // SQLite n'a pas de TRUNCATE, on utilise DELETE
        $sql = "DELETE FROM {$table}";

        // Réinitialiser l'auto-increment
        $sql .= "; DELETE FROM sqlite_sequence WHERE name = '" . str_replace("'", "''", $builder->getTable()) . "'";

        return $sql;
    }

    /**
     * {@inheritDoc}
     */
    protected function compileReplacement(string $table, string $columns, string $values): string
    {
        return "INSERT OR REPLACE INTO {$table} ({$columns}) VALUES {$values}";
    }

    /**
     * {@inheritDoc}
     */
    protected function compileUpsertment(string $table, string $columns, string $values, BaseBuilder $builder): string
    {
        // SQLite supporte INSERT OR REPLACE comme UPSERT basique
        return "INSERT OR REPLACE INTO {$table} ({$columns}) VALUES {$values}";
    }

    /**
     * {@inheritDoc}
     */
    protected function compileJsonContains(string $column, $value, bool $not = false): string
    {
        // SQLite 3.38+ supporte JSON avec les opérateurs -> et ->>
        if (version_compare($this->db->getVersion(), '3.38', '>=')) {
            $column = $this->db->escapeIdentifiers($column);
            $notStr = $not ? 'NOT ' : '';

            // Utiliser json_each ou json_extract pour simuler JSON_CONTAINS
            return "json_each({$column}) IS {$notStr}NULL";
        }

        // Version plus ancienne, pas de support JSON
        return $not ? '0' : '1';
    }

    /**
     * {@inheritDoc}
     */
    protected function compileJsonContainsKey(string $column, bool $not = false): string
    {
        // SQLite utilise json_extract pour vérifier l'existence
        if (version_compare($this->db->getVersion(), '3.38', '>=')) {
            $column = $this->db->escapeIdentifiers($column);
            $notStr = $not ? 'NOT ' : '';

            return "json_extract({$column}, ?) IS {$notStr}NULL";
        }

        return $not ? '0' : '1';
    }

    /**
     * {@inheritDoc}
     */
    protected function compileJsonLength(string $column, string $operator, int $value): string
    {
        if (version_compare($this->db->getVersion(), '3.38', '>=')) {
            $column = $this->db->escapeIdentifiers($column);

            return "json_array_length({$column}) {$operator} ?";
        }

        return '1';
    }

    /**
     * {@inheritDoc}
     */
    protected function compileJsonSearch(string $column, string $value, bool $not = false): string
    {
        // SQLite n'a pas d'équivalent direct à JSON_SEARCH
        if (version_compare($this->db->getVersion(), '3.38', '>=')) {
            $column = $this->db->escapeIdentifiers($column);
            $notStr = $not ? 'NOT ' : '';

            // Utiliser json_each pour rechercher dans les tableaux
            return "EXISTS (SELECT 1 FROM json_each({$column}) WHERE value = ?) IS {$notStr}TRUE";
        }

        return $not ? '0' : '1';
    }

    /**
     * {@inheritDoc}
     */
    protected function compileAnyAll(string $type, string $column, string $operator, array $values): string
    {
        // SQLite ne supporte pas ANY/ALL, on simule avec IN/NOT IN
        $column       = $this->db->escapeIdentifiers($column);
        $placeholders = implode(', ', array_fill(0, count($values), '?'));

        if ($type === 'ANY') {
            return "{$column} {$operator} ({$placeholders})";
        }

        // Pour ALL, c'est plus complexe - on utilise une sous-requête
        return "NOT EXISTS (SELECT 1 WHERE {$column} NOT {$operator} ({$placeholders}))";
    }
}
