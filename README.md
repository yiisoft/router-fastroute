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
[![Build Status](https://travis-ci.com/yiisoft/router-fastroute.svg?branch=master)](https://travis-ci.com/yiisoft/router-fastroute)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/yiisoft/router-fastroute/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/yiisoft/router-fastroute/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/yiisoft/router-fastroute/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/yiisoft/router-fastroute/?branch=master)

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
