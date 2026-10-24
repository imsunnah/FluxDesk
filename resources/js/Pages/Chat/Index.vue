<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { 
    Ticket, 
    MessageSquare, 
    Users, 
    Plus, 
    Shield, 
    Handshake, 
    UserCheck, 
    Trash2, 
    Mail, 
    Clock, 
    AlertCircle 
} from 'lucide-vue-next';
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
    staffAgents: {
        type: Array,
        default: () => [],
    },
    currentAgent: {
        type: Object,
        default: () => ({}),
    },
    initialView: {
        type: String,
        default: 'tickets',
    },
    filters: {
        type: Object,
        default: () => ({ filter: 'all', search: '', view: 'tickets' }),
    },
    reverb: {
        type: Object,
        default: () => ({}),
    },
});

const currentView = ref(props.initialView || props.filters?.view || 'tickets');
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

const isTicketView = computed(() => currentView.value === 'tickets');
const isChatView = computed(() => currentView.value === 'chats');
const isMembersView = computed(() => currentView.value === 'members');

const isCustomer = computed(() => !!props.currentAgent?.is_customer);
const isAdmin = computed(() => !!props.currentAgent?.is_admin);

const activeIsTicket = computed(() => {
    return !!activeConversationRef.value && (activeConversationRef.value.type === 'ticket' || !!activeConversationRef.value.ticket_number);
});

const activeIsChat = computed(() => {
    return !!activeConversationRef.value && activeConversationRef.value.type !== 'ticket' && !activeConversationRef.value.ticket_number;
});

const ticketsList = computed(() => {
    return localConversations.value.filter(c => c.type === 'ticket' || !!c.ticket_number);
});

const regularChatsList = computed(() => {
    return localConversations.value.filter(c => c.type !== 'ticket' && !c.ticket_number);
});

const staffDirectory = computed(() => {
    return props.agents.filter(u => u.is_admin || u.role !== 'customer');
});

const customerDirectory = computed(() => {
    return props.agents.filter(u => u.role === 'customer');
});

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

watch(() => props.initialView, (newView) => {
    if (newView && newView !== currentView.value) {
        currentView.value = newView;
    }
});

