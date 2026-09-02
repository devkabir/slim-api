<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Config\Settings;
use App\Handlers\HttpErrorHandler;
use App\Middleware\CorsMiddleware;
use App\Middleware\HttpsEnforcementMiddleware;
use App\Middleware\JsonBodyParserMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Middleware\RequestIdMiddleware;
use App\Middleware\SecurityHeadersMiddleware;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Log\LoggerInterface;
use Slim\App;

final class Middleware
{
    public static function register(App $app, ContainerInterface $container): void
    {
        $app->addRoutingMiddleware();

        $settings         = $container->get(Settings::class);
        $logger           = $container->get(LoggerInterface::class);
        $responseFactory  = $container->get(ResponseFactoryInterface::class);
        $callableResolver = $app->getCallableResolver();

        $app->add($container->get(JsonBodyParserMiddleware::class));
        $app->add($container->get(CorsMiddleware::class));

        $errorHandler    = new HttpErrorHandler($callableResolver, $responseFactory, $logger, $settings->isProduction());
        $errorMiddleware = $app->addErrorMiddleware($settings->isDebug(), true, true, $logger);
        $errorMiddleware->setDefaultErrorHandler($errorHandler);

        // Security headers, rate limiting, request ID, and HTTPS enforcement
        $app->add($container->get(RateLimitMiddleware::class));
        $app->add($container->get(SecurityHeadersMiddleware::class));
        $app->add($container->get(RequestIdMiddleware::class));
        $app->add($container->get(HttpsEnforcementMiddleware::class));
    }
}
