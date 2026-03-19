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

use BlitzPHP\Contracts\Database\ConnectionInterface;
use BlitzPHP\Contracts\Database\ConnectionResolverInterface;
use BlitzPHP\Contracts\Event\EventInterface;
use BlitzPHP\Contracts\Event\EventListenerInterface;
use BlitzPHP\Contracts\Event\EventManagerInterface;
use BlitzPHP\Database\Collectors\DatabaseCollector;
use BlitzPHP\Exceptions\LoadException;
use BlitzPHP\Loader\FileLocator;
use BlitzPHP\Loader\Load;

class DatabaseListener implements EventListenerInterface
{
    public function listen(EventManagerInterface $event): void
    {
        $event->on('db:result', static function (EventInterface $eventInterface) {
            call_user_func([DatabaseCollector::class, 'collect'], $eventInterface);
        });

        $event->on('app:init', function () {
            $this->addInfoToAboutCommand();
            $this->extendsFramework();
        });
    }

    private function addInfoToAboutCommand()
    {
        if (! class_exists(\BlitzPHP\Cli\Commands\Config\About::class)) {
            return;
        }

        \BlitzPHP\Cli\Commands\Config\About::add('Gestionnaires', static fn (ConnectionResolverInterface $connectionResolver) => array_filter([
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

    private function extendsFramework()
    {
        FileLocator::macro('model', function(string $model, ?ConnectionInterface $connection = null) {
            if (! class_exists($model) && ! str_ends_with($model, 'Model')) {
                $model .= 'Model';
            }

            if (! class_exists($model)) {
                $model = str_replace(APP_NAMESPACE . '\\Models\\', '', $model);
                $model = APP_NAMESPACE . '\\Models\\' . $model;
            }

            if (! class_exists($model)) {
                throw LoadException::modelNotFound($model);
            }

            return service('container')->make($model, ['db' => $connection]);
        });

        Load::macro('model', function(array|string $model, ?ConnectionInterface $connection = null) {
            if ($model === '' || $model === '0' || $model === []) {
                throw new LoadException('Veuillez specifier le modele à charger');
            }

            $models  = is_array($model) ? $model : [$model];
            $results = [];

            foreach ($models as $model) {
                if (null === $result = self::getLoaded('models', $model)) {
                   $result =  FileLocator::model($model, $connection);
                   self::loaded('models', $model, $result);
                }

                $results[] = $result;
            }
            
            return count($results) === 1 ? $results[0] : $results;
        });
    }
}
