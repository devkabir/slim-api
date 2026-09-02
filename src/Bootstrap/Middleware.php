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
use App\Middleware\SecurityHeadersMiddleware;
use App\Middleware\HttpsEnforcementMiddleware;

class Middleware
{
    public static function register(App $app, ContainerInterface $container): void
    {
        $app->addRoutingMiddleware();
        $app->add(new JsonBodyParserMiddleware());
        $app->add(new CorsMiddleware());

        $displayErrorDetails = AppConfig::isDebug();

        $logger           = $container->get(LoggerInterface::class);
        $callableResolver = $app->getCallableResolver();
        $responseFactory  = $app->getResponseFactory();

        $errorHandler = new HttpErrorHandler($callableResolver, $responseFactory, $logger);

        $errorMiddleware = $app->addErrorMiddleware($displayErrorDetails, true, true, $logger);
        $errorMiddleware->setDefaultErrorHandler($errorHandler);

        // Security headers and HTTPS enforcement
        $app->add(new SecurityHeadersMiddleware());
        $app->add(new HttpsEnforcementMiddleware($responseFactory));
    }
}
