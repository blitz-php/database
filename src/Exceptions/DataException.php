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

use RuntimeException;
use Throwable;

class DataException extends RuntimeException implements ExceptionInterface
{
    /**
     * Ajuste le constructeur de l'exception pour affecter le fichier/la ligne à l'endroit où
     * il est réellement déclenché plutôt qu'à l'endroit où il est instancié.
     */
    final public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);

        $trace = $this->getTrace()[0];

        if (isset($trace['class']) && $trace['class'] === static::class) {
            [
                'line' => $this->line,
                'file' => $this->file,
            ] = $trace;
        }
    }

    public static function invalidMethodTriggered(string $method)
    {
        return new static($method . ' is not a valid Model Event callback.');
    }

    public static function emptyDataset(string $mode)
    {
        return new static('There is no data to ' . $mode . '.');
    }

    public static function emptyPrimaryKey(string $mode)
    {
        return new static('There is no primary key defined when trying to make ' . $mode . '.');
    }

    public static function invalidArgument(string $argument)
    {
        return new static('You must provide a valid ' . $argument . '.');
    }

    public static function invalidAllowedFields(string $model)
    {
        return new static('Allowed fields must be specified for model: ' . $model);
    }

    public static function tableNotFound(string $table)
    {
        return new static('Table `' . $table . '` was not found in the current database.');
    }

    public static function emptyInputGiven(string $argument)
    {
        return new static('Empty statement is given for the field `' . $argument . '`');
    }

    public static function findColumnHaveMultipleColumns()
    {
        return new static('Only single column allowed in Column name.');
    }
}
