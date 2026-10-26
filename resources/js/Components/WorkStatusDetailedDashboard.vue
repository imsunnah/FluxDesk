<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import {
    BarChart3,
    CheckSquare,
    Clock,
    Calendar,
    AlertTriangle,
    CheckCircle2,
    TrendingUp,
    Filter,
    Users,
    Briefcase,
    Building,
    Search,
    Download,
    Eye,
    Trash2,
    X,
    ShieldCheck,
    Kanban,
    AlertCircle,
    ArrowUpRight,
    Percent,
    Layers,
    FileText,
    Lock,
    Unlock,
    History,
    AlertOctagon,
    PieChart,
    ChevronRight
} from 'lucide-vue-next';

const props = defineProps({
    tasks: {
        type: Array,
        default: () => [],
    },
    allTasks: {
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
    isLeadMode: {
        type: Boolean,
        default: true,
    },
});

const emit = defineEmits([
    'switch-to-kanban',
    'open-new-task',
    'open-complete-modal',
    'open-delay-modal'
]);

// Filters for Audit Table
const searchQuery = ref('');
const filterProject = ref('all');
const filterStatus = ref('all'); // 'all', 'done', 'late_only', 'pending'
const filterDelayCategory = ref('all');
const filterAssignee = ref('all');
const sortField = ref('delay_duration');
const sortDirection = ref('desc');

// Selected task for full detail inspection modal
const inspectingTask = ref(null);

// Today's date string YYYY-MM-DD
const todayStr = computed(() => {
    return new Date().toISOString().split('T')[0];
});

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
// SUMMARY CARDS (USER SPECIFICATION)
// 1. Total Tasks
// 2. On-Time Completion Rate (%)
// 3. Total Overdue
// 4. Active Blockers
// =========================================================
const summaryMetrics = computed(() => {
    const list = props.tasks;
    const total = list.length;
    if (total === 0) {
        return {
            total: 0,
            onTimeRate: 100,
            onTimeCount: 0,
            completedCount: 0,
            totalOverdue: 0,
            activeBlockers: 0,
            overdueRate: 0,
        };
    }

    let overdueCount = 0;
    let completedCount = 0;
    let completedOnTimeCount = 0;
    let blockerCount = 0;

    list.forEach((t) => {
        const isLate = isTaskOverdue(t);
        if (isLate) {
            overdueCount++;
            if (t.delay_category && t.delay_category !== 'Other') {
                blockerCount++;
            }
        }

        if (t.status === 'done') {
            completedCount++;
            if (!isLate) {
                completedOnTimeCount++;
            }
        }
    });

    const onTimeRate = total > 0 ? Math.round(((total - overdueCount) / total) * 100) : 100;
    const overdueRate = total > 0 ? Math.round((overdueCount / total) * 100) : 0;

    return {
        total,
        onTimeRate,
        onTimeCount: total - overdueCount,
        completedCount,
        totalOverdue: overdueCount,
        activeBlockers: blockerCount,
        overdueRate,
    };
});

// =========================================================
// VISUAL CHART 1: DELAYED TASKS CATEGORIZED BY TEAM MEMBER
// =========================================================
const memberDelayChartData = computed(() => {
    const members = props.staffAgents.map((staff) => {
        const staffTasks = props.tasks.filter((t) => {
            if (t.assignees && t.assignees.length > 0) {
                return t.assignees.some(a => a.id === staff.id);
            }
            return t.assigned_to === staff.id;
        });

        const totalAssigned = staffTasks.length;
        const delayedTasks = staffTasks.filter(t => isTaskOverdue(t));
        const delayedCount = delayedTasks.length;

        return {
            id: staff.id,
            name: staff.name,
            avatar: staff.avatar,
            role: staff.role || 'Member',
            totalAssigned,
            delayedCount,
            rate: totalAssigned > 0 ? Math.round((delayedCount / totalAssigned) * 100) : 0,
        };
    }).filter(m => m.totalAssigned > 0);

    // Sort by most delayed tasks first
    return members.sort((a, b) => b.delayedCount - a.delayedCount);
});

// Max delayed count for relative bar scaling
const maxMemberDelayedCount = computed(() => {
    const counts = memberDelayChartData.value.map(m => m.delayedCount);
    return Math.max(1, ...counts);
});

// =========================================================
// VISUAL CHART 2: DONUT CHART - DELAY CAUSES BREAKDOWN
// =========================================================
const delayCausesColors = {
    'Client Blocker': '#ef4444',            // Red
    'Resource Dependency': '#f97316',       // Orange
    'Scope Creep': '#eab308',               // Amber / Yellow
    'Technical Complexity': '#8b5cf6',      // Purple
    'External Dependency': '#06b6d4',       // Cyan
    'Infrastructure / Environment Issue': '#ec4899', // Pink
    'Other': '#64748b',                     // Slate
    'Unspecified': '#cbd5e1'                // Light slate
};

const delayCausesBreakdown = computed(() => {
    const counts = {};
    let totalDelayed = 0;

    props.tasks.filter(t => isTaskOverdue(t)).forEach((t) => {
        totalDelayed++;
        const cause = t.delay_category || 'Unspecified';
        counts[cause] = (counts[cause] || 0) + 1;
    });

    const entries = Object.entries(counts).map(([cause, count]) => {
        const percentage = totalDelayed > 0 ? Math.round((count / totalDelayed) * 100) : 0;
        return {
            cause,
            count,
            percentage,
            color: delayCausesColors[cause] || '#6366f1',
        };
    }).sort((a, b) => b.count - a.count);

    return {
        totalDelayed,
        entries,
    };
});

// Calculate SVG Donut strokeDasharray offsets
const donutSegments = computed(() => {
    const total = delayCausesBreakdown.value.totalDelayed;
    if (total === 0) return [];

    let currentOffset = 0;
    const circumference = 2 * Math.PI * 40; // r=40 -> C ~ 251.32

    return delayCausesBreakdown.value.entries.map((item) => {
        const length = (item.count / total) * circumference;
        const segment = {
            ...item,
            strokeDasharray: `${length} ${circumference - length}`,
            strokeDashoffset: -currentOffset,
        };
        currentOffset += length;
        return segment;
    });
});

// =========================================================
// DETAILED AUDIT TABLE: LIST OF ALL COMPLETED & LATE TASKS
// Columns: Task Name, Assignee, Due Date, Completion Date,
// Delay Duration, Reason, and Locked By.
// =========================================================
const auditTasks = computed(() => {
    // Only show completed or late tasks as specified
    let list = props.tasks.filter((t) => {
        return t.status === 'done' || isTaskOverdue(t);
    });

    // Search query
    if (searchQuery.value) {
        const q = searchQuery.value.toLowerCase().trim();
        list = list.filter((t) => {
            const title = t.title?.toLowerCase() || '';
            const proj = t.project?.name?.toLowerCase() || '';
            const reason = t.delay_reason?.toLowerCase() || '';
            const cat = t.delay_category?.toLowerCase() || '';
            const assignees = t.assignees?.map(a => a.name.toLowerCase()).join(' ') || '';
            return title.includes(q) || proj.includes(q) || reason.includes(q) || cat.includes(q) || assignees.includes(q);
        });
    }

    // Filter project
    if (filterProject.value !== 'all') {
        list = list.filter(t => String(t.project_id) === String(filterProject.value));
    }

    // Filter status
    if (filterStatus.value === 'done') {
        list = list.filter(t => t.status === 'done');
    } else if (filterStatus.value === 'late_only') {
        list = list.filter(t => isTaskOverdue(t));
    }

    // Filter delay category
    if (filterDelayCategory.value !== 'all') {
        list = list.filter(t => t.delay_category === filterDelayCategory.value);
    }

    // Filter assignee
    if (filterAssignee.value !== 'all') {
        list = list.filter((t) => {
            if (t.assignees && t.assignees.length > 0) {
                return t.assignees.some(a => String(a.id) === String(filterAssignee.value));
            }
            return String(t.assigned_to) === String(filterAssignee.value);
        });
    }

    // Sort
    list.sort((a, b) => {
        if (sortField.value === 'delay_duration') {
            const durA = getDelayDurationDays(a);
            const durB = getDelayDurationDays(b);
            return sortDirection.value === 'desc' ? durB - durA : durA - durB;
        }
        if (sortField.value === 'due_date') {
            return sortDirection.value === 'desc' ? (b.due_date || '').localeCompare(a.due_date || '') : (a.due_date || '').localeCompare(b.due_date || '');
        }
        return 0;
    });

    return list;
});

function toggleSort(field) {
    if (sortField.value === field) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortField.value = field;
        sortDirection.value = 'desc';
    }
}

