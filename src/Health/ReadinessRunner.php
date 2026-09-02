<?php

declare(strict_types=1);

namespace App\Health;

final readonly class ReadinessRunner
{
    /**
     * @param ReadinessCheckInterface[] $checks
     */
    public function __construct(
        private array $checks = []
    ) {
    }

    /**
     * @return array{isReady: bool, services: array<string, string>}
     */
    public function run(): array
    {
        $isReady  = true;
        $services = [];

        foreach ($this->checks as $check) {
            $result = $check->check();
            $services[$result->name] = $result->status;

            if ($result->isCritical && ! $result->isHealthy) {
                $isReady = false;
            }
        }

        return [
            'isReady'  => $isReady,
            'services' => $services,
        ];
    }
}
