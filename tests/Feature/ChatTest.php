<?php

namespace Tests\Feature;

use App\Events\ConversationUpdated;
use App\Events\MessageSent;
use App\Jobs\AuditLogMessage;
use App\Jobs\ProcessOfflineNotification;
use App\Models\CannedResponse;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use RefreshDatabase;

    protected User $agent;
    protected Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agent = User::create([
            'name' => 'Alex Rivera',
            'email' => 'alex@fluxdesk.io',
            'password' => bcrypt('password123'),
            'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=100&h=100&fit=crop',
            'role' => 'Lead Support Specialist',
        ]);

        $this->conversation = Conversation::create([
            'customer_name' => 'Sarah Jenkins',
            'customer_email' => 'sarah@fintechflow.io',
            'status' => 'open',
            'priority' => 'high',
            'assigned_agent_id' => $this->agent->id,
            'last_message_at' => now(),
        ]);
    }

    public function test_chat_workspace_renders_with_inertia_props(): void
    {
        $response = $this->actingAs($this->agent)->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Chat/Index')
            ->has('conversations')
            ->has('activeConversation')
            ->has('messages')
            ->has('cannedResponses')
            ->has('agents')
            ->has('reverb')
        );
    }

    public function test_agent_can_send_public_message(): void
    {
        Event::fake([MessageSent::class]);
        Queue::fake([ProcessOfflineNotification::class, AuditLogMessage::class]);

        $response = $this->actingAs($this->agent)->post("/conversations/{$this->conversation->id}/messages", [
            'body' => 'Checking our Reverb telemetry right now.',
            'is_internal_note' => false,
            'sender_type' => 'agent',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $this->conversation->id,
            'body' => 'Checking our Reverb telemetry right now.',
            'is_internal_note' => false,
            'sender_type' => 'agent',
            'sender_id' => $this->agent->id,
        ]);

        Event::assertDispatched(MessageSent::class);
        Queue::assertPushed(ProcessOfflineNotification::class);
        Queue::assertPushed(AuditLogMessage::class);
    }

    public function test_agent_can_post_internal_team_note(): void
    {
        Event::fake([MessageSent::class]);
        Queue::fake();

        $response = $this->actingAs($this->agent)->post("/conversations/{$this->conversation->id}/messages", [
            'body' => 'Redis queue supervisor was throttled at 512MB.',
            'is_internal_note' => true,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $this->conversation->id,
            'body' => 'Redis queue supervisor was throttled at 512MB.',
            'is_internal_note' => true,
        ]);

        Event::assertDispatched(MessageSent::class);
    }

    public function test_conversation_status_and_priority_can_be_updated(): void
    {
        Event::fake([ConversationUpdated::class]);

        $response = $this->actingAs($this->agent)->patch("/conversations/{$this->conversation->id}", [
            'status' => 'resolved',
            'priority' => 'low',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('conversations', [
            'id' => $this->conversation->id,
            'status' => 'resolved',
            'priority' => 'low',
        ]);

        Event::assertDispatched(ConversationUpdated::class);
    }

    public function test_canned_response_can_be_stored(): void
    {
        $response = $this->actingAs($this->agent)->post('/canned-responses', [
            'shortcut' => '/testshortcut',
            'title' => 'Test Title',
            'content' => 'Test canned response content',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('canned_responses', [
            'shortcut' => '/testshortcut',
            'title' => 'Test Title',
        ]);
    }

    public function test_customer_reply_can_be_simulated(): void
    {
        Event::fake([MessageSent::class]);

        $response = $this->post("/conversations/{$this->conversation->id}/simulate-customer", [
            'body' => 'The WebSocket connection is stable now!',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('messages', [
            'conversation_id' => $this->conversation->id,
            'body' => 'The WebSocket connection is stable now!',
            'sender_type' => 'customer',
        ]);

        Event::assertDispatched(MessageSent::class);
    }
}
