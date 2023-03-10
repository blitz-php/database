<?php

namespace BlitzPHP\Database\Migration;

use BlitzPHP\Contracts\Database\ConnectionInterface;
use BlitzPHP\Database\Connection\BaseConnection;
use BlitzPHP\Database\Database;
use BlitzPHP\Database\Exceptions\MigrationException;
use PDO;
use RuntimeException;

/**
 * Classe pour executer les migrations
 * 
 * @credit <a href="https://codeigniter.com">CodeIgniter4 - CodeIgniter\Database\MigrationRunner</a>
 */
class Runner
{
	/**
	 * Verifie si on s'est deja rassurer que la table existe ou pas=.
	 */
	protected bool $tableChecked = false;

	/**
	 * Utiliser pour sauter la migration courrante.
	 */
	protected bool $groupSkip = false;

	/**
     * Specifie si les migrations sont activees ou pas.
     */
    protected bool $enabled = false;

    /**
     * Nom de la table dans laquelle seront stockees les meta informations de migrations.
     */
    protected string $table;

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
    protected string $group;

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
     * Le filtre du groupe de la base de donnees.
     */
    protected ?string $groupFilter;

	/**
     * singleton
     */
    private static $_instance;


	/**
     * Constructor.
     *
     * When passing in $db, you may pass any of the following to connect:
     * - existing connection instance
     * - array of database configuration values
     */
    public function __construct(array $config, array|ConnectionInterface $db)
    {
		$this->enabled    = $config['enabled'] ?? false;
		$this->table      = $config['table'] ?? 'migrations';
		
		if ($db instanceof ConnectionInterface) {
			$this->db = $db;
		}
		else {
			$this->db = Database::connection($db, static::class);
		}
    }


	/**
     * singleton constructor
     */
    public static function instance(array $config, array|ConnectionInterface $db): self
    {
        if (null === self::$_instance) {
            self::$_instance = new self($config, $db);
        }

        return self::$_instance;
    }

