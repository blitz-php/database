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
 * des fichiers de migration.
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
     * Mode silencieux (pas de messages)
     */
    protected bool $silent = false;

    /**
     * Specifie si les migrations sont activees ou pas.
     */
    protected bool $enabled = false;

    /**
     * Messages de sortie
     *
     * @var array<string>
     */
    protected array $output = [];

    /**
     * Constructeur
     *
     * @param list<string>    $paths Chemins de recherche des migrations
     */
    public function __construct(protected DatabaseManager $dbManager, protected ?string $group, protected array $paths = [])
    {
        $config = config('migrations');

        $this->enabled = $config['enabled'] ?? false;
        $this->db      = $this->dbManager->connect($group);
        $this->history = new History($dbManager, $config['table'] ?? 'migrations');
    }

    /**
     * Active/désactive le mode silencieux
     */
    public function setSilent(bool $silent): self
    {
        $this->silent = $silent;

        return $this;
    }

    /**
     * Récupère les messages de sortie
     *
     * @return array<string>
     */
    public function getOutput(): array
    {
        return $this->output;
    }

    /**
     * Exécute toutes les migrations en attente
     *
     * @param string|null $group Groupe de connexion
     * @return array<string> Messages de sortie
     */
    public function latest(?string $group = null): array
    {
        $this->output = [];
        $migrations = $this->getPendingMigrations($group);

        dd($migrations);

        if (empty($migrations)) {
            $this->addOutput('Aucune migration en attente');

            return $this->output;
        }

        $batch = $this->history->getLastBatch() + 1;

        foreach ($migrations as $file => $migration) {
            $this->runMigration($migration, 'up', $group, $batch);
        }

        $this->addOutput(sprintf('✔ %d migration(s) exécutée(s)', count($migrations)), 'success');

        return $this->output;
    }

    /**
     * Annule les dernières migrations
     *
     * @param int         $steps Nombre de lots à annuler
     * @param string|null $group Groupe de connexion
     * @return array<string> Messages de sortie
     */
    public function rollback(int $steps = 1, ?string $group = null): array
    {
        $this->output = [];
        $batches = $this->history->getBatches();

        if (empty($batches)) {
            $this->addOutput('Aucune migration à annuler');
            
            return $this->output;
        }

        $targetBatch = count($batches) - $steps;
        $rolledBack = 0;

        for ($i = count($batches) - 1; $i >= $targetBatch; $i--) {
            $batchMigrations = $this->history->getBatch($batches[$i], 'desc');

            foreach ($batchMigrations as $history) {
                $migration = $this->loadMigration($history->class, $history->namespace);
                $migration->history = $history;
                $this->runMigration($migration, 'down', $group);
                $rolledBack++;
            }
        }

        $this->addOutput(sprintf('✔ %d migration(s) annulée(s)', $rolledBack), 'success');

        return $this->output;
    }

    /**
     * Annule toutes les migrations
     *
     * @param string|null $group Groupe de connexion
     * @return array<string> Messages de sortie
     */
    public function reset(?string $group = null): array
    {
        return $this->rollback(PHP_INT_MAX, $group);
    }

    /**
     * Réinitialise et réexécute toutes les migrations
     *
     * @param string|null $group Groupe de connexion
     * @return array<string> Messages de sortie
     */
    public function refresh(?string $group = null): array
    {
        $output = $this->reset($group);
        $output = array_merge($output, $this->latest($group));

        return $output;
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

        $executedClasses = array_map(fn($item) => $item->class, $executed);

        return array_filter(
            $files,
            fn($file, $class) => !in_array($class, $executedClasses),
            ARRAY_FILTER_USE_BOTH
        );
    }

    /**
     * Trouve tous les fichiers de migration
     *
     * @return array<object>
     */
    protected function findMigrationFiles(): array
    {
        $files = [];

        foreach ($this->paths as $path) {
            $globFiles = glob($path . '/*_*.php') ?: [];

            foreach ($globFiles as $file) {
                $content = file_get_contents($file);
                $namespace = $this->extractNamespace($content);
                $className = $namespace . '\\' . basename($file, '.php');

                if (preg_match($this->pattern, basename($file), $matches)) {
                    $files[$file] = (object) [
                        'path'      => $file,
                        'version'   => $matches[1],
                        'name'      => $matches[2],
                        'class'     => $className,
                        'namespace' => $namespace,
                    ];
                }
            }
        }

        uasort($files, fn($a, $b) => strcmp($a->version, $b->version));

        return $files;
    }

    /**
     * Extrait le namespace d'un fichier PHP
     */
    protected function extractNamespace(string $content): string
    {
        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            return $matches[1];
        }

        return '';
    }

    /**
     * Charge une instance de migration
     * 
     * @throws RuntimeException
     */
    protected function loadMigration(string $class, string $namespace): Migration
    {
        if (!class_exists($class)) {
            throw new RuntimeException(sprintf(
                'Classe de migration introuvable : %s',
                $class
            ));
        }

        return new $class($this->db);
    }

    /**
     * Exécute une migration
     *
     * @param object      $migration Données de la migration
     * @param string      $direction Direction (up|down)
     * @param string|null $group     Groupe
     * @param int|null    $batch     Lot (pour up)
     */
    protected function runMigration(object $migration, string $direction, ?string $group, ?int $batch = null): void
    {
        require_once $migration->path;

        $instance = $this->loadMigration($migration->class, $migration->namespace);

        // Vérifier le groupe
        if ($group && $instance->getGroup() && $instance->getGroup() !== $group) {
            $this->addOutput(sprintf(
                '⏭️  Migration %s_%s ignorée (groupe différent)',
                $migration->version,
                $migration->name
            ), 'comment');
            return;
        }

        $this->addOutput(sprintf(
            '%s : %s_%s',
            $direction === 'up' ? '⬆️  Exécution' : '⬇️  Annulation',
            $migration->version,
            $migration->name
        ), 'info');

        // Exécuter la migration
        $instance->{$direction}();

        // Appliquer les changements
        $executor = new Executor($this->dbManager->creator($this->db));
        foreach ($instance->getBuilders() as $builder) {
            $executor->execute($builder);
        }

        // Mettre à jour l'historique
        if ($direction === 'up' && $batch) {
            $this->history->add(
                $migration->version,
                $migration->class,
                $migration->namespace,
                $instance->getGroup() ?? $group ?? 'default',
                $batch
            );
        } elseif ($direction === 'down' && isset($migration->history)) {
            $this->history->remove($migration->history->id);
        }

        $this->addOutput(sprintf('   ✅ Terminé', 'success'));
    }

    /**
     * Ajoute un message à la sortie
     */
    protected function addOutput(string $message, string $type = 'info'): void
    {
        $this->output[] = $message;

        if (!$this->silent) {
            // Ici, vous pouvez appeler votre système de console
            // Par exemple : $this->console->$type($message)
        }
    }
}
