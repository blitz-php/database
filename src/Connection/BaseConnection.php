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

use BlitzPHP\Contracts\Database\ConnectionInterface;
use BlitzPHP\Database\Builder\BaseBuilder;
use BlitzPHP\Database\Exceptions\DatabaseException;
use BlitzPHP\Database\Query;
use BlitzPHP\Database\Result\BaseResult;
use BlitzPHP\Utilities\Helpers;
use Closure;
use Exception;
use PDO;
use PDOStatement;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use stdClass;
use Stringable;
use Throwable;

/**
 * @property array      $aliasedTables
 * @property string     $charset
 * @property string     $collation
 * @property bool       $compress
 * @property float      $connectDuration
 * @property float      $connectTime
 * @property string     $database
 * @property bool       $debug
 * @property string     $driver
 * @property string     $dsn
 * @property mixed      $encrypt
 * @property array      $failover
 * @property string     $hostname
 * @property mixed      $lastQuery
 * @property string     $password
 * @property bool       $persistent
 * @property int|string $port
 * @property string     $prefix
 * @property bool       $pretend
 * @property string     $queryClass
 * @property array      $reservedIdentifiers
 * @property bool       $strictOn
 * @property string     $subdriver
 * @property string     $swapPre
 * @property int        $transDepth
 * @property bool       $transFailure
 * @property bool       $transStatus
 */
abstract class BaseConnection implements ConnectionInterface
{
    /**
     * Data Source Name / Connect string
     */
    protected string $dsn = '';

    /**
     * Port de la base de données
     */
    protected int|string $port = '';

    /**
     * Nom d'hote
     */
    protected string $hostname = '';

    /**
     * Utilisateur de la base de données
     */
    protected string $username = '';

    /**
     * Mot de passe de l'utilisateur
     */
    protected string $password = '';

    /**
     * Nom de la base de données
     */
    protected string $database = '';

    /**
     * Pilote de la base de données
     */
    public string $driver = 'pdomysql';

    /**
     * Sub-driver
     */
    protected string $subdriver = '';

    /**
     * Prefixe des tables
     */
    protected string $prefix = '';

    /**
     * Drapeau de persistence de la connexion
     */
    protected bool $persistent = false;

    /**
     * Drapeau de debugage
     *
     * Doit on afficher les erreurs ?
     */
    public bool $debug = false;

    /**
     * Character set
     */
    protected string $charset = 'utf8mb4';

    /**
     * Collation
     */
    protected string $collation = 'utf8mb4_general_ci';

    /**
     * Swap Prefix
     */
    protected string $swapPre = '';

    /**
     * Encryption flag/data
     *
     * @var mixed
     */
    protected $encrypt = false;

    /**
     * Drapeau de compression
     */
    protected bool $compress = false;

    /**
     * Drapeau Strict ON
     *
     * Doit on execute en mode SQL strict.
     */
    protected bool $strictOn = false;

    /**
     * Parametres de connexion de secours
     */
    protected array $failover = [];

    /**
     * The last query object that was executed
     * on this connection.
     *
     * @var mixed
     */
    protected $lastQuery;

    /**
     * Connexion a la bd
     *
     * @var bool|object|PDO|resource
     */
    public $conn = false;

    /**
     * Resultat  de requete
     *
     * @var bool|object|PDOStatement|resource
     */
    public $result = false;

    /**
     * Drapeau de protection des identifiants
     */
    public bool $protectIdentifiers = true;

    /**
     * Liste des identifiants reserves
     *
     * Les identifiants ne doivent pas etre echaper.
     */
    protected array $reservedIdentifiers = ['*'];

    /**
     * Caractere d'echapement des identifiant
     */
    public string $escapeChar = '"';

    /**
     * ESCAPE statement string
     */
    public string $likeEscapeStr = " ESCAPE '%s' ";

    /**
     * ESCAPE character
     */
    public string $likeEscapeChar = '!';

    /**
     * RegExp a utiliser pour echaper les identifiants
     */
    protected array $pregEscapeChar = [];

    /**
     * Ancienes donnees pour les raisons de performance.
     */
    public array $dataCache = [];

    /**
     * Heure de debut de la connexion (microsecondes)
     */
    protected float $connectTime = 0.0;

    /**
     * Combien de temps la connexion a t-elle mise pour etre etablie
     */
    protected float $connectDuration = 0.0;

    /**
     * Si vrai, aucune requete ne pourra etre reexecuter en bd.
     */
    protected bool $pretend = false;

    /**
     * Drapeau d'activation des transactions
     */
    public bool $transEnabled = true;

    /**
     * Drapeau du mode de transactions strictes.
     */
    public bool $transStrict = true;

    /**
     * Niveau de profondeur des transactions
     */
    protected int $transDepth = 0;

    /**
     * Drapeau du statut des transaction
     *
     * Utilise avec les transactions pour determiner si un rollback est en cours.
     */
    protected bool $transStatus = true;

    /**
     * Drapeau d'echec des transactions
     *
     * Utilise avec les transactions pour determiner si une transation a echouee.
     */
    protected bool $transFailure = false;

    /**
     * tableau des alias des tables.
     */
    protected array $aliasedTables = [];

    /**
     * Specifie si on ajoute un hash a l'alias de table lorsqu'aucun alias n'est defini
     */
    public static bool $useHashedAliases = true;

    /**
     * Query Class
     */
    protected string $queryClass = Query::class;

    /**
     * Liste des connexions etablies
     */
    protected static array $allConnections = [];

    /**
     * Statistiques de la requete
     */
    protected array $stats = [
        'queries' => [],
    ];

    /**
     * Commandes sql a executer a l'initialisation de la connexion a la base de donnees
     */
    protected array $commands = [];

    /**
     * Specifie si on doit ouvrir la connexion au serveur en se connectant automatiquement à la base de donnees
     */
    protected bool $withDatabase = true;

    /**
     * Instance de la LoggerInterface pour logger les problemes de connexion
     */
    protected ?LoggerInterface $logger;

