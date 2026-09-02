<?php

declare(strict_types=1);

namespace App\Config;

use Memcached;
use Psr\Log\LoggerInterface;
use Throwable;

final class Cache
{
    private ?Memcached $memcached = null;
    private bool $connected = false;
    private bool $initialized = false;

    public function __construct(
        private readonly Settings $settings,
        private readonly ?LoggerInterface $logger = null,
        ?Memcached $memcached = null
    ) {
        if ($memcached !== null) {
            $this->memcached   = $memcached;
            $this->connected   = true;
            $this->initialized = true;
        }
    }

    public function getClient(): ?Memcached
    {
        $this->ensureInitialized();

        return $this->connected ? $this->memcached : null;
    }

    public function get(string $key): mixed
    {
        $cache = $this->getClient();
        if (! $cache) {
            return false;
        }

        try {
            $value = $cache->get($key);
            if ($cache->getResultCode() === Memcached::RES_SUCCESS) {
                return $value;
            }
        } catch (Throwable $e) {
            $this->logger?->warning('Cache get operation failed', [
                'key'     => $this->sanitizeKey($key),
                'message' => $this->sanitizeErrorMessage($e->getMessage()),
            ]);
        }

        return false;
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $cache = $this->getClient();
        if (! $cache) {
            return false;
        }

        if ($ttl === null) {
            $ttl = $this->settings->memcached['ttl'];
        }

        try {
            return $cache->set($key, $value, $ttl);
        } catch (Throwable $e) {
            $this->logger?->warning('Cache set operation failed', [
                'key'     => $this->sanitizeKey($key),
                'message' => $this->sanitizeErrorMessage($e->getMessage()),
            ]);

            return false;
        }
    }

    public function delete(string $key): bool
    {
        $cache = $this->getClient();
        if (! $cache) {
            return false;
        }

        try {
            return $cache->delete($key);
        } catch (Throwable $e) {
            $this->logger?->warning('Cache delete operation failed', [
                'key'     => $this->sanitizeKey($key),
                'message' => $this->sanitizeErrorMessage($e->getMessage()),
            ]);

            return false;
        }
    }

    /**
     * Delete multiple known keys directly in O(1) without scanning the cache with getAllKeys().
     *
     * @param string[] $keys
     */
    public function deleteMulti(array $keys): bool
    {
        $cache = $this->getClient();
        if (! $cache || empty($keys)) {
            return false;
        }

        try {
            if (method_exists($cache, 'deleteMulti')) {
                $cache->deleteMulti($keys);

                return true;
            }

            foreach ($keys as $key) {
                $cache->delete($key);
            }

            return true;
        } catch (Throwable $e) {
            $this->logger?->warning('Cache deleteMulti operation failed', [
                'count'   => count($keys),
                'message' => $this->sanitizeErrorMessage($e->getMessage()),
            ]);

            return false;
        }
    }

    /**
     * Get the current version of a cache namespace for versioned cache invalidation.
     */
    public function getNamespaceVersion(string $namespace): int
    {
        $versionKey = "ns_ver:{$namespace}";
        $version    = $this->get($versionKey);

        if ($version === false || ! is_int($version)) {
            $version = 1;
            $this->set($versionKey, $version, 0); // 0 = no expiration for namespace version
        }

        return $version;
    }

    /**
     * Increment the namespace version to invalidate all cached entries under this namespace in O(1) time.
     */
    public function incrementNamespaceVersion(string $namespace): int
    {
        $cache = $this->getClient();
        $key   = "ns_ver:{$namespace}";

        if (! $cache) {
            return 1;
        }

        try {
            $newVersion = $cache->increment($key, 1);
            if ($newVersion === false) {
                // If key did not exist, initialize it
                $newVersion = 2;
                $this->set($key, $newVersion, 0);
            }

            return (int)$newVersion;
        } catch (Throwable $e) {
            $this->logger?->warning('Cache incrementNamespaceVersion failed', [
                'namespace' => $namespace,
                'message'   => $this->sanitizeErrorMessage($e->getMessage()),
            ]);

            return 1;
        }
    }

    public function increment(string $key, int $offset = 1): int|false
    {
        $cache = $this->getClient();
        if (! $cache) {
            return false;
        }

        try {
            return $cache->increment($key, $offset);
        } catch (Throwable $e) {
            $this->logger?->warning('Cache increment operation failed', [
                'key'     => $this->sanitizeKey($key),
                'message' => $this->sanitizeErrorMessage($e->getMessage()),
            ]);

            return false;
        }
    }

    public function isConnected(): bool
    {
        $this->ensureInitialized();

        return $this->connected;
    }

    private function ensureInitialized(): void
    {
        if ($this->initialized) {
            return;
        }

        $this->initialized = true;

        if (! extension_loaded('memcached')) {
            $this->connected = false;
            return;
        }

        $host = $this->settings->memcached['host'];
        $port = $this->settings->memcached['port'];

        // Security check: warn if bound to wildcard / all interfaces
        if ($host === '0.0.0.0') {
            $this->logger?->warning(
                'Memcached is configured with 0.0.0.0. For security, bind to 127.0.0.1, a private VPC IP, or a Unix domain socket.'
            );
        }

        try {
            $memcached = new Memcached('slim_todo_app');

            if (empty($memcached->getServerList())) {
                $memcached->setOption(Memcached::OPT_COMPRESSION, true);
                $memcached->setOption(Memcached::OPT_CONNECT_TIMEOUT, 1000); // 1 second timeout
                $memcached->setOption(Memcached::OPT_SERVER_FAILURE_LIMIT, 2);
                $memcached->setOption(Memcached::OPT_TCP_NODELAY, true);

                $username = $this->settings->memcached['user'];
                $password = $this->settings->memcached['pass'];

                if ($username !== null && trim((string)$username) !== '' && $password !== null) {
                    if (method_exists($memcached, 'setSaslAuthData')) {
                        $memcached->setOption(Memcached::OPT_BINARY_PROTOCOL, true);
                        $memcached->setSaslAuthData((string)$username, (string)$password);
                    }
                }

                $memcached->addServer($host, $port);
            }

            $stats     = @$memcached->getStats();
            $serverKey = "{$host}:{$port}";

            if (isset($stats[$serverKey]) && is_array($stats[$serverKey]) && ($stats[$serverKey]['pid'] ?? 0) > 0) {
                $this->connected = true;
            } else {
                $this->connected = false;
                $this->logger?->warning('Memcached server unreachable, falling back to database', [
                    'host' => $host,
                    'port' => $port,
                ]);
            }

            $this->memcached = $memcached;
        } catch (Throwable $e) {
            $this->connected = false;
            $this->logger?->warning('Memcached initialization failed', [
                'message' => $this->sanitizeErrorMessage($e->getMessage()),
                'code'    => $e->getCode(),
            ]);
        }
    }

    /**
     * Sanitize error message to ensure no passwords or credentials leak in logs.
     */
    private function sanitizeErrorMessage(string $message): string
    {
        return (string)preg_replace('/(password|pass|secret|key|token|auth|pwd)=([^&\s;]+)/i', '$1=***REDACTED***', $message);
    }

    /**
     * Sanitize key for logging.
     */
    private function sanitizeKey(string $key): string
    {
        return (string)preg_replace('/(password|token|secret|key):[^\s]+/i', '$1:***', $key);
    }
}
