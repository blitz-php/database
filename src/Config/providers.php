<?php

/**
 * This file is part of Blitz PHP framework.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

use BlitzPHP\Contracts\Database\ConnectionInterface;
use BlitzPHP\Contracts\Database\ConnectionResolverInterface;
use BlitzPHP\Database\ConnectionResolver;

return [
    /** Interfaces */
    ConnectionResolverInterface::class => fn () => new ConnectionResolver(),
    ConnectionInterface::class         => fn (ConnectionResolverInterface $resolver) => $resolver->connect(),
];
