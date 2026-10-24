<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import ChatSidebar from '@/Components/ChatSidebar.vue';
import ChatHeader from '@/Components/ChatHeader.vue';
import MessageThread from '@/Components/MessageThread.vue';
import ChatInput from '@/Components/ChatInput.vue';
import ProfilePanel from '@/Components/ProfilePanel.vue';
import NewChatModal from '@/Components/NewChatModal.vue';

const props = defineProps({
    conversations: {
        type: Array,
        default: () => [],
    },
    activeConversation: {
        type: Object,
        default: null,
    },
    messages: {
        type: Array,
        default: () => [],
    },
    cannedResponses: {
        type: Array,
        default: () => [],
    },
    agents: {
        type: Array,
        default: () => [],
    },
    currentAgent: {
        type: Object,
        default: () => ({}),
    },
    filters: {
        type: Object,
        default: () => ({ filter: 'all', search: '' }),
    },
    reverb: {
        type: Object,
        default: () => ({}),
    },
});

const localMessages = ref([...props.messages]);
const localConversations = ref([...props.conversations]);
const activeConversationRef = ref(props.activeConversation ? { ...props.activeConversation } : null);
const searchQuery = ref(props.filters?.search || '');
const activeFilter = ref(props.filters?.filter || 'all');
const isProfileOpen = ref(true);
const isNewChatModalOpen = ref(false);
const activeModalTab = ref('ticket'); // 'ticket' | 'single' | 'group' | 'member'

const viewingAgents = ref([]);
const typingUser = ref(null);
let typingTimer = null;
let currentConvId = null;

watch(() => props.messages, (newMsgs) => {
    localMessages.value = [...newMsgs];
}, { deep: true });

watch(() => props.conversations, (newConvs) => {
    localConversations.value = [...newConvs];
}, { deep: true });

watch(() => props.activeConversation, (newActive) => {
    activeConversationRef.value = newActive ? { ...newActive } : null;
    joinChannels();
}, { deep: true });

// Setup Real-Time WebSockets: Connects both to public channel (0ms latency) and presence channel
function joinChannels() {
    if (!activeConversationRef.value) return;

    const convId = activeConversationRef.value.id;

    if (currentConvId && window.Echo) {
        window.Echo.leave(`chat.${currentConvId}`);
        window.Echo.leave(`chat.presence.${currentConvId}`);
    }

    currentConvId = convId;

    if (!window.Echo) return;

    // 1. Instant Public Channel: Receives broadcast in the EXACT SAME SECOND without auth delay!
    window.Echo.channel(`chat.${convId}`)
        .listen('.message.sent', (e) => {
            handleIncomingBroadcastMessage(e.message, e.conversation);
        })
        .listen('.conversation.updated', (e) => {
            handleIncomingConversationUpdate(e.conversation);
        });

    // 2. Presence Channel: Live viewers & typing whispers
    window.Echo.join(`chat.presence.${convId}`)
        .here((users) => {
            viewingAgents.value = users;
        })
        .joining((user) => {
            if (!viewingAgents.value.some(u => u.id === user.id)) {
                viewingAgents.value.push(user);
            }
        })
        .leaving((user) => {
            viewingAgents.value = viewingAgents.value.filter(u => u.id !== user.id);
        })
        .listenForWhisper('typing', (e) => {
            if (e.isTyping) {
                typingUser.value = e.user;
                clearTimeout(typingTimer);
                typingTimer = setTimeout(() => {
                    typingUser.value = null;
                }, 2000);
            } else {
                typingUser.value = null;
            }
        });
}

function handleIncomingBroadcastMessage(message, conversationMeta) {
    if (!message) return;

    // Check if message is already in local thread (e.g. sender's optimistic message)
    const existingIndex = localMessages.value.findIndex(m => 
        m.id === message.id || 
        (m.temp_id && m.body === message.body && m.sender_id === message.sender_id)
    );

    if (existingIndex > -1) {
        // Upgrade temporary message with real ID and attachments
        localMessages.value[existingIndex] = message;
    } else {
        // RECEIVER'S SCREEN: Immediately append message in the exact same second!
        localMessages.value.push(message);
    }

    // Update snippet in sidebar
    const target = localConversations.value.find(c => c.id === message.conversation_id);
    if (target) {
        target.latest_message = message;
        target.last_message_at = message.created_at;
        if (message.sender_type === 'customer' && target.id !== activeConversationRef.value?.id) {
            target.unread_count = (target.unread_count || 0) + 1;
        }
    }
}

