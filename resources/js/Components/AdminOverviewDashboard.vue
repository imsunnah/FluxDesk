<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import SearchableSelect from './SearchableSelect.vue';
import {
    Building2,
    Briefcase,
    Users,
    UserCheck,
    FolderKanban,
    ShieldCheck,
    UserPlus,
    UserCog,
    Plus,
    Search,
    Trash2,
    Edit3,
    CheckCircle2,
    AlertTriangle,
    AlertCircle,
    Clock,
    Calendar,
    Download,
    Eye,
    X,
    Filter,
    ChevronRight,
    Phone,
    Mail,
    ArrowRight,
    Shield,
    Award,
    Sparkles,
    Star,
    Layers,
    Check,
    RefreshCw,
    AlertOctagon,
    Hash
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
        default: () => [],
    },
    designations: {
        type: Array,
        default: () => [],
    },
    clients: {
        type: Array,
        default: () => [],
    },
    clientAssignments: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['switch-to-tab']);

// =========================================================
// 1. NAVIGATION & 6 DEDICATED SETUP TABS
// =========================================================
const activeTab = ref('departments'); // 'departments' | 'designations' | 'employees' | 'clients' | 'account_managers' | 'projects'
const globalSearch = ref('');

const tabs = [
    { id: 'departments', label: 'Departments', icon: Building2 },
    { id: 'designations', label: 'Designations', icon: Briefcase },
    { id: 'employees', label: 'Employees', icon: Users },
    { id: 'clients', label: 'Clients', icon: Building2 },
    { id: 'account_managers', label: 'Client Assignments', icon: UserCog },
    { id: 'projects', label: 'Projects', icon: FolderKanban },
];

// Toast Notification System
const toasts = ref([]);
function showToast(message, type = 'success') {
    const id = Date.now() + Math.random();
    toasts.value.push({ id, message, type });
    setTimeout(() => {
        toasts.value = toasts.value.filter(t => t.id !== id);
    }, 3500);
}

// Safe Delete Prevention Modal State
const safeDeleteModal = ref({
    isOpen: false,
    title: '',
    message: '',
    canDelete: false,
    dependencies: [],
    onConfirm: null,
});

function openSafeDeleteModal({ title, message, canDelete, dependencies = [], onConfirm }) {
    safeDeleteModal.value = {
        isOpen: true,
        title,
        message,
        canDelete,
        dependencies,
        onConfirm,
    };
}

// =========================================================
// TAB 1: DEPARTMENT SETUP STATE & LOGIC
// =========================================================
const departmentsList = ref([]);
watch(() => props.departments, (newVal) => {
    if (Array.isArray(newVal)) {
        departmentsList.value = newVal.map(d => {
            if (typeof d === 'string') {
                return { id: d, code: d.slice(0, 3).toUpperCase(), name: d, hodId: null, description: '', status: 'active' };
            }
            return {
                id: d.id,
                code: d.code || '',
                name: d.name || '',
                hodId: d.hod_id || d.hodId || null,
                description: d.description || '',
                status: d.status || 'active',
            };
        });
    } else {
        departmentsList.value = [];
    }
}, { immediate: true });

const departmentSearch = ref('');
const isDepartmentModalOpen = ref(false);
const editingDepartment = ref(null);
const departmentForm = ref({
    code: '',
    name: '',
    hodId: '',
    description: '',
    status: 'active',
});

// Staff count computed per department
function getDepartmentStaffCount(deptName) {
    return props.agents.filter(u => !u.is_customer && (u.department || '').toLowerCase() === (deptName || '').toLowerCase()).length;
}

function getHodUser(hodId) {
    if (!hodId) return null;
    return props.agents.find(u => u.id === hodId && !u.is_customer) || null;
}

const filteredDepartments = computed(() => {
    let list = departmentsList.value;
    const q = (departmentSearch.value || globalSearch.value).toLowerCase().trim();
    if (q) {
        list = list.filter(d => 
            d.name.toLowerCase().includes(q) ||
            d.code.toLowerCase().includes(q) ||
            (d.description || '').toLowerCase().includes(q)
        );
    }
    return list;
});

function openAddDepartment() {
    editingDepartment.value = null;
    departmentForm.value = {
        code: '',
        name: '',
        hodId: '',
        description: '',
        status: 'active',
    };
    isDepartmentModalOpen.value = true;
}

function openEditDepartment(dept) {
    editingDepartment.value = dept;
    departmentForm.value = {
        code: dept.code,
        name: dept.name,
        hodId: dept.hodId || '',
        description: dept.description || '',
        status: dept.status || 'active',
    };
    isDepartmentModalOpen.value = true;
}

function saveDepartment() {
    if (!departmentForm.value.code || !departmentForm.value.name) return;
    const payload = {
        code: departmentForm.value.code.toUpperCase(),
        name: departmentForm.value.name,
        hod_id: departmentForm.value.hodId ? Number(departmentForm.value.hodId) : null,
        description: departmentForm.value.description,
        status: departmentForm.value.status,
    };

    if (editingDepartment.value && typeof editingDepartment.value.id === 'number') {
        router.patch(`/admin/departments/${editingDepartment.value.id}`, payload, {
            preserveScroll: true,
            onSuccess: () => {
                isDepartmentModalOpen.value = false;
                showToast(`Department "${payload.name}" updated successfully.`);
            },
            onError: (errs) => {
                showToast(Object.values(errs)[0] || 'Error updating department.', 'error');
            }
        });
    } else {
        router.post('/admin/departments', payload, {
            preserveScroll: true,
            onSuccess: () => {
                isDepartmentModalOpen.value = false;
                showToast(`Department "${payload.name}" created successfully.`);
            },
            onError: (errs) => {
                showToast(Object.values(errs)[0] || 'Error creating department.', 'error');
            }
        });
    }
}

function attemptDeleteDepartment(dept) {
    const staffCount = getDepartmentStaffCount(dept.name);
    if (staffCount > 0) {
        openSafeDeleteModal({
            title: 'Cannot Delete Department',
            message: `Department "${dept.name}" cannot be deleted because it is currently assigned to ${staffCount} active staff member(s).`,
            canDelete: false,
            dependencies: [`${staffCount} Staff Employee(s) currently assigned to this department.`],
        });
        return;
    }

    openSafeDeleteModal({
        title: 'Delete Department Confirmation',
        message: `Are you sure you want to permanently delete department "${dept.name}" (${dept.code})?`,
        canDelete: true,
        dependencies: [],
        onConfirm: () => {
            if (typeof dept.id === 'number') {
                router.delete(`/admin/departments/${dept.id}`, {
                    preserveScroll: true,
                    onSuccess: () => {
                        showToast(`Department "${dept.name}" deleted.`);
                    },
                    onError: (errs) => {
                        showToast(Object.values(errs)[0] || 'Error deleting department.', 'error');
                    }
                });
            } else {
                departmentsList.value = departmentsList.value.filter(d => d.id !== dept.id);
                showToast(`Department "${dept.name}" deleted.`);
            }
        }
    });
}

// =========================================================
// TAB 2: DESIGNATION SETUP STATE & LOGIC
// =========================================================
const seniorityBands = ['Entry', 'Mid', 'Senior', 'Lead', 'Executive'];

const designationsList = ref([]);
watch(() => props.designations, (newVal) => {
    if (Array.isArray(newVal)) {
        designationsList.value = newVal.map(d => ({
            id: d.id,
            title: d.title || '',
            department: d.department?.name || d.department_name || '',
            departmentId: d.department_id,
            band: d.band || 'Mid',
            responsibilities: d.responsibilities || '',
        }));
    } else {
        designationsList.value = [];
    }
}, { immediate: true });

const designationSearch = ref('');
const isDesignationModalOpen = ref(false);
const editingDesignation = ref(null);
const designationForm = ref({
    title: '',
    department: '',
    band: 'Mid',
    responsibilities: '',
});

const filteredDesignations = computed(() => {
    let list = designationsList.value;
    const q = (designationSearch.value || globalSearch.value).toLowerCase().trim();
    if (q) {
        list = list.filter(d => 
            d.title.toLowerCase().includes(q) ||
            d.department.toLowerCase().includes(q) ||
            d.band.toLowerCase().includes(q) ||
            (d.responsibilities || '').toLowerCase().includes(q)
        );
    }
    return list;
});

function openAddDesignation(deptName = '') {
    editingDesignation.value = null;
    designationForm.value = {
        title: '',
        department: deptName || departmentsList.value[0]?.name || '',
        band: 'Mid',
        responsibilities: '',
    };
    isDesignationModalOpen.value = true;
}

function openEditDesignation(desig) {
    editingDesignation.value = desig;
    designationForm.value = {
        title: desig.title,
        department: desig.department,
        band: desig.band,
        responsibilities: desig.responsibilities || '',
    };
    isDesignationModalOpen.value = true;
}

function saveDesignation() {
    if (!designationForm.value.title || !designationForm.value.department) return;
    const deptObj = departmentsList.value.find(d => d.name === designationForm.value.department);
    const payload = {
        title: designationForm.value.title,
        department_name: designationForm.value.department,
        department_id: deptObj?.id || null,
        band: designationForm.value.band,
        responsibilities: designationForm.value.responsibilities,
    };

    if (editingDesignation.value && typeof editingDesignation.value.id === 'number') {
        router.patch(`/admin/designations/${editingDesignation.value.id}`, payload, {
            preserveScroll: true,
            onSuccess: () => {
                isDesignationModalOpen.value = false;
                showToast(`Designation "${payload.title}" updated.`);
            },
            onError: (errs) => {
                showToast(Object.values(errs)[0] || 'Error updating designation.', 'error');
            }
        });
    } else {
        router.post('/admin/designations', payload, {
            preserveScroll: true,
            onSuccess: () => {
                isDesignationModalOpen.value = false;
                showToast(`Designation "${payload.title}" created.`);
            },
            onError: (errs) => {
                showToast(Object.values(errs)[0] || 'Error creating designation.', 'error');
            }
        });
    }
}

