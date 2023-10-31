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

use BlitzPHP\Cli\Console\Command;
use BlitzPHP\Cli\Console\Console;
use BlitzPHP\Contracts\Database\ConnectionResolverInterface;
use BlitzPHP\Database\Connection\BaseConnection;
use Psr\Log\LoggerInterface;

/**
 * @property BaseConnection $db
 */
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

    private ?BaseConnection $_db = null;

    /**
     * @param Console         $app    Application Console
     * @param LoggerInterface $logger Le Logger à utiliser
     */
    public function __construct(Console $app, LoggerInterface $logger, protected ConnectionResolverInterface $resolver)
    {
        parent::__construct($app, $logger);
    }

    public function __get($name)
    {
        if (method_exists($this, $name)) {
            return call_user_func([$this, $name]);
        }

        return parent::__get($name);
    }

    protected function db(): BaseConnection
    {
        if (null === $this->_db) {
            $this->_db = $this->resolver->connection();
        }

        return $this->_db;
    }
}
