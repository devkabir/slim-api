<?php

declare(strict_types=1);

namespace App\Config;

use App\Services\CacheService;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

final class Cache
{
    private CacheService $service;

    public function __construct(
        Settings $settings,
        ?LoggerInterface $logger = null,
        ?CacheItemPoolInterface $pool = null
    ) {
        $pool ??= CachePoolFactory::createPool($settings, $logger);
        $this->service = new CacheService($pool, $settings, $logger);
    }

    public function get(string $key): mixed
    {
        return $this->service->get($key);
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        return $this->service->set($key, $value, $ttl);
    }

    public function delete(string $key): bool
    {
        return $this->service->delete($key);
    }

    /**
     * @param string[] $keys
     */
    public function deleteMulti(array $keys): bool
    {
        return $this->service->deleteMulti($keys);
    }

    public function getNamespaceVersion(string $namespace): int
    {
        return $this->service->getNamespaceVersion($namespace);
    }

    public function incrementNamespaceVersion(string $namespace): int
    {
        return $this->service->incrementNamespaceVersion($namespace);
    }

    public function isConnected(): bool
    {
        return $this->service->isHealthy();
    }

    public function getService(): CacheService
    {
        return $this->service;
    }
}
