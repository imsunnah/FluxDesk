<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { 
    Search, 
    MessageSquare, 
    Users, 
    Lock, 
    Plus, 
    Inbox, 
    UserPlus, 
    Trash2, 
    Shield, 
    LogOut,
    Check
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
});

const emit = defineEmits([
    'select-conversation', 
    'update:searchQuery', 
    'set-filter', 
    'open-new-chat-modal',
    'start-direct-chat-with-user'
]);

// Main navigation: 'chats' or 'directory' (User List)
const mainView = ref('chats'); // 'chats' | 'members'

const filterTabs = [
    { key: 'all', label: 'All', icon: MessageSquare },
    { key: 'direct', label: 'Direct', icon: MessageSquare },
    { key: 'groups', label: 'Groups', icon: Users },
    { key: 'encrypted', label: 'Encrypted', icon: Lock },
];

const filteredConversations = computed(() => {
    let list = props.conversations;

    if (props.activeFilter === 'direct') {
        list = list.filter(c => c.type !== 'group' && !c.is_encrypted);
    } else if (props.activeFilter === 'groups') {
        list = list.filter(c => c.type === 'group');
    } else if (props.activeFilter === 'encrypted') {
        list = list.filter(c => c.is_encrypted);
    }

    if (props.searchQuery.trim()) {
        const query = props.searchQuery.toLowerCase();
        list = list.filter(c => 
            (c.customer_name && c.customer_name.toLowerCase().includes(query)) ||
            (c.group_name && c.group_name.toLowerCase().includes(query)) ||
            (c.customer_email && c.customer_email.toLowerCase().includes(query)) ||
            (c.latest_message?.body && c.latest_message.body.toLowerCase().includes(query))
        );
    }

    return list;
});

const filteredMembers = computed(() => {
    let list = props.agents;
    if (props.searchQuery.trim()) {
        const query = props.searchQuery.toLowerCase();
        list = list.filter(u => 
            u.name.toLowerCase().includes(query) ||
            u.email.toLowerCase().includes(query) ||
            (u.member_id && u.member_id.toLowerCase().includes(query))
        );
    }
    return list;
});

function startChatWith(user) {
    emit('start-direct-chat-with-user', user);
}

