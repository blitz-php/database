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

use BlitzPHP\Contracts\Database\ConnectionInterface;
use BlitzPHP\Database\Connection\BaseConnection;
use BlitzPHP\Database\Database;
use BlitzPHP\Database\Exceptions\MigrationException;
use PDO;
use RuntimeException;
use stdClass;

/**
 * Classe pour executer les migrations
 *
 * @credit <a href="https://codeigniter.com">CodeIgniter4 - CodeIgniter\Database\MigrationRunner</a>
 */
class Runner
{
    /**
     * Specifie si les migrations sont activees ou pas.
     */
    protected bool $enabled = false;

    /**
     * Nom de la table dans laquelle seront stockees les meta informations de migrations.
     */
    protected string $table;

    /**
     * Le namespace où se trouvent les migrations.
     * `null` correspond à tous les espaces de noms.
     */
    protected ?string $namespace = null;

    /**
     * Liste des fichiers des migrations.
     * Le framework est responsable de la recherche de tous les fichiers necessaires regroupes par namespace.
     *
     * @var array<string, string[]> [namespace => [fichiers]]
     */
    protected array $files = [];

    /**
     * Le groupe de la base de donnee a migrer.
     */
    protected string $group = '';

    /**
     * Le nom de la migration.
     */
    protected string $name;

    /**
     * Le format (pattern) utiliser pour trouver la version du fichier de migration.
     */
    protected string $regex = '/\A(\d{4}[_-]?\d{2}[_-]?\d{2}[_-]?\d{6})_(\w+)\z/';

    /**
     * Connexion a la base de donnees.
     */
    protected BaseConnection $db;

    /**
     * si true, on continue l'activite sans levee d'exception en cas d'erreur.
     */
    protected bool $silent = false;

    /**
     * @var array<string, string>[] utiliser pour renvoyer les messages pour la console.
     */
    protected array $messages = [];

    /**
     * Verifie si on s'est deja rassurer que la table existe ou pas.
     */
    protected bool $tableChecked = false;

    /**
     * Chemin d'accès complet permettant de localiser les fichiers de migration.
     */
    protected string $path;

    /**
     * Le filtre du groupe de la base de donnees.
     */
    protected ?string $groupFilter = null;

    /**
     * Utiliser pour sauter la migration courrante.
     */
    protected bool $groupSkip = false;

    /**
     * singleton
     */
    private static $_instance;

    /**
     * Longueur par défaut des champs de type chaînes (varchar/char) pour les migrations.
     */
    public static int $defaultStringLength = 255;

    /**
     * La migration peut gérer plusieurs bases de données. 
     * Elle doit donc toujours utiliser le groupe de bases de données par défaut afin de créer la table `migrations` dans le groupe de bases de données par défaut. 
     * Par conséquent, le passage de $db est uniquement à des fins de test.
     *
     * @param array|ConnectionInterface|string|null $db Groupe de DB. À des fins de test uniquement.
     */
    public function __construct(array $config, $db = null)
    {
        $this->enabled = $config['enabled'] ?? false;
        $this->table   = $config['table'] ?? 'migrations';

        $this->namespace = defined('APP_NAMESPACE') ? constant('APP_NAMESPACE') : 'App';

        // Même si une connexion DB est transmise comme il s'agit d'un test, 
        // on suppose que le nom de groupe par défaut est utilisé.
        // $this->group = is_string($db) ? $db : config('database.connection');

        if ($db instanceof ConnectionInterface) {
            $this->db = $db;
        } else {
            $this->db = Database::connection($db, static::class);
        }
    }

    /**
     * singleton constructor
     */
    public static function instance(array $config, $db = null): self
    {
        if (null === self::$_instance) {
            self::$_instance = new self($config, $db);
        }

        return self::$_instance;
    }

    /**
     * Définir la longueur de la chaîne par défaut pour les migrations.
     */
    public static function defaultStringLength(int $length): void
    {
        static::$defaultStringLength = $length;
    }

