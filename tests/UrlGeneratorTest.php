<?php

declare(strict_types=1);

namespace Yiisoft\Router\FastRoute\Tests;

use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Router\FastRoute\UrlGenerator;
use Yiisoft\Router\Group;
use Yiisoft\Router\Route;
use Yiisoft\Router\RouteCollection;
use Yiisoft\Router\RouteCollectionInterface;
use Yiisoft\Router\RouteCollector;
use Yiisoft\Router\RouteNotFoundException;
use Yiisoft\Router\UrlGeneratorInterface;

final class UrlGeneratorTest extends TestCase
{
    private function createUrlGenerator(array $routes, CurrentRoute $currentRoute = null): UrlGeneratorInterface
    {
        $routeCollection = $this->createRouteCollection($routes);
        return new UrlGenerator($routeCollection, $currentRoute);
    }

    private function createRouteCollection(array $routes): RouteCollectionInterface
    {
        $rootGroup = Group::create(null)->routes(...$routes);
        $collector = new RouteCollector();
        $collector->addGroup($rootGroup);
        return new RouteCollection($collector);
    }

    public function testSimpleRouteGenerated(): void
    {
        $routes = [
            Route::get('/home/index')->name('index'),
        ];
        $url = $this->createUrlGenerator($routes)->generate('index');

        $this->assertEquals('/home/index', $url);
    }

    public function testRouteWithoutNameNotFound(): void
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

    public function testParametersSubstituted(): void
    {
        $routes = [
            Route::get('/view/{id:\d+}/{text:~[\w]+}#{tag:\w+}')->name('view'),
        ];
        $url = $this->createUrlGenerator($routes)->generate('view', ['id' => 100, 'tag' => 'yii', 'text' => '~test']);

        $this->assertEquals('/view/100/~test#yii', $url);
    }

    public function testParametersUrlencode(): void
    {
        $routes = [
            Route::get('/view/{name:.*?}/{text:~[\w]+}')->name('view'),
        ];
        $urlGenerator = $this->createUrlGenerator($routes);

        $url = $urlGenerator->generate('view', ['name' => 'with space', 'text' => '~test', 'param' => 'also space']);
        $this->assertEquals('/view/with%20space/~test?param=also+space', $url);

        $urlGenerator->setEncodeRaw(false);
        $url = $urlGenerator->generate('view', ['name' => 'with space', 'text' => '~test', 'param' => 'also space']);
        $this->assertEquals('/view/with+space/%7Etest?param=also+space', $url);
    }

    public function testExceptionThrownIfParameterPatternDoesntMatch(): void
    {
        $routes = [
            Route::get('/view/{id:\w+}')->name('view'),
        ];
        $urlGenerator = $this->createUrlGenerator($routes);

        $this->expectExceptionMessage('Parameter value for [id] did not match the regex `\w+`');
        $urlGenerator->generate('view', ['id' => null]);
    }

    public function testExceptionThrownIfAnyParameterIsMissing(): void
    {
        $routes = [
            Route::get('/view/{id:\d+}/{value}')->name('view'),
        ];
        $urlGenerator = $this->createUrlGenerator($routes);

        $this->expectExceptionMessage(
            'Route `view` expects at least parameter values for [id,value], but received [id]'
        );
        $urlGenerator->generate('view', ['id' => 123]);
    }

    public function testGroupPrefixAppended(): void
    {
        $routes = [
            Group::create('/api')->routes(
                Route::get('/post')->name('post/index'),
                Route::get('/post/{id}')->name('post/view')
            ),
        ];
        $urlGenerator = $this->createUrlGenerator($routes);

        $url = $urlGenerator->generate('post/index');
        $this->assertEquals('/api/post', $url);

        $url = $urlGenerator->generate('post/view', ['id' => 42]);
        $this->assertEquals('/api/post/42', $url);
    }

