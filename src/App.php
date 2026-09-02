<?php

declare(strict_types=1);

namespace App;

use App\Config\AppConfig;
use App\Config\AppLogger;
use App\Controllers\HealthController;
use App\Controllers\TodoController;
use App\Handlers\HttpErrorHandler;
use App\Middleware\CorsMiddleware;
use App\Middleware\JsonBodyParserMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App as SlimApp;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;

class App
{
    public static function create(): SlimApp
    {
        $app = AppFactory::create();

        // Middleware pipeline
        $app->addRoutingMiddleware();
        $app->add(new JsonBodyParserMiddleware());
        $app->add(new CorsMiddleware());

        // Error handling configuration based on environment
        $displayErrorDetails = AppConfig::isDebug();
        $logErrors = true;
        $logErrorDetails = true;

        $logger = AppLogger::getLogger();
        $callableResolver = $app->getCallableResolver();
        $responseFactory = $app->getResponseFactory();

        $errorHandler = new HttpErrorHandler($callableResolver, $responseFactory, $logger);

        $errorMiddleware = $app->addErrorMiddleware($displayErrorDetails, $logErrors, $logErrorDetails, $logger);
        $errorMiddleware->setDefaultErrorHandler($errorHandler);

        // Preflight CORS for all routes
        $app->options('/{routes:.+}', function (Request $request, Response $response): Response {
            return $response;
        });

        // API Info / Root endpoint
        $app->get('/', function (Request $request, Response $response): Response {
            $payload = [
                'name' => 'Slim 4 Todo CRUD API',
                'version' => '1.0.0',
                'environment' => AppConfig::getEnv(),
                'status' => 'online',
                'endpoints' => [
                    'GET /health/live' => 'Public liveness check',
                    'GET /health/ready' => 'Protected readiness check (requires X-Health-Key or Bearer token if configured)',
                    'GET /api/todos' => 'List all todos (supports ?completed=1 or ?completed=0)',
                    'GET /api/todos/{id}' => 'Get single todo by ID',
                    'POST /api/todos' => 'Create a new todo',
                    'PUT /api/todos/{id}' => 'Update an existing todo',
                    'PATCH /api/todos/{id}' => 'Partially update an existing todo',
                    'DELETE /api/todos/{id}' => 'Delete a todo',
                ],
            ];

            $response->getBody()->write((string)json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('X-Content-Type-Options', 'nosniff');
        });

        // Health check endpoints
        $healthController = new HealthController();
        $app->get('/health', [$healthController, 'liveness']);
        $app->get('/health/live', [$healthController, 'liveness']);
        $app->get('/health/ready', [$healthController, 'readiness']);

        // API Routes
        $app->group('/api/todos', function (RouteCollectorProxy $group) {
            $controller = new TodoController();

            $group->get('', [$controller, 'index']);
            $group->get('/{id:[0-9]+}', [$controller, 'show']);
            $group->post('', [$controller, 'create']);
            $group->put('/{id:[0-9]+}', [$controller, 'update']);
            $group->patch('/{id:[0-9]+}', [$controller, 'update']);
            $group->delete('/{id:[0-9]+}', [$controller, 'delete']);
        });

        return $app;
    }
}
