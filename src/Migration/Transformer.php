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
use BlitzPHP\Database\Exceptions\MigrationException;
use BlitzPHP\Database\Migration\Definitions\Column;
use BlitzPHP\Database\Migration\Definitions\ForeignKey;
use BlitzPHP\Database\Migration\Definitions\Index;
use BlitzPHP\Database\Query\Expression;

/**
 * Transforme les objets de structure en elements compatible avec le Creator
 */
class Transformer
{
    public function __construct(private BaseCreator $creator)
    {
    }

    /**
     * Demarrage de la manipulation de la base de donnees
     */
    public function process(Builder $builder)
    {
        $table  = $builder->getTable();
        $action = $builder->getAction();

        $this->addFluentIndexes($builder);

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
        $primaryKeyColumns      = [];
        $autoIncrementColumns   = [];
        $explicitPrimaryColumns = [];

        foreach ($builder->getColumns() as $column) {
            $this->creator->addField([
                $column->name => $this->makeColumn($column),
            ]);

            if ($column->autoIncrement ?? false) {
                $autoIncrementColumns[] = $column->name;
            }

            if ($column->primary ?? false) {
                $explicitPrimaryColumns[] = $column->name;
            }
        }

        // Détermination de la clé primaire
        if ($explicitPrimaryColumns !== []) {
            // L'utilisateur a explicitement demandé une clé primaire
            $primaryKeyColumns = $explicitPrimaryColumns;
        } elseif (count($autoIncrementColumns) === 1) {
            // Une seule colonne auto_increment => c'est la clé primaire
            $primaryKeyColumns = $autoIncrementColumns;
        } elseif (count($autoIncrementColumns) > 1) {
            // Plusieurs auto_increment (cas rare) - on prévient
            trigger_error(
                'Plusieurs colonnes auto_increment détectées. Utilisez primary() pour spécifier la clé primaire.',
                E_USER_WARNING,
            );
        }

        if ($primaryKeyColumns !== []) {
            $this->creator->addPrimaryKey(
                $primaryKeyColumns,
                $builder->createIndexName('primary', $primaryKeyColumns),
            );
        }

        foreach ($builder->getIndexes() as $index) {
            $this->addIndex($index);
        }

        foreach ($builder->getForeignKeys() as $foreignKey) {
            $this->addForeignKey($foreignKey);
        }

        $attributes = [];

        if ('' !== $engine = $builder->getEngine()) {
            $attributes['ENGINE'] = $engine;
        }
        if ('' !== $charset = $builder->getCharset()) {
            $attributes['DEFAULT CHARACTER SET'] = $charset;
        }
        if ('' !== $collation = $builder->getCollation()) {
            $attributes['COLLATE'] = $collation;
        }

        $this->creator->createTable($builder->getTable(), $ifNotExists, $attributes);
    }

