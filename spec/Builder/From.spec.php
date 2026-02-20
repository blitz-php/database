<?php

use BlitzPHP\Database\Builder\BaseBuilder;
use BlitzPHP\Database\Spec\Mock\MockConnection;

describe("Database / Query Builder : Tables (FROM)", function() {

    beforeEach(function() {
        $this->builder = new BaseBuilder(new MockConnection([]));
    });

    it(": Table simple", function() {
        $builder = $this->builder->from('jobs');

        expect($builder->sql())->toMatch('/^SELECT \* FROM jobs AS jobs_(?:[a-z0-9]+)$/'); 
    });
    
    it(": Appel multiple de la méthode `from`", function() {
        $builder = $this->builder->from('jobs')->from('users u');

        expect($builder->sql())->toMatch('/^SELECT \* FROM jobs AS jobs_(?:[a-z0-9]+), users AS u$/'); 
    });
    
    it(": Utilisation d'un tableau de tables", function() {
        $builder = $this->builder->from(['jobs', 'users u']);
        expect($builder->sql())->toMatch('/^SELECT \* FROM jobs AS jobs_(?:[a-z0-9]+), users AS u$/'); 
    });
    
    it(": Réinitialisation de la table", function() {
        $builder = $this->builder->from('jobs')->from('users u', true);

        expect($builder->sql())->toMatch('/^SELECT \* FROM users AS u$/'); 
    });
    
    it(": Réinitialisations", function() {
        $builder = $this->builder->from(['jobs j', 'roles r']);
        expect($builder->sql())->toBe('SELECT * FROM jobs AS j, roles AS r'); 
        
        $builder = $this->builder->from(null, true);
        expect($builder->sql())->toBe('SELECT *'); 
        
        $builder = $this->builder->from('jobs j');
        expect($builder->sql())->toBe('SELECT * FROM jobs AS j'); 
    });
    
    it(": Sous requetes", function() {
        $subquery = (clone $this->builder)->from('users u');
        $builder = $this->builder->fromSubquery($subquery, 'alias');
        expect($builder->sql())->toBe('SELECT * FROM (SELECT * FROM users AS u) AS alias'); 
        
        $subquery = (clone $this->builder)->from('users u')->select('id, name');
        $builder = $this->builder->fromSubquery($subquery, 'users_1');
        expect($builder->sql())->toBe('SELECT * FROM (SELECT id, name FROM users AS u) AS users_1'); 
        
        $subquery = (clone $this->builder)->from('users u');
        $builder = $this->builder->fromSubquery($subquery, 'alias')->from('some_table st');
        expect($builder->sql())->toBe('SELECT * FROM (SELECT * FROM users AS u) AS alias, some_table AS st'); 
    });
});
