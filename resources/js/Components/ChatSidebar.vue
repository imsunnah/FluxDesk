<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { 
    Search, 
    MessageSquare, 
    Ticket,
    Users, 
    Plus, 
    Trash2, 
    LogOut,
    CheckCircle2,
    Clock,
    AlertCircle,
    UserCheck,
    Handshake,
    Shield
} from 'lucide-vue-next';

const props = defineProps({
    conversations: {
        type: Array,
        required: true,
    },
    activeConversationId: {
        type: [Number, String, null],
        default: null,
    },
    activeFilter: {
        type: String,
        default: 'all',
    },
    searchQuery: {
        type: String,
        default: '',
    },
    currentAgent: {
        type: Object,
        default: () => ({}),
    },
    agents: {
        type: Array,
        default: () => [],
    },
    staffAgents: {
        type: Array,
        default: () => [],
    },
    activeView: {
        type: String,
        default: 'tickets',
    },
    initialView: {
        type: String,
        default: 'tickets',
    },
});

const emit = defineEmits([
    'select-conversation', 
    'update:searchQuery', 
    'set-filter', 
    'open-new-chat-modal',
    'start-direct-chat-with-user',
    'change-view',
    'update:activeView',
]);

const isCustomer = computed(() => !!props.currentAgent?.is_customer);
const isStaff = computed(() => !!props.currentAgent?.is_staff);
const isDualRole = computed(() => isStaff.value && isCustomer.value);
const canCreateTicket = computed(() => isCustomer.value);
const isAdmin = computed(() => !!props.currentAgent?.is_admin);

function toggleCustomerAccess(user) {
    router.post(`/users/${user.id}/toggle-customer-access`, {}, {
        preserveScroll: true,
        preserveState: true,
    });
}

// Main navigation: 'tickets' | 'chats' | 'members'
const mainView = computed(() => props.activeView || props.initialView || 'tickets');

function switchView(tab) {
    emit('update:activeView', tab);
    emit('change-view', tab);
}

const filterTabs = [
    { key: 'all', label: 'All' },
    { key: 'open', label: 'Open' },
    { key: 'in_progress', label: 'In Progress' },
    { key: 'resolved', label: 'Resolved' },
];

const ticketsList = computed(() => {
    let list = props.conversations.filter(c => c.type === 'ticket' || c.ticket_number);

    if (props.activeFilter !== 'all') {
        list = list.filter(c => c.status === props.activeFilter);
    }

    if (props.searchQuery.trim()) {
        const query = props.searchQuery.toLowerCase();
        list = list.filter(c => 
            (c.ticket_number && c.ticket_number.toLowerCase().includes(query)) ||
            (c.ticket_subject && c.ticket_subject.toLowerCase().includes(query)) ||
            (c.customer_name && c.customer_name.toLowerCase().includes(query)) ||
            (c.latest_message?.body && c.latest_message.body.toLowerCase().includes(query))
        );
    }

    return list;
});

// Group tickets for staff into: Unassigned (Available to collect) & My Assigned Tickets
const unassignedTickets = computed(() => {
    return ticketsList.value.filter(t => !t.assigned_agent_id);
});

const assignedTickets = computed(() => {
    return ticketsList.value.filter(t => !!t.assigned_agent_id);
});

const regularChatsList = computed(() => {
    let list = props.conversations.filter(c => c.type !== 'ticket' && !c.ticket_number);

    if (props.searchQuery.trim()) {
        const query = props.searchQuery.toLowerCase();
        list = list.filter(c => 
            (c.customer_name && c.customer_name.toLowerCase().includes(query)) ||
            (c.group_name && c.group_name.toLowerCase().includes(query)) ||
            (c.latest_message?.body && c.latest_message.body.toLowerCase().includes(query))
        );
    }

    return list;
});

const staffDirectory = computed(() => {
    return props.agents.filter(u => u.is_admin || u.role !== 'customer');
});

const customerDirectory = computed(() => {
    return props.agents.filter(u => u.role === 'customer');
});

function startChatWith(user) {
    emit('start-direct-chat-with-user', user);
}

function deleteMember(user) {
    if (confirm(`Remove member ${user.name}?`)) {
        router.delete(`/members/${user.id}`);
    }
}

