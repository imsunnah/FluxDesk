<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'project_id',
        'client_id',
        'description',
        'status',
        'priority',
        'approximate_hours',
        'completed_hours',
        'completion_remarks',
        'assigned_to',
        'created_by',
        'due_date',
    ];

    protected $casts = [
        'due_date' => 'date:Y-m-d',
        'approximate_hours' => 'float',
        'completed_hours' => 'float',
    ];

    protected $appends = [
        'time_ratio',
        'is_overdue_time',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function assignees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_user')->withTimestamps();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Ratio of completed (actual) hours to approximate (estimated) hours.
     * Ratio > 1.0 means the task took more time than estimated!
     */
    public function getTimeRatioAttribute(): ?float
    {
        if ($this->approximate_hours && $this->approximate_hours > 0 && $this->completed_hours !== null) {
            return round(($this->completed_hours / $this->approximate_hours), 2);
        }
        return null;
    }

    public function getIsOverdueTimeAttribute(): bool
    {
        if ($this->approximate_hours && $this->approximate_hours > 0 && $this->completed_hours !== null) {
            return $this->completed_hours > $this->approximate_hours;
        }
        return false;
    }
}
