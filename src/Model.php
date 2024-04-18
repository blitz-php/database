<?php

/**
 * This file is part of Blitz PHP framework - Database Layer.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Database;

use BadMethodCallException;
use BlitzPHP\Contracts\Database\ConnectionInterface;
use BlitzPHP\Contracts\Database\ConnectionResolverInterface;
use BlitzPHP\Database\Builder\BaseBuilder;
use BlitzPHP\Database\Connection\BaseConnection;
use BlitzPHP\Database\Exceptions\DataException;
use BlitzPHP\Utilities\Date;
use Closure;
use InvalidArgumentException;
use ReflectionClass;
use ReflectionProperty;
use stdClass;

/**
 * La classe Model étend BaseModel et fournit des
 * fonctionnalités pratiques qui rendent le travail avec une table de base de données SQL moins pénible.
 *
 * Ce sera:
 * - se connecte automatiquement à la base de données
 * - autoriser les appels croisés au constructeur
 * - supprime le besoin d'utiliser directement l'objet Result dans la plupart des cas
 *
 * @method array                                                       all(int|string $type = \PDO::FETCH_OBJ, ?string $key = null, int $expire = 0)
 * @method float                                                       avg(string $field, ?string $key = null, int $expire = 0)
 * @method $this                                                       between(string $field, $value1, $value2)
 * @method array                                                       bulckInsert(array $data, ?string $table = null)
 * @method int                                                         count(string $field = '*', ?string $key = null, int $expire = 0)
 * @method ConnectionInterface                                         db()
 * @method $this|\BlitzPHP\Database\BaseResult|string                  delete(?array $where = null, ?int $limit = null, bool $execute = true)
 * @method $this                                                       distinct()
 * @method \BlitzPHP\Database\BaseResult|\BlitzPHP\Database\Query|bool execute(?string $key = null, int $expire = 0)
 * @method array                                                       findAll(array|string $fields = '*', array $options = [], int|string $type = \PDO::FETCH_OBJ)
 * @method mixed                                                       findOne(array|string $fields = '*', array $options = [], int|string $type = \PDO::FETCH_OBJ)
 * @method mixed                                                       first(int|string $type = \PDO::FETCH_OBJ, ?string $key = null, int $expire = 0)
 * @method $this                                                       from(string|string[]|null $from, bool $overwrite = false)
 * @method $this                                                       fromSubquery(self $builder, string $alias = '')
 * @method $this                                                       fullJoin(string $table, array|string $fields)
 * @method string                                                      getTable()
 * @method $this                                                       group(string|string[] $field, ?bool $escape = null)
 * @method $this                                                       groupBy(string|string[] $field, ?bool $escape = null)
 * @method $this                                                       having(array|string $field, $values = null, ?bool $escape = null)
 * @method $this                                                       havingIn(string $field, array|callable|self $param)
 * @method $this                                                       havingLike(array|string $field, $match = '', string $side = 'both', bool $escape = true, bool $insensitiveSearch = false)
 * @method $this                                                       havingNotIn(string $field, array|callable|self $param)
 * @method $this                                                       havingNotLike(array|string $field, $match = '', string $side = 'both', bool $escape = true, bool $insensitiveSearch = false)
 * @method $this                                                       in(string $key, array|callable|self $param)
 * @method $this                                                       innerJoin(string $table, array|string $fields)
 * @method $this|\BlitzPHP\Database\BaseResult|string                  insert(array $data, bool $execute = true)
 * @method $this                                                       into(string $table)
 * @method $this                                                       join(string $table, array|string $fields, string $type = 'INNER')
 * @method $this                                                       leftJoin(string $table, array|string $fields, bool $outer = false)
 * @method $this                                                       like(array|string $field, $match = '', string $side = 'both', ?bool $escape = null, bool $insensitiveSearch = false)
 * @method $this                                                       limit(int $limit, ?int $offset = null)
 * @method float                                                       max(string $field, ?string $key = null, int $expire = 0)
 * @method float                                                       min(string $field, ?string $key = null, int $expire = 0)
 * @method $this                                                       naturalJoin(array|string $table)
 * @method $this                                                       notBetween(string $field, $value1, $value2)
 * @method $this                                                       notHavingLike($field, string $match = '', string $side = 'both', ?bool $escape = null, bool $insensitiveSearch = false)
 * @method $this                                                       notIn(string $key, array|callable|self $param)
 * @method $this                                                       notLike(array|string $field, $match = '', string $side = 'both', ?bool $escape = null, bool $insensitiveSearch = false)
 * @method $this                                                       notWhere(array|string $key, $value = null, ?bool $escape = null)
 * @method $this                                                       offset(int $offset, ?int $limit = null)
 * @method mixed                                                       one(int|string $type = \PDO::FETCH_OBJ, ?string $key = null, int $expire = 0)
 * @method $this                                                       orBetween(string $field, $value1, $value2)
 * @method $this                                                       order(string|string[] $field, string $direction = 'ASC', ?bool $escape = null)
 * @method $this                                                       orderBy(string|string[] $field, string $direction = 'ASC', ?bool $escape = null)
 * @method $this                                                       orHaving(array|string $field, $values = null, ?bool $escape = null)
 * @method $this                                                       orHavingIn(string $field, array|callable|self $param)
 * @method $this orHavingLike(array|string $field, $match = '', string $side = 'both', bool $escape = true, bool $insensitiveSearch = false): self
 * @method $this orHavingNotIn(string $field, array|callable|self $param)
 * @method $this orHavingNotLike(array|string $field, $match = '', string $side = 'both', bool $escape = true, bool $insensitiveSearch = false): self
 * @method $this                                                       orIn(string $key, array|callable|self $param)
 * @method $this                                                       orLike(array|string $field, string $match = '', string $side = 'both', ?bool $escape = null, bool $insensitiveSearch = false)
 * @method $this                                                       orNotBetween(string $field, $value1, $value2)
 * @method $this                                                       orNotHavingLike($field, string $match = '', string $side = 'both', ?bool $escape = null, bool $insensitiveSearch = false)
 * @method $this                                                       orNotIn(string $key, array|callable|self $param)
 * @method $this                                                       orNotLike(array|string $field, string $match = '', string $side = 'both', ?bool $escape = null, bool $insensitiveSearch = false)
 * @method $this                                                       orNotWhere(array|string $key, $value = null, ?bool $escape = null)
 * @method $this                                                       orWhere(array|string $key, $value = null, ?bool $escape = null)
 * @method $this                                                       orWhereBetween(string $field, $value1, $value2)
 * @method $this                                                       orWhereIn(string $key, array|callable|self $param)
 * @method $this                                                       orWhereLike(array|string $field, string $match = '', string $side = 'both', ?bool $escape = null, bool $insensitiveSearch = false)
 * @method $this                                                       orWhereNotBetween(string $field, $value1, $value2)
 * @method $this                                                       orWhereNotIn(string $key, array|callable|self $param)
 * @method $this                                                       orWhereNotLike(array|string $field, string $match = '', string $side = 'both', ?bool $escape = null, bool $insensitiveSearch = false)
 * @method $this                                                       orWhereNotNull(string|string[] $field)
 * @method $this                                                       orWhereNull(string|string[] $field)
 * @method $this                                                       params(array $params)
 * @method \BlitzPHP\Database\BaseResult|\BlitzPHP\Database\Query|bool query(string $sql, array $params = [])
 * @method $this                                                       rand(?int $digit = null)
 * @method array                                                       result(int|string $type = \PDO::FETCH_OBJ, ?string $key = null, int $expire = 0)
 * @method $this                                                       rightJoin(string $table, array|string $fields, bool $outer = false)
 * @method mixed                                                       row(int $index, int|string $type = \PDO::FETCH_OBJ, ?string $key = null, int $expire = 0)
 * @method $this                                                       select(array|string $fields = '*', ?int $limit = null, ?int $offset = null)
 * @method $this                                                       set(array|object|string $key, mixed $value = '', ?bool $escape = null)
 * @method $this                                                       sortAsc(string|string[] $field, ?bool $escape = null)
 * @method $this                                                       sortDesc(string|string[] $field, ?bool $escape = null)
 * @method $this                                                       sortRand(?int $digit = null)
 * @method string                                                      sql()
 * @method float                                                       sum(string $field, ?string $key = null, int $expire = 0)
 * @method $this                                                       table(string|string[]|null $table)
 * @method self                                                        testMode(bool $mode = true)
 * @method $this                                                       unless($value = null, ?callable $callback = null, ?callable $default = null)
 * @method $this|\BlitzPHP\Database\BaseResult                         update(array|string $data, bool $execute = true)
 * @method mixed|mixed[]                                               value(string|string[] $name, ?string $key = null, int $expire = 0)
 * @method mixed[]                                                     values(string|string[] $name, ?string $key = null, int $expire = 0)
 * @method $this                                                       when($value = null, ?callable $callback = null, ?callable $default = null)
 * @method $this                                                       where(array|string $key, $value = null, ?bool $escape = null)
 * @method $this                                                       whereBetween(string $field, $value1, $value2)
 * @method $this                                                       whereIn(string $key, array|callable|self $param)
 * @method $this                                                       whereLike(array|string $field, $match = '', string $side = 'both', ?bool $escape = null, bool $insensitiveSearch = false)
 * @method $this                                                       whereNotBetween(string $field, $value1, $value2)
 * @method $this                                                       whereNotIn(string $key, array|callable|self $param)
 * @method $this                                                       whereNotLike(array|string $field, $match = '', string $side = 'both', ?bool $escape = null, bool $insensitiveSearch = false)
 * @method $this                                                       whereNotNull(string|string[] $field)
 * @method $this                                                       whereNull(string|string[] $field)
 */