function exportCsv() {
    const headers = ['Task Name', 'Assignee', 'Due Date', 'Completion Date', 'Delay Duration (Days)', 'Delay Category', 'Delay Explanation Note', 'Locked By', 'Status'];
    const rows = auditTasks.value.map((t) => {
        const isLate = isTaskOverdue(t);
        const delayDays = getDelayDurationDays(t);
        const assignees = t.assignees?.map(a => a.name).join(', ') || t.assignee?.name || 'Unassigned';
        const completionDate = t.status === 'done' && t.completed_at ? t.completed_at.split('T')[0] : (t.status === 'done' ? 'Completed' : 'Pending');
        const lockedBy = t.delay_status === 'finalized_by_lead' ? (t.delay_locked_by_user?.name || 'Team Lead') : 'Not Locked';

        return [
            `"${(t.title || '').replace(/"/g, '""')}"`,
            `"${assignees.replace(/"/g, '""')}"`,
            t.due_date || 'N/A',
            completionDate,
            delayDays,
            `"${(t.delay_category || 'N/A').replace(/"/g, '""')}"`,
            `"${(t.delay_reason || 'N/A').replace(/"/g, '""')}"`,
            `"${lockedBy.replace(/"/g, '""')}"`,
            t.status
        ];
    });

    const csvContent = 'data:text/csv;charset=utf-8,' + [headers.join(','), ...rows.map(e => e.join(','))].join('\n');
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement('a');
    link.setAttribute('href', encodedUri);
    link.setAttribute('download', `late_and_completed_tasks_audit_${todayStr.value}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<template>
    <div class="flex-1 flex flex-col h-full overflow-hidden bg-slate-50">
        <!-- Sticky Top Navigation Header -->
        <div class="px-6 py-4 bg-white border-b border-slate-200 shrink-0 shadow-2xs">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
                <div class="flex items-center space-x-3">
                    <button
                        @click="emit('switch-to-kanban')"
                        class="px-3 py-1.5 rounded-xl border border-slate-200 bg-white hover:bg-slate-50 text-slate-700 text-xs font-semibold flex items-center space-x-1.5 shadow-2xs transition-all hover:border-slate-300"
                        title="Return to Workspace Board"
                    >
                        <Kanban class="w-3.5 h-3.5 text-indigo-600" />
                        <span>← Back to Workspace</span>
                    </button>

                    <div>
                        <div class="flex items-center space-x-2">
                            <BarChart3 class="w-5 h-5 text-indigo-600" />
                            <h2 class="text-base font-bold text-slate-900">Analytics & Late Task Insights</h2>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                Tab / View 2
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Delays breakdown, cause analysis, team member metrics, and hierarchical reason audit.
                        </p>
                    </div>
                </div>

                <div class="flex items-center space-x-2">
                    <button
                        @click="exportCsv"
                        class="px-3 py-1.5 rounded-xl border border-slate-200 bg-slate-50 hover:bg-slate-100 text-slate-700 text-xs font-semibold flex items-center space-x-1.5 transition-colors"
                        title="Export audit table to CSV"
                    >
                        <Download class="w-3.5 h-3.5 text-slate-500" />
                        <span>Export Audit CSV</span>
                    </button>

                    <button
                        @click="emit('open-new-task')"
                        class="px-3.5 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-semibold shadow-xs flex items-center space-x-1.5 transition-colors"
                    >
                        <span>+ New Task</span>
                    </button>
                </div>
            </div>

            <!-- ========================================================= -->
            <!-- SUMMARY CARDS (SPECIFICATION 3)                           -->
            <!-- 1. Total Tasks                                            -->
            <!-- 2. On-Time Completion Rate (%)                            -->
            <!-- 3. Total Overdue                                          -->
            <!-- 4. Active Blockers                                        -->
            <!-- ========================================================= -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mt-4">
                <!-- CARD 1: TOTAL TASKS -->
                <div class="p-3.5 rounded-2xl border border-slate-200 bg-white shadow-2xs flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500 flex items-center space-x-1">
                            <CheckSquare class="w-3.5 h-3.5 text-slate-600" />
                            <span>Total Tasks</span>
                        </span>
                        <span class="text-[10px] font-mono px-2 py-0.2 rounded font-bold bg-slate-100 text-slate-700">
                            Active Scope
                        </span>
                    </div>
                    <div class="mt-2 flex items-baseline space-x-2">
                        <div class="text-2xl font-black text-slate-900 font-mono">
                            {{ summaryMetrics.total }}
                        </div>
                        <span class="text-[11px] text-slate-500 font-medium">Tasks</span>
                    </div>
                    <div class="mt-2 text-[10px] text-slate-500 flex items-center justify-between border-t border-slate-100 pt-1.5">
                        <span class="text-emerald-600 font-semibold">{{ summaryMetrics.completedCount }} Completed</span>
                        <span>{{ summaryMetrics.total - summaryMetrics.completedCount }} In Progress</span>
                    </div>
                </div>

                <!-- CARD 2: ON-TIME COMPLETION RATE (HIGH-CONTRAST GREEN) -->
                <div class="p-3.5 rounded-2xl border border-emerald-200 bg-emerald-50/50 shadow-2xs flex flex-col justify-between">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-800 flex items-center space-x-1">
                            <CheckCircle2 class="w-3.5 h-3.5 text-emerald-600" />
                            <span>On-Time Rate</span>
                        </span>
                        <span class="text-[10px] font-mono px-2 py-0.2 rounded font-bold bg-emerald-100 text-emerald-800">
                            {{ summaryMetrics.onTimeCount }}/{{ summaryMetrics.total }}
                        </span>
                    </div>
                    <div class="mt-2 flex items-baseline space-x-2">
                        <div class="text-2xl font-black text-emerald-700 font-mono">
                            {{ summaryMetrics.onTimeRate }}%
                        </div>
                        <span class="text-[11px] text-emerald-600 font-medium">On Schedule</span>
                    </div>
                    <div class="mt-2 w-full h-1.5 rounded-full bg-emerald-200/70 overflow-hidden">
                        <div class="h-full bg-emerald-600 rounded-full transition-all duration-500" :style="{ width: `${summaryMetrics.onTimeRate}%` }"></div>
                    </div>
                    <div class="mt-1.5 text-[10px] text-emerald-700 font-medium">
                        Completed within deadline without delays
                    </div>
                </div>

                <!-- CARD 3: TOTAL OVERDUE (HIGH-CONTRAST RED) -->
                <div :class="[
                    'p-3.5 rounded-2xl border shadow-2xs flex flex-col justify-between transition-all',
                    summaryMetrics.totalOverdue > 0 ? 'bg-rose-50/70 border-rose-200' : 'bg-white border-slate-200'
                ]">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-rose-700 flex items-center space-x-1">
                            <AlertTriangle class="w-3.5 h-3.5 text-rose-600" />
                            <span>Total Overdue</span>
                        </span>
                        <span class="text-[10px] font-mono px-2 py-0.2 rounded font-bold bg-rose-100 text-rose-800">
                            {{ summaryMetrics.overdueRate }}% of Total
                        </span>
                    </div>
                    <div class="mt-2 flex items-baseline space-x-2">
                        <div class="text-2xl font-black text-rose-700 font-mono">
                            {{ summaryMetrics.totalOverdue }}
                        </div>
                        <span class="text-[11px] text-rose-600 font-medium">Late Tasks</span>
                    </div>
                    <div class="mt-2 w-full h-1.5 rounded-full bg-rose-200/70 overflow-hidden">
                        <div class="h-full bg-rose-600 rounded-full transition-all duration-500" :style="{ width: `${summaryMetrics.overdueRate}%` }"></div>
                    </div>
                    <div class="mt-1.5 text-[10px] text-rose-700 font-medium flex items-center justify-between">
                        <span>Requires Delay Reason</span>
                        <span class="font-bold">Urgent</span>
                    </div>
                </div>

                <!-- CARD 4: ACTIVE BLOCKERS (HIGH-CONTRAST AMBER) -->
                <div :class="[
                    'p-3.5 rounded-2xl border shadow-2xs flex flex-col justify-between transition-all',
                    summaryMetrics.activeBlockers > 0 ? 'bg-amber-50/60 border-amber-200' : 'bg-white border-slate-200'
                ]">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-amber-800 flex items-center space-x-1">
                            <AlertOctagon class="w-3.5 h-3.5 text-amber-600" />
                            <span>Active Blockers</span>
                        </span>
                        <span class="text-[10px] font-mono px-2 py-0.2 rounded font-bold bg-amber-100 text-amber-800">
                            Categorized
                        </span>
                    </div>
                    <div class="mt-2 flex items-baseline space-x-2">
                        <div class="text-2xl font-black text-amber-800 font-mono">
                            {{ summaryMetrics.activeBlockers }}
                        </div>
                        <span class="text-[11px] text-amber-700 font-medium">Recorded Impediments</span>
                    </div>
                    <div class="mt-2 text-[10px] text-amber-800 leading-tight border-t border-amber-200/60 pt-1.5">
                        Client, resource, or scope blockers needing team lead resolution.
                    </div>
                </div>
            </div>
        </div>

        <!-- Scrollable Analytics Body -->
        <div class="flex-1 overflow-y-auto p-6 space-y-6">
            <!-- ========================================================= -->
            <!-- VISUAL CHARTS SECTION (SPECIFICATION 3)                    -->
            <!-- Chart 1: Bar Chart - Delayed tasks by team member          -->
            <!-- Chart 2: Donut Chart - Delay causes breakdown              -->
            <!-- ========================================================= -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                <!-- CHART 1: BAR CHART (DELAYED TASKS CATEGORIZED BY TEAM MEMBER) -->
                <div class="p-5 bg-white rounded-2xl border border-slate-200 shadow-xs space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div class="flex items-center space-x-2">
                            <BarChart3 class="w-4 h-4 text-indigo-600" />
                            <h3 class="text-xs font-bold text-slate-900">Delayed Tasks by Team Member</h3>
                        </div>
                        <span class="text-[10px] font-mono font-bold text-slate-400">
                            {{ memberDelayChartData.length }} Active Members
                        </span>
                    </div>

                    <!-- Horizontal Bar Chart -->
                    <div class="space-y-3.5 pt-1">
                        <div
                            v-for="member in memberDelayChartData"
                            :key="member.id"
                            class="space-y-1.5"
                        >
                            <div class="flex items-center justify-between text-xs">
                                <div class="flex items-center space-x-2">
                                    <img :src="member.avatar || 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=50&h=50&fit=crop'" class="w-5 h-5 rounded-full object-cover shrink-0" />
                                    <span class="font-bold text-slate-800">{{ member.name }}</span>
                                    <span class="text-[10px] text-slate-400 font-mono">({{ member.totalAssigned }} tasks)</span>
                                </div>
                                <div class="flex items-center space-x-2 font-mono text-[11px]">
                                    <span :class="member.delayedCount > 0 ? 'text-rose-600 font-bold' : 'text-emerald-600'">
                                        {{ member.delayedCount }} delayed ({{ member.rate }}%)
                                    </span>
                                </div>
                            </div>

                            <!-- Bar Graphic -->
                            <div class="w-full h-3 rounded-full bg-slate-100 overflow-hidden flex">
                                <div
                                    :class="[
                                        'h-full rounded-full transition-all duration-700',
                                        member.delayedCount > 0 ? 'bg-rose-500' : 'bg-emerald-500'
                                    ]"
                                    :style="{ width: `${(member.delayedCount / maxMemberDelayedCount) * 100}%` }"
                                ></div>
                            </div>
                        </div>

                        <div v-if="memberDelayChartData.length === 0" class="py-8 text-center text-slate-400 text-xs">
                            No team workload data available in active scope.
                        </div>
                    </div>
                </div>

                <!-- CHART 2: DONUT CHART (DELAY CAUSES BREAKDOWN) -->
                <div class="p-5 bg-white rounded-2xl border border-slate-200 shadow-xs space-y-4">
                    <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div class="flex items-center space-x-2">
                            <PieChart class="w-4 h-4 text-indigo-600" />
                            <h3 class="text-xs font-bold text-slate-900">Delay Causes Breakdown</h3>
                        </div>
                        <span class="text-[10px] font-mono font-bold text-rose-700 bg-rose-50 px-2 py-0.5 rounded-full border border-rose-200">
                            {{ delayCausesBreakdown.totalDelayed }} Total Delayed
                        </span>
                    </div>

                    <!-- SVG Donut Chart + Legend Grid -->
                    <div class="flex flex-col sm:flex-row items-center justify-center gap-6 pt-1">
                        <!-- SVG Donut Graphic -->
                        <div class="relative w-36 h-36 shrink-0 flex items-center justify-center">
                            <svg viewBox="0 0 100 100" class="w-full h-full transform -rotate-90">
                                <!-- Background Track -->
                                <circle
                                    cx="50"
                                    cy="50"
                                    r="40"
                                    fill="transparent"
                                    stroke="#f1f5f9"
                                    stroke-width="12"
                                />
                                <!-- Donut Segments -->
                                <circle
                                    v-for="(seg, idx) in donutSegments"
                                    :key="idx"
                                    cx="50"
                                    cy="50"
                                    r="40"
                                    fill="transparent"
                                    :stroke="seg.color"
                                    stroke-width="12"
                                    :stroke-dasharray="seg.strokeDasharray"
                                    :stroke-dashoffset="seg.strokeDashoffset"
                                    class="transition-all duration-700"
                                />
                            </svg>

                            <!-- Center Label -->
                            <div class="absolute inset-0 flex flex-col items-center justify-center text-center">
                                <span class="text-lg font-black font-mono text-slate-800 leading-none">
                                    {{ delayCausesBreakdown.totalDelayed }}
                                </span>
                                <span class="text-[9px] text-slate-400 font-bold uppercase tracking-wider mt-0.5">
                                    Causes
                                </span>
                            </div>
                        </div>

                        <!-- Legend with breakdown and percentages -->
                        <div class="flex-1 space-y-1.5 w-full">
                            <div
                                v-for="item in delayCausesBreakdown.entries"
                                :key="item.cause"
                                class="flex items-center justify-between p-1 rounded-lg hover:bg-slate-50 transition-colors text-xs"
                            >
                                <div class="flex items-center space-x-2 truncate">
                                    <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ backgroundColor: item.color }"></span>
                                    <span class="font-medium text-slate-700 truncate max-w-[150px]">{{ item.cause }}</span>
                                </div>
                                <div class="flex items-center space-x-2 font-mono text-[11px] shrink-0">
                                    <span class="font-bold text-slate-900">{{ item.count }}</span>
                                    <span class="text-slate-400">({{ item.percentage }}%)</span>
                                </div>
                            </div>

                            <div v-if="delayCausesBreakdown.entries.length === 0" class="text-center text-slate-400 py-6 text-xs">
                                No delay causes recorded. All tasks are currently on schedule!
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================================= -->
            <!-- DETAILED AUDIT TABLE (SPECIFICATION 3)                    -->
            <!-- Columns: Task Name, Assignee, Due Date, Completion Date,   -->
            <!-- Delay Duration, Reason, and Locked By                     -->
            <!-- ========================================================= -->
            <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                <!-- Table Controls Header -->
                <div class="p-4 border-b border-slate-100 bg-slate-50/60 flex flex-col md:flex-row md:items-center justify-between gap-3">
                    <div class="flex items-center space-x-2">
                        <FileText class="w-4 h-4 text-indigo-600" />
                        <div>
                            <h3 class="text-xs font-bold text-slate-900">Detailed Late & Completed Tasks Audit Table</h3>
                            <span class="text-[11px] text-slate-500">
                                Showing {{ auditTasks.length }} completed and overdue tasks with hierarchical locking records
                            </span>
                        </div>
                    </div>

                    <!-- Search Input -->
                    <div class="relative w-full md:w-72">
                        <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-2.5" />
                        <input
                            v-model="searchQuery"
                            type="text"
                            placeholder="Search tasks, reason, project, assignee..."
                            class="w-full pl-9 pr-3 py-1.5 bg-white border border-slate-200 rounded-xl text-xs text-slate-800 placeholder-slate-400 focus:outline-none focus:border-indigo-600 shadow-2xs"
                        />
                        <button v-if="searchQuery" @click="searchQuery = ''" class="absolute right-2.5 top-2 text-slate-400 hover:text-slate-600">
                            <X class="w-3.5 h-3.5" />
                        </button>
                    </div>
                </div>

                <!-- Table Filters -->
                <div class="px-4 py-2.5 border-b border-slate-100 bg-white flex flex-wrap items-center gap-2 text-xs">
                    <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider flex items-center space-x-1 mr-1">
                        <Filter class="w-3 h-3" />
                        <span>Filter:</span>
                    </span>

                    <!-- Status Filter -->
                    <select
                        v-model="filterStatus"
                        class="px-2.5 py-1 bg-slate-50 border border-slate-200 rounded-lg text-xs font-medium text-slate-700 focus:outline-none focus:border-indigo-600"
                    >
                        <option value="all">All Audited Tasks</option>
                        <option value="late_only">🔴 Overdue / Late Only</option>
                        <option value="done">🟢 Completed Tasks Only</option>
                    </select>

                    <!-- Delay Category Filter -->
                    <select
                        v-model="filterDelayCategory"
                        class="px-2.5 py-1 bg-slate-50 border border-slate-200 rounded-lg text-xs font-medium text-slate-700 focus:outline-none focus:border-indigo-600"
                    >
                        <option value="all">All Delay Causes</option>
                        <option v-for="cause in Object.keys(delayCausesColors)" :key="cause" :value="cause">
                            {{ cause }}
                        </option>
                    </select>

                    <!-- Project Filter -->
                    <select
                        v-model="filterProject"
                        class="px-2.5 py-1 bg-slate-50 border border-slate-200 rounded-lg text-xs font-medium text-slate-700 focus:outline-none focus:border-indigo-600"
                    >
                        <option value="all">All Projects</option>
                        <option v-for="p in projects" :key="p.id" :value="p.id">
                            {{ p.name }}
                        </option>
                    </select>

                    <!-- Assignee Filter -->
                    <select
                        v-model="filterAssignee"
                        class="px-2.5 py-1 bg-slate-50 border border-slate-200 rounded-lg text-xs font-medium text-slate-700 focus:outline-none focus:border-indigo-600"
                    >
                        <option value="all">All Assignees</option>
                        <option v-for="agent in staffAgents" :key="agent.id" :value="agent.id">
                            {{ agent.name }}
                        </option>
                    </select>

                    <button
                        v-if="filterStatus !== 'all' || filterDelayCategory !== 'all' || filterProject !== 'all' || filterAssignee !== 'all' || searchQuery"
                        @click="filterStatus = 'all'; filterDelayCategory = 'all'; filterProject = 'all'; filterAssignee = 'all'; searchQuery = '';"
                        class="px-2 py-1 text-[11px] font-semibold text-rose-600 hover:text-rose-700 hover:underline"
                    >
                        Reset Filters
                    </button>
                </div>

                <!-- TABLE CONTENT WITH PRESCRIBED COLUMNS:
                     Task Name, Assignee, Due Date, Completion Date,
                     Delay Duration, Reason, and Locked By -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 border-b border-slate-200 text-[10px] uppercase font-bold text-slate-500">
                            <tr>
                                <th class="px-4 py-3">1. Task Name</th>
                                <th class="px-3 py-3">2. Assignee</th>
                                <th @click="toggleSort('due_date')" class="px-3 py-3 cursor-pointer hover:text-slate-800">
                                    3. Due Date
                                </th>
                                <th class="px-3 py-3">4. Completion Date</th>
                                <th @click="toggleSort('delay_duration')" class="px-3 py-3 cursor-pointer hover:text-slate-800">
                                    5. Delay Duration
                                </th>
                                <th class="px-3 py-3">6. Reason (Category + Explanation)</th>
                                <th class="px-3 py-3">7. Locked By & Review Status</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr
                                v-for="task in auditTasks"
                                :key="task.id"
                                :class="[
                                    'hover:bg-slate-50/80 transition-colors',
                                    isTaskOverdue(task) ? 'bg-rose-50/15' : ''
                                ]"
                            >
                                <!-- Column 1: Task Name -->
                                <td class="px-4 py-3 max-w-[220px]">
                                    <div class="font-bold text-slate-900 leading-snug truncate" :title="task.title">
                                        {{ task.title }}
                                    </div>
                                    <div class="text-[10px] text-indigo-700 font-semibold truncate mt-0.5">
                                        {{ task.project?.name || 'General Project' }}
                                    </div>
                                </td>

                                <!-- Column 2: Assignee -->
                                <td class="px-3 py-3 whitespace-nowrap">
                                    <div class="flex items-center space-x-1.5">
                                        <img
                                            :src="task.assignees?.[0]?.avatar || task.assignee?.avatar || 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=50&h=50&fit=crop'"
                                            class="w-5 h-5 rounded-full object-cover ring-1 ring-white"
                                        />
                                        <span class="text-slate-800 font-medium">
                                            {{ task.assignees?.map(a => a.name.split(' ')[0]).join(', ') || task.assignee?.name || 'Unassigned' }}
                                        </span>
                                    </div>
                                </td>

                                <!-- Column 3: Due Date -->
                                <td class="px-3 py-3 font-mono whitespace-nowrap text-slate-700">
                                    {{ task.due_date || 'N/A' }}
                                </td>

                                <!-- Column 4: Completion Date -->
                                <td class="px-3 py-3 font-mono whitespace-nowrap">
                                    <span v-if="task.status === 'done'" class="text-emerald-700 font-bold flex items-center space-x-1">
                                        <CheckCircle2 class="w-3.5 h-3.5 text-emerald-600" />
                                        <span>{{ task.completed_at ? task.completed_at.split('T')[0] : 'Completed' }}</span>
                                    </span>
                                    <span v-else class="text-rose-600 font-semibold flex items-center space-x-1">
                                        <Clock class="w-3.5 h-3.5 text-rose-500" />
                                        <span>Not Completed</span>
                                    </span>
                                </td>

                                <!-- Column 5: Delay Duration (High-contrast badges) -->
                                <td class="px-3 py-3 whitespace-nowrap">
                                    <template v-if="isTaskOverdue(task)">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold bg-rose-100 text-rose-800 border border-rose-300 shadow-2xs">
                                            +{{ getDelayDurationDays(task) }} days late
                                        </span>
                                    </template>
                                    <template v-else-if="task.status === 'done'">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                                            On-Time
                                        </span>
                                    </template>
                                    <template v-else>
                                        <span class="text-slate-400 font-mono text-[11px]">—</span>
                                    </template>
                                </td>

                                <!-- Column 6: Reason (Category + Explanation) -->
                                <td class="px-3 py-3 max-w-[240px]">
                                    <div v-if="task.delay_category" class="space-y-0.5">
                                        <span class="inline-block px-1.5 py-0.2 rounded text-[9px] font-bold bg-slate-100 text-slate-800 border border-slate-300">
                                            {{ task.delay_category }}
                                        </span>
                                        <p class="text-[11px] text-slate-600 line-clamp-2 leading-relaxed" :title="task.delay_reason">
                                            {{ task.delay_reason || 'No explanation note entered.' }}
                                        </p>
                                    </div>
                                    <span v-else-if="isTaskOverdue(task)" class="text-rose-600 font-bold text-[10px] flex items-center space-x-1">
                                        <AlertTriangle class="w-3 h-3" />
                                        <span>Delay Reason Missing</span>
                                    </span>
                                    <span v-else class="text-slate-400 text-xs">No delay</span>
                                </td>

                                <!-- Column 7: Locked By & Review Status -->
                                <td class="px-3 py-3 whitespace-nowrap">
                                    <!-- FINALIZED BY LEAD (PERMANENTLY LOCKED) -->
                                    <div v-if="task.delay_status === 'finalized_by_lead'" class="flex items-center space-x-1.5">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-100 text-indigo-800 border border-indigo-200 flex items-center space-x-1">
                                            <Lock class="w-3 h-3 text-indigo-700" />
                                            <span>Locked by {{ task.delay_locked_by_user?.name || 'Team Lead' }}</span>
                                        </span>
                                    </div>

                                    <!-- AWAITING LEAD REVIEW -->
                                    <div v-else-if="task.delay_status === 'awaiting_lead_review'" class="flex items-center space-x-1.5">
                                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-200 flex items-center space-x-1">
                                            <Clock class="w-3 h-3 text-amber-600" />
                                            <span>Awaiting Lead Review</span>
                                        </span>
                                    </div>

                                    <!-- NO REASON / NOT LOCKED -->
                                    <div v-else-if="isTaskOverdue(task)">
                                        <span class="text-[10px] text-slate-400">Not locked</span>
                                    </div>

                                    <div v-else>
                                        <span class="text-emerald-700 text-[10px] font-medium">Completed on time</span>
                                    </div>
                                </td>

                                <!-- Column 8: Actions -->
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end space-x-1">
                                        <button
                                            v-if="isTaskOverdue(task)"
                                            @click="emit('open-delay-modal', task)"
                                            class="px-2 py-1 rounded bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold text-[10px] border border-indigo-200 transition-colors"
                                            title="View / Modify Delay Reason and Audit Logs"
                                        >
                                            {{ task.delay_status === 'finalized_by_lead' && !isLeadMode ? 'View Locked Reason' : 'Review Reason' }}
                                        </button>
                                        <button
                                            @click="inspectingTask = task"
                                            class="p-1 rounded text-slate-400 hover:text-indigo-600 hover:bg-slate-100 transition-colors"
                                            title="Inspect All Details"
                                        >
                                            <Eye class="w-4 h-4" />
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <tr v-if="auditTasks.length === 0">
                                <td colspan="8" class="py-12 text-center text-slate-400 text-xs">
                                    No completed or late tasks match your filter criteria.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Full Task Inspection Modal -->
        <div
            v-if="inspectingTask"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs"
        >
            <div class="w-full max-w-lg bg-white rounded-2xl border border-slate-200 shadow-2xl p-6 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center space-x-2">
                        <CheckSquare class="w-5 h-5 text-indigo-600" />
                        <div>
                            <h3 class="text-sm font-bold text-slate-900">{{ inspectingTask.title }}</h3>
                            <div class="text-[10px] text-slate-400 font-mono">ID #{{ inspectingTask.id }}</div>
                        </div>
                    </div>
                    <button
                        @click="inspectingTask = null"
                        class="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100"
                    >
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div class="space-y-3 text-xs">
                    <!-- Delay Section -->
                    <div v-if="isTaskOverdue(inspectingTask)" class="p-3 bg-rose-50 border border-rose-200 rounded-xl space-y-1.5">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-rose-800 text-xs">Overdue Status: +{{ getDelayDurationDays(inspectingTask) }} days late</span>
                            <span v-if="inspectingTask.delay_status === 'finalized_by_lead'" class="text-[10px] font-bold text-indigo-700 bg-indigo-100 px-2 py-0.5 rounded-full">
                                🔒 Finalized by Lead
                            </span>
                        </div>
                        <div class="text-xs text-rose-950">
                            <strong>Category:</strong> {{ inspectingTask.delay_category || 'Unassigned' }}
                            <p class="mt-0.5 text-slate-700 whitespace-pre-wrap">{{ inspectingTask.delay_reason || 'No note entered.' }}</p>
                        </div>
                        <div v-if="inspectingTask.delay_revisions && inspectingTask.delay_revisions.length > 0" class="pt-1.5 border-t border-rose-200/60 text-[10px] text-rose-800">
                            <strong>Revisions Logged:</strong> {{ inspectingTask.delay_revisions.length }} entries
                        </div>
                    </div>

                    <!-- Details -->
                    <div class="grid grid-cols-2 gap-2">
                        <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-200">
                            <span class="text-[10px] text-slate-400 font-medium">Due Date</span>
                            <div class="font-bold text-slate-800">{{ inspectingTask.due_date || 'N/A' }}</div>
                        </div>
                        <div class="p-2.5 bg-slate-50 rounded-xl border border-slate-200">
                            <span class="text-[10px] text-slate-400 font-medium">Completion Date</span>
                            <div class="font-bold text-slate-800">{{ inspectingTask.completed_at ? inspectingTask.completed_at.split('T')[0] : (inspectingTask.status === 'done' ? 'Completed' : 'Pending') }}</div>
                        </div>
                    </div>

                    <div v-if="inspectingTask.description" class="p-2.5 bg-slate-50 rounded-xl border border-slate-200">
                        <span class="text-[10px] text-slate-400 font-medium">Description</span>
                        <p class="text-slate-700 whitespace-pre-wrap mt-0.5">{{ inspectingTask.description }}</p>
                    </div>
                </div>

                <div class="pt-3 border-t border-slate-100 flex items-center justify-between">
                    <button
                        v-if="isTaskOverdue(inspectingTask)"
                        @click="emit('open-delay-modal', inspectingTask); inspectingTask = null;"
                        class="text-xs font-bold text-indigo-600 hover:text-indigo-800 flex items-center space-x-1"
                    >
                        <span>Open Delay Reason & Revision Log</span>
                        <ChevronRight class="w-3.5 h-3.5" />
                    </button>
                    <button
                        @click="inspectingTask = null"
                        class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold"
                    >
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
