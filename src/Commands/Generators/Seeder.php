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

use BlitzPHP\Cli\Commands\Generators\GeneratorCommand;

/**
 * Génère un fichier squelette de seeder.
 */
class Seeder extends GeneratorCommand
{
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

	protected string $component     = 'Seeder';
	protected string $directory     = 'Database\Seeds';
	protected string $template      = 'seeder.tpl.php';
	protected string $templatePath  = __DIR__ . '/Views';
	protected string $classNameLang = 'CLI.generator.className.seeder';
}
