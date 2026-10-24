<script setup>
import { ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { Lock, Mail, Shield, User, ArrowRight } from 'lucide-vue-next';

const props = defineProps({
    demoUsers: {
        type: Array,
        default: () => [],
    },
});

const form = useForm({
    email: 'alex@fluxdesk.io',
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

    <div class="min-h-screen bg-slate-950 flex flex-col justify-center items-center p-4 selection:bg-indigo-500 selection:text-white">
        <!-- Brand -->
        <div class="mb-6 flex flex-col items-center">
            <div class="w-12 h-12 rounded-2xl bg-indigo-600 flex items-center justify-center text-white text-2xl font-black shadow-lg shadow-indigo-600/30 mb-2">
                ⚡
            </div>
            <h1 class="text-xl font-bold text-slate-100">FluxDesk</h1>
            <p class="text-xs text-slate-400">Real-Time Messaging & Workspace</p>
        </div>

        <div class="w-full max-w-sm bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-2xl space-y-5">
            <div>
                <h2 class="text-base font-bold text-slate-100">Sign in to your account</h2>
                <p class="text-xs text-slate-400 mt-0.5">Enter your email and password or use quick sign-in below.</p>
            </div>

            <form @submit.prevent="submit" class="space-y-4">
                <div class="space-y-1">
                    <label class="text-xs font-semibold text-slate-300">Email Address</label>
                    <div class="relative flex items-center">
                        <Mail class="w-4 h-4 text-slate-500 absolute left-3 pointer-events-none" />
                        <input 
                            v-model="form.email"
                            type="email" 
                            required
                            placeholder="you@fluxdesk.io" 
                            class="w-full pl-9 pr-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                        />
                    </div>
                    <div v-if="form.errors.email" class="text-xs text-rose-400 mt-1">{{ form.errors.email }}</div>
                </div>

                <div class="space-y-1">
                    <label class="text-xs font-semibold text-slate-300">Password</label>
                    <div class="relative flex items-center">
                        <Lock class="w-4 h-4 text-slate-500 absolute left-3 pointer-events-none" />
                        <input 
                            v-model="form.password"
                            type="password" 
                            required
                            placeholder="••••••••" 
                            class="w-full pl-9 pr-3 py-2 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-200 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                        />
                    </div>
                </div>

                <button 
                    type="submit"
                    :disabled="form.processing"
                    class="w-full py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-xs shadow-md shadow-indigo-600/30 transition-all flex items-center justify-center space-x-1.5"
                >
                    <span>Sign In</span>
                    <ArrowRight class="w-3.5 h-3.5" />
                </button>
            </form>

            <!-- Quick Demo Login Switcher -->
            <div class="pt-4 border-t border-slate-800 space-y-2">
                <div class="text-[11px] font-bold uppercase tracking-wider text-slate-400">
                    Quick Sign-In (1-Click Switch)
                </div>
                <div class="space-y-1.5">
                    <div 
                        v-for="u in demoUsers" 
                        :key="u.id"
                        @click="quickLogin(u)"
                        class="p-2 rounded-xl bg-slate-950 hover:bg-slate-800/80 border border-slate-800 cursor-pointer flex items-center justify-between transition-colors group"
                    >
                        <div class="flex items-center space-x-2.5">
                            <img :src="u.avatar" class="w-7 h-7 rounded-full object-cover ring-1 ring-slate-800" />
                            <div>
                                <div class="text-xs font-bold text-slate-200 group-hover:text-indigo-300">
                                    {{ u.name }}
                                </div>
                                <div class="text-[10px] text-slate-400 font-mono flex items-center space-x-1">
                                    <span v-if="u.is_admin" class="text-amber-400 font-semibold">[Admin]</span>
                                    <span>ID: {{ u.member_id || 'EMP-' + u.id }}</span>
                                </div>
                            </div>
                        </div>
                        <span class="text-[10px] text-indigo-400 font-semibold group-hover:underline">
                            Login &rarr;
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
