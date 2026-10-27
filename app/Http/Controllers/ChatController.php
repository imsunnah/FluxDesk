<?php

namespace App\Http\Controllers;

use App\Events\ConversationUpdated;
use App\Events\MessageSent;
use App\Jobs\AuditLogMessage;
use App\Jobs\ProcessOfflineNotification;
use App\Models\CannedResponse;
use App\Models\Client;
use App\Models\ClientAssignment;
use App\Models\Conversation;
use App\Models\Department;
use App\Models\Designation;
use App\Models\Message;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ChatController extends Controller
{
    /**
     * Safely broadcast WebSocket events without crashing the request if Reverb / Pusher is offline.
     */
    protected function safeBroadcast($event): void
    {
        try {
            $pending = broadcast($event);
            unset($pending);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('WebSocket broadcast skipped (Reverb offline): ' . $e->getMessage());
        }
    }

    /**
     * Monolithic Chat Workspace handler serving Inertia props.
     */
    public function index(Request $request): Response|\Illuminate\Http\RedirectResponse
    {
        $currentAgent = Auth::user();
        if (!$currentAgent) {
            return redirect()->route('login');
        }

        $filter = $request->query('filter', 'all'); // 'all', 'direct', 'groups', 'encrypted', 'unread'
        $search = $request->query('search', '');
        $selectedId = $request->query('conversation_id');
        $view = $request->query('view', 'tickets'); // 'tickets' | 'chat' | 'admin' | 'work_status'
        if ($view === 'chats') {
            $view = 'chat';
        }

        $isCustomer = $currentAgent->is_customer;
        $isAdmin = (bool) $currentAgent->is_admin;

        $query = Conversation::query()
            ->with(['assignedAgent', 'latestMessage', 'members', 'project'])
            ->withCount(['messages as unread_count' => function ($q) {
                $q->where('is_read', false)->where('sender_type', 'customer');
            }]);

        // 1. Role-based scoping:
        if ($isCustomer) {
            // Customer can ONLY see conversations they belong to or created
            $query->where(function ($q) use ($currentAgent) {
                $q->where('customer_email', $currentAgent->email)
                  ->orWhereHas('members', fn($m) => $m->where('users.id', $currentAgent->id));
            });
            // Customers never see internal team groups
            $query->where('type', '!=', 'group');
        } elseif (!$isAdmin) {
            // Normal Staff / Agent:
            // - For tickets: sees tickets assigned to them OR unassigned pool tickets
            // - Once a ticket is collected by another agent, other agents cannot see it!
            // - For direct/group chats: sees conversations where they are a member
            $query->where(function ($q) use ($currentAgent) {
                $q->where(function ($ticketQ) use ($currentAgent) {
                    $ticketQ->where('type', 'ticket')
                            ->where(function ($t) use ($currentAgent) {
                                $t->where('assigned_agent_id', $currentAgent->id)
                                  ->orWhereNull('assigned_agent_id');
                            });
                })->orWhere(function ($otherQ) use ($currentAgent) {
                    $otherQ->where('type', '!=', 'ticket')
                           ->whereHas('members', fn($m) => $m->where('users.id', $currentAgent->id));
                });
            });
        }
        // Admins can see all tickets and conversations

        // Filter tabs logic
        if ($filter === 'direct') {
            $query->where('type', 'direct')->where('is_encrypted', false);
        } elseif ($filter === 'groups') {
            if (!$isCustomer) {
                $query->where('type', 'group');
            }
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
                  ->orWhere('ticket_number', 'like', "%{$search}%")
                  ->orWhere('ticket_subject', 'like', "%{$search}%")
                  ->orWhereHas('messages', function ($sub) use ($search) {
                      $sub->where('body', 'like', "%{$search}%");
                  });
            });
        }

        $conversations = $query->orderByDesc('last_message_at')->get();

        // Determine active conversation based on view (tickets vs chats)
        $activeConversation = null;
        if ($selectedId) {
            $candidate = Conversation::with(['assignedAgent', 'members', 'project'])->find($selectedId);
            if ($candidate) {
                $isTicketCandidate = ($candidate->type === 'ticket' || !empty($candidate->ticket_number));
                
                // Strictly enforce view separation: tickets view only opens tickets, chats view only opens chats
                if ($view === 'tickets' && !$isTicketCandidate) {
                    $candidate = null;
                } elseif (($view === 'chats' || $view === 'chat') && $isTicketCandidate) {
                    $candidate = null;
                }

                if ($candidate) {
                    // Verify user has permission to view this conversation
                    if ($isCustomer) {
                        $isMember = $candidate->customer_email === $currentAgent->email || $candidate->members->contains('id', $currentAgent->id);
                        if ($isMember) {
                            $activeConversation = $candidate;
                        }
                    } elseif (!$isAdmin && $candidate->type === 'ticket') {
                        // Staff can only see if assigned to them or unassigned
                        if (is_null($candidate->assigned_agent_id) || $candidate->assigned_agent_id === $currentAgent->id) {
                            $activeConversation = $candidate;
                        }
                    } else {
                        $activeConversation = $candidate;
                    }
                }
            }
        }

        if (!$activeConversation && $conversations->isNotEmpty()) {
            if ($view === 'tickets') {
                $activeConversation = $conversations->first(fn($c) => $c->type === 'ticket' || !empty($c->ticket_number));
            } elseif ($view === 'chats' || $view === 'chat') {
                $activeConversation = $conversations->first(fn($c) => $c->type !== 'ticket' && empty($c->ticket_number));
            }
        }

        // Load messages for active conversation
        $activeMessages = [];
        if ($activeConversation) {
            Message::where('conversation_id', $activeConversation->id)
                ->where('sender_type', 'customer')
                ->where('is_read', false)
                ->update(['is_read' => true]);

            $messagesQuery = Message::with(['sender'])
                ->where('conversation_id', $activeConversation->id)
                ->orderBy('created_at', 'asc');

            // Customers cannot see internal notes!
            if ($isCustomer) {
                $messagesQuery->where('is_internal_note', false);
            }

            $activeMessages = $messagesQuery->get();
        }

        $cannedResponses = CannedResponse::orderBy('shortcut', 'asc')->get();

        // Directory of agents / contacts:
        // For customer: show ONLY assigned staff user(s) (never show other customers!)
        if ($isCustomer) {
            $assignedStaffIds = [];
            if ($currentAgent->assigned_agent_id) {
                $assignedStaffIds[] = $currentAgent->assigned_agent_id;
            }
            // Also include agents assigned to any customer's tickets
            $ticketAgentIds = Conversation::where('customer_email', $currentAgent->email)
                ->whereNotNull('assigned_agent_id')
                ->pluck('assigned_agent_id')
                ->toArray();
            $assignedStaffIds = array_unique(array_merge($assignedStaffIds, $ticketAgentIds));

            if (empty($assignedStaffIds)) {
                // If not assigned yet, show Admin
                $adminUser = User::where('is_admin', true)->first();
                if ($adminUser) {
                    $assignedStaffIds[] = $adminUser->id;
                }
            }

            $allMembers = User::whereIn('id', $assignedStaffIds)
                ->select('id', 'member_id', 'name', 'email', 'avatar', 'role', 'department', 'designation', 'is_admin', 'is_customer', 'is_team_lead', 'project_id', 'assigned_agent_id')
                ->with(['project:id,name', 'projects:id,name,client_id,status'])
                ->get();
        } else {
            // Staff / Admin can see staff and customers
            $allMembers = User::select('id', 'member_id', 'name', 'email', 'avatar', 'role', 'department', 'designation', 'is_admin', 'is_customer', 'is_team_lead', 'project_id', 'assigned_agent_id')
                ->with(['assignedAgent:id,name,member_id', 'project:id,name', 'projects:id,name,client_id,status', 'assignedTasks:id,title,status'])
                ->orderBy('is_admin', 'desc')
                ->orderBy('role', 'asc')
                ->get();
        }

        // All staff agents for ticket assignment dropdown
        $staffAgents = User::where(function ($q) {
            $q->where('is_admin', true)
              ->orWhereIn('role', ['admin', 'agent', 'staff', 'both', 'System Administrator', 'Senior Technical Agent', 'Customer Success Lead']);
        })->select('id', 'member_id', 'name', 'email', 'avatar', 'role', 'department', 'designation', 'is_customer', 'is_team_lead')->get();

        // Separated Admin Setup Tables Data
        $departments = Department::with('hod:id,name,email,avatar')
            ->withCount('designations')
            ->orderBy('name', 'asc')
            ->get();

        $designations = Designation::with('department:id,name,code')
            ->orderBy('title', 'asc')
            ->get();

        $clients = Client::with(['user:id,name,email,avatar', 'projects', 'assignment'])
            ->withCount('projects')
            ->orderBy('id', 'desc')
            ->get();

        $clientAssignments = ClientAssignment::with([
            'client:id,org_name,poc_name,poc_email,status',
            'primaryManager:id,name,email,avatar,role,department',
            'backupEscalation:id,name,email,avatar,role,department'
        ])->orderBy('id', 'desc')->get();

        $projects = Project::with([
            'client:id,name,email,member_id',
            'clientRecord:id,org_name,poc_name',
            'teamLead:id,name,email,avatar,role'
        ])
            ->withCount(['tasks', 'tickets', 'clients'])
            ->orderBy('id', 'desc')
            ->get();

        $tasksQuery = \App\Models\Task::with([
            'project:id,name',
            'client:id,name,email',
            'assignees:id,name,avatar,member_id',
            'creator:id,name',
            'delayLockedByUser:id,name,member_id'
        ]);
        if ($isCustomer) {
            $tasks = [];
            if ($view === 'work_status' || empty($view)) {
                $view = 'tickets';
            }
        } else {
            $tasks = $tasksQuery->orderBy('created_at', 'desc')->get();
        }

        return Inertia::render('Chat/Index', [
            'conversations' => $conversations,
            'activeConversation' => $activeConversation,
            'messages' => $activeMessages,
            'cannedResponses' => $cannedResponses,
            'agents' => $allMembers,
            'staffAgents' => $staffAgents,
            'departments' => $departments,
            'designations' => $designations,
            'clients' => $clients,
            'clientAssignments' => $clientAssignments,
            'projects' => $projects,
            'currentAgent' => $currentAgent,
            'tasks' => $tasks,
            'initialView' => $view,
            'filters' => [
                'filter' => $filter,
                'search' => $search,
                'view' => $view,
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
     * Prevents duplicate conversations for the same person!
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

        $targetUser = !empty($validated['target_user_id']) ? User::find($validated['target_user_id']) : null;
        if (!$targetUser && !empty($validated['email'])) {
            $targetUser = User::where('email', $validated['email'])->first();
        }

        // Customer can only message staff, never another customer
        if ($currentAgent->is_customer) {
            if ($targetUser && $targetUser->is_customer) {
                return redirect()->back()->withErrors(['error' => 'Customers can only message assigned staff support.']);
            }
        }

        if ($targetUser) {
            $customerName = $targetUser->name;
            $customerEmail = $targetUser->email;
            $avatar = $targetUser->avatar;
        } else {
            $customerName = $validated['name'];
            $customerEmail = $validated['email'];
            $avatar = 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=120&h=120&fit=crop';
        }

        // PREVENT DUPLICATES: Check if a direct conversation already exists between these 2 users
        $existing = null;
        if ($targetUser) {
            $existing = Conversation::where('type', 'direct')
                ->whereHas('members', fn($q) => $q->where('users.id', $currentAgent->id))
                ->whereHas('members', fn($q) => $q->where('users.id', $targetUser->id))
                ->first();
        } else {
            $existing = Conversation::where('type', 'direct')
                ->where('customer_email', $customerEmail)
                ->whereHas('members', fn($q) => $q->where('users.id', $currentAgent->id))
                ->first();
        }

        if ($existing) {
            if (!empty($validated['initial_message'])) {
                $body = $isEncrypted 
                    ? '🔐 [Encrypted Message] ' . $validated['initial_message'] 
                    : $validated['initial_message'];

                $msg = Message::create([
                    'conversation_id' => $existing->id,
                    'sender_type' => $currentAgent->is_customer ? 'customer' : 'agent',
                    'sender_id' => $currentAgent->id,
                    'body' => $body,
                    'is_internal_note' => false,
                    'is_read' => true,
                ]);

                $existing->update(['last_message_at' => now()]);
                $this->safeBroadcast(new MessageSent($msg));
                $this->safeBroadcast(new ConversationUpdated($existing));
            }

            return redirect()->route('chat.index', ['conversation_id' => $existing->id, 'view' => 'chat']);
        }

        // Create new direct conversation if none exists
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
                'sender_type' => $currentAgent->is_customer ? 'customer' : 'agent',
                'sender_id' => $currentAgent?->id,
                'body' => $body,
                'is_internal_note' => false,
                'is_read' => true,
            ]);

            $this->safeBroadcast(new MessageSent($msg));
        }

        return redirect()->route('chat.index', ['conversation_id' => $conversation->id, 'view' => 'chat']);
    }

    /**
     * Create a new group chat (Staff only).
     */
    public function createGroupChat(Request $request)
    {
        $currentAgent = Auth::user();
        if ($currentAgent->is_customer) {
            abort(403, 'Customers cannot create groups.');
        }

        $validated = $request->validate([
            'group_name' => 'required|string|max:100',
            'group_description' => 'nullable|string|max:300',
            'member_ids' => 'nullable|array',
            'member_ids.*' => 'exists:users,id',
            'is_encrypted' => 'boolean',
        ]);

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

        $this->safeBroadcast(new MessageSent($msg));

        return redirect()->route('chat.index', ['conversation_id' => $conversation->id, 'view' => 'chats']);
    }

    /**
     * Admin: Add new contact person / employee / customer into the database.
     */
    public function addMember(Request $request)
    {
        $validated = $request->validate([
            'member_id' => 'nullable|string|max:50|unique:users,member_id',
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:150|unique:users,email',
            'role' => 'nullable|string|max:100',
            'department' => 'nullable|string|max:100',
            'designation' => 'nullable|string|max:100',
            'is_customer' => 'nullable|boolean',
            'is_team_lead' => 'nullable|boolean',
            'project_id' => 'nullable|exists:projects,id',
            'assigned_agent_id' => 'nullable|exists:users,id',
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

        $role = $validated['role'] ?? 'agent';
        $isCustomer = (bool) ($validated['is_customer'] ?? ($role === 'customer'));
        $prefix = $isCustomer ? 'CUST-' : 'EMP-';
        $memberId = !empty($validated['member_id']) 
            ? $validated['member_id'] 
            : $prefix . str_pad((string)(User::count() + 101), 3, '0', STR_PAD_LEFT);

        $department = $validated['department'] ?? ($isCustomer ? 'Client Accounts' : 'Engineering');
        $designation = $validated['designation'] ?? ($isCustomer ? 'Client Representative' : 'Technical Support Engineer');

        $newUser = User::create([
            'member_id' => $memberId,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password'] ?? 'password123'),
            'avatar' => $avatarPool[array_rand($avatarPool)],
            'role' => $role,
            'department' => $department,
            'designation' => $designation,
            'is_admin' => (bool) ($validated['is_admin'] ?? false),
            'is_customer' => $isCustomer,
            'is_team_lead' => (bool) ($validated['is_team_lead'] ?? false),
            'project_id' => $validated['project_id'] ?? null,
            'assigned_agent_id' => $validated['assigned_agent_id'] ?? null,
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
                $this->safeBroadcast(new MessageSent($msg));
            }
        }

        return redirect()->back();
    }

    /**
     * Admin: Assign / update assigned staff user for a customer.
     */
    public function assignCustomerAgent(Request $request, User $user)
    {
        $current = Auth::user();
        if (!$current || !$current->is_admin) {
            abort(403, 'Only admins can assign staff to customers.');
        }

        $validated = $request->validate([
            'assigned_agent_id' => 'nullable|exists:users,id',
        ]);

        $user->update(['assigned_agent_id' => $validated['assigned_agent_id']]);

        return redirect()->back();
    }

    /**
     * Admin: Toggle customer ticket-creation access on any user.
     */
    public function toggleCustomerAccess(User $user)
    {
        $current = Auth::user();
        if (!$current || !$current->is_admin) {
            abort(403, 'Only admins can toggle customer access.');
        }

        $user->is_customer = !$user->is_customer;
        $user->save();

        return redirect()->back();
    }

    /**
     * Admin: Update member / employee / customer in the database.
     */
    public function updateMember(Request $request, User $user)
    {
        $current = Auth::user();
        if (!$current || !$current->is_admin) {
            abort(403, 'Only admins can update members.');
        }

        $validated = $request->validate([
            'member_id' => 'nullable|string|max:50|unique:users,member_id,' . $user->id,
            'name' => 'sometimes|required|string|max:100',
            'email' => 'sometimes|required|email|max:150|unique:users,email,' . $user->id,
            'role' => 'nullable|string|max:100',
            'department' => 'nullable|string|max:100',
            'designation' => 'nullable|string|max:100',
            'is_customer' => 'nullable|boolean',
            'is_team_lead' => 'nullable|boolean',
            'is_admin' => 'nullable|boolean',
            'project_id' => 'nullable|exists:projects,id',
            'assigned_agent_id' => 'nullable|exists:users,id',
        ]);

        $user->update($validated);

        return redirect()->back()->with('success', 'Member updated successfully.');
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

        $this->safeBroadcast(new MessageSent($msg));
        $this->safeBroadcast(new ConversationUpdated($conversation));

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

        $this->safeBroadcast(new MessageSent($msg));
        $this->safeBroadcast(new ConversationUpdated($conversation));

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

        $this->safeBroadcast(new MessageSent($msg));
        $this->safeBroadcast(new ConversationUpdated($conversation));

        return redirect()->back();
    }

    /**
     * Customer or Staff creates a support ticket.
     */
    public function createTicket(Request $request)
    {
        $currentUser = Auth::user();

        // ONLY CUSTOMER CAN CREATE TICKET. Staff/user without customer role CANNOT.
        // If login person is user and customer both, he can. Else not!
        if (!$currentUser || !$currentUser->is_customer) {
            abort(403, 'Only customers (or accounts with customer access) can create tickets.');
        }

        $validated = $request->validate([
            'subject' => 'required|string|max:150',
            'customer_name' => 'nullable|string|max:100',
            'customer_email' => 'nullable|email|max:150',
            'description' => 'required|string|max:5000',
            'project_id' => 'nullable|exists:projects,id',
            'priority' => 'nullable|in:low,medium,high',
            'assigned_agent_id' => 'nullable|exists:users,id',
        ]);

        $ticketNumber = 'TK-' . rand(1000, 9999);

        $customerName = $currentUser->name;
        $customerEmail = $currentUser->email;
        $customerAvatar = $currentUser->avatar;
        // Customer's ticket automatically assigns their designated agent if configured
        $assignedAgentId = $validated['assigned_agent_id'] ?? $currentUser->assigned_agent_id;

        $conversation = Conversation::create([
            'type' => 'ticket',
            'ticket_number' => $ticketNumber,
            'ticket_subject' => $validated['subject'],
            'ticket_description' => $validated['description'],
            'project_id' => $validated['project_id'] ?? $currentUser->project_id,
            'customer_name' => $customerName,
            'customer_email' => $customerEmail,
            'customer_avatar' => $customerAvatar,
            'status' => 'open',
            'priority' => $validated['priority'] ?? 'medium',
            'assigned_agent_id' => $assignedAgentId,
            'last_message_at' => now(),
        ]);

        if (!empty($conversation->assigned_agent_id)) {
            $conversation->members()->attach($conversation->assigned_agent_id, ['is_group_admin' => true]);
        }
        if ($currentUser) {
            $conversation->members()->syncWithoutDetaching([$currentUser->id => ['is_group_admin' => false]]);
        }

        // Initial ticket message
        $msg = Message::create([
            'conversation_id' => $conversation->id,
            'sender_type' => ($currentUser && !$currentUser->is_customer) ? 'agent' : 'customer',
            'sender_id' => $currentUser?->id,
            'body' => $validated['description'],
            'is_internal_note' => false,
            'is_read' => true,
        ]);

        $this->safeBroadcast(new MessageSent($msg));
        $this->safeBroadcast(new ConversationUpdated($conversation));

        return redirect()->route('chat.index', ['conversation_id' => $conversation->id, 'view' => 'tickets']);
    }

    /**
     * Staff "Collects / Claims" an unassigned ticket.
     * Once collected, other agents will no longer see this ticket in their queue!
     */
    public function claimTicket(Conversation $conversation)
    {
        $currentUser = Auth::user();
        if (!$currentUser || $currentUser->is_customer) {
            abort(403, 'Only staff members can collect tickets.');
        }

        $conversation->update([
            'assigned_agent_id' => $currentUser->id,
            'status' => 'in_progress',
            'last_message_at' => now(),
        ]);

        $conversation->members()->syncWithoutDetaching([$currentUser->id => ['is_group_admin' => true]]);

        $msg = Message::create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'agent',
            'sender_id' => $currentUser->id,
            'body' => "🙋‍♂️ Ticket collected by {$currentUser->name}.",
            'is_internal_note' => false,
            'is_read' => true,
        ]);

        $this->safeBroadcast(new MessageSent($msg));
        $this->safeBroadcast(new ConversationUpdated($conversation));

        return redirect()->back();
    }

    /**
     * Assigned Agent or Admin re-assigns / transfers ticket to another agent or back to pool.
     */
    public function reassignTicket(Request $request, Conversation $conversation)
    {
        $currentUser = Auth::user();
        if (!$currentUser || $currentUser->is_customer) {
            abort(403, 'Unauthorized.');
        }

        $validated = $request->validate([
            'target_agent_id' => 'nullable|exists:users,id',
            'note' => 'nullable|string|max:500',
        ]);

        $newAgent = !empty($validated['target_agent_id']) ? User::find($validated['target_agent_id']) : null;
        
        $conversation->assigned_agent_id = $newAgent?->id;
        $conversation->status = $newAgent ? 'in_progress' : 'open';
        $conversation->last_message_at = now();
        $conversation->save();

        if ($newAgent) {
            $conversation->members()->syncWithoutDetaching([$newAgent->id => ['is_group_admin' => true]]);
        }

        $body = $newAgent 
            ? "🔄 Ticket reassigned to {$newAgent->name} by {$currentUser->name}."
            : "🔄 Ticket returned to unassigned pool by {$currentUser->name}.";

        if (!empty($validated['note'])) {
            $body .= " Reason/Note: " . $validated['note'];
        }

        $msg = Message::create([
            'conversation_id' => $conversation->id,
            'sender_type' => 'agent',
            'sender_id' => $currentUser->id,
            'body' => $body,
            'is_internal_note' => false,
            'is_read' => true,
        ]);

        $this->safeBroadcast(new MessageSent($msg));
        $this->safeBroadcast(new ConversationUpdated($conversation));

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

        $currentUser = Auth::user();
        $isCustomer = $currentUser?->is_customer;

        // Customers cannot send internal notes
        $isInternalNote = (bool) ($validated['is_internal_note'] ?? false);
        if ($isCustomer) {
            $isInternalNote = false;
        }

        $senderType = $isCustomer ? 'customer' : 'agent';
        $senderId = $currentUser?->id;

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

        // Broadcast to all listeners in the exact same second
        $this->safeBroadcast(new MessageSent($message));
        $this->safeBroadcast(new ConversationUpdated($conversation));

        ProcessOfflineNotification::dispatch($message);
        AuditLogMessage::dispatch($message);

        return redirect()->back();
    }

    /**
     * Update conversation metadata (Status, Priority, Assigned Agent, Encryption, Notes).
     */
    public function updateConversation(Request $request, Conversation $conversation)
    {
        $validated = $request->validate([
            'status' => 'nullable|in:open,in_progress,pending,resolved,closed',
            'priority' => 'nullable|in:low,medium,high',
            'assigned_agent_id' => 'nullable|exists:users,id',
            'is_encrypted' => 'nullable|boolean',
            'update_note' => 'nullable|string|max:1000',
        ]);

        $conversation->fill(array_filter($validated, fn ($val, $key) => !is_null($val) && $key !== 'update_note', ARRAY_FILTER_USE_BOTH));
        $conversation->save();

        if (!empty($validated['update_note'])) {
            $current = Auth::user();
            $msg = Message::create([
                'conversation_id' => $conversation->id,
                'sender_type' => 'agent',
                'sender_id' => $current?->id,
                'body' => '📝 Update: ' . $validated['update_note'],
                'is_internal_note' => false,
                'is_read' => true,
            ]);
            $this->safeBroadcast(new MessageSent($msg));
        }

        $this->safeBroadcast(new ConversationUpdated($conversation));

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

        $this->safeBroadcast(new MessageSent($message));
        $this->safeBroadcast(new ConversationUpdated($conversation));
        AuditLogMessage::dispatch($message);

        return redirect()->back();
    }

    /**
     * Admin: Create a new project.
     */
    public function storeProject(Request $request)
    {
        $current = Auth::user();
        if (!$current || !$current->is_admin) {
            abort(403, 'Only admins can create projects.');
        }

        $validated = $request->validate([
            'code' => 'nullable|string|max:50',
            'name' => 'required|string|max:150',
            'client_id' => 'nullable',
            'team_lead_id' => 'nullable|exists:users,id',
            'team_member_ids' => 'nullable|array',
            'start_date' => 'nullable|date',
            'target_date' => 'nullable|date',
            'description' => 'nullable|string',
            'status' => 'nullable|string|max:50',
        ]);

        $clientId = $validated['client_id'] ?? null;
        if ($clientId) {
            $clientModel = Client::find($clientId);
            if ($clientModel && $clientModel->user_id) {
                $clientId = $clientModel->user_id;
            }
        }

        $code = $validated['code'] ?? ('PRJ-' . mt_rand(100, 999));

        Project::create([
            'code' => $code,
            'name' => $validated['name'],
            'client_id' => $clientId,
            'team_lead_id' => $validated['team_lead_id'] ?? null,
            'team_member_ids' => $validated['team_member_ids'] ?? [],
            'start_date' => $validated['start_date'] ?? null,
            'target_date' => $validated['target_date'] ?? null,
            'description' => $validated['description'] ?? null,
            'status' => $validated['status'] ?? 'Active',
        ]);

        return redirect()->back()->with('success', 'Project created successfully.');
    }

    /**
     * Admin: Update an existing project.
     */
    public function updateProject(Request $request, Project $project)
    {
        $current = Auth::user();
        if (!$current || !$current->is_admin) {
            abort(403, 'Only admins can update projects.');
        }

        $validated = $request->validate([
            'code' => 'nullable|string|max:50',
            'name' => 'sometimes|required|string|max:150',
            'client_id' => 'nullable',
            'team_lead_id' => 'nullable|exists:users,id',
            'team_member_ids' => 'nullable|array',
            'start_date' => 'nullable|date',
            'target_date' => 'nullable|date',
            'description' => 'nullable|string',
            'status' => 'sometimes|nullable|string|max:50',
        ]);

        if (isset($validated['client_id']) && $validated['client_id']) {
            $clientModel = Client::find($validated['client_id']);
            if ($clientModel && $clientModel->user_id) {
                $validated['client_id'] = $clientModel->user_id;
            }
        }

        $project->update($validated);

        return redirect()->back()->with('success', 'Project updated successfully.');
    }

    /**
     * Admin: Delete project.
     */
    public function deleteProject(Project $project)
    {
        $current = Auth::user();
        if (!$current || !$current->is_admin) {
            abort(403, 'Only admins can delete projects.');
        }

        $project->delete();

        return redirect()->back()->with('success', 'Project deleted.');
    }

    /**
     * Admin: Toggle Team Lead permission on a staff employee.
     */
    public function toggleTeamLead(User $user)
    {
        $current = Auth::user();
        if (!$current || !$current->is_admin) {
            abort(403, 'Only admins can toggle team lead status.');
        }

        $user->is_team_lead = !$user->is_team_lead;
        $user->save();

        return redirect()->back()->with('success', 'Team lead status updated.');
    }

    /**
     * Admin: Assign / update customer project.
     */
    public function updateUserProject(Request $request, User $user)
    {
        $current = Auth::user();
        if (!$current || !$current->is_admin) {
            abort(403, 'Only admins can update user project.');
        }

        $validated = $request->validate([
            'project_id' => 'nullable|exists:projects,id',
        ]);

        $user->project_id = $validated['project_id'] ?? null;
        $user->save();

        return redirect()->back()->with('success', 'Project assigned to client.');
    }
}
