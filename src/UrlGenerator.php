<?php

declare(strict_types=1);

namespace Yiisoft\Router\FastRoute;

use FastRoute\RouteParser;
use Psr\Http\Message\UriInterface;
use RuntimeException;
use Yiisoft\Router\CurrentRoute;
use Yiisoft\Router\RouteCollectionInterface;
use Yiisoft\Router\RouteNotFoundException;
use Yiisoft\Router\UrlGeneratorInterface;

use function array_key_exists;
use function array_keys;
use function implode;
use function is_string;
use function preg_match;

final class UrlGenerator implements UrlGeneratorInterface
{
    private string $uriPrefix = '';
    private bool $encodeRaw = true;
    private array $locales = [];
    private ?string $localeParameterName = null;
    private RouteCollectionInterface $routeCollection;
    private ?CurrentRoute $currentRoute;
    private RouteParser $routeParser;

    public function __construct(
        RouteCollectionInterface $routeCollection,
        CurrentRoute $currentRoute = null,
        RouteParser $parser = null
    ) {
        $this->currentRoute = $currentRoute;
        $this->routeCollection = $routeCollection;
        $this->routeParser = $parser ?? new RouteParser\Std();
    }

    /**
     * {@inheritDoc}
     *
     * Replacements in FastRoute are written as `{name}` or `{name:<pattern>}`;
     * this method uses {@see RouteParser\Std} to search for the best route
     * match based on the available substitutions and generates a uri.
     *
     * @throws RuntimeException if parameter value does not match its regex.
     */
    public function generate(string $name, array $parameters = []): string
    {
        if (
            $this->localeParameterName !== null
            && isset($parameters[$this->localeParameterName])
            && $this->locales !== []
        ) {
            $locale = $parameters[$this->localeParameterName];
            $path = ($this->currentRoute !== null && $this->currentRoute->getUri() !== null)
                ? $this->currentRoute->getUri()->getPath()
                : '';
            if (isset($this->locales[$locale])) {
                return sprintf('/%s/%s', $locale, ltrim($path, '/'));
            }
        }
        $route = $this->routeCollection->getRoute($name);
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
        throw new RuntimeException(
            sprintf(
                'Route `%s` expects at least parameter values for [%s], but received [%s]',
                $name,
                implode(',', $missingParameters),
                implode(',', array_keys($parameters))
            )
        );
    }

    public function generateAbsolute(
        string $name,
        array $parameters = [],
        string $scheme = null,
        string $host = null
    ): string {
        $url = $this->generate($name, $parameters);
        $route = $this->routeCollection->getRoute($name);
        /** @var UriInterface $uri */
        $uri = $this->currentRoute && $this->currentRoute->getUri() !== null ? $this->currentRoute->getUri() : null;
        $lastRequestScheme = $uri !== null ? $uri->getScheme() : null;

        if ($host !== null || ($host = $route->getHost()) !== null) {
            if ($scheme === null && !$this->isRelative($host)) {
                return rtrim($host, '/') . $url;
            }

            if ((empty($scheme) || $lastRequestScheme === null) && $host !== '' && $this->isRelative($host)) {
                $host = '//' . $host;
            }

            return $this->ensureScheme(rtrim($host, '/') . $url, $scheme ?? $lastRequestScheme);
        }

        return $uri === null ? $url : $this->generateAbsoluteFromLastMatchedRequest($url, $uri, $scheme);
    }

    private function generateAbsoluteFromLastMatchedRequest(string $url, UriInterface $uri, ?string $scheme): string
    {
        $port = $uri->getPort() === 80 || $uri->getPort() === null ? '' : ':' . $uri->getPort();
        return $this->ensureScheme('://' . $uri->getHost() . $port . $url, $scheme ?? $uri->getScheme());
    }

    /**
     * Normalize URL by ensuring that it use specified scheme.
     *
     * If URL is relative or scheme is null, normalization is skipped.
     *
     * @param string $url the URL to process
     * @param string|null $scheme the URI scheme used in URL (e.g. `http` or `https`). Use empty string to
     * create protocol-relative URL (e.g. `//example.com/path`)
     *
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
     *
     * @param string $url the URL to be checked
     *
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

    public function setEncodeRaw(bool $encodeRaw): void
    {
        $this->encodeRaw = $encodeRaw;
    }

    public function setUriPrefix(string $name): void
    {
        $this->uriPrefix = $name;
    }

    public function getLocales(): array
    {
        return $this->locales;
    }

    public function setLocales(array $locales): void
    {
        $this->locales = $locales;
    }

    public function setLocaleParameterName(string $localeParameterName): void
    {
        $this->localeParameterName = $localeParameterName;
    }

    /**
     * Checks for any missing route parameters
     *
     * @param array $parts
     * @param array $substitutions
     *
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
                throw new RuntimeException(
                    sprintf(
                        'Parameter value for [%s] did not match the regex `%s`',
                        $part[0],
                        $part[1]
                    )
                );
            }

            // Append the substituted value
            $path .= $this->encodeRaw
                ? rawurlencode((string)$parameters[$part[0]])
                : urlencode((string)$parameters[$part[0]]);
            unset($notSubstitutedParams[$part[0]]);
        }

        return $path . ($notSubstitutedParams !== [] ? '?' . http_build_query($notSubstitutedParams) : '');
    }
}
