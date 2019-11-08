<?php

namespace Yiisoft\Router\FastRoute;

use Throwable;

// TODO maybe it should be in yiisoft/router?
class RouteNotFoundException extends \RuntimeException
{
    public function __construct($routeName = '', $code = 0, Throwable $previous = null)
    {
        $message = sprintf(
            'Cannot generate URI for route "%s"; route not found',
            $routeName
        );
        parent::__construct($message, $code, $previous);
    }
}
