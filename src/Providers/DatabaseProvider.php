<?php

/**
 * This file is part of Blitz PHP framework - Database Layer.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Database\Providers;

use BlitzPHP\Container\AbstractProvider;
use BlitzPHP\Contracts\Database\ConnectionInterface;
use BlitzPHP\Contracts\Database\ConnectionResolverInterface;
use BlitzPHP\Database\ConnectionResolver;

class DatabaseProvider extends AbstractProvider
{
    /**
     * {@inheritDoc}
     */
    public static function definitions(): array
    {
        return [
            ConnectionResolverInterface::class => static fn () => new ConnectionResolver(),
            ConnectionInterface::class         => static fn (ConnectionResolverInterface $resolver) => $resolver->connect(),
        ];
    }
}