abstract class Model
{
    /**
     * Nom de la table
     *
     * @var string
     */
    protected $table;

    /**
     * Cle primaire.
     */
    protected string $primaryKey = 'id';

    /**
     * Le format dans lequel les résultats doivent être renvoyés.
     * Ce format sera surchargé si les méthodes as* sont utilisées.
     */
    protected string $returnType = 'array';

    /**
     * Utilié pour fournir une surchage temporaire pour le format de retour des resultats.
     *
     * @var string
     */
    protected $tempReturnType;

    /**
     * Primary Key value when inserting and useAutoIncrement is false.
     *
     * @var int|string|null
     */
    private $primaryKeyValue;

    /**
     * Groupe de la base de données a utiliser
     *
     * @var string
     */
    protected $group;

    /**
     * Doit-on utiliser l'auto increment.
     */
    protected bool $useAutoIncrement = true;

    /**
     * Le type de colonne que created_at et updated_at sont censés avoir.
     *
     * Autorisé: 'datetime', 'date', 'int'
     */
    protected string $dateFormat = 'datetime';

    /**
     * Si ce modèle doit utiliser "softDeletes" et définir simplement une date à laquelle les lignes sont supprimées,
     * ou effectuer des suppressions réelles.
     */
    protected bool $useSoftDeletes = false;

