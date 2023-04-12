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

use BlitzPHP\Database\Migration\Definitions\Column;
use BlitzPHP\Database\Migration\Definitions\ForeignId;
use BlitzPHP\Database\Migration\Definitions\ForeignKey;
use BlitzPHP\Utilities\Support\Fluent;
use Closure;

/**
 * Classe pour define la structure de la table a migrer.
 *
 * @credit <a href="https://laravel.com">Laravel Framework - Illuminate\Database\Schema\Blueprint</a>
 */
class Structure
{
    /**
     * Nom de la table
     */
    protected string $table;

    /**
     * Prefixe de la table
     */
    protected string $prefix;

    /**
     * @var Column[] Colonnes que l'on veut ajouter a la table
     */
    protected array $columns = [];

    /**
     * @var Fluent[] Commandes qu'on souhaite executer sur la table.
     */
    protected array $commands = [];

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
     * Doit-on creer une table temporaire.
     */
    public bool $temporary = false;

    /**
     * The column to add new columns after.
     */
    public string $after = '';

    public function __construct(string $table, ?Closure $callback = null, string $prefix = '')
    {
        $this->table  = $table;
        $this->prefix = $prefix;

        if (null !== $callback) {
            $callback($this);
        }
    }

    /**
     * Indique qu'on veut ajouter une colonne a la table
     */
    public function add(): Fluent
    {
        return $this->addCommand('add');
    }

    /**
     * Indique qu'on veut creer la table.
     */
    public function create(bool $ifNotExists = false): Fluent
    {
        return $this->addCommand('create', compact('ifNotExists'));
    }

    /**
     * Indique qu'on veut modifier la table.
     */
    public function modify(): Fluent
    {
        return $this->addCommand('modify');
    }

    /**
     * Indique qu'on veut une table temporaire.
     *
     * @return void
     */
    public function temporary()
    {
        $this->temporary = true;
    }

    /**
     * Indique qu'on veut supprimer la table.
     */
    public function drop(bool $ifExists = false): Fluent
    {
        if ($ifExists) {
            return $this->dropIfExists();
        }

        return $this->addCommand('drop');
    }

    /**
     * Indique qu'on veut supprimer la table si elle existe.
     */
    public function dropIfExists(): Fluent
    {
        return $this->addCommand('dropIfExists');
    }

    /**
     * Indique qu'on veut supprimer un champs.
     *
     * @param array|mixed $columns
     */
    public function dropColumn($columns): Fluent
    {
        $columns = is_array($columns) ? $columns : func_get_args();

        return $this->addCommand('dropColumn', compact('columns'));
    }

    /**
     * Indique qu'on veut renommer un champs.
     */
    public function renameColumn(string $from, string $to): Fluent
    {
        return $this->addCommand('renameColumn', compact('from', 'to'));
    }

    /**
     * Indique qu'on veut supprimer une cle primaire.
     */
    public function dropPrimary(string|array|null $index = null): Fluent
    {
        return $this->dropIndexCommand('dropPrimary', 'primary', $index);
    }

    /**
     * Indique qu'on veut supprimer une cle unique.
     */
    public function dropUnique(string|array $index): Fluent
    {
        return $this->dropIndexCommand('dropUnique', 'unique', $index);
    }

    /**
     * Indique qu'on veut supprimer un index.
     */
    public function dropIndex(string|array $index): Fluent
    {
        return $this->dropIndexCommand('dropIndex', 'index', $index);
    }

    /**
     * Indicate that the given fulltext index should be dropped.
     */
    public function dropFullText(string|array $index): Fluent
    {
        return $this->dropIndexCommand('dropFullText', 'fulltext', $index);
    }

    /**
     * Indique qu'on veut supprimer un index spacial.
     */
    public function dropSpatialIndex(string|array $index): Fluent
    {
        return $this->dropIndexCommand('dropSpatialIndex', 'spatialIndex', $index);
    }

    /**
     * Indique qu'on veut supprimer une cle etrangere.
     */
    public function dropForeign(string|array $index): Fluent
    {
        return $this->dropIndexCommand('dropForeign', 'foreign', $index);
    }

    /**
     * Indicate that the given column and foreign key should be dropped.
     */
    public function dropConstrainedForeignId(string $column): Column
    {
        $this->dropForeign([$column]);

        return $this->dropColumn($column);
    }

    /**
     * Indique qu'on veut renommer un indexe.
     */
    public function renameIndex(string $from, string $to): Fluent
    {
        return $this->addCommand('renameIndex', compact('from', 'to'));
    }

