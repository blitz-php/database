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

use BlitzPHP\Cli\Console\Command;
use BlitzPHP\Cli\Console\Console;
use BlitzPHP\Contracts\Database\ConnectionResolverInterface;
use BlitzPHP\Database\Connection\BaseConnection;
use Psr\Log\LoggerInterface;

abstract class DatabaseCommand extends Command
{
    /**
     * {@inheritDoc}
     */
    protected $group = 'Database';

    /**
     * {@inheritDoc}
     */
    protected $service = 'Service de gestion de base de données';

    protected BaseConnection $db;

    /**
     * @param Console         $app    Application Console
     * @param LoggerInterface $logger Le Logger à utiliser
     */
    public function __construct(Console $app, LoggerInterface $logger,  protected ConnectionResolverInterface $resolver)
    {
        parent::__construct($app, $logger);
        $this->db = $resolver->connection();
    }
}