<?php

declare(strict_types=1);

namespace Yiisoft\Router\FastRoute\Tests;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\CacheInterface;
use RuntimeException;
use Yiisoft\Router\FastRoute\UrlMatcher;
use Yiisoft\Router\Group;
use Yiisoft\Router\Route;
use Yiisoft\Router\RouteCollection;
use Yiisoft\Router\RouteCollector;
use Yiisoft\Router\UrlMatcherInterface;

final class UrlMatcherTest extends TestCase
{
    public function testDefaultsAreInResult(): void
    {
        $routes = [
            Route::get('/[{name}]')->action(fn () => 1)->defaults(['name' => 'test']),
        ];

        $urlMatcher = $this->createUrlMatcher($routes);

        $request = new ServerRequest('GET', '/');

        $result = $urlMatcher->match($request);
        $arguments = $result->arguments();

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('name', $arguments);
        $this->assertSame('test', $arguments['name']);
    }

    public function testSimpleRoute(): void
    {
        $routes = [
            Route::get('/site/index')->action(fn () => 1),
        ];

        $urlMatcher = $this->createUrlMatcher($routes);

        $request = new ServerRequest('GET', '/site/index');

        $result = $urlMatcher->match($request);
        $this->assertTrue($result->isSuccess());
    }

    public function testSimpleRouteWithDifferentMethods(): void
    {
        $routes = [
            Route::methods(['GET', 'POST'], '/site/index')->action(fn () => 1),
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
            Route::get('/site/post/{id}')->action(fn () => 1),
        ];

        $urlMatcher = $this->createUrlMatcher($routes);

        $request = new ServerRequest('GET', '/site/post/23');

        $result = $urlMatcher->match($request);
        $arguments = $result->arguments();

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('id', $arguments);
        $this->assertSame('23', $arguments['id']);
    }

    public function testSimpleRouteWithUrlencodedParam(): void
    {
        $routes = [
            Route::get('/site/post/{name1:.*?}/{name2:.*?}')->action(fn () => 1),
        ];

        $urlMatcher = $this->createUrlMatcher($routes);

        $request = new ServerRequest('GET', '/site/post/with+space/also%20space');

        $result = $urlMatcher->match($request);
        $arguments = $result->arguments();

        $this->assertTrue($result->isSuccess());
        $this->assertArrayHasKey('name1', $arguments);
        $this->assertArrayHasKey('name2', $arguments);
        $this->assertSame('with space', $arguments['name1']);
        $this->assertSame('also space', $arguments['name2']);
    }

    public function testSimpleRouteWithHostSuccess(): void
    {
        $routes = [
            Route::get('/site/index')->action(fn () => 1)->host('yii.test'),
            Route::get('/site/index')->action(fn () => 1)->host('{user}.yiiframework.com'),
        ];

        $urlMatcher = $this->createUrlMatcher($routes);

        $request = new ServerRequest('GET', '/site/index');
        $request1 = $request->withUri($request->getUri()->withHost('yii.test'));
        $request2 = $request->withUri($request->getUri()->withHost('rustamwin.yiiframework.com'));

        $result1 = $urlMatcher->match($request1);
        $result2 = $urlMatcher->match($request2);

        $this->assertTrue($result1->isSuccess());

        $this->assertArrayHasKey('user', $result2->arguments());
        $this->assertSame('rustamwin', $result2->arguments()['user']);
        $this->assertTrue($result2->isSuccess());
    }

    public function testSimpleRouteWithHostFailed(): void
    {
        $routes = [
            Route::get('/site/index')->action(fn () => 1)->host('yii.test'),
            Route::get('/site/index')->action(fn () => 1)->host('yiiframework.{zone:ru|com}'),
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
            Route::get('/site/post[/view]')->action(fn () => 1),
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
            Route::get('/site/post[/view]')->action(fn () => 1),
        ];

        $urlMatcher = $this->createUrlMatcher($routes);

        $request = new ServerRequest('GET', '/site/post/index');

        $result = $urlMatcher->match($request);

        $this->assertFalse($result->isSuccess());
    }

    public function testSimpleRouteWithOptionalParam(): void
    {
        $routes = [
            Route::get('/site/post[/{id}]')->action(fn () => 1),
        ];

        $urlMatcher = $this->createUrlMatcher($routes);

        $request1 = new ServerRequest('GET', '/site/post/23');
        $request2 = new ServerRequest('GET', '/site/post');

        $result1 = $urlMatcher->match($request1);
        $arguments1 = $result1->arguments();
        $result2 = $urlMatcher->match($request2);
        $arguments2 = $result2->arguments();

        $this->assertTrue($result1->isSuccess());
        $this->assertArrayHasKey('id', $arguments1);
        $this->assertSame('23', $arguments1['id']);
        $this->assertTrue($result2->isSuccess());
        $this->assertArrayNotHasKey('id', $arguments2);
    }

