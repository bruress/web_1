<?php

declare(strict_types=1);

namespace App;

final class RequestLogger
{
    public static function start(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        $started = hrtime(true);
        $body = '';
        header('X-Request-Id: ' . Log::requestId());

        // FINAL вызывается и после exit/редиректа. Учитываем явные flush/clean,
        // чтобы в журнал попало именно отправленное тело, целиком и один раз.
        ob_start(static function (string $chunk, int $phase) use ($started, &$body): string {
            if (($phase & PHP_OUTPUT_HANDLER_CLEAN) === 0) {
                $body .= $chunk;
            }
            if (($phase & PHP_OUTPUT_HANDLER_FINAL) !== 0) {
                $status = http_response_code() ?: 200;
                $error = error_get_last();
                if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                    $status = 500;
                }
                $context = [
                    'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? ''),
                    'uri' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
                    'remote_addr' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
                    'status' => $status,
                    'duration_ms' => round((hrtime(true) - $started) / 1e6, 3),
                    'response_body' => ($_SERVER['REQUEST_METHOD'] ?? '') === 'HEAD'
                        || in_array($status, [204, 304], true) ? '' : $body,
                ];
                if ($status >= 500) {
                    Log::error('http_request', $context);
                } else {
                    Log::info('http_request', $context);
                }
            }

            return $chunk;
        });
    }
}
