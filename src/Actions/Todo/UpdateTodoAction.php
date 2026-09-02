<?php

declare(strict_types=1);

namespace App\Actions\Todo;

use App\DTOs\UpdateTodoDTO;
use App\Exceptions\ValidationException;
use App\Response\ApiResponse;
use App\Services\TodoService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final readonly class UpdateTodoAction
{
    public function __construct(
        private TodoService $todoService,
        private ApiResponse $response
    ) {
    }

    /**
     * @param array<string, string> $args
     */
    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $id   = $this->resolveId($args['id'] ?? null);
        $body = (array)($request->getParsedBody() ?? []);
        $dto  = UpdateTodoDTO::fromArray($body);

        $updated = $this->todoService->updateTodo($id, $dto);

        return $this->response->success(
            data: $updated,
            message: 'Todo updated successfully.'
        );
    }

    private function resolveId(mixed $id): int
    {
        $validated = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($validated === false || $validated === null) {
            throw new ValidationException(['id' => 'The todo ID must be a positive integer.']);
        }

        return (int)$validated;
    }
}
