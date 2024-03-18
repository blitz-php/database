<?php

/**
 * This file is part of Blitz PHP framework - Database Layer.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Database\Validation\Rules;

use BlitzPHP\Database\Validation\DatabaseRule;
use BlitzPHP\Validation\Rules\AbstractRule;

class Exists extends AbstractRule
{
    use DatabaseRule;

    protected $message        = ':attribute :value do not exist';
    protected $fillableParams = ['table', 'column'];

    public function check($value): bool
    {
        $this->requireParameters(['table']);

        $column = $this->parameter('column') ?: $this->getAttribute()->getKey();

        $builder = $this->makeBuilder($this->parameter('table'), $column, $value);

        return $builder->count() > 0;
    }
}
