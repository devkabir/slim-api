<?php

declare(strict_types=1);

namespace App\Actions\Health;

use App\Response\ApiResponse;
use DateTimeImmutable;
use DateTimeInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final readonly class LivenessAction
{
    public function __construct(
        private ApiResponse $response
    ) {
    }

    public function __invoke(Request $request, Response $response): Response
    {
        return $this->response->json([
            'status'    => 'up',
            'app'       => 'Slim 4 Todo CRUD API',
            'timestamp' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
        ]);
    }
}
