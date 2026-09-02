<?php

declare(strict_types=1);

namespace App\Config;

use RuntimeException;

final readonly class Settings
{
    /**
     * @param array{
     *     host: string,
     *     port: int,
     *     name: string,
     *     user: string,
     *     pass: string,
     *     ssl_ca: ?string,
     *     ssl_cert: ?string,
     *     ssl_key: ?string,
     *     ssl_cipher: ?string,
     *     ssl_verify_server_cert: ?bool
     * } $db
     * @param array{
     *     host: string,
     *     port: int,
     *     user: ?string,
     *     pass: ?string,
     *     ttl: int
     * } $memcached
     * @param array{
     *     health_check_secret: ?string,
     *     force_https: bool,
     *     hsts_enabled: bool,
     *     hsts_max_age: int,
     *     hsts_include_subdomains: bool,
     *     hsts_preload: bool,
     *     referrer_policy: string,
     *     content_security_policy: string
     * } $security
     * @param array{
     *     read_limit_per_minute: int,
     *     mutation_limit_per_minute: int
     * } $rateLimit
     * @param array{
     *     max_body_size_bytes: int
     * } $bodyParser
     */
    public function __construct(
        public string $env,
        public bool $debug,
        public array $db,
        public array $memcached,
        public array $security,
        public array $rateLimit,
        public array $bodyParser
    ) {
    }

    public function isProduction(): bool
    {
        return in_array(strtolower($this->env), ['production', 'prod'], true);
    }

    public function isDebug(): bool
    {
        return $this->debug;
    }

    /**
     * @param array<string, mixed>|null $env
     */
    public static function fromEnv(?array $env = null): self
    {
        $source = $env ?? $_ENV;

        $appEnv = strtolower((string)($source['APP_ENV'] ?? 'development'));
        $isProd = in_array($appEnv, ['production', 'prod'], true);

        // Determine debug setting
        $rawDebug = $source['APP_DEBUG'] ?? null;
        if ($rawDebug === null) {
            $debug = ! $isProd;
        } else {
            $debug = self::toBool($rawDebug);
        }
        if ($isProd) {
            $debug = false; // Strictly false in production
        }

        // Database settings
        $db = [
            'host'                   => (string)($source['DB_HOST'] ?? '127.0.0.1'),
            'port'                   => isset($source['DB_PORT']) && is_numeric($source['DB_PORT']) ? (int)$source['DB_PORT'] : 3306,
            'name'                   => (string)($source['DB_NAME'] ?? ''),
            'user'                   => (string)($source['DB_USER'] ?? ''),
            'pass'                   => (string)($source['DB_PASS'] ?? ''),
            'ssl_ca'                 => ! empty($source['DB_SSL_CA']) ? (string)$source['DB_SSL_CA'] : null,
            'ssl_cert'               => ! empty($source['DB_SSL_CERT']) ? (string)$source['DB_SSL_CERT'] : null,
            'ssl_key'                => ! empty($source['DB_SSL_KEY']) ? (string)$source['DB_SSL_KEY'] : null,
            'ssl_cipher'             => ! empty($source['DB_SSL_CIPHER']) ? (string)$source['DB_SSL_CIPHER'] : null,
            'ssl_verify_server_cert' => isset($source['DB_SSL_VERIFY_SERVER_CERT']) ? self::toBool($source['DB_SSL_VERIFY_SERVER_CERT']) : null,
        ];

        // Memcached settings
        $memcached = [
            'host' => (string)($source['MEMCACHED_HOST'] ?? '127.0.0.1'),
            'port' => isset($source['MEMCACHED_PORT']) && is_numeric($source['MEMCACHED_PORT']) ? (int)$source['MEMCACHED_PORT'] : 11211,
            'user' => ! empty($source['MEMCACHED_USER']) ? (string)$source['MEMCACHED_USER'] : (! empty($source['MEMCACHED_USERNAME']) ? (string)$source['MEMCACHED_USERNAME'] : null),
            'pass' => ! empty($source['MEMCACHED_PASS']) ? (string)$source['MEMCACHED_PASS'] : (! empty($source['MEMCACHED_PASSWORD']) ? (string)$source['MEMCACHED_PASSWORD'] : null),
            'ttl'  => isset($source['MEMCACHED_TTL']) && is_numeric($source['MEMCACHED_TTL']) ? (int)$source['MEMCACHED_TTL'] : 3600,
        ];

        // Health check secret
        $healthSecret = $source['HEALTH_CHECK_SECRET'] ?? $source['HEALTH_CHECK_KEY'] ?? null;
        $healthSecret = ($healthSecret !== null && trim((string)$healthSecret) !== '') ? trim((string)$healthSecret) : null;

        // Force HTTPS
        $forceHttps = isset($source['FORCE_HTTPS']) ? self::toBool($source['FORCE_HTTPS']) : $isProd;

        // HSTS settings
        $hstsEnabled = isset($source['HSTS_ENABLED']) ? self::toBool($source['HSTS_ENABLED']) : $isProd;
        $hstsMaxAge  = isset($source['HSTS_MAX_AGE']) && is_numeric($source['HSTS_MAX_AGE']) ? (int)$source['HSTS_MAX_AGE'] : 31536000;
        $hstsInclude = isset($source['HSTS_INCLUDE_SUBDOMAINS']) ? self::toBool($source['HSTS_INCLUDE_SUBDOMAINS']) : true;
        $hstsPreload = isset($source['HSTS_PRELOAD']) ? self::toBool($source['HSTS_PRELOAD']) : false;

        // Headers
        $referrerPolicy = ! empty($source['REFERRER_POLICY']) && trim((string)$source['REFERRER_POLICY']) !== ''
            ? trim((string)$source['REFERRER_POLICY'])
            : 'strict-origin-when-cross-origin';

        $csp = isset($source['CONTENT_SECURITY_POLICY']) && trim((string)$source['CONTENT_SECURITY_POLICY']) !== ''
            ? trim((string)$source['CONTENT_SECURITY_POLICY'])
            : "default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'";

        $security = [
            'health_check_secret'     => $healthSecret,
            'force_https'             => $forceHttps,
            'hsts_enabled'            => $hstsEnabled,
            'hsts_max_age'            => $hstsMaxAge,
            'hsts_include_subdomains' => $hstsInclude,
            'hsts_preload'            => $hstsPreload,
            'referrer_policy'         => $referrerPolicy,
            'content_security_policy' => $csp,
        ];

        // Rate limit settings
        $rateLimit = [
            'read_limit_per_minute'     => isset($source['RATE_LIMIT_READ']) && is_numeric($source['RATE_LIMIT_READ']) ? (int)$source['RATE_LIMIT_READ'] : 300,
            'mutation_limit_per_minute' => isset($source['RATE_LIMIT_MUTATION']) && is_numeric($source['RATE_LIMIT_MUTATION']) ? (int)$source['RATE_LIMIT_MUTATION'] : 60,
        ];

        // Body parser settings
        $bodyParser = [
            'max_body_size_bytes' => isset($source['MAX_BODY_SIZE_BYTES']) && is_numeric($source['MAX_BODY_SIZE_BYTES']) ? (int)$source['MAX_BODY_SIZE_BYTES'] : 1048576,
        ];

        $settings = new self(
            env: $appEnv,
            debug: $debug,
            db: $db,
            memcached: $memcached,
            security: $security,
            rateLimit: $rateLimit,
            bodyParser: $bodyParser
        );

        $settings->validateProductionConfig();

        return $settings;
    }

    public function validateProductionConfig(): void
    {
        if (! $this->isProduction()) {
            return;
        }

        $missing = [];

        if (trim($this->db['host']) === '') {
            $missing[] = 'DB_HOST';
        }

        if (trim($this->db['name']) === '') {
            $missing[] = 'DB_NAME';
        }

        if (trim($this->db['user']) === '') {
            $missing[] = 'DB_USER';
        } elseif (strtolower(trim($this->db['user'])) === 'root') {
            throw new RuntimeException(
                'Security violation: Using the "root" database user in production is prohibited. Please configure a dedicated, least-privileged user.'
            );
        }

        if (trim($this->db['pass']) === '') {
            $missing[] = 'DB_PASS (blank database passwords are prohibited in production)';
        }

        if ($this->security['health_check_secret'] === null) {
            $missing[] = 'HEALTH_CHECK_SECRET';
        }

        if (! empty($missing)) {
            throw new RuntimeException(
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
