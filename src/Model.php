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
use BlitzPHP\Contracts\Database\RepositoryInterface;
use BlitzPHP\Database\Builder\BaseBuilder;
use BlitzPHP\Database\Connection\BaseConnection;
use BlitzPHP\Database\Exceptions\DatabaseException;
use BlitzPHP\Utilities\DateTime\Date;
use BlitzPHP\Utilities\Iterable\Collection;
use BlitzPHP\Validation\ErrorBag;
use BlitzPHP\Validation\Validator;
use Closure;
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
 * @mixin BaseBuilder
 */
abstract class Model implements RepositoryInterface
{
    /**
     * Nom de la table
     */
    protected string $table = '';
    
    /**
     * Clé primaire
     */
    protected string $primaryKey = 'id';
    
    /**
     * Type de retour par défaut
     */
    protected string $returnType = 'array';
    
    /**
     * Type de retour temporaire
     */
    protected string $tempReturnType;
    
    /**
     * Dernier ID inséré
     */
    protected int|string $lastInsertId = 0;
    
    /**
     * Groupe de connexion
     */
    protected ?string $group = null;
    
    /**
     * Utiliser l'auto-incrément
     */
    protected bool $useAutoIncrement = true;
    
    /**
     * Format des dates (Autorisé: 'datetime', 'date', 'int')
     */
    protected string $dateFormat = 'datetime';
    
    /**
     * Utiliser les soft deletes
     */
    protected bool $useSoftDeletes = false;
    
    /**
     * Champ de suppression logique
     */
    protected string $deletedField = 'deleted_at';
    
    /**
     * Temporaire pour soft deletes
     */
    protected bool $tempUseSoftDeletes;
    
    /**
     * Utiliser les timestamps
     */
    protected bool $useTimestamps = false;
    
    /**
     * Champ de création
     */
    protected string $createdField = 'created_at';
    
    /**
     * Champ de mise à jour
     */
    protected string $updatedField = 'updated_at';
    
    /**
     * Champs autorisés pour l'assignation de masse
     * 
     * @var list<string>
     */
    protected array $fillable = [];
    
    /**
     * Champs protégés (non assignables)
     * 
     * @var list<string>
     */
    protected array $guarded = ['id'];
    
    /**
     * Règles de validation
     * 
     * @var array<string, string>
     */
    protected array $rules = [];
    
    /**
     * Messages de validation personnalisés
     * 
     * @var array<string, string>
     */
    protected array $messages = [];

    /**
     * Le nombre de données à renvoyer pour la pagination.
     */
    protected int $perPage = 15;
    
    /**
     * Connexion à la base de données
     */
    protected BaseConnection $db;
    
    /**
     * Query Builders par table
     * 
     * @var array<string, BaseBuilder>
     */
    protected array $builders = [];
    
    /**
     * Builder actuel
     */
    protected ?BaseBuilder $currentBuilder = null;
    
    /**
     * Alias de la table actuellement utilisée
     */
    protected ?string $currentAlias = null;

    /**
     * Erreurs de validation
     */
    protected ?ErrorBag $errors = null;
    
    /**
     * Activer les callbacks
     */
    protected bool $allowCallbacks = true;
    
    /**
     * Temporaire pour callbacks
     */
    protected bool $tempAllowCallbacks;
    
    /**
     * Callbacks disponibles
     */
    protected array $events = [
        'beforeInsert',
        'afterInsert',
        'beforeUpdate',
        'afterUpdate',
        'beforeDelete',
        'afterDelete',
        'beforeFind',
        'afterFind',
        'beforeBulkInsert',
        'afterBulkInsert',
        'beforeBulkUpdate',
        'afterBulkUpdate',
    ];
    
    /**
     * Callbacks enregistrés
     */
    protected array $callbacks = [];
    
    public function __construct(protected ConnectionResolverInterface $resolver, ?ConnectionInterface $db = null)
    {
        $this->db = $db ?: $this->resolver->connection($this->group);
        
        $this->tempReturnType     = $this->returnType;
        $this->tempUseSoftDeletes = $this->useSoftDeletes;
        $this->tempAllowCallbacks = $this->allowCallbacks;
        
        $this->initializeCallbacks();
    }
    
