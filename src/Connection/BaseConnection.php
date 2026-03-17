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

use BadMethodCallException;
use BlitzPHP\Contracts\Database\BuilderInterface;
use BlitzPHP\Contracts\Database\ConnectionInterface;
use BlitzPHP\Contracts\Database\ResultInterface;
use BlitzPHP\Contracts\Event\EventManagerInterface;
use BlitzPHP\Database\Builder\BaseBuilder;
use BlitzPHP\Database\Exceptions\DatabaseException;
use BlitzPHP\Database\Exceptions\QueryException;
use BlitzPHP\Database\Query\Expression;
use BlitzPHP\Database\Query\Result;
use BlitzPHP\Database\Utils;
use BlitzPHP\Utilities\Iterable\Arr;
use Closure;
use DateTimeInterface;
use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use Stringable;
use Throwable;

/**
 * Connexion de base à la base de données
 * 
 * @method bool tableExists(string $name) Vérifie si une table existe
 * @method array getColumnNames(string $table) Retourne les noms des champs d'une table
 * @method bool columnExists(string $column, string $table) Vérifie si un champ existe dans une table
 * @method array getColumnData(string $table) Retourne les métadonnées des champs d'une table
 * @method array getIndexData(string $table) Retourne les métadonnées des index d'une table
 * @method array getForeignKeyData(string $table) Retourne les métadonnées des clés étrangères d'une table
 */
abstract class BaseConnection implements ConnectionInterface
{
    /**
     * Instance PDO
     */
    protected ?PDO $pdo = null;

    /**
     * Resultat de la requete
     */
    protected ?ResultInterface $result = null;

    /**
     * Configuration de la connexion
     * 
     * @var array{
     *  dsn?: string,
     *  hostname: string, port: int, username?: string, password?: string, 
     *  database?: string, charset?: string, collation?: string, strict_on?: boolean,
     *  debug?: bool
     * }
     */
    protected array $config = [];

    /**
     * Préfixe des tables
     */
    protected string $prefix = '';

    /**
     * tableau des alias des tables.
     */
    protected array $aliasedTables = [];

    /**
     * Tous les callbacks qui doivent être invoqués avant l'exécution d'une requête.
     *
     * @var (Closure(string, array, static): mixed)[]
     */
    protected array $beforeExecutingCallbacks = [];

    /**
     * Gestionnaire de métadonnées
     */
    protected ?MetadataCollector $metadata = null;

    protected array $proxyMethods = [
        'listTables',
        'tableExists',
        'getColumnNames',
        'columnExists',
        'getColumnData',
        'getIndexData',
        'getForeignKeyData',
        'resetDataCache' => 'clearCache',
    ];

    /**
     * Drapeau determinant si les transactions sont activées
     */
    protected bool $transEnabled = true;

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
     * Points de sauvegarde des transactions (pour les transaction imbriquees)
     */
    protected array $savepoints = [];

    /**
     * Caractère d'échappement des identifiants
     */
    protected string $escapeChar = '"';

    /**
     * Cache des colones et tables échapées
     */
    protected array $escapeCache = [];

    /**
     * Requête SQL pour désactiver les contraintes
     */
    protected string $disableForeignKeyChecks = '';

    /**
     * Requête SQL pour activer les contraintes
     */
    protected string $enableForeignKeyChecks = '';

    /**
     * Constructeur
     * 
     * @param ?LoggerInterface $logger Journaliseur
     * @param ?EventManagerInterface $event Gestionnaire d'evenement
     */
    public function __construct(array $config, protected ?LoggerInterface $logger = null, protected ?EventManagerInterface $event = null)
    {
        $this->config = $config;
        $this->prefix = $config['prefix'] ?? '';
    }

