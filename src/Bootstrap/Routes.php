<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Config\Settings;
use App\Controllers\HealthController;
use App\Controllers\TodoController;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Routing\RouteCollectorProxy;

final class Routes
{
    public static function register(App $app): void
    {
        // Preflight CORS for all routes
        $app->options('/{routes:.+}', function (Request $request, Response $response): Response {
            return $response;
        });

        // API Info / Root endpoint
        $app->get('/', function (Request $request, Response $response) use ($app): Response {
            $container   = $app->getContainer();
            $environment = 'development';

            if ($container !== null && $container->has(Settings::class)) {
                $environment = $container->get(Settings::class)->env;
            }

            $payload = [
                'name'        => 'Slim 4 Todo CRUD API',
                'version'     => '1.0.0',
                'environment' => $environment,
                'status'      => 'online',
                'endpoints'   => [
                    'GET /health/live'       => 'Public liveness check',
                    'GET /health/ready'      => 'Protected readiness check (requires X-Health-Key or Bearer token if configured)',
                    'GET /api/todos'         => 'List all todos (supports ?completed=1 or ?completed=0)',
                    'GET /api/todos/{id}'    => 'Get single todo by ID',
                    'POST /api/todos'        => 'Create a new todo',
                    'PUT /api/todos/{id}'    => 'Update an existing todo',
                    'PATCH /api/todos/{id}'  => 'Partially update an existing todo',
                    'DELETE /api/todos/{id}' => 'Delete a todo',
                ],
            ];

            $response->getBody()->write((string)json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $response
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('X-Content-Type-Options', 'nosniff');
        });

        // Health check endpoints
        $app->get('/health', [HealthController::class, 'liveness']);
        $app->get('/health/live', [HealthController::class, 'liveness']);
        $app->get('/health/ready', [HealthController::class, 'readiness']);

        // API Todo CRUD routes
        $app->group('/api/todos', function (RouteCollectorProxy $group) {
            $group->get('', [TodoController::class, 'index']);
            $group->get('/{id:[0-9]+}', [TodoController::class, 'show']);
            $group->post('', [TodoController::class, 'create']);
            $group->put('/{id:[0-9]+}', [TodoController::class, 'update']);
            $group->patch('/{id:[0-9]+}', [TodoController::class, 'update']);
            $group->delete('/{id:[0-9]+}', [TodoController::class, 'delete']);
        });
    }
}
