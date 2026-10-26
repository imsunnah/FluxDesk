<script setup>
import { ref, computed, watch } from 'vue';
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
    AlertTriangle, 
    ShieldCheck, 
    GripVertical, 
    BarChart3, 
    Kanban, 
    Eye, 
    ArrowUpRight, 
    Lock, 
    History, 
    UserCheck, 
    User, 
    Sparkles, 
    AlertOctagon,
    Table
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

const isCustomer = computed(() => !!props.currentAgent?.is_customer);
const isAdmin = computed(() => !!props.currentAgent?.is_admin);
const isActualTeamLead = computed(() => !!props.currentAgent?.is_team_lead || isAdmin.value);

// View Mode: 'workspace' (Clean Drag-and-Drop Task Board) vs 'overview' (Full Details & Overview Dashboard)
const currentView = ref('workspace');

// 1. TEAM LEAD INITIALLY SEES HIS OWN TASKS (Default: 'me')
// Keeps button/selector to see another member's tasks from the top!
const selectedMember = ref('me'); // 'me' | 'all' | agent.id string

// Date filter: 'all' vs 'today' (everyone can see today's data)
const dateFilter = ref('all'); // 'all' | 'today'

// Other standard filters
const filterPriority = ref('all');
const filterProject = ref('all');
const searchQuery = ref('');

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

// Local reactive tasks for optimistic updates
const localTasks = ref([...props.tasks]);

watch(() => props.tasks, (newTasks) => {
    localTasks.value = [...newTasks];
}, { deep: true });

// Check if a task belongs to a specific user
function taskBelongsToUser(task, userId) {
    if (task.assigned_to === userId || task.created_by === userId) return true;
    if (task.assignees && task.assignees.some(a => a.id === userId)) return true;
    return false;
}

// Filtered tasks based on Member Selector on top
const memberScopedTasks = computed(() => {
    // If not a team lead / admin, staff can ONLY see their own tasks
    if (!isActualTeamLead.value) {
        return localTasks.value.filter(t => taskBelongsToUser(t, props.currentAgent.id));
    }

    // Team lead can select from top:
    if (selectedMember.value === 'me') {
        // Initially sees his own task!
        return localTasks.value.filter(t => taskBelongsToUser(t, props.currentAgent.id));
    } else if (selectedMember.value === 'all') {
        // All members' tasks
        return localTasks.value;
    } else {
        // Specific member chosen from top
        const targetId = Number(selectedMember.value);
        return localTasks.value.filter(t => taskBelongsToUser(t, targetId));
    }
});

