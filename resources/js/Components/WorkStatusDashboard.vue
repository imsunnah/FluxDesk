<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { 
    CheckSquare, 
    Plus, 
    Calendar, 
    Trash2, 
    X, 
    AlertCircle, 
    Briefcase, 
    AlertTriangle, 
    GripVertical, 
    BarChart3, 
    Eye, 
    ArrowUpRight, 
    Lock, 
    History, 
    User, 
    Users, 
    Edit3,
    Check
} from 'lucide-vue-next';
import WorkStatusDetailedDashboard from '@/Components/WorkStatusDetailedDashboard.vue';

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

const isAdmin = computed(() => !!props.currentAgent?.is_admin);
const isTeamLead = computed(() => !isAdmin.value && !!props.currentAgent?.is_team_lead);
const isStaff = computed(() => !isAdmin.value && !isTeamLead.value);
const canManageAll = computed(() => isAdmin.value || isTeamLead.value);

// View Mode: 'workspace' (Clean Task Bar & Board) vs 'overview' (Overview & All Data Page)
const currentView = ref('workspace');

// Team lead board filter: 'me' vs 'team'
const leadBoardScope = ref('me');

// Local reactive tasks for optimistic updates
const localTasks = ref([...props.tasks]);

watch(() => props.tasks, (newTasks) => {
    localTasks.value = [...newTasks];
}, { deep: true });

// Check if a task belongs to a specific user
function taskBelongsToUser(task, userId) {
    if (!task || !userId) return false;
    if (task.assigned_to === userId || task.created_by === userId) return true;
    if (task.assignees && task.assignees.some(a => a.id === userId)) return true;
    return false;
}

// Today's date string YYYY-MM-DD
const todayStr = computed(() => {
    return new Date().toISOString().split('T')[0];
});

function isTaskToday(task) {
    if (!task.due_date) return false;
    return task.due_date === todayStr.value;
}

function isTaskOverdue(task) {
    if (!task.due_date) return false;
    if (task.status === 'done') {
        if (task.completed_at) {
            return task.completed_at.split('T')[0] > task.due_date;
        }
        return false;
    }
    return task.due_date < todayStr.value;
}

function getDelayDurationDays(task) {
    if (!task.due_date) return 0;
    const due = new Date(task.due_date);
    const end = task.status === 'done' && task.completed_at ? new Date(task.completed_at) : new Date();
    const diff = Math.floor((end - due) / (1000 * 60 * 60 * 24));
    return diff > 0 ? diff : 0;
}

// =========================================================
// SCOPING FOR OVERVIEW PAGE (PER USER SPECIFICATION)
// - Admin: sees all data ("admin all")
// - Team Lead: sees all employee data of this team ("team will see all employee data of this team")
// - Employee: sees only her own data ("employee will show only her data")
// =========================================================
const overviewScopedTasks = computed(() => {
    // 1. Admin: sees all data
    if (isAdmin.value) {
        return localTasks.value;
    }

    // 2. Team Lead: sees all employee data of this team
    if (isTeamLead.value) {
        return localTasks.value;
    }

    // 3. Employee: strictly sees only her own data
    return localTasks.value.filter(t => taskBelongsToUser(t, props.currentAgent.id));
});

const overviewStaffAgents = computed(() => {
    if (isAdmin.value || isTeamLead.value) {
        return props.staffAgents;
    }
    // Employee strictly sees only her own profile
    return props.staffAgents.filter(s => s.id === props.currentAgent?.id);
});

// =========================================================
// =========================================================
// SCOPING FOR FIRST PAGE (SIMPLE TASK BAR)
// - Employee: sees her own tasks
// - Team Lead: sees ONLY own tasks ("team task display in list not need to disply on taskbar")
// - Admin: sees tasks
// =========================================================
const boardTasks = computed(() => {
    // Both Staff and Team Lead see only their OWN tasks on the taskbar!
    if (!isAdmin.value) {
        return localTasks.value.filter(t => taskBelongsToUser(t, props.currentAgent.id));
    }
    // Admin sees all
    return localTasks.value;
});

// Permission check: Team lead should not edit team task; only their own tasks
function canEditTask(task) {
    if (!task) return false;
    if (isAdmin.value) return true;
    return taskBelongsToUser(task, props.currentAgent.id);
}

function canDeleteTask(task) {
    if (!task) return false;
    if (isAdmin.value) return true;
    return taskBelongsToUser(task, props.currentAgent.id);
}

