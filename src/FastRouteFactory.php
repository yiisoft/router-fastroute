<?php

namespace Yiisoft\Router\FastRoute;

use FastRoute\DataGenerator\GroupCountBased;
use FastRoute\RouteCollector;
use FastRoute\RouteParser\Std;
use Psr\Container\ContainerInterface;

class FastRouteFactory
{
    public function __invoke(ContainerInterface $container)
    {
        $collector = new RouteCollector(
            new Std(),
            new GroupCountBased()
        );

        $router = new FastRoute(
            $collector,
            function ($data) {
                return new \FastRoute\Dispatcher\GroupCountBased($data);
            }
        );

        return $router;
    }
}
