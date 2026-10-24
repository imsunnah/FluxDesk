<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public Message $message;

    public function __construct(Message $message)
    {
        $this->message = $message->load(['sender', 'conversation']);
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PresenceChannel('chat.' . $this->message->conversation_id),
        ];
    }

    /**
     * The event's broadcast name.
     */
    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * Get the data to broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'message' => [
                'id' => $this->message->id,
                'conversation_id' => $this->message->conversation_id,
                'sender_type' => $this->message->sender_type,
                'sender_id' => $this->message->sender_id,
                'body' => $this->message->body,
                'is_internal_note' => (bool) $this->message->is_internal_note,
                'is_read' => (bool) $this->message->is_read,
                'attachments' => $this->message->attachments,
                'created_at' => $this->message->created_at ? $this->message->created_at->toIso8601String() : now()->toIso8601String(),
                'sender' => $this->message->sender ? [
                    'id' => $this->message->sender->id,
                    'name' => $this->message->sender->name,
                    'avatar' => $this->message->sender->avatar,
                    'role' => $this->message->sender->role,
                ] : null,
            ],
            'conversation' => [
                'id' => $this->message->conversation->id,
                'last_message_at' => $this->message->conversation->last_message_at ? $this->message->conversation->last_message_at->toIso8601String() : now()->toIso8601String(),
                'status' => $this->message->conversation->status,
                'priority' => $this->message->conversation->priority,
            ]
        ];
    }
}