    public function testSimpleRouteWithNestedOptionalParts(): void
    {
        $routes = [
            Route::get('/site[/post[/view]]')->action(fn () => 1),
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
            Route::get('/site[/{name}[/{id}]]')->action(fn () => 1),
        ];

        $urlMatcher = $this->createUrlMatcher($routes);

        $request1 = new ServerRequest('GET', '/site/post/23');
        $request2 = new ServerRequest('GET', '/site/post');
        $request3 = new ServerRequest('GET', '/site');

        $result1 = $urlMatcher->match($request1);
        $arguments1 = $result1->arguments();
        $result2 = $urlMatcher->match($request2);
        $arguments2 = $result2->arguments();
        $result3 = $urlMatcher->match($request3);
        $arguments3 = $result3->arguments();

        $this->assertTrue($result1->isSuccess());
        $this->assertArrayHasKey('id', $arguments1);
        $this->assertArrayHasKey('name', $arguments1);
        $this->assertSame('23', $arguments1['id']);
        $this->assertSame('post', $arguments1['name']);
        $this->assertTrue($result2->isSuccess());
        $this->assertArrayHasKey('name', $arguments2);
        $this->assertSame('post', $arguments2['name']);
        $this->assertTrue($result3->isSuccess());
        $this->assertArrayNotHasKey('id', $arguments3);
        $this->assertArrayNotHasKey('name', $arguments3);
    }

    public function disallowedMethodsProvider(): array
    {
        return [
            [['GET', 'HEAD'], 'POST'],
            [['POST'], 'HEAD'],
            [['PATCH', 'PUT'], 'GET'],
        ];
    }

    /**
     * @dataProvider disallowedMethodsProvider
     */
    public function testDisallowedMethod(array $methods, string $disallowedMethod): void
    {
        $routes = [
            Route::methods($methods, '/site/index')->action(fn () => 1),
        ];

        $urlMatcher = $this->createUrlMatcher($routes);

        $request = new ServerRequest($disallowedMethod, '/site/index');

        $result = $urlMatcher->match($request);
        $this->assertFalse($result->isSuccess());
        $this->assertTrue($result->isMethodFailure());
        $this->assertSame($methods, $result->methods());
    }

    public function testAutoAllowedHEADMethod(): void
    {
        $routes = [
            Route::post('/site/post/view')->action(fn () => 1),
            Route::get('/site/index')->action(fn () => 1),
            Route::post('/site/index')->action(fn () => 1),
        ];

        $urlMatcher = $this->createUrlMatcher($routes);

        $request = new ServerRequest('HEAD', '/site/index');

        $result = $urlMatcher->match($request);
        $this->assertTrue($result->isSuccess());
        $this->assertFalse($result->isMethodFailure());
    }

    public function testNoCache(): void
    {
        $routes = [
            Route::get('/')->action(fn () => 1)->name('site/index'),
            Route::methods(['GET', 'POST'], '/contact')->action(fn () => 1)->name('site/contact'),
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
            1 => [],
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

    public function testStaticRouteExcludeFromMatching(): void
    {
        $routes = [
            Route::get('/test')->action(fn () => 1)->name('test'),
        ];

        $urlMatcher = $this->createUrlMatcher($routes);
        $request = new ServerRequest('GET', '/');
        $result = $urlMatcher->match($request);

        $this->assertFalse($result->isSuccess());
    }

    public function testCacheError(): void
    {
        $routes = [
            Route::get('/')->action(fn () => 1)->name('site/index'),
            Route::methods(['GET', 'POST'], '/contact')->action(fn () => 1)->name('site/contact'),
        ];

        $request = new ServerRequest('GET', '/contact');

        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')
            ->will($this->throwException(new RuntimeException()));
        $matcher = $this->createUrlMatcher($routes, $cache);
        $result = $matcher->match($request);
        $this->assertTrue($result->isSuccess());
    }

    public function testPure(): void
    {
        $matcher = new UrlMatcher(
            new RouteCollection(
                new RouteCollector()
            )
        );

        $result = $matcher->match(new ServerRequest('GET', '/contact'));

        $this->assertFalse($result->isSuccess());
    }

    public function testStaticRoutes(): void
    {
        $matcher = $this->createUrlMatcher([
            Route::get('/i/{image}')->name('image'),
        ]);

        $result = $matcher->match(new ServerRequest('GET', '/i/face.jpg'));

        $this->assertFalse($result->isSuccess());
    }

    private function createUrlMatcher(array $routes, CacheInterface $cache = null): UrlMatcherInterface
    {
        $rootGroup = Group::create(null)->routes(...$routes);
        $collector = new RouteCollector();
        $collector->addGroup($rootGroup);
        return new UrlMatcher(new RouteCollection($collector), $cache, ['cache_key' => 'route-cache']);
    }
}
