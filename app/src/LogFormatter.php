<?php

declare(strict_types=1);

namespace App;

use Monolog\Formatter\JsonFormatter;
use Monolog\LogRecord;

/** Сохраняет поля, которые разбирает существующий pipeline Graylog. */
final class LogFormatter extends JsonFormatter
{
    public function format(LogRecord $record): string
    {
        $data = json_decode(parent::format($record), true, 512, JSON_THROW_ON_ERROR);

        return $this->toJson([
            'time' => $data['datetime'],
            'log_level' => strtolower($data['level_name']),
            'message' => $data['message'],
            'request_id' => Log::requestId(),
            'route' => (string) ($_SERVER['SCRIPT_NAME'] ?? ''),
        ] + $data['context']) . "\n";
    }
}
