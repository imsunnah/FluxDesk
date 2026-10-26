<?php

namespace App\Models;

use Carbon\Carbon;
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
        'delay_category',
        'delay_reason',
        'delay_status',
        'delay_locked_by',
        'delay_locked_at',
        'delay_revisions',
        'assigned_to',
        'created_by',
        'due_date',
        'completed_at',
    ];

    protected $casts = [
        'due_date' => 'date:Y-m-d',
        'completed_at' => 'datetime',
        'delay_locked_at' => 'datetime',
        'delay_revisions' => 'array',
        'approximate_hours' => 'float',
        'completed_hours' => 'float',
    ];

    protected $appends = [
        'time_ratio',
        'is_overdue_time',
        'is_overdue',
        'delay_duration_days',
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

    public function delayLockedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delay_locked_by');
    }

    /**
     * Check if task is overdue by deadline
     */
    public function getIsOverdueAttribute(): bool
    {
        if ($this->status === 'done') {
            if ($this->due_date && $this->completed_at) {
                return Carbon::parse($this->completed_at)->startOfDay()->greaterThan(Carbon::parse($this->due_date)->startOfDay());
            }
            return false;
        }

        if ($this->due_date) {
            return Carbon::parse($this->due_date)->endOfDay()->isPast();
        }

        return false;
    }

    /**
     * Calculate delay duration in days
     */
    public function getDelayDurationDaysAttribute(): int
    {
        if (!$this->due_date) {
            return 0;
        }

        $due = Carbon::parse($this->due_date)->startOfDay();

        if ($this->status === 'done') {
            $end = $this->completed_at ? Carbon::parse($this->completed_at)->startOfDay() : Carbon::parse($this->updated_at)->startOfDay();
            if ($end->greaterThan($due)) {
                return (int) $due->diffInDays($end);
            }
            return 0;
        }

        $today = Carbon::today();
        if ($today->greaterThan($due)) {
            return (int) $due->diffInDays($today);
        }

        return 0;
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
