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
            $host   = $_ENV['DB_HOST'] ?? '127.0.0.1';
            $port   = $_ENV['DB_PORT'] ?? '3306';
            $dbName = $_ENV['DB_NAME'] ?? 'slim_todo_db';
            $user   = $_ENV['DB_USER'] ?? 'root';
            $pass   = $_ENV['DB_PASS'] ?? '';

            $dsn     = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$instance = new PDO($dsn, $user, $pass, $options);
            } catch (PDOException $e) {
                AppLogger::getLogger()->error('Database connection failed', [
                    'message' => $e->getMessage(),
                    'code'    => $e->getCode(),
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
