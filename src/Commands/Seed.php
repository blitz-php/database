<?php

/**
 * This file is part of Blitz PHP framework.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Database\Commands;

use BlitzPHP\Database\Seeder\Seeder;
use BlitzPHP\Container\Services;
use InvalidArgumentException;

/**
 * Exécute le fichier Seeder spécifié pour remplir la base de données avec certaines données.
 */
class Seed extends DatabaseCommand
{
    /**
     * {@inheritDoc}
     */
    protected $name = 'db:seed';

    /**
     * {@inheritDoc}
     */
    protected $description = 'Exécute le seeder spécifié pour remplir les données connues dans la base de données.';

    /**
     * {@inheritDoc}
     */
    protected $arguments = [
        'name' => 'Nom du seedr a executer',
    ];

    /**
     * {@inheritDoc}
     */
    public function execute(array $params)
    {
        if (empty($name = $this->argument('name'))) {
            $name = $this->prompt(lang('Migrations.migSeeder'), null, static function ($val) {
                if (empty($val)) {
                    throw new InvalidArgumentException('Veuillez entrer le nom du seeder.');
                }

                return $val;
            });
        }

        $seedClass = APP_NAMESPACE . '\Database\Seeds\\';
        $seedClass .= str_replace($seedClass, '', $name);

        /**
         * @var Seeder
         */
        $seeder = new $seedClass($this->db);

        if ($seeder->getLocale() === '') {
            $seeder->setLocale(config('app.language'));
        }

        $this->task('Demarrage du seed')->eol();
        sleep(2);
        $this->info('Remplissage en cours de traitement');

        if (method_exists($seeder, 'run')) {
            Services::container()->call([$seeder, 'run']);
        }

        $usedSeed = [
            Services::container()->call([$seeder, 'execute']),
            ...$seeder->getSeeded(),
        ];

        $this->eol()->success('Opération terminée.');

        foreach ($usedSeed as $seeded) {
            $this->eol()->write('- ')->writer->yellow($seeded);
        }
    }
}
