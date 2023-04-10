<?php

/**
 * This file is part of Blitz PHP framework - Database Layer.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Database\Builder;

use BadMethodCallException;
use BlitzPHP\Contracts\Database\BuilderInterface;
use BlitzPHP\Contracts\Database\ConnectionInterface;
use BlitzPHP\Database\Connection\BaseConnection;
use BlitzPHP\Database\Connection\MySQL as MySQLConnection;
use BlitzPHP\Database\Exceptions\DatabaseException;
use BlitzPHP\Utilities\Iterable\Arr;
use InvalidArgumentException;
use PDO;

/**
 * Fournit les principales méthodes du générateur de requêtes.
 * Les constructeurs spécifiques à la base de données peuvent avoir besoin de remplacer certaines méthodes pour les faire fonctionner.
 */
class BaseBuilder implements BuilderInterface
{
    /**
     * État du mode de test du générateur.
     */
    protected bool $testMode = false;

    /**
     * Type de jointures entre tables
     */
    protected array $joinTypes = [
        'LEFT',
        'RIGHT',
        'OUTER',
        'INNER',
        'LEFT OUTER',
        'RIGHT OUTER',
    ];

    /**
     * Specifie quelles requetes requetes sql
     * supportent l'option IGNORE.
     */
    protected array $supportedIgnoreStatements = [
        'insert' => 'IGNORE',
    ];

    protected string $tableName   = '';
    protected array $table        = [];
    protected array  $fields      = [];
    protected string $where       = '';
    protected array $params       = [];
    protected array $joins        = [];
    protected string $order       = '';
    protected string $groups      = '';
    protected string $having      = '';
    protected string $distinct    = '';
    protected string $ignore      = '';
    protected string $limit       = '';
    protected string $offset      = '';
    protected string $sql         = '';
    protected string $crud        = 'select';
    protected array $query_keys   = [];
    protected array $query_values = [];

    /**
     * @var BaseResult
     */
    protected $result;

    protected $class;

    /**
     * Une reference à la connexion  à la base de données.
     *
     * @var BaseConnection
     */
    protected $db;

    /**
     * Certaines bases de données, comme SQLite, n'autorisent pas par défaut
     * la limitation des clauses de suppression.
     */
    protected bool $canLimitDeletes = true;

    /**
     * Certaines bases de données n'autorisent pas par défaut
     * les requêtes de mise à jour limitées avec WHERE.
     */
    protected bool $canLimitWhereUpdates = true;

    /**
     * @var array Parametres de configuration de la base de donnees
     */
    protected $dbConfig = [];

    protected $dbType;

    /**
     * Constructor
     */
    public function __construct(ConnectionInterface $db, ?array $options = null)
    {
        /**
         * @var BaseConnection $db
         */
        $this->db = $db;

        if (! empty($options)) {
            foreach ($options as $key => $value) {
                if (property_exists($this, $key)) {
                    $this->{$key} = $value;
                }
            }
        }
    }

    /**
     * Renvoie la connexion actuelle à la base de données
     *
     * @return BaseConnection|ConnectionInterface
     */
    public function db(): ConnectionInterface
    {
        return $this->db;
    }

    /**
     * Définit un statut de mode de test.
     */
    public function testMode(bool $mode = true): self
    {
        $this->testMode = $mode;

        return $this;
    }

    /**
     * Recupere le nom de la table principale.
     */
    public function getTable(): string
    {
        if (empty($this->tableName)) {
            $this->tableName = $this->removeAlias(array_pop($this->table));
        }

        return (string) $this->tableName;
    }

    public function __clone()
    {
        $new = $this;

        return $new->reset();
    }

    /**
     * Génère la partie FROM de la requête
     *
     * @param string|string[]|null $from
     */
    final public function from($from, bool $overwrite = false): self
    {
        if ($from === null) {
            $this->table = [null];

            return $this;
        }

        if (true === $overwrite) {
            $this->table = [];
        }

        if (is_string($from)) {
            $from = explode(',', $from);
        }

        foreach ($from as $table) {
            $this->table[] = $this->db->makeTableName($table);
        }

        return $this;
    }

    /**
     *Génère la partie FROM de la requête
     *
     * @param string|string[]|null $from
     *
     * @alias self::from()
     */
    final public function table($from): self
    {
        return $this->from($from, true);
    }

    /**
     * Définit la table dans laquelle les données seront insérées
     */
    final public function into(string $table): self
    {
        return $this->table($table);
    }

    public function fromSubquery(BuilderInterface $builder, string $alias = ''): self
    {
        if ($builder === $this) {
            throw new DatabaseException('The subquery cannot be the same object as the main query object.');
        }

        $subquery = '(' . strtr($builder->sql(), "\n", ' ') . ')';

        $alias = trim($alias);
        if ($alias !== '') {
            $subquery .= ' ' . $this->db->escapeIdentifiers($alias);
        }

        $this->table = [$subquery];

        return $this;
    }

    /**
     * Génère la partie JOIN de la requête
     *
     * @param string       $table  Table à joindre
     * @param array|string $fields Champs à joindre
     *
     * @throws InvalidArgumentException Lorsque $fields est une chaine et qu'aucune table n'a ete au prealable definie
     */
    public function join(string $table, array|string $fields, string $type = 'INNER', bool $escape = false): self
    {
        $type = strtoupper(trim($type));

        if (! in_array($type, $this->joinTypes, true)) {
            $type = '';
        }

        // On sauvegarde le nom de base de la tabe
        $foreignTable = $table;

        $table = $this->db->makeTableName($table);

        // Les conditions réelles de la jointure
        $cond = [];

        if (is_string($fields)) {
            if (empty($this->table)) {
                throw new InvalidArgumentException('Join fields is not defined');
            }

            $key       = $fields;
            $joinTable = $this->table[count($this->table) - 1];

            [$foreignAlias] = $this->db->getTableAlias($foreignTable);
            [$joinAlias]    = $this->db->getTableAlias($joinTable);

            $fields = [$joinAlias . '.' . $key => $foreignAlias . '.' . $key];
        }

        foreach ($fields as $key => $value) {
            // On s'assure que les table des conditions de jointure utilise les aliases

            if (! is_string($key)) {
                $cond = array_merge($cond, [$key => $value]);

                continue;
            }

            // from('test')->join('essai', ['test.id' => 'essai.test_id'])
            // Genere ...
            // select * from prefix_test as test_222 inner join prefix_essai as essai_111 on test_222.id = essai_111.test_id

            $key = $this->buildParseField($key);

            if (is_string($value)) {
                $value = $this->buildParseField($value);
            }

            $cond = array_merge($cond, [$key => $value]);
        }

        $this->joins[] = $type . ' JOIN ' . $table . $this->parseCondition($cond, null, ' ON', $escape);

        return $this->asCrud('select');
    }

    /**
     * Génère la partie JOIN (de type FULL OUTER) de la requête
     *
     * @param string       $table  Table à joindre
     * @param array|string $fields Champs à joindre
     */
    final public function fullJoin(string $table, array|string $fields, bool $escape = false): self
    {
        return $this->join($table, $fields, 'FULL OUTER', $escape);
    }

    /**
     * Génère la partie JOIN (de type INNER) de la requête
     *
     * @param string       $table  Table à joindre
     * @param array|string $fields Champs à joindre
     */
    final public function innerJoin(string $table, array|string $fields, bool $escape = false): self
    {
        return $this->join($table, $fields, 'INNER', $escape);
    }

    /**
     * Génère la partie JOIN (de type LEFT) de la requête
     *
     * @param string       $table  Table à joindre
     * @param array|string $fields Champs à joindre
     */
    final public function leftJoin(string $table, array|string $fields, bool $outer = false, bool $escape = false): self
    {
        return $this->join($table, $fields, 'LEFT ' . ($outer ? 'OUTER' : ''), $escape);
    }

    /**
     * Génère la partie JOIN (de type RIGHT) de la requête
     *
     * @param string       $table  Table à joindre
     * @param array|string $fields Champs à joindre
     */
    final public function rightJoin(string $table, array|string $fields, bool $outer = false, bool $escape = false): self
    {
        return $this->join($table, $fields, 'RIGHT ' . ($outer ? 'OUTER' : ''), $escape);
    }

