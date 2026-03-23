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
use BlitzPHP\Database\Seeder\Seeder;
use InvalidArgumentException;

/**
 * Exécute le fichier Seeder spécifié pour remplir la base de données avec certaines données.
 */
class Seed extends DatabaseCommand
{
    /**
     * {@inheritDoc}
     */
    protected string $name = 'db:seed';

    /**
     * {@inheritDoc}
     */
    protected string $description = 'Exécute le seeder spécifié pour remplir les données connues dans la base de données.';

    /**
     * {@inheritDoc}
     */
    protected array $arguments = [
        'name' => 'Nom du seeder à exécuter (ex: DatabaseSeeder ou Users\\UserSeeder)',
    ];

    /**
     * {@inheritDoc}
     */
    protected array $options = [
        '--group'  => 'Groupe de connexion à utiliser',
        '--silent' => 'Mode silencieux (pas de sortie)',
        '--locale' => 'Langue à utiliser pour Faker (ex: fr_FR, en_US)',
    ];

    protected ?BaseConnection $db = null;

    /**
     * {@inheritDoc}
     */
    public function handle()
    {
        $group  = $this->option('group', config('database.connection', 'default'));
        $silent = $this->option('silent') !== null;
        $locale = $this->option('locale', config('app.language', 'fr_FR'));

        $this->db = $this->resolver->connect($group);

        $name = $this->getSeederName();

        $seeder = $this->resolveSeeder($name);

        $this->configureSeeder($seeder, $locale, $silent);

        $this->runSeeder($seeder);

        return EXIT_SUCCESS;
    }

    /**
     * Récupère le nom du seeder
     */
    protected function getSeederName(): string
    {
        if (null !== $name = $this->argument('name')) {
            return $name;
        }

        return $this->prompt(
            'Quel seeder souhaitez-vous exécuter ?',
            'DatabaseSeeder',
            static function ($val) {
                if (empty($val)) {
                    throw new InvalidArgumentException('Veuillez entrer le nom du seeder.');
                }

                return $val;
            },
        );
    }

    /**
     * Résout la classe du seeder
     */
    protected function resolveSeeder(string $name): Seeder
    {
        // Chemins possibles
        $paths = [
            APP_NAMESPACE . '\\Database\\Seeds\\',
            APP_NAMESPACE . '\\Database\\Seeders\\',
            'Database\\Seeds\\',
            'Database\\Seeders\\',
        ];

        $className = $name;

        // Si le nom ne contient pas de namespace, on essaie les chemins standards
        if (! str_contains($name, '\\')) {
            foreach ($paths as $path) {
                $fullClass = $path . $name;
                if (class_exists($fullClass)) {
                    $className = $fullClass;
                    break;
                }
            }
        }

        if (! class_exists($className)) {
            throw new InvalidArgumentException(
                "Le seeder '{$name}' n'a pas été trouvé.\n" .
                "Chemins recherchés :\n" .
                implode("\n", array_map(static fn ($p) => "- {$p}{$name}", $paths)),
            );
        }

        return new $className($this->db);
    }

    /**
     * Configure le seeder
     */
    protected function configureSeeder(Seeder $seeder, ?string $locale, bool $silent): void
    {
        if ($locale !== null) {
            $seeder->setLocale($locale);
        } elseif ($seeder->getLocale() === '') {
            $seeder->setLocale(config('app.language', 'fr_FR'));
        }

        if ($silent) {
            $seeder->setSilent(true);
        }
    }

    /**
     * Exécute le seeder
     */
    protected function runSeeder(Seeder $seeder): void
    {
        $this->task('Démarrage du seed')->eol();

        $this->info('Remplissage en cours...');

        $seeder->setCommand($this)->execute();

        $executed = [
            $seeder::class,
            ...$seeder->getCalled(),
        ];

        $this->eol()->success('Opération terminée avec succès !');

        $this->eol()->write('Seeders exécutés :');

        foreach (array_unique($executed) as $seeded) {
            $this->eol()->write('  ✔ ')->writer->green($seeded);
        }
    }
}
