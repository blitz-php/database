<?php

use BlitzPHP\Database\Connection\BaseConnection;
use BlitzPHP\Database\Exceptions\QueryException;
use BlitzPHP\Database\Spec\Mock\MockConnection;
use BlitzPHP\Utilities\DateTime\Date;

use function Kahlan\expect;

describe("Database / Connection : BaseConnection", function() {

    beforeEach(function() {
        $this->connection = new MockConnection([
            'driver' => 'mysql',
            'hostname' => 'localhost',
            'database' => 'test',
            'username' => 'root',
            'password' => '',
            'prefix' => 'db_',
        ]);
        $this->connection->escapeChar = '`';
        $this->connection->initialize();
    });

    it(": Initialisation", function() {
        expect($this->connection->initialize())->toBeNull();
        expect($this->connection->getConnection())->toBeAnInstanceOf(PDO::class);
    });

    it(": Récupération du driver", function() {
        expect($this->connection->getDriver())->toBe('mysql');
    });

    it(": Récupération du nom de la base", function() {
        expect($this->connection->getDatabase())->toBe('test');
    });

    it(": Récupération du préfixe", function() {
        expect($this->connection->getPrefix())->toBe('db_');
    });

    it(": Modification du préfixe", function() {
        $this->connection->setPrefix('new_');
        expect($this->connection->getPrefix())->toBe('new_');
    });

    it(": Création de table avec préfixe", function() {
        expect($this->connection->prefixTable('users'))->toBe('`db_users`');
    });

    it(": Création de table avec alias", function() {
        expect($this->connection->makeTableName('users u'))->toBe('`db_users` AS `u`');
    });

    it(": Échappement des identifiants", function() {
        expect($this->connection->escapeIdentifiers('users.id'))->toBe('`users`.`id`');
        expect($this->connection->escapeIdentifiers('count(id)'))->toBe('count(`id`)');
        expect($this->connection->escapeIdentifiers('count(users.id)'))->toBe('count(`users`.`id`)');
        expect($this->connection->escapeIdentifiers(['users.id', 'name']))->toBe(['`users`.`id`', '`name`']);
    });

    it(": Échappement des identifiants reservés", function() {
        expect($this->connection->escapeIdentifiers('users.*'))->toBe('`users`.*');
        expect($this->connection->escapeIdentifiers(['count(*)', 'count(users.*)']))->toBe(['count(*)', 'count(`users`.*)']);
        expect($this->connection->escapeIdentifiers(['users.id', 'name', '*']))->toBe(['`users`.`id`', '`name`', '*']);
    });

    it(": Échappement des chaînes", function() {
        $escaped = $this->connection->escapeString("O'Reilly");
        expect($escaped)->toBe("'O''Reilly'");
    });

    it(": Échappement avec LIKE", function() {
        $escaped = $this->connection->escapeString("100%", true);
        expect($escaped)->toMatch("/'100\\\\%'/");
    });

    it(": Échappement des valeurs", function() {
        expect($this->connection->escape("test"))->toBe("'test'");
        expect($this->connection->escape(123))->toBe(123);
        expect($this->connection->escape(true))->toBe('1');
        expect($this->connection->escape(false))->toBe('0');
        expect($this->connection->escape(null))->toBe('NULL');
    });

    it(": Transaction simple", function() {
        $this->connection->query('CREATE TABLE IF NOT EXISTS users(id int auto_increment primary key, name varchar(50));');
        $this->connection->query('CREATE TABLE IF NOT EXISTS profiles(id int auto_increment primary key, user_id int);');
        
        $result = $this->connection->transaction(function($db) {
            $db->query("INSERT INTO users (name) VALUES ('John')");
            $db->query("INSERT INTO profiles (user_id) VALUES (". $this->connection->insertID() .")");
            return true;
        });
        
        expect($result)->toBe(true);
        
        $this->connection->query('DROP TABLE IF EXISTS profiles;');
        $this->connection->query('DROP TABLE IF EXISTS users;');
    });

    it(": Transaction avec rollback sur exception", function() {
        $this->connection->query('CREATE TABLE IF NOT EXISTS users(id int auto_increment primary key, name varchar(50));');
        
        $exception = null;
        
        try {
            $this->connection->transaction(function($db) {
                $db->query("INSERT INTO users (name) VALUES ('John')");
                throw new Exception("Erreur volontaire");
            });
        } catch (Exception $e) {
            $exception = $e;
        }
        
        expect($exception)->toBeAnInstanceOf(Exception::class);
        expect($exception->getMessage())->toBe("Erreur volontaire");

        $this->connection->query('DROP TABLE IF EXISTS users;');
    });

    it(": Transactions imbriquées", function() {
        $this->connection->beginTransaction();
        expect($this->connection->transactionLevel())->toBe(1);
        
        $this->connection->beginTransaction();
        expect($this->connection->transactionLevel())->toBe(2);
        
        $this->connection->commit();
        expect($this->connection->transactionLevel())->toBe(1);
        
        $this->connection->commit();
        expect($this->connection->transactionLevel())->toBe(0);
    });

    it(": beforeExecuting callback", function() {
        $this->connection->query('CREATE TABLE IF NOT EXISTS users(id int auto_increment primary key, name varchar(50));');
        
        $called = false;
        
        $this->connection->beforeExecuting(function($query, $bindings, $connection) use (&$called) {
            $called = true;
            expect($query)->toBe("SELECT * FROM users WHERE id = ?");
            expect($bindings)->toBe([5]);
            expect($connection)->toBe($this->connection);
        });
        
        $this->connection->query("SELECT * FROM users WHERE id = ?", [5]);
        
        expect($called)->toBe(true);
    });

    it(": prepareBindings avec DateTime", function() {
        $date = Date::now();
        $bindings = ['name' => 'John', 'created_at' => $date];
        
        $prepared = $this->connection->prepareBindings($bindings);
        
        expect($prepared['created_at'])->toBe($date->format('Y-m-d H:i:s'));
    });

    it(": prepareBindings avec bool", function() {
        $bindings = ['active' => true, 'deleted' => false];
        
        $prepared = $this->connection->prepareBindings($bindings);
        
        expect($prepared['active'])->toBe(1);
        expect($prepared['deleted'])->toBe(0);
    });

    it(": QueryException formatage", function() {
        try {
            $this->connection->query("SELECT * FROM non_existent_table");
        } catch (QueryException $e) {
            expect($e->getConnectionName())->toBe($this->connection->getName());
            expect($e->getSql())->toBe("SELECT * FROM non_existent_table");
            expect($e->getBindings())->toBe([]);
            expect($e->getConnectionDetails())->toBeAn('array');
        }
    });
});
