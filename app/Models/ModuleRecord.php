<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['module', 'audience', 'student_id', 'subject_id', 'owner_id', 'creator_id', 'title', 'subtitle', 'description', 'status', 'occurred_at', 'meta'])]
class ModuleRecord extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime', 'meta' => 'array'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creator_id');
    }
}
