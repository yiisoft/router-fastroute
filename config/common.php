<?php

declare(strict_types=1);

use Yiisoft\Router\FastRoute\UrlGenerator;
use Yiisoft\Router\UrlGeneratorInterface;

return [
    UrlGeneratorInterface::class => [
        '__class' => UrlGenerator::class,
        'setYii2Compat()' => [$params['yiisoft/router']['yii2Compat']],
    ],
];
