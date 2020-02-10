<?php

namespace Yiisoft\Router\FastRoute;

use FastRoute\DataGenerator\GroupCountBased;
use FastRoute\Dispatcher\GroupCountBased as GroupCountBasedDispatcher;
use FastRoute\RouteCollector;
use FastRoute\RouteParser\Std;

class FastRouteFactory
{
    public function __invoke()
    {
        $routeParser = new Std();
        $collector = new RouteCollector(
            $routeParser,
            new GroupCountBased()
        );

        return new FastRoute(
            $collector,
            static function ($data) {
                return new GroupCountBasedDispatcher($data);
            }
        );
    }
}
