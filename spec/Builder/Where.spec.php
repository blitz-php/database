<?php

use BlitzPHP\Database\Builder\BaseBuilder;
use BlitzPHP\Database\Spec\Mock\MockConnection;

describe("Query Builder : Where", function() {

    beforeEach(function() {
        $this->builder = new BaseBuilder(new MockConnection([]));
    });

    describe('Simple where', function() {
        it(": Where simple", function() {
            $builder = $this->builder->from('users')->where('id', 3);
            expect($builder->sql())->toMatch('/^SELECT \* FROM users As users_(?:[a-z0-9]+) WHERE id = 3$/');           
        });
    
        it(": Where avec un operateur personnalisé", function() {
            $builder = $this->builder->from('users u')->where('id !=', 3);
            expect($builder->sql())->toBe('SELECT * FROM users As u WHERE id != 3');        
            
            $builder = $this->builder->from('users u')->where('firstname !=', 'john');
            expect($builder->sql())->toBe("SELECT * FROM users As u WHERE firstname != 'john'");
        });
    
        it(": Where avec un tableau de condition", function() {
            $builder = $this->builder->from('users u')->where([
                'firstname !=' => 'john',
                'lastname' => 'doe'
            ]);
            expect($builder->sql())->toBe("SELECT * FROM users As u WHERE firstname != 'john' AND lastname = 'doe'");
        });
        
        it(": Where Like dans un tableau de condition", function() {
            $builder = $this->builder->from('users u')->where([
                'id <'      => 100,
                'col1 LIKE' => '%gmail%',
            ]);
            expect($builder->sql())->toBe("SELECT * FROM users As u WHERE id < 100 AND col1 LIKE '%gmail%'");
        });  
        
        it(": Where en tant que chaine personnalisée", function() {
            $where = "id > 2 AND name != 'Accountant'";
            $builder = $this->builder->from('jobs j')->where($where);
            expect($builder->sql())->toBe("SELECT * FROM jobs As j WHERE id > 2 AND name != 'Accountant'");
        });  
        
        it(": Where en tant que chaine personnalisée avec l'operateur d'echappement desactivé", function() {
            $where = 'CURRENT_TIMESTAMP() = DATE_ADD(column, INTERVAL 2 HOUR)';
            $builder = $this->builder->from('jobs j')->where($where, null, false);
            expect($builder->sql())->toBe("SELECT * FROM jobs As j WHERE CURRENT_TIMESTAMP() = DATE_ADD(column, INTERVAL 2 HOUR)");
        });        
    });

    describe('where raw', function() {
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

    describe('where column', function() {
        it(": WhereColumn", function() {
            $builder = $this->builder->from(['users u', 'jobs j'])->whereColumn('users.id_user', 'jobs.id_user');
            expect($builder->sql())->toBe('SELECT * FROM users As u, jobs As j WHERE u.id_user = j.id_user');           
            
            $builder = $this->builder->from(['users u', 'jobs j'])->whereColumn('u.id_user', 'j.id_user');
            expect($builder->sql())->toBe('SELECT * FROM users As u, jobs As j WHERE u.id_user = j.id_user');

            $builder = $this->builder->from(['users', 'jobs'])->whereColumn('users.id_user', 'jobs.id_user');
            expect($builder->sql())->toMatch('/^SELECT \* FROM users As users_(?:[a-z0-9]+), jobs As jobs_(?:[a-z0-9]+) WHERE users_(?:[a-z0-9]+)\.id_user = jobs_(?:[a-z0-9]+)\.id_user$/');
            
            $builder = $this->builder->from(['users u', 'jobs j'])->whereColumn(['users.id_user' => 'jobs.id_user', 'u.name' => 'j.username']);
            expect($builder->sql())->toBe('SELECT * FROM users As u, jobs As j WHERE u.id_user = j.id_user AND u.name = j.username');
        });

        it(": NotWhereColumn", function() {
            $builder = $this->builder->from(['users u', 'jobs j'])->notWhereColumn('users.id_user', 'jobs.id_user');
            expect($builder->sql())->toBe('SELECT * FROM users As u, jobs As j WHERE u.id_user != j.id_user');           
            
            $builder = $this->builder->from(['users u', 'jobs j'])->notWhereColumn(['users.id_user' => 'jobs.id_user', 'u.name' => 'j.username']);
            expect($builder->sql())->toBe('SELECT * FROM users As u, jobs As j WHERE u.id_user != j.id_user AND u.name != j.username');

            $builder = $this->builder->from(['users u', 'jobs j'])->whereColumn(['users.id_user' => 'jobs.id_user'])->whereNotColumn(['u.name' => 'j.username']);
            expect($builder->sql())->toBe('SELECT * FROM users As u, jobs As j WHERE u.id_user = j.id_user AND u.name != j.username');
        });

        it(": OrWhereColumn", function() {
            $builder = $this->builder->from(['users u', 'jobs j'])->whereColumn(['users.id_user' => 'jobs.id_user'])->orWhereColumn('u.name', 'j.username');
            expect($builder->sql())->toBe('SELECT * FROM users As u, jobs As j WHERE u.id_user = j.id_user OR u.name = j.username');
            
            $builder = $this->builder->from(['users u', 'jobs j'])->orWhereColumn(['users.id_user' => 'jobs.id_user', 'u.name' => 'j.username']);
            expect($builder->sql())->toBe('SELECT * FROM users As u, jobs As j WHERE u.id_user = j.id_user OR u.name = j.username');
        });

        it(": OrNotWhereColumn", function() {
            $builder = $this->builder->from(['users u', 'jobs j'])->whereColumn(['users.id_user' => 'jobs.id_user'])->orNotWhereColumn('u.name', 'j.username');
            expect($builder->sql())->toBe('SELECT * FROM users As u, jobs As j WHERE u.id_user = j.id_user OR u.name != j.username');
        });
    });

    describe('whereOr', function(){
        it(": WhereOr simple", function() {
            $builder = $this->builder->from('users u')->where('name !=', 'John')->orWhere('id >', 2);
            expect($builder->sql())->toBe('SELECT * FROM users As u WHERE name != \'John\' OR id > 2');
        });

        it(": WhereOr avec la meme colonne", function() {
            $builder = $this->builder->from('users u')->where('name !=', 'John')->orWhere('name', 'Doe');
            expect($builder->sql())->toBe('SELECT * FROM users As u WHERE name != \'John\' OR name = \'Doe\'');
        });  
    });

    describe('whereNull', function(){
        it(": WhereNull simple", function() {
            $builder = $this->builder->from('users u')->whereNull('name');
            expect($builder->sql())->toBe('SELECT * FROM users As u WHERE name IS NULL');
        });  
        
        it(": WhereNull multiple", function() {
            $builder = $this->builder->from('users u')->whereNull(['name', 'surname']);
            expect($builder->sql())->toBe('SELECT * FROM users As u WHERE name IS NULL AND surname IS NULL');
            
            $builder = $this->builder->from('users u')->whereNull('name')->whereNull('surname');
            expect($builder->sql())->toBe('SELECT * FROM users As u WHERE name IS NULL AND surname IS NULL');
        }); 

        it(": WhereNull multiple avec une autre condition", function() {
            $builder = $this->builder->from('users u')->whereNull('name')->where('surname', 'blitz');
            expect($builder->sql())->toBe('SELECT * FROM users As u WHERE name IS NULL AND surname = \'blitz\'');
        });  

        it(": orWhereNull multiple avec une autre condition", function() {
            $builder = $this->builder->from('users u')->where('surname', 'blitz')->orWhereNull('name');
            expect($builder->sql())->toBe('SELECT * FROM users As u WHERE surname = \'blitz\' OR name IS NULL');
        });
        
        it(": WhereNotNull simple", function() {
            $builder = $this->builder->from('users u')->whereNotNull('name');
            expect($builder->sql())->toBe('SELECT * FROM users As u WHERE name IS NOT NULL');
        });  
        
        it(": WhereNotNull multiple", function() {
            $builder = $this->builder->from('users u')->whereNotNull(['name', 'surname']);
            expect($builder->sql())->toBe('SELECT * FROM users As u WHERE name IS NOT NULL AND surname IS NOT NULL');
            
            $builder = $this->builder->from('users u')->whereNotNull('name')->whereNotNull('surname');
            expect($builder->sql())->toBe('SELECT * FROM users As u WHERE name IS NOT NULL AND surname IS NOT NULL');
        }); 

        it(": WhereNotNull multiple avec une autre condition", function() {
            $builder = $this->builder->from('users u')->whereNotNull('name')->where('surname', 'blitz');
            expect($builder->sql())->toBe('SELECT * FROM users As u WHERE name IS NOT NULL AND surname = \'blitz\'');
        });  

        it(": orWhereNotNull multiple avec une autre condition", function() {
            $builder = $this->builder->from('users u')->where('surname', 'blitz')->orWhereNotNull('name');
            expect($builder->sql())->toBe('SELECT * FROM users As u WHERE surname = \'blitz\' OR name IS NOT NULL');
        });
        
    });
});