    /**
     * Un tableau de noms de champs qui peuvent être définis par l'utilisateur dans les insertions/mises à jour.
     *
     * @var string[]
     */
    protected array $allowedFields = [];

    /**
     * Si c'est vrai, définira les valeurs Created_at et Updated_at pendant les routines d'insertion et de mise à jour.
     */
    protected bool $useTimestamps = false;

    /**
     * La colonne utilisée pour insérer les horodatages
     */
    protected string $createdField = 'created_at';

    /**
     * La colonne utilisée pour modifier les horodatages
     */
    protected string $updatedField = 'updated_at';

    /**
     * Utilisé par withDeleted pour remplacer le paramètre softDelete du modèle.
     *
     * @var bool
     */
    protected $tempUseSoftDeletes;

    /**
     * La colonne utilisée pour enregistrer l'état de suppression réversible.
     */
    protected string $deletedField = 'deleted_at';

    /**
     * Le nombre de données à renvoyer pour la pagination.
     */
    protected int $perPage = 15;

    /**
     * Connexion à la base de données
     *
     * @var BaseConnection
     */
    protected $db;

    /**
     * Query Builder
     *
     * @var BaseBuilder|null
     */
    protected $builder;

    /**
     * Contient les informations transmises via 'set' afin que nous puissions les capturer (pas le constructeur) et nous assurer qu'elles sont validées en premier.
     *
     * @var array
     */
    protected $tempData = [];

    /**
     * Tableau d'échappement qui mappe l'utilisation de l'indicateur d'échappement pour chaque paramètre.
     *
     * @var array
     */
    protected $escape = [];

    /**
     * Methodes du builder qui ne doivent pas etre utilisees dans le model.
     *
     * @var string[] method name
     */
    private array $builderMethodsNotAvailable = [
        'getCompiledInsert',
        'getCompiledSelect',
        'getCompiledUpdate',
    ];

