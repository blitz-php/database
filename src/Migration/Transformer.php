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
use BlitzPHP\Database\Creator\BaseCreator;
use BlitzPHP\Database\Database;
use BlitzPHP\Database\Exceptions\MigrationException;
use BlitzPHP\Database\Migration\Definitions\Column;
use BlitzPHP\Database\RawSql;
use BlitzPHP\Utilities\Helpers;
use BlitzPHP\Utilities\Support\Fluent;

/**
 * Transforme les objets de structure en elements compatible avec le Creator
 */
class Transformer
{
    private BaseCreator $creator;

    public function __construct(ConnectionInterface $db)
    {
        $this->creator = Database::creator($db);
    }

    /**
     * Demarrage de la manipulation de la base de donnees
     */
    public function process(Structure $structure)
    {
        $commands = $this->getCommands($structure);

        $commandsName = array_map(static fn ($command) => $command->name, $commands);

        if (in_array('create', $commandsName, true)) {
            $this->createTable($structure, $commands);
        } elseif (in_array('modify', $commandsName, true)) {
            $this->modifyTable($structure, $commands);
        } elseif (in_array('rename', $commandsName, true)) {
            $command = array_filter($commands, static fn ($command) => $command->name === 'rename');

            $this->renameTable($structure->getTable(), $command[0]->to);
        } elseif (in_array('drop', $commandsName, true)) {
            $this->dropTable($structure->getTable());
        } elseif (in_array('dropIfExists', $commandsName, true)) {
            $this->dropTable($structure->getTable(), true);
        }
    }

    /**
     * Creation d'une nouvelle table
     */
    public function createTable(Structure $structure, array $commands = []): void
    {
        $ifNotExists = array_filter($commands, fn ($command) => $command->name === 'create' && $this->is($command, 'ifNotExists'));

        foreach ($this->getColumns($structure, true) as $column) {
            $this->creator->addField([$column->name => $this->makeColumn($column)]);
            $this->processKeys($column);
        }

        foreach ($commands as $command) {
            $this->processCommand($command);
        }

        $attributes = [];

        if ($structure->engine !== '') {
            $attributes['ENGINE'] = $structure->engine;
        }
        if ($structure->charset !== '') {
            $attributes['DEFAULT CHARACTER SET'] = $structure->charset;
        }
        if ($structure->collation !== '') {
            $attributes['COLLATE'] = $structure->collation;
        }

        $this->creator->createTable($structure->getTable(), $ifNotExists !== [], $attributes);
    }

    /**
     * Modification d'une table
     */
    public function modifyTable(Structure $structure, array $commands = []): void
    {
        $table = $structure->getTable();

        foreach ($this->getColumns($structure, true) as $column) {
            $this->creator->addColumn($table, [$column->name => $this->makeColumn($column)]);
            $this->processKeys($column);
            $this->creator->processIndexes($table);
        }
        
        foreach ($this->getColumns($structure, false) as $column) {
            $this->creator->modifyColumn($table, [$column->name => $this->makeColumn($column)]);
            $this->processKeys($column);
            $this->creator->processIndexes($table);
        }

        foreach ($commands as $command) {
            if ($command->name === 'dropColumn') {
                $this->creator->dropColumn($table, $command->columns);
            } elseif ($command->name === 'renameColumn') {
                $this->creator->renameColumn($table, $command->from, $command->to);
            } elseif ($command->name === 'dropIndex') {
                $this->creator->dropKey($table, $command->columns);
            } elseif ($command->name === 'dropUnique') {
                $this->creator->dropKey($table, $command->index);
            } elseif ($command->name === 'dropForeign') {
                $this->creator->dropForeignKey($table, $command->index);
            } elseif ($command->name === 'dropPrimary') {
                $this->creator->dropPrimaryKey($table, $command->index);
            }

            if ($this->processCommand($command)) {
                $this->creator->processIndexes($table);
            }
        }
    }

    /**
     * Suppression d'une table
     */
    public function dropTable(string $table, bool $ifExists = true): void
    {
        $this->creator->dropTable($table, $ifExists);
    }

    /**
     * Renommage d'une table
     */
    public function renameTable(string $table, string $to): void
    {
        $this->creator->renameTable($table, $to);
    }

    /**
     * Traite les clés d'une colonne donnée.
     *
     * Cette fonction vérifie si la colonne est une clé primaire, une clé unique ou un index,
     * et ajoute la clé appropriée au créateur.
     */
    private function processKeys(object $column): void
    {
        if ($this->is($column, 'primary')) {
            $this->creator->addPrimaryKey($column->name);
        } elseif ($this->is($column, 'unique')) {
            $this->creator->addUniqueKey($column->name);
        } elseif ($this->is($column, 'index')) {
            $this->creator->addKey($column->name);
        }
    }

