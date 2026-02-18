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
use BlitzPHP\Database\Builder\Compilers\MySQL as MySQLCompiler;
use BlitzPHP\Database\Builder\Compilers\Postgre as PostgreCompiler;
use BlitzPHP\Database\Builder\Compilers\QueryCompiler;
use BlitzPHP\Database\Builder\Compilers\SQLite as SQLiteCompiler;
use BlitzPHP\Database\Builder\Concerns\AdvancedMethods;
use BlitzPHP\Database\Builder\Concerns\CoreMethods;
use BlitzPHP\Database\Builder\Concerns\DataMethods;
use BlitzPHP\Database\Builder\Concerns\ProxyMethods;
use BlitzPHP\Database\Connection\BaseConnection;
use BlitzPHP\Database\Exceptions\DatabaseException;
use BlitzPHP\Database\Query;
use BlitzPHP\Database\Query\Expression;
use BlitzPHP\Database\Result\BaseResult;
use BlitzPHP\Database\Utils;
use BlitzPHP\Traits\Conditionable;
use BlitzPHP\Utilities\Iterable\Arr;
use Closure;
use PDO;
use RuntimeException;

/**
 * Fournit les principales méthodes du générateur de requêtes.
 * Les constructeurs spécifiques à la base de données peuvent avoir besoin de remplacer certaines méthodes pour les faire fonctionner.
 */
class BaseBuilder implements BuilderInterface
{
    use Conditionable;
    use AdvancedMethods;
    use CoreMethods;
    use DataMethods;
    use ProxyMethods;

    /**
     * État du mode de test du générateur.
     */
    protected bool $testMode = false;

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
    protected string|bool $distinct = false;

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
     * @var QueryCompiler
     */
    protected QueryCompiler $compiler;

