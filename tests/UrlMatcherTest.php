<?php

declare(strict_types=1);

namespace Yiisoft\Router\FastRoute\Tests;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use Yiisoft\Router\FastRoute\UrlMatcher;
use Yiisoft\Router\Group;
use Yiisoft\Router\Route;
use Yiisoft\Router\RouteCollection;
use Yiisoft\Router\UrlMatcherInterface;

final class UrlMatcherTest extends TestCase
{
    private function createUrlMatcher(array $routes, CacheInterface $cache = null): UrlMatcherInterface
    {
        $collector = Group::create();
        $rootGroup = Group::create(null, $routes);
        $collector->addGroup($rootGroup);
        return new UrlMatcher(new RouteCollection($collector), $cache, ['cache_key' => 'route-cache']);
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

    public function testSimpleRoute(): void
    {
        $routes = [
            Route::get('/site/index'),
        ];

        $urlMatcher = $this->createUrlMatcher($routes);

        $request = new ServerRequest('GET', '/site/index');

        $result = $urlMatcher->match($request);
        $this->assertTrue($result->isSuccess());
    }

    public function testSimpleRouteWithDifferentMethods(): void
    {
        $routes = [
            Route::methods(['GET', 'POST'], '/site/index'),
        ];

        $urlMatcher = $this->createUrlMatcher($routes);

        $request1 = new ServerRequest('GET', '/site/index');
        $request2 = new ServerRequest('POST', '/site/index');

        $result1 = $urlMatcher->match($request1);
        $result2 = $urlMatcher->match($request2);
        $this->assertTrue($result1->isSuccess());
        $this->assertTrue($result2->isSuccess());
    }

    public function testSimpleRouteWithParam(): void
    {
        $routes = [
            Route::get('/site/post/{id}'),
        ];

        $urlMatcher = $this->createUrlMatcher($routes);

        $request = new ServerRequest('GET', '/site/post/23');

        $result = $urlMatcher->match($request);
        $parameters = $result->parameters();

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('id', $parameters);
        $this->assertSame('23', $parameters['id']);
    }

    public function testSimpleRouteWithHostSuccess(): void
    {
        $routes = [
            Route::get('/site/index')->host('yii.test'),
            Route::get('/site/index')->host('{user}.yiiframework.com'),
        ];

        $urlMatcher = $this->createUrlMatcher($routes);

        $request = new ServerRequest('GET', '/site/index');
        $request1 = $request->withUri($request->getUri()->withHost('yii.test'));
        $request2 = $request->withUri($request->getUri()->withHost('rustamwin.yiiframework.com'));

        $result1 = $urlMatcher->match($request1);
        $result2 = $urlMatcher->match($request2);

        $this->assertTrue($result1->isSuccess());

        $this->assertArrayHasKey('user', $result2->parameters());
        $this->assertSame('rustamwin', $result2->parameters()['user']);
        $this->assertTrue($result2->isSuccess());
    }

    public function testSimpleRouteWithHostFailed(): void
    {
        $routes = [
            Route::get('/site/index')->host('yii.test'),
            Route::get('/site/index')->host('yiiframework.{zone:ru|com}'),
        ];

        $urlMatcher = $this->createUrlMatcher($routes);

        $request = new ServerRequest('GET', '/site/index');
        $request1 = $request->withUri($request->getUri()->withHost('yee.test'));
        $request2 = $request->withUri($request->getUri()->withHost('yiiframework.uz'));

        $result1 = $urlMatcher->match($request1);
        $result2 = $urlMatcher->match($request2);

        $this->assertFalse($result1->isSuccess());
        $this->assertFalse($result1->isMethodFailure());

        $this->assertFalse($result2->isSuccess());
        $this->assertFalse($result2->isMethodFailure());
    }

    public function testSimpleRouteWithOptionalPartSuccess(): void
    {
        $routes = [
            Route::get('/site/post[/view]'),
        ];

        $urlMatcher = $this->createUrlMatcher($routes);

        $request1 = new ServerRequest('GET', '/site/post/view');
        $request2 = new ServerRequest('GET', '/site/post');

        $result1 = $urlMatcher->match($request1);
        $result2 = $urlMatcher->match($request2);

        $this->assertTrue($result1->isSuccess());
        $this->assertTrue($result2->isSuccess());
    }

    public function testSimpleRouteWithOptionalPartFailed(): void
    {
        $routes = [
            Route::get('/site/post[/view]'),
        ];

        $urlMatcher = $this->createUrlMatcher($routes);

        $request = new ServerRequest('GET', '/site/post/index');

        $result = $urlMatcher->match($request);

        $this->assertFalse($result->isSuccess());
    }

    public function testSimpleRouteWithOptionalParam(): void
    {
        $routes = [
            Route::get('/site/post[/{id}]'),
        ];

        $urlMatcher = $this->createUrlMatcher($routes);

        $request1 = new ServerRequest('GET', '/site/post/23');
        $request2 = new ServerRequest('GET', '/site/post');

        $result1 = $urlMatcher->match($request1);
        $parameters1 = $result1->parameters();
        $result2 = $urlMatcher->match($request2);
        $parameters2 = $result2->parameters();

        $this->assertTrue($result1->isSuccess());
        $this->assertArrayHasKey('id', $parameters1);
        $this->assertSame('23', $parameters1['id']);
        $this->assertTrue($result2->isSuccess());
        $this->assertArrayNotHasKey('id', $parameters2);
    }

    public function testSimpleRouteWithNestedOptionalParts(): void
    {
        $routes = [
            Route::get('/site[/post[/view]]'),
        ];

        $urlMatcher = $this->createUrlMatcher($routes);

        $request1 = new ServerRequest('GET', '/site/post/view');
        $request2 = new ServerRequest('GET', '/site/post');
        $request3 = new ServerRequest('GET', '/site');

        $result1 = $urlMatcher->match($request1);
        $result2 = $urlMatcher->match($request2);
        $result3 = $urlMatcher->match($request3);

        $this->assertTrue($result1->isSuccess());
        $this->assertTrue($result2->isSuccess());
        $this->assertTrue($result3->isSuccess());
    }

    public function testSimpleRouteWithNestedOptionalParamsSuccess(): void
    {
        $routes = [
            Route::get('/site[/{name}[/{id}]]'),
        ];

        $urlMatcher = $this->createUrlMatcher($routes);

        $request1 = new ServerRequest('GET', '/site/post/23');
        $request2 = new ServerRequest('GET', '/site/post');
        $request3 = new ServerRequest('GET', '/site');

        $result1 = $urlMatcher->match($request1);
        $parameters1 = $result1->parameters();
        $result2 = $urlMatcher->match($request2);
        $parameters2 = $result2->parameters();
        $result3 = $urlMatcher->match($request3);
        $parameters3 = $result3->parameters();

        $this->assertTrue($result1->isSuccess());
        $this->assertArrayHasKey('id', $parameters1);
        $this->assertArrayHasKey('name', $parameters1);
        $this->assertSame('23', $parameters1['id']);
        $this->assertSame('post', $parameters1['name']);
        $this->assertTrue($result2->isSuccess());
        $this->assertArrayHasKey('name', $parameters2);
        $this->assertSame('post', $parameters2['name']);
        $this->assertTrue($result3->isSuccess());
        $this->assertArrayNotHasKey('id', $parameters3);
        $this->assertArrayNotHasKey('name', $parameters3);
    }

    public function testDisallowedMethod(): void
    {
        $routes = [
            Route::get('/site/index'),
        ];

        $urlMatcher = $this->createUrlMatcher($routes);

        $request = new ServerRequest('POST', '/site/index');

        $result = $urlMatcher->match($request);
        $this->assertFalse($result->isSuccess());
        $this->assertTrue($result->isMethodFailure());
        $this->assertSame(['GET'], $result->methods());
    }

    public function testDisallowedHEADMethod(): void
    {
        $routes = [
            Route::post('/site/post/view'),
            Route::get('/site/index'),
            Route::post('/site/index'),
        ];

        $urlMatcher = $this->createUrlMatcher($routes);

        $request = new ServerRequest('HEAD', '/site/index');

        $result = $urlMatcher->match($request);
        $this->assertFalse($result->isSuccess());
        $this->assertTrue($result->isMethodFailure());
        $this->assertSame(['GET', 'POST'], $result->methods());
    }

    public function testGetCurrentRoute(): void
    {
        $routes = [
            Route::get('/site/index')->name('request1'),
            Route::post('/site/index')->name('request2'),
        ];

        $urlMatcher = $this->createUrlMatcher($routes);

        $request = new ServerRequest('GET', '/site/index');

        $urlMatcher->match($request);
        $this->assertSame($routes[0]->getName(), $urlMatcher->getCurrentRoute()->getName());
    }

    public function testGetLastMatchedRequest(): void
    {
        $routes = [
            Route::get('/site/index')->name('request1'),
            Route::post('/site/index')->name('request2'),
        ];

        $urlMatcher = $this->createUrlMatcher($routes);

        $request = new ServerRequest('GET', '/site/index');

        $urlMatcher->match($request);
        $this->assertSame($request, $urlMatcher->getLastMatchedRequest());
    }

    public function testNoCache(): void
    {
        $routes = [
            Route::get('/')
                ->name('site/index'),
            Route::methods(['GET', 'POST'], '/contact')
                ->name('site/contact'),
        ];

        $request = new ServerRequest('GET', '/contact');

        $cache = $this->createMock(CacheInterface::class);
        $cache->method('has')
            ->willReturn(false);
        $matcher = $this->createUrlMatcher($routes, $cache);
        $result = $matcher->match($request);
        $this->assertTrue($result->isSuccess());
    }

    public function testHasCache(): void
    {
        $routes = [
            Route::get('/')
                ->name('site/index'),
            Route::methods(['GET', 'POST'], '/contact')
                ->name('site/contact'),
        ];

        $cacheArray = [
            0 => [
                'GET' => [
                    '/' => 'site/index',
                    '/contact' => 'site/contact',
                ],
                'POST' => [
                    '/contact' => 'site/contact',
                ],
            ],
            1 => []
        ];

        $request = new ServerRequest('GET', '/contact');

        $cache = $this->createMock(CacheInterface::class);
        $cache->method('has')
            ->willReturn(true);
        $cache->method('get')
            ->willReturn($cacheArray);
        $matcher = $this->createUrlMatcher($routes, $cache);
        $result = $matcher->match($request);
        $this->assertTrue($result->isSuccess());
    }

    public function testCacheError(): void
    {
        $routes = [
            Route::get('/')
                ->name('site/index'),
            Route::methods(['GET', 'POST'], '/contact')
                ->name('site/contact'),
        ];

        $request = new ServerRequest('GET', '/contact');

        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')
            ->will($this->throwException(new \RuntimeException()));
        $matcher = $this->createUrlMatcher($routes, $cache);
        $result = $matcher->match($request);
        $this->assertTrue($result->isSuccess());
    }
}
