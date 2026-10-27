<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { 
    Lock, 
    Mail, 
    Shield, 
    User, 
    ArrowRight, 
    UserCheck, 
    Headset, 
    Clock, 
    Briefcase, 
    Building, 
    Sparkles, 
    Check, 
    Zap,
    KeyRound,
    Crown,
    ChevronDown,
    ChevronUp
} from 'lucide-vue-next';

const props = defineProps({
    adminAccount: {
        type: Object,
        default: null,
    },
    teamLeadAccount: {
        type: Object,
        default: null,
    },
    employeeAccount: {
        type: Object,
        default: null,
    },
    clientAccount: {
        type: Object,
        default: null,
    },
    demoUsers: {
        type: Array,
        default: () => [],
    },
});

// 1. Admin Account
const adminUser = computed(() => {
    return props.adminAccount || props.demoUsers.find(u => u.is_admin || u.role === 'admin') || {
        name: 'Admin User',
        email: 'admin@fluxdesk.io',
        role: 'System Administrator',
        member_id: 'ADM-001',
        avatar: 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&h=150&fit=crop',
    };
});

// 2. Team Lead Account
const teamLeadUser = computed(() => {
    return props.teamLeadAccount || props.demoUsers.find(u => !u.is_admin && u.is_team_lead) || {
        name: 'User 1 (Team Lead)',
        email: 'user1@fluxdesk.io',
        role: 'Senior Team Lead',
        member_id: 'EMP-001',
        avatar: 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=150&h=150&fit=crop',
    };
});

// 3. Regular Employee Account
const employeeUser = computed(() => {
    return props.employeeAccount || props.demoUsers.find(u => !u.is_admin && !u.is_customer && !u.is_team_lead && u.role !== 'customer') || {
        name: 'User 2 (Employee)',
        email: 'user2@fluxdesk.io',
        role: 'Full-Stack Engineer',
        member_id: 'EMP-002',
        avatar: 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=150&h=150&fit=crop',
    };
});

// 4. Client Account
const clientUser = computed(() => {
    return props.clientAccount || props.demoUsers.find(u => u.is_customer || u.role === 'customer') || {
        name: 'Customer 1 (Acme Corp)',
        email: 'customer1@fluxdesk.io',
        role: 'Customer',
        member_id: 'CUST-001',
        avatar: 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=150&h=150&fit=crop',
    };
});

// Active selected account for visual indicator
const selectedAccountId = ref(adminUser.value?.id || null);

const form = useForm({
    email: adminUser.value?.email || 'admin@fluxdesk.io',
    password: 'password123',
});

function submit() {
    form.post('/login');
}

function quickLogin(user) {
    if (!user || !user.id) return;
    router.get(`/quick-login/${user.id}`);
}

function autofillAccount(user) {
    if (!user) return;
    selectedAccountId.value = user.id;
    form.email = user.email;
    form.password = 'password123';
}
</script>

