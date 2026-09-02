<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Config\Settings;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

final readonly class SecurityHeadersMiddleware implements MiddlewareInterface
{
    public function __construct(
        private Settings $settings
    ) {
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $response = $handler->handle($request);

        // Prevent MIME-type sniffing
        $response = $response->withHeader('X-Content-Type-Options', 'nosniff');

        // Restrict framing / prevent Clickjacking
        $response = $response->withHeader('X-Frame-Options', 'DENY');

        // Restrict Referrer information
        $response = $response->withHeader('Referrer-Policy', $this->settings->security['referrer_policy']);

        // Content Security Policy
        $csp = $this->settings->security['content_security_policy'];
        if ($csp !== '') {
            $response = $response->withHeader('Content-Security-Policy', $csp);
        }

        // Restrict browser features and APIs
        $response = $response->withHeader(
            'Permissions-Policy',
            'accelerometer=(), camera=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()'
        );

        // HTTP Strict Transport Security (HSTS)
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

        // Server-Timing benchmark header if APP_START is defined
        if (defined('APP_START')) {
            $durationMs = round((microtime(true) - APP_START) * 1000, 2);
            $response   = $response->withHeader('Server-Timing', "app;dur={$durationMs}");
        }

        return $response;
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
