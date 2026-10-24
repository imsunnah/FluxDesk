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

const viewingAgents = ref([]);
const typingUser = ref(null);
let typingTimer = null;
let currentChannel = null;

watch(() => props.messages, (newMsgs) => {
    localMessages.value = [...newMsgs];
}, { deep: true });

watch(() => props.conversations, (newConvs) => {
    localConversations.value = [...newConvs];
}, { deep: true });

watch(() => props.activeConversation, (newActive) => {
    activeConversationRef.value = newActive ? { ...newActive } : null;
    joinReverbChannel();
}, { deep: true });

function joinReverbChannel() {
    if (!activeConversationRef.value) return;

    const convId = activeConversationRef.value.id;

    if (currentChannel) {
        window.Echo?.leave(`chat.${currentChannel}`);
    }

    currentChannel = convId;

    if (!window.Echo) {
        return;
    }

    window.Echo.join(`chat.${convId}`)
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
                }, 2500);
            } else {
                typingUser.value = null;
            }
        })
        .listen('.message.sent', (e) => {
            handleIncomingBroadcastMessage(e.message, e.conversation);
        })
        .listen('.conversation.updated', (e) => {
            handleIncomingConversationUpdate(e.conversation);
        });
}

function handleIncomingBroadcastMessage(message, conversationMeta) {
    const exists = localMessages.value.some(m => m.id === message.id);
    if (!exists) {
        localMessages.value.push(message);
    }

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
    if (activeConversationRef.value && activeConversationRef.value.id === updatedConv.id) {
        activeConversationRef.value.status = updatedConv.status;
        activeConversationRef.value.priority = updatedConv.priority;
        activeConversationRef.value.assigned_agent_id = updatedConv.assigned_agent_id;
        activeConversationRef.value.assigned_agent = updatedConv.assigned_agent;
        if (updatedConv.is_encrypted !== undefined) {
            activeConversationRef.value.is_encrypted = updatedConv.is_encrypted;
        }
    }

    const target = localConversations.value.find(c => c.id === updatedConv.id);
    if (target) {
        target.status = updatedConv.status;
        target.priority = updatedConv.priority;
        target.assigned_agent_id = updatedConv.assigned_agent_id;
        target.assigned_agent = updatedConv.assigned_agent;
        if (updatedConv.is_encrypted !== undefined) {
            target.is_encrypted = updatedConv.is_encrypted;
        }
    }
}

function broadcastTyping(isTyping) {
    if (!currentChannel || !window.Echo) return;
    window.Echo.join(`chat.${currentChannel}`)
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

// Start direct chat with user from Directory
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

// Send Message with all file types support
function handleSendMessage({ body, is_internal_note, files }) {
    if (!activeConversationRef.value) return;
    const convId = activeConversationRef.value.id;

    let finalBody = body;
    if (activeConversationRef.value.is_encrypted && !is_internal_note && finalBody && !finalBody.startsWith('🔐')) {
        finalBody = '🔐 ' + finalBody;
    }

    // Prepare payload
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
        // Fast optimistic append
        const tempMessage = {
            temp_id: `temp-${Date.now()}`,
            conversation_id: convId,
            sender_type: 'agent',
            sender_id: props.currentAgent?.id || 1,
            body: finalBody,
            is_internal_note: is_internal_note,
            is_read: true,
            attachments: null,
            created_at: new Date().toISOString(),
            sender: props.currentAgent,
        };
        localMessages.value.push(tempMessage);

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
    if (confirm(`Permanently delete conversation "${activeConversationRef.value.display_name}"?`)) {
        router.delete(`/conversations/${activeConversationRef.value.id}`);
    }
}

function handleSimulateCustomerReply() {
    if (!activeConversationRef.value) return;
    const convId = activeConversationRef.value.id;

    const sampleReplies = [
        "Received your message! Updating in real time.",
        "Could you check the file attachment I shared?",
        "Sub-15ms WebSocket connection holding steady.",
        "Everything looks good on our end. Thanks!",
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
    }, 1000);
}

onMounted(() => {
    joinReverbChannel();
});

onUnmounted(() => {
    if (currentChannel && window.Echo) {
        window.Echo.leave(`chat.${currentChannel}`);
    }
});
</script>

<template>
    <Head title="FluxDesk — Real-Time Agent Chat OS" />

    <div class="flex h-screen w-screen overflow-hidden bg-slate-950 font-sans text-slate-100">
        <!-- 1. LEFT SIDEBAR (Chats & Members Directory) -->
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
            @open-new-chat-modal="isNewChatModalOpen = true"
            @start-direct-chat-with-user="startDirectChatWithUser"
        />

        <!-- 2. CENTER CHAT WORKSPACE -->
        <main class="flex-1 flex flex-col h-full min-w-0 bg-slate-950 relative">
            <template v-if="activeConversationRef">
                <!-- Header Bar with Delete Chat Button -->
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
                    @open-add-member="isNewChatModalOpen = true"
                    @delete-chat="handleDeleteChat"
                />

                <!-- Message Thread Stream (With Images, Videos, Audio, Files, & Delete) -->
                <MessageThread
                    :messages="localMessages"
                    :conversation="activeConversationRef"
                    :current-agent="currentAgent"
                />

                <!-- Chat Input Box (With All File Types Attachment) -->
                <ChatInput
                    :canned-responses="cannedResponses"
                    @send-message="handleSendMessage"
                    @typing="broadcastTyping"
                />
            </template>

            <!-- Empty State -->
            <div 
                v-else 
                class="flex-1 flex flex-col items-center justify-center p-8 text-center text-slate-500"
            >
                <div class="w-14 h-14 rounded-2xl bg-slate-900 border border-slate-800 flex items-center justify-center mb-3 text-indigo-400 text-2xl shadow">
                    ⚡
                </div>
                <h3 class="text-base font-bold text-slate-200">Welcome to FluxDesk</h3>
                <p class="text-xs text-slate-400 mt-1 max-w-sm">
                    Select a conversation from the sidebar or click "New" to start a direct chat, make a group, or add team members.
                </p>
                <button
                    @click="isNewChatModalOpen = true"
                    class="mt-4 px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs shadow transition-all"
                >
                    + New Chat or Group
                </button>
            </div>
        </main>

        <!-- 3. RIGHT PROFILE & GROUP ADMIN PANEL -->
        <ProfilePanel
            :conversation="activeConversationRef"
            :agents="agents"
            :current-agent="currentAgent"
            :is-open="isProfileOpen"
            @update-conversation="handleUpdateConversation"
            @open-add-member="isNewChatModalOpen = true"
            @delete-chat="handleDeleteChat"
            @close="isProfileOpen = false"
        />

        <!-- 4. NEW CHAT / GROUP / MEMBER MODAL -->
        <NewChatModal
            :is-open="isNewChatModalOpen"
            :agents="agents"
            :active-conversation-id="activeConversationRef?.id"
            :current-agent="currentAgent"
            @close="isNewChatModalOpen = false"
        />
    </div>
</template>
