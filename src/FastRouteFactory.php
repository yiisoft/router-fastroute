<?php

namespace Yiisoft\Router\FastRoute;

use FastRoute\DataGenerator\GroupCountBased;
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
            $routeParser,
            static function ($data) {
                return new \FastRoute\Dispatcher\GroupCountBased($data);
            }
        );
    }
}
