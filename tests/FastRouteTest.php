<?php

namespace Yiisoft\Router\FastRoute\Tests;

use PHPUnit\Framework\TestCase;
use Yiisoft\Router\Route;
use Yiisoft\Router\RouteNotFoundException;
use Yiisoft\Router\RouterInterface;

class FastRouteTest extends TestCase
{
    public function testSimpleNamedRoute(): void
    {
        $routes = [
            Route::get('/home/index')->name('index'),
        ];
        $routerCollector = $this->createRouterCollector($routes);

        $url = $routerCollector->generate('index');

        $this->assertEquals('/home/index', $url);
    }

    public function testRouteWithoutNameNotFound(): void
    {
        $routes = [
            Route::get('/home/index'),
            Route::get('/index'),
            Route::get('index'),
        ];
        $routerCollector = $this->createRouterCollector($routes);

        $this->expectException(RouteNotFoundException::class);
        $routerCollector->generate('index');
    }

    public function testRouteWithParameters(): void
    {
        $routes = [
            Route::get('/view/{id:\d+}#{tag:\w+}')->name('view'),
        ];
        $routerCollector = $this->createRouterCollector($routes);

        $url = $routerCollector->generate('view', ['id' => 100, 'tag' => 'yii']);

        $this->assertEquals('/view/100#yii', $url);
    }

    public function testRouteWithoutParameters(): void
    {
        $routes = [
            Route::get('/view/{id:\d+}#{tag:\w+}')->name('view'),
        ];
        $routerCollector = $this->createRouterCollector($routes);

        $this->expectException(\RuntimeException::class);
        $routerCollector->generate('view');
    }

    /**
     * @param array $routes
     * @return RouterInterface
     */
    private function createRouterCollector(array $routes): RouterInterface
    {
        $container = new DummyContainer();
        $factory = new RouteFactory();

        return $factory($routes, $container);
    }
}
