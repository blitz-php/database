<?php

use BlitzPHP\Database\Builder\BaseBuilder;
use BlitzPHP\Database\Spec\Mock\MockConnection;

describe("Database / Query Builder : UNION", function() {

    beforeEach(function() {
        $this->builder = new BaseBuilder(new MockConnection([]));
    });

    it(": UNION simple", function() {
        $builder = $this->builder->testMode()
            ->from('users')
            ->select('name, email')
            ->where('active', 1)
            ->union(function($q) {
                $q->from('deleted_users')
                  ->select('name, email')
                  ->where('restored', 0);
            });

        expect($builder->toSql())->toBe(
            "SELECT name, email FROM users WHERE active = ? " .
            "UNION SELECT name, email FROM deleted_users WHERE restored = ?"
        );
        expect($builder->getBindings())->toBe([1, 0]);
    });

    it(": UNION ALL", function() {
        $builder = $this->builder->testMode()
            ->from('orders_2023')
            ->select('id, total')
            ->unionAll(function($q) {
                $q->from('orders_2024')
                  ->select('id, total');
            });

        expect($builder->sql())->toBe(
            "SELECT id, total FROM orders_2023 " .
            "UNION ALL SELECT id, total FROM orders_2024"
        );
    });

    it(": UNION multiples", function() {
        $builder = $this->builder->testMode()
            ->from('q1')
            ->select('data')
            ->union(function($q) { $q->from('q2')->select('data'); })
            ->union(function($q) { $q->from('q3')->select('data'); });

        expect($builder->sql())->toBe(
            "SELECT data FROM q1 " .
            "UNION SELECT data FROM q2 " .
            "UNION SELECT data FROM q3"
        );
    });

    it(": UNION avec ORDER BY et LIMIT", function() {
        $builder = $this->builder->testMode()
            ->from('products')
            ->select('name, price')
            ->union(function($q) {
                $q->from('archived_products')
                  ->select('name, price');
            })
            ->orderBy('price', 'DESC')
            ->limit(10);

        expect($builder->sql())->toBe(
            "SELECT name, price FROM products " .
            "UNION SELECT name, price FROM archived_products " .
            "ORDER BY price DESC LIMIT 10"
        );
    });

    it(": UNION avec sous-requête complexe", function() {
        $subquery = (new BaseBuilder(new MockConnection([])))
            ->from('logs')
            ->select('user_id, COUNT(*) as count')
            ->groupBy('user_id')
            ->having('count >', 5);

        $builder = $this->builder->testMode()
            ->from('users')
            ->select('id, name')
            ->union(function($q) use ($subquery) {
                $q->fromSubquery($subquery, 'active_logs')
                  ->select('user_id as id, count as name');
            });

        expect($builder->toSql())->toBe(
            "SELECT id, name FROM users " .
            "UNION SELECT user_id AS id, count AS name FROM " .
            "(SELECT user_id, COUNT(*) as count FROM logs GROUP BY user_id HAVING count > ?) AS active_logs"
        );
        expect($builder->getBindings())->toBe([5]);
    });
});
