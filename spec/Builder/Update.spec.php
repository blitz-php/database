<?php

use BlitzPHP\Database\Builder\BaseBuilder;
use BlitzPHP\Database\Spec\Mock\MockConnection;

describe("Database / Query Builder : Mise à jour", function() {

    beforeEach(function() {
        $this->builder = new BaseBuilder(new MockConnection([]));
    });

    it(": Mise à jour simple", function() {
        $builder = $this->builder->testMode()->table('users');
        expect($builder->update([
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]))->toBe("UPDATE users SET name = 'John Doe', email = 'john@example.com'");
    });

    it(": Mise à jour avec condition", function() {
        $builder = $this->builder->testMode()->table('users')->where('id', 5);
        expect($builder->update(['name' => 'John Doe']))
            ->toBe("UPDATE users SET name = 'John Doe' WHERE id = 5");
    });

    it(": Mise à jour avec jointure", function() {
        $builder = $this->builder->testMode()
            ->table('users u')
            ->join('profiles p', 'u.id', '=', 'p.user_id')
            ->where('u.id', 5);
        
        expect($builder->update(['u.name' => 'John Doe', 'p.bio' => 'New bio']))
            ->toBe("UPDATE users AS u INNER JOIN profiles AS p ON u.id = p.user_id SET u.name = 'John Doe', p.bio = 'New bio' WHERE u.id = 5");
    });
    
    it(": Mise à jour avec jointure et sans condition", function() {
        $builder = $this->builder->testMode()
            ->table('users u')
            ->join('profiles p', 'u.id', '=', 'p.user_id');
        
        expect($builder->update(['u.name' => 'John Doe']))
            ->toBe("UPDATE users AS u INNER JOIN profiles AS p ON u.id = p.user_id SET u.name = 'John Doe'");
    });
    
    it(": Mise à jour avec jointures multiples", function() {
        $builder = $this->builder->testMode()
            ->table('users u')
            ->join('profiles p', 'u.id', '=', 'p.user_id')
            ->join('roles r', 'u.role_id', '=', 'r.id', 'LEFT')
            ->where('u.id', 5);
        
        expect($builder->update(['u.name' => 'John Doe', 'r.name' => 'Admin']))
            ->toBe("UPDATE users AS u INNER JOIN profiles AS p ON u.id = p.user_id LEFT JOIN roles AS r ON u.role_id = r.id SET u.name = 'John Doe', r.name = 'Admin' WHERE u.id = 5");
    });

    it(": Mise à jour avec limite", function() {
        $builder = $this->builder->testMode()->table('users')->where('active', 1)->limit(10);
        expect($builder->update(['status' => 'inactive']))
            ->toBe("UPDATE users SET status = 'inactive' WHERE active = 1 LIMIT 10");
    });

    it(": Mise à jour avec expression", function() {
        $builder = $this->builder->testMode()->table('users');
        expect($builder->update([
            'views' => $builder::raw('views + 1'),
            'updated_at' => $builder::raw('NOW()')
        ]))->toBe("UPDATE users SET views = views + 1, updated_at = NOW()");
    });

    it(": Increment", function() {
        $builder = $this->builder->testMode()->table('users')->where('id', 1);
        expect($builder->increment('views', 2))
            ->toBe("UPDATE users SET views = views + 2 WHERE id = 1");
    });

    it(": Increment avec données supplémentaires", function() {
        $builder = $this->builder->testMode()->table('users')->where('id', 1);
        expect($builder->increment('views', 1, ['updated_at' => '2024-01-01']))
            ->toBe("UPDATE users SET views = views + 1, updated_at = '2024-01-01' WHERE id = 1");
    });

    it(": Decrement", function() {
        $builder = $this->builder->testMode()->table('users')->where('id', 1);
        expect($builder->decrement('score', 5))
            ->toBe("UPDATE users SET score = score - 5 WHERE id = 1");
    });

    xdescribe('Difficilement testable à ce niveau', function() {
        it(": UpdateOrInsert - existant", function() {
            $builder = $this->builder->table('users');
            
            // Simuler l'existence
            allow($builder->clone()->where(['email' => 'john@example.com']))
                ->toReceive('exists')
                ->andReturn(true);
            
            $result = $builder->updateOrInsert(
                ['email' => 'john@example.com'],
                ['name' => 'John Updated']
            );
            
            expect($result)->toBe(true);
        });
    
        it(": UpdateOrInsert - nouveau", function() {
            $builder = $this->builder->table('users');
            
            // Simuler la non-existence
            allow($builder->clone()->where(['email' => 'new@example.com']))
                ->toReceive('exists')
                ->andReturn(false);
            
            allow($builder)->toReceive('insert')->andReturn(true);
            
            $result = $builder->updateOrInsert(
                ['email' => 'new@example.com'],
                ['name' => 'New User']
            );
            
            expect($result)->toBe(true);
        });
    });
});
