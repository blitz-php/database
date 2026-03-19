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

use BadMethodCallException;
use BlitzPHP\Contracts\Database\BuilderInterface;
use BlitzPHP\Contracts\Database\ConnectionInterface;
use BlitzPHP\Contracts\Database\ResultInterface;
use BlitzPHP\Database\Builder\Compilers\MySQL as MySQLCompiler;
use BlitzPHP\Database\Builder\Compilers\Postgre as PostgreCompiler;
use BlitzPHP\Database\Builder\Compilers\QueryCompiler;
use BlitzPHP\Database\Builder\Compilers\SQLite as SQLiteCompiler;
use BlitzPHP\Database\Builder\Concerns\AdvancedMethods;
use BlitzPHP\Database\Builder\Concerns\BuildsQueries;
use BlitzPHP\Database\Builder\Concerns\CoreMethods;
use BlitzPHP\Database\Builder\Concerns\DataMethods;
use BlitzPHP\Database\Builder\Concerns\ProxyMethods;
use BlitzPHP\Database\Connection\BaseConnection;
use BlitzPHP\Database\Exceptions\DatabaseException;
use BlitzPHP\Database\Query\Expression;
use BlitzPHP\Database\Query\Result;
use BlitzPHP\Database\Utils;
use BlitzPHP\Traits\Support\ForwardsCalls;
use BlitzPHP\Utilities\Iterable\Arr;
use BlitzPHP\Utilities\Iterable\Collection;
use Closure;
use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * Fournit les principales méthodes du générateur de requêtes.
 * Les constructeurs spécifiques à la base de données peuvent avoir besoin de remplacer certaines méthodes pour les faire fonctionner.
 */
class BaseBuilder implements BuilderInterface
{
    use AdvancedMethods;
    use BuildsQueries;
    use CoreMethods;
    use DataMethods;
    use ForwardsCalls;
    use ProxyMethods;

    /**
     * État du mode de test du générateur.
     */
    protected bool $testMode = false;

    /**
     * Defini si la requête est en attente ou pas
     * Si la requête est en attente, on ne l'exécutera pas
     */
    protected bool $pending = false;

    /**
     * La table principale de la requête
     */
    protected string $from = '';

    /**
     * Liste des tables de la requête (pour les sous-requêtes)
     */
    protected array $tables = [];

    /**
     * Colonnes à sélectionner
     */
    protected array $columns = [];

    /**
     * Liste des conditions WHERE
     */
    protected array $wheres = [];

    /**
     * Liste des jointures
     */
    protected array $joins = [];

    /**
     * Liste des ORDER BY
     */
    protected array $orders = [];

    /**
     * Liste des GROUP BY
     */
    protected array $groups = [];

    /**
     * Liste des conditions HAVING
     */
    protected array $havings = [];

    /**
     * Liste des requêtes UNION
     */
    protected array $unions = [];

    /**
     * Valeurs pour INSERT/UPDATE
     */
    protected array $values = [];

    /**
     * Bindings pour les requêtes préparées
     */
    protected BindingCollection $bindings;

    /**
     * Type d'opération CRUD
     */
    protected string $crud = 'select';

    /**
     * Option DISTINCT
     */
    protected bool|string $distinct = false;

    /**
     * Option IGNORE
     */
    protected bool $ignore = false;

    /**
     * LIMIT
     */
    protected ?int $limit = null;

    /**
     * OFFSET
     */
    protected ?int $offset = null;

    /**
     * Verrouillage (FOR UPDATE, LOCK IN SHARE MODE, etc.)
     */
    protected ?string $lock = null;

    /**
     * Colonnes uniques pour UPSERT
     */
    protected array $uniqueBy = [];

    /**
     * Colonnes à mettre à jour pour UPSERT
     */
    protected array $updateColumns = [];

    /**
     * Les callbacks qui doivent être invoqués avant l'exécution de la requête.
     *
     * @var list<callable($this): void>
     */
    protected array $beforeQueryCallbacks = [];

