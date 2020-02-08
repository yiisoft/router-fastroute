<?php

declare(strict_types=1);

namespace Yiisoft\Router\FastRoute;

use FastRoute\Dispatcher;
use FastRoute\Dispatcher\GroupCountBased;
use FastRoute\RouteCollector;
use FastRoute\RouteParser;
use Psr\Http\Message\ServerRequestInterface;
use Yiisoft\Router\Group;
use Yiisoft\Router\MatchingResult;
use Yiisoft\Http\Method;
use Yiisoft\Router\Route;
use Yiisoft\Router\RouteNotFoundException;
use Yiisoft\Router\RouterInterface;

use function array_key_exists;
use function array_keys;
use function array_merge;
use function array_reduce;
use function array_unique;
use function dirname;
use function file_exists;
use function file_put_contents;
use function implode;
use function is_array;
use function is_dir;
use function is_string;
use function is_writable;
use function preg_match;
use function restore_error_handler;
use function set_error_handler;
use function sprintf;
use function var_export;

use const E_WARNING;

/**
 * Router implementation bridging nikic/fast-route.
 * Adapted from https://github.com/zendframework/zend-expressive-fastroute/
 */
class FastRoute extends Group implements RouterInterface
{
    /**
     * Template used when generating the cache file.
     */
    public const CACHE_TEMPLATE = <<< 'EOT'
<?php
return %s;
EOT;

    /**
     * @const string Configuration key used to enable/disable fastroute caching
     */
    public const CONFIG_CACHE_ENABLED = 'cache_enabled';

    /**
     * @const string Configuration key used to set the cache file path
     */
    public const CONFIG_CACHE_FILE = 'cache_file';

    /**
     * Cache generated route data?
     *
     * @var bool
     */
    private $cacheEnabled = false;

    /**
     * Cache file path relative to the project directory.
     *
     * @var string
     */
    private $cacheFile = 'data/cache/fastroute.php.cache';

    /**
     * @var callable A factory callback that can return a dispatcher.
     */
    private $dispatcherCallback;

    /**
     * Cached data used by the dispatcher.
     *
     * @var array
     */
    private $dispatchData = [];

    /**
     * True if cache is enabled and valid dispatch data has been loaded from
     * cache.
     *
     * @var bool
     */
    private $hasCache = false;

    /**
     * FastRoute router
     *
     * @var RouteCollector
     */
    private $router;

    /**
     * All attached routes as Route instances
     *
     * @var Route[]
     */
    private $routes = [];

    /**
     * @var RouteParser
     */
    private $routeParser;

    /** @var string */
    private $uriPrefix = '';

    /** @var Route|null */
    private ?Route $currentRoute = null;

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
     * @param null|RouteCollector $router If not provided, a default
     *     implementation will be used.
     * @param RouteParser $routeParser
     * @param null|callable $dispatcherFactory Callable that will return a
     *     FastRoute dispatcher.
     * @param array $config Array of custom configuration options.
     */
    public function __construct(
        RouteCollector $router,
        RouteParser $routeParser,
        callable $dispatcherFactory = null,
        array $config = null
    ) {
        $this->router = $router;
        $this->dispatcherCallback = $dispatcherFactory;
        $this->routeParser = $routeParser;

        $this->loadConfig($config);
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

        if (isset($config[self::CONFIG_CACHE_ENABLED])) {
            $this->cacheEnabled = (bool)$config[self::CONFIG_CACHE_ENABLED];
        }

        if (isset($config[self::CONFIG_CACHE_FILE])) {
            $this->cacheFile = (string)$config[self::CONFIG_CACHE_FILE];
        }

        if ($this->cacheEnabled) {
            $this->loadDispatchData();
        }
    }

    public function match(ServerRequestInterface $request): MatchingResult
    {
        $this->request = $request;
        // Inject any pending route items
        $this->injectItems();

        $dispatchData = $this->getDispatchData();
        $path = rawurldecode($request->getUri()->getPath());
        $method = $request->getMethod();
        $result = $this->getDispatcher($dispatchData)->dispatch($method, $path);

        return $result[0] !== Dispatcher::FOUND
            ? $this->marshalFailedRoute($result)
            : $this->marshalMatchedRoute($result, $method);
    }