function attemptDeleteDesignation(desig) {
    const linkedEmployees = props.agents.filter(u => !u.is_customer && (u.designation || '').toLowerCase() === desig.title.toLowerCase());
    if (linkedEmployees.length > 0) {
        openSafeDeleteModal({
            title: 'Cannot Delete Designation',
            message: `Designation "${desig.title}" cannot be deleted because ${linkedEmployees.length} employee(s) hold this title.`,
            canDelete: false,
            dependencies: linkedEmployees.map(e => `${e.name} (${e.email})`),
        });
        return;
    }

    openSafeDeleteModal({
        title: 'Delete Designation Confirmation',
        message: `Delete designation "${desig.title}" in ${desig.department}?`,
        canDelete: true,
        dependencies: [],
        onConfirm: () => {
            if (typeof desig.id === 'number') {
                router.delete(`/admin/designations/${desig.id}`, {
                    preserveScroll: true,
                    onSuccess: () => {
                        showToast(`Designation "${desig.title}" removed.`);
                    },
                    onError: (errs) => {
                        showToast(Object.values(errs)[0] || 'Error deleting designation.', 'error');
                    }
                });
            } else {
                designationsList.value = designationsList.value.filter(d => d.id !== desig.id);
                showToast(`Designation "${desig.title}" removed.`);
            }
        }
    });
}

function getBandColor(band) {
    switch (band) {
        case 'Executive': return 'bg-purple-100 text-purple-800 border-purple-200';
        case 'Lead': return 'bg-indigo-100 text-indigo-800 border-indigo-200';
        case 'Senior': return 'bg-blue-100 text-blue-800 border-blue-200';
        case 'Mid': return 'bg-emerald-100 text-emerald-800 border-emerald-200';
        case 'Entry': return 'bg-slate-100 text-slate-700 border-slate-200';
        default: return 'bg-slate-100 text-slate-600 border-slate-200';
    }
}

// =========================================================
// TAB 3: EMPLOYEE SETUP STATE & LOGIC
// =========================================================
const employeeSearch = ref('');
const isEmployeeModalOpen = ref(false);
const editingEmployee = ref(null);
const employeeForm = ref({
    firstName: '',
    lastName: '',
    email: '',
    phone: '',
    department: 'Engineering',
    designation: '',
    reportingManagerId: '',
    role: 'Employee', // 'Admin' | 'Team Lead' | 'Employee'
    status: 'active',
});

// Dynamic designations filtered by selected department
const availableDesignationsForSelectedDept = computed(() => {
    return designationsList.value.filter(d => d.department === employeeForm.value.department);
});

// Watch department to auto-select first available designation
watch(() => employeeForm.value.department, (newDept) => {
    const matching = designationsList.value.filter(d => d.department === newDept);
    if (matching.length > 0 && !matching.some(m => m.title === employeeForm.value.designation)) {
        employeeForm.value.designation = matching[0].title;
    }
});

const staffEmployees = computed(() => {
    return props.agents.filter(u => !u.is_customer);
});

const filteredEmployees = computed(() => {
    let list = staffEmployees.value;
    const q = (employeeSearch.value || globalSearch.value).toLowerCase().trim();
    if (q) {
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

function openAddEmployee() {
    editingEmployee.value = null;
    const defaultDept = departmentsList.value[0]?.name || '';
    const firstMatchingDesig = designationsList.value.find(d => d.department === defaultDept)?.title || designationsList.value[0]?.title || '';

    employeeForm.value = {
        firstName: '',
        lastName: '',
        email: '',
        phone: '',
        department: defaultDept,
        designation: firstMatchingDesig,
        reportingManagerId: '',
        role: 'Employee',
        status: 'active',
    };
    isEmployeeModalOpen.value = true;
}

function openEditEmployee(user) {
    editingEmployee.value = user;
    const parts = (user.name || '').split(' ');
    employeeForm.value = {
        firstName: parts[0] || '',
        lastName: parts.slice(1).join(' ') || '',
        email: user.email || '',
        phone: user.phone || '',
        department: user.department || '',
        designation: user.designation || user.role || '',
        reportingManagerId: '',
        role: user.is_admin ? 'Admin' : (user.is_team_lead ? 'Team Lead' : 'Employee'),
        status: 'active',
    };
    isEmployeeModalOpen.value = true;
}

function saveEmployee() {
    if (!employeeForm.value.firstName || !employeeForm.value.email) return;
    const fullName = `${employeeForm.value.firstName} ${employeeForm.value.lastName}`.trim();
    const isTeamLead = employeeForm.value.role === 'Team Lead';
    const isAdmin = employeeForm.value.role === 'Admin';

    if (editingEmployee.value) {
        router.patch(`/members/${editingEmployee.value.id}`, {
            name: fullName,
            email: employeeForm.value.email,
            department: employeeForm.value.department,
            designation: employeeForm.value.designation,
            role: employeeForm.value.role,
            is_team_lead: isTeamLead,
            is_admin: isAdmin,
            is_customer: false,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                isEmployeeModalOpen.value = false;
                showToast(`Employee "${fullName}" updated successfully.`);
            }
        });
    } else {
        router.post('/members', {
            name: fullName,
            email: employeeForm.value.email,
            password: 'password123',
            department: employeeForm.value.department,
            designation: employeeForm.value.designation,
            role: employeeForm.value.role,
            is_team_lead: isTeamLead,
            is_admin: isAdmin,
            is_customer: false,
        }, {
            preserveScroll: true,
            onSuccess: () => {
                isEmployeeModalOpen.value = false;
                showToast(`Employee "${fullName}" saved successfully.`);
            }
        });
    }
}

function toggleTeamLead(user) {
    router.post(`/users/${user.id}/toggle-team-lead`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            showToast(`Team Lead status toggled for ${user.name}.`);
        }
    });
}

