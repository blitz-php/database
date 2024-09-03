<?php

/**
 * This file is part of Blitz PHP framework - Database Layer.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Database\Listeners;

use BlitzPHP\Contracts\Database\ConnectionResolverInterface;
use BlitzPHP\Contracts\Event\EventInterface;
use BlitzPHP\Contracts\Event\EventListenerInterface;
use BlitzPHP\Contracts\Event\EventManagerInterface;
use BlitzPHP\Database\Collectors\DatabaseCollector;

class DatabaseListener implements EventListenerInterface
{
    public function listen(EventManagerInterface $event): void
    {
        $event->on('db:result', static function (EventInterface $eventInterface) {
            call_user_func([DatabaseCollector::class, 'collect'], $eventInterface);
        });

        $event->on('app:init', function () {
            $this->addInfoToAboutCommand();
        });
    }

    private function addInfoToAboutCommand()
    {
        if (! class_exists(\BlitzPHP\Cli\Commands\Utilities\About::class)) {
            return;
        }

        \BlitzPHP\Cli\Commands\Utilities\About::add('Gestionnaires', static fn (ConnectionResolverInterface $connectionResolver) => array_filter([
            'Base de données' => static function () use ($connectionResolver) {
                [$group, $config] = $connectionResolver->connectionInfo();

                if (empty($group)) {
                    return null;
                }

                if (empty($config) || ! is_array($config)) {
                    return $group;
                }

                $output = str_ireplace('pdo', '', $config['driver']) . '/' . $config['host'];

                if (! empty($config['port'])) {
                    $output .= ':' . $config['port'];
                }
                if (! empty($config['username'])) {
                    $output .= '@' . $config['username'];
                }
                if (! empty($config['database'])) {
                    $output .= '/' . $config['database'];
                }

                return $group . ' [' . $output . ']';
            },
        ]));
    }
}