    /**
     * Traiter une commande de modification de table.
     *
     * Cette fonction traite différents types de commandes de modification d'une table de base de données, notamment l'ajout de clés primaires, de clés uniques, d'index et de clés étrangères,
     * y compris l'ajout de clés primaires, de clés uniques, d'index et de clés étrangères.
     *
     * @param object $command Objet de commande contenant les détails de la modification à effectuer.
     *                        Propriétés attendues :
     *                        - name: string (Le type de commande : 'primary', 'unique', 'index', ou 'foreign')
     *                        - columns: string|array (colonne(s) affectée(s) par la commande)
     *                        - index: string|null (le nom de l'index, le cas échéant)
     *                        Pour les commandes de clés étrangères :
     *                        - on: string (La table référencée)
     *                        - references: string (La colonne référencée)
     *                        - cascadeOnDelete, restrictOnDelete, nullOnDelete, noActionOnDelete: bool
     *                        - cascadeOnUpdate, restrictOnUpdate, nullOnUpdate, noActionOnUpdate: bool
     *                        - onDelete, onUpdate: string (actions `ON DELETE` et `ON UPDATE` personnalisées)
     *
     * @return bool Retourne true si une commande a été traitée, false sinon.
     */ 
    private function processCommand($command): bool
    {
        $process = false;

        if ($command->name === 'primary') {
            $this->creator->addPrimaryKey($command->columns, $command->index);
            $process = true;
        } elseif ($command->name === 'unique') {
            $this->creator->addUniqueKey($command->columns, $command->index);
            $process = true;
        } elseif ($command->name === 'index') {
            $this->creator->addKey($command->columns, false, false, $command->index);
            $process = true;
        } elseif ($command->name === 'foreign') {
            $onDelete = match (true) {
                ($command->cascadeOnDelete ?? null) === true  => 'cascade',
                ($command->restrictOnDelete ?? null) === true => 'restrict',
                ($command->nullOnDelete ?? null) === true     => 'set null',
                ($command->noActionOnDelete ?? null) === true => 'no action',
                default                                       => $command->onDelete ?? ''
            };
            $onUpdate = match (true) {
                ($command->cascadeOnUpdate ?? null) === true  => 'cascade',
                ($command->restrictOnUpdate ?? null) === true => 'restrict',
                ($command->nullOnUpdate ?? null) === true     => 'set null',
                ($command->noActionOnUpdate ?? null) === true => 'no action',
                default                                       => $command->onUpdate ?? ''
            };
            $this->creator->addForeignKey(
                $command->columns ?? '',
                $command->on ?? '',
                $command->references ?? '',
                $onUpdate,
                $onDelete,
                $command->index ?? ''
            );
            $process = true;
        }

        return $process;
    }

    /**
     * Recupere les colonnes a prendre en compte.
     */
    private function getColumns(Structure $structure, ?bool $added = null): array
    {
        $columns = Helpers::collect($structure->getColumns($added))->map(static fn (Column $column) => $column->getAttributes())->all();

        return array_map(static fn ($column) => (object) $column, $columns);
    }

    /**
     * Recupere les commandes a executer.
     */
    private function getCommands(Structure $structure): array
    {
        $commands = Helpers::collect($structure->getCommands())->map(static fn (Fluent $command) => $command->getAttributes())->all();

        return array_map(static fn ($command) => (object) $command, $commands);
    }

    /**
     * Fabrique un tableau contenant les definition d'un champs
     */
    private function makeColumn(object $column): array
    {
        if (empty($column->name) || empty($column->type)) {
            throw new MigrationException('Nom ou type du champ non defini');
        }

        $definition = [];

        $definition['type'] = $this->creator->typeOf($column->type);

        if (is_array($definition['type'])) {
            if (isset($definition['type'][1])) {
                $definition['constraint'] = $definition['type'][1];
            }
            $definition['type'] = $definition['type'][0];
        }
        if (str_contains($definition['type'], '|')) {
            $parts              = explode('|', $definition['type']);
            $definition['type'] = $parts[(int) $this->is($column, 'primary')];
        }
        if (str_contains($definition['type'], '{precision}')) {
            $definition['type'] = str_replace('{precision}', $column->precision, $definition['type']);
        }

        if (property_exists($column, 'nullable')) {
            $definition['null'] = $column->nullable;
        }
        if ($this->is($column, 'unsigned')) {
            $definition['unsigned'] = true;
        }
        if ($this->is($column, 'useCurrent')) {
            $definition['default'] = new RawSql('CURRENT_TIMESTAMP');
        } elseif (property_exists($column, 'default')) {
            $definition['default'] = $column->type === 'boolean' ? (int) $column->default : $column->default;
        }
        if ($this->isInteger($column) && $this->is($column, 'autoIncrement')) {
            $definition['auto_increment'] = true;
        }
        if (! empty($column->comment)) {
            $definition['comment'] = addslashes($column->comment);
        }
        if (! empty($column->collation)) {
            $definition['collate'] = '"' . htmlspecialchars($column->collation) . '"';
        }
        if (! empty($column->after)) {
            $definition['after'] = $column->after;
        } elseif ($this->is($column, 'first')) {
            $definition['first'] = true;
        }

        if (isset($column->length)) {
            $definition['constraint'] = $column->length;
        } elseif (isset($column->allowed)) {
            $definition['constraint'] = (array) $column->allowed;
        } elseif (isset($column->total) || isset($column->places)) {
            $definition['constraint'] = ($column->total ?? 8) . ', ' . ($column->places ?? 2);
        } elseif (isset($column->precision)) {
            $definition['constraint'] = $column->precision;
        }

        return $definition;
    }

    /**
     * Verifie si le champ a une certaine propriete particuliere
     *
     * Par exemple, on  peut tester si un champ doit etre null en mettant is('nullable')
     */
    private function is(object $column, string $property, mixed $match = true): bool
    {
        return property_exists($column, $property) && $column->{$property} === $match;
    }

    /**
     * Verifie si le champ est de type integer.
     */
    private function isInteger(object $column): bool
    {
        return in_array($column->type, ['integer', 'int', 'bigInteger', 'mediumInteger', 'smallInteger', 'tinyInteger'], true);
    }
}
