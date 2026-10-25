<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { 
    Users, 
    PanelRightClose, 
    PanelRightOpen, 
    Lock, 
    UserPlus, 
    Trash2, 
    Handshake, 
    Ticket
} from 'lucide-vue-next';

const props = defineProps({
    conversation: {
        type: Object,
        default: null,
    },
    activeAgentsCount: {
        type: Number,
        default: 1,
    },
    viewingAgents: {
        type: Array,
        default: () => [],
    },
    typingUser: {
        type: String,
        default: null,
    },
    isProfileOpen: {
        type: Boolean,
        default: true,
    },
    currentAgent: {
        type: Object,
        default: () => ({}),
    },
    activeView: {
        type: String,
        default: 'tickets',
    },
});

const emit = defineEmits([
    'toggle-profile', 
    'change-status', 
    'open-add-member',
    'delete-chat'
]);

const isTicketView = computed(() => props.activeView === 'tickets');
const isCustomer = computed(() => props.currentAgent?.is_customer);
const isUnassigned = computed(() => !props.conversation?.assigned_agent_id);

const title = computed(() => {
    if (!props.conversation) return '';
    if (isTicketView.value) {
        return props.conversation.ticket_subject || props.conversation.display_name || 'Support Ticket';
    }
    return props.conversation.display_name || props.conversation.customer_name || props.conversation.group_name || 'Chat';
});

const memberCount = computed(() => {
    if (!props.conversation) return 0;
    return props.conversation.members?.length || 1;
});

function claimTicket() {
    if (!props.conversation) return;
    router.post(`/conversations/${props.conversation.id}/claim`, {}, {
        preserveScroll: true,
        preserveState: true,
    });
}

function statusBadge(status) {
    switch (status) {
        case 'open':
            return 'bg-emerald-50 text-emerald-700 border border-emerald-200';
        case 'in_progress':
            return 'bg-sky-50 text-sky-700 border border-sky-200';
        case 'pending':
            return 'bg-amber-50 text-amber-700 border border-amber-200';
        case 'resolved':
        case 'closed':
            return 'bg-slate-100 text-slate-700 border border-slate-200';
        default:
            return 'bg-slate-100 text-slate-600';
    }
}
</script>

