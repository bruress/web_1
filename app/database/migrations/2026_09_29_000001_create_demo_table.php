<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration {
    public function up(): void
    {
        // Старые тома уже содержат demo из initdb. Их данные сохраняем.
        if (!Capsule::schema()->hasTable('demo')) {
            Capsule::schema()->create('demo', static function (Blueprint $table): void {
                $table->increments('id');
                $table->timestampTz('created_at')->useCurrent();
                $table->text('note')->nullable();
            });
        }
    }

    public function down(): void
    {
        Capsule::schema()->dropIfExists('demo');
    }
};
