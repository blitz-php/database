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

use InvalidArgumentException;

/**
 * Migration
 *
 * Classe abstraite de gestion de migrations de base de donnees
 */
abstract class Migration
{
    /**
     * @var Structure[] Liste des taches
     */
    private array $structures = [];

    /**
     * Nom du group a utiliser pour lexecuter les migrations
     */
    protected string $group = 'default';

    /**
     * Definition des etapes d'execution d'une migration.
     */
    abstract public function up();

    /**
     * Definition des etapes d'annulation d'une migration.
     */
    abstract public function down();

    /**
     * Renvoi la liste des executions
     *
     * @return Structure[]
     *
     * @internal Utilisee par le `runner`
     */
    final public function getStructure(): array
    {
        return $this->structures;
    }

    /**
     * Renvoi le nom du groupe a utiliser pour la connexion a la base de donnees
     *
     * @internal Utilisee par le `runner`
     */
    final public function getGroup(): ?string
    {
        return $this->group;
    }

    /**
     * Cree une nouvelle table dans la structure.
     */
    final protected function create(string $table, bool|callable $ifNotExists, ?callable $callback = null): void
    {
        if (is_callable($ifNotExists)) {
            $callback    = $ifNotExists;
            $ifNotExists = false;
        } elseif ($callback === null) {
            throw new InvalidArgumentException('Si vous passez un booléen en second argument de la méthode create, le troisième doit être un callback');
        }

        $structure = $this->build($table, $callback);
        $structure->create($ifNotExists);

        $this->structures[] = $structure;
    }

    /**
     * Modifie une table de la structure.
     */
    final protected function modify(string $table, callable $callback): void
    {
        $structure = $this->build($table, $callback);
        $structure->modify();

        $this->structures[] = $structure;
    }

    /**
     * Supprime une table de la structure.
     */
    final protected function drop(string $table, bool $ifExists = false): void
    {
        $structure = $this->createStructure($table);
        $structure->drop($ifExists);

        $this->structures[] = $structure;
    }

    /**
     * Supprime une table de la structure si elle existe.
     */
    final protected function dropIfExists(string $table): void
    {
        $structure = $this->createStructure($table);
        $structure->dropIfExists();

        $this->structures[] = $structure;
    }

    /**
     * Renomme une table
     */
    final protected function rename(string $from, string $to): void
    {
        $structure = $this->createStructure($from);
        $structure->rename($to);

        $this->structures[] = $structure;
    }

    /**
     * Execute le callback avec la structure
     */
    private function build(string $table, callable $callback): Structure
    {
        return $callback($this->createStructure($table));
    }

    /**
     * Cree et renvoi une structure
     */
    private function createStructure(string $table): Structure
    {
        return new Structure($table);
    }
}
