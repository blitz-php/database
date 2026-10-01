<?php

use BlitzPHP\Database\Builder\BaseBuilder;
use BlitzPHP\Database\Connection\BaseConnection;
use BlitzPHP\Database\Seeder\Seed;
use BlitzPHP\Database\Spec\Mock\MockConnection;
use Faker\Generator as FakerGenerator;

use function Kahlan\expect;

describe("Database / Seeder : Seed", function() {

    beforeEach(function() {
        $this->connection = new MockConnection([
            'driver' => 'mysql',
            'hostname' => 'localhost',
            'database' => 'test',
            'username' => 'root',
            'password' => '',
        ]);
        $this->connection->initialize();
        
        $this->faker = new FakerGenerator();
        $this->seed = new Seed($this->connection, 'users', $this->faker);
    });

    it(": __construct", function() {
        expect($this->seed)->toBeAnInstanceOf(Seed::class);
    });

    it(": columns with array", function() {
        $result = $this->seed->columns([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
        
        expect($result)->toBe($this->seed);
    });

    it(": column with simple value", function() {
        $result = $this->seed->column('name', 'John Doe');
        
        expect($result)->toBe($this->seed);
    });

    it(": column with closure", function() {
        $closure = function($faker) {
            return 'generated_name';
        };
        
        $result = $this->seed->column('name', $closure);
        
        expect($result)->toBe($this->seed);
    });

    it(": rows", function() {
        $result = $this->seed->rows(50);
        
        expect($result)->toBe($this->seed);
    });

    it(": bulkSize", function() {
        $result = $this->seed->bulkSize(25);
        
        expect($result)->toBe($this->seed);
    });

    it(": bulkSize with minimum value", function() {
        $result = $this->seed->bulkSize(0);
        
        expect($result)->toBe($this->seed);
    });

    it(": data with array of arrays", function() {
        $data = [
            ['name' => 'John', 'email' => 'john@example.com'],
            ['name' => 'Jane', 'email' => 'jane@example.com'],
        ];
        
        $result = $this->seed->data($data);
        
        expect($result)->toBe($this->seed);
    });

    it(": data with single array", function() {
        $data = ['name' => 'John', 'email' => 'john@example.com'];
        
        $result = $this->seed->data($data);
        
        expect($result)->toBe($this->seed);
    });

    it(": data with empty array throws exception", function() {
        $closure = function() {
            $this->seed->data([]);
        };
        
        expect($closure)->toThrow();
    });

    it(": truncate", function() {
        $result = $this->seed->truncate(true);
        
        expect($result)->toBe($this->seed);
    });

    it(": truncate with false", function() {
        $result = $this->seed->truncate(false);
        
        expect($result)->toBe($this->seed);
    });

    it(": beforeInsert", function() {
        $callback = function($data, $index) {
            $data['created_at'] = date('Y-m-d H:i:s');
            return $data;
        };
        
        $result = $this->seed->beforeInsert($callback);
        
        expect($result)->toBe($this->seed);
    });

    it(": afterInsert", function() {
        $callback = function($data, $index, $insertId) {
            // Do something after insert
        };
        
        $result = $this->seed->afterInsert($callback);
        
        expect($result)->toBe($this->seed);
    });

    it(": chaining methods", function() {
        $result = $this->seed
            ->columns(['name' => 'John'])
            ->rows(10)
            ->bulkSize(5)
            ->truncate(true);
        
        expect($result)->toBe($this->seed);
    });

    it(": multiple callbacks", function() {
        $callback1 = function($data) { return $data; };
        $callback2 = function($data) { return $data; };
        
        $this->seed->beforeInsert($callback1);
        $this->seed->beforeInsert($callback2);
        
        expect($this->seed)->toBeAnInstanceOf(Seed::class);
    });

    it(": columns with mixed types", function() {
        $closure = function($faker) { return 'test'; };
        
        $result = $this->seed->columns([
            'name' => 'John',
            'email' => 'john@example.com',
            'age' => 25,
            'active' => true,
            'description' => $closure,
        ]);
        
        expect($result)->toBe($this->seed);
    });

    it(": rows with large number", function() {
        $result = $this->seed->rows(1000);
        
        expect($result)->toBe($this->seed);
    });

    it(": bulkSize with large number", function() {
        $result = $this->seed->bulkSize(500);
        
        expect($result)->toBe($this->seed);
    });
});
