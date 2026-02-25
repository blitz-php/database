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

use BlitzPHP\Database\Commands\Seed as SeedCommand;
use BlitzPHP\Database\Connection\BaseConnection;
use BlitzPHP\Database\Exceptions\SeederException;

/**
 * Classe de base pour les seeders
 *
 * @inspired https://github.com/tebazil/db-seeder
 */
abstract class Seeder
{
    /**
     * Connexion à la base de données
     */
    protected BaseConnection $db;

    /**
     * Factory pour les configurations
     */
    protected Factory $factory;

    /**
     * Instance de la console
     */
    protected ?SeedCommand $command = null;

    /**
     * Tables à remplir
     *
     * @var array<string, Seed>
     */
    protected array $seeds = [];

    /**
     * Seeders appelés
     * 
     * @var list<class-string>
     */
    protected array $called = [];

    /**
     * Mode silencieux (pas de sortie)
     */
    protected bool $silent = false;

    /**
     * Langue pour Faker
     */
    protected string $locale = 'fr_FR';

    /**
     * Constructeur
     * 
     * @param BaseConnection $db Connexion à la base de données
     */
    public function __construct(BaseConnection $db)
    {
        $this->db = $db;

        $this->factory = new Factory($this->locale);
    }

    /**
     * Définit l'instance de commande
     */
    public function setCommand(SeedCommand $command): self
    {
        $this->command = $command;

        return $this;
    }

    /**
     * Définit le mode silencieux
     */
    public function setSilent(bool $silent): self
    {
        $this->silent = $silent;

        return $this;
    }

    /**
     * Définit la langue
     */
    public function setLocale(string $locale): self
    {
        $this->locale = $locale;
        $this->factory = new Factory($locale);
        
        return $this;
    }

    /**
     * Récupère la langue
     */
    public function getLocale(): string
    {
        return $this->locale;
    }

    /**
     * Récupère les seeders appelés
     * 
     * @return list<class-string>
     */
    public function getCalled(): array
    {
        return $this->called;
    }

    /**
     * Accès à la factory (pour la syntaxe $this->faker->...)
     */
    public function __get(string $name): mixed
    {
        if ($name === 'faker') {
            return $this->factory;
        }
        
        throw SeederException::propertyNotFound($name);
    }

    /**
     * Méthode principale à implémenter
     */
    abstract public function run(): void;

    /**
     * Définit une table à remplir
     */
    protected function table(string $table): Seed
    {
        if (!isset($this->seeds[$table])) {
            $this->seeds[$table] = new Seed($this->db, $table, $this->factory->faker);
        }

        return $this->seeds[$table];
    }

    /**
     * Appelle d'autres seeders
     */
    protected function call(array|string $seeders): self
    {
        foreach ((array) $seeders as $seeder) {
            $seeder = $this->resolve($seeder);
            $seeder->setSilent($this->silent)->run();
            $this->called[] = $seeder::class;
        }

        return $this;
    }

    /**
     * Résout un nom de seeder en instance
     */
    protected function resolve(string $class): self
    {
        if (!class_exists($class)) {
            throw SeederException::seederClassDoesNotExist($class);
        }
        
        return new $class($this->db);
    }

    /**
     * Exécute le seeder
     */
    public function execute(): void
    {
        $this->run();

        foreach ($this->seeds as $seed) {
            $seed->execute();
        }

        $this->seeds = [];
    }

    /**
     * Affiche un message si pas en mode silencieux
     */
    protected function output(string $message, string $type = 'info'): void
    {
        if ($this->silent) {
            return;
        }

        if ($this->command) {
            $this->command->{$type}($message);
        } else if(defined('STDOUT')) {
            fwrite(STDOUT, $message . PHP_EOL);
        } else {
            echo $message . PHP_EOL;
        }
    }
}
