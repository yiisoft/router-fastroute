<?php

namespace Yiisoft\Router\FastRoute\Tests;

use Psr\Container\ContainerInterface;
use Yiisoft\Router\FastRoute\FastRouteFactory;
use Yiisoft\Router\RouterFactory;

class RouteFactory
{
    public function __invoke(array $routes, ContainerInterface $container)
    {
        return (new RouterFactory(new FastRouteFactory(), $routes))($container);
    }
}