    /**
     * Callbacks.
     *
     * Chaque tableau doit contenir les noms de méthodes (au sein du modèle) qui doivent
     * être appelées lorsque ces événements sont déclenchés.
     *
     * Les méthodes « Update » et « Delete » reçoivent les mêmes éléments que ceux attribués
     * à leur méthode respective.
     *
     * Les méthodes "Find" reçoivent l'ID recherché (s'il est présent),
     * et "afterFind" reçoit en outre les résultats trouvés.
     */

    /**
     * S'il faut déclencher les callbacks définis
     */
    protected bool $allowCallbacks = true;

    /**
     * Utilisé par AllowCallbacks() pour remplacer le paramètre allowCallbacks du modèle.
     *
     * @var bool
     */
    protected $tempAllowCallbacks;

    /**
     * Callbacks pour beforeInsert
     *
     * @var string[]
     */
    protected array $beforeInsert = [];

    /**
     * Callbacks pour afterInsert
     *
     * @var string[]
     */
    protected array $afterInsert = [];

    /**
     * Callbacks pour beforeUpdate
     *
     * @var string[]
     */
    protected array $beforeUpdate = [];

    /**
     * Callbacks pour afterUpdate
     *
     * @var string[]
     */
    protected array $afterUpdate = [];

    /**
     * Callbacks pour beforeInsertBatch
     *
     * @var string[]
     */
    protected array $beforeInsertBatch = [];

    /**
     * Callbacks pour afterInsertBatch
     *
     * @var string[]
     */
    protected array $afterInsertBatch = [];

    /**
     * Callbacks pour beforeUpdateBatch
     *
     * @var string[]
     */
    protected array $beforeUpdateBatch = [];

    /**
     * Callbacks pour afterUpdateBatch
     *
     * @var string[]
     */
    protected array $afterUpdateBatch = [];

    /**
     * Callbacks pour beforeFind
     *
     * @var string[]
     */
    protected array $beforeFind = [];

    /**
     * Callbacks pour afterFind
     *
     * @var string[]
     */
    protected array $afterFind = [];

    /**
     * Callbacks pour beforeDelete
     *
     * @var string[]
     */
    protected array $beforeDelete = [];

    /**
     * Callbacks pour afterDelete
     *
     * @var string[]
     */
    protected array $afterDelete = [];

    public function __construct(protected ConnectionResolverInterface $resolver, ?ConnectionInterface $db = null)
    {
        $this->db = $db ?: $this->resolver->connection($this->group);

        $this->tempReturnType     = $this->returnType;
        $this->tempUseSoftDeletes = $this->useSoftDeletes;
        $this->tempAllowCallbacks = $this->allowCallbacks;
    }

    /**
     * Fourni une instance partagee du Query Builder.
     *
     * @throws ModelException
     */
    public function builder(?string $table = null): BaseBuilder
    {
        if ($this->builder instanceof BaseBuilder) {
            // S'assurer que la table utilisee differe de celle du builder
            $builderTable = $this->builder->getTable();
            if ($table && $builderTable !== $this->db->prefixTable($table)) {
                return $this->db->table($table);
            }

            if (empty($builderTable) && ! empty($this->table)) {
                $this->builder = $this->builder->table($this->table);
            }

            return $this->builder;
        }

        // S'assurer qu'on a une bonne connxion a la base de donnees
        if (! $this->db instanceof ConnectionInterface) {
            $this->db = $this->resolver->connection($this->group);
        }

        $table = empty($table) ? $this->table : $table;

        if (empty($table)) {
            $builder = $this->db->table('.')->from([], true);
        } else {
            $builder = $this->db->table($table);
        }

        // Considerer que c'est partagee seulement si la table est correct
        if ($table === $this->table) {
            $this->builder = $builder;
        }

        return $builder;
    }

    /**
     * Insere les données dans la base de données.
     * Si un objet est fourni, il tentera de le convertir en un tableau.
     *
     * @param bool $returnID Si l'ID de l'element inséré doit être retourné ou non.
     *
     * @return bool|int|null
     *
     * @throws ReflectionException
     */
    public function create(null|array|object $data = null, bool $returnID = true)
    {
        if (! empty($this->tempData['data'])) {
            if (empty($data)) {
                $data = $this->tempData['data'];
            } else {
                $data = $this->transformDataToArray($data, 'insert');
                $data = array_merge($this->tempData['data'], $data);
            }
        }

        if ($this->useAutoIncrement === false) {
            if (is_array($data) && isset($data[$this->primaryKey])) {
                $this->primaryKeyValue = $data[$this->primaryKey];
            } elseif (is_object($data) && isset($data->{$this->primaryKey})) {
                $this->primaryKeyValue = $data->{$this->primaryKey};
            }
        }

        $this->escape   = $this->tempData['escape'] ?? [];
        $this->tempData = [];

        $builder = $this->builder();

        if ($returnID && true === $inserted = $builder->insert($data)) {
            return $this->db->lastId($builder->getTable());
        }

        return $inserted;
    }

