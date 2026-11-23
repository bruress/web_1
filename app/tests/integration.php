<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Database;
use App\Models\Note;

function check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
    echo "OK: {$message}\n";
}

function phpCommand(array $arguments): array
{
    $command = [PHP_BINARY];
    if (php_ini_loaded_file() !== false) {
        $command = [...$command, '-c', php_ini_loaded_file()];
    }

    return [...$command, ...$arguments];
}

function cli(string $command, int $expectedCode = 0): string
{
    $process = proc_open(phpCommand([__DIR__ . '/../bin/migrate.php', $command]), [
        ['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w'],
    ], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start migration CLI.');
    }
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $code = proc_close($process);
    if ($code !== $expectedCode) {
        throw new RuntimeException("Migration {$command}: exit {$code}\n{$output}{$error}");
    }

    return $output . $error;
}

function startServer(string $outputPath): array
{
    $socket = stream_socket_server('tcp://127.0.0.1:0', $code, $error);
    if ($socket === false) {
        throw new RuntimeException($error);
    }
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $process = proc_open(phpCommand(['-S', $address, '-t', __DIR__ . '/../public']), [
        ['pipe', 'r'], ['file', $outputPath, 'a'], ['file', $outputPath, 'a'],
    ], $pipes);
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start HTTP server.');
    }
    fclose($pipes[0]);
    for ($attempt = 0; $attempt < 100; $attempt++) {
        $probe = @stream_socket_client('tcp://' . $address, $code, $error, 0.05);
        if (is_resource($probe)) {
            fclose($probe);
            return [$process, 'http://' . $address];
        }
        usleep(50_000);
    }
    proc_terminate($process);
    proc_close($process);
    throw new RuntimeException('HTTP server failed to start: ' . file_get_contents($outputPath));
}

function request(string $base, string $path, string $method = 'GET', ?array $form = null): array
{
    $headers = ['Connection: close'];
    $options = ['method' => $method, 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 5];
    if ($form !== null) {
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        $options['content'] = http_build_query($form);
    }
    $options['header'] = implode("\r\n", $headers);
    $stream = fopen($base . $path, 'r', false, stream_context_create(['http' => $options]));
    if ($stream === false) {
        throw new RuntimeException('HTTP request failed.');
    }
    $body = stream_get_contents($stream);
    $responseHeaders = stream_get_meta_data($stream)['wrapper_data'];
    fclose($stream);
    preg_match('/HTTP\/\S+ (\d+)/', $responseHeaders[0], $match);
    $id = '';
    foreach ($responseHeaders as $header) {
        if (stripos($header, 'X-Request-Id: ') === 0) {
            $id = substr($header, strlen('X-Request-Id: '));
        }
    }

    return ['status' => (int) $match[1], 'body' => $body, 'id' => $id,
        'method' => $method, 'uri' => $path, 'headers' => $responseHeaders];
}

$schema = 'web1_test_' . bin2hex(random_bytes(6));
$work = sys_get_temp_dir() . '/' . $schema;
mkdir($work, 0700);
putenv('DB_SCHEMA=' . $schema);
putenv('LOG_PATH=' . $work . '/app.log');
putenv('LOG_STDERR=0');
putenv('APP_METRICS_ENABLED=0');
$server = null;
$connection = null;
$schemaCreated = false;
$exitCode = 0;

