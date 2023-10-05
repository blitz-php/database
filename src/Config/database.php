<?php

/**
 * ------------------------------------------------- -------------------------
 * Configuration de la base de donnees
 * ------------------------------------------------- -------------------------
 *
 * Si vous souhaitez utiliser une base de donnees dans votre application,
 * ce fichier vous permet de definir les parametres d'accès et de gestion de celle-ci
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
        'driver'    => env('db.default.driver', 'pdomysql'),
        /** @var int */
        'port'      => env('db.default.port', 3306),
        /** @var string */
        'host'      => env('db.default.hostname', 'localhost'),
        /** @var string */
        'username'  => env('db.default.username', 'root'),
        /** @var string */
        'password'  => env('db.default.password', ''),
        /** @var string */
        'database'  => env('db.default.database', 'test'),
        /** 
         * @var bool|'auto'
         * 
         * Si défini sur 'auto', alors vaudra true en developpement et false en production
         */
        'debug'     => 'auto',
        /** @var string */
        'charset'   => 'utf8mb4',
        /** @var string */
        'collation' => 'utf8mb4_general_ci',
        /** 
         * @var string Prefixe des table de la base de données 
         */
        'prefix'    => env('db.default.prefix', ''),
        
        'options'   => [
            'column_case'  => 'inherit',
            'enable_stats' => false,
            'enable_cache' => true,
        ]
    ],
];