    /**
     * Génère la partie JOIN (de type NATURAL JOIN) de la requête
     * Uniquement pour ceux qui utilisent MySql
     *
     * @param string|string[] $table Table à joindre
     */
    final public function naturalJoin(string|array $table): self
    {
        if (! ($this->db instanceof MySQLConnection)) {
            throw new DatabaseException('The natural join is only available on MySQL driver');
        }

        foreach ((array) $table as $t) {
            $t = $this->db->makeTableName($t);

            $this->joins[] = 'NATURAL JOIN ' . $t;
        }

        return $this->asCrud('select');
    }

    /**
     * Génère la partie WHERE de la requête.
     * Sépare plusieurs appels avec 'AND'.
     *
     * @param array|string $field Un nom de champ ou un tableau de champs et de valeurs.
     * @param mixed        $value Une valeur de champ à comparer
     */
    public function where($field, $value = null, bool $escape = true): self
    {
        $join = empty($this->where) ? 'WHERE' : '';

        if (is_array($field)) {
            foreach ($field as $key => $val) {
                unset($field[$key]);
                $field[$this->buildParseField($key)] = $val;
            }
        } else {
            $field = $this->buildParseField($field);

            if ($escape === false && is_string($value) && strpos($value, '.') !== false) {
                $value = $this->buildParseField($value);
            }
        }

        $this->where .= $this->parseCondition($field, $value, $join, $escape);

        return $this;
    }

    /**
     * Génère la partie WHERE (de type WHERE x NOT y) de la requête.
     * Sépare plusieurs appels avec 'AND'.
     *
     * @param array|string $field Un nom de champ ou un tableau de champs et de valeurs.
     * @param mixed        $value Une valeur de champ à comparer
     */
    final public function notWhere($field, $value = null, bool $escape = true): self
    {
        if (! is_array($field)) {
            $field = [$field => $value];
        }

        foreach ($field as $key => $value) {
            $this->where($key . ' !=', $value, $escape);
        }

        return $this;
    }

    /**
     * Génère la partie WHERE de la requête.
     * Sépare plusieurs appels avec 'OR'.
     *
     * @param array|string $field Un nom de champ ou un tableau de champs et de valeurs.
     * @param mixed        $value Une valeur de champ à comparer
     */
    final public function orWhere($field, $value = null, bool $escape = true): self
    {
        if (! is_array($field)) {
            $field = [$field => $value];
        }

        foreach ($field as $key => $value) {
            $this->where('|' . $key, $value, $escape);
        }

        return $this;
    }

    /**
     * Génère la partie WHERE (de type WHERE x NOT y) de la requête.
     * Sépare plusieurs appels avec 'OR'.
     *
     * @param array|string $field Un nom de champ ou un tableau de champs et de valeurs.
     * @param mixed        $value Une valeur de champ à comparer
     */
    final public function orNotWhere($field, $value = null, bool $escape = true): self
    {
        if (! is_array($field)) {
            $field = [$field => $value];
        }

        foreach ($field as $key => $value) {
            $this->where('|' . $key . ' !=', $value, $escape);
        }

        return $this;
    }

    /**
     * Génère la partie WHERE (de type WHERE x IN(y)) de la requête.
     * Sépare plusieurs appels avec 'AND'.
     *
     * @param array|callable|self $param
     */
    final public function whereIn(string $field, $param): self
    {
        $param = $this->buildInCallbackParam($param, __METHOD__);

        return $this->where($field . ' IN (' . $param . ')');
    }

    /**
     * Génère la partie WHERE (de type WHERE x IN(y)) de la requête.
     * Sépare plusieurs appels avec 'AND'.
     *
     * @alias self::whereIn()
     */
    final public function in(string $field, array|callable|self $param): self
    {
        return $this->whereIn($field, $param);
    }

    /**
     * Génère la partie WHERE (de type WHERE x IN(y)) de la requête.
     * Sépare plusieurs appels avec 'OR'.
     */
    final public function orWhereIn(string $field, array|callable|self $param): self
    {
        $param = $this->buildInCallbackParam($param, __METHOD__);

        return $this->where('|' . $field . ' IN (' . $param . ')');
    }

    /**
     * Génère la partie WHERE (de type WHERE x IN(y)) de la requête.
     * Sépare plusieurs appels avec 'OR'.
     *
     * @param array|callable|self $param
     *
     * @alias self::orWhereIn()
     */
    final public function orIn(string $field, $param): self
    {
        return $this->orWhereIn($field, $param);
    }

    /**
     * Génère la partie WHERE (de type WHERE x NOT IN(y)) de la requête.
     * Sépare plusieurs appels avec 'AND'.
     *
     * @param array|callable|self $param
     */
    final public function whereNotIn(string $field, $param): self
    {
        $param = $this->buildInCallbackParam($param, __METHOD__);

        return $this->where($field . ' NOT IN (' . $param . ')');
    }

    /**
     * Génère la partie WHERE (de type WHERE x NOT IN(y)) de la requête.
     * Sépare plusieurs appels avec 'AND'.
     *
     * @param array|callable|self $param
     *
     * @alias self::whereNotIn()
     */
    final public function notIn(string $field, $param): self
    {
        return $this->whereNotIn($field, $param);
    }

    /**
     * Génère la partie WHERE (de type WHERE x NOT IN(y)) de la requête.
     * Sépare plusieurs appels avec 'OR'.
     *
     * @param array|callable|self $param
     */
    final public function orWhereNotIn(string $field, $param): self
    {
        $param = $this->buildInCallbackParam($param, __METHOD__);

        return $this->where('|' . $field . ' NOT IN (' . $param . ')');
    }

    /**
     * Génère la partie WHERE (de type WHERE x NOT IN(y)) de la requête.
     * Sépare plusieurs appels avec 'OR'.
     *
     * @param array|callable|self $param
     *
     * @alias self::orWhereNotIn()
     */
    final public function orNotIn(string $field, $param): self
    {
        return $this->orWhereNotIn($field, $param);
    }

    /**
     * Génère la partie WHERE (de type WHERE x LIKE y) de la requête.
     * Sépare plusieurs appels avec 'AND'.
     *
     * @param array|string $field Un nom de champ ou un tableau de champs et de valeurs.
     * @param mixed        $match Une valeur de champ à comparer
     * @param string       $side  Côté sur lequel sera ajouté le caractère '%' si necessaire
     */
    final public function whereLike($field, $match = '', string $side = 'both', bool $escape = true, bool $insensitiveSearch = false): self
    {
        if (! is_array($field)) {
            $field = [$field => $match];
        }

        foreach ($field as $key => $match) {
            [$key, $match, $condition] = $this->_likeStatement($key, $match, false, $insensitiveSearch);
            $this->where($key . ' ' . $condition, $this->buildLikeMatch($match, $side, $escape), false);
        }

        return $this;
    }

    /**
     * Génère la partie WHERE (de type WHERE x LIKE y) de la requête.
     * Sépare plusieurs appels avec 'AND'.
     *
     * @param array|string $field Un nom de champ ou un tableau de champs et de valeurs.
     * @param mixed        $match Une valeur de champ à comparer
     * @param string       $side  Côté sur lequel sera ajouté le caractère '%' si necessaire
     *
     * @alias self::whereLike()
     */
    final public function like($field, $match = '', string $side = 'both', bool $escape = true, bool $insensitiveSearch = false): self
    {
        return $this->whereLike($field, $match, $side, $escape, $insensitiveSearch);
    }

    /**
     * Génère la partie WHERE (de type WHERE x NOT LIKE y) de la requête.
     * Sépare plusieurs appels avec 'AND'.
     *
     * @param array|string $field Un nom de champ ou un tableau de champs et de valeurs.
     * @param mixed        $match Une valeur de champ à comparer
     * @param string       $side  Côté sur lequel sera ajouté le caractère '%' si necessaire
     */
    final public function whereNotLike($field, $match = '', string $side = 'both', bool $escape = true, bool $insensitiveSearch = false): self
    {
        if (! is_array($field)) {
            $field = [$field => $match];
        }

        foreach ($field as $key => $match) {
            [$key, $match, $condition] = $this->_likeStatement($key, $match, true, $insensitiveSearch);
            $this->where($key . ' ' . $condition, $this->buildLikeMatch($match, $side, $escape), false);
        }

        return $this;
    }

