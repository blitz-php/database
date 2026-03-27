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
use BlitzPHP\Utilities\Helpers;

/**
 * Génère un squelette de fichier de modèle.
 */
class Model extends GeneratorCommand
{
    /**
     * {@inheritDoc}
     */
    protected string $name = 'make:model';

    /**
     * {@inheritDoc}
     */
    protected string $description = 'Génère un nouveau fichier de modèle.';

    /**
     * {@inheritDoc}
     */
    protected array $arguments = [
        'name' => 'Le nom de la classe de modèle',
    ];

    /**
     * {@inheritDoc}
     */
    protected array $options = [
        '--table'     => ['Indiquez un nom de table. Par défaut : "le pluriel en minuscules du nom de la classe".'],
        '--dbgroup'   => ['Groupe de base de données à utiliser.'],
        '--return'    => ['Définit le type de retour des résultats, Options: [array, object, entity].', 'array'],
        '--namespace' => ['Définit le namespace du modèle.', APP_NAMESPACE],
        '--suffix'    => 'Ajoute "Model" au nom de la classe (par exemple, User => UserModel).',
        '--force'     => 'Forcer l\'écrasement du fichier existant.',
    ];

    /**
     * {@inheritDoc}
     */
    protected string $component = 'Model';

    /**
     * {@inheritDoc}
     */
    protected string $directory = 'Models';

    /**
     * {@inheritDoc}
     */
    protected string $templatePath = __DIR__ . '/Views';

    /**
     * {@inheritDoc}
     */
    protected string $template = 'model.tpl.php';

    /**
     * {@inheritDoc}
     */
    protected string $classNameLang = 'CLI.generator.className.model';

    /**
     * {@inheritDoc}
     */
    protected function prepare(string $class): string
    {
        $table   = $this->option('table');
        $dbGroup = $this->option('dbgroup');
        $return  = $this->option('return', 'array');

        $baseClass = Helpers::classBasename($class);

        if (preg_match('/^(\S+)Model$/i', $baseClass, $match) === 1) {
            $baseClass = $match[1];
        }

        helper('inflector');

        $table  = is_string($table) ? $table : plural(strtolower($baseClass));

        if (! in_array($return, ['array', 'object', 'entity'], true)) {
            $return = $this->choice(lang('CLI.generator.returnType'), ['array', 'object', 'entity'], 'array');
            $this->newLine();
        }

        if ($return === 'entity') {
            $return = str_replace('Models', 'Entities', $class);

            if (preg_match('/^(\S+)Model$/i', $return, $match) === 1) {
                $return = $match[1];

                if ($this->option('suffix')) {
                    $return .= 'Entity';
                }
            }

            $return = '\\' . trim($return, '\\') . '::class';

            if ($this->commandExists('make:entity')) {
                $this->call('make:entity', ['name' => $baseClass], [
                    '--namespace' => $this->option('namespace'),
                    '--suffix'    => $this->option('suffix'),
                ]);
            }
        } else {
            $return = "'{$return}'";
        }

        return $this->parseTemplate($class, ['{dbGroup}', '{table}', '{return}'], [$dbGroup, $table, $return], compact('dbGroup'));
    }
}
