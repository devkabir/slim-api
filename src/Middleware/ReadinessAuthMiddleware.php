<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Config\Settings;
use App\Response\ApiResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

final readonly class ReadinessAuthMiddleware implements MiddlewareInterface
{
    public function __construct(
        private Settings $settings,
        private ApiResponse $response
    ) {
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $secret = $this->settings->security['health_check_secret'];

        if ($secret !== null) {
            $authHeader   = $request->getHeaderLine('Authorization');
            $customHeader = $request->getHeaderLine('X-Health-Key');

            $providedToken = null;
            if (! empty($customHeader)) {
                $providedToken = trim($customHeader);
            } elseif (str_starts_with($authHeader, 'Bearer ')) {
                $providedToken = trim(substr($authHeader, 7));
            }

            if ($providedToken === null || ! hash_equals($secret, $providedToken)) {
                return $this->response->error(
                    message: 'Unauthorized: Invalid or missing health check key.',
                    type: 'UNAUTHORIZED',
                    statusCode: 401
                );
            }
        }

        return $handler->handle($request);
    }
}