    /**
     * Génère la partie WHERE (de type WHERE x NOT LIKE y) de la requête.
     * Sépare plusieurs appels avec 'AND'.
     *
     * @param array|string $field Un nom de champ ou un tableau de champs et de valeurs.
     * @param mixed        $match Une valeur de champ à comparer
     * @param string       $side  Côté sur lequel sera ajouté le caractère '%' si necessaire
     *
     * @alias self::whereNotLike()
     */
    final public function notLike($field, $match = '', string $side = 'both', bool $escape = true, bool $insensitiveSearch = false): self
    {
        return $this->whereNotLike($field, $match, $side, $escape, $insensitiveSearch);
    }

    /**
     * Génère la partie WHERE (de type WHERE x LIKE y) de la requête.
     * Sépare plusieurs appels avec 'OR'.
     *
     * @param array|string $field Un nom de champ ou un tableau de champs et de valeurs.
     * @param mixed        $match Une valeur de champ à comparer
     * @param string       $side  Côté sur lequel sera ajouté le caractère '%' si necessaire
     */
    final public function orWhereLike($field, $match = '', string $side = 'both', bool $escape = true, bool $insensitiveSearch = false): self
    {
        if (! is_array($field)) {
            $field = [$field => $match];
        }

        foreach ($field as $key => $match) {
            [$key, $match, $condition] = $this->_likeStatement($key, $match, false, $insensitiveSearch);
            $this->where('|' . $key . ' ' . $condition, $this->buildLikeMatch($match, $side, $escape), false);
        }

        return $this;
    }

    /**
     * Génère la partie WHERE (de type WHERE x LIKE y) de la requête.
     * Sépare plusieurs appels avec 'OR'.
     *
     * @param array|string $field Un nom de champ ou un tableau de champs et de valeurs.
     * @param mixed        $match Une valeur de champ à comparer
     * @param string       $side  Côté sur lequel sera ajouté le caractère '%' si necessaire
     *
     * @alias self::orWhereLike()
     */
    final public function orLike($field, $match = '', string $side = 'both', bool $escape = true, bool $insensitiveSearch = false): self
    {
        return $this->orWhereLike($field, $match, $side, $escape, $insensitiveSearch);
    }

    /**
     * Génère la partie WHERE (de type WHERE x NOT LIKE y) de la requête.
     * Sépare plusieurs appels avec 'OR'.
     *
     * @param array|string $field Un nom de champ ou un tableau de champs et de valeurs.
     * @param mixed        $match Une valeur de champ à comparer
     * @param string       $side  Côté sur lequel sera ajouté le caractère '%' si necessaire
     */
    final public function orWhereNotLike($field, $match = '', string $side = 'both', bool $escape = true, bool $insensitiveSearch = false): self
    {
        if (! is_array($field)) {
            $field = [$field => $match];
        }

        foreach ($field as $key => $match) {
            [$key, $match, $condition] = $this->_likeStatement($key, $match, true, $insensitiveSearch);
            $this->where('|' . $key . ' ' . $condition, $this->buildLikeMatch($match, $side, $escape), false);
        }

        return $this;
    }

    /**
     * Génère la partie WHERE (de type WHERE x LIKE y) de la requête.
     * Sépare plusieurs appels avec 'OR'.
     *
     * @param array|string $field Un nom de champ ou un tableau de champs et de valeurs.
     * @param mixed        $match Une valeur de champ à comparer
     * @param string       $side  Côté sur lequel sera ajouté le caractère '%' si necessaire
     *
     * @alias self::orWhereNotLike()
     */
    final public function orNotLike($field, $match = '', string $side = 'both', bool $escape = true, bool $insensitiveSearch = false): self
    {
        return $this->orWhereNotLike($field, $match, $side, $escape, $insensitiveSearch);
    }

    /**
     * Génère la partie WHERE (de type WHERE x IS NULL) de la requête.
     * Sépare plusieurs appels avec 'AND'.
     *
     * @param string|string[] $field Un nom de champ ou un tableau de champs
     */
    final public function whereNull($field): self
    {
        foreach ((array) $field as $value) {
            $this->where($value . ' IS NULL');
        }

        return $this;
    }

    /**
     * Génère la partie WHERE (de type WHERE x IS NOT NULL) de la requête.
     * Sépare plusieurs appels avec 'AND'.
     *
     * @param string|string[] $field Un nom de champ ou un tableau de champs
     */
    final public function whereNotNull($field): self
    {
        foreach ((array) $field as $value) {
            $this->where($value . ' IS NOT NULL');
        }

        return $this;
    }

    /**
     * Génère la partie WHERE (de type WHERE x IS NULL) de la requête.
     * Sépare plusieurs appels avec 'OR'.
     *
     * @param string|string[] $field Un nom de champ ou un tableau de champs
     */
    final public function orWhereNull($field): self
    {
        foreach ((array) $field as $value) {
            $this->where('|' . $value . ' IS NULL');
        }

        return $this;
    }

    /**
     * Génère la partie WHERE (de type WHERE x IS NOT NULL) de la requête.
     * Sépare plusieurs appels avec 'OR'.
     *
     * @param string|string[] $field Un nom de champ ou un tableau de champs
     */
    final public function orWhereNotNull($field): self
    {
        foreach ((array) $field as $value) {
            $this->where('|' . $value . ' IS NOT NULL');
        }

        return $this;
    }

    /**
     * Définit une clause between where.
     * Sépare plusieurs appels avec 'AND'.
     *
     * @param mixed $value1
     * @param mixed $value2
     */
    final public function whereBetween(string $field, $value1, $value2): self
    {
        return $this->where(sprintf(
            '%s BETWEEN %s AND %s',
            $this->db->escapeIdentifiers($field),
            $this->db->quote($value1),
            $this->db->quote($value2)
        ));
    }

    /**
     * Définit une clause between where.
     * Sépare plusieurs appels avec 'AND'.
     *
     * @alias self::whereBetween()
     *
     * @param mixed $value1
     * @param mixed $value2
     */
    final public function between(string $field, $value1, $value2): self
    {
        return $this->whereBetween($field, $value1, $value2);
    }

    /**
     * Génère la partie WHERE (de type WHERE x NOT BETWEEN a AND b) de la requête.
     * Sépare plusieurs appels avec 'AND'.
     *
     * @param mixed $value1
     * @param mixed $value2
     */
    final public function whereNotBetween(string $field, $value1, $value2): self
    {
        return $this->where(sprintf(
            '%s NOT BETWEEN %s AND %s',
            $this->db->escapeIdentifiers($field),
            $this->db->quote($value1),
            $this->db->quote($value2)
        ));
    }

    /**
     * Génère la partie WHERE (de type WHERE x NOT BETWEEN a AND b) de la requête.
     * Sépare plusieurs appels avec 'AND'.
     *
     * @alias self::whereNotBetween()
     *
     * @param mixed $value1
     * @param mixed $value2
     */
    final public function notBetween(string $field, $value1, $value2): self
    {
        return $this->whereNotBetween($field, $value1, $value2);
    }

    /**
     * Définit une clause between where.
     * Sépare plusieurs appels avec 'OR'.
     *
     * @param mixed $value1
     * @param mixed $value2
     */
    final public function orWhereBetween(string $field, $value1, $value2): self
    {
        return $this->orWhere(sprintf(
            '%s BETWEEN %s AND %s',
            $this->db->escapeIdentifiers($field),
            $this->db->quote($value1),
            $this->db->quote($value2)
        ));
    }

    /**
     * Définit une clause between where.
     * Sépare plusieurs appels avec 'OR'.
     *
     * @alias self::orWhereBetween()
     *
     * @param mixed $value1
     * @param mixed $value2
     */
    final public function orBetween(string $field, $value1, $value2): self
    {
        return $this->orWhereBetween($field, $value1, $value2);
    }

    /**
     * Génère la partie WHERE (de type WHERE x NOT BETWEEN a AND b) de la requête.
     * Sépare plusieurs appels avec 'OR'.
     *
     * @param mixed $value1
     * @param mixed $value2
     */
    final public function orWhereNotBetween(string $field, $value1, $value2): self
    {
        return $this->orWhere(sprintf(
            '%s NOT BETWEEN %s AND %s',
            $this->db->escapeIdentifiers($field),
            $this->db->quote($value1),
            $this->db->quote($value2)
        ));
    }

    /**
     * Génère la partie WHERE (de type WHERE x NOT BETWEEN a AND b) de la requête.
     * Sépare plusieurs appels avec 'OR'.
     *
     * @alias self::orWhereNotBetween()
     *
     * @param mixed $value1
     * @param mixed $value2
     */
    final public function orNotBetween(string $field, $value1, $value2): self
    {
        return $this->orWhereNotBetween($field, $value1, $value2);
    }

