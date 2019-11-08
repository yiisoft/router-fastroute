<?php

namespace Yiisoft\Router\FastRoute;

use FastRoute\DataGenerator\GroupCountBased;
use FastRoute\RouteCollector;
use FastRoute\RouteParser\Std;

class FastRouteFactory
{
    public function __invoke()
    {
        // TODO may it be used via di?
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
