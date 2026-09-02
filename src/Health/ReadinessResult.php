<?php

declare(strict_types=1);

namespace App\Health;

final readonly class ReadinessResult
{
    public function __construct(
        public string $name,
        public string $status,
        public bool $isCritical,
        public bool $isHealthy
    ) {
    }
}
