<?php

use BlitzPHP\Database\BaseBuilder;
use BlitzPHP\Database\Spec\Mock\MockConnection;

describe("Database / Query Builder : Tri", function() {

    beforeEach(function() {
        $this->builder = new BaseBuilder(new MockConnection([]));
    });

    it(": Tri croissant", function() {
        $builder = $this->builder->from('user u')->sortAsc('name');

        expect($builder->sql())->toBe('SELECT * FROM user As u ORDER BY name ASC');

        $builder = $this->builder->from('user u')->orderBy('name', 'ASC');

        expect($builder->sql())->toBe('SELECT * FROM user As u ORDER BY name ASC');
    });

    it(": Tri decroissant", function() {
        $builder = $this->builder->from('user u')->sortDesc('name');

        expect($builder->sql())->toBe('SELECT * FROM user As u ORDER BY name DESC');

        $builder = $this->builder->from('user u')->orderBy('name', 'desc');

        expect($builder->sql())->toBe('SELECT * FROM user As u ORDER BY name DESC');
    });

    it(": Tri aleatoire", function() {
        $builder = $this->builder->from('user u')->sortRand();

        expect($builder->sql())->toBe('SELECT * FROM user As u ORDER BY RAND()');

        $builder = $this->builder->from('user u')->orderBy('name', 'random');

        expect($builder->sql())->toBe('SELECT * FROM user As u ORDER BY RAND()');
    });
});
