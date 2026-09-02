<?php

declare(strict_types=1);

namespace App\Config;

use Memcached;
use Exception;

class Cache
{
    private static ?Memcached $instance = null;
    private static bool $connected = false;

    public static function getInstance(): ?Memcached
    {
        if (!extension_loaded('memcached')) {
            return null;
        }

        if (self::$instance === null) {
            $host = $_ENV['MEMCACHED_HOST'] ?? '127.0.0.1';
            $port = (int)($_ENV['MEMCACHED_PORT'] ?? 11211);

            $memcached = new Memcached('slim_todo_app');
            
            // Avoid adding duplicate servers on persistent connection
            if (empty($memcached->getServerList())) {
                $memcached->setOption(Memcached::OPT_COMPRESSION, true);
                $memcached->setOption(Memcached::OPT_CONNECT_TIMEOUT, 1000);
                $memcached->setOption(Memcached::OPT_SERVER_FAILURE_LIMIT, 2);
                $memcached->addServer($host, $port);
            }

            // Quick check if server is responsive
            $stats = @$memcached->getStats();
            $serverKey = "{$host}:{$port}";
            if (isset($stats[$serverKey]) && $stats[$serverKey]['pid'] > 0) {
                self::$connected = true;
            } else {
                self::$connected = false;
            }

            self::$instance = $memcached;
        }

        return self::$connected ? self::$instance : null;
    }

    public static function get(string $key): mixed
    {
        $cache = self::getInstance();
        if (!$cache) {
            return false;
        }

        try {
            $value = $cache->get($key);
            if ($cache->getResultCode() === Memcached::RES_SUCCESS) {
                return $value;
            }
        } catch (Exception $e) {
            // Silently fallback to DB on cache error
        }

        return false;
    }

    public static function set(string $key, mixed $value, ?int $ttl = null): bool
    {
        $cache = self::getInstance();
        if (!$cache) {
            return false;
        }

        if ($ttl === null) {
            $ttl = (int)($_ENV['MEMCACHED_TTL'] ?? 3600);
        }

        try {
            return $cache->set($key, $value, $ttl);
        } catch (Exception $e) {
            return false;
        }
    }

    public static function delete(string $key): bool
    {
        $cache = self::getInstance();
        if (!$cache) {
            return false;
        }

        try {
            return $cache->delete($key);
        } catch (Exception $e) {
            return false;
        }
    }

    public static function deleteByPrefix(string $prefix): bool
    {
        $cache = self::getInstance();
        if (!$cache) {
            return false;
        }

        try {
            $keys = $cache->getAllKeys();
            if (is_array($keys)) {
                foreach ($keys as $key) {
                    if (str_starts_with($key, $prefix)) {
                        $cache->delete($key);
                    }
                }
            }
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public static function isConnected(): bool
    {
        self::getInstance();
        return self::$connected;
    }
}
