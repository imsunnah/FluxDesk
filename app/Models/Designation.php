<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Designation extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'department_id',
        'department_name',
        'band',
        'responsibilities',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
