<?php

declare(strict_types=1);

namespace App;

use App\Bootstrap\Bootstrap;
use App\Bootstrap\ContainerFactory;
use App\Bootstrap\Middleware;
use App\Bootstrap\Routes;
use App\Config\Settings;
use Psr\Container\ContainerInterface;
use Slim\App as SlimApp;
use Slim\Factory\AppFactory;

final class App
{
    public static function bootstrap(?Settings $settings = null): SlimApp
    {
        $settings  = Bootstrap::init($settings);
        $container = ContainerFactory::create($settings);

        return self::create($container);
    }

    public static function create(?ContainerInterface $container = null, ?Settings $settings = null): SlimApp
    {
        if ($container === null) {
            $settings ??= Settings::fromEnv();
            $container = ContainerFactory::create($settings);
        }

        $app = AppFactory::createFromContainer($container);

        Middleware::register($app, $container);
        Routes::register($app);

        return $app;
    }
}
