<?php

/**
 * This file is part of Blitz PHP framework - Database Layer.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Database\Result;

use BlitzPHP\Contracts\Database\ResultInterface;
use BlitzPHP\Database\Connection\BaseConnection;
use PDO;
use PDOStatement;

abstract class BaseResult implements ResultInterface
{
    /**
     * Details de la requete
     */
    private array $details = [
        'num_rows'      => 0,
        'affected_rows' => 0,
        'insert_id'     => -1,
    ];

    /**
     * Resultat de la requete
     *
     * @var object|PDOStatement|resource
     */
    protected $query;

    /**
     * Instace BaseConnection
     */
    protected BaseConnection $db;

    /**
     * Enrergistrement courant (lors de la recuperation d'un select)
     */
    private int $currentRow = 0;

    /**
     * Constructor
     *
     * @param object|resource $query
     */
    public function __construct(BaseConnection &$db, &$query)
    {
        $this->query = &$query;
        $this->db    = &$db;

        $db->triggerEvent($this, 'db:result');
    }

    /**
     * Verifie si on utilise un objet pdo pour la connexion à la base de donnees
     */
    protected function isPdo(): bool
    {
        return $this->db->isPdo();
    }

    /**
     * Recupere le code sql qui a conduit a ce resultat
     */
    public function sql(): string
    {
        if ($this->isPdo()) {
            return $this->query->queryString;
        }

        return '';
    }

    /**
     * Fetch multiple rows from a select query.
     *
     * @alias self::result()
     */
    public function all(null|int|string $type = PDO::FETCH_OBJ): array
    {
        return $this->result($type);
    }

    /**
     * {@inheritDoc}
     */
    public function first(null|int|string $type = PDO::FETCH_OBJ)
    {
        $records = $this->result($type);

        return empty($records) ? null : $records[0];
    }

    /**
     * Recupere le premier resultat d'une requete en BD
     *
     * @return mixed
     *
     * @alias self::first()
     */
    public function one(null|int|string $type = PDO::FETCH_OBJ)
    {
        return $this->first($type);
    }

    /**
     * {@inheritDoc}
     */
    public function last(null|int|string $type = PDO::FETCH_OBJ)
    {
        $records = $this->all($type);

        if (empty($records)) {
            return null;
        }

        return $records[count($records) - 1];
    }

    /**
     * {@inheritDoc}
     */
    public function next(null|int|string $type = PDO::FETCH_OBJ)
    {
        $records = $this->result($type);

        if (empty($records)) {
            return null;
        }

        return isset($records[$this->currentRow + 1]) ? $records[++$this->currentRow] : null;
    }

    /**
     * {@inheritDoc}
     */
    public function previous(null|int|string $type = PDO::FETCH_OBJ)
    {
        $records = $this->result($type);

        if (empty($records)) {
            return null;
        }

        if (isset($records[$this->currentRow - 1])) {
            $this->currentRow--;
        }

        return $records[$this->currentRow];
    }

    /**
     * {@inheritDoc}
     */
    public function row(int $index, null|int|string $type = PDO::FETCH_OBJ)
    {
        $records = $this->result($type);

        if (empty($records[$index])) {
            return null;
        }

        return $records[$this->currentRow = $index];
    }

    /**
     * {@inheritDoc}
     */
    public function countField(): int
    {
        if ($this->isPdo()) {
            return $this->query->columnCount();
        }

        return $this->_countField();
    }

    /**
     * {@inheritDoc}
     */
    public function result(null|int|string $type = PDO::FETCH_OBJ): array
    {
        if (null === $type) {
            $type = PDO::FETCH_OBJ;
        }

        $data = [];

        if ($type === PDO::FETCH_OBJ || $type === 'object') {
            $data = $this->resultObject();
        } elseif ($type === PDO::FETCH_ASSOC || $type === 'array') {
            $data = $this->resultArray();
        } elseif (is_int($type) && $this->isPdo()) {
            $this->query->setFetchMode($type);
            $data = $this->query->fetchAll();
            $this->query->closeCursor();
        } elseif (is_string($type)) {
            if (is_subclass_of($type, Entity::class)) {
                $records = $this->resultArray();

                foreach ($records as $key => $value) {
                    if (! isset($data[$key])) {
                        // $data[$key] = Hydrator::hydrate($value, $type);
                    }
                }
            } elseif ($this->isPdo()) {
                $this->query->setFetchMode(PDO::FETCH_CLASS, $type);
                $data = $this->query->fetchAll();
                $this->query->closeCursor();
            } else {
                $data = $this->_result($type);
            }
        }

        $this->details['num_rows'] = count($data);

        return $data;
    }

    /**
     * {@inheritDoc}
     */
    public function resultObject(): array
    {
        if ($this->isPdo()) {
            $data = $this->query->fetchAll(PDO::FETCH_OBJ);
            $this->query->closeCursor();

            return $data;
        }

        return $this->_resultObject();
    }

    /**
     * {@inheritDoc}
     */
    public function resultArray(): array
    {
        if ($this->isPdo()) {
            $data = $this->query->fetchAll(PDO::FETCH_ASSOC);
            $this->query->closeCursor();

            return $data;
        }

        return $this->_resultArray();
    }

    /**
     * {@inheritDoc}
     */
    public function unbufferedRow($type = PDO::FETCH_OBJ)
    {
        if ($type === 'array' || $type === PDO::FETCH_ASSOC) {
            return $this->fetchAssoc();
        }

        if ($type === 'object' || $type === PDO::FETCH_OBJ) {
            return $this->fetchObject();
        }

        return $this->fetchObject($type);
    }

    /**
     * Returns the result set as an array.
     *
     * @return mixed
     */
    protected function fetchAssoc()
    {
        if ($this->isPdo()) {
            return $this->query->fetch(PDO::FETCH_ASSOC);
        }

        return $this->_fetchAssoc();
    }

    /**
     * Returns the result set as an object.
     *
     * @return object
     */
    protected function fetchObject(string $className = 'stdClass')
    {
        if (is_subclass_of($className, Entity::class)) {
            return empty($data = $this->fetchAssoc()) ? false : (new $className())->setAttributes($data);
        }

        if ($this->isPdo()) {
            $this->query->setFetchMode(PDO::FETCH_CLASS, $className);

            return $this->query->fetch();
        }

        return $this->_fetchObject($className);
    }

    /**
     * {@inheritDoc}
     */
    public function freeResult()
    {
        if ($this->isPdo()) {
            return;
        }

        $this->_freeResult();
    }

    /**
     * Recupere les details de la requete courrante
     */
    public function details(): array
    {
        if (! $this->query) {
            return $this->details;
        }

        $last = $this->db->getLastQuery();

        return $this->details = array_merge((array) $last, [
            'affected_rows' => $this->affectedRows(),
            'num_rows'      => $this->numRows(),
            'insert_id'     => $this->insertID(),
            'sql'           => $this->sql(),
        ]);
    }

    /**
     * Returns the total number of rows affected by this query.
     */
    public function affectedRows(): int
    {
        return $this->db->affectedRows();
    }

    /**
     * Returns the number of rows in the result set.
     */
    public function numRows(): int
    {
        return $this->db->numRows();
    }

    /**
     * Return the last id generated by autoincrement
     *
     * @return int|string
     */
    public function insertID()
    {
        return $this->db->insertID();
    }

    /**
     * Return the last id generated by autoincrement
     *
     * @alias self::insertID()
     *
     * @return int|null
     */
    public function lastId()
    {
        return $this->insertID();
    }

    protected function _resultObject(): array
    {
        return array_map(static fn ($data) => (object) $data, $this->resultArray());
    }

    /**
     * Returns the result set as an array.
     *
     * Overridden by driver classes.
     *
     * @return mixed
     */
    abstract protected function _fetchAssoc();

    /**
     * Returns the result set as an object.
     *
     * Overridden by child classes.
     *
     * @return object
     */
    abstract protected function _fetchObject(string $className = 'stdClass');

    /**
     * Gets the number of fields in the result set.
     */
    abstract protected function _countField(): int;

    abstract protected function _result($type): array;

    /**
     * Retourne une table contenant les resultat de la requete sous forme de tableau associatif
     */
    abstract protected function _resultArray(): array;

    /**
     * Frees the current result.
     */
    abstract protected function _freeResult();
}