<template>
    <Head title="Sign In — FluxDesk" />

    <div class="min-h-screen bg-slate-100/80 flex flex-col justify-center items-center p-4 sm:p-6 lg:p-8 font-sans selection:bg-indigo-500 selection:text-white">
        <!-- Brand Header -->
        <div class="mb-5 flex flex-col items-center text-center">
            <div class="w-11 h-11 rounded-2xl bg-gradient-to-tr from-indigo-600 to-indigo-500 flex items-center justify-center text-white text-xl font-black shadow-lg shadow-indigo-600/30 mb-2">
                ⚡
            </div>
            <h1 class="text-2xl font-extrabold text-slate-900 tracking-tight">FluxDesk</h1>
            <p class="text-xs font-medium text-slate-500 mt-0.5">Customer Support, Task Management & Team Workspace</p>
        </div>

        <!-- Main Card Container: 2-Column Responsive Layout -->
        <div class="w-full max-w-4xl bg-white border border-slate-200/90 rounded-3xl shadow-xl shadow-slate-200/50 overflow-hidden grid grid-cols-1 lg:grid-cols-12">
            
            <!-- LEFT PANEL: SIGN IN FORM (lg:col-span-5) -->
            <div class="lg:col-span-5 p-6 sm:p-8 flex flex-col justify-between border-b lg:border-b-0 lg:border-r border-slate-100 bg-white">
                <div>
                    <div class="flex items-center justify-between mb-5">
                        <div>
                            <h2 class="text-lg font-bold text-slate-900">Sign In</h2>
                            <p class="text-xs text-slate-500 mt-0.5">Enter credentials or pick a demo account</p>
                        </div>
                        <div class="flex items-center space-x-1 px-2.5 py-1 rounded-lg bg-indigo-50 border border-indigo-100 text-[10px] font-semibold text-indigo-700" title="Sessions are preserved for 6 hours">
                            <Clock class="w-3 h-3 text-indigo-600" />
                            <span>6h session</span>
                        </div>
                    </div>

                    <form @submit.prevent="submit" class="space-y-3.5">
                        <!-- Email Input -->
                        <div class="space-y-1">
                            <label class="text-xs font-semibold text-slate-700">Email Address</label>
                            <div class="relative flex items-center">
                                <Mail class="w-4 h-4 text-slate-400 absolute left-3.5 pointer-events-none" />
                                <input 
                                    v-model="form.email"
                                    type="email" 
                                    required
                                    placeholder="user@fluxdesk.io" 
                                    class="w-full pl-10 pr-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 placeholder-slate-400 focus:bg-white focus:outline-none focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600 transition-colors"
                                />
                            </div>
                            <div v-if="form.errors.email" class="text-xs text-rose-500 mt-1 font-medium">{{ form.errors.email }}</div>
                        </div>

                        <!-- Password Input -->
                        <div class="space-y-1">
                            <div class="flex items-center justify-between">
                                <label class="text-xs font-semibold text-slate-700">Password</label>
                                <span class="text-[10px] text-slate-400 font-mono">Default: password123</span>
                            </div>
                            <div class="relative flex items-center">
                                <Lock class="w-4 h-4 text-slate-400 absolute left-3.5 pointer-events-none" />
                                <input 
                                    v-model="form.password"
                                    type="password" 
                                    required
                                    placeholder="••••••••••••" 
                                    class="w-full pl-10 pr-3.5 py-2.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-900 placeholder-slate-400 focus:bg-white focus:outline-none focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600 transition-colors"
                                />
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <button 
                            type="submit"
                            :disabled="form.processing"
                            class="w-full py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs shadow-md shadow-indigo-600/25 transition-all flex items-center justify-center space-x-2 active:scale-98 cursor-pointer mt-2"
                        >
                            <span>Sign In to FluxDesk</span>
                            <ArrowRight class="w-4 h-4" />
                        </button>
                    </form>
                </div>

                <!-- Footer Note -->
                <div class="pt-5 mt-5 border-t border-slate-100 text-center">
                    <p class="text-[11px] text-slate-400">
                        Click <strong>Login &rarr;</strong> on any role on the right for instant 1-click access.
                    </p>
                </div>
            </div>

            <!-- RIGHT PANEL: 4 DATABASE DEMO ACCOUNTS (lg:col-span-7) -->
            <div class="lg:col-span-7 p-5 sm:p-7 bg-slate-50/70 flex flex-col justify-between space-y-3.5">
                <div>
                    <!-- Section Title -->
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex items-center space-x-2">
                            <div class="w-6 h-6 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center text-xs font-bold shadow-2xs">
                                ⚡
                            </div>
                            <h3 class="text-xs font-bold uppercase tracking-wider text-slate-800">
                                Database Demo Accounts
                            </h3>
                        </div>
                        <span class="text-[10px] font-semibold text-slate-500 bg-white px-2 py-0.5 rounded-full border border-slate-200 shadow-2xs">
                            Loaded from Database
                        </span>
                    </div>

                    <!-- THE 4 ROLE ACCOUNTS CARDS LIST -->
                    <div class="space-y-2.5">
                        
                        <!-- ============================================== -->
                        <!-- 1. ADMIN ACCOUNT                               -->
                        <!-- ============================================== -->
                        <div 
                            :class="[
                                'p-3 rounded-2xl border transition-all duration-200 relative group bg-white shadow-2xs',
                                selectedAccountId === adminUser?.id 
                                    ? 'border-purple-500 ring-2 ring-purple-500/20 shadow-xs' 
                                    : 'border-slate-200/90 hover:border-purple-300'
                            ]"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex items-center space-x-2.5">
                                    <div class="relative">
                                        <img 
                                            :src="adminUser.avatar || 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=150&h=150&fit=crop'" 
                                            class="w-9 h-9 rounded-full object-cover ring-2 ring-purple-200 shadow-2xs" 
                                        />
                                        <span class="absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full bg-purple-600 border-2 border-white flex items-center justify-center text-[7px] text-white font-black">
                                            ✓
                                        </span>
                                    </div>
                                    <div>
                                        <div class="flex items-center space-x-1.5 flex-wrap">
                                            <span class="text-xs font-bold text-slate-900">{{ adminUser.name }}</span>
                                            <span class="px-2 py-0.2 rounded-full text-[9px] font-bold bg-purple-50 text-purple-700 border border-purple-200 flex items-center space-x-1">
                                                <Shield class="w-2.5 h-2.5" />
                                                <span>Admin</span>
                                            </span>
                                        </div>
                                        <div class="text-[11px] text-slate-600 font-medium font-mono mt-0.5">
                                            {{ adminUser.email }}
                                        </div>
                                        <div class="text-[10px] text-slate-400 flex items-center space-x-1.5">
                                            <span>{{ adminUser.designation || adminUser.role || 'System Administrator' }}</span>
                                            <span v-if="adminUser.member_id" class="text-slate-300">•</span>
                                            <span v-if="adminUser.member_id" class="font-mono text-[9px]">{{ adminUser.member_id }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="flex items-center space-x-1.5 shrink-0">
                                    <button
                                        @click="autofillAccount(adminUser)"
                                        class="px-2 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-[10px] font-semibold transition-colors"
                                        title="Auto-fill form"
                                    >
                                        Fill
                                    </button>
                                    <button
                                        @click="quickLogin(adminUser)"
                                        class="px-2.5 py-1 rounded-lg bg-purple-600 hover:bg-purple-700 text-white text-[11px] font-bold shadow-2xs transition-all flex items-center space-x-1"
                                        title="1-Click Instant Login"
                                    >
                                        <Zap class="w-3 h-3" />
                                        <span>Login &rarr;</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- ============================================== -->
                        <!-- 2. TEAM LEAD ACCOUNT                           -->
                        <!-- ============================================== -->
                        <div 
                            :class="[
                                'p-3 rounded-2xl border transition-all duration-200 relative group bg-white shadow-2xs',
                                selectedAccountId === teamLeadUser?.id 
                                    ? 'border-indigo-500 ring-2 ring-indigo-500/20 shadow-xs' 
                                    : 'border-slate-200/90 hover:border-indigo-300'
                            ]"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex items-center space-x-2.5">
                                    <div class="relative">
                                        <img 
                                            :src="teamLeadUser.avatar || 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=150&h=150&fit=crop'" 
                                            class="w-9 h-9 rounded-full object-cover ring-2 ring-indigo-200 shadow-2xs" 
                                        />
                                        <span class="absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full bg-indigo-600 border-2 border-white flex items-center justify-center text-[7px] text-white font-black">
                                            ✓
                                        </span>
                                    </div>
                                    <div>
                                        <div class="flex items-center space-x-1.5 flex-wrap">
                                            <span class="text-xs font-bold text-slate-900">{{ teamLeadUser.name }}</span>
                                            <span class="px-2 py-0.2 rounded-full text-[9px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200 flex items-center space-x-1">
                                                <Crown class="w-2.5 h-2.5 text-indigo-600" />
                                                <span>Team Lead</span>
                                            </span>
                                        </div>
                                        <div class="text-[11px] text-slate-600 font-medium font-mono mt-0.5">
                                            {{ teamLeadUser.email }}
                                        </div>
                                        <div class="text-[10px] text-slate-400 flex items-center space-x-1.5">
                                            <span>{{ teamLeadUser.designation || teamLeadUser.role || 'Senior Team Lead' }}</span>
                                            <span v-if="teamLeadUser.member_id" class="text-slate-300">•</span>
                                            <span v-if="teamLeadUser.member_id" class="font-mono text-[9px]">{{ teamLeadUser.member_id }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="flex items-center space-x-1.5 shrink-0">
                                    <button
                                        @click="autofillAccount(teamLeadUser)"
                                        class="px-2 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-[10px] font-semibold transition-colors"
                                        title="Auto-fill form"
                                    >
                                        Fill
                                    </button>
                                    <button
                                        @click="quickLogin(teamLeadUser)"
                                        class="px-2.5 py-1 rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white text-[11px] font-bold shadow-2xs transition-all flex items-center space-x-1"
                                        title="1-Click Instant Login"
                                    >
                                        <Zap class="w-3 h-3" />
                                        <span>Login &rarr;</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- ============================================== -->
                        <!-- 3. EMPLOYEE ACCOUNT                            -->
                        <!-- ============================================== -->
                        <div 
                            :class="[
                                'p-3 rounded-2xl border transition-all duration-200 relative group bg-white shadow-2xs',
                                selectedAccountId === employeeUser?.id 
                                    ? 'border-blue-500 ring-2 ring-blue-500/20 shadow-xs' 
                                    : 'border-slate-200/90 hover:border-blue-300'
                            ]"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex items-center space-x-2.5">
                                    <div class="relative">
                                        <img 
                                            :src="employeeUser.avatar || 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=150&h=150&fit=crop'" 
                                            class="w-9 h-9 rounded-full object-cover ring-2 ring-blue-200 shadow-2xs" 
                                        />
                                        <span class="absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full bg-blue-600 border-2 border-white flex items-center justify-center text-[7px] text-white font-black">
                                            ✓
                                        </span>
                                    </div>
                                    <div>
                                        <div class="flex items-center space-x-1.5 flex-wrap">
                                            <span class="text-xs font-bold text-slate-900">{{ employeeUser.name }}</span>
                                            <span class="px-2 py-0.2 rounded-full text-[9px] font-bold bg-blue-50 text-blue-700 border border-blue-200 flex items-center space-x-1">
                                                <Briefcase class="w-2.5 h-2.5" />
                                                <span>Employee</span>
                                            </span>
                                        </div>
                                        <div class="text-[11px] text-slate-600 font-medium font-mono mt-0.5">
                                            {{ employeeUser.email }}
                                        </div>
                                        <div class="text-[10px] text-slate-400 flex items-center space-x-1.5">
                                            <span>{{ employeeUser.designation || employeeUser.role || 'Staff Engineer' }}</span>
                                            <span v-if="employeeUser.member_id" class="text-slate-300">•</span>
                                            <span v-if="employeeUser.member_id" class="font-mono text-[9px]">{{ employeeUser.member_id }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="flex items-center space-x-1.5 shrink-0">
                                    <button
                                        @click="autofillAccount(employeeUser)"
                                        class="px-2 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-[10px] font-semibold transition-colors"
                                        title="Auto-fill form"
                                    >
                                        Fill
                                    </button>
                                    <button
                                        @click="quickLogin(employeeUser)"
                                        class="px-2.5 py-1 rounded-lg bg-blue-600 hover:bg-blue-700 text-white text-[11px] font-bold shadow-2xs transition-all flex items-center space-x-1"
                                        title="1-Click Instant Login"
                                    >
                                        <Zap class="w-3 h-3" />
                                        <span>Login &rarr;</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- ============================================== -->
                        <!-- 4. CLIENT ACCOUNT                              -->
                        <!-- ============================================== -->
                        <div 
                            :class="[
                                'p-3 rounded-2xl border transition-all duration-200 relative group bg-white shadow-2xs',
                                selectedAccountId === clientUser?.id 
                                    ? 'border-emerald-500 ring-2 ring-emerald-500/20 shadow-xs' 
                                    : 'border-slate-200/90 hover:border-emerald-300'
                            ]"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex items-center space-x-2.5">
                                    <div class="relative">
                                        <img 
                                            :src="clientUser.avatar || 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?w=150&h=150&fit=crop'" 
                                            class="w-9 h-9 rounded-full object-cover ring-2 ring-emerald-200 shadow-2xs" 
                                        />
                                        <span class="absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full bg-emerald-600 border-2 border-white flex items-center justify-center text-[7px] text-white font-black">
                                            ✓
                                        </span>
                                    </div>
                                    <div>
                                        <div class="flex items-center space-x-1.5 flex-wrap">
                                            <span class="text-xs font-bold text-slate-900">{{ clientUser.name }}</span>
                                            <span class="px-2 py-0.2 rounded-full text-[9px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 flex items-center space-x-1">
                                                <UserCheck class="w-2.5 h-2.5" />
                                                <span>Client</span>
                                            </span>
                                        </div>
                                        <div class="text-[11px] text-slate-600 font-medium font-mono mt-0.5">
                                            {{ clientUser.email }}
                                        </div>
                                        <div class="text-[10px] text-slate-400 flex items-center space-x-1.5">
                                            <span>{{ clientUser.department || 'Client Representative' }}</span>
                                            <span v-if="clientUser.member_id" class="text-slate-300">•</span>
                                            <span v-if="clientUser.member_id" class="font-mono text-[9px]">{{ clientUser.member_id }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="flex items-center space-x-1.5 shrink-0">
                                    <button
                                        @click="autofillAccount(clientUser)"
                                        class="px-2 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-[10px] font-semibold transition-colors"
                                        title="Auto-fill form"
                                    >
                                        Fill
                                    </button>
                                    <button
                                        @click="quickLogin(clientUser)"
                                        class="px-2.5 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-[11px] font-bold shadow-2xs transition-all flex items-center space-x-1"
                                        title="1-Click Instant Login"
                                    >
                                        <Zap class="w-3 h-3" />
                                        <span>Login &rarr;</span>
                                    </button>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- Credential Info Strip -->
                <div class="pt-2.5 border-t border-slate-200/70 flex items-center justify-between text-[11px] text-slate-500">
                    <div class="flex items-center space-x-1.5">
                        <KeyRound class="w-3.5 h-3.5 text-slate-400" />
                        <span>All demo passwords: <code class="font-mono font-bold text-slate-800 bg-slate-100 px-1 py-0.5 rounded text-[10px]">password123</code></span>
                    </div>

                    <span class="text-indigo-600 font-medium text-[10px]">
                        1-Click Instant Login
                    </span>
                </div>
            </div>

        </div>
    </div>
</template>