    /**
     * {@inheritDoc}
     */
    public function initialize(): void
    {
        if ($this->pdo !== null) {
            return;
        }

        try {
            $this->pdo = $this->connect();

            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
            $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
            
            $this->afterConnect();
        } catch (PDOException $e) {
            throw new DatabaseException(
                "Impossible de se connecter à la base de données : " . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Actions à exécuter après la connexion
     */
    abstract protected function afterConnect(): void;

    /**
     * {@inheritDoc}
     * 
     * @return PDO
     */
    public function connect(bool $persistent = false): mixed
    {
        $options = $this->getPdoOptions();
        
        if ($persistent) {
            $options[PDO::ATTR_PERSISTENT] = true;
        }

        return new PDO(
            $this->getDsn(),
            $this->config['username'] ?? null,
            $this->config['password'] ?? null,
            $options
        );
    }

    /**
     * Créez une connexion persistante à la base de données.
     */
    public function persistentConnect(): PDO
    {
        return $this->connect(true);
    }

    /**
     * Retourne le DSN pour la connexion PDO
     */
    abstract protected function getDsn(): string;

    /**
     * Retourne les options PDO par défaut
     * 
     * @return array<int, mixed>
     */
    protected function getPdoOptions(): array
    {
        return [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function getConnection(): PDO
    {
        $this->initialize();

        return $this->pdo;
    }

    /**
     * {@inheritDoc}
     */
    public function reconnect(): void
    {
        $this->close();
        $this->initialize();
    }

    /**
     * {@inheritDoc}
     */
    public function close(): void
    {
        $this->pdo = null;
    }
    
    /**
     * Obtient le nom de la connexion à la base de données.
     */
    public function getName(): string
    {
        return $this->getConfig('name') ?? 'default';
    }

    /**
     * Obtient le nom du driver PDO.
     */
    public function getDriverName(): string
    {
        return $this->getConfig('driver');
    }

    /**
     * Obtient un nom lisible pour le driver de connexion donné.
     */
    public function getDriverTitle(): string
    {
        return $this->getDriverName();
    }

    /**
     * Obtient une option à partir des options de configuration.
     */
    public function getConfig(?string $option = null): mixed
    {
        return Arr::get($this->config, $option);
    }

    /**
     * Obtient les informations de connexion de base sous forme de tableau pour le débogage.
     */
    protected function getConnectionDetails(): array
    {
        return [
            'driver'      => $this->getDriverName(),
            'name'        => $this->getName(),
            'host'        => $this->config['hostname'] ?? null,
            'port'        => $this->config['port'] ?? null,
            'database'    => $this->config['database'] ?? null,
            'unix_socket' => $this->config['unix_socket'] ?? null,
        ];
    }

    /**
     * Enregistre un hook à exécuter juste avant l'exécution d'une requête.
     *
     * @param (Closure(string, array, static): mixed) $callback
     */
    public function beforeExecuting(Closure $callback): static
    {
        $this->beforeExecutingCallbacks[] = $callback;

        return $this;
    }

    /**
     * Prépare les bindings de requête pour l'exécution.
     */
    public function prepareBindings(array $bindings): array
    {
        foreach ($bindings as $key => $value) {
            if ($value instanceof DateTimeInterface) {
                $bindings[$key] = $value->format('Y-m-d H:i:s');
            } elseif (is_bool($value)) {
                $bindings[$key] = (int) $value;
            }
        }

        return $bindings;
    }
    
    /**
     * Exécute une instruction SQL et journalise son contexte d'exécution.
     * 
     * @param Closure(string, array): mixed $callback
     */
    protected function run(string $query, array $bindings, Closure $callback)
    {
        foreach ($this->beforeExecutingCallbacks as $beforeExecutingCallback) {
            $beforeExecutingCallback($query, $bindings, $this);
        }

        $this->initialize();

        $start = microtime(true);

        try {
            $result = $callback($query, $bindings);
            $this->logQuery($query, $bindings, microtime(true) - $start);
            
            return $result;
        } catch (PDOException $e) {
            $this->logQuery($query, $bindings, microtime(true) - $start, $e);
            
            if ($this->transDepth > 0) {
                $this->transStatus = false;
            }
            
            throw new QueryException(
                $this->getName(),
                $query,
                $this->prepareBindings($bindings),
                $e,
                $this->getConnectionDetails()
            );
        }
    }
    
    /**
     * {@inheritDoc}
     */
    public function query(string $sql, array $bindings = []): ResultInterface
    {
        return $this->run($sql, $bindings, function($query, $bindings) use ($sql) {
            $statement = $this->pdo->prepare($query);
            $success = $statement->execute($this->prepareBindings($bindings));
            
            return $this->result = new Result($this, $statement, $success);
        });
    }

    /**
     * Exécute une instruction SQL et retourne le résultat booléen.
     */
    public function statement(string $query, array $bindings = []): bool
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            $statement = $this->pdo->prepare($query);
            return $statement->execute($this->prepareBindings($bindings));
        });
    }

    /**
     * Exécute une instruction SQL et obtient le nombre de lignes affectées.
     */
    public function affectingStatement(string $query, array $bindings = []): int
    {
        return $this->run($query, $bindings, function ($query, $bindings) {
            $statement = $this->pdo->prepare($query);
            $statement->execute($this->prepareBindings($bindings));

            return $statement->rowCount();
        });
    }

    /**
     * Journalise une requête
     */
    protected function logQuery(string $sql, array $bindings, float $time, ?Throwable $e = null): void
    {
        $this->logger?->debug('Requête exécutée', [
            'sql'      => $sql,
            'bindings' => $bindings,
            'time'     => $time,
            'error'    => $e?->getMessage()
        ]);
    }

    /**
     * {@inheritDoc}
     */
    public function simpleQuery(string $query)
    {
        $this->initialize();
        
        try {
            return $this->pdo->query($query);
        } catch (PDOException $e) {
            throw new QueryException(
                $this->getName(),
                $query,
                [],
                $e,
                $this->getConnectionDetails()
            );
        }
    }

    /**
     * Declenche un evenement
     */
    public function triggerEvent(mixed $target, string $eventName = 'db.query'): void
    {
        $this->event?->emit($eventName, $target);
    }

    /*
    |--------------------------------------------------------------------------
    | Gestion des transactions
    |--------------------------------------------------------------------------
    */

    /**
     * {@inheritDoc}
     */
    public function beginTransaction(bool $testMode = false): bool
    {
        if (! $this->transEnabled) {
            return false;
        }

        $this->initialize();
        
        if ($this->transDepth === 0) {
            $this->transStatus = !$testMode;
            $this->transDepth = 1;
            
            return $this->pdo->beginTransaction();
        }
        
        // Création d'un savepoint pour les transactions imbriquées
        $savepoint = 'sp_' . $this->transDepth;
        $this->pdo->exec("SAVEPOINT {$savepoint}");
        $this->savepoints[$this->transDepth] = $savepoint;
        
        $this->transDepth++;
        
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function commit(): bool
    {
        if (! $this->transEnabled || $this->transDepth === 0) {
            return false;
        }
        
        $this->initialize();
        
        if ($this->transDepth === 1) {
            $this->transDepth = 0;
            return $this->pdo->commit();
        }
        
        // Libération du savepoint
        $savepoint = $this->savepoints[$this->transDepth] ?? null;
        if ($savepoint) {
            $this->pdo->exec("RELEASE SAVEPOINT {$savepoint}");
            unset($this->savepoints[$this->transDepth]);
        }
        
        $this->transDepth--;
        
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function rollback(): bool
    {
        if (! $this->transEnabled || $this->transDepth === 0) {
            return false;
        }
        
        $this->initialize();
        
        if ($this->transDepth === 1) {
            $this->transDepth = 0;
            $this->transStatus = true;
            return $this->pdo->rollBack();
        }
        
        // Retour au savepoint
        $savepoint = $this->savepoints[$this->transDepth] ?? null;
        if ($savepoint) {
            $this->pdo->exec("ROLLBACK TO SAVEPOINT {$savepoint}");
            unset($this->savepoints[$this->transDepth]);
        }
        
        $this->transDepth--;
        
        return true;
    }

    /**
     * {@inheritDoc}
     */
    public function transComplete(): bool
    {
        if ($this->transStatus === false) {
            $this->rollback();
            return false;
        }
        
        return $this->commit();
    }

    /**
     * {@inheritDoc}
     */
    public function transStatus(): bool
    {
        return $this->transStatus;
    }

    public function transactionLevel(): int
    {
        return $this->transDepth;
    }

    /**
     * {@inheritDoc}
     */
    public function transaction(Closure $callback, int $attempts = 1): mixed
    {
        for ($i = 1; $i <= $attempts; $i++) {
            $this->beginTransaction();
            
            try {
                $result = $callback($this);
                $this->commit();
                
                return $result;
            } catch (Throwable $e) {
                $this->rollback();
                
                if ($i === $attempts) {
                    throw $e;
                }
            }
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | Gestion des métadonnées (déléguée à Collector)
    |--------------------------------------------------------------------------
    */

    public function __call(string $name, array $arguments = []): mixed
    {
        if (in_array($name, $this->proxyMethods, true)) {
            return call_user_func_array([$this->metadata(), $name], $arguments);
        }
        if (array_key_exists($name, $this->proxyMethods)) {
            return call_user_func_array([$this->metadata(), $this->proxyMethods[$name]], $arguments);
        }
        throw new BadMethodCallException(sprintf('Methode %s non definie', static::class . '::' . $name));
    }

    /**
     * Retourne la requête SQL pour lister les tables
     *
     * @internal
     */
    abstract public function _listTables(bool $constrainByPrefix = false): string;

    /**
     * Retourne la requête SQL pour lister les index
     *
     * @internal
     */
    abstract public function _listIndexes(string $table): array;

    /**
     * Retourne la requête SQL pour lister les colonnes
     *
     * @internal
     */
    abstract public function _listColumns(string $table): array;

    /**
     * Retourne la requête SQL pour lister les clés étrangères
     *
     * @internal
     */
    abstract public function _listForeignKeys(string $table): array;

    private function metadata(): MetadataCollector
    {
        if (!$this->metadata) {
            $this->metadata = new MetadataCollector($this);
        }

        return $this->metadata;
    }

    /*
    |--------------------------------------------------------------------------
    | Échappement et formatage des identifiants
    |--------------------------------------------------------------------------
    */

    /**
     * {@inheritDoc}
     */
    public function escape(mixed $str): mixed
    {
        if (is_array($str)) {
            return array_map($this->escape(...), $str);
        }

        if ($str instanceof Stringable) {
            $str = (string) $str;
        }

        if (is_string($str)) {
            return $this->pdo->quote($str);
        }

        if (is_bool($str)) {
            return $str ? '1' : '0';
        }

        return $str ?? 'NULL';
    }

    /**
     * Echappe les chaine de caracteres
     *
     * @param list<string|Stringable>|string|Stringable $str
     * @param bool                                      $like Si la chaîne doit être utilisée dans une condition LIKE
     *
     * @return list<string>|string
     */
    public function escapeString($str, bool $like = false): array|string
    {
        if (is_array($str)) {
            return array_map(fn($s) => $this->escapeString($s, $like), $str);
        }

        if ($str instanceof Stringable) {
            $str = (string) $str;
        }
        
        $str = $this->pdo->quote($str);
        
        if ($like === true) {
            $str = str_replace(['%', '_'], ['\\%', '\\_'], $str);
        }
        
        return $str;
    }

    /**
     * Entoure une chaîne de guillemets et échappe le contenu d'un paramètre de chaîne.
     */
    public function quote(string|null|Expression $value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        
        if ($value instanceof Expression) {
            return (string) $value;
        }
        
        if (! is_string($value = Utils::castValue($value))) {
            return $value;
        }

        return $this->pdo->quote($value);
    }

    /**
     * {@inheritDoc}
     */
    public function escapeIdentifiers(mixed $item): mixed
    {
        if (is_array($item)) {
            return array_map([$this, 'escapeIdentifiers'], $item);
        }

        if (!isset($this->escapeCache[$item])) {
            $this->escapeCache[$item] = $this->doEscapeIdentifiers($item);
        }
            
        return $this->escapeCache[$item];
    }

    protected function doEscapeIdentifiers(string $item): string
    {
        if ($this->isReserved($item) || Utils::isSqlFunction($item)) {
            return $item;
        }

        if (str_contains($item, '.')) {
            $parts = explode('.', $item);
            return implode('.', array_map([$this, 'escapeIdentifier'], $parts));
        }

        return $this->escapeIdentifier($item);
    }

    /**
     * Échappe un identifiant simple
     */
    protected function escapeIdentifier(string $item): string
    {
        if ($this->isEscapedIdentifier($item)) {
            return $item;
        }

        return $this->escapeChar . $item . $this->escapeChar;
    }

    /**
     * Vérifie si un identifiant est réservé
     */
    protected function isReserved(string $item): bool
    {
        return in_array($item, ['*'], true);
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
            // && str_contains($value, '.')
            && str_ends_with($value, $this->escapeChar);
    }

    /**
     * Crée le nom de la table avec son alias et le prefix des table de la base de données
     */
    public function makeTableName(string $table): string
    {
        [$alias, $table] = $this->getTableAlias($table);

        if ($alias === $table) {
            return $this->prefixTable($table);
        }

        if ($alias !== $prefixedTable = $this->prefixTable($table)) {
            $prefixedTable .= ' AS ' . $this->escapeIdentifiers($alias);
        }

        return $prefixedTable;
    }

    /**
     * Recupère l'alias de la table
     */
    public function getTableAlias(string $table): array
    {
        $table = str_replace($this->prefix, '', trim($table));

        if (isset($this->aliasedTables[$table])) {
            return [$this->aliasedTables[$table], $table];
        }

        $tabs = explode(' ', $table);

        if (count($tabs) === 2) {
            $alias = $tabs[1];
            $table = $tabs[0];
        } elseif (preg_match('/\s+AS(.+)/i', $table, $matches)) {
            $alias = trim($matches[1] ?? $table);
            $table = isset($matches[1]) ? str_replace($matches[0], '', $table) : $table;
        } else {
            $key = array_search($table, $this->aliasedTables, true);

            $alias = $this->aliasedTables[$key] ?? $this->prefixTable($table);
            $table = $key !== false ? $key : $table;
        }

        if ($alias !== $table) {
            $this->aliasedTables[$table] = $alias;
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
     * Sets the Table Aliases to use. These are typically
     * collected during use of the Builder, and set here
     * so queries are built correctly.
     */
    public function setAliasedTables(array $aliases): self
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

    /*
    |--------------------------------------------------------------------------
    | Méthodes utilitaires
    |--------------------------------------------------------------------------
    */

    /**
     * Returns a string containing the version of the database being used.
     */
    public function getDriver(): string
    {
        $this->initialize();        
        
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }
    
    /**
     * Returns a string containing the version of the database being used.
     */
    public function getVersion(): string
    {
        $this->initialize();
                
        return $this->pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
    }

    /**
     * The name of the platform in use (MySQLi, Postgre, SQLite3, OCI8, etc)
     */
    public function getPlatform(): string
    {
        // pour le moment, on renvoie juste le driver tel qu'il est

        return $this->getDriver();
    }

    /**
     * Returns the name of the current database being used.
     */
    public function getDatabase(): string
    {
        return $this->config['database'] ?? '';
    }

    /**
     * Select a specific database table to use.
     */
    public function setDatabase(string $databaseName): bool
    {
        // À implémenter dans les classes filles si supporté
        return false;
    }

    /**
     * Returns the database prefix.
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * Set's the DB Prefix to something new without needing to reconnect
     */
    public function setPrefix(string $prefix = ''): string
    {
        $this->prefix = $prefix;

        return $this->prefix;
    }

    /**
     * Retourne une nouvelle instance non partagee du query builder pour cette connexion.
     * 
     * @param list<string>|string $tableName
     * 
     * @return BaseBuilder
     */
    public function table(array|string $tableName): BuilderInterface
    {
        return $this->newQuery()->table($tableName);
    }

    /**
     * Returns a new instance of the BaseBuilder class with a cleared FROM clause.
     * 
     * @return BaseBuilder
     */
    public function newQuery(): BuilderInterface
    {
        return new BaseBuilder($this);
    }


    /**
     * {@inheritDoc}
     */
    public function getLastQuery()
    {
        return null; // À implémenter si nécessaire
    }

    /**
     * {@inheritDoc}
     */
    public function error(): array
    {
        $errorInfo = $this->pdo?->errorInfo() ?? [];
        
        return [
            'code' => $errorInfo[1] ?? 0,
            'message' => $errorInfo[2] ?? ''
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function lastId(?string $table = null): ?int
    {
        try {
            return (int) $this->pdo->lastInsertId($table);
        } catch (PDOException) {
            return null;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function insertID(?string $table = null)
    {
        return $this->lastId($table);
    }

    public function affectedRows(): int
    {
        return $this->result?->affectedRows() ?? 0;
    }

    public function numRows(): int
    {
        return $this->result?->numRows() ?? 0;
    }

    /**
     * {@inheritDoc}
     */
    public function disableForeignKeyChecks()
    {
        return $this->statement($this->disableForeignKeyChecks);
    }

    /**
     * {@inheritDoc}
     */
    public function enableForeignKeyChecks()
    {
        return $this->statement($this->enableForeignKeyChecks);
    }
}