function handleIncomingConversationUpdate(updatedConv) {
    if (!updatedConv) return;

    if (activeConversationRef.value && activeConversationRef.value.id === updatedConv.id) {
        Object.assign(activeConversationRef.value, updatedConv);
    }

    const target = localConversations.value.find(c => c.id === updatedConv.id);
    if (target) {
        Object.assign(target, updatedConv);
    }
}

function broadcastTyping(isTyping) {
    if (!currentConvId || !window.Echo) return;
    window.Echo.join(`chat.presence.${currentConvId}`)
        .whisper('typing', {
            user: props.currentAgent?.name || 'User',
            isTyping: isTyping,
        });
}

function selectConversation(id) {
    router.get('/', {
        conversation_id: id,
        filter: activeFilter.value,
        search: searchQuery.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        only: ['activeConversation', 'messages'],
    });
}

function setFilter(filter) {
    activeFilter.value = filter;
}

function openModalWithTab(tab = 'ticket') {
    activeModalTab.value = tab;
    isNewChatModalOpen.value = true;
}

function startDirectChatWithUser(user) {
    const existing = localConversations.value.find(c => 
        c.type === 'direct' && 
        (c.customer_email === user.email || c.members?.some(m => m.id === user.id))
    );

    if (existing) {
        selectConversation(existing.id);
    } else {
        router.post('/conversations/direct', {
            target_user_id: user.id,
        });
    }
}

// Instant Send Message (Optimistic UI + Server Broadcast)
function handleSendMessage({ body, is_internal_note, files }) {
    if (!activeConversationRef.value) return;
    const convId = activeConversationRef.value.id;

    let finalBody = body;
    if (activeConversationRef.value.is_encrypted && !is_internal_note && finalBody && !finalBody.startsWith('🔐')) {
        finalBody = '🔐 ' + finalBody;
    }

    // 1. OPTIMISTIC INSTANT INSERTION (Sender sees text in 0.001 seconds!)
    const tempId = `temp-${Date.now()}`;
    const optimisticMessage = {
        temp_id: tempId,
        id: null,
        conversation_id: convId,
        sender_type: 'agent',
        sender_id: props.currentAgent?.id || 1,
        body: finalBody || 'Attached file(s)',
        is_internal_note: is_internal_note,
        is_read: true,
        attachments: files && files.length > 0 ? files.map(f => ({
            name: f.name,
            size: `${(f.size / 1024).toFixed(1)} KB`,
            is_image: f.type.startsWith('image/'),
            is_video: f.type.startsWith('video/'),
            is_audio: f.type.startsWith('audio/'),
            url: f.type.startsWith('image/') ? URL.createObjectURL(f) : '',
        })) : null,
        created_at: new Date().toISOString(),
        sender: props.currentAgent,
    };

    localMessages.value.push(optimisticMessage);

    // 2. DISPATCH ASYNCHRONOUSLY TO BACKEND (Broadcasting to receiver in exact same second!)
    if (files && files.length > 0) {
        const formData = new FormData();
        formData.append('body', finalBody || '');
        formData.append('is_internal_note', is_internal_note ? '1' : '0');
        formData.append('sender_type', 'agent');
        files.forEach((file) => {
            formData.append('files[]', file);
        });

        router.post(`/conversations/${convId}/messages`, formData, {
            preserveScroll: true,
            preserveState: true,
            forceFormData: true,
        });
    } else {
        router.post(`/conversations/${convId}/messages`, {
            body: finalBody,
            is_internal_note,
            sender_type: 'agent',
        }, {
            preserveScroll: true,
            preserveState: true,
        });
    }
}

function handleUpdateConversation(data) {
    if (!activeConversationRef.value) return;
    const convId = activeConversationRef.value.id;
    Object.assign(activeConversationRef.value, data);

    router.patch(`/conversations/${convId}`, data, {
        preserveScroll: true,
        preserveState: true,
    });
}

function handleDeleteChat() {
    if (!activeConversationRef.value) return;
    const name = activeConversationRef.value.ticket_number 
        ? `${activeConversationRef.value.ticket_number} - ${activeConversationRef.value.ticket_subject}` 
        : activeConversationRef.value.display_name;

    if (confirm(`Delete "${name}"?`)) {
        router.delete(`/conversations/${activeConversationRef.value.id}`);
    }
}

