<?php

declare(strict_types=1);

namespace App\Config;

use Memcached;
use Throwable;

class Cache
{
    private static ?Memcached $instance = null;
    private static bool $connected = false;

    public static function getInstance(): ?Memcached
    {
        if ( ! extension_loaded('memcached')) {
            return null;
        }

        if (self::$instance === null) {
            $host = (string)($_ENV['MEMCACHED_HOST'] ?? '127.0.0.1');
            $port = (int)($_ENV['MEMCACHED_PORT'] ?? 11211);

            // Security check: warn if bound to wildcard / all interfaces
            if ($host === '0.0.0.0') {
                AppLogger::getLogger()->warning(
                    'Memcached is configured with 0.0.0.0. For security, bind to 127.0.0.1, a private VPC IP, or a Unix domain socket.'
                );
            }

            try {
                $memcached = new Memcached('slim_todo_app');

                // Avoid adding duplicate servers on persistent connection
                if (empty($memcached->getServerList())) {
                    $memcached->setOption(Memcached::OPT_COMPRESSION, true);
                    $memcached->setOption(Memcached::OPT_CONNECT_TIMEOUT, 1000); // 1 second timeout
                    $memcached->setOption(Memcached::OPT_SERVER_FAILURE_LIMIT, 2);
                    $memcached->setOption(Memcached::OPT_TCP_NODELAY, true);

                    // SASL Authentication when credentials are provided
                    $username = $_ENV['MEMCACHED_USER'] ?? $_ENV['MEMCACHED_USERNAME'] ?? null;
                    $password = $_ENV['MEMCACHED_PASS'] ?? $_ENV['MEMCACHED_PASSWORD'] ?? null;

                    if ($username !== null && trim((string)$username) !== '' && $password !== null) {
                        if (method_exists($memcached, 'setSaslAuthData')) {
                            // SASL requires binary protocol
                            $memcached->setOption(Memcached::OPT_BINARY_PROTOCOL, true);
                            $memcached->setSaslAuthData((string)$username, (string)$password);
                        }
                    }

                    $memcached->addServer($host, $port);
                }

                // Check server connectivity without exposing credentials
                $stats     = @$memcached->getStats();
                $serverKey = "{$host}:{$port}";

                if (isset($stats[$serverKey]) && is_array($stats[$serverKey]) && ($stats[$serverKey]['pid'] ?? 0) > 0) {
                    self::$connected = true;
                } else {
                    self::$connected = false;
                    AppLogger::getLogger()->warning('Memcached server unreachable, falling back to database', [
                        'host' => $host,
                        'port' => $port,
                    ]);
                }

                self::$instance = $memcached;
            } catch (Throwable $e) {
                self::$connected = false;
                AppLogger::getLogger()->warning('Memcached initialization failed', [
                    'message' => self::sanitizeErrorMessage($e->getMessage()),
                    'code'    => $e->getCode(),
                ]);
            }
        }

        return self::$connected ? self::$instance : null;
    }

    public static function get(string $key): mixed
    {
        $cache = self::getInstance();
        if ( ! $cache) {
            return false;
        }

        try {
            $value = $cache->get($key);
            if ($cache->getResultCode() === Memcached::RES_SUCCESS) {
                return $value;
            }
        } catch (Throwable $e) {
            AppLogger::getLogger()->warning('Cache get operation failed', [
                'key'     => self::sanitizeKey($key),
                'message' => self::sanitizeErrorMessage($e->getMessage()),
            ]);
        }

        return false;
    }

    public static function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $cache = self::getInstance();
        if ( ! $cache) {
            return false;
        }

        if ($ttl === null) {
            $ttl = (int)($_ENV['MEMCACHED_TTL'] ?? 3600);
        }

        try {
            return $cache->set($key, $value, $ttl);
        } catch (Throwable $e) {
            AppLogger::getLogger()->warning('Cache set operation failed', [
                'key'     => self::sanitizeKey($key),
                'message' => self::sanitizeErrorMessage($e->getMessage()),
            ]);

            return false;
        }
    }

    public static function delete(string $key): bool
    {
        $cache = self::getInstance();
        if ( ! $cache) {
            return false;
        }

        try {
            return $cache->delete($key);
        } catch (Throwable $e) {
            AppLogger::getLogger()->warning('Cache delete operation failed', [
                'key'     => self::sanitizeKey($key),
                'message' => self::sanitizeErrorMessage($e->getMessage()),
            ]);

            return false;
        }
    }

    /**
     * Delete multiple known keys directly in O(1) without scanning the cache with getAllKeys().
     *
     * @param string[] $keys
     */
    public static function deleteMulti(array $keys): bool
    {
        $cache = self::getInstance();
        if ( ! $cache || empty($keys)) {
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
            AppLogger::getLogger()->warning('Cache deleteMulti operation failed', [
                'count'   => count($keys),
                'message' => self::sanitizeErrorMessage($e->getMessage()),
            ]);

            return false;
        }
    }

    /**
     * Get the current version of a cache namespace for versioned cache invalidation.
     */
    public static function getNamespaceVersion(string $namespace): int
    {
        $versionKey = "ns_ver:{$namespace}";
        $version    = self::get($versionKey);

        if ($version === false || ! is_int($version)) {
            $version = 1;
            self::set($versionKey, $version, 0); // 0 = no expiration for namespace version
        }

        return $version;
    }

    /**
     * Increment the namespace version to invalidate all cached entries under this namespace in O(1) time.
     */
    public static function incrementNamespaceVersion(string $namespace): int
    {
        $cache = self::getInstance();
        $key   = "ns_ver:{$namespace}";

        if ( ! $cache) {
            return 1;
        }

        try {
            $newVersion = $cache->increment($key, 1);
            if ($newVersion === false) {
                // If key did not exist, initialize it
                $newVersion = 2;
                self::set($key, $newVersion, 0);
            }

            return (int)$newVersion;
        } catch (Throwable $e) {
            AppLogger::getLogger()->warning('Cache incrementNamespaceVersion failed', [
                'namespace' => $namespace,
                'message'   => self::sanitizeErrorMessage($e->getMessage()),
            ]);

            return 1;
        }
    }

    public static function isConnected(): bool
    {
        self::getInstance();

        return self::$connected;
    }

    /**
     * Sanitize error message to ensure no passwords or credentials are leak in logs.
     */
    private static function sanitizeErrorMessage(string $message): string
    {
        return (string)preg_replace('/(password|pass|secret|key|token|auth|pwd)=([^&\s;]+)/i', '$1=***REDACTED***', $message);
    }

    /**
     * Sanitize key for logging.
     */
    private static function sanitizeKey(string $key): string
    {
        return (string)preg_replace('/(password|token|secret|key):[^\s]+/i', '$1:***', $key);
    }
}