    /**
     * @param BaseConnection $db
     */
    public function __construct(protected ConnectionInterface $db, protected array $options = [])
    {
        foreach ($options as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }

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
        $driver = $this->db->getPlatform();
        
        return match($driver) {
            'mysql' => new MySQLCompiler($this->db),
            'pgsql' => new PostgreCompiler($this->db),
            'sqlite' => new SQLiteCompiler($this->db),
            default => throw new DatabaseException("Unsupported driver: {$driver}")
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
        return new static($this->db, $this->options);
    }

    /**
     * Définit un statut de mode de test.
     */
    public function testMode(bool $mode = true): self
    {
        $this->testMode = $mode;

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
    public function from($from, bool $overwrite = false): self
    {
        if ($from === null) {
            $this->from = '';
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
    public function fromSubquery(BuilderInterface $from, string $alias = ''): self
    {
        $table = $this->buildSubquery($from, true, $alias);
        $this->db->addTableAlias($alias);
        $this->tables[] = $table;
        $this->from = $table;

        return $this;
    }

    /**
     * Génère la partie FROM de la requête
     *
     * @param list<string>|string|null $from
     *
     * @alias self::from()
     */
    public function table($from): self
    {
        return $this->from($from, true);
    }

    /**
     * Définit la table dans laquelle les données seront insérées
     */
    public function into(string $table): self
    {
        return $this->table($table);
    }

    /**
     * Définit les colonnes à sélectionner
     *
     * @param array|string|Expression $columns Colonnes à sélectionner
     */
    public function select($columns = '*'): self
    {
        // Gestion de l'ancienne signature avec limit/offset
        if (func_num_args() > 1 && is_int(func_get_arg(1))) {
            trigger_error(
                'Passing limit/offset to select() is deprecated. Use limit() and offset() methods instead.',
                E_USER_DEPRECATED
            );
            
            $limit = func_get_arg(1);
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
    public function selectAs(string $column, string $alias): self
    {
        $this->columns[] = $this->buildColumnName($column) . ' AS ' . $this->db->escapeIdentifiers($alias);
        
        return $this->asCrud('select');
    }

    /**
     * Sélectionne une expression avec alias
     */
    public function selectExpr(string $expression, string $alias, array $bindings = []): self
    {
        $this->columns[] = new Expression($expression);
        $this->bindings->addMany($bindings);
        return $this->asCrud('select');
    }

    /**
     * Ajoute une sous requete a la selection
     */
    public function selectSubquery(BuilderInterface $subquery, string $as): self
    {
        $this->columns[] = $this->buildSubquery($subquery, true, $as);
        return $this->asCrud('select');
    }

    /**
     * Ajoute une clause DISTINCT
     */
    public function distinct(bool $value = true): self
    {
        $this->distinct = $value;
        return $this->asCrud('select');
    }

    /**
     * Ajoute une clause DISTINCT ON (PostgreSQL)
     */
    public function distinctOn(array $columns): self
    {
        if ($this->db->getPlatform() !== 'pgsql') {
            throw new DatabaseException('DISTINCT ON is only supported by PostgreSQL');
        }

        $this->distinct = 'DISTINCT ON (' . implode(', ', array_map([$this->db, 'escapeIdentifiers'], $columns)) . ')';
        return $this->asCrud('select');
    }
    
    /**
     * Ajoute une clause LIMIT
     */
    public function limit(int $limit, ?int $offset = null): self
    {
        $this->limit = $limit;
        
        if ($offset !== null) {
            $this->offset = $offset;
        }

        return $this;
    }

    /**
     * Ajoute une clause OFFSET
     */
    public function offset(int $offset, ?int $limit = null): self
    {
        $this->offset = $offset;
        
        if ($limit !== null) {
            $this->limit = $limit;
        }

        return $this;
    }

    /**
     * Ajoute une option IGNORE
     */
    public function ignore(bool $value = true): self
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
    public function set($key, $value = ''): self
    {
        $key = $this->objectToArray($key);

        if (!is_array($key)) {
            $key = [$key => $value];
        }

        foreach ($key as $k => $v) {
            if ($v instanceof Expression) {
                $this->values[$k] = $v;
            } else {
                $this->values[$k] = $v;
                $this->bindings->add($v);
            }
        }

        return $this;
    }

    /**
     * Exécute une requête d'insertion
     *
     * @return BaseResult|self|string
     */
    public function insert(array|object $data = [], bool $execute = true)
    {
        $this->crud = 'insert';

        $data = $this->objectToArray($data);

        if (empty($data) && $this->values === []) {
            if (true === $execute) {
                throw new DatabaseException('You must give entries to insert.');
            }

            return $this;
        }

        if ($data !== []) {
            $this->set($data);
        }

        if ($this->testMode) {
            return $this->compiler->compileInsert($this);
        }
        if (true === $execute) {
            return $this->execute();
        }

        return $this;
    }

    /**
     * Insertion avec IGNORE
     *
     * @return BaseResult|self|string
     */
    public function insertIgnore(array|object $data, $execute = true)
    {
        return $this->ignore(true)->insert($data, $execute);
    }

    /**
     * Insertion multiple
     *
     * @param list<array|object> $data Tableau a deux dimensions contenant les valeurs a inserer
     *
     * @return BaseResult|string
     */
    public function bulkInsert(array $data, bool $ignore = false)
    {
        if (2 !== Arr::maxDimensions($data)) {
            throw new BadMethodCallException('Bad usage of ' . static::class . '::' . __METHOD__ . ' method');
        }

        $originalValues = $this->values;
        $originalBindings = clone $this->bindings;
        
        $allSql = [];

        foreach ($data as $item) {
            $this->values = [];
            $this->bindings = new BindingCollection();
            
            $this->ignore($ignore)->insert($item, false);
            $allSql[] = $this->compiler->compileInsert($this);
        }

        $this->values = $originalValues;
        $this->bindings = $originalBindings;
        
        $sql = implode('; ', $allSql);

        if ($this->testMode) {
            return $sql;
        }

        return $this->query($sql, $this->bindings->getValues());
    }

    /**
     * Insertion multiple avec IGNORE
     *
     * @param list<array|object> $data Tableau a deux dimensions contenant les valeurs a inserer
     *
     * @return BaseResult|string
     */
    public function bulkInsertIgnore(array $data)
    {
        return $this->bulkInsert($data, true);
    }

    /**
     * Alias de bulkInsert() pour la rétrocompatibilité
     * 
     * @deprecated use bulkInsert instead
     */
    final public function bulckInsert(array $data, bool $ignore = false)
    {
        trigger_error('bulckInsert() is deprecated. Use bulkInsert() instead.', E_USER_DEPRECATED);
        return $this->bulkInsert($data, $ignore);
    }

    /**
     * Alias de bulkInsertIgnore() pour la rétrocompatibilité
     * 
     * @deprecated use bulkInsertIgnore instead
     */
    final public function bulckInsertIgnore(array $data)
    {
        trigger_error('bulckInsertIgnore() is deprecated. Use bulkInsertIgnore() instead.', E_USER_DEPRECATED);
        return $this->bulkInsertIgnore($data);
    }

    /**
     * UPSERT (INSERT ... ON DUPLICATE KEY UPDATE)
     * 
     * @return int|string
     */
    public function upsert(array $values, array $uniqueBy, ?array $update = null)
    {
        $this->crud = 'upsert';
        
        // Support des insertions multiples
        if (isset($values[0]) && is_array($values[0])) {
            $this->values = $values;
        } else {
            $this->values = [$values];
        }
        
        $this->uniqueBy = $uniqueBy;
        $this->updateColumns = $update ?? array_keys($values[0] ?? $values);

        if ($this->testMode) {
            return $this->compiler->compileUpsert($this);
        }

        $result = $this->execute();

        return $result instanceof BaseResult ? $result->affectedRows() : 0;
    }

    /**
     * INSERT OR IGNORE
     */
    public function insertOrIgnore(array $values): int
    {
        return $this->ignore(true)->upsert($values, [], []);
    }

    /**
     * Exécute une requête de mise à jour.
     *
     * @param array|object|string $data    Tableau ou objet de clés et de valeurs, ou chaîne littérale
     * @param bool                $execute Spécifié si nous voulons exécuter directement la requête
     *
     * @return BaseResult|bool|self|string
     */
    public function update(array|object|string $data = [], bool $execute = true)
    {
        $this->crud = 'update';

        if (! is_string($data)) {
            $data = $this->objectToArray($data);
        }

        if (empty($data) && $this->values === []) {
            if (true === $execute) {
                throw new DatabaseException('You must give entries to update.');
            }

            return $this;
        }

        if (! empty($data)) {
            $this->set($data);
        }

         if ($this->testMode) {
            return $this->compiler->compileUpdate($this);
        }

        if ($execute) {
            return $this->execute();
        }

        return $this;
    }

    /**
     * Exécute une requête de remplacement.
     *
     * @param array|object $data    Tableau ou objet de clés et de valeurs à remplacer
     * @param bool         $execute Spécifié si nous voulons exécuter directement la requête
     *
     * @return BaseResult|self|string
     */
    public function replace(array|object $data = [], bool $execute = true)
    {
        $this->crud = 'replace';

        $data = $this->objectToArray($data);

        if (empty($data) && $this->values === []) {
            if (true === $execute) {
                throw new DatabaseException('You must give entries to replace.');
            }

            return $this;
        }

        if (! empty($data)) {
            $this->set($data);
        }

        if ($this->testMode) {
            return $this->compiler->compileReplace($this);
        }

        if ($execute) {
            return $this->execute();
        }

        return $this;
    }

    /**
     * UPDATE OR INSERT
     */
    public function updateOrInsert(array $attributes, array $values = []): bool
    {
        $exists = $this->clone()->where($attributes)->exists();

        if (!$exists) {
            return $this->insert(array_merge($attributes, $values)) !== false;
        }

        return $this->where($attributes)->update($values) !== false;
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
     * @param array $where   Conditions de suppression
     * @param bool  $execute Spécifié si nous voulons exécuter directement la requête
     *
     * @return BaseResult|self|string
     */
    public function delete(?array $where = null, ?int $limit = null, bool $execute = true)
    {
        $this->crud = 'delete';

        if ($where !== null && $where !== []) {
            $this->where($where);
        }

        if ($limit !== null) {
            $this->limit($limit);
        }

        if ($this->testMode) {
            return $this->compiler->compileDelete($this);
        }

        if ($execute) {
            return $this->execute();
        }

        return $this;
    }

    /**
     * Exécute une requête TRUNCATE
     *
     * Si la base de donnee ne supporte pas la commande truncate(),
     * cette fonction va executer "DELETE FROM table"
     *
     * @return bool|string TRUE on success, FALSE on failure, string on testMode
     */
    public function truncate(?string $table = null)
    {
        $this->crud = 'truncate';

        if ($table !== null && $table !== '') {
            $this->table($table);
        }

        if ($this->testMode) {
            return $this->compiler->compileTruncate($this);
        }

        return $this->execute();
    }
    
    /**
     * Exécute la requête construite
     */
    public function execute()
    {
        $result = $this->query($this->toSql(), $this->bindings->getValues());

        $this->reset();

        return $result;
    }

    /**
     * Exécute une requête SQL directe
     *
     * @return BaseResult|bool|Query BaseResult quand la requete est de type "lecture", bool quand la requete est de type "ecriture", Query quand on a une requete preparee
     */
    public function query(string $sql, array $params = [])
    {
        return $this->db->query($sql, $params);
    }

    /**
     * Récupère les résultats de la requete
     */
    public function result(int|string $type = PDO::FETCH_OBJ): array
    {
        return $this->execute()->result($type);
    }

    /**
     * Récupère le premier résultat
     */
    public function first($type = PDO::FETCH_OBJ): mixed
    {
        return $this->limit(1)->execute()->first($type);
    }

    /**
     * Récupère une ligne spécifique
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
        $row = $this->first(PDO::FETCH_OBJ);

        $values = [];

        foreach ((array) $name as $v) {
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
        $rows = $this->all(PDO::FETCH_OBJ);

        $fields = [];

        foreach ($rows as $row) {
            $values = [];

            foreach ((array) $name as $v) {
                if (is_string($v)) {
                    $values[$v] = $row->{$v} ?? null;
                }
            }
            $fields[] = is_string($name) ? ($values[$name] ?? null) : $values;
        }

        return $fields;
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
        return !$this->exists();
    }

    /**
     * Pagination simple
     */
    public function forPage(int $page, int $perPage = 15): self
    {
        return $this->offset(($page - 1) * $perPage)->limit($perPage);
    }

    /**
     * Pagination avec cursor (pour les grandes tables)
     */
    public function forPageBeforeId(int $perPage = 15, ?int $lastId = null, string $column = 'id'): self
    {
        $this->orderBy($column, 'ASC');

        if ($lastId !== null) {
            $this->where($column, '>', $lastId);
        }

        return $this->limit($perPage);
    }

    /**
     * Traitement par lots
     */
    public function chunk(int $count, Closure $callback): bool
    {
        $page = 1;

        do {
            $results = $this->clone()->forPage($page, $count)->all();
            $countResults = count($results);

            if ($countResults == 0) {
                break;
            }

            if ($callback($results, $page) === false) {
                return false;
            }

            $page++;
        } while ($countResults == $count);

        return true;
    }

    /**
     * Traitement par lots basé sur l'ID
     */
    public function chunkById(int $count, Closure $callback, string $column = 'id'): bool
    {
        $lastId = null;

        do {
            $clone = $this->clone()
                ->orderBy($column, 'ASC')
                ->limit($count);

            if ($lastId !== null) {
                $clone->where($column, '>', $lastId);
            }

            $results = $clone->all();

            if (count($results) == 0) {
                break;
            }

            if ($callback($results) === false) {
                return false;
            }

            $last = end($results);
            $lastId = is_object($last) ? $last->{$column} : $last[$column];
        } while (count($results) == $count);

        return true;
    }

    /**
     * Applique une fonction à chaque résultat
     */
    public function each(Closure $callback, int $chunk = 100): bool
    {
        return $this->chunk($chunk, function($results) use ($callback) {
            foreach ($results as $result) {
                if ($callback($result) === false) {
                    return false;
                }
            }
        });
    }

    /**
     * Verrouillage pour mise à jour
     */
    public function lockForUpdate(): self
    {
        $this->lock = match($this->db->getPlatform()) {
            'sqlite' => '', // SQLite ne supporte pas le verrouillage
            default => 'FOR UPDATE'
        };
        
        return $this;
    }

    /**
     * Verrouillage partagé
     */
    public function sharedLock(): self
    {
        $this->lock = match($this->db->getPlatform()) {
            'mysql' => 'LOCK IN SHARE MODE',
            'pgsql' => 'FOR SHARE',
            'sqlite' => '', // SQLite ne supporte pas le verrouillage
            default => 'LOCK IN SHARE MODE'
        };
        
        return $this;
    }

    /**
     * Verrouillage SKIP LOCKED
     */
    public function skipLocked(): self
    {
        $this->lock .= ' SKIP LOCKED';

        return $this;
    }

    /**
     * Verrouillage NOWAIT
     */
    public function lockNowait(): self
    {
        $this->lock .= ' NOWAIT';

        return $this;
    }

    /**
     * Incremente un champ numerique par la valeur specifiee.
     *
     * @throws DatabaseException
     */
    public function increment(string $column, float|int $value = 1): bool
    {
        $expression = new Expression($this->db->escapeIdentifiers($column) . " + {$value}");
        
        return $this->update([$column => $expression], true);
    }

    /**
     * Decremente un champ numerique par la valeur specifiee.
     *
     * @throws DatabaseException
     */
    public function decrement(string $column, float|int $value = 1): bool
    {
        $expression = new Expression($this->db->escapeIdentifiers($column) . " - {$value}");

        return $this->update([$column => $expression], true);
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

        if (!$preserve) {
            $this->reset();
        }

        return $sql;
    }

    /**
     * Récupère le SQL avec les bindings échappés
     */
    public function toRawSql(): string
    {
        $sql = $this->toSql();
        $bindings = $this->bindings->getValues();

        foreach ($bindings as $value) {
            $sql = preg_replace('/\?/', $this->db->quote($value), $sql, 1);
        }

        return $sql;
    }

    /**
     * Affiche le SQL pour debug
     */
    public function dump(): self
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
    public function reset(): self
    {
        $this->from = '';
        $this->tables = [];
        $this->columns = [];
        $this->wheres = [];
        $this->joins = [];
        $this->orders = [];
        $this->groups = [];
        $this->havings = [];
        $this->unions = [];
        $this->values = [];
        $this->bindings = new BindingCollection();
        $this->distinct = false;
        $this->ignore = false;
        $this->limit = null;
        $this->offset = null;
        $this->lock = null;
        $this->uniqueBy = [];
        $this->updateColumns = [];

        return $this->asCrud('select');
    }

    /**
     * Crée une copie du builder
     */
    public function clone(): static
    {
        $clone = clone $this;
        $clone->bindings = clone $this->bindings;
        
        return $clone;
    }

    /**
     * Définit le type d'opération CRUD à effectuer
     * 
     * @internal
     */
    protected function asCrud(string $type): self
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
     * @param self|Closure $builder
     */
    protected function buildSubquery(Closure|BuilderInterface $builder, bool $wrapped = false, string $alias = ''): string
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
            if (in_array(strtoupper($possibleFunction), Utils::SQL_FUNCTIONS, true)) {
                $column .= ' ' . array_shift($parts);
                $operator = implode(' ', $parts);
            }
        }

        // Étape 2: Extraction des alias (améliorée)
        if ($operator !== '' && !Utils::isOperator($operator)) {
            if (Utils::isAlias($operator)) {
                $alias = Utils::extractAlias($operator);
                $operator = '';
            } else {
                // Ce n'est pas un alias, c'est un opérateur ou une clause
                $column = implode(' ', [$column, $operator]);
                $operator = '';
            }
        }

        // Étape 3: Détection des fonctions d'agrégation
        $functionPattern = '/^(' . implode('|', array_map('preg_quote', Utils::SQL_FUNCTIONS)) . ')\s*\(\s*(.+?)\s*\)$/i';
        if (preg_match($functionPattern, $column, $matches)) {
            $aggregate = $matches[1];
            $column = $matches[2];
        }

        // Étape 4: Gestion des alias de table
        if (str_contains($column, '.')) {
            $column = Utils::formatQualifiedColumn($this->db, $column);
        } else {
            $column = $this->db->escapeIdentifiers($column);
        }

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
