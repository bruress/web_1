<?php

declare(strict_types=1);

namespace App;

use Illuminate\Database\Migrations\DatabaseMigrationRepository;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Filesystem\Filesystem;

final class Migrations
{
    public static function migrator(): Migrator
    {
        $capsule = Database::boot();

        return new Migrator(
            new DatabaseMigrationRepository($capsule->getDatabaseManager(), 'migrations'),
            $capsule->getDatabaseManager(),
            new Filesystem(),
            $capsule->getEventDispatcher(),
        );
    }

    public static function paths(): array
    {
        return [__DIR__ . '/../database/migrations'];
    }
}
