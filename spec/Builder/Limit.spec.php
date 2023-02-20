<?php

use BlitzPHP\Database\BaseBuilder;
use BlitzPHP\Database\Spec\Mock\MockConnection;

describe("Database / Query Builder : Limitation", function() {

    beforeEach(function() {
        $this->builder = new BaseBuilder(new MockConnection([]));
    });

    it(": Limite simple", function() {
        $builder = $this->builder->from('user u')->limit(5);

        expect($builder->sql())->toBe('SELECT * FROM user As u LIMIT 5');
    });

    it(": Limite avec decalage", function() {
        $builder = $this->builder->from('user u')->limit(5, 2);

        expect($builder->sql())->toBe('SELECT * FROM user As u LIMIT 5 OFFSET 2');
    });

    it(": Utiisation des methodes limit et offset", function() {
        $builder = $this->builder->from('user u')->limit(5)->offset(2);

        expect($builder->sql())->toBe('SELECT * FROM user As u LIMIT 5 OFFSET 2');
    });

    it(": Limite via la methode select", function() {
        $builder = $this->builder->from('user u')->select('*', 5, 2);

        expect($builder->sql())->toBe('SELECT * FROM user As u LIMIT 5 OFFSET 2');
    });
});