    /**
     * Initialise les callbacks
     */
    protected function initializeCallbacks(): void
    {
        foreach ($this->events as $event) {
            if (isset($this->{$event}) && is_array($this->{$event})) {
                $this->callbacks[$event] = $this->{$event};
            }
        }
    }
    
    /**
     * Sélectionne une table spécifique pour les prochaines opérations
     */
    public function table(string $table): static
    {
        // Extraire l'alias si présent (ex: "factureachat As fa")
        $alias = null;
        if (preg_match('/^(.+?)(?:\s+as\s+|\s+)(\w+)$/i', $table, $matches)) {
            $table = $matches[1];
            $alias = $matches[2];
        }
        
        $key = $alias ?: $table;
        
        if (!isset($this->builders[$key])) {
            $this->builders[$key] = $this->db->table($table);
        }
        
        $this->currentBuilder = $this->builders[$key]->reset();
        $this->currentAlias   = $key;
        
        return $this;
    }
    
    /**
     * {@inheritdoc}
     */
    public function query(): BaseBuilder
    {
        return $this->builder();
    }
    
    /**
     * Récupère le Query Builder pour une table spécifique ou la table par défaut
     */
    public function builder(?string $table = null): BaseBuilder
    {
        if ($table === null) {
            // Si aucun builder actif, utiliser la table par défaut
            if ($this->currentBuilder === null) {
                $this->table($this->table);
            }

            return $this->currentBuilder;
        }
        
        // Extraire l'alias si présent
        $alias = null;
        if (preg_match('/^(.+?)(?:\s+as\s+|\s+)(\w+)$/i', $table, $matches)) {
            $table = $matches[1];
            $alias = $matches[2];
        }
        
        $key = $alias ?: $table;
        
        if (!isset($this->builders[$key])) {
            $this->builders[$key] = $this->db->table($table);
        }
        
        return $this->builders[$key];
    }
    
    /**
     * {@inheritdoc}
     *
     * @param array|int|string|null $id Une clé primaire ou un tableau de clés primaires
     *
     * @return ($id is int|string ? array|object|null : Collection<int, array|object>) 
     */
    public function find($id = null): mixed
    {
        $singleton = is_numeric($id) || is_string($id);
        
        $eventData = $this->fire('beforeFind', [
            'id'        => $id,
            'method'    => 'find',
            'singleton' => $singleton,
        ]);
        
        if ($eventData['cancelled'] ?? false) {
            return $eventData['data'] ?? null;
        }
        
        $builder = $this->query();
        
        $this->applySoftDeleteCondition($builder);
        
        if ($id !== null && $id !== 0 && $id !== '0') {
            $builder->whereIn($this->primaryKey, (array) $id)
                    ->when($singleton, fn($b) => $b->limit(1));
        }
        
        $results = $builder->collect($this->tempReturnType);
        $data = $singleton ? $results->first() : $results;
        
        $eventData = $this->fire('afterFind', [
            'id'        => $id,
            'data'      => $data,
            'method'    => 'find',
            'singleton' => $singleton,
        ]);
        
        $this->resetTemporaryStates();
        
        return $eventData['data'] ?? $data;
    }
    
    /**
     * {@inheritdoc}
     */
    public function findAll(?int $limit = null, int $offset = 0): Collection
    {
        $eventData = $this->fire('beforeFind', [
            'method'    => 'findAll',
            'limit'     => $limit,
            'offset'    => $offset,
            'singleton' => false,
        ]);
        
        if ($eventData['cancelled'] ?? false) {
            return new Collection($eventData['data'] ?? []);
        }
        
        $builder = $this->query();
        
        $this->applySoftDeleteCondition($builder);
        
        if ($limit !== null) {
            $builder->limit($limit, $offset);
        }
        
        $results = $builder->collect($this->tempReturnType);
        
        $eventData = $this->fire('afterFind', [
            'data'      => $results,
            'limit'     => $limit,
            'offset'    => $offset,
            'method'    => 'findAll',
            'singleton' => false,
        ]);
        
        $this->resetTemporaryStates();
        
        return $eventData['data'] ?? $results;
    }
    
