<?php

declare(strict_types=1);

namespace Yiisoft\Router\FastRoute;

use Yiisoft\Router\Group;
use Yiisoft\Router\Route;
use Yiisoft\Router\RouteCollectorInterface;
use Yiisoft\Router\RouteNotFoundException;
use Yiisoft\Router\UrlMatcherInterface;
use Yiisoft\Router\UrlGeneratorInterface;
use FastRoute\RouteParser;

use function array_key_exists;
use function array_keys;
use function implode;
use function is_string;
use function preg_match;

class UrlGenerator implements UrlGeneratorInterface
{
    /** @var string */
    private $uriPrefix = '';

    /**
     * All attached routes as Route instances
     *
     * @var Route[]
     */
    private $routes = [];

    /**
     * @var UrlMatcherInterface $matcher
     */
    private $matcher;

    /**
     * @var RouteCollectorInterface $collector
     */
    private $collector;

    /**
     * @var RouteParser
     */
    private $routeParser;

    /**
     * Constructor
     *
     * @param UrlMatcherInterface $matcher url matcher
     * @param RouteCollectorInterface $collector route collector
     */
    public function __construct(
        UrlMatcherInterface $matcher,
        RouteCollectorInterface $collector
    ) {
        $this->matcher = $matcher;
        $this->collector = $collector;
        $this->routeParser = new RouteParser\Std();
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
        $uri = $this->matcher->getLastMatchedRequest() !== null ? $this->matcher->getLastMatchedRequest()->getUri() : null;
        $lastRequestScheme = $uri !== null ? $uri->getScheme() : null;

        if ($host !== null || ($host = $route->getHost()) !== null) {
            if ($scheme === null && !$this->isRelative($host)) {
                return rtrim($host, '/') . $url;
            }

            if ($scheme === '' && $host !== '' && $this->isRelative($host)) {
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


    public function getUriPrefix(): string
    {
        return $this->uriPrefix;
    }

    public function setUriPrefix(string $prefix): void
    {
        $this->uriPrefix = $prefix;
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

    /**
     * Inject queued items into the underlying router
     */
    private function injectItems(): void
    {
        if ($this->routes === []) {
            $items = $this->collector->getItems();
            foreach ($items as $index => $item) {
                $this->injectItem($item);
            }
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

        $this->routes[$route->getName()] = $route;
    }

    /**
     * Inject a Group instance into the underlying router.
     */
    private function injectGroup(Group $group, string $prefix = ''): void
    {
        $prefix .= $group->getPrefix();
        /** @var $items Group[]|Route[]*/
        $items = $group->getItems();
        foreach ($items as $index => $item) {
            if ($item instanceof Group) {
                $this->injectGroup($item, $prefix);
                continue;
            }

            /** @var Route $modifiedItem */
            $modifiedItem = $item->pattern($prefix . $item->getPattern());
            $this->routes[$modifiedItem->getName()] = $modifiedItem;
        }
    }
}
