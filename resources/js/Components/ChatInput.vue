<script setup>
import { ref, computed, watch, nextTick } from 'vue';
import { 
    Send, 
    Paperclip, 
    Smile, 
    Lock, 
    MessageSquare, 
    Zap, 
    X,
    FileText
} from 'lucide-vue-next';

const props = defineProps({
    cannedResponses: {
        type: Array,
        default: () => [],
    },
    disabled: {
        type: Boolean,
        default: false,
    },
});

const emit = defineEmits(['send-message', 'typing']);

const messageText = ref('');
const isInternalNote = ref(false);
const showCannedPopup = ref(false);
const showEmojiPicker = ref(false);
const attachments = ref([]);
const fileInput = ref(null);
const textareaRef = ref(null);

const cannedFilter = computed(() => {
    if (!messageText.value.startsWith('/')) return [];
    const query = messageText.value.slice(1).toLowerCase();
    return props.cannedResponses.filter(c => 
        c.shortcut.toLowerCase().includes(query) || 
        c.title.toLowerCase().includes(query)
    );
});

// Common emojis for quick reaction / support messaging
const emojis = ['👍', '👋', '⚡', '🚀', '✅', '❤️', '🙌', '🎉', '🔥', '💡', '💬', '🙏'];

let typingTimeout = null;

function handleInput(e) {
    // Check if user initiated a slash command
    if (messageText.value.startsWith('/')) {
        showCannedPopup.value = true;
    } else {
        showCannedPopup.value = false;
    }

    // Auto-grow textarea
    if (textareaRef.value) {
        textareaRef.value.style.height = 'auto';
        textareaRef.value.style.height = `${Math.min(textareaRef.value.scrollHeight, 120)}px`;
    }

    // Broadcast whisper typing event with debounce
    emit('typing', true);
    clearTimeout(typingTimeout);
    typingTimeout = setTimeout(() => {
        emit('typing', false);
    }, 2000);
}

function selectCanned(canned) {
    messageText.value = canned.content;
    showCannedPopup.value = false;
    nextTick(() => {
        textareaRef.value?.focus();
    });
}

function insertEmoji(emoji) {
    messageText.value += emoji;
    showEmojiPicker.value = false;
    nextTick(() => {
        textareaRef.value?.focus();
    });
}

function triggerFileUpload() {
    fileInput.value?.click();
}

function handleFileChange(e) {
    const files = e.target.files;
    if (!files || files.length === 0) return;

    for (let i = 0; i < files.length; i++) {
        const file = files[i];
        attachments.value.push({
            name: file.name,
            size: `${(file.size / 1024).toFixed(1)} KB`,
            type: file.type,
        });
    }
    // reset input
    e.target.value = '';
}

function removeAttachment(index) {
    attachments.value.splice(index, 1);
}

function submitMessage() {
    const text = messageText.value.trim();
    if (!text && attachments.value.length === 0) return;

    emit('send-message', {
        body: text,
        is_internal_note: isInternalNote.value,
        attachments: attachments.value.length > 0 ? [...attachments.value] : null,
    });

    // Reset input
    messageText.value = '';
    attachments.value = [];
    showCannedPopup.value = false;
    emit('typing', false);

    if (textareaRef.value) {
        textareaRef.value.style.height = 'auto';
    }
}

function handleKeyDown(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        submitMessage();
    } else if (e.key === 'Escape') {
        showCannedPopup.value = false;
        showEmojiPicker.value = false;
    }
}
</script>

