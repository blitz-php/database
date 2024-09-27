<?php

/**
 * This file is part of Blitz PHP framework - Database Layer.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Database\Commands\Dump;

use Ahc\Cli\Output\Color;
use BlitzPHP\Core\Application;
use BlitzPHP\Database\Commands\DatabaseCommand;
use BlitzPHP\Database\Config\Services;
use BlitzPHP\Utilities\String\Text;
use Dimtrovich\DbDumper\Exceptions\Exception as DumperException;
use Dimtrovich\DbDumper\Option;

/**
 * Exporte et sauvegarde votre base de données.
 */
class Backup extends DatabaseCommand
{
    /**
     * {@inheritDoc}
     */
    protected $name = 'db:backup';

    /**
     * {@inheritDoc}
     */
    protected $description = 'Exporte et sauvegarde votre base de données';

    /**
     * {@inheritDoc}
     */
    protected $required = [
        'dimtrovich/db-dumper',
    ];

    /**
     * {@inheritDoc}
     */
    protected $options = [
        '--path'  => 'Dossier de sauvegarde',
        '--group' => 'Groupe de la base de données à utiliser',

        '--compress'              => 'Moteur de compression du backup',
        '--default-character-set' => 'Encodage à utiliser',
        '--include-tables'        => 'Tables à inclure lors du backup (Séparées par des virgules). Si absent, toutes les tables seront incluses',
        '--exclude-tables'        => 'Tables à exclure lors du backup (Séparées par des virgules). Si absent, aucune table ne sera exclue',
        '--include-views'         => 'Vues à inclure lors du backup (Séparées par des virgules). Si absent, toutes les vues seront incluses',
        '--if-not-exists'         => 'Spécifie si on doit créér une nouvelle table uniquement si aucune table du même nom n\'existe déjà. Aucun message d\'erreur n\'est généré si la table existe déjà',
        '--reset-auto-increment'  => 'Spécifie si on doit supprimer l\'option AUTO_INCREMENT de la définition de la base de données',
        '--add-drop-database'     => 'Spécifie si on doit ajouter une instruction DROP DATABASE avant chaque instruction CREATE DATABASE.',
        '--add-drop-table'        => 'Spécifie si on doit ajouter une instruction DROP TABLE avant chaque instruction CREATE TABLE.',
        '--add-drop-trigger'      => 'Spécifie si on doit ajouter une instruction DROP TRIGGER avant chaque instruction CREATE TRIGGER.',
        '--add-locks'             => 'Spécifie si on doit entourer chaque vidage de table d\'instructions LOCK TABLES et UNLOCK TABLES.',
        '--complete-insert'       => 'Spécifie si on doir utiliser des instructions INSERT complètes qui incluent les noms des colonnes.',
        '--databases'             => 'Spécifie si on doit vider plusieurs bases de données.',
        '--disable-keys'          => 'Spécifie si on doit désactiver la vérification des clés lors des insertions des données',
        '--extended-insert'       => 'Spécifie si on rédige les instructions INSERT en utilisant une syntaxe à plusieurs lignes qui inclut plusieurs listes VALUES.',
        '--events'                => 'Spécifie si on inclut les événements du planificateur d\'événements pour les bases de données vidées dans la sortie.',
        '--hex-blob'              => 'Spécifie si on doit décharger les colonnes binaires en utilisant la notation hexadécimale (par exemple, "abc" devient 0x616263).',
        '--insert-ignore'         => 'Spécifie si on doit ajouter des instructions INSERT IGNORE plutôt que des instructions INSERT.',
        '--lock-tables'           => 'Spécifie si pour chaque base de données vidée, on doit verrouiller toutes les tables à vidanger avant de les vidanger.',
        '--routines'              => 'Spécifie si on doit inclure les routines stockées (procédures et fonctions) pour les bases de données vidées dans le résultat.',
        '--single-transaction'    => 'Cette option définit le mode d\'isolation de la transaction sur REPEATABLE READ et envoie une instruction SQL START TRANSACTION au serveur avant de déverser les données.',
        '--skip-triggers'         => 'Spécifie si on doit exclure les déclencheurs pour chaque table vidée dans la sortie.',
        '--skip-dump-date'        => 'Spécifie si on doit exclure la date de génération du dump de la base de données.',
        '--skip-definer'          => 'Spécifie si on doit omettre les clauses DEFINER et SQL SECURITY dans les instructions CREATE pour les vues et les programmes stockés.',
    ];

    /**
     * Execution de la commande
     */
    public function execute(array $params)
    {
        $config            = $this->getConfig($params);
        $config['message'] = $this->buildBackupMessage($config);

        try {
            $exporter = Services::dbExporter(config: $config);
        } catch (DumperException $e) {
            $this->fail($e->getMessage());

            return;
        }

        $this->task('Exportation de la base de données en cours...', 1)->eol();

        $option = $exporter->getOption();

        $ext = match ($option->compress) {
            Option::COMPRESSION_GZIP  => 'gz',
            Option::COMPRESSION_BZIP2 => 'bz2',
            default                   => 'sql'
        };

        $path = $config['path'] ?? storage_path('app/backups');
        if (! is_dir($path)) {
            @mkdir($path, 0o777, true);
        }

        $filename = Text::convertTo(config('app.name', 'blitz'), 'kebab') . '-database-backup_' . date('YmdHis') . '.' . $ext;
        $filename = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($path, '/\\')) . DIRECTORY_SEPARATOR . $filename;

        if (isset($config['rowTransformer']) && is_callable($config['rowTransformer'])) {
            $exporter->transformTableRow($config['rowTransformer']);
        }

        $exporter->onTableExport(function (string $tableName, int $rowCount) use ($config) {
            if (isset($config['onTableExport']) && is_callable($config['onTableExport'])) {
                $config['onTableExport']($tableName, $rowCount);
            }

            $this->justify('Table ' . $this->color->ok($tableName) . ' exportée', $this->color->warn($rowCount) . ' données');
        });

        $exporter->process($filename);

        $this->eol()->border();
        $this->justify('Base de données exportée avec succès', clean_path($filename), [
            'first'  => ['fg' => Color::YELLOW],
            'second' => ['fg' => Color::GREEN],
        ]);
    }

    private function buildBackupMessage(array $config): string
    {
        $message = '-- Base de donnée exportée par BlitzPHP. Version: ' . Application::VERSION . PHP_EOL .
                '-- https://github.com/blitz-php/framework' . PHP_EOL .
                '-- ' . PHP_EOL .
                '-- Application: ' . config('app.name') . PHP_EOL;

        if (isset($config['message']) && $config['message'] !== '') {
            if (! str_starts_with($config['message'], '-- ')) {
                $config['message'] = '-- ' . $config['message'];
            }

            $message .= '-- ' . PHP_EOL . $config['message'] . PHP_EOL;
        }

        return $message;
    }

    private function getConfig(array $params): array
    {
        $config = config('dump', []);

        foreach ($params as $key => $val) {
            if (null !== $val && is_string($key)) {
                $key = Text::snake($key);

                if (in_array($key, ['include_tables', 'exclude_tables', 'include_views'], true)) {
                    $val = array_map('trim', explode(',', $val));
                } elseif (! in_array($key, ['default_character_set', 'compress'], true)) {
                    $val = $val === 'true' || $val === '1' || $val === true;
                }

                $config[$key] = $val;
            }
        }

        return $config;
    }
}