    /**
     * Les callbacks qui doivent être invoqués après la récupération des données de la base de données.
     *
     * @var list<callable(mixed): mixed>
     */
    protected array $afterQueryCallbacks = [];

    protected QueryCompiler $compiler;

    /**
     * @param BaseConnection $db
     */
    public function __construct(protected ConnectionInterface $db)
    {
        $this->bindings = new BindingCollection();
        $this->compiler = $this->createCompiler();
    }

    /**
     * Methode magique pour recupere une valeur interne du Builder
     *
     * @internal
     */
    public function __get(string $name): mixed
    {
        if (property_exists($this, $name)) {
            return $this->{$name};
        }

        throw new RuntimeException(sprintf('La propriété %s n\'existe pas', $name));
    }

    /**
     * Crée le compilateur approprié pour le driver
     */
    protected function createCompiler(): QueryCompiler
    {
        $driver = $this->db->getDriver();

        return match ($driver) {
            'mysql'  => new MySQLCompiler($this->db),
            'pgsql'  => new PostgreCompiler($this->db),
            'sqlite' => new SQLiteCompiler($this->db),
            default  => throw new DatabaseException("Unsupported driver: {$driver}"),
        };
    }

    /**
     * Renvoie la connexion actuelle à la base de données
     *
     * @return BaseConnection|ConnectionInterface
     */
    public function db(): ConnectionInterface
    {
        return $this->db;
    }

    /**
     * Obtient une nouvelle instance du query builder.
     */
    public function newQuery(): static
    {
        return new static($this->db);
    }

    /**
     * Définit un statut de mode de test.
     */
    public function testMode(bool $mode = true): static
    {
        $this->testMode = $mode;

        return $this;
    }

    /**
     * Définit un statut de l'état d'attente de la requête.
     */
    public function pending(bool $state = true): static
    {
        $this->pending = $state;

        return $this;
    }

    /**
     * Recupere le nom de la table principale.
     */
    public function getTable(): string
    {
        if ('' === $table = $this->from ?: $this->tables[0] ?? '') {
            return '';
        }

        return $this->removeAlias($table);
    }

    /**
     * Génère la partie FROM de la requête
     *
     * @param list<string>|string|null $from
     */
    public function from($from, bool $overwrite = false): static
    {
        if ($from === null) {
            $this->from   = '';
            $this->tables = [];

            return $this;
        }

        if ($overwrite) {
            $this->tables = [];
        }

        if (is_string($from)) {
            $from = explode(',', $from);
        }

        foreach ($from as $table) {
            $this->tables[] = $this->db->makeTableName($table);
        }

        $this->tables = array_unique($this->tables);
        $this->from   = end($this->tables);

        return $this;
    }

    /**
     * @param BaseBuilder $from
     */
    public function fromSubquery(BuilderInterface $from, string $alias = ''): static
    {
        $table = $this->buildSubquery($from, true, $alias);
        $this->db->addTableAlias($alias);

        $this->reset();
        $this->tables = [$table];
        $this->bindings->merge($from->bindings);

        return $this;
    }

    /**
     * Génère la partie FROM de la requête
     *
     * @param list<string>|string|null $from
     *
     * @alias self::from()
     */
    public function table($from): static
    {
        return $this->from($from, true);
    }

    /**
     * Définit la table dans laquelle les données seront insérées
     */
    public function into(string $table): static
    {
        return $this->table($table);
    }

