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
use PDO;
use PDOException;
use stdClass;
use Stringable;

/**
 * Connexion pour PostgreSQL
 */
class Postgre extends BaseConnection
{
    /**
     * Pilote de la base de donnees
     */
    public string $driver = 'postgre';

    /**
     * Schema de la base de donnees
     */
    public string $schema = 'public';

    /**
     * Caractere d'echapement des identifiant
     */
    public string $escapeChar = '"';

    protected $connect_timeout;
    protected $options;
    protected $sslmode;
    protected $service;
    protected array $error = [
        'message' => '',
        'code'    => 0,
    ];

    /**
     * {@inheritDoc}
     *
     * @return false|resource
     * @phpstan-return false|PgSqlConnection
     */
    public function connect(bool $persistent = false)
    {
        if (empty($this->dsn)) {
            $this->buildDSN();
        }

        // Strip pgsql if exists
        if (mb_strpos($this->dsn, 'pgsql:') === 0) {
            $this->dsn = mb_substr($this->dsn, 6);
        }

        // Convert semicolons to spaces.
        $this->dsn = str_replace(';', ' ', $this->dsn);

        $db = null;

        if ($this->isPdo()) {
            $this->dsn = true === $this->withDatabase ? sprintf(
                'pgsql:host=%s;port=%d;dbname=%s',
                $this->hostname,
                $this->port,
                $this->database
            ) : sprintf(
                'pgsql:host=%s;port=%d',
                $this->hostname,
                $this->port
            );

            $db = new PDO($this->dsn, $this->username, $this->password);
        } else {
            $db = $persistent === true ? pg_pconnect($this->dsn) : pg_connect($this->dsn);

            if ($db !== false) {
                if ($persistent === true && pg_connection_status($db) === PGSQL_CONNECTION_BAD && pg_ping($db) === false) {
                    return false;
                }

                if (! empty($this->schema)) {
                    pg_query($db, "SET search_path TO {$this->schema},public");
                }

                if ($this->setClientEncoding($this->charset, $db) === false) {
                    return false;
                }
            }
        }

        if (! empty($this->charset)) {
            $this->commands[] = "SET NAMES '{$this->charset}'";
        }

        return self::pushConnection('pgsql', $this, $db);
    }

