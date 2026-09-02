<?php

declare(strict_types=1);

namespace App\Config;

use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;

class AppLogger
{
    private static ?LoggerInterface $instance = null;

    public static function getLogger(): LoggerInterface
    {
        if (self::$instance === null) {
            $logger = new Logger('app');

            $logDir = dirname(__DIR__, 2) . '/logs';
            if ( ! is_dir($logDir)) {
                @mkdir($logDir, 0775, true);
            }

            $logLevel = AppConfig::isDebug() ? Level::Debug : Level::Info;

            // Rotating log file (daily, retains 14 days)
            $logFile     = $logDir . '/app.log';
            $fileHandler = new RotatingFileHandler($logFile, 14, $logLevel);
            $logger->pushHandler($fileHandler);

            // In non-production CLI or if logs dir isn't writable, also attach stderr
            if ( ! is_writable($logDir) && ! is_writable($logFile)) {
                $logger->pushHandler(new StreamHandler('php://stderr', $logLevel));
            }

            self::$instance = $logger;
        }

        return self::$instance;
    }

}
