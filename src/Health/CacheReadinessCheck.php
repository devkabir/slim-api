<?php

declare(strict_types=1);

namespace App\Health;

use App\Services\CacheService;

final readonly class CacheReadinessCheck implements ReadinessCheckInterface
{
    public function __construct(
        private CacheService $cacheService
    ) {
    }

    public function name(): string
    {
        return 'memcached';
    }

    public function check(): ReadinessResult
    {
        $healthy = $this->cacheService->isHealthy();

        return new ReadinessResult(
            name: $this->name(),
            status: $healthy ? 'connected' : 'unavailable',
            isCritical: false, // Non-critical: degraded cache doesn't block overall readiness
            isHealthy: $healthy
        );
    }
}
