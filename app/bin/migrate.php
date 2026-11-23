#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\Migrations;

$command = $argv[1] ?? 'up';
if (!in_array($command, ['up', 'down', 'status'], true) || count($argv) > 2) {
    fwrite(STDERR, "Usage: php bin/migrate.php [up|down|status]\n");
    exit(2);
}

try {
    $migrator = Migrations::migrator();
    $repository = $migrator->getRepository();

    if ($command === 'up') {
        if (!$repository->repositoryExists()) {
            $repository->createRepository();
        }
        $ran = array_map('basename', $migrator->run(Migrations::paths()));
        echo $ran === [] ? "Nothing to migrate.\n" : 'Migrated: ' . implode(', ', $ran) . "\n";
    } elseif ($command === 'down') {
        $rolledBack = $repository->repositoryExists()
            ? $migrator->rollback(Migrations::paths())
            : [];
        echo $rolledBack === [] ? "Nothing to roll back.\n" : 'Rolled back: ' . implode(', ', $rolledBack) . "\n";
    } else {
        $ran = $repository->repositoryExists() ? $repository->getRan() : [];
        foreach ($migrator->getMigrationFiles(Migrations::paths()) as $name => $path) {
            echo (in_array($name, $ran, true) ? '[ran] ' : '[pending] ') . $name . "\n";
        }
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'Migration failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