    public function getUriPrefix(): string
    {
        return $this->uriPrefix;
    }

    public function setUriPrefix(string $prefix): void
    {
        $this->uriPrefix = $prefix;
    }

    /**
     * Generate a URI based on a given route.
     *
     * Replacements in FastRoute are written as `{name}` or `{name:<pattern>}`;
     * this method uses `FastRoute\RouteParser\Std` to search for the best route
     * match based on the available substitutions and generates a uri.
     *
     * @param string $name Route name.
     * @param array $parameters Key/value option pairs to pass to the router for
     * purposes of generating a URI; takes precedence over options present
     * in route used to generate URI.
     *
     * @return string URI path generated.
     * @throws \RuntimeException if the route name is not known or a parameter value does not match its regex.
     */
    public function generate(string $name, array $parameters = []): string
    {
        // Inject any pending route items
        $this->injectItems();

        $route = $this->getRoute($name);

        $parsedRoutes = array_reverse($this->routeParser->parse($route->getPattern()));
        if ($parsedRoutes === []) {
            throw new RouteNotFoundException($name);
        }

        $missingParameters = [];

        // One route pattern can correspond to multiple routes if it has optional parts
        foreach ($parsedRoutes as $parsedRouteParts) {
            // Check if all parameters can be substituted
            $missingParameters = $this->missingParameters($parsedRouteParts, $parameters);

            // If not all parameters can be substituted, try the next route
            if (!empty($missingParameters)) {
                continue;
            }

            return $this->generatePath($parameters, $parsedRouteParts);
        }

        // No valid route was found: list minimal required parameters
        throw new \RuntimeException(sprintf(
            'Route `%s` expects at least parameter values for [%s], but received [%s]',
            $name,
            implode(',', $missingParameters),
            implode(',', array_keys($parameters))
        ));
    }

    /**
     * Generates absolute URL from named route and parameters
     *
     * @param string $name name of the route
     * @param array $parameters parameter-value set
     * @param string|null $scheme host scheme
     * @param string|null $host host for manual setup
     * @return string URL generated
     * @throws RouteNotFoundException in case there is no route with the name specified
     */
    public function generateAbsolute(string $name, array $parameters = [], string $scheme = null, string $host = null): string
    {
        $url = $this->generate($name, $parameters);
        $route = $this->getRoute($name);
        $uri = $this->request !== null ? $this->request->getUri() : null;
        $lastRequestScheme = $uri !== null ? $uri->getScheme() : null;

        if ($host !== null || ($host = $route->getHost()) !== null) {
            if ($scheme === null && (strpos($host, '://') !== false || strpos($host, '//') === 0)) {
                return rtrim($host, '/') . $url;
            }

            if ($scheme === '' && $host !== '' && !(strpos($host, '://') !== false || strpos($host, '//') === 0)) {
                $host = '//' . $host;
            }
            return $this->ensureScheme(rtrim($host, '/') . $url, $scheme ?? $lastRequestScheme);
        }

        if ($uri !== null) {
            $port = $uri->getPort() === 80 || $uri->getPort() === null ? '' : ':' . $uri->getPort();
            return  $this->ensureScheme('://' . $uri->getHost() . $port . $url, $scheme ?? $lastRequestScheme);
        }

        return $url;
    }

    /**
     * Normalize URL by ensuring that it use specified scheme.
     *
     * If URL is relative or scheme is null, normalization is skipped.
     *
     * @param string $url the URL to process
     * @param string|null $scheme the URI scheme used in URL (e.g. `http` or `https`). Use empty string to
     * create protocol-relative URL (e.g. `//example.com/path`)
     * @return string the processed URL
     */
    private function ensureScheme(string $url, ?string $scheme): string
    {
        if ($scheme === null || $this->isRelative($url)) {
            return $url;
        }

        if (strpos($url, '//') === 0) {
            // e.g. //example.com/path/to/resource
            return $scheme === '' ? $url : "$scheme:$url";
        }

        if (($pos = strpos($url, '://')) !== false) {
            if ($scheme === '') {
                $url = substr($url, $pos + 1);
            } else {
                $url = $scheme . substr($url, $pos);
            }
        }

        return $url;
    }