    /**
     * {@inheritdoc}
     */
    public function create(array|object $data, bool $returnId = true)
    {
        $this->lastInsertId = 0;
        
        // Filtrer les données selon fillable/guarded
        $data = $this->filterFillable($data);
        
        // Valider les données
        if (! $this->validate($data, 'create')) {
            return false;
        }
        
        // Ajouter les timestamps
        $data = $this->addTimestamps($data, 'create');
        
        $eventData = $this->fire('beforeInsert', ['data' => $data]);
        
        if ($eventData['cancelled'] ?? false) {
            return false;
        }
        
        $data = $eventData['data'] ?? $data;
        
        $builder = $this->query();
        
        if ($returnId) {
            $result             = $builder->insertGetId($data);
            $this->lastInsertId = is_numeric($result) ? (int) $result : 0;
        } else {
            $result = $builder->insert($data);
        }
        
        $this->fire('afterInsert', [
            'id' => $this->lastInsertId,
            'data' => $data,
            'result' => $result,
        ]);
        
        $this->resetTemporaryStates();
        
        if (!$result) {
            return false;
        }
        
        return $returnId ? $this->lastInsertId : $result;
    }
    
    /**
     * {@inheritdoc}
     * 
     * @param array|int|string|null $id
     * 
     * @return bool|mixed
     */
    public function modify($id = null, array|object $data)
    {
        $id = $id ?: $this->primaryKeyValue;
        $id = $id ?: $this->idValue($data);
        
        // Filtrer les données selon fillable/guarded
        $data = $this->filterFillable($data);
        
        // Ne pas permettre la mise à jour de la clé primaire
        unset($data[$this->primaryKey]);
        
        if ($data === []) {
            return true; // Rien à mettre à jour
        }
        
        // Valider les données
        if (!$this->validate($data, 'update')) {
            return false;
        }
        
        // Ajouter les timestamps
        $data = $this->addTimestamps($data, 'update');
        
        $eventData = $this->fire('beforeUpdate', [
            'id'   => $id,
            'data' => $data,
        ]);
        
        if ($eventData['cancelled'] ?? false) {
            return false;
        }
        
        $data = $eventData['data'] ?? $data;
        $id   = $eventData['id'] ?? $id;
        
        $builder = $this->query();
        
        if (!in_array($id, [null, '', 0, '0', []], true)) {
            $ids = is_array($id) ? $id : [$id];
            $builder->whereIn($this->primaryKey, $ids);
        }
        
        if ($builder->wheres === []) {
            throw new DatabaseException('Updates require a WHERE clause for safety.');
        }
        
        $result = $builder->update($data);
        
        $this->fire('afterUpdate', [
            'id'     => $id,
            'data'   => $data,
            'result' => $result,
        ]);
        
        $this->resetTemporaryStates();
        
        return (bool) $result;
    }
    
    /**
     * {@inheritdoc}
     * 
     * @param array|int|string|null $id
     * 
     * @return bool|mixed
     */
    public function remove($id = null, bool $force = false): bool
    {
        $id = $id ?: $this->primaryKeyValue;

        $eventData = $this->fire('beforeDelete', [
            'id'    => $id,
            'force' => $force,
        ]);
        
        if ($eventData['cancelled'] ?? false) {
            return false;
        }
        
        $id = $eventData['id'] ?? $id;
        $force = $eventData['force'] ?? $force;
        
        $builder = $this->query();
        
        if (!in_array($id, [null, '', 0, '0', []], true)) {
            $ids = is_array($id) ? $id : [$id];
            $builder->whereIn($this->primaryKey, $ids);
        }
        
        if ($builder->wheres === []) {
            throw new DatabaseException('Deletes require a WHERE clause for safety.');
        }
        
        if ($this->useSoftDeletes && !$force) {
            $this->applySoftDeleteCondition($builder);
            
            $set = [$this->deletedField => $this->freshTimestamp()];
            if ($this->useTimestamps && $this->updatedField !== '') {
                $set[$this->updatedField] = $set[$this->deletedField];
            }

            $result = $builder->update($set);
        } else {
            $result = $builder->delete();
        }
        
        $this->fire('afterDelete', [
            'id'     => $id,
            'result' => $result,
            'force'  => $force,
        ]);
        
        $this->resetTemporaryStates();
        
        return $result > 0;
    }
    
