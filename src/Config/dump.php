<?php

/**
 * This file is part of Blitz PHP framework - Database Layer.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

use Dimtrovich\DbDumper\Option;

return [
    /**
     * Chemin d'acces au dossier de sauvegarde de la base de donnees
     */
    // 'path' => storage_path('app/backups'),

    /**
     * Moteur de compression du backup
     */
    // 'compress'              => Option::COMPRESSION_NONE, // 'None'

    /**
     * Encodage à utiliser
     */
    // 'default_character_set' => Option::CHARSET_UTF8, // 'utf8'

    /**
     * @see https://dev.mysql.com/doc/refman/8.4/en/mysqldump.html#option_mysqldump_net_buffer_length
     */
    // 'net_buffer_length'     => Option::MAXLINESIZE, // 1000000

    // ----------------------------------------------------------------
    // Options de sauvegarde
    // ----------------------------------------------------------------

    /**
     * Tables à inclure lors du backup. Si vide, toutes les tables seront incluses.
     *
     * @see https://dev.mysql.com/doc/refman/8.4/en/mysqldump.html#option_mysqldump_include-tables
     *
     * @var string[]
     */
    'include_tables' => [],

    /**
     * Tables à exclure lors du backup. Si vide, aucune table ne sera exclue.
     *
     * @see https://dev.mysql.com/doc/refman/8.4/en/mysqldump.html#option_mysqldump_exclude-tables
     *
     * @var string[]
     */
    'exclude_tables' => [],

    /**
     * Vues à inclure lors du backup. Si vide, toutes les vues seront incluses.
     *
     * @see https://dev.mysql.com/doc/refman/8.4/en/mysqldump.html#option_mysqldump_include-views
     */
    'include_views' => [],

    /**
     * @var string[]
     *
     * @see https://dev.mysql.com/doc/refman/8.4/en/mysqldump.html#option_mysqldump_single-transaction
     */
    'init_commands' => [],

    /**
     * Ne pas extraire les données de ces tables (tableau de noms de tables), prise en charge des expressions rationnelles.
     *
     * @var bool|string[] TRUE pour ignorer toutes les tables.
     *
     * @see https://dev.mysql.com/doc/refman/8.4/en/mysqldump.html#option_mysqldump_no-data
     */
    'no_data' => [],

    /**
     * Spécifie si on doit créér une nouvelle table uniquement si aucune table du même nom n'existe déjà.
     * Aucun message d'erreur n'est généré si la table existe déjà.
     *
     * @see https://dev.mysql.com/doc/refman/8.4/en/mysqldump.html#option_mysqldump_if-not-exists
     */
    'if_not_exists' => false,

    /**
     * Spécifie si on doit supprimer l'option AUTO_INCREMENT de la définition de la base de données.
     *
     * @see https://dev.mysql.com/doc/refman/8.4/en/mysqldump.html#option_mysqldump_reset-auto-increment
     */
    'reset_auto_increment' => false,

    /**
     * Spécifie si on doit ajouter une instruction DROP DATABASE avant chaque instruction CREATE DATABASE.
     *
     * @see https://dev.mysql.com/doc/refman/8.4/en/mysqldump.html#option_mysqldump_add-drop-database
     */
    'add_drop_database' => false,

    /**
     * Spécifie si on doit ajouter une instruction DROP TABLE avant chaque instruction CREATE TABLE.
     *
     * @see https://dev.mysql.com/doc/refman/8.4/en/mysqldump.html#option_mysqldump_add-drop-table
     */
    'add_drop_table' => false,

    /**
     * Spécifie si on doit ajouter une instruction DROP TRIGGER avant chaque instruction CREATE TRIGGER.
     *
     * @see https://dev.mysql.com/doc/refman/8.4/en/mysqldump.html#option_mysqldump_add-drop-trigger
     */
    'add_drop_trigger' => true,

    /**
     * Spécifie si on doit entourer chaque vidage de table d'instructions LOCK TABLES et UNLOCK TABLES.
     *
     * @see https://dev.mysql.com/doc/refman/8.4/en/mysqldump.html#option_mysqldump_add-locks
     */
    'add_locks' => true,

    /**
     * Spécifie si on doit utiliser des instructions INSERT complètes qui incluent les noms des colonnes.
     *
     * @see https://dev.mysql.com/doc/refman/8.4/en/mysqldump.html#option_mysqldump_complete-insert
     */
    'complete_insert' => false,

    /**
     * Spécifie si on doit vider plusieurs bases de données.
     *
     * @see https://dev.mysql.com/doc/refman/8.4/en/mysqldump.html#option_mysqldump_databases
     */
    'databases' => false,

    /**
     * Spécifie si on doit désactiver la vérification des clés lors des insertions des données.
     *
     * @see https://dev.mysql.com/doc/refman/8.4/en/mysqldump.html#option_mysqldump_disable-keys
     */
    'disable_keys' => true,

    /**
     * Spécifie si on rédige les instructions INSERT en utilisant une syntaxe à plusieurs lignes qui inclut plusieurs listes VALUES.
     *
     * @see https://dev.mysql.com/doc/refman/8.4/en/mysqldump.html#option_mysqldump_extended-insert
     */
    'extended_insert' => true,

    /**
     * Spécifie si on inclut les événements du planificateur d\'événements pour les bases de données vidées dans la sortie.
     *
     * @see https://dev.mysql.com/doc/refman/8.4/en/mysqldump.html#option_mysqldump_events
     */
    'events' => false,

    /**
     * Spécifie si on doit décharger les colonnes binaires en utilisant la notation hexadécimale (par exemple, "abc" devient 0x616263).
     *
     * @see https://dev.mysql.com/doc/refman/8.4/en/mysqldump.html#option_mysqldump_hex-blob
     */
    'hex_blob' => true,

    /**
     * Spécifie si on doit ajouter des instructions INSERT IGNORE plutôt que des instructions INSERT.
     *
     * @see https://dev.mysql.com/doc/refman/8.4/en/mysqldump.html#option_mysqldump_insert-ignore
     */
    'insert_ignore' => false,

    /**
     * Possibilité de désactiver l'autocommission (insertions plus rapides, pas de problèmes avec les clés d'index)
     *
     * @see https://dev.mysql.com/doc/refman/8.4/en/mysqldump.html#option_mysqldump_no-autocommit
     */
    'no_autocommit' => true,

    /**
     *  Spécifie si on doit retirer les instructions CREATE DATABASE qui sont autrement incluses dans la sortie si l'option --databases est définie à TRUE.
     *
     * @see https://dev.mysql.com/doc/refman/8.4/en/mysqldump.html#option_mysqldump_no-create-db
     */
    'no_create_db' => false,

    /**
     * Spécifie si on doit retirer les instructions CREATE TABLE qui créent chaque table vidée.
     *
     * @see https://dev.mysql.com/doc/refman/8.4/en/mysqldump.html#option_mysqldump_no-create-info
     */
    'no_create_info' => false,

    /**
     * Spécifie si pour chaque base de données vidée, on doit verrouiller toutes les tables à vidanger avant de les vidanger.
     *
     * @see https://dev.mysql.com/doc/refman/8.4/en/mysqldump.html#option_mysqldump_lock-tables
     */
    'lock_tables' => true,

    /**
     * Spécifie si on doit inclure les routines stockées (procédures et fonctions) pour les bases de données vidées dans le résultat.
     *
     * @see https://dev.mysql.com/doc/refman/8.4/en/mysqldump.html#option_mysqldump_routines
     */
    'routines' => false,

    /**
     * Cette option définit le mode d'isolation de la transaction sur REPEATABLE READ et envoie une instruction SQL START TRANSACTION au serveur avant de déverser les données.
     *
     * @see https://dev.mysql.com/doc/refman/8.4/en/mysqldump.html#option_mysqldump_single-transaction
     */
    'single_transaction' => true,

    /**
     * Spécifie si on doit exclure les déclencheurs pour chaque table vidée dans la sortie.
     *
     * @see https://dev.mysql.com/doc/refman/8.4/en/mysqldump.html#option_mysqldump_triggers
     */
    'skip_triggers' => false,

    /**
     * @see https://dev.mysql.com/doc/refman/8.4/en/mysqldump.html#option_mysqldump_tz-utc
     */
    'skip_tz_utc' => false,

    /**
     * @see https://dev.mysql.com/doc/refman/8.4/en/mysqldump.html#option_mysqldump_comments
     */
    'skip_comments' => false,

    /**
     * Spécifie si on doit exclure la date de génération du dump de la base de données.
     *
     * @see https://dev.mysql.com/doc/refman/8.4/en/mysqldump.html#option_mysqldump_skip-dump-date
     */
    'skip_dump_date' => false,

    /**
     * Spécifie si on doit omettre les clauses DEFINER et SQL SECURITY dans les instructions CREATE pour les vues et les programmes stockés.
     *
     * @see https://dev.mysql.com/doc/refman/8.4/en/mysqlpump.html#option_mysqlpump_skip-definer
     */
    'skip_definer' => false,

    // ----------------------------------------------------------------
    // Autres outils propre au systeme de backup
    // ----------------------------------------------------------------

    /**
     * Callback de transformation les valeurs lors de l'exportation.
     * Un exemple de cas d'utilisation pour cela est la suppression de données sensibles du dump de base de données.
     */
    'rowTransformer' => static fn (string $table, array $row): array => $row,

    /**
     * Callback qui sera utilisé pour signaler l'état d'avancement du dump.
     *
     * Utilisé lors de la sauvegarde
     */
    'onTableExport' => static function (string $table, int $rowCount) {
    },

    /**
     * Callback qui sera utilisé pour signaler l'état d'avancement du la restauration de la base de données.
     *
     * Utilisé lors de la restaration
     */
    'onTableCreate' => static function (string $table) {
    },

    /**
     * Callback qui sera utilisé pour signaler l'état d'avancement du la restauration de la base de données.
     *
     * Utilisé lors de la restaration
     */
    'onTableInsert' => static function (string $table, int $rowCount) {
    },
];
