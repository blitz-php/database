<?php

/**
 * This file is part of Blitz PHP framework - Database Layer.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Database\Builder;

use BlitzPHP\Database\Exceptions\DatabaseException;

/**
 * Builder pour PostgreSQL
 */
class Postgre extends BaseBuilder
{
    /**
     * Mots cles pour ORDER BY random
     */
    protected array $randomKeyword = [
        'RANDOM()',
    ];

    /**
     * Specifie quelles requetes requetes sql
     * supportent l'option IGNORE.
     */
    protected array $supportedIgnoreStatements = [
        'insert' => 'ON CONFLICT DO NOTHING',
    ];

    /**
     * Verifie si l'option IGNORE est supporter par
     * le pilote de la base de donnees pour la requete specifiee.
     */
    protected function compileIgnore(string $statement): string
    {
        $sql = parent::compileIgnore($statement);

        if (! empty($sql)) {
            $sql = ' ' . trim($sql);
        }

        return $sql;
    }

    /**
     * {@inheritDoc}
     */
    public function orderBy(string|array $field, string $direction = 'ASC', bool $escape = true): self
    {
        if (is_array($field)) {
            foreach ($field as $key => $item) {
                if (is_string($key)) {
                    $direction = $item ?? $direction;
                    $item      = $key;
                }
                $this->orderBy($item, $direction, $escape);
            }

            return $this;
        }

        $direction = strtoupper(trim($direction));
        if ($direction === 'RANDOM') {
            if (ctype_digit($field)) {
                $orderBy = (float) ($field > 1 ? "0.{$field}" : $field);
            }

            if (is_float($orderBy)) {
                $this->db->simpleQuery("SET SEED {$orderBy}");
            }

            $orderBy   = $this->randomKeyword[0];
            $direction = '';
            $escape    = false;
        }

        return parent::orderBy($orderBy, $direction, $escape);
    }

    /**
     * {@inheritDoc}
     */
    public function increment(string $column, int $value = 1)
    {
        $column = $this->db->protectIdentifiers($column);

        $sql = $this->update([$column => "to_number({$column}, '9999999') + {$value}"], false, false)->sql(true);

        if (! $this->testMode) {
            $this->reset();

            return $this->db->query($sql, null, false);
        }

        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function decrement(string $column, int $value = 1)
    {
        $column = $this->db->protectIdentifiers($column);

        $sql = $this->update([$column => "to_number({$column}, '9999999') - {$value}"], false, false)->sql(true);

        if (! $this->testMode) {
            $this->reset();

            return $this->db->query($sql, null, false);
        }

        return true;
    }

    /**
     * {@inheritDoc}
     *
     * Compiles an replace into string and runs the query.
     * Because PostgreSQL doesn't support the replace into command,
     * we simply do a DELETE and an INSERT on the first key/value
     * combo, assuming that it's either the primary key or a unique key.
     */
    public function replace(array|object $data = [], bool $escape = true, bool $execute = true)
    {
        $this->crud = 'replace';

        $data = $this->objectToArray($data);

        if (empty($data) && empty($this->query_values)) {
            if (true === $execute) {
                throw new DatabaseException('You must use the "set" method to update an entry.');
            }

            return $this;
        }

        if (! empty($data)) {
            $this->set($data, null, $escape);
        }

        $table  = array_pop($this->table);
        $values = $this->query_values;

        $key   = array_key_first($values);
        $value = $values[$key];

        $builder = $this->db->table($table);
        $exists  = $builder->where($key, $value, true)->first();

        if (empty($exists) && $this->testMode) {
            $result = $this->insert([], $escape, false);
        } elseif (empty($exists)) {
            $result = $builder->insert(array_combine(
                array_values($this->query_keys),
                array_values($this->query_values)
            ), $escape);
        } elseif ($this->testMode) {
            $result = $this->where($key, $value, true)->update();
        } else {
            $keys = $this->query_keys;
            array_shift($values);
            array_shift($keys);

            $result = $builder->where($key, $value, true)->update(array_combine(
                array_values($keys),
                array_values($values)
            ), $escape);
        }

        unset($builder);
        $this->reset();

        return $result;
    }

    /**
     * {@inheritDoc}
     *
     * @throws DatabaseException
     */
    public function delete(?array $where = null, ?int $limit = null, bool $execute = true)
    {
        if (! empty($limit) || ! empty($this->limit)) {
            throw new DatabaseException('PostgreSQL does not allow LIMITs on DELETE queries.');
        }

        return parent::delete($where, $limit, $execute);
    }

    /**
     * {@inheritDoc}
     *
     * @throws DatabaseException
     */
    public function update(array|string|object $data = [], bool $escape = true, bool $execute = true)
    {
        if (! empty($this->limit)) {
            throw new DatabaseException('PostgreSQL does not allow LIMITs on UPDATE queries.');
        }

        return parent::update($data, $escape, $execute);
    }

    /**
     * {@inheritDoc}
     */
    protected function _truncateStatement(string $table): string
    {
        return 'TRUNCATE ' . $table . ' RESTART IDENTITY';
    }

    /**
     * {@inheritDoc}
     *
     * In PostgreSQL, the ILIKE operator will perform case insensitive
     * searches according to the current locale.
     *
     * @see https://www.postgresql.org/docs/9.2/static/functions-matching.html
     */
    protected function _likeStatement(string $column, $match, bool $not, bool $insensitiveSearch = false): array
    {
        return [
            $column = $this->db->escapeIdentifiers($column),
            $match,
            ($not === true ? 'NOT ' : '') . ($insensitiveSearch === true ? 'ILIKE' : 'LIKE'),
        ];
    }

    /**
     * Genere la chaine INSERT conformement a la plateforme
     *
     * @return string|string[]
     */
    protected function _insertStatement(string $table, string $keys, string $values)
    {
        return trim(sprintf(
            'INSERT INTO %s (%s) VALUES (%s) %s',
            $table,
            $keys,
            $values,
            $this->compileIgnore('insert')
        ));
    }

    /**
     * {@inheritDoc}
     */
    public function join(string $table, array|string $fields, string $type = 'INNER', bool $escape = false): self
    {
        if (! in_array('FULL OUTER', $this->joinTypes, true)) {
            $this->joinTypes = array_merge($this->joinTypes, ['FULL OUTER']);
        }

        return parent::join($table, $fields, $type, $escape);
    }
}
