<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { 
    Users, 
    Trash2, 
    Crown, 
    Ticket,
    CheckCircle2,
    UserCheck,
    Handshake,
    ArrowRightLeft,
    Shield
} from 'lucide-vue-next';

const props = defineProps({
    conversation: {
        type: Object,
        default: null,
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
    isOpen: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['close', 'open-add-member', 'delete-chat']);

const isTicket = computed(() => props.conversation?.type === 'ticket' || !!props.conversation?.ticket_number);
const isGroup = computed(() => props.conversation?.type === 'group');
const isCustomer = computed(() => props.currentAgent?.is_customer);
const isAdmin = computed(() => props.currentAgent?.is_admin);

// Status & Assignee form state
const selectedStatus = ref(props.conversation?.status || 'open');
const selectedAssignee = ref(props.conversation?.assigned_agent_id || '');
const updateNoteText = ref('');
const isSaving = ref(false);

// Reassign / Transfer state
const transferTargetId = ref('');
const transferNote = ref('');
const isTransferring = ref(false);

watch(() => props.conversation, (newConv) => {
    if (newConv) {
        selectedStatus.value = newConv.status || 'open';
        selectedAssignee.value = newConv.assigned_agent_id || '';
    }
}, { immediate: true });

const isAssignedToMe = computed(() => {
    return props.conversation?.assigned_agent_id === props.currentAgent?.id;
});

const isUnassigned = computed(() => {
    return isTicket.value && !props.conversation?.assigned_agent_id;
});

const canManage = computed(() => {
    return isAdmin.value || 
           isAssignedToMe.value ||
           props.conversation?.members?.some(m => m.id === props.currentAgent?.id && m.pivot?.is_group_admin);
});

// Staff list for assigning/transferring
const eligibleAgents = computed(() => {
    if (props.staffAgents && props.staffAgents.length > 0) {
        return props.staffAgents;
    }
    return props.agents.filter(a => a.is_admin || a.role !== 'customer');
});

function claimTicket() {
    isSaving.value = true;
    router.post(`/conversations/${props.conversation.id}/claim`, {}, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            isSaving.value = false;
        },
    });
}

function handleReassign() {
    isTransferring.value = true;
    router.post(`/conversations/${props.conversation.id}/reassign`, {
        target_agent_id: transferTargetId.value ? Number(transferTargetId.value) : null,
        note: transferNote.value.trim() || undefined,
    }, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            isTransferring.value = false;
            transferNote.value = '';
        },
    });
}

function saveTicketUpdate() {
    isSaving.value = true;
    router.patch(`/conversations/${props.conversation.id}`, {
        status: selectedStatus.value,
        assigned_agent_id: selectedAssignee.value ? Number(selectedAssignee.value) : null,
        update_note: updateNoteText.value.trim() || undefined,
    }, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            isSaving.value = false;
            updateNoteText.value = '';
        },
    });
}

function makeAdmin(member) {
    if (confirm(`Make ${member.name} a Group Admin?`)) {
        router.post(`/conversations/${props.conversation.id}/members/${member.id}/admin`, {}, {
            preserveScroll: true,
            preserveState: true,
        });
    }
}

function removeMember(member) {
    if (confirm(`Remove ${member.name} from this chat?`)) {
        router.delete(`/conversations/${props.conversation.id}/members/${member.id}`, {
            preserveScroll: true,
            preserveState: true,
        });
    }
}
</script>