// Setup Real-Time WebSockets
function joinChannels() {
    if (!activeConversationRef.value) return;

    const convId = activeConversationRef.value.id;

    if (currentConvId && window.Echo) {
        window.Echo.leave(`chat.${currentConvId}`);
        window.Echo.leave(`chat.presence.${currentConvId}`);
    }

    currentConvId = convId;

    if (!window.Echo) return;

    window.Echo.channel(`chat.${convId}`)
        .listen('.message.sent', (e) => {
            handleIncomingBroadcastMessage(e.message, e.conversation);
        })
        .listen('.conversation.updated', (e) => {
            handleIncomingConversationUpdate(e.conversation);
        });

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

    const existingIndex = localMessages.value.findIndex(m => 
        m.id === message.id || 
        (m.temp_id && m.body === message.body && m.sender_id === message.sender_id)
    );

    if (existingIndex > -1) {
        localMessages.value[existingIndex] = message;
    } else {
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

function handleViewChange(newView) {
    currentView.value = newView;

    if (newView === 'tickets') {
        if (!activeIsTicket.value) {
            const firstTicket = ticketsList.value[0];
            if (firstTicket) {
                selectConversation(firstTicket.id, 'tickets');
            } else {
                activeConversationRef.value = null;
                localMessages.value = [];
            }
        }
    } else if (newView === 'chats') {
        if (!activeIsChat.value) {
            const firstChat = regularChatsList.value[0];
            if (firstChat) {
                selectConversation(firstChat.id, 'chats');
            } else {
                activeConversationRef.value = null;
                localMessages.value = [];
            }
        }
    } else if (newView === 'members') {
        activeConversationRef.value = null;
    }
}

function selectConversation(id, view = currentView.value) {
    currentView.value = view;
    router.get('/', {
        conversation_id: id,
        view: view,
        filter: activeFilter.value,
        search: searchQuery.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        only: ['activeConversation', 'messages', 'filters', 'initialView'],
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
    currentView.value = 'chats';
    const existing = localConversations.value.find(c => 
        c.type === 'direct' && 
        (c.customer_email === user.email || c.members?.some(m => m.id === user.id))
    );

    if (existing) {
        selectConversation(existing.id, 'chats');
    } else {
        router.post('/conversations/direct', {
            target_user_id: user.id,
        });
    }
}

function toggleCustomerAccess(user) {
    router.post(`/users/${user.id}/toggle-customer-access`, {}, {
        preserveScroll: true,
        preserveState: true,
    });
}

function updateCustomerAgent(customer, agentId) {
    router.post(`/users/${customer.id}/assign-agent`, {
        assigned_agent_id: agentId ? Number(agentId) : null,
    }, {
        preserveScroll: true,
        preserveState: true,
    });
}

function deleteMember(user) {
    if (confirm(`Remove member ${user.name}?`)) {
        router.delete(`/members/${user.id}`);
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

function formatDate(isoString) {
    if (!isoString) return '';
    const date = new Date(isoString);
    return date.toLocaleDateString([], { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

function statusBadge(status) {
    switch (status) {
        case 'open':
            return 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30';
        case 'in_progress':
            return 'bg-sky-500/20 text-sky-400 border border-sky-500/30';
        case 'pending':
            return 'bg-amber-500/20 text-amber-400 border border-amber-500/30';
        case 'resolved':
        case 'closed':
            return 'bg-slate-700/50 text-slate-300 border border-slate-600/30';
        default:
            return 'bg-slate-800 text-slate-400';
    }
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
            :staff-agents="staffAgents"
            :active-view="currentView"
            @select-conversation="selectConversation"
            @change-view="handleViewChange"
            @update:active-view="handleViewChange"
            @set-filter="setFilter"
            @update:search-query="searchQuery = $event"
            @open-new-chat-modal="openModalWithTab($event)"
            @start-direct-chat-with-user="startDirectChatWithUser"
        />

        <!-- 2. CENTER WORKSPACE -->
        <main class="flex-1 flex flex-col h-full min-w-0 bg-slate-950 relative">
            <!-- ========================================================= -->
            <!-- VIEW A: TICKET WORKSPACE (STRICTLY TICKETS - NO CHAT)    -->
            <!-- ========================================================= -->
            <template v-if="isTicketView">
                <template v-if="activeConversationRef && activeIsTicket">
                    <!-- Dedicated Ticket Header -->
                    <ChatHeader
                        :conversation="activeConversationRef"
                        :active-agents-count="viewingAgents.length || 1"
                        :viewing-agents="viewingAgents"
                        :typing-user="typingUser"
                        :is-profile-open="isProfileOpen"
                        :current-agent="currentAgent"
                        :active-view="'tickets'"
                        @toggle-profile="isProfileOpen = !isProfileOpen"
                        @change-status="handleUpdateConversation({ status: $event })"
                    />

                    <!-- Ticket Problem / Issue Card -->
                    <div class="mx-4 mt-3 p-3.5 rounded-2xl bg-slate-900 border border-indigo-500/30 shadow-md shrink-0">
                        <div class="flex items-center justify-between pb-2 border-b border-slate-800">
                            <div class="flex items-center space-x-2">
                                <span class="px-2.5 py-0.5 rounded-lg bg-indigo-500/20 text-indigo-300 font-mono text-xs font-bold border border-indigo-500/30">
                                    {{ activeConversationRef.ticket_number || '#TK-' + activeConversationRef.id }}
                                </span>
                                <span :class="['px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider', statusBadge(activeConversationRef.status)]">
                                    {{ activeConversationRef.status }}
                                </span>
                                <span class="text-xs text-slate-400">
                                    Priority: <strong class="text-slate-200 capitalize">{{ activeConversationRef.priority || 'medium' }}</strong>
                                </span>
                            </div>
                            <div class="text-[11px] text-slate-400 font-mono">
                                Created: {{ formatDate(activeConversationRef.created_at) }}
                            </div>
                        </div>

                        <div class="pt-2.5">
                            <h4 class="text-xs font-bold text-slate-300 uppercase tracking-wider mb-1">
                                Customer Problem Description:
                            </h4>
                            <div class="p-3 rounded-xl bg-slate-950 border border-slate-800 text-xs text-slate-200 leading-relaxed whitespace-pre-wrap font-sans">
                                {{ activeConversationRef.ticket_description || 'No detailed problem description provided.' }}
                            </div>
                        </div>

                        <div class="mt-2.5 pt-2 border-t border-slate-800/80 flex items-center justify-between text-xs text-slate-400">
                            <div class="flex items-center space-x-1.5">
                                <span class="text-slate-500">Requester:</span>
                                <span class="text-slate-200 font-semibold">{{ activeConversationRef.customer_name }}</span>
                                <span class="text-slate-500 font-mono text-[11px]">({{ activeConversationRef.customer_email }})</span>
                            </div>
                            <div class="flex items-center space-x-1.5">
                                <span class="text-slate-500">Assigned Agent:</span>
                                <span class="text-indigo-300 font-semibold">
                                    {{ activeConversationRef.assigned_agent?.name || 'Unassigned (Pool)' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Ticket Resolution Thread -->
                    <MessageThread
                        :messages="localMessages"
                        :conversation="activeConversationRef"
                        :current-agent="currentAgent"
                    />

                    <!-- Ticket Reply Composer -->
                    <ChatInput
                        :canned-responses="cannedResponses"
                        @send-message="handleSendMessage"
                        @typing="broadcastTyping"
                    />
                </template>

                <!-- Ticket Empty Queue State -->
                <div 
                    v-else 
                    class="flex-1 flex flex-col items-center justify-center p-8 text-center text-slate-500"
                >
                    <div class="w-16 h-16 rounded-2xl bg-indigo-950/40 border border-indigo-500/30 flex items-center justify-center mb-3 text-indigo-400 text-3xl shadow">
                        <Ticket class="w-8 h-8 text-indigo-400" />
                    </div>
                    <h3 class="text-base font-bold text-slate-100">Ticket Support Desk</h3>
                    <p class="text-xs text-slate-400 mt-1 max-w-sm">
                        Select a ticket from the sidebar queue to inspect issue details, claim ownership, or post resolution replies.
                    </p>
                    <div class="flex items-center space-x-2 mt-4" v-if="currentAgent?.is_customer">
                        <button
                            @click="openModalWithTab('ticket')"
                            class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs shadow transition-all flex items-center space-x-1"
                        >
                            <Plus class="w-3.5 h-3.5" />
                            <span>Create New Ticket</span>
                        </button>
                    </div>
                </div>
            </template>

            <!-- ========================================================= -->
            <!-- VIEW B: CHAT WORKSPACE (STRICTLY CHATS - NO TICKETS)     -->
            <!-- ========================================================= -->
            <template v-else-if="isChatView">
                <template v-if="activeConversationRef && activeIsChat">
                    <!-- Dedicated Chat Header (No ticket elements) -->
                    <ChatHeader
                        :conversation="activeConversationRef"
                        :active-agents-count="viewingAgents.length || 1"
                        :viewing-agents="viewingAgents"
                        :typing-user="typingUser"
                        :is-profile-open="isProfileOpen"
                        :current-agent="currentAgent"
                        :active-view="'chats'"
                        @toggle-profile="isProfileOpen = !isProfileOpen"
                        @open-add-member="openModalWithTab('member')"
                        @delete-chat="handleDeleteChat"
                    />

                    <!-- Message Thread Stream (No ticket cards) -->
                    <MessageThread
                        :messages="localMessages"
                        :conversation="activeConversationRef"
                        :current-agent="currentAgent"
                    />

                    <!-- Chat Input Bar -->
                    <ChatInput
                        :canned-responses="cannedResponses"
                        @send-message="handleSendMessage"
                        @typing="broadcastTyping"
                    />
                </template>

                <!-- Chat Empty State -->
                <div 
                    v-else 
                    class="flex-1 flex flex-col items-center justify-center p-8 text-center text-slate-500"
                >
                    <div class="w-16 h-16 rounded-2xl bg-indigo-950/40 border border-indigo-500/30 flex items-center justify-center mb-3 text-indigo-400 text-3xl shadow">
                        <MessageSquare class="w-8 h-8 text-indigo-400" />
                    </div>
                    <h3 class="text-base font-bold text-slate-100">Direct & Group Messaging</h3>
                    <p class="text-xs text-slate-400 mt-1 max-w-sm">
                        Select a contact or group channel from the sidebar to start live chatting.
                    </p>
                    <div class="flex items-center space-x-2 mt-4">
                        <button
                            @click="openModalWithTab('single')"
                            class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs shadow transition-all flex items-center space-x-1"
                        >
                            <Plus class="w-3.5 h-3.5" />
                            <span>Start Direct Chat</span>
                        </button>
                        <button
                            v-if="!isCustomer"
                            @click="openModalWithTab('group')"
                            class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 font-semibold text-xs border border-slate-700 transition-all flex items-center space-x-1"
                        >
                            <Users class="w-3.5 h-3.5" />
                            <span>Create Group</span>
                        </button>
                    </div>
                </div>
            </template>

            <!-- ========================================================= -->
            <!-- VIEW C: MEMBERS / USERS DIRECTORY (Staff only)           -->
            <!-- ========================================================= -->
            <template v-else-if="isMembersView">
                <div class="flex-1 flex flex-col h-full overflow-y-auto p-6 bg-slate-950 space-y-6">
                    <div class="flex items-center justify-between pb-4 border-b border-slate-800">
                        <div>
                            <h2 class="text-lg font-bold text-slate-100 flex items-center space-x-2">
                                <Users class="w-5 h-5 text-indigo-400" />
                                <span>Users & Team Directory</span>
                            </h2>
                            <p class="text-xs text-slate-400 mt-0.5">
                                Manage staff agents, customer accounts, and 1-on-1 direct conversations.
                            </p>
                        </div>
                        <button
                            v-if="isAdmin"
                            @click="openModalWithTab('member')"
                            class="px-3.5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold shadow flex items-center space-x-1.5 transition-all"
                        >
                            <Plus class="w-3.5 h-3.5" />
                            <span>Add User / Member</span>
                        </button>
                    </div>

                    <!-- Staff Section -->
                    <div class="space-y-3">
                        <div class="text-xs font-bold uppercase tracking-wider text-indigo-400 flex items-center space-x-2">
                            <span>Staff & Support Team ({{ staffDirectory.length }})</span>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                            <div
                                v-for="user in staffDirectory"
                                :key="user.id"
                                class="p-3.5 rounded-2xl bg-slate-900 border border-slate-800 space-y-3 shadow-sm hover:border-slate-700 transition-all"
                            >
                                <div class="flex items-center space-x-3">
                                    <img :src="user.avatar" class="w-10 h-10 rounded-full object-cover ring-2 ring-indigo-500/30" />
                                    <div class="min-w-0 flex-1">
                                        <div class="font-bold text-xs text-slate-100 truncate flex items-center space-x-1.5">
                                            <span>{{ user.name }}</span>
                                            <span v-if="user.is_admin" class="px-1.5 py-0.2 rounded bg-amber-500/20 text-amber-300 text-[9px] font-bold">Admin</span>
                                            <span v-if="user.is_customer" class="px-1.5 py-0.2 rounded bg-purple-500/20 text-purple-300 text-[9px] font-bold" title="Can also create tickets">Dual Role</span>
                                        </div>
                                        <p class="text-[11px] text-slate-400 truncate">{{ user.email }}</p>
                                        <p class="text-[10px] text-slate-500 font-mono">{{ user.member_id || 'ID: ' + user.id }} &bull; Staff</p>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between pt-2 border-t border-slate-800/80 text-xs">
                                    <button
                                        v-if="isAdmin"
                                        @click="toggleCustomerAccess(user)"
                                        :class="[
                                            'px-2 py-1 rounded-lg text-[10px] font-semibold border transition-all',
                                            user.is_customer 
                                                ? 'bg-purple-500/20 border-purple-500/40 text-purple-300 hover:bg-purple-500/30' 
                                                : 'bg-slate-950 border-slate-700 text-slate-400 hover:text-slate-200'
                                        ]"
                                        :title="user.is_customer ? 'Dual Role: Can create tickets. Click to remove.' : 'Click to grant customer ticket capability.'"
                                    >
                                        {{ user.is_customer ? 'Ticket: Enabled' : 'Ticket: Disabled' }}
                                    </button>

                                    <button
                                        @click="startDirectChatWithUser(user)"
                                        class="px-3 py-1 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-[11px] font-semibold flex items-center space-x-1 transition-all"
                                    >
                                        <MessageSquare class="w-3 h-3" />
                                        <span>Message</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Customers Section -->
                    <div class="space-y-3 pt-4 border-t border-slate-800">
                        <div class="text-xs font-bold uppercase tracking-wider text-emerald-400 flex items-center space-x-2">
                            <span>Customers Directory ({{ customerDirectory.length }})</span>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                            <div
                                v-for="cust in customerDirectory"
                                :key="cust.id"
                                class="p-3.5 rounded-2xl bg-slate-900 border border-slate-800 space-y-3 shadow-sm hover:border-slate-700 transition-all"
                            >
                                <div class="flex items-center space-x-3">
                                    <img :src="cust.avatar" class="w-10 h-10 rounded-full object-cover ring-2 ring-emerald-500/30" />
                                    <div class="min-w-0 flex-1">
                                        <div class="font-bold text-xs text-slate-100 truncate">
                                            {{ cust.name }}
                                        </div>
                                        <p class="text-[11px] text-slate-400 truncate">{{ cust.email }}</p>
                                        <p class="text-[10px] text-slate-500 font-mono">{{ cust.member_id }} &bull; Customer</p>
                                    </div>
                                </div>

                                <div class="pt-2 border-t border-slate-800/80 flex items-center justify-between text-xs">
                                    <div class="text-[11px]">
                                        <span class="text-slate-500">Assigned: </span>
                                        <select
                                            v-if="isAdmin"
                                            :value="cust.assigned_agent_id || ''"
                                            @change="updateCustomerAgent(cust, $event.target.value)"
                                            class="bg-slate-950 border border-slate-700 rounded px-1.5 py-0.5 text-[10px] text-indigo-300 focus:outline-none"
                                        >
                                            <option value="">None</option>
                                            <option v-for="s in staffDirectory" :key="s.id" :value="s.id">{{ s.name }}</option>
                                        </select>
                                        <span v-else class="text-indigo-300 font-medium">{{ cust.assigned_agent?.name || 'Unassigned' }}</span>
                                    </div>

                                    <button
                                        @click="startDirectChatWithUser(cust)"
                                        class="px-3 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-[11px] font-semibold flex items-center space-x-1 transition-all"
                                    >
                                        <MessageSquare class="w-3 h-3" />
                                        <span>Message</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </main>

        <!-- 3. RIGHT DETAILS & TICKET UPDATER PANEL -->
        <ProfilePanel
            v-if="!isMembersView && activeConversationRef"
            :conversation="activeConversationRef"
            :agents="agents"
            :staff-agents="staffAgents"
            :current-agent="currentAgent"
            :active-view="currentView"
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
            :staff-agents="staffAgents"
            :active-conversation-id="activeConversationRef?.id"
            :current-agent="currentAgent"
            :default-tab="activeModalTab"
            @close="isNewChatModalOpen = false"
        />
    </div>
</template>
