<?php

declare(strict_types=1);

namespace App;

use Monolog\Handler\RotatingFileHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;

final class Log
{
    private static ?Logger $logger = null;
    private static ?string $requestId = null;

    public static function requestId(): string
    {
        if (self::$requestId === null) {
            $incoming = (string) ($_SERVER['HTTP_X_REQUEST_ID'] ?? '');
            self::$requestId = preg_match('/^[A-Za-z0-9-]{1,63}$/D', $incoming) === 1
                ? $incoming
                : (PHP_SAPI === 'cli' ? '' : bin2hex(random_bytes(16)));
        }

        return self::$requestId;
    }

    public static function logger(): Logger
    {
        if (self::$logger === null) {
            $formatter = new LogFormatter();
            $file = new RotatingFileHandler(
                getenv('LOG_PATH') ?: __DIR__ . '/../storage/logs/app.log',
                7,
                Level::Info,
                true,
                0664,
                true,
            );
            $file->setFormatter($formatter);
            $handlers = [$file];
            if (getenv('LOG_STDERR') !== '0') {
                $stderr = new StreamHandler('php://stderr', Level::Info);
                $stderr->setFormatter($formatter);
                $handlers[] = $stderr;
            }
            self::$logger = new Logger('app', $handlers);
        }

        return self::$logger;
    }

    public static function info(string $message, array $context = []): void
    {
        self::write(Level::Info, $message, $context);
    }

    public static function error(string $message, array $context = []): void
    {
        self::write(Level::Error, $message, $context);
    }

    private static function write(Level $level, string $message, array $context): void
    {
        try {
            self::logger()->log($level, $message, $context);
        } catch (\Throwable $exception) {
            // Ошибка записи журнала не должна менять HTTP-ответ.
            error_log('Monolog: ' . $exception->getMessage());
        }
    }
}