    /**
     * Purge définitivement les éléments supprimés
     */
    public function purge(): int
    {
        if (!$this->useSoftDeletes) {
            return 0;
        }
        
        return $this->query()
            ->whereNotNull($this->deletedField)
            ->delete();
    }

    /**
     * Méthode pratique qui tentera de déterminer si les données doivent être insérées ou mises à jour.
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
            $response = $response !== false;
        }

        return $response;
    }
    
    /**
     * Pagination
     * 
     * @return array{
     *  data: Collection, 
     *  pagination: array{
     *      total: int,
     *      per_page: int, 
     *      current_page: int, 
     *      from: int,
     *      to: int
     *  }
     * }
     */
    public function paginate(?int $limit = null, ?int $page = null, ?int $total = null): array
    {
        $page   = max((int) $page, 1);
        $limit  = $limit ?: $this->perPage;
        $offset = ($page - 1) * $limit;
        
        $total = $total ?: $this->countAllResults(false);
        
        $data = $this->limit($limit, $offset)->collect($this->tempReturnType);
        
        $this->resetTemporaryStates();
        
        return [
            'data'       => $data,
            'pagination' => [
                'total'        => $total,
                'per_page'     => $limit,
                'current_page' => $page,
                'last_page'    => (int) ceil($total / $limit),
                'from'         => $offset + 1,
                'to'           => min($offset + $limit, $total),
            ],
        ];
    }
    
    /**
     * Traitement par lots
     */
    public function chunk(int $size, Closure $callback): bool
    {
        return $this->query()->chunk($size, function(Collection $rows, int $page) use ($callback) {
            if (class_exists($this->tempReturnType)) {
                $rows = $rows->mapInto($this->tempReturnType);
            }
            
            $this->resetTemporaryStates();
            
            return $callback($rows, $page);
        });
    }
    
    /**
     * Compte tous les résultats
     */
    public function countAllResults(bool $reset = true): int
    {
        $builder = $this->query();
        
        $this->applySoftDeleteCondition($builder);
        
        $count = $builder->countAllResults();
        
        if ($reset) {
            $this->resetTemporaryStates();
        }
        
        return (int) $count;
    }
    
    /**
     * Active temporairement les soft deletes
     */
    public function withDeleted(bool $enabled = true): static
    {
        $this->tempUseSoftDeletes = !$enabled;

        return $this;
    }
    
    /**
     * Ne récupère que les éléments supprimés
     */
    public function onlyDeleted(): static
    {
        $this->tempUseSoftDeletes = false;

        $this->query()->whereNotNull($this->deletedField);
        
        return $this;
    }
    
    /**
     * Définit le type de retour
     */
    public function asArray(): static
    {
        $this->tempReturnType = 'array';
        
        return $this;
    }
    
    /**
     * Définit le type de retour comme objet
     * 
     * @param 'object'|class-string $class
     */
    public function asObject(string $class = 'object'): static
    {
        $this->tempReturnType = $class;
        
        return $this;
    }
    
    /**
     * Active/désactive les callbacks temporairement
     */
    public function allowCallbacks(): static
    {
        $this->tempAllowCallbacks = false;
        
        return $this;
    }

    /**
     * Désactive les callbacks temporairement
     */
    public function withoutCallbacks(): static
    {
        return $this->allowCallbacks(false);
    }
    
    /**
     * Applique la condition de soft delete
     */
    protected function applySoftDeleteCondition(BaseBuilder $builder): void
    {
        if ($this->tempUseSoftDeletes && $this->useSoftDeletes) {
            $builder->whereNull($this->deletedField);
        }
    }
    
    /**
     * Ajoute les timestamps
     */
    protected function addTimestamps(array $data, string $action): array
    {
        if (!$this->useTimestamps) {
            return $data;
        }
        
        $timestamp = $this->freshTimestamp();
        
        if ($action === 'create' && $this->createdField && !isset($data[$this->createdField])) {
            $data[$this->createdField] = $timestamp;
        }
        
        if ($this->updatedField && !isset($data[$this->updatedField])) {
            $data[$this->updatedField] = $timestamp;
        }
        
        return $data;
    }
    