function attemptDeleteEmployee(user) {
    // Check safe delete dependencies
    const deps = [];

    // Check if assigned as HOD
    const isHod = departmentsList.value.find(d => d.hodId === user.id);
    if (isHod) deps.push(`Head of Department (HOD) for "${isHod.name}".`);

    // Check if primary account manager
    const isAccountManager = accountManagersList.value.find(m => m.primaryManagerId === user.id);
    if (isAccountManager) deps.push(`Primary Client Account Manager for "${isAccountManager.clientOrg}".`);

    // Check if assigned as lead on active project
    const isProjectLead = props.projects.find(p => p.client_id === user.id);
    if (isProjectLead) deps.push(`Lead on Project "${isProjectLead.name}".`);

    if (deps.length > 0) {
        openSafeDeleteModal({
            title: 'Active Dependency Conflict',
            message: `Employee "${user.name}" cannot be deleted because of active operational mappings. Reassign these roles first:`,
            canDelete: false,
            dependencies: deps,
        });
        return;
    }

    openSafeDeleteModal({
        title: 'Delete Employee Confirmation',
        message: `Are you sure you want to delete employee "${user.name}" (${user.email})? This action cannot be undone.`,
        canDelete: true,
        dependencies: [],
        onConfirm: () => {
            router.delete(`/members/${user.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    showToast(`Employee "${user.name}" deleted.`);
                }
            });
        }
    });
}

// =========================================================
// TAB 4: CLIENT SETUP STATE & LOGIC
// =========================================================
const initialClientsMetadata = {
    industries: ['Fintech & Banking', 'Healthcare & Biotech', 'E-Commerce & Retail', 'Enterprise SaaS', 'Logistics & Supply Chain', 'EdTech'],
};

const clientSearch = ref('');
const isClientModalOpen = ref(false);
const editingClient = ref(null);
const clientForm = ref({
    orgName: '',
    regNumber: '',
    industry: 'Enterprise SaaS',
    billingAddress: '',
    pocName: '',
    pocEmail: '',
    pocPhone: '',
    status: 'active',
});

const clientUsers = computed(() => props.agents.filter(u => u.is_customer));

const clientsList = ref([]);
watch(() => props.clients, (newVal) => {
    if (Array.isArray(newVal) && newVal.length > 0) {
        clientsList.value = newVal.map(c => ({
            id: c.id,
            userId: c.user_id,
            name: c.org_name,
            orgName: c.org_name,
            regNumber: c.reg_number || ('REG-' + c.id),
            industry: c.industry || 'Enterprise SaaS',
            billingAddress: c.billing_address || '',
            pocName: c.poc_name || c.org_name,
            pocEmail: c.poc_email || c.user?.email || '',
            pocPhone: c.poc_phone || '',
            status: c.status || 'active',
            avatar: c.user?.avatar || 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=50&h=50&fit=crop',
            member_id: c.user?.member_id || `CUST-${c.id}`,
            projectsCount: c.projects_count ?? (c.projects?.length || 0),
        }));
    } else {
        clientsList.value = clientUsers.value.map(u => ({
            id: u.id,
            userId: u.id,
            name: u.name,
            orgName: u.name,
            regNumber: 'REG-' + u.id,
            industry: 'Enterprise SaaS',
            billingAddress: '',
            pocName: u.name,
            pocEmail: u.email,
            pocPhone: '',
            status: 'active',
            avatar: u.avatar,
            member_id: u.member_id || `CUST-${u.id}`,
            projectsCount: getClientActiveProjectsCount(u.id),
        }));
    }
}, { immediate: true });

const filteredClients = computed(() => {
    let list = clientsList.value;
    const q = (clientSearch.value || globalSearch.value).toLowerCase().trim();
    if (q) {
        list = list.filter(c => 
            (c.orgName || c.name || '').toLowerCase().includes(q) ||
            (c.pocEmail || '').toLowerCase().includes(q) ||
            (c.pocName || '').toLowerCase().includes(q) ||
            (c.regNumber || '').toLowerCase().includes(q) ||
            (c.industry || '').toLowerCase().includes(q)
        );
    }
    return list;
});

function getClientActiveProjectsCount(clientId) {
    return props.projects.filter(p => (p.client_id === clientId || p.clientRecord?.id === clientId) && p.status !== 'completed').length;
}

function openAddClient() {
    editingClient.value = null;
    clientForm.value = {
        orgName: '',
        regNumber: '',
        industry: initialClientsMetadata.industries[0] || 'Enterprise SaaS',
        billingAddress: '',
        pocName: '',
        pocEmail: '',
        pocPhone: '',
        status: 'active',
    };
    isClientModalOpen.value = true;
}

function openEditClient(client) {
    editingClient.value = client;
    clientForm.value = {
        orgName: client.orgName || client.name || '',
        regNumber: client.regNumber || '',
        industry: client.industry || initialClientsMetadata.industries[0] || 'Enterprise SaaS',
        billingAddress: client.billingAddress || '',
        pocName: client.pocName || client.name || '',
        pocEmail: client.pocEmail || client.email || '',
        pocPhone: client.pocPhone || '',
        status: client.status || 'active',
    };
    isClientModalOpen.value = true;
}

function saveClient() {
    if (!clientForm.value.orgName || !clientForm.value.pocEmail) return;

    const payload = {
        org_name: clientForm.value.orgName,
        reg_number: clientForm.value.regNumber,
        industry: clientForm.value.industry,
        billing_address: clientForm.value.billingAddress,
        poc_name: clientForm.value.pocName,
        poc_email: clientForm.value.pocEmail,
        poc_phone: clientForm.value.pocPhone,
        status: clientForm.value.status,
    };

    if (editingClient.value && typeof editingClient.value.id === 'number') {
        router.patch(`/admin/clients/${editingClient.value.id}`, payload, {
            preserveScroll: true,
            onSuccess: () => {
                isClientModalOpen.value = false;
                showToast(`Client "${payload.org_name}" updated successfully.`);
            },
            onError: (errs) => {
                showToast(Object.values(errs)[0] || 'Error updating client.', 'error');
            }
        });
    } else {
        router.post('/admin/clients', payload, {
            preserveScroll: true,
            onSuccess: () => {
                isClientModalOpen.value = false;
                showToast(`Client "${payload.org_name}" saved successfully.`);
            },
            onError: (errs) => {
                showToast(Object.values(errs)[0] || 'Error saving client.', 'error');
            }
        });
    }
}

function attemptDeleteClient(client) {
    const activeProjects = props.projects.filter(p => p.client_id === client.id || (client.userId && p.client_id === client.userId));
    if (activeProjects.length > 0) {
        openSafeDeleteModal({
            title: 'Cannot Delete Client',
            message: `Client "${client.orgName || client.name}" cannot be deleted because it is linked to ${activeProjects.length} active project(s).`,
            canDelete: false,
            dependencies: activeProjects.map(p => `Project: "${p.name}" (Status: ${p.status})`),
        });
        return;
    }

    openSafeDeleteModal({
        title: 'Delete Client Confirmation',
        message: `Are you sure you want to delete client account "${client.orgName || client.name}"?`,
        canDelete: true,
        dependencies: [],
        onConfirm: () => {
            if (typeof client.id === 'number') {
                router.delete(`/admin/clients/${client.id}`, {
                    preserveScroll: true,
                    onSuccess: () => {
                        showToast(`Client "${client.orgName || client.name}" deleted.`);
                    },
                    onError: (errs) => {
                        showToast(Object.values(errs)[0] || 'Error deleting client.', 'error');
                    }
                });
            } else {
                clientsList.value = clientsList.value.filter(c => c.id !== client.id);
                showToast(`Client "${client.orgName || client.name}" deleted.`);
            }
        }
    });
}

// =========================================================
// TAB 5: CLIENT-HANDLING PERSON SETUP (ACCOUNT MAPPING)
// =========================================================
const accountManagersList = ref([]);
watch(() => props.clientAssignments, (newVal) => {
    if (Array.isArray(newVal)) {
        accountManagersList.value = newVal.map(a => ({
            id: a.id,
            clientId: a.client_id,
            clientOrg: a.client?.org_name || a.client?.poc_name || ('Client #' + a.client_id),
            selectedDepartments: Array.isArray(a.selected_departments) ? a.selected_departments : [],
            assignedMemberIds: Array.isArray(a.assigned_member_ids) ? a.assigned_member_ids : [],
            primaryManagerId: a.primary_manager_id,
            backupEscalationId: a.backup_escalation_id,
            slaLevel: a.sla_level || 'Standard',
            assignmentDate: a.assignment_date || '',
            communicationPreference: a.communication_preference || 'Email Dispatch & Weekly Standup',
            notes: a.notes || '',
        }));
    } else {
        accountManagersList.value = [];
    }
}, { immediate: true });

const accountManagerSearch = ref('');
const isAccountManagerModalOpen = ref(false);
const editingAccountManager = ref(null);
const isClientAlreadyAssigned = ref(false);

const accountManagerForm = ref({
    clientId: '',
    clientOrgName: '',
    assignedMemberIds: [],
});

// Searchable Select Options for Projects
const clientSelectOptions = computed(() => {
    if (clientsList.value.length > 0) {
        return clientsList.value.map(c => ({
            id: c.id,
            label: c.orgName || c.name,
            subtext: c.pocEmail || c.email,
            avatar: c.avatar,
            badge: c.regNumber || c.member_id || `CUST-${c.id}`,
        }));
    }
    return clientUsers.value.map(c => ({
        id: c.id,
        label: c.name,
        subtext: c.email,
        avatar: c.avatar,
        badge: c.member_id || `CUST-${c.id}`,
    }));
});

// Clients for Assignment: Already assigned clients do NOT show in list for create
const assignableClientSelectOptions = computed(() => {
    const allClients = clientsList.value.length > 0 ? clientsList.value : clientUsers.value;
    const assignedIds = new Set(accountManagersList.value.map(m => String(m.clientId)));
    const assignedOrgs = new Set(accountManagersList.value.map(m => (m.clientOrg || '').toLowerCase().trim()));

    return allClients
        .filter(c => {
            const idStr = String(c.id);
            const orgStr = (c.orgName || c.name || '').toLowerCase().trim();

            if (editingAccountManager.value) {
                const isCurrent = String(editingAccountManager.value.clientId) === idStr ||
                    (editingAccountManager.value.clientOrg || '').toLowerCase().trim() === orgStr;
                return isCurrent;
            }

            return !assignedIds.has(idStr) && !assignedOrgs.has(orgStr);
        })
        .map(c => ({
            id: c.id,
            label: c.orgName || c.name,
            subtext: c.pocEmail || c.email,
            avatar: c.avatar,
            badge: c.regNumber || c.member_id || `CUST-${c.id}`,
        }));
});

const staffSelectOptions = computed(() => {
    return staffEmployees.value.map(s => ({
        id: s.id,
        label: s.name,
        subtext: `${s.department || 'Staff'} • ${s.designation || s.role || 'Employee'}`,
        avatar: s.avatar,
        badge: s.is_team_lead ? 'Team Lead' : (s.is_admin ? 'Admin' : ''),
    }));
});

const filteredAccountManagers = computed(() => {
    let list = accountManagersList.value;
    const q = (accountManagerSearch.value || globalSearch.value).toLowerCase().trim();
    if (q) {
        list = list.filter(m => 
            (m.clientOrg || '').toLowerCase().includes(q) ||
            getAssignedMembers(m).some(u => (u.name || '').toLowerCase().includes(q) || (u.department || '').toLowerCase().includes(q))
        );
    }
    return list;
});

function getStaffName(userId) {
    if (!userId) return 'Unassigned';
    const user = props.agents.find(u => u.id === Number(userId));
    return user ? user.name : 'Staff #' + userId;
}

function getStaffUser(userId) {
    return props.agents.find(u => u.id === Number(userId)) || null;
}

function getAssignedMembers(mapping) {
    if (!mapping.assignedMemberIds || mapping.assignedMemberIds.length === 0) {
        const primary = getStaffUser(mapping.primaryManagerId);
        return primary ? [primary] : [];
    }
    return mapping.assignedMemberIds.map(id => getStaffUser(id)).filter(Boolean);
}

function removeAssignedMember(memberId) {
    accountManagerForm.value.assignedMemberIds = accountManagerForm.value.assignedMemberIds.filter(id => Number(id) !== Number(memberId));
}

function openAddAccountManager() {
    editingAccountManager.value = null;
    isClientAlreadyAssigned.value = false;

    const firstAvailable = assignableClientSelectOptions.value[0];
    const initialClientId = firstAvailable ? firstAvailable.id : '';
    const initialOrgName = firstAvailable ? firstAvailable.label : '';

    accountManagerForm.value = {
        clientId: initialClientId,
        clientOrgName: initialOrgName,
        assignedMemberIds: [],
    };
    isAccountManagerModalOpen.value = true;
}

function openEditAccountManager(mapping) {
    editingAccountManager.value = mapping;
    isClientAlreadyAssigned.value = true;

    let resolvedClientId = mapping.clientId;
    if (!resolvedClientId) {
        const allClients = clientsList.value.length > 0 ? clientsList.value : clientUsers.value;
        const found = allClients.find(c => 
            (c.orgName || c.name).toLowerCase() === mapping.clientOrg.toLowerCase() ||
            (c.orgName || c.name).toLowerCase().includes(mapping.clientOrg.toLowerCase()) ||
            mapping.clientOrg.toLowerCase().includes((c.orgName || c.name).toLowerCase())
        );
        if (found) resolvedClientId = found.id;
    }

    const memberIds = [...(mapping.assignedMemberIds || [mapping.primaryManagerId].filter(Boolean))];

    accountManagerForm.value = {
        clientId: resolvedClientId || '',
        clientOrgName: mapping.clientOrg,
        assignedMemberIds: memberIds,
    };
    isAccountManagerModalOpen.value = true;
}

function saveAccountManager() {
    if (!accountManagerForm.value.clientId) {
        showToast('Please select a client.', 'error');
        return;
    }

    const payload = {
        client_id: accountManagerForm.value.clientId,
        assigned_member_ids: accountManagerForm.value.assignedMemberIds || [],
        primary_manager_id: accountManagerForm.value.assignedMemberIds?.[0] ? Number(accountManagerForm.value.assignedMemberIds[0]) : null,
        backup_escalation_id: null,
        selected_departments: [],
        sla_level: 'Standard',
        communication_preference: '',
        notes: '',
    };

    router.post('/admin/client-assignments', payload, {
        preserveScroll: true,
        onSuccess: () => {
            isAccountManagerModalOpen.value = false;
            showToast(`Client assignment saved successfully.`);
        },
        onError: (errs) => {
            showToast(Object.values(errs)[0] || 'Error saving client assignment.', 'error');
        }
    });
}

function deleteAccountManager(mapping) {
    if (mapping.id && typeof mapping.id === 'number') {
        router.delete(`/admin/client-assignments/${mapping.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                showToast(`Client assignment removed.`);
            },
            onError: (errs) => {
                showToast(Object.values(errs)[0] || 'Error removing assignment.', 'error');
            }
        });
    } else {
        accountManagersList.value = accountManagersList.value.filter(m => m.id !== mapping.id);
        showToast(`Client assignment removed.`);
    }
}

// =========================================================
// TAB 6: PROJECT SETUP STATE & LOGIC
// =========================================================
const projectSearch = ref('');
const isProjectModalOpen = ref(false);
const editingProject = ref(null);
const projectForm = ref({
    code: '',
    name: '',
    clientId: '',
    clientHandlingLead: '',
    teamLeadId: '',
    teamMemberIds: [],
    startDate: '',
    targetDate: '',
    status: 'Active',
    description: '',
});

const projectStatuses = ['Planning', 'Active', 'Paused', 'Completed'];

const filteredProjects = computed(() => {
    let list = props.projects;
    const q = (projectSearch.value || globalSearch.value).toLowerCase().trim();
    if (q) {
        list = list.filter(p => 
            p.name.toLowerCase().includes(q) ||
            (p.client?.name || '').toLowerCase().includes(q) ||
            (p.description || '').toLowerCase().includes(q) ||
            p.status.toLowerCase().includes(q)
        );
    }
    return list;
});

function openAddProject() {
    editingProject.value = null;
    const defaultLead = staffEmployees.value.find(s => s.is_team_lead) || staffEmployees.value[0];
    const today = new Date().toISOString().split('T')[0];
    const target = new Date(Date.now() + 86400000 * 30).toISOString().split('T')[0];

    projectForm.value = {
        code: '',
        name: '',
        clientId: clientsList.value[0]?.id || clientUsers.value[0]?.id || '',
        clientHandlingLead: '',
        teamLeadId: defaultLead?.id || '',
        teamMemberIds: defaultLead ? [defaultLead.id] : [],
        startDate: today,
        targetDate: target,
        status: 'Active',
        description: '',
    };
    onProjectClientChange();
    isProjectModalOpen.value = true;
}

function openEditProject(proj) {
    editingProject.value = proj;
    projectForm.value = {
        code: proj.code || ('PRJ-' + proj.id),
        name: proj.name,
        clientId: proj.client_id || '',
        clientHandlingLead: '',
        teamLeadId: proj.team_lead_id || staffEmployees.value.find(s => s.is_team_lead)?.id || staffEmployees.value[0]?.id || '',
        teamMemberIds: Array.isArray(proj.team_member_ids) ? [...proj.team_member_ids] : [],
        startDate: proj.start_date || new Date().toISOString().split('T')[0],
        targetDate: proj.target_date || new Date(Date.now() + 86400000 * 30).toISOString().split('T')[0],
        status: proj.status ? proj.status.charAt(0).toUpperCase() + proj.status.slice(1) : 'Active',
        description: proj.description || '',
    };
    onProjectClientChange();
    isProjectModalOpen.value = true;
}

function onProjectClientChange() {
    const mapping = accountManagersList.value.find(m => {
        if (projectForm.value.clientId) {
            return String(m.clientId) === String(projectForm.value.clientId);
        }
        return false;
    });

    if (mapping && mapping.primaryManagerId) {
        const teamCount = (mapping.assignedMemberIds || []).length;
        projectForm.value.clientHandlingLead = getStaffName(mapping.primaryManagerId) + (teamCount > 1 ? ` (+${teamCount - 1} team)` : '');
    } else {
        const client = clientUsers.value.find(c => String(c.id) === String(projectForm.value.clientId));
        if (client && client.assigned_agent) {
            projectForm.value.clientHandlingLead = client.assigned_agent.name;
        } else {
            projectForm.value.clientHandlingLead = '';
        }
    }
}

function toggleProjectTeamMember(id) {
    const idx = projectForm.value.teamMemberIds.indexOf(id);
    if (idx > -1) {
        projectForm.value.teamMemberIds.splice(idx, 1);
    } else {
        projectForm.value.teamMemberIds.push(id);
    }
}

function saveProject() {
    if (!projectForm.value.name.trim()) return;

    const payload = {
        code: projectForm.value.code,
        name: projectForm.value.name,
        client_id: projectForm.value.clientId ? Number(projectForm.value.clientId) : null,
        team_lead_id: projectForm.value.teamLeadId ? Number(projectForm.value.teamLeadId) : null,
        team_member_ids: projectForm.value.teamMemberIds || [],
        start_date: projectForm.value.startDate || null,
        target_date: projectForm.value.targetDate || null,
        status: projectForm.value.status.toLowerCase(),
        description: projectForm.value.description,
    };

    if (editingProject.value) {
        router.patch(`/projects/${editingProject.value.id}`, payload, {
            preserveScroll: true,
            onSuccess: () => {
                isProjectModalOpen.value = false;
                showToast(`Project "${projectForm.value.name}" updated successfully.`);
            },
            onError: (errs) => {
                showToast(Object.values(errs)[0] || 'Error updating project.', 'error');
            }
        });
    } else {
        router.post('/projects', payload, {
            preserveScroll: true,
            onSuccess: () => {
                isProjectModalOpen.value = false;
                showToast(`Project "${projectForm.value.name}" created successfully.`);
            },
            onError: (errs) => {
                showToast(Object.values(errs)[0] || 'Error creating project.', 'error');
            }
        });
    }
}

function attemptDeleteProject(project) {
    const activeTasks = props.tasks.filter(t => t.project_id === project.id && t.status !== 'done');
    if (activeTasks.length > 0) {
        openSafeDeleteModal({
            title: 'Active Tasks Dependency Conflict',
            message: `Project "${project.name}" cannot be deleted because it has ${activeTasks.length} active in-flight task(s).`,
            canDelete: false,
            dependencies: activeTasks.map(t => `Task #${t.id}: "${t.title}" (${t.status})`),
        });
        return;
    }

    openSafeDeleteModal({
        title: 'Delete Project Confirmation',
        message: `Delete project "${project.name}"? Tasks associated with this project will be decoupled.`,
        canDelete: true,
        dependencies: [],
        onConfirm: () => {
            router.delete(`/projects/${project.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    showToast(`Project "${project.name}" deleted.`);
                }
            });
        }
    });
}

function getProjectStatusBadge(status) {
    const s = (status || '').toLowerCase();
    switch (s) {
        case 'active': return 'bg-emerald-100 text-emerald-800 border-emerald-300 font-bold';
        case 'planning': return 'bg-blue-100 text-blue-800 border-blue-300 font-bold';
        case 'paused': case 'on_hold': return 'bg-amber-100 text-amber-800 border-amber-300 font-bold';
        case 'completed': return 'bg-slate-100 text-slate-700 border-slate-300 font-bold';
        default: return 'bg-slate-100 text-slate-700 border-slate-200';
    }
}

// =========================================================
// CSV EXPORT LOGIC FOR ACTIVE TAB
// =========================================================
function exportActiveTabCsv() {
    let headers = [];
    let rows = [];
    const dateStr = new Date().toISOString().split('T')[0];

    if (activeTab.value === 'departments') {
        headers = ['Code', 'Department Name', 'HOD', 'Staff Count', 'Status', 'Description'];
        rows = filteredDepartments.value.map(d => [
            d.code,
            `"${d.name}"`,
            `"${getHodUser(d.hodId)?.name || 'Unassigned'}"`,
            getDepartmentStaffCount(d.name),
            d.status,
            `"${d.description || ''}"`
        ]);
    } else if (activeTab.value === 'designations') {
        headers = ['Title', 'Department', 'Seniority Band', 'Responsibilities Brief'];
        rows = filteredDesignations.value.map(d => [
            `"${d.title}"`,
            `"${d.department}"`,
            d.band,
            `"${(d.responsibilities || '').replace(/"/g, '""')}"`
        ]);
    } else if (activeTab.value === 'employees') {
        headers = ['Employee ID', 'Full Name', 'Email', 'Department', 'Designation', 'System Role', 'Team Lead'];
        rows = filteredEmployees.value.map(e => [
            e.member_id || 'N/A',
            `"${e.name}"`,
            e.email,
            `"${e.department || 'General'}"`,
            `"${e.designation || e.role || 'Staff'}"`,
            e.is_admin ? 'Admin' : (e.is_team_lead ? 'Team Lead' : 'Employee'),
            e.is_team_lead ? 'Yes' : 'No'
        ]);
    } else if (activeTab.value === 'clients') {
        headers = ['Client Organization', 'Member ID', 'Email', 'Active Projects Count'];
        rows = filteredClients.value.map(c => [
            `"${c.name}"`,
            c.member_id || 'N/A',
            c.email,
            getClientActiveProjectsCount(c.id)
        ]);
    } else if (activeTab.value === 'account_managers') {
        headers = ['Client', 'Assigned Users'];
        rows = filteredAccountManagers.value.map(m => {
            const members = getAssignedMembers(m).map(u => u.name).join('; ');
            return [
                `"${(m.clientOrg || '').replace(/"/g, '""')}"`,
                `"${members.replace(/"/g, '""')}"`
            ];
        });
    } else if (activeTab.value === 'projects') {
        headers = ['Project Code', 'Project Name', 'Client Name', 'Status', 'Description'];
        rows = filteredProjects.value.map(p => [
            `"PRJ-${p.id}"`,
            `"${p.name}"`,
            `"${p.client?.name || 'Internal'}"`,
            p.status,
            `"${(p.description || '').replace(/"/g, '""')}"`
        ]);
    }

    const csvContent = 'data:text/csv;charset=utf-8,' + [headers.join(','), ...rows.map(e => e.join(','))].join('\n');
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement('a');
    link.setAttribute('href', encodedUri);
    link.setAttribute('download', `admin_setup_${activeTab.value}_${dateStr}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    showToast(`Exported ${activeTab.value} table to CSV.`);
}
</script>

<template>
    <div class="flex-1 flex flex-col h-full bg-slate-50 overflow-y-auto">
        <!-- FLOATING TOAST NOTIFICATIONS -->
        <div class="fixed top-4 right-4 z-50 space-y-2 pointer-events-none">
            <div
                v-for="toast in toasts"
                :key="toast.id"
                class="pointer-events-auto px-4 py-2.5 rounded-xl bg-slate-900 text-white text-xs font-semibold shadow-xl flex items-center space-x-2 animate-bounce-short"
            >
                <CheckCircle2 class="w-4 h-4 text-emerald-400 shrink-0" />
                <span>{{ toast.message }}</span>
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- TOP BREADCRUMB & GLOBAL MASTER ACTION BAR                     -->
        <!-- ============================================================= -->
        <!-- ============================================================= -->
        <!-- TOP NAVIGATION & TABS BAR                                     -->
        <!-- ============================================================= -->
        <div class="bg-white border-b border-slate-200 px-6 py-3.5 shadow-2xs">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100">
                <div class="flex items-center space-x-2">
                    <ShieldCheck class="w-4 h-4 text-indigo-600" />
                    <h1 class="text-sm font-bold text-slate-900 tracking-tight">Admin Setup</h1>
                </div>

                <!-- Export CSV -->
                <button
                    @click="exportActiveTabCsv"
                    class="px-3 py-1.5 bg-slate-50 hover:bg-slate-100 border border-slate-200 text-slate-700 rounded-xl text-xs font-semibold flex items-center space-x-1.5 transition-colors shadow-2xs"
                    title="Export active table to CSV"
                >
                    <Download class="w-3.5 h-3.5 text-slate-500" />
                    <span>Export CSV</span>
                </button>
            </div>

            <!-- 6 DEDICATED SETUP TABS -->
            <div class="flex items-center space-x-1 pt-3 overflow-x-auto text-xs font-semibold">
                <button
                    v-for="t in tabs"
                    :key="t.id"
                    @click="activeTab = t.id"
                    :class="[
                        'px-3.5 py-2 rounded-xl flex items-center space-x-2 transition-all whitespace-nowrap',
                        activeTab === t.id
                            ? 'bg-indigo-600 text-white font-bold shadow-xs'
                            : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100'
                    ]"
                >
                    <component :is="t.icon" class="w-4 h-4" />
                    <span>{{ t.label }}</span>
                    <!-- Count Badge -->
                    <span :class="[
                        'px-1.5 py-0.2 rounded-full text-[10px] font-mono font-bold',
                        activeTab === t.id ? 'bg-indigo-700 text-white' : 'bg-slate-200/80 text-slate-700'
                    ]">
                        {{ 
                            t.id === 'departments' ? departmentsList.length :
                            t.id === 'designations' ? designationsList.length :
                            t.id === 'employees' ? staffEmployees.length :
                            t.id === 'clients' ? clientsList.length :
                            t.id === 'account_managers' ? accountManagersList.length :
                            projects.length
                        }}
                    </span>
                </button>
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- TAB CONTENT BODY                                              -->
        <!-- ============================================================= -->
        <div class="p-6">
            <!-- --------------------------------------------------------- -->
            <!-- TAB 1: DEPARTMENT SETUP                                    -->
            <!-- --------------------------------------------------------- -->
            <div v-if="activeTab === 'departments'" class="space-y-4">
                <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-2xs flex flex-col sm:flex-row items-center justify-between gap-3">
                    <h2 class="text-sm font-bold text-slate-900">Departments</h2>

                    <div class="flex items-center space-x-2 w-full sm:w-auto">
                        <div class="relative w-full sm:w-64">
                            <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-2.5" />
                            <input
                                v-model="departmentSearch"
                                type="text"
                                placeholder="Search departments..."
                                class="w-full pl-9 pr-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:bg-white focus:border-indigo-600"
                            />
                        </div>
                        <button
                            @click="openAddDepartment()"
                            class="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-semibold flex items-center space-x-1 whitespace-nowrap shadow-2xs"
                        >
                            <Plus class="w-3.5 h-3.5" />
                            <span>Add Department</span>
                        </button>
                    </div>
                </div>

                <!-- Department Data Table -->
                <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-2xs">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 border-b border-slate-200 text-[10px] uppercase font-bold text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Code</th>
                                <th class="px-3 py-3">Department Name</th>
                                <th class="px-3 py-3">Head of Department</th>
                                <th class="px-3 py-3 text-center">Staff Count</th>
                                <th class="px-3 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="filteredDepartments.length === 0">
                                <td colspan="6" class="px-4 py-8 text-center text-slate-400">
                                    No departments found.
                                </td>
                            </tr>
                            <tr v-for="dept in filteredDepartments" :key="dept.id" class="hover:bg-slate-50/70 transition-colors">
                                <td class="px-4 py-3 font-mono font-bold text-indigo-700">
                                    {{ dept.code }}
                                </td>
                                <td class="px-3 py-3">
                                    <div class="font-bold text-slate-900">{{ dept.name }}</div>
                                    <div v-if="dept.description" class="text-[10px] text-slate-400 line-clamp-1 max-w-sm">{{ dept.description }}</div>
                                </td>
                                <td class="px-3 py-3">
                                    <div v-if="getHodUser(dept.hodId)" class="flex items-center space-x-2">
                                        <img :src="getHodUser(dept.hodId).avatar" class="w-5 h-5 rounded-full object-cover" />
                                        <span class="font-medium text-slate-800">{{ getHodUser(dept.hodId).name }}</span>
                                    </div>
                                    <span v-else class="text-slate-400 italic">Unassigned</span>
                                </td>
                                <td class="px-3 py-3 text-center font-mono font-bold text-slate-700">
                                    <span class="px-2 py-0.5 rounded-full bg-slate-100 border border-slate-200 text-[10px]">
                                        {{ getDepartmentStaffCount(dept.name) }}
                                    </span>
                                </td>
                                <td class="px-3 py-3">
                                    <span :class="['px-2 py-0.5 rounded-full text-[10px] font-bold border', dept.status === 'active' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200']">
                                        {{ dept.status === 'active' ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <button
                                        @click="openEditDepartment(dept)"
                                        class="p-1 text-slate-400 hover:text-indigo-600 rounded transition-colors"
                                        title="Edit Department"
                                    >
                                        <Edit3 class="w-3.5 h-3.5" />
                                    </button>
                                    <button
                                        @click="attemptDeleteDepartment(dept)"
                                        class="p-1 text-slate-400 hover:text-rose-600 rounded transition-colors ml-1"
                                        title="Delete Department"
                                    >
                                        <Trash2 class="w-3.5 h-3.5" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- --------------------------------------------------------- -->
            <!-- TAB 2: DESIGNATION SETUP                                   -->
            <!-- --------------------------------------------------------- -->
            <div v-if="activeTab === 'designations'" class="space-y-4">
                <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-2xs flex flex-col sm:flex-row items-center justify-between gap-3">
                    <h2 class="text-sm font-bold text-slate-900">Designations</h2>

                    <div class="flex items-center space-x-2 w-full sm:w-auto">
                        <div class="relative w-full sm:w-64">
                            <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-2.5" />
                            <input
                                v-model="designationSearch"
                                type="text"
                                placeholder="Search designations..."
                                class="w-full pl-9 pr-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:bg-white focus:border-indigo-600"
                            />
                        </div>
                        <button
                            @click="openAddDesignation()"
                            class="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-semibold flex items-center space-x-1 whitespace-nowrap shadow-2xs"
                        >
                            <Plus class="w-3.5 h-3.5" />
                            <span>Add Designation</span>
                        </button>
                    </div>
                </div>

                <!-- Designations Data Table -->
                <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-2xs">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 border-b border-slate-200 text-[10px] uppercase font-bold text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Designation</th>
                                <th class="px-3 py-3">Department</th>
                                <th class="px-3 py-3">Band</th>
                                <th class="px-3 py-3">Responsibilities</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="filteredDesignations.length === 0">
                                <td colspan="5" class="px-4 py-8 text-center text-slate-400">
                                    No designations found.
                                </td>
                            </tr>
                            <tr v-for="desig in filteredDesignations" :key="desig.id" class="hover:bg-slate-50/70 transition-colors">
                                <td class="px-4 py-3 font-bold text-slate-900">
                                    {{ desig.title }}
                                </td>
                                <td class="px-3 py-3">
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200">
                                        {{ desig.department }}
                                    </span>
                                </td>
                                <td class="px-3 py-3">
                                    <span :class="['px-2 py-0.5 rounded-md text-[10px] font-bold border', getBandColor(desig.band)]">
                                        {{ desig.band }}
                                    </span>
                                </td>
                                <td class="px-3 py-3 text-slate-500 max-w-md truncate" :title="desig.responsibilities">
                                    {{ desig.responsibilities || 'Standard duties apply.' }}
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <button
                                        @click="openEditDesignation(desig)"
                                        class="p-1 text-slate-400 hover:text-indigo-600 rounded transition-colors"
                                        title="Edit Designation"
                                    >
                                        <Edit3 class="w-3.5 h-3.5" />
                                    </button>
                                    <button
                                        @click="attemptDeleteDesignation(desig)"
                                        class="p-1 text-slate-400 hover:text-rose-600 rounded transition-colors ml-1"
                                        title="Delete Designation"
                                    >
                                        <Trash2 class="w-3.5 h-3.5" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- --------------------------------------------------------- -->
            <!-- --------------------------------------------------------- -->
            <!-- TAB 3: EMPLOYEE SETUP                                      -->
            <!-- --------------------------------------------------------- -->
            <div v-if="activeTab === 'employees'" class="space-y-4">
                <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-2xs flex flex-col sm:flex-row items-center justify-between gap-3">
                    <h2 class="text-sm font-bold text-slate-900">Employees</h2>

                    <div class="flex items-center space-x-2 w-full sm:w-auto">
                        <div class="relative w-full sm:w-64">
                            <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-2.5" />
                            <input
                                v-model="employeeSearch"
                                type="text"
                                placeholder="Search employees..."
                                class="w-full pl-9 pr-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:bg-white focus:border-indigo-600"
                            />
                        </div>
                        <button
                            @click="openAddEmployee()"
                            class="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-semibold flex items-center space-x-1 whitespace-nowrap shadow-2xs"
                        >
                            <UserPlus class="w-3.5 h-3.5" />
                            <span>Add Employee</span>
                        </button>
                    </div>
                </div>

                <!-- Employees Data Table -->
                <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-2xs">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 border-b border-slate-200 text-[10px] uppercase font-bold text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Name</th>
                                <th class="px-3 py-3">Employee ID</th>
                                <th class="px-3 py-3">Email</th>
                                <th class="px-3 py-3">Department</th>
                                <th class="px-3 py-3">Designation</th>
                                <th class="px-3 py-3">Role</th>
                                <th class="px-3 py-3 text-center">Status</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="filteredEmployees.length === 0">
                                <td colspan="8" class="px-4 py-8 text-center text-slate-400">
                                    No employees found.
                                </td>
                            </tr>
                            <tr v-for="user in filteredEmployees" :key="user.id" class="hover:bg-slate-50/70 transition-colors">
                                <td class="px-4 py-3">
                                    <div class="flex items-center space-x-2.5">
                                        <img :src="user.avatar || 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=50&h=50&fit=crop'" class="w-7 h-7 rounded-full object-cover" />
                                        <span class="font-bold text-slate-900">{{ user.name }}</span>
                                    </div>
                                </td>
                                <td class="px-3 py-3 font-mono text-slate-600">{{ user.member_id || 'EMP-' + user.id }}</td>
                                <td class="px-3 py-3 text-slate-600">{{ user.email }}</td>
                                <td class="px-3 py-3">
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                                        {{ user.department || 'Engineering' }}
                                    </span>
                                </td>
                                <td class="px-3 py-3 font-medium text-slate-800">{{ user.designation || user.role || 'Software Engineer' }}</td>
                                <td class="px-3 py-3">
                                    <button
                                        @click="toggleTeamLead(user)"
                                        :class="[
                                            'px-2 py-0.5 rounded-md text-[10px] font-bold flex items-center space-x-1 border shadow-2xs transition-colors',
                                            user.is_admin ? 'bg-purple-100 text-purple-800 border-purple-200' :
                                            user.is_team_lead ? 'bg-amber-100 text-amber-900 border-amber-300' : 'bg-slate-100 text-slate-600 border-slate-200'
                                        ]"
                                        title="Click to toggle Team Lead status"
                                    >
                                        <Star v-if="user.is_team_lead" class="w-3 h-3 text-amber-600 fill-amber-500" />
                                        <span>{{ user.is_admin ? 'Admin' : (user.is_team_lead ? 'Team Lead' : 'Employee') }}</span>
                                    </button>
                                </td>
                                <td class="px-3 py-3 text-center">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        Active
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <button
                                        @click="openEditEmployee(user)"
                                        class="p-1 text-slate-400 hover:text-indigo-600 rounded transition-colors"
                                        title="Edit Employee"
                                    >
                                        <Edit3 class="w-3.5 h-3.5" />
                                    </button>
                                    <button
                                        @click="attemptDeleteEmployee(user)"
                                        class="p-1 text-slate-400 hover:text-rose-600 rounded transition-colors ml-1"
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

            <!-- --------------------------------------------------------- -->
            <!-- TAB 4: CLIENT SETUP                                        -->
            <!-- --------------------------------------------------------- -->
            <div v-if="activeTab === 'clients'" class="space-y-4">
                <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-2xs flex flex-col sm:flex-row items-center justify-between gap-3">
                    <h2 class="text-sm font-bold text-slate-900">Clients</h2>

                    <div class="flex items-center space-x-2 w-full sm:w-auto">
                        <div class="relative w-full sm:w-64">
                            <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-2.5" />
                            <input
                                v-model="clientSearch"
                                type="text"
                                placeholder="Search clients..."
                                class="w-full pl-9 pr-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:bg-white focus:border-indigo-600"
                            />
                        </div>
                        <button
                            @click="openAddClient()"
                            class="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-semibold flex items-center space-x-1 whitespace-nowrap shadow-2xs"
                        >
                            <Building2 class="w-3.5 h-3.5" />
                            <span>Add Client</span>
                        </button>
                    </div>
                </div>

                <!-- Client Data Table -->
                <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-2xs">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 border-b border-slate-200 text-[10px] uppercase font-bold text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Client</th>
                                <th class="px-3 py-3">Contact</th>
                                <th class="px-3 py-3">Industry</th>
                                <th class="px-3 py-3 text-center">Projects</th>
                                <th class="px-3 py-3 text-center">Status</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="filteredClients.length === 0">
                                <td colspan="6" class="px-4 py-8 text-center text-slate-400">
                                    No clients found.
                                </td>
                            </tr>
                            <tr v-for="client in filteredClients" :key="client.id" class="hover:bg-slate-50/70 transition-colors">
                                <td class="px-4 py-3">
                                    <div class="flex items-center space-x-2.5">
                                        <img :src="client.avatar || 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=50&h=50&fit=crop'" class="w-7 h-7 rounded-full object-cover" />
                                        <div>
                                            <div class="font-bold text-slate-900">{{ client.orgName || client.name }}</div>
                                            <div class="text-[10px] font-mono text-slate-400">{{ client.regNumber || client.member_id || 'CUST-' + client.id }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-3">
                                    <div class="font-medium text-slate-800">{{ client.pocName || client.name }}</div>
                                    <div class="text-[11px] text-slate-500">{{ client.pocEmail || client.email }}</div>
                                </td>
                                <td class="px-3 py-3">
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200">
                                        {{ client.industry || 'Enterprise SaaS' }}
                                    </span>
                                </td>
                                <td class="px-3 py-3 text-center font-mono font-bold text-indigo-700">
                                    {{ client.projectsCount ?? getClientActiveProjectsCount(client.id) }}
                                </td>
                                <td class="px-3 py-3 text-center">
                                    <span :class="['px-2 py-0.5 rounded-full text-[10px] font-bold border', (client.status || 'active') === 'active' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-slate-100 text-slate-600 border-slate-200']">
                                        {{ (client.status || 'active') === 'active' ? 'Active' : 'Inactive' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <button
                                        @click="openEditClient(client)"
                                        class="p-1 text-slate-400 hover:text-indigo-600 rounded transition-colors"
                                        title="Edit Client"
                                    >
                                        <Edit3 class="w-3.5 h-3.5" />
                                    </button>
                                    <button
                                        @click="attemptDeleteClient(client)"
                                        class="p-1 text-slate-400 hover:text-rose-600 rounded transition-colors ml-1"
                                        title="Delete Client"
                                    >
                                        <Trash2 class="w-3.5 h-3.5" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- --------------------------------------------------------- -->
            <!-- TAB 5: CLIENT ASSIGNMENTS                                  -->
            <!-- --------------------------------------------------------- -->
            <div v-if="activeTab === 'account_managers'" class="space-y-4">
                <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-2xs flex flex-col sm:flex-row items-center justify-between gap-3">
                    <h2 class="text-sm font-bold text-slate-900">Client Team Assignments</h2>

                    <div class="flex items-center space-x-2 w-full sm:w-auto">
                        <div class="relative w-full sm:w-64">
                            <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-2.5" />
                            <input
                                v-model="accountManagerSearch"
                                type="text"
                                placeholder="Search client or user..."
                                class="w-full pl-9 pr-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:bg-white focus:border-indigo-600"
                            />
                        </div>
                        <button
                            @click="openAddAccountManager()"
                            class="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-semibold flex items-center space-x-1 whitespace-nowrap shadow-2xs"
                        >
                            <UserCog class="w-3.5 h-3.5" />
                            <span>Assign Client</span>
                        </button>
                    </div>
                </div>

                <!-- Client Assignment Data Table (Client & Users Only) -->
                <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-2xs">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 border-b border-slate-200 text-[10px] uppercase font-bold text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Client</th>
                                <th class="px-4 py-3">Assigned Users</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="filteredAccountManagers.length === 0">
                                <td colspan="3" class="px-4 py-8 text-center text-slate-400">
                                    No client assignments found.
                                </td>
                            </tr>
                            <tr v-for="mapping in filteredAccountManagers" :key="mapping.id" class="hover:bg-slate-50/70 transition-colors">
                                <td class="px-4 py-3 font-bold text-slate-900">
                                    {{ mapping.clientOrg }}
                                </td>
                                <td class="px-4 py-3">
                                    <div v-if="getAssignedMembers(mapping).length > 0" class="flex flex-wrap items-center gap-1.5">
                                        <div
                                            v-for="user in getAssignedMembers(mapping)"
                                            :key="user.id"
                                            class="inline-flex items-center space-x-1.5 px-2 py-0.5 rounded-lg bg-slate-50 border border-slate-200 text-xs"
                                        >
                                            <img
                                                :src="user.avatar || 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=50&h=50&fit=crop'"
                                                class="w-4 h-4 rounded-full object-cover shrink-0"
                                            />
                                            <span class="font-medium text-slate-800">{{ user.name }}</span>
                                            <span class="text-[10px] text-slate-400">({{ user.department || 'Staff' }})</span>
                                        </div>
                                    </div>
                                    <span v-else class="text-slate-400 italic">No users assigned</span>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <button
                                        @click="openEditAccountManager(mapping)"
                                        class="p-1 text-slate-400 hover:text-indigo-600 rounded transition-colors"
                                        title="Edit Assignment"
                                    >
                                        <Edit3 class="w-3.5 h-3.5" />
                                    </button>
                                    <button
                                        @click="deleteAccountManager(mapping)"
                                        class="p-1 text-slate-400 hover:text-rose-600 rounded transition-colors ml-1"
                                        title="Remove Assignment"
                                    >
                                        <Trash2 class="w-3.5 h-3.5" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- --------------------------------------------------------- -->
            <!-- TAB 6: PROJECT SETUP                                       -->
            <!-- --------------------------------------------------------- -->
            <div v-if="activeTab === 'projects'" class="space-y-4">
                <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-2xs flex flex-col sm:flex-row items-center justify-between gap-3">
                    <h2 class="text-sm font-bold text-slate-900">Projects</h2>

                    <div class="flex items-center space-x-2 w-full sm:w-auto">
                        <div class="relative w-full sm:w-64">
                            <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-2.5" />
                            <input
                                v-model="projectSearch"
                                type="text"
                                placeholder="Search projects..."
                                class="w-full pl-9 pr-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:bg-white focus:border-indigo-600"
                            />
                        </div>
                        <button
                            @click="openAddProject()"
                            class="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-semibold flex items-center space-x-1 whitespace-nowrap shadow-2xs"
                        >
                            <FolderKanban class="w-3.5 h-3.5" />
                            <span>Add Project</span>
                        </button>
                    </div>
                </div>

                <!-- Projects Data Table -->
                <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-2xs">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 border-b border-slate-200 text-[10px] uppercase font-bold text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Code</th>
                                <th class="px-3 py-3">Project Name</th>
                                <th class="px-3 py-3">Client</th>
                                <th class="px-3 py-3">Handling Lead</th>
                                <th class="px-3 py-3 text-center">Status</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-if="filteredProjects.length === 0">
                                <td colspan="6" class="px-4 py-8 text-center text-slate-400">
                                    No projects found.
                                </td>
                            </tr>
                            <tr v-for="proj in filteredProjects" :key="proj.id" class="hover:bg-slate-50/70 transition-colors">
                                <td class="px-4 py-3 font-mono font-bold text-indigo-700">
                                    {{ proj.code || ('PRJ-' + proj.id) }}
                                </td>
                                <td class="px-3 py-3">
                                    <div class="font-bold text-slate-900">{{ proj.name }}</div>
                                    <div v-if="proj.description" class="text-[10px] text-slate-400 line-clamp-1 max-w-sm">{{ proj.description }}</div>
                                </td>
                                <td class="px-3 py-3 font-medium text-slate-800">
                                    {{ proj.clientRecord?.org_name || proj.client?.name || 'Internal Project' }}
                                </td>
                                <td class="px-3 py-3 text-indigo-700 font-semibold">
                                    {{ proj.client?.assigned_agent?.name || 'Unassigned' }}
                                </td>
                                <td class="px-3 py-3 text-center">
                                    <span :class="['px-2 py-0.5 rounded-full text-[10px] border capitalize', getProjectStatusBadge(proj.status)]">
                                        {{ proj.status || 'Active' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <button
                                        @click="openEditProject(proj)"
                                        class="p-1 text-slate-400 hover:text-indigo-600 rounded transition-colors"
                                        title="Edit Project"
                                    >
                                        <Edit3 class="w-3.5 h-3.5" />
                                    </button>
                                    <button
                                        @click="attemptDeleteProject(proj)"
                                        class="p-1 text-slate-400 hover:text-rose-600 rounded transition-colors ml-1"
                                        title="Delete Project"
                                    >
                                        <Trash2 class="w-3.5 h-3.5" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- SAFE DELETE PREVENTION MODAL                                  -->
        <!-- ============================================================= -->
        <div
            v-if="safeDeleteModal.isOpen"
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs"
        >
            <div class="w-full max-w-md bg-white rounded-2xl border border-slate-200 shadow-2xl p-5 space-y-4">
                <div class="flex items-center space-x-2 text-rose-600 border-b border-slate-100 pb-3">
                    <AlertTriangle class="w-5 h-5 shrink-0" />
                    <h3 class="text-sm font-bold text-slate-900">{{ safeDeleteModal.title }}</h3>
                </div>

                <p class="text-xs text-slate-600 leading-relaxed">
                    {{ safeDeleteModal.message }}
                </p>

                <!-- Dependency List -->
                <div v-if="safeDeleteModal.dependencies.length > 0" class="p-3 bg-rose-50 border border-rose-200 rounded-xl space-y-1">
                    <span class="text-[10px] font-bold uppercase text-rose-800 tracking-wider">Active Dependencies Blockers:</span>
                    <ul class="text-xs text-rose-900 list-disc list-inside space-y-0.5 mt-1 font-medium">
                        <li v-for="(dep, idx) in safeDeleteModal.dependencies" :key="idx">{{ dep }}</li>
                    </ul>
                </div>

                <div class="pt-2 flex items-center justify-end space-x-2 border-t border-slate-100">
                    <button
                        type="button"
                        @click="safeDeleteModal.isOpen = false"
                        class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold"
                    >
                        {{ safeDeleteModal.canDelete ? 'Cancel' : 'Understood' }}
                    </button>
                    <button
                        v-if="safeDeleteModal.canDelete"
                        type="button"
                        @click="safeDeleteModal.onConfirm && safeDeleteModal.onConfirm(); safeDeleteModal.isOpen = false;"
                        class="px-4 py-1.5 rounded-xl bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold shadow-xs"
                    >
                        Confirm Delete
                    </button>
                </div>
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- MODAL FORM 1: DEPARTMENT SETUP                                -->
        <!-- ============================================================= -->
        <div v-if="isDepartmentModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs">
            <div class="w-full max-w-md bg-white rounded-2xl border border-slate-200 shadow-2xl p-5 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-sm font-bold text-slate-900 flex items-center space-x-2">
                        <Building2 class="w-4 h-4 text-indigo-600" />
                        <span>{{ editingDepartment ? 'Edit Department' : 'Create New Department' }}</span>
                    </h3>
                    <button @click="isDepartmentModalOpen = false" class="p-1 rounded-lg text-slate-400 hover:text-slate-600">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form @submit.prevent="saveDepartment" class="space-y-3 text-xs">
                    <div class="grid grid-cols-3 gap-2">
                        <div class="space-y-1">
                            <label class="font-bold text-slate-700">Code *</label>
                            <input v-model="departmentForm.code" type="text" required placeholder="e.g. ENG" class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl uppercase font-mono font-bold" />
                        </div>
                        <div class="col-span-2 space-y-1">
                            <label class="font-bold text-slate-700">Department Name *</label>
                            <input v-model="departmentForm.name" type="text" required placeholder="e.g. Engineering" class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl" />
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="font-bold text-slate-700">Select HOD (from Active Employee Pool)</label>
                        <select v-model="departmentForm.hodId" class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl">
                            <option value="">No HOD Assigned</option>
                            <option v-for="emp in staffEmployees" :key="emp.id" :value="emp.id">
                                {{ emp.name }} ({{ emp.designation || emp.role }})
                            </option>
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label class="font-bold text-slate-700">Description</label>
                        <textarea v-model="departmentForm.description" rows="2" placeholder="Responsibilities and scope..." class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl resize-none"></textarea>
                    </div>

                    <div class="pt-2 flex items-center justify-end space-x-2">
                        <button type="button" @click="isDepartmentModalOpen = false" class="px-3 py-1.5 rounded-xl bg-slate-100 text-slate-700 font-semibold">Cancel</button>
                        <button type="submit" class="px-4 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold">Save Department</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- MODAL FORM 2: DESIGNATION SETUP                               -->
        <!-- ============================================================= -->
        <div v-if="isDesignationModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs">
            <div class="w-full max-w-md bg-white rounded-2xl border border-slate-200 shadow-2xl p-5 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-sm font-bold text-slate-900 flex items-center space-x-2">
                        <Briefcase class="w-4 h-4 text-indigo-600" />
                        <span>{{ editingDesignation ? 'Edit Designation' : 'Add New Designation' }}</span>
                    </h3>
                    <button @click="isDesignationModalOpen = false" class="p-1 rounded-lg text-slate-400 hover:text-slate-600">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form @submit.prevent="saveDesignation" class="space-y-3 text-xs">
                    <div class="space-y-1">
                        <label class="font-bold text-slate-700">Designation Name *</label>
                        <input v-model="designationForm.title" type="text" required placeholder="e.g. Senior Backend Engineer" class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl" />
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div class="space-y-1">
                            <label class="font-bold text-slate-700">Parent Department *</label>
                            <select v-model="designationForm.department" class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl">
                                <option v-for="dept in departmentsList" :key="dept.id" :value="dept.name">{{ dept.name }}</option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label class="font-bold text-slate-700">Seniority Band</label>
                            <select v-model="designationForm.band" class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl">
                                <option v-for="band in seniorityBands" :key="band" :value="band">{{ band }}</option>
                            </select>
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="font-bold text-slate-700">Responsibilities Brief</label>
                        <textarea v-model="designationForm.responsibilities" rows="2" placeholder="Key responsibilities and expectations..." class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl resize-none"></textarea>
                    </div>

                    <div class="pt-2 flex items-center justify-end space-x-2">
                        <button type="button" @click="isDesignationModalOpen = false" class="px-3 py-1.5 rounded-xl bg-slate-100 text-slate-700 font-semibold">Cancel</button>
                        <button type="submit" class="px-4 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold">Save Designation</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ============================================================= -->
        <!-- MODAL FORM 3: EMPLOYEE SETUP (DYNAMIC DEPT -> DESIGNATION)     -->
        <!-- ============================================================= -->
        <div v-if="isEmployeeModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs">
            <div class="w-full max-w-lg bg-white rounded-2xl border border-slate-200 shadow-2xl p-5 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-sm font-bold text-slate-900 flex items-center space-x-2">
                        <UserPlus class="w-4 h-4 text-indigo-600" />
                        <span>{{ editingEmployee ? 'Edit Employee Details' : 'Add New Staff Employee' }}</span>
                    </h3>
                    <button @click="isEmployeeModalOpen = false" class="p-1 rounded-lg text-slate-400 hover:text-slate-600">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form @submit.prevent="saveEmployee" class="space-y-3 text-xs">
                    <div class="grid grid-cols-2 gap-2">
                        <div class="space-y-1">
                            <label class="font-bold text-slate-700">First Name *</label>
                            <input v-model="employeeForm.firstName" type="text" required placeholder="e.g. Sarah" class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl" />
                        </div>
                        <div class="space-y-1">
                            <label class="font-bold text-slate-700">Last Name</label>
                            <input v-model="employeeForm.lastName" type="text" placeholder="e.g. Jenkins" class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div class="space-y-1">
                            <label class="font-bold text-slate-700">Work Email *</label>
                            <input v-model="employeeForm.email" type="email" required placeholder="sarah@fluxdesk.com" class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl" />
                        </div>
                        <div class="space-y-1">
                            <label class="font-bold text-slate-700">Phone</label>
                            <input v-model="employeeForm.phone" type="text" placeholder="+1 (555) 019-2834" class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl" />
                        </div>
                    </div>

                    <!-- Department and Designation -->
                    <div class="grid grid-cols-2 gap-2">
                        <div class="space-y-1">
                            <label class="font-bold text-slate-700">Department *</label>
                            <select v-model="employeeForm.department" class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl">
                                <option v-for="dept in departmentsList" :key="dept.id" :value="dept.name">{{ dept.name }}</option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label class="font-bold text-slate-700">Designation *</label>
                            <select v-model="employeeForm.designation" class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl">
                                <option v-for="desig in availableDesignationsForSelectedDept" :key="desig.id" :value="desig.title">
                                    {{ desig.title }} ({{ desig.band }})
                                </option>
                                <option v-if="availableDesignationsForSelectedDept.length === 0" value="General Staff">General Staff</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div class="space-y-1">
                            <label class="font-bold text-slate-700">Reporting Manager</label>
                            <select v-model="employeeForm.reportingManagerId" class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl">
                                <option value="">No Direct Manager</option>
                                <option v-for="mgr in staffEmployees" :key="mgr.id" :value="mgr.id">{{ mgr.name }} ({{ mgr.role }})</option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label class="font-bold text-slate-700">Role</label>
                            <select v-model="employeeForm.role" class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl font-semibold">
                                <option value="Employee">Employee</option>
                                <option value="Team Lead">Team Lead</option>
                                <option value="Admin">Admin</option>
                            </select>
                        </div>
                    </div>

                    <div class="pt-2 flex items-center justify-end space-x-2">
                        <button type="button" @click="isEmployeeModalOpen = false" class="px-3 py-1.5 rounded-xl bg-slate-100 text-slate-700 font-semibold">Cancel</button>
                        <button type="submit" class="px-4 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold">Save Employee</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- MODAL FORM 4: CLIENT SETUP                                -->
        <!-- ========================================================= -->
        <div v-if="isClientModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs">
            <div class="w-full max-w-lg bg-white rounded-2xl border border-slate-200 shadow-2xl p-5 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-sm font-bold text-slate-900 flex items-center space-x-2">
                        <Building2 class="w-4 h-4 text-indigo-600" />
                        <span>{{ editingClient ? 'Edit Client Account' : 'Register New Client' }}</span>
                    </h3>
                    <button @click="isClientModalOpen = false" class="p-1 rounded-lg text-slate-400 hover:text-slate-600">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form @submit.prevent="saveClient" class="space-y-3 text-xs">
                    <div class="grid grid-cols-2 gap-2">
                        <div class="space-y-1">
                            <label class="font-bold text-slate-700">Organization Name *</label>
                            <input v-model="clientForm.orgName" type="text" required placeholder="e.g. Acme Corporation" class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl" />
                        </div>
                        <div class="space-y-1">
                            <label class="font-bold text-slate-700">Registration Number</label>
                            <input v-model="clientForm.regNumber" type="text" placeholder="e.g. REG-101" class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl font-mono" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div class="space-y-1">
                            <label class="font-bold text-slate-700">Industry</label>
                            <select v-model="clientForm.industry" class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl">
                                <option v-for="ind in initialClientsMetadata.industries" :key="ind" :value="ind">{{ ind }}</option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label class="font-bold text-slate-700">Primary Contact Email *</label>
                            <input v-model="clientForm.pocEmail" type="email" required placeholder="contact@client.com" class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl" />
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="font-bold text-slate-700">Billing Address</label>
                        <input v-model="clientForm.billingAddress" type="text" placeholder="Billing address..." class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl" />
                    </div>

                    <div class="pt-2 flex items-center justify-end space-x-2">
                        <button type="button" @click="isClientModalOpen = false" class="px-3 py-1.5 rounded-xl bg-slate-100 text-slate-700 font-semibold">Cancel</button>
                        <button type="submit" class="px-4 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold">Save Client</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- MODAL FORM 5: CLIENT ASSIGNMENT                           -->
        <!-- ========================================================= -->
        <div v-if="isAccountManagerModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs">
            <div class="w-full max-w-lg bg-white rounded-2xl border border-slate-200 shadow-2xl p-5 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <div class="flex items-center space-x-2">
                        <UserCog class="w-4 h-4 text-indigo-600" />
                        <h3 class="text-sm font-bold text-slate-900">
                            {{ editingAccountManager ? 'Edit Client Assignment' : 'Assign Client' }}
                        </h3>
                    </div>
                    <button @click="isAccountManagerModalOpen = false" class="p-1 rounded-lg text-slate-400 hover:text-slate-600 hover:bg-slate-100 transition-colors">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <div v-if="!editingAccountManager && assignableClientSelectOptions.length === 0" class="p-4 text-center bg-amber-50 border border-amber-200 rounded-xl text-amber-800 text-xs">
                    All clients currently have active assignments. To assign another, edit an existing assignment or register a new client first.
                </div>

                <form v-else @submit.prevent="saveAccountManager" class="space-y-4 text-xs">
                    <!-- Client Selection (Already-assigned clients don't show when creating) -->
                    <div class="space-y-1">
                        <label class="font-bold text-slate-700 flex items-center space-x-1.5">
                            <Building2 class="w-3.5 h-3.5 text-indigo-600" />
                            <span>Client *</span>
                        </label>
                        <SearchableSelect
                            v-model="accountManagerForm.clientId"
                            :options="assignableClientSelectOptions"
                            placeholder="Select client..."
                            :disabled="!!editingAccountManager"
                        />
                    </div>

                    <!-- Assigned Users / Staff List -->
                    <div class="space-y-2">
                        <label class="font-bold text-slate-700 flex items-center space-x-1.5">
                            <Users class="w-3.5 h-3.5 text-indigo-600" />
                            <span>Assigned Users ({{ accountManagerForm.assignedMemberIds.length }}) *</span>
                        </label>

                        <!-- Selected Users Chips Container -->
                        <div v-if="accountManagerForm.assignedMemberIds.length > 0" class="flex flex-wrap gap-1.5 max-h-36 overflow-y-auto p-2 bg-slate-50 border border-slate-200 rounded-xl">
                            <div
                                v-for="memberId in accountManagerForm.assignedMemberIds"
                                :key="memberId"
                                class="inline-flex items-center space-x-1.5 px-2.5 py-1 rounded-lg text-xs bg-white border border-slate-200 shadow-2xs"
                            >
                                <img
                                    :src="getStaffUser(memberId)?.avatar || 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=50&h=50&fit=crop'"
                                    class="w-4 h-4 rounded-full object-cover shrink-0"
                                />
                                <span class="font-bold text-slate-800 text-[11px]">{{ getStaffUser(memberId)?.name || 'User #' + memberId }}</span>
                                <span class="px-1.5 py-0.2 rounded text-[9px] font-semibold bg-slate-100 text-slate-600">
                                    {{ getStaffUser(memberId)?.department || 'Staff' }}
                                </span>
                                <button
                                    type="button"
                                    @click="removeAssignedMember(memberId)"
                                    class="p-0.5 rounded hover:bg-rose-100 text-slate-400 hover:text-rose-700 transition-colors"
                                    title="Remove user"
                                >
                                    <X class="w-3 h-3" />
                                </button>
                            </div>
                        </div>

                        <!-- Multi-select User Dropdown -->
                        <SearchableSelect
                            v-model="accountManagerForm.assignedMemberIds"
                            :options="staffSelectOptions"
                            multiple
                            placeholder="Select users to assign..."
                        />
                    </div>

                    <div class="pt-2 flex items-center justify-end space-x-2 border-t border-slate-100">
                        <button type="button" @click="isAccountManagerModalOpen = false" class="px-3.5 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold transition-colors">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold shadow-xs transition-colors">
                            Save Assignment
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- MODAL FORM 6: PROJECT SETUP                               -->
        <!-- ========================================================= -->
        <div v-if="isProjectModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs">
            <div class="w-full max-w-lg bg-white rounded-2xl border border-slate-200 shadow-2xl p-5 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-sm font-bold text-slate-900 flex items-center space-x-2">
                        <FolderKanban class="w-4 h-4 text-indigo-600" />
                        <span>{{ editingProject ? 'Edit Project' : 'Create Project' }}</span>
                    </h3>
                    <button @click="isProjectModalOpen = false" class="p-1 rounded-lg text-slate-400 hover:text-slate-600">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form @submit.prevent="saveProject" class="space-y-3 text-xs">
                    <div class="grid grid-cols-3 gap-2">
                        <div class="space-y-1">
                            <label class="font-bold text-slate-700">Project Code *</label>
                            <input v-model="projectForm.code" type="text" required placeholder="e.g. PRJ-101" class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl font-mono uppercase" />
                        </div>
                        <div class="col-span-2 space-y-1">
                            <label class="font-bold text-slate-700">Project Name *</label>
                            <input v-model="projectForm.name" type="text" required placeholder="e.g. Mobile Banking v2" class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl font-bold" />
                        </div>
                    </div>

                    <!-- Client Selection with Auto-Fill of Client-Handling Lead -->
                    <div class="grid grid-cols-2 gap-2">
                        <div class="space-y-1">
                            <label class="font-bold text-slate-700">Client *</label>
                            <SearchableSelect
                                v-model="projectForm.clientId"
                                :options="clientSelectOptions"
                                placeholder="Select client..."
                                @change="onProjectClientChange"
                            />
                        </div>
                        <div class="space-y-1">
                            <label class="font-bold text-slate-700">Client Lead</label>
                            <input v-model="projectForm.clientHandlingLead" type="text" readonly class="w-full min-h-[38px] px-3 py-1.5 bg-slate-100 border border-slate-200 rounded-xl text-slate-600 cursor-not-allowed font-medium text-xs" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div class="space-y-1">
                            <label class="font-bold text-slate-700">Team Lead *</label>
                            <SearchableSelect
                                v-model="projectForm.teamLeadId"
                                :options="staffSelectOptions"
                                placeholder="Choose Team Lead..."
                            />
                        </div>
                        <div class="space-y-1">
                            <label class="font-bold text-slate-700">Status</label>
                            <select v-model="projectForm.status" class="w-full min-h-[38px] px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl font-bold">
                                <option v-for="st in projectStatuses" :key="st" :value="st">{{ st }}</option>
                            </select>
                        </div>
                    </div>

                    <!-- Team Members -->
                    <div class="space-y-1">
                        <label class="font-bold text-slate-700">Team Members</label>
                        <SearchableSelect
                            v-model="projectForm.teamMemberIds"
                            :options="staffSelectOptions"
                            multiple
                            placeholder="Select team members..."
                        />
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div class="space-y-1">
                            <label class="font-bold text-slate-700">Start Date</label>
                            <input v-model="projectForm.startDate" type="date" class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl" />
                        </div>
                        <div class="space-y-1">
                            <label class="font-bold text-slate-700">Target Date</label>
                            <input v-model="projectForm.targetDate" type="date" class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl" />
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="font-bold text-slate-700">Description</label>
                        <textarea v-model="projectForm.description" rows="2" placeholder="Optional description..." class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl resize-none"></textarea>
                    </div>

                    <div class="pt-2 flex items-center justify-end space-x-2">
                        <button type="button" @click="isProjectModalOpen = false" class="px-3 py-1.5 rounded-xl bg-slate-100 text-slate-700 font-semibold">Cancel</button>
                        <button type="submit" class="px-4 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold">Save Project</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>
