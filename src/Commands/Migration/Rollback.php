<?php

/**
 * This file is part of Blitz PHP framework - Database Layer.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Database\Commands\Migration;

use BlitzPHP\Database\Commands\DatabaseCommand;
use BlitzPHP\Database\Commands\Helper;

/**
 * Exécute toutes les migrations dans l'ordre inverse, jusqu'à ce qu'elles aient toutes été désappliquées.
 */
class Rollback extends DatabaseCommand
{
    /**
     * @var string Nom
     */
    protected $name = 'migrate:rollback';

    /**
     * {@inheritDoc}
     */
    protected $description = 'Recherche et annule toutes les migrations précédement exécutees.';

    /**
     * {@inheritDoc}
     */
    protected $options = [
        '-b, --batch' => "Spécifiez un lot à restaurer\u{a0}; par exemple. \"3\" pour revenir au lot #3 ou \"-2\" pour revenir en arrière deux fois",
        '-f, --force' => 'Forcer la commande - cette option vous permet de contourner la question de confirmation lors de l\'exécution de cette commande dans un environnement de production',
    ];

    /**
     * {@inheritDoc}
     */
    public function execute(array $params)
    {
        if (on_prod()) {
            // @codeCoverageIgnoreStart
            $force = $this->option('force');

            if (! $force && ! $this->confirm(lang('Migrations.rollBackConfirm'))) {
                return;
            }
            // @codeCoverageIgnoreEnd
        }

        $runner = Helper::runner(null);

        $batch = $this->option('batch') ?? ($runner->getLastBatch() - 1);

        if (is_string($batch)) {
            if (! ctype_digit($batch)) {
                $this->fail('Numéro de lot invalide: ' . $batch, true);

                return EXIT_ERROR;
            }

            $batch = (int) $batch;
        }
        
        $this->colorize(lang('Migrations.rollingBack') . ' ' . $batch, 'yellow');

        $runner->setFiles(Helper::getMigrationFiles(true));

        if (! $runner->regress($batch)) {
            $this->error(lang('Migrations.generalFault')); // @codeCoverageIgnore
        }

        $messages = $runner->getMessages();

        foreach ($messages as $message) {
            $this->colorize($message['message'], $message['color']);
        }

        $this->newLine()->success('Fin de l\'annulation des migrations.');
    }
}
