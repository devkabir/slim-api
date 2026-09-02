<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\TodoService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class TodoController
{
    public function __construct(
        private TodoService $todoService = new TodoService()
    ) {}

    /**
     * GET /api/todos
     */
    public function index(Request $request, Response $response): Response
    {
        $queryParams = $request->getQueryParams();
        $completed = null;

        if (isset($queryParams['completed'])) {
            $val = strtolower((string)$queryParams['completed']);
            if ($val === 'true' || $val === '1') {
                $completed = true;
            } elseif ($val === 'false' || $val === '0') {
                $completed = false;
            }
        }

        $todos = $this->todoService->getAllTodos($completed);

        return $this->jsonResponse($response, [
            'success' => true,
            'count' => count($todos),
            'data' => $todos,
        ]);
    }

    /**
     * GET /api/todos/{id}
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $todo = $this->todoService->getTodoById($id);

        if (!$todo) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => "Todo with ID {$id} not found."
            ], 404);
        }

        return $this->jsonResponse($response, [
            'success' => true,
            'data' => $todo,
        ]);
    }

    /**
     * POST /api/todos
     */
    public function create(Request $request, Response $response): Response
    {
        $body = (array)($request->getParsedBody() ?? []);

        $title = trim((string)($body['title'] ?? ''));
        $description = isset($body['description']) ? trim((string)$body['description']) : null;
        $completed = !empty($body['completed']);

        if (empty($title)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'Validation error: "title" field is required.'
            ], 422);
        }

        $todo = $this->todoService->createTodo($title, $description, $completed);

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Todo created successfully.',
            'data' => $todo,
        ], 201);
    }

    /**
     * PUT /api/todos/{id} or PATCH /api/todos/{id}
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $body = (array)($request->getParsedBody() ?? []);

        if (empty($body)) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => 'No update data provided.'
            ], 400);
        }

        $dataToUpdate = [];

        if (array_key_exists('title', $body)) {
            $title = trim((string)$body['title']);
            if (empty($title)) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'message' => 'Validation error: "title" cannot be empty.'
                ], 422);
            }
            $dataToUpdate['title'] = $title;
        }

        if (array_key_exists('description', $body)) {
            $dataToUpdate['description'] = $body['description'] !== null ? trim((string)$body['description']) : null;
        }

        if (array_key_exists('completed', $body)) {
            $dataToUpdate['completed'] = (bool)$body['completed'];
        }

        $updated = $this->todoService->updateTodo($id, $dataToUpdate);

        if (!$updated) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => "Todo with ID {$id} not found."
            ], 404);
        }

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => 'Todo updated successfully.',
            'data' => $updated,
        ]);
    }

    /**
     * DELETE /api/todos/{id}
     */
    public function delete(Request $request, Response $response, array $args): Response
    {
        $id = (int)$args['id'];
        $deleted = $this->todoService->deleteTodo($id);

        if (!$deleted) {
            return $this->jsonResponse($response, [
                'success' => false,
                'message' => "Todo with ID {$id} not found."
            ], 404);
        }

        return $this->jsonResponse($response, [
            'success' => true,
            'message' => "Todo with ID {$id} deleted successfully."
        ]);
    }

    private function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write((string)json_encode($data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
