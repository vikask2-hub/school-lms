<?php

namespace App\Models;

use Database\Factories\ExamResultFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['student_id', 'subject_id', 'exam_name', 'exam_date', 'marks', 'max_marks', 'grade', 'is_published'])]
class ExamResult extends Model
{
    /** @use HasFactory<ExamResultFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['exam_date' => 'date', 'is_published' => 'boolean'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