// Kanban Columns for the Task Bar
const columns = [
    { key: 'todo', label: 'To Do', dot: 'bg-slate-400', badge: 'bg-slate-100 text-slate-700 border-slate-200' },
    { key: 'in_progress', label: 'In Progress', dot: 'bg-blue-500', badge: 'bg-blue-50 text-blue-700 border-blue-200' },
    { key: 'review', label: 'In Review', dot: 'bg-amber-500', badge: 'bg-amber-50 text-amber-700 border-amber-200' },
    { key: 'done', label: 'Completed', dot: 'bg-emerald-500', badge: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
];

function getTasksByStatus(status) {
    return boardTasks.value.filter(t => t.status === status);
}

// Drag & Drop
const draggedTask = ref(null);
const dragOverColumn = ref(null);

function handleDragStart(task, event) {
    if (!canEditTask(task)) {
        event.preventDefault();
        return;
    }
    draggedTask.value = task;
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', String(task.id));
}

function handleDragEnd() {
    draggedTask.value = null;
    dragOverColumn.value = null;
}

function handleDragOver(colKey, event) {
    event.preventDefault();
    if (draggedTask.value) {
        dragOverColumn.value = colKey;
        event.dataTransfer.dropEffect = 'move';
    }
}

function handleDragLeave(colKey) {
    if (dragOverColumn.value === colKey) {
        dragOverColumn.value = null;
    }
}

function handleDrop(colKey, event) {
    event.preventDefault();
    const task = draggedTask.value;
    dragOverColumn.value = null;
    draggedTask.value = null;

    if (!task || task.status === colKey) return;
    updateTaskStatus(task, colKey);
}

function updateTaskStatus(task, newStatus) {
    if (!task || task.status === newStatus) return;

    const prevStatus = task.status;
    task.status = newStatus;

    const idx = localTasks.value.findIndex(t => t.id === task.id);
    if (idx > -1) {
        localTasks.value[idx].status = newStatus;
    }

    router.patch(`/tasks/${task.id}`, {
        status: newStatus,
        completion_remarks: newStatus === 'done' ? (task.completion_remarks || 'Completed via task bar.') : null
    }, {
        preserveScroll: true,
        preserveState: true,
        onError: () => {
            task.status = prevStatus;
            if (idx > -1) localTasks.value[idx].status = prevStatus;
        }
    });
}

// =========================================================
// CREATE / EDIT TASK MODAL
// =========================================================
const isModalOpen = ref(false);
const editingTaskId = ref(null);
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
const taskErrors = ref({});
const isSubmitting = ref(false);

function openNewTaskModal(status = 'todo', preselectedAssigneeId = null) {
    editingTaskId.value = null;
    taskErrors.value = {};

    let defaultAssignees = [];
    if (isAdmin.value) {
        defaultAssignees = preselectedAssigneeId 
            ? [preselectedAssigneeId] 
            : (props.staffAgents[0] ? [props.staffAgents[0].id] : []);
    } else if (isTeamLead.value) {
        defaultAssignees = preselectedAssigneeId ? [preselectedAssigneeId] : [props.currentAgent.id];
    } else {
        defaultAssignees = [props.currentAgent.id];
    }

    taskForm.value = {
        title: '',
        description: '',
        project_id: props.projects[0]?.id || '',
        client_id: props.projects[0]?.client_id || '',
        status,
        priority: 'medium',
        approximate_hours: 4,
        assignee_ids: defaultAssignees,
        due_date: new Date().toISOString().split('T')[0],
    };
    isModalOpen.value = true;
}

function openEditTaskModal(task) {
    if (!canEditTask(task)) {
        alert("You do not have permission to edit this team member's task. Team leads cannot edit employee tasks.");
        return;
    }
    editingTaskId.value = task.id;
    taskErrors.value = {};

    const assignees = task.assignees && task.assignees.length > 0 
        ? task.assignees.map(a => a.id) 
        : (task.assigned_to ? [task.assigned_to] : [props.currentAgent.id]);

    taskForm.value = {
        title: task.title || '',
        description: task.description || '',
        project_id: task.project_id || '',
        client_id: task.client_id || '',
        status: task.status || 'todo',
        priority: task.priority || 'medium',
        approximate_hours: task.approximate_hours || 4,
        assignee_ids: assignees,
        due_date: task.due_date || '',
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
    if (!canManageAll.value) return; // Staff creates for self only
    const idx = taskForm.value.assignee_ids.indexOf(userId);
    if (idx > -1) {
        taskForm.value.assignee_ids.splice(idx, 1);
    } else {
        taskForm.value.assignee_ids.push(userId);
    }
}

function submitTask() {
    taskErrors.value = {};
    if (!taskForm.value.title || taskForm.value.title.trim().length < 3) {
        taskErrors.value.title = 'Title must be at least 3 characters.';
    }
    if (!taskForm.value.due_date) {
        taskErrors.value.due_date = 'Due date is required.';
    }

    if (Object.keys(taskErrors.value).length > 0) return;

    const assignees = canManageAll.value 
        ? (taskForm.value.assignee_ids.length 
            ? taskForm.value.assignee_ids 
            : (isAdmin.value ? (props.staffAgents[0] ? [props.staffAgents[0].id] : []) : [props.currentAgent.id]))
        : [props.currentAgent.id];

    isSubmitting.value = true;

    if (editingTaskId.value) {
        router.patch(`/tasks/${editingTaskId.value}`, {
            title: taskForm.value.title,
            description: taskForm.value.description,
            project_id: taskForm.value.project_id ? Number(taskForm.value.project_id) : null,
            client_id: taskForm.value.client_id ? Number(taskForm.value.client_id) : null,
            status: taskForm.value.status,
            priority: taskForm.value.priority,
            approximate_hours: taskForm.value.approximate_hours ? Number(taskForm.value.approximate_hours) : 0,
            assignee_ids: assignees,
            due_date: taskForm.value.due_date || null,
        }, {
            preserveScroll: true,
            onFinish: () => {
                isSubmitting.value = false;
                isModalOpen.value = false;
                editingTaskId.value = null;
            },
        });
    } else {
        router.post('/tasks', {
            title: taskForm.value.title,
            description: taskForm.value.description,
            project_id: taskForm.value.project_id ? Number(taskForm.value.project_id) : null,
            client_id: taskForm.value.client_id ? Number(taskForm.value.client_id) : null,
            status: taskForm.value.status,
            priority: taskForm.value.priority,
            approximate_hours: taskForm.value.approximate_hours ? Number(taskForm.value.approximate_hours) : 0,
            assignee_ids: assignees,
            due_date: taskForm.value.due_date || null,
        }, {
            preserveScroll: true,
            onFinish: () => {
                isSubmitting.value = false;
                isModalOpen.value = false;
            },
        });
    }
}

function deleteTask(task) {
    if (!canDeleteTask(task)) {
        alert("You do not have permission to delete this team member's task.");
        return;
    }
    if (confirm(`Are you sure you want to delete task "${task.title}"?`)) {
        router.delete(`/tasks/${task.id}`, {
            preserveScroll: true,
            preserveState: true,
        });
    }
}

// =========================================================
// DELAY REASON MODAL
// =========================================================
const isDelayModalOpen = ref(false);
const delayTask = ref(null);
const delayForm = ref({
    delay_category: 'Client Blocker',
    delay_reason: '',
    action: 'submit',
});
const delayError = ref('');

const delayCategories = [
    'Client Blocker',
    'Resource Dependency',
    'Scope Creep',
    'Technical Complexity',
    'External Dependency',
    'Infrastructure / Environment Issue',
    'Other'
];

const isDelayLockedForCurrentRole = computed(() => {
    if (!delayTask.value) return false;
    if (!canManageAll.value && delayTask.value.delay_status === 'finalized_by_lead') {
        return true;
    }
    return false;
});

function openDelayModal(task) {
    delayTask.value = task;
    delayError.value = '';
    delayForm.value = {
        delay_category: task.delay_category || 'Client Blocker',
        delay_reason: task.delay_reason || '',
        action: 'submit',
    };
    isDelayModalOpen.value = true;
}

function submitDelayReason(actionType = 'submit') {
    if (!delayTask.value) return;
    if (isDelayLockedForCurrentRole.value) {
        delayError.value = 'This delay explanation has been finalized and permanently locked by the Team Lead.';
        return;
    }
    if (!delayForm.value.delay_reason || delayForm.value.delay_reason.length < 5) {
        delayError.value = 'Please provide an explanation note (minimum 5 characters).';
        return;
    }

    delayForm.value.action = actionType;
    const task = delayTask.value;
    const isFinalize = canManageAll.value && actionType === 'finalize';
    const newStatus = isFinalize ? 'finalized_by_lead' : 'awaiting_lead_review';

    const newRevision = {
        user_id: props.currentAgent.id,
        user_name: props.currentAgent.name,
        user_role: isAdmin.value ? 'Admin' : (isTeamLead.value ? 'Team Lead' : 'Staff'),
        action: isFinalize ? 'Finalized & Locked by Lead/Admin' : (canManageAll.value ? 'Reviewed & Updated by Lead/Admin' : 'Submitted by Staff'),
        category: delayForm.value.delay_category,
        note: delayForm.value.delay_reason,
        timestamp: new Date().toISOString(),
    };

    task.delay_category = delayForm.value.delay_category;
    task.delay_reason = delayForm.value.delay_reason;
    task.delay_status = newStatus;
    task.delay_revisions = Array.isArray(task.delay_revisions) ? [...task.delay_revisions, newRevision] : [newRevision];

    router.post(`/tasks/${task.id}/delay-reason`, {
        delay_category: delayForm.value.delay_category,
        delay_reason: delayForm.value.delay_reason,
        action: actionType,
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            isDelayModalOpen.value = false;
        },
        onError: (errors) => {
            if (errors.delay_reason) {
                delayError.value = errors.delay_reason;
            }
        }
    });
}

// Quick Preview Modal
const previewTask = ref(null);

function priorityConfig(priority) {
    switch (priority) {
        case 'high':
            return { label: 'High', badge: 'bg-rose-50 text-rose-700 border-rose-200' };
        case 'low':
            return { label: 'Low', badge: 'bg-slate-50 text-slate-600 border-slate-200' };
        default:
            return { label: 'Medium', badge: 'bg-amber-50 text-amber-700 border-amber-200' };
    }
}

function formatDate(dateStr) {
    if (!dateStr) return 'No deadline';
    const date = new Date(dateStr + 'T00:00:00');
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
}
</script>

<template>
    <!-- ========================================================= -->
    <!-- 1. OVERVIEW & ALL DATA PAGE (SHOWN WHEN USER CLICKS OVERVIEW) -->
    <!-- - Admin: sees all data                                    -->
    <!-- - Team Lead: sees all employee data of this team          -->
    <!-- - Employee: sees strictly her own data                    -->
    <!-- ========================================================= -->
    <WorkStatusDetailedDashboard
        v-if="currentView === 'overview'"
        :tasks="overviewScopedTasks"
        :all-tasks="localTasks"
        :projects="projects"
        :staff-agents="overviewStaffAgents"
        :current-agent="currentAgent"
        :is-lead-mode="isAdmin || isTeamLead"
        @switch-to-kanban="currentView = 'workspace'"
        @open-new-task="openNewTaskModal('todo')"
        @open-delay-modal="openDelayModal"
    />

    <!-- ========================================================= -->
    <!-- 2. FIRST PAGE: SIMPLE TASK BAR (KANBAN BOARD)             -->
    <!-- "in first page jsut show tak bar and add task button"     -->
    <!-- "filater tba like eonding thuis oalso no t need"           -->
    <!-- ========================================================= -->
    <div v-else class="flex-1 flex flex-col h-full overflow-hidden bg-slate-50 font-sans">
        <!-- SIMPLE HEADER: TITLE, OVERVIEW BUTTON & ADD TASK BUTTON -->
        <header class="bg-white border-b border-slate-200/90 px-6 py-3.5 shrink-0 shadow-2xs">
            <div class="flex items-center justify-between">
                <!-- Left: Title + Role Badge -->
                <div class="flex items-center space-x-3">
                    <div class="w-8 h-8 rounded-xl bg-indigo-50 border border-indigo-100 flex items-center justify-center text-indigo-600 shadow-2xs">
                        <CheckSquare class="w-4 h-4" />
                    </div>
                    <div>
                        <div class="flex items-center space-x-2">
                            <h1 class="text-sm font-bold text-slate-900 tracking-tight">Work Status</h1>
                            <span 
                                :class="[
                                    'px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider',
                                    isAdmin ? 'bg-purple-50 text-purple-700 border border-purple-200' : (isTeamLead ? 'bg-indigo-50 text-indigo-700 border border-indigo-200' : 'bg-slate-100 text-slate-600 border border-slate-200')
                                ]"
                            >
                                {{ isAdmin ? 'Admin' : (isTeamLead ? 'Team Lead' : 'Staff') }}
                            </span>
                        </div>
                        <p class="text-[11px] text-slate-500 font-medium">
                            {{ isAdmin ? 'All Tasks Board' : 'My Assigned Tasks' }}
                        </p>
                    </div>
                </div>

                <!-- Right: OVERVIEW / TEAM TASKS LIST BUTTON & ADD TASK BUTTON -->
                <div class="flex items-center space-x-2.5">
                    <!-- PROMINENT TEAM TASKS LIST & OVERVIEW BUTTON -->
                    <button
                        @click="currentView = 'overview'"
                        class="px-3.5 py-1.5 rounded-xl bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 text-indigo-700 text-xs font-bold flex items-center space-x-1.5 transition-all shadow-2xs hover:shadow-xs active:scale-98 cursor-pointer"
                        title="Open Team Tasks List (View all employee tasks with status) and Overview Dashboard"
                    >
                        <BarChart3 class="w-4 h-4 text-indigo-600" />
                        <span>{{ isTeamLead || isAdmin ? '📋 Team Tasks List & Overview' : '📊 Overview & All Data' }}</span>
                        <ArrowUpRight class="w-3.5 h-3.5 text-indigo-500" />
                    </button>

                    <!-- ADD TASK BUTTON -->
                    <button
                        @click="openNewTaskModal('todo')"
                        class="px-3.5 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold shadow-xs hover:shadow-sm flex items-center space-x-1.5 transition-all active:scale-98 cursor-pointer"
                    >
                        <Plus class="w-4 h-4" />
                        <span>Add Task</span>
                    </button>
                </div>
            </div>
        </header>

        <!-- ========================================================= -->
        <!-- CLEAN TASK BAR / KANBAN COLUMNS STREAM                    -->
        <!-- ========================================================= -->
        <div class="flex-1 p-4 lg:p-6 overflow-x-auto overflow-y-hidden">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 h-full min-w-[960px]">
                <div
                    v-for="col in columns"
                    :key="col.key"
                    @dragover="handleDragOver(col.key, $event)"
                    @dragleave="handleDragLeave(col.key)"
                    @drop="handleDrop(col.key, $event)"
                    :class="[
                        'flex flex-col rounded-2xl border transition-all duration-200 overflow-hidden h-full shadow-2xs',
                        dragOverColumn === col.key
                            ? 'border-2 border-dashed border-indigo-500 bg-indigo-50/70 shadow-lg scale-[1.01]'
                            : 'border-slate-200/90 bg-slate-100/70'
                    ]"
                >
                    <!-- Column Header -->
                    <div class="px-4 py-3 border-b border-slate-200 bg-white flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <span :class="['w-2.5 h-2.5 rounded-full', col.dot]"></span>
                            <span class="text-xs font-bold text-slate-800">{{ col.label }}</span>
                            <span :class="['px-2 py-0.2 rounded-full text-[10px] font-mono font-bold border', col.badge]">
                                {{ getTasksByStatus(col.key).length }}
                            </span>
                        </div>
                        <button
                            @click="openNewTaskModal(col.key)"
                            class="p-1 rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-slate-100 transition-colors"
                            title="Add task in this column"
                        >
                            <Plus class="w-3.5 h-3.5" />
                        </button>
                    </div>

                    <!-- Column Cards Stream -->
                    <div class="flex-1 p-2.5 space-y-2.5 overflow-y-auto">
                        <!-- Drop indicator -->
                        <div
                            v-if="dragOverColumn === col.key"
                            class="py-3 px-2 text-center text-indigo-700 font-bold text-xs bg-indigo-100/80 border-2 border-dashed border-indigo-400 rounded-xl animate-pulse"
                        >
                            + Drop task in {{ col.label }}
                        </div>

                        <!-- Task Card -->
                        <div
                            v-for="task in getTasksByStatus(col.key)"
                            :key="task.id"
                            draggable="true"
                            @dragstart="handleDragStart(task, $event)"
                            @dragend="handleDragEnd"
                            :class="[
                                'p-3.5 bg-white rounded-xl border transition-all duration-150 flex flex-col space-y-2.5 cursor-grab active:cursor-grabbing select-none hover:shadow-xs group',
                                isTaskOverdue(task) ? 'border-rose-300 ring-1 ring-rose-200/60 shadow-2xs' : 'border-slate-200/90 hover:border-indigo-300 shadow-2xs',
                                draggedTask?.id === task.id ? 'opacity-30 border-dashed border-indigo-400 scale-95' : ''
                            ]"
                        >
                            <!-- Card Top: Project + Priority + Badges -->
                            <div class="flex items-center justify-between text-[10px]">
                                <span
                                    v-if="task.project"
                                    class="inline-flex items-center space-x-1 font-semibold text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded-md border border-indigo-100 truncate max-w-[140px]"
                                    :title="task.project.name"
                                >
                                    <Briefcase class="w-2.5 h-2.5 shrink-0" />
                                    <span class="truncate">{{ task.project.name }}</span>
                                </span>
                                <span v-else class="text-slate-400 text-[10px]">General</span>

                                <div class="flex items-center space-x-1">
                                    <span
                                        v-if="isTaskToday(task)"
                                        class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-amber-100 text-amber-800 border border-amber-200"
                                    >
                                        Today
                                    </span>

                                    <span
                                        v-if="isTaskOverdue(task)"
                                        class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-rose-600 text-white shadow-2xs flex items-center space-x-0.5"
                                    >
                                        <AlertTriangle class="w-2.5 h-2.5" />
                                        <span>Late</span>
                                    </span>

                                    <span :class="['px-1.5 py-0.2 rounded text-[9px] font-bold uppercase shrink-0 border', priorityConfig(task.priority).badge]">
                                        {{ task.priority }}
                                    </span>
                                </div>
                            </div>

                            <!-- Title -->
                            <div>
                                <h4
                                    @click="previewTask = task"
                                    class="text-xs font-bold text-slate-800 leading-snug hover:text-indigo-600 transition-colors cursor-pointer"
                                    :title="task.title"
                                >
                                    {{ task.title }}
                                </h4>
                                <p v-if="task.description" class="text-[11px] text-slate-500 line-clamp-2 mt-1 leading-relaxed">
                                    {{ task.description }}
                                </p>
                            </div>

                            <!-- Overdue Delay Prompt (If Late) -->
                            <div v-if="isTaskOverdue(task)" class="p-2 rounded-lg bg-rose-50/70 border border-rose-200 text-xs space-y-1">
                                <div class="flex items-center justify-between text-[10px]">
                                    <span class="font-bold text-rose-800 flex items-center space-x-1">
                                        <AlertCircle class="w-3 h-3 text-rose-600" />
                                        <span>Delay: +{{ getDelayDurationDays(task) }}d</span>
                                    </span>
                                    <span v-if="task.delay_status === 'finalized_by_lead'" class="px-1.5 py-0.2 rounded-full text-[9px] font-bold bg-indigo-100 text-indigo-800 flex items-center space-x-0.5">
                                        <Lock class="w-2.5 h-2.5" />
                                        <span>Locked</span>
                                    </span>
                                    <span v-else class="text-[9px] font-bold text-rose-700">
                                        {{ task.delay_category ? 'Note Added' : 'Reason Needed' }}
                                    </span>
                                </div>
                                <button
                                    @click="openDelayModal(task)"
                                    class="w-full text-center text-[10px] font-bold text-rose-700 bg-white hover:bg-rose-100/60 py-0.5 rounded border border-rose-200 transition-colors"
                                >
                                    {{ canManageAll ? 'Review Delay Note →' : (task.delay_status === 'finalized_by_lead' ? 'View Locked Note' : 'Enter Delay Reason →') }}
                                </button>
                            </div>

                            <!-- Card Bottom: Assignee / Due Date / Grip / Actions -->
                            <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-500">
                                <div class="flex items-center space-x-1.5">
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
                                    <span v-if="task.due_date" :class="['text-[10px] flex items-center space-x-1', isTaskOverdue(task) ? 'text-rose-600 font-bold' : 'text-slate-500']">
                                        <Calendar class="w-3 h-3 text-slate-400" />
                                        <span>{{ formatDate(task.due_date) }}</span>
                                    </span>
                                </div>

                                <div class="flex items-center space-x-1.5">
                                    <div
                                        v-if="canEditTask(task)"
                                        class="p-0.5 rounded text-slate-300 group-hover:text-slate-500 cursor-grab"
                                        title="Drag to any column to change status"
                                    >
                                        <GripVertical class="w-3.5 h-3.5" />
                                    </div>
                                    <button
                                        v-if="canEditTask(task)"
                                        @click="openEditTaskModal(task)"
                                        class="p-0.5 text-slate-400 hover:text-indigo-600"
                                        title="Edit Task"
                                    >
                                        <Edit3 class="w-3.5 h-3.5" />
                                    </button>
                                    <button
                                        v-if="canDeleteTask(task)"
                                        @click="deleteTask(task)"
                                        class="p-0.5 text-slate-400 hover:text-rose-600"
                                        title="Delete Task"
                                    >
                                        <Trash2 class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Empty Column State -->
                        <div
                            v-if="getTasksByStatus(col.key).length === 0 && dragOverColumn !== col.key"
                            class="py-12 text-center text-slate-400 text-xs border border-dashed border-slate-200 rounded-xl"
                        >
                            <span>No tasks in {{ col.label }}</span>
                            <div class="text-[10px] text-slate-400 mt-1">Drag a task here</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- DELAY REASON MODAL                                        -->
        <!-- ========================================================= -->
        <div
            v-if="isDelayModalOpen && delayTask"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs"
        >
            <div class="w-full max-w-lg bg-white rounded-2xl border border-slate-200 shadow-2xl p-5 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center space-x-2">
                        <AlertTriangle class="w-5 h-5 text-rose-600" />
                        <div>
                            <h3 class="text-sm font-bold text-slate-900">Task Delay Explanation</h3>
                            <div class="text-[10px] text-slate-500 font-mono">
                                Task: {{ delayTask.title }} (Due: {{ delayTask.due_date }})
                            </div>
                        </div>
                    </div>
                    <button
                        @click="isDelayModalOpen = false"
                        class="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100"
                    >
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div v-if="delayError" class="p-2.5 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-700 font-medium">
                    {{ delayError }}
                </div>

                <div
                    v-if="delayTask.delay_status === 'finalized_by_lead'"
                    class="p-3 rounded-xl bg-indigo-50 border border-indigo-200 text-xs text-indigo-900 space-y-1"
                >
                    <div class="font-bold flex items-center space-x-1.5 text-indigo-800">
                        <Lock class="w-4 h-4 text-indigo-600" />
                        <span>Finalized and Permanently Locked by Team Lead</span>
                    </div>
                    <p class="text-[11px] text-indigo-700 leading-relaxed">
                        <template v-if="!canManageAll">
                            🔒 Confirmed by Team Lead. Editing is disabled to preserve audit integrity.
                        </template>
                        <template v-else>
                            🛡️ As Team Lead / Admin, you have rights to review or modify this locked delay explanation.
                        </template>
                    </p>
                </div>

                <form @submit.prevent="submitDelayReason('submit')" class="space-y-3 text-xs">
                    <div class="space-y-1">
                        <label class="font-bold text-slate-700 flex items-center justify-between">
                            <span>Delay Category</span>
                            <span class="text-rose-600 font-bold">* Required</span>
                        </label>
                        <select
                            v-model="delayForm.delay_category"
                            :disabled="isDelayLockedForCurrentRole"
                            :class="[
                                'w-full px-3 py-2 border rounded-xl text-slate-800 text-xs font-semibold focus:outline-none focus:border-indigo-600',
                                isDelayLockedForCurrentRole ? 'bg-slate-100 border-slate-200 text-slate-400 cursor-not-allowed' : 'bg-slate-50 border-slate-200'
                            ]"
                        >
                            <option v-for="cat in delayCategories" :key="cat" :value="cat">
                                {{ cat }}
                            </option>
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label class="font-bold text-slate-700 flex items-center justify-between">
                            <span>Delay Explanation Note</span>
                            <span class="text-rose-600 font-bold">* Required</span>
                        </label>
                        <textarea
                            v-model="delayForm.delay_reason"
                            rows="3"
                            :disabled="isDelayLockedForCurrentRole"
                            placeholder="Detail why the deadline was missed, blockers, or mitigation steps taken..."
                            :class="[
                                'w-full px-3 py-2 border rounded-xl text-slate-800 text-xs focus:outline-none focus:border-indigo-600 resize-none leading-relaxed',
                                isDelayLockedForCurrentRole ? 'bg-slate-100 border-slate-200 text-slate-400 cursor-not-allowed' : 'bg-slate-50 border-slate-200'
                            ]"
                        ></textarea>
                    </div>

                    <div class="pt-2 flex items-center justify-between border-t border-slate-100">
                        <button
                            type="button"
                            @click="isDelayModalOpen = false"
                            class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold"
                        >
                            Close
                        </button>

                        <div class="flex items-center space-x-2">
                            <button
                                v-if="canManageAll"
                                type="button"
                                @click="submitDelayReason('finalize')"
                                class="px-3.5 py-1.5 rounded-xl bg-indigo-700 hover:bg-indigo-800 text-white text-xs font-bold shadow-xs flex items-center space-x-1.5"
                                title="Finalize and permanently lock for employees"
                            >
                                <Lock class="w-3.5 h-3.5" />
                                <span>Finalize & Lock</span>
                            </button>

                            <button
                                v-if="!isDelayLockedForCurrentRole"
                                type="submit"
                                class="px-3.5 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-xs"
                            >
                                {{ canManageAll ? 'Save Changes' : 'Submit for Review' }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- CREATE / EDIT TASK MODAL                                  -->
        <!-- ========================================================= -->
        <div
            v-if="isModalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs"
        >
            <div class="w-full max-w-lg bg-white rounded-2xl border border-slate-200 shadow-2xl p-5 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-sm font-bold text-slate-900 flex items-center space-x-2">
                        <CheckSquare class="w-4 h-4 text-indigo-600" />
                        <span>{{ editingTaskId ? 'Edit Task' : 'Create New Task' }}</span>
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
                        <label class="font-medium text-slate-700 flex items-center justify-between">
                            <span>Task Title</span>
                            <span v-if="taskErrors.title" class="text-rose-600 font-bold text-[10px]">{{ taskErrors.title }}</span>
                        </label>
                        <input
                            v-model="taskForm.title"
                            type="text"
                            required
                            placeholder="e.g. Implement payment webhook callback retry"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:bg-white focus:border-indigo-600"
                        />
                    </div>

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
                            placeholder="Task instructions and expectations..."
                            class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 resize-none focus:outline-none focus:border-indigo-600"
                        ></textarea>
                    </div>

                    <div class="grid grid-cols-3 gap-2">
                        <div class="space-y-1">
                            <label class="font-medium text-slate-700">Status</label>
                            <select
                                v-model="taskForm.status"
                                class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none"
                            >
                                <option value="todo">To Do</option>
                                <option value="in_progress">In Progress</option>
                                <option value="review">Review</option>
                                <option value="done">Completed</option>
                            </select>
                        </div>

                        <div class="space-y-1">
                            <label class="font-medium text-slate-700">Priority</label>
                            <select
                                v-model="taskForm.priority"
                                class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none"
                            >
                                <option value="low">🟢 Low</option>
                                <option value="medium">🟡 Medium</option>
                                <option value="high">🔴 High</option>
                            </select>
                        </div>

                        <div class="space-y-1">
                            <label class="font-medium text-slate-700">Approx. Hours</label>
                            <input
                                v-model.number="taskForm.approximate_hours"
                                type="number"
                                step="0.5"
                                min="0.5"
                                class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none"
                            />
                        </div>
                    </div>

                    <!-- Assignee section -->
                    <div class="space-y-1">
                        <div class="flex items-center justify-between">
                            <label class="font-medium text-slate-700">Assignee</label>
                            <span v-if="!canManageAll" class="text-[10px] text-amber-600 font-bold">
                                Assigned to yourself
                            </span>
                        </div>

                        <div v-if="canManageAll" class="max-h-28 overflow-y-auto p-2 bg-slate-50 rounded-xl border border-slate-200 space-y-1">
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
                                    <span>{{ agent.name }} ({{ agent.member_id || agent.role }})</span>
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

                        <div v-else class="p-2 bg-slate-100 rounded-xl border border-slate-200 flex items-center space-x-2 text-xs text-slate-700">
                            <img :src="currentAgent.avatar || 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=50&h=50&fit=crop'" class="w-5 h-5 rounded-full object-cover" />
                            <span class="font-semibold">{{ currentAgent.name }} (Assigned to You)</span>
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="font-medium text-slate-700 flex items-center justify-between">
                            <span>Due Date Deadline</span>
                            <span v-if="taskErrors.due_date" class="text-rose-600 font-bold text-[10px]">{{ taskErrors.due_date }}</span>
                        </label>
                        <input
                            v-model="taskForm.due_date"
                            type="date"
                            required
                            class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none"
                        />
                    </div>

                    <div class="pt-2 flex items-center justify-end space-x-2 border-t border-slate-100">
                        <button
                            type="button"
                            @click="isModalOpen = false"
                            class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-medium"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="isSubmitting"
                            class="px-4 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-xs cursor-pointer"
                        >
                            {{ editingTaskId ? 'Update Task' : 'Create Task' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- QUICK TASK PREVIEW MODAL                                  -->
        <!-- ========================================================= -->
        <div
            v-if="previewTask"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs"
        >
            <div class="w-full max-w-md bg-white rounded-2xl border border-slate-200 shadow-2xl p-5 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-sm font-bold text-slate-900 flex items-center space-x-2">
                        <CheckSquare class="w-4 h-4 text-indigo-600" />
                        <span>Task Details</span>
                    </h3>
                    <button
                        @click="previewTask = null"
                        class="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100"
                    >
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div class="space-y-3 text-xs">
                    <div>
                        <span class="text-[10px] font-bold uppercase text-slate-400">Title:</span>
                        <div class="text-sm font-bold text-slate-900 mt-0.5">{{ previewTask.title }}</div>
                    </div>

                    <div v-if="previewTask.description">
                        <span class="text-[10px] font-bold uppercase text-slate-400">Description:</span>
                        <p class="text-xs text-slate-700 bg-slate-50 p-2.5 rounded-xl border border-slate-200/80 whitespace-pre-wrap mt-0.5">
                            {{ previewTask.description }}
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-200">
                            <span class="text-[10px] text-slate-400 font-medium">Project</span>
                            <div class="font-bold text-slate-800">{{ previewTask.project?.name || 'General' }}</div>
                        </div>
                        <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-200">
                            <span class="text-[10px] text-slate-400 font-medium">Priority & Status</span>
                            <div class="font-bold text-slate-800 capitalize">{{ previewTask.priority }} • {{ previewTask.status }}</div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-200">
                            <span class="text-[10px] text-slate-400 font-medium">Due Date</span>
                            <div :class="['font-bold', isTaskOverdue(previewTask) ? 'text-rose-600' : 'text-slate-800']">
                                {{ formatDate(previewTask.due_date) }}
                            </div>
                        </div>
                        <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-200">
                            <span class="text-[10px] text-slate-400 font-medium">Approx. Hours</span>
                            <div class="font-bold text-slate-800">{{ previewTask.approximate_hours || 0 }}h</div>
                        </div>
                    </div>

                    <div v-if="isTaskOverdue(previewTask)" class="p-3 bg-rose-50 border border-rose-200 rounded-xl space-y-1">
                        <div class="font-bold text-rose-800 flex items-center justify-between">
                            <span>Overdue: +{{ getDelayDurationDays(previewTask) }} days late</span>
                            <span v-if="previewTask.delay_status === 'finalized_by_lead'" class="text-[10px] text-indigo-700 font-bold">🔒 Finalized by Lead</span>
                        </div>
                        <div v-if="previewTask.delay_category" class="text-xs text-rose-900">
                            <strong>Category:</strong> {{ previewTask.delay_category }}
                            <p class="mt-0.5 text-slate-700">{{ previewTask.delay_reason }}</p>
                        </div>
                    </div>
                </div>

                <div class="pt-2 flex items-center justify-between border-t border-slate-100">
                    <button
                        v-if="canEditTask(previewTask)"
                        @click="openEditTaskModal(previewTask); previewTask = null;"
                        class="text-xs font-bold text-indigo-600 hover:text-indigo-800 flex items-center space-x-1"
                    >
                        <Edit3 class="w-3.5 h-3.5" />
                        <span>Edit Task Details</span>
                    </button>
                    <span v-else class="text-[11px] text-slate-400 font-medium italic">
                        Viewing team member task
                    </span>
                    <button
                        @click="previewTask = null"
                        class="px-3.5 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
