<?php

namespace Yiisoft\Router\FastRoute\Tests;

use PHPUnit\Framework\TestCase;
use Yiisoft\Router\FastRoute\RouteNotFoundException;
use Yiisoft\Router\RouterInterface;

class FastRouteTest extends TestCase
{
    public function testSimpleNamedRoute()
    {
        $routes = [
            \Yiisoft\Router\Route::get('/home/index')->name('index'),
        ];
        $routerCollector = $this->createRouterCollector($routes);

        $url = $routerCollector->generate('index');

        $this->assertEquals('/home/index', $url);
    }

    public function testRouteWithoutNameNotFound()
    {
        $routes = [
            \Yiisoft\Router\Route::get('/home/index'),
            \Yiisoft\Router\Route::get('/index'),
            \Yiisoft\Router\Route::get('index'),
        ];
        $routerCollector = $this->createRouterCollector($routes);

        $this->expectException(RouteNotFoundException::class);
        $routerCollector->generate('index');
    }

    public function testRouteWithParameters()
    {
        $routes = [
            \Yiisoft\Router\Route::get('/view/{id:\d+}#{tag:\w+}')->name('view'),
        ];
        $routerCollector = $this->createRouterCollector($routes);

        $url = $routerCollector->generate('view', ['id' => 100, 'tag' => 'yii']);

        $this->assertEquals('/view/100#yii', $url);
    }

    public function testRouteWithoutParameters()
    {
        $routes = [
            \Yiisoft\Router\Route::get('/view/{id:\d+}#{tag:\w+}')->name('view'),
        ];
        $routerCollector = $this->createRouterCollector($routes);

        $this->expectException(\RuntimeException::class);
        $routerCollector->generate('view');
    }

    /**
     * @param array $routes
     * @return \Yiisoft\Router\RouterInterface
     */
    private function createRouterCollector(array $routes): RouterInterface
    {
        $container = new DummyContainer();
        $factory = new RouteFactory();

        return $factory($routes, $container);
    }
}