<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Config\Settings;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

final class RateLimitMiddleware implements MiddlewareInterface
{
    private RateLimiterFactory $readLimiterFactory;
    private RateLimiterFactory $mutationLimiterFactory;
    private int $readLimit;
    private int $mutationLimit;

    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly Settings $settings,
        CacheItemPoolInterface $rateLimiterCachePool
    ) {
        $storage = new CacheStorage($rateLimiterCachePool);

        $this->readLimit     = $this->settings->rateLimit['read_limit_per_minute'];
        $this->mutationLimit = $this->settings->rateLimit['mutation_limit_per_minute'];

        $this->readLimiterFactory = new RateLimiterFactory([
            'id'       => 'read_tier',
            'policy'   => 'fixed_window',
            'limit'    => $this->readLimit,
            'interval' => '60 seconds',
        ], $storage);

        $this->mutationLimiterFactory = new RateLimiterFactory([
            'id'       => 'mutation_tier',
            'policy'   => 'fixed_window',
            'limit'    => $this->mutationLimit,
            'interval' => '60 seconds',
        ], $storage);
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $method     = strtoupper($request->getMethod());
        $isMutation = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
        $maxLimit   = $isMutation ? $this->mutationLimit : $this->readLimit;

        $clientIp = $this->resolveClientIp($request);
        $factory  = $isMutation ? $this->mutationLimiterFactory : $this->readLimiterFactory;
        $limiter  = $factory->create($clientIp);

        $rateLimitResult = $limiter->consume(1);

        $now        = time();
        $retryAfter = max(1, $rateLimitResult->getRetryAfter()?->getTimestamp() ? ($rateLimitResult->getRetryAfter()->getTimestamp() - $now) : 60);
        $resetAt    = $rateLimitResult->getRetryAfter()?->getTimestamp() ?? ($now + 60);
        $remaining  = $rateLimitResult->getRemainingTokens();

        if (! $rateLimitResult->isAccepted()) {
            $response = $this->responseFactory->createResponse(429)
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Retry-After', (string)$retryAfter)
                ->withHeader('X-RateLimit-Limit', (string)$maxLimit)
                ->withHeader('X-RateLimit-Remaining', '0')
                ->withHeader('X-RateLimit-Reset', (string)$resetAt);

            $response->getBody()->write((string)json_encode([
                'success' => false,
                'error'   => [
                    'type'        => 'RATE_LIMIT_EXCEEDED',
                    'message'     => sprintf('Too many requests. Rate limit exceeded. Please retry in %d seconds.', $retryAfter),
                    'retry_after' => $retryAfter,
                ],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $response;
        }

        $response = $handler->handle($request);

        return $response
            ->withHeader('X-RateLimit-Limit', (string)$maxLimit)
            ->withHeader('X-RateLimit-Remaining', (string)$remaining)
            ->withHeader('X-RateLimit-Reset', (string)$resetAt);
    }

    private function resolveClientIp(Request $request): string
    {
        $remoteAddr = (string)($request->getServerParams()['REMOTE_ADDR'] ?? '127.0.0.1');

        if ($this->isTrustedProxy($remoteAddr)) {
            $forwarded = $request->getHeaderLine('X-Forwarded-For');
            if ($forwarded !== '') {
                $ips = explode(',', $forwarded);
                $ip  = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return filter_var($remoteAddr, FILTER_VALIDATE_IP) ? $remoteAddr : '127.0.0.1';
    }

    private function isTrustedProxy(string $ip): bool
    {
        if ($ip === '' || empty($this->settings->trusted_proxies)) {
            return false;
        }

        return in_array($ip, $this->settings->trusted_proxies, true);
    }
}
