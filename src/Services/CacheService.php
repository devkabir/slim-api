<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\Settings;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Throwable;

final class CacheService
{
    public function __construct(
        private readonly CacheItemPoolInterface $pool,
        private readonly Settings $settings,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    public function get(string $key): mixed
    {
        $normalizedKey = $this->sanitizeKey($key);

        try {
            $item = $this->pool->getItem($normalizedKey);
            if ($item->isHit()) {
                return $item->get();
            }
        } catch (Throwable $e) {
            $this->logger?->warning('Cache get operation failed', [
                'key'     => $normalizedKey,
                'message' => $this->sanitizeErrorMessage($e->getMessage()),
            ]);
        }

        return false;
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $normalizedKey = $this->sanitizeKey($key);
        $ttl ??= $this->settings->memcached['ttl'];

        try {
            $item = $this->pool->getItem($normalizedKey);
            $item->set($value);
            if ($ttl > 0) {
                $item->expiresAfter($ttl);
            }

            return $this->pool->save($item);
        } catch (Throwable $e) {
            $this->logger?->warning('Cache set operation failed', [
                'key'     => $normalizedKey,
                'message' => $this->sanitizeErrorMessage($e->getMessage()),
            ]);

            return false;
        }
    }

    public function delete(string $key): bool
    {
        $normalizedKey = $this->sanitizeKey($key);

        try {
            return $this->pool->deleteItem($normalizedKey);
        } catch (Throwable $e) {
            $this->logger?->warning('Cache delete operation failed', [
                'key'     => $normalizedKey,
                'message' => $this->sanitizeErrorMessage($e->getMessage()),
            ]);

            return false;
        }
    }

    /**
     * @param string[] $keys
     */
    public function deleteMulti(array $keys): bool
    {
        if (empty($keys)) {
            return true;
        }

        $normalizedKeys = array_map(fn(string $k) => $this->sanitizeKey($k), $keys);

        try {
            return $this->pool->deleteItems($normalizedKeys);
        } catch (Throwable $e) {
            $this->logger?->warning('Cache deleteMulti operation failed', [
                'count'   => count($keys),
                'message' => $this->sanitizeErrorMessage($e->getMessage()),
            ]);

            return false;
        }
    }

    public function getNamespaceVersion(string $namespace): int
    {
        $versionKey = 'ns_ver.' . $this->sanitizeKey($namespace);
        $version    = $this->get($versionKey);

        if ($version === false || ! is_int($version)) {
            $version = 1;
            $this->set($versionKey, $version, 0);
        }

        return $version;
    }

    public function incrementNamespaceVersion(string $namespace): int
    {
        $versionKey = 'ns_ver.' . $this->sanitizeKey($namespace);
        $current    = $this->get($versionKey);
        $newVersion = (is_int($current) ? $current : 1) + 1;

        $this->set($versionKey, $newVersion, 0);

        return $newVersion;
    }

    public function isHealthy(): bool
    {
        try {
            $testKey = 'health_check_probe';
            $item    = $this->pool->getItem($testKey);
            $item->set(1);
            $item->expiresAfter(5);
            $this->pool->save($item);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function getPool(): CacheItemPoolInterface
    {
        return $this->pool;
    }

    /**
     * Normalize key to satisfy PSR-6: [a-zA-Z0-9_.]
     */
    private function sanitizeKey(string $key): string
    {
        return (string)preg_replace('/[^a-zA-Z0-9_.]/', '.', $key);
    }

    private function sanitizeErrorMessage(string $message): string
    {
        return (string)preg_replace('/(password|pass|secret|key|token|auth|pwd)=([^&\s;]+)/i', '$1=***REDACTED***', $message);
    }
}