try {
    $connection = Database::boot()->getConnection();
    // Уникальная схема изолирует тесты от таблиц и данных приложения.
    $connection->statement('CREATE SCHEMA "' . $schema . '"');
    $schemaCreated = true;
    check(str_contains(cli('status'), '[pending]'), 'CLI lists pending migrations');
    cli('down');
    check(str_contains(cli('invalid', 2), 'Usage:'), 'CLI rejects unknown commands');
    cli('up');
    check(Note::count() === 1, 'Migration creates demo and a test note through Eloquent');
    check($connection->table('migrations')->count() === 2, 'Migration history contains both migrations');
    check(str_contains(cli('up'), 'Nothing to migrate'), 'Repeated migration is a no-op');
    check(Note::count() === 1, 'Repeated migration does not duplicate the test note');
    check(substr_count(cli('status'), '[ran]') === 2, 'CLI reports applied migrations');

    [$server, $base] = startServer($work . '/server.log');
    $responses = [];
    $responses[] = $get = request($base, '/');
    check($get['status'] === 200 && str_contains($get['body'], 'Тестовая запись из миграции Eloquent'), 'GET renders migrated data');
    $text = '<script>alert("x")</script> & Привет';
    $responses[] = $post = request($base, '/', 'POST', ['note' => $text]);
    check($post['status'] === 303 && in_array('Location: /', $post['headers'], true), 'POST saves a note and redirects with 303');
    check($post['body'] === '' && Note::where('note', $text)->exists(), 'Redirect has an empty body and data is persisted');
    $responses[] = $get = request($base, '/?after=post');
    check(str_contains($get['body'], htmlspecialchars($text, ENT_QUOTES, 'UTF-8'))
        && !str_contains($get['body'], $text), 'User content is escaped in HTML');
    foreach ([[], ['note' => '   '], ['note' => ['bad']], ['note' => str_repeat('я', 501)]] as $form) {
        $responses[] = $invalid = request($base, '/', 'POST', $form);
        check($invalid['status'] === 422, 'Invalid note returns 422');
    }
    check(Note::count() === 2, 'Invalid submissions do not create rows');
    $responses[] = $boundary = request($base, '/', 'POST', ['note' => str_repeat('я', 500)]);
    check($boundary['status'] === 303 && Note::count() === 3, '500 Unicode characters are accepted');
    $responses[] = $head = request($base, '/', 'HEAD');
    check($head['status'] === 200 && $head['body'] === '', 'HEAD returns no body');
    $responses[] = $health = request($base, '/health.php');
    check($health['status'] === 200 && json_decode($health['body'], true)['status'] === 'ok', 'Health endpoint still connects through the shared database layer');
    $responses[] = $error = request($base, '/demo/error.php?rate=1');
    check($error['status'] === 500, 'Demo error returns 500 after exit');
    $responses[] = $cpu = request($base, '/demo/cpu.php?ms=0');
    check($cpu['status'] === 200, 'CPU demo is logged through the bootstrap');
    $responses[] = $slow = request($base, '/demo/slow.php?seconds=0');
    check($slow['status'] === 200, 'Slow-query demo still works');
    $responses[] = $metrics = request($base, '/metrics.php');
    check($metrics['status'] === 404, 'Disabled metrics return 404');

    cli('down');
    check(!$connection->getSchemaBuilder()->hasTable('demo'), 'Rollback removes demo');
    check($connection->table('migrations')->count() === 0, 'Rollback clears migration history');
    $responses[] = $unavailable = request($base, '/');
    check($unavailable['status'] === 503 && !str_contains($unavailable['body'], 'SQLSTATE'), 'Database errors return 503 without exposing SQL');
    $responses[] = $failedPost = request($base, '/', 'POST', ['note' => 'cannot save']);
    check($failedPost['status'] === 503, 'Failed writes return 503 instead of a success redirect');

    $records = [];
    foreach (glob($work . '/app-*.log') as $path) {
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $records[] = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        }
    }
    $http = array_values(array_filter($records, static fn (array $record): bool => $record['message'] === 'http_request'));
    check(count($http) === count($responses), 'Exactly one HTTP log entry is written for each request');
    foreach ($responses as $response) {
        $matches = array_values(array_filter($http, static fn (array $record): bool => $record['request_id'] === $response['id']));
        if ($response['id'] === '' || count($matches) !== 1) {
            throw new RuntimeException('Missing or duplicate request ID.');
        }
        $record = $matches[0];
        foreach (['status', 'method', 'uri'] as $key) {
            if ($record[$key] !== $response[$key]) {
                throw new RuntimeException('Wrong log field: ' . $key);
            }
        }
        if ($record['response_body'] !== $response['body'] || $record['duration_ms'] < 0) {
            throw new RuntimeException('Response body or duration in the log is incorrect.');
        }
        if ($record['log_level'] !== ($response['status'] >= 500 ? 'error' : 'info')) {
            throw new RuntimeException('Incorrect log severity.');
        }
    }
    check(true, 'Logged status, method, URI and full body match every actual HTTP response');
    check(count(array_filter($records, static fn (array $record): bool => $record['message'] === 'notes_read_failed')) > 0,
        'Monolog records database exceptions separately');

    // Проверяем обновление старого тома с таблицей из исходного initdb.
    $connection->statement('CREATE TABLE demo (id serial PRIMARY KEY, created_at timestamptz NOT NULL DEFAULT now(), note text)');
    $connection->table('demo')->insert(['note' => 'legacy note']);
    cli('up');
    check(Note::where('note', 'legacy note')->exists() && Note::count() === 2, 'Migration preserves existing initdb data');
    check(str_contains(cli('up'), 'Nothing to migrate'), 'Migration remains idempotent after adopting a legacy table');
    cli('down');
    cli('up');
    check(Note::count() === 1, 'Migrations can be applied again after rollback');
    echo "All integration checks passed.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, 'FAIL: ' . $exception->getMessage() . "\n");
    if (is_file($work . '/server.log')) {
        fwrite(STDERR, file_get_contents($work . '/server.log'));
    }
    $exitCode = 1;
} finally {
    if (is_resource($server)) {
        proc_terminate($server);
        proc_close($server);
    }
    if ($schemaCreated) {
        $connection->statement('DROP SCHEMA IF EXISTS "' . $schema . '" CASCADE');
    }
    foreach (glob($work . '/*') as $path) {
        unlink($path);
    }
    rmdir($work);
}

exit($exitCode);
