<?php

namespace App\Models;

use Database\Factories\BookIssueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['student_id', 'book_title', 'author', 'accession_number', 'issued_on', 'due_on', 'returned_on', 'status'])]
class BookIssue extends Model
{
    /** @use HasFactory<BookIssueFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['issued_on' => 'date', 'due_on' => 'date', 'returned_on' => 'date'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
