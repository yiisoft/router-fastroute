<?php

namespace Yiisoft\Router\FastRoute\Tests;

use PHPUnit\Framework\TestCase;
use Yiisoft\Router\Route;
use Yiisoft\Router\RouteCollectorInterface;
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
            Route::get('/view/{id:\d+}/{text:~[\w]+}#{tag:\w+}')->name('view'),
        ];
        $routerCollector = $this->createRouterCollector($routes);

        $url = $routerCollector->generate('view', ['id' => 100, 'tag' => 'yii', 'text' => '~test']);

        $this->assertEquals('/view/100/~test#yii', $url);
    }

    public function testParamPattern(): void
    {
        $routes = [
            Route::get('/view/{id:\w+}')->name('view'),
        ];
        $routerCollector = $this->createRouterCollector($routes);

        $this->expectExceptionMessage('Parameter value for [id] did not match the regex `\w+`');
        $routerCollector->generate('view', ['id' => null]);
    }

    public function testMissedParams(): void
    {
        $routes = [
            Route::get('/view/{id:\w+}')->name('view'),
        ];
        $routerCollector = $this->createRouterCollector($routes);

        $this->expectExceptionMessage('Route `view` expects at least parameter values for [id], but received []');
        $routerCollector->generate('view');
    }

    public function testMissedParam(): void
    {
        $routes = [
            Route::get('/view/{id:\d+}/{value}')->name('view'),
        ];
        $routerCollector = $this->createRouterCollector($routes);

        $this->expectExceptionMessage('Route `view` expects at least parameter values for [id,value], but received [id]');
        $routerCollector->generate('view', ['id' => 123]);
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

    public function testGroup(): void
    {
        $routes = [
            Route::get('/home/index')->name('index'),
            ['/api', static function (RouteCollectorInterface $r) {
                $r->addRoute(Route::get('/post')->name('post/index'));
                $r->addRoute(Route::get('/post/{id}')->name('post/view'));
            }],
        ];
        $routerCollector = $this->createRouterCollector($routes);

        $url = $routerCollector->generate('index');
        $this->assertEquals('/home/index', $url);

        $url = $routerCollector->generate('post/index');
        $this->assertEquals('/api/post', $url);

        $url = $routerCollector->generate('post/view', ['id' => 42]);
        $this->assertEquals('/api/post/42', $url);
    }
}
