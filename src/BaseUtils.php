<?php

/**
 * This file is part of Blitz PHP framework.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Database;

use BlitzPHP\Database\Contracts\ConnectionInterface;
use BlitzPHP\Database\Contracts\ResultInterface;
use BlitzPHP\Database\Exceptions\DatabaseException;

/**
 * Class BaseUtils
 */
abstract class BaseUtils
{
    /**
     * Database object
     *
     * @var ConnectionInterface|object
     */
    protected $db;

    /**
     * Instruction pour lister les bases de données
     *
     * @var bool|string
     */
    protected $listDatabases = false;

    /**
     * Instruction pour optimiser les tables
     *
     * @var bool|string
     */
    protected $optimizeTable = false;

    /**
     * Instruction pour reparer les tables
     *
     * @var bool|string
     */
    protected $repairTable = false;

    /**
     * Class constructor
     */
    public function __construct(ConnectionInterface $db)
    {
        $this->db = $db;
    }

    /**
     * Liste les bases de données
     *
     * @throws DatabaseException
     *
     * @return array|bool
     */
    public function listDatabases()
    {
        // Y a-t-il un résultat en cache ?
        if (isset($this->db->dataCache['db_names'])) {
            return $this->db->dataCache['db_names'];
        }

        if ($this->listDatabases === false) {
            if ($this->db->debug) {
                throw new DatabaseException('Unsupported feature of the database platform you are using.');
            }

            return false;
        }

        $this->db->dataCache['db_names'] = [];

        $query = $this->db->query($this->listDatabases);
        if ($query === false) {
            return $this->db->dataCache['db_names'];
        }

        for ($i = 0, $query = $query->resultArray(), $c = count($query); $i < $c; $i++) {
            $this->db->dataCache['db_names'][] = current($query[$i]);
        }

        return $this->db->dataCache['db_names'];
    }

    /**
     * Déterminer si une base de données particulière existe
     */
    public function databaseExists(string $databaseName): bool
    {
        return in_array($databaseName, $this->listDatabases(), true);
    }

    /**
     * Optimiser la table
     *
     * @throws DatabaseException
     *
     * @return bool
     */
    public function optimizeTable(string $tableName)
    {
        if ($this->optimizeTable === false) {
            if ($this->db->debug) {
                throw new DatabaseException('Unsupported feature of the database platform you are using.');
            }

            return false;
        }

        $query = $this->db->query(sprintf($this->optimizeTable, $this->db->escapeIdentifiers($tableName)));

        return $query !== false;
    }

    /**
     * Optimiser la base de données
     *
     * @throws DatabaseException
     *
     * @return mixed
     */
    public function optimizeDatabase()
    {
        if ($this->optimizeTable === false) {
            if ($this->db->debug) {
                throw new DatabaseException('Unsupported feature of the database platform you are using.');
            }

            return false;
        }

        $result = [];

        foreach ($this->db->listTables() as $tableName) {
            $res = $this->db->query(sprintf($this->optimizeTable, $this->db->escapeIdentifiers($tableName)));
            if (is_bool($res)) {
                return $res;
            }

            // Construisez le tableau de résultats...
            $res = $res->resultArray();

            // Postgre & SQLite3 renvoie un tableau vide
            if (empty($res)) {
                $key = $tableName;
            } else {
                $res  = current($res);
                $key  = str_replace($this->db->database . '.', '', current($res));
                $keys = array_keys($res);
                unset($res[$keys[0]]);
            }

            $result[$key] = $res;
        }

        return $result;
    }

    /**
     * Repair Table
     *
     * @throws DatabaseException
     *
     * @return mixed
     */
    public function repairTable(string $tableName)
    {
        if ($this->repairTable === false) {
            if ($this->db->debug) {
                throw new DatabaseException('Unsupported feature of the database platform you are using.');
            }

            return false;
        }

        $query = $this->db->query(sprintf($this->repairTable, $this->db->escapeIdentifiers($tableName)));
        if (is_bool($query)) {
            return $query;
        }

        $query = $query->resultArray();

        return current($query);
    }

