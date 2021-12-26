<?php

declare(strict_types=1);

namespace Yiisoft\Router\FastRoute\Tests\Support;

use FastRoute\RouteParser;

final class NotFoundRouteParser implements RouteParser
{
    public function parse($route)
    {
        return [];
    }
}
