<?php

namespace App\Models;

use Database\Factories\FeeRecordFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['student_id', 'fee_type', 'amount', 'paid_amount', 'due_date', 'status', 'reference', 'payment_method', 'paid_on'])]
class FeeRecord extends Model
{
    /** @use HasFactory<FeeRecordFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_amount' => 'decimal:2', 'due_date' => 'date', 'paid_on' => 'date'];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
