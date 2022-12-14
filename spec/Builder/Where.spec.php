<?php

use BlitzPHP\Database\BaseBuilder;
use BlitzPHP\Database\Spec\Mock\MockConnection;

describe("Query Builder : Where", function() {

    beforeEach(function() {
        $this->builder = new BaseBuilder(new MockConnection([]));
    });

    it(": Where raw", function() {
        $builder = $this->builder->from(['users', 'jobs'])->where('users.id_user', 'jobs.id_user', false);
        expect($builder->sql())->toMatch('/^SELECT \* FROM users As users_(?:[a-z0-9]+), jobs As jobs_(?:[a-z0-9]+) WHERE users_(?:[a-z0-9]+)\.id_user = jobs_(?:[a-z0-9]+)\.id_user$/');           
    });

    it(": Where raw (Conservation des alias)", function() {
        $builder = $this->builder->from(['users u', 'jobs j'])->where('users.id_user', 'jobs.id_user', false);
        expect($builder->sql())->toBe('SELECT * FROM users As u, jobs As j WHERE u.id_user = j.id_user');           
        
        $builder = $this->builder->from(['users u', 'jobs j'])->where('u.id_user', 'j.id_user', false);
        expect($builder->sql())->toBe('SELECT * FROM users As u, jobs As j WHERE u.id_user = j.id_user');           
    });
    it(": Where raw (Conservation d'un alias)", function() {
        $builder = $this->builder->from(['users u', 'jobs'])->where('u.id_user', 'jobs.id_user', false);
        expect($builder->sql())->toMatch('/^SELECT \* FROM users As u, jobs As jobs_(?:[a-z0-9]+) WHERE u\.id_user = jobs_(?:[a-z0-9]+)\.id_user$/');           
    });
});
