<?php

/**
 * This file is part of Blitz PHP framework - Database Layer.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Database\Events;

use BlitzPHP\Contracts\Event\EventInterface;
use BlitzPHP\Contracts\Event\EventListenerInterface;
use BlitzPHP\Contracts\Event\EventManagerInterface;
use BlitzPHP\Database\Collectors\DatabaseCollector;

class Listener implements EventListenerInterface
{
    public function listen(EventManagerInterface $event): void
    {
        $event->attach('db:result', static function (EventInterface $eventInterface) {
            call_user_func([DatabaseCollector::class, 'collect'], $eventInterface);
        });
    }
}
