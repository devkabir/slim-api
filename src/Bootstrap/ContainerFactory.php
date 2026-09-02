<?php

declare(strict_types=1);

namespace App\Bootstrap;

use PDO;
use DI\Container;
use App\Config\Database;
use DI\ContainerBuilder;
use App\Config\AppLogger;
use Psr\Log\LoggerInterface;
use App\Response\ApiResponse;
use App\Services\TodoService;
use App\Controllers\TodoController;
use App\Repositories\TodoRepository;
use App\Controllers\HealthController;
use Slim\Psr7\Factory\ResponseFactory;
use Psr\Http\Message\ResponseFactoryInterface;

use function DI\factory;
use function DI\autowire;

class ContainerFactory
{
    public static function create(): Container
    {
        $builder = new ContainerBuilder();

        $builder->addDefinitions([
            LoggerInterface::class => factory(fn() => AppLogger::getLogger()),

            ResponseFactoryInterface::class => autowire(ResponseFactory::class),

            PDO::class => factory(fn() => Database::getConnection()),

            ApiResponse::class      => autowire(ApiResponse::class),
            TodoRepository::class   => autowire(TodoRepository::class),
            TodoService::class      => autowire(TodoService::class),
            TodoController::class   => autowire(TodoController::class),
            HealthController::class => autowire(HealthController::class),
        ]);

        return $builder->build();
    }
}