<template>
    <div class="p-3 border-t border-slate-800/80 bg-slate-900/95 backdrop-blur-md relative">
        <!-- Floating Canned Responses Slash Popup Menu -->
        <div 
            v-if="showCannedPopup && cannedFilter.length > 0"
            class="absolute bottom-full left-4 right-4 sm:left-6 sm:w-96 mb-2 rounded-xl bg-slate-900 border border-slate-700/80 shadow-2xl p-2 z-30 max-h-60 overflow-y-auto animate-pop-in"
        >
            <div class="px-2 py-1.5 border-b border-slate-800 flex items-center justify-between text-xs text-slate-400">
                <span class="font-bold flex items-center space-x-1 text-indigo-400">
                    <Zap class="w-3.5 h-3.5" />
                    <span>Canned Quick Responses ({{ cannedFilter.length }})</span>
                </span>
                <span class="text-[10px] text-slate-500 font-mono">Click or Tab to insert</span>
            </div>
            <div class="divide-y divide-slate-800/40">
                <button
                    v-for="item in cannedFilter"
                    :key="item.id"
                    @click="selectCanned(item)"
                    class="w-full text-left p-2 rounded-lg hover:bg-slate-800/90 transition-colors group flex flex-col space-y-0.5"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-indigo-300 font-mono">{{ item.shortcut }}</span>
                        <span class="text-[10px] text-slate-400">{{ item.title }}</span>
                    </div>
                    <p class="text-[11px] text-slate-400 truncate group-hover:text-slate-300">
                        {{ item.content }}
                    </p>
                </button>
            </div>
        </div>

        <!-- Floating Emoji Quick Picker -->
        <div 
            v-if="showEmojiPicker"
            class="absolute bottom-full right-16 mb-2 p-2 rounded-xl bg-slate-900 border border-slate-700/80 shadow-2xl z-30 grid grid-cols-6 gap-1 w-52 animate-pop-in"
        >
            <button
                v-for="e in emojis"
                :key="e"
                @click="insertEmoji(e)"
                class="p-1.5 text-base hover:bg-slate-800 rounded-lg transition-transform hover:scale-125"
            >
                {{ e }}
            </button>
        </div>

        <!-- Attachment chips row -->
        <div v-if="attachments.length > 0" class="flex flex-wrap gap-2 mb-2">
            <div 
                v-for="(att, idx) in attachments" 
                :key="idx"
                class="flex items-center space-x-1.5 px-2.5 py-1 rounded-lg bg-slate-800 border border-slate-700 text-xs text-slate-200"
            >
                <FileText class="w-3.5 h-3.5 text-indigo-400" />
                <span class="truncate max-w-[150px]">{{ att.name }}</span>
                <button @click="removeAttachment(idx)" class="text-slate-400 hover:text-rose-400 p-0.5">
                    <X class="w-3 h-3" />
                </button>
            </div>
        </div>

        <!-- Top Control Bar: Mode Toggle ("Public Message" vs "Internal Note") & Slash shortcut hint -->
        <div class="flex items-center justify-between mb-2 px-1">
            <div class="flex items-center space-x-1 p-0.5 rounded-lg bg-slate-950/80 border border-slate-800">
                <button
                    type="button"
                    @click="isInternalNote = false"
                    :class="[
                        'flex items-center space-x-1.5 px-2.5 py-1 rounded-md text-xs font-semibold transition-all',
                        !isInternalNote 
                            ? 'bg-indigo-600 text-white shadow-sm' 
                            : 'text-slate-400 hover:text-slate-200'
                    ]"
                >
                    <MessageSquare class="w-3.5 h-3.5" />
                    <span>Public Message</span>
                </button>

                <button
                    type="button"
                    @click="isInternalNote = true"
                    :class="[
                        'flex items-center space-x-1.5 px-2.5 py-1 rounded-md text-xs font-semibold transition-all',
                        isInternalNote 
                            ? 'bg-amber-500 text-slate-950 font-bold shadow-sm' 
                            : 'text-slate-400 hover:text-amber-300'
                    ]"
                >
                    <Lock class="w-3.5 h-3.5" />
                    <span>Internal Note</span>
                </button>
            </div>

            <!-- Slash command hint badge -->
            <button 
                type="button"
                @click="messageText = '/'; showCannedPopup = true; textareaRef?.focus()"
                class="hidden sm:inline-flex items-center space-x-1 text-[11px] text-slate-400 hover:text-indigo-300 transition-colors"
                title="Click or type / to browse canned shortcuts"
            >
                <span class="px-1.5 py-0.5 rounded bg-slate-800 border border-slate-700 font-mono text-[10px] text-indigo-400">/</span>
                <span>Canned Responses</span>
            </button>
        </div>

        <!-- Pill-Shaped Chat Input Container -->
        <div 
            :class="[
                'flex items-end space-x-2 rounded-2xl p-2 transition-all duration-200 border',
                isInternalNote 
                    ? 'bg-amber-950/20 border-amber-500/50 focus-within:ring-2 focus-within:ring-amber-500/30' 
                    : 'bg-slate-950/80 border-slate-800 focus-within:border-indigo-500/70 focus-within:ring-2 focus-within:ring-indigo-500/20'
            ]"
        >
            <!-- Attachment File Input (Hidden) -->
            <input 
                type="file" 
                ref="fileInput" 
                @change="handleFileChange" 
                class="hidden" 
                multiple 
            />

            <!-- Attachment Button -->
            <button
                type="button"
                @click="triggerFileUpload"
                class="p-2 text-slate-400 hover:text-slate-200 hover:bg-slate-800/60 rounded-xl transition-all"
                title="Attach file"
            >
                <Paperclip class="w-4 h-4" />
            </button>

            <!-- Main Dynamic Textarea -->
            <textarea
                ref="textareaRef"
                v-model="messageText"
                @input="handleInput"
                @keydown="handleKeyDown"
                :disabled="disabled"
                rows="1"
                :placeholder="isInternalNote ? 'Write a private internal note for the support team...' : 'Reply to customer... (Press Enter to send, / for quick replies)'"
                :class="[
                    'flex-1 bg-transparent border-0 focus:outline-none focus:ring-0 text-xs sm:text-sm resize-none py-1.5 px-1 max-h-32 placeholder-slate-500',
                    isInternalNote ? 'text-amber-100 placeholder-amber-400/50' : 'text-slate-100'
                ]"
            ></textarea>

            <!-- Emoji Picker Trigger -->
            <button
                type="button"
                @click="showEmojiPicker = !showEmojiPicker"
                class="p-2 text-slate-400 hover:text-amber-400 hover:bg-slate-800/60 rounded-xl transition-all"
                title="Insert emoji"
            >
                <Smile class="w-4 h-4" />
            </button>

            <!-- Send Action Button -->
            <button
                type="button"
                @click="submitMessage"
                :disabled="disabled || (!messageText.trim() && attachments.length === 0)"
                :class="[
                    'p-2 rounded-xl transition-all flex items-center justify-center shadow-md',
                    (!messageText.trim() && attachments.length === 0)
                        ? 'bg-slate-800 text-slate-600 cursor-not-allowed'
                        : (isInternalNote 
                            ? 'bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold shadow-amber-500/20' 
                            : 'bg-indigo-600 hover:bg-indigo-500 text-white shadow-indigo-600/30 hover:scale-105')
                ]"
                title="Send message (Enter)"
            >
                <Send class="w-4 h-4" />
            </button>
        </div>
    </div>
</template>
