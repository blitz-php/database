<?php

/**
 * This file is part of Blitz PHP framework - Database Layer.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Database\Migration;

use BlitzPHP\Database\Connection\BaseConnection;
use BlitzPHP\Database\DatabaseManager;
use RuntimeException;

/**
 * Exécuteur de migrations
 * 
 * Cette classe orchestre la découverte, l'ordonnancement et l'exécution
 * des fichiers de migration, avec support des classes anonymes et nommées.
 */
class Runner
{
    /**
     * Longueur de chaîne par défaut pour les migrations.
     */
    public static int $defaultStringLength = 255;

    /**
     * Type par défaut de clé pour les relations de polymorphiques.
     */
    public static string $defaultMorphKeyType = 'int';

    /**
     * Connexion à la base de données
     */
    protected BaseConnection $db;

    /**
     * Gestionnaire d'historique
     */
    protected History $history;

    /**
     * Pattern de reconnaissance des fichiers de migration
     */
    protected string $pattern = '/\A(\d{4}[_-]?\d{2}[_-]?\d{2}[_-]?\d{6})_(\w+)\z/';

    /**
     * Spécifie si les migrations sont activées ou pas.
     */
    protected bool $enabled = false;

    /**
     * Cache des instances de migration chargées
     *
     * @var array<string, Migration>
     */
    protected array $loaded = [];

    /**
     * Callbacks pour les événements
     *
     * @var array<string, array<callable>>
     */
    protected array $listeners = [];

    /**
     * Constructeur
     *
     * @param array<string, list<string>> $paths Chemins de recherche des migrations
     */
    public function __construct(protected DatabaseManager $dbManager, protected ?string $group, protected array $paths = [])
    {
        $config = config('migrations');

        $this->enabled = $config['enabled'] ?? false;
        $this->db      = $this->dbManager->connect($group);
        $this->history = new History($dbManager, $config['table'] ?? 'migrations');
    }

    /**
     * Enregistre un écouteur pour un événement
     */
    public function on(string $event, callable $callback): self
    {
        if (!isset($this->listeners[$event])) {
            $this->listeners[$event] = [];
        }
        
        $this->listeners[$event][] = $callback;
        
        return $this;
    }

    /**
     * Déclenche un événement
     */
    protected function fire(string $event, array $payload = []): void
    {
        if (!isset($this->listeners[$event])) {
            return;
        }

        foreach ($this->listeners[$event] as $callback) {
            $callback($payload, $event, $this);
        }
    }

    /**
     * Exécute toutes les migrations en attente
     *
     * @param string|null $group Groupe de connexion
     * @return int Nombre de migrations exécutées
     */
    public function latest(?string $group = null): int
    {
        if (!$this->enabled) {
            $this->fire('process.migrations-disabled');
            return 0;
        }

        $migrations = $this->getPendingMigrations($group);

        if ($migrations === []) {
            $this->fire('process.empty-migrations');
            return 0;
        }

        $this->fire('process.start', [
            'count' => count($migrations),
            'group' => $group,
            'start' => $start = microtime(true),
        ]);
        
        $batch = $this->history->getLastBatch() + 1;
        $executed = 0;

        foreach ($migrations as $migration) {
            $success = $this->runMigration($migration, 'up', $group, $batch);
            if ($success) {
                $executed++;
            }
        }

        $this->fire('process.completed', [
            'executed' => $executed,
            'total'    => count($migrations),
            'batch'    => $batch,
            'duration' => number_format(microtime(true) - $start, 4),
        ]);

        return $executed;
    }

