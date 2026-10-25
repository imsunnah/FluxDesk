<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import {
    ShieldCheck,
    FolderKanban,
    Users,
    UserCheck,
    CheckSquare,
    Ticket,
    Plus,
    Search,
    Trash2,
    CheckCircle2,
    Clock,
    AlertTriangle,
    ArrowRight,
    Star,
    Layers,
    X,
    Building2,
    UserPlus,
    Briefcase,
    Tag,
    Filter
} from 'lucide-vue-next';

const props = defineProps({
    projects: {
        type: Array,
        default: () => [],
    },
    agents: {
        type: Array,
        default: () => [],
    },
    tasks: {
        type: Array,
        default: () => [],
    },
    conversations: {
        type: Array,
        default: () => [],
    },
    currentAgent: {
        type: Object,
        default: () => ({}),
    },
    departments: {
        type: Array,
        default: () => [
            'Engineering',
            'Technical Support',
            'Customer Success',
            'Quality Assurance (QA)',
            'DevOps & Infrastructure',
            'Product & Design',
            'Management & Operations'
        ],
    },
});

const emit = defineEmits(['switch-to-tab']);

// Active sub-section within Admin Panel: 'overview' | 'clients' | 'projects' | 'staff' | 'overtime_audit'
const activeSection = ref('overview');

// Search & filter states
const projectSearch = ref('');
const staffSearch = ref('');
const clientSearch = ref('');
const selectedStaffDepartment = ref('all');

// Modals
const isCreateProjectModalOpen = ref(false);
const isCreateUserModalOpen = ref(false);
const createUserRoleType = ref('client'); // 'client' | 'staff'

// New Project Form
const projectForm = ref({
    name: '',
    client_id: '',
    description: '',
    status: 'active',
});

// New User Form (with Department and Designation)
const userForm = ref({
    name: '',
    email: '',
    password: 'password123',
    role: 'Staff Employee',
    department: 'Engineering',
    designation: 'Software Engineer',
    is_customer: false,
    is_team_lead: false,
    project_id: '',
    assigned_agent_id: '',
});

// Overview Statistics
const stats = computed(() => {
    const totalProjects = props.projects.length;
    const activeProjects = props.projects.filter(p => p.status === 'active').length;
    
    const clients = props.agents.filter(u => u.is_customer);
    const staff = props.agents.filter(u => !u.is_customer);
    const teamLeads = staff.filter(u => u.is_team_lead);

    const totalTasks = props.tasks.length;
    const doneTasks = props.tasks.filter(t => t.status === 'done').length;
    const overtimeTasks = props.tasks.filter(t => (t.time_ratio || 0) > 1.0);

    const tickets = props.conversations.filter(c => c.type === 'ticket');
    const openTickets = tickets.filter(c => c.status === 'open').length;

    // Department counts
    const deptCounts = {};
    staff.forEach(s => {
        const d = s.department || 'General';
        deptCounts[d] = (deptCounts[d] || 0) + 1;
    });

    return {
        totalProjects,
        activeProjects,
        totalClients: clients.length,
        totalStaff: staff.length,
        totalTeamLeads: teamLeads.length,
        totalTasks,
        doneTasks,
        overtimeTasksCount: overtimeTasks.length,
        totalTickets: tickets.length,
        openTickets,
        deptCounts,
    };
});

// Helper to get all projects belonging to a client
function getClientProjects(user) {
    return props.projects.filter(p => p.client_id === user.id);
}

// Department badge color helper
function getDeptColor(dept) {
    const d = (dept || '').toLowerCase();
    if (d.includes('eng') || d.includes('dev')) return 'bg-blue-50 text-blue-700 border-blue-200';
    if (d.includes('support') || d.includes('success')) return 'bg-emerald-50 text-emerald-700 border-emerald-200';
    if (d.includes('product') || d.includes('design')) return 'bg-purple-50 text-purple-700 border-purple-200';
    if (d.includes('qa') || d.includes('quality')) return 'bg-amber-50 text-amber-700 border-amber-200';
    if (d.includes('manage') || d.includes('operat')) return 'bg-indigo-50 text-indigo-700 border-indigo-200';
    return 'bg-slate-100 text-slate-700 border-slate-200';
}

// Filtered Lists
const filteredProjects = computed(() => {
    if (!projectSearch.value) return props.projects;
    const q = projectSearch.value.toLowerCase();
    return props.projects.filter(p => 
        p.name.toLowerCase().includes(q) || 
        (p.client?.name || '').toLowerCase().includes(q) ||
        (p.description || '').toLowerCase().includes(q)
    );
});

const staffList = computed(() => {
    let list = props.agents.filter(u => !u.is_customer);
    
    // Department filter
    if (selectedStaffDepartment.value !== 'all') {
        list = list.filter(u => (u.department || 'General') === selectedStaffDepartment.value);
    }

    if (staffSearch.value) {
        const q = staffSearch.value.toLowerCase();
        list = list.filter(u => 
            u.name.toLowerCase().includes(q) || 
            u.email.toLowerCase().includes(q) ||
            (u.member_id || '').toLowerCase().includes(q) ||
            (u.department || '').toLowerCase().includes(q) ||
            (u.designation || '').toLowerCase().includes(q) ||
            (u.role || '').toLowerCase().includes(q)
        );
    }
    return list;
});

