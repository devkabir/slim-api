<?php

declare(strict_types=1);

namespace App;

use App\Bootstrap\Bootstrap;
use App\Bootstrap\ContainerFactory;
use App\Bootstrap\Middleware;
use App\Bootstrap\Routes;
use App\Config\Settings;
use DI\Container;
use Psr\Container\ContainerInterface;
use Slim\App as SlimApp;
use Slim\Factory\AppFactory;
use Slim\Interfaces\RouteParserInterface;

final class App
{
    public static function bootstrap(?Settings $settings = null): SlimApp
    {
        $settings  = Bootstrap::init($settings);
        $container = ContainerFactory::create($settings);

        return self::create($container, $settings);
    }

    public static function create(?ContainerInterface $container = null, ?Settings $settings = null): SlimApp
    {
        if ($container === null) {
            $settings ??= Settings::fromEnv();
            $container = ContainerFactory::create($settings);
        }

        $app = AppFactory::createFromContainer($container);

        if ($container instanceof Container) {
            $container->set(RouteParserInterface::class, $app->getRouteCollector()->getRouteParser());
        }

        if ($container->has(Settings::class)) {
            $appSettings = $container->get(Settings::class);

            if ($appSettings->basePath !== '') {
                $app->setBasePath($appSettings->basePath);
            }

            if ($appSettings->routeCacheEnabled && $appSettings->routeCacheFile !== null) {
                $cacheDir = dirname($appSettings->routeCacheFile);
                if (! is_dir($cacheDir)) {
                    @mkdir($cacheDir, 0775, true);
                }
                $app->getRouteCollector()->setCacheFile($appSettings->routeCacheFile);
            }
        }

        Middleware::register($app, $container);
        Routes::register($app);

        return $app;
    }
}
