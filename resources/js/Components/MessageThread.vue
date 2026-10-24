<script setup>
import { ref, onMounted, watch, nextTick } from 'vue';
import { 
    Check, 
    CheckCheck, 
    Lock, 
    FileText, 
    Download, 
    Sparkles, 
    ShieldAlert, 
    Clock
} from 'lucide-vue-next';

const props = defineProps({
    messages: {
        type: Array,
        required: true,
    },
    conversation: {
        type: Object,
        default: null,
    },
    currentAgent: {
        type: Object,
        default: () => ({}),
    },
});

const threadContainer = ref(null);

function scrollToBottom(smooth = true) {
    nextTick(() => {
        if (threadContainer.value) {
            threadContainer.value.scrollTo({
                top: threadContainer.value.scrollHeight,
                behavior: smooth ? 'smooth' : 'auto',
            });
        }
    });
}

watch(() => props.messages.length, () => {
    scrollToBottom(true);
});

onMounted(() => {
    scrollToBottom(false);
});

function formatTime(isoString) {
    if (!isoString) return '';
    const date = new Date(isoString);
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

function formatDateHeader(isoString) {
    if (!isoString) return '';
    const date = new Date(isoString);
    const today = new Date();
    if (date.toDateString() === today.toDateString()) {
        return 'Today';
    }
    return date.toLocaleDateString([], { weekday: 'long', month: 'short', day: 'numeric' });
}
</script>

<template>
    <div 
        ref="threadContainer"
        class="flex-1 overflow-y-auto px-4 py-6 space-y-4 bg-gradient-to-b from-slate-950 via-slate-925 to-slate-950"
    >
        <!-- Date Divider -->
        <div class="flex items-center justify-center my-2">
            <span class="px-3 py-1 rounded-full bg-slate-900/90 border border-slate-800 text-[11px] font-medium text-slate-400 shadow-sm">
                {{ messages.length > 0 ? formatDateHeader(messages[0].created_at) : 'Today' }}
            </span>
        </div>

        <!-- Empty Conversation State -->
        <div v-if="messages.length === 0" class="flex flex-col items-center justify-center h-64 text-center text-slate-500">
            <div class="w-12 h-12 rounded-full bg-slate-800 flex items-center justify-center mb-3">
                <Sparkles class="w-6 h-6 text-indigo-400" />
            </div>
            <p class="text-sm font-semibold text-slate-300">Conversation started</p>
            <p class="text-xs text-slate-500 mt-1 max-w-sm">
                Send a public message to assist the customer or post an internal team note to coordinate with other agents.
            </p>
        </div>

        <!-- Messages Stream Loop -->
        <div 
            v-for="msg in messages" 
            :key="msg.id || msg.temp_id"
            class="transition-all duration-200"
        >
            <!-- 1. SPECIALIZED INTERNAL TEAM NOTE (Amber Full-Width Callout) -->
            <div 
                v-if="msg.is_internal_note"
                class="my-3 mx-auto max-w-2xl p-3.5 rounded-xl bg-gradient-to-r from-amber-500/15 via-amber-600/10 to-amber-500/15 border border-amber-500/30 text-amber-200 shadow-sm"
            >
                <div class="flex items-start space-x-2.5">
                    <div class="p-1 rounded-lg bg-amber-500/20 text-amber-400 shrink-0 mt-0.5">
                        <Lock class="w-4 h-4" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-xs font-bold uppercase tracking-wider text-amber-400 flex items-center space-x-1.5">
                                <span>Internal Team Note</span>
                                <span class="text-amber-500/80 font-normal">&bull; Visible only to agents</span>
                            </span>
                            <span class="text-[10px] text-amber-400/70 font-mono">
                                {{ formatTime(msg.created_at) }}
                            </span>
                        </div>
                        <p class="text-xs text-amber-100 leading-relaxed whitespace-pre-wrap font-sans">
                            {{ msg.body }}
                        </p>
                        <div class="mt-2 text-[10px] text-amber-400/80 flex items-center space-x-1 font-medium">
                            <img 
                                :src="msg.sender?.avatar || currentAgent?.avatar" 
                                class="w-3.5 h-3.5 rounded-full object-cover inline-block" 
                            />
                            <span>{{ msg.sender?.name || currentAgent?.name || 'Agent' }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. REGULAR CHAT MESSAGES (Customer Left / Agent Right) -->
            <div 
                v-else
                :class="[
                    'flex flex-col',
                    msg.sender_type === 'agent' ? 'items-end' : 'items-start'
                ]"
            >
                <div 
                    :class="[
                        'flex items-end space-x-2 max-w-[85%] sm:max-w-[75%]',
                        msg.sender_type === 'agent' ? 'flex-row-reverse space-x-reverse' : 'flex-row'
                    ]"
                >
                    <!-- Mini Avatar -->
                    <img 
                        :src="msg.sender_type === 'agent' 
                            ? (msg.sender?.avatar || currentAgent?.avatar || 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=80&h=80&fit=crop') 
                            : (conversation?.customer_avatar || 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=80&h=80&fit=crop')"
                        class="w-7 h-7 rounded-full object-cover shrink-0 ring-1 ring-slate-800 mb-1" 
                        :alt="msg.sender_type === 'agent' ? 'Agent' : 'Customer'"
                    />

                    <!-- Message Bubble Content -->
                    <div
                        :class="[
                            'px-4 py-2.5 rounded-2xl text-xs sm:text-[13px] leading-relaxed break-words transition-all',
                            msg.sender_type === 'agent'
                                ? 'bg-indigo-600 text-white rounded-br-none shadow-lg shadow-indigo-600/20'
                                : 'bg-slate-800 text-slate-100 border border-slate-700/80 rounded-bl-none shadow-md'
                        ]"
                    >
                        <!-- Sender label if agent in team -->
                        <div 
                            v-if="msg.sender_type === 'agent' && msg.sender?.name"
                            class="text-[10px] font-semibold text-indigo-200 mb-0.5"
                        >
                            {{ msg.sender.name }}
                        </div>

                        <!-- Body text -->
                        <div class="whitespace-pre-wrap selection:bg-slate-900/40">
                            {{ msg.body }}
                        </div>

                        <!-- Attachments preview if present -->
                        <div v-if="msg.attachments && msg.attachments.length > 0" class="mt-2 space-y-1">
                            <div 
                                v-for="(file, idx) in msg.attachments" 
                                :key="idx"
                                class="flex items-center space-x-2 p-1.5 rounded-lg bg-black/20 text-[11px]"
                            >
                                <FileText class="w-3.5 h-3.5 opacity-80" />
                                <span class="truncate flex-1">{{ file.name }}</span>
                                <span class="text-[9px] opacity-70">{{ file.size }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Subtle Time & Delivery Status under each bubble -->
                <div 
                    :class="[
                        'flex items-center space-x-1 text-[10px] text-slate-400 mt-1 px-10 font-mono',
                        msg.sender_type === 'agent' ? 'justify-end' : 'justify-start'
                    ]"
                >
                    <span>{{ formatTime(msg.created_at) }}</span>
                    <span>&bull;</span>
                    <span v-if="msg.sender_type === 'agent'" class="flex items-center space-x-0.5 text-indigo-400">
                        <span>Delivered</span>
                        <CheckCheck class="w-3 h-3 text-indigo-400 inline" />
                    </span>
                    <span v-else class="text-slate-400">
                        Received
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
