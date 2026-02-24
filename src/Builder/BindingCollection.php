<?php

/**
 * This file is part of Blitz PHP framework - Database Layer.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Database\Builder;

use PDO;
use PDOStatement;

class BindingCollection
{
    protected array $values = [];
    protected array $types = [];

    /**
     * Ajoute un binding
     */
    public function add(mixed $value, ?int $type = null): self
    {
        $this->values[] = $value;
        $this->types[] = $type ?? $this->guessType($value);
        
        return $this;
    }

    /**
     * Ajoute plusieurs bindings
     */
    public function addMany(array $values): self
    {
        foreach ($values as $value) {
            $this->add($value);
        }
        
        return $this;
    }

    /**
     * Ajoute un binding nommé
     */
    public function addNamed(string $name, mixed $value, ?int $type = null): self
    {
        $this->values[$name] = $value;
        $this->types[$name] = $type ?? $this->guessType($value);
        
        return $this;
    }

    /**
     * Récupère toutes les valeurs
     */
    public function getValues(): array
    {
        return $this->values;
    }

    /**
     * Récupère tous les types
     */
    public function getTypes(): array
    {
        return $this->types;
    }

    /**
     * Récupère un binding
     */
    public function get(string|int $key): mixed
    {
        return $this->values[$key] ?? null;
    }

    /**
     * Récupère le type d'un binding
     */
    public function getType(string|int $key): ?int
    {
        return $this->types[$key] ?? null;
    }

    /**
     * Vérifie si des bindings existent
     */
    public function isEmpty(): bool
    {
        return empty($this->values);
    }

    /**
     * Vide la collection
     */
    public function clear(): self
    {
        $this->values = [];
        $this->types = [];
        
        return $this;
    }

    /**
     * Compte le nombre de bindings
     */
    public function count(): int
    {
        return count($this->values);
    }

    /**
     * Fusionne une autre collection
     */
    public function merge(self $bindings): self
    {
        $this->values = array_merge($this->values, $bindings->values);
        $this->types = array_merge($this->types, $bindings->types);
        
        return $this;
    }

    /**
     * Devine le type PDO d'une valeur
     */
    protected function guessType(mixed $value): int
    {
        return match(true) {
            is_int($value) => PDO::PARAM_INT,
            is_bool($value) => PDO::PARAM_BOOL,
            is_null($value) => PDO::PARAM_NULL,
            $value instanceof PDOStatement => PDO::PARAM_STMT,
            default => PDO::PARAM_STR,
        };
    }

    /**
     * Clone la collection
     */
    public function __clone()
    {
        // Rien de spécial à faire, les tableaux sont copiés
    }
}