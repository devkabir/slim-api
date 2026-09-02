<?php

declare(strict_types=1);

namespace App\Bootstrap;

use PDO;
use DI\Container;
use DI\ContainerBuilder;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use App\Config\Settings;
use App\Config\Database;
use App\Config\Cache;
use App\Config\AppLogger;
use App\Response\ApiResponse;
use App\Repositories\TodoRepository;
use App\Services\TodoService;
use App\Controllers\TodoController;
use App\Controllers\HealthController;
use App\Middleware\CorsMiddleware;
use App\Middleware\HttpsEnforcementMiddleware;
use App\Middleware\JsonBodyParserMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Middleware\RequestIdMiddleware;
use App\Middleware\SecurityHeadersMiddleware;

use function DI\factory;
use function DI\autowire;

final class ContainerFactory
{
    public static function create(?Settings $settings = null): Container
    {
        $settings ??= Settings::fromEnv();

        $builder = new ContainerBuilder();

        $builder->addDefinitions([
            // Immutable application settings
            Settings::class => $settings,

            // PSR interfaces & Core infrastructure
            ResponseFactoryInterface::class => autowire(ResponseFactory::class),
            LoggerInterface::class          => factory(fn(Settings $s) => AppLogger::createLogger($s)),
            PDO::class                      => factory(fn(Settings $s, LoggerInterface $logger) => Database::createConnection($s, $logger)),

            // Caching & Response Envelope
            Cache::class       => autowire(Cache::class),
            ApiResponse::class => autowire(ApiResponse::class),

            // Repositories & Services
            TodoRepository::class => autowire(TodoRepository::class),
            TodoService::class    => autowire(TodoService::class),

            // Controllers / Actions
            TodoController::class   => autowire(TodoController::class),
            HealthController::class => autowire(HealthController::class),

            // Middleware
            CorsMiddleware::class             => autowire(CorsMiddleware::class),
            HttpsEnforcementMiddleware::class => autowire(HttpsEnforcementMiddleware::class),
            JsonBodyParserMiddleware::class   => autowire(JsonBodyParserMiddleware::class),
            RateLimitMiddleware::class        => autowire(RateLimitMiddleware::class),
            RequestIdMiddleware::class        => autowire(RequestIdMiddleware::class),
            SecurityHeadersMiddleware::class  => autowire(SecurityHeadersMiddleware::class),
        ]);

        return $builder->build();
    }
}
