<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { 
    Users, 
    UserPlus, 
    Lock, 
    Trash2, 
    Crown, 
    Shield, 
    UserMinus, 
    Check
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
    currentAgent: {
        type: Object,
        default: () => ({}),
    },
    isOpen: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits(['update-conversation', 'close', 'open-add-member', 'delete-chat']);

const isGroup = computed(() => props.conversation?.type === 'group');

// Check if current user is admin or group admin
const canManageGroup = computed(() => {
    if (props.currentAgent?.is_admin) return true;
    const currentMember = props.conversation?.members?.find(m => m.id === props.currentAgent?.id);
    return currentMember?.pivot?.is_group_admin;
});

function toggleEncryption() {
    emit('update-conversation', {
        is_encrypted: !props.conversation.is_encrypted,
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
    if (confirm(`Remove ${member.name} from this group?`)) {
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
        class="w-72 lg:w-76 h-full bg-slate-900 border-l border-slate-800 flex flex-col overflow-y-auto select-none shrink-0 z-10 text-slate-200"
    >
        <!-- Panel Header -->
        <div class="px-4 py-3 border-b border-slate-800 flex items-center justify-between bg-slate-900">
            <h4 class="text-xs font-bold uppercase tracking-wider text-slate-300">
                {{ isGroup ? 'Group Information' : 'Chat Details' }}
            </h4>
            <button 
                @click="$emit('close')"
                class="text-slate-400 hover:text-slate-200 text-xs p-1 rounded-md hover:bg-slate-800"
            >
                ✕
            </button>
        </div>

        <div class="p-4 space-y-4 flex-1">
            <!-- Header Card -->
            <div class="flex flex-col items-center text-center p-3.5 rounded-xl bg-slate-950 border border-slate-800">
                <img 
                    :src="conversation.customer_avatar || 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=100&h=100&fit=crop'" 
                    :alt="conversation.display_name"
                    class="w-14 h-14 rounded-full object-cover ring-2 ring-indigo-500/30 mb-2 shadow" 
                />
                <h3 class="text-xs font-bold text-slate-100">
                    {{ conversation.display_name }}
                </h3>
                <p class="text-[11px] text-slate-400 mt-0.5 truncate max-w-full">
                    {{ conversation.group_description || conversation.customer_email }}
                </p>

                <!-- Encrypted State Button -->
                <div 
                    @click="toggleEncryption"
                    :class="[
                        'mt-3 w-full p-2 rounded-lg cursor-pointer flex items-center justify-between text-xs font-semibold border transition-all',
                        conversation.is_encrypted 
                            ? 'bg-emerald-950/40 border-emerald-500/40 text-emerald-300' 
                            : 'bg-slate-900 border-slate-800 text-slate-400 hover:text-slate-200'
                    ]"
                >
                    <div class="flex items-center space-x-1.5">
                        <Lock class="w-3.5 h-3.5" :class="conversation.is_encrypted ? 'text-emerald-400' : 'text-slate-400'" />
                        <span>{{ conversation.is_encrypted ? 'Encrypted (E2EE)' : 'Standard Chat' }}</span>
                    </div>
                    <span class="text-[10px] text-slate-500">Toggle</span>
                </div>
            </div>

            <!-- Members Section (For Group Chats) -->
            <div v-if="isGroup" class="space-y-2">
                <div class="flex items-center justify-between text-xs font-semibold text-slate-300">
                    <span class="flex items-center space-x-1.5">
                        <Users class="w-3.5 h-3.5 text-indigo-400" />
                        <span>Members ({{ conversation.members?.length || 0 }})</span>
                    </span>
                    <button 
                        @click="$emit('open-add-member')"
                        class="text-[11px] text-indigo-400 hover:text-indigo-300 flex items-center space-x-0.5 font-medium"
                    >
                        <UserPlus class="w-3 h-3" />
                        <span>Add</span>
                    </button>
                </div>

                <div class="space-y-1.5 max-h-56 overflow-y-auto">
                    <div 
                        v-for="member in conversation.members" 
                        :key="member.id"
                        class="p-2 rounded-lg bg-slate-950 border border-slate-800/80 flex items-center justify-between text-xs group"
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

                        <!-- Admin controls: Make Admin / Remove Member -->
                        <div v-if="canManageGroup && member.id !== currentAgent?.id" class="flex items-center space-x-1 shrink-0 opacity-0 group-hover:opacity-100 transition-opacity">
                            <button
                                v-if="!member.pivot?.is_group_admin"
                                @click="makeAdmin(member)"
                                class="px-1.5 py-0.5 rounded bg-amber-500/10 text-amber-300 hover:bg-amber-500/20 text-[10px] font-semibold"
                                title="Make Group Admin"
                            >
                                Admin
                            </button>
                            <button
                                @click="removeMember(member)"
                                class="p-1 rounded text-slate-500 hover:text-rose-400 hover:bg-slate-900"
                                title="Remove from group"
                            >
                                <UserMinus class="w-3 h-3" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Danger Zone: Delete Chat Button -->
            <div class="pt-4 border-t border-slate-800">
                <button
                    @click="$emit('delete-chat')"
                    class="w-full py-2 px-3 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/30 text-rose-400 text-xs font-semibold flex items-center justify-center space-x-1.5 transition-all"
                >
                    <Trash2 class="w-3.5 h-3.5" />
                    <span>Delete Conversation</span>
                </button>
            </div>
        </div>
    </aside>
</template>
