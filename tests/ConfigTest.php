<?php

declare(strict_types=1);

namespace Yiisoft\Router\FastRoute\Tests;

use PHPUnit\Framework\TestCase;
use Yiisoft\Di\Container;
use Yiisoft\Di\ContainerConfig;
use Yiisoft\Router\FastRoute\UrlGenerator;
use Yiisoft\Router\FastRoute\UrlMatcher;
use Yiisoft\Router\RouteCollectionInterface;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Router\UrlMatcherInterface;

final class ConfigTest extends TestCase
{
    public function testDi(): void
    {
        $container = $this->createContainer();

        $urlGenerator = $container->get(UrlGeneratorInterface::class);

        $this->assertInstanceOf(UrlGenerator::class, $urlGenerator);
    }

    public function testDiWeb(): void
    {
        $container = $this->createContainer('web');

        $urlMatcher = $container->get(UrlMatcherInterface::class);

        $this->assertInstanceOf(UrlMatcher::class, $urlMatcher);
    }

    private function createContainer(?string $postfix = null): Container
    {
        return new Container(
            ContainerConfig::create()->withDefinitions(
                $this->getDiConfig($postfix)
                +
                [
                    RouteCollectionInterface::class => $this->createMock(RouteCollectionInterface::class),
                ]
            )
        );
    }

    private function getDiConfig(?string $postfix = null): array
    {
        $params = $this->getParams();
        return require dirname(__DIR__) . '/config/di' . ($postfix !== null ? '-' . $postfix : '') . '.php';
    }

    private function getParams(): array
    {
        return require dirname(__DIR__) . '/config/params.php';
    }
}
