<?php

declare(strict_types=1);

use App\Config\Cache;
use App\Config\Database;
use App\Controllers\TodoController;
use App\Middleware\CorsMiddleware;
use App\Middleware\JsonBodyParserMiddleware;
use Dotenv\Dotenv;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;

require __DIR__ . '/../vendor/autoload.php';

// Load environment variables
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->safeLoad();
}

$app = AppFactory::create();

// Add routing and parsing middleware
$app->addRoutingMiddleware();
$app->add(new JsonBodyParserMiddleware());
$app->add(new CorsMiddleware());

// Add error handling middleware (display error details in dev)
$errorMiddleware = $app->addErrorMiddleware(true, true, true);

// Preflight CORS for all routes
$app->options('/{routes:.+}', function (Request $request, Response $response): Response {
    return $response;
});

// Health check / System status endpoint
$app->get('/', function (Request $request, Response $response): Response {
    $dbStatus = 'disconnected';
    $cacheStatus = 'disconnected';

    try {
        $pdo = Database::getConnection();
        $pdo->query('SELECT 1');
        $dbStatus = 'connected';
    } catch (\Throwable $e) {
        $dbStatus = 'error: ' . $e->getMessage();
    }

    $cacheStatus = Cache::isConnected() ? 'connected' : 'disconnected/unavailable';

    $payload = [
        'name' => 'Slim 4 Todo CRUD API',
        'status' => 'online',
        'services' => [
            'mysql' => $dbStatus,
            'memcached' => $cacheStatus,
        ],
        'endpoints' => [
            'GET /api/todos' => 'List all todos (supports ?completed=1 or ?completed=0)',
            'GET /api/todos/{id}' => 'Get single todo by ID',
            'POST /api/todos' => 'Create a new todo',
            'PUT /api/todos/{id}' => 'Update an existing todo',
            'DELETE /api/todos/{id}' => 'Delete a todo',
        ]
    ];

    $response->getBody()->write((string)json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    return $response->withHeader('Content-Type', 'application/json');
});

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

$app->run();