function claimTicket(ticket, event) {
    if (event) event.stopPropagation();
    router.post(`/conversations/${ticket.id}/claim`, {}, {
        preserveScroll: true,
        preserveState: true,
    });
}

function updateCustomerAgent(customer, agentId) {
    router.post(`/users/${customer.id}/assign-agent`, {
        assigned_agent_id: agentId ? Number(agentId) : null,
    }, {
        preserveScroll: true,
        preserveState: true,
    });
}

function logout() {
    router.post('/logout');
}

function formatTime(isoString) {
    if (!isoString) return '';
    const date = new Date(isoString);
    const now = new Date();
    const diffMins = Math.floor((now - date) / 60000);

    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins}m`;
    if (diffMins < 1440) return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    return date.toLocaleDateString([], { month: 'short', day: 'numeric' });
}

function statusBadge(status) {
    switch (status) {
        case 'open':
            return 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30';
        case 'in_progress':
            return 'bg-sky-500/20 text-sky-400 border border-sky-500/30';
        case 'pending':
            return 'bg-amber-500/20 text-amber-400 border border-amber-500/30';
        case 'resolved':
        case 'closed':
            return 'bg-slate-700/50 text-slate-300 border border-slate-600/30';
        default:
            return 'bg-slate-800 text-slate-400';
    }
}
</script>

<template>
    <aside class="w-80 md:w-84 lg:w-88 flex flex-col h-full bg-slate-900 border-r border-slate-800 select-none z-10 shrink-0">
        <!-- Top Simple Header Bar -->
        <div class="px-3.5 py-2.5 border-b border-slate-800 flex items-center justify-between bg-slate-900">
            <div class="flex items-center space-x-2">
                <div class="w-7 h-7 rounded-lg bg-indigo-600 flex items-center justify-center text-white font-bold text-xs shadow">
                    ⚡
                </div>
                <div>
                    <div class="flex items-center space-x-1">
                        <span class="font-bold text-slate-100 text-xs">FluxDesk</span>
                        <span v-if="isDualRole" class="px-1 text-[9px] bg-purple-500/20 text-purple-300 rounded font-bold">Staff + Customer</span>
                        <span v-else-if="isAdmin" class="px-1 text-[9px] bg-amber-500/20 text-amber-400 rounded font-bold">Admin</span>
                        <span v-else-if="isCustomer" class="px-1 text-[9px] bg-emerald-500/20 text-emerald-400 rounded font-bold">Customer</span>
                        <span v-else class="px-1 text-[9px] bg-sky-500/20 text-sky-400 rounded font-bold">Staff (User)</span>
                    </div>
                    <p class="text-[10px] text-slate-400 truncate max-w-[130px]">
                        {{ currentAgent?.name }} ({{ currentAgent?.member_id || 'ID' }})
                    </p>
                </div>
            </div>

            <!-- Header Actions -->
            <div class="flex items-center space-x-1">
                <!-- Only customer or user with customer access can create tickets -->
                <button
                    v-if="canCreateTicket"
                    @click="$emit('open-new-chat-modal', 'ticket')"
                    class="px-2 py-1 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-[11px] font-semibold flex items-center space-x-1 shadow transition-all"
                    title="Create Ticket"
                >
                    <Plus class="w-3 h-3" />
                    <span>Ticket</span>
                </button>

                <button 
                    @click="logout"
                    class="p-1 rounded-lg text-slate-400 hover:text-slate-200 hover:bg-slate-800"
                    title="Sign Out"
                >
                    <LogOut class="w-3.5 h-3.5" />
                </button>
            </div>
        </div>

        <!-- Role-Aware Navigation Tabs -->
        <div :class="['grid p-1 mx-2.5 mt-2 bg-slate-950 rounded-xl border border-slate-800 text-xs font-semibold', isCustomer ? 'grid-cols-2' : 'grid-cols-3']">
            <button
                @click="switchView('tickets')"
                :class="[
                    'py-1.5 rounded-lg flex items-center justify-center space-x-1 transition-all',
                    mainView === 'tickets' ? 'bg-indigo-600 text-white shadow' : 'text-slate-400 hover:text-slate-200'
                ]"
            >
                <Ticket class="w-3.5 h-3.5" />
                <span>{{ isCustomer ? 'My Tickets' : 'Tickets' }} ({{ ticketsList.length }})</span>
            </button>

            <button
                @click="switchView('chats')"
                :class="[
                    'py-1.5 rounded-lg flex items-center justify-center space-x-1 transition-all',
                    mainView === 'chats' ? 'bg-indigo-600 text-white shadow' : 'text-slate-400 hover:text-slate-200'
                ]"
            >
                <MessageSquare class="w-3.5 h-3.5" />
                <span>{{ isCustomer ? 'Support Chat' : 'Chats' }} ({{ regularChatsList.length }})</span>
            </button>

            <button
                v-if="!isCustomer"
                @click="switchView('members')"
                :class="[
                    'py-1.5 rounded-lg flex items-center justify-center space-x-1 transition-all',
                    mainView === 'members' ? 'bg-indigo-600 text-white shadow' : 'text-slate-400 hover:text-slate-200'
                ]"
            >
                <Users class="w-3.5 h-3.5" />
                <span>Users ({{ agents.length }})</span>
            </button>
        </div>

        <!-- Search Bar -->
        <div class="px-2.5 pt-2 pb-1.5">
            <div class="relative flex items-center">
                <Search class="w-3.5 h-3.5 text-slate-500 absolute left-3 pointer-events-none" />
                <input 
                    type="text"
                    :value="searchQuery"
                    @input="$emit('update:searchQuery', $event.target.value)"
                    :placeholder="mainView === 'tickets' ? 'Search tickets by #ID, subject...' : 'Search chats...'"
                    class="w-full pl-8 pr-7 py-1.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 placeholder-slate-500 focus:outline-none focus:border-indigo-500"
                />
                <button 
                    v-if="searchQuery"
                    @click="$emit('update:searchQuery', '')"
                    class="absolute right-2 text-xs text-slate-500 hover:text-slate-300"
                >
                    ✕
                </button>
            </div>
        </div>

        <!-- 1. TICKETS VIEW -->
        <template v-if="mainView === 'tickets'">
            <!-- Status Filter -->
            <div class="px-2.5 pb-2">
                <div class="flex items-center space-x-1 p-0.5 bg-slate-950/70 rounded-lg border border-slate-800 text-[11px] font-medium overflow-x-auto">
                    <button
                        v-for="tab in filterTabs"
                        :key="tab.key"
                        @click="$emit('set-filter', tab.key)"
                        :class="[
                            'px-2 py-0.5 rounded-md transition-all shrink-0 capitalize',
                            activeFilter === tab.key ? 'bg-indigo-600 text-white font-semibold' : 'text-slate-400 hover:text-slate-200'
                        ]"
                    >
                        {{ tab.label }}
                    </button>
                </div>
            </div>

            <!-- Tickets List -->
            <div class="flex-1 overflow-y-auto px-2 space-y-2">
                <div 
                    v-if="ticketsList.length === 0"
                    class="flex flex-col items-center justify-center h-48 text-center p-6 text-slate-500"
                >
                    <Ticket class="w-7 h-7 text-slate-600 mb-2" />
                    <p class="text-xs font-semibold text-slate-400">No tickets found</p>
                    <button 
                        v-if="canCreateTicket"
                        @click="$emit('open-new-chat-modal', 'ticket')"
                        class="mt-2 text-xs text-indigo-400 hover:text-indigo-300 font-semibold"
                    >
                        + Create a ticket
                    </button>
                    <p v-else class="text-[11px] text-slate-500 mt-1">
                        Only customers can create tickets
                    </p>
                </div>

                <!-- Unassigned Tickets Pool (For Staff / Admins) -->
                <div v-if="!isCustomer && unassignedTickets.length > 0" class="space-y-1">
                    <div class="px-1 text-[10px] font-bold uppercase tracking-wider text-amber-400 flex items-center justify-between">
                        <span>⚡ Available Pool (Click to Collect)</span>
                        <span class="bg-amber-500/20 px-1.5 py-0.2 rounded">{{ unassignedTickets.length }}</span>
                    </div>

                    <div
                        v-for="ticket in unassignedTickets"
                        :key="ticket.id"
                        @click="$emit('select-conversation', ticket.id, 'tickets')"
                        :class="[
                            'group p-2.5 rounded-xl cursor-pointer transition-all border relative',
                            activeConversationId === ticket.id
                                ? 'bg-amber-950/40 border-amber-500/60 text-slate-100 shadow-sm'
                                : 'bg-slate-950/80 hover:bg-slate-800/60 border-amber-500/30 text-slate-200'
                        ]"
                    >
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-mono text-xs font-bold text-amber-400">
                                {{ ticket.ticket_number || '#' + ticket.id }}
                            </span>
                            <div class="flex items-center space-x-1.5">
                                <button
                                    @click="claimTicket(ticket, $event)"
                                    class="px-2 py-0.5 rounded bg-emerald-600 hover:bg-emerald-500 text-white text-[10px] font-bold shadow flex items-center space-x-1 transition-all"
                                    title="Collect and claim this ticket"
                                >
                                    <Handshake class="w-2.5 h-2.5" />
                                    <span>Collect Ticket</span>
                                </button>
                                <span class="text-[10px] text-slate-400 font-mono">
                                    {{ formatTime(ticket.last_message_at || ticket.updated_at) }}
                                </span>
                            </div>
                        </div>

                        <h4 class="text-xs font-bold text-slate-100 group-hover:text-amber-300 truncate mb-1">
                            {{ ticket.ticket_subject || ticket.display_name }}
                        </h4>

                        <div class="flex items-center justify-between text-[10px] text-slate-400 pt-1 border-t border-slate-800/80">
                            <span class="truncate max-w-[140px]">From: {{ ticket.customer_name }}</span>
                            <span class="text-amber-300 font-semibold">Unassigned</span>
                        </div>
                    </div>
                </div>

                <!-- Assigned Tickets List -->
                <div v-if="assignedTickets.length > 0 || isCustomer" class="space-y-1">
                    <div v-if="!isCustomer && unassignedTickets.length > 0" class="px-1 pt-1 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                        <span>{{ isAdmin ? 'All Assigned Tickets' : 'My Assigned Tickets' }}</span>
                    </div>

                    <div
                        v-for="ticket in (isCustomer ? ticketsList : assignedTickets)"
                        :key="ticket.id"
                        @click="$emit('select-conversation', ticket.id, 'tickets')"
                        :class="[
                            'group p-2.5 rounded-xl cursor-pointer transition-all border',
                            activeConversationId === ticket.id
                                ? 'bg-indigo-950/60 border-indigo-500/50 text-slate-100 shadow-sm'
                                : 'hover:bg-slate-800/40 border-slate-800/60 text-slate-300'
                        ]"
                    >
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-mono text-xs font-bold text-indigo-400">
                                {{ ticket.ticket_number || '#' + ticket.id }}
                            </span>
                            <div class="flex items-center space-x-1">
                                <span :class="['px-1.5 py-0.2 rounded text-[9px] font-bold uppercase', statusBadge(ticket.status)]">
                                    {{ ticket.status }}
                                </span>
                                <span class="text-[10px] text-slate-500 font-mono">
                                    {{ formatTime(ticket.last_message_at || ticket.updated_at) }}
                                </span>
                            </div>
                        </div>

                        <h4 class="text-xs font-bold text-slate-100 group-hover:text-indigo-300 truncate mb-1">
                            {{ ticket.ticket_subject || ticket.display_name }}
                        </h4>

                        <div class="flex items-center justify-between text-[10px] text-slate-400 pt-1 border-t border-slate-800/60">
                            <span class="truncate max-w-[130px]">
                                {{ isCustomer ? 'Assigned: ' + (ticket.assigned_agent?.name || 'Support Pool') : 'From: ' + ticket.customer_name }}
                            </span>
                            <span v-if="!isCustomer" class="text-indigo-300 font-medium truncate max-w-[120px]">
                                {{ ticket.assigned_agent?.name || 'Unassigned' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <!-- 2. LIVE CHATS VIEW -->
        <template v-else-if="mainView === 'chats'">
            <div class="px-2.5 pb-1.5 flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">
                    {{ isCustomer ? 'Support Chats' : 'Direct & Group Chats' }}
                </span>
                <button 
                    @click="$emit('open-new-chat-modal', 'single')"
                    class="text-xs text-indigo-400 hover:text-indigo-300 font-medium"
                >
                    + New Chat
                </button>
            </div>

            <div class="flex-1 overflow-y-auto px-2 space-y-1">
                <div 
                    v-if="regularChatsList.length === 0"
                    class="flex flex-col items-center justify-center h-48 text-center p-6 text-slate-500"
                >
                    <MessageSquare class="w-7 h-7 text-slate-600 mb-2" />
                    <p class="text-xs font-semibold text-slate-400">No chats yet</p>
                    <button 
                        @click="$emit('open-new-chat-modal', 'single')"
                        class="mt-2 text-xs text-indigo-400 hover:text-indigo-300 font-medium"
                    >
                        + Start a chat
                    </button>
                </div>

                <div
                    v-for="conv in regularChatsList"
                    :key="conv.id"
                    @click="$emit('select-conversation', conv.id, 'chats')"
                    :class="[
                        'group p-2.5 rounded-xl cursor-pointer transition-all flex items-center space-x-2.5 border',
                        activeConversationId === conv.id
                            ? 'bg-indigo-950/60 border-indigo-500/50 text-slate-100'
                            : 'hover:bg-slate-800/40 border-transparent text-slate-300'
                    ]"
                >
                    <div class="relative shrink-0">
                        <img 
                            :src="conv.customer_avatar || 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=100&h=100&fit=crop'" 
                            class="w-9 h-9 rounded-full object-cover ring-1 ring-slate-800" 
                        />
                        <span class="absolute -bottom-0.5 -right-0.5 w-2 h-2 rounded-full bg-emerald-500 ring-2 ring-slate-900"></span>
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between mb-0.5">
                            <span class="text-xs font-semibold text-slate-100 truncate group-hover:text-indigo-300">
                                {{ conv.display_name }}
                            </span>
                            <span class="text-[10px] text-slate-500 font-mono shrink-0 ml-1">
                                {{ formatTime(conv.last_message_at || conv.updated_at) }}
                            </span>
                        </div>

                        <p class="text-[11px] text-slate-400 truncate">
                            {{ conv.latest_message?.body || 'No messages yet' }}
                        </p>
                    </div>
                </div>
            </div>
        </template>

        <!-- 3. MEMBERS / USERS DIRECTORY (Staff only) -->
        <template v-else>
            <div class="px-2.5 pb-1.5 flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Users Directory</span>
                <button 
                    v-if="isAdmin"
                    @click="$emit('open-new-chat-modal', 'member')"
                    class="text-xs text-indigo-400 hover:text-indigo-300 font-medium"
                >
                    + Add User
                </button>
            </div>

            <div class="flex-1 overflow-y-auto px-2 space-y-3">
                <!-- Staff Section -->
                <div class="space-y-1">
                    <div class="px-1 text-[10px] font-bold uppercase tracking-wider text-indigo-400">
                        Staff & Support Agents ({{ staffDirectory.length }})
                    </div>
                    <div
                        v-for="user in staffDirectory"
                        :key="user.id"
                        class="p-2 rounded-xl bg-slate-950 border border-slate-800 flex items-center justify-between"
                    >
                        <div class="flex items-center space-x-2 min-w-0">
                            <img :src="user.avatar" class="w-8 h-8 rounded-full object-cover ring-1 ring-slate-800" />
                            <div class="min-w-0">
                                <div class="text-xs font-bold text-slate-200 truncate flex items-center space-x-1">
                                    <span>{{ user.name }}</span>
                                    <span v-if="user.is_admin" class="text-[9px] px-1 bg-amber-500/20 text-amber-400 rounded">Admin</span>
                                    <span v-if="user.is_customer" class="text-[9px] px-1 bg-purple-500/20 text-purple-300 rounded font-bold" title="Dual Role: Can create tickets">Customer Access</span>
                                </div>
                                <div class="text-[10px] text-slate-400 font-mono truncate">
                                    {{ user.member_id || 'ID: ' + user.id }} &bull; Staff
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center space-x-1 shrink-0 ml-2">
                            <!-- Admin can toggle customer access (Dual role) -->
                            <button
                                v-if="isAdmin"
                                @click="toggleCustomerAccess(user)"
                                :class="[
                                    'px-1.5 py-0.5 rounded text-[9px] font-semibold border transition-all',
                                    user.is_customer 
                                        ? 'bg-purple-500/20 border-purple-500/40 text-purple-300 hover:bg-purple-500/30' 
                                        : 'bg-slate-800 border-slate-700 text-slate-400 hover:text-slate-200'
                                ]"
                                :title="user.is_customer ? 'Dual Role: Can create tickets. Click to remove.' : 'Staff only: Cannot create tickets. Click to grant customer ticket capability.'"
                            >
                                {{ user.is_customer ? 'Ticket: YES' : 'Ticket: NO' }}
                            </button>

                            <button
                                @click="startChatWith(user)"
                                class="px-2 py-0.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-[10px] font-semibold flex items-center space-x-0.5"
                            >
                                <MessageSquare class="w-2.5 h-2.5" />
                                <span>Chat</span>
                            </button>

                            <button
                                v-if="isAdmin && currentAgent?.id !== user.id"
                                @click="deleteMember(user)"
                                class="p-1 rounded text-slate-500 hover:text-rose-400"
                                title="Delete"
                            >
                                <Trash2 class="w-3 h-3" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Customers Section -->
                <div class="space-y-1">
                    <div class="px-1 text-[10px] font-bold uppercase tracking-wider text-emerald-400">
                        Customers & Assigned Staff ({{ customerDirectory.length }})
                    </div>
                    <div
                        v-for="cust in customerDirectory"
                        :key="cust.id"
                        class="p-2 rounded-xl bg-slate-950 border border-slate-800 space-y-1.5"
                    >
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-2 min-w-0">
                                <img :src="cust.avatar" class="w-8 h-8 rounded-full object-cover ring-1 ring-slate-800" />
                                <div class="min-w-0">
                                    <div class="text-xs font-bold text-slate-200 truncate">
                                        {{ cust.name }}
                                    </div>
                                    <div class="text-[10px] text-slate-400 font-mono truncate">
                                        {{ cust.member_id }} &bull; {{ cust.email }}
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center space-x-1 shrink-0 ml-2">
                                <button
                                    @click="startChatWith(cust)"
                                    class="px-2 py-0.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-[10px] font-semibold flex items-center space-x-0.5"
                                >
                                    <MessageSquare class="w-2.5 h-2.5" />
                                    <span>Chat</span>
                                </button>
                                <button
                                    v-if="isAdmin"
                                    @click="deleteMember(cust)"
                                    class="p-1 rounded text-slate-500 hover:text-rose-400"
                                    title="Delete"
                                >
                                    <Trash2 class="w-3 h-3" />
                                </button>
                            </div>
                        </div>

                        <!-- Assigned Staff Selector (Admin can assign or edit) -->
                        <div class="pt-1.5 border-t border-slate-800/80 flex items-center justify-between text-[10px]">
                            <span class="text-slate-400">Assigned Staff:</span>
                            <select
                                v-if="isAdmin"
                                :value="cust.assigned_agent_id || ''"
                                @change="updateCustomerAgent(cust, $event.target.value)"
                                class="bg-slate-900 border border-slate-700 rounded px-1.5 py-0.5 text-[10px] text-indigo-300 focus:outline-none focus:border-indigo-500"
                            >
                                <option value="">None (Unassigned)</option>
                                <option v-for="s in staffDirectory" :key="s.id" :value="s.id">
                                    {{ s.name }} ({{ s.member_id }})
                                </option>
                            </select>
                            <span v-else class="text-indigo-300 font-medium">
                                {{ cust.assigned_agent?.name || 'Unassigned' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <!-- Sidebar Bottom Footer -->
        <div class="p-2 border-t border-slate-800 bg-slate-950 text-slate-400 text-xs flex items-center justify-between">
            <div class="flex items-center space-x-1.5">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                <span class="text-[11px] text-slate-300">Live Reverb</span>
            </div>
            <button
                v-if="canCreateTicket"
                @click="$emit('open-new-chat-modal', 'ticket')"
                class="text-[11px] text-indigo-400 hover:text-indigo-300 font-semibold"
            >
                + Create Ticket
            </button>
            <span v-else class="text-[11px] text-slate-500 font-medium">
                Staff Workspace
            </span>
        </div>
    </aside>
</template>
