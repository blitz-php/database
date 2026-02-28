<?php

/**
 * This file is part of Blitz PHP framework - Database Layer.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Database\Commands\Generators;

use BlitzPHP\Cli\Console\Command;
use BlitzPHP\Cli\Traits\GeneratorTrait;
use InvalidArgumentException;

/**
 * Génère un squelette de fichier de migration.
 * 
 * Analyse le nom de la migration pour déterminer automatiquement
 * l'action (create/modify) et la table concernée.
 */
class Migration extends Command
{
    use GeneratorTrait;

    /**
     * {@inheritDoc}
     */
    protected string $group = 'Générateurs';

    /**
     * {@inheritDoc}
     */
    protected string $name = 'make:migration';

    /**
     * {@inheritDoc}
     */
    protected string $description = 'Génère un nouveau fichier de migration.';

    /**
     * {@inheritDoc}
     */
    protected array $arguments = [
        'name' => 'Le nom de la classe de migration (ex: create_users_table, CreateUsersTable, add_email_to_users)',
    ];

    /**
     * {@inheritDoc}
     */
    protected array $options = [
        '--table'     => 'Force le nom de la table (optionnel - sera déduit du nom si non fourni)',
        '--create'    => 'Spécifie qu\'on veut créer une nouvelle table',
        '--alter'     => 'Spécifie qu\'on veut modifier une table existante',
        '--session'   => 'Génère une migration pour la table de sessions',
        '--group'     => 'Groupe de base de données',
        '--namespace' => ['Définit le namespace de la migration', APP_NAMESPACE],
        '--anonymous' => 'Générer une classe anonyme (au lieu d\'une classe nommée)',
        '--suffix'    => 'Ajoute "Migration" au nom de la classe (par exemple, User => UserMigration)',
    ];

    /**
     * Mots-clés pour les actions de création
     */
    protected array $createKeywords = ['create', 'make', 'new', 'add'];

    /**
     * Mots-clés pour les actions de modification
     */
    protected array $modifyKeywords = [
        'update', 'modify', 'alter', 'change', 'edit',
        'add', 'remove', 'drop', 'delete', 'rename',
        'add_column', 'remove_column', 'drop_column', 'rename_column',
        'add_index', 'remove_index', 'drop_index',
        'add_foreign', 'remove_foreign', 'drop_foreign',
    ];

    /**
     * Mots-clés pour les actions de suppression
     */
    protected array $dropKeywords = ['drop', 'delete', 'remove'];

