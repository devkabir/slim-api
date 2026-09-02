<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config\AppConfig;
use App\Config\AppLogger;
use App\Config\Cache;
use App\Config\Database;
use DateTimeImmutable;
use DateTimeInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Throwable;

class HealthController
{
    /**
     * Public liveness endpoint: verifies that the application process is running.
     * GET /health/live (or /health)
     */
    public function liveness(Request $request, Response $response): Response
    {
        $payload = [
            'status' => 'up',
            'app' => 'Slim 4 Todo CRUD API',
            'timestamp' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
        ];

        return $this->jsonResponse($response, $payload, 200);
    }

    /**
     * Protected readiness endpoint: verifies connectivity to dependencies.
     * GET /health/ready
     */
    public function readiness(Request $request, Response $response): Response
    {
        // Check authorization if a secret key is configured
        $secret = AppConfig::getHealthCheckSecret();
        if ($secret !== null) {
            $authHeader = $request->getHeaderLine('Authorization');
            $customHeader = $request->getHeaderLine('X-Health-Key');
            $queryKey = $request->getQueryParams()['key'] ?? null;

            $providedToken = null;
            if (!empty($customHeader)) {
                $providedToken = trim($customHeader);
            } elseif (str_starts_with($authHeader, 'Bearer ')) {
                $providedToken = trim(substr($authHeader, 7));
            } elseif (!empty($queryKey) && is_string($queryKey)) {
                $providedToken = trim($queryKey);
            }

            if ($providedToken === null || !hash_equals($secret, $providedToken)) {
                return $this->jsonResponse($response, [
                    'success' => false,
                    'error' => [
                        'type' => 'UNAUTHORIZED',
                        'message' => 'Unauthorized: Invalid or missing health check key.',
                    ]
                ], 401);
            }
        }

        // Check MySQL Database
        $dbStatus = 'unavailable';
        $dbHealthy = false;
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->query('SELECT 1');
            if ($stmt !== false) {
                $dbStatus = 'connected';
                $dbHealthy = true;
            }
        } catch (Throwable $e) {
            // Log raw diagnostic info securely on the server
            AppLogger::getLogger()->error('Database health check failed', [
                'type' => get_class($e),
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);
            $dbStatus = 'unavailable';
        }

        // Check Memcached
        $cacheStatus = Cache::isConnected() ? 'connected' : 'unavailable';

        $isReady = $dbHealthy;
        $httpStatus = $isReady ? 200 : 503;

        $payload = [
            'status' => $isReady ? 'ready' : 'unhealthy',
            'timestamp' => (new DateTimeImmutable())->format(DateTimeInterface::ATOM),
            'services' => [
                'mysql' => $dbStatus,
                'memcached' => $cacheStatus,
            ],
        ];

        return $this->jsonResponse($response, $payload, $httpStatus);
    }

    private function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write((string)json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withStatus($status);
    }
}
