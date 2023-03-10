<?php

namespace BlitzPHP\Database\Migration;

use BlitzPHP\Contracts\Database\ConnectionInterface;
use BlitzPHP\Database\Creator\BaseCreator;
use BlitzPHP\Database\Database;
use BlitzPHP\Database\Exceptions\MigrationException;
use BlitzPHP\Database\Migration\Definitions\Column;
use BlitzPHP\Utilities\Collection;

/**
 * Transforme les objets de structure en elements compatible avec le Creator
 */
class Transformer
{
    private BaseCreator $creator;

    public function __construct(ConnectionInterface $db)
    {
        $this->creator = Database::creator($db);
    }

    /**
     * Demarrage de la manipulation de la base de donnees
     */
    public function process(Structure $structure)
    {
        $commands = $this->getCommands($structure);   

        $commandsName = array_map(fn($command) => $command->name, $commands);
      
        if (in_array('create', $commandsName, true)) {
            $this->createTable($structure, $commands);
        }
        else if (in_array('modify', $commandsName, true)) {
            $this->modifyTable($structure, $commands);
        }
        else if (in_array('rename', $commandsName, true)) {
            $command = array_filter($commands, fn($command) => $command->name === 'rename');
            
            $this->renameTable($structure->getTable(), $command[0]->to);
        }
        else if (in_array('drop', $commandsName, true)) {
            $this->dropTable($structure->getTable());
        }
        else if (in_array('dropIfExists', $commandsName, true)) {
            $this->dropTable($structure->getTable(), true);
        }
    }

    /**
     * Creation d'une nouvelle table
     */
    public function createTable(Structure $structure, array $commands = []): void
    {
        $ifNotExists = array_filter($commands, fn($command) => $command->name === 'create' && $this->is($command, 'ifNotExists'));
            
        foreach ($this->getColumns($structure, true) as $column) {
            $this->creator->addField([$column->name => $this->makeColumn($column)]);

            if ($this->is($column, 'primary')) {
                $this->creator->addPrimaryKey($column->name);
            }
            else if ($this->is($column, 'unique')) {
                $this->creator->addUniqueKey($column->name);
            }
            else if ($this->is($column, 'index')) {
                $this->creator->addKey($column->name);
            }
        }

        foreach ($commands as $command) {
            if ($command->name === 'foreign') {
                $this->creator->addForeignKey(
                    $command->columns, 
                    $command->on, 
                    $command->references, 
                    $command->onUpdate, 
                    $command->onDelete,
                    $command->index
                );
            }
            else if ($command->name === 'primary') {
                $this->creator->addPrimaryKey($command->columns, $command->index);
            }
            else if ($command->name === 'index') {
                $this->creator->addKey($command->columns, false, false, $command->index);
            }
            else if ($command->name === 'unique') {
                $this->creator->addUniqueKey($command->columns, $command->index);
            }
        }

        $attributes = [];
            
        if ($structure->engine !== '') {
            $attributes['ENGINE'] = $structure->engine;
        }
        if ($structure->charset !== '') {
            $attributes['DEFAULT CHARACTER SET'] = $structure->charset;
        }
        if ($structure->collation !== '') {
            $attributes['COLLATE'] = $structure->collation;
        }
            
        $this->creator->createTable($structure->getTable(), $ifNotExists !== [], $attributes);
    }

    /**
     * Modification d'une table
     */
    public function modifyTable(Structure $structure, array $commands = []): void
    {
        $table = $structure->getTable();

        foreach ($this->getColumns($structure, true) as $column) {
            $this->creator->addColumn($table, [$column->name => $this->makeColumn($column)]);
        }
        foreach ($this->getColumns($structure, false) as $column) {
            // $this->creator->modifyColumn($table, [$column->name => $this->makeColumn($column)]);
        }
        
        foreach ($commands as $command) {
            if ($command->name === 'dropColumn') {
                $this->creator->dropColumn($table, $command->columns);
            }
            else if ($command->name === 'renameColumn') {
                $this->creator->modifyColumn($table, [
                    $command->from => array_merge(['name' => $command->to], $this->makeColumn($command))
                ]);
            }
            else if ($command->name === 'dropIndex') {
                $this->creator->dropKey($table, $command->columns);
            }
            else if ($command->name === 'dropForeign') {
                $this->creator->dropForeignKey($table, $command->index);
            }
            else if ($command->name === 'dropPrimary') {
                $this->creator->dropPrimaryKey($table, $command->index);
            }

            $process = false;

            if ($command->name === 'primary') {
                $this->creator->addPrimaryKey($command->columns, $command->index);
                $process = true;
            }
            else if ($command->name === 'unique') {
                $this->creator->addUniqueKey($command->columns, $command->index);
                $process = true;
            }
            else if ($command->name === 'index') {
                $this->creator->addKey($command->columns, false, false, $command->index);
                $process = true;
            }
            else if ($command->name === 'foreign') {
                $this->creator->addForeignKey(
                    $command->columns,
                    $command->on,
                    $command->references,
                    $command->onUpdate,
                    $command->onDelete,
                    $command->index
                );
                $process = true;
            }
            if ($process) {
                $this->creator->processIndexes($table);
            }
        }
    }

