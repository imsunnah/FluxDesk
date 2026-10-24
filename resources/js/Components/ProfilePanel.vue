<script setup>
import { computed } from 'vue';
import { 
    User, 
    Mail, 
    Phone, 
    MapPin, 
    Building, 
    Calendar, 
    Tag, 
    UserCheck, 
    Activity, 
    Clock, 
    Shield, 
    History,
    Sparkles,
    CheckCircle2
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
    isOpen: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['update-conversation', 'close']);

function formatDate(isoString) {
    if (!isoString) return 'Recent';
    const date = new Date(isoString);
    return date.toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric' });
}

function handleAgentChange(e) {
    emit('update-conversation', {
        assigned_agent_id: e.target.value ? Number(e.target.value) : null,
    });
}

function handlePriorityChange(e) {
    emit('update-conversation', {
        priority: e.target.value,
    });
}

function handleStatusChange(status) {
    emit('update-conversation', {
        status: status,
    });
}

// Realistic mock ticket history for customer
const mockTicketHistory = [
    { id: 'FD-1092', subject: 'Reverb WebSocket cluster connection latency', status: 'resolved', date: '3 days ago' },
    { id: 'FD-1048', subject: 'Inertia Vue 3 asset build configuration', status: 'resolved', date: '2 weeks ago' },
    { id: 'FD-994', subject: 'Enterprise Redis Queue supervisor scaling', status: 'resolved', date: '1 month ago' },
];
</script>

