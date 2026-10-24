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
    Shield,
    Mail,
    User,
    Lock
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
    activeView: {
        type: String,
        default: 'tickets',
    },
    isOpen: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['close', 'open-add-member', 'delete-chat']);

const isTicketView = computed(() => props.activeView === 'tickets');
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
    return isTicketView.value && !props.conversation?.assigned_agent_id;
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
        <div class="px-4 py-3 border-b border-slate-800 flex items-center justify-between bg-slate-900">
            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-300 flex items-center space-x-1.5">
                <Ticket v-if="isTicketView" class="w-4 h-4 text-indigo-400" />
                <Users v-else class="w-4 h-4 text-indigo-400" />
                <span>{{ isTicketView ? 'Ticket Inspector' : (isGroup ? 'Group Information' : 'Contact Profile') }}</span>
            </h4>
            <button 
                @click="$emit('close')"
                class="text-slate-400 hover:text-slate-200 text-xs p-1 rounded-md hover:bg-slate-800"
            >
                ✕
            </button>
        </div>

        <div class="p-3.5 space-y-4 flex-1 text-xs">
            <!-- ============================================== -->
            <!-- SECTION A: TICKET MODE (ONLY FOR TICKETS) -->
            <!-- ============================================== -->
            <template v-if="isTicketView">
                <!-- 1. Ticket Overview Card -->
                <div class="p-3 rounded-xl bg-slate-950 border border-slate-800 space-y-2.5">
                    <div class="flex items-center justify-between">
                        <span class="font-mono text-indigo-400 font-bold text-xs bg-indigo-500/10 px-2 py-0.5 rounded border border-indigo-500/20">
                            {{ conversation.ticket_number || '#TK-' + conversation.id }}
                        </span>
                        <span :class="['px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider', conversation.status === 'open' ? 'bg-emerald-500/20 text-emerald-300' : 'bg-indigo-500/20 text-indigo-300']">
                            {{ conversation.status }}
                        </span>
                    </div>

                    <div>
                        <h3 class="font-bold text-slate-100 text-xs leading-snug">
                            {{ conversation.ticket_subject || conversation.display_name }}
                        </h3>
                        <div class="flex items-center space-x-1.5 mt-1.5 text-slate-400 text-[11px]">
                            <User class="w-3.5 h-3.5 text-slate-500 shrink-0" />
                            <span>Requester: <strong class="text-slate-200">{{ conversation.customer_name }}</strong></span>
                        </div>
                        <div class="flex items-center space-x-1.5 mt-0.5 text-slate-500 text-[10px] font-mono">
                            <Mail class="w-3 h-3 text-slate-600 shrink-0" />
                            <span>{{ conversation.customer_email }}</span>
                        </div>
                    </div>

                    <div v-if="conversation.ticket_description" class="pt-2 border-t border-slate-800/80 text-[11px] text-slate-300 leading-relaxed">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Original Issue:</p>
                        <p class="bg-slate-900/80 p-2 rounded-lg border border-slate-800/80">{{ conversation.ticket_description }}</p>
                    </div>
                </div>

                <!-- 2. Collect Ticket Banner (If Unassigned & Staff) -->
                <div v-if="isUnassigned && !isCustomer" class="p-3 rounded-xl bg-amber-950/40 border border-amber-500/40 space-y-2">
                    <div class="flex items-center space-x-1.5 text-amber-300 font-bold text-xs">
                        <Handshake class="w-4 h-4 text-amber-400" />
                        <span>Available in Public Pool</span>
                    </div>
                    <p class="text-[11px] text-slate-300 leading-normal">
                        This ticket is currently unassigned. Click below to claim exclusive ownership.
                    </p>
                    <button
                        @click="claimTicket"
                        :disabled="isSaving"
                        class="w-full py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs shadow-md transition-all flex items-center justify-center space-x-1.5"
                    >
                        <CheckCircle2 class="w-3.5 h-3.5" />
                        <span>{{ isSaving ? 'Collecting...' : '🙋‍♂️ Collect Ticket' }}</span>
                    </button>
                </div>

                <!-- 3. Transfer / Reassign Ticket (Staff / Admin) -->
                <div v-if="!isCustomer && (isAssignedToMe || isAdmin)" class="p-3 rounded-xl bg-slate-950 border border-slate-800 space-y-2.5">
                    <div class="font-bold text-slate-200 flex items-center space-x-1.5">
                        <ArrowRightLeft class="w-3.5 h-3.5 text-sky-400" />
                        <span>Transfer / Re-assign</span>
                    </div>
                    <p class="text-[11px] text-slate-400 leading-normal">
                        Transfer this ticket to another staff member or release it back to the open pool.
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
                        <label class="text-[11px] font-medium text-slate-400">Transfer Note</label>
                        <input 
                            v-model="transferNote"
                            type="text"
                            placeholder="e.g. Requires backend debugging..."
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

                <!-- 4. Ticket Status & Note Controls (Staff / Admin) -->
                <div v-if="!isCustomer && canManage" class="p-3 rounded-xl bg-slate-950 border border-slate-800 space-y-2.5">
                    <div class="font-bold text-slate-200 flex items-center space-x-1.5">
                        <UserCheck class="w-3.5 h-3.5 text-indigo-400" />
                        <span>Status & Notes</span>
                    </div>

                    <div class="space-y-1">
                        <label class="text-[11px] font-medium text-slate-400">Update Status</label>
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

                    <div class="space-y-1">
                        <label class="text-[11px] font-medium text-slate-400">Status Check Note</label>
                        <textarea 
                            v-model="updateNoteText"
                            rows="2"
                            placeholder="Add note for ticket audit log..."
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

                <!-- 5. Delete Ticket (Admin) -->
                <div v-if="!isCustomer && isAdmin" class="pt-3 border-t border-slate-800">
                    <button
                        @click="$emit('delete-chat')"
                        class="w-full py-2 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/30 text-rose-400 text-xs font-semibold flex items-center justify-center space-x-1.5 transition-all"
                    >
                        <Trash2 class="w-3.5 h-3.5" />
                        <span>Delete Ticket</span>
                    </button>
                </div>
            </template>

            <!-- ============================================== -->
            <!-- SECTION B: CHAT MODE (ONLY FOR CHATS / GROUPS) -->
            <!-- ============================================== -->
            <template v-else>
                <!-- 1. DIRECT CHAT PROFILE -->
                <div v-if="!isGroup" class="p-3.5 rounded-xl bg-slate-950 border border-slate-800 text-center space-y-3">
                    <div class="relative inline-block mx-auto">
                        <img 
                            :src="conversation.customer_avatar || 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=120&h=120&fit=crop'" 
                            class="w-16 h-16 rounded-full object-cover ring-2 ring-indigo-500/40 mx-auto"
                        />
                        <span class="absolute bottom-0 right-0 w-3.5 h-3.5 rounded-full bg-emerald-500 ring-2 ring-slate-900"></span>
                    </div>

                    <div>
                        <h3 class="font-bold text-slate-100 text-sm">
                            {{ conversation.display_name || conversation.customer_name }}
                        </h3>
                        <p class="text-xs text-slate-400 mt-0.5">
                            {{ conversation.customer_email }}
                        </p>
                    </div>

                    <div class="pt-2 border-t border-slate-800/80 flex items-center justify-center space-x-2 text-xs">
                        <span v-if="conversation.is_encrypted" class="inline-flex items-center space-x-1 text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded-full border border-emerald-500/20 text-[10px] font-semibold">
                            <Lock class="w-3 h-3" />
                            <span>End-to-End Encrypted</span>
                        </span>
                        <span class="inline-flex items-center space-x-1 text-indigo-300 bg-indigo-500/10 px-2 py-0.5 rounded-full border border-indigo-500/20 text-[10px] font-semibold">
                            <span>Direct Chat</span>
                        </span>
                    </div>
                </div>

                <!-- 2. GROUP CHAT MEMBERS & CONTROLS -->
                <div v-else class="space-y-3">
                    <!-- Group Header Card -->
                    <div class="p-3 rounded-xl bg-slate-950 border border-slate-800 space-y-2">
                        <div class="flex items-center space-x-2.5">
                            <div class="w-10 h-10 rounded-xl bg-indigo-600/30 border border-indigo-500/40 flex items-center justify-center text-indigo-300 font-bold text-sm">
                                👥
                            </div>
                            <div class="min-w-0 flex-1">
                                <h3 class="font-bold text-slate-100 text-xs truncate">
                                    {{ conversation.group_name || conversation.display_name }}
                                </h3>
                                <p class="text-[11px] text-slate-400 truncate">
                                    {{ conversation.members?.length || 0 }} members in this group
                                </p>
                            </div>
                        </div>
                        <p v-if="conversation.group_description" class="text-[11px] text-slate-400 pt-1.5 border-t border-slate-800/60 leading-normal">
                            {{ conversation.group_description }}
                        </p>
                    </div>

                    <!-- Members List -->
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between text-xs font-semibold text-slate-300 px-1">
                            <span class="flex items-center space-x-1.5">
                                <Users class="w-3.5 h-3.5 text-indigo-400" />
                                <span>Group Members</span>
                            </span>
                            <button 
                                v-if="!isCustomer"
                                @click="$emit('open-add-member')"
                                class="text-[11px] text-indigo-400 hover:text-indigo-300 font-medium"
                            >
                                + Add Member
                            </button>
                        </div>

                        <div class="space-y-1 max-h-56 overflow-y-auto">
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
                </div>

                <!-- 3. Delete Chat (Staff / Admin) -->
                <div v-if="!isCustomer" class="pt-3 border-t border-slate-800">
                    <button
                        @click="$emit('delete-chat')"
                        class="w-full py-2 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/30 text-rose-400 text-xs font-semibold flex items-center justify-center space-x-1.5 transition-all"
                    >
                        <Trash2 class="w-3.5 h-3.5" />
                        <span>Delete Chat</span>
                    </button>
                </div>
            </template>
        </div>
    </aside>
</template>
