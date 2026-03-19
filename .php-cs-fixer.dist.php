<?php

declare(strict_types=1);

/**
 * This file is part of Blitz PHP framework - Database Layer.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

use BlitzPHP\CodingStandard\Blitz;
use Nexus\CsConfig\Factory;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->files()
    ->in([__DIR__ . '/src'])
    ->append([__FILE__]);

$overrides = [];

$options = [
    'cacheFile' => 'build/.php-cs-fixer.cache',
    'finder'    => $finder,
];

return Factory::create(new Blitz(), $overrides, $options)->forLibrary(
    'Blitz PHP framework - Database Layer',
    'Dimitri Sitchet Tomkeu',
    'devcode.dst@gmail.com',
    2022,
);