    /**
     * Localisez et exécutez toutes les nouvelles migrations.
     *
     * @throws MigrationException
     * @throws RuntimeException
     */
    public function latest(?string $group = null): bool
    {
        if (! $this->enabled) {
            throw MigrationException::disabledMigrations();
        }

        $this->ensureTable();

        if ($group !== null) {
            $this->groupFilter = $group;
            $this->setGroup($group);
        }

        $migrations = $this->getMigrations();

        if ($migrations === []) {
            return true;
        }

        foreach ($this->getHistory((string) $group) as $history) {
            unset($migrations[$this->getObjectUid($history)]);
        }

        $batch = $this->getLastBatch() + 1;

        foreach ($migrations as $migration) {
            if ($this->migrate('up', $migration)) {
                if ($this->groupSkip === true) {
                    $this->groupSkip = false;

                    continue;
                }

                $this->addHistory($migration, $batch);
            } else {
                $this->regress(-1);

                $message = 'Migration failed!';

                if ($this->silent) {
                    $this->pushMessage($message, 'red');

                    return false;
                }

                throw new RuntimeException($message);
            }
        }

        $data           = get_object_vars($this);
        $data['method'] = 'latest';
        $this->db->triggerEvent($data, 'migrate');

        return true;
    }

    /**
     * Migrer vers un lot précédent
     *
     * Appelle chaque étape de migration requise pour accéder au lot fourni
     *
     * @param int $targetBatch Numéro de lot cible, ou négatif pour un lot relatif, 0 pour tous
     *
     * @return bool Vrai en cas de succès, FAUX en cas d'échec ou aucune migration n'est trouvée
     *
     * @throws MigrationException
     * @throws RuntimeException
     */
    public function regress(int $targetBatch = 0): bool
    {
        if (! $this->enabled) {
            throw MigrationException::disabledMigrations();
        }

        $this->ensureTable();

        $batches = $this->getBatches();

        if ($targetBatch < 0) {
            $targetBatch = $batches[count($batches) - 1 + $targetBatch] ?? 0;
        }

        if ($batches === [] && $targetBatch === 0) {
            return true;
        }

        if ($targetBatch !== 0 && ! in_array($targetBatch, $batches, true)) {
            $message = 'Target batch not found: ' . $targetBatch;

            if ($this->silent) {
                $this->pushMessage($message, 'red');

                return false;
            }

            throw new RuntimeException($message);
        }

        $tmpNamespace = $this->namespace;

        $this->namespace = null;
        $allMigrations   = $this->getMigrations();

        $migrations    = [];

        while ($batch = array_pop($batches)) {
            if ($batch <= $targetBatch) {
                break;
            }

            foreach ($this->getBatchHistory($batch, 'desc') as $history) {
                $uid = $this->getObjectUid($history);

                if (! isset($allMigrations[$uid])) {
                    $message = 'Il y a une lacune dans la séquence de migration près du numéro de version: ' . $history->version;

                    if ($this->silent) {
                        $this->pushMessage($message, 'red');

                        return false;
                    }

                    throw new RuntimeException($message);
                }

                $migration          = $allMigrations[$uid];
                $migration->history = $history;
                $migrations[]       = $migration;
            }
        }

        foreach ($migrations as $migration) {
            if ($this->migrate('down', $migration)) {
                $this->removeHistory($migration->history);
            } else {
                $message = 'Migration failed!';

                if ($this->silent) {
                    $this->pushMessage($message, 'red');

                    return false;
                }

                throw new RuntimeException($message);
            }
        }

        $data           = get_object_vars($this);
        $data['method'] = 'regress';
        $this->db->triggerEvent($data, 'migrate');

        $this->namespace = $tmpNamespace;

        return true;
    }