function deleteMember(user) {
    if (confirm(`Remove member ${user.name} (${user.member_id})?`)) {
        router.delete(`/members/${user.id}`);
    }
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
</script>

<template>
    <aside class="w-80 md:w-84 lg:w-88 flex flex-col h-full bg-slate-900 border-r border-slate-800 select-none z-10 shrink-0">
        <!-- Top Simple Header Bar -->
        <div class="px-3.5 py-3 border-b border-slate-800 flex items-center justify-between bg-slate-900">
            <div class="flex items-center space-x-2.5">
                <div class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center text-white font-bold text-sm shadow">
                    ⚡
                </div>
                <div>
                    <div class="flex items-center space-x-1.5">
                        <h2 class="font-bold text-slate-100 text-sm leading-tight">FluxDesk</h2>
                        <span v-if="currentAgent?.is_admin" class="px-1.5 py-0.2 rounded bg-amber-500/20 text-amber-400 font-bold text-[9px] uppercase border border-amber-500/30">
                            Admin
                        </span>
                    </div>
                    <p class="text-[11px] text-slate-400 truncate max-w-[130px]">
                        {{ currentAgent?.name }} ({{ currentAgent?.member_id || 'ID' }})
                    </p>
                </div>
            </div>

            <div class="flex items-center space-x-1">
                <!-- Add New Button -->
                <button
                    @click="$emit('open-new-chat-modal')"
                    class="px-2.5 py-1 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold flex items-center space-x-1 shadow transition-all"
                    title="Add Chat, Group, or Member"
                >
                    <Plus class="w-3.5 h-3.5" />
                    <span>New</span>
                </button>

                <!-- Logout -->
                <button 
                    @click="logout"
                    class="p-1.5 rounded-lg text-slate-400 hover:text-slate-200 hover:bg-slate-800"
                    title="Sign Out"
                >
                    <LogOut class="w-3.5 h-3.5" />
                </button>
            </div>
        </div>

        <!-- Main Switcher: Chats vs Members -->
        <div class="grid grid-cols-2 p-1 mx-3 mt-2.5 bg-slate-950 rounded-xl border border-slate-800 text-xs font-semibold">
            <button
                @click="mainView = 'chats'"
                :class="[
                    'py-1.5 rounded-lg flex items-center justify-center space-x-1.5 transition-all',
                    mainView === 'chats' ? 'bg-indigo-600 text-white shadow' : 'text-slate-400 hover:text-slate-200'
                ]"
            >
                <MessageSquare class="w-3.5 h-3.5" />
                <span>Chats ({{ conversations.length }})</span>
            </button>

            <button
                @click="mainView = 'members'"
                :class="[
                    'py-1.5 rounded-lg flex items-center justify-center space-x-1.5 transition-all',
                    mainView === 'members' ? 'bg-indigo-600 text-white shadow' : 'text-slate-400 hover:text-slate-200'
                ]"
            >
                <Users class="w-3.5 h-3.5" />
                <span>Members ({{ agents.length }})</span>
            </button>
        </div>

        <!-- Search Bar -->
        <div class="px-3 pt-2.5 pb-2">
            <div class="relative flex items-center">
                <Search class="w-4 h-4 text-slate-500 absolute left-3 pointer-events-none" />
                <input 
                    type="text"
                    :value="searchQuery"
                    @input="$emit('update:searchQuery', $event.target.value)"
                    :placeholder="mainView === 'chats' ? 'Search chats or messages...' : 'Search members by name or ID...'"
                    class="w-full pl-9 pr-7 py-1.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 placeholder-slate-500 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
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

        <!-- 1. CHATS VIEW -->
        <template v-if="mainView === 'chats'">
            <!-- Simple Filter Tabs -->
            <div class="px-3 pb-2">
                <div class="flex items-center space-x-1 p-1 bg-slate-950/70 rounded-xl border border-slate-800/80 text-[11px] font-medium overflow-x-auto">
                    <button
                        v-for="tab in filterTabs"
                        :key="tab.key"
                        @click="$emit('set-filter', tab.key)"
                        :class="[
                            'flex items-center space-x-1 px-2.5 py-1 rounded-lg transition-all shrink-0',
                            activeFilter === tab.key 
                                ? 'bg-indigo-600 text-white font-semibold shadow' 
                                : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/60'
                        ]"
                    >
                        <component :is="tab.icon" class="w-3 h-3" />
                        <span>{{ tab.label }}</span>
                    </button>
                </div>
            </div>

            <!-- Conversations List -->
            <div class="flex-1 overflow-y-auto px-2 space-y-1">
                <div 
                    v-if="filteredConversations.length === 0"
                    class="flex flex-col items-center justify-center h-48 text-center p-6 text-slate-500"
                >
                    <Inbox class="w-7 h-7 text-slate-600 mb-2" />
                    <p class="text-xs font-semibold text-slate-400">No chats found</p>
                    <button 
                        @click="$emit('open-new-chat-modal')"
                        class="mt-2 text-xs text-indigo-400 hover:text-indigo-300 font-medium"
                    >
                        + Start a chat or group
                    </button>
                </div>

                <div
                    v-for="conv in filteredConversations"
                    :key="conv.id"
                    @click="$emit('select-conversation', conv.id)"
                    :class="[
                        'group relative p-2.5 rounded-xl cursor-pointer transition-all flex items-center space-x-3',
                        activeConversationId === conv.id
                            ? 'bg-indigo-950/50 border border-indigo-500/40 text-slate-100'
                            : 'hover:bg-slate-800/40 border border-transparent text-slate-300'
                    ]"
                >
                    <!-- Avatar -->
                    <div class="relative shrink-0">
                        <img 
                            :src="conv.customer_avatar || 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=100&h=100&fit=crop'" 
                            :alt="conv.display_name"
                            class="w-10 h-10 rounded-full object-cover ring-1 ring-slate-800" 
                        />
                        <span class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 rounded-full bg-emerald-500 ring-2 ring-slate-900"></span>
                    </div>

                    <!-- Details -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between mb-0.5">
                            <div class="flex items-center space-x-1.5 truncate">
                                <span class="text-xs font-semibold text-slate-100 truncate group-hover:text-indigo-300">
                                    {{ conv.display_name }}
                                </span>
                                <Lock v-if="conv.is_encrypted" class="w-3 h-3 text-emerald-400 shrink-0" title="Encrypted" />
                                <span v-if="conv.type === 'group'" class="px-1.5 py-0.2 rounded bg-indigo-500/20 text-indigo-400 text-[9px] font-bold uppercase">
                                    Group
                                </span>
                            </div>
                            <span class="text-[10px] text-slate-500 font-mono shrink-0 ml-1">
                                {{ formatTime(conv.last_message_at || conv.updated_at) }}
                            </span>
                        </div>

                        <div class="flex items-center justify-between text-[11px] text-slate-400">
                            <p class="truncate leading-tight max-w-[170px]">
                                <span v-if="conv.latest_message?.is_internal_note" class="text-amber-400 font-medium">[Note] </span>
                                <span v-else-if="conv.latest_message?.sender_type === 'agent'" class="text-indigo-400">You: </span>
                                {{ conv.latest_message?.body || 'No messages yet' }}
                            </p>

                            <span 
                                v-if="(conv.unread_count || 0) > 0"
                                class="px-1.5 py-0.2 rounded-full bg-rose-500 text-white font-extrabold text-[10px] shrink-0"
                            >
                                {{ conv.unread_count }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <!-- 2. MEMBERS DIRECTORY VIEW -->
        <template v-else>
            <div class="px-3 pb-2 flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Team & Contacts</span>
                <button 
                    @click="$emit('open-new-chat-modal')"
                    class="text-xs text-indigo-400 hover:text-indigo-300 font-medium flex items-center space-x-1"
                >
                    <UserPlus class="w-3 h-3" />
                    <span>Add Member</span>
                </button>
            </div>

            <div class="flex-1 overflow-y-auto px-2 space-y-1.5">
                <div
                    v-for="user in filteredMembers"
                    :key="user.id"
                    class="p-2.5 rounded-xl bg-slate-950/60 border border-slate-800/80 hover:border-slate-700 transition-all flex items-center justify-between"
                >
                    <div class="flex items-center space-x-2.5 min-w-0">
                        <div class="relative shrink-0">
                            <img :src="user.avatar" class="w-9 h-9 rounded-full object-cover ring-1 ring-slate-800" />
                            <span class="absolute -bottom-0.5 -right-0.5 w-2 h-2 rounded-full bg-emerald-500 ring-2 ring-slate-900"></span>
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center space-x-1.5">
                                <span class="text-xs font-bold text-slate-200 truncate">{{ user.name }}</span>
                                <span v-if="user.is_admin" class="px-1 py-0.2 rounded bg-amber-500/20 text-amber-400 text-[9px] font-bold">Admin</span>
                            </div>
                            <div class="text-[10px] text-slate-400 font-mono truncate">
                                {{ user.member_id || 'ID: ' + user.id }} &bull; {{ user.role }}
                            </div>
                        </div>
                    </div>

                    <!-- Actions: Chat button & Admin delete member -->
                    <div class="flex items-center space-x-1 shrink-0 ml-2">
                        <button
                            @click="startChatWith(user)"
                            class="px-2 py-1 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-[11px] font-semibold transition-all flex items-center space-x-1"
                            title="Open direct chat"
                        >
                            <MessageSquare class="w-3 h-3" />
                            <span>Chat</span>
                        </button>

                        <button
                            v-if="currentAgent?.is_admin && currentAgent?.id !== user.id"
                            @click="deleteMember(user)"
                            class="p-1 rounded-lg text-slate-500 hover:text-rose-400 hover:bg-slate-900 transition-colors"
                            title="Remove member"
                        >
                            <Trash2 class="w-3.5 h-3.5" />
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <!-- Sidebar Footer -->
        <div class="p-2.5 border-t border-slate-800 bg-slate-950 text-slate-400 text-xs flex items-center justify-between">
            <div class="flex items-center space-x-2">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                <span class="text-[11px] text-slate-300">Reverb Live</span>
            </div>
            <button
                @click="$emit('open-new-chat-modal')"
                class="text-[11px] text-indigo-400 hover:text-indigo-300 font-medium"
            >
                + New Contact / Group
            </button>
        </div>
    </aside>
</template>
