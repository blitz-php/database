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

use BlitzPHP\Database\Query\Expression;
use InvalidArgumentException;
use PDO;
use PDOStatement;

class BindingCollection
{
    /**
     * Types de bindings supportés
     */
    public const TYPES = [
        'select', 'from', 'join', 'where', 'having',
        'order', 'union', 'values', 'uniqueBy',
    ];

    /**
     * Bindings organisés par type
     *
     * @var array<string, list<mixed>>
     */
    protected array $bindings = [];

    /**
     * Types PDO pour chaque binding (optionnel)
     *
     * @var array<string, list<int>>
     */
    protected array $types = [];

    public function __construct()
    {
        foreach (self::TYPES as $type) {
            $this->bindings[$type] = [];
            $this->types[$type]    = [];
        }
    }

    /**
     * Ajoute un binding dans un contexte spécifique
     *
     * @param mixed    $value   Valeur à binder
     * @param string   $type    Contexte ('where', 'values', etc.)
     * @param int|null $pdoType Type PDO (optionnel)
     *
     * @throws InvalidArgumentException
     */
    public function add(mixed $value, string $type = 'where', ?int $pdoType = null): self
    {
        if (! in_array($type, self::TYPES, true)) {
            throw new InvalidArgumentException("Type de binding invalide: {$type}");
        }

        if ($value === null) {
            $this->bindings[$type][] = '__NULL__';
            $this->types[$type][] = PDO::PARAM_NULL;
            
            return $this;
        }
        
        if ($value instanceof Expression) {
            $this->bindings[$type][] = $value;
            $this->types[$type][] = null;
            
            return $this;
        }
        
        $this->bindings[$type][] = $value;
        $this->types[$type][]    = $pdoType ?? $this->guessType($value);

        return $this;
    }

    /**
     * Ajoute plusieurs bindings dans un contexte
     */
    public function addMany(array $values, string $type = 'where'): self
    {
        foreach ($values as $value) {
            $this->add($value, $type);
        }

        return $this;
    }

    /**
     * (Re)Définit un ensemble de bindings pour un contexte
     *
     * Cet méthode écrase les bindings existants pour le contexte donné
     */
    public function set(array $values, string $context = 'where'): static
    {
        $this->clear($context);
        $this->addMany($values, $context);

        return $this;
    }

    /**
     * Ajoute un binding nommé
     */
    public function addNamed(string $name, mixed $value, string $context = 'where', ?int $pdoType = null): self
    {
        $this->bindings[$context][$name] = $value;
        $this->types[$context][$name]    = $pdoType ?? $this->guessType($value);

        return $this;
    }

    /**
     * Récupère tous les bindings d'un contexte
     *
     * @return list<mixed>|mixed
     */
    public function get(string $context, ?string $name = null)
    {
        $bindings = $this->bindings[$context] ?? [];

        return $name ? ($bindings[$name] ?? null) : $bindings;
    }

    /**
     * Récupère tous les bindings dans l'ordre de compilation
     *
     * @param list<string> $contexts
     *
     * @return list<mixed>
     */
    public function getOrdered(array $contexts = []): array
    {
        if ($contexts === []) {
            $contexts = self::TYPES;
        }

        $result = [];

        foreach ($contexts as $context) {
            if (! empty($this->bindings[$context])) {
                foreach ($this->bindings[$context] as $binding) {
                    if ($binding === '__NULL__' || $binding instanceof Expression) {
                        continue;
                    }
                    $result[] = $binding;
                }
            }
        }

        return $result;
    }

    /**
     * Récupère les types PDO dans l'ordre
     * 
     * @param list<string> $contexts
     *
     * @return list<int>
     */
    public function getTypesOrdered(array $contexts = []): array
    {
        if ($contexts === []) {
            $contexts = self::TYPES;
        }

        $result = [];
        foreach ($contexts as $context) {
            if (!empty($this->types[$context])) {
                foreach ($this->types[$context] as $index => $type) {
                    $binding = $this->bindings[$context][$index] ?? null;
                    if ($binding === '__NULL__') {
                        $result[] = PDO::PARAM_NULL;
                    } elseif ($type !== null) {
                        $result[] = $type;
                    }
                }
            }
        }
        
        return $result;
    }

    /**
     * Vérifie si un contexte a des bindings
     */
    public function has(string $context): bool
    {
        return ! empty($this->bindings[$context]);
    }

    /**
     * Compte le nombre total de bindings
     */
    public function count(?string $context = null): int
    {
        $context = $context === null ? [] : [$context];

        $bindings = $this->getOrdered($context);

        return count($bindings);
    }

    /**
     * Vérifie si un contexte est vide
     */
    public function isEmpty(?string $context = null): bool
    {
        return $this->count($context) === 0;
    }

    /**
     * Vide tous les bindings
     */
    public function clear(?string $context = null): self
    {
        if ($context !== null) {
            return $this->clearContext($context);
        }

        foreach (self::TYPES as $t) {
            $this->bindings[$t] = [];
            $this->types[$t]    = [];
        }

        return $this;
    }

    /**
     * Vide les bindings d'un contexte spécifique
     */
    public function clearContext(string $context): self
    {
        if (isset($this->bindings[$context])) {
            $this->bindings[$context] = [];
            $this->types[$context]    = [];
        }

        return $this;
    }

    /**
     * Fusionne une autre collection
     */
    public function merge(self $collection): self
    {
        foreach (self::TYPES as $type) {
            array_push($this->bindings[$type], ...$collection->bindings[$type]);
            array_push($this->types[$type], ...$collection->types[$type]);
        }

        return $this;
    }

    /**
     * Retire les expressions des bindings (elles ne doivent pas être bindées)
     *
     * @param list<mixed> $bindings
     *
     * @return list<mixed>
     */
    public function clean(array $bindings): array
    {
        return array_filter($bindings, static fn ($binding) => ! $binding instanceof Expression);
    }

    /**
     * Devine le type PDO d'une valeur
     */
    protected function guessType(mixed $value): int
    {
        return match (true) {
            is_int($value)                 => PDO::PARAM_INT,
            is_bool($value)                => PDO::PARAM_BOOL,
            null === $value                => PDO::PARAM_NULL,
            $value instanceof PDOStatement => PDO::PARAM_STMT,
            default                        => PDO::PARAM_STR,
        };
    }

    /**
     * Pour le débogage
     */
    public function toArray(): array
    {
        return $this->bindings;
    }

    public function __clone()
    {
        foreach ($this->bindings as $type => $bindings) {
            $this->bindings[$type] = $bindings;
        }

        foreach ($this->types as $type => $types) {
            $this->types[$type] = $types;
        }
    }
}
