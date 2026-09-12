<?php

namespace App\Models;

use Database\Factories\LearningItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['subject_id', 'teacher_id', 'type', 'title', 'description', 'url', 'scheduled_at', 'status'])]
class LearningItem extends Model
{
    /** @use HasFactory<LearningItemFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['scheduled_at' => 'datetime'];
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
