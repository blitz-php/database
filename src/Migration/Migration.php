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

use BlitzPHP\Database\Config\Services;
use BlitzPHP\Database\Connection\BaseConnection;
use BlitzPHP\Database\DatabaseManager;

/**
 * Classe de base pour les migrations de base de données
 */
abstract class Migration
{
    /**
     * Liste des builders de tables
     *
     * @var list<Builder>
     */
    protected array $builders = [];

    /**
     * Connexion par défaut
     */
    protected BaseConnection $db;

    /**
     * Gestionnaire de base de données
     */
    protected DatabaseManager $dbManager;

    /**
     * Connexions alternatives pour cette migration
     *
     * @var array<string, BaseConnection>
     */
    protected array $connections = [];

    /**
     * Méthode appelée lors de l'application de la migration
     */
    abstract public function up(): void;

    /**
     * Méthode appelée lors de l'annulation de la migration
     */
    abstract public function down(): void;

    /**
     * Détermine si cette migration doit être exécutée
     *
     * Peut être surchargée pour des conditions complexes
     */
    public function shouldRun(): bool
    {
        return true;
    }

    /**
     * Défini le groupe de connextion à utiliser pour la migration
     */
    protected function useConnection(): string
    {
        return 'default';
    }

    /**
     * Initialise les éléments nécessaire pour le fonctionnement de la migration
     *
     * @internal Utilisé par le Runner pour injecter la connexion et le gestionnaire de bd
     */
    public function initialize(DatabaseManager $dbManager, BaseConnection $db): self
    {
        $this->db                                  = $db;
        $this->dbManager                           = $dbManager;
        $this->connections[$this->useConnection()] = $db;

        return $this;
    }

    /**
     * Récupère les builders de tables
     *
     * @return list<Builder>
     *
     * @internal Utilisé par le Runner
     */
    public function getBuilders(): array
    {
        return $this->builders;
    }

    /**
     * Récupère les connexions utilisées par cette migration
     *
     * @return array<string, BaseConnection>
     *
     * @internal Utilisé par le ConnectionProxy
     */
    public function getConnections(): array
    {
        return $this->connections;
    }

    /**
     * Crée une nouvelle table sur une connexion spécifique
     *
     * @internal Utilisé par le ConnectionProxy
     */
    public function createOnConnection(string $connection, string $table, callable $callback, bool $ifNotExists = false): void
    {
        $builder = $this->makeBuilderFor($connection, $table);
        $callback($builder);
        $builder->createTable($ifNotExists);

        $this->builders[] = $builder;
    }

    /**
     * Modifie une table existante sur une connexion spécifique
     *
     * @internal Utilisé par le ConnectionProxy
     */
    public function alterOnConnection(string $connection, string $table, callable $callback): void
    {
        $builder = $this->makeBuilderFor($connection, $table);
        $callback($builder);
        $builder->alterTable();

        $this->builders[] = $builder;
    }

    /**
     * Supprime une table existante sur une connexion spécifique
     *
     * @internal Utilisé par le ConnectionProxy
     */
    public function dropOnConnection(string $connection, string $table, bool $ifExists): void
    {
        $builder = $this->makeBuilderFor($connection, $table);
        $builder->dropTable($ifExists);

        $this->builders[] = $builder;
    }

    /**
     * renomme une table existante sur une connexion spécifique
     *
     * @internal Utilisé par le ConnectionProxy
     */
    public function renameOnConnection(string $connection, string $from, string $to): void
    {
        $builder = $this->makeBuilderFor($connection, $from);
        $builder->renameTable($to);

        $this->builders[] = $builder;
    }

    /**
     * Vérifie si une table existe sur une connexion spécifique
     *
     * @internal Utilisé par le ConnectionProxy
     */
    public function hasTableOnConnection(string $connection, string $table): bool
    {
        return ($this->connections[$connection] ?? $this->db)->tableExists($table);
    }

    /**
     * Vérifie si un champ existe dans une table sur une connexion spécifique
     *
     * @internal Utilisé par le ConnectionProxy
     */
    public function hasColumnOnConnection(string $connection, string $table, string $column): bool
    {
        return ($this->connections[$connection] ?? $this->db)->columnExists($column, $table);
    }

    /**
     * Sélectionne une connexion alternative pour la suite des opérations
     */
    protected function connection(string $name): ConnectionProxy
    {
        if (! isset($this->connections[$name])) {
            // Résoudre la connexion via le DatabaseManager
            $this->connections[$name] = Services::dbManager()->connect($name);
        }

        return new ConnectionProxy($this->connections[$name], $this);
    }

    /**
     * Crée une nouvelle table sur la connexion courante
     */
    protected function create(string $table, callable $callback, bool $ifNotExists = false): void
    {
        $this->createOnConnection($this->useConnection(), $table, $callback, $ifNotExists);
    }

    /**
     * Crée une nouvelle table si elle n'existe pas
     */
    protected function createIfNotExists(string $table, callable $callback): void
    {
        $this->create($table, $callback, true);
    }

    /**
     * Modifie une table existante sur la connexion courante
     */
    protected function alter(string $table, callable $callback): void
    {
        $this->alterOnConnection($this->useConnection(), $table, $callback);
    }

    /**
     * Alias de alter() pour compatibilité
     */
    public function modify(string $table, callable $callback): void
    {
        $this->alter($table, $callback);
    }

    /**
     * Supprime une table
     */
    protected function drop(string $table, bool $ifExists = false): void
    {
        $this->dropOnConnection($this->useConnection(), $table, $ifExists);
    }

    /**
     * Supprime une table si elle existe
     */
    protected function dropIfExists(string $table): void
    {
        $this->drop($table, true);
    }

    /**
     * Renomme une table
     */
    protected function rename(string $from, string $to): void
    {
        $this->renameOnConnection($this->useConnection(), $from, $to);
    }

    /**
     * Vérifie si une table existe
     */
    protected function hasTable(string $name): bool
    {
        return $this->hasTableOnConnection($this->useConnection(), $name);
    }

    /**
     * Vérifie si un champ existe dans une table
     */
    protected function hasColumn(string $table, string $column): bool
    {
        return $this->hasColumnOnConnection($this->useConnection(), $table, $column);
    }

    /**
     * Crée un builder pour une connexion et une table spécifiques
     */
    private function makeBuilderFor(string $connection, string $table): Builder
    {
        $builder = new Builder($table);
        $builder->setConnection($this->connections[$connection] ?? $this->db);

        return $builder;
    }
}
