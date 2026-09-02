<?php

declare(strict_types=1);

namespace App\Bootstrap;

use App\Config\Settings;
use Dotenv\Dotenv;
use Throwable;

final class Bootstrap
{
    public static function init(?Settings $settings = null): Settings
    {
        // Enforce baseline error display prevention before anything else runs
        ini_set('display_errors', '0');
        ini_set('display_startup_errors', '0');
        ini_set('log_errors', '1');
        error_reporting(E_ALL);

        // Configure strict execution and network timeouts
        ini_set('max_execution_time', '30');
        ini_set('max_input_time', '30');
        ini_set('default_socket_timeout', '5');

        // Load environment variables
        $rootPath = dirname(__DIR__, 2);
        if (file_exists($rootPath . '/.env')) {
            $dotenv = Dotenv::createImmutable($rootPath);
            $dotenv->safeLoad();
        }

        $settings ??= Settings::fromEnv();

        // Adjust display_errors only for development when debug is enabled; strictly 0 in production
        if ($settings->isDebug() && ! $settings->isProduction()) {
            ini_set('display_errors', '1');
            ini_set('display_startup_errors', '1');
        } else {
            ini_set('display_errors', '0');
            ini_set('display_startup_errors', '0');
        }

        // Global bootstrap exception handler to prevent path disclosure on fatal bootstrap errors
        set_exception_handler(function (Throwable $e) use ($settings): void {
            error_log(sprintf(
                '[%s] Uncaught %s: %s in %s on line %d',
                date('c'),
                get_class($e),
                $e->getMessage(),
                $e->getFile(),
                $e->getLine()
            ));

            if (! headers_sent()) {
                http_response_code(500);
                header('Content-Type: application/json; charset=utf-8');
                header('X-Content-Type-Options: nosniff');
            }

            if ($settings->isDebug() && ! $settings->isProduction()) {
                echo json_encode([
                    'success' => false,
                    'error'   => [
                        'type'    => 'BOOTSTRAP_ERROR',
                        'message' => $e->getMessage(),
                        'file'    => $e->getFile(),
                        'line'    => $e->getLine(),
                    ],
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            } else {
                echo json_encode([
                    'success' => false,
                    'error'   => [
                        'type'    => 'SERVER_ERROR',
                        'message' => 'An internal server error occurred.',
                    ],
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            }
            exit(1);
        });

        return $settings;
    }
}
