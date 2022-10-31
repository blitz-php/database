<?php

/**
 * This file is part of Blitz PHP framework - Database Layer.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Database;

class Utils
{
    /**
     * Compte les dimensions de *tous* les éléments du tableau. Utile pour trouver le nombre
     * maximal de dimensions dans un tableau mixte.
     *
     * @param array $data Tableau sur lequel compter les dimensions
     *
     * @return int Le nombre maximum de dimensions dans $data
     *
     * @credit CakePHP - http://book.cakephp.org/2.0/en/core-utility-libraries/hash.html#Hash::maxDimensions
     */
    public static function maxDimensions(array $data): int
    {
        $depth = [];
        if (is_array($data) && reset($data) !== false) {
            foreach ($data as $value) {
                $depth[] = self::dimensions((array) $value) + 1;
            }
        }

        return max($depth);
    }

    /**
     * Compte les dimensions d'un tableau.
     * Ne considère que la dimension du premier élément du tableau.
     *
     * Si vous avez un tableau inégal ou hétérogène, pensez à utiliser Arr::maxDimensions()
     * pour obtenir les dimensions du tableau.
     *
     * @param array $data Tableau sur lequel compter les dimensions
     *
     * @return int Le nombre de dimensions dans $data
     *
     * @credit CakePHP - http://book.cakephp.org/2.0/en/core-utility-libraries/hash.html#Hash::dimensions
     */
    public static function dimensions(array $data): int
    {
        if (empty($data)) {
            return 0;
        }
        reset($data);
        $depth = 1;

        while ($elem = array_shift($data)) {
            if (is_array($elem)) {
                $depth++;
                $data = &$elem;
            } else {
                break;
            }
        }

        return $depth;
    }

    /**
     * Détermine si une chaîne donnée commence par une sous-chaîne donnée.
     *
     * @param array|string $needles
     */
    public static function strStartsWith(string $haystack, $needles): bool
    {
        foreach ((array) $needles as $needle) {
            if ($needle !== '' && substr($haystack, 0, strlen($needle)) === (string) $needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * Supprimer les caractères invisibles
     *
     * Cela empêche de prendre en sandwich des caractères nuls
     * entre les caractères ascii, comme Java\0script.
     */
    public static function removeInvisibleCharacters(string $str, bool $url_encoded = true): string
    {
        $non_displayables = [];

        if ($url_encoded) {
            $non_displayables[] = '/%0[0-8bcef]/i';	// url encoded 00-08, 11, 12, 14, 15
            $non_displayables[] = '/%1[0-9a-f]/i';	// url encoded 16-31
            $non_displayables[] = '/%7f/i';	// url encoded 127
        }

        $non_displayables[] = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S';	// 00-08, 11, 12, 14-31, 127

        do {
            $str = preg_replace($non_displayables, '', $str, -1, $count);
        } while ($count);

        return $str;
    }
}
