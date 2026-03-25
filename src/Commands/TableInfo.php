<?php

/**
 * This file is part of Blitz PHP framework - Database Layer.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Database\Commands;

use BlitzPHP\Database\Connection\BaseConnection;
use BlitzPHP\Database\Query\Result;
use InvalidArgumentException;
use PDO;

/**
 * Obtenir les données de la table si elles existent dans la base de données.
 */
class TableInfo extends DatabaseCommand
{
    /**
     * {@inheritDoc}
     */
    protected string $name = 'db:table';

    /**
     * {@inheritDoc}
     */
    protected string $description = 'Récupère les informations sur la table sélectionnée.';

    /**
     * {@inheritDoc}
     */
    protected string $usage = <<<'EOL'
            db:table --show
            db:table --metadata
            db:table my_table --metadata
            db:table my_table
            db:table my_table --limit-rows 5 --limit-field-value 10 --desc
        EOL;

    /**
     * {@inheritDoc}
     */
    protected array $arguments = [
        'table' => 'Le nom de la table dont on veut avoir les infos',
    ];

    /**
     * {@inheritDoc}
     */
    protected array $options = [
        '--show'              => 'Liste les noms de toutes les tables de la base de données.',
        '--metadata'          => 'Récupère la liste contenant les informations du champ.',
        '--desc'              => 'Trie les lignes du tableau dans l\'ordre DESC.',
        '--limit-rows'        => 'Limite le nombre de lignes. Par défaut : 10.',
        '--limit-field-value' => 'Limite la longueur des valeurs des champs. Par défaut : 15.',
        '--group'             => 'Groupe de bases de données à afficher.',
    ];

    /**
     * @var list<list<int|string>> Données de la table.
     */
    private array $tbody;

    /**
     * @var bool Trier les lignes du tableau dans l'ordre DESC ou non.
     */
    private bool $sortDesc = false;

    private string $prefix = '';

    private BaseConnection $db;

    public function handle()
    {
        try {
            $this->db = $this->db($this->option('group'));
        } catch (InvalidArgumentException $e) {
            $this->fail($e->getMessage());

            return EXIT_ERROR;
        }

        $this->prefix = $this->db->getPrefix();

        $tables = $this->db->listTables();

        $this->showDBConfig();

        if ($this->hasParameter('desc')) {
            $this->sortDesc = true;
        }

        if ($tables === []) {
            $this->error('La base de données n\'a aucune table!');

            return EXIT_ERROR;
        }

        if (true === $this->option('show')) {
            $this->showAllTables($tables);

            return EXIT_SUCCESS;
        }

        $tableName       = $this->argument('table');
        $limitRows       = (int) $this->option('limit-rows', 10);
        $limitFieldValue = (int) $this->option('limit-field-value', 15);

        while (! in_array($tableName, $tables, true)) {
            $tableName = $this->choice("Voici les tables disponible dans votre base de données. \nQuelle table souhaitez-vous afficher?", $tables);
        }

        if (true === $this->option('metadata')) {
            $this->showFieldMetaData($tableName);

            return EXIT_SUCCESS;
        }

        $this->showDataOfTable($tableName, $limitRows, $limitFieldValue);

        return EXIT_SUCCESS;
    }

    private function showDBConfig(): void
    {
        $config = $this->db->getConfig();

        $this->table([[
            'hostname' => $config['hostname'],
            'database' => $this->db->getDatabase(),
            'username' => $config['username'],
            'driver'   => $this->db->getPlatform(),
            'prefix'   => $this->prefix,
            'port'     => $config['port'],
        ]]);
    }

    private function removeDBPrefix(): void
    {
        $this->db->setPrefix('');
    }

    private function restoreDBPrefix(): void
    {
        $this->db->setPrefix($this->prefix);
    }

    private function showDataOfTable(string $tableName, int $limitRows, int $limitFieldValue)
    {
        $this->newLine()->io->blackBgYellow("Données de la table \"{$tableName}\":", true);

        $this->removeDBPrefix();
        $thead = $this->db->getColumnNames($tableName);
        $this->restoreDBPrefix();

        // Si on a un champ id, on trie en fonction de lui.
        $sortField = null;
        if (in_array('id', $thead, true)) {
            $sortField = 'id';
        }

        $this->tbody = $this->makeTableRows($tableName, $limitRows, $limitFieldValue, $sortField);

        $this->table($this->tbody);
    }

    /**
     * @param list<string> $tables
     */
    private function showAllTables(array $tables): void
    {
        $this->newLine()->io->blackBgYellow("Voici une liste des noms de toutes les tables de base de données\u{a0}:", true);

        $this->tbody = $this->makeTbodyForShowAllTables($tables);

        $this->table($this->tbody);
    }

    private function makeTbodyForShowAllTables(array $tables): array
    {
        $this->removeDBPrefix();

        foreach ($tables  as $id => $tableName) {
            $table = $this->db->escapeIdentifiers($tableName);
            /** @var Result $db */
            $db = $this->db->query("SELECT * FROM {$table}");

            $this->tbody[] = [
                'ID'                       => $id + 1,
                'Nom de la table'          => $tableName,
                'Nombre d\'enregistrement' => $db->numRows(),
                'Nombre de champs'         => $db->countColumn(),
            ];
        }

        $this->restoreDBPrefix();

        if ($this->sortDesc) {
            krsort($this->tbody);
        }

        return $this->tbody;
    }

    /**
     * Fabrique les lignes du tableau
     *
     * @return list<list<int|string>>
     */
    private function makeTableRows(
        string $tableName,
        int $limitRows,
        int $limitFieldValue,
        ?string $sortField = null,
    ): array {
        $this->tbody = [];

        $this->removeDBPrefix();
        $builder = $this->db->table($tableName);
        $builder->limit($limitRows);
        if ($sortField !== null) {
            $builder->orderBy($sortField, $this->sortDesc ? 'DESC' : 'ASC');
        }
        $rows = $builder->result(PDO::FETCH_ASSOC);
        $this->restoreDBPrefix();

        foreach ($rows as $row) {
            $row = array_map(
                static fn ($item): string => mb_strlen((string) $item) > $limitFieldValue
                    ? mb_substr((string) $item, 0, $limitFieldValue) . '...'
                    : (string) $item,
                $row,
            );
            $this->tbody[] = $row;
        }

        if ($sortField === null && $this->sortDesc) {
            krsort($this->tbody);
        }

        return $this->tbody;
    }

    private function showFieldMetaData(string $tableName): void
    {
        $this->newLine()->io->blackBgYellow("Liste des informations de métadonnées dans la table \"{$tableName}\"\u{a0}:", true);

        $this->removeDBPrefix();
        $fields = $this->db->getColumnData($tableName);
        $this->restoreDBPrefix();

        foreach ($fields as $row) {
            $this->tbody[] = [
                'Nom du champ'      => $row->name,
                'Type'              => $row->type,
                'Taille maximale'   => (string) $row->max_length,
                'Nullable'          => isset($row->nullable) ? $this->setYesOrNo($row->nullable) : 'n/a',
                'Valeur par défaut' => (string) $row->default,
                'Clé primaire'      => isset($row->primary_key) ? $this->setYesOrNo($row->primary_key) : 'n/a',
            ];
        }

        if ($this->sortDesc) {
            krsort($this->tbody);
        }

        $this->table($this->tbody);
    }

    /**
     * @param bool|int|string|null $fieldValue
     */
    private function setYesOrNo($fieldValue): string
    {
        if ((bool) $fieldValue) {
            return 'Oui';
        }

        return 'Non';
    }
}
