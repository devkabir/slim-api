<?php

declare(strict_types=1);

namespace App;

use Slim\App as SlimApp;
use App\Bootstrap\Routes;
use Slim\Factory\AppFactory;
use App\Bootstrap\Middleware;
use App\Bootstrap\ContainerFactory;

class App
{
    public static function create(): SlimApp
    {
        $container = ContainerFactory::create();
        $app       = AppFactory::createFromContainer($container);

        Middleware::register($app, $container);
        Routes::register($app);

        return $app;
    }
}
