<?php

use BlitzPHP\Database\Builder\BaseBuilder;
use BlitzPHP\Database\Spec\Mock\MockConnection;

describe("Database / Query Builder : Recherche", function() {

    beforeEach(function() {
        $this->builder = new BaseBuilder(new MockConnection([]));
    });

    it(": Like simple", function() {
        $builder = $this->builder->from('jobs j');
        
        $builder->like('name', 'veloper');
        expect($builder->sql())->toBe('SELECT * FROM jobs As j WHERE name LIKE \'%veloper%\'');
    });

    it(": Like exacte", function() {
        $builder = $this->builder->from('jobs j');
        
        $builder->like('name', 'veloper', 'none');
        expect($builder->sql())->toBe('SELECT * FROM jobs As j WHERE name LIKE \'veloper\'');
    });

    it(": Like avec le caratere `%` a gauche", function() {
        $builder = $this->builder->from('jobs j');
        
        $builder->like('name', 'veloper', 'before');
        expect($builder->sql())->toBe('SELECT * FROM jobs As j WHERE name LIKE \'%veloper\'');
    });

    it(": Like avec le caratere `%` a droite", function() {
        $builder = $this->builder->from('jobs j');
        
        $builder->like('name', 'veloper', 'after');
        expect($builder->sql())->toBe('SELECT * FROM jobs As j WHERE name LIKE \'veloper%\'');
    });

    it(": orLike", function() {
        $builder = $this->builder->from('jobs j');
        
        $builder->like('name', 'veloper')->orLike('name', 'ian');
        expect($builder->sql())->toBe('SELECT * FROM jobs As j WHERE name LIKE \'%veloper%\' OR name LIKE \'%ian%\'');
    });
    
    it(": notLike", function() {
        $builder = $this->builder->from('jobs j');
        
        $builder->notLike('name', 'veloper');
        expect($builder->sql())->toBe('SELECT * FROM jobs As j WHERE name NOT LIKE \'%veloper%\'');
    });
    
    it(": orNotLike", function() {
        $builder = $this->builder->from('jobs j');
        
        $builder->like('name', 'veloper')->orNotLike('name', 'ian');
        expect($builder->sql())->toBe('SELECT * FROM jobs As j WHERE name LIKE \'%veloper%\' OR name NOT LIKE \'%ian%\'');
    });
    
    it(": orNotLike", function() {
        $builder = $this->builder->from('jobs j');
        
        $builder->like('name', 'veloper')->orNotLike('name', 'ian');
        expect($builder->sql())->toBe('SELECT * FROM jobs As j WHERE name LIKE \'%veloper%\' OR name NOT LIKE \'%ian%\'');
    });

    it(": Like avec respect de la casse", function() {
        $builder = $this->builder->from('jobs j');
        
        $builder->like('name', 'VELOPER', 'both', true, true);
        expect($builder->sql())->toBe('SELECT * FROM jobs As j WHERE LOWER(name) LIKE \'%veloper%\'');
    });

    it(": Like avec prefixe de la table", function() {
        $this->builder->db()->setPrefix('db_');
        $builder = $this->builder->from('test t');

        $builder->like('test.field', 'string');
        expect($builder->sql())->toBe('SELECT * FROM db_test As t WHERE t.field LIKE \'%string%\'');
    });
});
