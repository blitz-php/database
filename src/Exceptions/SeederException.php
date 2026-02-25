<?php

namespace BlitzPHP\Database\Exceptions;

class SeederException extends DatabaseException
{
    public static function dataCannotBeEmpty()
    {
        return new static(static::t('Les données ne peuvent pas être vides.'));
    }

    public static function unspecifiedFakerMethod()
    {
        return new static(static::t('Méthode Faker non spécifiée.'));
    }

    public static function relationTableNotFound(string $table)
    {
        return new static(static::t("Table de relation '%s' non trouvée.", [$table]));
    }

    public static function propertyNotFound(string $property)
    {
        return new static(static::t("Propriété '%s' non trouvée.", [$property]));
    }

    public static function seederClassDoesNotExist(string $class)
    {
        return new static(static::t("La classe de seeder '%s' n'existe pas.", [$class]));
    }
}
