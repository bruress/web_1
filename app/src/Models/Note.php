<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class Note extends Model
{
    protected $table = 'demo';

    public const UPDATED_AT = null;

    protected $fillable = ['note'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
