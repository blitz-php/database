<?php

/**
 * This file is part of Blitz PHP framework - Database Layer.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Database\Collectors;

use BlitzPHP\Contracts\Event\EventInterface;
use BlitzPHP\Database\Connection\BaseConnection;
use BlitzPHP\Database\ConnectionResolver;
use BlitzPHP\Debug\Toolbar\Collectors\BaseCollector;
use BlitzPHP\Utilities\Date;
use BlitzPHP\Utilities\String\Text;

/**
 * Collecteur pour l'onglet Base de données de la barre d'outils de débogage.
 *
 * @credit	<a href="https://codeigniter.com">CodeIgniter 4.2 - CodeIgniter\Debug\Toolbar\Collectors\Database</a>
 */
class DatabaseCollector extends BaseCollector
{
    /**
     * {@inheritDoc}
     */
    protected bool $hasTimeline = true;

    /**
     * {@inheritDoc}
     */
    protected bool $hasTabContent = true;

    /**
     * {@inheritDoc}
     */
    protected bool $hasVarData = false;

    /**
     * {@inheritDoc}
     */
    protected string $title = 'Base de donées';

    /**
     * {@inheritDoc}
     */
    protected string $view = __NAMESPACE__ . '\Views\database.tpl';

    /**
     * Tableau de connexions à la base de données.
     *
     * @var BaseConnection[]
     */
    protected array $connections = [];

    /**
     * Les instances de requête qui ont été collectées via l'événement DBQuery.
     */
    protected static array $queries = [];

    /**
     * Constructeur
     */
    public function __construct()
    {
        $this->getConnections();
    }

    /**
     * La méthode statique utilisée lors des événements pour collecter des données.
     */
    public static function collect(EventInterface $event)
    {
        /**
         * @var \BlitzPHP\Database\Result\BaseResult
         */
        $result = $event->getTarget();

        if (count(static::$queries) < config('toolbar.max_queries', 100)) {
            $query = (object) $result->details();

            $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

            if (! is_cli()) {
                // lorsqu'ils sont appelés dans le navigateur, les deux premiers tableaux de traces 
                // proviennent du déclencheur d'événement de la base de données, qui ne sont pas nécessaires
                $backtrace = array_slice($backtrace, 2);
            }

            static::$queries[] = [
                'query'     => $query,
                'string'    => $query->sql,
                'duplicate' => in_array($query->sql, array_column(static::$queries, 'string', null), true),
                'trace'     => $backtrace,
            ];
        }
    }

    /**
     * {@inheritDoc}
     */
    protected function formatTimelineData(): array
    {
        $data = [];

        foreach ($this->connections as $alias => $connection) {
            $data[] = [
                'name'      => 'Connexion à la base de données: "' . $connection->getDatabase() . '". Config: "' . $alias . '"',
                'component' => 'Base de données',
                'start'     => $connection->getConnectStart(),
                'duration'  => $connection->getConnectDuration(),
            ];
        }

        foreach (static::$queries as $query) {
            $data[] = [
                'name'      => 'Requête',
                'component' => 'Base de données',
                'query'     => $query['query']->sql,
                'start'     => $query['query']->start,
                'duration'  => $query['query']->duration,
            ];
        }

        return $data;
    }

    /**
     * {@inheritDoc}
     */
    public function display(): array
    {
        $data            = [];
        $data['queries'] = array_map(function (array $query): array {
            $isDuplicate = $query['duplicate'] === true;

            $firstNonSystemLine = '';

            foreach ($query['trace'] as $index => &$line) {
                // simplifier le fichier et la ligne
                if (isset($line['file'])) {
                    $line['file'] = clean_path($line['file']) . ':' . $line['line'];
                    unset($line['line']);
                } else {
                    $line['file'] = '[internal function]';
                }

                // trouver la première ligne de trace qui ne provient pas du systeme
                if ($firstNonSystemLine === '' && ! Text::contains($line['file'], ['SYST_PATH', 'BLITZ_PATH'])) {
                    $firstNonSystemLine = $line['file'];
                }

                // simplifier l'appel de fonction
                if (isset($line['class'])) {
                    $line['function'] = $line['class'] . $line['type'] . $line['function'];
                    unset($line['class'], $line['type']);
                }

                if (strrpos($line['function'], '{closure}') === false) {
                    $line['function'] .= '()';
                }

                $line['function'] = str_repeat(chr(0xC2) . chr(0xA0), 8) . $line['function'];

                // ajouter une numérotation d'index complétée par un espace insécable
                $indexPadded = str_pad(sprintf('%d', $index + 1), 3, ' ', STR_PAD_LEFT);
                $indexPadded = preg_replace('/\s/', chr(0xC2) . chr(0xA0), $indexPadded);

                $line['index'] = $indexPadded . str_repeat(chr(0xC2) . chr(0xA0), 4);
            }

            return [
                'hover'         => $isDuplicate ? 'Cette requête a été appelée plus d\'une fois.' : '',
                'class'         => $isDuplicate ? 'duplicate' : '',
                'duration'      => (number_format($query['query']->duration, 5) * 1000) . ' ms',
                'sql'           => $this->highlight($query['query']->sql),
                'affected_rows' => $query['query']->affected_rows,
                'trace'         => $query['trace'],
                'trace-file'    => $firstNonSystemLine,
                'qid'           => md5($query['query']->sql . Date::now()->format('0.u00 U')),
            ];
        }, static::$queries);

        return $data;
    }