    /**
     * Indique qu'on veut supprimer les colones de type timestamp.
     */
    public function dropTimestamps(): Column
    {
        return $this->dropColumn('created_at', 'updated_at');
    }

    /**
     * Indique qu'on veut supprimer les colones de type timestamp.
     */
    public function dropTimestampsTz(): Column
    {
        return $this->dropTimestamps();
    }

    /**
     * Indicate that the soft delete column should be dropped.
     */
    public function dropSoftDeletes(string $column = 'deleted_at'): void
    {
        $this->dropColumn($column);
    }

    /**
     * Indicate that the soft delete column should be dropped.
     */
    public function dropSoftDeletesTz(string $column = 'deleted_at'): void
    {
        $this->dropSoftDeletes($column);
    }

    /**
     * Indicate that the remember token column should be dropped.
     */
    public function dropRememberToken(): void
    {
        $this->dropColumn('remember_token');
    }

    /**
     * Indique qu'on veut supprimer les colones polymorphe.
     *
     * @return void
     */
    public function dropMorphs(string $name, ?string $indexName = null)
    {
        $this->dropIndex($indexName ?: $this->createIndexName('index', ["{$name}_type", "{$name}_id"]));

        $this->dropColumn("{$name}_type", "{$name}_id");
    }

    /**
     * Rennome la table avec le nom donné.
     */
    public function rename(string $to): Fluent
    {
        return $this->addCommand('rename', compact('to'));
    }

    /**
     * Specifie les clés primaire de la table.
     */
    public function primary(string|array $columns, ?string $name = null, ?string $algorithm = null): Fluent
    {
        return $this->indexCommand('primary', $columns, $name, $algorithm);
    }

    /**
     * Specifie un indexe unique pour la table.
     */
    public function unique(string|array $columns, ?string $name = null, ?string $algorithm = null): Fluent
    {
        return $this->indexCommand('unique', $columns, $name, $algorithm);
    }

    /**
     * Specifie un index pour la table.
     */
    public function index(string|array $columns, ?string $name = null, ?string $algorithm = null): Fluent
    {
        return $this->indexCommand('index', $columns, $name, $algorithm);
    }

    /**
     * Specify an fulltext for the table.
     */
    public function fullText(string|array $columns, ?string $name = null, ?string $algorithm = null): Fluent
    {
        return $this->indexCommand('fulltext', $columns, $name, $algorithm);
    }

    /**
     * Specifie un index spacial pour la table.
     */
    public function spatialIndex(string|array $columns, ?string $name = null): Fluent
    {
        return $this->indexCommand('spatialIndex', $columns, $name);
    }

    /**
     * Specifie une clé étrangère pour la table.
     *
     * @return ForeignKey
     */
    public function foreign(string|array $columns, ?string $name = null): Fluent
    {
        return $this->indexCommand('foreign', $columns, $name);
    }

    /**
     * Create a new auto-incrementing big integer (8-byte) column on the table.
     */
    public function id(string $column = 'id'): Column
    {
        return $this->bigIncrements($column);
    }

    /**
     * Créé une nouvelle colonne de type entier auto-incrementé (4-byte) sur la table.
     */
    public function increments(string $column): Column
    {
        return $this->unsignedInteger($column, true)->primary();
    }

    /**
     * Créé une nouvelle colonne de type entier auto-incrementé (4-byte) sur la table.
     */
    public function integerIncrements(string $column): Column
    {
        return $this->increments($column);
    }

    /**
     * Créé une nouvelle colonne de type tiny-integer auto-incrementé (1-byte) sur la table.
     */
    public function tinyIncrements(string $column): Column
    {
        return $this->unsignedTinyInteger($column, true)->primary();
    }

    /**
     * Créé une nouvelle colonne de type small integer auto-incrementé (2-byte) sur la table.
     */
    public function smallIncrements(string $column): Column
    {
        return $this->unsignedSmallInteger($column, true)->primary();
    }

    /**
     * Create a new auto-incrementing medium integer (3-byte) column on the table.
     */
    public function mediumIncrements(string $column): Column
    {
        return $this->unsignedMediumInteger($column, true)->primary();
    }

    /**
     * Create a new auto-incrementing big integer (8-byte) column on the table.
     */
    public function bigIncrements(string $column): Column
    {
        return $this->unsignedBigInteger($column, true)->primary();
    }

    /**
     * Create a new char column on the table.
     */
    public function char(string $column, int $length = 255): Column
    {
        $length = max($length, 1);

        return $this->addColumn('char', $column, compact('length'));
    }