<template>
    <header class="h-16 px-4 py-2 border-b border-slate-200 bg-white flex items-center justify-between z-10 shrink-0 select-none">
        <!-- ============================================== -->
        <!-- A. TICKET HEADER (ONLY FOR TICKET DESK) -->
        <!-- ============================================== -->
        <template v-if="isTicketView">
            <div class="flex items-center space-x-3 min-w-0" v-if="conversation">
                <!-- Ticket ID Pill -->
                <div class="shrink-0">
                    <span class="inline-flex items-center px-2.5 py-1 rounded-lg font-mono bg-indigo-50 text-indigo-700 text-xs font-bold border border-indigo-200 shadow-xs">
                        {{ conversation.ticket_number || '#TK-' + conversation.id }}
                    </span>
                </div>

                <div class="min-w-0">
                    <div class="flex items-center space-x-2">
                        <h3 class="text-sm font-bold text-slate-900 truncate" :title="title">
                            {{ title }}
                        </h3>
                        <span :class="['px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider', statusBadge(conversation.status)]">
                            {{ conversation.status }}
                        </span>
                        <span v-if="conversation.priority" class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-600 border border-slate-200 uppercase">
                            {{ conversation.priority }}
                        </span>
                    </div>

                    <div class="flex items-center space-x-2 text-xs text-slate-500 mt-0.5">
                        <span class="text-slate-500 text-[11px]">
                            Requester: <strong class="text-slate-800">{{ conversation.customer_name }}</strong> 
                            <span class="text-slate-400 font-mono">({{ conversation.customer_email }})</span>
                        </span>
                        <span class="text-slate-300">&bull;</span>
                        <span class="text-[11px]">
                            Assignee: 
                            <strong v-if="conversation.assigned_agent" class="text-indigo-600 font-semibold">{{ conversation.assigned_agent.name }}</strong>
                            <strong v-else class="text-amber-700 font-semibold">⚡ Unassigned Pool</strong>
                        </span>
                    </div>
                </div>
            </div>

            <div v-else class="text-slate-400 text-xs font-medium">
                No Ticket Selected
            </div>

            <!-- Ticket Right Controls -->
            <div class="flex items-center space-x-2 shrink-0" v-if="conversation">
                <!-- Collect Ticket Button (If Unassigned & User is Staff) -->
                <button
                    v-if="isUnassigned && !isCustomer"
                    @click="claimTicket"
                    class="inline-flex items-center space-x-1.5 px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold shadow-xs transition-all"
                    title="Claim and collect ticket ownership"
                >
                    <Handshake class="w-3.5 h-3.5" />
                    <span>Collect Ticket</span>
                </button>

                <!-- Status Dropdown (Staff / Admin) -->
                <div v-if="!isCustomer" class="flex items-center space-x-1 bg-slate-50 border border-slate-200 rounded-xl px-2 py-1">
                    <span class="text-[10px] text-slate-500 font-medium">Status:</span>
                    <select
                        :value="conversation.status"
                        @change="$emit('change-status', $event.target.value)"
                        class="bg-transparent text-xs text-indigo-700 font-semibold focus:outline-none cursor-pointer capitalize"
                    >
                        <option value="open">Open</option>
                        <option value="in_progress">In Progress</option>
                        <option value="pending">Pending</option>
                        <option value="resolved">Resolved</option>
                        <option value="closed">Closed</option>
                    </select>
                </div>

                <!-- Toggle Ticket Details Panel -->
                <button
                    @click="$emit('toggle-profile')"
                    :class="[
                        'p-2 rounded-xl border transition-all flex items-center space-x-1 text-xs font-semibold',
                        isProfileOpen ? 'bg-indigo-50 border-indigo-300 text-indigo-700 shadow-xs' : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'
                    ]"
                    title="Ticket Details"
                >
                    <Ticket class="w-4 h-4" />
                    <span class="hidden sm:inline">Details</span>
                </button>
            </div>
        </template>

        <!-- ============================================== -->
        <!-- B. CHAT HEADER (ONLY FOR DIRECT & GROUP CHATS) -->
        <!-- ============================================== -->
        <template v-else>
            <div class="flex items-center space-x-3 min-w-0" v-if="conversation">
                <div class="relative shrink-0">
                    <img 
                        :src="conversation.customer_avatar || 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=100&h=100&fit=crop'" 
                        :alt="title" 
                        class="w-10 h-10 rounded-full object-cover ring-2 ring-indigo-100"
                    />
                    <span class="absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full bg-emerald-500 ring-2 ring-white"></span>
                </div>

                <div class="min-w-0">
                    <div class="flex items-center space-x-2">
                        <h3 class="text-sm font-bold text-slate-900 truncate">
                            {{ title }}
                        </h3>

                        <!-- Encrypted Pill -->
                        <span 
                            v-if="conversation.is_encrypted" 
                            class="inline-flex items-center space-x-1 px-2 py-0.5 rounded-full bg-emerald-50 border border-emerald-200 text-emerald-700 text-[10px] font-semibold"
                            title="End-to-End Encrypted"
                        >
                            <Lock class="w-2.5 h-2.5" />
                            <span>Encrypted</span>
                        </span>

                        <!-- Group Badge -->
                        <span 
                            v-if="conversation.type === 'group'" 
                            class="inline-flex items-center space-x-1 px-2 py-0.5 rounded-full bg-indigo-50 border border-indigo-200 text-indigo-700 text-[10px] font-semibold"
                        >
                            <Users class="w-2.5 h-2.5" />
                            <span>{{ memberCount }} members</span>
                        </span>
                    </div>

                    <!-- Subtitle: Typing indicator or online -->
                    <div class="flex items-center space-x-2 text-xs text-slate-500 mt-0.5">
                        <div v-if="typingUser" class="flex items-center space-x-1 text-indigo-600 font-medium">
                            <span class="w-1.5 h-1.5 bg-indigo-600 rounded-full animate-ping"></span>
                            <span class="text-[11px]">{{ typingUser }} is typing...</span>
                        </div>

                        <div v-else class="flex items-center space-x-1.5 text-slate-500 text-[11px]">
                            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                            <span v-if="conversation.type === 'group'">Team Channel</span>
                            <span v-else class="text-emerald-600 font-medium">Online</span>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="text-slate-400 text-xs font-medium">
                No Chat Selected
            </div>

            <!-- Chat Right Controls -->
            <div class="flex items-center space-x-2 shrink-0" v-if="conversation">
                <!-- Add Member (Group Chat only) -->
                <button
                    v-if="conversation.type === 'group' && !isCustomer"
                    @click="$emit('open-add-member')"
                    class="inline-flex items-center space-x-1 px-2.5 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-medium border border-slate-200 transition-all"
                    title="Add member"
                >
                    <UserPlus class="w-3.5 h-3.5 text-indigo-600" />
                    <span class="hidden sm:inline">Add Member</span>
                </button>

                <!-- Delete Chat Button -->
                <button
                    v-if="!isCustomer"
                    @click="$emit('delete-chat')"
                    class="p-2 rounded-xl text-slate-400 hover:text-rose-600 hover:bg-rose-50 transition-colors"
                    title="Delete Chat"
                >
                    <Trash2 class="w-4 h-4" />
                </button>

                <!-- Toggle Chat Details Panel -->
                <button
                    @click="$emit('toggle-profile')"
                    :class="[
                        'p-2 rounded-xl border transition-all text-xs font-semibold flex items-center space-x-1',
                        isProfileOpen ? 'bg-indigo-50 border-indigo-300 text-indigo-700 shadow-xs' : 'bg-white border-slate-200 text-slate-600 hover:bg-slate-50'
                    ]"
                    title="Chat Info"
                >
                    <Users class="w-4 h-4" />
                    <span class="hidden sm:inline">Info</span>
                </button>
            </div>
        </template>
    </header>
</template>
