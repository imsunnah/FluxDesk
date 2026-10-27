<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'client_id',
        'team_lead_id',
        'team_member_ids',
        'start_date',
        'target_date',
        'description',
        'status',
    ];

    protected $casts = [
        'team_member_ids' => 'array',
        'start_date' => 'date:Y-m-d',
        'target_date' => 'date:Y-m-d',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function clientRecord(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'client_id');
    }

    public function teamLead(): BelongsTo
    {
        return $this->belongsTo(User::class, 'team_lead_id');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Conversation::class, 'project_id');
    }

    public function clients(): HasMany
    {
        return $this->hasMany(User::class, 'project_id');
    }
}
