<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'primary_manager_id',
        'backup_escalation_id',
        'selected_departments',
        'assigned_member_ids',
        'sla_level',
        'assignment_date',
        'communication_preference',
        'notes',
    ];

    protected $casts = [
        'selected_departments' => 'array',
        'assigned_member_ids' => 'array',
        'assignment_date' => 'date:Y-m-d',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function primaryManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'primary_manager_id');
    }

    public function backupEscalation(): BelongsTo
    {
        return $this->belongsTo(User::class, 'backup_escalation_id');
    }
}