<template>
    <aside 
        v-if="isOpen && conversation"
        class="w-72 lg:w-80 h-full bg-slate-900 border-l border-slate-800/80 flex flex-col overflow-y-auto select-none shrink-0 z-10 transition-all duration-200"
    >
        <!-- Header -->
        <div class="p-4 border-b border-slate-800/80 flex items-center justify-between bg-slate-900/90">
            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-300 flex items-center space-x-1.5">
                <User class="w-3.5 h-3.5 text-indigo-400" />
                <span>Customer & Ticket Details</span>
            </h4>
            <button 
                @click="$emit('close')"
                class="text-slate-400 hover:text-slate-200 text-xs p-1 rounded-md hover:bg-slate-800"
            >
                ✕
            </button>
        </div>

        <div class="p-4 space-y-6 flex-1">
            <!-- Customer Profile Card -->
            <div class="flex flex-col items-center text-center p-4 rounded-2xl bg-gradient-to-b from-slate-800/60 to-slate-950/60 border border-slate-800">
                <div class="relative mb-3">
                    <img 
                        :src="conversation.customer_avatar || 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=120&h=120&fit=crop'" 
                        :alt="conversation.customer_name"
                        class="w-16 h-16 rounded-full object-cover ring-2 ring-indigo-500/40 shadow-xl" 
                    />
                    <span class="absolute bottom-0 right-0 w-3.5 h-3.5 rounded-full bg-emerald-500 ring-2 ring-slate-900"></span>
                </div>

                <h3 class="text-sm font-bold text-slate-100">
                    {{ conversation.customer_name }}
                </h3>
                <p class="text-xs text-indigo-400 font-medium mt-0.5">
                    {{ conversation.customer_company || 'Verified Customer' }}
                </p>

                <!-- Contact details table list -->
                <div class="w-full mt-4 space-y-2 text-left pt-3 border-t border-slate-800/80 text-xs">
                    <div class="flex items-center space-x-2 text-slate-300">
                        <Mail class="w-3.5 h-3.5 text-slate-500 shrink-0" />
                        <span class="truncate text-slate-300">{{ conversation.customer_email }}</span>
                    </div>
                    <div class="flex items-center space-x-2 text-slate-300" v-if="conversation.customer_phone">
                        <Phone class="w-3.5 h-3.5 text-slate-500 shrink-0" />
                        <span>{{ conversation.customer_phone }}</span>
                    </div>
                    <div class="flex items-center space-x-2 text-slate-300" v-if="conversation.customer_location">
                        <MapPin class="w-3.5 h-3.5 text-slate-500 shrink-0" />
                        <span>{{ conversation.customer_location }}</span>
                    </div>
                </div>
            </div>

            <!-- Ticket Metadata Controls -->
            <div class="space-y-4">
                <h5 class="text-[11px] font-bold uppercase tracking-wider text-slate-400 flex items-center space-x-1.5">
                    <Tag class="w-3 h-3 text-indigo-400" />
                    <span>Ticket Management</span>
                </h5>

                <!-- Assigned Agent Dropdown -->
                <div class="space-y-1.5">
                    <label class="text-[11px] font-medium text-slate-400">Assigned Agent</label>
                    <div class="relative">
                        <select 
                            :value="conversation.assigned_agent_id || ''"
                            @change="handleAgentChange"
                            class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                        >
                            <option value="">Unassigned</option>
                            <option 
                                v-for="agent in agents" 
                                :key="agent.id" 
                                :value="agent.id"
                            >
                                {{ agent.name }} ({{ agent.role }})
                            </option>
                        </select>
                    </div>
                </div>

                <!-- Priority Selector -->
                <div class="space-y-1.5">
                    <label class="text-[11px] font-medium text-slate-400">Ticket Priority</label>
                    <select 
                        :value="conversation.priority"
                        @change="handlePriorityChange"
                        class="w-full bg-slate-950 border border-slate-800 rounded-xl px-3 py-2 text-xs text-slate-200 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                    >
                        <option value="low">🟢 Low Priority</option>
                        <option value="medium">🟡 Medium Priority</option>
                        <option value="high">🔴 High Priority</option>
                    </select>
                </div>

                <!-- Status Toggle Buttons -->
                <div class="space-y-1.5">
                    <label class="text-[11px] font-medium text-slate-400">Lifecycle Status</label>
                    <div class="grid grid-cols-3 gap-1.5">
                        <button
                            v-for="st in ['open', 'pending', 'resolved']"
                            :key="st"
                            @click="handleStatusChange(st)"
                            :class="[
                                'py-1.5 rounded-lg text-xs font-semibold capitalize transition-all border',
                                conversation.status === st
                                    ? (st === 'open' ? 'bg-emerald-600/30 text-emerald-300 border-emerald-500/50' : (st === 'pending' ? 'bg-amber-600/30 text-amber-300 border-amber-500/50' : 'bg-slate-700/60 text-slate-200 border-slate-600'))
                                    : 'bg-slate-950/60 border-slate-800 text-slate-400 hover:text-slate-200'
                            ]"
                        >
                            {{ st }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Customer Ticket History -->
            <div class="space-y-3 pt-2 border-t border-slate-800/80">
                <h5 class="text-[11px] font-bold uppercase tracking-wider text-slate-400 flex items-center justify-between">
                    <span class="flex items-center space-x-1.5">
                        <History class="w-3 h-3 text-indigo-400" />
                        <span>Ticket History</span>
                    </span>
                    <span class="text-[10px] text-slate-500 font-mono">3 previous</span>
                </h5>

                <div class="space-y-2">
                    <div 
                        v-for="item in mockTicketHistory" 
                        :key="item.id"
                        class="p-2.5 rounded-xl bg-slate-950/50 border border-slate-800/80 hover:border-slate-700 transition-colors"
                    >
                        <div class="flex items-center justify-between text-[10px] mb-1">
                            <span class="font-mono text-indigo-400 font-semibold">{{ item.id }}</span>
                            <span class="text-slate-500">{{ item.date }}</span>
                        </div>
                        <p class="text-xs text-slate-300 line-clamp-1">
                            {{ item.subject }}
                        </p>
                        <div class="mt-1 flex items-center space-x-1 text-[10px] text-emerald-400">
                            <CheckCircle2 class="w-3 h-3" />
                            <span>Resolved</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </aside>
</template>
