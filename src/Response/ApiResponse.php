<?php

declare(strict_types=1);

namespace App\Response;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface as Response;

final readonly class ApiResponse
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory
    ) {
    }

    /**
     * @param array<string, mixed> $meta
     */
    public function success(
        mixed $data = null,
        ?string $message = null,
        int $statusCode = 200,
        array $meta = []
    ): Response {
        $payload = ['success' => true];

        if ($message !== null) {
            $payload['message'] = $message;
        }

        if (! empty($meta)) {
            $payload = array_merge($payload, $meta);
        }

        if ($data !== null) {
            $payload['data'] = $data;
        }

        return $this->json($payload, $statusCode);
    }

    public function json(array $payload, int $statusCode = 200): Response
    {
        $response = $this->responseFactory->createResponse($statusCode);
        $response->getBody()->write((string)json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('X-Content-Type-Options', 'nosniff');
    }

    /**
     * @param array<string, mixed>|null $details
     */
    public function error(
        string $message,
        string $type = 'BAD_REQUEST',
        int $statusCode = 400,
        ?array $details = null
    ): Response {
        $payload = [
            'success' => false,
            'error'   => [
                'type'    => $type,
                'message' => $message,
            ],
        ];

        if ($details !== null) {
            $payload['error']['details'] = $details;
        }

        return $this->json($payload, $statusCode);
    }
}
