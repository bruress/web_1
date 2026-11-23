<?php

declare(strict_types=1);

namespace App;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Events\Dispatcher;
use PDO;

final class Database
{
    private static ?Capsule $capsule = null;

    public static function boot(): Capsule
    {
        if (self::$capsule !== null) {
            return self::$capsule;
        }

        $capsule = new Capsule();
        $capsule->addConnection([
            'driver' => 'pgsql',
            'host' => getenv('DB_HOST') ?: 'postgres',
            'port' => getenv('DB_PORT') ?: '5432',
            'database' => getenv('DB_NAME') ?: 'app',
            'username' => getenv('DB_USER') ?: 'app',
            'password' => getenv('DB_PASSWORD') ?: '',
            'charset' => 'utf8',
            'prefix' => '',
            'search_path' => getenv('DB_SCHEMA') ?: 'public',
            'sslmode' => getenv('DB_SSLMODE') ?: 'prefer',
            'application_name' => Log::requestId() ?: 'web_1',
            'options' => [PDO::ATTR_TIMEOUT => 3],
        ]);
        $capsule->setEventDispatcher(new Dispatcher($capsule->getContainer()));
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        return self::$capsule = $capsule;
    }

    // Совместимость с health-check и учебным сценарием pg_sleep.
    public static function connect(): PDO
    {
        return self::boot()->getConnection()->getPdo();
    }
}
