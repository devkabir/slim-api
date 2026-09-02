<?php

declare(strict_types=1);

namespace App\Config;

class AppConfig
{
    public static function isDebug(): bool
    {
        if (self::isProduction()) {
            return false;
        }

        $debug = $_ENV['APP_DEBUG'] ?? null;
        if ($debug === null) {
            return ! self::isProduction();
        }

        if (is_bool($debug)) {
            return $debug;
        }

        $normalized = strtolower(trim((string)$debug));

        return in_array($normalized, ['true', '1', 'yes', 'on'], true);
    }

    public static function isProduction(): bool
    {
        return self::getEnv() === 'production';
    }

    public static function getEnv(): string
    {
        return strtolower((string)($_ENV['APP_ENV'] ?? 'development'));
    }

    public static function getHealthCheckSecret(): ?string
    {
        $secret = $_ENV['HEALTH_CHECK_SECRET'] ?? $_ENV['HEALTH_CHECK_KEY'] ?? null;

        return ($secret !== null && trim((string)$secret) !== '') ? trim((string)$secret) : null;
    }
}
