<?php

use BlitzPHP\Database\Seeder\Factory;

use function Kahlan\expect;

describe("Database / Seeder : Factory", function() {

    it(": __construct with default locale", function() {
        $factory = new Factory('fr_FR');
        expect($factory)->toBeAnInstanceOf(Factory::class);
    });

    it(": __construct with custom locale", function() {
        $factory = new Factory('en_US');
        expect($factory)->toBeAnInstanceOf(Factory::class);
    });

    it(": __call returns faker config", function() {
        $factory = new Factory();
        $config = $factory->name();
        expect($config)->toBeAn('array');
        expect($config[0])->toBe('faker');
        expect($config[1])->toBe('name');
    });

    it(": __call with arguments", function() {
        $factory = new Factory();
        $config = $factory->text(50);
        expect($config)->toBe(['faker', 'text', [50]]);
    });

    it(": __call unique returns unique config", function() {
        $factory = new Factory();
        $config = $factory->unique('email');
        expect($config)->toBe(['faker:unique', 'email', []]);
    });

    it(": __call unique with arguments", function() {
        $factory = new Factory();
        $config = $factory->unique('email', 100);
        expect($config)->toBe(['faker:unique', 'email', [100]]);
    });

    it(": __get returns faker config", function() {
        $factory = new Factory();
        $config = $factory->name;
        expect($config)->toBe(['faker', 'name', []]);
    });

    it(": __get email", function() {
        $factory = new Factory();
        $config = $factory->email;
        expect($config)->toBe(['faker', 'email', []]);
    });

    it(": relation", function() {
        $factory = new Factory();
        $config = $factory->relation('users', 'id');
        expect($config)->toBe(['relation', 'users', 'id']);
    });

    it(": relation with default column", function() {
        $factory = new Factory();
        $config = $factory->relation('posts');
        expect($config)->toBe(['relation', 'posts', 'id']);
    });

    it(": optional with weight", function() {
        $factory = new Factory();
        $config = $factory->optional(0.7);
        expect($config)->toBe(['optional', 0.7, null]);
    });

    it(": optional with weight and default", function() {
        $factory = new Factory();
        $config = $factory->optional(0.5, 'default_value');
        expect($config)->toBe(['optional', 0.5, 'default_value']);
    });

    it(": optional with weight, default and value", function() {
        $factory = new Factory();
        $config = $factory->optional(0.5, 'default_value', 'actual_value');
        expect($config)->toBe(['optional', 0.5, 'default_value', 'actual_value']);
    });

    it(": raw returns value", function() {
        $factory = new Factory();
        $value = $factory->raw('fixed_value');
        expect($value)->toBe('fixed_value');
    });

    it(": raw with array", function() {
        $factory = new Factory();
        $value = $factory->raw(['key' => 'value']);
        expect($value)->toBe(['key' => 'value']);
    });

    it(": raw with number", function() {
        $factory = new Factory();
        $value = $factory->raw(42);
        expect($value)->toBe(42);
    });

    it(": raw with null", function() {
        $factory = new Factory();
        $value = $factory->raw(null);
        expect($value)->toBeNull();
    });

    it(": multiple faker calls", function() {
        $factory = new Factory();
        
        $name = $factory->name;
        expect($name)->toBe(['faker', 'name', []]);
        
        $email = $factory->email;
        expect($email)->toBe(['faker', 'email', []]);
        
        $text = $factory->text(100);
        expect($text)->toBe(['faker', 'text', [100]]);
    });

    it(": chained configuration", function() {
        $factory = new Factory();
        
        $config = [
            'name' => $factory->name,
            'email' => $factory->unique('email'),
            'age' => $factory->numberBetween(18, 65),
            'country' => $factory->country,
        ];
        
        expect($config['name'])->toBe(['faker', 'name', []]);
        expect($config['email'])->toBe(['faker:unique', 'email', []]);
        expect($config['age'])->toBe(['faker', 'numberBetween', [18, 65]]);
        expect($config['country'])->toBe(['faker', 'country', []]);
    });
});
