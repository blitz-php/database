<?php

/**
 * This file is part of Blitz PHP framework - Database Layer.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Database;

use BlitzPHP\Database\Connection\BaseConnection;
use BlitzPHP\Database\Query\Expression;
use BlitzPHP\Utilities\String\Text;

class Utils
{
    public const OPERATORS = [
        '%', '!%', '@', '!@',
        '<=', '>=', '<>', '!=', '<', '>', '=',
        'IS NULL', 'IS NOT NULL', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN',
    ];
    public const CUSTOM_OPERATORS_MAP = [
        '%'  => 'LIKE',
        '!%' => 'NOT LIKE',
        '@'  => 'IN',
        '!@' => 'NOT IN',
    ];
    public const SQL_FUNCTIONS = [
        /** Agrégations statistique */
        'AVG', 'COUNT', 'MAX', 'MIN', 'SUM', 'EVERY', 'SOME', 'ANY',
        /** Fonctions systeme */
        'CURRENT_DATE', 'CURRENT_TIME', 'CURRENT_TIMESTAMP', 'CURRENT_USER', 'SESSION_USER', 'SYSTEM_USER', 'CURDATE', 'CURTIME', 'DATABASE', 'TODAY', 'NOW', 'GETDATE', 'SYSDATE', 'USER', 'VERSION',
        /** Fonctions générales */
        'CAST', 'COALESCE', 'NULLIF', 'OCTET_LENGTH', 'DATALENGTH', 'DECODE', 'GREATEST', 'IFNULL', 'LEAST', 'LENGTH', 'NVL', 'TO_DATE', 'TO_CHAR', 'TO_NUMBER',
        /** Fonctions de chaines */
        'CHAR_LENGTH', 'CHARACTER_LENGTH', 'COLLATE', 'CONCATENATE', 'CONVERT', 'LIKE', 'LOWER', 'POSITION', 'SUBSTRING', 'TRANSLATE', 'TO_CHAR', 'TRIM', 'UPPER',
        'CHAR', 'CHAR_OCTET_LENGTH', 'CHARACTER_MAXIMUM_LENGTH', 'CHARACTER_OCTET_LENGTH', 'CONCAT', 'ILIKE', 'INITCAP', 'INSTR', 'LCASE', 'LOCATE', 'LPAD', 'LTRIM',
        'NCHAR', 'PATINDEX', 'REPLACE', 'REVERSE', 'RPAD', 'RTRIM', 'SPACE', 'SUBSTR', 'UCASE', 'SIMILAR',
        /** Fonctions numériques */
        'ABS', 'ASCII', 'ASIN', 'ATAN', 'CEILING', 'COS', 'COT', 'EXP', 'FLOOR', 'LN', 'LOG10', 'LOG', 'MOD', 'PI', 'POWER', 'RAND', 'ROUND', 'SIGN', 'SIN', 'SQRT', 'TAN', 'TRUNC', 'TRUNCATE', 'UNICODE',
        /** Fonctions temporelles */
        'EXTRACT', 'INTERVAL', 'OVERLAPS', 'ADDDATE', 'AGE', 'DATE_ADD', 'DATE_FORMAT', 'DATE_PART', 'DATE_SUB', 'DATEADD', 'DATEDIFF', 'DATENAME', 'DATEPART', 'DAY', 'DAYNAME', 'DAYOFMONTH', 'DAYOFWEEK',
        'DAYOFYEAR', 'HOUR', 'LAST_DAY', 'MINUTE', 'MONTH', 'MONTH_BETWEEN', 'MONTHNAME', 'NEXT_DAY', 'SECOND', 'SUBDATE', 'WEEK', 'YEAR',

        'TO_TIME', 'TO_TIMESTAMP', 'FIRST', 'LAST', 'MID', 'LEN', 'FORMAT',
        'NOT EXISTS', 'EXISTS',
    ];

    public const SQL_KEYWORDS = [
        'SELECT', 'DISTINCT', 'FROM', 'AS',
        'WHERE', 'AND', 'OR',
        'NOT IN', 'IN', 'IS NOT NULL', 'IS NULL', 'NOT LIKE', 'LIKE', 'NULL', 'NOT',
        'INNER JOIN', 'LEFT JOIN', 'NATURAL JOIN', 'RIGHT JOIN', 'JOIN', 'ON',
        'UNION',
        'GROUP BY', 'HAVING', 
        'ORDER BY', 'ASC', 'DESC', 'LIMIT', 'OFFSET',
        'INSERT', 'INTO', 'VALUES',
        'UPDATE',
        'COUNT', 'MAX', 'MIN', 'AVG', 'SUM',
        'UPPER', 'LOWER',
    ];

    private static ?string $expressionPattern = null;

    public static function isSqlFunction(string $value): bool
    {
        return in_array(strtoupper($value), static::SQL_FUNCTIONS, true);
    }

    /**
     * Determine si la requete est une requete qui ecrit des donnees en bd
     */
    public static function isWritableSql(string $value): bool
    {
        return (bool) preg_match(
            '/^\s*"?(SET|INSERT|UPDATE|DELETE|REPLACE|CREATE|DROP|TRUNCATE|LOAD|COPY|ALTER|RENAME|GRANT|REVOKE|LOCK|UNLOCK|REINDEX|MERGE)\s/i',
            $value,
        );
    }

    /**
     * Vérifie si une chaîne contient un opérateur SQL
     */
    public static function hasOperator(string $value): bool
    {
        return Text::contains($value, static::OPERATORS, true);
    }

    /**
     * Traduit les opérateurs personnalisés
     */
    public static function translateOperator(string $operator): string
    {
        $operator = trim($operator);

        return static::CUSTOM_OPERATORS_MAP[$operator] ?? strtoupper($operator);
    }

    /**
     * Inverse un opérateur
     */
    public static function invertOperator(string $operator): string
    {
        return match ($operator) {
            '='  => '!=',
            '!=' => '=',
            '<'  => '>=',
            '>'  => '<=',
            '<=' => '>',
            '>=' => '<',
            'LIKE', '%' => 'NOT LIKE',
            'NOT LIKE', '!%' => 'LIKE',
            'IN', '@' => 'NOT IN',
            'NOT IN', '!@' => 'IN',
            default => $operator,
        };
    }

    /**
     * Vérifie si une chaîne est un alias valide
     */
    public static function isAlias(string $value): bool
    {
        // Un alias peut être précédé ou non de "AS"
        $clean = static::extractAlias($value);

        // Un alias valide ne contient que des lettres, chiffres, underscore
        return preg_match('/^[a-zA-Z0-9_]+$/', $clean ?? '') === 1;
    }

    /**
     * Extrait le nom de l'alias (avec ou sans "AS")
     */
    public static function extractAlias(string $value): ?string
    {
        $value = trim($value);

        // Chercher "AS alias" à la fin de l'expression
        if (preg_match('/\s+AS\s+([^\s]+)$/i', $value, $matches)) {
            return trim($matches[1]);
        }
        
        // Format sans AS: "... alias" (PostgreSQL style)
        if (preg_match('/\s+([^\s]+)$/', $value, $matches)) {
            $possibleAlias = trim($matches[1]);
            // Vérifier que ce n'est pas un mot-clé SQL
            if (! in_array(strtoupper($possibleAlias), static::SQL_KEYWORDS, true)) {
                return $possibleAlias;
            }
        }
        
        return null;
    }

    /**
     * Vérifie si une chaîne est une expression SQL brute
     */
    public static function isRawExpression(mixed $value): bool
    {
        // Une expression brute est souvent entre parenthèses ou contient des fonctions complexes
        return $value instanceof Expression
            || str_contains($value, '(') && str_contains($value, ')')
            || preg_match('/[+\-*\/<>!=]/', $value);
    }

    /**
     * Formate une colonne qualifiée (avec point)
     */
    public static function formatQualifiedColumn(BaseConnection $db, string $column): string
    {
        if (! str_contains($column, '.')) {
            return $db->escapeIdentifiers($column);
        }

        $parts   = explode('.', $column, 2);
        [$table] = $db->getTableAlias($parts[0]);

        if (empty($table)) {
            $table = $db->prefixTable($parts[0]);
        }

        return $db->escapeIdentifiers($table) . '.' . $db->escapeIdentifiers($parts[1]);
    }

    public static function extractOperatorFromColumn(string $column, ?string $operator = null): array
    {
        if (str_contains($column, ' ')) {
            $parts    = explode(' ', $column);
            $operator = array_pop($parts);
            $column   = implode(' ', $parts);
        }
        if (empty($operator) || ! in_array($operator, static::OPERATORS, true)) {
            $operator = '=';
        }

        $operator = static::translateOperator($operator);

        return [$column, $operator];
    }

    public static function parseExpression(string $expression)
    {
        if (self::$expressionPattern === null) {
            $escaped = array_map(static fn ($op) => preg_quote($op, '/'), static::OPERATORS);
            usort($escaped, static fn ($a, $b) => strlen($b) <=> strlen($a));
            self::$expressionPattern = '/^(.*?)\s*(' . implode('|', $escaped) . ')\s*(.*)$/i';
        }

        if (preg_match(self::$expressionPattern, $expression, $matches)) {
            $column   = trim($matches[1]);
            $operator = static::translateOperator($matches[2]);
            $rawValue = $matches[3] ?? '';

            // Cas des opérateurs sans valeur
            if (in_array($operator, ['', 'IS NULL', 'IS NOT NULL'], true)) {
                $value = null;
            }
            // Cas des listes IN (...)
            elseif (in_array($operator, ['IN', 'NOT IN', '@', '!@'], true) && preg_match('/^\((.*)\)$/', $rawValue, $m)) {
                $items = array_map('trim', explode(',', $m[1]));
                $value = array_map(static::castValue(...), $items);
            }
            // Cas BETWEEN / NOT BETWEEN
            elseif (in_array($operator, ['BETWEEN', 'NOT BETWEEN'], true)) {
                // Exemple: "BETWEEN 1 AND 10"
                if (preg_match('/^(.*?)\s+AND\s+(.*)$/i', $rawValue, $m)) {
                    $value = [static::castValue($m[1]), static::castValue($m[2])];
                }
            } else { // Cas général
                $value = $rawValue === '' ? null : static::castValue($rawValue);
            }

            return [$column, $operator, $value];
        }

        return null;
    }

    public static function castValue(string $value): mixed
    {
        $value = trim($value);

        if (preg_match('/^-?\d+$/', $value)) {
            return (int) $value;
        }
        if (preg_match('/^-?\d+\.\d+$/', $value)) {
            return (float) $value;
        }
        if (preg_match('/^(true|false)$/i', $value)) {
            return $value === 'true';
        }
        if (preg_match('/^["\'](.*)["\']$/', $value, $m)) {
            return $m[1];
        }

        return $value;
    }
}
