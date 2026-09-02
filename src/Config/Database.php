<?php

declare(strict_types=1);

namespace App\Config;

use PDO;
use PDOException;
use Psr\Log\LoggerInterface;
use RuntimeException;

final readonly class Database
{
    public static function createConnection(Settings $settings, ?LoggerInterface $logger = null): PDO
    {
        $db     = $settings->db;
        $host   = $db['host'];
        $port   = $db['port'];
        $dbName = $db['name'];
        $user   = $db['user'];
        $pass   = $db['pass'];

        if (trim((string)$host) === '') {
            throw new RuntimeException('Database host (DB_HOST) must be configured in environment variables.');
        }

        if (trim((string)$dbName) === '') {
            throw new RuntimeException('Database name (DB_NAME) must be configured in environment variables.');
        }

        if (trim((string)$user) === '') {
            throw new RuntimeException('Database user (DB_USER) must be configured in environment variables.');
        }

        $dsn     = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // Disabled emulation: enforce real server-side prepared statements
        ];

        if (! empty($db['ssl_ca'])) {
            $options[PDO::MYSQL_ATTR_SSL_CA] = (string)$db['ssl_ca'];
        }

        if (! empty($db['ssl_cert'])) {
            $options[PDO::MYSQL_ATTR_SSL_CERT] = (string)$db['ssl_cert'];
        }

        if (! empty($db['ssl_key'])) {
            $options[PDO::MYSQL_ATTR_SSL_KEY] = (string)$db['ssl_key'];
        }

        if (! empty($db['ssl_cipher'])) {
            $options[PDO::MYSQL_ATTR_SSL_CIPHER] = (string)$db['ssl_cipher'];
        }

        if (isset($db['ssl_verify_server_cert'])) {
            $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = (bool)$db['ssl_verify_server_cert'];
        }

        try {
            return new PDO($dsn, (string)$user, (string)$pass, $options);
        } catch (PDOException $e) {
            $logger?->error('Database connection failed', [
                'host' => $host,
                'port' => $port,
                'db'   => $dbName,
                'code' => $e->getCode(),
            ]);
            throw new RuntimeException('Database connection failed.', (int)$e->getCode(), $e);
        }
    }
}
