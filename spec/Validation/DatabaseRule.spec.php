<?php

use BlitzPHP\Database\Connection\BaseConnection;
use BlitzPHP\Database\Spec\Mock\MockConnection;
use BlitzPHP\Database\Validation\DatabaseRule;
use BlitzPHP\Wolke\Model;

use function Kahlan\expect;

class TestDatabaseRule
{
    use DatabaseRule;
}

describe("Database / Validation : DatabaseRule", function() {

    beforeEach(function() {
        $this->connection = new MockConnection([
            'driver' => 'mysql',
            'hostname' => 'localhost',
            'database' => 'test',
            'username' => 'root',
            'password' => '',
        ]);
        $this->connection->initialize();
        
        $this->rule = new TestDatabaseRule($this->connection);
    });

    it(": __construct", function() {
        expect($this->rule)->toBeAnInstanceOf(TestDatabaseRule::class);
    });

    it(": resolveTableName with simple string", function() {
        $table = $this->rule->resolveTableName('users');
        expect($table)->toBe('users');
    });

    it(": resolveTableName with dot notation", function() {
        $table = $this->rule->resolveTableName('db.users');
        expect($table)->toBe('db.users');
    });

    it(": resolveTableName with non-existent class", function() {
        $table = $this->rule->resolveTableName('NonExistentClass');
        expect($table)->toBe('NonExistentClass');
    });

    it(": where with simple value", function() {
        $result = $this->rule->where('status', 'active');
        expect($result)->toBe($this->rule);
    });

    it(": where with array value", function() {
        $result = $this->rule->where('status', ['active', 'pending']);
        expect($result)->toBe($this->rule);
    });

    it(": where with null value", function() {
        $result = $this->rule->where('deleted_at', null);
        expect($result)->toBe($this->rule);
    });

    it(": where with closure", function() {
        $result = $this->rule->where(function($query) {
            // Custom query logic
        });
        expect($result)->toBe($this->rule);
    });

    it(": whereNot", function() {
        $result = $this->rule->whereNot('status', 'inactive');
        expect($result)->toBe($this->rule);
    });

    it(": whereNot with array", function() {
        $result = $this->rule->whereNot('status', ['inactive', 'banned']);
        expect($result)->toBe($this->rule);
    });

    it(": whereNull", function() {
        $result = $this->rule->whereNull('deleted_at');
        expect($result)->toBe($this->rule);
    });

    it(": whereNotNull", function() {
        $result = $this->rule->whereNotNull('deleted_at');
        expect($result)->toBe($this->rule);
    });

    it(": whereIn", function() {
        $result = $this->rule->whereIn('id', [1, 2, 3]);
        expect($result)->toBe($this->rule);
    });

    it(": whereNotIn", function() {
        $result = $this->rule->whereNotIn('id', [4, 5, 6]);
        expect($result)->toBe($this->rule);
    });

    it(": withoutTrashed", function() {
        $result = $this->rule->withoutTrashed();
        expect($result)->toBe($this->rule);
    });

    it(": withoutTrashed with custom column", function() {
        $result = $this->rule->withoutTrashed('archived_at');
        expect($result)->toBe($this->rule);
    });

    it(": onlyTrashed", function() {
        $result = $this->rule->onlyTrashed();
        expect($result)->toBe($this->rule);
    });

    it(": onlyTrashed with custom column", function() {
        $result = $this->rule->onlyTrashed('archived_at');
        expect($result)->toBe($this->rule);
    });

    it(": using with closure", function() {
        $result = $this->rule->using(function($query) {
            $query->where('active', true);
        });
        expect($result)->toBe($this->rule);
    });

    it(": multiple using callbacks", function() {
        $this->rule->using(function($query) {
            $query->where('active', true);
        });
        
        $this->rule->using(function($query) {
            $query->where('verified', true);
        });
        
        $callbacks = $this->rule->queryCallbacks();
        expect($callbacks)->not->toBeEmpty();
        expect(count($callbacks))->toBe(2);
    });

    it(": queryCallbacks", function() {
        $this->rule->using(function($query) {
            $query->where('active', true);
        });
        
        $callbacks = $this->rule->queryCallbacks();
        expect($callbacks)->not->toBeEmpty();
        expect(count($callbacks))->toBe(1);
    });

    it(": queryCallbacks returns empty array when no callbacks", function() {
        $callbacks = $this->rule->queryCallbacks();
        expect($callbacks)->toBeEmpty();
    });

    it(": chained where conditions", function() {
        $result = $this->rule
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->where('role', 'admin');
        
        expect($result)->toBe($this->rule);
    });

    it(": mixed conditions", function() {
        $result = $this->rule
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->whereIn('id', [1, 2, 3])
            ->using(function($query) {
                $query->where('verified', true);
            });
        
        expect($result)->toBe($this->rule);
    });

    it(": getWheres returns correct array", function() {
        $this->rule->where('status', 'active');
        $this->rule->where('role', 'admin');
        
        $reflection = new ReflectionClass($this->rule);
        $method = $reflection->getMethod('getWheres');
        $method->setAccessible(true);
        $wheres = $method->invoke($this->rule);
        expect($wheres)->toBeAn('array');
        expect($wheres)->toContainKeys(['status', 'role']);
    });
});
