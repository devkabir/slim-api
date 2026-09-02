<?php

declare(strict_types=1);

namespace App\Health;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Throwable;

final readonly class DatabaseReadinessCheck implements ReadinessCheckInterface
{
    public function __construct(
        private Connection $connection,
        private ?LoggerInterface $logger = null
    ) {
    }

    public function name(): string
    {
        return 'mysql';
    }

    public function check(): ReadinessResult
    {
        try {
            $result = $this->connection->executeQuery('SELECT 1')->fetchOne();
            $healthy = ($result !== false && $result !== null);

            return new ReadinessResult(
                name: $this->name(),
                status: $healthy ? 'connected' : 'unavailable',
                isCritical: true,
                isHealthy: $healthy
            );
        } catch (Throwable $e) {
            $this->logger?->error('Database readiness check failed', [
                'type'    => get_class($e),
                'message' => (string)preg_replace('/(password|pass|secret|key|token|auth|pwd)=([^&\s;]+)/i', '$1=***REDACTED***', $e->getMessage()),
                'code'    => $e->getCode(),
            ]);

            return new ReadinessResult(
                name: $this->name(),
                status: 'unavailable',
                isCritical: true,
                isHealthy: false
            );
        }
    }
}