    /**
     * {@inheritDoc}
     */
    public function handle()
    {
        $this->component    = 'Migration';
        $this->directory    = 'Database\Migrations';
        $this->template     = 'migration.tpl.php';
        $this->templatePath = __DIR__ . '/Views';

        try {
            $this->generateClass($this->parameters());
            
            return EXIT_SUCCESS;
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());
            
            return EXIT_ERROR;
        }
    }

    /**
     * Prépare les options et effectue les remplacements nécessaires.
     */
    protected function prepare(string $class): string
    {
        $name      = $this->argument('name');
        $anonymous = $this->option('anonymous') === true;
        
        $parsed = $this->parseMigrationName($name);
        
        $create  = $this->option('create');
        $alter   = $this->option('alter');
        $table   = $this->option('table');
        $session = $this->option('session') === true;

        if ($create && $alter) {
            throw new InvalidArgumentException(
                'Impossible d\'utiliser "create" et "alter" simultanément.'
            );
        }

        if ($create) {
            $action        = 'create';
            $detectedTable = is_string($create) ? $create : $parsed['table'];
        } elseif ($alter) {
            $action        = 'alter';
            $detectedTable = is_string($alter) ? $alter : $parsed['table'];
        } else {
            $action        = $parsed['action'];
            $detectedTable = $parsed['table'];
        }

         // Si c'est une session, on force l'action à 'create' et la table par défaut
        if ($session) {
            $action = 'create';
            $table = $table ?: 'blitz_sessions';
            $detectedTable = $table;
        }

        if (!$action) {
            throw new InvalidArgumentException(
                "Impossible de déterminer l'action à partir du nom '{$name}'.\n" .
                "Utilisez --create=table ou --table=table pour spécifier explicitement."
            );
        }

        $table = $this->cleanTableName($table ?: $detectedTable);

        // Valider qu'on a une table (sauf pour certaines actions)
        if (!$table && !in_array($action, ['drop', 'delete', 'remove'])) {
            throw new InvalidArgumentException(
                "Impossible de déterminer la table à partir du nom '{$name}'. \n" .
                "Utilisez --table=tableName pour spécifier le nom de la table explicitement."
            );
        }

        $group   = $this->option('group');
        $matchIP = config('session.match_ip', false);

        $data = [
            'group'     => $group,
            'action'    => $action,
            'table'     => $table,
            'session'   => $session,
            'matchIP'   => $matchIP,
            'anonymous' => $anonymous,
        ];

        return $this->parseTemplate($class, [], [], $data);
    }

    /**
     * Analyse le nom de la migration pour en extraire l'action et la table.
     *
     * @return array{action: string|null, table: string|null}
     */
    protected function parseMigrationName(string $name): array
    {
        $name = $this->normalizeName($name);
        
        $result = [
            'action' => null,
            'table'  => null,
        ];

        // Pattern 1: action_table (ex: create_users_table, add_email_to_users)
        if (preg_match('/^(' . $this->getKeywordsPattern() . ')_(.+?)(?:_table)?$/', $name, $matches)) {
            $result['action'] = $this->mapKeywordToAction($matches[1]);
            $result['table'] = $this->extractTableName($matches[2]);
        }
        
        // Pattern 2: ActionTable (ex: CreateUsersTable, AddEmailToUsers)
        elseif (preg_match('/^(' . $this->getKeywordsPattern(true) . ')([A-Z][a-zA-Z0-9]+)$/', $name, $matches)) {
            $result['action'] = $this->mapKeywordToAction(strtolower($matches[1]));
            $result['table'] = $this->decamelize($matches[2]);
        }
        
        // Pattern 3: table_action (ex: users_create, users_add_email)
        elseif (preg_match('/^([a-z][a-z0-9_]+)_(' . $this->getKeywordsPattern() . ')(?:_(.+))?$/', $name, $matches)) {
            $result['table'] = $matches[1];
            $result['action'] = $this->mapKeywordToAction($matches[2]);
            
            // Si c'est une modification de colonne, on garde le nom original
            if (isset($matches[3])) {
                $result['table'] .= ' (colonne: ' . $matches[3] . ')';
            }
        }

        return $result;
    }

    /**
     * Nettoie le nom de la table en enlevant les préfixes/suffixes redondants et les caractères indésirables.
     */
    protected function cleanTableName(?string $table): ?string
    {
        if ($table === null) {
            return null;
        }

        // Enlever les préfixes/suffixes "table" redondants
        $table = preg_replace('/^(table_|tbl_)/', '', $table);
        $table = preg_replace('/(_table|_tbl)$/', '', $table);
        
        // Enlever les caractères indésirables
        $table = preg_replace('/[^a-z0-9_]/', '', $table);
        
        // Éviter les underscores multiples
        $table = preg_replace('/_+/', '_', $table);
        
        $table = trim($table, '_');
        
        return $table ?: null;
    }

    /**
     * Normalise le nom en snake_case pour l'analyse
     */
    protected function normalizeName(string $name): string
    {
        // Convertir CamelCase en snake_case
        $name = preg_replace('/(?<!^)[A-Z]/', '_$0', $name);
        $name = strtolower($name);
        
        // Nettoyer les caractères spéciaux
        $name = preg_replace('/[^a-z0-9_]/', '', $name);
        
        return trim($name, '_');
    }

    /**
     * Convertit un nom CamelCase en snake_case
     */
    protected function decamelize(string $input): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
    }

    /**
     * Extrait le nom de la table d'une chaîne
     */
    protected function extractTableName(string $input): string
    {
        // Enlever les prépositions courantes
        $input = preg_replace('/_(to|in|on|at|for|from)_/', '_', $input);
        
        // Garder seulement le dernier segment significatif
        $parts = explode('_', $input);
        
        // Si c'est "add_column_to_table", on veut "table"
        if (count($parts) > 2 && in_array($parts[0], ['add', 'remove', 'drop'])) {
            return end($parts);
        }
        
        return $input;
    }

    /**
     * Obtient le pattern des mots-clés pour la regex
     */
    protected function getKeywordsPattern(bool $forCamelCase = false): string
    {
        $allKeywords = array_merge(
            $this->createKeywords,
            $this->modifyKeywords,
            $this->dropKeywords
        );
        
        $allKeywords = array_unique($allKeywords);
        
        if ($forCamelCase) {
            // Pour CamelCase, on garde les mots tels quels
            $allKeywords = array_map('ucfirst', $allKeywords);
        }
        
        return implode('|', array_map('preg_quote', $allKeywords));
    }

    /**
     * Mappe un mot-clé à une action
     */
    protected function mapKeywordToAction(string $keyword): string
    {
        $keyword = strtolower($keyword);
        
        if (in_array($keyword, $this->createKeywords)) {
            return 'create';
        }
        
        if (in_array($keyword, $this->dropKeywords)) {
            return 'drop';
        }
        
        if (in_array($keyword, $this->modifyKeywords)) {
            return 'alter';
        }
        
        // Par défaut, on considère comme une modification
        return 'alter';
    }

    /**
     * Modifie le nom de base du fichier avant de l'enregistrer.
     */
    protected function basename(string $filename): string
    {
        $timestamp = gmdate(config('migrations.timestampFormat', 'YmdHis_'));
        
        return $timestamp . $this->decamelize(basename($filename));
    }
}
