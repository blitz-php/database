<?php

/**
 * This file is part of Blitz PHP framework - Database Layer.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Database\Seeder;

use BlitzPHP\Database\Builder\BaseBuilder;
use BlitzPHP\Database\Connection\BaseConnection;
use BlitzPHP\Database\Exceptions\SeederException;
use Faker\Generator as FakerGenerator;
use PDO;

/**
 * Définition d'une table à remplir
 */
class Seed
{
    /**
     * Builder pour la table
     */
    protected BaseBuilder $builder;

    /**
     * Définition des colonnes
     * 
     * @var array<string, mixed>
     */
    protected array $columns = [];

    /**
     * Closures pour les cas complexes
     * 
     * @var array<string, callable>
     */
    protected array $closures = [];

    /**
     * Données brutes
     */
    protected array $rawData = [];

    /**
     * Nombre de lignes à générer
     */
    protected int $rowCount = 30;

    /**
     * Faut-il vider la table avant ?
     */
    protected bool $truncate = false;

    /**
     * Callbacks avant insertion
     * 
     * @var list<callable>
     */
    protected array $beforeInsertCallbacks = [];

    /**
     * Callbacks après insertion
     * 
     * @var list<callable>
     */
    protected array $afterInsertCallbacks = [];

    /**
     * Données générées
     */
    protected array $generated = [];

    /**
     * Cache des valeurs résolues
     */
    protected array $cache = [];

    /**
     * Constructeur
     * 
     * @param BaseConnection $db Connexion à la base de données
     * @param string $table Nom de la table
     * @param FakerGenerator $faker Générateur Faker
     */
    public function __construct(protected BaseConnection $db, protected string $table, protected FakerGenerator $faker)
    {
        $this->builder = $db->table($table);
    }

    /**
     * Définit les colonnes
     */
    public function columns(array $columns): self
    {
        foreach ($columns as $name => $definition) {
            $this->column($name, $definition);
        }

        return $this;
    }

    /**
     * Ajoute une colonne
     */
    public function column(string $name, mixed $definition): self
    {
        // Si c'est une closure, on la garde à part pour la flexibilité
        if (is_callable($definition) && !is_string($definition)) {
            $this->closures[$name] = $definition;
        } else {
            $this->columns[$name] = $definition;
        }

        return $this;
    }

    /**
     * Définit le nombre de lignes à insérer en base de données
     */
    public function rows(int $count): self
    {
        $this->rowCount = $count;

        return $this;
    }

    /**
     * Définit des données brutes
     */
    public function data(array $data): self
    {
        if ($data === []) {
            throw SeederException::dataCannotBeEmpty();
        }

        $firstRow = reset($data);
        
        if (! is_array($firstRow)) {
            $this->rawData = [$data];
        } else {
            $this->rawData = $data;
        }

        return $this;
    }

    /**
     * Vide la table avant insertion
     */
    public function truncate(bool $truncate = true): self
    {
        $this->truncate = $truncate;

        return $this;
    }

    /**
     * Ajoute un callback avant chaque insertion
     */
    public function beforeInsert(callable $callback): self
    {
        $this->beforeInsertCallbacks[] = $callback;
        
        return $this;
    }

    /**
     * Ajoute un callback après chaque insertion
     */
    public function afterInsert(callable $callback): self
    {
        $this->afterInsertCallbacks[] = $callback;
        
        return $this;
    }

    /**
     * Exécute le seed
     */
    public function execute(): void
    {
        if ($this->truncate) {
            $this->builder->truncate();
        }

        if (!empty($this->rawData)) {
            $this->insertRawData();
        } else {
            $this->generateData();
        }
    }

    /**
     * Insère les données brutes
     */
    protected function insertRawData(): void
    {
        $columns = array_keys(reset($this->rawData));

        foreach ($this->rawData as $index => $row) {
            $data = [];
            foreach ($columns as $column) {
                $data[$column] = $row[$column] ?? null;
            }

            $this->executeCallbacks($this->beforeInsertCallbacks, $data, $index);
            $this->builder->insert($data);
            $this->executeCallbacks($this->afterInsertCallbacks, $data, $index, $this->db->lastId());
        }
    }

