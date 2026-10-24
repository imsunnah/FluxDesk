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
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ChatController extends Controller
{
    /**
     * Monolithic Chat Workspace handler serving Inertia props.
     */
    public function index(Request $request): Response
    {
        $currentAgent = Auth::user();
        if (!$currentAgent) {
            $currentAgent = User::where('is_admin', true)->first() ?? User::first();
            if ($currentAgent) {
                Auth::login($currentAgent);
            } else {
                $currentAgent = User::create([
                    'member_id' => 'ADM-001',
                    'name' => 'Alex Rivera (Admin)',
                    'email' => 'alex@fluxdesk.io',
                    'password' => bcrypt('password123'),
                    'avatar' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=120&h=120&fit=crop&crop=faces',
                    'role' => 'System Administrator',
                    'is_admin' => true,
                ]);
                Auth::login($currentAgent);
            }
        }

        $filter = $request->query('filter', 'all'); // 'all', 'direct', 'groups', 'encrypted', 'unread'
        $search = $request->query('search', '');
        $selectedId = $request->query('conversation_id');

        $query = Conversation::query()
            ->with(['assignedAgent', 'latestMessage', 'members'])
            ->withCount(['messages as unread_count' => function ($q) {
                $q->where('is_read', false)->where('sender_type', 'customer');
            }]);

        // Filter tabs logic
        if ($filter === 'direct') {
            $query->where('type', 'direct')->where('is_encrypted', false);
        } elseif ($filter === 'groups') {
            $query->where('type', 'group');
        } elseif ($filter === 'encrypted') {
            $query->where('is_encrypted', true);
        } elseif ($filter === 'unread') {
            $query->has('messages', '>', 0)
                ->whereHas('messages', function ($q) {
                    $q->where('is_read', false)->where('sender_type', 'customer');
                });
        }

        // Search logic
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('customer_name', 'like', "%{$search}%")
                  ->orWhere('group_name', 'like', "%{$search}%")
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
            $activeConversation = Conversation::with(['assignedAgent', 'members'])->find($selectedId);
        }
        if (!$activeConversation && $conversations->isNotEmpty()) {
            $activeConversation = $conversations->first();
        }

        // Load messages for active conversation and mark unread messages as read
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
        $allMembers = User::select('id', 'member_id', 'name', 'email', 'avatar', 'role', 'is_admin')
            ->orderBy('id', 'asc')
            ->get();

        return Inertia::render('Chat/Index', [
            'conversations' => $conversations,
            'activeConversation' => $activeConversation,
            'messages' => $activeMessages,
            'cannedResponses' => $cannedResponses,
            'agents' => $allMembers,
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
     * Start direct 1-on-1 chat with a contact or team member.
     */
    public function createDirectChat(Request $request)
    {
        $validated = $request->validate([
            'target_user_id' => 'nullable|exists:users,id',
            'name' => 'required_without:target_user_id|nullable|string|max:100',
            'email' => 'required_without:target_user_id|nullable|email|max:150',
            'is_encrypted' => 'boolean',
            'initial_message' => 'nullable|string|max:1000',
        ]);

        $currentAgent = Auth::user();
        $isEncrypted = (bool) ($validated['is_encrypted'] ?? false);

        if (!empty($validated['target_user_id'])) {
            $targetUser = User::findOrFail($validated['target_user_id']);
            $customerName = $targetUser->name;
            $customerEmail = $targetUser->email;
            $avatar = $targetUser->avatar;
        } else {
            $customerName = $validated['name'];
            $customerEmail = $validated['email'];
            $avatar = 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=120&h=120&fit=crop';
        }

        $conversation = Conversation::create([
            'type' => 'direct',
            'customer_name' => $customerName,
            'customer_email' => $customerEmail,
            'customer_avatar' => $avatar,
            'is_encrypted' => $isEncrypted,
            'status' => 'open',
            'priority' => 'medium',
            'assigned_agent_id' => $currentAgent?->id,
            'last_message_at' => now(),
        ]);

        if ($currentAgent) {
            $conversation->members()->attach($currentAgent->id);
        }
        if (!empty($validated['target_user_id']) && $validated['target_user_id'] !== $currentAgent?->id) {
            $conversation->members()->attach($validated['target_user_id']);
        }

        if (!empty($validated['initial_message'])) {
            $body = $isEncrypted 
                ? '🔐 [Encrypted Message] ' . $validated['initial_message'] 
                : $validated['initial_message'];

            $msg = Message::create([
                'conversation_id' => $conversation->id,
                'sender_type' => 'agent',
                'sender_id' => $currentAgent?->id,
                'body' => $body,
                'is_internal_note' => false,
                'is_read' => true,
            ]);

            broadcast(new MessageSent($msg))->toOthers();
        }

        return redirect()->route('chat.index', ['conversation_id' => $conversation->id]);
    }

    /**
     * Create a new group chat.
     */
    public function createGroupChat(Request $request)
    {
        $validated = $request->validate([
            'group_name' => 'required|string|max:100',
            'group_description' => 'nullable|string|max:300',
            'member_ids' => 'nullable|array',
            'member_ids.*' => 'exists:users,id',
            'is_encrypted' => 'boolean',
        ]);

        $currentAgent = Auth::user();
        $isEncrypted = (bool) ($validated['is_encrypted'] ?? false);

        $conversation = Conversation::create([
            'type' => 'group',
            'group_name' => $validated['group_name'],
            'group_description' => $validated['group_description'] ?? 'Team collaboration room',
            'customer_name' => $validated['group_name'],
            'customer_email' => strtolower(str_replace(' ', '-', $validated['group_name'])) . '@fluxdesk.group',
            'customer_avatar' => 'https://images.unsplash.com/photo-1522071820081-009f0129c71c?w=150&h=150&fit=crop',
            'is_encrypted' => $isEncrypted,
            'status' => 'open',
            'priority' => 'high',
            'assigned_agent_id' => $currentAgent?->id,
            'last_message_at' => now(),
        ]);

        $memberIds = $validated['member_ids'] ?? [];
        $syncData = [];
        foreach ($memberIds as $mId) {
            $syncData[$mId] = ['is_group_admin' => false];
        }

        // Creator is default group admin
        if ($currentAgent) {
            $syncData[$currentAgent->id] = ['is_group_admin' => true];
        }

        $conversation->members()->sync($syncData);

        $welcomeBody = $isEncrypted
            ? "🔐 [Encrypted Room Created] Group '{$conversation->group_name}' was created."
            : "Group '{$conversation->group_name}' was created.";

        $msg = Message::create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'agent',
            'sender_id' => $currentAgent?->id,
            'body' => $welcomeBody,
            'is_internal_note' => false,
            'is_read' => true,
        ]);

        broadcast(new MessageSent($msg))->toOthers();

        return redirect()->route('chat.index', ['conversation_id' => $conversation->id]);
    }

    /**
     * Admin: Add new contact person / employee / member with ID into the database.
     */
    public function addMember(Request $request)
    {
        $validated = $request->validate([
            'member_id' => 'nullable|string|max:50|unique:users,member_id',
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:150|unique:users,email',
            'role' => 'nullable|string|max:100',
            'password' => 'nullable|string|min:6',
            'is_admin' => 'boolean',
            'conversation_id' => 'nullable|exists:conversations,id',
        ]);

        $avatarPool = [
            'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=120&h=120&fit=crop',
            'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=120&h=120&fit=crop',
            'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=120&h=120&fit=crop',
            'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=120&h=120&fit=crop',
            'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=120&h=120&fit=crop',
            'https://images.unsplash.com/photo-1519085360753-af0119f7cbe7?w=120&h=120&fit=crop',
        ];

        $memberId = !empty($validated['member_id']) 
            ? $validated['member_id'] 
            : 'EMP-' . str_pad((string)(User::count() + 101), 3, '0', STR_PAD_LEFT);

        $newUser = User::create([
            'member_id' => $memberId,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password'] ?? 'password123'),
            'avatar' => $avatarPool[array_rand($avatarPool)],
            'role' => $validated['role'] ?? 'Member',
            'is_admin' => (bool) ($validated['is_admin'] ?? false),
        ]);

        if (!empty($validated['conversation_id'])) {
            $conv = Conversation::find($validated['conversation_id']);
            if ($conv) {
                $conv->members()->syncWithoutDetaching([$newUser->id => ['is_group_admin' => false]]);
                
                $msg = Message::create([
                    'conversation_id' => $conv->id,
                    'sender_type' => 'agent',
                    'sender_id' => $newUser->id,
                    'body' => "👋 {$newUser->name} ({$newUser->member_id}) was added to the chat.",
                    'is_internal_note' => false,
                    'is_read' => true,
                ]);
                broadcast(new MessageSent($msg))->toOthers();
            }
        }

        return redirect()->back();
    }

    /**
     * Delete Member from workspace (Admin only).
     */
    public function deleteMember(User $user)
    {
        $current = Auth::user();
        if ($current && $current->is_admin && $current->id !== $user->id) {
            $user->delete();
        }
        return redirect()->back();
    }

    /**
     * Delete Entire Conversation (Admin or Chat Member).
     */
    public function deleteConversation(Conversation $conversation)
    {
        $conversation->delete();
        return redirect()->route('chat.index');
    }

    /**
     * Delete a single message.
     */
    public function deleteMessage(Message $message)
    {
        $message->delete();
        return redirect()->back();
    }

    /**
     * Add member into a conversation/group.
     */
    public function joinConversationMember(Request $request, Conversation $conversation)
    {
        $validated = $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $conversation->members()->syncWithoutDetaching([$validated['user_id'] => ['is_group_admin' => false]]);
        $user = User::find($validated['user_id']);

        $msg = Message::create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'agent',
            'sender_id' => $user->id,
            'body' => "👋 {$user->name} was added to this group.",
            'is_internal_note' => false,
            'is_read' => true,
        ]);

        broadcast(new MessageSent($msg))->toOthers();
        broadcast(new ConversationUpdated($conversation))->toOthers();

        return redirect()->back();
    }

    /**
     * Remove member from group.
     */
    public function removeGroupMember(Conversation $conversation, User $user)
    {
        $conversation->members()->detach($user->id);

        $msg = Message::create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'agent',
            'sender_id' => Auth::id(),
            'body' => "Member {$user->name} was removed from the group.",
            'is_internal_note' => false,
            'is_read' => true,
        ]);

        broadcast(new MessageSent($msg))->toOthers();
        broadcast(new ConversationUpdated($conversation))->toOthers();

        return redirect()->back();
    }

    /**
     * Make a member Group Admin.
     */
    public function makeGroupAdmin(Conversation $conversation, User $user)
    {
        $conversation->members()->updateExistingPivot($user->id, ['is_group_admin' => true]);

        $msg = Message::create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'agent',
            'sender_id' => Auth::id(),
            'body' => "👑 {$user->name} is now a Group Admin.",
            'is_internal_note' => false,
            'is_read' => true,
        ]);

        broadcast(new MessageSent($msg))->toOthers();
        broadcast(new ConversationUpdated($conversation))->toOthers();

        return redirect()->back();
    }

    /**
     * Send message supporting all file types (image, video, audio, document).
     */
    public function sendMessage(Request $request, Conversation $conversation)
    {
        $validated = $request->validate([
            'body' => 'nullable|string|max:5000',
            'is_internal_note' => 'boolean',
            'sender_type' => 'in:agent,customer',
            'files.*' => 'nullable|file|max:51200', // up to 50MB per file
        ]);

        $currentAgent = Auth::user();
        $senderType = $validated['sender_type'] ?? 'agent';
        $senderId = ($senderType === 'agent') ? ($currentAgent?->id ?? 1) : null;
        $isInternalNote = (bool) ($validated['is_internal_note'] ?? false);

        if ($isInternalNote) {
            $senderType = 'agent';
            $senderId = $currentAgent?->id ?? 1;
        }

        $body = $validated['body'] ?? '';

        // Process file attachments (Image, Video, Audio, Document, Zip, etc.)
        $storedAttachments = [];
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $uploadedFile) {
                $path = $uploadedFile->store('attachments', 'public');
                $mime = $uploadedFile->getMimeType() ?? '';
                $storedAttachments[] = [
                    'name' => $uploadedFile->getClientOriginalName(),
                    'url' => Storage::url($path),
                    'size' => round($uploadedFile->getSize() / 1024, 1) . ' KB',
                    'type' => $mime,
                    'is_image' => str_starts_with($mime, 'image/'),
                    'is_video' => str_starts_with($mime, 'video/'),
                    'is_audio' => str_starts_with($mime, 'audio/'),
                ];
            }
        }

        if (empty($body) && empty($storedAttachments)) {
            return redirect()->back();
        }

        if ($conversation->is_encrypted && !$isInternalNote && !empty($body)) {
            if (!str_starts_with($body, '🔐')) {
                $body = '🔐 ' . $body;
            }
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_type' => $senderType,
            'sender_id' => $senderId,
            'body' => $body ?: 'Attached file(s)',
            'is_internal_note' => $isInternalNote,
            'is_read' => ($senderType === 'customer') ? false : true,
            'attachments' => count($storedAttachments) > 0 ? $storedAttachments : null,
        ]);

        $conversation->last_message_at = now();
        if ($senderType === 'customer' && $conversation->status === 'resolved') {
            $conversation->status = 'open';
        }
        $conversation->save();

        broadcast(new MessageSent($message))->toOthers();
        ProcessOfflineNotification::dispatch($message);
        AuditLogMessage::dispatch($message);

        return redirect()->back();
    }

    /**
     * Update conversation metadata (Status, Priority, Assigned Agent, Encryption).
     */
    public function updateConversation(Request $request, Conversation $conversation)
    {
        $validated = $request->validate([
            'status' => 'nullable|in:open,pending,resolved',
            'priority' => 'nullable|in:low,medium,high',
            'assigned_agent_id' => 'nullable|exists:users,id',
            'is_encrypted' => 'nullable|boolean',
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
     * Helper to simulate customer response.
     */
    public function simulateCustomer(Request $request, Conversation $conversation)
    {
        $validated = $request->validate([
            'body' => 'required|string|max:2000',
        ]);

        $body = $validated['body'];
        if ($conversation->is_encrypted) {
            $body = '🔐 ' . $body;
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'customer',
            'sender_id' => null,
            'body' => $body,
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
