<?php

/**
 * This file is part of Blitz PHP framework - Database Layer.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Database\Migration;

use BlitzPHP\Database\Connection\BaseConnection;
use BlitzPHP\Database\Migration\Definitions\Column;
use BlitzPHP\Database\Migration\Definitions\ForeignId;
use BlitzPHP\Database\Migration\Definitions\ForeignKey;
use BlitzPHP\Database\Migration\Definitions\Index;
use BlitzPHP\Database\Query\Expression;
use BlitzPHP\Traits\Macroable;
use Closure;

/**
 * Constructeur de définition de table
 *
 * Cette classe permet de définir la structure d'une table de manière fluide.
 * Elle est utilisée par les migrations pour décrire les modifications à apporter.
 *
 * @credit <a href="https://laravel.com">Laravel Framework - Illuminate\Database\Schema\Blueprint</a>
 */
class Builder
{
    use Macroable;

    /**
     * Type d'action (create, alter, drop, rename)
     */
    protected string $action = 'create';

    /**
     * Liste des colonnes à ajouter/modifier
     *
     * @var list<Column>
     */
    protected array $columns = [];

    /**
     * Liste des index à ajouter
     *
     * @var list<Index>
     */
    protected array $indexes = [];

    /**
     * Liste des clés étrangères à ajouter
     *
     * @var list<ForeignKey>
     */
    protected array $foreignKeys = [];

    /**
     * Connexion utilisée pour ce builder
     */
    protected BaseConnection $db;

    /**
     * Éléments à supprimer
     *
     * @var array{
     *     columns?: list<string>,
     *     primary?: string,
     *     unique?: list<string>,
     *     index?: list<string>,
     *     foreign?: list<string>
     * }
     */
    protected array $drops = [];

    /**
     * Informations de renommage
     *
     * @var array{to?: string}
     */
    protected array $renames = [];

    /**
     * Moteur de stockage a utiliser sur la table.
     */
    public string $engine = '';

    /**
     * Charset par défaut
     */
    public string $charset = '';

    /**
     * Collation par defaut.
     */
    public string $collation = '';

    /**
     * Commentaire de la table
     */
    public string $comment = '';

    /**
     * Doit-on creer une table temporaire.
     */
    public bool $temporary = false;

    /**
     * La colonne après laquelle de nouvelles colonnes seront ajoutées.
     */
    public ?string $after = null;

    /**
     * Constructeur
     *
     * @param string $table  Nom de la table
     * @param string $prefix Préfixe de la table
     */
    public function __construct(protected string $table, protected string $prefix = '')
    {
    }

    /**
     * Spécifie la connexion utilisée pour ce builder
     *
     * @internal
     */
    public function setConnection(BaseConnection $db): self
    {
        $this->db = $db;

        return $this;
    }

    /**
     * Récupère l'instance de la connexion utilisée par ce builder
     *
     * @internal
     */
    public function getConnection(): BaseConnection
    {
        return $this->db;
    }

    /*
    |--------------------------------------------------------------------------
    | Actions
    |--------------------------------------------------------------------------
    */

    /**
     * Indique que la table doit être créée
     *
     * @internal
     */
    public function createTable(bool $ifNotExists = false): void
    {
        $this->action = $ifNotExists ? 'createIfNotExists' : 'create';
    }

    /**
     * Indique que la table doit être modifiée
     *
     * @internal
     */
    public function alterTable(): void
    {
        $this->action = 'alter';
    }

    /**
     * Indique que la table doit être supprimée
     *
     * @internal
     */
    public function dropTable(bool $ifExists = false): void
    {
        $this->action = $ifExists ? 'dropIfExists' : 'drop';
    }

    /**
     * Indique que la table doit être renommée
     *
     * @internal
     */
    public function renameTable(string $to): void
    {
        $this->action = 'rename';

        $this->renames['to'] = $to;
    }

    /**
     * Définit le moteur de stockage pour la table
     */
    public function engine(string $engine): static
    {
        $this->engine = $engine;

        return $this;
    }

