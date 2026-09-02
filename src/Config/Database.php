<?php

declare(strict_types=1);

namespace App\Config;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use PDO;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;

final readonly class Database
{
    public static function createConnection(Settings $settings, ?LoggerInterface $logger = null): Connection
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

        $driverOptions = [
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        if (! empty($db['ssl_ca'])) {
            $driverOptions[PDO::MYSQL_ATTR_SSL_CA] = (string)$db['ssl_ca'];
        }

        if (! empty($db['ssl_cert'])) {
            $driverOptions[PDO::MYSQL_ATTR_SSL_CERT] = (string)$db['ssl_cert'];
        }

        if (! empty($db['ssl_key'])) {
            $driverOptions[PDO::MYSQL_ATTR_SSL_KEY] = (string)$db['ssl_key'];
        }

        if (! empty($db['ssl_cipher'])) {
            $driverOptions[PDO::MYSQL_ATTR_SSL_CIPHER] = (string)$db['ssl_cipher'];
        }

        if (isset($db['ssl_verify_server_cert'])) {
            $driverOptions[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = (bool)$db['ssl_verify_server_cert'];
        }

        $params = [
            'dbname'        => $dbName,
            'user'          => $user,
            'password'      => $pass,
            'host'          => $host,
            'port'          => $port,
            'driver'        => 'pdo_mysql',
            'charset'       => 'utf8mb4',
            'driverOptions' => $driverOptions,
        ];

        try {
            $config = new Configuration();

            return DriverManager::getConnection($params, $config);
        } catch (Throwable $e) {
            $logger?->error('Database connection initialization failed', [
                'host' => $host,
                'port' => $port,
                'db'   => $dbName,
                'code' => $e->getCode(),
            ]);
            throw new RuntimeException('Database connection failed.', (int)$e->getCode(), $e);
        }
    }
}