    public function testNestedGroupsPrefixAppended(): void
    {
        $routes = [
            Group::create('/api')->routes(
                Group::create('/v1')->routes(
                    Route::get('/user')->name('api-v1-user/index'),
                    Route::get('/user/{id}')->name('api-v1-user/view'),
                    Group::create('/news')->routes(
                        Route::get('/post')->name('api-v1-news-post/index'),
                        Route::get('/post/{id}')->name('api-v1-news-post/view'),
                    ),
                    Group::create('/blog')->routes(
                        Route::get('/post')->name('api-v1-blog-post/index'),
                        Route::get('/post/{id}')->name('api-v1-blog-post/view'),
                    ),
                    Route::get('/note')->name('api-v1-note/index'),
                    Route::get('/note/{id}')->name('api-v1-note/view')
                )
            ),
        ];

        $urlGenerator = $this->createUrlGenerator($routes);

        $url = $urlGenerator->generate('api-v1-user/index');
        $this->assertEquals('/api/v1/user', $url);

        $url = $urlGenerator->generate('api-v1-user/view', ['id' => 42]);
        $this->assertEquals('/api/v1/user/42', $url);

        $url = $urlGenerator->generate('api-v1-news-post/index');
        $this->assertEquals('/api/v1/news/post', $url);

        $url = $urlGenerator->generate('api-v1-news-post/view', ['id' => 42]);
        $this->assertEquals('/api/v1/news/post/42', $url);

        $url = $urlGenerator->generate('api-v1-blog-post/index');
        $this->assertEquals('/api/v1/blog/post', $url);

        $url = $urlGenerator->generate('api-v1-blog-post/view', ['id' => 42]);
        $this->assertEquals('/api/v1/blog/post/42', $url);

        $url = $urlGenerator->generate('api-v1-note/index');
        $this->assertEquals('/api/v1/note', $url);

        $url = $urlGenerator->generate('api-v1-note/view', ['id' => 42]);
        $this->assertEquals('/api/v1/note/42', $url);
    }

    public function testExtraParametersAddedAsQueryString(): void
    {
        $routes = [
            Route::get('/test/{name}')
                ->name('test'),
        ];

        $url = $this->createUrlGenerator($routes)->generate('test', ['name' => 'post', 'id' => 12, 'sort' => 'asc']);
        $this->assertEquals('/test/post?id=12&sort=asc', $url);
    }

    public function testDefaultNotUsedForOptionalParameter(): void
    {
        $routes = [
            Route::get('/[{name}]')
                ->name('defaults')
                ->defaults(['name' => 'default']),
        ];

        $url = $this->createUrlGenerator($routes)->generate('defaults');
        $this->assertEquals('/', $url);
    }

    public function testValueUsedForOptionalParameter(): void
    {
        $routes = [
            Route::get('/[{name}]')
                ->name('defaults')
                ->defaults(['name' => 'default']),
        ];

        $url = $this->createUrlGenerator($routes)->generate('defaults', ['name' => 'test']);
        $this->assertEquals('/test', $url);
    }

    public function testDefaultNotUsedForRequiredParameter(): void
    {
        $routes = [
            Route::get('/{name}')
                ->name('defaults')
                ->defaults(['name' => 'default']),
        ];

        $this->expectExceptionMessage('Route `defaults` expects at least parameter values for [name], but received []');
        $this->createUrlGenerator($routes)->generate('defaults');
    }

    /**
     * Host specified in generateAbsolute() should override host specified in route
     */
    public function testAbsoluteUrlHostOverride(): void
    {
        $routes = [
            Route::get('/home/index')->name('index')->host('http://test.com'),
        ];
        $url = $this->createUrlGenerator($routes)->generateAbsolute('index', [], null, 'http://mysite.com');

        $this->assertEquals('http://mysite.com/home/index', $url);
    }

    /**
     * Trailing slash in host argument of generateAbsolute() should not break URL generated
     */
    public function testAbsoluteUrlHostOverrideWithTrailingSlash(): void
    {
        $routes = [
            Route::get('/home/index')->name('index')->host('http://test.com'),
        ];
        $url = $this->createUrlGenerator($routes)->generateAbsolute('index', [], null, 'http://mysite.com/');

        $this->assertEquals('http://mysite.com/home/index', $url);
    }

    /**
     * Scheme specified in generateAbsolute() should override scheme specified in route
     */
    public function testAbsoluteUrlSchemeOverrideHostInRouteScheme(): void
    {
        $routes = [
            Route::get('/home/index')->name('index')->host('http://test.com'),
        ];
        $url = $this->createUrlGenerator($routes)->generateAbsolute('index', [], 'https');

        $this->assertEquals('https://test.com/home/index', $url);
    }

    /**
     * Scheme specified in generateAbsolute() should override scheme specified in method
     */
    public function testAbsoluteUrlSchemeOverrideHostInMethodScheme(): void
    {
        $routes = [
            Route::get('/home/index')->name('index'),
        ];
        $url = $this->createUrlGenerator($routes)->generateAbsolute('index', [], 'https', 'http://test.com');

        $this->assertEquals('https://test.com/home/index', $url);
    }

