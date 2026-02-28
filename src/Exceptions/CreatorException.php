<?php

/**
 * This file is part of Blitz PHP framework - Database Layer.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Database\Exceptions;

/**
 * Exception pour la couche Creator
 */
class CreatorException extends DatabaseException
{
    public static function unsupportedFeature(string $feature): self
    {
        return new static(static::t(
            'La fonctionnalité "%s" n\'est pas supportée par ce pilote de base de données.',
            [$feature]
        ));
    }

    public static function missingFieldDefinition(string $table): self
    {
        return new static(static::t(
            'Aucun champ défini pour la table "%s".',
            [$table]
        ));
    }

    public static function invalidFieldType(string $type): self
    {
        return new static(static::t(
            'Type de champ invalide : "%s".',
            [$type]
        ));
    }
}
