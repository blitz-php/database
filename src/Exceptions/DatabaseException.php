<?php

/**
 * This file is part of Blitz PHP framework - Database Layer.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Database\Exceptions;

use Error;

class DatabaseException extends Error implements ExceptionInterface
{
    /**
     * Exit status code
     *
     * @var int
     */
    protected $code = 8;
}
