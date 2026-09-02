<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Config\Settings;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

final readonly class HttpsEnforcementMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private Settings $settings
    ) {
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        if ($this->settings->security['force_https'] && ! $this->isHttps($request)) {
            $uri = $request->getUri();

            $targetUri = $uri
                ->withScheme('https')
                ->withPort(null);

            $method     = strtoupper($request->getMethod());
            $statusCode = in_array($method, ['GET', 'HEAD', 'OPTIONS'], true) ? 301 : 308;

            $response = $this->responseFactory->createResponse($statusCode)
                ->withHeader('Location', (string)$targetUri);

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

    private function isHttps(Request $request): bool
    {
        if (strtolower($request->getUri()->getScheme()) === 'https') {
            return true;
        }

        $remoteAddr = (string)($request->getServerParams()['REMOTE_ADDR'] ?? '');
        if ($this->isTrustedProxy($remoteAddr)) {
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

    private function isTrustedProxy(string $ip): bool
    {
        if ($ip === '' || empty($this->settings->trusted_proxies)) {
            return false;
        }

        return in_array($ip, $this->settings->trusted_proxies, true);
    }
}
