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

use BlitzPHP\Database\Connection\BaseConnection;
use BlitzPHP\Database\Exceptions\DatabaseException;
use InvalidArgumentException;

/**
 * Genere du faux contenu pour remplir une base de donnees.
 *
 * @credit <a href="https://github.com/tebazil/db-seeder">tebazil/db-seeder</a>
 */
abstract class Seeder
{
    /**
     * @var Table[] Liste des tables a remplir
     */
    private array $tables = [];

    /**
     * Générateur de contenu
     */
    private ?Generator $generator = null;

    /**
     * Liste des tables qui ont deja été remplies
     */
    private array $filledTablesNames = [];

    /**
     * @var string[] Liste des seeders executes
     */
    private array $seeded = [];

    /**
     * Langue à utiliser pour la génération des fake data via Faker
     */
    protected string $locale = '';

    /**
     * If true, will not display CLI messages.
     */
    protected bool $silent = false;

    public function __construct(protected BaseConnection $db)
    {
    }

    /**
     * Sets the silent treatment.
     */
    public function setSilent(bool $silent): self
    {
        $this->silent = $silent;

        return $this;
    }

    /**
     * Recupere la langue de generation de contenu
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * Modifie la langue de generation de contenu
     */
    public function setLocale(string $locale): self
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * Recupere la liste des sous seeder executes via la methode call()
     *
     * @return string[]
     */
    public function getSeeded(): array
    {
        return $this->seeded;
    }

    /**
     *  Lance la generation des donnees
     */
    public function execute(): string
    {
        $this->checkCrossDependentTables();

        $tableNames = array_keys($this->tables);
        sort($tableNames);

        $foolProofCounter       = 0;
        $tableNamesIntersection = [];

        while ($tableNamesIntersection !== $tableNames) {
            if ($foolProofCounter++ > 500) {
                throw new DatabaseException("Quelque chose d'inattendu s'est produit\u{a0}: certaines tables ne peuvent peut-être pas être remplies");
            }

            foreach ($this->tables as $tableName => $table) {
                if (! $table->isFilled() && $table->canBeFilled($this->filledTablesNames)) {
                    $table->fill();
                    $this->generator->setColumns($tableName, $table->getColumns());

                    if (! in_array($tableName, $this->filledTablesNames, true)) {
                        $this->filledTablesNames[] = $tableName;
                    }
                }
            }

            $tableNamesIntersection = array_intersect($this->filledTablesNames, $tableNames);
            sort($tableNamesIntersection);
        }

        return static::class;
    }

    /**
     * Specifie la table a remplir.
     */
    protected function table(string $name, bool $truncate = false): TableDef
    {
        if (! isset($this->tables[$name])) {
            $this->tables[$name] = new Table($this->generator(), $this->db->table($name), $truncate);
        }

        return new TableDef($this->tables[$name]);
    }

    /**
     * Loads the specified seeder and runs it.
     *
     * @throws InvalidArgumentException
     */
    protected function call(array|string $classes)
    {
        $classes = (array) $classes;

        foreach ($classes as $class) {
            $class = trim($class);

            if ($class === '') {
                throw new InvalidArgumentException('No seeder was specified.');
            }

            /** @var Seeder $seeder */
            $seeder = new $class($this->db);
            $seeder->setSilent($this->silent);

            if (method_exists($seeder, 'run')) {
                call_user_func([$seeder, 'run'], new Faker());
            }

            $this->seeded[] = $seeder->execute();

            unset($seeder);
        }
    }

    /**
     * Singleton pour avoir le générateur
     */
    private function generator(): Generator
    {
        if (null === $this->generator) {
            $this->generator = new Generator($this->locale);
        }

        return $this->generator;
    }

    /**
     * Verifie les dependences entres les tables
     */
    private function checkCrossDependentTables()
    {
        $dependencyMap = [];

        foreach ($this->tables as $tableName => $table) {
            $dependencyMap[$tableName] = $table->getDependsOn();
        }

        foreach ($dependencyMap as $tableName => $tableDependencies) {
            foreach ($tableDependencies as $dependencyTableName) {
                if (in_array($tableName, $dependencyMap[$dependencyTableName], true)) {
                    throw new InvalidArgumentException('Vous ne pouvez pas passer des tables qui dépendent les unes des autres');
                }
            }
        }
    }
}