<template>
    <aside 
        v-if="isOpen && conversation"
        class="w-72 lg:w-80 h-full bg-slate-900 border-l border-slate-800 flex flex-col overflow-y-auto select-none shrink-0 z-10 text-slate-200"
    >
        <!-- Panel Header -->
        <div class="px-4 py-2.5 border-b border-slate-800 flex items-center justify-between bg-slate-900">
            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-300 flex items-center space-x-1.5">
                <Ticket v-if="isTicket" class="w-3.5 h-3.5 text-indigo-400" />
                <span>{{ isTicket ? 'Ticket Details' : (isGroup ? 'Group Details' : 'Chat Info') }}</span>
            </h4>
            <button 
                @click="$emit('close')"
                class="text-slate-400 hover:text-slate-200 text-xs p-1 rounded-md hover:bg-slate-800"
            >
                ✕
            </button>
        </div>

        <div class="p-3.5 space-y-4 flex-1 text-xs">
            <!-- 1. TICKET PROFILE / INFO CARD -->
            <div class="p-3 rounded-xl bg-slate-950 border border-slate-800 space-y-2">
                <div v-if="isTicket" class="flex items-center justify-between">
                    <span class="font-mono text-indigo-400 font-bold text-xs">{{ conversation.ticket_number || '#TK-' + conversation.id }}</span>
                    <span :class="['px-2 py-0.5 rounded text-[10px] font-bold uppercase', conversation.status === 'open' ? 'bg-emerald-500/20 text-emerald-300' : 'bg-indigo-500/20 text-indigo-300']">
                        {{ conversation.status }}
                    </span>
                </div>

                <div>
                    <h3 class="font-bold text-slate-100 text-xs leading-snug">
                        {{ conversation.ticket_subject || conversation.display_name }}
                    </h3>
                    <p class="text-[11px] text-slate-400 mt-0.5">
                        Requester: {{ conversation.customer_name }}
                    </p>
                    <p class="text-[10px] text-slate-500 font-mono">
                        {{ conversation.customer_email }}
                    </p>
                </div>

                <div v-if="conversation.ticket_description" class="pt-2 border-t border-slate-800/80 text-[11px] text-slate-300 leading-relaxed">
                    {{ conversation.ticket_description }}
                </div>
            </div>

            <!-- 2. COLLECT TICKET BANNER (For Unassigned tickets viewed by Staff) -->
            <div v-if="isTicket && isUnassigned && !isCustomer" class="p-3 rounded-xl bg-amber-950/40 border border-amber-500/40 space-y-2">
                <div class="flex items-center space-x-1.5 text-amber-300 font-bold text-xs">
                    <Handshake class="w-4 h-4 text-amber-400" />
                    <span>Unassigned Ticket</span>
                </div>
                <p class="text-[11px] text-slate-300 leading-normal">
                    This ticket is currently unassigned in the public pool. Claim it to take ownership and manage it.
                </p>
                <button
                    @click="claimTicket"
                    :disabled="isSaving"
                    class="w-full py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-md transition-all flex items-center justify-center space-x-1.5"
                >
                    <CheckCircle2 class="w-3.5 h-3.5" />
                    <span>{{ isSaving ? 'Collecting...' : '🙋‍♂️ Collect Ticket (Claim Ownership)' }}</span>
                </button>
            </div>

            <!-- 3. REASSIGN / TRANSFER TICKET (If collected by staff and they want to assign to another one) -->
            <div v-if="isTicket && !isCustomer && (isAssignedToMe || isAdmin)" class="p-3 rounded-xl bg-slate-950 border border-slate-800 space-y-2.5">
                <div class="font-bold text-slate-200 flex items-center space-x-1.5">
                    <ArrowRightLeft class="w-3.5 h-3.5 text-sky-400" />
                    <span>Transfer / Re-assign</span>
                </div>
                <p class="text-[11px] text-slate-400 leading-normal">
                    If you cannot resolve this ticket, transfer it to another agent or release it back to the open pool.
                </p>

                <div class="space-y-1">
                    <label class="text-[11px] font-medium text-slate-400">Transfer To Agent</label>
                    <select 
                        v-model="transferTargetId"
                        class="w-full bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-1.5 text-xs text-slate-200 focus:outline-none focus:border-sky-500"
                    >
                        <option value="">Release to Unassigned Pool</option>
                        <option v-for="agent in eligibleAgents" :key="agent.id" :value="agent.id">
                            {{ agent.name }} ({{ agent.member_id || agent.role }})
                        </option>
                    </select>
                </div>

                <div class="space-y-1">
                    <label class="text-[11px] font-medium text-slate-400">Transfer Reason / Note</label>
                    <input 
                        v-model="transferNote"
                        type="text"
                        placeholder="e.g. Needs senior devops assistance on Redis"
                        class="w-full px-2.5 py-1.5 bg-slate-900 border border-slate-700 rounded-lg text-xs text-slate-200 focus:outline-none focus:border-sky-500"
                    />
                </div>

                <button
                    @click="handleReassign"
                    :disabled="isTransferring"
                    class="w-full py-1.5 rounded-lg bg-sky-600 hover:bg-sky-500 text-white font-semibold text-xs shadow transition-all flex items-center justify-center space-x-1"
                >
                    <ArrowRightLeft class="w-3.5 h-3.5" />
                    <span>{{ isTransferring ? 'Transferring...' : 'Transfer Ticket' }}</span>
                </button>
            </div>

            <!-- 4. TICKET STATUS & NOTE CONTROLS (Staff / Admin only) -->
            <div v-if="isTicket && !isCustomer && canManage" class="p-3 rounded-xl bg-slate-950 border border-slate-800 space-y-2.5">
                <div class="font-bold text-slate-200 flex items-center space-x-1.5">
                    <UserCheck class="w-3.5 h-3.5 text-indigo-400" />
                    <span>Status & Notes</span>
                </div>

                <!-- Status Update -->
                <div class="space-y-1">
                    <label class="text-[11px] font-medium text-slate-400">Ticket Status</label>
                    <select 
                        v-model="selectedStatus"
                        class="w-full bg-slate-900 border border-slate-700 rounded-lg px-2.5 py-1.5 text-xs text-slate-200 focus:outline-none focus:border-indigo-500 capitalize"
                    >
                        <option value="open">🟢 Open</option>
                        <option value="in_progress">🔵 In Progress</option>
                        <option value="pending">🟡 Pending</option>
                        <option value="resolved">⚪ Resolved</option>
                        <option value="closed">⬛ Closed</option>
                    </select>
                </div>

                <!-- Status Update Note with Text -->
                <div class="space-y-1">
                    <label class="text-[11px] font-medium text-slate-400">Add Check / Status Note</label>
                    <textarea 
                        v-model="updateNoteText"
                        rows="2"
                        placeholder="e.g. Reviewed logs and adjusted configuration..."
                        class="w-full px-2.5 py-1.5 bg-slate-900 border border-slate-700 rounded-lg text-xs text-slate-200 focus:outline-none focus:border-indigo-500 resize-none"
                    ></textarea>
                </div>

                <button
                    @click="saveTicketUpdate"
                    :disabled="isSaving"
                    class="w-full py-2 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs shadow transition-all flex items-center justify-center space-x-1"
                >
                    <CheckCircle2 class="w-3.5 h-3.5" />
                    <span>{{ isSaving ? 'Saving...' : 'Update Status' }}</span>
                </button>
            </div>

            <!-- 5. GROUP MEMBERS (For Group Chats) -->
            <div v-else-if="isGroup && !isCustomer" class="space-y-2">
                <div class="flex items-center justify-between text-xs font-semibold text-slate-300">
                    <span class="flex items-center space-x-1.5">
                        <Users class="w-3.5 h-3.5 text-indigo-400" />
                        <span>Members ({{ conversation.members?.length || 0 }})</span>
                    </span>
                    <button 
                        @click="$emit('open-add-member')"
                        class="text-[11px] text-indigo-400 hover:text-indigo-300 font-medium"
                    >
                        + Add
                    </button>
                </div>

                <div class="space-y-1 max-h-48 overflow-y-auto">
                    <div 
                        v-for="member in conversation.members" 
                        :key="member.id"
                        class="p-2 rounded-lg bg-slate-950 border border-slate-800 flex items-center justify-between text-xs group"
                    >
                        <div class="flex items-center space-x-2 min-w-0">
                            <img :src="member.avatar" class="w-6 h-6 rounded-full object-cover shrink-0" />
                            <div class="min-w-0">
                                <div class="font-medium text-slate-200 truncate flex items-center space-x-1">
                                    <span class="truncate">{{ member.name }}</span>
                                    <Crown v-if="member.pivot?.is_group_admin" class="w-3 h-3 text-amber-400 shrink-0" title="Group Admin" />
                                </div>
                                <div class="text-[10px] text-slate-400 truncate">{{ member.member_id || member.role }}</div>
                            </div>
                        </div>

                        <div v-if="canManage && member.id !== currentAgent?.id" class="flex items-center space-x-1 opacity-0 group-hover:opacity-100">
                            <button
                                v-if="!member.pivot?.is_group_admin"
                                @click="makeAdmin(member)"
                                class="px-1.5 py-0.5 rounded bg-amber-500/10 text-amber-300 text-[9px] font-bold"
                            >
                                Admin
                            </button>
                            <button
                                @click="removeMember(member)"
                                class="p-1 rounded text-slate-500 hover:text-rose-400"
                                title="Remove"
                            >
                                ✕
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 6. DELETE CHAT / TICKET BUTTON (Admin only or Chat Member) -->
            <div v-if="!isCustomer" class="pt-3 border-t border-slate-800">
                <button
                    @click="$emit('delete-chat')"
                    class="w-full py-2 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/30 text-rose-400 text-xs font-semibold flex items-center justify-center space-x-1.5 transition-all"
                >
                    <Trash2 class="w-3.5 h-3.5" />
                    <span>{{ isTicket ? 'Delete Ticket' : 'Delete Chat' }}</span>
                </button>
            </div>
        </div>
    </aside>
</template>
