<?php

declare(strict_types=1);

use Yiisoft\Router\FastRoute\UrlGenerator;
use Yiisoft\Router\FastRoute\UrlMatcher;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Router\UrlMatcherInterface;

return [
    UrlGeneratorInterface::class => UrlGenerator::class,
    UrlMatcherInterface::class => UrlMatcher::class,
];
