<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Config\AppConfig;
use Psr\Http\Message\ResponseFactoryInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class HttpsEnforcementMiddleware implements MiddlewareInterface
{
    private ResponseFactoryInterface $responseFactory;

    public function __construct(
        ?ResponseFactoryInterface $responseFactory = null
    ) {
        $this->responseFactory = $responseFactory ?? new ResponseFactory();
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        if (AppConfig::isForceHttps() && ! $this->isHttps($request)) {
            $uri = $request->getUri();
            
            // Build the HTTPS target URI
            $targetUri = $uri
                ->withScheme('https')
                ->withPort(null); // standard HTTPS port (443)

            $method     = strtoupper($request->getMethod());
            // Safe methods can use 301, while mutation methods MUST use 308 to preserve method and request body (RFC 7538)
            $statusCode = in_array($method, ['GET', 'HEAD', 'OPTIONS'], true) ? 301 : 308;

            $response = $this->responseFactory->createResponse($statusCode)
                ->withHeader('Location', (string)$targetUri)
                ->withHeader('X-Content-Type-Options', 'nosniff');

            $response->getBody()->write((string)json_encode([
                'success' => false,
                'error'   => [
                    'type'     => 'HTTPS_REQUIRED',
                    'message'  => 'This API is served exclusively over HTTPS. Please use HTTPS.',
                    'redirect' => (string)$targetUri,
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $response->withHeader('Content-Type', 'application/json');
        }

        return $handler->handle($request);
    }

    /**
     * Check if the incoming request was made via HTTPS directly or via an SSL-terminating reverse proxy.
     */
    private function isHttps(Request $request): bool
    {
        if (strtolower($request->getUri()->getScheme()) === 'https') {
            return true;
        }

        $forwardedProto = strtolower($request->getHeaderLine('X-Forwarded-Proto'));
        if ($forwardedProto === 'https') {
            return true;
        }

        $forwardedSsl = strtolower($request->getHeaderLine('X-Forwarded-Ssl'));
        if ($forwardedSsl === 'on') {
            return true;
        }

        $frontEndHttps = strtolower($request->getHeaderLine('Front-End-Https'));
        if ($frontEndHttps === 'on') {
            return true;
        }

        $serverParams = $request->getServerParams();
        $httpsParam   = strtolower((string)($serverParams['HTTPS'] ?? ''));
        if ($httpsParam === 'on' || $httpsParam === '1') {
            return true;
        }

        $serverPort = (string)($serverParams['SERVER_PORT'] ?? '');
        if ($serverPort === '443') {
            return true;
        }

        return false;
    }
}
