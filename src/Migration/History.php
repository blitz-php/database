<?php

/**
 * This file is part of Blitz PHP framework - Database Layer.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Database\Migration;

use BlitzPHP\Database\Connection\BaseConnection;
use BlitzPHP\Database\DatabaseManager;

/**
 * Gestionnaire de l'historique des migrations
 * 
 * Cette classe s'occupe de la table de migrations qui enregistre
 * toutes les migrations exécutées.
 */
class History
{
    /**
     * Connection à la base de données
     */
    protected BaseConnection $db;

    /** 
     * Indique si la table d'historique a été vérifiée/créée
     */
    private bool $tableChecked = false;

    /**
     * Constructeur
     *
     * @param string         $table Nom de la table d'historique des migrations
     */
    public function __construct(protected DatabaseManager $dbManager, protected string $table = 'migrations')
    {
        $this->db = $this->dbManager->activeConnection();

        $this->ensureTable();
    }

    /**
     * Crée la table d'historique si elle n'existe pas
     */
    protected function ensureTable(): void
    {
        if ($this->tableChecked || $this->db->tableExists($this->table)) {
            return;
        }

        $builder = new Builder($this->table);
        $builder->id();
        $builder->string('migration');
        $builder->string('version');
        $builder->string('namespace');
        $builder->string('group');
        $builder->unsignedInteger('batch');
        $builder->integer('time');
        $builder->createTable(true);
        
        (new Transformer($this->dbManager->creator($this->db)))->process($builder);

        $this->tableChecked = true;
    }

    /**
     * Récupère tout l'historique
     *
     * @return array<object>
     */
    public function getAll(?string $group = null): array
    {
        return $this->db->table($this->table)
            ->orderBy('batch', 'ASC')
            ->orderBy('id', 'ASC')
            ->when($group, function ($query) use ($group) {
                $query->where('group', $group);
            })
            ->all();
    }

    /**
     * Récupère l'historique d'un lot
     * 
     * @return array<object>
     */
    public function getBatch(int $batch, string $order = 'asc'): array
    {
        return $this->db->table($this->table)
            ->where('batch', $batch)
            ->orderBy('id', $order)
            ->all();
    }

    /**
     * Récupère le dernier numéro de lot
     */
    public function getLastBatch(): int
    {
        return (int) $this->db->table($this->table)->max('batch');
    }

    /**
     * Récupère tous les numéros de lots
     *
     * @return array<int>
     */
    public function getBatches(): array
    {
        $rows = $this->db->table($this->table)
            ->select('batch')
            ->distinct()
            ->orderBy('batch', 'ASC')
            ->all();

        return array_column($rows, 'batch');
    }

    /**
     * Ajoute une entrée dans l'historique
     *
     * @param string $version   Version de la migration
     * @param string $migration Nom de la migration
     * @param string $namespace Namespace
     * @param string $group     Groupe de connexion
     * @param int    $batch     Numéro de lot
     */
    public function add(string $version, string $migration, string $namespace, string $group, int $batch): void
    {
        $this->db->table($this->table)->insert([
            'version'   => $version,
            'migration' => $migration,
            'namespace' => $namespace,
            'group'     => $group,
            'batch'     => $batch,
            'time'      => time(),
        ]);
    }

    /**
     * Supprime une entrée de l'historique
     */
    public function remove(int $id): void
    {
        $this->db->table($this->table)->where('id', $id)->delete();
    }

    /**
     * Vide l'historique
     */
    public function clear(): void
    {
        $this->db->table($this->table)->truncate();
    }

    /**
     * Vérifie si une migration a déjà été exécutée
     */
    public function has(string $class): bool
    {
        return $this->db->table($this->table)
            ->where('class', $class)
            ->count() > 0;
    }
}
