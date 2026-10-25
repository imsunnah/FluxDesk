<script setup>
import { ref, computed, nextTick } from 'vue';
import { 
    Send, 
    Paperclip, 
    Smile, 
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
        is_internal_note: false,
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
    <div class="p-3 border-t border-slate-200 bg-white relative">
        <!-- Slash Command Popup -->
        <div 
            v-if="showCannedPopup && cannedFilter.length > 0"
            class="absolute bottom-full left-4 sm:w-96 mb-2 rounded-xl bg-white border border-slate-200 shadow-xl p-2 z-30 max-h-56 overflow-y-auto"
        >
            <div class="px-2 py-1 border-b border-slate-100 flex items-center justify-between text-xs text-slate-500">
                <span class="font-bold text-indigo-600">Quick Replies ({{ cannedFilter.length }})</span>
                <span class="text-[10px] text-slate-400 font-mono">Click to insert</span>
            </div>
            <div class="divide-y divide-slate-100">
                <button
                    v-for="item in cannedFilter"
                    :key="item.id"
                    @click="selectCanned(item)"
                    class="w-full text-left p-2 rounded-lg hover:bg-slate-50 transition-colors flex flex-col group"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-semibold text-indigo-600 font-mono">{{ item.shortcut }}</span>
                        <span class="text-[10px] text-slate-500">{{ item.title }}</span>
                    </div>
                    <p class="text-[11px] text-slate-600 truncate">{{ item.content }}</p>
                </button>
            </div>
        </div>

        <!-- Quick Emoji Bar -->
        <div 
            v-if="showEmojiPicker"
            class="absolute bottom-full right-16 mb-2 p-2 rounded-xl bg-white border border-slate-200 shadow-xl z-30 grid grid-cols-6 gap-1 w-52"
        >
            <button
                v-for="e in emojis"
                :key="e"
                @click="insertEmoji(e)"
                class="p-1.5 text-base hover:bg-slate-100 rounded-lg transition-transform hover:scale-125"
            >
                {{ e }}
            </button>
        </div>

        <!-- Attached Files Thumbnails / Chips -->
        <div v-if="selectedFiles.length > 0" class="flex flex-wrap gap-2 mb-2">
            <div 
                v-for="(file, idx) in selectedFiles" 
                :key="idx"
                class="flex items-center space-x-1.5 px-2.5 py-1 rounded-lg bg-slate-100 border border-slate-200 text-xs text-slate-700"
            >
                <img v-if="file.isImage && file.previewUrl" :src="file.previewUrl" class="w-6 h-6 rounded object-cover" />
                <Video v-else-if="file.isVideo" class="w-4 h-4 text-purple-600" />
                <FileText v-else class="w-4 h-4 text-indigo-600" />

                <span class="truncate max-w-[140px]">{{ file.name }}</span>
                <span class="text-[9px] text-slate-400">({{ file.size }})</span>

                <button @click="removeFile(idx)" class="text-slate-400 hover:text-rose-600 p-0.5">
                    <X class="w-3 h-3" />
                </button>
            </div>
        </div>

        <!-- Clean Chat Input Box -->
        <div class="flex items-end space-x-2 rounded-2xl p-2 border bg-slate-50 border-slate-300 focus-within:bg-white focus-within:border-indigo-600 focus-within:ring-1 focus-within:ring-indigo-600 transition-all">
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
                class="p-2 text-slate-500 hover:text-slate-700 hover:bg-slate-200/60 rounded-xl transition-all"
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
                placeholder="Type a message... (Press Enter to send)"
                class="flex-1 bg-transparent border-0 focus:outline-none focus:ring-0 text-xs sm:text-sm resize-none py-1.5 px-1 max-h-32 text-slate-900 placeholder-slate-400"
            ></textarea>

            <!-- Emoji -->
            <button
                type="button"
                @click="showEmojiPicker = !showEmojiPicker"
                class="p-2 text-slate-500 hover:text-amber-600 hover:bg-slate-200/60 rounded-xl transition-all"
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
                    'p-2 rounded-xl transition-all flex items-center justify-center shadow-xs',
                    (!messageText.trim() && selectedFiles.length === 0)
                        ? 'bg-slate-200 text-slate-400 cursor-not-allowed'
                        : 'bg-indigo-600 hover:bg-indigo-700 text-white'
                ]"
                title="Send"
            >
                <Send class="w-4 h-4" />
            </button>
        </div>
    </div>
</template>