const clientList = computed(() => {
    let list = props.agents.filter(u => u.is_customer);
    if (clientSearch.value) {
        const q = clientSearch.value.toLowerCase();
        list = list.filter(u => {
            const nameMatch = u.name.toLowerCase().includes(q);
            const emailMatch = u.email.toLowerCase().includes(q);
            const memberMatch = (u.member_id || '').toLowerCase().includes(q);
            const projMatch = getClientProjects(u).some(p => p.name.toLowerCase().includes(q));
            return nameMatch || emailMatch || memberMatch || projMatch;
        });
    }
    return list;
});

const overtimeTasksList = computed(() => {
    return props.tasks.filter(t => (t.time_ratio || 0) > 1.0);
});

// Actions
function submitProject() {
    if (!projectForm.value.name.trim() || !projectForm.value.client_id) return;
    router.post('/projects', projectForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            isCreateProjectModalOpen.value = false;
            projectForm.value = { name: '', client_id: '', description: '', status: 'active' };
        }
    });
}

function openAddProjectForClient(client) {
    projectForm.value = {
        name: '',
        client_id: client.id,
        description: '',
        status: 'active',
    };
    isCreateProjectModalOpen.value = true;
}

function updateProjectStatus(project, newStatus) {
    router.patch(`/projects/${project.id}`, { status: newStatus }, { preserveScroll: true });
}

function deleteProject(project) {
    if (confirm(`Are you sure you want to delete project "${project.name}"?`)) {
        router.delete(`/projects/${project.id}`, { preserveScroll: true });
    }
}

function toggleTeamLead(user) {
    router.post(`/users/${user.id}/toggle-team-lead`, {}, { preserveScroll: true });
}

function deleteUser(user) {
    if (confirm(`Delete account for ${user.name}? This cannot be undone.`)) {
        router.delete(`/members/${user.id}`, { preserveScroll: true });
    }
}

function openCreateUserModal(type) {
    createUserRoleType.value = type;
    if (type === 'client') {
        userForm.value = {
            name: '',
            email: '',
            password: 'password123',
            role: 'Client / Customer',
            department: 'Client Accounts',
            designation: 'Client Representative',
            is_customer: true,
            is_team_lead: false,
            project_id: '',
            assigned_agent_id: '',
        };
    } else {
        userForm.value = {
            name: '',
            email: '',
            password: 'password123',
            role: 'Staff Employee',
            department: 'Engineering',
            designation: 'Software Engineer',
            is_customer: false,
            is_team_lead: false,
            project_id: '',
        };
    }
    isCreateUserModalOpen.value = true;
}

function submitUser() {
    if (!userForm.value.name.trim() || !userForm.value.email.trim()) return;
    router.post('/members', userForm.value, {
        preserveScroll: true,
        onSuccess: () => {
            isCreateUserModalOpen.value = false;
        }
    });
}
</script>

