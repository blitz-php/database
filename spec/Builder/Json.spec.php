<?php

use BlitzPHP\Database\Builder\BaseBuilder;
use BlitzPHP\Database\Spec\Mock\MockConnection;

describe("Database / Query Builder : JSON", function() {

    beforeEach(function() {
        $this->builder = new BaseBuilder(new MockConnection([]));
    });

    it(": whereJsonContains", function() {
        $builder = $this->builder->testMode()
            ->from('users')
            ->whereJsonContains('preferences->languages', 'fr');

        expect($builder->toSql())->toBe(
            "SELECT * FROM users WHERE JSON_CONTAINS(preferences->languages, ?)"
        );
        expect($builder->getBindings())->toBe(['fr']);
    });

    it(": whereJsonDoesntContain", function() {
        $builder = $this->builder->testMode()
            ->from('users')
            ->whereJsonDoesntContain('preferences->tags', 'premium');

        expect($builder->toSql())->toBe(
            "SELECT * FROM users WHERE NOT JSON_CONTAINS(preferences->tags, ?)"
        );
        expect($builder->getBindings())->toBe(['premium']);
    });

    it(": whereJsonContainsKey", function() {
        $builder = $this->builder->testMode()
            ->from('users')
            ->whereJsonContainsKey('settings->notifications');

        expect($builder->sql())->toBe(
            "SELECT * FROM users WHERE JSON_CONTAINS_PATH(settings->notifications, 'one', ?) = 1"
        );
    });

    it(": whereJsonLength", function() {
        $builder = $this->builder->testMode()
            ->from('users')
            ->whereJsonLength('preferences->items', '>', 5);

        expect($builder->toSql())->toBe(
            "SELECT * FROM users WHERE JSON_LENGTH(preferences->items) > ?"
        );
        expect($builder->getBindings())->toBe([5]);
    });

    it(": orWhereJsonContains", function() {
        $builder = $this->builder->testMode()
            ->from('users')
            ->where('active', 1)
            ->orWhereJsonContains('preferences->languages', 'en');

        expect($builder->toSql())->toBe(
            "SELECT * FROM users WHERE active = ? OR JSON_CONTAINS(preferences->languages, ?)"
        );
        expect($builder->getBindings())->toBe([1, 'en']);
    });
});
