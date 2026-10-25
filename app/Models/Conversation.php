<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'ticket_number',
        'ticket_subject',
        'ticket_description',
        'group_name',
        'group_description',
        'is_encrypted',
        'customer_name',
        'customer_email',
        'customer_phone',
        'customer_location',
        'customer_company',
        'customer_avatar',
        'status',
        'priority',
        'project_id',
        'assigned_agent_id',
        'last_message_at',
    ];

    protected $casts = [
        'is_encrypted' => 'boolean',
        'project_id' => 'integer',
        'last_message_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = ['display_name'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function getDisplayNameAttribute(): string
    {
        if (!empty($this->ticket_subject)) {
            $prefix = $this->ticket_number ? "[{$this->ticket_number}] " : '';
            return $prefix . $this->ticket_subject;
        }

        if ($this->type === 'group' && !empty($this->group_name)) {
            return $this->group_name;
        }

        return $this->customer_name ?? 'Conversation';
    }

    public function assignedAgent(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_agent_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_members')
            ->withPivot('is_group_admin')
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at', 'asc');
    }

    public function unreadCount(): int
    {
        return $this->messages()
            ->where('is_read', false)
            ->where('sender_type', 'customer')
            ->count();
    }

    public function latestMessage()
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }
}
