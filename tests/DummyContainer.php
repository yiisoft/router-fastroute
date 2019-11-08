<?php

namespace Yiisoft\Router\FastRoute\Tests;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

class DummyContainer implements ContainerInterface
{
    /**
     * @inheritDoc
     */
    public function get($id)
    {
        // passed for tests
    }

    /**
     * @inheritDoc
     */
    public function has($id)
    {
        // passed for tests
    }
}