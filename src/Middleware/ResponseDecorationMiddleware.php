<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Config\Settings;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

final readonly class ResponseDecorationMiddleware implements MiddlewareInterface
{
    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private Settings $settings
    ) {
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        // Handle preflight OPTIONS requests directly
        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            $response = $this->responseFactory->createResponse(200);

            return $this->decorate($request, $response);
        }

        $response = $handler->handle($request);

        return $this->decorate($request, $response);
    }

    private function decorate(Request $request, Response $response): Response
    {
        // 1. Security Headers
        $response = $response
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('Referrer-Policy', $this->settings->security['referrer_policy'])
            ->withHeader(
                'Permissions-Policy',
                'accelerometer=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()'
            );

        $csp = $this->settings->security['content_security_policy'];
        if ($csp !== '') {
            $response = $response->withHeader('Content-Security-Policy', $csp);
        }

        if ($this->settings->security['hsts_enabled'] && $this->isHttps($request)) {
            $hsts = sprintf('max-age=%d', $this->settings->security['hsts_max_age']);

            if ($this->settings->security['hsts_include_subdomains']) {
                $hsts .= '; includeSubDomains';
            }

            if ($this->settings->security['hsts_preload']) {
                $hsts .= '; preload';
            }

            $response = $response->withHeader('Strict-Transport-Security', $hsts);
        }

        // 2. CORS Headers
        $origin = $request->getHeaderLine('Origin');
        $allowedOrigins = $this->settings->cors_allowed_origins;

        $allowOriginValue = '*';
        if (in_array('*', $allowedOrigins, true)) {
            $allowOriginValue = '*';
        } elseif ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
            $allowOriginValue = $origin;
        } elseif (! empty($allowedOrigins)) {
            $allowOriginValue = $allowedOrigins[0];
        }

        $response = $response
            ->withHeader('Access-Control-Allow-Origin', $allowOriginValue)
            ->withHeader(
                'Access-Control-Allow-Headers',
                'X-Requested-With, Content-Type, Accept, Origin, Authorization, X-Health-Key, X-Request-ID, X-HTTP-Method-Override'
            )
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, PATCH, OPTIONS');

        // 3. Server-Timing benchmark
        if (defined('APP_START')) {
            $durationMs = round((microtime(true) - APP_START) * 1000, 2);
            $response   = $response->withHeader('Server-Timing', "app;dur={$durationMs}");
        }

        return $response;
    }

    private function isHttps(Request $request): bool
    {
        if (strtolower($request->getUri()->getScheme()) === 'https') {
            return true;
        }

        $remoteAddr = (string)($request->getServerParams()['REMOTE_ADDR'] ?? '');
        $trusted    = $this->isTrustedProxy($remoteAddr);

        if ($trusted) {
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
