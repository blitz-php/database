<?php

use BlitzPHP\Database\Builder\BaseBuilder;
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

        $builder = $this->builder->from('user u')->oldest();
        expect($builder->sql())->toBe('SELECT * FROM user As u ORDER BY created_at ASC');

        $builder = $this->builder->from('user u')->oldest('name');
        expect($builder->sql())->toBe('SELECT * FROM user As u ORDER BY name ASC');
    });

    it(": Tri decroissant", function() {
        $builder = $this->builder->from('user u')->sortDesc('name');
        expect($builder->sql())->toBe('SELECT * FROM user As u ORDER BY name DESC');

        $builder = $this->builder->from('user u')->order('name', 'desc');
        expect($builder->sql())->toBe('SELECT * FROM user As u ORDER BY name DESC');

        $builder = $this->builder->from('user u')->latest();
        expect($builder->sql())->toBe('SELECT * FROM user As u ORDER BY created_at DESC');

        $builder = $this->builder->from('user u')->latest('name');
        expect($builder->sql())->toBe('SELECT * FROM user As u ORDER BY name DESC');
    });

    it(": Tri aleatoire", function() {
        $builder = $this->builder->from('user u')->sortRand();

        expect($builder->sql())->toBe('SELECT * FROM user As u ORDER BY RAND()');

        $builder = $this->builder->from('user u')->orderBy('name', 'random');

        expect($builder->sql())->toBe('SELECT * FROM user As u ORDER BY RAND()');
    });

    it(": Tri avec alias", function() {
        $builder = $this->builder->from('user u')->sortDesc('user.id');

        expect($builder->sql())->toBe('SELECT * FROM user As u ORDER BY u.id DESC');

        $builder = $this->builder->from('user u')->orderBy('user.name', 'asc');

        expect($builder->sql())->toBe('SELECT * FROM user As u ORDER BY u.name ASC');
    });

    it(": Tri sans definition explicite d'alias", function() {
        $builder = $this->builder->from('user')->sortDesc('user.id');

        expect($builder->sql())->toMatch('/^SELECT \* FROM user As user_(?:[a-z0-9]+) ORDER BY user_(?:[a-z0-9]+)\.id DESC$/');
    });
});
