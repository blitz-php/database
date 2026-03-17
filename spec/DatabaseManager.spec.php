<?php

use BlitzPHP\Database\DatabaseManager;
use BlitzPHP\Database\Connection\MySQL;
use BlitzPHP\Database\Connection\SQLite;

describe("Database / DatabaseManager", function() {

    beforeEach(function() {
        $this->manager = new DatabaseManager();
        
        // Mock de la config
        allow('config')->toBeCalled()->with('database')->andReturn([
            'default' => [
                'driver' => 'mysql',
                'hostname' => 'localhost',
                'database' => 'test',
                'username' => 'root',
                'password' => '',
                'prefix' => '',
            ],
            'test' => [
                'driver' => 'sqlite',
                'database' => ':memory:',
                'prefix' => '',
            ],
        ]);
    });

    it(": Connexion par défaut", function() {
        $connection = $this->manager->connection();
        expect($connection)->toBeAnInstanceOf(MySQL::class);
    });

    it(": Connexion nommée", function() {
        $connection = $this->manager->connection('test');
        expect($connection)->toBeAnInstanceOf(SQLite::class); // SQLite utilise Mock
    });

    it(": Connexion partagée", function() {
        $conn1 = $this->manager->connection();
        $conn2 = $this->manager->connection();
        
        expect($conn1)->toBe($conn2);
    });

    it(": Connexion non partagée", function() {
        $conn1 = $this->manager->connect('default', false);
        $conn2 = $this->manager->connect('default', false);
        
        expect($conn1)->not->toBe($conn2);
    });

    it(": ConnectionInfo avec tableau de config", function() {
        [$name, $config] = $this->manager->connectionInfo([
            'driver' => 'mysql',
            'database' => 'custom',
        ]);
        
        expect($name)->toMatch('/^custom-/');
        expect($config['database'])->toBe('custom');
    });

    it(": Création de builder", function() {
        $connection = $this->manager->connection('test');
        $builder = $this->manager->builder($connection);
        
        expect($builder)->toBeAnInstanceOf(\BlitzPHP\Database\Builder\BaseBuilder::class);
    });

    it(": Changement de connexion par défaut", function() {
        $this->manager->setDefaultConnection('test');
        expect($this->manager->getDefaultConnection())->toBe('test');
        
        $connection = $this->manager->connection();
        expect($connection)->toBeAnInstanceOf(SQLite::class);
    });

    it(": Fermeture de toutes les connexions", function() {
        $this->manager->connection('default');
        $this->manager->connection('test');
        
        expect($this->manager->getConnections())->toHaveLength(2);
        
        $this->manager->closeAll();
        
        expect($this->manager->getConnections())->toBe([]);
    });

    it(": Connexion active", function() {
        $this->manager->connection('test');
        $active = $this->manager->activeConnection();
        
        expect($active)->toBeAnInstanceOf(SQLite::class);
    });
});
