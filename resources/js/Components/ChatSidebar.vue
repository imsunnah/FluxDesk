<script setup>
import { computed } from 'vue';
import { 
    Search, 
    MessageSquare, 
    Inbox, 
    UserCheck, 
    CheckCircle2, 
    SlidersHorizontal,
    Sparkles
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
});

const emit = defineEmits(['select-conversation', 'update:searchQuery', 'set-filter']);

const filterTabs = [
    { key: 'all', label: 'All Chats', icon: MessageSquare },
    { key: 'unread', label: 'Unread', icon: Inbox },
    { key: 'mine', label: 'Mine', icon: UserCheck },
    { key: 'resolved', label: 'Resolved', icon: CheckCircle2 },
];

const filteredConversations = computed(() => {
    let list = props.conversations;

    // Filter by tab if client filtering
    if (props.activeFilter === 'unread') {
        list = list.filter(c => (c.unread_count || 0) > 0);
    } else if (props.activeFilter === 'mine') {
        list = list.filter(c => c.assigned_agent_id === props.currentAgent?.id);
    } else if (props.activeFilter === 'resolved') {
        list = list.filter(c => c.status === 'resolved');
    }

    // Filter by search query
    if (props.searchQuery.trim()) {
        const query = props.searchQuery.toLowerCase();
        list = list.filter(c => 
            c.customer_name?.toLowerCase().includes(query) ||
            c.customer_email?.toLowerCase().includes(query) ||
            c.customer_company?.toLowerCase().includes(query) ||
            c.latest_message?.body?.toLowerCase().includes(query)
        );
    }

    return list;
});

const totalUnreadCount = computed(() => {
    return props.conversations.reduce((sum, c) => sum + (c.unread_count || 0), 0);
});

