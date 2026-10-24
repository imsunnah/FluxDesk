<?php

namespace App\Http\Controllers;

use App\Events\ConversationUpdated;
use App\Events\MessageSent;
use App\Jobs\AuditLogMessage;
use App\Jobs\ProcessOfflineNotification;
use App\Models\CannedResponse;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ChatController extends Controller
{
    /**
     * Monolithic Chat Workspace handler serving Inertia props.
     */
    public function index(Request $request): Response
    {
        $currentAgent = Auth::user() ?? User::first();
        if (!$currentAgent) {
            $currentAgent = User::create([
                'name' => 'Alex Rivera',
                'email' => 'alex@fluxdesk.io',
                'password' => bcrypt('password'),
                'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=120&h=120&fit=crop&crop=faces',
                'role' => 'Lead Support Specialist',
            ]);
        }

        $filter = $request->query('filter', 'all'); // 'all', 'unread', 'mine', 'resolved'
        $search = $request->query('search', '');
        $selectedId = $request->query('conversation_id');

        $query = Conversation::query()
            ->with(['assignedAgent', 'latestMessage'])
            ->withCount(['messages as unread_count' => function ($q) {
                $q->where('is_read', false)->where('sender_type', 'customer');
            }]);

        // Filter tabs logic
        if ($filter === 'unread') {
            $query->has('messages', '>', 0)
                ->whereHas('messages', function ($q) {
                    $q->where('is_read', false)->where('sender_type', 'customer');
                });
        } elseif ($filter === 'mine') {
            $query->where('assigned_agent_id', $currentAgent->id);
        } elseif ($filter === 'resolved') {
            $query->where('status', 'resolved');
        } else {
            // 'all': usually exclude resolved or show all
            if (!$request->has('filter')) {
                // By default in support desks, open & pending are prioritized
            }
        }

        // Search logic across customer name, email, or message content
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                  ->orWhere('customer_email', 'like', "%{$search}%")
                  ->orWhereHas('messages', function ($sub) use ($search) {
                      $sub->where('body', 'like', "%{$search}%");
                  });
            });
        }

        $conversations = $query->orderByDesc('last_message_at')->get();

        // Determine active conversation
        $activeConversation = null;
        if ($selectedId) {
            $activeConversation = Conversation::with(['assignedAgent'])->find($selectedId);
        }
        if (!$activeConversation && $conversations->isNotEmpty()) {
            $activeConversation = $conversations->first();
        }

        // Load messages for active conversation and mark unread customer messages as read
        $activeMessages = [];
        if ($activeConversation) {
            Message::where('conversation_id', $activeConversation->id)
                ->where('sender_type', 'customer')
                ->where('is_read', false)
                ->update(['is_read' => true]);

            $activeMessages = Message::with(['sender'])
                ->where('conversation_id', $activeConversation->id)
                ->orderBy('created_at', 'asc')
                ->get();
        }

        $cannedResponses = CannedResponse::orderBy('shortcut', 'asc')->get();
        $agents = User::select('id', 'name', 'email', 'avatar', 'role')->get();

        return Inertia::render('Chat/Index', [
            'conversations' => $conversations,
            'activeConversation' => $activeConversation,
            'messages' => $activeMessages,
            'cannedResponses' => $cannedResponses,
            'agents' => $agents,
            'currentAgent' => $currentAgent,
            'filters' => [
                'filter' => $filter,
                'search' => $search,
            ],
            'reverb' => [
                'key' => config('reverb.apps.apps.0.key', env('VITE_REVERB_APP_KEY')),
                'host' => env('VITE_REVERB_HOST', 'localhost'),
                'port' => (int) env('VITE_REVERB_PORT', 8080),
                'scheme' => env('VITE_REVERB_SCHEME', 'http'),
            ],
        ]);
    }

    /**
     * Send a new message or internal note.
     */
    public function sendMessage(Request $request, Conversation $conversation)
    {
        $validated = $request->validate([
            'body' => 'required|string|max:5000',
            'is_internal_note' => 'boolean',
            'sender_type' => 'in:agent,customer',
            'attachments' => 'nullable|array',
        ]);

        $currentAgent = Auth::user() ?? User::first();
        $senderType = $validated['sender_type'] ?? 'agent';
        $senderId = ($senderType === 'agent') ? ($currentAgent?->id ?? 1) : null;
        $isInternalNote = (bool) ($validated['is_internal_note'] ?? false);

        // Internal notes can only be posted by agents
        if ($isInternalNote) {
            $senderType = 'agent';
            $senderId = $currentAgent?->id ?? 1;
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_type' => $senderType,
            'sender_id' => $senderId,
            'body' => $validated['body'],
            'is_internal_note' => $isInternalNote,
            'is_read' => ($senderType === 'customer') ? false : true,
            'attachments' => $validated['attachments'] ?? null,
        ]);

        // Touch last message at and update status if customer replies
        $conversation->last_message_at = now();
        if ($senderType === 'customer' && $conversation->status === 'resolved') {
            $conversation->status = 'open';
        }
        $conversation->save();

        // 1. WebSocket Broadcast: sub-100ms dispatch via Reverb
        broadcast(new MessageSent($message))->toOthers();

        // 2. Background Asynchronous Processing (Redis)
        ProcessOfflineNotification::dispatch($message);
        AuditLogMessage::dispatch($message, [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->back();
    }

    /**
     * Update conversation metadata (Status, Priority, Assigned Agent).
     */
    public function updateConversation(Request $request, Conversation $conversation)
    {
        $validated = $request->validate([
            'status' => 'nullable|in:open,pending,resolved',
            'priority' => 'nullable|in:low,medium,high',
            'assigned_agent_id' => 'nullable|exists:users,id',
        ]);

        $conversation->fill(array_filter($validated, fn ($val) => !is_null($val)));
        $conversation->save();

        broadcast(new ConversationUpdated($conversation))->toOthers();

        return redirect()->back();
    }

    /**
     * Create or update canned response shortcuts.
     */
    public function storeCannedResponse(Request $request)
    {
        $validated = $request->validate([
            'shortcut' => 'required|string|starts_with:/|max:50|unique:canned_responses,shortcut',
            'title' => 'required|string|max:100',
            'content' => 'required|string|max:1000',
        ]);

        CannedResponse::create($validated);

        return redirect()->back();
    }

    /**
     * Helper to simulate customer response for instantaneous interactive testing.
     */
    public function simulateCustomer(Request $request, Conversation $conversation)
    {
        $validated = $request->validate([
            'body' => 'required|string|max:2000',
        ]);

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'customer',
            'sender_id' => null,
            'body' => $validated['body'],
            'is_internal_note' => false,
            'is_read' => false,
        ]);

        $conversation->last_message_at = now();
        if ($conversation->status === 'resolved') {
            $conversation->status = 'open';
        }
        $conversation->save();

        broadcast(new MessageSent($message))->toOthers();
        AuditLogMessage::dispatch($message);

        return redirect()->back();
    }
}
