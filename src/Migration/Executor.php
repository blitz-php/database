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

use BlitzPHP\Database\Creator\BaseCreator;
use BlitzPHP\Database\Migration\Definitions\Column;
use BlitzPHP\Database\Migration\Definitions\ForeignKey;
use BlitzPHP\Database\Migration\Definitions\Index;

/**
 * Exécuteur de migrations
 * 
 * Cette classe transforme les builders en actions sur la base de données
 * via le système Creator (basé sur CodeIgniter Forge).
 */
class Executor
{
    /**
     * Constructeur
     *
     * @param BaseCreator $creator Instance du Creator
     */
    public function __construct(protected BaseCreator $creator)
    {
    }

    /**
     * Exécute un builder
     */
    public function execute(Builder $builder): void
    {
        $action = $builder->getAction();
        $table = $builder->getTable();

        match ($action) {
            'create'            => $this->createTable($builder),
            'createIfNotExists' => $this->createTable($builder, true),
            'alter'             => $this->alterTable($builder),
            'drop'              => $this->creator->dropTable($table, false),
            'dropIfExists'      => $this->creator->dropTable($table, true),
            'rename'            => $this->creator->renameTable($table, $builder->getRenames()['to']),
            default             => null,
        };
    }

    /**
     * Crée une nouvelle table
     */
    protected function createTable(Builder $builder, bool $ifNotExists = false): void
    {
        // Ajout des colonnes
        foreach ($builder->getColumns() as $column) {
            $this->creator->addField([
                $column->name => $this->columnToArray($column)
            ]);
        }

        // Ajout des index
        foreach ($builder->getIndexes() as $index) {
            $this->addIndex($index);
        }

        // Ajout des clés étrangères
        foreach ($builder->getForeignKeys() as $foreignKey) {
            $this->addForeignKey($foreignKey);
        }

        // Création de la table
        $this->creator->createTable($builder->getTable(), $ifNotExists);
    }

    /**
     * Modifie une table existante
     */
    protected function alterTable(Builder $builder): void
    {
        $table = $builder->getTable();

        // Ajout des nouvelles colonnes
        foreach ($builder->getAddedColumns() as $column) {
            $this->creator->addColumn($table, [
                $column->name => $this->columnToArray($column)
            ]);
        }

        // Modification des colonnes existantes
        foreach ($builder->getChangedColumns() as $column) {
            $this->creator->modifyColumn($table, [
                $column->name => $this->columnToArray($column)
            ]);
        }

        // Suppressions
        foreach ($builder->getDrops() as $type => $items) {
            $this->processDrops($table, $type, $items);
        }

        // Nouveaux index
        foreach ($builder->getIndexes() as $index) {
            $this->addIndex($index);
            $this->creator->processIndexes($table);
        }

        // Nouvelles clés étrangères
        foreach ($builder->getForeignKeys() as $foreignKey) {
            $this->addForeignKey($foreignKey);
            $this->creator->processIndexes($table);
        }
    }

    /**
     * Convertit une colonne en tableau pour Creator
     */
    protected function columnToArray(Column $column): array
    {
        $attrs = $column->getAttributes();
        $result = ['type' => $attrs['type']];

        if (isset($attrs['length'])) {
            $result['constraint'] = $attrs['length'];
        }

        if (isset($attrs['total']) && isset($attrs['places'])) {
            $result['constraint'] = $attrs['total'] . ',' . $attrs['places'];
        }

        if (isset($attrs['precision'])) {
            $result['constraint'] = $attrs['precision'];
        }

        if (isset($attrs['nullable'])) {
            $result['null'] = $attrs['nullable'];
        }

        if (isset($attrs['default'])) {
            $result['default'] = $attrs['default'];
        }

        if (isset($attrs['unsigned'])) {
            $result['unsigned'] = $attrs['unsigned'];
        }

        if (isset($attrs['autoIncrement'])) {
            $result['auto_increment'] = true;
        }

        if (isset($attrs['comment'])) {
            $result['comment'] = $attrs['comment'];
        }

        if (isset($attrs['after'])) {
            $result['after'] = $attrs['after'];
        }

        if (isset($attrs['first'])) {
            $result['first'] = true;
        }

        return $result;
    }

    /**
     * Ajoute un index
     */
    protected function addIndex(Index $index): void
    {
        $type    = $index->type;
        $columns = $index->columns;
        $name    = $index->name ?? '';

        match ($type) {
            'primary' => $this->creator->addPrimaryKey($columns, $name),
            'unique'  => $this->creator->addUniqueKey($columns, $name),
            'index'   => $this->creator->addKey($columns, false, false, $name),
            default   => null,
        };
    }

    /**
     * Ajoute une clé étrangère
     */
    protected function addForeignKey(ForeignKey $fk): void
    {
        $this->creator->addForeignKey(
            $fk->columns,
            $fk->on,
            $fk->references ?? $fk->columns,
            $fk->onUpdate ?? '',
            $fk->onDelete ?? '',
            $fk->name ?? ''
        );
    }

    /**
     * Traite les suppressions
     */
    protected function processDrops(string $table, string $type, mixed $items): void
    {
        $items = (array) $items;

        foreach ($items as $item) {
            match ($type) {
                'columns' => $this->creator->dropColumn($table, $item),
                'primary' => $this->creator->dropPrimaryKey($table, $item),
                'unique', 'index' => $this->creator->dropKey($table, $item),
                'foreign' => $this->creator->dropForeignKey($table, $item),
                default   => null,
            };
        }
    }
}
