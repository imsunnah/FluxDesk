<script setup>
import { computed } from 'vue';
import { 
    Users, 
    PanelRightClose, 
    PanelRightOpen, 
    Lock, 
    UserPlus, 
    Bot,
    Trash2
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
});

const emit = defineEmits([
    'toggle-profile', 
    'change-status', 
    'simulate-customer-reply', 
    'open-add-member',
    'delete-chat'
]);

const title = computed(() => {
    if (!props.conversation) return '';
    return props.conversation.display_name || props.conversation.customer_name || 'Chat';
});

const memberCount = computed(() => {
    if (!props.conversation) return 0;
    return props.conversation.members?.length || 1;
});
</script>

<template>
    <header class="h-14 px-4 py-2 border-b border-slate-800 bg-slate-900 flex items-center justify-between z-10 shrink-0">
        <!-- Left: Chat Identity & Presence -->
        <div class="flex items-center space-x-3 min-w-0" v-if="conversation">
            <div class="relative shrink-0">
                <img 
                    :src="conversation.customer_avatar || 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=100&h=100&fit=crop'" 
                    :alt="title" 
                    class="w-9 h-9 rounded-full object-cover ring-1 ring-slate-700"
                />
                <span class="absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full bg-emerald-500 ring-2 ring-slate-900"></span>
            </div>

            <div class="min-w-0">
                <div class="flex items-center space-x-2">
                    <h3 class="text-xs sm:text-sm font-bold text-slate-100 truncate">
                        {{ title }}
                    </h3>

                    <!-- Encrypted Pill -->
                    <span 
                        v-if="conversation.is_encrypted" 
                        class="inline-flex items-center space-x-1 px-1.5 py-0.5 rounded-full bg-emerald-500/15 border border-emerald-500/30 text-emerald-400 text-[10px] font-semibold"
                        title="End-to-End Encrypted"
                    >
                        <Lock class="w-2.5 h-2.5" />
                        <span>Encrypted</span>
                    </span>

                    <!-- Group Badge -->
                    <span 
                        v-if="conversation.type === 'group'" 
                        class="inline-flex items-center space-x-1 px-1.5 py-0.5 rounded-full bg-indigo-500/15 border border-indigo-500/30 text-indigo-400 text-[10px] font-semibold"
                    >
                        <Users class="w-2.5 h-2.5" />
                        <span>{{ memberCount }} members</span>
                    </span>
                </div>

                <!-- Subtitle: Typing indicator or status -->
                <div class="flex items-center space-x-2 text-xs text-slate-400 mt-0.5">
                    <div v-if="typingUser" class="flex items-center space-x-1 text-indigo-400 font-medium">
                        <span class="w-1.5 h-1.5 bg-indigo-400 rounded-full animate-ping"></span>
                        <span class="text-[11px]">{{ typingUser }} is typing...</span>
                    </div>

                    <div v-else class="flex items-center space-x-1 text-slate-400 text-[11px]">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        <span v-if="conversation.type === 'group'">Active group chat</span>
                        <span v-else>{{ activeAgentsCount }} viewing</span>
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="text-slate-400 text-sm">
            Select a conversation
        </div>

        <!-- Right Controls -->
        <div class="flex items-center space-x-1.5" v-if="conversation">
            <!-- Add Member -->
            <button
                @click="$emit('open-add-member')"
                class="inline-flex items-center space-x-1 px-2.5 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-medium border border-slate-700/80 transition-all"
                title="Add member"
            >
                <UserPlus class="w-3.5 h-3.5 text-indigo-400" />
                <span class="hidden sm:inline">Add Member</span>
            </button>

            <!-- Delete Chat Button -->
            <button
                @click="$emit('delete-chat')"
                class="p-1.5 rounded-lg text-slate-400 hover:text-rose-400 hover:bg-slate-800 transition-colors"
                title="Delete Chat"
            >
                <Trash2 class="w-4 h-4" />
            </button>

            <!-- Quick Simulator -->
            <button
                @click="$emit('simulate-customer-reply')"
                title="Simulate incoming reply"
                class="hidden md:inline-flex items-center space-x-1 px-2 py-1 rounded-lg bg-indigo-950/60 hover:bg-indigo-900 border border-indigo-800 text-indigo-300 text-xs font-medium"
            >
                <Bot class="w-3.5 h-3.5" />
                <span>Simulate</span>
            </button>

            <!-- Toggle Details Panel -->
            <button
                @click="$emit('toggle-profile')"
                :class="[
                    'p-1.5 rounded-lg border transition-all',
                    isProfileOpen ? 'bg-slate-800 border-slate-700 text-indigo-400' : 'bg-slate-950 border-slate-800 text-slate-400'
                ]"
                title="Details"
            >
                <component :is="isProfileOpen ? PanelRightClose : PanelRightOpen" class="w-4 h-4" />
            </button>
        </div>
    </header>
</template>
