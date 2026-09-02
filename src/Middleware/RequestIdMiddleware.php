<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Config\AppLogger;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class RequestIdMiddleware implements MiddlewareInterface
{
    public const HEADER_NAME = 'X-Request-ID';

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

        // Inject request_id into Monolog logging context processor for incident correlation
        $logger = AppLogger::getLogger();
        if (method_exists($logger, 'pushProcessor')) {
            $logger->pushProcessor(function (array|\Monolog\LogRecord $record) use ($requestId) {
                if ($record instanceof \Monolog\LogRecord) {
                    return $record->with(extra: array_merge($record->extra, ['request_id' => $requestId]));
                }
                $record['extra']['request_id'] = $requestId;

                return $record;
            });
        }

        $response = $handler->handle($request);

        return $response->withHeader(self::HEADER_NAME, $requestId);
    }
}
