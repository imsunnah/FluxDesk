<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { 
    MessageSquare, 
    Users, 
    UserPlus, 
    Lock, 
    X, 
    Check, 
    ShieldCheck,
    Sparkles
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
    activeConversationId: {
        type: [Number, String, null],
        default: null,
    },
    currentAgent: {
        type: Object,
        default: () => ({}),
    },
});

const emit = defineEmits(['close']);

const activeTab = ref('single'); // 'single' | 'group' | 'member'

const directForm = ref({
    name: '',
    email: '',
    is_encrypted: false,
    initial_message: '',
});

const groupForm = ref({
    group_name: '',
    group_description: '',
    member_ids: [],
    is_encrypted: false,
});

const memberForm = ref({
    member_id: '',
    name: '',
    email: '',
    role: 'Employee',
    password: 'password123',
    is_admin: false,
    add_to_current: true,
});

const isSubmitting = ref(false);

function submitDirectChat() {
    if (!directForm.value.name || !directForm.value.email) return;
    isSubmitting.value = true;
    router.post('/conversations/direct', directForm.value, {
        onFinish: () => {
            isSubmitting.value = false;
            emit('close');
            directForm.value = { name: '', email: '', is_encrypted: false, initial_message: '' };
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
    router.post('/members', {
        member_id: memberForm.value.member_id,
        name: memberForm.value.name,
        email: memberForm.value.email,
        role: memberForm.value.role,
        password: memberForm.value.password,
        is_admin: memberForm.value.is_admin,
        conversation_id: memberForm.value.add_to_current ? props.activeConversationId : null,
    }, {
        onFinish: () => {
            isSubmitting.value = false;
            emit('close');
            memberForm.value = {
                member_id: '',
                name: '',
                email: '',
                role: 'Employee',
                password: 'password123',
                is_admin: false,
                add_to_current: true,
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
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm"
    >
        <div class="w-full max-w-md rounded-2xl bg-slate-900 border border-slate-800 shadow-2xl overflow-hidden flex flex-col">
            <!-- Modal Header -->
            <div class="px-5 py-3.5 border-b border-slate-800 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-slate-100 flex items-center space-x-2">
                        <Sparkles class="w-4 h-4 text-indigo-400" />
                        <span>Start Chat or Add Member</span>
                    </h3>
                    <p class="text-[11px] text-slate-400">Choose action below</p>
                </div>
                <button 
                    @click="$emit('close')"
                    class="p-1 rounded-lg text-slate-400 hover:text-slate-200 hover:bg-slate-800"
                >
                    <X class="w-4 h-4" />
                </button>
            </div>

            <!-- Tab Switcher -->
            <div class="grid grid-cols-3 p-1 mx-4 mt-3 bg-slate-950 rounded-xl border border-slate-800 text-xs font-semibold">
                <button
                    @click="activeTab = 'single'"
                    :class="[
                        'py-1.5 px-2 rounded-lg flex items-center justify-center space-x-1 transition-all',
                        activeTab === 'single' ? 'bg-indigo-600 text-white shadow' : 'text-slate-400 hover:text-slate-200'
                    ]"
                >
                    <MessageSquare class="w-3.5 h-3.5" />
                    <span>Single Chat</span>
                </button>

                <button
                    @click="activeTab = 'group'"
                    :class="[
                        'py-1.5 px-2 rounded-lg flex items-center justify-center space-x-1 transition-all',
                        activeTab === 'group' ? 'bg-indigo-600 text-white shadow' : 'text-slate-400 hover:text-slate-200'
                    ]"
                >
                    <Users class="w-3.5 h-3.5" />
                    <span>New Group</span>
                </button>

                <button
                    @click="activeTab = 'member'"
                    :class="[
                        'py-1.5 px-2 rounded-lg flex items-center justify-center space-x-1 transition-all',
                        activeTab === 'member' ? 'bg-indigo-600 text-white shadow' : 'text-slate-400 hover:text-slate-200'
                    ]"
                >
                    <UserPlus class="w-3.5 h-3.5" />
                    <span>Add Member</span>
                </button>
            </div>

            <!-- TAB 1: SINGLE CHAT -->
            <form 
                v-if="activeTab === 'single'" 
                @submit.prevent="submitDirectChat"
                class="p-4 space-y-3.5 text-xs"
            >
                <div class="space-y-1">
                    <label class="font-medium text-slate-300">Name</label>
                    <input 
                        v-model="directForm.name"
                        type="text" 
                        required
                        placeholder="Contact or Employee Name" 
                        class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-slate-200 focus:outline-none focus:border-indigo-500"
                    />
                </div>

                <div class="space-y-1">
                    <label class="font-medium text-slate-300">Email</label>
                    <input 
                        v-model="directForm.email"
                        type="email" 
                        required
                        placeholder="email@example.com" 
                        class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-slate-200 focus:outline-none focus:border-indigo-500"
                    />
                </div>

                <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-950 border border-slate-800">
                    <div class="flex items-center space-x-2">
                        <Lock :class="['w-4 h-4', directForm.is_encrypted ? 'text-emerald-400' : 'text-slate-400']" />
                        <div>
                            <div class="font-bold text-slate-200">Encrypted Chat (E2EE)</div>
                            <div class="text-[10px] text-slate-400">Lock chat with private encryption</div>
                        </div>
                    </div>
                    <input 
                        v-model="directForm.is_encrypted" 
                        type="checkbox" 
                        class="w-4 h-4 text-emerald-500 rounded bg-slate-900 border-slate-700 cursor-pointer"
                    />
                </div>

                <div class="space-y-1">
                    <label class="font-medium text-slate-300">Initial Message (Optional)</label>
                    <input 
                        v-model="directForm.initial_message"
                        type="text"
                        placeholder="Hello, how can I help you?" 
                        class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-slate-200 focus:outline-none focus:border-indigo-500"
                    />
                </div>

                <button 
                    type="submit"
                    :disabled="isSubmitting"
                    class="w-full py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold shadow transition-all"
                >
                    Start Chat
                </button>
            </form>

            <!-- TAB 2: MAKE GROUP -->
            <form 
                v-else-if="activeTab === 'group'" 
                @submit.prevent="submitGroupChat"
                class="p-4 space-y-3 text-xs"
            >
                <div class="space-y-1">
                    <label class="font-medium text-slate-300">Group Name</label>
                    <input 
                        v-model="groupForm.group_name"
                        type="text" 
                        required
                        placeholder="e.g. Sales Team or Tech Support" 
                        class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-slate-200 focus:outline-none focus:border-indigo-500"
                    />
                </div>

                <div class="space-y-1">
                    <label class="font-medium text-slate-300">Description</label>
                    <input 
                        v-model="groupForm.group_description"
                        type="text" 
                        placeholder="What is this group for?" 
                        class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-slate-200 focus:outline-none focus:border-indigo-500"
                    />
                </div>

                <!-- Select Members -->
                <div class="space-y-1">
                    <label class="font-medium text-slate-300">Select Members</label>
                    <div class="max-h-36 overflow-y-auto space-y-1 p-2 bg-slate-950 rounded-xl border border-slate-800">
                        <div 
                            v-for="agent in agents" 
                            :key="agent.id"
                            @click="toggleGroupMember(agent.id)"
                            :class="[
                                'flex items-center justify-between p-1.5 rounded-lg cursor-pointer transition-colors',
                                groupForm.member_ids.includes(agent.id) ? 'bg-indigo-950 border border-indigo-500/40 text-white' : 'hover:bg-slate-900 text-slate-300'
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
                                    groupForm.member_ids.includes(agent.id) ? 'bg-indigo-600 border-indigo-500 text-white' : 'border-slate-700'
                                ]"
                            >
                                <Check v-if="groupForm.member_ids.includes(agent.id)" class="w-3 h-3 stroke-[3]" />
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-950 border border-slate-800">
                    <div class="flex items-center space-x-2">
                        <Lock :class="['w-4 h-4', groupForm.is_encrypted ? 'text-emerald-400' : 'text-slate-400']" />
                        <div>
                            <div class="font-bold text-slate-200">Encrypted Group</div>
                            <div class="text-[10px] text-slate-400">All room messages encrypted</div>
                        </div>
                    </div>
                    <input 
                        v-model="groupForm.is_encrypted" 
                        type="checkbox" 
                        class="w-4 h-4 text-emerald-500 rounded bg-slate-900 border-slate-700 cursor-pointer"
                    />
                </div>

                <button 
                    type="submit"
                    :disabled="isSubmitting || !groupForm.group_name"
                    class="w-full py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold shadow transition-all"
                >
                    Create Group
                </button>
            </form>

            <!-- TAB 3: ADD MEMBER / EMPLOYEE WITH ID -->
            <form 
                v-else-if="activeTab === 'member'" 
                @submit.prevent="submitNewMember"
                class="p-4 space-y-3 text-xs"
            >
                <div class="grid grid-cols-2 gap-2">
                    <div class="space-y-1">
                        <label class="font-medium text-slate-300">Member ID / Code</label>
                        <input 
                            v-model="memberForm.member_id"
                            type="text" 
                            placeholder="e.g. EMP-201" 
                            class="w-full px-3 py-1.5 bg-slate-950 border border-slate-800 rounded-xl text-slate-200 focus:outline-none focus:border-indigo-500 font-mono"
                        />
                    </div>

                    <div class="space-y-1">
                        <label class="font-medium text-slate-300">Role / Title</label>
                        <input 
                            v-model="memberForm.role"
                            type="text" 
                            placeholder="e.g. Support, Developer" 
                            class="w-full px-3 py-1.5 bg-slate-950 border border-slate-800 rounded-xl text-slate-200 focus:outline-none focus:border-indigo-500"
                        />
                    </div>
                </div>

                <div class="space-y-1">
                    <label class="font-medium text-slate-300">Full Name</label>
                    <input 
                        v-model="memberForm.name"
                        type="text" 
                        required
                        placeholder="e.g. Rachel Adams" 
                        class="w-full px-3 py-1.5 bg-slate-950 border border-slate-800 rounded-xl text-slate-200 focus:outline-none focus:border-indigo-500"
                    />
                </div>

                <div class="space-y-1">
                    <label class="font-medium text-slate-300">Email Address</label>
                    <input 
                        v-model="memberForm.email"
                        type="email" 
                        required
                        placeholder="rachel@fluxdesk.io" 
                        class="w-full px-3 py-1.5 bg-slate-950 border border-slate-800 rounded-xl text-slate-200 focus:outline-none focus:border-indigo-500"
                    />
                </div>

                <div class="space-y-1">
                    <label class="font-medium text-slate-300">Password</label>
                    <input 
                        v-model="memberForm.password"
                        type="password" 
                        required
                        placeholder="Default password123" 
                        class="w-full px-3 py-1.5 bg-slate-950 border border-slate-800 rounded-xl text-slate-200 focus:outline-none focus:border-indigo-500"
                    />
                </div>

                <!-- Admin checkbox -->
                <div class="flex items-center space-x-2 pt-1">
                    <input 
                        v-model="memberForm.is_admin" 
                        type="checkbox" 
                        id="is_admin_check"
                        class="w-4 h-4 text-amber-500 rounded bg-slate-900 border-slate-700 cursor-pointer"
                    />
                    <label for="is_admin_check" class="text-xs text-amber-300 font-semibold cursor-pointer">
                        Make this member a System Administrator
                    </label>
                </div>

                <button 
                    type="submit"
                    :disabled="isSubmitting"
                    class="w-full py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold shadow transition-all flex items-center justify-center space-x-1.5"
                >
                    <UserPlus class="w-3.5 h-3.5" />
                    <span>Save Member to Database</span>
                </button>
            </form>
        </div>
    </div>
</template>