    /**
     * Définit les colonnes à sélectionner
     *
     * @param array|Expression|string $columns Colonnes à sélectionner
     */
    public function select($columns = '*'): static
    {
        // Gestion de l'ancienne signature avec limit/offset
        if (func_num_args() > 1 && is_int(func_get_arg(1))) {
            if (! $this->testMode) {
                @trigger_error(
                    'Passing limit/offset to select() is deprecated. Use limit() and offset() methods instead.',
                    E_USER_DEPRECATED,
                );
            }

            $limit  = func_get_arg(1);
            $offset = func_num_args() > 2 ? func_get_arg(2) : null;
            $this->limit($limit, $offset);

            $columns = func_get_arg(0);
        }

        if ($columns === '' || $columns === []) {
            $columns = ['*'];
        }

        if (is_string($columns)) {
            $columns = array_map('trim', explode(',', $columns));
        }

        if (! is_array($columns)) {
            $columns = [$columns];
        }

        foreach ($columns as $key => $column) {
            if ($column instanceof Expression) {
                $this->columns[] = $column;
            } elseif (is_string($key)) {
                // Format avec alias: ['alias' => 'column']
                $this->columns[] = $this->buildColumnName($key) . ' AS ' . $this->db->escapeIdentifiers($column);
            } elseif (is_string($column)) {
                $this->columns[] = $this->buildColumnName($column);
            }
        }

        $this->columns = array_unique($this->columns);

        return $this->asCrud('select');
    }

    /**
     * Sélectionne avec un alias explicite
     */
    public function selectAs(string $column, string $alias): static
    {
        $this->columns[] = $this->buildColumnName($column) . ' AS ' . $this->db->escapeIdentifiers($alias);

        return $this->asCrud('select');
    }

    /**
     * Sélectionne une expression avec alias
     */
    public function selectExpr(string $expression, string $alias, array $bindings = []): static
    {
        $this->columns[] = new Expression($expression);
        $this->bindings->addMany($bindings);

        return $this->asCrud('select');
    }

    /**
     * Ajoute une sous requete a la selection
     */
    public function selectSubquery(BuilderInterface $subquery, string $as): static
    {
        $this->columns[] = $this->buildSubquery($subquery, true, $as);

        return $this->asCrud('select');
    }

    /**
     * Ajoute une clause DISTINCT
     */
    public function distinct(bool $value = true): static
    {
        $this->distinct = $value;

        return $this->asCrud('select');
    }

    /**
     * Ajoute une clause DISTINCT ON (PostgreSQL)
     */
    public function distinctOn(array $columns): static
    {
        if ($this->db->getDriver() !== 'pgsql') {
            throw new DatabaseException('DISTINCT ON is only supported by PostgreSQL');
        }

        $this->distinct = 'DISTINCT ON (' . implode(', ', array_map([$this->db, 'escapeIdentifiers'], $columns)) . ')';

        return $this->asCrud('select');
    }

    /**
     * Ajoute une clause LIMIT
     */
    public function limit(int $limit, ?int $offset = null): static
    {
        $this->limit = max(0, $limit);

        if ($offset !== null) {
            $this->offset = max(0, $offset);
        }

        return $this;
    }

    /**
     * Ajoute une clause OFFSET
     */
    public function offset(int $offset, ?int $limit = null): static
    {
        $this->offset = max(0, $offset);

        if ($limit !== null) {
            $this->limit = max(0, $limit);
        }

        return $this;
    }

    /**
     * Ajoute une option IGNORE
     */
    public function ignore(bool $value = true): static
    {
        $this->ignore = $value;

        return $this;
    }

    /**
     * Définit les valeurs pour INSERT/UPDATE
     *
     * @param array|object|string $key   Nom du champ, ou tableau de paire champs/valeurs
     * @param mixed               $value Valeur du champ, si $key est un simple champ
     */
    public function set($key, $value = ''): static
    {
        if (is_string($key)) {
            $key = [$key => $value];
        }

        $key = $this->objectToArray($key);

        foreach ($key as $k => $v) {
            if ($v instanceof Expression) {
                $this->values[$k] = $v;
            } else {
                $this->values[$k] = $v;
                $this->bindings->add($v, 'values');
            }
        }

        return $this;
    }

    /**
     * Exécute une requête d'insertion
     *
     * @return bool|static|string
     */
    public function insert(array|object $data = [])
    {
        return $this->run('successfulable', function () use ($data) {
            $this->crud = 'insert';

            $this->set($data);

            if ($this->values === [] && ! $this->pending) {
                throw new DatabaseException('You must give entries to insert.');
            }
        });
    }

    /**
     * Insertion avec IGNORE
     *
     * @return bool|static|string
     */
    public function insertIgnore(array|object $data = [])
    {
        return $this->ignore(true)->insert($data);
    }