function formatTime(isoString) {
    if (!isoString) return '';
    const date = new Date(isoString);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);

    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins}m ago`;
    if (diffMins < 1440) {
        return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }
    return date.toLocaleDateString([], { month: 'short', day: 'numeric' });
}

function priorityColor(priority) {
    switch (priority) {
        case 'high':
            return 'bg-rose-500/15 text-rose-400 border border-rose-500/30';
        case 'medium':
            return 'bg-amber-500/15 text-amber-400 border border-amber-500/30';
        case 'low':
            return 'bg-emerald-500/15 text-emerald-400 border border-emerald-500/30';
        default:
            return 'bg-slate-700/50 text-slate-300 border border-slate-600/30';
    }
}
</script>

<template>
    <aside class="w-80 md:w-88 lg:w-96 flex flex-col h-full bg-slate-900 border-r border-slate-800/80 select-none z-10 shrink-0">
        <!-- Top App Brand & Workspace Header -->
        <div class="p-3.5 border-b border-slate-800/80 flex items-center justify-between bg-slate-900/90 backdrop-blur-md">
            <div class="flex items-center space-x-2.5">
                <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-indigo-600 via-indigo-500 to-cyan-400 flex items-center justify-center shadow-lg shadow-indigo-500/20 text-white font-black text-lg tracking-wider">
                    ⚡
                </div>
                <div>
                    <div class="flex items-center space-x-1.5">
                        <span class="font-bold text-slate-100 text-sm tracking-tight">FluxDesk</span>
                        <span class="text-[10px] uppercase font-bold tracking-wider px-1.5 py-0.5 rounded bg-indigo-500/20 text-indigo-400 border border-indigo-500/30">PRO</span>
                    </div>
                    <p class="text-xs text-slate-400">Live Agent Workspace</p>
                </div>
            </div>

            <!-- Agent Mini Profile Badge -->
            <div class="flex items-center space-x-2 px-2 py-1 rounded-lg bg-slate-800/60 border border-slate-700/50">
                <div class="relative">
                    <img :src="currentAgent?.avatar" alt="Agent" class="w-6 h-6 rounded-full object-cover" />
                    <span class="absolute -bottom-0.5 -right-0.5 w-2 h-2 rounded-full bg-emerald-500 ring-2 ring-slate-900"></span>
                </div>
                <span class="text-xs font-medium text-slate-200 truncate max-w-[80px]">{{ currentAgent?.name?.split(' ')[0] }}</span>
            </div>
        </div>

        <!-- Search Bar with instant reactive filter -->
        <div class="p-3 pb-2">
            <div class="relative flex items-center">
                <Search class="w-4 h-4 text-slate-400 absolute left-3 pointer-events-none" />
                <input 
                    type="text"
                    :value="searchQuery"
                    @input="$emit('update:searchQuery', $event.target.value)"
                    placeholder="Search customers or messages..."
                    class="w-full pl-9 pr-8 py-2 bg-slate-950/70 border border-slate-800 rounded-xl text-xs text-slate-200 placeholder-slate-500 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/40 transition-all"
                />
                <button 
                    v-if="searchQuery"
                    @click="$emit('update:searchQuery', '')"
                    class="absolute right-2.5 text-xs text-slate-500 hover:text-slate-300 p-0.5"
                >
                    ✕
                </button>
            </div>
        </div>

        <!-- Filter Tabs: All Chats, Unread, Mine, Resolved -->
        <div class="px-3 pb-2">
            <div class="grid grid-cols-4 gap-1 p-1 bg-slate-950/60 rounded-xl border border-slate-800/80">
                <button
                    v-for="tab in filterTabs"
                    :key="tab.key"
                    @click="$emit('set-filter', tab.key)"
                    :class="[
                        'flex items-center justify-center space-x-1 py-1.5 px-1 rounded-lg text-xs font-medium transition-all relative',
                        activeFilter === tab.key 
                            ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/30' 
                            : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/50'
                    ]"
                >
                    <component :is="tab.icon" class="w-3.5 h-3.5 shrink-0" />
                    <span class="truncate text-[11px]">{{ tab.label }}</span>
                    <!-- Unread badge on Unread tab -->
                    <span 
                        v-if="tab.key === 'unread' && totalUnreadCount > 0"
                        class="absolute -top-1 -right-1 px-1.5 py-0.2 text-[9px] font-bold bg-rose-500 text-white rounded-full ring-2 ring-slate-900"
                    >
                        {{ totalUnreadCount }}
                    </span>
                </button>
            </div>
        </div>

        <!-- Conversations List -->
        <div class="flex-1 overflow-y-auto divide-y divide-slate-800/40 px-2 space-y-1">
            <div 
                v-if="filteredConversations.length === 0"
                class="flex flex-col items-center justify-center h-48 text-center p-6 text-slate-500"
            >
                <Inbox class="w-8 h-8 stroke-1 text-slate-600 mb-2" />
                <p class="text-xs font-medium text-slate-400">No conversations found</p>
                <p class="text-[11px] text-slate-600 mt-1">Try adjusting your filters or search keywords.</p>
            </div>

            <div
                v-for="conv in filteredConversations"
                :key="conv.id"
                @click="$emit('select-conversation', conv.id)"
                :class="[
                    'group relative p-3 rounded-xl cursor-pointer transition-all duration-150',
                    activeConversationId === conv.id
                        ? 'bg-gradient-to-r from-indigo-950/70 to-slate-800/80 border border-indigo-500/40 shadow-sm shadow-indigo-500/10'
                        : 'hover:bg-slate-800/40 border border-transparent'
                ]"
            >
                <!-- Active Indicator Border Bar on Left -->
                <div 
                    v-if="activeConversationId === conv.id"
                    class="absolute left-0 top-3 bottom-3 w-1 bg-indigo-500 rounded-r-full shadow-md shadow-indigo-500"
                ></div>

                <div class="flex items-start space-x-3">
                    <!-- Customer Avatar with active green dot -->
                    <div class="relative shrink-0">
                        <img 
                            :src="conv.customer_avatar || 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=100&h=100&fit=crop'" 
                            :alt="conv.customer_name"
                            class="w-11 h-11 rounded-full object-cover ring-2 ring-slate-800/80 group-hover:ring-slate-700" 
                        />
                        <!-- Active Green Status Dot -->
                        <span 
                            class="absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full bg-emerald-500 ring-2 ring-slate-900 shadow-sm"
                            title="Active customer"
                        ></span>
                    </div>

                    <!-- Conversation Details -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between mb-0.5">
                            <h4 class="text-xs font-semibold text-slate-100 truncate group-hover:text-indigo-300 transition-colors">
                                {{ conv.customer_name }}
                            </h4>
                            <span class="text-[10px] text-slate-400 font-mono shrink-0 ml-1">
                                {{ formatTime(conv.last_message_at || conv.updated_at) }}
                            </span>
                        </div>

                        <!-- Company or Email Sub-line -->
                        <div class="text-[11px] text-slate-400 truncate mb-1">
                            {{ conv.customer_company || conv.customer_email }}
                        </div>

                        <!-- Last Message Snippet -->
                        <p class="text-xs text-slate-300 truncate leading-relaxed">
                            <span v-if="conv.latest_message?.is_internal_note" class="text-amber-400 font-medium">[Note] </span>
                            <span v-else-if="conv.latest_message?.sender_type === 'agent'" class="text-indigo-400">You: </span>
                            {{ conv.latest_message?.body || 'No messages yet' }}
                        </p>

                        <!-- Bottom Meta: Priority Tag + Unread Badge + Status -->
                        <div class="mt-2 flex items-center justify-between text-[10px]">
                            <div class="flex items-center space-x-1.5">
                                <span :class="['px-1.5 py-0.5 rounded font-bold uppercase tracking-wider text-[9px]', priorityColor(conv.priority)]">
                                    {{ conv.priority }}
                                </span>
                                <span 
                                    v-if="conv.status === 'resolved'"
                                    class="px-1.5 py-0.5 rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-medium"
                                >
                                    Resolved
                                </span>
                                <span 
                                    v-else-if="conv.status === 'pending'"
                                    class="px-1.5 py-0.5 rounded bg-sky-500/10 text-sky-400 border border-sky-500/20 font-medium"
                                >
                                    Pending
                                </span>
                            </div>

                            <!-- Unread Counter Badge -->
                            <div 
                                v-if="(conv.unread_count || 0) > 0"
                                class="px-2 py-0.5 rounded-full bg-gradient-to-r from-rose-500 to-pink-500 text-white font-extrabold text-[10px] shadow-sm shadow-rose-500/30"
                            >
                                {{ conv.unread_count }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar Footer Status Indicator -->
        <div class="p-3 border-t border-slate-800/80 bg-slate-950/70 text-slate-400 text-xs flex items-center justify-between">
            <div class="flex items-center space-x-2">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span class="text-[11px] font-medium text-slate-300">Reverb WebSockets Active</span>
            </div>
            <span class="text-[10px] text-slate-500 font-mono">{{ filteredConversations.length }} chats</span>
        </div>
    </aside>
</template>
