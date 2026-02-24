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

/**
 * Génère un fichier squelette de seeder.
 */
class Seeder extends Command
{
    use GeneratorTrait;

    /**
     * {@inheritDoc}
     */
    protected string $group = 'Generateurs';

    /**
     * {@inheritDoc}
     */
    protected string $name = 'make:seeder';

    /**
     * {@inheritDoc}
     */
    protected string $description = 'Génère un nouveau fichier seeder.';

    /**
     * {@inheritDoc}
     */
    protected string $service = 'Service de génération de code';

    /**
     * {@inheritDoc}
     */
    protected array $arguments = [
        'name' => 'Le nom de la classe du seeder.',
    ];

    /**
     * {@inheritDoc}
     */
    protected array $options = [
        '--namespace' => "Définissez l'espace de noms racine. Par défaut\u{a0}: \"APP_NAMESPACE\".",
        '--suffix'    => 'Ajoutez le titre du composant au nom de la classe (par exemple, User => UserSeeder).',
        '--force'     => 'Forcer l\'écrasement du fichier existant.',
    ];

    /**
     * {@inheritDoc}
     */
    public function handle()
    {
        $this->component    = 'Seeder';
        $this->directory    = 'Database\Seeds';
        $this->template     = 'seeder.tpl.php';
        $this->templatePath = __DIR__ . '/Views';

        $this->classNameLang = 'CLI.generator.className.seeder';
        $this->generateClass($this->parameters());
    }
}
