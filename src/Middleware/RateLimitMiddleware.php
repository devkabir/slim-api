<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Config\Cache;
use Psr\Http\Message\ResponseFactoryInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

class RateLimitMiddleware implements MiddlewareInterface
{
    private ResponseFactoryInterface $responseFactory;
    private int $readLimitPerMinute;
    private int $mutationLimitPerMinute;

    /**
     * Local in-memory fallback store when Memcached is unavailable
     * @var array<string, array{count: int, reset_at: int}>
     */
    private static array $memoryStore = [];

    public function __construct(
        ?ResponseFactoryInterface $responseFactory = null,
        int $readLimitPerMinute = 300,
        int $mutationLimitPerMinute = 60
    ) {
        $this->responseFactory        = $responseFactory ?? new ResponseFactory();
        $this->readLimitPerMinute     = $readLimitPerMinute;
        $this->mutationLimitPerMinute = $mutationLimitPerMinute;
    }

    public function process(Request $request, RequestHandler $handler): Response
    {
        $method     = strtoupper($request->getMethod());
        $isMutation = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
        $maxLimit   = $isMutation ? $this->mutationLimitPerMinute : $this->readLimitPerMinute;

        $clientIp = $this->resolveClientIp($request);
        $tier     = $isMutation ? 'mutation' : 'read';
        $window   = 60; // 60-second sliding/fixed window

        $rateStatus = $this->checkRateLimit($clientIp, $tier, $maxLimit, $window);

        if ($rateStatus['exceeded']) {
            $retryAfter = max(1, $rateStatus['reset_at'] - time());
            $response   = $this->responseFactory->createResponse(429)
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('X-Content-Type-Options', 'nosniff')
                ->withHeader('Retry-After', (string)$retryAfter)
                ->withHeader('X-RateLimit-Limit', (string)$maxLimit)
                ->withHeader('X-RateLimit-Remaining', '0')
                ->withHeader('X-RateLimit-Reset', (string)$rateStatus['reset_at']);

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
            ->withHeader('X-RateLimit-Remaining', (string)$rateStatus['remaining'])
            ->withHeader('X-RateLimit-Reset', (string)$rateStatus['reset_at']);
    }

    /**
     * @return array{exceeded: bool, remaining: int, reset_at: int}
     */
    private function checkRateLimit(string $clientIp, string $tier, int $maxLimit, int $window): array
    {
        $now       = time();
        $windowKey = (int)floor($now / $window);
        $cacheKey  = "rate_limit:{$tier}:{$clientIp}:{$windowKey}";
        $resetAt   = ($windowKey + 1) * $window;

        $memcached = Cache::getInstance();

        if ($memcached !== null) {
            // Memcached atomic counter
            $current = $memcached->increment($cacheKey, 1);
            if ($current === false) {
                $memcached->set($cacheKey, 1, $window + 10);
                $current = 1;
            }

            $current = (int)$current;
        } else {
            // Local memory fallback
            if (!isset(self::$memoryStore[$cacheKey]) || self::$memoryStore[$cacheKey]['reset_at'] <= $now) {
                self::$memoryStore[$cacheKey] = ['count' => 1, 'reset_at' => $resetAt];
                $current = 1;
            } else {
                self::$memoryStore[$cacheKey]['count']++;
                $current = self::$memoryStore[$cacheKey]['count'];
            }
        }

        $remaining = max(0, $maxLimit - $current);
        $exceeded  = $current > $maxLimit;

        return [
            'exceeded'  => $exceeded,
            'remaining' => $remaining,
            'reset_at'  => $resetAt,
        ];
    }

    private function resolveClientIp(Request $request): string
    {
        $forwarded = $request->getHeaderLine('X-Forwarded-For');
        if ($forwarded !== '') {
            $ips = explode(',', $forwarded);
            $ip  = trim($ips[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }

        $serverParams = $request->getServerParams();
        $remoteAddr   = (string)($serverParams['REMOTE_ADDR'] ?? '127.0.0.1');

        return filter_var($remoteAddr, FILTER_VALIDATE_IP) ? $remoteAddr : '127.0.0.1';
    }
}
