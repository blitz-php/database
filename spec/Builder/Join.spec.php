<?php

use BlitzPHP\Database\BaseBuilder;
use BlitzPHP\Database\MySQL\Connection as MySQLConnection;
use BlitzPHP\Database\Spec\Mock\MockConnection;

describe("Database / Query Builder : Jointures", function() {

    beforeEach(function() {
        $this->builder = new BaseBuilder(new MockConnection([]));
    });

    it(": Jointure implicite (sans definition des cles des jointures)", function() {
        $builder = $this->builder->from('jobs j');
        
        $builder->join('users u', 'id_utilisateur');
        expect($builder->sql())->toBe('SELECT * FROM jobs As j INNER JOIN users As u ON j.id_utilisateur = u.id_utilisateur');
    });
    
    it(": Natural JOIN (Seulement MySQL)", function() {
        $this->builder = new BaseBuilder(new MySQLConnection([]));
        
        $builder = $this->builder->from('jobs j')->naturalJoin('users u');
        expect($builder->sql())->toBe('SELECT * FROM `jobs` As `j` NATURAL JOIN `users` As `u`');
    });

    it(": Natural JOIN (Seulement MySQL) Sans alias", function() {
        $this->builder = new BaseBuilder(new MySQLConnection([]));
        
        $builder = $this->builder->from('jobs')->naturalJoin('users');
        expect($builder->sql())->toMatch('/^SELECT \* FROM `jobs` As `jobs_(?:[a-z0-9]+)` NATURAL JOIN `users` As `users_(?:[a-z0-9]+)`$/');
    });
});