    /**
     * Insertion multiple
     *
     * @param list<array|object> $data      Tableau a deux dimensions contenant les valeurs a inserer
     * @param int                $chunkSize Taille optimale des chunks
     *
     * @return int|string
     */
    public function bulkInsert(array $data, bool $ignore = false, int $chunkSize = 100)
    {
        if ($data === []) {
            return 0;
        }

        if (2 !== Arr::maxDimensions($data)) {
            throw new BadMethodCallException('Bad usage of ' . static::class . '::' . __METHOD__ . ' method');
        }

        $columns      = array_keys((array) reset($data));
        $placeholders = '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $columnList   = implode(', ', array_map([$this->db, 'escapeIdentifiers'], $columns));
        $table        = $this->db->escapeIdentifiers($this->getTable());

        $totalAffected = 0;
        $chunks        = array_chunk($data, $chunkSize);
        $allSql        = [];

        $callback = function () use ($ignore, $chunks, $table, $columnList, $placeholders, &$totalAffected, &$allSql) {
            foreach ($chunks as $chunk) {
                $values   = array_fill(0, count($chunk), $placeholders);
                $bindings = [];

                foreach ($chunk as $row) {
                    array_push($bindings, ...array_values((array) $row));
                }

                $sql = $this->compiler->compileInsertion($table, $columnList, implode(', ', $values), $ignore);

                if ($this->testMode) {
                    $allSql[] = $sql;
                } else {
                    $totalAffected += $this->db->affectingStatement($sql, $bindings);
                }
            }

            return [$allSql, $totalAffected];
        };

        [$allSql, $totalAffected] = $this->db->transaction($callback);

        return $this->testMode ? implode('; ', $allSql) : $totalAffected;
    }

    /**
     * Insertion multiple avec IGNORE
     *
     * @param list<array|object> $data      Tableau a deux dimensions contenant les valeurs a inserer
     * @param int                $chunkSize Taille optimale des chunks
     *
     * @return int|string
     */
    public function bulkInsertIgnore(array $data, int $chunkSize = 100)
    {
        return $this->bulkInsert($data, true, $chunkSize);
    }

    /**
     * UPSERT (INSERT ... ON DUPLICATE KEY UPDATE)
     *
     * @return int|static|string
     */
    public function upsert(array $values, array $uniqueBy, ?array $update = null)
    {
        return $this->run('affectable', function () use ($values, $uniqueBy, $update) {
            $this->crud = 'upsert';

            // Support des insertions multiples
            if (isset($values[0]) && is_array($values[0])) {
                $this->values = $values;
            } else {
                $this->values = [$values];
            }

            $this->uniqueBy      = $uniqueBy;
            $this->updateColumns = $update ?? array_keys($values[0] ?? $values);
        });
    }

    /**
     * INSERT OR IGNORE
     *
     * @return int|static|string
     */
    public function insertOrIgnore(array $values)
    {
        return $this->ignore(true)->upsert($values, [], []);
    }

    /**
     * Exécute une requête de mise à jour.
     *
     * @param array|object $data Tableau ou objet de clés et de valeurs
     *
     * @return int|static|string
     */
    public function update(array|object $data = [])
    {
        return $this->run('affectable', function () use ($data) {
            $this->crud = 'update';

            $this->set($data);

            if ($this->values === [] && ! $this->pending) {
                throw new DatabaseException('You must give entries to insert.');
            }
        });
    }

    /**
     * Exécute une requête de remplacement.
     *
     * @param array|object $data Tableau ou objet de clés et de valeurs à remplacer
     *
     * @return int|static|string
     */
    public function replace(array|object $data = [])
    {
        return $this->run('affectable', function () use ($data) {
            $this->crud = 'replace';

            $this->set($data);

            if ($this->values === [] && ! $this->pending) {
                throw new DatabaseException('You must give entries to insert.');
            }
        });
    }

