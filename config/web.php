<?php

use Yiisoft\Router\FastRoute\UrlGenerator;
use Yiisoft\Router\FastRoute\UrlMatcher;
use Yiisoft\Router\UrlGeneratorInterface;
use Yiisoft\Router\UrlMatcherInterface;

return [
    UrlMatcherInterface::class => UrlMatcher::class,
    UrlGeneratorInterface::class => UrlGenerator::class,
];
