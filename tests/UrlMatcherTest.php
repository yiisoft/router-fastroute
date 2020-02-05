<?php


namespace Yiisoft\Router\FastRoute\Tests;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Yiisoft\Router\Route;
use Yiisoft\Router\UrlMatcherInterface;

class UrlMatcherTest extends TestCase
{
    private function createUrlMatcher(array $routes): UrlMatcherInterface
    {
        $container = new DummyContainer();
        $factory = new RouteFactory();

        return $factory($routes, $container);
    }

    public function testDefaultsAreInResult(): void
    {
        $routes = [
            Route::get('/[{name}]')->defaults(['name' => 'test']),
        ];

        $urlMatcher = $this->createUrlMatcher($routes);

        $request = new ServerRequest('GET', '/');

        $result = $urlMatcher->match($request);
        $parameters = $result->parameters();

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('name', $parameters);
        $this->assertSame('test', $parameters['name']);
    }
}
