<?php

declare(strict_types=1);

use Yiisoft\Router\FastRoute\UrlGenerator;
use Yiisoft\Router\UrlGeneratorInterface;

/** @var array $params */

return [
    UrlGeneratorInterface::class => [
        'class' => UrlGenerator::class,
        '__construct()' => [
            'scheme' => $params['yiisoft/router-fastroute']['scheme'],
            'host' => $params['yiisoft/router-fastroute']['host'],
        ],
        'setEncodeRaw()' => [$params['yiisoft/router-fastroute']['encodeRaw']],
        'reset' => function () {
            $this->defaultArguments = [];
        },
    ],
];