    /**
     * Scheme specified in generateAbsolute() should override scheme specified in the matched host
     */
    public function testAbsoluteUrlSchemeOverrideLastMatchedHostScheme(): void
    {
        $request = new ServerRequest('GET', 'http://test.com/home/index');
        $routes = [
            Route::get('/home/index')->name('index'),
        ];
        $currentRoute = new CurrentRoute();
        $currentRoute->setUri($request->getUri());
        $url = $this->createUrlGenerator($routes, $currentRoute)->generateAbsolute('index', [], 'https');

        $this->assertEquals('https://test.com/home/index', $url);
    }

    /**
     * If there's host specified in route, it should be used unless there's host parameter in generateAbsolute()
     */
    public function testAbsoluteUrlWithHostInRoute(): void
    {
        $routes = [
            Route::get('/home/index')->name('index')->host('http://test.com'),
        ];
        $url = $this->createUrlGenerator($routes)->generateAbsolute('index');

        $this->assertEquals('http://test.com/home/index', $url);
    }

    /**
     * Trailing slash in route host should not break URL generated
     */
    public function testAbsoluteUrlWithTrailingSlashHostInRoute(): void
    {
        $routes = [
            Route::get('/home/index')->name('index')->host('http://test.com/'),
        ];
        $url = $this->createUrlGenerator($routes)->generateAbsolute('index');

        $this->assertEquals('http://test.com/home/index', $url);
    }

    /**
     * Last matched host is used for absolute URL generation in case
     * host is not specified in either route or createUrlGenerator()
     */
    public function testLastMatchedHostUsedForAbsoluteUrl(): void
    {
        $request = new ServerRequest('GET', 'http://test.com/home/index');
        $routes = [
            Route::get('/home/index')->name('index'),
        ];

        $currentRoute = new CurrentRoute();
        $currentRoute->setUri($request->getUri());
        $url = $this->createUrlGenerator($routes, $currentRoute)->generateAbsolute('index');

        $this->assertEquals('http://test.com/home/index', $url);
    }

    /**
     * If there's non-standard port used in last matched host,
     * it should end up in the URL generated
     */
    public function testLastMatchedHostWithPortUsedForAbsoluteUrl(): void
    {
        $request = new ServerRequest('GET', 'http://test.com:8080/home/index');
        $routes = [
            Route::get('/home/index')->name('index'),
        ];

        $currentRoute = new CurrentRoute();
        $currentRoute->setUri($request->getUri());
        $url = $this->createUrlGenerator($routes, $currentRoute)->generateAbsolute('index');

        $this->assertEquals('http://test.com:8080/home/index', $url);
    }

    /**
     * Schema from route host should have more priority than schema from last matched request.
     */
    public function testHostInRouteWithProtocolRelativeSchemeAbsoluteUrl(): void
    {
        $request = new ServerRequest('GET', 'http://test.com/home/index');
        $routes = [
            Route::get('/home/index')->name('index')->host('//test.com'),
        ];

        $currentRoute = new CurrentRoute();
        $currentRoute->setUri($request->getUri());
        $url = $this->createUrlGenerator($routes, $currentRoute)->generateAbsolute('index');

        $this->assertEquals('//test.com/home/index', $url);
    }

    /**
     * Schema from generateAbsolute() should have more priority than both
     * route and last matched request.
     */
    public function testHostInMethodWithProtocolRelativeSchemeAbsoluteUrl(): void
    {
        $request = new ServerRequest('GET', 'http://test.com/home/index');
        $routes = [
            Route::get('/home/index')->name('index')->host('//mysite.com'),
        ];

        $currentRoute = new CurrentRoute();
        $currentRoute->setUri($request->getUri());
        $url = $this->createUrlGenerator($routes, $currentRoute)->generateAbsolute('index', [], null, '//test.com');

        $this->assertEquals('//test.com/home/index', $url);
    }

