<?php

use BlitzPHP\Database\BaseBuilder;
use BlitzPHP\Database\Exceptions\DatabaseException;
use BlitzPHP\Database\Spec\Mock\MockConnection;

describe("Database / Query Builder : Insertion", function() {

    beforeEach(function() {
        $this->builder = new BaseBuilder(new MockConnection([]));
    });

    it(": Insertion de base (avec les tableaux)", function() {
        $builder = $this->builder->into('jobs')->testMode();
        expect($builder->insert([
            'id'   => 1,
            'name' => 'Grocery Sales',
        ]))
        ->toBe('INSERT INTO jobs (id,name) VALUES (1,\'Grocery Sales\')');
    });
    
    it(": Insertion de base (avec les objets)", function() {
        $builder = $this->builder->into('jobs')->testMode();
        expect($builder->insert((object) [
            'id'   => 1,
            'name' => 'Grocery Sales',
        ]))
        ->toBe('INSERT INTO jobs (id,name) VALUES (1,\'Grocery Sales\')');
    });
    
    it(": Insert Ignore", function() {
        $builder = $this->builder->into('jobs')->testMode();
        expect($builder->insertIgnore([
            'id'   => 1,
            'name' => 'Grocery Sales',
        ]))
        ->toBe('INSERT IGNORE INTO jobs (id,name) VALUES (1,\'Grocery Sales\')');
    });
    
    it(": Insertion avec l'alias sur la table", function() {
        $builder = $this->builder->into('jobs as j')->testMode();
        expect($builder->insert((object) [
            'id'   => 1,
            'name' => 'Grocery Sales',
        ]))
        ->toBe('INSERT INTO jobs (id,name) VALUES (1,\'Grocery Sales\')');
    });
    
    it(": Vérification de la présence d'une table", function() {
        $builder = $this->builder->testMode();
        expect(function() use ($builder) {
            $builder->insert([
                'id'   => 1,
                'name' => 'Grocery Sales',
            ]);
        })->toThrow(new DatabaseException("Table is not defined."));
    });

    it(": Vérification de la présence des donnees", function() {
        $builder = $this->builder->testMode()->into('jobs');
        expect(function() use ($builder) {
            $builder->insert([]);
        })->toThrow(new DatabaseException("You must give entries to insert."));
    });

    describe('BulkInsert', function() {
        it(": Insertion multiple", function() {
            $builder = $this->builder->into('jobs')->testMode();
            expect($builder->bulckInsert([
                [
                    'id'          => 2,
                    'name'        => 'Commedian',
                    'description' => 'There\'s something in your teeth',
                ],
                [
                    'id'          => 3,
                    'name'        => 'Cab Driver',
                    'description' => 'I am yellow',
                ],
            ]))
            ->toBe("INSERT INTO jobs (id,name,description) VALUES (2,'Commedian','There''s something in your teeth'); INSERT INTO jobs (id,name,description) VALUES (3,'Cab Driver','I am yellow')");
        });
    
        it(": Insertion multiple IGNORE", function() {
            $builder = $this->builder->into('jobs')->testMode();
            expect($builder->bulckInsertIgnore([
                [
                    'id'          => 2,
                    'name'        => 'Commedian',
                    'description' => 'There\'s something in your teeth',
                ],
                [
                    'id'          => 3,
                    'name'        => 'Cab Driver',
                    'description' => 'I am yellow',
                ],
            ]))
            ->toBe("INSERT IGNORE INTO jobs (id,name,description) VALUES (2,'Commedian','There''s something in your teeth'); INSERT IGNORE INTO jobs (id,name,description) VALUES (3,'Cab Driver','I am yellow')");
        });

        it(": Insertion multiple sans echappement", function() {
            $builder = $this->builder->into('jobs')->testMode();
            expect($builder->bulckInsert([
                [
                    'id'          => 2,
                    'name'        => '1 + 1',
                    'description' => '1 + 2',
                ],
                [
                    'id'          => 3,
                    'name'        => '2 + 1',
                    'description' => '2 + 2',
                ],
            ], false))
            ->toBe("INSERT INTO jobs (id,name,description) VALUES (2,1 + 1,1 + 2); INSERT INTO jobs (id,name,description) VALUES (3,2 + 1,2 + 2)");
        });
    });
});
