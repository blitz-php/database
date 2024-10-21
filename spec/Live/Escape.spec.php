<?php

use BlitzPHP\Database\Database;
use BlitzPHP\Utilities\Date;

use function Kahlan\expect;

xdescribe("Live / Escape", function() {

    beforeAll(function() {
        $this->db   = Database::connection([
            'driver'    => 'sqlite',
            'port'      => 3306,
            'host'      => '127.0.0.1',
            'username'  => '',
            'password'  => '',
            'database'  => ':memory:',
            'debug'     => true,
            'charset'   => 'utf8',
            'collation' => '',
            'prefix'    => 'db_',         // Needed to ensure we're working correctly with prefixes live. DO NOT REMOVE FOR BLITZ DEVS
            'options'   => [
                'column_case'  => 'inherit',
                'enable_stats' => false,
                'enable_cache' => true,
            ],
        ], 'test');

        $this->char = str_contains($this->db->driver, 'mysql') ? '\\' : "'";
        $this->db->escapeChar = $this->char;
    });

    it("N'echappe pas les nombre negatif", function() {
        expect($this->db->escape(-100))->toBe(-100);
    });
    
    it("Echappe normalement", function() {
        $expected = "SELECT * FROM brands WHERE name = 'O" . $this->char . "'Doules'";
        $sql      = 'SELECT * FROM brands WHERE name = ' . $this->db->escape("O'Doules");

        expect($sql)->toBe($expected);
    });
    
    it("Echappe les objets Stringable", function() {
        $expected = "SELECT * FROM brands WHERE name = '2024-01-01 12:00:00'";
        $sql      = 'SELECT * FROM brands WHERE name = ' . $this->db->escape(new Date('2024-01-01 12:00:00'));
 
        expect($sql)->toBe($expected);
    });
    
    it("Echappe les chaine de caracteres", function() {
        $expected = "SELECT * FROM brands WHERE name = 'O" . $this->char . "'Doules'";
        $sql      = "SELECT * FROM brands WHERE name = '" . $this->db->escapeString("O'Doules") . "'";

        expect($sql)->toBe($expected);
    });

    it("Echappe les chaine de caractere Stringable", function() {
        $expected = "SELECT * FROM brands WHERE name = '2024-01-01 12:00:00'";
        $sql      = "SELECT * FROM brands WHERE name = '"
            . $this->db->escapeString(new Date('2024-01-01 12:00:00')) . "'";

        expect($sql)->toBe($expected);
    });

    it("Echappe la clause Like", function() {
        $expected = "SELECT * FROM brands WHERE column LIKE '%10!% more%' ESCAPE '!'";
        $sql      = "SELECT * FROM brands WHERE column LIKE '%" . $this->db->escapeLikeString('10% more') . "%' ESCAPE '!'";

        expect($sql)->toBe($expected);
    });
    
    it("Echappe la clause Like ayant un Stringable", function() {
        $expected = "SELECT * FROM brands WHERE column LIKE '%2024-01-01 12:00:00%' ESCAPE '!'";
        $sql      = "SELECT * FROM brands WHERE column LIKE '%"
            . $this->db->escapeLikeString(new Date('2024-01-01 12:00:00')) . "%' ESCAPE '!'";

        expect($sql)->toBe($expected);
    });

    it("Echappe les tableau", function() {
        $stringArray = [' A simple string ', false, null];

        $escapedString = $this->db->escape($stringArray);

        expect("' A simple string '")->toBe($escapedString[0]);

        if (str_contains($this->db->driver, 'postgre')) {
            expect('FALSE')->toBe($escapedString[1]);
        } else {
            expect(0)->toBe($escapedString[1]);
        }

        expect('NULL')->toBe($escapedString[2]);
    });
});
