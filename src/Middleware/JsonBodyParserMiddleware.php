<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseFactoryInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class JsonBodyParserMiddleware implements MiddlewareInterface
{
    private ResponseFactoryInterface $responseFactory;
    private int $maxBodySizeBytes;

    public function __construct(
        ?ResponseFactoryInterface $responseFactory = null,
        int $maxBodySizeBytes = 1048576 // 1MB default
    ) {
        $this->responseFactory  = $responseFactory ?? new ResponseFactory();
        $this->maxBodySizeBytes = $maxBodySizeBytes;
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $method = strtoupper($request->getMethod());

        // We strictly validate JSON bodies on mutation endpoints: POST, PUT, PATCH
        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $contentType = $request->getHeaderLine('Content-Type');

            // 1. Content-Type: application/json check
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

            // 3. Read raw stream and verify payload size
            $rawBody = (string)$request->getBody();
            if (strlen($rawBody) > $this->maxBodySizeBytes) {
                return $this->createErrorResponse(
                    statusCode: 413,
                    type: 'CONTENT_TOO_LARGE',
                    message: sprintf('Request body exceeds maximum allowed size of %d bytes.', $this->maxBodySizeBytes)
                );
            }

            // 4. Empty body check
            $trimmedBody = trim($rawBody);
            if ($trimmedBody === '') {
                return $this->createErrorResponse(
                    statusCode: 400,
                    type: 'BAD_REQUEST',
                    message: 'Request body cannot be empty. A top-level JSON object is required.'
                );
            }

            // 5. JSON Syntax Validation
            $parsed = json_decode($rawBody, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return $this->createErrorResponse(
                    statusCode: 400,
                    type: 'BAD_REQUEST',
                    message: 'Malformed JSON syntax: ' . json_last_error_msg() . '.'
                );
            }

            // 6. Top-level JSON Object Validation (must be object, not array list or primitive)
            if ( ! is_array($parsed) || str_starts_with($trimmedBody, '[') || ( ! empty($parsed) && array_is_list($parsed))) {
                return $this->createErrorResponse(
                    statusCode: 400,
                    type: 'BAD_REQUEST',
                    message: 'The request body must be a top-level JSON object.'
                );
            }

            $request = $request->withParsedBody($parsed);
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

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('X-Content-Type-Options', 'nosniff');
    }
}
