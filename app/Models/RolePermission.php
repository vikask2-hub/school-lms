<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['role', 'permission', 'is_allowed'])]
class RolePermission extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return ['is_allowed' => 'boolean'];
    }
}