    /**
     * Définit les parametres de la requete en cas d'utilisation de requete préparées classiques
     */
    public function params(array $params): self
    {
        $this->params = array_merge($this->params, $params);

        return $this;
    }

    /**
     * Ajouter des champs pour les tri
     *
     * @param string|string[] $field Un nom de champ ou un tableau de champs
     */
    public function orderBy(string|array $field, string $direction = 'ASC', bool $escape = true): self
    {
        if (is_array($field)) {
            foreach ($field as $key => $item) {
                if (is_string($key)) {
                    $direction = $item ?? $direction;
                    $item      = $key;
                }
                $this->orderBy($item, $direction, $escape);
            }

            return $this;
        }

        $join = empty($this->order) ? 'ORDER BY ' : ', ';

        $direction = strtoupper(trim($direction));

        if ($direction === 'RANDOM') {
            $direction = '';
            $field     = ctype_digit($field) ? sprintf('RAND(%d)', $field) : 'RAND()';
            $escape    = false;
        } elseif ($direction !== '') {
            $direction = in_array($direction, ['ASC', 'DESC'], true) ? ' ' . $direction : '';
        }

        $this->order .= $join . ($escape ? $this->db->escapeIdentifiers($field) : $field) . $direction;

        return $this->asCrud('select');
    }

    /**
     * Ajouter des champs pour les tri.
     *
     * @param string|string[] $field Un nom de champ ou un tableau de champs
     *
     * @alias self::orderBy()
     */
    final public function order(string|array $field, string $direction = 'ASC', bool $escape = true): self
    {
        return $this->orderBy($field, $direction, $escape);
    }

    /**
     * Ajoute un tri croissant pour un champ.
     *
     * @param string|string[] $field Un nom de champ ou un tableau de champs
     */
    final public function sortAsc(string|array $field, bool $escape = true): self
    {
        return $this->orderBy($field, 'ASC', $escape);
    }

    /**
     * Ajoute un tri decroissant pour un champ.
     *
     * @param string|string[] $field Un nom de champ ou un tableau de champs
     */
    final public function sortDesc(string|array $field, bool $escape = true): self
    {
        return $this->orderBy($field, 'DESC', $escape);
    }

    /**
     * Ajoute un tri aléatoire pour les champs.
     *
     * @alias self::rand
     */
    final public function sortRand(?int $digit = null): self
    {
        return $this->rand($digit);
    }

    /**
     * Ajoute un tri aléatoire pour les champs.
     */
    final public function rand(?int $digit = null): self
    {
        if ($digit === null) {
            $digit = '';
        }

        return $this->orderBy((string) $digit, 'RANDOM', false);
    }

    /**
     * Ajoute des champs à regrouper.
     *
     * @param string|string[] $field Nom de champ ou tableau de noms de champs
     */
    public function groupBy($field, bool $escape = true): self
    {
        $join = empty($this->groups) ? 'GROUP BY' : ',';

        if (is_array($field)) {
            foreach ($field as &$val) {
                $val = $this->buildParseField($escape ? $this->db->escapeIdentifiers($val) : $val);
            }

            $fields = implode(',', $field);
        } else {
            $fields = $this->buildParseField($escape ? $this->db->escapeIdentifiers($field) : $field);
        }

        $this->groups .= $join . ' ' . $fields;

        return $this->asCrud('select');
    }

    /**
     * Ajoute des champs à regrouper.
     *
     * @param string|string[] $field Nom de champ ou tableau de noms de champs
     *
     * @alias self::orderBy()
     */
    final public function group($field, bool $escape = true): self
    {
        return $this->groupBy($field, $escape);
    }

    /**
     * Ajoute des conditions de type HAVING.
     * Sépare plusieurs appels avec 'AND'.
     *
     * @param array|string $field Un nom de champ ou un tableau de champs et de valeurs.
     * @param string       $value Une valeur de champ à comparer
     */
    public function having($field, $value = null, bool $escape = true): self
    {
        $join = empty($this->having) ? 'HAVING' : '';

        if (is_array($field)) {
            foreach ($field as $key => $val) {
                unset($field[$key]);
                $field[$this->buildParseField($key)] = $val;
            }
        } else {
            $field = $this->buildParseField($field);
        }

        $this->having .= $this->parseCondition($field, $value, $join, $escape);

        return $this->asCrud('select');
    }

    /**
     * Ajoute des conditions de type HAVING.
     * Sépare plusieurs appels avec 'OR'.
     *
     * @param array|string $field Un nom de champ ou un tableau de champs et de valeurs.
     * @param string       $value Une valeur de champ à comparer
     */
    public function orHaving($field, $value = null, bool $escape = true): self
    {
        if (! is_array($field)) {
            $field = [$field => $value];
        }

        foreach ($field as $key => $value) {
            $this->having('|' . $key, $value, $escape);
        }

        return $this;
    }

    /**
     * Ajoute des conditions de type HAVING IN.
     * Sépare plusieurs appels avec 'AND'.
     */
    final public function havingIn(string $field, array|callable|self $param): self
    {
        $param = $this->buildInCallbackParam($param, __METHOD__);

        return $this->having($field . ' IN (' . $param . ')', null, false);
    }

    /**
     * Ajoute des conditions de type HAVING NOT IN.
     * Sépare plusieurs appels avec 'AND'.
     */
    final public function havingNotIn(string $field, array|callable|self $param): self
    {
        $param = $this->buildInCallbackParam($param, __METHOD__);

        return $this->having($field . ' NOT IN (' . $param . ')', null, false);
    }

    /**
     * Ajoute des conditions de type HAVING IN.
     * Sépare plusieurs appels avec 'OR'.
     */
    final public function orHavingIn(string $field, array|callable|self $param): self
    {
        $param = $this->buildInCallbackParam($param, __METHOD__);

        return $this->orHaving($field . ' IN (' . $param . ')', null, false);
    }

    /**
     * Ajoute des conditions de type HAVING NOT IN.
     * Sépare plusieurs appels avec 'OR'.
     */
    final public function orHavingNotIn(string $field, array|callable|self $param): self
    {
        $param = $this->buildInCallbackParam($param, __METHOD__);

        return $this->orHaving($field . ' NOT IN (' . $param . ')', null, false);
    }

    /**
     * Ajoute des conditions de type HAVING LIKE.
     * Sépare plusieurs appels avec 'AND'.
     *
     * @param array|string $field Un nom de champ ou un tableau de champs et de valeurs.
     * @param mixed        $match Une valeur de champ à comparer
     * @param string       $side  Côté sur lequel sera ajouté le caractère '%' si necessaire
     */
    public function havingLike(array|string $field, $match = '', string $side = 'both', bool $escape = true, bool $insensitiveSearch = false): self
    {
        if (! is_array($field)) {
            $field = [$field => $match];
        }

        foreach ($field as $key => $match) {
            $key   = $insensitiveSearch === true ? 'LOWER(' . $key . ')' : $key;
            $match = $insensitiveSearch === true ? strtolower($match) : $match;
            $this->having($key . ' %', $this->buildLikeMatch($match, $side, $escape), false);
        }

        return $this;
    }

    /**
     * Ajoute des conditions de type HAVING NOT LIKE.
     * Sépare plusieurs appels avec 'AND'.
     *
     * @param array|string $field Un nom de champ ou un tableau de champs et de valeurs.
     * @param mixed        $match Une valeur de champ à comparer
     * @param string       $side  Côté sur lequel sera ajouté le caractère '%' si necessaire
     */
    public function havingNotLike(array|string $field, $match = '', string $side = 'both', bool $escape = true, bool $insensitiveSearch = false): self
    {
        if (! is_array($field)) {
            $field = [$field => $match];
        }

        foreach ($field as $key => $match) {
            $key   = $insensitiveSearch === true ? 'LOWER(' . $key . ')' : $key;
            $match = $insensitiveSearch === true ? strtolower($match) : $match;
            $this->having($key . ' !%', $this->buildLikeMatch($match, $side, $escape), false);
        }

        return $this;
    }

