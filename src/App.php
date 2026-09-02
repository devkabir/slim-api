<?php

declare(strict_types=1);

namespace App;

use Slim\App as SlimApp;
use App\Config\AppConfig;
use App\Bootstrap\Routes;
use App\Bootstrap\Bootstrap;
use Slim\Factory\AppFactory;
use App\Bootstrap\Middleware;
use App\Bootstrap\ContainerFactory;

class App
{
    public static function bootstrap(): SlimApp
    {
        Bootstrap::init();

        return self::create();
    }

    public static function create(): SlimApp
    {
        AppConfig::validateProductionConfig();

        $container = ContainerFactory::create();
        $app       = AppFactory::createFromContainer($container);

        Middleware::register($app, $container);
        Routes::register($app);

        return $app;
    }
}
