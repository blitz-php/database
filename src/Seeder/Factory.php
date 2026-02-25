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

use Faker\Factory as FakerFactory;
use Faker\Generator as FakerGenerator;

/**
 * Générateur de configurations pour les seeders
 * 
 * @mixin FakerGenerator
 */
class Factory
{
    /**
     * Instance Faker
     */
    protected FakerGenerator $faker;

    /**
     * Constructeur
     */
    public function __construct(string $locale = 'fr_FR')
    {
        $this->faker = FakerFactory::create($locale);
    }

    /**
     * Appel Faker (retourne une configuration)
     */
    public function __call(string $name, array $arguments): array
    {
        if ($name === 'unique') {
            return ['faker:unique', $arguments[0] ?? null, array_slice($arguments, 1) ?? []];
        }

        return ['faker', $name, $arguments];
    }

    /**
     * Propriété Faker (retourne une configuration)
     */
    public function __get(string $name): array
    {
        return ['faker', $name, []];
    }

    /**
     * Relation avec une autre table
     */
    public function relation(string $table, string $column = 'id'): array
    {
        return ['relation', $table, $column];
    }

    /**
     * Valeur optionnelle
     */
    public function optional(float $weight = 0.5, mixed $default = null, mixed $value = null): array
    {
        if ($value === null) {
            // Si pas de valeur fournie, on utilisera une closure
            return ['optional', $weight, $default];
        }
        return ['optional', $weight, $default, $value];
    }

    /**
     * Valeur fixe (pour compatibilité)
     */
    public function raw(mixed $value): mixed
    {
        return $value;
    }
}
