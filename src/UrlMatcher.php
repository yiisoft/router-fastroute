<?php

declare(strict_types=1);

namespace Yiisoft\Router\FastRoute;

use FastRoute\DataGenerator\GroupCountBased as RouteGenerator;
use FastRoute\Dispatcher;
use FastRoute\Dispatcher\GroupCountBased;
use FastRoute\RouteCollector;
use FastRoute\RouteParser\Std as RouteParser;
use Psr\Http\Message\ServerRequestInterface;
use Psr\SimpleCache\CacheInterface;
use RuntimeException;
use Yiisoft\Http\Method;
use Yiisoft\Router\MatchingResult;
use Yiisoft\Router\RouteCollectionInterface;
use Yiisoft\Router\UrlMatcherInterface;

use function array_merge;

final class UrlMatcher implements UrlMatcherInterface
{
    /**
     * Configuration key used to set the cache file path.
     */
    public const CONFIG_CACHE_KEY = 'cache_key';

    /**
     * Configuration key used to set the cache file path.
     */
    private string $cacheKey = 'routes-cache';

    /**
     * @var ?callable A factory callback that can return a dispatcher.
     */
    private $dispatcherCallback;

    /**
     * @var array Cached data used by the dispatcher.
     */
    private array $dispatchData = [];

    /**
     * @var bool Whether cache is enabled and valid dispatch data has been loaded from cache.
     */
    private bool $hasCache = false;

    private ?CacheInterface $cache;

    private RouteCollector $fastRouteCollector;
    private RouteCollectionInterface $routeCollection;
    private bool $hasInjectedRoutes = false;

    /**
     * Accepts optionally a FastRoute RouteCollector and a callable factory
     * that can return a FastRoute dispatcher.
     *
     * If either is not provided defaults will be used:
     *
     * - A RouteCollector instance will be created composing a RouteParser and
     *   RouteGenerator.
     * - A callable that returns a GroupCountBased dispatcher will be created.
     *
     * @param RouteCollector|null $fastRouteCollector If not provided, a default
     *     implementation will be used.
     * @param callable|null $dispatcherFactory Callable that will return a
     *     FastRoute dispatcher.
     * @param array|null $config Array of custom configuration options.
     */
    public function __construct(
        RouteCollectionInterface $routeCollection,
        CacheInterface $cache = null,
        ?array $config = null,
        RouteCollector $fastRouteCollector = null,
        callable $dispatcherFactory = null
    ) {
        $this->fastRouteCollector = $fastRouteCollector ?? $this->createRouteCollector();
        $this->routeCollection = $routeCollection;
        $this->dispatcherCallback = $dispatcherFactory;
        $this->loadConfig($config);
        $this->cache = $cache;

        $this->loadDispatchData();
    }

    public function match(ServerRequestInterface $request): MatchingResult
    {
        if (!$this->hasCache && !$this->hasInjectedRoutes) {
            $this->injectRoutes();
        }

        $dispatchData = $this->getDispatchData();
        $path = urldecode($request->getUri()->getPath());
        $method = $request->getMethod();
        $result = $this->getDispatcher($dispatchData)->dispatch($method, $request->getUri()->getHost() . $path);

        return $result[0] !== Dispatcher::FOUND
            ? $this->marshalFailedRoute($result)
            : $this->marshalMatchedRoute($result);
    }

    /**
     * Load configuration parameters.
     *
     * @param array|null $config Array of custom configuration options.
     */
    private function loadConfig(?array $config): void
    {
        if ($config === null) {
            return;
        }

        if (isset($config[self::CONFIG_CACHE_KEY])) {
            $this->cacheKey = (string) $config[self::CONFIG_CACHE_KEY];
        }
    }

    /**
     * Retrieve the dispatcher instance.
     *
     * Uses the callable factory in $dispatcherCallback, passing it $data
     * (which should be derived from the router's getData() method); this
     * approach is done to allow testing against the dispatcher.
     *
     * @param array|object $data Data from {@see RouteCollector::getData()}.
     *
     * @return Dispatcher
     */
    private function getDispatcher($data): Dispatcher
    {
        if (!$this->dispatcherCallback) {
            $this->dispatcherCallback = $this->createDispatcherCallback();
        }

        $factory = $this->dispatcherCallback;

        return $factory($data);
    }

    /**
     * Create a default FastRoute Collector instance.
     */
    private function createRouteCollector(): RouteCollector
    {
        return new RouteCollector(new RouteParser(), new RouteGenerator());
    }

    /**
     * Return a default implementation of a callback that can return a Dispatcher.
     */
    private function createDispatcherCallback(): callable
    {
        return static function (array $data) {
            return new GroupCountBased($data);
        };
    }

    /**
     * Marshal a routing failure result.
     *
     * If the failure was due to the HTTP method, passes the allowed HTTP
     * methods to the factory.
     *
     * @param array $result
     *
     * @return MatchingResult
     */
    private function marshalFailedRoute(array $result): MatchingResult
    {
        $resultCode = $result[0];
        if ($resultCode === Dispatcher::METHOD_NOT_ALLOWED) {
            return MatchingResult::fromFailure($result[1]);
        }

        return MatchingResult::fromFailure(Method::ALL);
    }

    /**
     * Marshals a route result based on the results of matching.
     *
     * @param array $result
     *
     * @return MatchingResult
     */
    private function marshalMatchedRoute(array $result): MatchingResult
    {
        [, $name, $arguments] = $result;

        $route = $this->routeCollection->getRoute($name);

        $arguments = array_merge($route->getData('defaults'), $arguments);

        return MatchingResult::fromSuccess($route, $arguments);
    }

    /**
     * Inject routes into the underlying router.
     */
    private function injectRoutes(): void
    {
        foreach ($this->routeCollection->getRoutes() as $route) {
            if (!$route->getData('hasMiddlewares')) {
                continue;
            }
            $hostPattern = $route->getData('host') ?? '{_host:[a-zA-Z0-9\.\-]*}';
            $methods = $route->getData('methods');
            $this->fastRouteCollector->addRoute(
                $methods,
                $hostPattern . $route->getData('pattern'),
                $route->getData('name')
            );
        }
        $this->hasInjectedRoutes = true;
    }

    /**
     * Get the dispatch data either from cache or freshly generated by the
     * FastRoute data generator.
     *
     * If caching is enabled, store the freshly generated data to file.
     */
    private function getDispatchData(): array
    {
        if ($this->hasCache) {
            return $this->dispatchData;
        }

        $dispatchData = $this->fastRouteCollector->getData();

        if ($this->cache !== null) {
            $this->cacheDispatchData($dispatchData);
        }

        return $dispatchData;
    }

    /**
     * Load dispatch data from cache.
     */
    private function loadDispatchData(): void
    {
        if ($this->cache !== null && $this->cache->has($this->cacheKey)) {
            $dispatchData = $this->cache->get($this->cacheKey);

            $this->hasCache = true;
            $this->dispatchData = $dispatchData;
            return;
        }

        $this->hasCache = false;
    }

    /**
     * Save dispatch data to cache.
     *
     * @param array $dispatchData
     * @psalm-suppress PossiblyNullReference
     */
    private function cacheDispatchData(array $dispatchData): void
    {
        $this->cache->set($this->cacheKey, $dispatchData);
    }
}