    /**
     * Ajoute des conditions de type HAVING NOT LIKE.
     * Sépare plusieurs appels avec 'AND'.
     *
     * @param array|string $field Un nom de champ ou un tableau de champs et de valeurs.
     * @param mixed        $match Une valeur de champ à comparer
     * @param string       $side  Côté sur lequel sera ajouté le caractère '%' si necessaire
     *
     * @alias self::havingNotLike()
     */
    final public function notHavingLike(array|string $field, $match = '', string $side = 'both', bool $escape = true, bool $insensitiveSearch = false): self
    {
        return $this->havingNotLike($field, $match, $side, $escape, $insensitiveSearch);
    }

    /**
     * Ajoute des conditions de type HAVING Like.
     * Sépare plusieurs appels avec 'OR'.
     *
     * @param array|string $field Un nom de champ ou un tableau de champs et de valeurs.
     * @param mixed        $match Une valeur de champ à comparer
     * @param string       $side  Côté sur lequel sera ajouté le caractère '%' si necessaire
     */
    final public function orHavingLike(array|string $field, $match = '', string $side = 'both', bool $escape = true, bool $insensitiveSearch = false): self
    {
        if (! is_array($field)) {
            $field = [$field => $match];
        }

        foreach ($field as $key => $match) {
            $key   = $insensitiveSearch === true ? 'LOWER(' . $key . ')' : $key;
            $match = $insensitiveSearch === true ? strtolower($match) : $match;
            $this->having('|' . $key . ' %', $this->buildLikeMatch($match, $side, $escape), false);
        }

        return $this;
    }

    /**
     * Ajoute des conditions de type HAVING NOT LIKE.
     * Sépare plusieurs appels avec 'OR'.
     *
     * @param array|string $field Un nom de champ ou un tableau de champs et de valeurs.
     * @param mixed        $match Une valeur de champ à comparer
     * @param string       $side  Côté sur lequel sera ajouté le caractère '%' si necessaire
     */
    final public function orHavingNotLike(array|string $field, $match = '', string $side = 'both', bool $escape = true, bool $insensitiveSearch = false): self
    {
        if (! is_array($field)) {
            $field = [$field => $match];
        }

        foreach ($field as $key => $match) {
            $key   = $insensitiveSearch === true ? 'LOWER(' . $key . ')' : $key;
            $match = $insensitiveSearch === true ? strtolower($match) : $match;
            $this->having('|' . $key . ' !%', $this->buildLikeMatch($match, $side, $escape), false);
        }

        return $this;
    }

    /**
     * Ajoute une limite à la requête.
     */
    final public function limit(int $limit, ?int $offset = null): self
    {
        if ($offset !== null) {
            $this->offset($offset);
        }
        $this->limit = 'LIMIT ' . $limit;

        return $this;
    }

    /**
     * Ajoute un décalage à la requête.
     */
    final public function offset(int $offset, ?int $limit = null): self
    {
        if ($limit !== null) {
            $this->limit($limit);
        }
        $this->offset = 'OFFSET ' . $offset;

        return $this->asCrud('select');
    }

    /**
     * Définit un indicateur qui indique au compilateur de chaîne de requête d'ajouter DISTINCT.
     */
    final public function distinct(bool $value = true): self
    {
        $this->distinct = $value ? 'DISTINCT' : '';

        return $this->asCrud('select');
    }

    /**
     * Construit une requête de sélection.
     *
     * @param string|string[] $fields Nom de champ ou tableau de noms de champs à sélectionner
     */
    public function select($fields = '*', ?int $limit = null, ?int $offset = null): self
    {
        if ($limit !== null) {
            $this->limit($limit, $offset);
        }

        if (is_string($fields)) {
            $fields = explode(',', $fields);
        }

        foreach ($fields as &$val) {
            $val = $this->buildParseField($val);
        }

        $this->fields[] = implode(',', $fields);

        return $this->asCrud('select');
    }

    /**
     * Définit un indicateur qui indique au compilateur de chaîne de requête d'ajouter IGNORE.
     */
    final public function ignore(bool $value = true): self
    {
        $this->ignore = $value ? 'IGNORE' : '';

        return $this->asCrud('insert');
    }

    /**
     * Construit une requête d'insertion.
     *
     * @param array|object $data    Tableau ou objet de clés et de valeurs à insérer
     * @param bool         $execute Spécifié si nous voulons exécuter directement la requête
     *
     * @return BaseResult|self|string
     */
    public function insert(array|object $data = [], bool $escape = true, bool $execute = true)
    {
        $this->crud = 'insert';

        $data = $this->objectToArray($data);

        if (empty($data) && empty($this->query_values)) {
            if (true === $execute) {
                throw new DatabaseException('You must give entries to insert.');
            }

            return $this;
        }

        if (! empty($data)) {
            $this->set($data, null, $escape);
        }

        if ($this->testMode) {
            return $this->sql();
        }
        if (true === $execute) {
            return $this->execute();
        }

        return $this;
    }

    /**
     * Construit une requête d'insertion de type INSERT IGNORE.
     *
     * @param array:object $data    Tableau ou objet de clés et de valeurs à insérer
     * @param bool $execute Spécifié si nous voulons exécuter directement la requête
     *
     * @return BaseResult|self|string
     */
    final public function insertIgnore(array|object $data, bool $escape = true, $execute = true)
    {
        return $this->ignore(true)->insert($data, $escape, $execute);
    }

    /**
     * Construit une requête d'insertion multiple.
     *
     * @param array<array|object> $data Tableau a deux dimensions contenant les valeurs a inserer
     *
     * @return BaseResult|string
     */
    final public function bulckInsert(array $data, bool $escape = true, bool $ignore = false)
    {
        if (2 !== Arr::maxDimensions($data)) {
            throw new BadMethodCallException('Bad usage of ' . static::class . '::' . __METHOD__ . ' method');
        }

        $table = array_pop($this->table);

        $statement = [];

        foreach ($data as $item) {
            if (is_array($item)) {
                $result = $this->ignore($ignore)->into($table)->insert($item, $escape, false);
                if (is_string($result)) {
                    $statement[] = $result;
                } elseif ($result instanceof self) {
                    $statement[] = $result->sql();
                }
            }
        }

        $sql = implode('; ', $statement);

        if ($this->testMode) {
            return $sql;
        }

        return $this->result = $this->query($sql, $this->params);
    }

    /**
     * Construit une requête d'insertion multiple de type INSERT IGNORE.
     *
     * @param array<array|object> $data Tableau a deux dimensions contenant les valeurs a inserer
     *
     * @return BaseResult|string
     */
    final public function bulckInsertIgnore(array $data, bool $escape = true)
    {
        return $this->bulckInsert($data, $escape, true);
    }

    /**
     * Construit une requête de mise à jour.
     *
     * @param array|object|string $data    Tableau ou objet de clés et de valeurs, ou chaîne littérale
     * @param bool                $execute Spécifié si nous voulons exécuter directement la requête
     *
     * @return BaseResult|self|string
     */
    public function update(array|string|object $data = [], bool $escape = true, bool $execute = true)
    {
        $this->crud = 'update';

        if (! is_string($data)) {
            $data = $this->objectToArray($data);
        }

        if (empty($data) && empty($this->query_values)) {
            if (true === $execute) {
                throw new DatabaseException('You must give entries to update.');
            }

            return $this;
        }

        if (! empty($data)) {
            $this->set($data, null, $escape);
        }

        if ($this->testMode) {
            return $this->sql();
        }
        if (true === $execute) {
            return $this->execute();
        }

        return $this;
    }

    /**
     * Construit une requête de remplacement (REPLACE INTO).
     *
     * @param array|object $data    Tableau ou objet de clés et de valeurs à remplacer
     * @param bool         $execute Spécifié si nous voulons exécuter directement la requête
     *
     * @return BaseResult|self|string
     */
    public function replace(array|object $data = [], bool $escape = true, bool $execute = true)
    {
        $this->crud = 'replace';

        $data = $this->objectToArray($data);

        if (empty($data) && empty($this->query_values)) {
            if (true === $execute) {
                throw new DatabaseException('You must give entries to replace.');
            }

            return $this;
        }

        if (! empty($data)) {
            $this->set($data, null, $escape);
        }

        if ($this->testMode) {
            return $this->sql();
        }
        if (true === $execute) {
            return $this->execute();
        }

        return $this;
    }

