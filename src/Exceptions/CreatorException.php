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

use Throwable;

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

    public static function unableToCreateDatabase(string $name, ?Throwable $previous = null): self
    {
        return new static(static::t('Impossible de créer la base de données "%s".', [$name]), previous: $previous);
    }
    
    public static function unableToDropDatabase(string $name, ?Throwable $previous = null): self
    {
        return new static(static::t('Impossible de supprimer la base de données "%s".', [$name]), previous: $previous);
    }

    public static function needTableName(): self
    {
        return new static(static::t('Un nom de table est nécessaire pour cette opération.'));
    }
}
