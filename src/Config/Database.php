<?php

declare(strict_types=1);

namespace App\Config;

use PDO;
use PDOException;
use RuntimeException;

class Database
{
    private static ?PDO $instance = null;

    public static function getConnection(): PDO
    {
        if (self::$instance === null) {
            $host   = $_ENV['DB_HOST'] ?? null;
            $port   = $_ENV['DB_PORT'] ?? '3306';
            $dbName = $_ENV['DB_NAME'] ?? null;
            $user   = $_ENV['DB_USER'] ?? null;
            $pass   = $_ENV['DB_PASS'] ?? null;

            // Enforce explicit configuration: no default 'root' or blank password fallback
            if ($host === null || trim((string)$host) === '') {
                throw new RuntimeException('Database host (DB_HOST) must be configured in environment variables.');
            }

            if ($dbName === null || trim((string)$dbName) === '') {
                throw new RuntimeException('Database name (DB_NAME) must be configured in environment variables.');
            }

            if ($user === null || trim((string)$user) === '') {
                throw new RuntimeException('Database user (DB_USER) must be configured in environment variables.');
            }

            if ($pass === null) {
                throw new RuntimeException('Database password (DB_PASS) must be configured in environment variables.');
            }

            $dsn     = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false, // Disabled emulation: enforce real server-side prepared statements
            ];

            // ------------------------------------------------------------------
            // SSL / TLS Encrypted Database Connection Support
            // ------------------------------------------------------------------
            if (!empty($_ENV['DB_SSL_CA'])) {
                $options[PDO::MYSQL_ATTR_SSL_CA] = (string)$_ENV['DB_SSL_CA'];
            }

            if (!empty($_ENV['DB_SSL_CERT'])) {
                $options[PDO::MYSQL_ATTR_SSL_CERT] = (string)$_ENV['DB_SSL_CERT'];
            }

            if (!empty($_ENV['DB_SSL_KEY'])) {
                $options[PDO::MYSQL_ATTR_SSL_KEY] = (string)$_ENV['DB_SSL_KEY'];
            }

            if (!empty($_ENV['DB_SSL_CIPHER'])) {
                $options[PDO::MYSQL_ATTR_SSL_CIPHER] = (string)$_ENV['DB_SSL_CIPHER'];
            }

            if (isset($_ENV['DB_SSL_VERIFY_SERVER_CERT'])) {
                $verify = strtolower(trim((string)$_ENV['DB_SSL_VERIFY_SERVER_CERT']));
                $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = in_array($verify, ['true', '1', 'yes', 'on'], true);
            }

            try {
                self::$instance = new PDO($dsn, (string)$user, (string)$pass, $options);
            } catch (PDOException $e) {
                AppLogger::getLogger()->error('Database connection failed', [
                    'host' => $host,
                    'port' => $port,
                    'db'   => $dbName,
                    'code' => $e->getCode(),
                ]);
                throw new RuntimeException('Database connection failed.', (int)$e->getCode(), $e);
            }
        }

        return self::$instance;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }
}