    /**
     * Met à jour un seul enregistrement dans la base de données.
     * Si un objet est fourni, il tentera de le convertir en tableau.
     *
     * @param array|int|string|null $id
     * @param array|object|null     $data
     *
     * @throws ReflectionException
     */
    public function modify($id = null, $data = null): bool
    {
        $id = $id ?: $this->primaryKeyValue;

        if (! empty($this->tempData['data'])) {
            if (empty($data)) {
                $data = $this->tempData['data'];
            } else {
                $data = $this->transformDataToArray($data, 'update');
                $data = array_merge($this->tempData['data'], $data);
            }

            $id = $id ?: $this->idValue($data);
        }

        $this->escape   = $this->tempData['escape'] ?? [];
        $this->tempData = [];

        return $this->builder()->whereIn($this->primaryKey, (array) $id)->update($data);
    }

    /**
     * Une méthode pratique qui tentera de déterminer si les données doivent être insérées ou mises à jour.
     * Fonctionnera avec un tableau ou un objet.
     * Lors de l'utilisation avec des objets de classe personnalisés, vous devez vous assurer que la classe fournira l'accès aux variables de classe, même via une méthode magique.
     */
    public function save(array|object $data): bool
    {
        if (empty($data)) {
            return true;
        }

        if ($this->shouldUpdate($data)) {
            $response = $this->modify($this->idValue($data), $data);
        } else {
            $response = $this->create($data, false);

            if ($response !== false) {
                $response = true;
            }
        }

        return $response;
    }

    /**
     * Boucle sur les enregistrements par lots, ce qui vous permet d'opérer sur eux.
     * Fonctionne avec $this->builder pour obtenir la sélection compilée afin de déterminer les lignes sur lesquelles opérer.
     * Cette méthode ne fonctionne qu'avec les dbCalls.
     *
     * @throws DataException
     */
    public function chunk(int $size, Closure $userFunc)
    {
        $total  = $this->builder()->countAllResults();
        $offset = 0;

        while ($offset <= $total) {
            $builder = clone $this->builder();
            $rows    = $builder->limit($size, $offset)->all($this->returnType);

            if (! $rows) {
                throw DataException::emptyDataset('chunk');
            }

            $offset += $size;

            if (empty($rows)) {
                continue;
            }

            foreach ($rows as $row) {
                if ($userFunc($row) === false) {
                    return;
                }
            }
        }
    }

    /**
     * Remplacez countAllResults pour tenir compte des lignes supprimés de manière logique (softdeletes).
     *
     * @return int|string
     */
    public function countAllResults(bool $reset = true, bool $test = false)
    {
        if ($this->tempUseSoftDeletes) {
            $this->builder()->whereNull($this->table . '.' . $this->deletedField);
        }

        // Lorsque $reset === false, $tempUseSoftDeletes dépendra de la valeur $useSoftDeletes
        // car nous ne voulons pas ajouter la même condition "where" pour la deuxième fois.
        $this->tempUseSoftDeletes = $reset
            ? $this->useSoftDeletes
            : ($this->useSoftDeletes ? false : $this->useSoftDeletes);

        return $this->builder()->testMode($test)->countAllResults($reset);
    }

    public function paginate(?int $limit = null, ?int $page = null, ?int $total = null): array
    {
        $page   = max((int) $page, 1);
        $total  = $total ?: $this->countAllResults(false);
        $limit  = $limit ?: $this->perPage;
        $offset = ($page - 1) * $limit;

        return $this->findAll($limit, $offset);
    }

