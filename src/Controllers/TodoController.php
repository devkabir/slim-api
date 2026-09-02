<?php

declare(strict_types=1);

namespace App\Controllers;

use App\DTOs\CreateTodoDTO;
use App\DTOs\UpdateTodoDTO;
use App\Response\ApiResponse;
use App\Services\TodoService;
use App\DTOs\TodoListQueryDTO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class TodoController
{
    public function __construct(
        private TodoService $todoService,
        private ApiResponse $response
    ) {
    }

    /**
     * GET /api/todos
     */
    public function index(Request $request, Response $response): Response
    {
        $query = TodoListQueryDTO::fromQueryParams($request->getQueryParams());
        $todos = $this->todoService->getAllTodos($query->completed);

        return $this->response->success(
            data: $todos,
            meta: ['count' => count($todos)]
        );
    }

    /**
     * GET /api/todos/{id}
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        $id = $this->validateId($args['id'] ?? null);
        if ($id === null) {
            return $this->response->error(
                message: 'The todo ID must be a positive integer.',
                type: 'VALIDATION_ERROR',
                statusCode: 422
            );
        }

        $todo = $this->todoService->getTodoById($id);

        if ( ! $todo) {
            return $this->response->error(
                message: "Todo with ID {$id} not found.",
                type: 'NOT_FOUND',
                statusCode: 404
            );
        }

        return $this->response->success(data: $todo);
    }

    /**
     * POST /api/todos
     */
    public function create(Request $request, Response $response): Response
    {
        $body = (array)($request->getParsedBody() ?? []);
        $dto  = CreateTodoDTO::fromArray($body);
        $todo = $this->todoService->createTodo($dto);

        return $this->response->success(
            data: $todo,
            message: 'Todo created successfully.',
            statusCode: 201
        );
    }

    /**
     * PUT /api/todos/{id} or PATCH /api/todos/{id}
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $id = $this->validateId($args['id'] ?? null);
        if ($id === null) {
            return $this->response->error(
                message: 'The todo ID must be a positive integer.',
                type: 'VALIDATION_ERROR',
                statusCode: 422
            );
        }

        $body = (array)($request->getParsedBody() ?? []);
        $dto  = UpdateTodoDTO::fromArray($body);

        $updated = $this->todoService->updateTodo($id, $dto);

        if ( ! $updated) {
            return $this->response->error(
                message: "Todo with ID {$id} not found.",
                type: 'NOT_FOUND',
                statusCode: 404
            );
        }

        return $this->response->success(
            data: $updated,
            message: 'Todo updated successfully.'
        );
    }

    /**
     * DELETE /api/todos/{id}
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = $this->validateId($args['id'] ?? null);
        if ($id === null) {
            return $this->response->error(
                message: 'The todo ID must be a positive integer.',
                type: 'VALIDATION_ERROR',
                statusCode: 422
            );
        }

        $deleted = $this->todoService->deleteTodo($id);

        if ( ! $deleted) {
            return $this->response->error(
                message: "Todo with ID {$id} not found.",
                type: 'NOT_FOUND',
                statusCode: 404
            );
        }

        return $this->response->success(
            message: "Todo with ID {$id} deleted successfully."
        );
    }

    /**
     * Validate and return a positive integer ID, or null if invalid.
     */
    private function validateId(mixed $id): ?int
    {
        $validated = filter_var($id, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1]
        ]);

        return ($validated === false || $validated === null) ? null : (int)$validated;
    }
}
