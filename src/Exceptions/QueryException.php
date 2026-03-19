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

use BlitzPHP\Utilities\String\Text;
use PDOException;
use Throwable;

class QueryException extends PDOException
{
    /**
     * Create a new query exception instance.
     *
     * @param string              $connectionName    Le nom de la connexion à la base de données.
     * @param string              $sql               Le SQL de la requête.
     * @param array               $bindings          Les bindings pour la requête.
     * @param 'read'|'write'|null $readWriteType     Le type de lecture/écriture PDO pour la requête exécutée.
     * @param array               $connectionDetails Les détails de connexion pour la requête (hôte, port, base de données, etc.).
     */
    public function __construct(public string $connectionName, protected string $sql, protected array $bindings, Throwable $previous, protected array $connectionDetails = [], public ?string $readWriteType = null)
    {
        parent::__construct('', 0, $previous);

        $this->code    = $previous->getCode();
        $this->message = $this->formatMessage($connectionName, $sql, $bindings, $previous);

        if ($previous instanceof PDOException) {
            $this->errorInfo = $previous->errorInfo;
        }
    }

    /**
     * Formate le message d'erreur SQL.
     */
    protected function formatMessage(string $connectionName, string $sql, array $bindings, Throwable $previous): string
    {
        $details = $this->formatConnectionDetails();

        return $previous->getMessage() . ' (Connection: ' . $connectionName . $details . ', SQL: ' . Text::replaceArray('?', $bindings, $sql) . ')';
    }

    /**
     * Formate les détails de connexion pour le message d'erreur.
     */
    protected function formatConnectionDetails(): string
    {
        if (empty($this->connectionDetails)) {
            return '';
        }

        $driver = $this->connectionDetails['driver'] ?? '';

        $segments = [];

        if ($driver !== 'sqlite') {
            if (! empty($this->connectionDetails['unix_socket'])) {
                $segments[] = 'Socket: ' . $this->connectionDetails['unix_socket'];
            } else {
                $host = $this->connectionDetails['host'] ?? '';

                $segments[] = 'Host: ' . (is_array($host) ? implode(', ', $host) : $host);
                $segments[] = 'Port: ' . ($this->connectionDetails['port'] ?? '');
            }
        }

        $segments[] = 'Database: ' . ($this->connectionDetails['database'] ?? '');

        return ', ' . implode(', ', $segments);
    }

    /**
     * Obtient le nom de la connexion pour la requête.
     */
    public function getConnectionName(): string
    {
        return $this->connectionName;
    }

    /**
     * Obtient le SQL pour la requête.
     */
    public function getSql(): string
    {
        return $this->sql;
    }

    /**
     * Obtient la représentation SQL brute de la requête avec les bindings intégrés.
     */
    public function getRawSql(): string
    {
        $sql      = $this->getSql();
        $bindings = $this->getBindings();

        foreach ($bindings as $value) {
            $sql = preg_replace('/\?/', $value, $sql, 1);
        }

        return $sql;
    }

    /**
     * Obtient les bindings pour la requête.
     */
    public function getBindings(): array
    {
        return $this->bindings;
    }

    /**
     * Obtient des informations sur la connexion telles que l'hôte, le port, la base de données, etc.
     */
    public function getConnectionDetails(): array
    {
        return $this->connectionDetails;
    }
}