    public function testHostInRouteProtocolRelativeSchemeAbsoluteUrl(): void
    {
        $request = new ServerRequest('GET', 'http://test.com/home/index');
        $routes = [
            Route::get('/home/index')->name('index')->host('http://test.com'),
            Route::get('/home/view')->name('view')->host('test.com'),
        ];

        $currentRoute = new CurrentRoute();
        $currentRoute->setUri($request->getUri());
        $url1 = $this->createUrlGenerator($routes, $currentRoute)->generateAbsolute('index', [], '');
        $url2 = $this->createUrlGenerator($routes, $currentRoute)->generateAbsolute('view', [], '');

        $this->assertEquals('//test.com/home/index', $url1);
        $this->assertEquals('//test.com/home/view', $url2);
    }

    public function testHostInMethodProtocolRelativeSchemeAbsoluteUrl(): void
    {
        $request = new ServerRequest('GET', 'http://test.com/home/index');
        $routes = [
            Route::get('/home/index')->name('index')->host('//mysite.com'),
        ];

        $currentRoute = new CurrentRoute();
        $currentRoute->setUri($request->getUri());
        $url1 = $this->createUrlGenerator($routes, $currentRoute)->generateAbsolute('index', [], '', 'http://test.com');
        $url2 = $this->createUrlGenerator($routes, $currentRoute)->generateAbsolute('index', [], '', 'test.com');
        $url3 = $this->createUrlGenerator($routes, $currentRoute)->generateAbsolute(
            'index',
            [],
            null,
            'http://test.com'
        );
        $url4 = $this->createUrlGenerator($routes, $currentRoute)->generateAbsolute('index', [], null, 'test.com');

        $this->assertEquals('//test.com/home/index', $url1);
        $this->assertEquals('//test.com/home/index', $url2);
        $this->assertEquals('http://test.com/home/index', $url3);
        $this->assertEquals('http://test.com/home/index', $url4);
    }

    public function testLastMatchedHostProtocolRelativeSchemeAbsoluteUrl(): void
    {
        $request = new ServerRequest('GET', 'http://test.com/home/index');
        $routes = [
            Route::get('/home/index')->name('index'),
        ];

        $currentRoute = new CurrentRoute();
        $currentRoute->setUri($request->getUri());
        $url = $this->createUrlGenerator($routes, $currentRoute)->generateAbsolute('index', [], '');

        $this->assertEquals('//test.com/home/index', $url);
    }

    public function testHostInRouteWithoutSchemeAbsoluteUrl(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/home/index');
        $routes = [
            Route::get('/home/index')->name('index')->host('example.com'),
        ];

        $currentRoute = new CurrentRoute();
        $url = $this->createUrlGenerator($routes, $currentRoute)->generateAbsolute('index');

        $this->assertEquals('//example.com/home/index', $url);

        $currentRoute->setUri($request->getUri());
        $url = $this->createUrlGenerator($routes, $currentRoute)->generateAbsolute('index');

        $this->assertEquals('http://example.com/home/index', $url);
    }

    public function testFallbackAbsoluteUrl(): void
    {
        $routes = [
            Route::get('/home/index')->name('index'),
        ];

        $currentRoute = new CurrentRoute();
        $url = $this->createUrlGenerator($routes, $currentRoute)->generateAbsolute('index');

        $this->assertEquals('/home/index', $url);
    }
    
    public function testWithLocales(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/home/index');

        $routes = [
            Route::get('/home/index')->name('index'),
        ];

        $currentRoute = new CurrentRoute();
        $currentRoute->setUri($request->getUri());
        $urlGenerator = $this->createUrlGenerator($routes, $currentRoute);
        $urlGenerator->setLocaleParameterName('_locale');
        $urlGenerator->setLocales(['uz' => 'uz-UZ', 'en' => 'en-US', 'ru' => 'ru-RU']);

        $url = $urlGenerator->generate('index', ['_locale' => 'uz']);

        $this->assertEquals('/uz/home/index', $url);
    }

    public function testAbsoluteUrlWithLocales(): void
    {
        $request = new ServerRequest('GET', 'http://example.com/home/index');

        $routes = [
            Route::get('/home/index')->name('index'),
        ];

        $currentRoute = new CurrentRoute();
        $currentRoute->setUri($request->getUri());
        $urlGenerator = $this->createUrlGenerator($routes, $currentRoute);
        $urlGenerator->setLocaleParameterName('_locale');
        $urlGenerator->setLocales(['uz' => 'uz-UZ', 'en' => 'en-US', 'ru' => 'ru-RU']);

        $url = $urlGenerator->generateAbsolute('index', ['_locale' => 'uz']);

        $this->assertEquals('http://example.com/uz/home/index', $url);
    }
}