	/**
	 * Locate and run all new migrations
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

        if (empty($migrations)) {
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
     * Migrate down to a previous batch
     *
     * Calls each migration step required to get to the provided batch
     *
     * @param int $targetBatch Target batch number, or negative for a relative batch, 0 for all
     *
     * @return mixed Current batch number on success, FALSE on failure or no migrations are found
     *
     * @throws MigrationException
     * @throws RuntimeException
     */
    public function regress(int $targetBatch = 0, ?string $group = null)
    {
		if (! $this->enabled) {
            throw MigrationException::disabledMigrations();
        }

        if ($group !== null) {
            $this->setGroup($group);
        }

        $this->ensureTable();

        $batches = $this->getBatches();

        if ($targetBatch < 0) {
            $targetBatch = $batches[count($batches) - 1 + $targetBatch] ?? 0;
        }

        if (empty($batches) && $targetBatch === 0) {
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

		
        $allMigrations    = $this->getMigrations();
        $migrations       = [];

		while ($batch = array_pop($batches)) {
            if ($batch <= $targetBatch) {
                break;
            }

            foreach ($this->getBatchHistory($batch, 'desc') as $history) {
                $uid = $this->getObjectUid($history);

                if (! isset($allMigrations[$uid])) {
                    $message = 'There is a gap in the migration sequence near version number: ' . $history->version;

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

        return true;
	}

	/**
     * Migrate a single file regardless of order or batches.
     * Method "up" or "down" determined by presence in history.
     * NOTE: This is not recommended and provided mostly for testing.
     *
     * @param string $path Full path to a valid migration file
     * @param string $path Namespace of the target migration
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
            $message = 'Migration file not found: '.$path;

            if ($this->silent) {
                $this->pushMessage($message, 'red');

                return false;
            }

            throw new RuntimeException($message);
        }

        $method = 'up';
        // $this->setNamespaces([$migration->namespace]);

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
	
	//--------------------------------------------------------------------

    
    /**
     * Allows other scripts to modify on the fly as needed.
     */
    public function setGroup(string $group): self
    {
        $this->group = $group;

        return $this;
    }

    /**
     * Allows other scripts to modify on the fly as needed.
     */
    public function setFiles(array $files): self
    {
        $this->files = $files;

        return $this;
    }
	
	/**
     */
    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * If $silent == true, then will not throw exceptions and will
     * attempt to continue gracefully.
     */
    public function setSilent(bool $silent): self
    {
        $this->silent = $silent;

        return $this;
    }

	/**
	 * Retrieves messages formatted for CLI output
	 *
	 * @return array    Current migration version
	 */
	public function getMessages(): array
	{
		return $this->messages;
	}
	
	//--------------------------------------------------------------------

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
     * Retrieves a list of available migration scripts for one namespace
     */
    public function findNamespaceMigrations(string $namespace, array $files): array
    {
        $migrations = [];
        
        foreach ($files as $file) {
            if ($migration = $this->migrationFromFile($file, $namespace)) {
                $migrations[] = $migration;
            }
        }

        return $migrations;
    }
    
    /**
	 * Create a migration object from a file path.
	 *
	 * @return object|false    Returns the migration object, or false on failure
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

		$migration = new \stdClass();

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
	 * @return string    Portion numerique du nom de fichier de la migration
	 */
	protected function getMigrationNumber(string $filename): string
	{
		preg_match($this->regex, $filename, $matches);

        return count($matches) ? $matches[1] : '0';
	}

	/**
	 * Extrait le nom de la classe de migration
	 */
	private function getMigrationClass(string $path): string
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
	 * Uses the non-repeatable portions of a migration or history
	 * to create a sortable unique key.
	 */
	private function getObjectUid(object $migration): string
	{
		return preg_replace('/[^0-9]/', '', $migration->version) . $migration->class;
	}

	/**
     * Extracts the migration name from a filename
     *
     * Note: The migration name should be the classname, but maybe they are
     *       different.
     *
     * @param string $migration A migration filename w/o path.
     */
    protected function getMigrationName(string $migration): string
    {
        preg_match($this->regex, $migration, $matches);

        return count($matches) ? $matches[2] : '';
    }

	//--------------------------------------------------------------------

	
	/**
	 * Set CLI messages
	 */
	private function pushMessage(string $message, string $color = 'green') : self
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

	//--------------------------------------------------------------------

	/**
	 * Truncates the history table.
	 *
	 * @return void
	 */
	public function clearHistory()
	{
		if ($this->db->tableExists($this->table)) {
			$this->db->table($this->table)->truncate();
		}
	}

	/**
	 * Add a history to the table.
	 *
	 * @return void
	 */
	protected function addHistory(object $migration, int $batch)
	{
		$this->db->table($this->table)->insert([
            'version'   => $migration->version,
            'class'     => $migration->class,
            'group'     => $this->group,
            'namespace' => $migration->namespace,
            'time'      => time(),
            'batch'     => $batch,
        ]);

		$this->pushMessage(sprintf(
			'Running: %s %s_%s',
			$migration->namespace,
			$migration->version,
			$migration->class), 
			'yellow'
		);
	}

	/**
	 * Removes a single history
	 *
	 * @return void
	 */
	protected function removeHistory(object $history)
	{
		$this->db->table($this->table)->where('id', $history->id)->delete();

		$this->pushMessage(sprintf(
			'Rolling back: %s %s_%s',
			$history->namespace,
			$history->version,
			$history->class), 
			'yellow'
		);
	}

	//--------------------------------------------------------------------

	/**
	 * Grabs the full migration history from the database for a group
	 */
	public function getHistory(): array
	{
		$this->ensureTable();

        $builder = $this->db->table($this->table);

        $namespaces = array_keys($this->files);

        if (count($namespaces) == 1) {
            $builder->where('namespace', $namespaces[0]);
        }

        return $builder->sortAsc('id')->all();
	}

	/**
	 * Returns the migration history for a single batch.
	 */
	public function getBatchHistory(int $batch, $order = 'asc'): array
	{
		$this->ensureTable();

		return $this->db->table($this->table)->where('batch', $batch)->orderBy('id', $order)->all();
	}

	//--------------------------------------------------------------------

	/**
	 * Returns all the batches from the database history in order.
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
	 * Returns the value of the last batch in the database.
	 */
	public function getLastBatch(): int
	{
		return (int) $this->db->table($this->table)->max('batch');
	}

	/**
	 * Returns the version number of the first migration for a batch.
	 * Mostly just for tests.
	 */
	public function getBatchStart(int $batch, int $targetBatch = 0): string
	{
		// Convert a relative batch to its absolute
		if ($batch < 0)
		{
			$batches = $this->getBatches();
			$batch   = $batches[count($batches) - 1 + $targetBatch] ?? 0;
		}

		$migration = $this->db->table($this->table)->where('batch', $batch)->sortAsc('id')->first();

		return $migration->version ?? '0';
	}

	/**
	 * Returns the version number of the last migration for a batch.
	 * Mostly just for tests.
	 */
	public function getBatchEnd(int $batch, int $targetBatch = 0): string
	{
		// Convert a relative batch to its absolute
		if ($batch < 0)
		{
			$batches = $this->getBatches();
			$batch   = $batches[count($batches) - 1 + $targetBatch] ?? 0;
		}

		$migration = $this->db->table($this->table)->where('batch', $batch)->sortDesc('id')->first();

		return $migration->version ?? '0';
	}

	//--------------------------------------------------------------------

	/**
	 * Ensures that we have created our migrations table in the database.
	 */
	public function ensureTable()
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
		$structure->create();

		$transformer = new Transformer($this->db);
		$transformer->process($structure);

        $this->tableChecked = true;
	}

	/**
	 * Handles the actual running of a migration.
	 *
	 * @param string $direction   "up" or "down"
	 */
	protected function migrate(string $direction, object $migration): bool
	{
		include_once $migration->path;

        $class = $migration->class;
        $this->setName($migration->name);

        // Validate the migration file structure
        if (! class_exists($class, false)) {
            $message = sprintf('The migration class "%s" could not be found.', $class);

            if ($this->silent) {
				$this->pushMessage($message, 'red');

                return false;
            }

            throw new RuntimeException($message);
        }

		// Initialize migration
		/**
		 * @var Migration $instance
		 */
		$instance = new $class();
		$group    = $instance->getGroup();

		if ($direction === 'up' && $this->groupFilter !== null && $this->groupFilter !== $group) {
            $this->groupSkip = true;

            return true;
        }

		// $this->setGroup($group);

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
