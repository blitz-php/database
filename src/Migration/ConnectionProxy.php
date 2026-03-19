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
 * Proxy pour permettre les opérations sur différentes connexions
 *
 * Permet d'écrire : $this->connection('sqlite')->create('users', ...)
 */
class ConnectionProxy
{
    public function __construct(protected BaseConnection $connection, protected Migration $migration)
    {
    }

    /**
     * Crée une nouvelle table sur cette connexion
     */
    public function create(string $table, callable $callback, bool $ifNotExists = false): void
    {
        $this->migration->createOnConnection(
            $this->getConnectionName(),
            $table,
            $callback,
            $ifNotExists,
        );
    }

    /**
     * Crée une nouvelle table si elle n'existe pas
     */
    public function createIfNotExists(string $table, callable $callback): void
    {
        $this->create($table, $callback, true);
    }

    /**
     * Modifie une table existante sur cette connexion
     */
    public function alter(string $table, callable $callback): void
    {
        $this->migration->alterOnConnection(
            $this->getConnectionName(),
            $table,
            $callback,
        );
    }

    /**
     * Alias de alter()
     */
    public function modify(string $table, callable $callback): void
    {
        $this->alter($table, $callback);
    }

    /**
     * Supprime une table sur cette connexion
     */
    public function drop(string $table, bool $ifExists = false): void
    {
        $this->migration->dropOnConnection(
            $this->getConnectionName(),
            $table,
            $ifExists,
        );
    }

    /**
     * Supprime une table si elle existe
     */
    public function dropIfExists(string $table): void
    {
        $this->drop($table, true);
    }

    /**
     * Renomme une table sur cette connexion
     */
    public function rename(string $from, string $to): void
    {
        $this->migration->renameOnConnection(
            $this->getConnectionName(),
            $from,
            $to,
        );
    }

    /**
     * Vérifie si une table existe
     */
    public function hasTable(string $name): bool
    {
        return $this->migration->hasTableOnConnection(
            $this->getConnectionName(),
            $name,
        );
    }

    /**
     * Vérifie si un champ existe dans une table
     */
    public function hasColumn(string $table, string $column): bool
    {
        return $this->migration->hasColumnOnConnection(
            $this->getConnectionName(),
            $table,
            $column,
        );
    }

    /**
     * Retourne le nom de la connexion (pour usage interne)
     */
    protected function getConnectionName(): string
    {
        // Trouver le nom de la connexion à partir des connections enregistrées
        foreach ($this->migration->getConnections() as $name => $conn) {
            if ($conn === $this->connection) {
                return $name;
            }
        }

        return 'unknown';
    }
}