    /**
     * Suppression d'une table
     */
    public function dropTable(string $table, bool $ifExists = true): void
    {
        $this->creator->dropTable($table, $ifExists);
    }

    /**
     * Renommage d'une table
     */
    public function renameTable(string $table, string $to): void
    {
        $this->creator->renameTable($table, $to);
    }
    

	/**
	 * Recupere les colonnes a prendre en compte.
	 */
	private function getColumns(Structure $structure, ?bool $added = null) : array
	{
		$columns = Collection::make($structure->getColumns($added))->map(fn(Column $column) => $column->getAttributes())->all();

        return array_map(fn($column) => (object) $column, $columns);
	}
    
    /**
	 * Recupere les commandes a executer.
	 */
	private function getCommands(Structure $structure): array
	{
		$commands = Collection::make($structure->getCommands())->map(fn(Column $command) => $command->getAttributes())->all();

        return array_map(fn($command) => (object) $command, $commands);
	}

    /**
     * Fabrique un tableau contenant les definition d'un champs
     */
    private function makeColumn(object $column): array
    {
        if (empty($column->name) OR empty($column->type)) {
            throw new MigrationException('Nom ou type du champ non defini');
        }
        
        $definition = [];

        $definition['type'] = $this->creator->typeOf($column->type);

        if (is_array($definition['type'])) {
            if (isset($definition['type'][1])) {
                $definition['constraint'] = $definition['type'][1];
            }
            $definition['type'] = $definition['type'][0];
        }
        if (strpos($definition['type'], '|') !== false) {
            $parts = explode('|', $definition['type']);
            $definition['type'] = $parts[(int) $this->is($column, 'primary')];
        }
        if (strpos($definition['type'], '{precision}') !== false) {
            $definition['type'] = str_replace('{precision}', $column->precision, $definition['type']);
        }

        if ($this->is($column, 'nullable')) {
            $definition['null'] = true;
        }
        if ($this->is($column, 'unique')) {
            $definition['unique'] = true;
        }
        if ($this->is($column, 'unsigned')) {
            $definition['unsigned'] = true;
        }
        if ($this->is($column, 'useCurrent')) {
            $definition['default'] = 'CURRENT_TIMESTAMP';
        }
        else if (property_exists($column, 'default')) {
            $definition['default'] = $column->type === 'boolean' ? (int) $column->default : $column->default;
        }
        if ($this->isInteger($column) && $this->is($column, 'autoIncrement')) {
            $definition['auto_increment'] = true;
        }
        if (!empty($column->comment)) {
            $definition['comment'] = addslashes($column->comment);
        }
        if (!empty($column->collation)) {
            $definition['collate'] = '"'.htmlspecialchars($column->collation).'"';
        }
        if (!empty($column->after)) {
            $definition['after'] = $column->after;
        }
        else if ($this->is($column, 'first')) {
            $definition['first'] = true;
        }

        if (isset($column->length)) {
            $definition['constraint'] = $column->length;
        }
        else if (isset($column->allowed)) {
            $definition['constraint'] = (array) $column->allowed;
        }
        else if(isset($column->total) || isset($column->places)) {
            $definition['constraint'] = ($column->total ?? 8) . ', ' . ($column->places ?? 2);
        }
        else if(isset($column->precision)) {
            $definition['constraint'] = $column->precision;
        }
        
        return $definition;
    }
    
    /**
     * Verifie si le champ a une certaine propriete particuliere
     * 
     * Par exemple, on  peut tester si un champ doit etre null en mettant is('nullable')
     */
    private function is(object $column, string $property, mixed $match = true): bool
    {
        return property_exists($column, $property) && $column->{$property} === $match;
    }

    /**
     * Verifie si le champ est de type integer.
     */
    private function isInteger(object $column): bool
    {
        return in_array($column->type, ['integer', 'int', 'bigInteger', 'mediumInteger', 'smallInteger', 'tinyInteger']);
    }
}