    /**
     * Gestionnaire d'evenement
     */
    protected ?object $event;

    /**
     * Saves our connection settings.
     */
    public function __construct(array $params, ?LoggerInterface $logger = null, ?object $event = null)
    {
        $this->logger = $logger;
        $this->event  = $event;

        foreach ($params as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }

        $queryClass = str_replace('Connection', 'Query', static::class);

        if (class_exists($queryClass)) {
            $this->queryClass = $queryClass;
        }

        if ($this->failover !== []) {
            // If there is a failover database, connect now to do failover.
            // Otherwise, Query Builder creates SQL statement with the main database config
            // (prefix) even when the main database is down.
            $this->initialize();
        }
    }

    /**
     * Initializes the database connection/settings.
     *
     * @return mixed
     *
     * @throws DatabaseException
     */
    public function initialize()
    {
        /* If an established connection is available, then there's
         * no need to connect and select the database.
         *
         * Depending on the database driver, conn_id can be either
         * boolean TRUE, a resource or an object.
         */
        if ($this->conn) {
            return;
        }

        $this->connectTime = microtime(true);
        $connectionErrors  = [];

        try {
            // Connect to the database and set the connection ID
            $this->conn = $this->connect($this->persistent);
        } catch (Throwable $e) {
            $connectionErrors[] = sprintf('Main connection [%s]: %s', $this->driver, $e->getMessage());
            $this->log('Error connecting to the database: ' . $e);
        }

        // No connection resource? Check if there is a failover else throw an error
        if (! $this->conn) {
            // Check if there is a failover set
            if (! empty($this->failover) && is_array($this->failover)) {
                // Go over all the failovers
                foreach ($this->failover as $index => $failover) {
                    // Replace the current settings with those of the failover
                    foreach ($failover as $key => $val) {
                        if (property_exists($this, $key)) {
                            $this->{$key} = $val;
                        }
                    }

                    try {
                        // Try to connect
                        $this->conn = $this->connect($this->persistent);
                    } catch (Throwable $e) {
                        $connectionErrors[] = sprintf('Failover #%d [%s]: %s', ++$index, $this->driver, $e->getMessage());
                        $this->log('Error connecting to the database: ' . $e);
                    }

                    // If a connection is made break the foreach loop
                    if ($this->conn) {
                        break;
                    }
                }
            }

            // We still don't have a connection?
            if (! $this->conn) {
                throw new DatabaseException(sprintf(
                    'Unable to connect to the database.%s%s',
                    PHP_EOL,
                    implode(PHP_EOL, $connectionErrors)
                ));
            }
        }

        $this->execCommands();

        $this->connectDuration = microtime(true) - $this->connectTime;
    }

    /**
     * Renvoi la liste des toutes les connexions a la base de donnees
     */
    public static function getAllConnections(): array
    {
        return static::$allConnections;
    }

    /**
     * Ajoute une connexion etablie
     *
     * @param object|resource $conn
     *
     * @return object|resource
     */
    protected static function pushConnection(string $name, BaseConnection $driver, $conn)
    {
        static::$allConnections[$name] = compact('driver', 'conn');

        return $conn;
    }

    /**
     * Verifie si on utilise une connexion pdo ou pas
     */
    public function isPdo(): bool
    {
        if (! empty($this->conn)) {
            if ($this->conn instanceof PDO) {
                return true;
            }
        }

        return preg_match('#pdo#', $this->driver);
    }

    /**
     * Connect to the database.
     *
     * @return mixed
     */
    abstract public function connect(bool $persistent = false);

    /**
     * Close the database connection.
     */
    public function close()
    {
        if ($this->conn) {
            $this->_close();
            $this->conn = false;
        }
    }

    /**
     * Platform dependent way method for closing the connection.
     *
     * @return mixed
     */
    abstract protected function _close();

    /**
     * Create a persistent database connection.
     *
     * @return mixed
     */
    public function persistentConnect()
    {
        return $this->connect(true);
    }

    /**
     * Keep or establish the connection if no queries have been sent for
     * a length of time exceeding the server's idle timeout.
     *
     * @return mixed
     */
    public function reconnect()
    {
        $this->close();
        $this->initialize();
    }

    /**
     * Returns the actual connection object. If both a 'read' and 'write'
     * connection has been specified, you can pass either term in to
     * get that connection. If you pass either alias in and only a single
     * connection is present, it must return the sole connection.
     *
     * @return mixed
     */
    public function getConnection(?string $alias = null)
    {
        // @todo work with read/write connections
        return $this->conn;
    }

    /**
     * Select a specific database table to use.
     *
     * @return mixed
     */
    abstract public function setDatabase(string $databaseName);

    /**
     * Returns the name of the current database being used.
     */
    public function getDatabase(): string
    {
        return empty($this->database) ? '' : $this->database;
    }

    /**
     * Set's the DB Prefix to something new without needing to reconnect
     */
    public function setPrefix(string $prefix = ''): string
    {
        return $this->prefix = $prefix;
    }

    /**
     * Returns the database prefix.
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * The name of the platform in use (MySQLi, Postgre, SQLite3, OCI8, etc)
     */
    public function getPlatform(): string
    {
        return $this->driver;
    }

    /**
     * Returns a string containing the version of the database being used.
     */
    abstract public function getVersion(): string;

    /**
     * Crée le nom de la table avec son alias et le prefix des table de la base de données
     */
    public function makeTableName(string $table): string
    {
        $table = str_replace($this->prefix, '', trim($table));

        [$alias, $table] = $this->getTableAlias($table);

        if ($alias === $table) {
            return $this->prefixTable($table);
        }

        return $this->prefixTable($table) . ' As ' . $this->escapeIdentifiers($alias);
    }