    /**
     * Create a new string column on the table.
     */
    public function string(string $column, int $length = 255): Column
    {
        $length = max($length, 1);

        return $this->addColumn('string', $column, compact('length'));
    }

    /**
     * Create a new tiny text column on the table.
     */
    public function tinyText(string $column): Column
    {
        return $this->addColumn('tinyText', $column);
    }

    /**
     * Create a new text column on the table.
     */
    public function text(string $column): Column
    {
        return $this->addColumn('text', $column);
    }

    /**
     * Create a new medium text column on the table.
     */
    public function mediumText(string $column): Column
    {
        return $this->addColumn('mediumText', $column);
    }

    /**
     * Create a new long text column on the table.
     */
    public function longText(string $column): Column
    {
        return $this->addColumn('longText', $column);
    }

    /**
     * Create a new integer (4-byte) column on the table.
     */
    public function integer(string $column, bool $autoIncrement = false, bool $unsigned = false): Column
    {
        return $this->addColumn('integer', $column, compact('autoIncrement', 'unsigned'));
    }

    /**
     * Create a new tiny integer (1-byte) column on the table.
     */
    public function tinyInteger(string $column, bool $autoIncrement = false, bool $unsigned = false): Column
    {
        return $this->addColumn('tinyInteger', $column, compact('autoIncrement', 'unsigned'));
    }

    /**
     * Create a new small integer (2-byte) column on the table.
     */
    public function smallInteger(string $column, bool $autoIncrement = false, bool $unsigned = false): Column
    {
        return $this->addColumn('smallInteger', $column, compact('autoIncrement', 'unsigned'));
    }

    /**
     * Create a new medium integer (3-byte) column on the table.
     */
    public function mediumInteger(string $column, bool $autoIncrement = false, bool $unsigned = false): Column
    {
        return $this->addColumn('mediumInteger', $column, compact('autoIncrement', 'unsigned'));
    }

    /**
     * Create a new big integer (8-byte) column on the table.
     */
    public function bigInteger(string $column, bool $autoIncrement = false, bool $unsigned = false): Column
    {
        return $this->addColumn('bigInteger', $column, compact('autoIncrement', 'unsigned'));
    }

    /**
     * Create a new unsigned integer (4-byte) column on the table.
     */
    public function unsignedInteger(string $column, bool $autoIncrement = false): Column
    {
        return $this->integer($column, $autoIncrement, true);
    }

    /**
     * Create a new unsigned tiny integer (1-byte) column on the table.
     */
    public function unsignedTinyInteger(string $column, bool $autoIncrement = false): Column
    {
        return $this->tinyInteger($column, $autoIncrement, true);
    }

    /**
     * Create a new unsigned small integer (2-byte) column on the table.
     */
    public function unsignedSmallInteger(string $column, bool $autoIncrement = false): Column
    {
        return $this->smallInteger($column, $autoIncrement, true);
    }

    /**
     * Create a new unsigned medium integer (3-byte) column on the table.
     */
    public function unsignedMediumInteger(string $column, bool $autoIncrement = false): Column
    {
        return $this->mediumInteger($column, $autoIncrement, true);
    }

    /**
     * Create a new unsigned big integer (8-byte) column on the table.
     */
    public function unsignedBigInteger(string $column, bool $autoIncrement = false): Column
    {
        return $this->bigInteger($column, $autoIncrement, true);
    }

    /**
     * Create a new unsigned big integer (8-byte) column on the table.
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
     * Create a new float column on the table.
     *
     * @param mixed $unsigned
     */
    public function float(string $column, int $total = 8, int $places = 2, $unsigned = false): Column
    {
        return $this->addColumn('float', $column, compact('total', 'places', 'unsigned'));
    }

    /**
     * Create a new double column on the table.
     *
     * @param mixed $unsigned
     */
    public function double(string $column, ?int $total = null, ?int $places = null, $unsigned = false): Column
    {
        return $this->addColumn('double', $column, compact('total', 'places', 'unsigned'));
    }

    /**
     * Create a new decimal column on the table.
     *
     * @param mixed $unsigned
     */
    public function decimal(string $column, int $total = 8, int $places = 2, $unsigned = false): Column
    {
        return $this->addColumn('decimal', $column, compact('total', 'places', 'unsigned'));
    }

    /**
     * Create a new unsigned float column on the table.
     */
    public function unsignedFloat(string $column, int $total = 8, int $places = 2): Column
    {
        return $this->float($column, $total, $places, true);
    }

