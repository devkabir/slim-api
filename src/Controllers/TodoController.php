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
        $id   = (int)$args['id'];
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
        $id   = (int)$args['id'];
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
        $id      = (int)$args['id'];
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
}
