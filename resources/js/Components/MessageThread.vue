<script setup>
import { ref, onMounted, watch, nextTick } from 'vue';
import { router } from '@inertiajs/vue3';
import { 
    CheckCheck, 
    Lock, 
    FileText, 
    Sparkles, 
    ShieldCheck,
    Download,
    Trash2,
    Play
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

function deleteMessage(msgId) {
    if (confirm('Delete this message?')) {
        router.delete(`/messages/${msgId}`, {
            preserveScroll: true,
            preserveState: true,
        });
    }
}
</script>

<template>
    <div 
        ref="threadContainer"
        class="flex-1 overflow-y-auto px-4 py-5 space-y-3 bg-slate-950"
    >
        <!-- End-to-End Encryption Banner if encrypted -->
        <div 
            v-if="conversation?.is_encrypted"
            class="max-w-md mx-auto p-2.5 rounded-xl bg-emerald-950/30 border border-emerald-500/20 text-center flex items-center justify-center space-x-2 text-[11px] text-emerald-300 shadow-sm"
        >
            <ShieldCheck class="w-4 h-4 text-emerald-400 shrink-0" />
            <span>Messages in this chat are end-to-end encrypted. No third party can read them.</span>
        </div>

        <!-- Empty State -->
        <div v-if="messages.length === 0" class="flex flex-col items-center justify-center h-48 text-center text-slate-500">
            <Sparkles class="w-8 h-8 text-indigo-400 mb-2" />
            <p class="text-xs font-semibold text-slate-300">No messages yet</p>
            <p class="text-[11px] text-slate-500 mt-0.5">Send a message below to start the conversation.</p>
        </div>

        <!-- Messages Loop -->
        <div 
            v-for="msg in messages" 
            :key="msg.id || msg.temp_id"
            class="group/msg relative transition-all duration-150"
        >
            <!-- 1. INTERNAL TEAM NOTE (Amber Callout) -->
            <div 
                v-if="msg.is_internal_note"
                class="my-2 mx-auto max-w-xl p-3 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-200 shadow-sm relative group"
            >
                <div class="flex items-start space-x-2">
                    <Lock class="w-3.5 h-3.5 text-amber-400 shrink-0 mt-0.5" />
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between text-[11px] font-bold text-amber-400 mb-0.5">
                            <span>INTERNAL NOTE</span>
                            <div class="flex items-center space-x-2">
                                <span class="font-mono text-[10px] text-amber-400/70">{{ formatTime(msg.created_at) }}</span>
                                <button 
                                    v-if="(currentAgent?.is_admin || msg.sender_id === currentAgent?.id) && msg.id"
                                    @click="deleteMessage(msg.id)"
                                    class="text-amber-400/50 hover:text-rose-400 opacity-0 group-hover:opacity-100 transition-opacity p-0.5"
                                    title="Delete Note"
                                >
                                    <Trash2 class="w-3 h-3" />
                                </button>
                            </div>
                        </div>
                        <p class="text-xs text-amber-100 whitespace-pre-wrap leading-relaxed">{{ msg.body }}</p>
                    </div>
                </div>
            </div>

            <!-- 2. REGULAR CHAT MESSAGES -->
            <div 
                v-else
                :class="[
                    'flex flex-col',
                    msg.sender_type === 'agent' ? 'items-end' : 'items-start'
                ]"
            >
                <div 
                    :class="[
                        'flex items-end space-x-2 max-w-[85%] sm:max-w-[70%]',
                        msg.sender_type === 'agent' ? 'flex-row-reverse space-x-reverse' : 'flex-row'
                    ]"
                >
                    <!-- Mini Avatar -->
                    <img 
                        :src="msg.sender_type === 'agent' 
                            ? (msg.sender?.avatar || currentAgent?.avatar || 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=80&h=80&fit=crop') 
                            : (conversation?.customer_avatar || 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=80&h=80&fit=crop')"
                        class="w-6 h-6 rounded-full object-cover shrink-0 ring-1 ring-slate-800 mb-0.5" 
                    />

                    <!-- Message Bubble -->
                    <div
                        :class="[
                            'px-3.5 py-2 rounded-2xl text-xs sm:text-[13px] leading-relaxed break-words shadow-sm relative group',
                            msg.sender_type === 'agent'
                                ? 'bg-indigo-600 text-white rounded-br-none'
                                : 'bg-slate-800 text-slate-100 border border-slate-700/60 rounded-bl-none'
                        ]"
                    >
                        <!-- Group sender name -->
                        <div 
                            v-if="conversation?.type === 'group' && msg.sender?.name"
                            class="text-[10px] font-bold text-indigo-200 mb-0.5"
                        >
                            {{ msg.sender.name }}
                        </div>

                        <!-- Text Body -->
                        <div v-if="msg.body && msg.body !== 'Attached file(s)'" class="whitespace-pre-wrap">
                            {{ msg.body }}
                        </div>

                        <!-- ATTACHMENTS (Images, Videos, Audio, Files) -->
                        <div v-if="msg.attachments && msg.attachments.length > 0" class="mt-2 space-y-2">
                            <div 
                                v-for="(file, idx) in msg.attachments" 
                                :key="idx"
                                class="rounded-lg overflow-hidden border border-black/20"
                            >
                                <!-- Image Preview -->
                                <div v-if="file.is_image || file.type?.startsWith('image/')">
                                    <a :href="file.url" target="_blank" class="block group/img relative">
                                        <img :src="file.url" class="max-h-60 w-auto rounded-lg object-contain hover:opacity-95" />
                                    </a>
                                </div>

                                <!-- Video Player -->
                                <div v-else-if="file.is_video || file.type?.startsWith('video/')">
                                    <video controls class="max-h-64 rounded-lg w-full bg-black">
                                        <source :src="file.url" :type="file.type">
                                        Your browser does not support video playback.
                                    </video>
                                </div>

                                <!-- Audio Player -->
                                <div v-else-if="file.is_audio || file.type?.startsWith('audio/')" class="p-2 bg-slate-900 rounded-lg">
                                    <audio controls class="w-full">
                                        <source :src="file.url" :type="file.type">
                                    </audio>
                                </div>

                                <!-- Document / Generic File -->
                                <div v-else class="flex items-center justify-between p-2 rounded-lg bg-black/20 text-xs">
                                    <div class="flex items-center space-x-2 truncate">
                                        <FileText class="w-4 h-4 text-indigo-300 shrink-0" />
                                        <div class="truncate">
                                            <div class="font-medium truncate">{{ file.name }}</div>
                                            <div class="text-[10px] opacity-70">{{ file.size }}</div>
                                        </div>
                                    </div>
                                    <a 
                                        :href="file.url" 
                                        :download="file.name"
                                        class="p-1 rounded hover:bg-white/20 transition-colors ml-2"
                                        title="Download File"
                                    >
                                        <Download class="w-4 h-4" />
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Delete message button for Admin / Sender -->
                        <button
                            v-if="(currentAgent?.is_admin || msg.sender_id === currentAgent?.id) && msg.id"
                            @click="deleteMessage(msg.id)"
                            class="absolute -top-2 -right-2 p-1 rounded-full bg-slate-900 border border-slate-700 text-slate-400 hover:text-rose-400 opacity-0 group-hover:opacity-100 transition-opacity shadow"
                            title="Delete Message"
                        >
                            <Trash2 class="w-3 h-3" />
                        </button>
                    </div>
                </div>

                <!-- Timestamp -->
                <div 
                    :class="[
                        'flex items-center space-x-1 text-[10px] text-slate-400 mt-0.5 px-8 font-mono',
                        msg.sender_type === 'agent' ? 'justify-end' : 'justify-start'
                    ]"
                >
                    <span>{{ formatTime(msg.created_at) }}</span>
                    <span v-if="msg.sender_type === 'agent'" class="text-indigo-400 flex items-center space-x-0.5">
                        <CheckCheck class="w-3 h-3 inline" />
                    </span>
                </div>
            </div>
        </div>
    </div>
</template>
