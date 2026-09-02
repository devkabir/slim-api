<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Config\Settings;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

final readonly class RequestBodyGuardMiddleware implements MiddlewareInterface
{
    private int $maxBodySizeBytes;

    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private Settings $settings
    ) {
        $this->maxBodySizeBytes = $this->settings->bodyParser['max_body_size_bytes'];
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $method = strtoupper($request->getMethod());

        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $contentType = $request->getHeaderLine('Content-Type');

            // 1. Content-Type validation
            if ($contentType === '' || ! str_contains(strtolower($contentType), 'application/json')) {
                return $this->createErrorResponse(
                    statusCode: 415,
                    type: 'UNSUPPORTED_MEDIA_TYPE',
                    message: 'Unsupported Media Type: Content-Type must be application/json.'
                );
            }

            // 2. Content-Length header size pre-check
            $contentLength = $request->getHeaderLine('Content-Length');
            if ($contentLength !== '' && is_numeric($contentLength) && (int)$contentLength > $this->maxBodySizeBytes) {
                return $this->createErrorResponse(
                    statusCode: 413,
                    type: 'CONTENT_TOO_LARGE',
                    message: sprintf('Request body exceeds maximum allowed size of %d bytes.', $this->maxBodySizeBytes)
                );
            }

            // 3. Raw stream size check
            $body = $request->getBody();
            $rawBody = (string)$body;
            if (strlen($rawBody) > $this->maxBodySizeBytes) {
                return $this->createErrorResponse(
                    statusCode: 413,
                    type: 'CONTENT_TOO_LARGE',
                    message: sprintf('Request body exceeds maximum allowed size of %d bytes.', $this->maxBodySizeBytes)
                );
            }

            // 4. Non-empty body check
            if (trim($rawBody) === '') {
                return $this->createErrorResponse(
                    statusCode: 400,
                    type: 'BAD_REQUEST',
                    message: 'Request body cannot be empty. A top-level JSON object is required.'
                );
            }

            // Rewind stream for downstream parsers
            if ($body->isSeekable()) {
                $body->rewind();
            }
        }

        return $handler->handle($request);
    }

    private function createErrorResponse(int $statusCode, string $type, string $message): Response
    {
        $response = $this->responseFactory->createResponse($statusCode);
        $payload  = [
            'success' => false,
            'error'   => [
                'type'    => $type,
                'message' => $message,
            ],
        ];

        $response->getBody()->write((string)json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