function handleSimulateCustomerReply() {
    if (!activeConversationRef.value) return;
    const convId = activeConversationRef.value.id;

    const sampleReplies = [
        "Received your reply! Testing sub-second WebSocket ping.",
        "That worked right away, thank you!",
        "Can you also verify if the server logs look clear?",
    ];

    const randomBody = sampleReplies[Math.floor(Math.random() * sampleReplies.length)];

    typingUser.value = activeConversationRef.value.customer_name;
    setTimeout(() => {
        typingUser.value = null;
        router.post(`/conversations/${convId}/simulate-customer`, {
            body: randomBody,
        }, {
            preserveScroll: true,
            preserveState: true,
        });
    }, 800);
}

onMounted(() => {
    joinChannels();
});

onUnmounted(() => {
    if (currentConvId && window.Echo) {
        window.Echo.leave(`chat.${currentConvId}`);
        window.Echo.leave(`chat.presence.${currentConvId}`);
    }
});
</script>

<template>
    <Head title="FluxDesk — Real-Time Support & Tickets" />

    <div class="flex h-screen w-screen overflow-hidden bg-slate-950 font-sans text-slate-100">
        <!-- 1. LEFT SIDEBAR (Tickets, Live Chats, Members) -->
        <ChatSidebar
            :conversations="localConversations"
            :active-conversation-id="activeConversationRef?.id"
            :active-filter="activeFilter"
            :search-query="searchQuery"
            :current-agent="currentAgent"
            :agents="agents"
            @select-conversation="selectConversation"
            @set-filter="setFilter"
            @update:search-query="searchQuery = $event"
            @open-new-chat-modal="openModalWithTab($event)"
            @start-direct-chat-with-user="startDirectChatWithUser"
        />

        <!-- 2. CENTER WORKSPACE -->
        <main class="flex-1 flex flex-col h-full min-w-0 bg-slate-950 relative">
            <template v-if="activeConversationRef">
                <!-- Header Bar -->
                <ChatHeader
                    :conversation="activeConversationRef"
                    :active-agents-count="viewingAgents.length || 1"
                    :viewing-agents="viewingAgents"
                    :typing-user="typingUser"
                    :is-profile-open="isProfileOpen"
                    :current-agent="currentAgent"
                    @toggle-profile="isProfileOpen = !isProfileOpen"
                    @change-status="handleUpdateConversation({ status: $event })"
                    @simulate-customer-reply="handleSimulateCustomerReply"
                    @open-add-member="openModalWithTab('member')"
                    @delete-chat="handleDeleteChat"
                />

                <!-- Message Thread Stream (Instant Optimistic & Real-Time) -->
                <MessageThread
                    :messages="localMessages"
                    :conversation="activeConversationRef"
                    :current-agent="currentAgent"
                />

                <!-- Bottom Chat Input Bar -->
                <ChatInput
                    :canned-responses="cannedResponses"
                    @send-message="handleSendMessage"
                    @typing="broadcastTyping"
                />
            </template>

            <!-- No Selection State -->
            <div 
                v-else 
                class="flex-1 flex flex-col items-center justify-center p-8 text-center text-slate-500"
            >
                <div class="w-14 h-14 rounded-2xl bg-slate-900 border border-slate-800 flex items-center justify-center mb-3 text-indigo-400 text-2xl shadow">
                    ⚡
                </div>
                <h3 class="text-base font-bold text-slate-200">Welcome to FluxDesk</h3>
                <p class="text-xs text-slate-400 mt-1 max-w-sm">
                    Select a ticket or chat from the sidebar, or create a new ticket below.
                </p>
                <div class="flex items-center space-x-2 mt-4">
                    <button
                        @click="openModalWithTab('ticket')"
                        class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs shadow transition-all"
                    >
                        + Create Ticket
                    </button>
                    <button
                        @click="openModalWithTab('single')"
                        class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 font-semibold text-xs border border-slate-700 transition-all"
                    >
                        + Start Chat
                    </button>
                </div>
            </div>
        </main>

        <!-- 3. RIGHT DETAILS & TICKET UPDATER PANEL -->
        <ProfilePanel
            :conversation="activeConversationRef"
            :agents="agents"
            :current-agent="currentAgent"
            :is-open="isProfileOpen"
            @update-conversation="handleUpdateConversation"
            @open-add-member="openModalWithTab('member')"
            @delete-chat="handleDeleteChat"
            @close="isProfileOpen = false"
        />

        <!-- 4. ACTION MODAL (Ticket, Chat, Group, Member) -->
        <NewChatModal
            :is-open="isNewChatModalOpen"
            :agents="agents"
            :active-conversation-id="activeConversationRef?.id"
            :current-agent="currentAgent"
            :default-tab="activeModalTab"
            @close="isNewChatModalOpen = false"
        />
    </div>
</template>