<template>
    <div class="flex-1 flex flex-col h-full bg-slate-50 overflow-y-auto">
        <!-- Top Banner / Control Title -->
        <div class="bg-white border-b border-slate-200 px-6 py-5">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl bg-indigo-600 text-white flex items-center justify-center shadow-xs">
                        <ShieldCheck class="w-5 h-5" />
                    </div>
                    <div>
                        <div class="flex items-center space-x-2">
                            <h1 class="text-lg font-bold text-slate-900 tracking-tight">Admin Setup & Operations Hub</h1>
                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                Administrator Control
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 mt-0.5">
                            Setup Clients first &rarr; assign multiple Projects &rarr; configure Staff Departments & Designations &rarr; toggle Team Leads.
                        </p>
                    </div>
                </div>

                <!-- Fast Actions -->
                <div class="flex items-center space-x-2">
                    <button
                        @click="openCreateUserModal('client')"
                        class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-semibold flex items-center space-x-1.5 shadow-xs transition-colors"
                    >
                        <UserPlus class="w-3.5 h-3.5" />
                        <span>1. Add Client</span>
                    </button>
                    <button
                        @click="isCreateProjectModalOpen = true"
                        class="px-3 py-2 bg-white hover:bg-slate-50 border border-slate-200 text-slate-700 rounded-lg text-xs font-semibold flex items-center space-x-1.5 shadow-xs transition-colors"
                    >
                        <FolderKanban class="w-3.5 h-3.5 text-indigo-600" />
                        <span>2. Add Project for Client</span>
                    </button>
                    <button
                        @click="openCreateUserModal('staff')"
                        class="px-3 py-2 bg-white hover:bg-slate-50 border border-slate-200 text-slate-700 rounded-lg text-xs font-semibold flex items-center space-x-1.5 shadow-xs transition-colors"
                    >
                        <UserCheck class="w-3.5 h-3.5 text-emerald-600" />
                        <span>3. Add Staff (Dept & Designation)</span>
                    </button>
                </div>
            </div>

            <!-- Navigation Sub-Tabs -->
            <div class="flex items-center space-x-2 mt-5 border-t border-slate-100 pt-3 text-xs font-semibold text-slate-600">
                <button
                    @click="activeSection = 'overview'"
                    :class="['px-3 py-1.5 rounded-lg transition-colors flex items-center space-x-1.5', activeSection === 'overview' ? 'bg-indigo-50 text-indigo-700 font-bold' : 'hover:bg-slate-100']"
                >
                    <Layers class="w-3.5 h-3.5" />
                    <span>Setup Procedure & Overview</span>
                </button>
                <button
                    @click="activeSection = 'clients'"
                    :class="['px-3 py-1.5 rounded-lg transition-colors flex items-center space-x-1.5', activeSection === 'clients' ? 'bg-indigo-50 text-indigo-700 font-bold' : 'hover:bg-slate-100']"
                >
                    <Users class="w-3.5 h-3.5" />
                    <span>Clients & Projects ({{ clientList.length }})</span>
                </button>
                <button
                    @click="activeSection = 'projects'"
                    :class="['px-3 py-1.5 rounded-lg transition-colors flex items-center space-x-1.5', activeSection === 'projects' ? 'bg-indigo-50 text-indigo-700 font-bold' : 'hover:bg-slate-100']"
                >
                    <FolderKanban class="w-3.5 h-3.5" />
                    <span>All Projects ({{ projects.length }})</span>
                </button>
                <button
                    @click="activeSection = 'staff'"
                    :class="['px-3 py-1.5 rounded-lg transition-colors flex items-center space-x-1.5', activeSection === 'staff' ? 'bg-indigo-50 text-indigo-700 font-bold' : 'hover:bg-slate-100']"
                >
                    <UserCheck class="w-3.5 h-3.5" />
                    <span>Staff (Department & Designation) ({{ staffList.length }})</span>
                </button>
                <button
                    @click="activeSection = 'overtime_audit'"
                    :class="['px-3 py-1.5 rounded-lg transition-colors flex items-center space-x-1.5', activeSection === 'overtime_audit' ? 'bg-amber-50 text-amber-800 font-bold' : 'hover:bg-slate-100']"
                >
                    <Clock class="w-3.5 h-3.5 text-amber-600" />
                    <span>Overtime Time Ratio ({{ stats.overtimeTasksCount }})</span>
                </button>
            </div>
        </div>

        <!-- Main Body Content -->
        <div class="p-6 space-y-6">
            <!-- Metric Cards -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-3.5">
                <!-- Card 1: Clients -->
                <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-xs">
                    <div class="flex items-center justify-between text-slate-500 text-xs font-semibold">
                        <span>Clients</span>
                        <Users class="w-4 h-4 text-blue-600" />
                    </div>
                    <div class="mt-2 text-2xl font-bold text-slate-900 font-mono">{{ stats.totalClients }}</div>
                    <div class="text-[11px] text-slate-500 mt-1">Client Accounts</div>
                </div>

                <!-- Card 2: Projects -->
                <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-xs">
                    <div class="flex items-center justify-between text-slate-500 text-xs font-semibold">
                        <span>Projects</span>
                        <FolderKanban class="w-4 h-4 text-indigo-600" />
                    </div>
                    <div class="mt-2 text-2xl font-bold text-indigo-700 font-mono">{{ stats.totalProjects }}</div>
                    <div class="text-[11px] text-slate-500 mt-1 flex items-center space-x-1">
                        <span class="text-emerald-600 font-bold">{{ stats.activeProjects }} active</span>
                        <span>•</span>
                        <span>Across {{ stats.totalClients }} clients</span>
                    </div>
                </div>

                <!-- Card 3: Team Leads -->
                <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-xs">
                    <div class="flex items-center justify-between text-slate-500 text-xs font-semibold">
                        <span>Team Leads</span>
                        <Star class="w-4 h-4 text-indigo-600" />
                    </div>
                    <div class="mt-2 text-2xl font-bold text-slate-900 font-mono">{{ stats.totalTeamLeads }}</div>
                    <div class="text-[11px] text-slate-500 mt-1">Can assign tasks to staff</div>
                </div>

                <!-- Card 4: Staff Employees -->
                <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-xs">
                    <div class="flex items-center justify-between text-slate-500 text-xs font-semibold">
                        <span>Staff Employees</span>
                        <UserCheck class="w-4 h-4 text-emerald-600" />
                    </div>
                    <div class="mt-2 text-2xl font-bold text-slate-900 font-mono">{{ stats.totalStaff }}</div>
                    <div class="text-[11px] text-slate-500 mt-1">Across {{ Object.keys(stats.deptCounts).length }} depts</div>
                </div>

                <!-- Card 5: Overtime Tasks -->
                <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-xs">
                    <div class="flex items-center justify-between text-slate-500 text-xs font-semibold">
                        <span>Overtime Ratio</span>
                        <Clock class="w-4 h-4 text-amber-500" />
                    </div>
                    <div class="mt-2 text-2xl font-bold text-amber-600 font-mono">{{ stats.overtimeTasksCount }}</div>
                    <div class="text-[11px] text-slate-500 mt-1">Actual > Approx hours</div>
                </div>
            </div>

            <!-- ========================================================= -->
            <!-- SECTION 1: OVERVIEW & SYSTEM SETUP PROCEDURE              -->
            <!-- ========================================================= -->
            <div v-if="activeSection === 'overview'" class="space-y-6">
                <!-- Visual Setup Procedure Cards -->
                <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-xs">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h2 class="text-sm font-bold text-slate-900">Standard Setup Procedure</h2>
                            <p class="text-xs text-slate-500 mt-0.5">
                                Hierarchy: <strong>First setup Client</strong> &rarr; then <strong>set Projects according to Client</strong> (one client can have multiple projects) &rarr; configure <strong>Staff Departments & Designations & Team Leads</strong>.
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <!-- Step 1: Onboard Client First -->
                        <div class="p-4 rounded-xl border border-blue-100 bg-blue-50/40 relative">
                            <div class="w-6 h-6 rounded-full bg-blue-600 text-white font-bold text-xs flex items-center justify-center mb-2.5">
                                1
                            </div>
                            <h3 class="text-xs font-bold text-slate-900">1. Setup Client First</h3>
                            <p class="text-[11px] text-slate-600 mt-1 leading-relaxed">
                                Create the Client / Customer account first (e.g. Acme Corp, Nexus Health).
                            </p>
                            <button
                                @click="openCreateUserModal('client')"
                                class="mt-3 text-xs font-semibold text-blue-700 hover:text-blue-900 flex items-center space-x-1"
                            >
                                <span>+ Add Client</span>
                                <ArrowRight class="w-3 h-3" />
                            </button>
                        </div>

                        <!-- Step 2: Set Projects for Client -->
                        <div class="p-4 rounded-xl border border-indigo-100 bg-indigo-50/40 relative">
                            <div class="w-6 h-6 rounded-full bg-indigo-600 text-white font-bold text-xs flex items-center justify-center mb-2.5">
                                2
                            </div>
                            <h3 class="text-xs font-bold text-slate-900">2. Set Projects per Client</h3>
                            <p class="text-[11px] text-slate-600 mt-1 leading-relaxed">
                                Under each client, set 1 or multiple designated projects. One client can have multiple projects.
                            </p>
                            <button
                                @click="isCreateProjectModalOpen = true"
                                class="mt-3 text-xs font-semibold text-indigo-700 hover:text-indigo-900 flex items-center space-x-1"
                            >
                                <span>+ Add Project for Client</span>
                                <ArrowRight class="w-3 h-3" />
                            </button>
                        </div>

                        <!-- Step 3: Staff Department, Designation & Team Lead -->
                        <div class="p-4 rounded-xl border border-emerald-100 bg-emerald-50/40 relative">
                            <div class="w-6 h-6 rounded-full bg-emerald-600 text-white font-bold text-xs flex items-center justify-center mb-2.5">
                                3
                            </div>
                            <h3 class="text-xs font-bold text-slate-900">3. Department & Team Lead</h3>
                            <p class="text-[11px] text-slate-600 mt-1 leading-relaxed">
                                Assign staff to Departments & Designations. Authorize Team Leads to delegate work to employees.
                            </p>
                            <button
                                @click="activeSection = 'staff'"
                                class="mt-3 text-xs font-semibold text-emerald-700 hover:text-emerald-900 flex items-center space-x-1"
                            >
                                <span>Manage Staff</span>
                                <ArrowRight class="w-3 h-3" />
                            </button>
                        </div>

                        <!-- Step 4: Track Work Status -->
                        <div class="p-4 rounded-xl border border-amber-100 bg-amber-50/40 relative">
                            <div class="w-6 h-6 rounded-full bg-amber-600 text-white font-bold text-xs flex items-center justify-center mb-2.5">
                                4
                            </div>
                            <h3 class="text-xs font-bold text-slate-900">4. Track Work Status</h3>
                            <p class="text-[11px] text-slate-600 mt-1 leading-relaxed">
                                Staff log approx vs completed hours. System detects overtime ratios and requires completion remarks.
                            </p>
                            <button
                                @click="emit('switch-to-tab', 'work_status')"
                                class="mt-3 text-xs font-semibold text-amber-800 hover:text-amber-900 flex items-center space-x-1"
                            >
                                <span>Go to Work Status</span>
                                <ArrowRight class="w-3 h-3" />
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Departments Breakdown Card -->
                <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-xs">
                    <h3 class="text-xs font-bold text-slate-900 mb-3 flex items-center space-x-2">
                        <Building2 class="w-4 h-4 text-indigo-600" />
                        <span>Staff Department Overview</span>
                    </h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <div
                            v-for="(count, dept) in stats.deptCounts"
                            :key="dept"
                            class="p-3 rounded-xl border border-slate-200/80 bg-slate-50/60 flex items-center justify-between"
                        >
                            <div>
                                <span class="text-xs font-bold text-slate-800">{{ dept }}</span>
                                <div class="text-[10px] text-slate-500">Department</div>
                            </div>
                            <span class="px-2 py-0.5 rounded-full text-xs font-mono font-bold bg-white text-slate-700 border border-slate-200 shadow-2xs">
                                {{ count }} staff
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========================================================= -->
            <!-- SECTION 2: CLIENTS & THEIR MULTIPLE PROJECTS              -->
            <!-- ========================================================= -->
            <div v-else-if="activeSection === 'clients'" class="space-y-4">
                <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-xs flex flex-col md:flex-row items-center justify-between gap-3">
                    <div>
                        <h2 class="text-xs font-bold text-slate-900">Clients & Multiple Projects Setup</h2>
                        <p class="text-[11px] text-slate-500 mt-0.5">
                            First create the Client account, then add one or multiple projects associated with that client.
                        </p>
                    </div>
                    <div class="flex items-center space-x-2">
                        <div class="relative w-64">
                            <Search class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" />
                            <input
                                v-model="clientSearch"
                                type="text"
                                placeholder="Search clients or projects..."
                                class="w-full pl-9 pr-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
                            />
                        </div>
                        <button
                            @click="openCreateUserModal('client')"
                            class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-semibold flex items-center space-x-1 shadow-xs"
                        >
                            <UserPlus class="w-3.5 h-3.5" />
                            <span>Add Client</span>
                        </button>
                    </div>
                </div>

                <!-- Clients Table with Multiple Projects -->
                <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-xs">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50/80 border-b border-slate-200 text-slate-600 font-semibold">
                                <th class="py-3 px-4">Client</th>
                                <th class="py-3 px-4">Member ID</th>
                                <th class="py-3 px-4">Department / Designation</th>
                                <th class="py-3 px-4">Associated Projects (Multiple)</th>
                                <th class="py-3 px-4">Assigned Agent</th>
                                <th class="py-3 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="user in clientList" :key="user.id" class="hover:bg-slate-50/60 transition-colors">
                                <td class="py-3 px-4">
                                    <div class="flex items-center space-x-2.5">
                                        <img :src="user.avatar" class="w-7 h-7 rounded-full object-cover" />
                                        <div>
                                            <div class="font-bold text-slate-900">{{ user.name }}</div>
                                            <div class="text-[11px] text-slate-500">{{ user.email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-4 font-mono text-slate-600">{{ user.member_id || '—' }}</td>
                                <td class="py-3 px-4">
                                    <div class="font-medium text-slate-800">{{ user.designation || 'Client Contact' }}</div>
                                    <div class="text-[10px] text-slate-500">{{ user.department || 'Executive Office' }}</div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <!-- Badges for each project owned by this client -->
                                        <span
                                            v-for="p in getClientProjects(user)"
                                            :key="p.id"
                                            class="inline-flex items-center space-x-1 px-2 py-0.5 rounded-md text-[10px] font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200"
                                        >
                                            <FolderKanban class="w-3 h-3 text-indigo-500" />
                                            <span>{{ p.name }}</span>
                                        </span>

                                        <span v-if="getClientProjects(user).length === 0" class="text-amber-600 italic text-[11px] font-medium">
                                            No projects yet
                                        </span>

                                        <!-- Fast button to add project for this specific client -->
                                        <button
                                            @click="openAddProjectForClient(user)"
                                            class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-white hover:bg-slate-50 border border-dashed border-indigo-300 text-indigo-700 hover:bg-indigo-50 flex items-center space-x-1 transition-colors"
                                            title="Add new project for this client"
                                        >
                                            <Plus class="w-2.5 h-2.5" />
                                            <span>Add Project</span>
                                        </button>
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-slate-700">
                                    {{ user.assigned_agent?.name || 'Unassigned' }}
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <button
                                        @click="deleteUser(user)"
                                        class="p-1 hover:bg-rose-50 text-slate-400 hover:text-rose-600 rounded transition-colors"
                                        title="Delete Client"
                                    >
                                        <Trash2 class="w-3.5 h-3.5" />
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="clientList.length === 0">
                                <td colspan="6" class="py-8 text-center text-slate-400 italic">
                                    No client accounts found. Click "+ Add Client" above to create one.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ========================================================= -->
            <!-- SECTION 3: MANAGE ALL PROJECTS                            -->
            <!-- ========================================================= -->
            <div v-else-if="activeSection === 'projects'" class="space-y-4">
                <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-xs flex flex-col md:flex-row items-center justify-between gap-3">
                    <div class="relative w-full md:w-80">
                        <Search class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" />
                        <input
                            v-model="projectSearch"
                            type="text"
                            placeholder="Search projects or clients..."
                            class="w-full pl-9 pr-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
                        />
                    </div>
                    <button
                        @click="isCreateProjectModalOpen = true"
                        class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-semibold flex items-center space-x-1 shadow-xs"
                    >
                        <Plus class="w-3.5 h-3.5" />
                        <span>Create Project</span>
                    </button>
                </div>

                <!-- Projects Table -->
                <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-xs">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50/80 border-b border-slate-200 text-slate-600 font-semibold">
                                <th class="py-3 px-4">Project Name</th>
                                <th class="py-3 px-4">Owner Client</th>
                                <th class="py-3 px-4">Status</th>
                                <th class="py-3 px-4 text-center">Tasks</th>
                                <th class="py-3 px-4 text-center">Tickets</th>
                                <th class="py-3 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="proj in filteredProjects" :key="proj.id" class="hover:bg-slate-50/60 transition-colors">
                                <td class="py-3 px-4">
                                    <div class="font-bold text-slate-900">{{ proj.name }}</div>
                                    <div class="text-[11px] text-slate-500 line-clamp-1">{{ proj.description || 'No description provided' }}</div>
                                </td>
                                <td class="py-3 px-4">
                                    <div v-if="proj.client" class="flex items-center space-x-1.5">
                                        <Users class="w-3.5 h-3.5 text-indigo-600" />
                                        <div>
                                            <span class="font-semibold text-slate-800">{{ proj.client.name }}</span>
                                            <span class="text-[10px] text-slate-500 ml-1 font-mono">({{ proj.client.member_id || proj.client.email }})</span>
                                        </div>
                                    </div>
                                    <span v-else class="text-slate-400 italic">Unassigned</span>
                                </td>
                                <td class="py-3 px-4">
                                    <select
                                        :value="proj.status"
                                        @change="updateProjectStatus(proj, $event.target.value)"
                                        class="text-[11px] font-semibold rounded-md border border-slate-200 px-2 py-0.5 bg-white text-slate-700"
                                    >
                                        <option value="active">Active</option>
                                        <option value="on_hold">On Hold</option>
                                        <option value="completed">Completed</option>
                                    </select>
                                </td>
                                <td class="py-3 px-4 text-center font-mono font-bold text-slate-700">
                                    {{ proj.tasks_count || 0 }}
                                </td>
                                <td class="py-3 px-4 text-center font-mono font-bold text-slate-700">
                                    {{ proj.tickets_count || 0 }}
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <button
                                        @click="deleteProject(proj)"
                                        class="p-1 hover:bg-rose-50 text-slate-400 hover:text-rose-600 rounded transition-colors"
                                        title="Delete Project"
                                    >
                                        <Trash2 class="w-3.5 h-3.5" />
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="filteredProjects.length === 0">
                                <td colspan="6" class="py-8 text-center text-slate-400 italic">
                                    No projects found matching the criteria.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ========================================================= -->
            <!-- SECTION 4: EMPLOYEES & TEAM LEADS (DEPT & DESIGNATION)    -->
            <!-- ========================================================= -->
            <div v-else-if="activeSection === 'staff'" class="space-y-4">
                <div class="bg-white border border-slate-200 rounded-xl p-4 shadow-xs flex flex-col md:flex-row items-center justify-between gap-3">
                    <div>
                        <h2 class="text-xs font-bold text-slate-900">Staff Hierarchy: Department & Designation & Team Lead</h2>
                        <p class="text-[11px] text-slate-500 mt-0.5">
                            Employees belong to specific departments and hold professional designations. Team Leads can assign tasks to employees.
                        </p>
                    </div>
                    <div class="flex items-center space-x-2">
                        <!-- Department Filter Dropdown -->
                        <div class="flex items-center space-x-1">
                            <Filter class="w-3.5 h-3.5 text-slate-400" />
                            <select
                                v-model="selectedStaffDepartment"
                                class="text-xs font-semibold px-2 py-1.5 bg-slate-50 border border-slate-200 rounded-lg text-slate-700"
                            >
                                <option value="all">All Departments</option>
                                <option v-for="d in departments" :key="d" :value="d">{{ d }}</option>
                            </select>
                        </div>

                        <div class="relative w-56">
                            <Search class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" />
                            <input
                                v-model="staffSearch"
                                type="text"
                                placeholder="Search employees..."
                                class="w-full pl-9 pr-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-lg focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
                            />
                        </div>

                        <button
                            @click="openCreateUserModal('staff')"
                            class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-xs font-semibold flex items-center space-x-1 shadow-xs"
                        >
                            <Plus class="w-3.5 h-3.5" />
                            <span>Add Staff</span>
                        </button>
                    </div>
                </div>

                <!-- Staff Table with Department & Designation -->
                <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-xs">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50/80 border-b border-slate-200 text-slate-600 font-semibold">
                                <th class="py-3 px-4">Employee</th>
                                <th class="py-3 px-4">Member ID</th>
                                <th class="py-3 px-4">Department & Designation</th>
                                <th class="py-3 px-4 text-center">Team Lead Permission</th>
                                <th class="py-3 px-4 text-center">Assigned Tasks</th>
                                <th class="py-3 px-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="user in staffList" :key="user.id" class="hover:bg-slate-50/60 transition-colors">
                                <td class="py-3 px-4">
                                    <div class="flex items-center space-x-2.5">
                                        <img :src="user.avatar" class="w-7 h-7 rounded-full object-cover" />
                                        <div>
                                            <div class="font-bold text-slate-900 flex items-center space-x-1.5">
                                                <span>{{ user.name }}</span>
                                                <span v-if="user.is_admin" class="px-1.5 py-0.2 rounded-full bg-indigo-100 text-indigo-700 text-[9px] font-bold">Admin</span>
                                            </div>
                                            <div class="text-[11px] text-slate-500">{{ user.email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3 px-4 font-mono text-slate-600">{{ user.member_id || '—' }}</td>
                                <td class="py-3 px-4">
                                    <div class="font-bold text-slate-800">{{ user.designation || user.role || 'Staff Member' }}</div>
                                    <div class="mt-0.5">
                                        <span :class="['px-2 py-0.5 rounded-full text-[10px] font-semibold border', getDeptColor(user.department)]">
                                            {{ user.department || 'Engineering' }}
                                        </span>
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <button
                                        @click="toggleTeamLead(user)"
                                        :class="[
                                            'px-3 py-1 rounded-full text-[11px] font-semibold transition-all flex items-center space-x-1.5 mx-auto',
                                            user.is_team_lead 
                                                ? 'bg-amber-100 text-amber-900 border border-amber-300 font-bold shadow-xs' 
                                                : 'bg-slate-100 text-slate-600 hover:bg-slate-200'
                                        ]"
                                    >
                                        <Star class="w-3 h-3" :class="user.is_team_lead ? 'text-amber-600 fill-amber-500' : 'text-slate-400'" />
                                        <span>{{ user.is_team_lead ? 'Team Lead (Can Assign)' : 'Employee (Own Tasks)' }}</span>
                                    </button>
                                </td>
                                <td class="py-3 px-4 text-center font-mono font-bold text-slate-700">
                                    {{ user.assigned_tasks?.length || 0 }}
                                </td>
                                <td class="py-3 px-4 text-right">
                                    <button
                                        v-if="!user.is_admin"
                                        @click="deleteUser(user)"
                                        class="p-1 hover:bg-rose-50 text-slate-400 hover:text-rose-600 rounded transition-colors"
                                        title="Delete Employee"
                                    >
                                        <Trash2 class="w-3.5 h-3.5" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- ========================================================= -->
            <!-- SECTION 5: OVERTIME WORK RATIO AUDIT                      -->
            <!-- ========================================================= -->
            <div v-else-if="activeSection === 'overtime_audit'" class="space-y-4">
                <div class="bg-amber-50/60 border border-amber-200 rounded-xl p-4 flex items-start space-x-3">
                    <AlertTriangle class="w-5 h-5 text-amber-600 shrink-0 mt-0.5" />
                    <div>
                        <h2 class="text-xs font-bold text-amber-900">Overtime Time Ratio Warning System</h2>
                        <p class="text-[11px] text-amber-800 mt-0.5">
                            Tasks listed below required more actual hours than originally estimated (Ratio > 1.0x). Review staff completion remarks to understand scope adjustments.
                        </p>
                    </div>
                </div>

                <div class="bg-white border border-slate-200 rounded-xl overflow-hidden shadow-xs">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-slate-50/80 border-b border-slate-200 text-slate-600 font-semibold">
                                <th class="py-3 px-4">Task & Project</th>
                                <th class="py-3 px-4">Assignees</th>
                                <th class="py-3 px-4 text-center">Estimated</th>
                                <th class="py-3 px-4 text-center">Actual Spent</th>
                                <th class="py-3 px-4 text-center">Time Ratio</th>
                                <th class="py-3 px-4">Completion Remarks</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="t in overtimeTasksList" :key="t.id" class="hover:bg-slate-50/60 transition-colors">
                                <td class="py-3 px-4">
                                    <div class="font-bold text-slate-900">{{ t.title }}</div>
                                    <div class="text-[11px] text-indigo-600 font-semibold">{{ t.project?.name || 'General' }}</div>
                                </td>
                                <td class="py-3 px-4">
                                    <div class="flex items-center -space-x-1">
                                        <img
                                            v-for="a in t.assignees"
                                            :key="a.id"
                                            :src="a.avatar"
                                            :title="a.name"
                                            class="w-6 h-6 rounded-full border border-white object-cover"
                                        />
                                        <span v-if="!t.assignees?.length" class="text-slate-400 italic">None</span>
                                    </div>
                                </td>
                                <td class="py-3 px-4 text-center font-mono text-slate-700">
                                    {{ t.approximate_hours }}h
                                </td>
                                <td class="py-3 px-4 text-center font-mono font-bold text-rose-600">
                                    {{ t.completed_hours }}h
                                </td>
                                <td class="py-3 px-4 text-center">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold font-mono bg-rose-100 text-rose-800 border border-rose-200">
                                        {{ t.time_ratio }}x Overtime
                                    </span>
                                </td>
                                <td class="py-3 px-4 text-slate-600 italic">
                                    {{ t.completion_remarks || 'No remarks provided' }}
                                </td>
                            </tr>
                            <tr v-if="overtimeTasksList.length === 0">
                                <td colspan="6" class="py-8 text-center text-slate-400 italic">
                                    No tasks exceeded approximate estimation. Great pacing!
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- CREATE PROJECT MODAL (Client First, Then Project)         -->
        <!-- ========================================================= -->
        <div v-if="isCreateProjectModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-md w-full p-5 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div class="flex items-center space-x-2">
                        <FolderKanban class="w-4 h-4 text-indigo-600" />
                        <h3 class="text-sm font-bold text-slate-900">Create Project for Client</h3>
                    </div>
                    <button @click="isCreateProjectModalOpen = false" class="p-1 hover:bg-slate-100 rounded text-slate-400 hover:text-slate-600">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form @submit.prevent="submitProject" class="space-y-3.5 text-xs">
                    <!-- Client Selection is Primary & Required -->
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Target Client *</label>
                        <select
                            v-model="projectForm.client_id"
                            required
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 font-medium"
                        >
                            <option value="">Select a Client...</option>
                            <option v-for="c in clientList" :key="c.id" :value="c.id">
                                {{ c.name }} ({{ c.member_id || c.email }})
                            </option>
                        </select>
                        <p class="text-[10px] text-slate-500 mt-1">One client can have multiple projects.</p>
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Project Name *</label>
                        <input
                            v-model="projectForm.name"
                            type="text"
                            required
                            placeholder="e.g. Project 1B — Mobile App System"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
                        />
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Status</label>
                        <select
                            v-model="projectForm.status"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
                        >
                            <option value="active">Active</option>
                            <option value="on_hold">On Hold</option>
                            <option value="completed">Completed</option>
                        </select>
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Description / Project Scope</label>
                        <textarea
                            v-model="projectForm.description"
                            rows="3"
                            placeholder="Brief project deliverables, objectives, or SLA notes..."
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
                        ></textarea>
                    </div>

                    <div class="flex items-center justify-end space-x-2 pt-3 border-t border-slate-100">
                        <button
                            type="button"
                            @click="isCreateProjectModalOpen = false"
                            class="px-3 py-1.5 text-slate-600 hover:bg-slate-100 rounded-lg font-semibold"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="px-4 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-semibold shadow-xs"
                        >
                            Save Project
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- CREATE USER MODAL (Client or Staff with Dept & Desig)     -->
        <!-- ========================================================= -->
        <div v-if="isCreateUserModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs">
            <div class="bg-white rounded-2xl border border-slate-200 shadow-xl max-w-md w-full p-5 space-y-4">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                    <div class="flex items-center space-x-2">
                        <UserPlus class="w-4 h-4 text-indigo-600" />
                        <h3 class="text-sm font-bold text-slate-900">
                            {{ createUserRoleType === 'client' ? 'Onboard New Client Account' : 'Add Staff Member' }}
                        </h3>
                    </div>
                    <button @click="isCreateUserModalOpen = false" class="p-1 hover:bg-slate-100 rounded text-slate-400 hover:text-slate-600">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form @submit.prevent="submitUser" class="space-y-3.5 text-xs">
                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Full Name / Contact Name *</label>
                        <input
                            v-model="userForm.name"
                            type="text"
                            required
                            placeholder="e.g. John Doe"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
                        />
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Email Address *</label>
                        <input
                            v-model="userForm.email"
                            type="email"
                            required
                            placeholder="john@example.com"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
                        />
                    </div>

                    <div>
                        <label class="block font-semibold text-slate-700 mb-1">Password</label>
                        <input
                            v-model="userForm.password"
                            type="text"
                            placeholder="password123"
                            class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 font-mono"
                        />
                    </div>

                    <!-- Staff specific: Department, Designation & Team Lead -->
                    <div v-if="createUserRoleType === 'staff'" class="space-y-3 pt-1 border-t border-slate-100">
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Department *</label>
                            <select
                                v-model="userForm.department"
                                required
                                class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 font-medium"
                            >
                                <option v-for="d in departments" :key="d" :value="d">{{ d }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Designation / Job Title *</label>
                            <input
                                v-model="userForm.designation"
                                type="text"
                                required
                                placeholder="e.g. Senior Backend Engineer"
                                class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500"
                            />
                        </div>

                        <div class="p-3 bg-amber-50/60 border border-amber-200 rounded-xl">
                            <label class="flex items-center space-x-2.5 cursor-pointer">
                                <input
                                    v-model="userForm.is_team_lead"
                                    type="checkbox"
                                    class="w-4 h-4 text-amber-600 rounded border-slate-300 focus:ring-amber-500"
                                />
                                <div>
                                    <span class="text-xs font-bold text-amber-900">Authorize as Team Lead?</span>
                                    <p class="text-[10px] text-amber-700">Team Leads can assign tasks to multiple employees. Normal employees can only create tasks for themselves.</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Client specific: Assigned Staff Person & Notes -->
                    <div v-else class="space-y-3">
                        <div>
                            <label class="block font-semibold text-slate-700 mb-1">Assign Support Person / Staff</label>
                            <select
                                v-model="userForm.assigned_agent_id"
                                class="w-full px-3 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-hidden focus:ring-2 focus:ring-indigo-500/20 focus:border-indigo-500 font-medium"
                            >
                                <option value="">-- None (Defaults to Admin) --</option>
                                <option v-for="agent in staffList" :key="agent.id" :value="agent.id">
                                    {{ agent.name }} ({{ agent.designation || agent.role || 'Staff' }})
                                </option>
                            </select>
                            <p class="text-[10px] text-slate-500 mt-1">This staff member will appear in the client's direct contact list in Realtime Chat.</p>
                        </div>

                        <div class="p-3 bg-blue-50/60 border border-blue-200 rounded-xl">
                            <div class="text-[11px] text-blue-900 font-semibold flex items-center space-x-1.5">
                                <Building2 class="w-3.5 h-3.5 text-blue-600" />
                                <span>Client Account Setup</span>
                            </div>
                            <p class="text-[10px] text-blue-700 mt-1">
                                The Client is created first. After creating, you can set up one or multiple projects specifically for this client.
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center justify-end space-x-2 pt-3 border-t border-slate-100">
                        <button
                            type="button"
                            @click="isCreateUserModalOpen = false"
                            class="px-3 py-1.5 text-slate-600 hover:bg-slate-100 rounded-lg font-semibold"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            class="px-4 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-semibold shadow-xs"
                        >
                            Create Account
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
