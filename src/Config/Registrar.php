<?php

/**
 * This file is part of Blitz PHP framework - Database Layer.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Database\Config;

class Registrar
{
    /**
     * Enregistre les fichiers de configurations publiable
     */
    public static function config(): array
    {
        return ['database', 'dump', 'migrations'];
    }
}
