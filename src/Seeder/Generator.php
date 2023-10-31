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

use Faker\Factory;
use Faker\Generator as TrueFaker;
use InvalidArgumentException;

/**
 * Generateur de données
 *
 * @credit <a href="https://github.com/tebazil/db-seeder">tebazil/db-seeder</a>
 */
class Generator
{
    public const PK       = 'pk';
    public const FAKER    = 'faker';
    public const RELATION = 'relation';

    private bool $reset = false;

    /**
     * Instance du generateur de fake data
     */
    private ?TrueFaker $faker = null;

    /**
     * Valeur courante de la cle primaire
     */
    private int $pkValue = 1;

    /**
     * Liste des tables pour lesquelles les donnees doivent etre generees
     */
    private array $tables = [];

    public function __construct(private string $locale)
    {
    }

    /**
     * Recupere une valeur generee
     */
    public function getValue(array|string $config): mixed
    {
        if (! is_array($config)) {
            $config = [$config];
        }

        return match ($config[0]) {
            self::PK       => $this->pkValue(),
            self::FAKER    => $this->fakerValue($config[1], $config[2] ?? [], $config[3] ?? []),
            self::RELATION => $this->relationValue($config[1], $config[2] ?? ''),
            default        => is_callable($config[0]) ? $config[0]() : $config[0]
        };
    }

    /**
     * Reinitialise les valeurs du generateur
     *
     * @return void
     */
    public function reset()
    {
        $this->reset = true;
    }

    /**
     * Definition des champs d'une table
     */
    public function setColumns(string $table, array $columns)
    {
        $this->tables[$table] = $columns;
    }

    /**
     * Recupere la cle primaire auto incrementee
     */
    private function pkValue(): int
    {
        if ($this->reset) {
            $this->pkValue = 1;
            $this->reset   = false;
        }

        return $this->pkValue++;
    }

    /**
     * Recupere une valeur generee par Faker
     */
    private function fakerValue(mixed $format, array $arguments, array $options): mixed
    {
        if (empty($format)) {
            return null;
        }

        $faker = $this->faker();

        if (isset($options[Faker::UNIQUE]) && is_array($options[Faker::UNIQUE])) {
            $faker = call_user_func_array([$faker, 'unique'], $options[Faker::UNIQUE]);
        }

        if (isset($options[Faker::OPTIONAL]) && is_array($options[Faker::OPTIONAL])) {
            $faker = call_user_func_array([$faker, 'optinal'], $options[Faker::OPTIONAL]);
        }

        if (isset($options[Faker::VALID]) && is_array($options[Faker::VALID])) {
            $faker = call_user_func_array([$faker, 'valid'], $options[Faker::VALID]);
        }

        return $faker->format($format, $arguments);
    }

    /**
     * Recupere une valeur issue d'une relation
     */
    private function relationValue(string $table, string $column): mixed
    {
        if (! $this->isColumnSet($table, $column)) {
            throw new InvalidArgumentException("Table {$table} , column {$column} is not filled");
        }

        return $this->tables[$table][$column][array_rand($this->tables[$table][$column])];
    }

    /**
     * Instance unique du generateur faker pour le generateur courant
     */
    private function faker(): TrueFaker
    {
        if (null === $this->faker) {
            $this->faker = Factory::create($this->locale);
        }

        return $this->faker;
    }

    /**
     * Verifie si un champ de table a une valeur renseignee
     */
    private function isColumnSet(string $table, string $column): bool
    {
        return isset($this->tables[$table]) && isset($this->tables[$table][$column]);
    }
}
