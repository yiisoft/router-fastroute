<?php

namespace Yiisoft\Router\FastRoute\Tests;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Yiisoft\Router\DispatcherInterface;

class DummyDispatcher implements DispatcherInterface
{
    public function dispatch(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        return $handler->handle($request);
    }

    public function withMiddlewares(array $middlewares): DispatcherInterface
    {
        return $this;
    }
}