    /**
     * Returns a value indicating whether a URL is relative.
     * A relative URL does not have host info part.
     * @param string $url the URL to be checked
     * @return bool whether the URL is relative
     */
    private function isRelative(string $url): bool
    {
        return strncmp($url, '//', 2) && strpos($url, '://') === false;
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
     * Checks for any missing route parameters
     * @param array $parts
     * @param array $substitutions
     * @return array with minimum required parameters if any are missing or an empty array if none are missing
     */
    private function missingParameters(array $parts, array $substitutions): array
    {
        $missingParameters = [];

        // Gather required parameters
        foreach ($parts as $part) {
            if (is_string($part)) {
                continue;
            }

            $missingParameters[] = $part[0];
        }

        // Check if all parameters exist
        foreach ($missingParameters as $parameter) {
            if (!array_key_exists($parameter, $substitutions)) {
                // Return the parameters so they can be used in an
                // exception if needed
                return $missingParameters;
            }
        }

        // All required parameters are available
        return [];
    }

    /**
     * Retrieve the dispatcher instance.
     *
     * Uses the callable factory in $dispatcherCallback, passing it $data
     * (which should be derived from the router's getData() method); this
     * approach is done to allow testing against the dispatcher.
     *
     * @param array|object $data Data from RouteCollection::getData()
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
        [, $path, $parameters] = $result;

        /* @var Route $route */
        $route = array_reduce(
            $this->routes,
            static function ($matched, Route $route) use ($path, $method) {
                if ($matched) {
                    return $matched;
                }

                if ($path !== $route->getPattern()) {
                    return $matched;
                }

                if (!in_array($method, $route->getMethods(), true)) {
                    return $matched;
                }

                return $route;
            },
            false
        );

        if (false === $route) {
            return $this->marshalMethodNotAllowedResult($result);
        }

        $parameters = array_merge($route->getDefaults(), $parameters);
        $this->currentRoute = $route;

        return MatchingResult::fromSuccess($route, $parameters);
    }

    /**
     * Inject queued items into the underlying router
     */
    private function injectItems(): void
    {
        foreach ($this->items as $index => $item) {
            $this->injectItem($item);
            unset($this->items[$index]);
        }
    }

    /**
     * Inject an item into the underlying router
     * @param Route|Group $route
     */
    private function injectItem($route): void
    {
        if ($route instanceof Group) {
            $this->injectGroup($route);
            return;
        }

        // Filling the routes' hash-map is required by the `generateUri` method
        $this->routes[$route->getName()] = $route;

        // Skip feeding FastRoute collector if valid cached data was already loaded
        if ($this->hasCache) {
            return;
        }

        $this->router->addRoute($route->getMethods(), $route->getPattern(), $route->getPattern());
    }

    /**
     * Inject a Group instance into the underlying router.
     */
    private function injectGroup(Group $group, RouteCollector $collector = null, string $prefix = ''): void
    {
        if ($collector === null) {
            $collector = $this->router;
        }

        $collector->addGroup(
            $group->getPrefix(),
            function (RouteCollector $r) use ($group, $prefix) {
                $prefix .= $group->getPrefix();
                foreach ($group->items as $index => $item) {
                    if ($item instanceof Group) {
                        $this->injectGroup($item, $r, $prefix);
                        continue;
                    }

                    /** @var Route $modifiedItem */
                    $modifiedItem = $item->pattern($prefix . $item->getPattern());

                    $groupMiddlewares = $group->getMiddlewares();

                    for (end($groupMiddlewares); key($groupMiddlewares) !== null; prev($groupMiddlewares)) {
                        $modifiedItem = $modifiedItem->addMiddleware(current($groupMiddlewares));
                    }

                    // Filling the routes' hash-map is required by the `generateUri` method
                    $this->routes[$modifiedItem->getName()] = $modifiedItem;

                    // Skip feeding FastRoute collector if valid cached data was already loaded
                    if ($this->hasCache) {
                        continue;
                    }

                    $r->addRoute($item->getMethods(), $item->getPattern(), $modifiedItem->getPattern());
                }
            }
        );
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

        $dispatchData = (array)$this->router->getData();

        if ($this->cacheEnabled) {
            $this->cacheDispatchData($dispatchData);
        }

        return $dispatchData;
    }

