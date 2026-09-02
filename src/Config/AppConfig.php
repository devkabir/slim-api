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
        return in_array(self::getEnv(), ['production', 'prod'], true);
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

    public static function isForceHttps(): bool
    {
        $force = $_ENV['FORCE_HTTPS'] ?? null;
        if ($force === null) {
            return self::isProduction();
        }

        return self::toBool($force);
    }

    public static function isHstsEnabled(): bool
    {
        $enabled = $_ENV['HSTS_ENABLED'] ?? null;
        if ($enabled === null) {
            return self::isProduction();
        }

        return self::toBool($enabled);
    }

    public static function getHstsMaxAge(): int
    {
        $maxAge = $_ENV['HSTS_MAX_AGE'] ?? null;
        if ($maxAge !== null && is_numeric($maxAge)) {
            return (int)$maxAge;
        }

        return 31536000; // 1 year default
    }

    public static function getHstsIncludeSubDomains(): bool
    {
        $include = $_ENV['HSTS_INCLUDE_SUBDOMAINS'] ?? 'true';

        return self::toBool($include);
    }

    public static function getHstsPreload(): bool
    {
        $preload = $_ENV['HSTS_PRELOAD'] ?? 'false';

        return self::toBool($preload);
    }

    public static function getReferrerPolicy(): string
    {
        $policy = $_ENV['REFERRER_POLICY'] ?? null;
        if ($policy !== null && trim((string)$policy) !== '') {
            return trim((string)$policy);
        }

        return 'strict-origin-when-cross-origin';
    }

    public static function getContentSecurityPolicy(): string
    {
        $csp = $_ENV['CONTENT_SECURITY_POLICY'] ?? null;
        if ($csp !== null && trim((string)$csp) !== '') {
            return trim((string)$csp);
        }

        return "default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'";
    }

    public static function validateProductionConfig(): void
    {
        if ( ! self::isProduction()) {
            return;
        }

        $missing = [];

        $dbHost = $_ENV['DB_HOST'] ?? '';
        if (trim((string)$dbHost) === '') {
            $missing[] = 'DB_HOST';
        }

        $dbName = $_ENV['DB_NAME'] ?? '';
        if (trim((string)$dbName) === '') {
            $missing[] = 'DB_NAME';
        }

        $dbUser = $_ENV['DB_USER'] ?? '';
        if (trim((string)$dbUser) === '') {
            $missing[] = 'DB_USER';
        } elseif (strtolower(trim((string)$dbUser)) === 'root') {
            throw new \RuntimeException(
                'Security violation: Using the "root" database user in production is prohibited. Please configure a dedicated, least-privileged user.'
            );
        }

        $dbPass = $_ENV['DB_PASS'] ?? '';
        if (trim((string)$dbPass) === '') {
            $missing[] = 'DB_PASS (blank database passwords are prohibited in production)';
        }

        $healthSecret = self::getHealthCheckSecret();
        if ($healthSecret === null) {
            $missing[] = 'HEALTH_CHECK_SECRET';
        }

        if ( ! empty($missing)) {
            throw new \RuntimeException(
                'Application startup failed: Required production configuration is missing: ' . implode(', ', $missing)
            );
        }
    }

    private static function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string)$value));

        return in_array($normalized, ['true', '1', 'yes', 'on'], true);
    }
}

