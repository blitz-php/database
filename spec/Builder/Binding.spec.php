<?php

use BlitzPHP\Database\Builder\BaseBuilder;
use BlitzPHP\Database\Builder\BindingCollection;
use BlitzPHP\Database\Spec\Mock\MockConnection;

use function Kahlan\expect;

describe("Database / Query Builder : Bindings", function() {

    beforeEach(function() {
        $this->builder = new BaseBuilder(new MockConnection([]));
    });

    describe("BindingCollection simple", function() {
        it(": BindingCollection ajout simple", function() {
            $collection = new BindingCollection();
            $collection->add('value1');
            $collection->add(123);
            $collection->add(true);
            $collection->add(null);
            
            // le null n'est pas pris en charge par le binding
            expect($collection->count())->toBe(3);
            expect($collection->getOrdered())->toBe(['value1', 123, true]);
        });
    
        it(": BindingCollection ajout nommé", function() {
            $collection = new BindingCollection();
            $collection->addNamed(':name', 'John');
            $collection->addNamed(':age', 30);
            
            expect($collection->get('where', ':name'))->toBe('John');
            expect($collection->get('where', ':age'))->toBe(30);
        });
    
        it(": BindingCollection types", function() {
            $collection = new BindingCollection();
            $collection->add('string');
            $collection->add(123);
            $collection->add(true);
            $collection->add(null);
            
            $types = $collection->getTypesOrdered();
            expect($types[0])->toBe(PDO::PARAM_STR);
            expect($types[1])->toBe(PDO::PARAM_INT);
            expect($types[2])->toBe(PDO::PARAM_BOOL);
            expect($types[3])->toBe(PDO::PARAM_NULL);
        });
    
        it(": BindingCollection merge", function() {
            $col1 = new BindingCollection();
            $col1->add('a')->add('b');
            
            $col2 = new BindingCollection();
            $col2->add('c')->add('d');
            
            $col1->merge($col2);
            
            expect($col1->count())->toBe(4);
            expect($col1->getOrdered())->toBe(['a', 'b', 'c', 'd']);
        });
    
        it(": BindingCollection clear", function() {
            $collection = new BindingCollection();
            $collection->add('test');
            expect($collection->isEmpty())->toBe(false);
            
            $collection->clear();
            expect($collection->isEmpty())->toBe(true);
        });
    
        it(": Les bindings sont correctement transmis dans la requête", function() {
            $builder = $this->builder->testMode()
                ->from('users')
                ->where('id', 5)
                ->where('name', 'John')
                ->whereIn('status', [1, 2, 3]);
    
            expect($builder->bindings->count())->toBe(5);
            expect($builder->bindings->getOrdered())->toBe([5, 'John', 1, 2, 3]);
        });
    
        it(": Les bindings sont réinitialisés après exécution", function() {
            $builder = $this->builder->from('users')->where('id', 5);
            
            expect($builder->bindings->isEmpty())->toBe(false);

            try {
                $sql = $builder->get();
            } catch(Exception) {
                // l'execution ne passera pas car on a pas de bd.
                // on veut juste se rassuer que les bindings sont reset
                expect($builder->bindings->isEmpty())->toBe(true);
            }
        });
    
        it(": Les bindings sont préservés dans les sous-requêtes", function() {
            $builder = $this->builder->testMode()
                ->from('users')
                ->whereIn('id', function($q) {
                    $q->from('profiles')
                      ->select('user_id')
                      ->where('active', 1)
                      ->where('points >', 100);
                });
    
            expect($builder->bindings->count())->toBe(2);
            expect($builder->bindings->getOrdered())->toBe([1, 100]);
        });
    });

    describe("BindingCollection avec types", function() {
        it(": getOrdered avec types spécifiques", function() {
            $bindings = new BindingCollection();
            $bindings->add('value1', 'values');
            $bindings->add(5, 'where');
            $bindings->add('join_cond', 'join');
            
            expect($bindings->getOrdered(['values', 'where']))->toBe(['value1', 5]);
            expect($bindings->getOrdered(['where', 'values']))->toBe([5, 'value1']);
            expect($bindings->getOrdered())->toHaveLength(3);
        });

        it(": getOrdered ignore les types vides", function() {
            $bindings = new BindingCollection();
            $bindings->add('value1', 'values');
            
            expect($bindings->getOrdered(['values', 'where', 'having']))->toBe(['value1']);
        });
    });

    describe("BaseBuilder::getBindings", function() {
        it(": UPDATE - valeurs avant where", function() {
            $builder = $this->builder->table('users')
                ->where('id', 5)
                ->where('active', 1)
                ->set(['name' => 'John'])
                ->pending() // pour eviter l'execution
                ->update();
                
            expect($builder->getBindings())->toBe(['John', 5, 1]);
        });

        it(": INSERT - seulement valeurs", function() {
            $builder = $this->builder->table('users')
                ->set(['name' => 'John', 'age' => 30])
                ->pending() // pour eviter l'execution
                ->insert();
                
            expect($builder->getBindings())->toBe(['John', 30]);
        });

        it(": SELECT - where dans l'ordre", function() {
            $builder = $this->builder->table('users')
                ->where('id', 5)
                ->where('name', 'John')
                ->orderBy('created_at');
                
            expect($builder->getBindings())->toBe([5, 'John']);
        });
    });
});
