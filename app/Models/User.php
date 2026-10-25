<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'member_id',
        'name',
        'email',
        'password',
        'avatar',
        'role',
        'department',
        'designation',
        'is_admin',
        'is_customer',
        'is_team_lead',
        'project_id',
        'assigned_agent_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_customer' => 'boolean',
            'is_team_lead' => 'boolean',
            'project_id' => 'integer',
            'assigned_agent_id' => 'integer',
        ];
    }

    protected $appends = ['is_customer', 'is_staff', 'can_create_ticket', 'is_dual_role'];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class, 'client_id');
    }

    public function assignedTasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_user')->withTimestamps();
    }

    public function getIsCustomerAttribute(): bool
    {
        return (bool) ($this->attributes['is_customer'] ?? ($this->role === 'customer'));
    }

    public function getIsStaffAttribute(): bool
    {
        return $this->is_admin || in_array($this->role, ['admin', 'agent', 'staff', 'both', 'System Administrator', 'Senior Technical Agent', 'Customer Success Lead']);
    }

    public function getCanCreateTicketAttribute(): bool
    {
        return $this->is_customer;
    }

    public function getIsDualRoleAttribute(): bool
    {
        return $this->is_staff && $this->is_customer;
    }

    public function assignedAgent(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_agent_id');
    }

    public function assignedCustomers(): HasMany
    {
        return $this->hasMany(User::class, 'assigned_agent_id');
    }

    public function assignedConversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'assigned_agent_id');
    }

    public function memberConversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_members')
            ->withPivot('is_group_admin')
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }
}
