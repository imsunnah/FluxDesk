<script setup>
import { ref, computed, nextTick } from 'vue';
import { 
    Send, 
    Paperclip, 
    Smile, 
    Lock, 
    MessageSquare, 
    Zap, 
    X,
    FileText,
    Image,
    Video
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
const selectedFiles = ref([]);
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

const emojis = ['👍', '👋', '⚡', '🚀', '✅', '❤️', '🙌', '🎉', '🔥', '💡', '💬', '🙏'];
let typingTimeout = null;

function handleInput() {
    if (messageText.value.startsWith('/')) {
        showCannedPopup.value = true;
    } else {
        showCannedPopup.value = false;
    }

    if (textareaRef.value) {
        textareaRef.value.style.height = 'auto';
        textareaRef.value.style.height = `${Math.min(textareaRef.value.scrollHeight, 120)}px`;
    }

    emit('typing', true);
    clearTimeout(typingTimeout);
    typingTimeout = setTimeout(() => {
        emit('typing', false);
    }, 2000);
}

function selectCanned(canned) {
    messageText.value = canned.content;
    showCannedPopup.value = false;
    nextTick(() => textareaRef.value?.focus());
}

function insertEmoji(emoji) {
    messageText.value += emoji;
    showEmojiPicker.value = false;
    nextTick(() => textareaRef.value?.focus());
}

function triggerFileUpload() {
    fileInput.value?.click();
}

function handleFileChange(e) {
    const files = e.target.files;
    if (!files || files.length === 0) return;

    for (let i = 0; i < files.length; i++) {
        const file = files[i];
        selectedFiles.value.push({
            rawFile: file,
            name: file.name,
            size: `${(file.size / 1024).toFixed(1)} KB`,
            type: file.type,
            isImage: file.type.startsWith('image/'),
            isVideo: file.type.startsWith('video/'),
            previewUrl: file.type.startsWith('image/') ? URL.createObjectURL(file) : null,
        });
    }
    e.target.value = '';
}

function removeFile(index) {
    selectedFiles.value.splice(index, 1);
}

function submitMessage() {
    const text = messageText.value.trim();
    if (!text && selectedFiles.value.length === 0) return;

    emit('send-message', {
        body: text,
        is_internal_note: isInternalNote.value,
        files: selectedFiles.value.map(f => f.rawFile),
    });

    messageText.value = '';
    selectedFiles.value = [];
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
    <div class="p-3 border-t border-slate-800 bg-slate-900 relative">
        <!-- Slash Command Popup -->
        <div 
            v-if="showCannedPopup && cannedFilter.length > 0"
            class="absolute bottom-full left-4 sm:w-96 mb-2 rounded-xl bg-slate-900 border border-slate-700 shadow-2xl p-2 z-30 max-h-56 overflow-y-auto"
        >
            <div class="px-2 py-1 border-b border-slate-800 flex items-center justify-between text-xs text-slate-400">
                <span class="font-bold text-indigo-400">Quick Replies ({{ cannedFilter.length }})</span>
                <span class="text-[10px] text-slate-500 font-mono">Click to insert</span>
            </div>
            <div class="divide-y divide-slate-800/40">
                <button
                    v-for="item in cannedFilter"
                    :key="item.id"
                    @click="selectCanned(item)"
                    class="w-full text-left p-2 rounded-lg hover:bg-slate-800 transition-colors flex flex-col group"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-indigo-300 font-mono">{{ item.shortcut }}</span>
                        <span class="text-[10px] text-slate-400">{{ item.title }}</span>
                    </div>
                    <p class="text-[11px] text-slate-400 truncate">{{ item.content }}</p>
                </button>
            </div>
        </div>

        <!-- Quick Emoji Bar -->
        <div 
            v-if="showEmojiPicker"
            class="absolute bottom-full right-16 mb-2 p-2 rounded-xl bg-slate-900 border border-slate-700 shadow-2xl z-30 grid grid-cols-6 gap-1 w-52"
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

        <!-- Attached Files Thumbnails / Chips -->
        <div v-if="selectedFiles.length > 0" class="flex flex-wrap gap-2 mb-2">
            <div 
                v-for="(file, idx) in selectedFiles" 
                :key="idx"
                class="flex items-center space-x-1.5 px-2.5 py-1 rounded-lg bg-slate-800 border border-slate-700 text-xs text-slate-200"
            >
                <img v-if="file.isImage && file.previewUrl" :src="file.previewUrl" class="w-6 h-6 rounded object-cover" />
                <Video v-else-if="file.isVideo" class="w-4 h-4 text-purple-400" />
                <FileText v-else class="w-4 h-4 text-indigo-400" />

                <span class="truncate max-w-[140px]">{{ file.name }}</span>
                <span class="text-[9px] text-slate-400">({{ file.size }})</span>

                <button @click="removeFile(idx)" class="text-slate-400 hover:text-rose-400 p-0.5">
                    <X class="w-3 h-3" />
                </button>
            </div>
        </div>

        <!-- Top mode switch: Public Message vs Internal Note -->
        <div class="flex items-center justify-between mb-2 px-1">
            <div class="flex items-center space-x-1 p-0.5 rounded-lg bg-slate-950 border border-slate-800">
                <button
                    type="button"
                    @click="isInternalNote = false"
                    :class="[
                        'flex items-center space-x-1 px-2.5 py-1 rounded-md text-xs font-semibold transition-all',
                        !isInternalNote ? 'bg-indigo-600 text-white shadow' : 'text-slate-400 hover:text-slate-200'
                    ]"
                >
                    <MessageSquare class="w-3.5 h-3.5" />
                    <span>Public</span>
                </button>

                <button
                    type="button"
                    @click="isInternalNote = true"
                    :class="[
                        'flex items-center space-x-1 px-2.5 py-1 rounded-md text-xs font-semibold transition-all',
                        isInternalNote ? 'bg-amber-500 text-slate-950 font-bold shadow' : 'text-slate-400 hover:text-amber-300'
                    ]"
                >
                    <Lock class="w-3.5 h-3.5" />
                    <span>Internal Note</span>
                </button>
            </div>

            <button 
                type="button"
                @click="messageText = '/'; showCannedPopup = true; textareaRef?.focus()"
                class="text-[11px] text-slate-400 hover:text-indigo-300 transition-colors hidden sm:inline"
            >
                Type <span class="font-mono text-indigo-400 font-bold">/</span> for quick replies
            </button>
        </div>

        <!-- Pill Chat Input Box -->
        <div 
            :class="[
                'flex items-end space-x-2 rounded-2xl p-2 border transition-all',
                isInternalNote ? 'bg-amber-950/20 border-amber-500/50' : 'bg-slate-950 border-slate-800 focus-within:border-indigo-500'
            ]"
        >
            <!-- All types file upload: image, video, audio, pdf, docs -->
            <input 
                type="file" 
                ref="fileInput" 
                @change="handleFileChange" 
                class="hidden" 
                multiple 
                accept="*/*"
            />

            <!-- Attachment Button (Share image/video/all file types) -->
            <button
                type="button"
                @click="triggerFileUpload"
                class="p-2 text-slate-400 hover:text-slate-200 hover:bg-slate-800 rounded-xl transition-all"
                title="Share image, video, audio, or document"
            >
                <Paperclip class="w-4 h-4" />
            </button>

            <!-- Text Input -->
            <textarea
                ref="textareaRef"
                v-model="messageText"
                @input="handleInput"
                @keydown="handleKeyDown"
                :disabled="disabled"
                rows="1"
                :placeholder="isInternalNote ? 'Write private internal note...' : 'Type a message... (Press Enter to send)'"
                :class="[
                    'flex-1 bg-transparent border-0 focus:outline-none focus:ring-0 text-xs sm:text-sm resize-none py-1.5 px-1 max-h-32',
                    isInternalNote ? 'text-amber-100 placeholder-amber-400/50' : 'text-slate-100 placeholder-slate-500'
                ]"
            ></textarea>

            <!-- Emoji -->
            <button
                type="button"
                @click="showEmojiPicker = !showEmojiPicker"
                class="p-2 text-slate-400 hover:text-amber-400 hover:bg-slate-800 rounded-xl transition-all"
                title="Emoji"
            >
                <Smile class="w-4 h-4" />
            </button>

            <!-- Send Button -->
            <button
                type="button"
                @click="submitMessage"
                :disabled="disabled || (!messageText.trim() && selectedFiles.length === 0)"
                :class="[
                    'p-2 rounded-xl transition-all flex items-center justify-center shadow-md',
                    (!messageText.trim() && selectedFiles.length === 0)
                        ? 'bg-slate-800 text-slate-600 cursor-not-allowed'
                        : (isInternalNote ? 'bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold' : 'bg-indigo-600 hover:bg-indigo-500 text-white')
                ]"
                title="Send"
            >
                <Send class="w-4 h-4" />
            </button>
        </div>
    </div>
</template>
