<?php

declare(strict_types=1);

namespace App\Handlers;

use App\Exceptions\TodoNotFoundException;
use App\Exceptions\ValidationException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpBadRequestException;
use Slim\Exception\HttpException;
use Slim\Exception\HttpForbiddenException;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Exception\HttpUnauthorizedException;
use Slim\Handlers\ErrorHandler as SlimErrorHandler;
use Slim\Interfaces\CallableResolverInterface;
use Throwable;

final class HttpErrorHandler extends SlimErrorHandler
{
    public const TYPE_SERVER_ERROR           = 'SERVER_ERROR';
    public const TYPE_NOT_FOUND              = 'NOT_FOUND';
    public const TYPE_NOT_ALLOWED            = 'NOT_ALLOWED';
    public const TYPE_UNAUTHORIZED           = 'UNAUTHORIZED';
    public const TYPE_FORBIDDEN              = 'FORBIDDEN';
    public const TYPE_BAD_REQUEST            = 'BAD_REQUEST';
    public const TYPE_VALIDATION_ERROR       = 'VALIDATION_ERROR';
    public const TYPE_CONTENT_TOO_LARGE      = 'CONTENT_TOO_LARGE';
    public const TYPE_UNSUPPORTED_MEDIA_TYPE = 'UNSUPPORTED_MEDIA_TYPE';

    public function __construct(
        CallableResolverInterface $callableResolver,
        ResponseFactoryInterface $responseFactory,
        ?LoggerInterface $logger = null,
        private readonly bool $isProduction = false
    ) {
        parent::__construct($callableResolver, $responseFactory, $logger);
    }

    protected function respond(): Response
    {
        $exception  = $this->exception;
        $statusCode = 500;
        $type       = self::TYPE_SERVER_ERROR;
        $message    = 'An internal server error occurred.';
        $details    = null;
        $headers    = [];

        if ($exception instanceof ValidationException) {
            $statusCode = 422;
            $type       = self::TYPE_VALIDATION_ERROR;
            $message    = $exception->getMessage();
            $details    = $exception->getErrors();
        } elseif ($exception instanceof TodoNotFoundException) {
            $statusCode = 404;
            $type       = self::TYPE_NOT_FOUND;
            $message    = $exception->getMessage();
        } elseif ($exception instanceof HttpException) {
            $statusCode = (int)$exception->getCode();
            $message    = $exception->getMessage();

            if ($exception instanceof HttpNotFoundException) {
                $type = self::TYPE_NOT_FOUND;
            } elseif ($exception instanceof HttpMethodNotAllowedException) {
                $type = self::TYPE_NOT_ALLOWED;
                $allowed = $exception->getAllowedMethods();
                if (! empty($allowed)) {
                    $headers['Allow'] = implode(', ', $allowed);
                }
            } elseif ($exception instanceof HttpUnauthorizedException) {
                $type = self::TYPE_UNAUTHORIZED;
            } elseif ($exception instanceof HttpForbiddenException) {
                $type = self::TYPE_FORBIDDEN;
            } elseif ($exception instanceof HttpBadRequestException) {
                $type = self::TYPE_BAD_REQUEST;
            } elseif ($statusCode === 413) {
                $type = self::TYPE_CONTENT_TOO_LARGE;
            } elseif ($statusCode === 415) {
                $type = self::TYPE_UNSUPPORTED_MEDIA_TYPE;
            }
        } elseif ($exception->getCode() === 400 || $exception->getCode() === 422 || $exception->getCode() === 404) {
            $statusCode = (int)$exception->getCode();
            $message    = $exception->getMessage();
            $type       = match ($statusCode) {
                400     => self::TYPE_BAD_REQUEST,
                422     => self::TYPE_VALIDATION_ERROR,
                404     => self::TYPE_NOT_FOUND,
                default => self::TYPE_SERVER_ERROR,
            };
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

        // Detailed error information only when debug is active AND strictly NOT in production
        if ($this->displayErrorDetails && ! $this->isProduction) {
            $payload['debug'] = [
                'type'    => get_class($exception),
                'message' => $exception->getMessage(),
                'file'    => $exception->getFile(),
                'line'    => $exception->getLine(),
                'trace'   => explode("\n", $exception->getTraceAsString()),
            ];
        }

        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            $encoded = json_encode([
                'success' => false,
                'error'   => [
                    'type'    => self::TYPE_SERVER_ERROR,
                    'message' => 'An internal server error occurred during response encoding.',
                ],
            ]);
        }

        $response = $this->responseFactory->createResponse($statusCode);
        $response->getBody()->write((string)$encoded);

        $response = $response->withHeader('Content-Type', 'application/json');

        foreach ($headers as $headerName => $headerValue) {
            $response = $response->withHeader($headerName, $headerValue);
        }

        return $response;
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
        $message = (string)preg_replace('/(password|pass|secret|key|token|auth|pwd)=([^&\s;]+)/i', '$1=***REDACTED***', $message);
        $message = (string)preg_replace('/(using password:\s*)(YES|NO)/i', '$1***', $message);

        return $message;
    }
}
