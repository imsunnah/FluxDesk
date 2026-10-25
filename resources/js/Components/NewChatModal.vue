<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { 
    Ticket,
    MessageSquare, 
    Users, 
    UserPlus, 
    Lock, 
    X, 
    Check, 
    Sparkles,
    Shield,
    UserCheck
} from 'lucide-vue-next';

const props = defineProps({
    isOpen: {
        type: Boolean,
        default: false,
    },
    agents: {
        type: Array,
        default: () => [],
    },
    staffAgents: {
        type: Array,
        default: () => [],
    },
    activeConversationId: {
        type: [Number, String, null],
        default: null,
    },
    currentAgent: {
        type: Object,
        default: () => ({}),
    },
    defaultTab: {
        type: String,
        default: 'ticket',
    },
    projects: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['close']);

const isCustomer = computed(() => !!props.currentAgent?.is_customer);
const isStaff = computed(() => !!props.currentAgent?.is_staff);
const canCreateTicket = computed(() => isCustomer.value);
const isAdmin = computed(() => !!props.currentAgent?.is_admin);

// Available Tabs based on user role and customer capability
const availableTabs = computed(() => {
    const list = [];
    if (canCreateTicket.value) {
        list.push({ key: 'ticket', label: 'Ticket', icon: Ticket });
    }
    list.push({ key: 'single', label: isCustomer.value && !isStaff.value ? 'Text Support' : 'Chat', icon: MessageSquare });
    if (isStaff.value) {
        list.push({ key: 'group', label: 'Group', icon: Users });
    }
    if (isAdmin.value) {
        list.push({ key: 'member', label: 'Add User', icon: UserPlus });
    }
    return list;
});

const activeTab = ref(
    (!canCreateTicket.value && props.defaultTab === 'ticket') 
        ? 'single' 
        : (props.defaultTab || 'single')
);

watch(() => props.defaultTab, (newTab) => {
    if (newTab === 'ticket' && !canCreateTicket.value) {
        activeTab.value = 'single';
    } else {
        activeTab.value = newTab || 'single';
    }
});

// Staff list for customer to message or ticket assignment
const staffList = computed(() => {
    if (props.staffAgents && props.staffAgents.length > 0) {
        return props.staffAgents;
    }
    return props.agents.filter(a => a.is_admin || a.role !== 'customer');
});

// Ticket Form
const ticketForm = ref({
    subject: '',
    project_id: props.currentAgent?.project_id || '',
    customer_name: props.currentAgent?.name || '',
    customer_email: props.currentAgent?.email || '',
    description: '',
    priority: 'medium',
    assigned_agent_id: props.currentAgent?.assigned_agent_id || '',
});

// Direct Chat Form
const directForm = ref({
    target_user_id: '',
    name: '',
    email: '',
    is_encrypted: false,
    initial_message: '',
});

// Group Form
const groupForm = ref({
    group_name: '',
    group_description: '',
    member_ids: [],
    is_encrypted: false,
});

// Member Form (Staff or Customer)
const memberForm = ref({
    role_type: 'staff', // 'staff' | 'customer'
    member_id: '',
    name: '',
    email: '',
    role: 'Technical Support',
    is_team_lead: false,
    project_id: '',
    assigned_agent_id: '',
    password: 'password123',
    is_admin: false,
});

watch(() => props.currentAgent, (user) => {
    if (user) {
        ticketForm.value.customer_name = user.name || '';
        ticketForm.value.customer_email = user.email || '';
        ticketForm.value.assigned_agent_id = user.assigned_agent_id || '';
        if (user.project_id) {
            ticketForm.value.project_id = user.project_id;
        }
    }
}, { immediate: true });

const isSubmitting = ref(false);

function submitTicket() {
    if (!ticketForm.value.subject || !ticketForm.value.description) return;
    isSubmitting.value = true;
    router.post('/tickets', {
        subject: ticketForm.value.subject,
        project_id: ticketForm.value.project_id ? Number(ticketForm.value.project_id) : null,
        customer_name: ticketForm.value.customer_name,
        customer_email: ticketForm.value.customer_email,
        description: ticketForm.value.description,
        priority: ticketForm.value.priority,
        assigned_agent_id: ticketForm.value.assigned_agent_id ? Number(ticketForm.value.assigned_agent_id) : null,
    }, {
        onFinish: () => {
            isSubmitting.value = false;
            emit('close');
            ticketForm.value.subject = '';
            ticketForm.value.description = '';
        },
    });
}

function submitDirectChat() {
    isSubmitting.value = true;
    router.post('/conversations/direct', {
        target_user_id: directForm.value.target_user_id ? Number(directForm.value.target_user_id) : null,
        name: directForm.value.name,
        email: directForm.value.email,
        is_encrypted: directForm.value.is_encrypted,
        initial_message: directForm.value.initial_message,
    }, {
        onFinish: () => {
            isSubmitting.value = false;
            emit('close');
            directForm.value = { target_user_id: '', name: '', email: '', is_encrypted: false, initial_message: '' };
        },
    });
}

function submitGroupChat() {
    if (!groupForm.value.group_name) return;
    isSubmitting.value = true;
    router.post('/conversations/group', groupForm.value, {
        onFinish: () => {
            isSubmitting.value = false;
            emit('close');
            groupForm.value = { group_name: '', group_description: '', member_ids: [], is_encrypted: false };
        },
    });
}

function submitNewMember() {
    if (!memberForm.value.name || !memberForm.value.email) return;
    isSubmitting.value = true;
    const isCust = memberForm.value.role_type === 'customer';
    router.post('/members', {
        member_id: memberForm.value.member_id || undefined,
        name: memberForm.value.name,
        email: memberForm.value.email,
        role: isCust ? 'customer' : (memberForm.value.role || 'Staff'),
        is_customer: isCust || !!memberForm.value.is_customer,
        is_team_lead: !isCust && !!memberForm.value.is_team_lead,
        project_id: memberForm.value.project_id ? Number(memberForm.value.project_id) : null,
        assigned_agent_id: isCust && memberForm.value.assigned_agent_id ? Number(memberForm.value.assigned_agent_id) : null,
        password: memberForm.value.password,
        is_admin: !isCust && memberForm.value.is_admin,
    }, {
        onFinish: () => {
            isSubmitting.value = false;
            emit('close');
            memberForm.value = {
                role_type: 'staff',
                member_id: '',
                name: '',
                email: '',
                role: 'Technical Support',
                is_team_lead: false,
                project_id: '',
                assigned_agent_id: '',
                password: 'password123',
                is_admin: false,
            };
        },
    });
}

function toggleGroupMember(agentId) {
    const idx = groupForm.value.member_ids.indexOf(agentId);
    if (idx > -1) {
        groupForm.value.member_ids.splice(idx, 1);
    } else {
        groupForm.value.member_ids.push(agentId);
    }
}
</script>

<template>
    <div 
        v-if="isOpen" 
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs"
    >
        <div class="w-full max-w-md rounded-2xl bg-white border border-slate-200 shadow-2xl overflow-hidden flex flex-col">
            <!-- Modal Header -->
            <div class="px-5 py-3.5 border-b border-slate-100 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-slate-900 flex items-center space-x-2">
                        <Sparkles class="w-4 h-4 text-indigo-600" />
                        <span>Action Menu</span>
                    </h3>
                    <p class="text-[11px] text-slate-500">
                        {{ isCustomer ? 'Create support ticket or message your assigned agent' : 'Create ticket, start chat, group, or add user' }}
                    </p>
                </div>
                <button 
                    @click="$emit('close')"
                    class="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors"
                >
                    <X class="w-4 h-4" />
                </button>
            </div>

            <!-- Dynamic Role-Scaped Tabs -->
            <div 
                class="grid p-1 mx-4 mt-3 bg-slate-100 rounded-xl border border-slate-200/80 text-[11px] font-semibold gap-1"
                :style="{ gridTemplateColumns: `repeat(${availableTabs.length}, minmax(0, 1fr))` }"
            >
                <button
                    v-for="t in availableTabs"
                    :key="t.key"
                    @click="activeTab = t.key"
                    :class="[
                        'py-1.5 px-1 rounded-lg flex items-center justify-center space-x-1 transition-all',
                        activeTab === t.key ? 'bg-white text-indigo-700 font-bold shadow-xs' : 'text-slate-600 hover:text-slate-900'
                    ]"
                >
                    <component :is="t.icon" class="w-3 h-3" />
                    <span>{{ t.label }}</span>
                </button>
            </div>

            <!-- TAB 1: CREATE TICKET -->
            <form 
                v-if="activeTab === 'ticket'" 
                @submit.prevent="submitTicket"
                class="p-4 space-y-3 text-xs"
            >
                <!-- Project Selection -->
                <div class="space-y-1">
                    <label class="font-medium text-slate-700">Project</label>
                    <select
                        v-model="ticketForm.project_id"
                        class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:bg-white focus:border-indigo-600 transition-colors"
                    >
                        <option value="">Select Project</option>
                        <option v-for="p in projects" :key="p.id" :value="p.id">
                            {{ p.name }}
                        </option>
                    </select>
                </div>

                <div class="space-y-1">
                    <label class="font-medium text-slate-700">Ticket Title / Issue Subject</label>
                    <input 
                        v-model="ticketForm.subject"
                        type="text" 
                        required
                        placeholder="e.g. WebSocket connection drops on port 8080" 
                        class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 placeholder-slate-400 focus:outline-none focus:bg-white focus:border-indigo-600 transition-colors"
                    />
                </div>

                <div class="grid grid-cols-2 gap-2" v-if="!isCustomer">
                    <div class="space-y-1">
                        <label class="font-medium text-slate-700">Requester Name</label>
                        <input 
                            v-model="ticketForm.customer_name"
                            type="text" 
                            required
                            placeholder="Name" 
                            class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 placeholder-slate-400 focus:outline-none focus:bg-white focus:border-indigo-600 transition-colors"
                        />
                    </div>

                    <div class="space-y-1">
                        <label class="font-medium text-slate-700">Requester Email</label>
                        <input 
                            v-model="ticketForm.customer_email"
                            type="email" 
                            required
                            placeholder="Email address" 
                            class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 placeholder-slate-400 focus:outline-none focus:bg-white focus:border-indigo-600 transition-colors"
                        />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <!-- Priority -->
                    <div class="space-y-1">
                        <label class="font-medium text-slate-700">Priority</label>
                        <select 
                            v-model="ticketForm.priority"
                            class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:bg-white focus:border-indigo-600 transition-colors"
                        >
                            <option value="low">🟢 Low</option>
                            <option value="medium">🟡 Medium</option>
                            <option value="high">🔴 High</option>
                        </select>
                    </div>

                    <!-- Assign Person -->
                    <div class="space-y-1">
                        <label class="font-medium text-slate-700">
                            {{ isCustomer ? 'Assigned Support Agent' : 'Assign To (Staff Agent)' }}
                        </label>
                        <select 
                            v-model="ticketForm.assigned_agent_id"
                            class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:bg-white focus:border-indigo-600 transition-colors"
                        >
                            <option value="">Leave for Pool (Unassigned)</option>
                            <option v-for="a in staffList" :key="a.id" :value="a.id">
                                {{ a.name }} ({{ a.member_id || a.role }})
                            </option>
                        </select>
                    </div>
                </div>

                <!-- Description -->
                <div class="space-y-1">
                    <label class="font-medium text-slate-700">Issue Description / Details</label>
                    <textarea 
                        v-model="ticketForm.description"
                        required
                        rows="3"
                        placeholder="Explain what happened or request support..."
                        class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 placeholder-slate-400 focus:outline-none focus:bg-white focus:border-indigo-600 resize-none transition-colors"
                    ></textarea>
                </div>

                <button 
                    type="submit"
                    :disabled="isSubmitting"
                    class="w-full py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-medium shadow-xs transition-colors flex items-center justify-center space-x-1.5"
                >
                    <Ticket class="w-3.5 h-3.5" />
                    <span>Create & Submit Ticket</span>
                </button>
            </form>

            <!-- TAB 2: SINGLE / DIRECT CHAT -->
            <form 
                v-else-if="activeTab === 'single'" 
                @submit.prevent="submitDirectChat"
                class="p-4 space-y-3 text-xs"
            >
                <!-- If Customer: can ONLY pick an assigned staff agent (never another customer) -->
                <div v-if="isCustomer" class="space-y-1">
                    <label class="font-medium text-slate-700">Choose Assigned Support Agent to Text</label>
                    <select 
                        v-model="directForm.target_user_id"
                        required
                        class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:bg-white focus:border-indigo-600 transition-colors"
                    >
                        <option value="" disabled>Select Support Agent</option>
                        <option v-for="agent in staffList" :key="agent.id" :value="agent.id">
                            {{ agent.name }} ({{ agent.member_id || 'Staff' }})
                        </option>
                    </select>
                    <p class="text-[10px] text-slate-500">
                        As a customer, you can message your assigned support staff.
                    </p>
                </div>

                <!-- If Staff: can pick from all users or input custom email -->
                <div v-else class="space-y-2">
                    <div class="space-y-1">
                        <label class="font-medium text-slate-700">Select User / Customer</label>
                        <select 
                            v-model="directForm.target_user_id"
                            class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:bg-white focus:border-indigo-600 transition-colors"
                        >
                            <option value="">Or type custom name/email below</option>
                            <option v-for="u in agents" :key="u.id" :value="u.id">
                                {{ u.name }} ({{ u.role === 'customer' ? 'Customer - ' + u.member_id : 'Staff - ' + u.member_id }})
                            </option>
                        </select>
                    </div>

                    <div v-if="!directForm.target_user_id" class="grid grid-cols-2 gap-2">
                        <div class="space-y-1">
                            <label class="font-medium text-slate-700">Contact Name</label>
                            <input 
                                v-model="directForm.name"
                                type="text" 
                                placeholder="Full Name" 
                                class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 placeholder-slate-400 focus:outline-none focus:bg-white focus:border-indigo-600 transition-colors"
                            />
                        </div>

                        <div class="space-y-1">
                            <label class="font-medium text-slate-700">Email</label>
                            <input 
                                v-model="directForm.email"
                                type="email" 
                                placeholder="email@domain.com" 
                                class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 placeholder-slate-400 focus:outline-none focus:bg-white focus:border-indigo-600 transition-colors"
                            />
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 border border-slate-200">
                    <div class="flex items-center space-x-2">
                        <Lock :class="['w-4 h-4', directForm.is_encrypted ? 'text-emerald-600' : 'text-slate-400']" />
                        <div>
                            <div class="font-medium text-slate-800">Encrypted Chat</div>
                            <div class="text-[10px] text-slate-500">Lock conversation with E2EE encryption</div>
                        </div>
                    </div>
                    <input 
                        v-model="directForm.is_encrypted" 
                        type="checkbox" 
                        class="w-4 h-4 text-emerald-600 rounded bg-white border-slate-300 cursor-pointer"
                    />
                </div>

                <div class="space-y-1">
                    <label class="font-medium text-slate-700">Initial Message (Optional)</label>
                    <input 
                        v-model="directForm.initial_message"
                        type="text"
                        placeholder="Hello, I have a question regarding..." 
                        class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 placeholder-slate-400 focus:outline-none focus:bg-white focus:border-indigo-600 transition-colors"
                    />
                </div>

                <button 
                    type="submit"
                    :disabled="isSubmitting"
                    class="w-full py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-medium shadow-xs transition-colors"
                >
                    Start Chat
                </button>
            </form>

            <!-- TAB 3: MAKE GROUP (Staff only) -->
            <form 
                v-else-if="activeTab === 'group' && !isCustomer" 
                @submit.prevent="submitGroupChat"
                class="p-4 space-y-3 text-xs"
            >
                <div class="space-y-1">
                    <label class="font-medium text-slate-700">Group Name</label>
                    <input 
                        v-model="groupForm.group_name"
                        type="text" 
                        required
                        placeholder="e.g. DevOps Support Team" 
                        class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 placeholder-slate-400 focus:outline-none focus:bg-white focus:border-indigo-600 transition-colors"
                    />
                </div>

                <div class="space-y-1">
                    <label class="font-medium text-slate-700">Description</label>
                    <input 
                        v-model="groupForm.group_description"
                        type="text" 
                        placeholder="Purpose of this group room" 
                        class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 placeholder-slate-400 focus:outline-none focus:bg-white focus:border-indigo-600 transition-colors"
                    />
                </div>

                <div class="space-y-1">
                    <label class="font-medium text-slate-700">Select Members</label>
                    <div class="max-h-32 overflow-y-auto space-y-1 p-2 bg-slate-50 rounded-xl border border-slate-200">
                        <div 
                            v-for="agent in staffList" 
                            :key="agent.id"
                            @click="toggleGroupMember(agent.id)"
                            :class="[
                                'flex items-center justify-between p-1.5 rounded-lg cursor-pointer transition-colors',
                                groupForm.member_ids.includes(agent.id) ? 'bg-indigo-50 border border-indigo-200 text-indigo-900 font-semibold' : 'hover:bg-slate-100 text-slate-700'
                            ]"
                        >
                            <div class="flex items-center space-x-2">
                                <img :src="agent.avatar" class="w-5 h-5 rounded-full object-cover" />
                                <span class="font-medium">{{ agent.name }}</span>
                                <span class="text-[10px] text-slate-500 font-mono">({{ agent.member_id || 'ID' }})</span>
                            </div>
                            <div 
                                :class="[
                                    'w-4 h-4 rounded-full flex items-center justify-center border',
                                    groupForm.member_ids.includes(agent.id) ? 'bg-indigo-600 border-indigo-600 text-white' : 'border-slate-300'
                                ]"
                            >
                                <Check v-if="groupForm.member_ids.includes(agent.id)" class="w-3 h-3 stroke-[3]" />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between p-2 rounded-xl bg-slate-50 border border-slate-200">
                    <div class="flex items-center space-x-2">
                        <Lock :class="['w-4 h-4', groupForm.is_encrypted ? 'text-emerald-600' : 'text-slate-400']" />
                        <div>
                            <div class="font-medium text-slate-800">Encrypted Group</div>
                            <div class="text-[10px] text-slate-500">All room messages encrypted</div>
                        </div>
                    </div>
                    <input 
                        v-model="groupForm.is_encrypted" 
                        type="checkbox" 
                        class="w-4 h-4 text-emerald-600 rounded bg-white border-slate-300 cursor-pointer"
                    />
                </div>

                <button 
                    type="submit"
                    :disabled="isSubmitting || !groupForm.group_name"
                    class="w-full py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-medium shadow-xs transition-colors"
                >
                    Create Group
                </button>
            </form>

            <!-- TAB 4: ADD USER (Staff or Customer) (Admin only) -->
            <form 
                v-else-if="activeTab === 'member' && !isCustomer && isAdmin" 
                @submit.prevent="submitNewMember"
                class="p-4 space-y-3 text-xs"
            >
                <!-- Role selector -->
                <div class="space-y-1">
                    <label class="font-medium text-slate-700">Account Type</label>
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            type="button"
                            @click="memberForm.role_type = 'staff'"
                            :class="[
                                'py-1.5 rounded-lg border font-medium flex items-center justify-center space-x-1 transition-colors',
                                memberForm.role_type === 'staff' ? 'bg-indigo-600 border-indigo-600 text-white shadow-xs' : 'bg-slate-50 border-slate-200 text-slate-600 hover:bg-slate-100'
                            ]"
                        >
                            <span>Support Staff</span>
                        </button>
                        <button
                            type="button"
                            @click="memberForm.role_type = 'customer'"
                            :class="[
                                'py-1.5 rounded-lg border font-medium flex items-center justify-center space-x-1 transition-colors',
                                memberForm.role_type === 'customer' ? 'bg-emerald-600 border-emerald-600 text-white shadow-xs' : 'bg-slate-50 border-slate-200 text-slate-600 hover:bg-slate-100'
                            ]"
                        >
                            <span>Customer</span>
                        </button>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div class="space-y-1">
                        <label class="font-medium text-slate-700">User ID / Code</label>
                        <input 
                            v-model="memberForm.member_id"
                            type="text" 
                            :placeholder="memberForm.role_type === 'customer' ? 'e.g. CUST-504' : 'e.g. EMP-104'" 
                            class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 placeholder-slate-400 focus:outline-none focus:bg-white focus:border-indigo-600 font-mono transition-colors"
                        />
                    </div>

                    <div v-if="memberForm.role_type === 'staff'" class="space-y-1">
                        <label class="font-medium text-slate-700">Role / Title</label>
                        <input 
                            v-model="memberForm.role"
                            type="text" 
                            placeholder="e.g. Senior Agent" 
                            class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 placeholder-slate-400 focus:outline-none focus:bg-white focus:border-indigo-600 transition-colors"
                        />
                    </div>

                    <!-- If customer, Admin assigns a Staff Agent -->
                    <div v-else class="space-y-1">
                        <label class="font-medium text-slate-700">Assign Staff Agent</label>
                        <select 
                            v-model="memberForm.assigned_agent_id"
                            class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:bg-white focus:border-emerald-600 transition-colors"
                        >
                            <option value="">Leave Unassigned</option>
                            <option v-for="agent in staffList" :key="agent.id" :value="agent.id">
                                {{ agent.name }} ({{ agent.member_id }})
                            </option>
                        </select>
                    </div>
                </div>

                <div class="space-y-1">
                    <label class="font-medium text-slate-700">Full Name</label>
                    <input 
                        v-model="memberForm.name"
                        type="text" 
                        required
                        placeholder="e.g. Daniel Morgan" 
                        class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 placeholder-slate-400 focus:outline-none focus:bg-white focus:border-indigo-600 transition-colors"
                    />
                </div>

                <div class="space-y-1">
                    <label class="font-medium text-slate-700">Email Address</label>
                    <input 
                        v-model="memberForm.email"
                        type="email" 
                        required
                        placeholder="daniel@example.com" 
                        class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 placeholder-slate-400 focus:outline-none focus:bg-white focus:border-indigo-600 transition-colors"
                    />
                </div>

                <div class="space-y-1">
                    <label class="font-medium text-slate-700">Password</label>
                    <input 
                        v-model="memberForm.password"
                        type="password" 
                        required
                        placeholder="Default: password123" 
                        class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 placeholder-slate-400 focus:outline-none focus:bg-white focus:border-indigo-600 transition-colors"
                    />
                </div>

                <!-- If customer, Admin assigns Project -->
                <div v-if="memberForm.role_type === 'customer'" class="space-y-1">
                    <label class="font-medium text-slate-700">Client's Project</label>
                    <select 
                        v-model="memberForm.project_id"
                        class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:bg-white focus:border-emerald-600 transition-colors"
                    >
                        <option value="">Select Project for this Client</option>
                        <option v-for="p in projects" :key="p.id" :value="p.id">
                            {{ p.name }}
                        </option>
                    </select>
                </div>

                <div v-if="memberForm.role_type === 'staff'" class="space-y-2 pt-1">
                    <div class="flex items-center space-x-2">
                        <input 
                            v-model="memberForm.is_team_lead" 
                            type="checkbox" 
                            id="is_team_lead_check_modal"
                            class="w-4 h-4 text-indigo-600 rounded bg-white border-slate-300 cursor-pointer"
                        />
                        <label for="is_team_lead_check_modal" class="text-xs text-indigo-800 font-semibold cursor-pointer">
                            Is Team Lead? (Can assign and delegate tasks to others)
                        </label>
                    </div>

                    <div class="flex items-center space-x-2">
                        <input 
                            v-model="memberForm.is_admin" 
                            type="checkbox" 
                            id="is_admin_check_modal"
                            class="w-4 h-4 text-amber-600 rounded bg-white border-slate-300 cursor-pointer"
                        />
                        <label for="is_admin_check_modal" class="text-xs text-amber-700 font-medium cursor-pointer">
                            Make this member a System Administrator
                        </label>
                    </div>

                    <div class="flex items-center space-x-2">
                        <input 
                            v-model="memberForm.is_customer" 
                            type="checkbox" 
                            id="is_customer_dual_check_modal"
                            class="w-4 h-4 text-purple-600 rounded bg-white border-slate-300 cursor-pointer"
                        />
                        <label for="is_customer_dual_check_modal" class="text-xs text-purple-700 font-medium cursor-pointer">
                            Grant Dual Role: Allow this user to also create tickets as a customer
                        </label>
                    </div>
                </div>

                <button 
                    type="submit"
                    :disabled="isSubmitting"
                    class="w-full py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-medium shadow-xs transition-colors flex items-center justify-center space-x-1.5"
                >
                    <UserPlus class="w-3.5 h-3.5" />
                    <span>Save Account</span>
                </button>
            </form>
        </div>
    </div>
</template>
