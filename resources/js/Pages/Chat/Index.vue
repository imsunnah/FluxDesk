<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import ChatSidebar from '@/Components/ChatSidebar.vue';
import ChatHeader from '@/Components/ChatHeader.vue';
import MessageThread from '@/Components/MessageThread.vue';
import ChatInput from '@/Components/ChatInput.vue';
import ProfilePanel from '@/Components/ProfilePanel.vue';

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

// Reactive state
const localMessages = ref([...props.messages]);
const localConversations = ref([...props.conversations]);
const activeConversationRef = ref(props.activeConversation ? { ...props.activeConversation } : null);
const searchQuery = ref(props.filters?.search || '');
const activeFilter = ref(props.filters?.filter || 'all');
const isProfileOpen = ref(true);

// Real-Time Reverb WebSockets State
const viewingAgents = ref([]);
const typingUser = ref(null);
let typingTimer = null;
let currentChannel = null;

// Synchronize props when Inertia updates
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

// Setup & Teardown Reverb Presence Channel
function joinReverbChannel() {
    if (!activeConversationRef.value) return;

    const convId = activeConversationRef.value.id;

    // Leave previous channel if switching conversations
    if (currentChannel) {
        window.Echo?.leave(`chat.${currentChannel}`);
    }

    currentChannel = convId;

    if (!window.Echo) {
        console.warn('FluxDesk: Echo is not initialized on window.');
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
    // Check if message is already in local list to avoid duplicates
    const exists = localMessages.value.some(m => m.id === message.id);
    if (!exists) {
        localMessages.value.push(message);
    }

    // Update conversation snippet in sidebar
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
    }

    const target = localConversations.value.find(c => c.id === updatedConv.id);
    if (target) {
        target.status = updatedConv.status;
        target.priority = updatedConv.priority;
        target.assigned_agent_id = updatedConv.assigned_agent_id;
        target.assigned_agent = updatedConv.assigned_agent;
    }
}

// Whisper typing event
function broadcastTyping(isTyping) {
    if (!currentChannel || !window.Echo) return;
    window.Echo.join(`chat.${currentChannel}`)
        .whisper('typing', {
            user: props.currentAgent?.name || 'Agent Alex',
            isTyping: isTyping,
        });
}

// Conversation Selection
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

// Filter Tabs Selection
function setFilter(filter) {
    activeFilter.value = filter;
}

// Send Message (Optimistic UI + Inertia asynchronous submission)
function handleSendMessage({ body, is_internal_note, attachments }) {
    if (!activeConversationRef.value) return;

    const convId = activeConversationRef.value.id;

    // Optimistic message append
    const tempMessage = {
        temp_id: `temp-${Date.now()}`,
        conversation_id: convId,
        sender_type: 'agent',
        sender_id: props.currentAgent?.id || 1,
        body: body,
        is_internal_note: is_internal_note,
        is_read: true,
        attachments: attachments,
        created_at: new Date().toISOString(),
        sender: props.currentAgent,
    };

    localMessages.value.push(tempMessage);

    // Inertia form submission with preserveScroll
    router.post(`/conversations/${convId}/messages`, {
        body,
        is_internal_note,
        sender_type: 'agent',
        attachments,
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            // Updated messages will flow in via props or broadcast
        },
    });
}

// Update Conversation Status / Priority / Agent
function handleUpdateConversation(data) {
    if (!activeConversationRef.value) return;
    const convId = activeConversationRef.value.id;

    // Optimistically update
    Object.assign(activeConversationRef.value, data);

    router.patch(`/conversations/${convId}`, data, {
        preserveScroll: true,
        preserveState: true,
    });
}

// Simulate Customer Reply
function handleSimulateCustomerReply() {
    if (!activeConversationRef.value) return;
    const convId = activeConversationRef.value.id;

    const sampleReplies = [
        "That solved our Reverb WebSocket disconnect! Ping dropped to 12ms. Thank you Alex!",
        "Could you also send over the updated API docs for the presence channel auth token?",
        "Testing the live chat response! Everything is updating in real time across the board.",
        "We are ready to deploy to production this afternoon. Thanks for the quick support!",
    ];

    const randomBody = sampleReplies[Math.floor(Math.random() * sampleReplies.length)];

    // Show simulated typing first for 1.2s
    typingUser.value = activeConversationRef.value.customer_name;
    setTimeout(() => {
        typingUser.value = null;
        router.post(`/conversations/${convId}/simulate-customer`, {
            body: randomBody,
        }, {
            preserveScroll: true,
            preserveState: true,
        });
    }, 1200);
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
        <!-- 1. LEFT CHAT LIST PANEL (Sidebar) -->
        <ChatSidebar
            :conversations="localConversations"
            :active-conversation-id="activeConversationRef?.id"
            :active-filter="activeFilter"
            :search-query="searchQuery"
            :current-agent="currentAgent"
            @select-conversation="selectConversation"
            @set-filter="setFilter"
            @update:search-query="searchQuery = $event"
        />

        <!-- 2. CENTER CHAT SCREEN (Main Workspace) -->
        <main class="flex-1 flex flex-col h-full min-w-0 bg-slate-950 relative">
            <template v-if="activeConversationRef">
                <!-- Header Bar -->
                <ChatHeader
                    :conversation="activeConversationRef"
                    :active-agents-count="viewingAgents.length || 1"
                    :viewing-agents="viewingAgents"
                    :typing-user="typingUser"
                    :is-profile-open="isProfileOpen"
                    @toggle-profile="isProfileOpen = !isProfileOpen"
                    @change-status="handleUpdateConversation({ status: $event })"
                    @simulate-customer-reply="handleSimulateCustomerReply"
                />

                <!-- Message Thread Stream (Customer Bubbles, Agent Bubbles, Amber Internal Notes) -->
                <MessageThread
                    :messages="localMessages"
                    :conversation="activeConversationRef"
                    :current-agent="currentAgent"
                />

                <!-- Modern Chat Input Box (Pill-shaped, Public/Internal Toggle, Slash commands, Attachments) -->
                <ChatInput
                    :canned-responses="cannedResponses"
                    @send-message="handleSendMessage"
                    @typing="broadcastTyping"
                />
            </template>

            <!-- No conversation selected state -->
            <div 
                v-else 
                class="flex-1 flex flex-col items-center justify-center p-8 text-center text-slate-500"
            >
                <div class="w-16 h-16 rounded-2xl bg-slate-900 border border-slate-800 flex items-center justify-center mb-4 text-indigo-400 text-2xl shadow-xl">
                    ⚡
                </div>
                <h3 class="text-base font-bold text-slate-200">Welcome to FluxDesk</h3>
                <p class="text-xs text-slate-400 mt-1.5 max-w-sm">
                    Select a conversation from the left inbox sidebar to begin collaborating in real time with Reverb WebSockets.
                </p>
            </div>
        </main>

        <!-- 3. RIGHT PROFILE / CHANNEL INFO PANEL (Collapsible) -->
        <ProfilePanel
            :conversation="activeConversationRef"
            :agents="agents"
            :is-open="isProfileOpen"
            @update-conversation="handleUpdateConversation"
            @close="isProfileOpen = false"
        />
    </div>
</template>