    /**
     * Create a new unsigned double column on the table.
     */
    public function unsignedDouble(string $column, ?int $total = null, ?int $places = null): Column
    {
        return $this->double($column, $total, $places, true);
    }

    /**
     * Create a new unsigned decimal column on the table.
     */
    public function unsignedDecimal(string $column, int $total = 8, int $places = 2): Column
    {
        return $this->decimal($column, $total, $places, true);
    }

    /**
     * Create a new boolean column on the table.
     */
    public function boolean(string $column): Column
    {
        return $this->addColumn('boolean', $column);
    }

    /**
     * Create a new enum column on the table.
     */
    public function enum(string $column, array $allowed): Column
    {
        return $this->addColumn('enum', $column, compact('allowed'));
    }

    /**
     * Create a new set column on the table.
     */
    public function set(string $column, array $allowed): Column
    {
        return $this->addColumn('set', $column, compact('allowed'));
    }

    /**
     * Create a new json column on the table.
     */
    public function json(string $column): Column
    {
        return $this->addColumn('json', $column);
    }

    /**
     * Create a new jsonb column on the table.
     */
    public function jsonb(string $column): Column
    {
        return $this->addColumn('jsonb', $column);
    }

    /**
     * Create a new date column on the table.
     */
    public function date(string $column): Column
    {
        return $this->addColumn('date', $column);
    }

    /**
     * Create a new date-time column on the table.
     */
    public function dateTime(string $column, int $precision = 0): Column
    {
        return $this->addColumn('dateTime', $column, compact('precision'));
    }

    /**
     * Create a new date-time column (with time zone) on the table.
     */
    public function dateTimeTz(string $column, int $precision = 0): Column
    {
        return $this->addColumn('dateTimeTz', $column, compact('precision'));
    }

    /**
     * Create a new time column on the table.
     */
    public function time(string $column, int $precision = 0): Column
    {
        return $this->addColumn('time', $column, compact('precision'));
    }

    /**
     * Create a new time column (with time zone) on the table.
     */
    public function timeTz(string $column, int $precision = 0): Column
    {
        return $this->addColumn('timeTz', $column, compact('precision'));
    }

    /**
     * Create a new timestamp column on the table.
     */
    public function timestamp(string $column, int $precision = 0): Column
    {
        return $this->addColumn('timestamp', $column, compact('precision'));
    }

    /**
     * Create a new timestamp (with time zone) column on the table.
     */
    public function timestampTz(string $column, int $precision = 0): Column
    {
        return $this->addColumn('timestampTz', $column, compact('precision'));
    }

    /**
     * Add nullable creation and update timestamps to the table.
     */
    public function timestamps(int $precision = 0): void
    {
        $this->timestamp('created_at', $precision)->nullable();

        $this->timestamp('updated_at', $precision)->nullable();
    }

    /**
     * Add nullable creation and update timestamps to the table.
     *
     * Alias for self::timestamps().
     */
    public function nullableTimestamps(int $precision = 0): void
    {
        $this->timestamps($precision);
    }

    /**
     * Add creation and update timestampTz columns to the table.
     *
     * @return void
     */
    public function timestampsTz(int $precision = 0)
    {
        $this->timestampTz('created_at', $precision)->nullable();

        $this->timestampTz('updated_at', $precision)->nullable();
    }

    /**
     * Add a "deleted at" timestamp for the table.
     */
    public function softDeletes(string $column = 'deleted_at', int $precision = 0): Column
    {
        return $this->timestamp($column, $precision)->nullable();
    }

    /**
     * Add a "deleted at" timestampTz for the table.
     */
    public function softDeletesTz(string $column = 'deleted_at', int $precision = 0): Column
    {
        return $this->timestampTz($column, $precision)->nullable();
    }

    /**
     * Create a new year column on the table.
     */
    public function year(string $column): Column
    {
        return $this->addColumn('year', $column);
    }

    /**
     * Create a new binary column on the table.
     */
    public function binary(string $column): Column
    {
        return $this->addColumn('binary', $column);
    }

    /**
     * Create a new uuid column on the table.
     */
    public function uuid(string $column): Column
    {
        return $this->addColumn('uuid', $column);
    }

    /**
     * Create a new UUID column on the table with a foreign key constraint.
     */
    public function foreignUuid(string $column): ForeignId
    {
        return $this->addColumnDefinition(new ForeignId($this, [
            'type' => 'uuid',
            'name' => $column,
        ]));
    }

