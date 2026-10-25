<script setup>
import { ref, computed } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { Lock, Mail, Shield, User, ArrowRight, UserCheck, Headset, Clock } from 'lucide-vue-next';

const props = defineProps({
    demoUsers: {
        type: Array,
        default: () => [],
    },
});

const staffUsers = computed(() => {
    return props.demoUsers.filter(u => u.is_admin || u.role !== 'customer');
});

const customerUsers = computed(() => {
    return props.demoUsers.filter(u => u.role === 'customer');
});

const form = useForm({
    email: 'admin@fluxdesk.io',
    password: 'password123',
});

function submit() {
    form.post('/login');
}

function quickLogin(user) {
    router.get(`/quick-login/${user.id}`);
}
</script>

<template>
    <Head title="Sign In — FluxDesk" />

    <div class="min-h-screen bg-slate-50 flex flex-col justify-center items-center p-4 selection:bg-indigo-500 selection:text-white">
        <!-- Brand -->
        <div class="mb-5 flex flex-col items-center">
            <div class="w-11 h-11 rounded-2xl bg-indigo-600 flex items-center justify-center text-white text-xl font-black shadow-md shadow-indigo-600/20 mb-2">
                ⚡
            </div>
            <h1 class="text-xl font-bold text-slate-900">FluxDesk</h1>
            <p class="text-xs text-slate-500">Support Desk & Live Messaging</p>
        </div>

        <div class="w-full max-w-md bg-white border border-slate-200 rounded-2xl p-6 shadow-xl space-y-5">
            <div class="flex items-start justify-between">
                <div>
                    <h2 class="text-base font-bold text-slate-900">Sign in to your account</h2>
                    <p class="text-xs text-slate-500 mt-0.5">Enter email and password or use 1-click test login below.</p>
                </div>
                <div class="flex items-center space-x-1 px-2 py-1 rounded-lg bg-indigo-50 border border-indigo-200 text-[10px] font-semibold text-indigo-700" title="Sessions expire after 6 hours of idle or activity">
                    <Clock class="w-3 h-3 text-indigo-600 shrink-0" />
                    <span>6h session</span>
                </div>
            </div>

            <form @submit.prevent="submit" class="space-y-3.5">
                <div class="space-y-1">
                    <label class="text-xs font-semibold text-slate-700">Email Address</label>
                    <div class="relative flex items-center">
                        <Mail class="w-4 h-4 text-slate-400 absolute left-3 pointer-events-none" />
                        <input 
                            v-model="form.email"
                            type="email" 
                            required
                            placeholder="you@fluxdesk.io" 
                            class="w-full pl-9 pr-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs text-slate-900 placeholder-slate-400 focus:bg-white focus:outline-none focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600 transition-colors"
                        />
                    </div>
                    <div v-if="form.errors.email" class="text-xs text-rose-500 mt-1">{{ form.errors.email }}</div>
                </div>

                <div class="space-y-1">
                    <label class="text-xs font-semibold text-slate-700">Password</label>
                    <div class="relative flex items-center">
                        <Lock class="w-4 h-4 text-slate-400 absolute left-3 pointer-events-none" />
                        <input 
                            v-model="form.password"
                            type="password" 
                            required
                            placeholder="••••••••" 
                            class="w-full pl-9 pr-3 py-2 bg-slate-50 border border-slate-300 rounded-xl text-xs text-slate-900 placeholder-slate-400 focus:bg-white focus:outline-none focus:border-indigo-600 focus:ring-1 focus:ring-indigo-600 transition-colors"
                        />
                    </div>
                </div>

                <button 
                    type="submit"
                    :disabled="form.processing"
                    class="w-full py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold text-xs shadow-md shadow-indigo-600/20 transition-all flex items-center justify-center space-x-1.5"
                >
                    <span>Sign In</span>
                    <ArrowRight class="w-3.5 h-3.5" />
                </button>
            </form>

            <!-- Quick Demo Login Switcher -->
            <div class="pt-4 border-t border-slate-100 space-y-3">
                <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">
                    ⚡ 1-Click Quick Login
                </div>

                <!-- 1. Staff Section -->
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-wider text-indigo-600 flex items-center space-x-1 mb-1.5">
                        <Headset class="w-3 h-3" />
                        <span>Support Staff & Admins</span>
                    </div>
                    <div class="space-y-1">
                        <div 
                            v-for="u in staffUsers" 
                            :key="u.id"
                            @click="quickLogin(u)"
                            class="p-2 rounded-xl bg-slate-50 hover:bg-indigo-50/60 border border-slate-200 hover:border-indigo-200 cursor-pointer flex items-center justify-between transition-colors group"
                        >
                            <div class="flex items-center space-x-2">
                                <img :src="u.avatar" class="w-7 h-7 rounded-full object-cover ring-1 ring-slate-200" />
                                <div>
                                    <div class="text-xs font-bold text-slate-800 group-hover:text-indigo-600">
                                        {{ u.name }}
                                    </div>
                                    <div class="text-[10px] text-slate-500 font-mono">
                                        <span v-if="u.is_admin" class="text-amber-600 font-bold">[Admin • Setup & Control] </span>
                                        <span v-else-if="u.is_team_lead" class="text-indigo-600 font-bold">[Team Lead • Can Assign] </span>
                                        <span v-else class="text-slate-500 font-semibold">[Staff Employee] </span>
                                        <span>ID: {{ u.member_id }}</span>
                                    </div>
                                </div>
                            </div>
                            <span class="text-[10px] text-indigo-600 font-semibold group-hover:underline">
                                Login &rarr;
                            </span>
                        </div>
                    </div>
                </div>

                <!-- 2. Customer Section -->
                <div>
                    <div class="text-[10px] font-bold uppercase tracking-wider text-emerald-600 flex items-center space-x-1 mb-1.5">
                        <UserCheck class="w-3 h-3" />
                        <span>Clients / Customers</span>
                    </div>
                    <div class="space-y-1">
                        <div 
                            v-for="u in customerUsers" 
                            :key="u.id"
                            @click="quickLogin(u)"
                            class="p-2 rounded-xl bg-slate-50 hover:bg-emerald-50/60 border border-slate-200 hover:border-emerald-200 cursor-pointer flex items-center justify-between transition-colors group"
                        >
                            <div class="flex items-center space-x-2">
                                <img :src="u.avatar" class="w-7 h-7 rounded-full object-cover ring-1 ring-slate-200" />
                                <div>
                                    <div class="text-xs font-bold text-slate-800 group-hover:text-emerald-700">
                                        {{ u.name }}
                                    </div>
                                    <div class="text-[10px] text-slate-500 font-mono">
                                        <span class="text-emerald-600 font-bold">[Client] </span>
                                        <span v-if="u.project" class="text-indigo-600 font-semibold">[{{ u.project.name }}] </span>
                                        <span>ID: {{ u.member_id }}</span>
                                    </div>
                                </div>
                            </div>
                            <span class="text-[10px] text-emerald-600 font-semibold group-hover:underline">
                                Login &rarr;
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
