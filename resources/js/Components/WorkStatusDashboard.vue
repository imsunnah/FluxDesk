<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { 
    CheckSquare, 
    Plus, 
    Clock, 
    Calendar, 
    Trash2, 
    X, 
    AlertCircle, 
    CheckCircle2, 
    TrendingUp,
    Filter,
    Users,
    Briefcase,
    Building,
    MessageSquare,
    AlertTriangle,
    ShieldCheck
} from 'lucide-vue-next';

const props = defineProps({
    tasks: {
        type: Array,
        default: () => [],
    },
    projects: {
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
});

const isCustomer = computed(() => !!props.currentAgent?.is_customer);
const isAdmin = computed(() => !!props.currentAgent?.is_admin);
const isTeamLead = computed(() => !!props.currentAgent?.is_team_lead);
const canAssignOthers = computed(() => isAdmin.value || isTeamLead.value);

// Filters
const filterPriority = ref('all');
const filterProject = ref('all');
const filterTimeStatus = ref('all'); // 'all', 'overtime', 'ontrack'
const searchQuery = ref('');

const filteredTasks = computed(() => {
    return props.tasks.filter((t) => {
        if (filterPriority.value !== 'all' && t.priority !== filterPriority.value) {
            return false;
        }
        if (filterProject.value !== 'all' && String(t.project_id) !== String(filterProject.value)) {
            return false;
        }
        if (filterTimeStatus.value === 'overtime') {
            if (!t.approximate_hours || !t.completed_hours || t.completed_hours <= t.approximate_hours) return false;
        } else if (filterTimeStatus.value === 'ontrack') {
            if (!t.approximate_hours || !t.completed_hours || t.completed_hours > t.approximate_hours) return false;
        }
        if (searchQuery.value) {
            const q = searchQuery.value.toLowerCase();
            const titleMatch = t.title?.toLowerCase().includes(q);
            const descMatch = t.description?.toLowerCase().includes(q);
            const projMatch = t.project?.name?.toLowerCase().includes(q);
            const clientMatch = t.client?.name?.toLowerCase().includes(q);
            const assigneeMatch = t.assignees?.some(a => a.name.toLowerCase().includes(q)) || t.assignee?.name?.toLowerCase().includes(q);
            if (!titleMatch && !descMatch && !projMatch && !clientMatch && !assigneeMatch) return false;
        }
        return true;
    });
});

const columns = [
    { key: 'todo', label: 'To Do', color: 'bg-slate-100 text-slate-700 border-slate-300' },
    { key: 'in_progress', label: 'In Progress', color: 'bg-blue-50 text-blue-700 border-blue-200' },
    { key: 'review', label: 'Review', color: 'bg-amber-50 text-amber-700 border-amber-200' },
    { key: 'done', label: 'Completed', color: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
];

function getTasksByStatus(status) {
    return filteredTasks.value.filter((t) => t.status === status);
}

// Metrics
const totalCount = computed(() => props.tasks.length);
const inProgressCount = computed(() => props.tasks.filter((t) => t.status === 'in_progress').length);
const doneCount = computed(() => props.tasks.filter((t) => t.status === 'done').length);
const overtimeTasks = computed(() => props.tasks.filter((t) => t.approximate_hours > 0 && t.completed_hours > t.approximate_hours));

// Create Task Modal State
const isModalOpen = ref(false);
const taskForm = ref({
    title: '',
    description: '',
    project_id: '',
    client_id: '',
    status: 'todo',
    priority: 'medium',
    approximate_hours: 4,
    assignee_ids: [],
    due_date: '',
});
const isSubmitting = ref(false);

function openNewTaskModal(status = 'todo') {
    taskForm.value = {
        title: '',
        description: '',
        project_id: props.projects[0]?.id || '',
        client_id: props.projects[0]?.client_id || '',
        status,
        priority: 'medium',
        approximate_hours: 4,
        assignee_ids: [props.currentAgent.id],
        due_date: new Date(Date.now() + 86400000 * 3).toISOString().split('T')[0],
    };
    isModalOpen.value = true;
}

function onProjectChange() {
    const selectedProj = props.projects.find(p => String(p.id) === String(taskForm.value.project_id));
    if (selectedProj && selectedProj.client_id) {
        taskForm.value.client_id = selectedProj.client_id;
    }
}

function toggleAssignee(userId) {
    const idx = taskForm.value.assignee_ids.indexOf(userId);
    if (idx > -1) {
        taskForm.value.assignee_ids.splice(idx, 1);
    } else {
        taskForm.value.assignee_ids.push(userId);
    }
}

function submitTask() {
    if (!taskForm.value.title) return;
    isSubmitting.value = true;
    router.post('/tasks', {
        title: taskForm.value.title,
        description: taskForm.value.description,
        project_id: taskForm.value.project_id ? Number(taskForm.value.project_id) : null,
        client_id: taskForm.value.client_id ? Number(taskForm.value.client_id) : null,
        status: taskForm.value.status,
        priority: taskForm.value.priority,
        approximate_hours: taskForm.value.approximate_hours ? Number(taskForm.value.approximate_hours) : 0,
        assignee_ids: canAssignOthers.value ? taskForm.value.assignee_ids : [props.currentAgent.id],
        due_date: taskForm.value.due_date || null,
    }, {
        preserveScroll: true,
        onFinish: () => {
            isSubmitting.value = false;
            isModalOpen.value = false;
        },
    });
}

// Completion Remarks Modal
const isCompleteModalOpen = ref(false);
const completingTask = ref(null);
const completeForm = ref({
    completed_hours: 0,
    completion_remarks: '',
});

function openCompletionModal(task) {
    completingTask.value = task;
    completeForm.value = {
        completed_hours: task.completed_hours || task.approximate_hours || 1,
        completion_remarks: task.completion_remarks || '',
    };
    isCompleteModalOpen.value = true;
}

function submitCompletion() {
    if (!completingTask.value || !completeForm.value.completion_remarks) return;
    isSubmitting.value = true;
    router.patch(`/tasks/${completingTask.value.id}`, {
        status: 'done',
        completed_hours: Number(completeForm.value.completed_hours) || 0,
        completion_remarks: completeForm.value.completion_remarks,
    }, {
        preserveScroll: true,
        onFinish: () => {
            isSubmitting.value = false;
            isCompleteModalOpen.value = false;
            completingTask.value = null;
        },
    });
}

function handleStatusSelect(task, newStatus) {
    if (newStatus === 'done') {
        openCompletionModal(task);
    } else {
        router.patch(`/tasks/${task.id}`, { status: newStatus }, {
            preserveScroll: true,
            preserveState: true,
        });
    }
}

function deleteTask(task) {
    if (confirm(`Delete task "${task.title}"?`)) {
        router.delete(`/tasks/${task.id}`, {
            preserveScroll: true,
            preserveState: true,
        });
    }
}

function calculateRatio(task) {
    if (!task.approximate_hours || task.approximate_hours <= 0 || task.completed_hours === null || task.completed_hours === undefined) {
        return null;
    }
    return (task.completed_hours / task.approximate_hours);
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
</script>

<template>
    <div class="flex-1 flex flex-col h-full overflow-hidden bg-slate-50">
        <!-- Top Action Bar & Metrics -->
        <div class="px-6 py-4 bg-white border-b border-slate-200 shrink-0">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <div class="flex items-center space-x-2">
                        <CheckSquare class="w-5 h-5 text-indigo-600" />
                        <h2 class="text-lg font-bold text-slate-900">Work Status & Tasks</h2>
                        <span v-if="isTeamLead" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200 flex items-center space-x-1">
                            <ShieldCheck class="w-3 h-3" />
                            <span>Team Lead</span>
                        </span>
                        <span v-else-if="!isCustomer" class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 text-slate-600 border border-slate-200">
                            Team Member
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 mt-0.5">
                        Track projects, clients, approximate vs completed hours ratio, and completion remarks.
                    </p>
                </div>

                <div class="flex items-center space-x-2">
                    <button
                        @click="openNewTaskModal('todo')"
                        class="px-3.5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-xs flex items-center space-x-1.5 transition-colors"
                    >
                        <Plus class="w-3.5 h-3.5" />
                        <span>Create Task</span>
                    </button>
                </div>
            </div>

            <!-- Summary Cards with Ratio Analysis -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-4">
                <div class="p-3 bg-slate-50 border border-slate-200 rounded-xl flex items-center justify-between">
                    <div>
                        <span class="text-[11px] font-medium text-slate-500 uppercase tracking-wider">Total Tasks</span>
                        <div class="text-lg font-bold text-slate-800">{{ totalCount }}</div>
                    </div>
                    <div class="w-8 h-8 rounded-lg bg-white border border-slate-200 flex items-center justify-center text-slate-600">
                        <CheckSquare class="w-4 h-4" />
                    </div>
                </div>

                <div class="p-3 bg-blue-50/50 border border-blue-200/80 rounded-xl flex items-center justify-between">
                    <div>
                        <span class="text-[11px] font-medium text-blue-600 uppercase tracking-wider">In Progress</span>
                        <div class="text-lg font-bold text-blue-800">{{ inProgressCount }}</div>
                    </div>
                    <div class="w-8 h-8 rounded-lg bg-blue-100/60 text-blue-700 flex items-center justify-center">
                        <TrendingUp class="w-4 h-4" />
                    </div>
                </div>

                <div class="p-3 bg-emerald-50/50 border border-emerald-200/80 rounded-xl flex items-center justify-between">
                    <div>
                        <span class="text-[11px] font-medium text-emerald-600 uppercase tracking-wider">Completed</span>
                        <div class="text-lg font-bold text-emerald-800">{{ doneCount }}</div>
                    </div>
                    <div class="w-8 h-8 rounded-lg bg-emerald-100/60 text-emerald-700 flex items-center justify-center">
                        <CheckCircle2 class="w-4 h-4" />
                    </div>
                </div>

                <!-- Overtime Tasks Ratio Metric -->
                <div 
                    :class="[
                        'p-3 border rounded-xl flex items-center justify-between',
                        overtimeTasks.length > 0 ? 'bg-rose-50/60 border-rose-200' : 'bg-slate-50 border-slate-200'
                    ]"
                >
                    <div>
                        <span :class="['text-[11px] font-medium uppercase tracking-wider', overtimeTasks.length > 0 ? 'text-rose-600 font-bold' : 'text-slate-500']">
                            Overtime Ratio
                        </span>
                        <div :class="['text-lg font-bold', overtimeTasks.length > 0 ? 'text-rose-700' : 'text-slate-800']">
                            {{ overtimeTasks.length }} tasks
                        </div>
                    </div>
                    <div :class="['w-8 h-8 rounded-lg flex items-center justify-center', overtimeTasks.length > 0 ? 'bg-rose-100 text-rose-700' : 'bg-white border border-slate-200 text-slate-600']">
                        <AlertTriangle class="w-4 h-4" />
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

                    <!-- Priority Filter -->
                    <div class="flex items-center space-x-1 text-xs">
                        <span class="text-slate-500 text-[11px]">Priority:</span>
                        <select
                            v-model="filterPriority"
                            class="px-2 py-1 bg-white border border-slate-200 rounded-lg text-xs text-slate-700 focus:outline-none focus:border-indigo-500"
                        >
                            <option value="all">All</option>
                            <option value="high">High</option>
                            <option value="medium">Medium</option>
                            <option value="low">Low</option>
                        </select>
                    </div>

                    <!-- Time Ratio Filter -->
                    <div class="flex items-center space-x-1 text-xs">
                        <span class="text-slate-500 text-[11px]">Ratio:</span>
                        <select
                            v-model="filterTimeStatus"
                            class="px-2 py-1 bg-white border border-slate-200 rounded-lg text-xs text-slate-700 focus:outline-none focus:border-indigo-500"
                        >
                            <option value="all">All Times</option>
                            <option value="overtime">⚠️ Overtime (Ratio > 1.0)</option>
                            <option value="ontrack">✅ On Time (Ratio ≤ 1.0)</option>
                        </select>
                    </div>
                </div>

                <input
                    v-model="searchQuery"
                    type="text"
                    placeholder="Search by title, project, client or assignee..."
                    class="w-full sm:w-64 px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:bg-white focus:border-indigo-600"
                />
            </div>
        </div>

        <!-- Kanban Board Columns -->
        <div class="flex-1 p-6 overflow-x-auto overflow-y-hidden">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 h-full min-w-[1000px]">
                <div
                    v-for="col in columns"
                    :key="col.key"
                    class="flex flex-col bg-slate-100/80 rounded-2xl border border-slate-200 overflow-hidden h-full"
                >
                    <!-- Column Header -->
                    <div class="px-3.5 py-3 border-b border-slate-200 bg-white flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <span :class="['w-2.5 h-2.5 rounded-full', col.key === 'done' ? 'bg-emerald-500' : col.key === 'in_progress' ? 'bg-blue-500' : col.key === 'review' ? 'bg-amber-500' : 'bg-slate-400']"></span>
                            <span class="text-xs font-bold text-slate-800">{{ col.label }}</span>
                            <span class="px-1.5 py-0.2 rounded-full bg-slate-100 text-slate-600 text-[10px] font-mono font-bold">
                                {{ getTasksByStatus(col.key).length }}
                            </span>
                        </div>
                        <button
                            @click="openNewTaskModal(col.key)"
                            class="p-1 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors"
                            title="Add task in this column"
                        >
                            <Plus class="w-3.5 h-3.5" />
                        </button>
                    </div>

                    <!-- Column Cards Stream -->
                    <div class="flex-1 p-2.5 space-y-2.5 overflow-y-auto">
                        <div
                            v-for="task in getTasksByStatus(col.key)"
                            :key="task.id"
                            class="p-3 bg-white rounded-xl border border-slate-200 shadow-xs hover:border-slate-300 transition-all flex flex-col space-y-2.5"
                        >
                            <!-- Project & Client Tags -->
                            <div class="flex items-center justify-between text-[10px] text-slate-500">
                                <span v-if="task.project" class="inline-flex items-center space-x-1 font-semibold text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded-md border border-indigo-100 truncate max-w-[140px]">
                                    <Briefcase class="w-2.5 h-2.5 shrink-0" />
                                    <span class="truncate">{{ task.project.name }}</span>
                                </span>
                                <span v-else class="text-slate-400">No Project</span>

                                <span v-if="task.client" class="text-slate-600 truncate max-w-[100px] flex items-center space-x-0.5" :title="'Client: ' + task.client.name">
                                    <Building class="w-2.5 h-2.5 text-slate-400" />
                                    <span class="truncate">{{ task.client.name }}</span>
                                </span>
                            </div>

                            <!-- Title & Priority -->
                            <div class="flex items-start justify-between gap-1">
                                <h4 class="text-xs font-bold text-slate-900 leading-snug">
                                    {{ task.title }}
                                </h4>
                                <span :class="['px-1.5 py-0.5 rounded text-[9px] font-bold uppercase shrink-0', priorityBadge(task.priority)]">
                                    {{ task.priority }}
                                </span>
                            </div>

                            <!-- Description -->
                            <p v-if="task.description" class="text-[11px] text-slate-500 line-clamp-2 leading-relaxed">
                                {{ task.description }}
                            </p>

                            <!-- Approximate Time & Completed Time Ratio -->
                            <div class="p-2 rounded-lg bg-slate-50 border border-slate-200/80 text-[10px] space-y-1">
                                <div class="flex items-center justify-between text-slate-600">
                                    <span class="flex items-center space-x-1">
                                        <Clock class="w-3 h-3 text-slate-400" />
                                        <span>Approx: <strong>{{ task.approximate_hours || 0 }}h</strong></span>
                                    </span>
                                    <span v-if="task.completed_hours !== null && task.completed_hours !== undefined">
                                        Actual: <strong>{{ task.completed_hours }}h</strong>
                                    </span>
                                    <span v-else class="text-slate-400">
                                        In Progress
                                    </span>
                                </div>

                                <!-- Time Ratio Indicator: which task take more time to approximate -->
                                <div v-if="calculateRatio(task) !== null" class="pt-1 border-t border-slate-200/60 flex items-center justify-between">
                                    <span class="font-medium text-slate-500">Ratio (Actual/Approx):</span>
                                    <span 
                                        :class="[
                                            'px-1.5 py-0.2 rounded font-mono font-bold text-[9px]',
                                            calculateRatio(task) > 1.0 
                                                ? 'bg-rose-100 text-rose-700 border border-rose-200' 
                                                : 'bg-emerald-100 text-emerald-700 border border-emerald-200'
                                        ]"
                                    >
                                        {{ calculateRatio(task).toFixed(2) }}x 
                                        {{ calculateRatio(task) > 1.0 ? '(Overtime +' + Math.round((calculateRatio(task) - 1) * 100) + '%)' : '(On Track)' }}
                                    </span>
                                </div>
                            </div>

                            <!-- Completion Remarks (Mandatory after complete) -->
                            <div v-if="task.status === 'done' && task.completion_remarks" class="p-2 rounded-lg bg-emerald-50/60 border border-emerald-100 text-[11px] text-emerald-900">
                                <div class="font-semibold text-[10px] uppercase text-emerald-800 flex items-center space-x-1 mb-0.5">
                                    <CheckCircle2 class="w-3 h-3 text-emerald-600" />
                                    <span>Completion Remarks:</span>
                                </div>
                                <p class="text-emerald-800 whitespace-pre-wrap leading-relaxed line-clamp-2">
                                    {{ task.completion_remarks }}
                                </p>
                            </div>

                            <!-- Multiple Assignees Display -->
                            <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-500">
                                <div class="flex items-center space-x-1">
                                    <div class="flex -space-x-1.5 overflow-hidden">
                                        <template v-if="task.assignees && task.assignees.length > 0">
                                            <img
                                                v-for="a in task.assignees"
                                                :key="a.id"
                                                :src="a.avatar || 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=50&h=50&fit=crop'"
                                                class="w-5 h-5 rounded-full object-cover ring-1 ring-white"
                                                :title="a.name"
                                            />
                                        </template>
                                        <template v-else-if="task.assignee">
                                            <img
                                                :src="task.assignee.avatar || 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=50&h=50&fit=crop'"
                                                class="w-5 h-5 rounded-full object-cover ring-1 ring-white"
                                                :title="task.assignee.name"
                                            />
                                        </template>
                                    </div>
                                    <span class="text-[10px] text-slate-600 font-medium truncate max-w-[90px]">
                                        {{ task.assignees?.map(a => a.name.split(' ')[0]).join(', ') || task.assignee?.name || 'Unassigned' }}
                                    </span>
                                </div>

                                <div class="flex items-center space-x-1 text-[10px] text-slate-400" v-if="task.due_date">
                                    <Calendar class="w-3 h-3 text-slate-400" />
                                    <span>{{ task.due_date }}</span>
                                </div>
                            </div>

                            <!-- Card Bottom Status Selector & Actions -->
                            <div class="pt-1.5 flex items-center justify-between text-xs">
                                <select
                                    :value="task.status"
                                    @change="handleStatusSelect(task, $event.target.value)"
                                    class="bg-slate-50 border border-slate-200 rounded px-1.5 py-0.5 text-[10px] font-medium text-slate-700 focus:outline-none focus:border-indigo-500 cursor-pointer"
                                >
                                    <option value="todo">To Do</option>
                                    <option value="in_progress">In Progress</option>
                                    <option value="review">Review</option>
                                    <option value="done">Done (Add Remarks)</option>
                                </select>

                                <button
                                    @click="deleteTask(task)"
                                    class="p-1 text-slate-400 hover:text-rose-600 rounded transition-colors"
                                    title="Delete task"
                                >
                                    <Trash2 class="w-3 h-3" />
                                </button>
                            </div>
                        </div>

                        <!-- Empty State -->
                        <div
                            v-if="getTasksByStatus(col.key).length === 0"
                            class="py-8 text-center text-slate-400 text-xs border border-dashed border-slate-200 rounded-xl"
                        >
                            No tasks
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- CREATE TASK MODAL (With Project, Client, Hours, Assignees)-->
        <!-- ========================================================= -->
        <div
            v-if="isModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs"
        >
            <div class="w-full max-w-lg bg-white rounded-2xl border border-slate-200 shadow-2xl p-5 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-sm font-bold text-slate-900 flex items-center space-x-2">
                        <CheckSquare class="w-4 h-4 text-indigo-600" />
                        <span>Create New Task</span>
                    </h3>
                    <button
                        @click="isModalOpen = false"
                        class="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100"
                    >
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form @submit.prevent="submitTask" class="space-y-3 text-xs">
                    <div class="space-y-1">
                        <label class="font-medium text-slate-700">Task Title</label>
                        <input
                            v-model="taskForm.title"
                            type="text"
                            required
                            placeholder="e.g. Implement payment webhook callback retry"
                            class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 placeholder-slate-400 focus:outline-none focus:bg-white focus:border-indigo-600"
                        />
                    </div>

                    <!-- Project & Client Selection -->
                    <div class="grid grid-cols-2 gap-2">
                        <div class="space-y-1">
                            <label class="font-medium text-slate-700">Project</label>
                            <select
                                v-model="taskForm.project_id"
                                @change="onProjectChange"
                                class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:bg-white focus:border-indigo-600"
                            >
                                <option value="">Select Project</option>
                                <option v-for="p in projects" :key="p.id" :value="p.id">
                                    {{ p.name }}
                                </option>
                            </select>
                        </div>

                        <div class="space-y-1">
                            <label class="font-medium text-slate-700">Client</label>
                            <select
                                v-model="taskForm.client_id"
                                class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:bg-white focus:border-indigo-600"
                            >
                                <option value="">Select Client</option>
                                <option v-for="p in projects.filter(x => x.client)" :key="p.client?.id" :value="p.client?.id">
                                    {{ p.client?.name }} ({{ p.name }})
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="font-medium text-slate-700">Description</label>
                        <textarea
                            v-model="taskForm.description"
                            rows="2"
                            placeholder="Add task instructions and acceptance criteria..."
                            class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 placeholder-slate-400 focus:outline-none focus:bg-white focus:border-indigo-600 resize-none"
                        ></textarea>
                    </div>

                    <div class="grid grid-cols-3 gap-2">
                        <div class="space-y-1">
                            <label class="font-medium text-slate-700">Status</label>
                            <select
                                v-model="taskForm.status"
                                class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:bg-white focus:border-indigo-600"
                            >
                                <option value="todo">To Do</option>
                                <option value="in_progress">In Progress</option>
                                <option value="review">Review</option>
                                <option value="done">Done</option>
                            </select>
                        </div>

                        <div class="space-y-1">
                            <label class="font-medium text-slate-700">Priority</label>
                            <select
                                v-model="taskForm.priority"
                                class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:bg-white focus:border-indigo-600"
                            >
                                <option value="low">🟢 Low</option>
                                <option value="medium">🟡 Medium</option>
                                <option value="high">🔴 High</option>
                            </select>
                        </div>

                        <!-- Approximate Time to Complete -->
                        <div class="space-y-1">
                            <label class="font-medium text-slate-700">Approx. Hours</label>
                            <input
                                v-model.number="taskForm.approximate_hours"
                                type="number"
                                step="0.5"
                                min="0.5"
                                placeholder="e.g. 4"
                                class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:bg-white focus:border-indigo-600"
                            />
                        </div>
                    </div>

                    <!-- Assign To Multiple Persons (Team Lead can assign to others, Employee creates own task) -->
                    <div class="space-y-1">
                        <div class="flex items-center justify-between">
                            <label class="font-medium text-slate-700">
                                Assign Task
                            </label>
                            <span v-if="!canAssignOthers" class="text-[10px] text-amber-600 font-medium">
                                (Employee can create own task; only Team Lead can assign to others)
                            </span>
                        </div>

                        <!-- If Team Lead / Admin: can assign to multiple people -->
                        <div v-if="canAssignOthers" class="max-h-28 overflow-y-auto p-2 bg-slate-50 rounded-xl border border-slate-200 space-y-1">
                            <div
                                v-for="agent in staffAgents"
                                :key="agent.id"
                                @click="toggleAssignee(agent.id)"
                                :class="[
                                    'flex items-center justify-between p-1.5 rounded-lg cursor-pointer transition-colors text-xs',
                                    taskForm.assignee_ids.includes(agent.id) ? 'bg-indigo-50 border border-indigo-200 text-indigo-900 font-semibold' : 'hover:bg-slate-100 text-slate-700'
                                ]"
                            >
                                <div class="flex items-center space-x-2">
                                    <img :src="agent.avatar || 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=50&h=50&fit=crop'" class="w-5 h-5 rounded-full object-cover" />
                                    <span>{{ agent.name }}</span>
                                    <span class="text-[10px] text-slate-400 font-mono">({{ agent.member_id || 'Staff' }})</span>
                                </div>
                                <div
                                    :class="[
                                        'w-4 h-4 rounded-full flex items-center justify-center border',
                                        taskForm.assignee_ids.includes(agent.id) ? 'bg-indigo-600 border-indigo-600 text-white' : 'border-slate-300'
                                    ]"
                                >
                                    <span v-if="taskForm.assignee_ids.includes(agent.id)" class="text-[10px] font-bold">✓</span>
                                </div>
                            </div>
                        </div>

                        <!-- If normal employee: locked to themselves -->
                        <div v-else class="p-2.5 bg-slate-50 rounded-xl border border-slate-200 flex items-center space-x-2 text-xs text-slate-700">
                            <img :src="currentAgent.avatar" class="w-5 h-5 rounded-full object-cover" />
                            <span>{{ currentAgent.name }} (Assigned to You)</span>
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="font-medium text-slate-700">Due Date</label>
                        <input
                            v-model="taskForm.due_date"
                            type="date"
                            class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:bg-white focus:border-indigo-600"
                        />
                    </div>

                    <div class="pt-2 flex items-center justify-end space-x-2">
                        <button
                            type="button"
                            @click="isModalOpen = false"
                            class="px-3 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-medium transition-colors"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="isSubmitting"
                            class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-xs transition-colors"
                        >
                            Create Task
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- COMPLETION REMARKS & ACTUAL TIME MODAL (MANDATORY)        -->
        <!-- ========================================================= -->
        <div
            v-if="isCompleteModalOpen && completingTask"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs"
        >
            <div class="w-full max-w-md bg-white rounded-2xl border border-slate-200 shadow-2xl p-5 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-sm font-bold text-slate-900 flex items-center space-x-2">
                        <CheckCircle2 class="w-4 h-4 text-emerald-600" />
                        <span>Complete Task & Add Remarks</span>
                    </h3>
                    <button
                        @click="isCompleteModalOpen = false"
                        class="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100"
                    >
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div class="text-xs text-slate-600">
                    Task: <strong class="text-slate-900">{{ completingTask.title }}</strong>
                </div>

                <form @submit.prevent="submitCompletion" class="space-y-3 text-xs">
                    <div class="grid grid-cols-2 gap-2">
                        <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200">
                            <span class="text-[10px] text-slate-500 uppercase font-medium">Approx. Hours:</span>
                            <div class="text-base font-bold text-slate-800">{{ completingTask.approximate_hours || 0 }}h</div>
                        </div>

                        <div class="space-y-1">
                            <label class="font-medium text-slate-700">Actual Hours Taken</label>
                            <input
                                v-model.number="completeForm.completed_hours"
                                type="number"
                                step="0.25"
                                min="0.25"
                                required
                                class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:bg-white focus:border-emerald-600 font-bold"
                            />
                        </div>
                    </div>

                    <!-- Mandatory Remarks -->
                    <div class="space-y-1">
                        <label class="font-medium text-slate-700 flex items-center justify-between">
                            <span>Completion Remarks (Mandatory)</span>
                            <span class="text-[10px] text-rose-600 font-bold">* Required</span>
                        </label>
                        <textarea
                            v-model="completeForm.completion_remarks"
                            required
                            rows="3"
                            placeholder="Explain what was accomplished, deployment notes, or reasons for any time variance..."
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 placeholder-slate-400 focus:outline-none focus:bg-white focus:border-emerald-600 resize-none leading-relaxed"
                        ></textarea>
                    </div>

                    <div class="pt-2 flex items-center justify-end space-x-2">
                        <button
                            type="button"
                            @click="isCompleteModalOpen = false"
                            class="px-3 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-medium transition-colors"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="isSubmitting || !completeForm.completion_remarks"
                            class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow-xs transition-colors"
                        >
                            Confirm & Mark Done
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
