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

/**
 * Classe de base pour les migrations de base de données
 * 
 * Cette classe doit être étendue par toutes les classes de migration.
 * Elle fournit une API fluide pour définir les modifications de schéma.
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
     * Groupe de connexion à utiliser pour cette migration
     */
    protected ?string $group = null;

    /**
     * Constructeur
     *
     * @param BaseConnection $db Connexion à la base de données
     */
    public function __construct(protected BaseConnection $db)
    {
    }

    /**
     * Méthode appelée lors de l'application de la migration
     */
    abstract public function up(): void;

    /**
     * Méthode appelée lors de l'annulation de la migration
     */
    abstract public function down(): void;

    /**
     * Récupère les builders de tables
     *
     * @return list<Builder>
     * 
     * @internal Utilisé par le Runner
     */
    final public function getBuilders(): array
    {
        return $this->builders;
    }

    /**
     * Récupère le groupe de connexion
     *
     * @internal Utilisé par le Runner
     */
    final public function getGroup(): ?string
    {
        return $this->group;
    }

    /**
     * Crée une nouvelle table
     */
    final protected function create(string $table, callable $callback, bool $ifNotExists = false): void
    {
        $builder = new Builder($table);
        $callback($builder);
        $builder->createTable($ifNotExists);

        $this->builders[] = $builder;
    }

    /**
     * Crée une nouvelle table si elle n'existe pas
     */
    final protected function createIfNotExists(string $table, callable $callback): void
    {
        $this->create($table, $callback, true);
    }

    /**
     * Modifie une table existante
     */
    final protected function alter(string $table, callable $callback): void
    {
        $builder = new Builder($table);
        $callback($builder);
        $builder->alterTable();

        $this->builders[] = $builder;
    }

    /**
     * Modifie une table existante
     *
     * @deprecated 1.0.0 use self::alter() instead
     */
    public function modify(string $table, callable $callback): void
    {
        $this->alter($table, $callback);
    }

    /**
     * Supprime une table
     */
    final protected function drop(string $table, bool $ifExists = false): void
    {
        $builder = new Builder($table);
        $builder->dropTable($ifExists);
        
        $this->builders[] = $builder;
    }

    /**
     * Supprime une table si elle existe
     */
    final protected function dropIfExists(string $table): void
    {
        $this->drop($table, true);
    }

    /**
     * Renomme une table
     */
    final protected function rename(string $from, string $to): void
    {
        $builder = new Builder($from);
        $builder->renameTable($to);
        
        $this->builders[] = $builder;
    }
}