// Final filtered tasks applied to Kanban columns
const filteredTasks = computed(() => {
    return memberScopedTasks.value.filter((t) => {
        // Today's data filter
        if (dateFilter.value === 'today' && !isTaskToday(t)) {
            return false;
        }
        if (filterPriority.value !== 'all' && t.priority !== filterPriority.value) {
            return false;
        }
        if (filterProject.value !== 'all' && String(t.project_id) !== String(filterProject.value)) {
            return false;
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
    { key: 'todo', label: 'To Do', color: 'border-slate-300 text-slate-700 bg-slate-50', badge: 'bg-slate-200 text-slate-700' },
    { key: 'in_progress', label: 'In Progress', color: 'border-blue-300 text-blue-700 bg-blue-50/40', badge: 'bg-blue-100 text-blue-700' },
    { key: 'review', label: 'Review', color: 'border-amber-300 text-amber-700 bg-amber-50/40', badge: 'bg-amber-100 text-amber-700' },
    { key: 'done', label: 'Completed', color: 'border-emerald-300 text-emerald-700 bg-emerald-50/40', badge: 'bg-emerald-100 text-emerald-700' },
];

function getTasksByStatus(status) {
    return filteredTasks.value.filter(t => t.status === status);
}

// Counts for the top bar
const todayTasksCount = computed(() => memberScopedTasks.value.filter(t => isTaskToday(t)).length);
const totalVisibleCount = computed(() => filteredTasks.value.length);

// Selected member's display name
const selectedMemberName = computed(() => {
    if (selectedMember.value === 'me') return 'My Own Tasks';
    if (selectedMember.value === 'all') return 'All Team Members';
    const member = props.staffAgents.find(a => String(a.id) === String(selectedMember.value));
    return member ? `${member.name}'s Tasks` : 'Member Tasks';
});

// =========================================================
// 2. DRAG AND DROP STATUS UPDATES (NO SELECT DROPDOWN NEEDED)
// "he drap the trask from pending to review drop ok"
// Team lead can edit status of member tasks and his own. Staff can update own.
// =========================================================
const draggedTask = ref(null);
const dragOverColumn = ref(null);

function handleDragStart(task, event) {
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

    // Optimistic instantaneous move: "drap the trask from pending to review drop ok"
    const prevStatus = task.status;
    task.status = colKey;
    const localIdx = localTasks.value.findIndex(t => t.id === task.id);
    if (localIdx > -1) {
        localTasks.value[localIdx].status = colKey;
    }

    // Backend PATCH to update status
    router.patch(`/tasks/${task.id}`, { 
        status: colKey,
        completion_remarks: colKey === 'done' ? (task.completion_remarks || 'Completed via task board.') : null
    }, {
        preserveScroll: true,
        preserveState: true,
        onError: () => {
            // Revert if error
            task.status = prevStatus;
            if (localIdx > -1) localTasks.value[localIdx].status = prevStatus;
        }
    });
}

// =========================================================
// DELAY REASON & HIERARCHICAL REASON LOCK MODAL
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
    // If locked by lead AND user is not team lead/admin -> disabled
    if (!isActualTeamLead.value && delayTask.value.delay_status === 'finalized_by_lead') {
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
    const isFinalize = isActualTeamLead.value && actionType === 'finalize';
    const newStatus = isFinalize ? 'finalized_by_lead' : 'awaiting_lead_review';

    const newRevision = {
        user_id: props.currentAgent.id,
        user_name: props.currentAgent.name,
        user_role: isActualTeamLead.value ? 'Team Lead' : 'Staff',
        action: isFinalize ? 'Finalized & Locked by Lead' : (isActualTeamLead.value ? 'Reviewed & Updated by Lead' : 'Submitted by Staff'),
        category: delayForm.value.delay_category,
        note: delayForm.value.delay_reason,
        timestamp: new Date().toISOString(),
    };

    const revisions = Array.isArray(task.delay_revisions) ? [...task.delay_revisions, newRevision] : [newRevision];

    task.delay_category = delayForm.value.delay_category;
    task.delay_reason = delayForm.value.delay_reason;
    task.delay_status = newStatus;
    task.delay_revisions = revisions;
    if (isFinalize) {
        task.delay_locked_by = props.currentAgent.id;
        task.delay_locked_at = new Date().toISOString();
        task.delay_locked_by_user = { id: props.currentAgent.id, name: props.currentAgent.name };
    }

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

// =========================================================
// CREATE TASK MODAL
// =========================================================
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
const taskErrors = ref({});
const isSubmitting = ref(false);

function openNewTaskModal(status = 'todo') {
    taskErrors.value = {};
    const defaultAssignee = props.currentAgent.id;
    taskForm.value = {
        title: '',
        description: '',
        project_id: props.projects[0]?.id || '',
        client_id: props.projects[0]?.client_id || '',
        status,
        priority: 'medium',
        approximate_hours: 4,
        assignee_ids: [defaultAssignee],
        due_date: new Date().toISOString().split('T')[0],
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
    if (!isActualTeamLead.value) return; // Staff creates for self only
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
        taskErrors.value.due_date = 'Due date deadline is required.';
    }

    if (Object.keys(taskErrors.value).length > 0) return;

    const assignees = isActualTeamLead.value 
        ? (taskForm.value.assignee_ids.length ? taskForm.value.assignee_ids : [props.currentAgent.id])
        : [props.currentAgent.id];

    isSubmitting.value = true;
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

function deleteTask(task) {
    if (confirm(`Delete task "${task.title}"?`)) {
        router.delete(`/tasks/${task.id}`, {
            preserveScroll: true,
            preserveState: true,
        });
    }
}

// Quick View Task Modal
const previewTask = ref(null);

function priorityBadge(p) {
    switch (p) {
        case 'high':
            return 'bg-rose-50 text-rose-700 border-rose-200';
        case 'medium':
            return 'bg-amber-50 text-amber-700 border-amber-200';
        case 'low':
            return 'bg-emerald-50 text-emerald-700 border-emerald-200';
        default:
            return 'bg-slate-100 text-slate-600 border-slate-200';
    }
}
</script>

<template>
    <!-- OVERVIEW DASHBOARD & ALL DETAILS (DISPLAYED WHEN USER CLICKS BUTTON ON TOP) -->
    <WorkStatusDetailedDashboard
        v-if="currentView === 'overview'"
        :tasks="memberScopedTasks"
        :all-tasks="tasks"
        :projects="projects"
        :staff-agents="staffAgents"
        :current-agent="currentAgent"
        :is-lead-mode="isActualTeamLead"
        @switch-to-kanban="currentView = 'workspace'"
        @open-new-task="openNewTaskModal('todo')"
        @open-delay-modal="openDelayModal"
    />

    <!-- MAIN CLEAN TASK WORKSPACE: NO TOP DASHBOARD CLUTTER -->
    <div v-else class="flex-1 flex flex-col h-full overflow-hidden bg-slate-50">
        <!-- TOP STREAMLINED CONTROL BAR -->
        <div class="px-5 py-3 bg-white border-b border-slate-200 shrink-0 shadow-2xs">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-2.5">
                <!-- Left: Title + Member Selection for Team Lead + Today's Data -->
                <div class="flex items-center space-x-2.5 flex-wrap gap-y-2">
                    <div class="flex items-center space-x-1.5 mr-1">
                        <CheckSquare class="w-4 h-4 text-indigo-600" />
                        <h2 class="text-sm font-bold text-slate-900 whitespace-nowrap">Task Board</h2>
                        <span v-if="isActualTeamLead" class="px-2 py-0.2 rounded-full text-[9px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                            Team Lead
                        </span>
                    </div>

                    <!-- 1. TEAM LEAD MEMBER SELECTOR (INITIAL: OWN TASKS, SELECT OTHERS FROM TOP) -->
                    <div v-if="isActualTeamLead" class="flex items-center p-0.5 bg-slate-100 rounded-xl border border-slate-200 text-xs font-semibold">
                        <!-- Button: My Tasks (Initially selected by default!) -->
                        <button
                            @click="selectedMember = 'me'"
                            :class="[
                                'px-2.5 py-1 rounded-lg flex items-center space-x-1 transition-all',
                                selectedMember === 'me'
                                    ? 'bg-white text-indigo-700 font-bold shadow-2xs'
                                    : 'text-slate-600 hover:text-slate-900'
                            ]"
                            title="View your own assigned tasks"
                        >
                            <User class="w-3.5 h-3.5 text-indigo-600" />
                            <span>My Tasks</span>
                        </button>

                        <!-- Button: All Team Tasks -->
                        <button
                            @click="selectedMember = 'all'"
                            :class="[
                                'px-2.5 py-1 rounded-lg flex items-center space-x-1 transition-all',
                                selectedMember === 'all'
                                    ? 'bg-white text-indigo-700 font-bold shadow-2xs'
                                    : 'text-slate-600 hover:text-slate-900'
                            ]"
                            title="View all team members' tasks"
                        >
                            <Users class="w-3.5 h-3.5 text-indigo-600" />
                            <span>All Team</span>
                        </button>

                        <!-- Dropdown to select ANY other team member from top -->
                        <div class="flex items-center pl-1 border-l border-slate-200/80">
                            <select
                                v-model="selectedMember"
                                class="bg-transparent text-xs font-semibold text-slate-700 px-2 py-1 rounded-lg focus:outline-none cursor-pointer"
                                title="Select a specific team member to view their tasks"
                            >
                                <option value="me">👤 View: My Own Tasks</option>
                                <option value="all">👥 View: All Team Members ({{ staffAgents.length }})</option>
                                <optgroup label="Select Team Member:">
                                    <option v-for="agent in staffAgents" :key="agent.id" :value="String(agent.id)">
                                        {{ agent.name }} ({{ agent.member_id || agent.role }})
                                    </option>
                                </optgroup>
                            </select>
                        </div>
                    </div>

                    <!-- Employee scope indicator if normal staff -->
                    <div v-else class="flex items-center space-x-1 text-xs font-medium text-slate-600 bg-slate-100 px-2.5 py-1 rounded-lg border border-slate-200">
                        <User class="w-3.5 h-3.5 text-slate-500" />
                        <span>My Tasks ({{ currentAgent.name }})</span>
                    </div>

                    <!-- 3. EVERYONE CAN SEE TODAY'S DATA TOGGLE -->
                    <div class="flex items-center p-0.5 bg-slate-100 rounded-xl border border-slate-200 text-xs font-semibold">
                        <button
                            @click="dateFilter = 'all'"
                            :class="[
                                'px-2.5 py-1 rounded-lg transition-all',
                                dateFilter === 'all' ? 'bg-white text-indigo-700 font-bold shadow-2xs' : 'text-slate-600 hover:text-slate-900'
                            ]"
                        >
                            All Tasks
                        </button>
                        <button
                            @click="dateFilter = 'today'"
                            :class="[
                                'px-2.5 py-1 rounded-lg flex items-center space-x-1 transition-all',
                                dateFilter === 'today' ? 'bg-white text-indigo-700 font-bold shadow-2xs' : 'text-slate-600 hover:text-slate-900'
                            ]"
                            title="Filter tasks due or active today"
                        >
                            <Calendar class="w-3.5 h-3.5 text-indigo-600" />
                            <span>Today's Data</span>
                            <span v-if="todayTasksCount > 0" class="px-1.5 py-0.2 rounded-full text-[9px] font-mono font-bold bg-indigo-100 text-indigo-800">
                                {{ todayTasksCount }}
                            </span>
                        </button>
                    </div>
                </div>

                <!-- Right: Prominent Overview Button & Create Task -->
                <div class="flex items-center space-x-2">
                    <!-- BUTTON FOR OVERVIEW: SHOW ALL DETAILS & OVERVIEW OF TASKS -->
                    <button
                        @click="currentView = 'overview'"
                        class="px-3.5 py-1.5 rounded-xl bg-indigo-50 hover:bg-indigo-100 border border-indigo-200 text-indigo-700 text-xs font-bold flex items-center space-x-1.5 transition-all shadow-2xs hover:shadow-xs"
                        title="Display full overview, all details, late ratios, and analytics"
                    >
                        <BarChart3 class="w-4 h-4 text-indigo-600" />
                        <span>Overview & All Details</span>
                        <ArrowUpRight class="w-3.5 h-3.5 text-indigo-500" />
                    </button>

                    <button
                        @click="openNewTaskModal('todo')"
                        class="px-3.5 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-xs flex items-center space-x-1.5 transition-colors"
                    >
                        <Plus class="w-3.5 h-3.5" />
                        <span>New Task</span>
                    </button>
                </div>
            </div>

            <!-- Compact Sub-bar: Project filter, priority filter, search -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 mt-2 pt-2 border-t border-slate-100 text-xs">
                <div class="flex items-center space-x-2 flex-wrap gap-y-1">
                    <span class="text-slate-500 text-[11px] font-medium">
                        Showing: <strong class="text-slate-800">{{ selectedMemberName }}</strong> ({{ totalVisibleCount }} tasks)
                    </span>

                    <!-- Project Filter -->
                    <div class="flex items-center space-x-1 ml-2">
                        <span class="text-slate-400 text-[11px]">Project:</span>
                        <select
                            v-model="filterProject"
                            class="px-2 py-0.5 bg-slate-50 border border-slate-200 rounded-lg text-xs text-slate-700 focus:outline-none"
                        >
                            <option value="all">All Projects</option>
                            <option v-for="p in projects" :key="p.id" :value="p.id">
                                {{ p.name }}
                            </option>
                        </select>
                    </div>

                    <!-- Priority Filter -->
                    <div class="flex items-center space-x-1">
                        <span class="text-slate-400 text-[11px]">Priority:</span>
                        <select
                            v-model="filterPriority"
                            class="px-2 py-0.5 bg-slate-50 border border-slate-200 rounded-lg text-xs text-slate-700 focus:outline-none"
                        >
                            <option value="all">All Priorities</option>
                            <option value="high">🔴 High</option>
                            <option value="medium">🟡 Medium</option>
                            <option value="low">🟢 Low</option>
                        </select>
                    </div>
                </div>

                <div class="relative w-full sm:w-60">
                    <input
                        v-model="searchQuery"
                        type="text"
                        placeholder="Search tasks..."
                        class="w-full px-2.5 py-1 bg-slate-50 border border-slate-200 rounded-lg text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:bg-white focus:border-indigo-600"
                    />
                    <button v-if="searchQuery" @click="searchQuery = ''" class="absolute right-2 top-1 text-slate-400 hover:text-slate-600">
                        <X class="w-3 h-3" />
                    </button>
                </div>
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- DRAG AND DROP KANBAN BOARD                                    -->
        <!-- "stadff or all can update statuyse using drag and drop not    -->
        <!-- need select then update, he drap the trask from pending to    -->
        <!-- review drop ok"                                               -->
        <!-- ============================================================= -->
        <div class="flex-1 p-4 overflow-x-auto overflow-y-hidden">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-3.5 h-full min-w-[960px]">
                <div
                    v-for="col in columns"
                    :key="col.key"
                    @dragover="handleDragOver(col.key, $event)"
                    @dragleave="handleDragLeave(col.key)"
                    @drop="handleDrop(col.key, $event)"
                    :class="[
                        'flex flex-col rounded-2xl border transition-all duration-200 overflow-hidden h-full',
                        dragOverColumn === col.key
                            ? 'border-2 border-dashed border-indigo-500 bg-indigo-50/70 shadow-lg scale-[1.01]'
                            : 'border-slate-200 bg-slate-100/70'
                    ]"
                >
                    <!-- Column Header -->
                    <div class="px-3.5 py-2.5 border-b border-slate-200 bg-white flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <span :class="['w-2.5 h-2.5 rounded-full', col.key === 'done' ? 'bg-emerald-500' : col.key === 'in_progress' ? 'bg-blue-500' : col.key === 'review' ? 'bg-amber-500' : 'bg-slate-400']"></span>
                            <span class="text-xs font-bold text-slate-800">{{ col.label }}</span>
                            <span :class="['px-2 py-0.2 rounded-full text-[10px] font-mono font-bold', col.badge]">
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
                    <div class="flex-1 p-2 space-y-2 overflow-y-auto">
                        <!-- Active Drag Drop Zone Indicator -->
                        <div
                            v-if="dragOverColumn === col.key"
                            class="py-3 px-2 text-center text-indigo-700 font-bold text-xs bg-indigo-100/70 border-2 border-dashed border-indigo-400 rounded-xl animate-pulse"
                        >
                            + Drop task in {{ col.label }}
                        </div>

                        <!-- STREAMLINED TASK CARD: NO SELECT DROPDOWN (DRAG & DROP ONLY) -->
                        <div
                            v-for="task in getTasksByStatus(col.key)"
                            :key="task.id"
                            draggable="true"
                            @dragstart="handleDragStart(task, $event)"
                            @dragend="handleDragEnd"
                            :class="[
                                'p-3 bg-white rounded-xl border transition-all flex flex-col space-y-2 cursor-grab active:cursor-grabbing select-none hover:shadow-xs group',
                                isTaskOverdue(task) ? 'border-rose-300 ring-1 ring-rose-200/60 shadow-2xs' : 'border-slate-200 hover:border-indigo-300 shadow-2xs',
                                draggedTask?.id === task.id ? 'opacity-30 border-dashed border-indigo-400 scale-95' : ''
                            ]"
                        >
                            <!-- Top: Project Badge + Priority + Overdue/Today Badges -->
                            <div class="flex items-center justify-between text-[10px]">
                                <span
                                    v-if="task.project"
                                    class="inline-flex items-center space-x-1 font-semibold text-indigo-700 bg-indigo-50 px-2 py-0.5 rounded-md border border-indigo-100 truncate max-w-[130px]"
                                    :title="task.project.name"
                                >
                                    <Briefcase class="w-2.5 h-2.5 shrink-0" />
                                    <span class="truncate">{{ task.project.name }}</span>
                                </span>
                                <span v-else class="text-slate-400 text-[10px]">General</span>

                                <div class="flex items-center space-x-1">
                                    <!-- Today Badge -->
                                    <span
                                        v-if="isTaskToday(task)"
                                        class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-amber-100 text-amber-800 border border-amber-200"
                                        title="Due Today!"
                                    >
                                        Today
                                    </span>

                                    <!-- Overdue Badge -->
                                    <span
                                        v-if="isTaskOverdue(task)"
                                        class="px-1.5 py-0.2 rounded text-[9px] font-bold bg-rose-600 text-white shadow-2xs flex items-center space-x-0.5"
                                        title="Task is overdue!"
                                    >
                                        <AlertTriangle class="w-2.5 h-2.5" />
                                        <span>Late</span>
                                    </span>

                                    <!-- Priority Badge -->
                                    <span :class="['px-1.5 py-0.2 rounded text-[9px] font-bold uppercase shrink-0 border', priorityBadge(task.priority)]">
                                        {{ task.priority }}
                                    </span>
                                </div>
                            </div>

                            <!-- Title -->
                            <div class="flex items-start justify-between gap-1">
                                <h4
                                    @click="previewTask = task"
                                    class="text-xs font-bold text-slate-800 leading-snug hover:text-indigo-600 transition-colors cursor-pointer"
                                    :title="task.title"
                                >
                                    {{ task.title }}
                                </h4>
                                <span class="text-slate-300 text-[10px] font-mono shrink-0">#{{ task.id }}</span>
                            </div>

                            <!-- Description -->
                            <p v-if="task.description" class="text-[11px] text-slate-500 line-clamp-1 leading-relaxed">
                                {{ task.description }}
                            </p>

                            <!-- Delay Reason Box (If Overdue) -->
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
                                    <span v-else-if="task.delay_status === 'awaiting_lead_review'" class="px-1.5 py-0.2 rounded-full text-[9px] font-bold bg-amber-100 text-amber-800">
                                        Awaiting Lead
                                    </span>
                                    <span v-else class="px-1.5 py-0.2 rounded-full text-[9px] font-bold bg-rose-200 text-rose-900">
                                        Reason Needed
                                    </span>
                                </div>

                                <div v-if="task.delay_category" class="text-[10px] text-rose-950 font-medium truncate">
                                    <span class="font-bold">[{{ task.delay_category }}]</span> {{ task.delay_reason }}
                                </div>

                                <button
                                    @click="openDelayModal(task)"
                                    class="w-full text-center text-[10px] font-bold text-rose-700 bg-white hover:bg-rose-100/60 py-0.5 rounded border border-rose-200 transition-colors"
                                >
                                    {{ isActualTeamLead ? 'Review Delay Reason →' : (task.delay_status === 'finalized_by_lead' ? 'View Locked Reason' : 'Enter Delay Reason →') }}
                                </button>
                            </div>

                            <!-- Bottom Row: Assignees + Due Date + Drag Grip Indicator (NO SELECT DROPDOWN) -->
                            <div class="pt-1.5 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-500">
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
                                    <span class="text-[10px] text-slate-600 font-medium truncate max-w-[80px]">
                                        {{ task.assignees?.map(a => a.name.split(' ')[0]).join(', ') || task.assignee?.name || 'Unassigned' }}
                                    </span>
                                </div>

                                <div class="flex items-center space-x-2">
                                    <span v-if="task.due_date" :class="['text-[10px] flex items-center space-x-0.5', isTaskOverdue(task) ? 'text-rose-600 font-bold' : 'text-slate-400']">
                                        <Calendar class="w-3 h-3" />
                                        <span>{{ task.due_date.split('-').slice(1).join('/') }}</span>
                                    </span>

                                    <!-- Drag & Drop Handle (Clean visual feedback) -->
                                    <div class="p-0.5 rounded text-slate-300 group-hover:text-slate-500 cursor-grab" title="Drag and drop to any column to change status">
                                        <GripVertical class="w-3.5 h-3.5" />
                                    </div>

                                    <!-- Quick Actions -->
                                    <button
                                        @click="previewTask = task"
                                        class="p-0.5 text-slate-400 hover:text-indigo-600"
                                        title="View Details"
                                    >
                                        <Eye class="w-3.5 h-3.5" />
                                    </button>
                                    <button
                                        @click="deleteTask(task)"
                                        class="p-0.5 text-slate-400 hover:text-rose-600"
                                        title="Delete"
                                    >
                                        <Trash2 class="w-3.5 h-3.5" />
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Empty Column State -->
                        <div
                            v-if="getTasksByStatus(col.key).length === 0 && dragOverColumn !== col.key"
                            class="py-8 text-center text-slate-400 text-xs border border-dashed border-slate-200 rounded-xl"
                        >
                            <span>No tasks</span>
                            <div class="text-[10px] text-slate-400 mt-0.5">Drag tasks here</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- DELAY REASON & HIERARCHICAL REASON LOCK MODAL             -->
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
                            <h3 class="text-sm font-bold text-slate-900">Delay Reason & Hierarchical Lock</h3>
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
                        <template v-if="!isActualTeamLead">
                            🔒 Confirmed by Team Lead <strong>{{ delayTask.delay_locked_by_user?.name || 'Lead' }}</strong>. As staff, editing is permanently disabled to preserve audit integrity.
                        </template>
                        <template v-else>
                            🛡️ As a Team Lead, you have exclusive rights to review, modify, or override this locked delay explanation.
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
                            placeholder="Detail why the deadline was missed, dependencies, client blockers, or mitigation steps taken..."
                            :class="[
                                'w-full px-3 py-2 border rounded-xl text-slate-800 text-xs focus:outline-none focus:border-indigo-600 resize-none leading-relaxed',
                                isDelayLockedForCurrentRole ? 'bg-slate-100 border-slate-200 text-slate-400 cursor-not-allowed' : 'bg-slate-50 border-slate-200'
                            ]"
                        ></textarea>
                    </div>

                    <!-- Revision Log -->
                    <div class="space-y-1.5 pt-2 border-t border-slate-100">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500 flex items-center space-x-1">
                                <History class="w-3.5 h-3.5" />
                                <span>Revision Audit Log</span>
                            </span>
                            <span class="text-[10px] text-slate-400 font-mono">
                                {{ delayTask.delay_revisions?.length || 0 }} revision(s)
                            </span>
                        </div>

                        <div class="max-h-24 overflow-y-auto bg-slate-50 rounded-xl p-2.5 border border-slate-200/80 space-y-2 text-[11px]">
                            <template v-if="delayTask.delay_revisions && delayTask.delay_revisions.length > 0">
                                <div
                                    v-for="(rev, idx) in delayTask.delay_revisions.slice().reverse()"
                                    :key="idx"
                                    class="border-b border-slate-200/60 pb-1.5 last:border-b-0 last:pb-0"
                                >
                                    <div class="flex items-center justify-between text-[10px]">
                                        <span class="font-bold text-slate-800">{{ rev.user_name }} ({{ rev.user_role }})</span>
                                        <span class="text-slate-400 font-mono">{{ rev.timestamp ? new Date(rev.timestamp).toLocaleString() : 'Recent' }}</span>
                                    </div>
                                    <div class="text-indigo-700 font-semibold text-[10px] mt-0.5">
                                        {{ rev.action }} • [{{ rev.category }}]
                                    </div>
                                    <p class="text-slate-600 text-[10px] line-clamp-2 mt-0.5">{{ rev.note }}</p>
                                </div>
                            </template>
                            <div v-else class="text-center text-slate-400 py-1 text-[10px]">
                                No prior revisions recorded.
                            </div>
                        </div>
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
                                v-if="isActualTeamLead"
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
                                {{ isActualTeamLead ? 'Save Changes' : 'Submit for Review' }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- CREATE TASK MODAL                                         -->
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
                        <label class="font-medium text-slate-700 flex items-center justify-between">
                            <span>Task Title</span>
                            <span v-if="taskErrors.title" class="text-rose-600 font-bold text-[10px]">{{ taskErrors.title }}</span>
                        </label>
                        <input
                            v-model="taskForm.title"
                            type="text"
                            required
                            placeholder="e.g. Implement payment webhook callback retry"
                            class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 focus:outline-none focus:bg-white focus:border-indigo-600"
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
                            placeholder="Task instructions..."
                            class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800 resize-none"
                        ></textarea>
                    </div>

                    <div class="grid grid-cols-3 gap-2">
                        <div class="space-y-1">
                            <label class="font-medium text-slate-700">Status</label>
                            <select
                                v-model="taskForm.status"
                                class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800"
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
                                class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800"
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
                                class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800"
                            />
                        </div>
                    </div>

                    <div class="space-y-1">
                        <div class="flex items-center justify-between">
                            <label class="font-medium text-slate-700">Assignee</label>
                            <span v-if="!isActualTeamLead" class="text-[10px] text-amber-600 font-bold">
                                Assigned to yourself
                            </span>
                        </div>

                        <div v-if="isActualTeamLead" class="max-h-24 overflow-y-auto p-2 bg-slate-50 rounded-xl border border-slate-200 space-y-1">
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
                            <img :src="currentAgent.avatar" class="w-5 h-5 rounded-full object-cover" />
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
                            class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl text-slate-800"
                        />
                    </div>

                    <div class="pt-2 flex items-center justify-end space-x-2">
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
                            class="px-4 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-xs"
                        >
                            Create Task
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
                        <div class="text-sm font-bold text-slate-900">{{ previewTask.title }}</div>
                    </div>

                    <div v-if="previewTask.description">
                        <span class="text-[10px] font-bold uppercase text-slate-400">Description:</span>
                        <p class="text-xs text-slate-700 bg-slate-50 p-2.5 rounded-xl border border-slate-200/80 whitespace-pre-wrap">
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
                                {{ previewTask.due_date || 'None' }}
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
                        v-if="isTaskOverdue(previewTask)"
                        @click="openDelayModal(previewTask); previewTask = null;"
                        class="text-xs font-bold text-indigo-600 hover:text-indigo-800"
                    >
                        Edit Delay Reason →
                    </button>
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
