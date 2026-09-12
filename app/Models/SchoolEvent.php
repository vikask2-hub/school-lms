<?php

namespace App\Models;

use Database\Factories\SchoolEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['title', 'description', 'starts_at', 'ends_at', 'location', 'type', 'audience'])]
class SchoolEvent extends Model
{
    /** @use HasFactory<SchoolEventFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }
}
