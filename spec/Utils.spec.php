<?php

use BlitzPHP\Database\Utils;

use function Kahlan\expect;

describe("Database / Utils", function() {

    it(": isSqlFunction", function() {
        expect(Utils::isSqlFunction('COUNT'))->toBe(true);
        expect(Utils::isSqlFunction('NOW'))->toBe(true);
        expect(Utils::isSqlFunction('RANDOM'))->toBe(false);
        expect(Utils::isSqlFunction('NOT EXISTS'))->toBe(true);
    });

    it(": isWritableSql", function() {
        expect(Utils::isWritableSql('SELECT * FROM users'))->toBe(false);
        expect(Utils::isWritableSql('INSERT INTO users'))->toBe(true);
        expect(Utils::isWritableSql('UPDATE users SET'))->toBe(true);
        expect(Utils::isWritableSql('DELETE FROM users'))->toBe(true);
        expect(Utils::isWritableSql('CREATE TABLE'))->toBe(true);
        expect(Utils::isWritableSql('DROP TABLE'))->toBe(true);
    });

    it(": hasOperator", function() {
        expect(Utils::hasOperator('id >'))->toBe(true);
        expect(Utils::hasOperator('name LIKE'))->toBe(true);
        expect(Utils::hasOperator('created_at BETWEEN'))->toBe(true);
        expect(Utils::hasOperator('simple_column'))->toBe(false);
    });

    it(": translateOperator", function() {
        expect(Utils::translateOperator('%'))->toBe('LIKE');
        expect(Utils::translateOperator('!%'))->toBe('NOT LIKE');
        expect(Utils::translateOperator('@'))->toBe('IN');
        expect(Utils::translateOperator('!@'))->toBe('NOT IN');
        expect(Utils::translateOperator('='))->toBe('=');
    });

    it(": invertOperator", function() {
        expect(Utils::invertOperator('='))->toBe('!=');
        expect(Utils::invertOperator('!='))->toBe('=');
        expect(Utils::invertOperator('<'))->toBe('>=');
        expect(Utils::invertOperator('>'))->toBe('<=');
        expect(Utils::invertOperator('LIKE'))->toBe('NOT LIKE');
        expect(Utils::invertOperator('NOT LIKE'))->toBe('LIKE');
        expect(Utils::invertOperator('IN'))->toBe('NOT IN');
    });

    it(": isAlias", function() {
        expect(Utils::isAlias('user_alias'))->toBe(true);
        expect(Utils::isAlias('AS user_alias'))->toBe(true);
        expect(Utils::isAlias('user-alias'))->toBe(false); // tiret pas autorisé
        expect(Utils::isAlias('user.alias'))->toBe(false); // point pas autorisé
    });

    it(": extractAlias", function() {
        expect(Utils::extractAlias('AS alias'))->toBe('alias');
        expect(Utils::extractAlias('alias'))->toBe('alias');
        expect(Utils::extractAlias('  AS  alias  '))->toBe('alias');
    });

    it(": extractOperatorFromColumn", function() {
        $result = Utils::extractOperatorFromColumn('id >');
        expect($result)->toBe(['id', '>']);
        
        $result = Utils::extractOperatorFromColumn('name LIKE', '%');
        expect($result)->toBe(['name', 'LIKE']);
        
        $result = Utils::extractOperatorFromColumn('age');
        expect($result)->toBe(['age', '=']);
    });

    it(": parseExpression", function() {
        $result = Utils::parseExpression('id > 5');
        expect($result)->toBe(['id', '>', 5]);
        
        $result = Utils::parseExpression('name LIKE "%john%"');
        expect($result)->toBe(['name', 'LIKE', '%john%']);
        
        $result = Utils::parseExpression('status IN (1,2,3)');
        expect($result[0])->toBe('status');
        expect($result[1])->toBe('IN');
        expect($result[2])->toBe([1, 2, 3]);
        
        $result = Utils::parseExpression('age BETWEEN 18 AND 30');
        expect($result[0])->toBe('age');
        expect($result[1])->toBe('BETWEEN');
        expect($result[2])->toBe([18, 30]);
        
        $result = Utils::parseExpression('deleted_at IS NULL');
        expect($result[0])->toBe('deleted_at');
        expect($result[1])->toBe('IS NULL');
        expect($result[2])->toBe(null);
    });

    it(": castValue", function() {
        expect(Utils::castValue('123'))->toBe(123);
        expect(Utils::castValue('123.45'))->toBe(123.45);
        expect(Utils::castValue('true'))->toBe(true);
        expect(Utils::castValue('false'))->toBe(false);
        expect(Utils::castValue('"string"'))->toBe('string');
        expect(Utils::castValue("'string'"))->toBe('string');
        expect(Utils::castValue('not_quoted'))->toBe('not_quoted');
    });
});