    /**
     * Recupère l'alias de la table
     */
    public function getTableAlias(string $table): array
    {
        $table = str_replace($this->prefix, '', trim($table));

        if (empty($this->aliasedTables[$table])) {
            $tabs = explode(' ', $table);

            if (count($tabs) === 2) {
                $alias = $tabs[1];
                $table = $tabs[0];
            } elseif (preg_match('/\s+AS(.+)/i', $table, $matches)) {
                if (! empty($matches[1])) {
                    $alias = trim($matches[1]);
                    $table = str_replace($matches[0], '', $table);
                } else {
                    $alias = $table . (static::$useHashedAliases ? '_' . uniqid() : '');
                }
            } else {
                $key = array_search($table, $this->aliasedTables, true);

                if (! empty($this->aliasedTables[$key])) {
                    $alias = $this->aliasedTables[$key];
                    $table = $key;
                } else {
                    $alias = $table . (static::$useHashedAliases ? '_' . uniqid() : '');
                }
            }

            if (! empty($this->aliasedTables[$alias])) {
                $alias = $this->aliasedTables[$alias];
            }

            if ($alias !== $table) {
                $this->aliasedTables[$table] = $alias;
            }
        }

        return [$this->aliasedTables[$table] ?? $table, $table];
    }

    /**
     * Recupère le nom prefixé de la table en fonction de la configuration
     */
    public function prefixTable(string $table): string
    {
        $table = str_replace($this->prefix, '', trim($table));

        if ($table === '') {
            throw new DatabaseException('A table name is required for that operation.');
        }

        return $this->escapeIdentifiers($this->prefix . $table);
    }

