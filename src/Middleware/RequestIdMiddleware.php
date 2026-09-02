<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Psr\Log\LoggerInterface;

final readonly class RequestIdMiddleware implements MiddlewareInterface
{
    public const HEADER_NAME = 'X-Request-ID';

    public function __construct(
        private ?LoggerInterface $logger = null
    ) {
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $incomingId = $request->getHeaderLine(self::HEADER_NAME);

        // Sanitize incoming request ID or generate a new cryptographically secure random one
        if ($incomingId !== '' && preg_match('/^[a-zA-Z0-9_\-]{8,64}$/', $incomingId)) {
            $requestId = $incomingId;
        } else {
            $requestId = bin2hex(random_bytes(16));
        }

        // Attach to request attributes
        $request = $request->withAttribute('request_id', $requestId);

        $response = $handler->handle($request);

        return $response->withHeader(self::HEADER_NAME, $requestId);
    }
}
