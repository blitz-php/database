<?php

/**
 * This file is part of Blitz PHP framework - Database Layer.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

 namespace BlitzPHP\Database\Connection;

use BlitzPHP\Database\Exceptions\DatabaseException;
use ErrorException;
use Exception;
use PDO;
use PDOException;
use SQLite3;
use stdClass;

/**
 * Connexion SQLite
 */
class SQLite extends BaseConnection
{
    /**
     * Pilote de la base de donnees
     */
    public string $driver = 'sqlite';

    /**
     * {@inheritDoc}
     */
    public string $escapeChar = '`';

    /**
     * Activr ou non les contraintes de cle primaire
     */
    protected bool $foreignKeys = false;

    /**
     * The milliseconds to sleep
     *
     * @var int|null milliseconds
     *
     * @see https://www.php.net/manual/en/sqlite3.busytimeout
     */
    protected ?int $busyTimeout = null;

    protected array $error = [
        'message' => '',
        'code'    => 0,
    ];


    public function initialize()
    {
        parent::initialize();

        if ($this->foreignKeys) {
            $this->enableForeignKeyChecks();
        }

        if (is_int($this->busyTimeout) && ! $this->isPdo()) {
            $this->conn->busyTimeout($this->busyTimeout);
        }
    }

    /**
     * Connect to the database.
     *
     * @return SQLite3|PDO
     *
     * @throws DatabaseException
     */
    public function connect(bool $persistent = false)
    {
        $db = null;

        if (! $this->isPdo()) {
            if ($persistent && $this->debug) {
                throw new DatabaseException('SQLite3 doesn\'t support persistent connections.');
            }
            try {    
                $db = (! $this->password)
                    ? new SQLite3($this->database)
                    : new SQLite3($this->database, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE, $this->password);
            } catch (Exception $e) {
                throw new DatabaseException('SQLite3 error: ' . $e->getMessage());
            }
        } else {
            $this->dsn = sprintf('sqlite:%s', $this->database);
            $db        = new PDO($this->dsn, $this->username, $this->password);
        }

        return self::pushConnection('sqlite', $this, $db);

        
    }
    
    /**
     * {@inheritDoc}
     */
    protected function _close()
    {
        if ($this->isPdo()) {
            return $this->conn = null;
        }

        $this->conn->close();
    }

