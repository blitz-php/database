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

use BlitzPHP\Database\Builder\BaseBuilder;
use InvalidArgumentException;

/**
 * @credit <a href="https://github.com/tebazil/db-seeder">tebazil/db-seeder</a>
 */
class Table
{
    public const DEFAULT_ROW_QUANTITY = 30;

    /**
     * Nom de la table courrante
     */
    private string $name;

    /**
     * Nom des champs dans lesquels les donnees seront inserees
     */
    private array $columns = [];

    /**
     * Quantite de donnees a remplir lors de l'execution
     */
    private ?int $rowQuantity = null;

    /**
     * Donnees a remplir lors de l'execution
     */
    private array $rows = [];

    /**
     * Donnees brutes a remplir (specifiees par l'utilisateur)
     */
    private array $rawData = [];

    /**
     * Drapeau specifiant que la table a ete remplie
     */
    private bool $isFilled = false;

    /**
     * Drapeau specifiant que la table a ete partiellement remplie
     */
    private bool $isPartiallyFilled = false;

    /**
     * Liste des tables dont depend cette table
     */
    private array $dependsOn = [];

    private array $selfDependentColumns = [];
    private array $columnConfig         = [];

    /**
     * constructor
     */
    public function __construct(private Generator $generator, private BaseBuilder $builder, private bool $truncateTable)
    {
        $this->name = $builder->getTable();
    }

    /**
     * Definit les colonnes où les insertions se passeront
     */
    public function setColumns(array $columns): self
    {
        $columnNames = array_keys($columns);

        foreach ($columnNames as $columnName) {
            $this->columns[$columnName] = [];
        }

        $this->columnConfig = $columns;

        $this->calcDependsOn();
        $this->calcSelfDependentColumns();

        return $this;
    }

    /**
     * Definit le nombre d'element a generer
     */
    public function setRowQuantity(int $rows = self::DEFAULT_ROW_QUANTITY): self
    {
        $this->rowQuantity = $rows;

        return $this;
    }

    /**
     * Defini les donnees brutes a inserer
     */
    public function setRawData(array $rawData, array $columnNames = []): self
    {
        if ($rawData === []) {
            throw new InvalidArgumentException('$rawData cannot be empty array');
        }
        if (! is_array($firstRow = reset($rawData))) {
            throw new InvalidArgumentException('$rawData should be an array of arrays (2d array)');
        }
        if (is_numeric(key($firstRow)) && ! $columnNames) {
            throw new InvalidArgumentException('Either provide $rawData line arrays with corresponding column name keys, or provide column names in $columnNames');
        }

        $this->rawData      = $rawData;
        $columnNames        = $columnNames ?: array_keys(reset($this->rawData));
        $this->columnConfig = [];                                                 // just in case

        foreach ($columnNames as $columnName) {
            if ($columnName) {
                $this->columns[$columnName] = []; // we skip false columns and empty columns
            }

            $this->columnConfig[] = $columnName;
        }

        return $this;
    }

    /**
     * Remplissage des donnees
     */
    public function fill(bool $writeDatabase = true)
    {
        [] === $this->rawData
            ? $this->fillFromGenerators($this->columnConfig)
            : $this->fillFromRawData($this->columnConfig, $this->rawData);

        if ($this->selfDependentColumns) {
            if ($this->isPartiallyFilled) {
                $this->isFilled = true; // second run
            } else {
                $this->isPartiallyFilled = true; // first run
            }
        } else {
            $this->isFilled = true; // no self-dependent columns
        }

        if ($this->isFilled && $writeDatabase) {
            $this->insertData();
        }
    }

    /**
     * Véerifie si la table a déjà étée chargée
     */
    public function isFilled(): bool
    {
        return $this->isFilled;
    }

    /**
     * Verifie si la table peut etre chargee
     */
    public function canBeFilled(array $filledTableNames): bool
    {
        $intersection = array_intersect($filledTableNames, $this->dependsOn);
        sort($intersection);

        return $intersection === $this->dependsOn;
    }

    /**
     * Recuperes les valeurs des champs a inserer dans la table
     */
    public function getRows(): array
    {
        return $this->rows;
    }

    /**
     * Liste des colonnes de la table dans lesquelles on fera les insertions
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * Liste des tables dont depend cette table
     */
    public function getDependsOn(): array
    {
        return $this->dependsOn;
    }

    /**
     * Ajoute les donnees de generation a partir des donnees brutes renseignees par l'utilisateur.
     */
    private function fillFromRawData(array $columnConfig, array $data)
    {
        $sizeofColumns = count($columnConfig);
        $data          = array_values($data);
        $sizeofData    = count($data);

        for ($rowNo = 0; $rowNo < ($this->rowQuantity ?? $sizeofData); $rowNo++) {
            $dataKey = ($rowNo < $sizeofData) ? $rowNo : ($rowNo % $sizeofData);
            $rowData = array_values($data[$dataKey]);

            for ($i = 0; $i < $sizeofColumns; $i++) {
                if (! $columnConfig[$i]) {
                    continue;
                }

                $this->rows[$rowNo][$columnConfig[$i]]    = $rowData[$i];
                $this->columns[$columnConfig[$i]][$rowNo] = $rowData[$i];
            }
        }
    }

    /**
     * Ajoute les donnees de generation a partir du generateur (via Faker si necessaire).
     */
    private function fillFromGenerators(array $columnConfig)
    {
        $this->generator->reset();

        for ($rowNo = 0; $rowNo < $this->rowQuantity ?? self::DEFAULT_ROW_QUANTITY; $rowNo++) {
            foreach ($columnConfig as $column => $config) {
                // first and second run separation
                if ($this->selfDependentColumns) {
                    $columnIsSelfDependent = in_array($column, $this->selfDependentColumns, true);
                    if (! $this->isPartiallyFilled) {
                        if ($columnIsSelfDependent) {
                            continue;
                        }
                    } elseif (! $columnIsSelfDependent) {
                        continue;
                    }
                }

                $value = $this->generator->getValue($config);

                $this->rows[$rowNo][$column]    = $value;
                $this->columns[$column][$rowNo] = $value;
            }
        }
    }

    private function calcDependsOn()
    {
        if ($this->rawData) {
            return false;
        }

        foreach ($this->columnConfig as $name => $config) {
            if (! is_callable($config)) {
                if (is_array($config) && ($config[0] === Generator::RELATION) && ($this->name !== $config[1])) {
                    $this->dependsOn[] = $config[1];
                }
            }
        }

        sort($this->dependsOn);
    }

    private function calcSelfDependentColumns()
    {
        if ($this->rawData) {
            return false;
        }

        foreach ($this->columnConfig as $name => $config) {
            if (! is_callable($config)) {
                if (is_array($config) && ($config[0] === Generator::RELATION) && ($config[1] === $this->name)) {
                    $this->selfDependentColumns[] = $name;
                }
            }
        }
    }

    /**
     * Insertion des donnees generees en db
     */
    private function insertData()
    {
        if (true === $this->truncateTable) {
            $this->builder->db()->disableFk();
            $this->builder->truncate($this->name);
        }

        foreach ($this->rows as $row) {
            $this->builder->into($this->name)->insert($row);
        }
    }
}
