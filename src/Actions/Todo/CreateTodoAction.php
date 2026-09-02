<?php

declare(strict_types=1);

namespace App\Actions\Todo;

use App\DTOs\CreateTodoDTO;
use App\Response\ApiResponse;
use App\Services\TodoService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Interfaces\RouteParserInterface;

final readonly class CreateTodoAction
{
    public function __construct(
        private TodoService $todoService,
        private ApiResponse $response,
        private RouteParserInterface $routeParser
    ) {
    }

    public function __invoke(Request $request, Response $response): Response
    {
        $body = (array)($request->getParsedBody() ?? []);
        $dto  = CreateTodoDTO::fromArray($body);
        $todo = $this->todoService->createTodo($dto);

        $locationUrl = $this->routeParser->urlFor('todos.show', ['id' => (string)$todo->id]);

        $res = $this->response->success(
            data: $todo,
            message: 'Todo created successfully.',
            statusCode: 201
        );

        return $res->withHeader('Location', $locationUrl);
    }
}
