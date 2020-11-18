<p align="center">
    <a href="https://github.com/yiisoft" target="_blank">
        <img src="https://avatars0.githubusercontent.com/u/993323" height="100px">
    </a>
    <h1 align="center">Yii Router FastRoute adapter</h1>
    <br>
</p>

The package provides FastRoute adapter for [Yii Router](https://github.com/yiisoft/router).

[![Latest Stable Version](https://poser.pugx.org/yiisoft/router-fastroute/v/stable.png)](https://packagist.org/packages/yiisoft/router-fastroute)
[![Total Downloads](https://poser.pugx.org/yiisoft/router-fastroute/downloads.png)](https://packagist.org/packages/yiisoft/router-fastroute)
[![Build status](https://github.com/yiisoft/router-fastroute/workflows/build/badge.svg)](https://github.com/yiisoft/router-fastroute/actions?query=workflow%3Abuild)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/yiisoft/router-fastroute/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/yiisoft/router-fastroute/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/yiisoft/router-fastroute/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/yiisoft/router-fastroute/?branch=master)
[![Mutation testing badge](https://img.shields.io/endpoint?style=flat&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fyiisoft%2Frouter-fastroute%2Fmaster)](https://dashboard.stryker-mutator.io/reports/github.com/yiisoft/router-fastroute/master)
[![static analysis](https://github.com/yiisoft/router-fastroute/workflows/static%20analysis/badge.svg)](https://github.com/yiisoft/router-fastroute/actions?query=workflow%3A%22static+analysis%22)
[![type-coverage](https://shepherd.dev/github/yiisoft/router-fastroute/coverage.svg)](https://shepherd.dev/github/yiisoft/router-fastroute)

## General usage

Router instance could be obtained like the following:

```php
use Yiisoft\Router\FastRoute\FastRouteFactory;

$factory = new FastRouteFactory();
$router = $factory();
```

## Custom route factory

If you need to make custom route factory you can do something like the following:

```php
namespace App\Factory;

use Psr\Container\ContainerInterface;
use Yiisoft\Router\FastRoute\FastRouteFactory;
use Yiisoft\Router\Route;
use Yiisoft\Router\RouterFactory;
use Yiisoft\Router\RouterInterface;
use Yiisoft\Yii\Web\Middleware\ActionCaller;
use App\Controller\SiteController;

class RouteFactory
{
    public function __invoke(ContainerInterface $container): RouterInterface
    {
        $routes = [
            Route::get('/', [SiteController::class, 'index'], $container)->name('site/index'),
            Route::get('/about', [SiteController::class, 'about'], $container)->name('site/about'),
        ];

        return (new RouterFactory(new FastRouteFactory(), $routes))($container);
    }
}
```

setting up your container

```php
use App\Factory\RouteFactory;
use Yiisoft\Router\RouterInterface;

return [
    /** 
     * ...
     * There other container configuration. 
     * ...
     */

    RouterInterface::class => new RouteFactory(),
];
```

### Unit testing

The package is tested with [PHPUnit](https://phpunit.de/). To run tests:

```shell
./vendor/bin/phpunit
```

### Mutation testing

The package tests are checked with [Infection](https://infection.github.io/) mutation framework. To run it:

```shell
./vendor/bin/infection
```

### Static analysis

The code is statically analyzed with [Psalm](https://psalm.dev/). To run static analysis:

```shell
./vendor/bin/psalm
```

### Support the project

[![Open Collective](https://img.shields.io/badge/Open%20Collective-sponsor-7eadf1?logo=open%20collective&logoColor=7eadf1&labelColor=555555)](https://opencollective.com/yiisoft)

### Follow updates

[![Official website](https://img.shields.io/badge/Powered_by-Yii_Framework-green.svg?style=flat)](https://www.yiiframework.com/)
[![Twitter](https://img.shields.io/badge/twitter-follow-1DA1F2?logo=twitter&logoColor=1DA1F2&labelColor=555555?style=flat)](https://twitter.com/yiiframework)
[![Telegram](https://img.shields.io/badge/telegram-join-1DA1F2?style=flat&logo=telegram)](https://t.me/yii3en)
[![Facebook](https://img.shields.io/badge/facebook-join-1DA1F2?style=flat&logo=facebook&logoColor=ffffff)](https://www.facebook.com/groups/yiitalk)
[![Slack](https://img.shields.io/badge/slack-join-1DA1F2?style=flat&logo=slack)](https://yiiframework.com/go/slack)

## License

The Yii Router FastRoute adapter is free software. It is released under the terms of the BSD License.
Please see [`LICENSE`](./LICENSE.md) for more information.

Maintained by [Yii Software](https://www.yiiframework.com/).