    /**
     * {@inheritDoc}
     */
    public function getBadgeValue(): int
    {
        return count(static::$queries);
    }

    /**
     * {@inheritDoc}
     *
     * @return string Le nombre de requêtes (entre parenthèses) ou une chaîne vide.
     */
    public function getTitleDetails(): string
    {
        $this->getConnections();

        $queryCount      = count(static::$queries);
        $uniqueCount     = count(array_filter(static::$queries, static fn ($query): bool => $query['duplicate'] === false));
        $connectionCount = count($this->connections);

        return sprintf(
            '(%d requête%s au total, %s %d unique sur %d connexion%s)',
            $queryCount,
            $queryCount > 1 ? 's' : '',
            $uniqueCount > 1 ? 'dont' : '',
            $uniqueCount,
            $connectionCount,
            $connectionCount > 1 ? 's' : '',
        );
    }

    /**
     * {@inheritDoc}
     */
    public function isEmpty(): bool
    {
        return static::$queries === [];
    }

    /**
     * {@inheritDoc}
     */
    public function icon(): string
    {
        return 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABgAAAAYCAYAAADgdz34AAAAAXNSR0IArs4c6QAAAARnQU1BAACxjwv8YQUAAAAJcEhZcwAADsMAAA7DAcdvqGQAAADMSURBVEhLY6A3YExLSwsA4nIycQDIDIhRWEBqamo/UNF/SjDQjF6ocZgAKPkRiFeEhoYyQ4WIBiA9QAuWAPEHqBAmgLqgHcolGQD1V4DMgHIxwbCxYD+QBqcKINseKo6eWrBioPrtQBq/BcgY5ht0cUIYbBg2AJKkRxCNWkDQgtFUNJwtABr+F6igE8olGQD114HMgHIxAVDyAhA/AlpSA8RYUwoeXAPVex5qHCbIyMgwBCkAuQJIY00huDBUz/mUlBQDqHGjgBjAwAAACexpph6oHSQAAAAASUVORK5CYII=';
    }

    /**
     * Obtient les connexions à partir de la configuration de la base de données
     */
    private function getConnections()
    {
        $this->connections = ConnectionResolver::getConnections();
    }

    private function highlight(string $statement): string
    {
        // Liste des mots-clés à mettre en gras
        $replacements = array_map(fn($term) => "<strong>{$term}</strong>", $search = [
            'SELECT',
            'DISTINCT',
            'FROM',
            'WHERE',
            'AND',
            'INNER JOIN',
            'LEFT JOIN',
            'NATURAL JOIN',
            'RIGHT JOIN',
            'JOIN',
            'ORDER BY',
            'ASC',
            'DESC',
            'GROUP BY',
            'LIMIT',
            'INSERT',
            'INTO',
            'VALUES',
            'UPDATE',
            'OR ',
            'HAVING',
            'OFFSET',
            'NOT IN',
            'IN',
            'IS NOT NULL',
            'IS NULL',
            'NOT LIKE',
            'LIKE',
            'COUNT',
            'MAX',
            'MIN',
            'ON',
            'AS',
            'As',
            'AVG',
            'SUM',
            'UPPER',
            'LOWER',
            '(',
            ')',
        ]);

       return strtr($statement, array_combine($search, $replacements));
    }
}
