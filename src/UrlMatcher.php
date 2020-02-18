<?php

declare(strict_types=1);

namespace Yiisoft\Router\FastRoute;

use FastRoute\Dispatcher;
use FastRoute\Dispatcher\GroupCountBased;
use FastRoute\RouteParser\Std as RouteParser;
use FastRoute\DataGenerator\GroupCountBased as RouteGenerator;
use FastRoute\RouteCollector;
use Psr\Http\Message\ServerRequestInterface;
use Psr\SimpleCache\CacheInterface;
use Yiisoft\Http\Method;
use Yiisoft\Router\Route;
use Yiisoft\Router\MatchingResult;
use Yiisoft\Router\UrlMatcherInterface;
use Yiisoft\Router\RouteCollectionInterface;

use function array_merge;
use function array_reduce;
use function array_unique;

final class UrlMatcher implements UrlMatcherInterface
{
    /**
     * @const string Configuration key used to set the cache file path
     */
    public const CONFIG_CACHE_KEY = 'cache_key';

    /**
     * @const string Configuration key used to set the cache file path
     */
    private string $cacheKey = 'routes-cache';

    /**
     * @var callable A factory callback that can return a dispatcher.
     */
    private $dispatcherCallback;

    /**
     * Cached data used by the dispatcher.
     *
     * @var array
     */
    private array $dispatchData = [];

    /**
     * True if cache is enabled and valid dispatch data has been loaded from
     * cache.
     *
     * @var bool
     */
    private bool $hasCache = false;
    private ?CacheInterface $cache = null;

    private RouteCollector $fastRouteCollector;
    private RouteCollectionInterface $routeCollection;
    private ?Route $currentRoute = null;
    private bool $hasInjectedRoutes = false;

    /**
     * Last matched request
     *
     * @var ServerRequestInterface|null
     */
    private ?ServerRequestInterface $request = null;

    /**
     * Constructor
     *
     * Accepts optionally a FastRoute RouteCollector and a callable factory
     * that can return a FastRoute dispatcher.
     *
     * If either is not provided defaults will be used:
     *
     * - A RouteCollector instance will be created composing a RouteParser and
     *   RouteGenerator.
     * - A callable that returns a GroupCountBased dispatcher will be created.
     *
     * @param null|RouteCollector $fastRouteCollector If not provided, a default
     *     implementation will be used.
     * @param null|callable $dispatcherFactory Callable that will return a
     *     FastRoute dispatcher.
     * @param array $config Array of custom configuration options.
     */
    public function __construct(
        RouteCollectionInterface $routeCollection,
        CacheInterface $cache = null,
        array $config = null,
        RouteCollector $fastRouteCollector = null,
        callable $dispatcherFactory = null
    ) {
        if (null === $fastRouteCollector) {
            $fastRouteCollector = $this->createRouteCollector();
        }
        $this->routeCollection = $routeCollection;
        $this->fastRouteCollector = $fastRouteCollector;
        $this->dispatcherCallback = $dispatcherFactory;
        $this->loadConfig($config);
        $this->cache = $cache;

        $this->loadDispatchData();
    }

    public function match(ServerRequestInterface $request): MatchingResult
    {
        $this->request = $request;

        if (!$this->hasCache && !$this->hasInjectedRoutes) {
            $this->injectRoutes();
        }

        $dispatchData = $this->getDispatchData();
        $path = rawurldecode($request->getUri()->getPath());
        $method = $request->getMethod();
        $result = $this->getDispatcher($dispatchData)->dispatch($method, $path);

        return $result[0] !== Dispatcher::FOUND
            ? $this->marshalFailedRoute($result)
            : $this->marshalMatchedRoute($result, $method);
    }

    /**
     * Returns the current Route object
     * @return Route|null current route
     */
    public function getCurrentRoute(): ?Route
    {
        return $this->currentRoute;
    }

