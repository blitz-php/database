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
use BlitzPHP\Database\Commands\DatabaseCommand;
use BlitzPHP\Database\Config\Services;
use BlitzPHP\Utilities\Date;
use BlitzPHP\Utilities\Helpers;
use BlitzPHP\Utilities\String\Text;
use Dimtrovich\DbDumper\Exceptions\Exception as DumperException;
use InvalidArgumentException;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Importe une sauvegarde votre base de données.
 */
class Restore extends DatabaseCommand
{
    /**
     * {@inheritDoc}
     */
    protected $name = 'db:restore';

    /**
     * {@inheritDoc}
     */
    protected $description = 'Restore votre base de données à partir d\'un fichier de sauvegarde';

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
        '--path'  => 'Dossier à partir duquel on cherchera les fichiers de restauration des données',
        '--group' => 'Groupe de la base de données à utiliser',
        '--file'  => 'Fichier à utiliser pour la restauration des données',
    ];

    /**
     * Execution de la commande
     */
    public function execute(array $params)
    {
        $config = $this->getConfig($params);

        try {
            $importer = Services::dbImporter();
        } catch (DumperException $e) {
            $this->fail($e->getMessage());

            return;
        }

        $path = $config['path'] ?? storage_path('app/backups');
        $file = $config['file'] ?? null;

        if ($file === null && ! is_dir($path)) {
            $this->info(sprintf('Le dossier de sauvegarde "%s" n\'existe pas ou est inaccessible.', $path));
            $this->io->warn('Impossible de restaurer la base de données car aucun fichier de restauration n\'est disponible');

            return;
        }

        if ($file === null) {
            $files = Services::fs()->files($path, false, 'modifiedTime');
            $files = Helpers::collect($files)
                ->reverse()
                ->filter(static fn (SplFileInfo $f) => preg_match('/[a-zA-Z0-9-]+\-database\-backup\_\d{14}$/', $f->getFilenameWithoutExtension()))
                ->map(static fn (SplFileInfo $f) => [
                    'version'    => $f->getFilename(),
                    'updated_at' => Date::createFromTimestamp($f->getMTime())->format('d M Y - H:i:s'),
                    'filename'   => $f->getPathname(),
                ])
                ->take(5)->values()->all();

            if ($files === []) {
                $this->io->warn('Aucun fichier de sauvegarde trouvé');

                return;
            }

            $this->center('Liste des sauvegardes disponibles', ['sep' => '-'])->eol();

            $this->justify('Version', 'Dernière modification');

            foreach ($files as $i => $f) {
                $this->justify($this->color->warn(++$i) . ' ' . $f['version'], $this->color->info($f['updated_at']));
            }

            $version = $this->eol()->prompt('Veuillez entrer le numero de la version de votre base de donnee', 1, static function ($value) use ($i) {
                if (! is_numeric($value) || $value < 1 || $value > $i) {
                    $indication = $i > 1 ? ' (entre 1 et ' . $i . ')' : '';

                    throw new InvalidArgumentException('Veuillez entrer un numero de la version valide' . $indication);
                }

                return $value - 1;
            });

            $file = $files[$version]['filename'];
        }

        $this->task('Restauration de la base de données en cours...')->eol();

        $importer->onTableCreate(function (string $tableName) use ($config) {
            if (isset($config['onTableCreate']) && is_callable($config['onTableCreate'])) {
                $config['onTableCreate']($tableName);
            }

            $this->justify('Table ' . $this->color->ok($tableName), $this->color->warn('restaurée'));
        });

        $importer->onTableInsert(static function (string $tableName, $rowCount) use ($config) {
            if (isset($config['onTableInsert']) && is_callable($config['onTableInsert'])) {
                $config['onTableInsert']($tableName, $rowCount);
            }
        });

        $importer->process($file);

        $this->eol()->border();
        $this->justify('Base de données restaurée avec succès', clean_path($file), [
            'first'  => ['fg' => Color::YELLOW],
            'second' => ['fg' => Color::GREEN],
        ]);
    }

    private function getConfig(array $params): array
    {
        $config = config('dump', []);

        foreach ($params as $key => $val) {
            if (null !== $val && is_string($key)) {
                $key = Text::snake($key);

                $config[$key] = $val;
            }
        }

        return $config;
    }
}
