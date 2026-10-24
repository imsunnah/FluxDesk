<script setup>
import { computed } from 'vue';
import { 
    Users, 
    MoreVertical, 
    PanelRightClose, 
    PanelRightOpen, 
    CheckCircle2, 
    Clock, 
    AlertCircle,
    Bot,
    Radio
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
    statusUpdating: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['toggle-profile', 'change-status', 'simulate-customer-reply']);

const statusConfig = {
    open: { label: 'Open', color: 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30' },
    pending: { label: 'Pending', color: 'bg-amber-500/15 text-amber-400 border-amber-500/30' },
    resolved: { label: 'Resolved', color: 'bg-slate-700/40 text-slate-400 border-slate-600/30' },
};
</script>

<template>
    <header class="h-16 px-4 border-b border-slate-800/80 bg-slate-900/90 backdrop-blur-md flex items-center justify-between z-10 shrink-0">
        <!-- Left: Customer info & Presence -->
        <div class="flex items-center space-x-3 min-w-0" v-if="conversation">
            <div class="relative shrink-0">
                <img 
                    :src="conversation.customer_avatar || 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=100&h=100&fit=crop'" 
                    :alt="conversation.customer_name" 
                    class="w-10 h-10 rounded-full object-cover ring-2 ring-slate-800"
                />
                <span class="absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full bg-emerald-500 ring-2 ring-slate-900"></span>
            </div>

            <div class="min-w-0">
                <div class="flex items-center space-x-2">
                    <h3 class="text-sm font-semibold text-slate-100 truncate">
                        {{ conversation.customer_name }}
                    </h3>
                    <span class="text-xs text-slate-400 hidden sm:inline truncate">
                        &bull; {{ conversation.customer_company || conversation.customer_email }}
                    </span>
                </div>

                <!-- Live Indicators Row: Presence Counter + Typing indicator -->
                <div class="flex items-center space-x-3 text-xs mt-0.5">
                    <!-- Typing Indicator Notification -->
                    <div v-if="typingUser" class="flex items-center space-x-1.5 text-indigo-400 font-medium animate-pulse">
                        <span class="flex space-x-0.5">
                            <span class="w-1.5 h-1.5 bg-indigo-400 rounded-full animate-bounce [animation-delay:-0.3s]"></span>
                            <span class="w-1.5 h-1.5 bg-indigo-400 rounded-full animate-bounce [animation-delay:-0.15s]"></span>
                            <span class="w-1.5 h-1.5 bg-indigo-400 rounded-full animate-bounce"></span>
                        </span>
                        <span class="text-[11px]">{{ typingUser }} is typing...</span>
                    </div>

                    <!-- Online Presence Counter -->
                    <div v-else class="flex items-center space-x-1.5 text-slate-400">
                        <span class="relative flex h-2 w-2">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
                        </span>
                        <span class="text-[11px]">
                            {{ activeAgentsCount }} {{ activeAgentsCount === 1 ? 'agent' : 'agents' }} viewing
                        </span>
                        <div class="hidden md:flex -space-x-1 overflow-hidden ml-1">
                            <img 
                                v-for="agent in viewingAgents.slice(0, 3)" 
                                :key="agent.id" 
                                :src="agent.avatar" 
                                :title="agent.name"
                                class="inline-block h-4 w-4 rounded-full ring-1 ring-slate-900 object-cover" 
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="text-slate-400 text-sm">
            Select a conversation to start messaging
        </div>

        <!-- Right: Status controls, Simulator trigger, and Profile Toggle -->
        <div class="flex items-center space-x-2" v-if="conversation">
            <!-- Customer Simulation Trigger (For instantaneous interactive demonstration) -->
            <button
                @click="$emit('simulate-customer-reply')"
                title="Simulate customer reply to test real-time WebSocket flow"
                class="hidden sm:inline-flex items-center space-x-1 px-2.5 py-1.5 rounded-lg bg-indigo-950/60 hover:bg-indigo-900/80 border border-indigo-700/40 text-indigo-300 text-xs font-medium transition-all shadow-sm"
            >
                <Bot class="w-3.5 h-3.5 text-indigo-400" />
                <span>Simulate Customer</span>
            </button>

            <!-- Status Selector Dropdown / Buttons -->
            <div class="inline-flex rounded-lg p-0.5 bg-slate-950/80 border border-slate-800">
                <button
                    v-for="st in ['open', 'pending', 'resolved']"
                    :key="st"
                    @click="$emit('change-status', st)"
                    :class="[
                        'px-2 py-1 text-[11px] font-medium capitalize rounded-md transition-all',
                        conversation.status === st 
                            ? (st === 'open' ? 'bg-emerald-600 text-white shadow' : (st === 'pending' ? 'bg-amber-600 text-white shadow' : 'bg-slate-700 text-white shadow'))
                            : 'text-slate-400 hover:text-slate-200'
                    ]"
                >
                    {{ st }}
                </button>
            </div>

            <!-- Toggle Right Profile / Ticket Info Panel Button -->
            <button
                @click="$emit('toggle-profile')"
                :class="[
                    'p-2 rounded-lg border transition-all',
                    isProfileOpen 
                        ? 'bg-slate-800 border-slate-700 text-indigo-400' 
                        : 'bg-slate-950/60 border-slate-800 text-slate-400 hover:text-slate-200'
                ]"
                title="Toggle Contact & Ticket Info (Collapsible)"
            >
                <component :is="isProfileOpen ? PanelRightClose : PanelRightOpen" class="w-4 h-4" />
            </button>
        </div>
    </header>
</template>
