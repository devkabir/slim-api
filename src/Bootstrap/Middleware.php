<?php

declare(strict_types=1);

namespace App\Bootstrap;

use Slim\App;
use App\Config\AppConfig;
use Psr\Log\LoggerInterface;
use App\Handlers\HttpErrorHandler;
use App\Middleware\CorsMiddleware;
use Psr\Container\ContainerInterface;
use App\Middleware\JsonBodyParserMiddleware;

class Middleware
{
    public static function register(App $app, ContainerInterface $container): void
    {
        $app->addRoutingMiddleware();
        $app->add(new JsonBodyParserMiddleware());
        $app->add(new CorsMiddleware());

        $displayErrorDetails = AppConfig::isDebug();
        $logErrors           = true;
        $logErrorDetails     = true;

        $logger           = $container->get(LoggerInterface::class);
        $callableResolver = $app->getCallableResolver();
        $responseFactory  = $app->getResponseFactory();

        $errorHandler = new HttpErrorHandler($callableResolver, $responseFactory, $logger);

        $errorMiddleware = $app->addErrorMiddleware($displayErrorDetails, $logErrors, $logErrorDetails, $logger);
        $errorMiddleware->setDefaultErrorHandler($errorHandler);
    }
}