    /**
     * Annule les dernières migrations
     *
     * @param int         $steps Nombre de lots à annuler
     * @param string|null $group Groupe de connexion
     * @return int Nombre de migrations annulées
     */
    public function rollback(int $steps = 1, ?string $group = null): int
    {
        if (!$this->enabled) {
            $this->fire('process.migrations-disabled');
            return 0;
        }

        $batches = $this->history->getBatches();

        if ($batches === []) {
            $this->fire('process.empty-migrations');
            return 0;
        }

        $this->fire('process.start', [
            'steps'             => $steps,
            'available_batches' => count($batches),
            'group'             => $group,
            'start'             => $start = microtime(true),
        ]);

        $targetBatch = $steps === 0 ? 0 : (count($batches) - $steps);
        $rolledBack = 0;

        for ($i = count($batches) - 1; $i >= $targetBatch; $i--) {
            $batchMigrations = $this->history->getBatch($batches[$i], 'desc');

            foreach ($batchMigrations as $history) {
                $migration = $this->createMigrationFromHistory($history);
                
                if (!$migration->path) {
                    $this->fire('migration.skipped', ['migration' => $migration]);
                    continue;
                }

                $success = $this->runMigration($migration, 'down', $group);
                if ($success) {
                    $rolledBack++;
                }
            }
        }

        $this->fire('process.completed', [
            'rolled_back'  => $rolledBack,
            'target_batch' => $targetBatch,
            'duration'     => number_format(microtime(true) - $start, 4),
        ]);

        return $rolledBack;
    }

    /**
     * Annule toutes les migrations
     *
     * @param string|null $group Groupe de connexion
     * @return int Nombre de migrations annulées
     */
    public function reset(?string $group = null): int
    {
        return $this->rollback(PHP_INT_MAX, $group);
    }

    /**
     * Réinitialise et réexécute toutes les migrations
     *
     * @param string|null $group Groupe de connexion
     * @return array{reset: int, latest: int} Nombre de migrations annulées et exécutées
     */
    public function refresh(?string $group = null): array
    {
        $reset = $this->reset($group);
        $latest = $this->latest($group);

        return ['reset' => $reset, 'latest' => $latest];
    }

    /**
     * Crée un objet migration à partir de l'historique
     */
    protected function createMigrationFromHistory(object $history): object
    {
        $migration = (object) [
            'path'      => null,
            'version'   => $history->version,
            'migration' => $history->migration,
            'namespace' => $history->namespace,
            'history'   => $history,
        ];

        // Trouver le chemin du fichier
        $files = $this->findMigrationFiles();
        foreach ($files as $file) {
            if ($file->version === $history->version && 
                $file->migration === $history->migration && 
                $file->namespace === $history->namespace) {
                $migration->path = $file->path;
                break;
            }
        }

        return $migration;
    }

    /**
     * Récupère les migrations en attente
     * 
     * @return array<object>
     */
    protected function getPendingMigrations(?string $group): array
    {
        $files = $this->findMigrationFiles();
        $executed = $this->history->getAll($group);
        
        $executedMap = [];
        foreach ($executed as $item) {
            $key = implode('.', [$item->namespace, $item->migration, $item->version]);
            $executedMap[$key] = true;
        }

        return array_filter($files, function($file) use ($executedMap) {
            $key = implode('.', [$file->namespace, $file->migration, $file->version]);
            return !isset($executedMap[$key]);
        });
    }

    /**
     * Trouve tous les fichiers de migration
     *
     * @return array<object>
     */
    public function findMigrationFiles(): array
    {
        $files = [];

        foreach ($this->paths as $namespace => $paths) {
            foreach ($paths as $file) {
                $name = basename($file, '.php');
                if (preg_match($this->pattern, $name, $matches)) {
                    $files[] = (object) [
                        'path'      => $file,
                        'version'   => $matches[1],
                        'migration' => $matches[2],
                        'namespace' => $namespace,
                    ];
                }
            }
        }

        usort($files, fn($a, $b) => strcmp($a->version, $b->version));

        return $files;
    }

    /**
     * Charge une instance de migration
     * 
     * @param object $migration Données de la migration
     * @param bool   $fresh     Forcer un rechargement frais
     * 
     * @throws RuntimeException
     */
    protected function loadMigration(object $migration, bool $fresh = false): Migration
    {
        $cacheKey = $migration->path . ':' . ($fresh ? 'fresh' : 'cached');

        // Retourner l'instance en cache si disponible et pas de rechargement frais
        if (!$fresh && isset($this->loaded[$cacheKey])) {
            return clone $this->loaded[$cacheKey];
        }

        // Détecter le type de migration
        $content = file_get_contents($migration->path);
        if ($content === false) {
            throw new RuntimeException("Impossible de lire le fichier : {$migration->path}");
        }

        $isAnonymous = str_contains($content, 'return new class extends Migration');

        if ($isAnonymous) {
            // Mode anonyme : le fichier retourne directement l'instance
            $instance = require $migration->path;
            
            if (!$instance instanceof Migration) {
                throw new RuntimeException(
                    "Le fichier {$migration->path} doit retourner une instance de Migration"
                );
            }
        } else {
            // Mode classique : chercher la classe déclarée
            require_once $migration->path;
            
            $className = $this->extractClassName($content, $migration->namespace);
            
            if (!$className || !class_exists($className)) {
                throw new RuntimeException(
                    "Impossible de trouver la classe de migration dans {$migration->path}"
                );
            }
            
            $instance = new $className();
        }

        $instance = $instance->initialize($this->dbManager, $this->db);
        
        // Mettre en cache pour les appels suivants
        if (!$fresh) {
            $this->loaded[$migration->path . ':cached'] = $instance;
        }

        return $instance;
    }