    /**
     * UPDATE OR INSERT
     */
    public function updateOrInsert(array $attributes, array $values = []): bool
    {
        $exists = $this->clone()->where($attributes)->exists();

        if (! $exists) {
            return $this->insert(array_merge($attributes, $values)) !== false;
        }

        return $this->where($attributes)->update($values) > 0;
    }

    /**
     * FIRST OR CREATE
     */
    public function firstOrCreate(array $attributes, array $values = [])
    {
        $exists = $this->clone()->where($attributes)->first();

        if ($exists) {
            return $exists;
        }

        $this->insert(array_merge($attributes, $values));

        return $this->clone()->where($attributes)->first();
    }

    /**
     * FIRST OR NEW
     */
    public function firstOrNew(array $attributes, array $values = [])
    {
        $exists = $this->clone()->where($attributes)->first();

        if ($exists) {
            return $exists;
        }

        return (object) array_merge($attributes, $values);
    }

    /**
     * Exécute une requête de suppression.
     *
     * @param array $where Conditions de suppression
     *
     * @return int|self|string
     */
    public function delete(?array $where = null, ?int $limit = null)
    {
        return $this->run('affectable', function () use ($where, $limit) {
            $this->crud = 'delete';

            if ($where !== null && $where !== []) {
                $this->where($where);
            }

            if ($limit !== null) {
                $this->limit($limit);
            }
        });
    }

    /**
     * Exécute une requête TRUNCATE
     *
     * Si la base de donnee ne supporte pas la commande truncate(),
     * cette fonction va executer "DELETE FROM table"
     *
     * @return bool|static|string TRUE on success, FALSE on failure, string on testMode
     */
    public function truncate(?string $table = null)
    {
        return $this->run('successfulable', function () use ($table) {
            $this->crud = 'truncate';

            if ($table !== null && $table !== '') {
                $this->table($table);
            }
        });
    }

    /**
     * Exécute la requête construite
     *
     * @return Result
     */
    public function execute(): ResultInterface
    {
        $this->applyBeforeQueryCallbacks();

        try {
            $result = $this->query($this->toSql(), $this->getBindings());

            return $this->applyAfterQueryCallbacks($result);
        } finally {
            $this->reset();
        }
    }

    /**
     * Exécute une requête SQL directe
     *
     * @return Result
     */
    public function query(string $sql, array $params = []): ResultInterface
    {
        return $this->db->query($sql, $params);
    }

    /**
     * @param 'affectable'|'successfulable' $as
     *
     * @return ($as is 'affectable' ? int|static|string : bool|static|string)
     */
    protected function run(string $as, Closure $callback)
    {
        $callback();

        if ($this->testMode) {
            $sql = $this->toRawSql();
            $this->reset();

            return $sql;
        }

        if ($this->pending) {
            return $this;
        }

        $result = $this->execute();

        if ($result instanceof Result) {
            return $as === 'affectable' ? $result->affectedRows() : $result->successful();
        }

        return $result;
    }

    /**
     * Récupère les résultats de la requete
     */
    public function result(int|string $type = PDO::FETCH_OBJ): array
    {
        return $this->execute()->get($type);
    }

    /**
     * Récupère les résultats de la requete dans une collection
     *
     * @return Collection<int, TValue>
     */
    public function collect(int|string $type = PDO::FETCH_OBJ): Collection
    {
        return new Collection($this->result($type));
    }

    /**
     * Récupère le premier résultat
     *
     * @param mixed $type
     */
    public function first($type = PDO::FETCH_OBJ): mixed
    {
        return $this->limit(1)->execute()->first($type);
    }

    /**
     * Récupère une ligne spécifique
     *
     * @param mixed $type
     */
    public function row(int $index, $type = PDO::FETCH_OBJ): mixed
    {
        return $this->execute()->row($index, $type);
    }

    /**
     * Récupère une valeur spécifique
     *
     * @return list<mixed>|mixed
     */
    public function value(array|string $name)
    {
        $names  = (array) $name;
        $values = [];

        $row = $this->select($names)->first(PDO::FETCH_OBJ);

        foreach ($names as $v) {
            if (is_string($v)) {
                $values[] = $row->{$v} ?? null;
            }
        }

        return is_string($name) ? $values[0] : $values;
    }

