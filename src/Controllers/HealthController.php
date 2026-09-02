<?php

declare(strict_types=1);

namespace App\Controllers;

use PDO;
use Throwable;
use App\Config\Cache;
use App\Config\Settings;
use DateTimeImmutable;
use DateTimeInterface;
use Psr\Log\LoggerInterface;
use App\Response\ApiResponse;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

final readonly class HealthController
{
    public function __construct(
        private PDO $pdo,
        private ApiResponse $response,
        private Settings $settings,
        private Cache $cache,
        private ?LoggerInterface $logger = null
    ) {
    }

    /**
     * Public liveness endpoint: verifies that the application process is running.
     * GET /health/live (or /health)
     */
    public function liveness(Request $request, Response $response): Response
    {
        return $this->response->json([
            'status'    => 'up',
            'app'       => 'Slim 4 Todo CRUD API',
            'timestamp' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
        ]);
    }

    /**
     * Protected readiness endpoint: verifies connectivity to dependencies.
     * GET /health/ready
     */
    public function readiness(Request $request, Response $response): Response
    {
        // Check authorization if a secret key is configured
        $secret = $this->settings->security['health_check_secret'];
        if ($secret !== null) {
            $authHeader   = $request->getHeaderLine('Authorization');
            $customHeader = $request->getHeaderLine('X-Health-Key');
            $queryKey     = $request->getQueryParams()['key'] ?? null;

            $providedToken = null;
            if (! empty($customHeader)) {
                $providedToken = trim($customHeader);
            } elseif (str_starts_with($authHeader, 'Bearer ')) {
                $providedToken = trim(substr($authHeader, 7));
            } elseif (! empty($queryKey) && is_string($queryKey)) {
                $providedToken = trim($queryKey);
            }

            if ($providedToken === null || ! hash_equals($secret, $providedToken)) {
                return $this->response->error(
                    message: 'Unauthorized: Invalid or missing health check key.',
                    type: 'UNAUTHORIZED',
                    statusCode: 401
                );
            }
        }

        // Check MySQL Database
        $dbStatus  = 'unavailable';
        $dbHealthy = false;
        try {
            $stmt = $this->pdo->query('SELECT 1');
            if ($stmt !== false) {
                $dbStatus  = 'connected';
                $dbHealthy = true;
            }
        } catch (Throwable $e) {
            $this->logger?->error('Database health check failed', [
                'type'    => get_class($e),
                'message' => (string)preg_replace('/(password|pass|secret|key|token|auth|pwd)=([^&\s;]+)/i', '$1=***REDACTED***', $e->getMessage()),
                'code'    => $e->getCode(),
            ]);
            $dbStatus = 'unavailable';
        }

        // Check Memcached
        $cacheStatus = $this->cache->isConnected() ? 'connected' : 'unavailable';

        $isReady    = $dbHealthy;
        $httpStatus = $isReady ? 200 : 503;

        return $this->response->json([
            'status'    => $isReady ? 'ready' : 'unhealthy',
            'timestamp' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
            'services'  => [
                'mysql'     => $dbStatus,
                'memcached' => $cacheStatus,
            ],
        ], $httpStatus);
    }
}