    /**
     * Construit une requête de suppression.
     *
     * @param array $where   Conditions de suppression
     * @param bool  $execute Spécifié si nous voulons exécuter directement la requête
     *
     * @return BaseResult|self|string
     */
    public function delete(?array $where = null, ?int $limit = null, bool $execute = true)
    {
        $this->crud = 'delete';

        if ($where !== null) {
            $this->where($where);
        }

        if ($limit !== null) {
            $this->limit($limit);
        }

        if (! empty($this->limit) && ! $this->canLimitDeletes) {
            throw new DatabaseException('SQLite3 does not allow LIMITs on DELETE queries.');
        }

        if ($this->testMode) {
            return $this->sql();
        }

        if (true === $execute) {
            return $this->execute();
        }

        return $this;
    }

    /**
     * Compile une chaine truncate string et execute la requete.
     *
     * Si la base de donnee ne supporte pas la commande truncate(),
     * cette fonction va executer "DELETE FROM table"
     *
     * @return bool|string TRUE on success, FALSE on failure, string on testMode
     */
    public function truncate(?string $table = null)
    {
        $this->crud = 'truncate';

        if (! empty($table)) {
            $this->table($table);
        }

        if ($this->testMode) {
            return $this->sql();
        }

        return $this->execute();
    }

    /**
     * Allows key/value pairs to be set for insert(), update() or replace().
     *
     * @param array|object|string $key   Nom du champ, ou tableau de paire champs/valeurs
     * @param mixed               $value Valeur du champ, si $key est un simple champ
     */
    public function set($key, $value = '', ?bool $escape = null): self
    {
        $key = $this->objectToArray($key);

        if (! is_array($key)) {
            $key = [$key => $value];
        }

        $escape = is_bool($escape) ? $escape : $this->db->protectIdentifiers;

        foreach ($key as $k => $v) {
            $this->query_keys[$k]   = $this->db->escapeIdentifiers($k);
            $this->query_values[$k] = $escape === true ? $this->db->quote($v) : $v;
        }

        return $this;
    }

    // Méthodes d'agrégation SQL

    /**
     * Obtient la valeur minimale d'un champ spécifié.
     *
     * @param string|null $key    Clé de cache
     * @param int         $expire Délai d'expiration en secondes
     *
     * @return float|string float en mode reel et string (la chaîne SQL) en mode test
     */
    final public function min(string $field, ?string $key = null, int $expire = 0)
    {
        $this->select('MIN(' . $field . ') min_value');

        if ($this->testMode) {
            return $this->sql();
        }

        $value = $this->value(
            'min_value',
            $key,
            $expire
        );

        return (float) ($value ?? 0);
    }

    /**
     * Obtient la valeur maximale d'un champ spécifié.
     *
     * @param string|null $key    Clé de cache
     * @param int         $expire Délai d'expiration en secondes
     *
     * @return float|string float en mode reel et string (la chaîne SQL) en mode test
     */
    final public function max(string $field, ?string $key = null, int $expire = 0)
    {
        $this->select('MAX(' . $field . ') max_value');

        if ($this->testMode) {
            return $this->sql();
        }

        $value = $this->value(
            'max_value',
            $key,
            $expire
        );

        return (float) ($value ?? 0);
    }

    /**
     * Obtient la somme des valeurs d'un champ spécifié.
     *
     * @param string|null $key    Clé de cache
     * @param int         $expire Délai d'expiration en secondes
     *
     * @return float|string float en mode reel et string (la chaîne SQL) en mode test
     */
    final public function sum(string $field, ?string $key = null, int $expire = 0)
    {
        $this->select('SUM(' . $field . ') sum_value');

        if ($this->testMode) {
            return $this->sql();
        }

        $value = $this->value(
            'sum_value',
            $key,
            $expire
        );

        return (float) ($value ?? 0);
    }

    /**
     * Obtient la valeur moyenne pour un champ spécifié.
     *
     * @param string|null $key    Clé de cache
     * @param int         $expire Délai d'expiration en secondes
     *
     * @return float|string float en mode reel et string (la chaîne SQL) en mode test
     */
    final public function avg(string $field, ?string $key = null, int $expire = 0)
    {
        $this->select('AVG(' . $field . ') avg_value');

        if ($this->testMode) {
            return $this->sql();
        }

        $value = $this->value(
            'avg_value',
            $key,
            $expire
        );

        return (float) ($value ?? 0);
    }

    /**
     * Obtient le nombre d'enregistrements pour une table.
     *
     * @param string|null $key    Clé de cache
     * @param int         $expire Délai d'expiration en secondes
     *
     * @return int|string int en mode reel et string (la chaîne SQL) en mode test
     */
    final public function count(string $field = '*', ?string $key = null, int $expire = 0)
    {
        if (! empty($this->distinct) || ! empty($this->groups)) {
            // Nous devons sauvegarder le SELECT d'origine au cas où 'Prefix' serait utilisé
            $select = $this->sql();

            $this->table = ['( ' . $select . ' ) BLITZ_count_all_results'];
            $statement   = $this->select('COUNT(' . $field . ') As num_rows');

            // Restaurer la partie SELECT
            $this->setSql($select);
            unset($select);
        } else {
            $statement = $this->select('COUNT(' . $field . ') As num_rows');
        }

        if ($this->testMode) {
            return $statement->sql();
        }

        $value = $statement->value(
            'num_rows',
            $key,
            $expire
        );

        return (int) ($value ?? 0);
    }

    // Méthodes d'extraction de données

    /**
     * Execute une requete sql donnée
     *
     * @return BaseResult|bool|Query BaseResult quand la requete est de type "lecture", bool quand la requete est de type "ecriture", Query quand on a une requete preparee
     */
    final public function query(string $sql, array $params = [])
    {
        return $this->db->query($sql, $params);
    }

    /**
     * Exécute une instruction sql.
     *
     * @param string|null $key    Clé de cache
     * @param int         $expire Délai d'expiration en secondes
     *
     * @return BaseResult|bool|Query BaseResult quand la requete est de type "lecture", bool quand la requete est de type "ecriture", Query quand on a une requete preparee
     */
    final public function execute(?string $key = null, int $expire = 0)
    {
        return $this->result = $this->query($this->sql(), $this->params);
    }

    /**
     * Recupere plusieurs lignes des resultats de la reauete select.
     *
     * @param string|null $key    Clé de cache
     * @param int         $expire Délai d'expiration en secondes
     */
    final public function result(int|string $type = PDO::FETCH_OBJ, ?string $key = null, int $expire = 0): array
    {
        return $this->execute($key, $expire)->result($type);
    }

    /**
     * Recupere plusieurs lignes des resultats de la reauete select.
     *
     * @param int|string  $type
     * @param string|null $key    Clé de cache
     * @param int         $expire Délai d'expiration en secondes
     *
     * @alias self::result()
     */
    final public function all($type = PDO::FETCH_OBJ, ?string $key = null, int $expire = 0): array
    {
        return $this->result($type, $key, $expire);
    }

    /**
     * Recupere la premiere ligne des resultats de la reauete select..
     *
     * @param int|string  $type
     * @param string|null $key    Clé de cache
     * @param int         $expire Délai d'expiration en secondes
     *
     * @return mixed
     */
    final public function first($type = PDO::FETCH_OBJ, ?string $key = null, int $expire = 0)
    {
        $this->limit(1);

        return $this->execute($key, $expire)->first($type);
    }

    /**
     * Recupere le premier resultat d'une requete en BD
     *
     * @param int|string $type
     *
     * @return mixed
     *
     * @alias self::first()
     */
    final public function one($type = PDO::FETCH_OBJ, ?string $key = null, int $expire = 0)
    {
        return $this->first($type, $key, $expire);
    }

    /**
     * Recupere un resultat precis dans les resultat d'une requete en BD
     *
     * @param int|string  $type
     * @param string|null $key    Clé de cache
     * @param int         $expire Délai d'expiration en secondes
     *
     * @return mixed La ligne souhaitee
     */
    public function row(int $index, $type = PDO::FETCH_OBJ, ?string $key = null, int $expire = 0)
    {
        return $this->execute($key, $expire)->row($index, $type);
    }

    /**
     * Recupere la valeur d'un ou de plusieurs champs.
     *
     * @param string|string[] $name   Le nom du/des champs de la base de donnees
     * @param string|null     $key    Cle du cache
     * @param int             $expire Délai d'expiration en secondes
     *
     * @return mixed|mixed[] La valeur du/des champs
     */
    final public function value(string|array $name, ?string $key = null, int $expire = 0)
    {
        $row = $this->first(PDO::FETCH_OBJ, $key, $expire);

        $values = [];

        foreach ((array) $name as $v) {
            if (is_string($v)) {
                $values[] = $row->{$v} ?? null;
            }
        }

        return is_string($name) ? $values[0] : $values;
    }