    /**
     * Récupère plusieurs valeurs
     *
     * @return list<mixed>
     */
    public function values(array|string $name): array
    {
        $names   = (array) $name;
        $columns = [];

        $rows = $this->select($names)->all(PDO::FETCH_OBJ);

        foreach ($rows as $row) {
            $values = [];

            foreach ($names as $v) {
                if (is_string($v)) {
                    $values[$v] = $row->{$v} ?? null;
                }
            }
            $columns[] = is_string($name) ? ($values[$name] ?? null) : $values;
        }

        return $columns;
    }

    /**
     * Vérifie si des enregistrements existent
     */
    public function exists(): bool
    {
        return $this->clone()->selectRaw('1')->limit(1)->first() !== null;
    }

    /**
     * Vérifie si des enregistrements n'existent pas
     */
    public function doesntExist(): bool
    {
        return ! $this->exists();
    }

    /**
     * Verrouillage pour mise à jour
     */
    public function lockForUpdate(): static
    {
        $this->lock = match ($this->db->getDriver()) {
            'sqlite' => '', // SQLite ne supporte pas le verrouillage
            default  => 'FOR UPDATE',
        };

        return $this;
    }

    /**
     * Verrouillage partagé
     */
    public function sharedLock(): static
    {
        $this->lock = match ($this->db->getDriver()) {
            'mysql'  => 'LOCK IN SHARE MODE',
            'pgsql'  => 'FOR SHARE',
            'sqlite' => '', // SQLite ne supporte pas le verrouillage
            default  => 'LOCK IN SHARE MODE',
        };

        return $this;
    }

    /**
     * Verrouillage SKIP LOCKED
     */
    public function skipLocked(): static
    {
        $this->lock .= ' SKIP LOCKED';

        return $this;
    }

    /**
     * Verrouillage NOWAIT
     */
    public function lockNowait(): static
    {
        $this->lock .= ' NOWAIT';

        return $this;
    }

    /**
     * Incremente un champ numerique par la valeur specifiee.
     *
     * @param array<string, mixed> $extra
     *
     * @return int<0, max>|static|string
     *
     * @throws DatabaseException
     */
    public function increment(string $column, float|int $value = 1, array $extra = [])
    {
        return $this->incrementEach([$column => $value], $extra);
    }

    /**
     * Incrémente les valeurs des colonnes spécifiées par les montants donnés.
     *
     * @param array<string, float|int|numeric-string> $columns
     * @param array<string, mixed>                    $extra
     *
     * @return int<0, max>|static|string
     *
     * @throws InvalidArgumentException
     */
    public function incrementEach(array $columns, array $extra = [])
    {
        foreach ($columns as $column => $amount) {
            if (! is_numeric($amount)) {
                throw new InvalidArgumentException("Non-numeric value passed as increment amount for column: '{$column}'.");
            }
            if (! is_string($column)) {
                throw new InvalidArgumentException('Non-associative array passed to incrementEach method.');
            }

            $columns[$column] = new Expression($this->db->escapeIdentifiers($column) . " + {$amount}");
        }

        return $this->update(array_merge($columns, $extra));
    }

    /**
     * Decremente un champ numerique par la valeur specifiee.
     *
     * @param array<string, mixed> $extra
     *
     * @return int<0, max>|static|string
     *
     * @throws DatabaseException
     */
    public function decrement(string $column, float|int $value = 1, array $extra = [])
    {
        return $this->decrementEach([$column => $value], $extra);
    }

    /**
     * Décrémente les valeurs des colonnes spécifiées par les montants donnés.
     *
     * @param array<string, float|int|numeric-string> $columns
     * @param array<string, mixed>                    $extra
     *
     * @return int<0, max>|static|string
     *
     * @throws InvalidArgumentException
     */
    public function decrementEach(array $columns, array $extra = [])
    {
        foreach ($columns as $column => $amount) {
            if (! is_numeric($amount)) {
                throw new InvalidArgumentException("Non-numeric value passed as decrement amount for column: '{$column}'.");
            }
            if (! is_string($column)) {
                throw new InvalidArgumentException('Non-associative array passed to decrementEach method.');
            }

            $columns[$column] = new Expression($this->db->escapeIdentifiers($column) . " - {$amount}");
        }

        return $this->update(array_merge($columns, $extra));
    }

