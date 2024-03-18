<?php

/**
 * This file is part of Blitz PHP framework - Database Layer.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Database\Validation\Rules;

use BlitzPHP\Database\Validation\DatabaseRule;
use BlitzPHP\Validation\Rules\AbstractRule;
use BlitzPHP\Wolke\Entity;

class Unique extends AbstractRule
{
    use DatabaseRule;

    protected $message        = ':attribute :value has been used';
    protected $fillableParams = ['table', 'column', 'ignore'];

    /**
     * Nom de la colonne servant d'ID
     */
    protected string $idColumn = 'id';

    /**
     * Ignorer l'ID donné lors de la vérification de l'unicité.
     */
    public function ignore(mixed $id, ?string $idColumn = null): self
    {
        if (class_exists(Entity::class) && $id instanceof Entity) {
            return $this->ignoreEntity($id, $idColumn);
        }

        $this->params['ignore'] = $id;
        $this->idColumn         = $idColumn ?? 'id';

        return $this;
    }

    /**
     * Ignorer l'entité donné lors de la vérification de l'unicité
     */
    public function ignoreEntity(Entity $entity, ?string $idColumn = null): self
    {
        $this->idColumn         = $idColumn ?? $entity->getKeyName();
        $this->params['ignore'] = $entity->{$this->idColumn};

        return $this;
    }

    public function check($value): bool
    {
        $this->requireParameters(['table']);

        $column = $this->parameter('column') ?: $this->getAttribute()->getKey();

        $builder = $this->makeBuilder($this->parameter('table'), $column, $value);

        if (! empty($ignore = $this->parameter('ignore'))) {
            $builder->where($this->idColumn . ' !=', $ignore);
        }

        return $builder->count() === 0;
    }
}
