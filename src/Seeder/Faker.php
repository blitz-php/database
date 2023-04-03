<?php

/**
 * This file is part of Blitz PHP framework - Database Layer.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Database\Seeder;

use DateTime;

/**
 * Generator
 *
 * @credit <a href="https://github.com/tebazil/db-seeder">tebazil/db-seeder</a>
 *
 * @property string   $address
 * @property string   $amPm
 * @property string   $bankAccountNumber
 * @property string   $buildingNumber
 * @property int      $century
 * @property string   $chrome
 * @property string   $city
 * @property string   $citySuffix
 * @property string   $colorName
 * @property string   $company
 * @property string   $companyEmail
 * @property string   $companySuffix
 * @property string   $country
 * @property string   $countryCode
 * @property string   $countryISOAlpha3
 * @property string   $creditCardDetails
 * @property DateTime $creditCardExpirationDate
 * @property string   $creditCardExpirationDateString
 * @property string   $creditCardNumber
 * @property string   $creditCardType
 * @property string   $currencyCode
 * @property DateTime $dateTime
 * @property DateTime $dateTimeAD
 * @property DateTime $dateTimeThisCentury
 * @property DateTime $dateTimeThisDecade
 * @property DateTime $dateTimeThisMonth
 * @property DateTime $dateTimeThisYear
 * @property int      $dayOfMonth
 * @property int      $dayOfWeek
 * @property string   $domainName
 * @property string   $domainWord
 * @property string   $ean13
 * @property string   $ean8
 * @property string   $email
 * @property string   $fileExtension
 * @property string   $firefox
 * @property string   $firstName
 * @property string   $firstNameFemale
 * @method   array    shuffleArray(array $array = array())
 * @property string   $firstNameMale
 * @method   string   asciify($string = '****')
 * @property string   $freeEmail
 * @method   int      biasedNumberBetween($min = 0, $max = 100, $function = 'sqrt')
 * @property string   $freeEmailDomain
 * @method   bool     boolean($chanceOfGettingTrue = 50)
 * @method   string   bothify($string = '## ??')
 *
 * @property string       $hexColor
 * @property string       $internetExplorer
 * @property string       $ipv4
 * @property string       $ipv6
 * @property string       $isbn10
 * @property string       $isbn13
 * @property string       $iso8601
 * @property string       $languageCode
 * @method   string       creditCardNumber($type = null, $formatted = false, $separator = '-')
 * @property string       $lastName
 * @property float        $latitude
 * @property string       $linuxPlatformToken
 * @property string       $linuxProcessor
 * @property string       $locale
 * @method   string       date($format = 'Y-m-d', $max = 'now')
 * @property string       $localIpv4
 * @property float        $longitude
 * @property string       $macAddress
 * @property string       $macPlatformToken
 * @property string       $macProcessor
 * @property string       $md5
 * @property string       $mimeType
 * @property int          $month
 * @property string       $monthName
 * @property string       $name
 * @property string       $opera
 * @property string       $paragraph
 * @property array|string $paragraphs
 * @property string       $password
 * @property string       $phoneNumber
 * @property string       $postcode
 * @property string       $randomAscii
 * @property int          $randomDigit
 * @property int          $randomDigitNotNull
 * @property string       $randomLetter
 * @method   DateTime     dateTimeBetween($startDate = '-30 years', $endDate = 'now')
 * @method   string       file($sourceDirectory = '/tmp', $targetDirectory = '/tmp', $fullPath = true)
 * @method   string       image($dir = null, $width = 640, $height = 480, $category = null, $fullPath = true)
 *
 * @property string $rgbColor
 * @property array  $rgbColorAsArray
 * @property string $rgbCssColor
 * @property string $safari
 * @property string $safeColorName
 * @property string $safeEmail
 * @property string $safeEmailDomain
 * @property string $safeHexColor
 * @method   string imageUrl($width = 640, $height = 480, $category = null, $randomize = true)
 *
 * @property string       $sentence
 * @property array|string $sentences
 * @property string       $sha1
 * @property string       $sha256
 * @method   string       lexify($string = '????')
 * @method   int          numberBetween($min = 0, $max = 2147483647)
 * @method   string       numerify($string = '###')
 * @method   string       paragraph($nbSentences = 3, $variableNbSentences = true)
 * @method   array|string paragraphs($nb = 3, $asText = false)
 * @method   string       password($minLength = 6, $maxLength = 20)
 * @method   float        randomFloat($nbMaxDecimals = null, $min = 0, $max = null)
 * @method   int          randomNumber($nbDigits = null, $strict = false)
 * @method   string       realText($maxNbChars = 200, $indexSize = 2)
 * @method   string       regexify($regex = '')
 * @method   string       sentence($nbWords = 6, $variableNbWords = true)
 * @method   array|string sentences($nb = 3, $asText = false)
 * @method   array|string shuffle($arg = '')
 * @method   string       shuffleString($string = '', $encoding = 'UTF-8')
 * @method   string       slug($nbWords = 6, $variableNbWords = true)
 * @method   string       text($maxNbChars = 200)
 * @method   string       time($format = 'H:i:s', $max = 'now')
 *
 * @property string       $slug
 * @property string       $streetAddress
 * @property string       $streetName
 * @property string       $streetSuffix
 * @property string       $swiftBicNumber
 * @property string       $text
 * @property string       $timezone
 * @property string       $title
 * @property string       $titleFemale
 * @property string       $titleMale
 * @property string       $tld
 * @property int          $unixTime
 * @property string       $url
 * @property string       $userAgent
 * @method   string       toLower($string = '')
 * @method   string       toUpper($string = '')
 * @method   array|string words($nb = 3, $asText = false)
 *
 * @property string       $userName
 * @property string       $uuid
 * @property string       $vat
 * @property string       $windowsPlatformToken
 * @property string       $word
 * @property array|string $words
 * @property int          $year
 */
class Faker
{
    public const OPTIONAL         = 'optional';
    public const UNIQUE           = 'unique';
    public const VALID            = 'valid';
    private const DEFAULT_OPTIONS = [
        self::OPTIONAL => false,
        self::UNIQUE   => false,
        self::VALID    => false,
    ];

    private array $options = self::DEFAULT_OPTIONS;

    public function __get(string $property)
    {
        return $this->retrive($property);
    }

    public function __call(string $method, array $arguments)
    {
        return $this->retrive($method, $arguments);
    }

    /**
     * Specifie qu'on souhaite avoir des données optionelles
     */
    public function optional(float $weight = 0.5, mixed $default = null): self
    {
        $this->options[self::OPTIONAL] = func_get_args();

        return $this;
    }

    /**
     * Specifie qu'on souhaite avoir des donnees uniques
     */
    public function unique(bool $reset = false, int $maxRetries = 10000): self
    {
        $this->options[self::UNIQUE] = func_get_args();

        return $this;
    }

    /**
     * Specifie qu'on veut les donnees valide
     *
     * @param mixed $validator
     */
    public function valid($validator, int $maxRetries = 10000): self
    {
        $this->options[self::VALID] = func_get_args();

        return $this;
    }

    /**
     * Facade de recuperation les valeurs de configuration de fakerphp
     */
    private function retrive(string $name, ?array $arguments = null): mixed
    {
        $return = [Generator::FAKER, $name, $arguments, $this->options];
        $this->optionsReset();

        return $return;
    }

    /**
     * Reinitialise les options du generateur
     */
    private function optionsReset()
    {
        $this->options = self::DEFAULT_OPTIONS;
    }
}
