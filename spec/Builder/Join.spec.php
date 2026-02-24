<?php

use BlitzPHP\Database\Builder\BaseBuilder;
use BlitzPHP\Database\Connection\MySQL as MySQLConnection;
use BlitzPHP\Database\Spec\Mock\MockConnection;

describe("Database / Query Builder : Jointures", function() {

    beforeEach(function() {
        $this->builder = new BaseBuilder(new MockConnection([]));
    });

    it(": Jointure implicite (sans definition des cles des jointures)", function() {
        $builder = $this->builder->from('jobs j');
        
        $builder->join('users u', 'id_utilisateur');
        expect($builder->sql())->toBe('SELECT * FROM jobs AS j INNER JOIN users AS u ON j.id_utilisateur = u.id_utilisateur');
    });

    it(": Jointure explicite (avec definition des cles des jointures)", function() {
        $builder = $this->builder->from('jobs j');
        
        $builder->join('users u', ['u.id_utilisateur' => 'j.id_utilisateur']);
        expect($builder->sql())->toBe('SELECT * FROM jobs AS j INNER JOIN users AS u ON u.id_utilisateur = j.id_utilisateur');
    });

    it(": Jointure explicite (avec plusieurs conditions de jointure)", function() {
        $builder = $this->builder->from('table1 t1')->join('table2 t2', [
            't1.field1' => 't2.field2',
            't1.field2' => 'foo',
            '| t2.field2 !=' => '0',
        ], 'LEFT');

        expect($builder->sql())->toBe('SELECT * FROM table1 AS t1 LEFT JOIN table2 AS t2 ON t1.field1 = t2.field2 AND t1.field2 = foo OR t2.field2 != 0');
    });
    
    xit(": Natural JOIN (Seulement MySQL)", function() {
        $this->builder = new BaseBuilder(new MySQLConnection([]));
        
        $builder = $this->builder->from('jobs j')->naturalJoin('users u');
        expect($builder->sql())->toBe('SELECT * FROM `jobs` AS `j` NATURAL JOIN `users` AS `u`');
    });

    xit(": Natural JOIN (Seulement MySQL) Sans alias", function() {
        $this->builder = new BaseBuilder(new MySQLConnection([]));
        
        $builder = $this->builder->from('jobs')->naturalJoin('users');
        expect($builder->sql())->toMatch('/^SELECT \* FROM `jobs` AS `jobs_(?:[a-z0-9]+)` NATURAL JOIN `users` AS `users_(?:[a-z0-9]+)`$/');
    });
});