    /**
     * Enregistre une closure à invoquer avant l'exécution de la requête.
     *
     * @param callable($this): void $callback
     */
    public function beforeQuery(callable $callback): static
    {
        $this->beforeQueryCallbacks[] = $callback;

        return $this;
    }

    /**
     * Invoque les callbacks de modification "avant requête".
     */
    public function applyBeforeQueryCallbacks(): void
    {
        foreach ($this->beforeQueryCallbacks as $callback) {
            $callback($this);
        }

        $this->beforeQueryCallbacks = [];
    }

    /**
     * Enregistre une closure à invoquer après l'exécution de la requête.
     *
     * @param callable(mixed): mixed $callback
     */
    public function afterQuery(callable $callback): static
    {
        $this->afterQueryCallbacks[] = $callback;

        return $this;
    }

    /**
     * Invoque les callbacks de modification "après requête".
     */
    public function applyAfterQueryCallbacks(mixed $result): mixed
    {
        foreach ($this->afterQueryCallbacks as $callback) {
            $result = $callback($result) ?: $result;
        }

        return $result;
    }

    public function explain(): array
    {
        $sql = 'EXPLAIN ' . $this->toSql();

        return $this->query($sql, $this->getBindings())->resultArray();
    }

    /**
     * Récupère le SQL sans l'exécuter
     */
    public function toSql(): string
    {
        return $this->compiler->compile($this);
    }

    /**
     * Récupère le SQL et réinitialise le builder
     */
    public function sql(bool $preserve = false): string
    {
        $sql = $this->toRawSql();

        if (! $preserve) {
            $this->reset();
        }

        return $sql;
    }

    /**
     * Vide les bindings d'un context
     */
    public function clearBindings(?string $context = null): void
    {
        $this->bindings->clear($context);
    }

    /**
     * Récupère les bindings utilisés
     */
    public function getBindings(): array
    {
        $types = match ($this->crud) {
            'select' => ['where', 'having', 'order', 'union'],
            'insert', 'replace' => ['values'],
            'upsert'   => ['values', 'uniqueBy'], // Si on a des bindings pour les conflits
            'update'   => ['values', 'where', 'join'],
            'delete'   => ['where', 'join'],
            'truncate' => null, // Pas de bindings pour TRUNCATE
            default    => [], // Fallback à tous
        };

        return $types === null
            ? []
            : $this->db->prepareBindings($this->bindings->getOrdered($types));
    }

    /**
     * Récupère le SQL avec les bindings échappés
     */
    public function toRawSql(): string
    {
        $sql      = $this->toSql();
        $bindings = $this->getBindings();

        foreach ($bindings as $value) {
            $sql = preg_replace('/\?/', $this->db->quote($value), $sql, 1);
        }

        return $sql;
    }

    /**
     * Affiche le SQL pour debug
     */
    public function dump(): static
    {
        dump($this->toRawSql());

        return $this;
    }

    /**
     * Affiche le SQL et termine le script
     */
    public function dd(): void
    {
        dd($this->toRawSql());
    }

    /**
     * Réinitialise le builder
     */
    public function reset(): static
    {
        $this->from          = '';
        $this->tables        = [];
        $this->columns       = [];
        $this->wheres        = [];
        $this->joins         = [];
        $this->orders        = [];
        $this->groups        = [];
        $this->havings       = [];
        $this->unions        = [];
        $this->values        = [];
        $this->bindings      = new BindingCollection();
        $this->distinct      = false;
        $this->ignore        = false;
        $this->limit         = null;
        $this->offset        = null;
        $this->lock          = null;
        $this->uniqueBy      = [];
        $this->updateColumns = [];
        $this->db->setAliasedTables([]);

        return $this->asCrud('select');
    }

