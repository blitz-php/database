<?php 

namespace BlitzPHP\Database\Migration;

use BlitzPHP\Database\Migration\Definitions\Column;
use BlitzPHP\Database\Migration\Definitions\ForeignKey;
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
     * @var \BlitzPHP\Utilities\Fluent[] Commandes qu'on souhaite executer sur la table.
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


    public function __construct(string $table, ?Closure $callback = null, string $prefix = '')
    {
        $this->table = $table;
        $this->prefix = $prefix;

        if (! is_null($callback)) {
            $callback($this);
        }
    }

    /**
     * Indique qu'on veut ajouter une colonne a la table
     */
    public function add(): Column
    {
        return $this->addCommand('add');
    }
    
    /**
     * Indique qu'on veut creer la table.
     */
    public function create(bool $ifNotExists = false): Column
    {
        return $this->addCommand('create', compact('ifNotExists'));
    }

    /**
     * Indique qu'on veut modifier la table.
     */
    public function modify(): Column
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
    public function drop(bool $ifExists = false): Column
    {
        if ($ifExists) {
            return $this->dropIfExists();
        }

        return $this->addCommand('drop');
    }

    /**
     * Indique qu'on veut supprimer la table si elle existe.
     */
    public function dropIfExists(): Column
    {
        return $this->addCommand('dropIfExists');
    }

    /**
     * Indique qu'on veut supprimer un champs.
     *
     * @param  array|mixed  $columns
     */
    public function dropColumn($columns): Column
    {
        $columns = is_array($columns) ? $columns : func_get_args();

        return $this->addCommand('dropColumn', compact('columns'));
    }

    /**
     * Indique qu'on veut renommer un champs.
     */
    public function renameColumn(string $from, string $to): Column
    {
        return $this->addCommand('renameColumn', compact('from', 'to'));
    }

    /**
     * Indique qu'on veut supprimer une cle primaire.
     */
    public function dropPrimary(string|array|null $index = null): Column
    {
        return $this->dropIndexCommand('dropPrimary', 'primary', $index);
    }

    /**
     * Indique qu'on veut supprimer une cle unique.
     */
    public function dropUnique(string|array $index): Column
    {
        return $this->dropIndexCommand('dropUnique', 'unique', $index);
    }

    /**
     * Indique qu'on veut supprimer un index.
     */
    public function dropIndex(string|array $index): Column
    {
        return $this->dropIndexCommand('dropIndex', 'index', $index);
    }

    /**
     * Indique qu'on veut supprimer un index spacial.
     */
    public function dropSpatialIndex(string|array $index): Column
    {
        return $this->dropIndexCommand('dropSpatialIndex', 'spatialIndex', $index);
    }

    /**
     * Indique qu'on veut supprimer une cle etrangere.
     */
    public function dropForeign(string|array $index): Column
    {
        return $this->dropIndexCommand('dropForeign', 'foreign', $index);
    }

    /**
     * Indique qu'on veut renommer un indexe.
     */
    public function renameIndex(string $from, string $to): Column
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
    public function rename(string $to): Column
    {
        return $this->addCommand('rename', compact('to'));
    }

    /**
     * Specifie les clés primaire de la table.
     */
    public function primary(string|array $columns, ?string $name = null, ?string $algorithm = null): Column
    {
        return $this->indexCommand('primary', $columns, $name, $algorithm);
    }

    /**
     * Specifie un indexe unique pour la table.
     */
    public function unique(string|array $columns, ?string $name = null, ?string $algorithm = null): Column
    {
        return $this->indexCommand('unique', $columns, $name, $algorithm);
    }

    /**
     * Specifie un index pour la table.
     */
    public function index(string|array $columns, ?string $name = null, ?string $algorithm = null): Column
    {
        return $this->indexCommand('index', $columns, $name, $algorithm);
    }

    /**
     * Specifie un index spacial pour la table.
     */
    public function spatialIndex(string|array $columns, ?string $name = null): Column
    {
        return $this->indexCommand('spatialIndex', $columns, $name);
    }

    /**
     * Specifie une clé étrangère pour la table.
     */
    public function foreign(string|array $columns, ?string $name = null): ForeignKey|Column
    {
        return $this->indexCommand('foreign', $columns, $name);
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
    public function mediumIncrements(string $column) : Column
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
    public function string(string $column, int $length = 255) : Column
    {
        $length = max($length, 1);

        return $this->addColumn('string', $column, compact('length'));
    }

    /**
     * Create a new text column on the table.
     */
    public function text(string $column) : Column
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
    public function longText(string $column) : Column
    {
        return $this->addColumn('longText', $column);
    }

    /**
     * Create a new integer (4-byte) column on the table.
     *
     * @param  string  $column
     * @param  bool  $autoIncrement
     * @param  bool  $unsigned
     * @return Column
     */
    public function integer(string $column, bool $autoIncrement = false, bool $unsigned = false) : Column
    {
        return $this->addColumn('integer', $column, compact('autoIncrement', 'unsigned'));
    }

    /**
     * Create a new tiny integer (1-byte) column on the table.
     *
     * @param  string  $column
     * @param  bool  $autoIncrement
     * @param  bool  $unsigned
     * @return Column
     */
    public function tinyInteger(string $column, bool $autoIncrement = false, bool $unsigned = false) : Column
    {
        return $this->addColumn('tinyInteger', $column, compact('autoIncrement', 'unsigned'));
    }

    /**
     * Create a new small integer (2-byte) column on the table.
     *
     * @param  string  $column
     * @param  bool  $autoIncrement
     * @param  bool  $unsigned
     * @return Column
     */
    public function smallInteger(string $column, bool $autoIncrement = false, bool $unsigned = false) : Column
    {
        return $this->addColumn('smallInteger', $column, compact('autoIncrement', 'unsigned'));
    }

    /**
     * Create a new medium integer (3-byte) column on the table.
     *
     * @param  string  $column
     * @param  bool  $autoIncrement
     * @param  bool  $unsigned
     * @return Column
     */
    public function mediumInteger(string $column, bool $autoIncrement = false, bool $unsigned = false) : Column
    {
        return $this->addColumn('mediumInteger', $column, compact('autoIncrement', 'unsigned'));
    }

    /**
     * Create a new big integer (8-byte) column on the table.
     *
     * @param  string  $column
     * @param  bool  $autoIncrement
     * @param  bool  $unsigned
     * @return Column
     */
    public function bigInteger(string $column, bool $autoIncrement = false, bool $unsigned = false) : Column
    {
        return $this->addColumn('bigInteger', $column, compact('autoIncrement', 'unsigned'));
    }

    /**
     * Create a new unsigned integer (4-byte) column on the table.
     *
     * @param  string  $column
     * @param  bool  $autoIncrement
     * @return Column
     */
    public function unsignedInteger(string $column, bool $autoIncrement = false) : Column
    {
        return $this->integer($column, $autoIncrement, true);
    }

    /**
     * Create a new unsigned tiny integer (1-byte) column on the table.
     *
     * @param  string  $column
     * @param  bool  $autoIncrement
     * @return Column
     */
    public function unsignedTinyInteger(string $column, bool $autoIncrement = false)
    {
        return $this->tinyInteger($column, $autoIncrement, true);
    }

    /**
     * Create a new unsigned small integer (2-byte) column on the table.
     *
     * @param  string  $column
     * @param  bool  $autoIncrement
     * @return Column
     */
    public function unsignedSmallInteger(string $column, bool $autoIncrement = false) : Column
    {
        return $this->smallInteger($column, $autoIncrement, true);
    }

    /**
     * Create a new unsigned medium integer (3-byte) column on the table.
     *
     * @param  string  $column
     * @param  bool  $autoIncrement
     * @return Column
     */
    public function unsignedMediumInteger(string $column, bool $autoIncrement = false) : Column
    {
        return $this->mediumInteger($column, $autoIncrement, true);
    }

    /**
     * Create a new unsigned big integer (8-byte) column on the table.
     *
     * @param  string  $column
     * @param  bool  $autoIncrement
     * @return Column
     */
    public function unsignedBigInteger(string $column, bool $autoIncrement = false) : Column
    {
        return $this->bigInteger($column, $autoIncrement, true);
    }

    /**
     * Create a new float column on the table.
     *
     * @param  string  $column
     * @param  int  $total
     * @param  int  $places
     * @return Column
     */
    public function float(string $column, int $total = 8, int $places = 2) : Column
    {
        return $this->addColumn('float', $column, compact('total', 'places'));
    }

    /**
     * Create a new double column on the table.
     *
     * @param  string  $column
     * @param  int|null  $total
     * @param  int|null  $places
     * @return Column
     */
    public function double(string $column, ?int $total = null, ?int $places = null) : Column
    {
        return $this->addColumn('double', $column, compact('total', 'places'));
    }

    /**
     * Create a new decimal column on the table.
     *
     * @param  string  $column
     * @param  int  $total
     * @param  int  $places
     * @return Column
     */
    public function decimal(string $column, int $total = 8, int $places = 2) : Column
    {
        return $this->addColumn('decimal', $column, compact('total', 'places'));
    }

    /**
     * Create a new unsigned decimal column on the table.
     *
     * @param  string  $column
     * @param  int  $total
     * @param  int  $places
     * @return Column
     */
    public function unsignedDecimal(string $column, int $total = 8, int $places = 2) : Column
    {
        return $this->addColumn('decimal', $column, [
            'total' => $total, 'places' => $places, 'unsigned' => true,
        ]);
    }

    /**
     * Create a new boolean column on the table.
     *
     * @param  string  $column
     * @return Column
     */
    public function boolean(string $column) : Column
    {
        return $this->addColumn('boolean', $column);
    }

    /**
     * Create a new enum column on the table.
     *
     * @param  string  $column
     * @param  array  $allowed
     * @return Column
     */
    public function enum(string $column, array $allowed) : Column
    {
        return $this->addColumn('enum', $column, compact('allowed'));
    }

    /**
     * Create a new set column on the table.
     *
     * @param  string  $column
     * @param  array  $allowed
     * @return Column
     */
    public function set(string $column, array $allowed) : Column
    {
        return $this->addColumn('set', $column, compact('allowed'));
    }

    /**
     * Create a new json column on the table.
     *
     * @param  string  $column
     * @return Column
     */
    public function json(string $column) : Column
    {
        return $this->addColumn('json', $column);
    }

    /**
     * Create a new jsonb column on the table.
     *
     * @param  string  $column
     * @return Column
     */
    public function jsonb(string $column) : Column
    {
        return $this->addColumn('jsonb', $column);
    }

    /**
     * Create a new date column on the table.
     *
     * @param  string  $column
     * @return Column
     */
    public function date(string $column) : Column
    {
        return $this->addColumn('date', $column);
    }

    /**
     * Create a new date-time column on the table.
     *
     * @param  string  $column
     * @param  int  $precision
     * @return Column
     */
    public function dateTime(string $column, int $precision = 0) : Column
    {
        return $this->addColumn('dateTime', $column, compact('precision'));
    }

    /**
     * Create a new date-time column (with time zone) on the table.
     *
     * @param  string  $column
     * @param  int  $precision
     * @return Column
     */
    public function dateTimeTz(string $column, int $precision = 0) : Column
    {
        return $this->addColumn('dateTimeTz', $column, compact('precision'));
    }

    /**
     * Create a new time column on the table.
     *
     * @param  string  $column
     * @param  int  $precision
     * @return Column
     */
    public function time(string $column, int $precision = 0) : Column
    {
        return $this->addColumn('time', $column, compact('precision'));
    }

    /**
     * Create a new time column (with time zone) on the table.
     *
     * @param  string  $column
     * @param  int  $precision
     * @return Column
     */
    public function timeTz(string $column, int $precision = 0) : Column
    {
        return $this->addColumn('timeTz', $column, compact('precision'));
    }

    /**
     * Create a new timestamp column on the table.
     *
     * @param  string  $column
     * @param  int  $precision
     * @return Column
     */
    public function timestamp(string $column, int $precision = 0) : Column
    {
        return $this->addColumn('timestamp', $column, compact('precision'));
    }

    /**
     * Create a new timestamp (with time zone) column on the table.
     *
     * @param  string  $column
     * @param  int  $precision
     * @return Column
     */
    public function timestampTz(string $column, int $precision = 0) : Column
    {
        return $this->addColumn('timestampTz', $column, compact('precision'));
    }

    /**
     * Add nullable creation and update timestamps to the table.
     *
     * @param  int  $precision
     * @return void
     */
    public function timestamps(int $precision = 0)
    {
        $this->timestamp('created_at', $precision)->nullable();

        $this->timestamp('updated_at', $precision)->nullable();
    }

    /**
     * Add nullable creation and update timestamps to the table.
     *
     * Alias for self::timestamps().
     *
     * @param  int  $precision
     * @return void
     */
    public function nullableTimestamps(int $precision = 0)
    {
        $this->timestamps($precision);
    }

    /**
     * Add creation and update timestampTz columns to the table.
     *
     * @param  int  $precision
     * @return void
     */
    public function timestampsTz(int $precision = 0)
    {
        $this->timestampTz('created_at', $precision)->nullable();

        $this->timestampTz('updated_at', $precision)->nullable();
    }

    /**
     * Create a new year column on the table.
     *
     * @param  string  $column
     * @return Column
     */
    public function year(string $column) : Column
    {
        return $this->addColumn('year', $column);
    }

    /**
     * Create a new binary column on the table.
     *
     * @param  string  $column
     * @return Column
     */
    public function binary(string $column) : Column
    {
        return $this->addColumn('binary', $column);
    }

    /**
     * Create a new uuid column on the table.
     *
     * @param  string  $column
     * @return Column
     */
    public function uuid(string $column) : Column
    {
        return $this->addColumn('uuid', $column);
    }

    /**
     * Create a new IP address column on the table.
     *
     * @param  string  $column
     * @return Column
     */
    public function ipAddress(string $column) : Column
    {
        return $this->addColumn('ipAddress', $column);
    }

    /**
     * Create a new MAC address column on the table.
     *
     * @param  string  $column
     * @return Column
     */
    public function macAddress(string $column) : Column
    {
        return $this->addColumn('macAddress', $column);
    }

    /**
     * Create a new geometry column on the table.
     *
     * @param  string  $column
     * @return Column
     */
    public function geometry(string $column) : Column
    {
        return $this->addColumn('geometry', $column);
    }

    /**
     * Create a new point column on the table.
     *
     * @param  string  $column
     * @param  int|null  $srid
     * @return Column
     */
    public function point(string $column, ?int $srid = null) : Column
    {
        return $this->addColumn('point', $column, compact('srid'));
    }

    /**
     * Create a new linestring column on the table.
     *
     * @param  string  $column
     * @return Column
     */
    public function lineString(string $column) : Column
    {
        return $this->addColumn('linestring', $column);
    }

    /**
     * Create a new polygon column on the table.
     *
     * @param  string  $column
     * @return Column
     */
    public function polygon(string $column) : Column
    {
        return $this->addColumn('polygon', $column);
    }

    /**
     * Create a new geometrycollection column on the table.
     *
     * @param  string  $column
     * @return Column
     */
    public function geometryCollection(string $column) : Column
    {
        return $this->addColumn('geometrycollection', $column);
    }

    /**
     * Create a new multipoint column on the table.
     *
     * @param  string  $column
     * @return Column
     */
    public function multiPoint(string $column) : Column
    {
        return $this->addColumn('multipoint', $column);
    }

    /**
     * Create a new multilinestring column on the table.
     *
     * @param  string  $column
     * @return Column
     */
    public function multiLineString(string $column) : Column
    {
        return $this->addColumn('multilinestring', $column);
    }

    /**
     * Create a new multipolygon column on the table.
     *
     * @param  string  $column
     * @return Column
     */
    public function multiPolygon(string $column) : Column
    {
        return $this->addColumn('multipolygon', $column);
    }

    /**
     * Create a new multipolygon column on the table.
     *
     * @param  string  $column
     * @return Column
     */
    public function multiPolygonZ(string $column) : Column
    {
        return $this->addColumn('multipolygonz', $column);
    }

    /**
     * Create a new generated, computed column on the table.
     *
     * @param  string  $column
     * @param  string  $expression
     * @return Column
     */
    public function computed(string $column, string $expression) : Column
    {
        return $this->addColumn('computed', $column, compact('expression'));
    }

    /**
     * Add the proper columns for a polymorphic table.
     *
     * @param  string  $name
     * @param  string|null  $indexName
     * @return void
     */
    public function morphs(string $name, ?string $indexName = null)
    {
        $this->string("{$name}_type");

        $this->unsignedBigInteger("{$name}_id");

        $this->index(["{$name}_type", "{$name}_id"], $indexName);
    }

    /**
     * Add nullable columns for a polymorphic table.
     *
     * @param  string  $name
     * @param  string|null  $indexName
     * @return void
     */
    public function nullableMorphs(string $name, ?string $indexName = null)
    {
        $this->string("{$name}_type")->nullable();

        $this->unsignedBigInteger("{$name}_id")->nullable();

        $this->index(["{$name}_type", "{$name}_id"], $indexName);
    }

    /**
     * Add the proper columns for a polymorphic table using UUIDs.
     *
     * @param  string  $name
     * @param  string|null  $indexName
     * @return void
     */
    public function uuidMorphs(string $name, ?string $indexName = null)
    {
        $this->string("{$name}_type");

        $this->uuid("{$name}_id");

        $this->index(["{$name}_type", "{$name}_id"], $indexName);
    }

    /**
     * Add nullable columns for a polymorphic table using UUIDs.
     *
     * @param  string  $name
     * @param  string|null  $indexName
     * @return void
     */
    public function nullableUuidMorphs(string $name, ?string $indexName = null)
    {
        $this->string("{$name}_type")->nullable();

        $this->uuid("{$name}_id")->nullable();

        $this->index(["{$name}_type", "{$name}_id"], $indexName);
    }

    /**
     * Adds the `remember_token` column to the table.
     *
     * @return Column
     */
    public function rememberToken()
    {
        return $this->string('remember_token', 100)->nullable();
    }

    /**
     * Add a new index command to the blueprint.
     *
     * @param  string  $type
     * @param  string|array  $columns
     * @param  string  $index
     * @param  string|null  $algorithm
     * @return Column
     */
    protected function indexCommand(string $type, $columns, ?string $index = null, ?string $algorithm = null) : Column
    {
        $columns = (array) $columns;

        // If no name was specified for this index, we will create one using a basic
        // convention of the table name, followed by the columns, followed by an
        // index type, such as primary or index, which makes the index unique.
        $index = $index ?: $this->createIndexName($type, $columns);

        return $this->addCommand(
            $type, compact('index', 'columns', 'algorithm')
        );
    }

    /**
     * Cree une nouvelle commande de suppression d'indexe dans la structure.
     */
    protected function dropIndexCommand(string $command, string $type, string|array $index): Column
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
    protected function createIndexName(string $type, array $columns):  string
    {
        $index = strtolower($this->prefix.$this->table.'_'.implode('_', $columns).'_'.$type);

        return str_replace(['-', '.'], '_', $index);
    }

    /**
     * Ajoute un champ a la structure.
     */
    public function addColumn(string $type, string $name, array $parameters = []): Column
    {
        $this->columns[] = $column = new Column(
            array_merge(compact('type', 'name'), $parameters)
        );

        return $column;
    }

    /**
     * Retire un champ a la structure
     */
    public function removeColumn(string $name): self
    {
        $this->columns = array_values(array_filter($this->columns, function ($c) use ($name) {
            return $c['name'] != $name;
        }));

        return $this;
    }

    /**
     * Ajoute une nouvelle commande a la structure
     */
    protected function addCommand(string $name, array $parameters = []): Column
    {
        $this->commands[] = $command = $this->createCommand($name, $parameters);

        return $command;
    }

    /**
     * Cree une nouvelle commande
     */
    protected function createCommand(string $name, array $parameters = []): Column
    {
        return new Column(array_merge(compact('name'), $parameters));
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
     * @return Column[]
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
        return array_filter($this->columns, function ($column) {
            return ! $column->change;
        });
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
        return array_filter($this->columns, function ($column) {
            return (bool) $column->change;
        });
    }
}