    /**
     * {@inheritDoc}
     */
    public function setDatabase(string $databaseName): bool
    {
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function getPlatform(): string
    {
        if (isset($this->dataCache['platform'])) {
            return $this->dataCache['platform'];
        }

        if (empty($this->conn)) {
            $this->initialize();
        }

        return $this->dataCache['platform'] = ! $this->isPdo() ? 'sqlite' : $this->conn->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    /**
     * {@inheritDoc}
     */
    public function getVersion(): string
    {
        if (isset($this->dataCache['version'])) {
            return $this->dataCache['version'];
        }

        if (empty($this->conn) && $this->isPdo()) {
            $this->initialize();
        }

        return $this->dataCache['version'] = ! $this->isPdo() ? SQLite3::version()['versionString'] : $this->conn->getAttribute(PDO::ATTR_SERVER_VERSION);
    }

    /**
     * Execute the query
     *
     * @return mixed
     */
    protected function execute(string $sql, array $params = [])
    {
        $error  = null;
        $result = false;
        $time   = microtime(true);

        if (! $this->isPdo()) {
            try {
                $result = $this->isWriteType($sql)
                    ? $this->conn->exec($sql)
                    : $this->conn->query($sql);
            } catch (ErrorException $e) {
                $this->log((string) $e);
                $this->error['code']    = $e->getCode();
                $this->error['message'] = $error = $e->getMessage();
            }
        } else {
            try {
                $result = $this->conn->prepare($sql);

                if (! $result) {
                    $error = $this->conn->errorInfo();
                } else {
                    foreach ($params as $key => $value) {
                        $result->bindValue(
                            is_int($key) ? $key + 1 : $key,
                            $value,
                            is_int($value) || is_bool($value) ? PDO::PARAM_INT : PDO::PARAM_STR
                        );
                    }
                    $result->execute();
                }
            } catch (PDOException $e) {
                $this->log('Database: ' . (string) $e);
                $this->error['code']    = $e->getCode();
                $this->error['message'] = $error = $e->getMessage();
            }
        }

        if ($error !== null) {
            $error .= "\nSQL: " . $sql;

            throw new DatabaseException('Database error: ' . $error);
        }

        $this->lastQuery = [
            'sql'      => $sql,
            'start'    => $time,
            'duration' => microtime(true) - $time,
        ];
        $this->stats['queries'][] = &$this->lastQuery;

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function affectedRows(): int
    {
        if ($this->isPdo()) {
            return $this->result->rowCount();
        }

        return $this->conn->changes();
    }

    /**
     * {@inheritDoc}
     */
    public function numRows(): int
    {
        if (! $this->isPdo()) {
            return 0; // TODO
        }

        return $this->result->rowCount();
    }

    /**
     * {@inheritDoc}
     */
    protected function _escapeString(string $str): string
    {
        if (! $this->connID instanceof SQLite3) {
            $this->initialize();
        }

        if (! $this->isPdo()) {
            return $this->conn->escapeString($str);
        }

        return $this->conn->quote($str);
    }

    /**
     *{@inheritDoc}
     */
    protected function _listTables(bool $prefixLimit = false, ?string $tableName = null): string
    {
        if ($tableName !== null) {
            return 'SELECT "NAME" FROM "SQLITE_MASTER" WHERE "TYPE" = \'table\''
                   . ' AND "NAME" NOT LIKE \'sqlite!_%\' ESCAPE \'!\''
                   . ' AND "NAME" LIKE ' . $this->escape($tableName);
        }

        return 'SELECT "NAME" FROM "SQLITE_MASTER" WHERE "TYPE" = \'table\''
               . ' AND "NAME" NOT LIKE \'sqlite!_%\' ESCAPE \'!\''
               . (($prefixLimit !== false && $this->prefix !== '')
                    ? ' AND "NAME" LIKE \'' . $this->escapeLikeString($this->prefix) . '%\' ' . sprintf($this->likeEscapeStr, $this->likeEscapeChar)
                    : '');
    }

    /**
     * {@inheritDoc}
     */
    protected function _listColumns(string $table = ''): string
    {
        return 'PRAGMA TABLE_INFO(' . $this->protectIdentifiers($table, true, null, false) . ')';
    }

    /**
     * @return array|false
     *
     * @throws DatabaseException
     */
    public function getFieldNames(string $table)
    {
        // Is there a cached result?
        if (isset($this->dataCache['field_names'][$table])) {
            return $this->dataCache['field_names'][$table];
        }

        if (! $this->conn) {
            $this->initialize();
        }

        $sql = $this->_listColumns($table);

        $query                                  = $this->query($sql);
        $this->dataCache['field_names'][$table] = [];

        foreach ($query->resultArray() as $row) {
            // Do we know from where to get the column's name?
            if (! isset($key)) {
                if (isset($row['column_name'])) {
                    $key = 'column_name';
                } elseif (isset($row['COLUMN_NAME'])) {
                    $key = 'COLUMN_NAME';
                } elseif (isset($row['name'])) {
                    $key = 'name';
                } else {
                    // We have no other choice but to just get the first element's key.
                    $key = key($row);
                }
            }

            $this->dataCache['field_names'][$table][] = $row[$key];
        }

        return $this->dataCache['field_names'][$table];
    }

    /**
     * {@inheritDoc}
     *
     * @return stdClass[]
     *
     * @throws DatabaseException
     */
    protected function _fieldData(string $table): array
    {
        if (false === $query = $this->query('PRAGMA TABLE_INFO(' . $this->protectIdentifiers($table, true, null, false) . ')')) {
            throw new DatabaseException('No data fied found');
        }

        $query = $query->resultObject();

        if (empty($query)) {
            return [];
        }

        $retVal = [];

        for ($i = 0, $c = count($query); $i < $c; $i++) {
            $retVal[$i] = new stdClass();

            $retVal[$i]->name        = $query[$i]->name;
            $retVal[$i]->type        = $query[$i]->type;
            $retVal[$i]->max_length  = null;
            $retVal[$i]->default     = $query[$i]->dflt_value;
            $retVal[$i]->primary_key = isset($query[$i]->pk) && (bool) $query[$i]->pk;
            $retVal[$i]->nullable    = isset($query[$i]->notnull) && ! (bool) $query[$i]->notnull;
        }

        return $retVal;
    }

    /**
     * {@inheritDoc}
     *
     * @return stdClass[]
     *
     * @throws DatabaseException
     */
    protected function _indexData(string $table): array
    {
        $sql = "SELECT 'PRIMARY' as indexname, l.name as fieldname, 'PRIMARY' as indextype
                FROM pragma_table_info(" . $this->escape(strtolower($table)) . ") as l
                WHERE l.pk <> 0
                UNION ALL
                SELECT sqlite_master.name as indexname, ii.name as fieldname,
                CASE
                WHEN ti.pk <> 0 AND sqlite_master.name LIKE 'sqlite_autoindex_%' THEN 'PRIMARY'
                WHEN sqlite_master.name LIKE 'sqlite_autoindex_%' THEN 'UNIQUE'
                WHEN sqlite_master.sql LIKE '% UNIQUE %' THEN 'UNIQUE'
                ELSE 'INDEX'
                END as indextype
                FROM sqlite_master
                INNER JOIN pragma_index_xinfo(sqlite_master.name) ii ON ii.name IS NOT NULL
                LEFT JOIN pragma_table_info(" . $this->escape(strtolower($table)) . ") ti ON ti.name = ii.name
                WHERE sqlite_master.type='index' AND sqlite_master.tbl_name = " . $this->escape(strtolower($table)) . ' COLLATE NOCASE';

        if (($query = $this->query($sql)) === false) {
            throw new DatabaseException('No index data found');
        }
        $query = $query->resultObject();

        $tempVal = [];

        foreach ($query as $row) {
            if ($row->indextype === 'PRIMARY') {
                $tempVal['PRIMARY']['indextype']               = $row->indextype;
                $tempVal['PRIMARY']['indexname']               = $row->indexname;
                $tempVal['PRIMARY']['fields'][$row->fieldname] = $row->fieldname;
            } else {
                $tempVal[$row->indexname]['indextype']               = $row->indextype;
                $tempVal[$row->indexname]['indexname']               = $row->indexname;
                $tempVal[$row->indexname]['fields'][$row->fieldname] = $row->fieldname;
            }
        }

        $retVal = [];

        foreach ($tempVal as $val) {
            $obj                = new stdClass();
            $obj->name          = $val['indexname'];
            $obj->fields        = array_values($val['fields']);
            $obj->type          = $val['indextype'];
            $retVal[$obj->name] = $obj;
        }

        return $retVal;
    }

    /**
     * {@inheritDoc}
     *
     * @return stdClass[]
     */
    protected function _foreignKeyData(string $table): array
    {
        if ($this->supportsForeignKeys() !== true) {
            return [];
        }

        $query   = $this->query("PRAGMA foreign_key_list({$table})")->result(PDO::FETCH_OBJ);
        $indexes = [];

        foreach ($query as $row) {
            $indexes[$row->id]['constraint_name']       = null;
            $indexes[$row->id]['table_name']            = $table;
            $indexes[$row->id]['foreign_table_name']    = $row->table;
            $indexes[$row->id]['column_name'][]         = $row->from;
            $indexes[$row->id]['foreign_column_name'][] = $row->to;
            $indexes[$row->id]['on_delete']             = $row->on_delete;
            $indexes[$row->id]['on_update']             = $row->on_update;
            $indexes[$row->id]['match']                 = $row->match;
        }

        return $this->foreignKeyDataToObjects($indexes);
    }

    /**
     * {@inheritDoc}
     */
    protected function _disableForeignKeyChecks(): string
    {
        return 'PRAGMA foreign_keys = OFF';
    }

    /**
     * {@inheritDoc}
     */
    protected function _enableForeignKeyChecks(): string
    {
        return 'PRAGMA foreign_keys = ON';
    }

    /**
     * Returns the last error code and message.
     * Must return this format: ['code' => string|int, 'message' => string]
     * intval(code) === 0 means "no error".
     *
     * @return array<string, int|string>
     */
    public function error(): array
    {
        $code    = $this->error['code'] ?? 0;
        $message = $this->error['message'] ?? '';

        if (empty($message)) {
            $message = $this->isPdo()
                ? $this->conn->errorInfo()
                : $this->conn->lastErrorMsg();
        }

        return compact('code', 'message');
    }

    /**
     * Insert ID
     */
    public function insertID(): int
    {
        if (! $this->isPdo()) {
            return $this->conn->lastInsertRowID();
        }

        return $this->conn->lastInsertId();
    }

    /**
     * {@inheritDoc}
     */
    protected function _transBegin(): bool
    {
        if (! $this->isPdo()) {
            return $this->conn->exec('BEGIN TRANSACTION');
        }

        return $this->conn->beginTransaction();
    }

    /**
     * {@inheritDoc}
     */
    protected function _transCommit(): bool
    {
        if (! $this->isPdo()) {
            return $this->conn->exec('END TRANSACTION');
        }

        return $this->conn->commit();

    }

    /**
     * {@inheritDoc}
     */
    protected function _transRollback(): bool
    {
        if (! $this->isPdo()) {
            return $this->conn->exec('ROLLBACK');
        }

        return $this->conn->rollback();
    }

    /**
     * Checks to see if the current install supports Foreign Keys
     * and has them enabled.
     */
    public function supportsForeignKeys(): bool
    {
        $result = $this->simpleQuery('PRAGMA foreign_keys');

        return (bool) $result;
    }
}