    /**
     * Crée une copie du builder
     */
    public function clone(): static
    {
        $clone           = clone $this;
        $clone->bindings = clone $this->bindings;

        return $clone;
    }

    /**
     * Définit le type d'opération CRUD à effectuer
     *
     * @internal
     */
    protected function asCrud(string $type): static
    {
        $this->crud = $type;

        return $this;
    }

    /**
     * Convertit un objet en tableau
     */
    protected function objectToArray(array|object $object): array
    {
        if (! is_object($object)) {
            return $object;
        }

        if (method_exists($object, 'toArray')) {
            return $object->toArray();
        }

        $array = [];

        foreach (get_object_vars($object) as $key => $val) {
            if (! is_object($val) && ! is_array($val)) {
                $array[$key] = $val;
            }
        }

        return $array;
    }

    /**
     * Supprime l'alias d'un nom de table
     *
     * @internal
     */
    protected function removeAlias(string $from): string
    {
        if (str_contains($from, ' ')) {
            $from  = preg_replace('/\s+AS\s+/i', ' ', $from);
            $parts = explode(' ', $from);
            $from  = $parts[0];
        }

        return $from;
    }

    /**
     * Construit une sous-requête
     *
     * @param Closure|self $builder
     */
    protected function buildSubquery(BuilderInterface|Closure $builder, bool $wrapped = false, string $alias = ''): string
    {
        if ($builder instanceof Closure) {
            $builder($builder = $this->db->newQuery());
        }

        if ($builder === $this) {
            throw new DatabaseException('The subquery cannot be the same object as the main query object.');
        }

        $subquery = $builder->toSql();

        if ($wrapped) {
            $subquery = '(' . $subquery . ')';
            $alias    = trim($alias);

            if ($alias !== '') {
                $subquery .= ' AS ' . $this->db->escapeIdentifiers($alias);
            }
        }

        return $subquery;
    }

    protected function buildColumnName(string $column): string
    {
        $column = trim($column);

        // Cas spécial: expression SQL brute (ne pas parser)
        if (preg_match('/^\(.*\)$/', $column) || Utils::isRawExpression($column)) {
            return $column;
        }

        $parts     = explode(' ', $column);
        $column    = array_shift($parts);
        $operator  = implode(' ', $parts);
        $aggregate = null;
        $alias     = '';

        // Étape 1: Détection des fonctions SQL composées (ex: "NOT EXISTS")
        if (isset($parts[0])) {
            $possibleFunction = rtrim($column) . ' ' . ltrim($parts[0]);
            if (Utils::isSqlFunction($possibleFunction)) {
                $column .= ' ' . array_shift($parts);
                $operator = implode(' ', $parts);
            }
        }

        // Étape 2: Extraction des alias (améliorée)
        if ($operator !== '' && ! Utils::hasOperator($operator)) {
            if (Utils::isAlias($operator)) {
                $alias    = Utils::extractAlias($operator);
                $operator = '';
            } else {
                // Ce n'est pas un alias, c'est un opérateur ou une clause
                $column   = implode(' ', [$column, $operator]);
                $operator = '';
            }
        }

        // Étape 3: Détection des fonctions d'agrégation
        $functionPattern = '/^(' . implode('|', array_map('preg_quote', Utils::SQL_FUNCTIONS)) . ')\s*\(\s*(.+?)\s*\)$/i';
        if (preg_match($functionPattern, $column, $matches)) {
            $aggregate = $matches[1];
            $column    = $matches[2];
        }

        // Étape 4: Gestion des alias de table
        $column = Utils::formatQualifiedColumn($this->db, $column);

        // Étape 5: Reconstruction avec fonction d'agrégation
        if ($aggregate !== null) {
            $column = strtoupper($aggregate) . '(' . $column . ')';
        }

        // Étape 6: Ajout de l'alias
        if ($alias !== '') {
            $column .= ' AS ' . $this->db->escapeIdentifiers($alias);
        }

        return $column;
    }
}