    /**
     * Timestamp formaté
     */
    protected function freshTimestamp(): string|int
    {
        $now = Date::now();
        
        return match($this->dateFormat) {
            'int' => $now->getTimestamp(),
            'date' => $now->format('Y-m-d'),
            default => $now->format('Y-m-d H:i:s'),
        };
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

        if (is_array($data) && isset($data[$this->primaryKey])) {
            return $data[$this->primaryKey];
        }

        return null;
    }

    /**
     * Cette méthode est appelée lors de la sauvegarde pour déterminer si l'entrée doit être mise à jour.
     */
    protected function shouldUpdate(array|object $data): bool
    {
        if (empty($id = $this->idValue($data))) {
            return false;
        }

        if ($this->useAutoIncrement === true) {
            return true;
        }

        return $this->where($this->primaryKey, $id)->countAllResults() === 1;
    }
    
    /**
     * Filtre les données selon fillable/guarded
     */
    protected function filterFillable(array|object $data): array
    {
        if (empty($data)) {
            return [];
        }

        if (is_object($data) && ! $data instanceof stdClass) {
            $data = $this->objectToArray($data);
        }
        
        if (is_object($data)) {
            $data = (array) $data;
        }

        if ($this->fillable !== []) {
            return array_intersect_key($data, array_flip($this->fillable));
        } 
        if ($this->guarded !== []) {
            return array_diff_key($data, array_flip($this->guarded));
        }
        
        return $data;
    }

    /**
     * Takes a class and returns an array of its public and protected
     * properties as an array suitable for use in creates and updates.
     *
     * @return array<string, mixed>
     */
    protected function objectToArray(object $object): array
    {
        if (method_exists($object, 'toArray')) {
            $properties = $object->toArray();
        } else {
            $mirror = new ReflectionClass($object);
            $props  = $mirror->getProperties(ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED);

            $properties = [];
            foreach ($props as $prop) {
                $properties[$prop->getName()] = $prop->getValue($object);
            }
        }

        return $properties;
    }
    
    /**
     * Validation des données
     */
    protected function validate(array $data, string $action): bool
    {
        if ($this->rules === []) {
            return true;
        }
        
        // Si un validateur est disponible
        if (class_exists(Validator::class)) {
            $validator = Validator::make($data, $this->rules, $this->messages);
            
            if ($validator->fails()) {
                $this->errors = $validator->errors();

                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Déclenche un événement
     */
    protected function fire(string $event, array $payload = []): array
    {
        if (!$this->tempAllowCallbacks || !isset($this->callbacks[$event])) {
            return $payload;
        }
        
        foreach ($this->callbacks[$event] as $callback) {
            if (is_string($callback) && method_exists($this, $callback)) {
                $result = $this->{$callback}($payload);
                
                if (is_array($result)) {
                    $payload = $result;
                } elseif ($result === false) {
                    $payload['cancelled'] = true;
                    break;
                }
            }
        }
        
        return $payload;
    }
    
    /**
     * Réinitialise les états temporaires
     */
    protected function resetTemporaryStates(): void
    {
        $this->tempReturnType     = $this->returnType;
        $this->tempUseSoftDeletes = $this->useSoftDeletes;
        $this->tempAllowCallbacks = $this->allowCallbacks;
    }
    
    /**
     * Magic getter
     */
    public function __get(string $name)
    {
        if (property_exists($this, $name)) {
            return $this->{$name};
        }
        
        if (isset($this->db->{$name})) {
            return $this->db->{$name};
        }
        
        $builder = $this->builder();
        
        return $builder->{$name} ?? null;
    }
    
    /**
     * Magic isset
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
     * Magic call pour proxy vers le Query Builder
     */
    public function __call(string $name, array $arguments)
    {
        // Méthodes du Query Builder
        if (method_exists($this->builder(), $name)) {
            $result = $this->builder()->{$name}(...$arguments);
            
            // Si le résultat est une instance du builder, retourner $this pour la fluidité
            if ($result instanceof BaseBuilder) {
                return $this;
            }
            
            return $result;
        }
        
        // Méthodes de la connexion
        if (method_exists($this->db, $name)) {
            return $this->db->{$name}(...$arguments);
        }
        
        throw new BadMethodCallException("Method {$name} not found in " . static::class);
    }
}
