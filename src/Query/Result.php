<?php

namespace BlitzPHP\Database\Query;

use BadMethodCallException;
use BlitzPHP\Contracts\Database\ResultInterface;
use BlitzPHP\Database\Connection\BaseConnection;
use BlitzPHP\Database\Utils;
use InvalidArgumentException;
use PDO;
use PDOStatement;
use stdClass;

class Result implements ResultInterface
{
    /**
     * Details de la requete
     * 
     * @var array{num_rows: int, affected_rows: int, insert_id: int}
     */
    private array $details = [
        'num_rows'      => 0,
        'affected_rows' => 0,
        'insert_id'     => -1,
    ];

    /**
     * Enrergistrement courant (lors de la recuperation d'un select)
     */
    private int $currentRow = 0;

    /**
     * Cache
     */
    private array $cache = [
        'column_names' => [],
        'column_data'  => [],
    ];

    private array $proxy = [
        'all'    => 'get',
        'one'    => 'first',
        'columnCount' => 'countColumn',
        'lastId' => 'insertID',
    ];

    public function __construct(protected BaseConnection $db, protected PDOStatement $statement, protected bool $success = true)
    {
        $db->triggerEvent($this, 'db:result');
    }

    /**
     * Recupere le code sql qui a conduit a ce resultat
     */
    public function sql(): string
    {
        return $this->statement->queryString;
    }

    /**
     * {@inheritDoc}
     */
    public function successful(): bool 
    {
        return $this->success;
    }

    /**
     * Détermine si la requête est une requête qui écrit des données en BD
     */
    public function isWritableQuery(): bool 
    {
        return Utils::isWritableSql($this->sql());
    }

    /**
     * {@inheritDoc}
     */
    public function first(int|string $type = PDO::FETCH_OBJ): mixed
    {
        if (in_array($type, ['array', PDO::FETCH_ASSOC])) {
            return $this->fetchAssoc();
        }

        if (in_array($type, ['object', PDO::FETCH_OBJ])) {
            return $this->fetchObject();
        }

        if (is_string($type) && class_exists($type)) {
            return $this->fetchObject($type);
        }
        
        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function last(int|string $type = PDO::FETCH_OBJ): mixed
    {
        $records = $this->get($type);

        return $records === [] ? null : $records[count($records) - 1];
    }

    /**
     * {@inheritDoc}
     */
    public function next(int|string $type = PDO::FETCH_OBJ): mixed
    {
        if ([] === $records = $this->get($type)) {
            return null;
        }

        return isset($records[$this->currentRow + 1]) ? $records[++$this->currentRow] : null;
    }

    /**
     * {@inheritDoc}
     */
    public function previous(int|string $type = PDO::FETCH_OBJ): mixed
    {
        if ([] === $records = $this->get($type)) {
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
    public function row(int $index, null|int|string $type = PDO::FETCH_OBJ): mixed
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
    public function countColumn(): int
    {
        return $this->statement->columnCount();
    }

    /**
     * {@inheritDoc}
     */
    public function columnNames(): array
    {
        if ($this->cache['column_names'] !== []) {
            return $this->cache['column_names'];
        }

        $count = $this->countColumn();
        $names = [];

        for ($i = 0; $i < $count; $i++) {
            $column = $this->statement->getColumnMeta($i);
            $names[] = $column['name'] ?? "column_{$i}";
        }

        $this->cache['column_names'] = $names;

        return $names;
    }

    /**
     * {@inheritDoc}
     */
    public function columnData(): array
    {
        if ($this->cache['column_data'] !== []) {
            return $this->cache['column_data'];
        }

        $count = $this->countColumn();
        $data = [];

        for ($i = 0; $i < $count; $i++) {
            $meta = $this->statement->getColumnMeta($i);
            
            $column            = new stdClass();
            $column->name      = $meta['name'] ?? "column_{$i}";
            $column->type      = $meta['native_type'] ?? 'unknown';
            $column->length    = $meta['len'] ?? null;
            $column->precision = $meta['precision'] ?? null;
            $column->flags     = $meta['flags'] ?? [];
            
            $data[] = $column;
        }

        $this->cache['column_data'] = $data;

        return $data;
    }

    /**
     * {@inheritDoc}
     */
    public function get(int|string $type = PDO::FETCH_OBJ): array
    {
        $data = is_string($type) ? $this->resultClass($type) : $this->result($type);
        
        $this->details['num_rows'] = count($data);

        return $data;
    }

    public function result(int $mode = PDO::FETCH_OBJ, ?string $className = null): array
    {
        if ($this->isWritableQuery()) {
            return [];
        }

        if ($mode === PDO::FETCH_CLASS) {
            $this->statement->setFetchMode($mode, $className);
        } else {
            $this->statement->setFetchMode($mode);
        }
        
        $data = $this->statement->fetchAll();
     
        $this->statement->closeCursor();

        return $data;
    }

    /**
     * {@inheritDoc}
     */
    public function resultObject(): array
    {
        return $this->result(PDO::FETCH_OBJ);
    }

    /**
     * {@inheritDoc}
     */
    public function resultArray(): array
    {
        return $this->result(PDO::FETCH_ASSOC);
    }

    public function resultClass(string $className): array
    {
        if (! class_exists($className)) {
            throw new InvalidArgumentException("La classe {$className} n'existe pas");
        }

        return $this->result(PDO::FETCH_CLASS, $className);
    }

    /**
     * Returns the result set as an array.
     *
     * @return mixed
     */
    protected function fetchAssoc()
    {
        if ($this->isWritableQuery()) {
            return null;
        }

        return $this->statement->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Returns the result set as an object.
     *
     * @return ?object
     */
    protected function fetchObject(string $className = 'stdClass')
    {
        if ($this->isWritableQuery()) {
            return null;
        }
        
        $this->statement->setFetchMode(PDO::FETCH_CLASS, $className);

        return $this->statement->fetch();
    }

    /**
     * Recupere les details de la requete courrante
     */
    public function details(): array
    {
        return $this->details = [
            'affected_rows' => $this->affectedRows(),
            'num_rows'      => $this->numRows(),
            'insert_id'     => $this->insertID(),
            'sql'           => $this->sql(),
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function affectedRows(): int
    {
        return $this->success ? $this->statement->rowCount() : 0;
    }

    /**
     * {@inheritDoc}
     */
    public function numRows(): int
    {
        if ($this->details['num_rows'] === 0) {
            return $this->statement->rowCount();
        }

        return $this->details['num_rows'];
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

    public function __call(string $name, array $arguments): mixed
    {
        if (isset($this->proxy[$name])) {
            return $this->{$this->proxy[$name]}(...$arguments);
        }

        throw new BadMethodCallException("Méthode {$name} non trouvée");
    }
}