    /**
     * Create a new ULID column on the table.
     */
    public function ulid(string $column = 'uuid', int $length = 26): Column
    {
        return $this->char($column, $length);
    }

    /**
     * Create a new ULID column on the table with a foreign key constraint.
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
     * Create a new IP address column on the table.
     */
    public function ipAddress(string $column = 'ip_address'): Column
    {
        return $this->addColumn('ipAddress', $column);
    }

    /**
     * Create a new MAC address column on the table.
     */
    public function macAddress(string $column = 'mac_address'): Column
    {
        return $this->addColumn('macAddress', $column);
    }

    /**
     * Create a new geometry column on the table.
     */
    public function geometry(string $column): Column
    {
        return $this->addColumn('geometry', $column);
    }

    /**
     * Create a new point column on the table.
     */
    public function point(string $column, ?int $srid = null): Column
    {
        return $this->addColumn('point', $column, compact('srid'));
    }

    /**
     * Create a new linestring column on the table.
     */
    public function lineString(string $column): Column
    {
        return $this->addColumn('linestring', $column);
    }

    /**
     * Create a new polygon column on the table.
     */
    public function polygon(string $column): Column
    {
        return $this->addColumn('polygon', $column);
    }

    /**
     * Create a new geometrycollection column on the table.
     */
    public function geometryCollection(string $column): Column
    {
        return $this->addColumn('geometrycollection', $column);
    }

    /**
     * Create a new multipoint column on the table.
     */
    public function multiPoint(string $column): Column
    {
        return $this->addColumn('multipoint', $column);
    }

    /**
     * Create a new multilinestring column on the table.
     */
    public function multiLineString(string $column): Column
    {
        return $this->addColumn('multilinestring', $column);
    }

    /**
     * Create a new multipolygon column on the table.
     */
    public function multiPolygon(string $column): Column
    {
        return $this->addColumn('multipolygon', $column);
    }

    /**
     * Create a new multipolygon column on the table.
     */
    public function multiPolygonZ(string $column): Column
    {
        return $this->addColumn('multipolygonz', $column);
    }

    /**
     * Create a new generated, computed column on the table.
     */
    public function computed(string $column, string $expression): Column
    {
        return $this->addColumn('computed', $column, compact('expression'));
    }

    /**
     * Add the proper columns for a polymorphic table.
     */
    public function morphs(string $name, ?string $indexName = null): void
    {
        $this->string("{$name}_type");

        $this->unsignedBigInteger("{$name}_id");

        $this->index(["{$name}_type", "{$name}_id"], $indexName);
    }

    /**
     * Add nullable columns for a polymorphic table.
     */
    public function nullableMorphs(string $name, ?string $indexName = null): void
    {
        $this->string("{$name}_type")->nullable();

        $this->unsignedBigInteger("{$name}_id")->nullable();

        $this->index(["{$name}_type", "{$name}_id"], $indexName);
    }

    /**
     * Add the proper columns for a polymorphic table using numeric IDs (incremental).
     */
    public function numericMorphs(string $name, ?string $indexName = null): void
    {
        $this->string("{$name}_type");

        $this->unsignedBigInteger("{$name}_id");

        $this->index(["{$name}_type", "{$name}_id"], $indexName);
    }

    /**
     * Add nullable columns for a polymorphic table using numeric IDs (incremental).
     */
    public function nullableNumericMorphs(string $name, ?string $indexName = null): void
    {
        $this->string("{$name}_type")->nullable();

        $this->unsignedBigInteger("{$name}_id")->nullable();

        $this->index(["{$name}_type", "{$name}_id"], $indexName);
    }

    /**
     * Add the proper columns for a polymorphic table using UUIDs.
     */
    public function uuidMorphs(string $name, ?string $indexName = null): void
    {
        $this->string("{$name}_type");

        $this->uuid("{$name}_id");

        $this->index(["{$name}_type", "{$name}_id"], $indexName);
    }

    /**
     * Add nullable columns for a polymorphic table using UUIDs.
     */
    public function nullableUuidMorphs(string $name, ?string $indexName = null): void
    {
        $this->string("{$name}_type")->nullable();

        $this->uuid("{$name}_id")->nullable();

        $this->index(["{$name}_type", "{$name}_id"], $indexName);
    }

    /**
     * Add the proper columns for a polymorphic table using ULIDs.
     */
    public function ulidMorphs(string $name, ?string $indexName = null): void
    {
        $this->string("{$name}_type");

        $this->ulid("{$name}_id");

        $this->index(["{$name}_type", "{$name}_id"], $indexName);
    }