    /**
     * Générer un CSV à partir d'un objet de résultat de requête
     */
    public function getCSVFromResult(ResultInterface $query, string $delim = ',', string $newline = "\n", string $enclosure = '"'): string
    {
        $out = '';

        foreach ($query->fieldNames() as $name) {
            $out .= $enclosure . str_replace($enclosure, $enclosure . $enclosure, $name) . $enclosure . $delim;
        }

        $out = substr($out, 0, -strlen($delim)) . $newline;

        // Parcourez ensuite le tableau de résultats et construisez les lignes
        while ($row = $query->unbufferedRow('array')) {
            $line = [];

            foreach ($row as $item) {
                $line[] = $enclosure . str_replace($enclosure, $enclosure . $enclosure, $item ?? '') . $enclosure;
            }

            $out .= implode($delim, $line) . $newline;
        }

        return $out;
    }

    /**
     * Générer des données XML à partir d'un objet de résultat de requête
     */
    public function getXMLFromResult(ResultInterface $query, array $params = []): string
    {
        foreach (['root' => 'root', 'element' => 'element', 'newline' => "\n", 'tab' => "\t"] as $key => $val) {
            if (! isset($params[$key])) {
                $params[$key] = $val;
            }
        }

        $root    = $params['root'];
        $newline = $params['newline'];
        $tab     = $params['tab'];
        $element = $params['element'];

        $xml = '<' . $root . '>' . $newline;

        while ($row = $query->unbufferedRow()) {
            $xml .= $tab . '<' . $element . '>' . $newline;

            foreach ($row as $key => $val) {
                $val = (! empty($val)) ? $this->xml_convert($val) : '';

                $xml .= $tab . $tab . '<' . $key . '>' . $val . '</' . $key . '>' . $newline;
            }

            $xml .= $tab . '</' . $element . '>' . $newline;
        }

        return $xml . '</' . $root . '>' . $newline;
    }

    /**
     * Sauvegarde de la base de données
     *
     * @param array|string $params
     *
     * @throws DatabaseException
     *
     * @return mixed
     */
    public function backup($params = [])
    {
        if (is_string($params)) {
            $params = ['tables' => $params];
        }

        $prefs = [
            'tables'             => [],
            'ignore'             => [],
            'filename'           => '',
            'format'             => 'gzip', // gzip, txt
            'add_drop'           => true,
            'add_insert'         => true,
            'newline'            => "\n",
            'foreign_key_checks' => true,
        ];

        if (! empty($params)) {
            foreach (array_keys($prefs) as $key) {
                if (isset($params[$key])) {
                    $prefs[$key] = $params[$key];
                }
            }
        }

        if (empty($prefs['tables'])) {
            $prefs['tables'] = $this->db->listTables();
        }

        if (! in_array($prefs['format'], ['gzip', 'txt'], true)) {
            $prefs['format'] = 'txt';
        }

        if ($prefs['format'] === 'gzip' && ! function_exists('gzencode')) {
            if ($this->db->debug) {
                throw new DatabaseException('The file compression format you chose is not supported by your server.');
            }

            $prefs['format'] = 'txt';
        }

        if ($prefs['format'] === 'txt') {
            return $this->_backup($prefs);
        }

        return gzencode($this->_backup($prefs));
    }

    /**
     * Version dépendante de la plateforme de la fonction de sauvegarde.
     *
     * @return mixed
     */
    abstract public function _backup(?array $prefs = null);

    /**
     * Convertir les caractères XML réservés en entités
     */
    private function xml_convert(string $str): string
    {
        $temp = '__TEMP_AMPERSANDS__';

        // Remplacez les entités par des marqueurs temporaires afin que les esperluettes ne soient pas gâchées
        $str = preg_replace('/&#(\d+);/', $temp . '\\1;', $str);

        $original = [
            '&',
            '<',
            '>',
            '"',
            "'",
            '-',
        ];

        $replacement = [
            '&amp;',
            '&lt;',
            '&gt;',
            '&quot;',
            '&apos;',
            '&#45;',
        ];

        $str = str_replace($original, $replacement, $str);

        // Décodez les marqueurs temporaires en entités
        return preg_replace('/' . $temp . '(\d+);/', '&#\\1;', $str);
    }
}
