<?php

namespace App\Jobs;

use App\Models\Message;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessOfflineNotification implements ShouldQueue
{
    use Queueable;

    public Message $message;

    /**
     * Create a new job instance.
     */
    public function __construct(Message $message)
    {
        $this->message = $message;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $conversation = $this->message->conversation;

        if (!$conversation) {
            return;
        }

        // If message is by agent and customer has not read it, trigger fallback notification
        if ($this->message->sender_type === 'agent' && !$this->message->is_internal_note) {
            Log::info("Dispatching offline email notification fallback for customer [{$conversation->customer_email}] on conversation #{$conversation->id}. Preview: " . substr($this->message->body, 0, 50));
        }

        // Audit log entry
        Log::channel('single')->info("Message #{$this->message->id} queued notification processed for conversation #{$conversation->id}");
    }
}
