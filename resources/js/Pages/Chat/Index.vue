<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { 
    CheckSquare,
    Ticket, 
    MessageSquare, 
    Plus, 
    Users,
    Search, 
    LogOut,
    CheckCircle2,
    ShieldCheck,
    UserCheck,
    Columns,
    MessageCircle,
    User,
    Sparkles,
    Building2,
    UserCircle2
} from 'lucide-vue-next';
import WorkStatusDashboard from '@/Components/WorkStatusDashboard.vue';
import TicketDashboard from '@/Components/TicketDashboard.vue';
import AdminOverviewDashboard from '@/Components/AdminOverviewDashboard.vue';
import ChatHeader from '@/Components/ChatHeader.vue';
import MessageThread from '@/Components/MessageThread.vue';
import ChatInput from '@/Components/ChatInput.vue';
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
    tasks: {
        type: Array,
        default: () => [],
    },
    projects: {
        type: Array,
        default: () => [],
    },
    departments: {
        type: Array,
        default: () => [],
    },
    designations: {
        type: Array,
        default: () => [],
    },
    clients: {
        type: Array,
        default: () => [],
    },
    clientAssignments: {
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
        default: 'work_status',
    },
    filters: {
        type: Object,
        default: () => ({ filter: 'all', search: '', view: 'work_status' }),
    },
    reverb: {
        type: Object,
        default: () => ({}),
    },
});

const isCustomer = computed(() => !!props.currentAgent?.is_customer);
const isAdmin = computed(() => !!props.currentAgent?.is_admin);

// Main modules: 'work_status' | 'tickets' | 'chat' | 'admin'
const currentTab = ref(
    props.filters?.view === 'admin' && isAdmin.value ? 'admin' :
    (props.filters?.view === 'chat' || props.filters?.view === 'chats') ? 'chat' :
    isCustomer.value || props.filters?.view === 'tickets' ? 'tickets' :
    'work_status'
);

const localMessages = ref([...props.messages]);
const localConversations = ref([...props.conversations]);
const activeConversationRef = ref(props.activeConversation ? { ...props.activeConversation } : null);

const isNewChatModalOpen = ref(false);
const activeModalTab = ref('ticket');
const chatSearch = ref('');

const viewingAgents = ref([]);
const typingUser = ref(null);
let typingTimer = null;
let currentConvId = null;

// Counts
const openTicketsCount = computed(() => {
    return localConversations.value.filter(c => c.type === 'ticket' && c.status === 'open').length;
});

const unreadChatCount = computed(() => {
    return localConversations.value
        .filter(c => c.type !== 'ticket')
        .reduce((sum, c) => sum + (c.unread_count || 0), 0);
});

// Chat Filter List
const filteredChatList = computed(() => {
    return localConversations.value.filter(c => {
        if (c.type === 'ticket') return false;
        if (chatSearch.value) {
            const q = chatSearch.value.toLowerCase();
            const nameMatch = (c.display_name || c.customer_name || c.group_name || '').toLowerCase().includes(q);
            const msgMatch = (c.latest_message?.body || '').toLowerCase().includes(q);
            if (!nameMatch && !msgMatch) return false;
        }
        return true;
    });
});

// Chat Panel View Mode: 'split' (shows both User List and Chat List) | 'chats' | 'users'
const chatPanelMode = ref('split');
const userSearch = ref('');
const userFilterRole = ref('all'); // 'all' | 'staff' | 'client'

const displayUserList = computed(() => {
    return props.agents.filter(u => {
        // Exclude current logged in user from directory
        if (u.id === props.currentAgent?.id) return false;

        // Role filter for staff/admin
        if (!isCustomer.value && userFilterRole.value !== 'all') {
            if (userFilterRole.value === 'client' && !u.is_customer) return false;
            if (userFilterRole.value === 'staff' && u.is_customer) return false;
        }

        // Search query
        if (userSearch.value.trim()) {
            const q = userSearch.value.toLowerCase();
            const nameMatch = (u.name || '').toLowerCase().includes(q);
            const emailMatch = (u.email || '').toLowerCase().includes(q);
            const deptMatch = (u.department || '').toLowerCase().includes(q);
            const desigMatch = (u.designation || '').toLowerCase().includes(q);
            const memberIdMatch = (u.member_id || '').toLowerCase().includes(q);
            if (!nameMatch && !emailMatch && !deptMatch && !desigMatch && !memberIdMatch) return false;
        }
        return true;
    });
});