    /**
     * Récupère tous les résultats, tout en les limitant éventuellement.
     */
    public function findAll(int $limit = 0, int $offset = 0): array
    {
        if ($this->tempAllowCallbacks) {
            // Call the before event and check for a return
            $eventData = $this->trigger('beforeFind', [
                'method'    => 'findAll',
                'limit'     => $limit,
                'offset'    => $offset,
                'singleton' => false,
            ]);

            if (isset($eventData['returnData']) && $eventData['returnData'] === true) {
                return $eventData['data'];
            }
        }

        $eventData = [
            'data'      => $this->builder()->findAll('*', compact('limit', 'offset'), $this->returnType),
            'limit'     => $limit,
            'offset'    => $offset,
            'method'    => 'findAll',
            'singleton' => false,
        ];

        if ($this->tempAllowCallbacks) {
            $eventData = $this->trigger('afterFind', $eventData);
        }

        $this->tempReturnType     = $this->returnType;
        $this->tempUseSoftDeletes = $this->useSoftDeletes;
        $this->tempAllowCallbacks = $this->allowCallbacks;

        return $eventData['data'];
    }

    /**
     * Fournit/instancie la connexion builder/db et les noms de table/clé primaire du modèle et le type de retour.
     *
     * @return mixed
     */
    public function __get(string $name)
    {
        if (property_exists($this, $name)) {
            return $this->{$name};
        }

        if (isset($this->db->{$name})) {
            return $this->db->{$name};
        }

        if (isset($this->builder()->{$name})) {
            return $this->builder()->{$name};
        }

        return null;
    }

    /**
     * Verifie si une propriete existe dans le modele, le builder, et la db connection.
     */
    public function __isset(string $name): bool
    {
        if (property_exists($this, $name)) {
            return true;
        }

        if (isset($this->db->{$name})) {
            return true;
        }

        return isset($this->builder()->{$name});
    }

    /**
     * Fourni un acces direct a une methode du builder (si disponible)
     * et la database connection.
     *
     * @return mixed
     */
    public function __call(string $name, array $params)
    {
        $builder = $this->builder();
        $result  = null;

        if (method_exists($this->db, $name)) {
            $result = $this->db->{$name}(...$params);
        } elseif (method_exists($builder, $name)) {
            $this->checkBuilderMethod($name);

            $result = $builder->{$name}(...$params);
        } else {
            throw new BadMethodCallException('Call to undefined method ' . static::class . '::' . $name);
        }

        if ($result instanceof BaseBuilder) {
            return $this;
        }

        return $result;
    }

    /**
     * Renvoie la valeur id pour le tableau de données ou l'objet.
     *
     * @return array|int|string|null
     */
    protected function idValue(array|object $data)
    {
        if (is_object($data) && isset($data->{$this->primaryKey})) {
            return $data->{$this->primaryKey};
        }

        if (is_array($data) && ! empty($data[$this->primaryKey])) {
            return $data[$this->primaryKey];
        }

        return null;
    }

    /**
     * Cette méthode est appelée lors de la sauvegarde pour déterminer si l'entrée doit être mise à jour.
     * Si cette méthode renvoie une opération d'insertion fausse, elle sera exécutée
     */
    protected function shouldUpdate(array|object $data): bool
    {
        return ! empty($this->idValue($data));
    }

    /**
     * Prend une classe et retourne un tableau de ses propriétés publiques et protégées sous la forme d'un tableau adapté à une utilisation dans les créations et les mises à jour.
     * Cette méthode utilise objectToRawArray() en interne et effectue la conversion en chaîne sur toutes les instances Time
     *
     * @param bool $onlyChanged Propriété modifiée uniquement
     * @param bool $recursive   Si vrai, les entités internes seront également converties en tableau
     *
     * @throws ReflectionException
     */
    protected function objectToArray(object|string $data, bool $onlyChanged = true, bool $recursive = false): array
    {
        $properties = $this->objectToRawArray($data, $onlyChanged, $recursive);

        // Convertissez toutes les instances de Date en $dateFormat approprié
        if ($properties) {
            $properties = array_map(function ($value) {
                if ($value instanceof Date) {
                    return $this->timeToDate($value);
                }

                return $value;
            }, $properties);
        }

        return $properties;
    }

    /**
     * Prend une classe et renvoie un tableau de ses propriétés publiques et protégées sous la forme d'un tableau avec des valeurs brutes.
     *
     * @param bool $onlyChanged Propriété modifiée uniquement
     * @param bool $recursive   Si vrai, les entités internes seront également converties en tableau
     *
     * @throws ReflectionException
     */
    protected function objectToRawArray(object|string $data, bool $onlyChanged = true, bool $recursive = false): ?array
    {
        if (method_exists($data, 'toRawArray')) {
            $properties = $data->toRawArray($onlyChanged, $recursive);
        } elseif (method_exists($data, 'toArray')) {
            $properties = $data->toArray();
        } else {
            $mirror = new ReflectionClass($data);
            $props  = $mirror->getProperties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED);

            $properties = [];

            // Boucle sur chaque propriété, en enregistrant le nom/valeur dans un nouveau tableau
            // que nous pouvons retourner.
            foreach ($props as $prop) {
                // Doit rendre les valeurs protégées accessibles.
                $prop->setAccessible(true);
                $properties[$prop->getName()] = $prop->getValue($data);
            }
        }