    /**
     * Spécifie que le moteur InnoDB doit être utilisé (MySQL uniquement)
     */
    public function innoDb(): static
    {
        return $this->engine('InnoDB');
    }

    /**
     * Définit le jeu de caractères pour la table
     */
    public function charset(string $charset): static
    {
        $this->charset = $charset;

        return $this;
    }

    /**
     * Définit la collation pour la table
     */
    public function collation(string $collation): static
    {
        $this->collation = $collation;

        return $this;
    }

    /**
     * Indique que la table doit être temporaire
     */
    public function temporary(): static
    {
        $this->temporary = true;

        return $this;
    }

    /**
     * Ajoute un commentaire à la table
     */
    public function comment(string $comment): static
    {
        $this->comment = $comment;

        return $this;
    }

    /**
     * Ajoute les colonnes après la colonne spécifiée
     */
    public function after(string $column, Closure $callback): static
    {
        $this->after = $column;
        $callback($this);
        $this->after = null;

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Types de colonnes
    |--------------------------------------------------------------------------
    */

    /**
     * Ajoute une colonne auto-incrémentée (alias de bigIncrements)
     */
    public function id(string $column = 'id'): Column
    {
        return $this->bigIncrements($column);
    }

    /**
     * Ajoute une colonne INTEGER auto-incrémentée
     */
    public function increments(string $column): Column
    {
        return $this->unsignedInteger($column, true);
    }

    /**
     * Ajoute une colonne INTEGER auto-incrémentée
     */
    public function integerIncrements(string $column): Column
    {
        return $this->unsignedInteger($column, true);
    }

    /**
     * Ajoute une colonne TINYINT auto-incrémentée (1 octet)
     */
    public function tinyIncrements(string $column): Column
    {
        return $this->unsignedTinyInteger($column, true);
    }

    /**
     * Ajoute une colonne SMALLINT auto-incrémentée (2 octets)
     */
    public function smallIncrements(string $column): Column
    {
        return $this->unsignedSmallInteger($column, true);
    }

    /**
     * Ajoute une colonne MEDIUMINT auto-incrémentée (3 octets)
     */
    public function mediumIncrements(string $column): Column
    {
        return $this->unsignedMediumInteger($column, true);
    }

    /**
     * Ajoute une colonne BIGINT auto-incrémentée
     */
    public function bigIncrements(string $column): Column
    {
        return $this->unsignedBigInteger($column, true);
    }

    /**
     * Ajoute une colonne CHAR
     */
    public function char(string $column, ?int $length = null): Column
    {
        $length = max($length ?: Runner::$defaultStringLength, 1);

        return $this->addColumn('char', $column, ['length' => $length]);
    }

    /**
     * Ajoute une colonne de type chaîne de caractères
     */
    public function string(string $column, ?int $length = null): Column
    {
        $length = max($length ?: Runner::$defaultStringLength, 1);

        return $this->addColumn('string', $column, ['length' => $length]);
    }

    /**
     * Ajoute une colonne TINYTEXT
     */
    public function tinyText(string $column): Column
    {
        return $this->addColumn('tinyText', $column);
    }

    /**
     * Ajoute une colonne de type texte
     */
    public function text(string $column): Column
    {
        return $this->addColumn('text', $column);
    }

    /**
     * Ajoute une colonne MEDIUMTEXT
     */
    public function mediumText(string $column): Column
    {
        return $this->addColumn('mediumText', $column);
    }

    /**
     * Ajoute une colonne LONGTEXT
     */
    public function longText(string $column): Column
    {
        return $this->addColumn('longText', $column);
    }

    /**
     * Ajoute une colonne de type entier
     */
    public function integer(string $column, bool $autoIncrement = false, bool $unsigned = false): Column
    {
        return $this->addColumn('integer', $column, compact('autoIncrement', 'unsigned'));
    }

    /**
     * Ajoute une colonne TINYINT
     */
    public function tinyInteger(string $column, bool $autoIncrement = false, bool $unsigned = false): Column
    {
        return $this->addColumn('tinyInteger', $column, compact('autoIncrement', 'unsigned'));
    }

    /**
     * Ajoute une colonne SMALLINT
     */
    public function smallInteger(string $column, bool $autoIncrement = false, bool $unsigned = false): Column
    {
        return $this->addColumn('smallInteger', $column, compact('autoIncrement', 'unsigned'));
    }

    /**
     * Ajoute une colonne MEDIUMINT
     */
    public function mediumInteger(string $column, bool $autoIncrement = false, bool $unsigned = false): Column
    {
        return $this->addColumn('mediumInteger', $column, compact('autoIncrement', 'unsigned'));
    }

    /**
     * Ajoute une colonne de type entier long
     */
    public function bigInteger(string $column, bool $autoIncrement = false, bool $unsigned = false): Column
    {
        return $this->addColumn('bigInteger', $column, compact('autoIncrement', 'unsigned'));
    }

    /**
     * Ajoute une colonne INTEGER non signée
     */
    public function unsignedInteger(string $column, bool $autoIncrement = false): Column
    {
        return $this->integer($column, $autoIncrement, true);
    }

    /**
     * Ajoute une colonne TINYINT non signée
     */
    public function unsignedTinyInteger(string $column, bool $autoIncrement = false): Column
    {
        return $this->tinyInteger($column, $autoIncrement, true);
    }

    /**
     * Ajoute une colonne SMALLINT non signée
     */
    public function unsignedSmallInteger(string $column, bool $autoIncrement = false): Column
    {
        return $this->smallInteger($column, $autoIncrement, true);
    }

    /**
     * Ajoute une colonne MEDIUMINT non signée
     */
    public function unsignedMediumInteger(string $column, bool $autoIncrement = false): Column
    {
        return $this->mediumInteger($column, $autoIncrement, true);
    }

    /**
     * Ajoute une colonne BIGINT non signée
     */
    public function unsignedBigInteger(string $column, bool $autoIncrement = false): Column
    {
        return $this->bigInteger($column, $autoIncrement, true);
    }

    /**
     * Ajoute une colonne de clé étrangère (BIGINT non signé)
     */
    public function foreignId(string $column): ForeignId
    {
        return $this->addColumnDefinition(new ForeignId($this, [
            'type'          => 'bigInteger',
            'name'          => $column,
            'autoIncrement' => false,
            'unsigned'      => true,
        ]));
    }

    /**
     * Ajoute une colonne de clé étrangère pour le modèle donné
     *
     * @param string      $model  Classe du modèle
     * @param string|null $column Nom de la colonne
     */
    public function foreignIdFor(string $model, ?string $column = null): ForeignId
    {
        // Par défaut, on utilise une BIGINT non signée
        // Dans une implémentation réelle, on détecterait le type de clé du modèle
        return $this->foreignId($column ?? strtolower(basename(str_replace('\\', '/', $model))) . '_id');
    }

    /**
     * Ajoute une colonne de type nombre à virgule flottante
     */
    public function float(string $column, int $total = 8, int $places = 2, bool $unsigned = false): Column
    {
        return $this->addColumn('float', $column, compact('total', 'places', 'unsigned'));
    }

    /**
     * Ajoute une colonne de type DOUBLE
     */
    public function double(string $column, ?int $total = null, ?int $places = null, bool $unsigned = false): Column
    {
        return $this->addColumn('double', $column, compact('total', 'places', 'unsigned'));
    }

    /**
     * Ajoute une colonne de type nombre décimal
     */
    public function decimal(string $column, int $total = 8, int $places = 2, bool $unsigned = false): Column
    {
        return $this->addColumn('decimal', $column, compact('total', 'places', 'unsigned'));
    }

    /**
     * Ajoute une colonne FLOAT non signée
     */
    public function unsignedFloat(string $column, int $total = 8, int $places = 2): Column
    {
        return $this->float($column, $total, $places, true);
    }

    /**
     * Ajoute une colonne DOUBLE non signée
     */
    public function unsignedDouble(string $column, ?int $total = null, ?int $places = null): Column
    {
        return $this->double($column, $total, $places, true);
    }

    /**
     * Ajoute une colonne DECIMAL non signée
     */
    public function unsignedDecimal(string $column, int $total = 8, int $places = 2): Column
    {
        return $this->decimal($column, $total, $places, true);
    }

    /**
     * Ajoute une colonne de type booléen
     */
    public function boolean(string $column): Column
    {
        return $this->addColumn('boolean', $column);
    }

    /**
     * Ajoute une colonne de type ENUM
     */
    public function enum(string $column, array $allowed): Column
    {
        return $this->addColumn('enum', $column, ['allowed' => $allowed]);
    }

    /**
     * Ajoute une colonne de type SET
     */
    public function set(string $column, array $allowed): Column
    {
        return $this->addColumn('set', $column, ['allowed' => $allowed]);
    }

    /**
     * Ajoute une colonne de type JSON
     */
    public function json(string $column): Column
    {
        return $this->addColumn('json', $column);
    }

    /**
     * Ajoute une colonne de type JSON binaire
     */
    public function jsonb(string $column): Column
    {
        return $this->addColumn('jsonb', $column);
    }

    /**
     * Ajoute une colonne de type date
     */
    public function date(string $column): Column
    {
        return $this->addColumn('date', $column);
    }

    /**
     * Ajoute une colonne de type date et heure
     */
    public function dateTime(string $column, int $precision = 0): Column
    {
        return $this->addColumn('dateTime', $column, ['precision' => $precision]);
    }

    /**
     * Ajoute une colonne DATETIME (avec fuseau horaire)
     */
    public function dateTimeTz(string $column, int $precision = 0): Column
    {
        return $this->addColumn('dateTimeTz', $column, ['precision' => $precision]);
    }

    /**
     * Ajoute une colonne de type time
     */
    public function time(string $column, int $precision = 0): Column
    {
        return $this->addColumn('time', $column, ['precision' => $precision]);
    }

    /**
     * Ajoute une colonne TIME (avec fuseau horaire)
     */
    public function timeTz(string $column, int $precision = 0): Column
    {
        return $this->addColumn('timeTz', $column, ['precision' => $precision]);
    }

    /**
     * Ajoute une colonne de type timestamp
     */
    public function timestamp(string $column, int $precision = 0): Column
    {
        return $this->addColumn('timestamp', $column, ['precision' => $precision]);
    }

    /**
     * Ajoute une colonne TIMESTAMP (avec fuseau horaire)
     */
    public function timestampTz(string $column, int $precision = 0): Column
    {
        return $this->addColumn('timestampTz', $column, ['precision' => $precision]);
    }

    /**
     * Ajoute les colonnes created_at et updated_at
     */
    public function timestamps(int $precision = 0): void
    {
        $this->timestamp('created_at', $precision)->nullable();
        $this->timestamp('updated_at', $precision)->nullable();
    }

    /**
     * Ajoute les colonnes timestamps nullables
     */
    public function nullableTimestamps(int $precision = 0): void
    {
        $this->timestamps($precision);
    }

    /**
     * Ajoute les colonnes TIMESTAMP avec fuseau horaire
     */
    public function timestampsTz(int $precision = 0): void
    {
        $this->timestampTz('created_at', $precision)->nullable();
        $this->timestampTz('updated_at', $precision)->nullable();
    }

    /**
     * Ajoute les colonnes DATETIME
     */
    public function datetimes(int $precision = 0): void
    {
        $this->datetime('created_at', $precision)->nullable();
        $this->datetime('updated_at', $precision)->nullable();
    }

    /**
     * Ajoute une colonne de suppression logique (soft delete)
     */
    public function softDeletes(string $column = 'deleted_at', int $precision = 0): Column
    {
        return $this->timestamp($column, $precision)->nullable();
    }

    /**
     * Ajoute une colonne de suppression logique avec fuseau horaire
     */
    public function softDeletesTz(string $column = 'deleted_at', int $precision = 0): Column
    {
        return $this->timestampTz($column, $precision)->nullable();
    }

    /**
     * Ajoute une colonne de suppression logique DATETIME
     */
    public function softDeletesDatetime(string $column = 'deleted_at', int $precision = 0): Column
    {
        return $this->datetime($column, $precision)->nullable();
    }

    /**
     * Ajoute une colonne YEAR
     */
    public function year(string $column): Column
    {
        return $this->addColumn('year', $column);
    }

    /**
     * Ajoute une colonne binaire
     */
    public function binary(string $column): Column
    {
        return $this->addColumn('binary', $column);
    }

    /**
     * Ajoute une colonne de type UUID
     */
    public function uuid(string $column): Column
    {
        return $this->addColumn('uuid', $column);
    }

    /**
     * Ajoute une colonne UUID avec contrainte de clé étrangère
     */
    public function foreignUuid(string $column): ForeignId
    {
        return $this->addColumnDefinition(new ForeignId($this, [
            'type' => 'uuid',
            'name' => $column,
        ]));
    }

    /**
     * Ajoute une colonne ULID (Universally Unique Lexicographically Sortable Identifier)
     */
    public function ulid(string $column = 'ulid', int $length = 26): Column
    {
        return $this->char($column, $length);
    }

    /**
     * Ajoute une colonne ULID avec contrainte de clé étrangère
     */
    public function foreignUlid(string $column, int $length = 26): ForeignId
    {
        return $this->addColumnDefinition(new ForeignId($this, [
            'type'   => 'char',
            'name'   => $column,
            'length' => $length,
        ]));
    }

    /**
     * Ajoute une colonne d'adresse IP
     */
    public function ipAddress(string $column = 'ip_address'): Column
    {
        return $this->addColumn('ipAddress', $column);
    }

    /**
     * Ajoute une colonne d'adresse MAC
     */
    public function macAddress(string $column = 'mac_address'): Column
    {
        return $this->addColumn('macAddress', $column);
    }

    /**
     * Ajoute une colonne de type geometry
     */
    public function geometry(string $column): Column
    {
        return $this->addColumn('geometry', $column);
    }

    /**
     * Ajoute une colonne de type point
     */
    public function point(string $column, ?int $srid = null): Column
    {
        return $this->addColumn('point', $column, compact('srid'));
    }

    /**
     * Ajoute une colonne de type linestring
     */
    public function lineString(string $column): Column
    {
        return $this->addColumn('linestring', $column);
    }

    /**
     * Ajoute une colonne de type polygon
     */
    public function polygon(string $column): Column
    {
        return $this->addColumn('polygon', $column);
    }

    /**
     * Ajoute une colonne de type geometrycollection
     */
    public function geometryCollection(string $column): Column
    {
        return $this->addColumn('geometrycollection', $column);
    }

    /**
     * Ajoute une colonne de type multipoint
     */
    public function multiPoint(string $column): Column
    {
        return $this->addColumn('multipoint', $column);
    }

    /**
     * Ajoute une colonne de type multilinestring
     */
    public function multiLineString(string $column): Column
    {
        return $this->addColumn('multilinestring', $column);
    }

    /**
     * Ajoute une colonne de type multipolygon
     */
    public function multiPolygon(string $column): Column
    {
        return $this->addColumn('multipolygon', $column);
    }

    /**
     * Ajoute une colonne de type multipolygon Z
     */
    public function multiPolygonZ(string $column): Column
    {
        return $this->addColumn('multipolygonz', $column);
    }

    /**
     * Ajoute une colonne calculée (generated/computed)
     */
    public function computed(string $column, string $expression): Column
    {
        return $this->addColumn('computed', $column, compact('expression'));
    }

    /**
     * Ajoute la colonne remember_token
     */
    public function rememberToken(): Column
    {
        return $this->string('remember_token', 100)->nullable();
    }

    /*
    |--------------------------------------------------------------------------
    | Méthodes pour les tables polymorphiques
    |--------------------------------------------------------------------------
    */

    /**
     * Ajoute les colonnes pour une table polymorphique
     */
    public function morphs(string $name, ?string $indexName = null): void
    {
        if (Runner::$defaultMorphKeyType === 'uuid') {
            $this->uuidMorphs($name, $indexName);
        } elseif (Runner::$defaultMorphKeyType === 'ulid') {
            $this->ulidMorphs($name, $indexName);
        } else {
            $this->numericMorphs($name, $indexName);
        }
    }

    /**
     * Ajoute les colonnes nullables pour une table polymorphique
     */
    public function nullableMorphs(string $name, ?string $indexName = null): void
    {
        if (Runner::$defaultMorphKeyType === 'uuid') {
            $this->nullableUuidMorphs($name, $indexName);
        } elseif (Runner::$defaultMorphKeyType === 'ulid') {
            $this->nullableUlidMorphs($name, $indexName);
        } else {
            $this->nullableNumericMorphs($name, $indexName);
        }
    }

    /**
     * Ajoute les colonnes pour une table polymorphique avec IDs numériques
     */
    public function numericMorphs(string $name, ?string $indexName = null): void
    {
        $this->string("{$name}_type");
        $this->unsignedBigInteger("{$name}_id");
        $this->index(["{$name}_type", "{$name}_id"], $indexName);
    }

    /**
     * Ajoute les colonnes nullables pour une table polymorphique avec IDs numériques
     */
    public function nullableNumericMorphs(string $name, ?string $indexName = null): void
    {
        $this->string("{$name}_type")->nullable();
        $this->unsignedBigInteger("{$name}_id")->nullable();
        $this->index(["{$name}_type", "{$name}_id"], $indexName);
    }

    /**
     * Ajoute les colonnes pour une table polymorphique avec UUIDs
     */
    public function uuidMorphs(string $name, ?string $indexName = null): void
    {
        $this->string("{$name}_type");
        $this->uuid("{$name}_id");
        $this->index(["{$name}_type", "{$name}_id"], $indexName);
    }

    /**
     * Ajoute les colonnes nullables pour une table polymorphique avec UUIDs
     */
    public function nullableUuidMorphs(string $name, ?string $indexName = null): void
    {
        $this->string("{$name}_type")->nullable();
        $this->uuid("{$name}_id")->nullable();
        $this->index(["{$name}_type", "{$name}_id"], $indexName);
    }

    /**
     * Ajoute les colonnes pour une table polymorphique avec ULIDs
     */
    public function ulidMorphs(string $name, ?string $indexName = null): void
    {
        $this->string("{$name}_type");
        $this->ulid("{$name}_id");
        $this->index(["{$name}_type", "{$name}_id"], $indexName);
    }

    /**
     * Ajoute les colonnes nullables pour une table polymorphique avec ULIDs
     */
    public function nullableUlidMorphs(string $name, ?string $indexName = null): void
    {
        $this->string("{$name}_type")->nullable();
        $this->ulid("{$name}_id")->nullable();
        $this->index(["{$name}_type", "{$name}_id"], $indexName);
    }

    /*
    |--------------------------------------------------------------------------
    | Index
    |--------------------------------------------------------------------------
    */

    /**
     * Ajoute une clé primaire
     */
    public function primary(array|string $columns, ?string $name = null, ?string $algorithm = null): Index
    {
        return $this->addIndex('primary', $columns, $name, $algorithm);
    }

    /**
     * Ajoute un index unique
     */
    public function unique(array|string $columns, ?string $name = null, ?string $algorithm = null): Index
    {
        return $this->addIndex('unique', $columns, $name, $algorithm);
    }

    /**
     * Ajoute un index simple
     */
    public function index(array|string $columns, ?string $name = null, ?string $algorithm = null): Index
    {
        return $this->addIndex('index', $columns, $name, $algorithm);
    }

    /**
     * Ajoute un index FULLTEXT
     */
    public function fullText(array|string $columns, ?string $name = null, ?string $algorithm = null): Index
    {
        return $this->addIndex('fulltext', $columns, $name, $algorithm);
    }

    /**
     * Ajoute un index spatial
     */
    public function spatialIndex(array|string $columns, ?string $name = null): Index
    {
        return $this->addIndex('spatialIndex', $columns, $name);
    }

    /**
     * Ajoute un index brut (expression)
     */
    public function rawIndex(string $expression, string $name): Index
    {
        return $this->addIndex('index', [new Expression($expression)], $name);
    }

    /**
     * Ajoute une clé étrangère
     */
    public function foreign(array|string $columns, ?string $name = null): ForeignKey
    {
        return $this->addForeignKey($columns, $name);
    }

    /**
     * Supprime une clé étrangère avec sa colonne
     */
    public function dropConstrainedForeignId(string $column): void
    {
        $this->dropForeign([$column]);
        $this->dropColumn($column);
    }

    /**
     * Supprime une clé étrangère pour le modèle donné
     */
    public function dropForeignIdFor(string $model, ?string $column = null): void
    {
        $column ??= strtolower(basename(str_replace('\\', '/', $model))) . '_id';

        $this->dropForeign([$column]);
    }

    /**
     * Supprime une clé étrangère avec sa colonne pour le modèle donné
     */
    public function dropConstrainedForeignIdFor(string $model, ?string $column = null): void
    {
        $column ??= strtolower(basename(str_replace('\\', '/', $model))) . '_id';

        $this->dropConstrainedForeignId($column);
    }

    /**
     * Renomme un index
     */
    public function renameIndex(string $from, string $to): void
    {
        $this->renames['index'][$from] = $to;
    }

    /*
    |--------------------------------------------------------------------------
    | Suppressions
    |--------------------------------------------------------------------------
    */

    /**
     * Supprime une ou plusieurs colonnes
     */
    public function dropColumn(array|string $columns): void
    {
        foreach ((array) $columns as $column) {
            $this->drops['columns'][] = $column;
        }
    }

    /**
     * Supprime la clé primaire
     */
    public function dropPrimary(?string $name = null): void
    {
        $this->drops['primary'] = $name ?? 'primary';
    }

    /**
     * Supprime un index unique
     */
    public function dropUnique(array|string $columns): void
    {
        $this->dropNamedIndex('unique', $columns);
    }

    /**
     * Supprime un index simple
     */
    public function dropIndex(array|string $columns): void
    {
        $this->dropNamedIndex('index', $columns);
    }

    /**
     * Supprime un index FULLTEXT
     */
    public function dropFullText(array|string $columns): void
    {
        $this->dropNamedIndex('fulltext', $columns);
    }

    /**
     * Supprime un index spatial
     */
    public function dropSpatialIndex(array|string $columns): void
    {
        $this->dropNamedIndex('spatialIndex', $columns);
    }

    /**
     * Supprime une clé étrangère
     */
    public function dropForeign(array|string $columns): void
    {
        $this->dropNamedIndex('foreign', $columns);
    }

    /**
     * Supprime les colonnes de timestamps
     */
    public function dropTimestamps(): void
    {
        $this->dropColumn(['created_at', 'updated_at']);
    }

    /**
     * Supprime les colonnes de timestamps avec fuseau horaire
     */
    public function dropTimestampsTz(): void
    {
        $this->dropTimestamps();
    }

    /**
     * Supprime la colonne de suppression logique
     */
    public function dropSoftDeletes(string $column = 'deleted_at'): void
    {
        $this->dropColumn($column);
    }

    /**
     * Supprime la colonne de suppression logique avec fuseau horaire
     */
    public function dropSoftDeletesTz(string $column = 'deleted_at'): void
    {
        $this->dropSoftDeletes($column);
    }

    /**
     * Supprime la colonne remember_token
     */
    public function dropRememberToken(): void
    {
        $this->dropColumn('remember_token');
    }

    /**
     * Supprime les colonnes polymorphiques
     */
    public function dropMorphs(string $name, ?string $indexName = null): void
    {
        $this->dropIndex($indexName ?? $this->createIndexName('index', ["{$name}_type", "{$name}_id"]));

        $this->dropColumn(["{$name}_type", "{$name}_id"]);
    }

    /*
    |--------------------------------------------------------------------------
    | Méthodes internes
    |--------------------------------------------------------------------------
    */

    /**
     * Ajoute une colonne
     */
    protected function addColumn(string $type, string $name, array $attributes = []): Column
    {
        return $this->addColumnDefinition(new Column(
            array_merge(['type' => $type, 'name' => $name], $attributes),
        ));
    }

    /**
     * Ajoute une définition de colonne
     */
    protected function addColumnDefinition(Column $definition): Column
    {
        $this->columns[] = $definition;

        if ($this->after) {
            $definition->after($this->after);

            $this->after = $definition->name;
        }

        return $definition;
    }

    /**
     * Ajoute un index
     */
    protected function addIndex(string $type, array|string $columns, ?string $name, ?string $algorithm = null): Index
    {
        $columns = (array) $columns;
        $name ??= $this->createIndexName($type, $columns);
        $index           = new Index(array_filter(compact('type', 'name', 'columns', 'algorithm')));
        $this->indexes[] = $index;

        return $index;
    }

    /**
     * Ajoute une clé étrangère
     */
    protected function addForeignKey(array|string $columns, ?string $name): ForeignKey
    {
        $columns = (array) $columns;
        $name ??= $this->createIndexName('foreign', $columns);
        $fk                  = new ForeignKey(compact('name', 'columns'));
        $this->foreignKeys[] = $fk;

        return $fk;
    }

    /**
     * Supprime un index nommé
     */
    protected function dropNamedIndex(string $type, array|string $columns): void
    {
        if (is_string($columns)) {
            $this->drops[$type][] = $columns;
        } else {
            $name                 = $this->createIndexName($type, $columns);
            $this->drops[$type][] = $name;
        }
    }

    /**
     * Crée un nom d'index par défaut
     *
     * Exemple : users_email_unique pour un index unique sur la colonne email de la table users
     *
     * @internal
     */
    public function createIndexName(string $type, array $columns): string
    {
        $index = strtolower($this->table . '_' . implode('_', $columns) . '_' . $type);

        return str_replace(['-', '.'], '_', $index);
    }

    /**
     * Supprime une colonne du blueprint
     */
    public function removeColumn(string $column): self
    {
        $this->columns = array_values(array_filter($this->columns, static fn ($c) => $c->name !== $column));

        return $this;
    }

    /*
    |--------------------------------------------------------------------------
    | Getters pour Executor
    |--------------------------------------------------------------------------
    */

    /**
     * Récupère le nom de la table
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Récupère le préfixe de la table
     */
    public function getPrefix(): string
    {
        return $this->prefix ?? '';
    }

    /**
     * Récupère l'action à effectuer
     */
    public function getAction(): string
    {
        return $this->action;
    }

    /**
     * Récupère les colonnes
     *
     * @return list<Column>
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * Récupère les colonnes ajoutées (non modifiées)
     *
     * @return list<Column>
     */
    public function getAddedColumns(): array
    {
        return array_filter($this->columns, static fn ($column) => ! $column->change);
    }

    /**
     * Récupère les colonnes modifiées
     *
     * @return list<Column>
     */
    public function getChangedColumns(): array
    {
        return array_filter($this->columns, static fn ($column) => (bool) $column->change);
    }

    /**
     * Récupère les index
     *
     * @return list<Index>
     */
    public function getIndexes(): array
    {
        return $this->indexes;
    }

    /**
     * Récupère les clés étrangères
     *
     * @return list<ForeignKey>
     */
    public function getForeignKeys(): array
    {
        return $this->foreignKeys;
    }

    /**
     * Récupère les éléments à supprimer
     */
    public function getDrops(): array
    {
        return $this->drops;
    }

    /**
     * Récupère les informations de renommage
     */
    public function getRenames(): array
    {
        return $this->renames;
    }

    /**
     * Récupère le moteur de stockage
     */
    public function getEngine(): ?string
    {
        return $this->engine ?? null;
    }

    /**
     * Récupère le jeu de caractères
     */
    public function getCharset(): ?string
    {
        return $this->charset ?? null;
    }

    /**
     * Récupère la collation
     */
    public function getCollation(): ?string
    {
        return $this->collation ?? null;
    }

    /**
     * Vérifie si la table est temporaire
     */
    public function isTemporary(): bool
    {
        return $this->temporary ?? false;
    }

    /**
     * Récupère le commentaire
     */
    public function getComment(): ?string
    {
        return $this->comment ?? null;
    }
}