    /**
     * Migrer un seul fichier, quel que soit l'ordre ou les lots.
     * La méthode "up" ou "down" est déterminée par la présence dans l'historique.
     * REMARQUE : cette méthode n'est pas recommandée et est principalement fournie à des fins de test.
     *
     * @param string $path Chemin d'accès complet vers un fichier de migration valide.
     * @param string $path Espace de noms de la migration cible.
     */
    public function force(string $path, string $namespace, ?string $group = null)
    {
        if (! $this->enabled) {
            throw MigrationException::disabledMigrations();
        }

        $this->ensureTable();

        if ($group !== null) {
            $this->groupFilter = $group;
            $this->setGroup($group);
        }

        $migration = $this->migrationFromFile($path, $namespace);
        if (empty($migration)) {
            $message = 'Migration file not found: ' . $path;

            if ($this->silent) {
                $this->pushMessage($message, 'red');

                return false;
            }

            throw new RuntimeException($message);
        }

        $method = 'up';
        $this->setNamespace($migration->namespace);

        foreach ($this->getHistory($this->group) as $history) {
            if ($this->getObjectUid($history) === $migration->uid) {
                $method             = 'down';
                $migration->history = $history;
                break;
            }
        }

        if ($method === 'up') {
            $batch = $this->getLastBatch() + 1;

            if ($this->migrate('up', $migration) && $this->groupSkip === false) {
                $this->addHistory($migration, $batch);

                return true;
            }

            $this->groupSkip = false;
        } elseif ($this->migrate('down', $migration)) {
            $this->removeHistory($migration->history);

            return true;
        }

        $message = 'Migration failed!';

        if ($this->silent) {
            $this->pushMessage($message, 'red');

            return false;
        }

        throw new RuntimeException($message);
    }

    /**
     * Permet à d'autres scripts d'effectuer des modifications à la volée selon les besoins.
     */
    public function setNamespace(?string $namespace): self
    {
        $this->namespace = $namespace;

        return $this;
    }

    /**
     * Permet à d'autres scripts d'effectuer des modifications à la volée selon les besoins.
     */
    public function setGroup(string $group): self
    {
        $this->group = $group;

        return $this;
    }

