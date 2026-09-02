<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Config\Settings;
use App\Handlers\HttpErrorHandler;
use App\Middleware\HttpsEnforcementMiddleware;
use App\Middleware\MethodOverrideMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Middleware\RequestBodyGuardMiddleware;
use App\Middleware\RequestIdMiddleware;
use App\Middleware\ResponseDecorationMiddleware;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Log\LoggerInterface;
use Slim\App;
use Slim\Exception\HttpBadRequestException;
use Slim\Middleware\BodyParsingMiddleware;

final class Middleware
{
    public static function register(App $app, ContainerInterface $container): void
    {
        $settings         = $container->get(Settings::class);
        $logger           = $container->get(LoggerInterface::class);
        $responseFactory  = $container->get(ResponseFactoryInterface::class);
        $callableResolver = $app->getCallableResolver();

        // 1. Strict Body Parsing Middleware (executes just before route actions)
        $bodyParsingMiddleware = new BodyParsingMiddleware();
        $bodyParsingMiddleware->registerParser('application/json', function (string $input): mixed {
            $trimmed = trim($input);
            if ($trimmed === '') {
                return [];
            }

            $parsed = json_decode($input, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException('Malformed JSON syntax: ' . json_last_error_msg() . '.', 400);
            }

            if (! is_array($parsed) || str_starts_with($trimmed, '[') || (! empty($parsed) && array_is_list($parsed))) {
                throw new \RuntimeException('The request body must be a top-level JSON object.', 400);
            }

            return $parsed;
        });
        $app->add($bodyParsingMiddleware);

        // 2. Request body guard for mutation requests
        $app->add($container->get(RequestBodyGuardMiddleware::class));

        // 3. Native Slim routing middleware
        $app->addRoutingMiddleware();

        // 4. Rate limiting middleware
        $app->add($container->get(RateLimitMiddleware::class));

        // 5. HTTP method override header support (POST -> PUT/PATCH/DELETE)
        $app->add($container->get(MethodOverrideMiddleware::class));

        // 6. HTTPS enforcement middleware
        $app->add($container->get(HttpsEnforcementMiddleware::class));

        // 7. Centralized error handling middleware
        $errorHandler    = new HttpErrorHandler($callableResolver, $responseFactory, $logger, $settings->isProduction());
        $errorMiddleware = $app->addErrorMiddleware($settings->isDebug(), true, true, $logger);
        $errorMiddleware->setDefaultErrorHandler($errorHandler);

        // 8. Consolidated CORS & security response decoration middleware
        $app->add($container->get(ResponseDecorationMiddleware::class));

        // 9. Request ID generation and logging correlation (executes first)
        $app->add($container->get(RequestIdMiddleware::class));
    }
}
