<?php 

namespace BlitzPHP\Database;

use BlitzPHP\Database\Connection\BaseConnection;
use BlitzPHP\Utilities\String\Text;

class Utils
{
    public const OPERATORS = [
        '%', '!%', '@', '!@',
        '<', '>', '<=', '>=', '<>', '=', '!=',
        'IS NULL', 'IS NOT NULL', 'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'BETWEEN', 'NOT BETWEEN',
    ];

    public const SQL_FUNCTIONS =  [
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

    /**
     * Vérifie si une chaîne est un opérateur SQL
     */
    public static function isOperator(string $value): bool
    {
        return Text::contains($value, static::OPERATORS, true);
    }

    /**
     * Vérifie si une chaîne est un alias valide
     */
    public static function isAlias(string $value): bool
    {
        // Un alias peut être précédé ou non de "AS"
        $clean = static::extractAlias($value);
        
        // Un alias valide ne contient que des lettres, chiffres, underscore
        return preg_match('/^[a-zA-Z0-9_]+$/', $clean) === 1;
    }

    /**
     * Extrait le nom de l'alias (avec ou sans "AS")
     */
    public static function extractAlias(string $value): string
    {
        return preg_replace('/^\s*AS\s+/i', '', trim($value));
    }

    /**
     * Vérifie si une chaîne est une expression SQL brute
     */
    public static function isRawExpression(string $value): bool
    {
        // Une expression brute est souvent entre parenthèses ou contient des fonctions complexes
        return str_contains($value, '(') && str_contains($value, ')') 
            || preg_match('/[+\-*\/<>!=]/', $value);
    }

    /**
     * Formate une colonne qualifiée (avec point)
     */
    public static function formatQualifiedColumn(BaseConnection $db, string $column): string
    {
        $parts = explode('.', $column, 2);
        
        [$table] = $db->getTableAlias($parts[0]);
        
        if (empty($table)) {
            $table = $db->prefixTable($parts[0]);
        }

        $table = $db->escapeIdentifiers($table);
        $column = $db->escapeIdentifiers($parts[1]);
        
        return $table . '.' . $column;
    }
}