    /**
     * Load dispatch data from cache
     * @throws \RuntimeException If the cache file contains invalid data
     */
    private function loadDispatchData(): void
    {
        set_error_handler(
            static function () {
            },
            E_WARNING
        ); // suppress php warnings
        $dispatchData = include $this->cacheFile;
        restore_error_handler();

        // Cache file does not exist
        if (false === $dispatchData) {
            return;
        }

        if (!is_array($dispatchData)) {
            throw new \RuntimeException(
                sprintf(
                    'Invalid cache file "%s"; cache file MUST return an array',
                    $this->cacheFile
                )
            );
        }

        $this->hasCache = true;
        $this->dispatchData = $dispatchData;
    }

    /**
     * Save dispatch data to cache
     * @param array $dispatchData
     * @return int|false bytes written to file or false if error
     * @throws \RuntimeException If the cache directory does not exist.
     * @throws \RuntimeException If the cache directory is not writable.
     * @throws \RuntimeException If the cache file exists but is not writable
     */
    private function cacheDispatchData(array $dispatchData)
    {
        $cacheDir = dirname($this->cacheFile);

        if (!is_dir($cacheDir)) {
            throw new \RuntimeException(
                sprintf(
                    'The cache directory "%s" does not exist',
                    $cacheDir
                )
            );
        }

        if (!is_writable($cacheDir)) {
            throw new \RuntimeException(
                sprintf(
                    'The cache directory "%s" is not writable',
                    $cacheDir
                )
            );
        }

        if (file_exists($this->cacheFile) && !is_writable($this->cacheFile)) {
            throw new \RuntimeException(
                sprintf(
                    'The cache file %s is not writable',
                    $this->cacheFile
                )
            );
        }

        return file_put_contents(
            $this->cacheFile,
            sprintf(self::CACHE_TEMPLATE, var_export($dispatchData, true)),
            LOCK_EX
        );
    }

    private function marshalMethodNotAllowedResult(array $result): MatchingResult
    {
        $path = $result[1];

        $allowedMethods = array_unique(
            array_reduce(
                $this->routes,
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
     * @param string $name
     * @return Route
     */
    private function getRoute(string $name): Route
    {
        if (!array_key_exists($name, $this->routes)) {
            throw new RouteNotFoundException($name);
        }

        return $this->routes[$name];
    }

    /**
     * @param array $parameters
     * @param array $parts
     * @return string
     */
    private function generatePath(array $parameters, array $parts): string
    {
        $notSubstitutedParams = $parameters;
        $path = $this->getUriPrefix();

        foreach ($parts as $part) {
            if (is_string($part)) {
                // Append the string
                $path .= $part;
                continue;
            }

            // Check substitute value with regex
            $pattern = str_replace('~', '\~', $part[1]);
            if (preg_match('~^' . $pattern . '$~', (string)$parameters[$part[0]]) === 0) {
                throw new \RuntimeException(
                    sprintf(
                        'Parameter value for [%s] did not match the regex `%s`',
                        $part[0],
                        $part[1]
                    )
                );
            }

            // Append the substituted value
            $path .= $parameters[$part[0]];
            unset($notSubstitutedParams[$part[0]]);
        }

        return $path . ($notSubstitutedParams !== [] ? '?' . http_build_query($notSubstitutedParams) : '');
    }
}
