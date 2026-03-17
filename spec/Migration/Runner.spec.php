<?php

use BlitzPHP\Database\DatabaseManager;
use BlitzPHP\Database\Migration\Runner;
use BlitzPHP\Database\Migration\Migration;
use BlitzPHP\Database\Spec\Mock\MockConnection;

use function Kahlan\expect;

describe("Database / Migration : Runner", function() {

    beforeEach(function() {
        $this->dbManager = new DatabaseManager();
        $this->connection = new MockConnection(['prefix' => '', 'driver' => 'sqlite']);
        $this->connection->escapeChar = '"';

        allow($this->dbManager)->toReceive('connect')->andReturn($this->connection);
        allow($this->dbManager)->toReceive('activeConnection')->andReturn($this->connection);
        
        $this->paths = [
            'App' => [__DIR__ . '/../fixtures/migrations/20240101000000_test_migration.php']
        ];
        
        $this->runner = new Runner($this->dbManager, 'default', $this->paths);
    });

    it(": Désactivé si config disabled", function() {
        $runner = new Runner($this->dbManager, 'default', $this->paths, ['enabled' => false]);
        expect($runner->latest())->toBe(0);
    });

    it(": Récupération des fichiers de migration", function() {
        $files = $this->runner->findMigrationFiles();
        
        expect($files)->toBeAn('array');
        expect($files)->not->toBeEmpty();
        expect($files[0]->version)->toBe('20240101000000');
        expect($files[0]->migration)->toBe('test_migration');
        expect($files[0]->namespace)->toBe('App');
    });

    it(": Événements", function() {
        $events = [
            'process.start' => false,
            'process.completed' => false,
            'migration.before' => false,
            'migration.done' => false,
        ];
        
        $this->runner->on('process.start', function() use (&$events) {
            $events['process.start'] = true;
        })->on('process.completed', function() use (&$events) {
            $events['process.completed'] = true;
        })->on('migration.before', function() use (&$events) {
            $events['migration.before'] = true;
        })->on('migration.done', function() use (&$events) {
            $events['migration.done'] = true;
        });
        
        // Simuler l'absence de migrations en attente
        allow($this->runner)->toReceive('getPendingMigrations')->andReturn([]);
        
        $this->runner->latest();
        
        expect($events['process.start'])->toBe(false);
    });
});