    /**
     * Returns last matched Request
     * @return ServerRequestInterface|null current route
     */
    public function getLastMatchedRequest(): ?ServerRequestInterface
    {
        return $this->request;
    }

    /**
     * @return RouteCollectionInterface collection of routes
     */
    public function getRouteCollection(): RouteCollectionInterface
    {
        return $this->routeCollection;
    }

    /**
     * Load configuration parameters
     *
     * @param null|array $config Array of custom configuration options.
     */
    private function loadConfig(array $config = null): void
    {
        if (null === $config) {
            return;
        }

        if (isset($config[self::CONFIG_CACHE_KEY])) {
            $this->cacheKey = (string)$config[self::CONFIG_CACHE_KEY];
        }
    }

    /**
     * Retrieve the dispatcher instance.
     *
     * Uses the callable factory in $dispatcherCallback, passing it $data
     * (which should be derived from the router's getData() method); this
     * approach is done to allow testing against the dispatcher.
     *
     * @param array|object $data Data from RouteCollector::getData()
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
     * Create a default FastRoute Collector instance
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
        return static function ($data) {
            return new GroupCountBased($data);
        };
    }

    /**
     * Marshal a routing failure result.
     *
     * If the failure was due to the HTTP method, passes the allowed HTTP
     * methods to the factory.
     * @param array $result
     * @return MatchingResult
     */
    private function marshalFailedRoute(array $result): MatchingResult
    {
        $resultCode = $result[0];
        if ($resultCode === Dispatcher::METHOD_NOT_ALLOWED) {
            return MatchingResult::fromFailure($result[1]);
        }

        return MatchingResult::fromFailure(Method::ANY);
    }

    /**
     * Marshals a route result based on the results of matching and the current HTTP method.
     * @param array $result
     * @param string $method
     * @return MatchingResult
     */
    private function marshalMatchedRoute(array $result, string $method): MatchingResult
    {
        [, $name, $parameters] = $result;

        $route = $this->routeCollection->getRoute($name);
        if (!in_array($method, $route->getMethods(), true)) {
            $result[1] = $route->getPattern();
            return $this->marshalMethodNotAllowedResult($result);
        }

        $parameters = array_merge($route->getDefaults(), $parameters);
        $this->currentRoute = $route;

        return MatchingResult::fromSuccess($route, $parameters);
    }

    private function marshalMethodNotAllowedResult(array $result): MatchingResult
    {
        $path = $result[1];

        $allowedMethods = array_unique(
            array_reduce(
                $this->routeCollection->getRoutes(),
                static function ($allowedMethods, Route $route) use ($path) {
                    if ($path !== $route->getPattern()) {
                        return $allowedMethods;
                    }

                    return array_merge($allowedMethods, $route->getMethods());
                },
                []
            )
        );

        return MatchingResult::fromFailure($allowedMethods);
    }

    /**
     * Inject routes into the underlying router
     */
    private function injectRoutes(): void
    {
        foreach ($this->routeCollection->getRoutes() as $index => $route) {
            /** @var Route $route */
            $this->fastRouteCollector->addRoute($route->getMethods(), $route->getPattern(), $route->getName());
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

        $dispatchData = (array)$this->fastRouteCollector->getData();

        if ($this->cache !== null) {
            $this->cacheDispatchData($dispatchData);
        }

        return $dispatchData;
    }

    /**
     * Load dispatch data from cache
     * @throws \RuntimeException If the cache file contains invalid data
     * @throws \Psr\SimpleCache\InvalidArgumentException
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
     * Save dispatch data to cache
     * @param array $dispatchData
     * @throws \RuntimeException If the cache directory does not exist.
     * @throws \RuntimeException If the cache directory is not writable.
     * @throws \RuntimeException If the cache file exists but is not writable
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    private function cacheDispatchData(array $dispatchData): void
    {
        $this->cache->set($this->cacheKey, $dispatchData);
    }
}