    /**
     * Recupere les valeurs d'un ou de plusieurs champs.
     *
     * @param string|string[] $name   Le nom du/des champs de la base de donnees
     * @param string|null     $key    Cle du cache
     * @param int             $expire Délai d'expiration en secondes
     *
     * @return mixed[] La/les valeurs du/des champs
     */
    final public function values(string|array $name, ?string $key = null, int $expire = 0): array
    {
        $rows = $this->all(PDO::FETCH_OBJ, $key, $expire);

        $fields = [];

        foreach ($rows as $row) {
            $values = [];

            foreach ((array) $name as $v) {
                if (is_string($v)) {
                    $values[$v] = $row->{$v} ?? null;
                }
            }
            $fields[] = is_string($name) ? ($values[$name] ?? null) : $values;
        }

        return $fields;
    }

    /**
     * Incremente un champ numerique par la valeur specifiee.
     *
     * @return bool
     *
     * @throws DatabaseException
     */
    public function increment(string $column, int $value = 1)
    {
        $column = $this->db->protectIdentifiers($column);

        $sql = $this->update([$column => "{$column} + {$value}"], false, false)->sql(true);

        if (! $this->testMode) {
            $this->reset();

            return $this->db->query($sql, null, false);
        }

        return true;
    }

    /**
     * Decremente un champ numerique par la valeur specifiee.
     *
     * @return bool
     *
     * @throws DatabaseException
     */
    public function decrement(string $column, int $value = 1)
    {
        $column = $this->db->protectIdentifiers($column);

        $sql = $this->update([$column => "{$column} - {$value}"], false, false)->sql(true);

        if (! $this->testMode) {
            $this->reset();

            return $this->db->query($sql, null, false);
        }

        return true;
    }

    // Advanced finders methods

    /**
     * Find all elements in database
     *
     * @param array|string $fields  Array of field names to select
     * @param array        $options Array of selecting options
     *                              - @var int limit
     *                              - @var int offset
     *                              - @var array where
     */
    final public function findAll(array|string $fields = '*', array $options = [], int|string $type = PDO::FETCH_OBJ): array
    {
        $this->select($fields);

        if (isset($options['limit'])) {
            $this->limit($options['limit']);
        }
        if (isset($options['offset'])) {
            $this->offset($options['offset']);
        }
        if (isset($options['where']) && is_array($options['where'])) {
            $this->where($options['where']);
        }

        return $this->all($type);
    }

    /**
     * Find one element in database
     *
     * @param array|string $fields  Array of field names to select
     * @param array        $options Array of selecting options
     *                              - @var int offset
     *                              - @var array where
     *
     * @return mixed
     */
    final public function findOne(array|string $fields = '*', array $options = [], int|string $type = PDO::FETCH_OBJ)
    {
        $this->select($fields);

        if (isset($options['offset'])) {
            $this->offset($options['offset']);
        }
        if (isset($options['where']) && is_array($options['where'])) {
            $this->where($options['where']);
        }

        return $this->one($type);
    }

    /**
     * Handles dynamic "where" clauses to the query.
     */
    private function dynamicWhere(string $method, array $parameters): self
    {
        $finder = substr($method, 5);

        $segments = preg_split(
            '/(And|Or)(?=[A-Z])/',
            $finder,
            -1,
            PREG_SPLIT_DELIM_CAPTURE
        );

        // The connector variable will determine which connector will be used for the
        // query condition. We will change it as we come across new boolean values
        // in the dynamic method strings, which could contain a number of these.
        $connector = 'and';

        $index = 0;

        foreach ($segments as $segment) {
            // If the segment is not a boolean connector, we can assume it is a column's name
            // and we will add it to the query as a new constraint as a where clause, then
            // we can keep iterating through the dynamic method string's segments again.
            if ($segment !== 'And' && $segment !== 'Or') {
                $this->addDynamic($segment, $connector, $parameters, $index);

                $index++;
            }

            // Otherwise, we will store the connector so we know how the next where clause we
            // find in the query should be connected to the previous ones, meaning we will
            // have the proper boolean connector to connect the next where clause found.
            else {
                $connector = $segment;
            }
        }

        return $this;
    }

    /**
     * Add a single dynamic where clause statement to the query.
     *
     * @return void
     */
    protected function addDynamic(string $segment, string $connector, array $parameters, int $index)
    {
        if (! ctype_lower($segment)) {
            $segment = preg_replace('/\s+/u', '', ucwords($segment));

            $segment = mb_strtolower(preg_replace('/(.)(?=[A-Z])/u', '$1_', $segment), 'UTF-8');
        }

        // Once we have parsed out the columns and formatted the boolean operators we
        // are ready to add it to this query as a where clause just like any other
        // clause on the query. Then we'll increment the parameter index values.

        if ('or' === strtolower($connector)) {
            $this->orWhere($segment, $parameters[$index]);
        } else {
            $this->where($segment, $parameters[$index]);
        }
    }

    // SQL Statement Generator Methods

    /**
     * Recupere la requete sql courrante et reinitialise le builder.
     */
    final public function sql(bool $preserve = false): string
    {
        $sql = $this->statement()->sql;

        if (false === $preserve) {
            $this->reset();
        }

        return $sql;
    }

    /**
     * Creer la requete sql pour la demande
     */
    private function statement(): self
    {
        $this->checkTable();

        $keys   = [];
        $values = [];

        foreach ($this->query_keys as $key => $value) {
            if (isset($this->query_values[$key])) {
                $keys[]   = $value;
                $values[] = $this->query_values[$key];
            }
        }

        if ($this->crud === 'insert') {
            $this->setSql($this->_insertStatement(
                $this->getTable(),
                implode(',', $keys),
                implode(',', $values)
            ));
        } elseif ($this->crud === 'replace') {
            $this->setSql($this->_replaceStatement(
                $this->getTable(),
                implode(',', $keys),
                implode(',', $values)
            ));
        } elseif ($this->crud === 'delete') {
            $this->setSql([
                'DELETE FROM',
                $this->getTable(),
                $this->where,
                $this->order,
                $this->limit,
                $this->offset,
            ]);
        } elseif ($this->crud === 'truncate') {
            $this->setSql($this->_truncateStatement($this->getTable()));
        } elseif ($this->crud === 'update') {
            $this->setSql([
                'UPDATE',
                $this->getTable(),
                'SET',
                implode(',', $values),
                $this->where,
                $this->order,
                $this->limit,
                $this->offset,
            ]);
        } elseif ($this->crud === 'select') {
            $this->setSql([
                'SELECT',
                $this->distinct,
                implode(', ', ! empty($this->fields) ? $this->fields : ['*']),
                $this->table === [null] ? '' : 'FROM',
                implode(', ', $this->table),
                implode(' ', $this->joins),
                $this->where,
                $this->groups,
                $this->having,
                $this->order,
                $this->limit,
                $this->offset,
            ]);
        }

        return $this;
    }

    /**
     * Define statement
     *
     * @param array|string $sql
     *
     * @return void
     */
    private function setSql($sql)
    {
        $this->sql = $this->makeSql($sql);
    }

    private function makeSql($sql): string
    {
        return trim(
            is_array($sql) ? array_reduce($sql, [$this, 'build']) : $sql
        );
    }

    /**
     * Constructeur de requete LIKE independament de la platforme
     *
     * @param mixed $match
     *
     * @return string[] [column, match, condition]
     */
    protected function _likeStatement(string $column, $match, bool $not, bool $insensitiveSearch = false): array
    {
        $column = $this->db->escapeIdentifiers($column);

        return [
            $insensitiveSearch === true ? 'LOWER(' . $column . ')' : $column,
            $insensitiveSearch === true && is_string($match) ? strtolower($match) : $match,
            ($not === true ? 'NOT ' : '') . 'LIKE',
        ];
    }

    /**
     * Genere la chaine REPLACE INTO conformement a la plateforme
     *
     * @return string|string[]
     */
    protected function _replaceStatement(string $table, string $keys, string $values)
    {
        return [
            'REPLACE INTO',
            $table,
            '(' . $keys . ')',
            'VALUES',
            '(' . $values . ')',
        ];
    }

