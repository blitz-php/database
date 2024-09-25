<?php

use BlitzPHP\Database\Builder\BaseBuilder;
use BlitzPHP\Database\Builder\MySQL as MySQLBuilder;
use BlitzPHP\Database\Builder\Postgre as PostgreBuilder;
use BlitzPHP\Database\Builder\SQLite as SQLiteBuilder;
use BlitzPHP\Database\Spec\Mock\MockConnection;
use BlitzPHP\Utilities\Date;

use function Kahlan\expect;

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
            $builder = $this->builder->from('users u')->whereNull('name')->whereNull('surname')->where('lastname', 'blitz');
            expect($builder->sql())->toBe('SELECT * FROM users As u WHERE name IS NULL AND surname IS NULL AND lastname = \'blitz\'');
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

    describe('whereDate', function(){
        beforeEach(function() {
            $this->builder = new MySQLBuilder(new MockConnection([]));
        });

        it(": WhereDate simple", function() {
            $builder = $this->builder->from('users u')->whereDate('created_at', Date::now());
            expect($builder->sql())->toBe('SELECT * FROM users As u WHERE DATE(created_at) = \'' . Date::now()->format('Y-m-d') . '\'');
            
            $builder = $this->builder->from('users u')->whereDate('created_at', '2024-03-24');
            expect($builder->sql())->toBe("SELECT * FROM users As u WHERE DATE(created_at) = '2024-03-24'");
            
            $builder = $this->builder->from('users u')->whereDate('created_at', 1711269528);
            expect($builder->sql())->toBe("SELECT * FROM users As u WHERE DATE(created_at) = '2024-03-24'");
        });

        it(": WhereDate multiple", function() {
            $builder = $this->builder->from('users u')->whereDate('created_at', Date::now())->whereDate('updated_at', '2024-03-24');
            expect($builder->sql())->toBe("SELECT * FROM users As u WHERE DATE(created_at) = '" . Date::now()->format('Y-m-d') . "' AND DATE(updated_at) = '2024-03-24'");

            $builder = $this->builder->from('users u')->whereDate([
                'created_at' => 1711269528,
                'updated_at' => '2024-03-25'
            ]);
            expect($builder->sql())->toBe("SELECT * FROM users As u WHERE DATE(created_at) = '2024-03-24' AND DATE(updated_at) = '2024-03-25'");
        });  
        
        it(": WhereDate avec condition personnalisee", function() {
            $builder = $this->builder->from('users u')->whereDate('created_at >=', Date::now())->whereDate('updated_at <', '2024-03-24');
            expect($builder->sql())->toBe("SELECT * FROM users As u WHERE DATE(created_at) >= '" . Date::now()->format('Y-m-d') . "' AND DATE(updated_at) < '2024-03-24'");

            $builder = $this->builder->from('users u')->whereDate([
                'created_at >' => 1711269528,
                'updated_at !=' => '2024-03-25'
            ]);
            expect($builder->sql())->toBe("SELECT * FROM users As u WHERE DATE(created_at) > '2024-03-24' AND DATE(updated_at) != '2024-03-25'");
        }); 

        it(": OrWhereDate", function() {
            $builder = $this->builder->from('users u')->whereDate('created_at >=', Date::now())->orWhereDate('updated_at <', '2024-03-24');
            expect($builder->sql())->toBe("SELECT * FROM users As u WHERE DATE(created_at) >= '" . Date::now()->format('Y-m-d') . "' OR DATE(updated_at) < '2024-03-24'");

            $builder = $this->builder->from('users u')->orWhereDate([
                'created_at >' => 1711269528,
                'updated_at !=' => '2024-03-25'
            ]);
            expect($builder->sql())->toBe("SELECT * FROM users As u WHERE DATE(created_at) > '2024-03-24' OR DATE(updated_at) != '2024-03-25'");
        });  

        it(": WhereDate SQLite", function() {
            $builder = new SQLiteBuilder(new MockConnection([]));
            $builder = $builder->from('users u')->whereDate('created_at', Date::now());
            expect($builder->sql())->toBe("SELECT * FROM users As u WHERE strftime('%Y-%m-%d', created_at) = cast(" . Date::now()->format('Y-m-d') . " as text)");
            
            $builder = $builder->from('users u')->orWhereDate([
                'created_at >' => 1711269528,
                'updated_at !=' => '2024-03-25'
            ]);
            expect($builder->sql())->toBe("SELECT * FROM users As u WHERE strftime('%Y-%m-%d', created_at) > cast(2024-03-24 as text) OR strftime('%Y-%m-%d', updated_at) != cast(2024-03-25 as text)");
        });

        it(": WhereDate Postgre", function() {
            $builder = new PostgreBuilder(new MockConnection([]));
            $builder = $builder->from('users u')->whereDate('created_at', Date::now());
            expect($builder->sql())->toBe("SELECT * FROM users As u WHERE created_at::date = '" . Date::now()->format('Y-m-d') . "'");
            
            $builder = $builder->from('users u')->orWhereDate([
                'created_at >' => 1711269528,
                'updated_at !=' => '2024-03-25'
            ]);
            expect($builder->sql())->toBe("SELECT * FROM users As u WHERE created_at::date > '2024-03-24' OR updated_at::date != '2024-03-25'");
        });
    });

    describe('whereExists', function() {
        it(': whereExists simple', function() {
            $builder = $this->builder->from('users u')->whereExists(function($query) {
                $query->from('posts p')->where('u.id', 'p.user_id');
            });

            expect($builder->sql())->toBe('SELECT * FROM users As u WHERE EXISTS (SELECT * FROM posts As p WHERE u.id = p.user_id)');
        });

        it(": WhereExists simple avec une autre condition", function() {
            $builder = $this->builder->from('users u')->whereNull('deleted_at')->whereExists(function($query) {
                $query->from('posts p')->where('u.id', 'p.user_id');
            });
            expect($builder->sql())->toBe('SELECT * FROM users As u WHERE deleted_at IS NULL AND EXISTS (SELECT * FROM posts As p WHERE u.id = p.user_id)');
        });
        
        it(': whereExists multiple', function() {
            $builder = $this->builder->from('parcours p')->whereExists(function($query) {
                $query->from('users u')->where('u.id', 'p.enseignant_id');
            })->whereExists(function($query) {
                $query->from('users u')->where('u.id', 'p.apprenant_id');
            });

            expect($builder->sql())->toBe('SELECT * FROM parcours As p WHERE EXISTS (SELECT * FROM users As u WHERE u.id = p.enseignant_id) AND EXISTS (SELECT * FROM users As u WHERE u.id = p.apprenant_id)');
        });

        it(": WhereExists multiple avec une autre condition", function() {
            $builder = $this->builder->from('parcours p')->whereExists(function($query) {
                $query->from('users u')->where('u.id', 'p.enseignant_id');
            })->whereExists(function($query) {
                $query->from('users u')->where('u.id', 'p.apprenant_id');
            })->whereNull('deleted_at')->where('status', 'active');

            expect($builder->sql())->toBe('SELECT * FROM parcours As p WHERE EXISTS (SELECT * FROM users As u WHERE u.id = p.enseignant_id) AND EXISTS (SELECT * FROM users As u WHERE u.id = p.apprenant_id) AND deleted_at IS NULL AND status = \'active\'');
        });

        it(": orWhereExists simple avec une autre condition", function() {
            $builder = $this->builder->from('users u')->whereNull('deleted_at')->orWhereExists(function($query) {
                $query->from('posts p')->where('u.id', 'p.user_id');
            });
            expect($builder->sql())->toBe('SELECT * FROM users As u WHERE deleted_at IS NULL OR EXISTS (SELECT * FROM posts As p WHERE u.id = p.user_id)');
        
            $builder = $this->builder->from('users u')->whereNull('u.deleted_at')->orWhereExists(function($query) {
                $query->from('posts p')->where('u.id', 'p.user_id')->whereNull('p.deleted_at');
            });
            expect($builder->sql())->toBe('SELECT * FROM users As u WHERE u.deleted_at IS NULL OR EXISTS (SELECT * FROM posts As p WHERE u.id = p.user_id AND p.deleted_at IS NULL)');
        });

        it(': orWhereExists multiple', function() {
            $builder = $this->builder->from('parcours p')->orWhereExists(function($query) {
                $query->from('users u')->where('u.id', 'p.enseignant_id');
            })->orWhereExists(function($query) {
                $query->from('users u')->where('u.id', 'p.apprenant_id');
            });

            expect($builder->sql())->toBe('SELECT * FROM parcours As p WHERE EXISTS (SELECT * FROM users As u WHERE u.id = p.enseignant_id) OR EXISTS (SELECT * FROM users As u WHERE u.id = p.apprenant_id)');
        });

        it(": orWhereExists multiple avec une autre condition", function() {
            $builder = $this->builder->from('parcours p')->whereExists(function($query) {
                $query->from('users u')->where('u.id', 'p.enseignant_id');
            })->orWhereExists(function($query) {
                $query->from('users u')->where('u.id', 'p.apprenant_id');
            })->whereNull('deleted_at')->where('status', 'active');

            expect($builder->sql())->toBe('SELECT * FROM parcours As p WHERE EXISTS (SELECT * FROM users As u WHERE u.id = p.enseignant_id) OR EXISTS (SELECT * FROM users As u WHERE u.id = p.apprenant_id) AND deleted_at IS NULL AND status = \'active\'');
        });
    });
    
    describe('whereNotExists()', function() {
        it(': whereNotExists simple', function() {
            $builder = $this->builder->from('users u')->whereNotExists(function($query) {
                $query->from('posts p')->where('u.id', 'p.user_id');
            });

            expect($builder->sql())->toBe('SELECT * FROM users As u WHERE NOT EXISTS (SELECT * FROM posts As p WHERE u.id = p.user_id)');
        }); 
        
        it(": WhereNotExists simple avec une autre condition", function() {
            $builder = $this->builder->from('users u')->whereNull('deleted_at')->whereNotExists(function($query) {
                $query->from('posts p')->where('u.id', 'p.user_id');
            });
            expect($builder->sql())->toBe('SELECT * FROM users As u WHERE deleted_at IS NULL AND NOT EXISTS (SELECT * FROM posts As p WHERE u.id = p.user_id)');
        });

        it(': whereNotExists multiple', function() {
            $builder = $this->builder->from('parcours p')->whereNotExists(function($query) {
                $query->from('users u')->where('u.id', 'p.enseignant_id');
            })->whereNotExists(function($query) {
                $query->from('users u')->where('u.id', 'p.apprenant_id');
            });

            expect($builder->sql())->toBe('SELECT * FROM parcours As p WHERE NOT EXISTS (SELECT * FROM users As u WHERE u.id = p.enseignant_id) AND NOT EXISTS (SELECT * FROM users As u WHERE u.id = p.apprenant_id)');
        });

        it(": WhereNotExists multiple avec une autre condition", function() {
            $builder = $this->builder->from('parcours p')->whereNotExists(function($query) {
                $query->from('users u')->where('u.id', 'p.enseignant_id');
            })->whereNotExists(function($query) {
                $query->from('users u')->where('u.id', 'p.apprenant_id');
            })->whereNull('deleted_at')->where('status', 'active');

            expect($builder->sql())->toBe('SELECT * FROM parcours As p WHERE NOT EXISTS (SELECT * FROM users As u WHERE u.id = p.enseignant_id) AND NOT EXISTS (SELECT * FROM users As u WHERE u.id = p.apprenant_id) AND deleted_at IS NULL AND status = \'active\'');
        });

        it(": orWhereNotExists simple avec une autre condition", function() {
            $builder = $this->builder->from('users u')->whereNull('deleted_at')->orWhereNotExists(function($query) {
                $query->from('posts p')->where('u.id', 'p.user_id');
            });
            expect($builder->sql())->toBe('SELECT * FROM users As u WHERE deleted_at IS NULL OR NOT EXISTS (SELECT * FROM posts As p WHERE u.id = p.user_id)');
        
            $builder = $this->builder->from('users u')->whereNull('u.deleted_at')->orWhereNotExists(function($query) {
                $query->from('posts p')->where('u.id', 'p.user_id')->whereNull('p.deleted_at');
            });
            expect($builder->sql())->toBe('SELECT * FROM users As u WHERE u.deleted_at IS NULL OR NOT EXISTS (SELECT * FROM posts As p WHERE u.id = p.user_id AND p.deleted_at IS NULL)');
        });

        it(': orWhereExists multiple', function() {
            $builder = $this->builder->from('parcours p')->orWhereNotExists(function($query) {
                $query->from('users u')->where('u.id', 'p.enseignant_id');
            })->orWhereNotExists(function($query) {
                $query->from('users u')->where('u.id', 'p.apprenant_id');
            });

            expect($builder->sql())->toBe('SELECT * FROM parcours As p WHERE NOT EXISTS (SELECT * FROM users As u WHERE u.id = p.enseignant_id) OR NOT EXISTS (SELECT * FROM users As u WHERE u.id = p.apprenant_id)');
        });

        it(": orWhereExists multiple avec une autre condition", function() {
            $builder = $this->builder->from('parcours p')->whereNotExists(function($query) {
                $query->from('users u')->where('u.id', 'p.enseignant_id');
            })->orWhereNotExists(function($query) {
                $query->from('users u')->where('u.id', 'p.apprenant_id');
            })->whereNull('deleted_at')->where('status', 'active');

            expect($builder->sql())->toBe('SELECT * FROM parcours As p WHERE NOT EXISTS (SELECT * FROM users As u WHERE u.id = p.enseignant_id) OR NOT EXISTS (SELECT * FROM users As u WHERE u.id = p.apprenant_id) AND deleted_at IS NULL AND status = \'active\'');
        });
    });
});
