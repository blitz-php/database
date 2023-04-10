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

use BlitzPHP\Utilities\Iterable\Arr;
use Exception;
use InvalidArgumentException;

/**
 * @credit <a href="https://github.com/tebazil/db-seeder">tebazil/db-seeder</a>
 */
class TableDef
{
    public function __construct(private Table $table)
    {
    }

    /**
     * Defini les type de donnees a generer pour chaque colone qu'on souhaite remplir dans la base de donnees
     */
    public function columns(array $columns): self
    {
        $columns = $this->preprocess($columns);
        $this->table->setColumns($columns);

        return $this;
    }

    /**
     * Specifie le nombre de ligne a inserer dans la table
     */
    public function rows(int $rows = Table::DEFAULT_ROW_QUANTITY): self
    {
        $this->table->setRowQuantity($rows);

        return $this;
    }

    /**
     * Definit les donnees brutes a inserer
     */
    public function data(array $data): self
    {
        $dim = Arr::dimensions($data);

        if ($dim > 2) {
            throw new InvalidArgumentException('Vous ne pouvez pas inserer un tableau de dimension supperieure a 2');
        }

        if ($dim === 1) {
            $columnNames = array_keys($data);
            $data        = [array_values($data)];
        } else {
            $columnNames = array_keys(reset($data));
            $data        = array_map('array_values', $data);
        }

        $this->table->setRawData($data, $columnNames);

        return $this;
    }

    /**
     * Undocumented function
     */
    private function preprocess(array $columns): array
    {
        $newColumns = [];

        foreach ($columns as $key => $value) {
            if (is_numeric($key)) {
                if (! is_scalar($value)) {
                    throw new Exception("Si la colonne est configurée à la volée, sa valeur doit être scalaire - soit id, soit clé étrangère, c'est-à-dire status_id");
                }

                $config = explode('_', $value);

                if ($config[0] === 'id') {
                    $newColumns[$value] = [Generator::PK];
                } elseif (count($config) === 2 || $config[1] === 'id') {
                    $newColumns[$value] = [Generator::RELATION, $config[0], 'id'];
                } else {
                    throw new Exception('Le champ ' . $value . ' est mal configuré');
                }
            } else {
                $newColumns[$key] = $value;
            }
        }

        return $newColumns;
    }
}