    /**
     * {@inheritDoc}
     */
    public function reconnect()
    {
        if ($this->isPdo()) {
            parent::reconnect();
        } elseif (pg_ping($this->conn) === false) {
            $this->conn = false;
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function _close()
    {
        if ($this->isPdo()) {
            return $this->conn = null;
        }
        pg_close($this->conn);
    }

    /**
     * {@inheritDoc}
     */
    public function setDatabase(string $databaseName): bool
    {
        return false;
    }

    /**
     * The name of the platform in use (MySQLi, mssql, etc)
     */
    public function getPlatform(): string
    {
        if (isset($this->dataCache['platform'])) {
            return $this->dataCache['platform'];
        }

        if (empty($this->conn)) {
            $this->initialize();
        }

        return $this->dataCache['platform'] = ! $this->isPdo() ? 'postgres' : $this->conn->getAttribute(PDO::ATTR_DRIVER_NAME);
    }

    /**
     * {@inheritDoc}
     */
    public function getVersion(): string
    {
        if (isset($this->dataCache['version'])) {
            return $this->dataCache['version'];
        }

        if (empty($this->conn) || (! $this->isPdo() && ($pgVersion = pg_version($this->conn)) === false)) {
            $this->initialize();
        }

        return $this->dataCache['version'] = ! $this->isPdo()
            ? ($pgVersion['server'] ?? false)
            : $this->conn->getAttribute(PDO::ATTR_CLIENT_VERSION);
    }

    /**
     * {@inheritDoc}
     *
     * @phpstan-return false|PgSqlResult
     */
    public function execute(string $sql, array $params = [])
    {
        $error  = null;
        $result = false;
        $time   = microtime(true);

        if (! $this->isPdo()) {
            try {
                $result = pg_query($this->conn, $sql);
            } catch (ErrorException $e) {
                if ($this->logger) {
                    $this->logger->error('Database: ' . (string) $e);
                }
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
                if ($this->logger) {
                    $this->logger->error('Database: ' . (string) $e);
                }
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
    protected function getDriverFunctionPrefix(): string
    {
        return 'pg_';
    }

    /**
     * {@inheritDoc}
     */
    public function affectedRows(): int
    {
        if ($this->isPdo()) {
            return $this->result->rowCount();
        }

        return pg_affected_rows($this->result);
    }

    /**
     * {@inheritDoc}
     */
    public function numRows(): int
    {
        if ($this->isPdo()) {
            return $this->result->rowCount();
        }

        return pg_num_rows($this->result);
    }

    /**
     * "Smart" Escape String
     *
     * Escapes data based on type
     *
     * @param array|bool|float|int|object|string|null $str
     *
     * @return array|float|int|string
     * @phpstan-return ($str is array ? array : float|int|string)
     */
    public function escape($str)
    {
        if (! $this->conn) {
            $this->initialize();
        }

        if ($str instanceof Stringable) {
            $str = (string) $str;
        }

        if (is_string($str) && ! $this->isPdo()) {
            return pg_escape_literal($this->conn, $str);
        }

        if (is_bool($str)) {
            return $str ? 'TRUE' : 'FALSE';
        }

        /** @psalm-suppress NoValue I don't know why ERROR. */
        return parent::escape($str);
    }

    /**
     * {@inheritDoc}
     */
    protected function _escapeString(string $str): string
    {
        if (is_bool($str)) {
            return $str;
        }

        if (! $this->conn) {
            $this->initialize();
        }

        if (! $this->isPdo()) {
            return pg_escape_string($this->conn, $str);
        }

        return $this->conn->quote($str);
    }

    /**
     * {@inheritDoc}
     *
     * @param string|null $tableName If $tableName is provided will return only this table if exists.
     */
    protected function _listTables(bool $prefixLimit = false, ?string $tableName = null): string
    {
        $sql = 'SELECT "table_name" FROM "information_schema"."tables" WHERE "table_schema" = \'' . $this->schema . "'";

        if ($tableName !== null) {
            return $sql . ' AND "table_name" LIKE ' . $this->escape($tableName);
        }

        if ($prefixLimit !== false && $this->prefix !== '') {
            return $sql . ' AND "table_name" LIKE \''
                . $this->escapeLikeString($this->prefix) . "%' "
                . sprintf($this->likeEscapeStr, $this->likeEscapeChar);
        }

        return $sql;
    }

    /**
     * {@inheritDoc}
     */
    protected function _listColumns(string $table = ''): string
    {
        return 'SELECT "column_name"
			FROM "information_schema"."columns"
			WHERE LOWER("table_name") = '
                . $this->escape(strtolower($this->prefix . $table))
                . ' ORDER BY "ordinal_position"';
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
        $sql = 'SELECT "column_name", "data_type", "character_maximum_length", "numeric_precision", "column_default",  "is_nullable"
			FROM "information_schema"."columns"
			WHERE LOWER("table_name") = '
                . $this->escape(strtolower($this->prefix . $table))
                . ' ORDER BY "ordinal_position"';

        if (($query = $this->query($sql)) === false) {
            throw new DatabaseException('No data fied found');
        }
        $query = $query->resultObject();

        $retVal = [];

        for ($i = 0, $c = count($query); $i < $c; $i++) {
            $retVal[$i] = new stdClass();

            $retVal[$i]->name       = $query[$i]->column_name;
            $retVal[$i]->type       = $query[$i]->data_type;
            $retVal[$i]->nullable   = $query[$i]->is_nullable === 'YES';
            $retVal[$i]->default    = $query[$i]->column_default;
            $retVal[$i]->max_length = $query[$i]->character_maximum_length > 0 ? $query[$i]->character_maximum_length : $query[$i]->numeric_precision;
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
        $sql = 'SELECT "indexname", "indexdef"
			FROM "pg_indexes"
			WHERE LOWER("tablename") = ' . $this->escape(strtolower($this->prefix . $table)) . '
			AND "schemaname" = ' . $this->escape('public');

        if (($query = $this->query($sql)) === false) {
            throw new DatabaseException('No index data found');
        }

        $query = $query->resultObject();

        $retVal = [];

        foreach ($query as $row) {
            $obj         = new stdClass();
            $obj->name   = $row->indexname;
            $_fields     = explode(',', preg_replace('/^.*\((.+?)\)$/', '$1', trim($row->indexdef)));
            $obj->fields = array_map(static fn ($v) => trim($v), $_fields);

            if (str_starts_with($row->indexdef, 'CREATE UNIQUE INDEX pk')) {
                $obj->type = 'PRIMARY';
            } else {
                $obj->type = (str_starts_with($row->indexdef, 'CREATE UNIQUE')) ? 'UNIQUE' : 'INDEX';
            }

            $retVal[$obj->name] = $obj;
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
    protected function _foreignKeyData(string $table): array
    {
        $sql = 'SELECT c.constraint_name,
                x.table_name,
                x.column_name,
                y.table_name as foreign_table_name,
                y.column_name as foreign_column_name,
                c.delete_rule,
                c.update_rule,
                c.match_option
                FROM information_schema.referential_constraints c
                JOIN information_schema.key_column_usage x
                    on x.constraint_name = c.constraint_name
                JOIN information_schema.key_column_usage y
                    on y.ordinal_position = x.position_in_unique_constraint
                    and y.constraint_name = c.unique_constraint_name
                WHERE x.table_name = ' . $this->escape($this->prefix . $table) .
                'order by c.constraint_name, x.ordinal_position';

        if (($query = $this->query($sql)) === false) {
            throw new DatabaseException('No foreign keys found for table ' . $table);
        }

        $query   = $query->resultObject();
        $indexes = [];

        foreach ($query as $row) {
            $indexes[$row->constraint_name]['constraint_name']       = $row->constraint_name;
            $indexes[$row->constraint_name]['table_name']            = $table;
            $indexes[$row->constraint_name]['column_name'][]         = $row->column_name;
            $indexes[$row->constraint_name]['foreign_table_name']    = $row->foreign_table_name;
            $indexes[$row->constraint_name]['foreign_column_name'][] = $row->foreign_column_name;
            $indexes[$row->constraint_name]['on_delete']             = $row->delete_rule;
            $indexes[$row->constraint_name]['on_update']             = $row->update_rule;
            $indexes[$row->constraint_name]['match']                 = $row->match_option;
        }

        return $this->foreignKeyDataToObjects($indexes);
    }

    /**
     * {@inheritDoc}
     */
    protected function _disableForeignKeyChecks(): string
    {
        return 'SET CONSTRAINTS ALL DEFERRED';
    }

    /**
     * {@inheritDoc}
     */
    protected function _enableForeignKeyChecks(): string
    {
        return 'SET CONSTRAINTS ALL IMMEDIATE;';
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
                : (pg_last_error($this->conn) ?: '');
        }

        return compact('code', 'message');
    }

    /**
     * {@inheritDoc}
     */
    public function insertID(?string $table = null)
    {
        if ($this->isPdo()) {
            return $this->conn->lastInsertId($table);
        }

        $v = pg_version($this->connID);
        // 'server' key is only available since PostgreSQL 7.4
        $v = explode(' ', $v['server'])[0] ?? 0;

        $column = func_num_args() > 1 ? func_get_arg(1) : null;

        if ($table === null && $v >= '8.1') {
            $sql = 'SELECT LASTVAL() AS ins_id';
        } elseif ($table !== null) {
            if ($column !== null && $v >= '8.0') {
                $sql   = "SELECT pg_get_serial_sequence('{$table}', '{$column}') AS seq";
                $query = $this->query($sql);
                $query = $query->first();
                $seq   = $query->seq;
            } else {
                // seq_name passed in table parameter
                $seq = $table;
            }

            $sql = "SELECT CURRVAL('{$seq}') AS ins_id";
        } else {
            return pg_last_oid($this->resultID);
        }

        $query = $this->query($sql);
        $query = $query->first();

        return (int) $query->ins_id;
    }

    /**
     * Build a DSN from the provided parameters
     */
    protected function buildDSN()
    {
        if ($this->dsn !== '') {
            $this->dsn = '';
        }

        // If UNIX sockets are used, we shouldn't set a port
        if (str_contains($this->hostname, '/')) {
            $this->port = '';
        }

        if ($this->hostname !== '') {
            $this->dsn = "host={$this->hostname} ";
        }

        // ctype_digit only accepts strings
        $port = (string) $this->port;

        if ($port !== '' && ctype_digit($port)) {
            $this->dsn .= "port={$port} ";
        }

        if ($this->username !== '') {
            $this->dsn .= "user={$this->username} ";

            // An empty password is valid!
            // password must be set to null to ignore it.
            if ($this->password !== null) {
                $this->dsn .= "password='{$this->password}' ";
            }
        }

        if ($this->database !== '' && $this->withDatabase) {
            $this->dsn .= "dbname={$this->database} ";
        }

        // We don't have these options as elements in our standard configuration
        // array, but they might be set by parse_url() if the configuration was
        // provided via string> Example:
        //
        // Postgre://username:password@localhost:5432/database?connect_timeout=5&sslmode=1
        foreach (['connect_timeout', 'options', 'sslmode', 'service'] as $key) {
            if (isset($this->{$key}) && is_string($this->{$key}) && $this->{$key} !== '') {
                $this->dsn .= "{$key}='{$this->{$key}}' ";
            }
        }

        $this->dsn = rtrim($this->dsn);
    }

    /**
     * Set client encoding
     *
     * @param mixed|null $db
     */
    protected function setClientEncoding(string $charset, &$db = null): bool
    {
        if (! $this->conn && ! $db) {
            return false;
        }

        return pg_set_client_encoding(
            $this->conn === null ? $db : $this->conn,
            $charset
        ) === 0;
    }

    /**
     * Begin Transaction
     */
    protected function _transBegin(): bool
    {
        if (! $this->isPdo()) {
            return (bool) pg_query($this->conn, 'BEGIN');
        }

        return $this->conn->beginTransaction();
    }

    /**
     * Commit Transaction
     */
    protected function _transCommit(): bool
    {
        if (! $this->isPdo()) {
            return (bool) pg_query($this->conn, 'COMMIT');
        }

        return $this->conn->commit();
    }

    /**
     * Rollback Transaction
     */
    protected function _transRollback(): bool
    {
        if (! $this->isPdo()) {
            return (bool) pg_query($this->conn, 'ROLLBACK');
        }

        return $this->conn->rollback();
    }

    /**
     * Determines if a query is a "write" type.
     *
     * Overrides BaseConnection::isWriteType, adding additional read query types.
     *
     * @param mixed $sql
     */
    public function isWriteType($sql): bool
    {
        if (preg_match('#^(INSERT|UPDATE).*RETURNING\s.+(\,\s?.+)*$#is', $sql)) {
            return false;
        }

        return parent::isWriteType($sql);
    }
}
