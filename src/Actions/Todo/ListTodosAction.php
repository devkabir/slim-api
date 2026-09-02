<?php

declare(strict_types=1);

namespace App\Actions\Todo;

use App\DTOs\TodoListQueryDTO;
use App\Response\ApiResponse;
use App\Services\TodoService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final readonly class ListTodosAction
{
    public function __construct(
        private TodoService $todoService,
        private ApiResponse $response
    ) {
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $query  = TodoListQueryDTO::fromQueryParams($request->getQueryParams());
        $result = $this->todoService->getPaginatedTodos($query->completed, $query->page, $query->limit);

        return $this->response->success(
            data: $result['todos'],
            meta: ['pagination' => $result['pagination']]
        );
    }
}
