<?php

declare(strict_types=1);

namespace App\Actions\Home;

use App\Config\Settings;
use App\Response\ApiResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Interfaces\RouteParserInterface;

final readonly class ApiInfoAction
{
    public function __construct(
        private RouteParserInterface $routeParser,
        private Settings $settings,
        private ApiResponse $response
    ) {
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $payload = [
            'name'        => 'Slim 4 Todo CRUD API',
            'version'     => '1.0.0',
            'environment' => $this->settings->env,
            'status'      => 'online',
            'endpoints'   => [
                'GET ' . $this->routeParser->urlFor('health.live')                    => 'Public liveness check',
                'GET ' . $this->routeParser->urlFor('health.ready')                   => 'Protected readiness check (requires X-Health-Key or Bearer token if configured)',
                'GET ' . $this->routeParser->urlFor('todos.index')                    => 'List all todos (supports ?completed=1 or ?completed=0)',
                'GET ' . $this->routeParser->urlFor('todos.show', ['id' => '{id}'])   => 'Get single todo by ID',
                'POST ' . $this->routeParser->urlFor('todos.create')                  => 'Create a new todo',
                'PUT ' . $this->routeParser->urlFor('todos.update', ['id' => '{id}'])  => 'Update an existing todo',
                'PATCH ' . $this->routeParser->urlFor('todos.patch', ['id' => '{id}']) => 'Partially update an existing todo',
                'DELETE ' . $this->routeParser->urlFor('todos.delete', ['id' => '{id}']) => 'Delete a todo',
            ],
        ];

        return $this->response->json($payload);
    }
}