    /**
     * Add nullable columns for a polymorphic table using ULIDs.
     */
    public function nullableUlidMorphs(string $name, ?string $indexName = null): void
    {
        $this->string("{$name}_type")->nullable();

        $this->ulid("{$name}_id")->nullable();

        $this->index(["{$name}_type", "{$name}_id"], $indexName);
    }

    /**
     * Adds the `remember_token` column to the table.
     */
    public function rememberToken(): Column
    {
        return $this->string('remember_token', 100)->nullable();
    }

    /**
     * Add a comment to the table.
     */
    public function comment(string $comment): Fluent
    {
        return $this->addCommand('tableComment', compact('comment'));
    }

    /**
     * Add a new index command to the structure.
     */
    protected function indexCommand(string $type, array|string $columns, ?string $index = null, ?string $algorithm = null): Fluent
    {
        $columns = (array) $columns;

        // If no name was specified for this index, we will create one using a basic
        // convention of the table name, followed by the columns, followed by an
        // index type, such as primary or index, which makes the index unique.
        $index = $index ?: $this->createIndexName($type, $columns);

        return $this->addCommand(
            $type,
            compact('index', 'columns', 'algorithm')
        );
    }

    /**
     * Cree une nouvelle commande de suppression d'indexe dans la structure.
     */
    protected function dropIndexCommand(string $command, string $type, string|array $index): Fluent
    {
        $columns = [];

        // If the given "index" is actually an array of columns, the developer means
        // to drop an index merely by specifying the columns involved without the
        // conventional name, so we will build the index name from the columns.
        if (is_array($index)) {
            $index = $this->createIndexName($type, $columns = $index);
        }

        return $this->indexCommand($command, $columns, $index);
    }

    /**
     * Create a default index name for the table.
     */
    protected function createIndexName(string $type, array $columns): string
    {
        $index = strtolower($this->prefix . $this->table . '_' . implode('_', $columns) . '_' . $type);

        return str_replace(['-', '.'], '_', $index);
    }

    /**
     * Ajoute un champ a la structure.
     */
    public function addColumn(string $type, string $name, array $parameters = []): Column
    {
        return $this->addColumnDefinition(new Column(
            array_merge(compact('type', 'name'), $parameters)
        ));
    }

    /**
     * Retire un champ a la structure
     */
    public function removeColumn(string $name): self
    {
        $this->columns = array_values(array_filter($this->columns, static fn ($c) => $c['name'] !== $name));

        return $this;
    }

    /**
     * Add the columns from the callback after the given column.
     */
    public function after(string $column, Closure $callback): void
    {
        $this->after = $column;

        $callback($this);

        $this->after = null;
    }

    /**
     * Add a new column definition to the structure.
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
     * Ajoute une nouvelle commande a la structure
     */
    protected function addCommand(string $name, array $parameters = []): Fluent
    {
        $this->commands[] = $command = $this->createCommand($name, $parameters);

        return $command;
    }

    /**
     * Cree une nouvelle commande
     */
    protected function createCommand(string $name, array $parameters = []): Fluent
    {
        return new Fluent(array_merge(compact('name'), $parameters));
    }

    /**
     * Recupere la table que la structure decrit.
     *
     * @internal utilisee par le `transformer`
     */
    public function getTable(): string
    {
        return $this->table;
    }

    /**
     * Get the columns on the schema.
     *
     * @return Column[]
     *
     * @internal utilisee par le `transformer`
     */
    public function getColumns(?bool $added = null): array
    {
        if ($added === null) {
            return $this->columns;
        }

        if ($added === true) {
            return $this->getAddedColumns();
        }

        return $this->getChangedColumns();
    }

    /**
     * Get the commands on the schema.
     *
     * @return Fluent[]
     *
     * @internal utilisee par le `transformer`
     */
    public function getCommands(): array
    {
        return $this->commands;
    }

    /**
     * Recupere les colones de la structure qui doivent etre ajoutees.
     *
     * @return Column[]
     *
     * @internal utilisee par le `transformer`
     */
    public function getAddedColumns(): array
    {
        return array_filter($this->columns, static fn ($column) => ! $column->change);
    }

    /**
     * Recupere les colones de la structure qui doivent etre modifiees.
     *
     * @return Column[]
     *
     * @internal utilisee par le `transformer`
     */
    public function getChangedColumns(): array
    {
        return array_filter($this->columns, static fn ($column) => (bool) $column->change);
    }
}
