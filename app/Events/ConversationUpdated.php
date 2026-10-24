<?php

namespace App\Events;

use App\Models\Conversation;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Conversation $conversation;

    public function __construct(Conversation $conversation)
    {
        $this->conversation = $conversation->load(['assignedAgent']);
    }

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('chat.' . $this->conversation->id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'conversation.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'conversation' => [
                'id' => $this->conversation->id,
                'status' => $this->conversation->status,
                'priority' => $this->conversation->priority,
                'assigned_agent_id' => $this->conversation->assigned_agent_id,
                'assigned_agent' => $this->conversation->assignedAgent ? [
                    'id' => $this->conversation->assignedAgent->id,
                    'name' => $this->conversation->assignedAgent->name,
                    'avatar' => $this->conversation->assignedAgent->avatar,
                ] : null,
                'last_message_at' => $this->conversation->last_message_at ? $this->conversation->last_message_at->toIso8601String() : null,
            ]
        ];
    }
}
