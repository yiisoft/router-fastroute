<?php

namespace Yiisoft\Router\FastRoute\Tests;

use PHPUnit\Framework\TestCase;
use Yiisoft\Router\Route;
use Yiisoft\Router\Group;
use Yiisoft\Router\RouteCollectorInterface;
use Yiisoft\Router\RouteNotFoundException;
use Yiisoft\Router\UrlGeneratorInterface;

class UrlGeneratorTest extends TestCase
{
    private function createUrlGenerator(array $routes): UrlGeneratorInterface
    {
        $container = new DummyContainer();
        $factory = new RouteFactory();

        return $factory($routes, $container);
    }

    public function testSimpleRouteShouldBeGenerated(): void
    {
        $routes = [
            Route::get('/home/index')->name('index'),
        ];
        $url = $this->createUrlGenerator($routes)->generate('index');

        $this->assertEquals('/home/index', $url);
    }

    public function testRouteWithoutNameShouldNotBeFound(): void
    {
        $routes = [
            Route::get('/home/index'),
            Route::get('/index'),
            Route::get('index'),
        ];
        $urlGenerator = $this->createUrlGenerator($routes);

        $this->expectException(RouteNotFoundException::class);
        $urlGenerator->generate('index');
    }

    public function testParametersShouldBeSubstituted(): void
    {
        $routes = [
            Route::get('/view/{id:\d+}/{text:~[\w]+}#{tag:\w+}')->name('view'),
        ];
        $url = $this->createUrlGenerator($routes)->generate('view', ['id' => 100, 'tag' => 'yii', 'text' => '~test']);

        $this->assertEquals('/view/100/~test#yii', $url);
    }

    public function testExceptionShouldBeThrownIfParameterPatternDoesntMatch(): void
    {
        $routes = [
            Route::get('/view/{id:\w+}')->name('view'),
        ];
        $urlGenerator = $this->createUrlGenerator($routes);

        $this->expectExceptionMessage('Parameter value for [id] did not match the regex `\w+`');
        $urlGenerator->generate('view', ['id' => null]);
    }

    public function testExceptionShouldBeThrownIfAnyParameterIsMissing(): void
    {
        $routes = [
            Route::get('/view/{id:\d+}/{value}')->name('view'),
        ];
        $urlGenerator = $this->createUrlGenerator($routes);

        $this->expectExceptionMessage('Route `view` expects at least parameter values for [id,value], but received [id]');
        $urlGenerator->generate('view', ['id' => 123]);
    }

    public function testGroupPrefixShouldBeAppended(): void
    {
        $routes = [
            ['/api', static function (RouteCollectorInterface $r) {
                $r->addRoute(Route::get('/post')->name('post/index'));
                $r->addRoute(Route::get('/post/{id}')->name('post/view'));
            }],
        ];
        $urlGenerator = $this->createUrlGenerator($routes);

        $url = $urlGenerator->generate('post/index');
        $this->assertEquals('/api/post', $url);

        $url = $urlGenerator->generate('post/view', ['id' => 42]);
        $this->assertEquals('/api/post/42', $url);
    }

    public function testNestedGroupsPrefixShouldBeAppended(): void
    {
        $routes = [
            Group::create('/api', [
                Group::create('/v1', [
                    Group::create('/blog', [
                        Route::get('/post')->name('api-v1-post/index'),
                        Route::get('/post/{id}')->name('api-v1-post/view'),
                    ]),
                ])
            ])
        ];
        $urlGenerator = $this->createUrlGenerator($routes);

        $url = $urlGenerator->generate('api-v1-post/index');
        $this->assertEquals('/api/v1/blog/post', $url);

        $url = $urlGenerator->generate('api-v1-post/view', ['id' => 42]);
        $this->assertEquals('/api/v1/blog/post/42', $url);
    }

    public function testDefaultSholdNotBeUsedForOptionalParameter(): void
    {
        $routes = [
            Route::get('/[{name}]')
                ->name('defaults')
                ->defaults(['name' => 'default'])
        ];

        $url = $this->createUrlGenerator($routes)->generate('defaults');
        $this->assertEquals('/', $url);
    }

    /**
     * @test
     */
    public function testValueShouldBeUsedForOptionalParameter(): void
    {
        $routes = [
            Route::get('/[{name}]')
                ->name('defaults')
                ->defaults(['name' => 'default'])
        ];

        $url = $this->createUrlGenerator($routes)->generate('defaults', ['name' => 'test']);
        $this->assertEquals('/test', $url);
    }

    public function testDefaultShouldNotBeUsedForRequiredParameter(): void
    {
        $routes = [
            Route::get('/{name}')
                ->name('defaults')
                ->defaults(['name' => 'default'])
        ];

        $this->expectExceptionMessage('Route `defaults` expects at least parameter values for [name], but received []');
        $this->createUrlGenerator($routes)->generate('defaults');
    }
}