    /**
     * Permet à d'autres scripts d'effectuer des modifications à la volée selon les besoins.
     */
    public function setFiles(array $files): self
    {
        $this->files = $files;

        return $this;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * Si $silent == true, alors aucune exception ne sera levée 
     * et le programme tentera de continuer normalement.
     */
    public function setSilent(bool $silent): self
    {
        $this->silent = $silent;

        return $this;
    }

    /**
     * Récupère les messages formatés pour la sortie CLI.
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * Recupere toutes les migrations
     */
    private function getMigrations(): array
    {
        $migrations = [];

        foreach ($this->files as $namespace => $files) {
            foreach ($this->findNamespaceMigrations($namespace, $files) as $migration) {
                $migrations[$migration->uid] = $migration;
            }
        }

        // Sort migrations ascending by their UID (version)
        ksort($migrations);

        return $migrations;
    }

    /**
     * Récupère la liste des scripts de migration disponibles pour un namespace.
     */
    public function findNamespaceMigrations(string $namespace, array $files): array
    {
        $migrations = [];

        foreach ($files as $file) {
            $file = empty($this->path) ? $file : $this->path . str_replace($this->path, '', $file);

            if ($migration = $this->migrationFromFile($file, $namespace)) {
                $migrations[] = $migration;
            }
        }

        return $migrations;
    }

    /**
     * Créer un objet de migration à partir d'un chemin d'accès à un fichier.
     *
     * @param string $path Chemin d'accès complet à un fichier de migration valide.
     *
     * @return false|object Renvoie l'objet de migration ou false en cas d'échec.
     */
    protected function migrationFromFile(string $path, string $namespace)
    {
        if (substr($path, -4) !== '.php') {
            return false;
        }

        // Retrait de l'extension
        $filename = basename($path, '.php');

        // Si le fichier ne match pas avec le format des migrations, pas la peine de continuer
        if (! preg_match($this->regex, $filename)) {
            return false;
        }

        $migration = new stdClass();

        $migration->version   = $this->getMigrationNumber($filename);
        $migration->name      = $this->getMigrationName($filename);
        $migration->path      = $path;
        $migration->class     = $this->getMigrationClass($path);
        $migration->namespace = $namespace;
        $migration->uid       = $this->getObjectUid($migration);

        return $migration;
    }

    /**
     * Extrait le numero de la migration a partir du nom du fichier.
     *
     * @return string Portion numerique du nom de fichier de la migration
     */
    protected function getMigrationNumber(string $filename): string
    {
        preg_match($this->regex, $filename, $matches);

        return count($matches) ? $matches[1] : '0';
    }

    /**
     * Extrait le nom de la classe de migration
     */
    protected function getMigrationClass(string $path): string
    {
        $php       = file_get_contents($path);
        $tokens    = token_get_all($php);
        $dlm       = false;
        $namespace = '';
        $className = '';

        foreach ($tokens as $i => $token) {
            if ($i < 2) {
                continue;
            }

            if ((isset($tokens[$i - 2][1]) && ($tokens[$i - 2][1] === 'phpnamespace' || $tokens[$i - 2][1] === 'namespace')) || ($dlm && $tokens[$i - 1][0] === T_NS_SEPARATOR && $token[0] === T_STRING)) {
                if (! $dlm) {
                    $namespace = 0;
                }
                if (isset($token[1])) {
                    $namespace = $namespace ? $namespace . '\\' . $token[1] : $token[1];
                    $dlm       = true;
                }
            } elseif ($dlm && ($token[0] !== T_NS_SEPARATOR) && ($token[0] !== T_STRING)) {
                $dlm = false;
            }

            if (($tokens[$i - 2][0] === T_CLASS || (isset($tokens[$i - 2][1]) && $tokens[$i - 2][1] === 'phpclass'))
                && $tokens[$i - 1][0] === T_WHITESPACE
                && $token[0] === T_STRING) {
                $className = $token[1];
                break;
            }
        }

        if (empty($className)) {
            return '';
        }

        return $namespace . '\\' . $className;
    }

    /**
     * Utilise les parties non reproductibles d'une migration ou d'un historique pour créer une clé unique triable.
     */
    public function getObjectUid(object $migration): string
    {
        return preg_replace('/[^0-9]/', '', $migration->version) . $migration->class;
    }

    /**
     * Extrait le nom de la migration d'un nom de fichier
     *
     * Remarque : Le nom de la migration doit être le nom de la classe, mais peut-être le sont-ils
     *       différent.
     *
     * @param string $migration Un nom de fichier de migration sans chemin.
     */
    protected function getMigrationName(string $migration): string
    {
        preg_match($this->regex, $migration, $matches);

        return count($matches) ? $matches[2] : '';
    }

    /**
     * Definit les messages CLI
     */
    private function pushMessage(string $message, string $color = 'green'): self
    {
        $this->messages[] = compact('message', 'color');

        return $this;
    }

    /**
     * Efface les messages CLI.
     */
    public function clearMessages(): self
    {
        $this->messages = [];

        return $this;
    }

    /**
     * Tronque la table d'historique.
     */
    public function clearHistory(): void
    {
        if ($this->db->tableExists($this->table)) {
            $this->db->table($this->table)->truncate();
        }
    }

    /**
     * Ajouter un historique à la table.
     */
    protected function addHistory(object $migration, int $batch): void
    {
        $this->db->table($this->table)->insert([
            'version'   => $migration->version,
            'class'     => $migration->class,
            'group'     => $this->group,
            'namespace' => $migration->namespace,
            'time'      => time(),
            'batch'     => $batch,
        ]);

        $this->pushMessage(
            sprintf(
                'Running: %s %s_%s',
                $migration->namespace,
                $migration->version,
                $migration->class
            ),
            'yellow'
        );
    }

    /**
     * Supprime un seul historique.
     */
    protected function removeHistory(object $history): void
    {
        $this->db->table($this->table)->where('id', $history->id)->delete();

        $this->pushMessage(
            sprintf(
                'Rolling back: %s %s_%s',
                $history->namespace,
                $history->version,
                $history->class
            ),
            'yellow'
        );
    }

    /**
     * Récupère l'historique complet des migrations de la base de données pour un groupe.
     */
    public function getHistory(string $group = 'default'): array
    {
        $this->ensureTable();

        $builder = $this->db->table($this->table);

        // Si un groupe a été spécifié, utilisez-le.
        if ($group !== '') {
            $builder->where('group', $group);
        }

        // Si un namespace a été spécifié, utilisez-le.
        if ($this->namespace !== null) {
            $builder->where('namespace', $this->namespace);
        }

        return $builder->sortAsc('id')->all();
    }

    /**
     * Renvoie l'historique des migrations pour un seul lot.
     */
    public function getBatchHistory(int $batch, string $order = 'asc'): array
    {
        $this->ensureTable();

        return $this->db->table($this->table)->where('batch', $batch)->orderBy('id', $order)->all();
    }

    /**
     * Renvoie tous les lots de l'historique de la base de données dans l'ordre.
     */
    public function getBatches(): array
    {
        $this->ensureTable();

        $batches = $this->db->table($this->table)
            ->select('batch')
            ->distinct()
            ->sortAsc('batch')
            ->all(PDO::FETCH_ASSOC);

        return array_map('intval', array_column($batches, 'batch'));
    }

    /**
     * Renvoie la valeur du dernier lot dans la base de données.
     */
    public function getLastBatch(): int
    {
        $this->ensureTable();

        return (int) $this->db->table($this->table)->max('batch');
    }

    /**
     * Renvoie le numéro de version de la première migration d'un lot.
     * Principalement utilisé à des fins de test.
     */
    public function getBatchStart(int $batch, int $targetBatch = 0): string
    {
        // Convertir un lot relatif en lot absolu
        if ($batch < 0) {
            $batches = $this->getBatches();
            $batch   = $batches[count($batches) - 1 + $targetBatch] ?? 0;
        }

        $migration = $this->db->table($this->table)->where('batch', $batch)->sortAsc('id')->first();

        return $migration->version ?? '0';
    }

    /**
     * Renvoie le numéro de version de la dernière migration d'un lot.
     * Principalement utilisé à des fins de test.
     */
    public function getBatchEnd(int $batch, int $targetBatch = 0): string
    {
        // Convertir un lot relatif en lot absolu
        if ($batch < 0) {
            $batches = $this->getBatches();
            $batch   = $batches[count($batches) - 1 + $targetBatch] ?? 0;
        }

        $migration = $this->db->table($this->table)->where('batch', $batch)->sortDesc('id')->first();

        return $migration->version ?? '0';
    }

    /**
     * S'assure que nous avons créé notre table de migrations dans la base de données.
     */
    public function ensureTable(): void
    {
        if ($this->tableChecked || $this->db->tableExists($this->table)) {
            return;
        }

        $structure = new Structure($this->table);
        $structure->bigIncrements('id');
        $structure->string('version');
        $structure->string('class');
        $structure->string('group');
        $structure->string('namespace');
        $structure->integer('time');
        $structure->integer('batch')->unsigned();
        $structure->create(true);

        $transformer = new Transformer($this->db);
        $transformer->process($structure);

        $this->tableChecked = true;
    }

    /**
     * Gère l'exécution effective d'une migration.
     *
     * @param string $direction "up" ou "down"
     * @param object $migration La migration à exécuter
     */
    protected function migrate(string $direction, object $migration): bool
    {
        include_once $migration->path;

        $class = $migration->class;
        $this->setName($migration->name);

        // Valider la structure du fichier de migration
        if (! class_exists($class, false)) {
            $message = sprintf('The migration class "%s" could not be found.', $class);

            if ($this->silent) {
                $this->pushMessage($message, 'red');

                return false;
            }

            throw new RuntimeException($message);
        }

        /** @var Migration $instance */
        $instance = new $class($this->db);
        $group    = $instance->getGroup() ?? $this->group;

        if ($direction === 'up' && $this->groupFilter !== null && $this->groupFilter !== $group) {
            $this->groupSkip = true;

            return true;
        }

        if (! is_callable([$instance, $direction])) {
            $message = sprintf('The migration class is missing an "%s" method.', $direction);

            if ($this->silent) {
                $this->pushMessage($message, 'red');

                return false;
            }

            throw new RuntimeException($message);
        }

        $instance->{$direction}();

        $transformer = new Transformer($this->db);

        foreach ($instance->getStructure() as $structure) {
            $transformer->process($structure);
        }

        return true;
    }
}