const staffCount = computed(() => props.agents.filter(u => !u.is_customer && u.id !== props.currentAgent?.id).length);
const clientCount = computed(() => props.agents.filter(u => u.is_customer && u.id !== props.currentAgent?.id).length);

function getConversationPartner(conv) {
    if (!conv || conv.type !== 'direct') return null;
    if (conv.members && conv.members.length > 0) {
        return conv.members.find(m => m.id !== props.currentAgent?.id) || null;
    }
    return null;
}

function getConversationName(conv) {
    if (!conv) return '';
    if (conv.type === 'ticket') {
        return (conv.ticket_number ? `[${conv.ticket_number}] ` : '') + (conv.ticket_subject || conv.customer_name);
    }
    if (conv.type === 'group') {
        return conv.group_name || 'Group Room';
    }
    const partner = getConversationPartner(conv);
    if (partner) {
        return partner.name;
    }
    return conv.display_name || conv.customer_name || 'Direct Chat';
}

function getConversationAvatar(conv) {
    if (!conv) return 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=80&h=80&fit=crop';
    if (conv.type === 'group') {
        return conv.customer_avatar || 'https://images.unsplash.com/photo-1522071820081-009f0129c71c?w=80&h=80&fit=crop';
    }
    const partner = getConversationPartner(conv);
    if (partner && partner.avatar) {
        return partner.avatar;
    }
    return conv.customer_avatar || 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=80&h=80&fit=crop';
}

function startDirectChatWithUser(user) {
    if (!user) return;

    // 1. Check if a direct conversation with this user is already in localConversations
    const existing = localConversations.value.find(c => {
        if (c.type === 'ticket') return false;
        if (c.customer_email && c.customer_email.toLowerCase() === user.email.toLowerCase()) return true;
        if (c.members && c.members.some(m => m.id === user.id)) return true;
        return false;
    });

    if (existing) {
        selectConversation(existing);
        return;
    }

    // 2. Otherwise create/open direct conversation on the backend
    router.post('/conversations/direct', {
        target_user_id: user.id,
        name: user.name,
        email: user.email,
    }, {
        preserveScroll: true,
        preserveState: true,
    });
}

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

function switchMainTab(tab) {
    if (isCustomer.value && (tab === 'work_status' || tab === 'admin')) {
        tab = 'tickets';
    }
    currentTab.value = tab;
    const url = new URL(window.location.href);
    url.searchParams.set('view', tab);
    window.history.replaceState({}, '', url);

    // If switching to chat and no chat is active, pick first chat if available
    if (tab === 'chat' && (!activeConversationRef.value || activeConversationRef.value.type === 'ticket')) {
        const firstChat = filteredChatList.value[0];
        if (firstChat) {
            selectConversation(firstChat);
        } else {
            activeConversationRef.value = null;
        }
    }
}

function selectConversation(conv) {
    if (!conv) {
        activeConversationRef.value = null;
        localMessages.value = [];
        return;
    }
    activeConversationRef.value = { ...conv };
    router.get('/', {
        conversation_id: conv.id,
        view: currentTab.value,
    }, {
        preserveState: true,
        preserveScroll: true,
        only: ['activeConversation', 'messages'],
    });
}

function openModalWithTab(tab) {
    activeModalTab.value = tab;
    isNewChatModalOpen.value = true;
}

