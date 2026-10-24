<?php

namespace App\Jobs;

use App\Models\Message;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class AuditLogMessage implements ShouldQueue
{
    use Queueable;

    public Message $message;
    public array $metadata;

    /**
     * Create a new job instance.
     */
    public function __construct(Message $message, array $metadata = [])
    {
        $this->message = $message;
        $this->metadata = $metadata;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info("FluxDesk Audit Log: Message #{$this->message->id} | Conv #{$this->message->conversation_id} | Sender: {$this->message->sender_type} | Internal: " . ($this->message->is_internal_note ? 'YES' : 'NO'), [
            'message_id' => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'sender_id' => $this->message->sender_id,
            'bytes' => strlen($this->message->body),
            'metadata' => $this->metadata,
            'processed_at' => now()->toIso8601String(),
        ]);
    }
}
