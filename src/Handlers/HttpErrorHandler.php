<?php

declare(strict_types=1);

namespace App\Handlers;

use Slim\Exception\HttpException;
use App\Exceptions\ValidationException;
use Slim\Exception\HttpNotFoundException;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpUnauthorizedException;
use Slim\Exception\HttpMethodNotAllowedException;
use Psr\Http\Message\ResponseInterface as Response;
use Slim\Handlers\ErrorHandler as SlimErrorHandler;

class HttpErrorHandler extends SlimErrorHandler
{
    public const TYPE_SERVER_ERROR = 'SERVER_ERROR';
    public const TYPE_NOT_FOUND = 'NOT_FOUND';
    public const TYPE_NOT_ALLOWED = 'NOT_ALLOWED';
    public const TYPE_UNAUTHORIZED = 'UNAUTHORIZED';
    public const TYPE_FORBIDDEN = 'FORBIDDEN';
    public const TYPE_BAD_REQUEST = 'BAD_REQUEST';
    public const TYPE_VALIDATION_ERROR = 'VALIDATION_ERROR';

    protected function respond(): Response
    {
        $exception  = $this->exception;
        $statusCode = 500;
        $type       = self::TYPE_SERVER_ERROR;
        $message    = 'An internal server error occurred.';
        $details    = null;

        if ($exception instanceof ValidationException) {
            $statusCode = 422;
            $type       = self::TYPE_VALIDATION_ERROR;
            $message    = $exception->getMessage();
            $details    = $exception->getErrors();
        } elseif ($exception instanceof HttpException) {
            $statusCode = (int)$exception->getCode();
            $message    = $exception->getMessage();

            if ($exception instanceof HttpNotFoundException) {
                $type = self::TYPE_NOT_FOUND;
            } elseif ($exception instanceof HttpMethodNotAllowedException) {
                $type = self::TYPE_NOT_ALLOWED;
            } elseif ($exception instanceof HttpUnauthorizedException) {
                $type = self::TYPE_UNAUTHORIZED;
            } elseif ($exception instanceof HttpForbiddenException) {
                $type = self::TYPE_FORBIDDEN;
            } elseif ($exception instanceof HttpBadRequestException) {
                $type = self::TYPE_BAD_REQUEST;
            }
        }

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

        // Detailed error information only when displayErrorDetails is explicitly enabled
        if ($this->displayErrorDetails) {
            $payload['debug'] = [
                'type'    => get_class($exception),
                'message' => $exception->getMessage(),
                'file'    => $exception->getFile(),
                'line'    => $exception->getLine(),
                'trace'   => explode("\n", $exception->getTraceAsString()),
            ];
        }

        $response = $this->responseFactory->createResponse($statusCode);
        $response->getBody()->write((string)json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('X-Content-Type-Options', 'nosniff');
    }

    protected function writeToErrorLog(): void
    {
        if ($this->logger === null) {
            return;
        }

        $exception = $this->exception;
        $context   = [
            'type'    => get_class($exception),
            'message' => $this->sanitizeMessage($exception->getMessage()),
            'code'    => $exception->getCode(),
            'file'    => $exception->getFile(),
            'line'    => $exception->getLine(),
            'uri'     => (string)$this->request->getUri(),
            'method'  => $this->request->getMethod(),
        ];

        if ($this->logErrorDetails) {
            $context['trace'] = $exception->getTraceAsString();
        }

        $this->logger->error($context['message'], $context);
    }

    private function sanitizeMessage(string $message): string
    {
        // Redact potential passwords, db credentials, or secrets in logged strings
        $pattern = '/(password|pass|secret|key|token|auth|pwd)=([^&\s;]+)/i';

        return (string)preg_replace($pattern, '$1=***REDACTED***', $message);
    }
}
