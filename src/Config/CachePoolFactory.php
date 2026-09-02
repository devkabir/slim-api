<?php

declare(strict_types=1);

namespace App\Config;

use Memcached;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\MemcachedAdapter;
use Throwable;

final readonly class CachePoolFactory
{
    public static function createPool(Settings $settings, ?LoggerInterface $logger = null, string $namespace = 'app'): CacheItemPoolInterface
    {
        $defaultTtl = $settings->memcached['ttl'];

        if (extension_loaded('memcached')) {
            $host = $settings->memcached['host'];
            $port = $settings->memcached['port'];

            if ($host === '0.0.0.0') {
                $logger?->warning(
                    'Memcached is configured with 0.0.0.0. For security, bind to 127.0.0.1, a private VPC IP, or a Unix domain socket.'
                );
            }

            try {
                $memcached = new Memcached('slim_todo_app_' . $namespace);

                if (empty($memcached->getServerList())) {
                    $memcached->setOption(Memcached::OPT_COMPRESSION, true);
                    $memcached->setOption(Memcached::OPT_CONNECT_TIMEOUT, 1000);
                    $memcached->setOption(Memcached::OPT_SERVER_FAILURE_LIMIT, 2);
                    $memcached->setOption(Memcached::OPT_TCP_NODELAY, true);

                    $username = $settings->memcached['user'];
                    $password = $settings->memcached['pass'];

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
                    return new MemcachedAdapter($memcached, $namespace, $defaultTtl);
                }

                $logger?->warning('Memcached unreachable, falling back to filesystem cache adapter', [
                    'host' => $host,
                    'port' => $port,
                ]);
            } catch (Throwable $e) {
                $logger?->warning('Memcached adapter initialization failed, falling back to filesystem adapter', [
                    'message' => $e->getMessage(),
                ]);
            }
        }

        // Filesystem cache fallback
        $cacheDir = dirname(__DIR__, 2) . '/var/cache/' . $namespace;
        try {
            return new FilesystemAdapter($namespace, $defaultTtl, $cacheDir);
        } catch (Throwable) {
            return new ArrayAdapter($defaultTtl, false);
        }
    }

    public static function createRateLimiterPool(Settings $settings, ?LoggerInterface $logger = null): CacheItemPoolInterface
    {
        return self::createPool($settings, $logger, 'rate_limiter');
    }
}