        return $properties;
    }

    /**
     * Convertit la valeur Date en chaîne en utilisant $this->dateFormat.
     *
     * Les formats disponibles sont :
     * - 'int' - Stocke la date sous la forme d'un horodatage entier
     * - 'datetime' - Stocke les données au format datetime SQL
     * - 'date' - Stocke la date (uniquement) au format de date SQL.
     *
     * @return int|string
     */
    protected function timeToDate(Date $value)
    {
        switch ($this->dateFormat) {
            case 'datetime':
                return $value->format('Y-m-d H:i:s');

            case 'date':
                return $value->format('Y-m-d');

            case 'int':
                return $value->getTimestamp();

            default:
                return (string) $value;
        }
    }

    /**
     * Transformer les données en tableau.
     *
     * @param string $type Type de donnees (insert|update)
     *
     * @throws DataException
     * @throws InvalidArgumentException
     * @throws ReflectionException
     */
    protected function transformDataToArray(null|array|object $data, string $type): array
    {
        if (! in_array($type, ['insert', 'update'], true)) {
            throw new InvalidArgumentException(sprintf('Invalid type "%s" used upon transforming data to array.', $type));
        }

        if (empty($data)) {
            throw DataException::emptyDataset($type);
        }

        // Si $data utilise une classe personnalisée avec des propriétés publiques ou protégées représentant
        // les éléments de la collection, nous devons les saisir sous forme de tableau.
        if (is_object($data) && ! $data instanceof stdClass) {
            $data = $this->objectToArray($data, $type === 'update', true);
        }

        // S'il s'agit toujours d'une stdClass, continuez et convertissez en un tableau afin que
        // les autres méthodes de modèle n'aient pas à effectuer de vérifications spéciales.
        if (is_object($data)) {
            $data = (array) $data;
        }

        // S'il est toujours vide ici, cela signifie que $data n'a pas changé ou est un objet vide
        if (! $this->allowEmptyInserts && empty($data)) {
            throw DataException::emptyDataset($type);
        }

        return $data;
    }

    /**
     * Définit la valeur $tempAllowCallbacks afin que nous puissions temporairement remplacer le paramètre.
     * Se réinitialise après la prochaine méthode utilisant des déclencheurs.
     */
    public function allowCallbacks(bool $val = true): self
    {
        $this->tempAllowCallbacks = $val;

        return $this;
    }

    /**
     * Un simple déclencheur d'événement pour les événements de modèle qui permet une manipulation supplémentaire des données au sein du modèle.
     * Spécifiquement destiné à être utilisé par les modèles enfants, il peut être utilisé pour formater des données, enregistrer/charger des classes associées, etc.
     *
     * Il est de la responsabilité des méthodes de rappel de renvoyer les données elles-mêmes.
     *
     * Chaque tableau $eventData DOIT avoir une clé 'data' avec les données pertinentes pour les méthodes de rappel (comme un tableau de paires clé/valeur à insérer ou à mettre à jour, un tableau de résultats, etc.)
     *
     * Si les rappels ne sont pas autorisés, renvoie immédiatement $eventData.
     *
     * @throws DataException
     */
    protected function trigger(string $event, array $eventData): array
    {
        // S'assurer que c'est un evenement valide
        if (! isset($this->{$event}) || $this->{$event} === []) {
            return $eventData;
        }

        foreach ($this->{$event} as $callback) {
            if (! method_exists($this, $callback)) {
                throw DataException::invalidMethodTriggered($callback);
            }

            $eventData = $this->{$callback}($eventData);
        }

        return $eventData;
    }

    /**
     * Verifie si la methode du builder peut etre utilisee dans le modele.
     */
    private function checkBuilderMethod(string $name): void
    {
        if (in_array($name, $this->builderMethodsNotAvailable, true)) {
            //   throw ModelException::forMethodNotAvailable(static::class, $name . '()');
        }
    }
}
