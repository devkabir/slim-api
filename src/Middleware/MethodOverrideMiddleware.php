<?php

declare(strict_types=1);

namespace App\Middleware;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

final readonly class MethodOverrideMiddleware implements MiddlewareInterface
{
    private const ALLOWED_OVERRIDES = ['PUT', 'PATCH', 'DELETE'];

    public function process(Request $request, RequestHandler $handler): Response
    {
        if (strtoupper($request->getMethod()) === 'POST') {
            $overrideHeader = strtoupper(trim($request->getHeaderLine('X-HTTP-Method-Override')));

            if ($overrideHeader !== '' && in_array($overrideHeader, self::ALLOWED_OVERRIDES, true)) {
                $request = $request->withMethod($overrideHeader);
            }
        }

        return $handler->handle($request);
    }
}
