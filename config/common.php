<?php

use Yiisoft\Router\UrlMatcherInterface;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Router\FastRoute\UrlMatcher;
use Yiisoft\Router\FastRoute\UrlGenerator;

return [
    UrlMatcherInterface::class => UrlMatcher::class,
    UrlGeneratorInterface::class => UrlGenerator::class,
];