    /**
     * Génère les données
     */
    protected function generateData(): void
    {
        // Résoudre les dépendances une seule fois
        $dependencies = $this->resolveDependencies();

        // Générer les données ligne par ligne
        for ($i = 0; $i < $this->rowCount; $i++) {
            $row = [];
            
            // Traiter les configurations d'abord (plus rapides)
            foreach ($this->columns as $column => $definition) {
                $row[$column] = $this->resolveConfig($definition, $dependencies);
            }
            
            // Traiter les closures ensuite (plus flexibles)
            foreach ($this->closures as $column => $closure) {
                $row[$column] = $closure($this->faker, $dependencies, $i);
            }
            
            $this->generated[] = $row;
        }

        // Insérer les données
        foreach ($this->generated as $index => $row) {
            $this->executeCallbacks($this->beforeInsertCallbacks, $row, $index);
            $this->builder->insert($row);
            $this->executeCallbacks($this->afterInsertCallbacks, $row, $index, $this->db->lastId());
        }
    }

    /**
     * Résout une configuration
     */
    protected function resolveConfig(mixed $config, array $dependencies): mixed
    {
        // Cache pour les appels répétés
        $cacheKey = is_array($config) ? md5(serialize($config)) : null;
        
        if ($cacheKey && isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $result = match (true) {
            // Valeur simple (pas de configuration)
            !is_array($config) => $config,

            // Configuration standard
            default => $this->resolveArrayConfig($config, $dependencies)
        };

        if ($cacheKey) {
            $this->cache[$cacheKey] = $result;
        }

        return $result;
    }

    /**
     * Résout une configuration sous forme de tableau
     */
    protected function resolveArrayConfig(array $config, array $dependencies): mixed
    {
        $type = $config[0] ?? null;

        return match ($type) {
            'faker' => $this->resolveFaker($config),
            'faker:unique' => $this->resolveUniqueFaker($config),
            'relation' => $this->resolveRelation($config, $dependencies),
            'optional' => $this->resolveOptional($config, $dependencies),
            default => $config // Retourne tel quel si non reconnu
        };
    }

    /**
     * Résout un appel Faker
     */
    protected function resolveFaker(array $config): mixed
    {
        $method = $config[1] ?? null;
        $arguments = $config[2] ?? [];

        if (!$method) {
            throw SeederException::unspecifiedFakerMethod();
        }

        return $this->faker->{$method}(...$arguments);
    }

    /**
     * Résout un appel Faker unique
     */
    protected function resolveUniqueFaker(array $config): mixed
    {
        $method = $config[1] ?? null;
        $arguments = $config[2] ?? [];
        $maxRetries = $config[3] ?? 10000;

        if (!$method) {
            throw SeederException::unspecifiedFakerMethod();
        }

        return $this->faker->unique(maxRetries: (int) $maxRetries)->{$method}(...$arguments);
    }

    /**
     * Résout une relation
     */
    protected function resolveRelation(array $config, array $dependencies): mixed
    {
        $table = $config[1] ?? null;
        $column = $config[2] ?? 'id';

        if (!$table || !isset($dependencies[$table])) {
            throw SeederException::relationTableNotFound($table);
        }

        $values = $dependencies[$table];
        return $values[array_rand($values)];
    }

    /**
     * Résout une valeur optionnelle
     */
    protected function resolveOptional(array $config, array $dependencies): mixed
    {
        $weight = $config[1] ?? 0.5;
        $default = $config[2] ?? null;
        $value = $config[3] ?? null;

        if (mt_rand() / mt_getrandmax() <= $weight) {
            if ($value === null) {
                // Pas de valeur fournie, on utilise une closure par défaut
                // ou on retourne null
                return null;
            }
            return $this->resolveConfig($value, $dependencies);
        }

        return $default;
    }

    /**
     * Résout les dépendances entre tables
     */
    protected function resolveDependencies(): array
    {
        $dependencies = [];

        // Chercher les relations dans les configurations
        foreach ($this->columns as $definition) {
            if (is_array($definition) && ($definition[0] ?? null) === 'relation') {
                $table = $definition[1] ?? null;
                if ($table && $table !== $this->table) {
                    $dependencies[$table] = $this->getTableValues($table, $definition[2] ?? 'id');
                }
            }
        }

        // TODO Chercher aussi dans les closures (moins probable mais possible)
        foreach ($this->closures as $closure) {
            // On ne peut pas analyser les closures statiquement
            // On laisse l'utilisateur gérer
        }

        return $dependencies;
    }

    /**
     * Récupère les valeurs d'une table pour les relations
     */
    protected function getTableValues(string $table, string $column): array
    {
        return $this->db->table($table)
            ->select($column)
            ->all(PDO::FETCH_COLUMN);
    }

    /**
     * Exécute les callbacks
     */
    protected function executeCallbacks(array $callbacks, array &$data, int $index, $insertId = null): void
    {
        foreach ($callbacks as $callback) {
            $result = $callback($data, $index, $insertId);
            if ($result !== null) {
                $data = $result; // Permet de modifier les données
            }
        }
    }
}
