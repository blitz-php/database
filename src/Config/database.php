<?php

/**
 * This file is part of Blitz PHP framework - Database Layer.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

return [
    /**
     * Configuration à utiliser
     *
     * Si defini a 'auto', la configuration 'production' sera utilisée en production et 'development' en developpement
     * Si les configuration 'production' et 'development' ne sont pas définies, 'default' sera utilisée
     *
     * @var string
     */
    'connection' => env('db.connection', 'auto'),

    /**
     * Configuration pas défaut
     *
     * @var array<string, mixed>
     */
    'default' => [
        /**
         * @var string Pilote de base de données à utiliser
         */
        'driver' => env('db.default.driver', 'pdomysql'),
        /** @var int */
        'port' => env('db.default.port', 3306),
        /** @var string */
        'host' => env('db.default.hostname', 'localhost'),
        /** @var string */
        'username' => env('db.default.username', 'root'),
        /** @var string */
        'password' => env('db.default.password', ''),
        /** @var string */
        'database' => env('db.default.database', 'test'),
        /**
         * @var 'auto'|bool
         *
         * Si défini sur 'auto', alors vaudra true en developpement et false en production
         */
        'debug' => 'auto',
        /** @var string */
        'charset' => 'utf8mb4',
        /** @var string */
        'collation' => 'utf8mb4_general_ci',
        /**
         * @var string Prefixe des table de la base de données
         */
        'prefix' => env('db.default.prefix', ''),

        'options' => [
            'column_case'  => 'inherit',
            'enable_stats' => false,
            'enable_cache' => true,
        ],
    ],
];
