<?php

declare(strict_types=1);

use App\Models\Note;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    private const TEST_NOTE = 'Тестовая запись из миграции Eloquent';

    public function up(): void
    {
        Note::create(['note' => self::TEST_NOTE]);
    }

    public function down(): void
    {
        Note::where('note', self::TEST_NOTE)->delete();
    }
};