    /**
     * Extrait le nom de classe du contenu PHP
     */
    protected function extractClassName(string $content, string $namespace): ?string
    {
        // Chercher "class NomDeClasse extends Migration"
        if (preg_match('/class\s+([a-zA-Z0-9_]+)\s+extends\s+Migration/', $content, $matches)) {
            $className = $matches[1];
            
            // Si le namespace est vide ou déjà présent
            if (empty($namespace) || str_starts_with($className, $namespace)) {
                return $className;
            }
            
            return $namespace . '\\' . $className;
        }

        return null;
    }

    /**
     * Exécute une migration
     *
     * @param object      $migration Données de la migration
     * @param string      $direction Direction (up|down)
     * @param string|null $group     Groupe
     * @param int|null    $batch     Lot (pour up)
     * @return bool Succès ou échec
     */
    protected function runMigration(object $migration, string $direction, ?string $group, ?int $batch = null): bool
    {
        $this->fire('migration.before', [
            'migration' => $migration,
            'direction' => $direction,
            'group'     => $group,
            'batch'     => $batch,
        ]);

        try {
            // Pour le rollback, on force un rechargement frais
            $fresh = ($direction === 'down');
            $instance = $this->loadMigration($migration, $fresh);

            // Vérifier si la migration doit être exécutée
            if ($direction === 'up' && !$instance->shouldRun()) {
                $this->fire('migration.ignored', ['migration' => $migration]);
                
                // On marque comme réussie pour ne pas bloquer les suivantes
                return true;
            }

            // Exécuter la migration
            $start = microtime(true);
            $instance->{$direction}();

            // On doit créer un transformer pour chaque connexion utilisée
            $transformers = [];
            
            foreach ($instance->getBuilders() as $builder) {
                $conn = $builder->getConnection();
                $connKey = spl_object_hash($conn);
                
                if (!isset($transformers[$connKey])) {
                    $transformers[$connKey] = new Transformer($this->dbManager->creator($conn));
                }
                
                $transformers[$connKey]->process($builder);
            }

            // Mettre à jour l'historique
            if ($direction === 'up' && $batch) {
                $this->history->add(
                    $migration->version,
                    $migration->migration,
                    $migration->namespace,
                    $group ?? 'default',
                    $batch
                );
            } elseif ($direction === 'down') {
                $this->removeFromHistory($migration, $group);
            }

            $this->fire('migration.done', [
                'migration' => $migration,
                'duration'  => number_format(microtime(true) - $start, 3),
            ]);

            return true;
        } catch (\Throwable $e) {
            $this->fire('migration.error', [
                'migration' => $migration,
                'direction' => $direction,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);

            return false;
        }
    }

    /**
     * Supprime une entrée de l'historique
     */
    protected function removeFromHistory(object $migration, ?string $group): void
    {
        $history = $this->history->getAll($group);
        
        foreach ($history as $entry) {
            if ($entry->migration === $migration->migration && 
                $entry->version === $migration->version && 
                $entry->namespace === $migration->namespace) {
                
                $this->history->remove($entry->id);
                
                break;
            }
        }
    }

    /**
     * Récupère l'historique des migrations
     */
    public function getHistory(?string $group = null): array
    {
        return $this->history->getAll($group);
    }

    /**
     * Récupère le dernier numéro de lot
     */
    public function getLastBatch(): int
    {
        return $this->history->getLastBatch();
    }

    /**
     * Vide le cache des instances chargées
     */
    public function clearCache(): self
    {
        $this->loaded = [];

        return $this;
    }
}
