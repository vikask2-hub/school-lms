<?php

namespace App\Models;

use Database\Factories\AcademicClassFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'section', 'room', 'capacity', 'academic_year'])]
class AcademicClass extends Model
{
    /** @use HasFactory<AcademicClassFactory> */
    use HasFactory;

    public function students(): HasMany
    {
        return $this->hasMany(StudentProfile::class);
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class);
    }
}