    /**
     * Entoure une chaîne de guillemets et échappe le contenu d'un paramètre de chaîne.
     *
     * @param mixed $value
     *
     * @return mixed Valeur cotée
     */
    public function quote($value)
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_string($value)) {
            try {
                return $this->escapeString($value);
            } catch (DatabaseException) {
                return "'" . $this->simpleEscapeString($value) . "'";
            }
        }

        return $value;
    }

    /**
     * Sets the Table Aliases to use. These are typically
     * collected during use of the Builder, and set here
     * so queries are built correctly.
     *
     * @return $this
     */
    public function setAliasedTables(array $aliases)
    {
        $this->aliasedTables = $aliases;

        return $this;
    }

    /**
     * Recupere les aliases de tables definis
     */
    public function getAliasedTables(): array
    {
        return $this->aliasedTables;
    }

    /**
     * Ajoutez un alias de table à notre liste.
     */
    public function addTableAlias(string $table): self
    {
        if (! in_array($table, $this->aliasedTables, true)) {
            $this->aliasedTables[] = $table;
        }

        return $this;
    }

    /**
     * Executes the query against the database.
     *
     * @return mixed
     */
    abstract protected function execute(string $sql, array $params = []);

    /**
     * {@inheritDoc}
     *
     * @return BaseResult|bool|Query BaseResult quand la requete est de type "lecture", bool quand la requete est de type "ecriture", Query quand on a une requete preparee
     */
    public function query(string $sql, $binds = null, bool $setEscapeFlags = true, string $queryClass = '')
    {
        $queryClass = $queryClass ?: $this->queryClass;

        if (empty($this->conn)) {
            $this->initialize();
        }

        /**
         * @var Query $query
         */
        $query = new $queryClass($this);

        $query->setQuery($sql, $binds, $setEscapeFlags);

        if (! empty($this->swapPre) && ! empty($this->prefix)) {
            $query->swapPrefix($this->prefix, $this->swapPre);
        }

        $startTime = microtime(true);

        // Always save the last query so we can use
        // the getLastQuery() method.
        $this->lastQuery = $query;

        // If $pretend is true, then we just want to return
        // the actual query object here. There won't be
        // any results to return.
        if ($this->pretend) {
            $query->setDuration($startTime);

            return $query;
        }

        // Run the query for real
        try {
            $exception    = null;
            $this->result = $this->simpleQuery($query->getQuery());
        } catch (Exception $exception) {
            $this->result = false;
        }

        if ($this->result === false) {
            $query->setDuration($startTime, $startTime);

            // This will trigger a rollback if transactions are being used
            if ($this->transDepth !== 0) {
                $this->transStatus = false;
            }

            if ($this->debug) {
                // We call this function in order to roll-back queries
                // if transactions are enabled. If we don't call this here
                // the error message will trigger an exit, causing the
                // transactions to remain in limbo.
                while ($this->transDepth !== 0) {
                    $transDepth = $this->transDepth;
                    $this->transComplete();

                    if ($transDepth === $this->transDepth) {
                        $this->log('Failure during an automated transaction commit/rollback!');
                        break;
                    }
                }

                // Let others do something with this query.
                $this->triggerEvent($query);

                if ($exception !== null) {
                    throw $exception;
                }

                return false;
            }

            // Let others do something with this query.
            $this->triggerEvent($query);

            return false;
        }

        $query->setDuration($startTime);

        // Let others do something with this query
        $this->triggerEvent($query);

        // resultID is not false, so it must be successful
        if ($this->isWriteType($sql)) {
            if ($this->result instanceof PDOStatement) {
                $this->result->closeCursor();
            }

            return true;
        }

        // query is not write-type, so it must be read-type query; return QueryResult
        $resultClass = str_replace('Connection', 'Result', static::class);

        return new $resultClass($this, $this->result);
    }

    /**
     * Declanche un evenement
     *
     * @param mixed $target
     *
     * @return void
     */
    public function triggerEvent($target, string $eventName = 'db.query')
    {
        if ($this->event) {
            if (method_exists($this->event, 'trigger')) {
                $this->event->trigger($eventName, $target);
            }
            if (method_exists($this->event, 'dispatch')) {
                $this->event->dispatch($target);
            }
        }
    }

    /**
     * Enregistre un log
     *
     * @param int $level
     *
     * @return void
     */
    public function log(string|Stringable $message, string $level = LogLevel::ERROR, array $context = [])
    {
        if ($this->logger) {
            $this->logger->log($level, 'Database: ' . $message, $context);
        }
    }

    /**
     * Performs a basic query against the database. No binding or caching
     * is performed, nor are transactions handled. Simply takes a raw
     * query string and returns the database-specific result id.
     *
     * @return mixed
     */
    public function simpleQuery(string $sql)
    {
        if (empty($this->conn)) {
            $this->initialize();
        }

        return $this->execute($sql);
    }

    /**
     * Disable Transactions
     *
     * This permits transactions to be disabled at run-time.
     */
    public function transOff()
    {
        $this->transEnabled = false;
    }

    /**
     * Enable/disable Transaction Strict Mode
     *
     * When strict mode is enabled, if you are running multiple groups of
     * transactions, if one group fails all subsequent groups will be
     * rolled back.
     *
     * If strict mode is disabled, each group is treated autonomously,
     * meaning a failure of one group will not affect any others
     *
     * @param bool $mode = true
     *
     * @return $this
     */
    public function transStrict(bool $mode = true)
    {
        $this->transStrict = $mode;

        return $this;
    }

    /**
     * Start Transaction
     */
    public function transStart(bool $testMode = false): bool
    {
        if (! $this->transEnabled) {
            return false;
        }

        return $this->beginTransaction($testMode);
    }

    /**
     * Complete Transaction
     */
    public function transComplete(): bool
    {
        if (! $this->transEnabled) {
            return false;
        }

        // The query() function will set this flag to FALSE in the event that a query failed
        if ($this->transStatus === false || $this->transFailure === true) {
            $this->rollback();

            // Si nous ne fonctionnons PAS en mode strict,
            // nous réinitialiserons l'indicateur _trans_status afin que les
            // groupes de transactions suivants soient autorisés.
            if ($this->transStrict === false) {
                $this->transStatus = true;
            }

            return false;
        }

        return $this->commit();
    }

    /**
     * Lets you retrieve the transaction flag to determine if it has failed
     */
    public function transStatus(): bool
    {
        return $this->transStatus;
    }

    /**
     * Demarre la transaction
     */
    public function beginTransaction(bool $testMode = false): bool
    {
        if (! $this->transEnabled) {
            return false;
        }

        // Lorsque les transactions sont imbriquées, nous ne commençons/validons/annulons que les plus externes
        if ($this->transDepth > 0) {
            $this->transDepth++;

            return true;
        }

        if (empty($this->conn)) {
            $this->initialize();
        }

        // Reset the transaction failure flag.
        // If the $test_mode flag is set to TRUE transactions will be rolled back
        // even if the queries produce a successful result.
        $this->transFailure = ($testMode === true);

        if ($this->_transBegin()) {
            $this->transDepth++;

            return true;
        }

        return false;
    }

    /**
     * @deprecated 2.0. Utilisez beginTransaction a la place
     */
    public function transBegin(bool $testMode = false): bool
    {
        return $this->beginTransaction($testMode);
    }

    /**
     * Valide la transaction
     */
    public function commit(): bool
    {
        if (! $this->transEnabled || $this->transDepth === 0) {
            return false;
        }

        // Lorsque les transactions sont imbriquées, nous ne commençons/validons/annulons que les plus externes
        if ($this->transDepth > 1 || $this->_transCommit()) {
            $this->transDepth--;

            return true;
        }

        return false;
    }

    /**
     * @deprecated 2.0. Utilisez commit() a la place
     */
    public function transCommit(): bool
    {
        return $this->commit();
    }

    /**
     * Annule la transaction
     */
    public function rollback(): bool
    {
        if (! $this->transEnabled || $this->transDepth === 0) {
            return false;
        }

        // Lorsque les transactions sont imbriquées, nous ne commençons/validons/annulons que les plus externes
        if ($this->transDepth > 1 || $this->_transRollback()) {
            $this->transDepth--;

            return true;
        }

        return false;
    }

    /**
     * @deprecated 2.0. Utilisez rollback a la place
     */
    public function transRollback(): bool
    {
        return $this->rollback();
    }

    /**
     * Demarre la transaction
     */
    abstract protected function _transBegin(): bool;

    /**
     * Valide la transaction
     */
    abstract protected function _transCommit(): bool;

    /**
     * Annulle la transaction
     */
    abstract protected function _transRollback(): bool;

    /**
     * Execute une Closure dans une transaction.
     *
     * @throws Throwable
     */
    public function transaction(Closure $callback, int $attempts = 1): mixed
    {
        for ($currentAttempt = 1; $currentAttempt <= $attempts; $currentAttempt++) {
            try {
                $this->beginTransaction();
                $callbackResult = $callback($this);
                $this->commit();

                return $callbackResult;
            } catch (Throwable $th) {
                $this->rollback();

                if ($currentAttempt === $attempts) {
                    throw $th;
                }

                continue;
            }
        }
    }

    /**
     * Retourne une nouvelle instance non partagee du query builder pour cette connexion.
     *
     * @param array|string $tableName
     *
     * @return BaseBuilder
     *
     * @throws DatabaseException
     */
    public function table($tableName)
    {
        if (empty($tableName)) {
            throw new DatabaseException('You must set the database table to be used with your query.');
        }

        $className = str_replace('Connection', 'Builder', static::class);

        return (new $className($this))->table($tableName);
    }

    /**
     * Returns a new instance of the BaseBuilder class with a cleared FROM clause.
     */
    public function newQuery(): BaseBuilder
    {
        return $this->table('.')->from([], true);
    }

    /**
     * Creates a prepared statement with the database that can then
     * be used to execute multiple statements against. Within the
     * closure, you would build the query in any normal way, though
     * the Query Builder is the expected manner.
     *
     * Example:
     *    $stmt = $db->prepare(function($db)
     *           {
     *             return $db->table('users')
     *                   ->where('id', 1)
     *                     ->get();
     *           })
     *
     * @return BasePreparedQuery|null
     */
    public function prepare(Closure $func, array $options = [])
    {
        if (empty($this->conn)) {
            $this->initialize();
        }

        $this->pretend();

        $sql = $func($this);

        $this->pretend(false);

        /*  if ($sql instanceof QueryInterface) {
             $sql = $sql->getOriginalQuery();
         } */

        $class = str_ireplace('Connection', 'PreparedQuery', static::class);
        /** @var BasePreparedQuery $class */
        $class = new $class($this);

        return $class->prepare($sql, $options);
    }

    /**
     * Returns the last query's statement object.
     *
     * @return mixed
     */
    public function getLastQuery()
    {
        return $this->lastQuery;
    }

    /**
     * Returns a string representation of the last query's statement object.
     */
    public function showLastQuery(): string
    {
        return (string) $this->lastQuery;
    }

    /**
     * Returns the time we started to connect to this database in
     * seconds with microseconds.
     *
     * Used by the Debug Toolbar's timeline.
     */
    public function getConnectStart(): ?float
    {
        return $this->connectTime;
    }

    /**
     * Returns the number of seconds with microseconds that it took
     * to connect to the database.
     *
     * Used by the Debug Toolbar's timeline.
     */
    public function getConnectDuration(int $decimals = 6): string
    {
        return number_format($this->connectDuration, $decimals);
    }

    /**
     * Protect Identifiers
     *
     * This function is used extensively by the Query Builder class, and by
     * a couple functions in this class.
     * It takes a column or table name (optionally with an alias) and inserts
     * the table prefix onto it. Some logic is necessary in order to deal with
     * column names that include the path. Consider a query like this:
     *
     * SELECT hostname.database.table.column AS c FROM hostname.database.table
     *
     * Or a query with aliasing:
     *
     * SELECT m.member_id, m.member_name FROM members AS m
     *
     * Since the column name can include up to four segments (host, DB, table, column)
     * or also have an alias prefix, we need to do a bit of work to figure this out and
     * insert the table prefix (if it exists) in the proper position, and escape only
     * the correct identifiers.
     *
     * @param array|string $item
     * @param bool         $prefixSingle       Prefix a table name with no segments?
     * @param bool         $protectIdentifiers Protect table or column names?
     * @param bool         $fieldExists        Supplied $item contains a column name?
     *
     * @return array|string
     * @phpstan-return ($item is array ? array : string)
     */
    public function protectIdentifiers($item, bool $prefixSingle = false, ?bool $protectIdentifiers = null, bool $fieldExists = true)
    {
        if (! is_bool($protectIdentifiers)) {
            $protectIdentifiers = $this->protectIdentifiers;
        }

        if (is_array($item)) {
            $escapedArray = [];

            foreach ($item as $k => $v) {
                $escapedArray[$this->protectIdentifiers($k)] = $this->protectIdentifiers($v, $prefixSingle, $protectIdentifiers, $fieldExists);
            }

            return $escapedArray;
        }

        // This is basically a bug fix for queries that use MAX, MIN, etc.
        // If a parenthesis is found we know that we do not need to
        // escape the data or add a prefix. There's probably a more graceful
        // way to deal with this, but I'm not thinking of it
        //
        // Added exception for single quotes as well, we don't want to alter
        // literal strings.
        if (strcspn($item, "()'") !== strlen($item)) {
            return $item;
        }

        // Do not protect identifiers and do not prefix, no swap prefix, there is nothing to do
        if ($protectIdentifiers === false && $prefixSingle === false && $this->swapPre === '') {
            return $item;
        }

        // Convert tabs or multiple spaces into single spaces
        $item = preg_replace('/\s+/', ' ', trim($item));

        // If the item has an alias declaration we remove it and set it aside.
        // Note: strripos() is used in order to support spaces in table names
        if ($offset = strripos($item, ' AS ')) {
            $alias = ($protectIdentifiers) ? substr($item, $offset, 4) . $this->escapeIdentifiers(substr($item, $offset + 4)) : substr($item, $offset);
            $item  = substr($item, 0, $offset);
        } elseif ($offset = strrpos($item, ' ')) {
            $alias = ($protectIdentifiers) ? ' ' . $this->escapeIdentifiers(substr($item, $offset + 1)) : substr($item, $offset);
            $item  = substr($item, 0, $offset);
        } else {
            $alias = '';
        }

        // Break the string apart if it contains periods, then insert the table prefix
        // in the correct location, assuming the period doesn't indicate that we're dealing
        // with an alias. While we're at it, we will escape the components
        if (str_contains($item, '.')) {
            return $this->protectDotItem($item, $alias, $protectIdentifiers, $fieldExists);
        }

        // In some cases, especially 'from', we end up running through
        // protect_identifiers twice. This algorithm won't work when
        // it contains the escapeChar so strip it out.
        $item = trim($item, $this->escapeChar);

        // Is there a table prefix? If not, no need to insert it
        if ($this->prefix !== '') {
            // Verify table prefix and replace if necessary
            if ($this->swapPre !== '' && str_starts_with($item, $this->swapPre)) {
                $item = preg_replace('/^' . $this->swapPre . '(\S+?)/', $this->prefix . '\\1', $item);
            }
            // Do we prefix an item with no segments?
            elseif ($prefixSingle === true && ! str_starts_with($item, $this->prefix)) {
                $item = $this->prefix . $item;
            }
        }

        if ($protectIdentifiers === true && ! in_array($item, $this->reservedIdentifiers, true)) {
            $item = $this->escapeIdentifiers($item);
        }

        return $item . $alias;
    }

    private function protectDotItem(string $item, string $alias, bool $protectIdentifiers, bool $fieldExists): string
    {
        $parts = explode('.', $item);

        // Does the first segment of the exploded item match
        // one of the aliases previously identified? If so,
        // we have nothing more to do other than escape the item
        //
        // NOTE: The ! empty() condition prevents this method
        // from breaking when QB isn't enabled.
        if (! empty($this->aliasedTables) && in_array($parts[0], $this->aliasedTables, true)) {
            if ($protectIdentifiers === true) {
                foreach ($parts as $key => $val) {
                    if (! in_array($val, $this->reservedIdentifiers, true)) {
                        $parts[$key] = $this->escapeIdentifiers($val);
                    }
                }

                $item = implode('.', $parts);
            }

            return $item . $alias;
        }

        // Is there a table prefix defined in the config file? If not, no need to do anything
        if ($this->prefix !== '') {
            // We now add the table prefix based on some logic.
            // Do we have 4 segments (hostname.database.table.column)?
            // If so, we add the table prefix to the column name in the 3rd segment.
            if (isset($parts[3])) {
                $i = 2;
            }
            // Do we have 3 segments (database.table.column)?
            // If so, we add the table prefix to the column name in 2nd position
            elseif (isset($parts[2])) {
                $i = 1;
            }
            // Do we have 2 segments (table.column)?
            // If so, we add the table prefix to the column name in 1st segment
            else {
                $i = 0;
            }

            // This flag is set when the supplied $item does not contain a field name.
            // This can happen when this function is being called from a JOIN.
            if ($fieldExists === false) {
                $i++;
            }

            // Verify table prefix and replace if necessary
            if ($this->swapPre !== '' && str_starts_with($parts[$i], $this->swapPre)) {
                $parts[$i] = preg_replace('/^' . $this->swapPre . '(\S+?)/', $this->prefix . '\\1', $parts[$i]);
            }
            // We only add the table prefix if it does not already exist
            elseif (! str_starts_with($parts[$i], $this->prefix)) {
                $parts[$i] = $this->prefix . $parts[$i];
            }

            // Put the parts back together
            $item = implode('.', $parts);
        }

        if ($protectIdentifiers === true) {
            $item = $this->escapeIdentifiers($item);
        }

        return $item . $alias;
    }

    /**
     * Determine si une chaine est échappée comme un identifiant SQL
     */
    public function isEscapedIdentifier(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        $value = trim($value);

        return str_starts_with($value, $this->escapeChar)
            && str_contains($value, '.')
            && str_ends_with($value, $this->escapeChar);
    }

    /**
     * Échappe un identifiant SQL
     *
     * Cette fonction échappe à un identifiant unique.
     *
     * @param non-empty-string $item
     */
    public function escapeIdentifier(string $item): string
    {
        return $this->escapeChar
            . str_replace(
                $this->escapeChar,
                $this->escapeChar . $this->escapeChar,
                $item
            )
            . $this->escapeChar;
    }

    /**
     * Échappe des identifiants SQL
     *
     * Cette fonction échappe les noms de colonnes et de tables
     *
     * @param mixed $item
     *
     * @return mixed
     */
    public function escapeIdentifiers($item)
    {
        if ($this->escapeChar === '' || empty($item) || in_array($item, $this->reservedIdentifiers, true) || in_array($item, BaseBuilder::sqlFunctions(), true)) {
            return $item;
        }

        if (is_array($item)) {
            foreach ($item as $key => $value) {
                $item[$key] = $this->escapeIdentifiers($value);
            }

            return $item;
        }

        // Avoid breaking functions and literal values inside queries
        if (ctype_digit($item)
            || $item[0] === "'"
            || ($this->escapeChar !== '"' && $item[0] === '"')
            || str_contains($item, '(')) {
            return $item;
        }

        if ($this->pregEscapeChar === []) {
            if (is_array($this->escapeChar)) {
                $this->pregEscapeChar = [
                    preg_quote($this->escapeChar[0], '/'),
                    preg_quote($this->escapeChar[1], '/'),
                    $this->escapeChar[0],
                    $this->escapeChar[1],
                ];
            } else {
                $this->pregEscapeChar[0] = $this->pregEscapeChar[1] = preg_quote($this->escapeChar, '/');
                $this->pregEscapeChar[2] = $this->pregEscapeChar[3] = $this->escapeChar;
            }
        }

        foreach ($this->reservedIdentifiers as $id) {
            if (str_contains($item, '.' . $id)) {
                return preg_replace(
                    '/' . $this->pregEscapeChar[0] . '?([^' . $this->pregEscapeChar[1] . '\.]+)' . $this->pregEscapeChar[1] . '?\./i',
                    $this->pregEscapeChar[2] . '$1' . $this->pregEscapeChar[3] . '.',
                    $item
                );
            }
        }

        return preg_replace(
            '/' . $this->pregEscapeChar[0] . '?([^' . $this->pregEscapeChar[1] . '\.]+)' . $this->pregEscapeChar[1] . '?(\.)?/i',
            $this->pregEscapeChar[2] . '$1' . $this->pregEscapeChar[3] . '$2',
            $item
        );
    }

    /**
     * Échappe une valeur de la clause where
     */
    public function escapeValue(bool $escape, $value)
    {
        if (! $escape || is_numeric($value)) {
            return $value;
        }

        if (is_string($value) && ! str_starts_with($value, "'") && ! str_ends_with($value, "'") ) {
            return $this->quote($value);
        }
       
        return $value;
    }


    /**
     * "Chaîne d'échappement "intelligente
     *
     * Échappe les données en fonction de leur type.
     * Définit les types booléen et nul
     *
     * @param mixed $str
     *
     * @return mixed
     */
    public function escape($str)
    {
        if (is_array($str)) {
            return array_map($this->escape(...), $str);
        }

        if ($str instanceof Stringable) {
            $str = (string) $str;
        }

        if (is_string($str)) {
            return $this->escapeString($str);
        }

        if (is_bool($str)) {
            return ($str === false) ? 0 : 1;
        }

        return $str ?? 'NULL';
    }

    /**
     * Echappe les chaine de caracteres
     *
     * @param list<string|Stringable>|string|Stringable $str
     * @param bool                                      $like Si la chaîne doit être utilisée dans une condition LIKE
     *
     * @return string|string[]
     */
    public function escapeString($str, bool $like = false)
    {
        if (is_array($str)) {
            foreach ($str as $key => $val) {
                $str[$key] = $this->escapeString($val, $like);
            }

            return $str;
        }

        if ($str instanceof Stringable) {
            $str = (string) $str;
        }

        $str = $this->_escapeString($str);

        // échapper aux caractères génériques de la condition LIKE
        if ($like === true) {
            return str_replace(
                [$this->likeEscapeChar, '%', '_'],
                [$this->likeEscapeChar . $this->likeEscapeChar, $this->likeEscapeChar . '%', $this->likeEscapeChar . '_'],
                $str
            );
        }

        return $str;
    }

    /**
     * Échapper à la chaîne LIKE
     *
     * Appelle le pilote individuel pour l'échappement spécifique à la plate-forme pour les conditions LIKE.
     *
     * @param string|string[] $str
     *
     * @return string|string[]
     */
    public function escapeLikeString($str)
    {
        return $this->escapeString($str, true);
    }

    /**
     * Échappatoire de chaînes de caractères indépendant de la plate-forme.
     *
     * Sera probablement surchargé dans les classes enfantines.
     */
    protected function _escapeString(string $str): string
    {
        return $this->simpleEscapeString($str);
    }

    public function simpleEscapeString(string $str): string
    {
        return str_replace("'", "''", Helpers::removeInvisibleCharacters($str, false));
    }

    /**
     * Cette fonction vous permet d'appeler des fonctions de base de données PHP qui ne sont pas nativement incluses
     * dans Blitz PHP, de manière indépendante de la plateforme.
     *
     * @param array ...$params
     *
     * @throws DatabaseException
     */
    public function callFunction(string $functionName, ...$params): bool
    {
        $driver = $this->getDriverFunctionPrefix();

        if (! str_contains($driver, $functionName)) {
            $functionName = $driver . $functionName;
        }

        if (! function_exists($functionName)) {
            if ($this->debug) {
                throw new DatabaseException('This feature is not available for the database you are using.');
            }

            return false;
        }

        return $functionName(...$params);
    }

    /**
     * Get the prefix of the function to access the DB.
     */
    protected function getDriverFunctionPrefix(): string
    {
        return strtolower($this->driver) . '_';
    }

    // --------------------------------------------------------------------
    // META Methods
    // --------------------------------------------------------------------

    /**
     * Returns an array of table names
     *
     * @return array|bool
     *
     * @throws DatabaseException
     */
    public function listTables(bool $constrainByPrefix = false)
    {
        // Is there a cached result?
        if (isset($this->dataCache['table_names']) && $this->dataCache['table_names']) {
            return $constrainByPrefix ?
                preg_grep("/^{$this->prefix}/", $this->dataCache['table_names'])
                : $this->dataCache['table_names'];
        }

        if (false === ($sql = $this->_listTables($constrainByPrefix))) {
            if ($this->DBDebug) {
                throw new DatabaseException('This feature is not available for the database you are using.');
            }

            return false;
        }

        $this->dataCache['table_names'] = [];

        $query = $this->query($sql);

        foreach ($query->resultArray() as $row) {
            // Do we know from which column to get the table name?
            if (! isset($key)) {
                if (isset($row['table_name'])) {
                    $key = 'table_name';
                } elseif (isset($row['TABLE_NAME'])) {
                    $key = 'TABLE_NAME';
                } else {
                    /* We have no other choice but to just get the first element's key.
                     * Due to array_shift() accepting its argument by reference, if
                     * E_STRICT is on, this would trigger a warning. So we'll have to
                     * assign it first.
                     */
                    $key = array_keys($row);
                    $key = array_shift($key);
                }
            }

            $this->dataCache['table_names'][] = $row[$key];
        }

        return $this->dataCache['table_names'];
    }

    /**
     * Determine if a particular table exists
     *
     * @param bool $cached Whether to use data cache
     */
    public function tableExists(string $tableName, bool $cached = true): bool
    {
        if ($cached === true) {
            return in_array($this->protectIdentifiers($tableName, true, false, false), $this->listTables(), true);
        }

        if (false === ($sql = $this->_listTables(false, $tableName))) {
            if ($this->debug) {
                throw new DatabaseException('This feature is not available for the database you are using.');
            }

            return false;
        }

        $tableExists = $this->query($sql)->resultArray() !== [];

        // if cache has been built already
        if (! empty($this->dataCache['table_names'])) {
            $key = array_search(
                strtolower($tableName),
                array_map('strtolower', $this->dataCache['table_names']),
                true
            );

            // table doesn't exist but still in cache - lets reset cache, it can be rebuilt later
            // OR if table does exist but is not found in cache
            if (($key !== false && ! $tableExists) || ($key === false && $tableExists)) {
                $this->resetDataCache();
            }
        }

        return $tableExists;
    }

    /**
     * Fetch Field Names
     *
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

        if (empty($this->conn)) {
            $this->initialize();
        }

        if (false === ($sql = $this->_listColumns($table))) {
            if ($this->debug) {
                throw new DatabaseException('This feature is not available for the database you are using.');
            }

            return false;
        }

        $query = $this->query($sql);

        $this->dataCache['field_names'][$table] = [];

        foreach ($query->resultArray() as $row) {
            // Do we know from where to get the column's name?
            if (! isset($key)) {
                if (isset($row['column_name'])) {
                    $key = 'column_name';
                } elseif (isset($row['COLUMN_NAME'])) {
                    $key = 'COLUMN_NAME';
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
     * Determine if a particular field exists
     */
    public function fieldExists(string $fieldName, string $tableName): bool
    {
        return in_array($fieldName, $this->getFieldNames($tableName), true);
    }

    /**
     * Returns an object with field data
     *
     * @return stdClass[]
     */
    public function getFieldData(string $table)
    {
        return $this->_fieldData($this->protectIdentifiers($table, true, false, false));
    }

    /**
     * Returns an object with key data
     *
     * @return array
     */
    public function getIndexData(string $table)
    {
        return $this->_indexData($this->protectIdentifiers($table, true, false, false));
    }

    /**
     * Returns an object with foreign key data
     *
     * @return array
     */
    public function getForeignKeyData(string $table)
    {
        return $this->_foreignKeyData($this->protectIdentifiers($table, true, false, false));
    }

    /**
     * Disables foreign key checks temporarily.
     */
    public function disableForeignKeyChecks()
    {
        $sql = $this->_disableForeignKeyChecks();

        return $this->query($sql);
    }

    public function disableFk()
    {
        return $this->disableForeignKeyChecks();
    }

    /**
     * Returns platform-specific SQL to disable foreign key checks.
     */
    abstract protected function _disableForeignKeyChecks(): string;

    /**
     * Enables foreign key checks temporarily.
     */
    public function enableForeignKeyChecks()
    {
        $sql = $this->_enableForeignKeyChecks();

        return $this->query($sql);
    }

    /**
     * Returns platform-specific SQL to disable foreign key checks.
     */
    abstract protected function _enableForeignKeyChecks(): string;

    /**
     * Allows the engine to be set into a mode where queries are not
     * actually executed, but they are still generated, timed, etc.
     *
     * This is primarily used by the prepared query functionality.
     *
     * @return $this
     */
    public function pretend(bool $pretend = true)
    {
        $this->pretend = $pretend;

        return $this;
    }

    /**
     * Empties our data cache. Especially helpful during testing.
     *
     * @return $this
     */
    public function resetDataCache()
    {
        $this->dataCache = [];

        return $this;
    }

    /**
     * Determines if the statement is a write-type query or not.
     *
     * @param string $sql
     */
    public function isWriteType($sql): bool
    {
        return (bool) preg_match('/^\s*"?(SET|INSERT|UPDATE|DELETE|REPLACE|CREATE|DROP|TRUNCATE|LOAD|COPY|ALTER|RENAME|GRANT|REVOKE|LOCK|UNLOCK|REINDEX|MERGE)\s/i', $sql);
    }

    /**
     * Returns the last error code and message.
     *
     * Must return an array with keys 'code' and 'message':
     *
     * @return array<string, int|string|null>
     * @phpstan-return array{code: int|string|null, message: string|null}
     */
    abstract public function error(): array;

    /**
     * Return the last id generated by autoincrement
     */
    public function lastId(?string $table = null): ?int
    {
        $params = func_get_args();

        return $this->insertID(...$params);
    }

    /**
     * Insert ID
     *
     * @return int|string
     */
    abstract public function insertID(?string $table = null);

    /**
     * Returns the total number of rows affected by this query.
     */
    abstract public function affectedRows(): int;

    /**
     * Returns the number of rows in the result set.
     */
    abstract public function numRows(): int;

    /**
     * Generates the SQL for listing tables in a platform-dependent manner.
     *
     * @return false|string
     */
    abstract protected function _listTables(bool $constrainByPrefix = false);

    /**
     * Generates a platform-specific query string so that the column names can be fetched.
     *
     * @return false|string
     */
    abstract protected function _listColumns(string $table = '');

    /**
     * Platform-specific field data information.
     * Returns an array of objects with field data
     *
     * @see    getFieldData()
     */
    abstract protected function _fieldData(string $table): array;

    /**
     * Platform-specific index data.
     * Returns an array of objects with index data
     *
     * @see    getIndexData()
     */
    abstract protected function _indexData(string $table): array;

    /**
     * Platform-specific foreign keys data.
     * Returns an array of objects with Foreign key data
     *
     * @see    getForeignKeyData()
     */
    abstract protected function _foreignKeyData(string $table): array;

    /**
     * Converts array of arrays generated by _foreignKeyData() to array of objects
     *
     * @return array[
     *    {constraint_name} =>
     *        stdClass[
     *            'constraint_name'     => string,
     *            'table_name'          => string,
     *            'column_name'         => string[],
     *            'foreign_table_name'  => string,
     *            'foreign_column_name' => string[],
     *            'on_delete'           => string,
     *            'on_update'           => string,
     *            'match'               => string
     *        ]
     * ]
     */
    protected function foreignKeyDataToObjects(array $data)
    {
        $retVal = [];

        foreach ($data as $row) {
            $name = $row['constraint_name'];

            // for sqlite generate name
            if ($name === null) {
                $name = $row['table_name'] . '_' . implode('_', $row['column_name']) . '_foreign';
            }

            $obj                      = new stdClass();
            $obj->constraint_name     = $name;
            $obj->table_name          = $row['table_name'];
            $obj->column_name         = $row['column_name'];
            $obj->foreign_table_name  = $row['foreign_table_name'];
            $obj->foreign_column_name = $row['foreign_column_name'];
            $obj->on_delete           = $row['on_delete'];
            $obj->on_update           = $row['on_update'];
            $obj->match               = $row['match'];

            $retVal[$name] = $obj;
        }

        return $retVal;
    }

    /**
     * Accessor for properties if they exist.
     *
     * @return mixed
     */
    public function __get(string $key)
    {
        if (property_exists($this, $key)) {
            return $this->{$key};
        }

        return null;
    }

    /**
     * Checker for properties existence.
     */
    public function __isset(string $key): bool
    {
        return property_exists($this, $key);
    }

    /**
     * Execute les commandes sql
     *
     * @return void
     */
    private function execCommands()
    {
        if (! empty($this->conn) && $this->isPdo()) {
            foreach ($this->commands as $command) {
                $this->conn->exec($command);
            }

            if ($this->debug === true) {
                $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            }

            if (isset($this->options['column_case'])) {
                switch (strtolower($this->options['column_case'])) {
                    case 'lower' :
                        $casse = PDO::CASE_LOWER;
                        break;

                    case 'upper' :
                        $casse = PDO::CASE_UPPER;
                        break;

                    default:
                        $casse = PDO::CASE_NATURAL;
                        break;
                }
                $this->conn->setAttribute(PDO::ATTR_CASE, $casse);
            }
        }
    }
}
