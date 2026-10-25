<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { 
    Ticket, 
    Plus, 
    Search, 
    ArrowLeft, 
    Clock, 
    CheckCircle2, 
    AlertCircle, 
    User, 
    Handshake,
    Filter,
    Briefcase,
    Building,
    MessageSquare,
    Calendar,
    FileText,
    Check
} from 'lucide-vue-next';

const props = defineProps({
    conversations: {
        type: Array,
        default: () => [],
    },
    projects: {
        type: Array,
        default: () => [],
    },
    activeConversation: {
        type: Object,
        default: null,
    },
    currentAgent: {
        type: Object,
        default: () => ({}),
    },
    staffAgents: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits([
    'select-ticket',
    'open-new-ticket',
    'update-ticket',
    'switch-to-chat',
]);

const isCustomer = computed(() => !!props.currentAgent?.is_customer);
const isAdmin = computed(() => !!props.currentAgent?.is_admin);

// Filter tickets
const filterStatus = ref('all');
const filterProject = ref('all');
const searchQuery = ref('');

const ticketConversations = computed(() => {
    return props.conversations.filter((c) => c.type === 'ticket');
});

const filteredTickets = computed(() => {
    return ticketConversations.value.filter((t) => {
        if (filterStatus.value !== 'all') {
            if (filterStatus.value === 'my') {
                if (t.assigned_agent_id !== props.currentAgent?.id) return false;
            } else if (t.status !== filterStatus.value) {
                return false;
            }
        }
        if (filterProject.value !== 'all') {
            if (String(t.project_id) !== String(filterProject.value)) return false;
        }
        if (searchQuery.value) {
            const q = searchQuery.value.toLowerCase();
            const numMatch = t.ticket_number?.toLowerCase().includes(q);
            const subMatch = t.ticket_subject?.toLowerCase().includes(q);
            const projMatch = t.project?.name?.toLowerCase().includes(q);
            const nameMatch = t.customer_name?.toLowerCase().includes(q);
            const emailMatch = t.customer_email?.toLowerCase().includes(q);
            if (!numMatch && !subMatch && !projMatch && !nameMatch && !emailMatch) return false;
        }
        return true;
    });
});

// Metrics
const totalTickets = computed(() => ticketConversations.value.length);
const openTickets = computed(() => ticketConversations.value.filter(t => t.status === 'open').length);
const inProgressTickets = computed(() => ticketConversations.value.filter(t => t.status === 'in_progress').length);
const resolvedTickets = computed(() => ticketConversations.value.filter(t => t.status === 'resolved' || t.status === 'closed').length);

function selectTicket(ticket) {
    emit('select-ticket', ticket);
}

function clearActiveTicket() {
    emit('select-ticket', null);
}

function handleStatusChange(ticket, newStatus) {
    emit('update-ticket', { status: newStatus });
}

function claimTicket(ticket) {
    router.post(`/conversations/${ticket.id}/claim`, {}, {
        preserveScroll: true,
        preserveState: true,
    });
}

// Resolution / Update Note
const updateNoteText = ref('');
const isSubmittingNote = ref(false);

function submitUpdateNote(ticket) {
    if (!updateNoteText.value.trim()) return;
    isSubmittingNote.value = true;
    router.patch(`/conversations/${ticket.id}`, {
        update_note: updateNoteText.value.trim(),
    }, {
        preserveScroll: true,
        onFinish: () => {
            isSubmittingNote.value = false;
            updateNoteText.value = '';
        },
    });
}

function statusBadge(status) {
    switch (status) {
        case 'open':
            return 'bg-emerald-50 text-emerald-700 border border-emerald-200';
        case 'in_progress':
            return 'bg-sky-50 text-sky-700 border border-sky-200';
        case 'pending':
            return 'bg-amber-50 text-amber-700 border border-amber-200';
        case 'resolved':
        case 'closed':
            return 'bg-slate-100 text-slate-600 border border-slate-200';
        default:
            return 'bg-slate-100 text-slate-600 border border-slate-200';
    }
}

function priorityBadge(p) {
    switch (p) {
        case 'high':
            return 'bg-rose-50 text-rose-700 border border-rose-200';
        case 'medium':
            return 'bg-amber-50 text-amber-700 border border-amber-200';
        case 'low':
            return 'bg-emerald-50 text-emerald-700 border border-emerald-200';
        default:
            return 'bg-slate-100 text-slate-600 border border-slate-200';
    }
}

function formatDate(iso) {
    if (!iso) return '';
    const d = new Date(iso);
    return d.toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit' });
}
</script>

<template>
    <div class="flex-1 flex flex-col h-full overflow-hidden bg-slate-50">
        <!-- ========================================================= -->
        <!-- SUB-VIEW 1: TICKET DETAILS VIEW (NO CHAT WINDOW AS ASKED) -->
        <!-- ========================================================= -->
        <template v-if="activeConversation && activeConversation.type === 'ticket'">
            <!-- Clean Ticket Header Bar -->
            <div class="px-6 py-4 bg-white border-b border-slate-200 flex items-center justify-between shrink-0">
                <div class="flex items-center space-x-3 min-w-0">
                    <button
                        @click="clearActiveTicket"
                        class="p-2 rounded-xl border border-slate-200 text-slate-600 hover:bg-slate-100 transition-colors"
                        title="Back to Tickets List"
                    >
                        <ArrowLeft class="w-4 h-4" />
                    </button>

                    <div class="shrink-0 font-mono text-xs font-bold px-2.5 py-1 rounded-lg bg-indigo-50 text-indigo-700 border border-indigo-200">
                        {{ activeConversation.ticket_number || '#TK-' + activeConversation.id }}
                    </div>

                    <div class="min-w-0">
                        <div class="flex items-center space-x-2">
                            <h3 class="text-sm font-bold text-slate-900 truncate">
                                {{ activeConversation.ticket_subject || 'Support Ticket' }}
                            </h3>
                            <span :class="['px-2 py-0.5 rounded text-[10px] font-bold uppercase', statusBadge(activeConversation.status)]">
                                {{ activeConversation.status }}
                            </span>
                            <span :class="['px-1.5 py-0.5 rounded text-[9px] font-bold uppercase', priorityBadge(activeConversation.priority)]">
                                {{ activeConversation.priority || 'medium' }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Right Actions -->
                <div class="flex items-center space-x-2 shrink-0">
                    <button
                        v-if="!activeConversation.assigned_agent_id && !isCustomer"
                        @click="claimTicket(activeConversation)"
                        class="px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-xs flex items-center space-x-1 transition-colors"
                    >
                        <Handshake class="w-3.5 h-3.5" />
                        <span>Collect Ticket</span>
                    </button>

                    <!-- Status Selector -->
                    <div v-if="!isCustomer" class="flex items-center space-x-1.5 bg-slate-50 border border-slate-200 rounded-xl px-2.5 py-1 text-xs">
                        <span class="text-slate-500 font-medium">Status:</span>
                        <select
                            :value="activeConversation.status"
                            @change="handleStatusChange(activeConversation, $event.target.value)"
                            class="bg-transparent font-semibold text-slate-800 focus:outline-none cursor-pointer capitalize"
                        >
                            <option value="open">Open</option>
                            <option value="in_progress">In Progress</option>
                            <option value="pending">Pending</option>
                            <option value="resolved">Resolved</option>
                            <option value="closed">Closed</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Ticket Details Card & Resolution Log (Clean Form - No Chat Window) -->
            <div class="flex-1 p-6 overflow-y-auto space-y-4 max-w-4xl mx-auto w-full">
                <!-- Meta Card (Project, Client, Assigned, Date) -->
                <div class="p-5 bg-white rounded-2xl border border-slate-200 shadow-xs grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                    <div>
                        <span class="text-[10px] font-semibold uppercase text-slate-400">Associated Project</span>
                        <div class="font-bold text-indigo-700 flex items-center space-x-1.5 mt-1 text-sm">
                            <Briefcase class="w-4 h-4 text-indigo-600 shrink-0" />
                            <span>{{ activeConversation.project?.name || 'General Project' }}</span>
                        </div>
                    </div>

                    <div>
                        <span class="text-[10px] font-semibold uppercase text-slate-400">Requester / Client</span>
                        <div class="font-bold text-slate-800 mt-1">
                            {{ activeConversation.customer_name }}
                        </div>
                        <div class="text-[11px] text-slate-500 font-mono">{{ activeConversation.customer_email }}</div>
                    </div>

                    <div>
                        <span class="text-[10px] font-semibold uppercase text-slate-400">Assigned Staff</span>
                        <div class="font-semibold text-slate-800 mt-1 flex items-center space-x-1">
                            <User class="w-3.5 h-3.5 text-indigo-500" />
                            <span>{{ activeConversation.assigned_agent?.name || 'Unassigned (Pool)' }}</span>
                        </div>
                        <div class="text-[10px] text-slate-400 mt-0.5">Created: {{ formatDate(activeConversation.created_at) }}</div>
                    </div>
                </div>

                <!-- Problem Description Block -->
                <div class="p-5 bg-white rounded-2xl border border-slate-200 shadow-xs space-y-2">
                    <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center space-x-1.5">
                        <FileText class="w-4 h-4 text-indigo-600" />
                        <span>Problem & Issue Description</span>
                    </h4>
                    <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-800 leading-relaxed whitespace-pre-wrap font-sans">
                        {{ activeConversation.ticket_description || 'No detailed problem description provided.' }}
                    </div>
                </div>

                <!-- Resolution Log & Notes (Staff updates) -->
                <div class="p-5 bg-white rounded-2xl border border-slate-200 shadow-xs space-y-3">
                    <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center space-x-1.5">
                        <CheckCircle2 class="w-4 h-4 text-emerald-600" />
                        <span>Resolution & Progress Notes</span>
                    </h4>

                    <!-- Note Form (Staff can add updates or resolution summary) -->
                    <div v-if="!isCustomer" class="space-y-2 pt-1">
                        <textarea
                            v-model="updateNoteText"
                            rows="2"
                            placeholder="Add ticket update note or resolution summary..."
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:bg-white focus:border-indigo-600 resize-none"
                        ></textarea>
                        <div class="flex justify-end">
                            <button
                                @click="submitUpdateNote(activeConversation)"
                                :disabled="isSubmittingNote || !updateNoteText.trim()"
                                class="px-3.5 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 text-white text-xs font-semibold shadow-xs transition-colors"
                            >
                                Save Note
                            </button>
                        </div>
                    </div>

                    <!-- Direct Chat Shortcut -->
                    <div class="p-3 bg-indigo-50/60 rounded-xl border border-indigo-100 flex items-center justify-between text-xs">
                        <div class="flex items-center space-x-2 text-indigo-900">
                            <MessageSquare class="w-4 h-4 text-indigo-600 shrink-0" />
                            <span>Need to live chat directly with this customer?</span>
                        </div>
                        <button
                            @click="$emit('switch-to-chat', activeConversation)"
                            class="px-3 py-1 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-xs shadow-xs transition-colors"
                        >
                            Open in Realtime Chat
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <!-- ========================================================= -->
        <!-- SUB-VIEW 2: TICKETS DASHBOARD & QUEUE LIST               -->
        <!-- ========================================================= -->
        <template v-else>
            <!-- Top Action Bar & Metrics -->
            <div class="px-6 py-4 bg-white border-b border-slate-200 shrink-0">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-bold text-slate-900 flex items-center space-x-2">
                            <Ticket class="w-5 h-5 text-indigo-600" />
                            <span>Ticket Management</span>
                        </h2>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Customer problem requests tracked by project and SLA status.
                        </p>
                    </div>

                    <div class="flex items-center space-x-2">
                        <button
                            v-if="isCustomer"
                            @click="$emit('open-new-ticket')"
                            class="px-3.5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-xs flex items-center space-x-1.5 transition-colors"
                        >
                            <Plus class="w-3.5 h-3.5" />
                            <span>Create Ticket</span>
                        </button>
                    </div>
                </div>

                <!-- Ticket Summary Metrics -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-4">
                    <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-between">
                        <div>
                            <span class="text-[11px] font-medium text-slate-500 uppercase tracking-wider">Total Tickets</span>
                            <div class="text-lg font-bold text-slate-800">{{ totalTickets }}</div>
                        </div>
                        <div class="w-8 h-8 rounded-lg bg-white border border-slate-200 flex items-center justify-center text-slate-600">
                            <Ticket class="w-4 h-4" />
                        </div>
                    </div>

                    <div class="p-3 bg-emerald-50/50 border border-emerald-200/80 rounded-xl flex items-center justify-between">
                        <div>
                            <span class="text-[11px] font-medium text-emerald-600 uppercase tracking-wider">Open</span>
                            <div class="text-lg font-bold text-emerald-800">{{ openTickets }}</div>
                        </div>
                        <div class="w-8 h-8 rounded-lg bg-emerald-100/60 text-emerald-700 flex items-center justify-center">
                            <AlertCircle class="w-4 h-4" />
                        </div>
                    </div>

                    <div class="p-3 bg-sky-50/50 border border-sky-200/80 rounded-xl flex items-center justify-between">
                        <div>
                            <span class="text-[11px] font-medium text-sky-600 uppercase tracking-wider">In Progress</span>
                            <div class="text-lg font-bold text-sky-800">{{ inProgressTickets }}</div>
                        </div>
                        <div class="w-8 h-8 rounded-lg bg-sky-100/60 text-sky-700 flex items-center justify-center">
                            <Clock class="w-4 h-4" />
                        </div>
                    </div>

                    <div class="p-3 bg-slate-100/80 border border-slate-200 rounded-xl flex items-center justify-between">
                        <div>
                            <span class="text-[11px] font-medium text-slate-600 uppercase tracking-wider">Resolved</span>
                            <div class="text-lg font-bold text-slate-800">{{ resolvedTickets }}</div>
                        </div>
                        <div class="w-8 h-8 rounded-lg bg-white border border-slate-200 flex items-center justify-center text-slate-600">
                            <CheckCircle2 class="w-4 h-4" />
                        </div>
                    </div>
                </div>

                <!-- Filters & Search -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mt-4">
                    <div class="flex items-center space-x-2 flex-wrap gap-y-2">
                        <!-- Project Filter -->
                        <div class="flex items-center space-x-1 text-xs">
                            <span class="text-slate-500 text-[11px]">Project:</span>
                            <select
                                v-model="filterProject"
                                class="px-2 py-1 bg-white border border-slate-200 rounded-lg text-xs text-slate-700 focus:outline-none focus:border-indigo-500"
                            >
                                <option value="all">All Projects</option>
                                <option v-for="p in projects" :key="p.id" :value="p.id">
                                    {{ p.name }}
                                </option>
                            </select>
                        </div>

                        <!-- Status Filter -->
                        <div class="flex items-center space-x-1 overflow-x-auto">
                            <button
                                v-for="f in [
                                    { key: 'all', label: 'All' },
                                    { key: 'open', label: 'Open' },
                                    { key: 'in_progress', label: 'In Progress' },
                                    { key: 'resolved', label: 'Resolved' },
                                    ...(!isCustomer ? [{ key: 'my', label: 'My Assigned' }] : [])
                                ]"
                                :key="f.key"
                                @click="filterStatus = f.key"
                                :class="[
                                    'px-2.5 py-1 rounded-lg text-xs font-medium transition-colors shrink-0',
                                    filterStatus === f.key ? 'bg-indigo-600 text-white shadow-xs' : 'bg-slate-100 hover:bg-slate-200 text-slate-600'
                                ]"
                            >
                                {{ f.label }}
                            </button>
                        </div>
                    </div>

                    <div class="relative max-w-xs w-full">
                        <Search class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Search ticket #, subject or project..."
                            class="w-full pl-8 pr-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:bg-white focus:border-indigo-600"
                        />
                    </div>
                </div>
            </div>

            <!-- Tickets Queue Table -->
            <div class="flex-1 p-6 overflow-y-auto">
                <div class="bg-white rounded-2xl border border-slate-200 overflow-hidden shadow-xs">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs text-slate-700">
                            <thead class="bg-slate-50 border-b border-slate-200 text-slate-500 font-semibold uppercase tracking-wider text-[10px]">
                                <tr>
                                    <th class="px-4 py-3">Ticket ID</th>
                                    <th class="px-4 py-3">Project</th>
                                    <th class="px-4 py-3">Subject & Details</th>
                                    <th class="px-4 py-3">Requester / Client</th>
                                    <th class="px-4 py-3">Assigned Staff</th>
                                    <th class="px-4 py-3">Priority</th>
                                    <th class="px-4 py-3">Status</th>
                                    <th class="px-4 py-3 text-right">Created</th>
                                    <th class="px-4 py-3 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <tr
                                    v-for="ticket in filteredTickets"
                                    :key="ticket.id"
                                    @click="selectTicket(ticket)"
                                    class="hover:bg-slate-50/80 cursor-pointer transition-colors"
                                >
                                    <td class="px-4 py-3 font-mono font-bold text-indigo-600">
                                        {{ ticket.ticket_number || '#TK-' + ticket.id }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span v-if="ticket.project" class="inline-flex items-center space-x-1 font-semibold text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded-md border border-indigo-100 truncate max-w-[140px]">
                                            <Briefcase class="w-2.5 h-2.5 shrink-0" />
                                            <span class="truncate">{{ ticket.project.name }}</span>
                                        </span>
                                        <span v-else class="text-slate-400">General</span>
                                    </td>
                                    <td class="px-4 py-3 max-w-xs">
                                        <div class="font-bold text-slate-900 truncate">
                                            {{ ticket.ticket_subject || 'Support Ticket' }}
                                        </div>
                                        <div class="text-[11px] text-slate-500 truncate mt-0.5">
                                            {{ ticket.ticket_description || 'No description provided' }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-slate-800">{{ ticket.customer_name }}</div>
                                        <div class="text-[10px] text-slate-400 font-mono">{{ ticket.customer_email }}</div>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span v-if="ticket.assigned_agent" class="inline-flex items-center space-x-1 font-medium text-slate-800">
                                            <User class="w-3 h-3 text-indigo-500" />
                                            <span>{{ ticket.assigned_agent.name }}</span>
                                        </span>
                                        <span v-else class="text-amber-700 font-medium">⚡ Unassigned</span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span :class="['px-2 py-0.5 rounded text-[10px] font-bold uppercase', priorityBadge(ticket.priority)]">
                                            {{ ticket.priority || 'medium' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <span :class="['px-2 py-0.5 rounded text-[10px] font-bold uppercase', statusBadge(ticket.status)]">
                                            {{ ticket.status }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-right text-slate-400 font-mono text-[10px]">
                                        {{ formatDate(ticket.created_at) }}
                                    </td>
                                    <td class="px-4 py-3 text-right" @click.stop>
                                        <button
                                            @click="selectTicket(ticket)"
                                            class="px-2.5 py-1 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-semibold text-[11px] transition-colors"
                                        >
                                            View
                                        </button>
                                    </td>
                                </tr>

                                <tr v-if="filteredTickets.length === 0">
                                    <td colspan="9" class="px-4 py-12 text-center text-slate-400 text-xs">
                                        No tickets found in this filter.
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </template>
    </div>
</template>