    /**
     * Modifie une table existante
     */
    protected function alterTable(Builder $builder): void
    {
        $table = $builder->getTable();

        // Colonnes ajoutées
        foreach ($builder->getAddedColumns() as $column) {
            $this->creator->addColumn($table, [$column->name => $this->makeColumn($column)]);
            $this->creator->processIndexes($table);
        }

        // Colonnes modifiées
        foreach ($builder->getChangedColumns() as $column) {
            $this->creator->modifyColumn($table, [$column->name => $this->makeColumn($column)]);
            $this->creator->processIndexes($table);
        }

        // Colonnes supprimées
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
     * Convertit les index fluides des colonnes en commandes explicites
     */
    protected function addFluentIndexes(Builder $builder): void
    {
        $existingIndexes = [];

        foreach ($builder->getIndexes() as $index) {
            $key                   = $index->type . ':' . implode(',', $index->columns);
            $existingIndexes[$key] = true;
        }

        foreach ($builder->getColumns() as $column) {
            // Ignorer les colonnes qui n'ont pas d'index
            if (! $column->hasFluentIndexes()) {
                continue;
            }

            foreach ($column->getFluentIndexes() as $indexType => $value) {
                // Gestion spéciale pour les index vectoriels (si supportés)
                $indexMethod = $indexType === 'index' && $column->type === 'vector'
                    ? 'vectorIndex'
                    : $indexType;

                // Créer une clé unique pour cet index potentiel
                $indexKey = $indexType . ':' . $column->name;

                // Vérifier si un index similaire existe déjà
                if (isset($existingIndexes[$indexKey])) {
                    // Ignorer car déjà ajouté explicitement
                    continue;
                }

                // Cas 1: $value === true (index avec nom auto-généré)
                if ($value === true) {
                    // Éviter la duplication de clé primaire pour auto-increment (MySQL)
                    if ($indexType === 'primary' && $column->autoIncrement && $this->creator->getConnection()->getDriver() === 'mysql') {
                        continue;
                    }

                    $builder->{$indexMethod}($column->name);
                    $existingIndexes[$indexKey] = true;
                }

                // Cas 2: $value === false (suppression d'index)
                elseif ($value === false && $column->change) {
                    $dropMethod = 'drop' . ucfirst($indexMethod);
                    $builder->{$dropMethod}([$column->name]);
                }

                // Cas 3: $value est une chaîne (nom d'index explicite)
                elseif (is_string($value)) {
                    $builder->{$indexMethod}($column->name, $value);
                    $existingIndexes[$indexKey] = true;
                }
            }

            // Nettoyer les attributs d'index pour éviter les doublons
            foreach (array_keys($column->getFluentIndexes()) as $indexType) {
                unset($column[$indexType]);
            }
        }
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
        $onDelete = match (true) {
            ($fk->cascadeOnDelete ?? null) === true  => 'cascade',
            ($fk->restrictOnDelete ?? null) === true => 'restrict',
            ($fk->nullOnDelete ?? null) === true     => 'set null',
            ($fk->noActionOnDelete ?? null) === true => 'no action',
            default                                  => $fk->onDelete ?? '',
        };
        $onUpdate = match (true) {
            ($fk->cascadeOnUpdate ?? null) === true  => 'cascade',
            ($fk->restrictOnUpdate ?? null) === true => 'restrict',
            ($fk->nullOnUpdate ?? null) === true     => 'set null',
            ($fk->noActionOnUpdate ?? null) === true => 'no action',
            default                                  => $fk->onUpdate ?? '',
        };

        $this->creator->addForeignKey(
            $fk->columns,
            $fk->on,
            $fk->references ?? $fk->columns,
            $onUpdate,
            $onDelete,
            $fk->name ?? '',
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

    /**
     * Fabrique un tableau contenant les definition d'un champs
     */
    private function makeColumn(Column $column): array
    {
        if (empty($column->name) || empty($column->type)) {
            throw new MigrationException('Nom ou type du champ non defini');
        }

        $attributes = [];
        $type       = $this->creator->typeOf($column->type);

        if (is_array($type)) {
            $attributes['type']       = $type[0];
            $attributes['constraint'] = $type[1] ?? null;
        } elseif (str_contains($type, '|')) {
            $parts              = explode('|', $type);
            $attributes['type'] = $parts[$column->primary === true ? 1 : 0];
        } elseif (str_contains($type, '{precision}')) {
            $attributes['type'] = str_replace('{precision}', $column->precision, $type);
        } else {
            $attributes['type'] = $type;
        }

        if (isset($column->length)) {
            $attributes['constraint'] = $column->length;
        } elseif (isset($column->allowed)) {
            $attributes['constraint'] = (array) $column->allowed;
        } elseif (isset($column->total) || isset($column->places)) {
            $attributes['constraint'] = ($column->total ?? 8) . ', ' . ($column->places ?? 2);
        } elseif (isset($column->precision)) {
            $attributes['constraint'] = $column->precision;
        }

        if (isset($column->nullable)) {
            $attributes['null'] = $column->nullable;
        }
        if ($column->unsigned === true) {
            $attributes['unsigned'] = true;
        }

        if ($column->useCurrent === true) {
            $attributes['default'] = new Expression('CURRENT_TIMESTAMP');
        } elseif (isset($column->default)) {
            $attributes['default'] = $column->type === 'boolean' ? (int) $column->default : $column->default;
        }

        if ($column->autoIncrement === true) {
            $attributes['auto_increment'] = true;
        }
        if (! empty($column->comment)) {
            $attributes['comment'] = addslashes($column->comment);
        }
        if (! empty($column->collation)) {
            $attributes['collate'] = '"' . htmlspecialchars($column->collation) . '"';
        }

        if (! empty($column->after)) {
            $attributes['after'] = $column->after;
        } elseif ($column->first === true) {
            $attributes['first'] = true;
        }

        return $attributes;
    }
}