function handleSendMessage({ body, files, tempId }) {
    if (!activeConversationRef.value) return;
    const convId = activeConversationRef.value.id;
    const finalBody = body?.trim() || (files?.length ? 'Attached file(s)' : '');

    const tempMsg = {
        id: tempId || Date.now(),
        temp_id: tempId,
        conversation_id: convId,
        sender_type: 'agent',
        sender_id: props.currentAgent.id,
        sender: props.currentAgent,
        body: finalBody,
        is_internal_note: false,
        created_at: new Date().toISOString(),
        attachments: files ? files.map(f => ({
            name: f.name,
            size: f.size,
            type: f.type,
            url: URL.createObjectURL(f),
            is_image: f.type?.startsWith('image/'),
        })) : [],
    };
    localMessages.value.push(tempMsg);

    if (files && files.length > 0) {
        const formData = new FormData();
        formData.append('body', finalBody || '');
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
    const name = activeConversationRef.value.display_name || activeConversationRef.value.customer_name;
    if (confirm(`Delete conversation "${name}"?`)) {
        router.delete(`/conversations/${activeConversationRef.value.id}`);
        activeConversationRef.value = null;
    }
}

function switchToDirectChat(ticket) {
    if (!ticket) return;
    const matchingChat = localConversations.value.find(c => 
        c.type !== 'ticket' && (c.customer_email === ticket.customer_email || c.customer_name === ticket.customer_name)
    );
    if (matchingChat) {
        selectConversation(matchingChat);
        switchMainTab('chat');
    } else {
        router.post('/conversations/direct', {
            name: ticket.customer_name,
            email: ticket.customer_email,
            initial_message: `Hi ${ticket.customer_name}, following up regarding ticket ${ticket.ticket_number || ''}`,
        }, {
            onSuccess: () => {
                switchMainTab('chat');
            }
        });
    }
}

function logout() {
    router.post('/logout');
}

function formatTime(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
}

// WebSocket Listeners
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
            isTyping,
            user: props.currentAgent.name,
        });
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
    <Head title="FluxDesk — Work Status, Tickets & Realtime Chat" />

    <div class="flex flex-col h-screen w-screen overflow-hidden bg-slate-50 font-sans text-slate-800">
        <!-- ============================================================= -->
        <!-- TOP GLOBAL HEADER WITH 3 MAIN MIDDLE TABS                    -->
        <!-- ============================================================= -->
        <header class="h-16 px-6 bg-white border-b border-slate-200 flex items-center justify-between shrink-0 z-20">
            <!-- Left: Logo & Brand -->
            <div class="flex items-center space-x-3">
                <div class="w-9 h-9 rounded-xl bg-indigo-600 text-white flex items-center justify-center font-bold text-base shadow-xs">
                    FD
                </div>
                <div>
                    <span class="text-sm font-bold text-slate-900 tracking-tight">FluxDesk</span>
                    <span class="ml-2 px-2 py-0.5 rounded-full text-[10px] font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200">
                        Workspace
                    </span>
                </div>
            </div>

            <!-- Middle: 3 Main Module Tabs (Work Status, Tickets, Chat) -->
            <div class="flex items-center p-1 bg-slate-100 rounded-xl border border-slate-200/90 text-xs font-semibold">
                <!-- Tab 1: Work Status (Task Management) - ONLY FOR STAFF, NEVER FOR CUSTOMERS -->
                <button
                    v-if="!isCustomer"
                    @click="switchMainTab('work_status')"
                    :class="[
                        'px-4 py-1.5 rounded-lg flex items-center space-x-2 transition-all',
                        currentTab === 'work_status' ? 'bg-white text-indigo-700 font-bold shadow-xs' : 'text-slate-600 hover:text-slate-900'
                    ]"
                >
                    <CheckSquare class="w-4 h-4 text-indigo-600" />
                    <span>Work Status</span>
                    <span class="px-1.5 py-0.2 rounded-full bg-slate-200/80 text-slate-700 text-[10px] font-mono">
                        {{ tasks.length }}
                    </span>
                </button>

                <!-- Tab 2: Ticket Management -->
                <button
                    @click="switchMainTab('tickets')"
                    :class="[
                        'px-4 py-1.5 rounded-lg flex items-center space-x-2 transition-all',
                        currentTab === 'tickets' ? 'bg-white text-indigo-700 font-bold shadow-xs' : 'text-slate-600 hover:text-slate-900'
                    ]"
                >
                    <Ticket class="w-4 h-4 text-indigo-600" />
                    <span>Tickets</span>
                    <span v-if="openTicketsCount > 0" class="px-1.5 py-0.2 rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-mono font-bold">
                        {{ openTicketsCount }}
                    </span>
                </button>

                <!-- Tab 3: Realtime Chatting -->
                <button
                    @click="switchMainTab('chat')"
                    :class="[
                        'px-4 py-1.5 rounded-lg flex items-center space-x-2 transition-all',
                        currentTab === 'chat' ? 'bg-white text-indigo-700 font-bold shadow-xs' : 'text-slate-600 hover:text-slate-900'
                    ]"
                >
                    <MessageSquare class="w-4 h-4 text-indigo-600" />
                    <span>Realtime Chat</span>
                    <span v-if="unreadChatCount > 0" class="px-1.5 py-0.2 rounded-full bg-indigo-600 text-white text-[10px] font-mono font-bold">
                        {{ unreadChatCount }}
                    </span>
                </button>

                <!-- Tab 4: Admin Hub & Setup (Admin Only) -->
                <button
                    v-if="isAdmin"
                    @click="switchMainTab('admin')"
                    :class="[
                        'px-4 py-1.5 rounded-lg flex items-center space-x-2 transition-all',
                        currentTab === 'admin' ? 'bg-white text-indigo-700 font-bold shadow-xs' : 'text-slate-600 hover:text-slate-900'
                    ]"
                >
                    <ShieldCheck class="w-4 h-4 text-indigo-600" />
                    <span>Admin Setup</span>
                    <span class="px-1.5 py-0.2 rounded-full bg-amber-100 text-amber-800 text-[10px] font-mono font-bold">
                        Admin
                    </span>
                </button>
            </div>

            <!-- Right: Logged-in User Profile & Logout -->
            <div class="flex items-center space-x-3">
                <div class="flex items-center space-x-2.5">
                    <img
                        :src="currentAgent.avatar || 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=80&h=80&fit=crop'"
                        class="w-8 h-8 rounded-full object-cover ring-2 ring-indigo-100"
                    />
                    <div class="hidden sm:block text-left">
                        <div class="text-xs font-bold text-slate-800 leading-tight">{{ currentAgent.name }}</div>
                        <div class="text-[10px] text-slate-500 font-mono">{{ currentAgent.member_id || currentAgent.role }}</div>
                    </div>
                </div>

                <button
                    @click="logout"
                    class="p-2 rounded-xl border border-slate-200 text-slate-500 hover:text-rose-600 hover:bg-slate-50 transition-colors"
                    title="Log out"
                >
                    <LogOut class="w-4 h-4" />
                </button>
            </div>
        </header>

        <!-- ============================================================= -->
        <!-- MAIN DASHBOARD CONTENT AREA (SWITCHED BY THE 3 TABS)         -->
        <!-- ============================================================= -->
        <main class="flex-1 flex overflow-hidden">
            <!-- MODULE 1: WORK STATUS (TASK MANAGEMENT DASHBOARD) -->
            <WorkStatusDashboard
                v-if="currentTab === 'work_status' && !isCustomer"
                :tasks="tasks"
                :projects="projects"
                :staff-agents="staffAgents"
                :current-agent="currentAgent"
            />

            <!-- MODULE 2: TICKET MANAGEMENT DASHBOARD (NO CHAT WINDOW) -->
            <TicketDashboard
                v-else-if="currentTab === 'tickets'"
                :conversations="localConversations"
                :projects="projects"
                :active-conversation="activeConversationRef"
                :current-agent="currentAgent"
                :staff-agents="staffAgents"
                @select-ticket="selectConversation"
                @open-new-ticket="openModalWithTab('ticket')"
                @update-ticket="handleUpdateConversation"
                @switch-to-chat="switchToDirectChat"
            />

            <!-- MODULE 3: REALTIME CHATTING WORKSPACE -->
            <div v-else-if="currentTab === 'chat'" class="flex-1 flex overflow-hidden">
                <!-- ======================================================== -->
                <!-- COLUMN 1: USER LIST (CONTACTS & ASSIGNED PERSONS)        -->
                <!-- ======================================================== -->
                <div 
                    v-if="chatPanelMode === 'split' || chatPanelMode === 'users'"
                    :class="[
                        'border-r border-slate-200 bg-white flex flex-col shrink-0 transition-all',
                        chatPanelMode === 'split' ? 'w-72' : 'w-80 md:w-96'
                    ]"
                >
                    <!-- Header -->
                    <div class="p-3.5 border-b border-slate-100 flex items-center justify-between bg-slate-50/60">
                        <div>
                            <div class="flex items-center space-x-1.5">
                                <UserCheck v-if="isCustomer" class="w-4 h-4 text-emerald-600" />
                                <Users v-else class="w-4 h-4 text-indigo-600" />
                                <h3 class="text-xs font-bold text-slate-800">
                                    {{ isCustomer ? 'Assigned Person' : 'User List' }}
                                </h3>
                                <span class="px-1.5 py-0.2 rounded-full bg-slate-200 text-slate-700 text-[10px] font-mono font-bold">
                                    {{ displayUserList.length }}
                                </span>
                            </div>
                            <p class="text-[10px] text-slate-500 mt-0.5">
                                {{ isCustomer ? 'Your designated support specialist' : 'Direct message team or clients' }}
                            </p>
                        </div>

                        <!-- Mode Switcher -->
                        <div class="flex items-center p-0.5 bg-slate-200/70 rounded-lg text-[10px] font-semibold text-slate-600">
                            <button
                                @click="chatPanelMode = 'split'"
                                :class="['px-2 py-1 rounded transition-colors', chatPanelMode === 'split' ? 'bg-white text-indigo-700 shadow-2xs font-bold' : 'hover:text-slate-900']"
                                title="Show both User List and Chat List"
                            >
                                <Columns class="w-3.5 h-3.5" />
                            </button>
                            <button
                                @click="chatPanelMode = 'chats'"
                                :class="['px-2 py-1 rounded transition-colors', chatPanelMode === 'chats' ? 'bg-white text-indigo-700 shadow-2xs font-bold' : 'hover:text-slate-900']"
                                title="Show Chat List only"
                            >
                                <MessageSquare class="w-3.5 h-3.5" />
                            </button>
                            <button
                                @click="chatPanelMode = 'users'"
                                :class="['px-2 py-1 rounded transition-colors', chatPanelMode === 'users' ? 'bg-white text-indigo-700 shadow-2xs font-bold' : 'hover:text-slate-900']"
                                title="Show User List only"
                            >
                                <Users class="w-3.5 h-3.5" />
                            </button>
                        </div>
                    </div>

                    <!-- Role Filters (Staff/Admin only) -->
                    <div v-if="!isCustomer" class="px-3 pt-2 pb-1.5 flex items-center space-x-1 border-b border-slate-100 bg-white">
                        <button
                            @click="userFilterRole = 'all'"
                            :class="['px-2 py-0.8 rounded-md text-[10px] font-semibold transition-colors', userFilterRole === 'all' ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200']"
                        >
                            All ({{ props.agents.filter(u => u.id !== currentAgent.id).length }})
                        </button>
                        <button
                            @click="userFilterRole = 'staff'"
                            :class="['px-2 py-0.8 rounded-md text-[10px] font-semibold transition-colors', userFilterRole === 'staff' ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200']"
                        >
                            Staff ({{ staffCount }})
                        </button>
                        <button
                            @click="userFilterRole = 'client'"
                            :class="['px-2 py-0.8 rounded-md text-[10px] font-semibold transition-colors', userFilterRole === 'client' ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200']"
                        >
                            Clients ({{ clientCount }})
                        </button>
                    </div>

                    <!-- Search Input -->
                    <div class="p-2.5 border-b border-slate-100 bg-white">
                        <div class="relative">
                            <Search class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                            <input
                                v-model="userSearch"
                                type="text"
                                :placeholder="isCustomer ? 'Search support specialist...' : 'Search users, dept, role...'"
                                class="w-full pl-8 pr-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs text-slate-800 placeholder-slate-400 focus:outline-hidden focus:bg-white focus:border-indigo-600"
                            />
                        </div>
                    </div>

                    <!-- User Cards Scroll List -->
                    <div class="flex-1 overflow-y-auto divide-y divide-slate-100 p-2 space-y-1 bg-white">
                        <div
                            v-for="u in displayUserList"
                            :key="u.id"
                            @click="startDirectChatWithUser(u)"
                            :class="[
                                'p-2.5 rounded-xl cursor-pointer transition-all border text-left',
                                (activeConversationRef?.type === 'direct' && (activeConversationRef?.members?.some(m => m.id === u.id) || activeConversationRef?.customer_email === u.email))
                                    ? 'bg-indigo-50/90 border-indigo-200 shadow-2xs' 
                                    : 'border-transparent hover:bg-slate-50 hover:border-slate-200'
                            ]"
                        >
                            <div class="flex items-start space-x-2.5">
                                <div class="relative shrink-0 mt-0.5">
                                    <img
                                        :src="u.avatar || 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=80&h=80&fit=crop'"
                                        class="w-9 h-9 rounded-full object-cover ring-1 ring-slate-200"
                                    />
                                    <span class="absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full bg-emerald-500 ring-2 ring-white"></span>
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center justify-between gap-1">
                                        <h4 class="text-xs font-bold text-slate-900 truncate">
                                            {{ u.name }}
                                        </h4>
                                        <!-- Role / Status Badge -->
                                        <span 
                                            v-if="u.is_admin"
                                            class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-purple-100 text-purple-700 shrink-0"
                                        >
                                            Admin
                                        </span>
                                        <span 
                                            v-else-if="u.is_customer"
                                            class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-emerald-100 text-emerald-800 shrink-0"
                                        >
                                            Client
                                        </span>
                                        <span 
                                            v-else-if="u.is_team_lead"
                                            class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-amber-100 text-amber-800 shrink-0"
                                        >
                                            Lead
                                        </span>
                                        <span 
                                            v-else
                                            class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-slate-100 text-slate-700 shrink-0"
                                        >
                                            Staff
                                        </span>
                                    </div>

                                    <!-- Department & Designation -->
                                    <div class="text-[11px] text-slate-600 truncate mt-0.5 font-medium">
                                        {{ u.designation || u.role }}
                                    </div>
                                    <div class="text-[10px] text-slate-400 truncate flex items-center space-x-1 mt-0.5">
                                        <span>{{ u.department || 'Operations' }}</span>
                                        <span>•</span>
                                        <span class="font-mono text-slate-500">{{ u.member_id || u.email }}</span>
                                    </div>

                                    <!-- For Customer: Highlight "Assigned Specialist" -->
                                    <div v-if="isCustomer" class="mt-2 pt-1.5 border-t border-slate-100 flex items-center justify-between">
                                        <span class="text-[10px] font-bold text-emerald-700 flex items-center space-x-1">
                                            <Sparkles class="w-3 h-3 text-emerald-600" />
                                            <span>Your Assigned Specialist</span>
                                        </span>
                                        <span class="text-[10px] font-semibold text-indigo-600 hover:text-indigo-800">
                                            Chat Now &rarr;
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="displayUserList.length === 0" class="py-12 text-center text-xs text-slate-400">
                            {{ isCustomer ? 'No assigned support specialist found.' : 'No users match your search.' }}
                        </div>
                    </div>
                </div>

                <!-- ======================================================== -->
                <!-- COLUMN 2: CHAT LIST (ACTIVE CONVERSATIONS)              -->
                <!-- ======================================================== -->
                <div 
                    v-if="chatPanelMode === 'split' || chatPanelMode === 'chats'"
                    :class="[
                        'border-r border-slate-200 bg-white flex flex-col shrink-0 transition-all',
                        chatPanelMode === 'split' ? 'w-72' : 'w-80 md:w-96'
                    ]"
                >
                    <!-- Header -->
                    <div class="p-3.5 border-b border-slate-100 flex items-center justify-between bg-slate-50/60">
                        <div class="flex items-center space-x-1.5">
                            <MessageSquare class="w-4 h-4 text-indigo-600" />
                            <h3 class="text-xs font-bold text-slate-800">
                                Chat List
                            </h3>
                            <span class="px-1.5 py-0.2 rounded-full bg-slate-200 text-slate-700 text-[10px] font-mono font-bold">
                                {{ filteredChatList.length }}
                            </span>
                        </div>

                        <div class="flex items-center space-x-1">
                            <button
                                v-if="!isCustomer"
                                @click="openModalWithTab('group')"
                                class="px-2 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-[11px] font-semibold flex items-center space-x-1 transition-colors"
                                title="Create Group Room"
                            >
                                <Users class="w-3.5 h-3.5" />
                                <span>Group</span>
                            </button>

                            <!-- Mode Switcher if in chats-only mode -->
                            <div v-if="chatPanelMode === 'chats'" class="flex items-center p-0.5 bg-slate-200/70 rounded-lg text-[10px] font-semibold text-slate-600">
                                <button
                                    @click="chatPanelMode = 'split'"
                                    class="px-2 py-1 rounded hover:text-slate-900 transition-colors"
                                    title="Show both User List & Chat List"
                                >
                                    <Columns class="w-3.5 h-3.5" />
                                </button>
                                <button
                                    @click="chatPanelMode = 'users'"
                                    class="px-2 py-1 rounded hover:text-slate-900 transition-colors"
                                    title="Show User List"
                                >
                                    <Users class="w-3.5 h-3.5" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Search Box -->
                    <div class="p-2.5 border-b border-slate-100 bg-white">
                        <div class="relative">
                            <Search class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                            <input
                                v-model="chatSearch"
                                type="text"
                                placeholder="Search conversations..."
                                class="w-full pl-8 pr-3 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-xs text-slate-800 placeholder-slate-400 focus:outline-hidden focus:bg-white focus:border-indigo-600"
                            />
                        </div>
                    </div>

                    <!-- Chat Items List -->
                    <div class="flex-1 overflow-y-auto divide-y divide-slate-100 p-2 space-y-1 bg-white">
                        <div
                            v-for="c in filteredChatList"
                            :key="c.id"
                            @click="selectConversation(c)"
                            :class="[
                                'p-2.5 rounded-xl cursor-pointer transition-all border text-left flex items-center space-x-3',
                                activeConversationRef?.id === c.id 
                                    ? 'bg-indigo-50/90 border-indigo-200 shadow-2xs' 
                                    : 'border-transparent hover:bg-slate-50 hover:border-slate-200'
                            ]"
                        >
                            <div class="relative shrink-0">
                                <img
                                    :src="getConversationAvatar(c)"
                                    class="w-9 h-9 rounded-full object-cover ring-1 ring-slate-200"
                                />
                                <span class="absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full bg-emerald-500 ring-2 ring-white"></span>
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between">
                                    <h4 class="text-xs font-bold text-slate-900 truncate">
                                        {{ getConversationName(c) }}
                                    </h4>
                                    <span class="text-[10px] text-slate-400 font-mono">
                                        {{ formatTime(c.last_message_at) }}
                                    </span>
                                </div>
                                <p class="text-[11px] text-slate-500 truncate mt-0.5">
                                    {{ c.latest_message?.body || 'No messages yet' }}
                                </p>
                            </div>

                            <span v-if="c.unread_count > 0" class="w-4 h-4 rounded-full bg-indigo-600 text-white text-[9px] font-bold flex items-center justify-center shrink-0">
                                {{ c.unread_count }}
                            </span>
                        </div>

                        <div v-if="filteredChatList.length === 0" class="py-12 text-center text-xs text-slate-400">
                            <p>No active chats yet</p>
                            <p class="text-[10px] text-slate-400 mt-1">Select any person from the User List to start chatting.</p>
                        </div>
                    </div>
                </div>

                <!-- ======================================================== -->
                <!-- COLUMN 3: ACTIVE CHAT CONVERSATION WINDOW                -->
                <!-- ======================================================== -->
                <div class="flex-1 flex flex-col h-full bg-white relative min-w-0">
                    <template v-if="activeConversationRef && activeConversationRef.type !== 'ticket'">
                        <ChatHeader
                            :conversation="activeConversationRef"
                            :active-agents-count="viewingAgents.length || 1"
                            :viewing-agents="viewingAgents"
                            :typing-user="typingUser"
                            :is-profile-open="false"
                            :current-agent="currentAgent"
                            :active-view="'chats'"
                            @open-add-member="openModalWithTab('member')"
                            @delete-chat="handleDeleteChat"
                        />

                        <MessageThread
                            :messages="localMessages"
                            :conversation="activeConversationRef"
                            :current-agent="currentAgent"
                        />

                        <ChatInput
                            :canned-responses="cannedResponses"
                            @send-message="handleSendMessage"
                            @typing="broadcastTyping"
                        />
                    </template>

                    <!-- Empty State for Chat -->
                    <div v-else class="flex-1 flex flex-col items-center justify-center p-8 text-center text-slate-500 bg-slate-50/50">
                        <div class="w-14 h-14 rounded-2xl bg-indigo-50 border border-indigo-100 flex items-center justify-center mb-3 text-indigo-600 shadow-xs">
                            <MessageSquare class="w-7 h-7 text-indigo-600" />
                        </div>
                        <h3 class="text-sm font-bold text-slate-800">Realtime Chat</h3>
                        <p class="text-xs text-slate-500 mt-1 max-w-sm">
                            {{ isCustomer ? 'Click your assigned support specialist in the User List to start chatting.' : 'Select any user from the User List or an active conversation to chat live.' }}
                        </p>
                        
                        <!-- Quick Action in Empty State -->
                        <div v-if="displayUserList.length > 0" class="mt-4 flex flex-wrap gap-2 justify-center max-w-md">
                            <button
                                v-for="u in displayUserList.slice(0, 3)"
                                :key="u.id"
                                @click="startDirectChatWithUser(u)"
                                class="px-3 py-1.5 bg-white hover:bg-indigo-50 border border-slate-200 hover:border-indigo-300 rounded-xl text-xs font-semibold text-slate-700 flex items-center space-x-2 transition-all shadow-2xs"
                            >
                                <img :src="u.avatar" class="w-5 h-5 rounded-full object-cover" />
                                <span>Chat with {{ u.name.split(' ')[0] }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- MODULE 4: ADMIN HUB & SETUP PROCEDURE -->
            <AdminOverviewDashboard
                v-else-if="currentTab === 'admin' && isAdmin"
                :projects="projects"
                :agents="agents"
                :tasks="tasks"
                :conversations="localConversations"
                :departments="departments"
                :designations="designations"
                :clients="clients"
                :client-assignments="clientAssignments"
                :current-agent="currentAgent"
                @switch-to-tab="switchMainTab"
            />
        </main>

        <!-- Dynamic Action Modal (Ticket, Chat, Group, Member) -->
        <NewChatModal
            :is-open="isNewChatModalOpen"
            :agents="agents"
            :staff-agents="staffAgents"
            :projects="projects"
            :active-conversation-id="activeConversationRef?.id"
            :current-agent="currentAgent"
            :default-tab="activeModalTab"
            @close="isNewChatModalOpen = false"
        />
    </div>
</template>
