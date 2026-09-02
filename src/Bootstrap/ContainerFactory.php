<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Actions\Health\LivenessAction;
use App\Actions\Health\ReadinessAction;
use App\Actions\Home\ApiInfoAction;
use App\Actions\Todo\CreateTodoAction;
use App\Actions\Todo\DeleteTodoAction;
use App\Actions\Todo\GetTodoAction;
use App\Actions\Todo\ListTodosAction;
use App\Actions\Todo\UpdateTodoAction;
use App\Config\AppLogger;
use App\Config\Cache;
use App\Config\CachePoolFactory;
use App\Config\Database;
use App\Config\Settings;
use App\Health\CacheReadinessCheck;
use App\Health\DatabaseReadinessCheck;
use App\Health\ReadinessRunner;
use App\Middleware\CorsMiddleware;
use App\Middleware\HttpsEnforcementMiddleware;
use App\Middleware\JsonBodyParserMiddleware;
use App\Middleware\MethodOverrideMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Middleware\ReadinessAuthMiddleware;
use App\Middleware\RequestBodyGuardMiddleware;
use App\Middleware\RequestIdMiddleware;
use App\Middleware\ResponseDecorationMiddleware;
use App\Middleware\SecurityHeadersMiddleware;
use App\Repositories\DoctrineTodoRepository;
use App\Repositories\TodoRepository;
use App\Repositories\TodoRepositoryInterface;
use App\Response\ApiResponse;
use App\Services\CacheService;
use App\Services\TodoService;
use DI\Container;
use DI\ContainerBuilder;
use Doctrine\DBAL\Connection;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Log\LoggerInterface;
use Slim\Interfaces\RouteParserInterface;
use Slim\Psr7\Factory\ResponseFactory;

use function DI\autowire;
use function DI\factory;

final class ContainerFactory
{
    public static function create(?Settings $settings = null): Container
    {
        $settings ??= Settings::fromEnv();

        $builder = new ContainerBuilder();

        $builder->addDefinitions([
            // Immutable application settings
            Settings::class => $settings,

            // Core PSR interfaces
            ResponseFactoryInterface::class => autowire(ResponseFactory::class),
            LoggerInterface::class          => factory(fn(Settings $s) => AppLogger::createLogger($s)),

            // Database connection via Doctrine DBAL
            Connection::class => factory(fn(Settings $s, LoggerInterface $logger) => Database::createConnection($s, $logger)),

            // PSR-6 Cache pool & Cache service
            CacheItemPoolInterface::class => factory(fn(Settings $s, LoggerInterface $logger) => CachePoolFactory::createPool($s, $logger)),
            CacheService::class           => autowire(CacheService::class),
            Cache::class                  => autowire(Cache::class),

            // Standardized API Response
            ApiResponse::class => autowire(ApiResponse::class),

            // Persistence & Domain services
            TodoRepositoryInterface::class => autowire(DoctrineTodoRepository::class),
            DoctrineTodoRepository::class  => autowire(DoctrineTodoRepository::class),
            TodoRepository::class          => autowire(TodoRepository::class),
            TodoService::class             => autowire(TodoService::class),

            // Health Readiness checks & runner
            DatabaseReadinessCheck::class => autowire(DatabaseReadinessCheck::class),
            CacheReadinessCheck::class    => autowire(CacheReadinessCheck::class),
            ReadinessRunner::class        => factory(function (ContainerInterface $c) {
                return new ReadinessRunner([
                    $c->get(DatabaseReadinessCheck::class),
                    $c->get(CacheReadinessCheck::class),
                ]);
            }),

            // Actions
            ApiInfoAction::class   => autowire(ApiInfoAction::class),
            LivenessAction::class  => autowire(LivenessAction::class),
            ReadinessAction::class => autowire(ReadinessAction::class),
            ListTodosAction::class => autowire(ListTodosAction::class),
            GetTodoAction::class   => autowire(GetTodoAction::class),
            CreateTodoAction::class => autowire(CreateTodoAction::class),
            UpdateTodoAction::class => autowire(UpdateTodoAction::class),
            DeleteTodoAction::class => autowire(DeleteTodoAction::class),

            // Middleware
            RequestIdMiddleware::class          => autowire(RequestIdMiddleware::class),
            ResponseDecorationMiddleware::class => autowire(ResponseDecorationMiddleware::class),
            HttpsEnforcementMiddleware::class   => autowire(HttpsEnforcementMiddleware::class),
            MethodOverrideMiddleware::class     => autowire(MethodOverrideMiddleware::class),
            RateLimitMiddleware::class          => factory(function (ContainerInterface $c) {
                $settings        = $c->get(Settings::class);
                $logger          = $c->get(LoggerInterface::class);
                $responseFactory = $c->get(ResponseFactoryInterface::class);
                $rateLimiterPool = CachePoolFactory::createRateLimiterPool($settings, $logger);

                return new RateLimitMiddleware($responseFactory, $settings, $rateLimiterPool);
            }),
            RequestBodyGuardMiddleware::class   => autowire(RequestBodyGuardMiddleware::class),
            ReadinessAuthMiddleware::class      => autowire(ReadinessAuthMiddleware::class),
            CorsMiddleware::class               => autowire(CorsMiddleware::class),
            SecurityHeadersMiddleware::class    => autowire(SecurityHeadersMiddleware::class),
            JsonBodyParserMiddleware::class     => autowire(JsonBodyParserMiddleware::class),
        ]);

        return $builder->build();
    }
}