    /**
     * Genere la chaine INSERT conformement a la plateforme
     *
     * @return string|string[]
     */
    protected function _insertStatement(string $table, string $keys, string $values)
    {
        return [
            'INSERT', $this->compileIgnore('insert'), 'INTO',
            $table,
            '(' . $keys . ')',
            'VALUES',
            '(' . $values . ')',
        ];
    }

    /**
     * Genere la chaine TRUNCATE conformement a la plateforme
     *
     * Si la base de donnee ne supporte pas la commande truncate(),
     * cette fonction va executer "DELETE FROM table"
     */
    protected function _truncateStatement(string $table): string
    {
        return 'TRUNCATE ' . $table;
    }

    /**
     * Verifie si l'option IGNORE est supporter par
     * le pilote de la base de donnees pour la requete specifiee.
     */
    protected function compileIgnore(string $statement): string
    {
        if (! empty($this->ignore) && isset($this->supportedIgnoreStatements[$statement])) {
            return trim($this->supportedIgnoreStatements[$statement]) . ' ';
        }

        return '';
    }

    /**
     * Joins string tokens into a SQL statement.
     *
     * @param string $sql   SQL statement
     * @param string $input Input string to append
     *
     * @return string New SQL statement
     */
    private function build(?string $sql, ?string $input): string
    {
        return trim(($input !== '') ? ($sql . ' ' . $input) : $sql);
    }

    /**
     * Analyse une déclaration de condition.
     *
     * @param array<string, mixed>|string $field  Champ de base de données
     * @param array|string                $value  Valeur de la condition
     * @param string                      $join   Mot de jonction
     * @param bool                        $escape Réglage des valeurs d'échappement
     *
     * @return string Condition sous forme de chaîne
     *
     * @throws DatabaseException Pour une condition where invalide
     */
    protected function parseCondition($field, $value = null, $join = '', $escape = true)
    {
        if (is_array($field)) {
            $str = '';

            foreach ($field as $key => $value) {
                $str .= $this->parseCondition($key, $value, $join, $escape);
                $join = '';
            }

            return $str;
        }

        if (! is_string($field)) {
            throw new DatabaseException('Invalid where condition.');
        }

        $field = trim($field);

        if (empty($join)) {
            $join = ($field[0] === '|') ? ' OR ' : ' AND ';
        }
        $field = trim(str_replace('|', '', $field));

        if ($value === null) {
            return rtrim($join) . ' ' . ltrim($field);
        }

        $operator = '';
        if (strpos($field, ' ') !== false) {
            $parts    = explode(' ', $field);
            $field    = array_shift($parts);
            $operator = implode(' ', $parts);
        }

        if (! empty($operator)) {
            switch (strtoupper($operator)) {
                case '%':
                case 'LIKE':
                    $condition = ' LIKE ';
                    break;

                case '!%':
                case 'NOT LIKE':
                    $condition = ' NOT LIKE ';
                    break;

                case '@':
                case 'IN':
                    $condition = ' IN ';
                    break;

                case '!@':
                case 'NOT IN':
                    $condition = ' NOT IN ';
                    break;

                default:
                    $condition = " {$operator} ";
            }
        } else {
            $condition = ' = ';
        }

        if (is_array($value)) {
            if (strpos($operator, '@') === false) {
                $condition = ' IN ';
            }
            $value = '(' . implode(',', array_map(fn ($val) => $escape === true ? $this->db->quote($val) : $val, $value)) . ')';
        } else {
            $value = ($escape && ! is_numeric($value)) ? $this->db->quote($value) : $value;
        }

        return rtrim($join) . ' ' . ltrim($field . $condition . $value);
    }

    /**
     * Réinitialise les propriétés du builder.
     */
    public function reset(): self
    {
        $this->tableName = '';
        $this->table     = [];
        $this->params    = [];
        $this->where     = '';
        $this->fields    = [];
        $this->joins     = [];
        $this->order     = '';
        $this->groups    = '';
        $this->having    = '';
        $this->ignore    = '';
        $this->distinct  = '';
        $this->limit     = '';
        $this->offset    = '';
        $this->sql       = '';

        return $this->asCrud('select');
    }

    /**
     * Prend un objet en entree et convertit les variable de calss en tableau de cle/valeurs
     *
     * @param mixed $object
     */
    protected function objectToArray($object)
    {
        if (! is_object($object)) {
            return $object;
        }

        if (method_exists($object, 'toArray')) {
            return $object->toArray();
        }

        $array = [];

        foreach (get_object_vars($object) as $key => $val) {
            if (! is_object($val) && ! is_array($val)) {
                $array[$key] = $val;
            }
        }

        return $array;
    }

    /**
     * Vérifie si la propriété de table a été définie.
     */
    protected function checkTable()
    {
        if (empty($this->table)) {
            throw new DatabaseException('Table is not defined.');
        }
    }

    /**
     * Vérifie si la propriété de classe a été définie.
     */
    protected function checkClass()
    {
        if (! $this->class) {
            throw new DatabaseException('Class is not defined.');
        }
    }

    /**
     * Defini le type d'action CRUD à éffectuer
     *
     * @internal
     */
    private function asCrud(string $type): self
    {
        $this->crud = $type;

        return $this;
    }

    /**
     * Parse les champs d'une condition
     * ceci recherche si on utilise la notation `table.champ` pour aliaxer ou prefixer la table
     *
     * @internal
     */
    private function buildParseField(string $field): string
    {
        $aggregate = null;

        if (preg_match('/^(AVG|COUNT|MAX|MIN|SUM)\S?\(([a-zA-Z0-9\*_\.]+)\)/isU', $field, $matches)) {
            $aggregate = $matches[1];
            $field     = str_replace($aggregate . '(' . $matches[2] . ')', $matches[2], $field);
        }

        $field = explode('.', $field);

        if (count($field) === 2) {
            $operator = '';
            if ($field[0][0] === '|') {
                $field[0] = substr($field[0], 1);
                $operator = '|';
            }

            [$field[0]] = $this->db->getTableAlias($field[0]);
            if (empty($field[0])) {
                $field[0] = $this->db->prefixTable($field[0]);
            }

            $field[0] = $operator . $field[0];
        }

        $result = implode('.', $field);

        if (null !== $aggregate) {
            $parts = explode(' ', $result);
            $field = array_shift($parts);

            $result = $aggregate . '(' . $this->db->escapeIdentifiers($field) . ') ' . implode(' ', $parts);
        } else {
            $result = $this->db->escapeIdentifiers($result);
        }

        return $result;
    }

    /**
     * Genere la chaine appropiée pour une valeur de requete 'LIKE'
     *
     * @param string $side Côté sur lequel sera ajouté le caractère '%' si necessaire
     *
     * @internal
     */
    private function buildLikeMatch(string $value, string $side = 'both', bool $escape = true): string
    {
        $count = substr_count($value, '%');
        $pos   = strpos($value, '%');
        if ($pos !== false) {
            if ($count === 2) {
                $side = 'both';
            } else {
                $side = $pos === 0 ? 'before' : 'after';
            }

            $value = str_replace('%', '', $value);
        }

        switch ($side) {
            case 'none':
                return "'{$value}'";

            case 'before':
                return "'%{$value}'";

            case 'after':
                return "'{$value}%'";

            default:
                return "'%{$value}%'";
        }
    }

    /**
     * Genere la chaine appropiée pour les conditions de type whereIn et havingIn
     *
     * @param array|callable|string $param
     */
    private function buildInCallbackParam($param, string $method): string
    {
        if (is_callable($param)) {
            $param = $param(clone $this);
        }

        if (is_array($param)) {
            $param = implode(',', array_map([$this->db, 'quote'], $param));
        } elseif ($param instanceof self) {
            $param = $param->sql();
        } elseif (! is_string($param)) {
            throw new InvalidArgumentException(sprintf('Unrecognized argument type for method "%s".', static::class . '::' . $method));
        }

        return $param;
    }

    /**
     * @internal
     */
    private function removeAlias(string $from): string
    {
        if (strpos($from, ' ') !== false) {
            // si l'alias est écrit avec le mot-clé AS, supprimez-le
            $from = preg_replace('/\s+AS\s+/i', ' ', $from);

            $parts = explode(' ', $from);
            $from  = $parts[0];
        }

        return $from;
    }
}
