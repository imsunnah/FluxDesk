<script setup>
import { ref, computed, watch, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
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
    { id: 'account_managers', label: 'Account Managers', icon: UserCog },
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
const initialDepartments = [
    { id: 1, code: 'ENG', name: 'Engineering', hodId: 1, description: 'Software Architecture, Core Development, and API Engineering', status: 'active' },
    { id: 2, code: 'SUP', name: 'Technical Support', hodId: 2, description: 'Tier-1 and Tier-2 Technical Helpdesk and Real-time Incident Response', status: 'active' },
    { id: 3, code: 'CS', name: 'Customer Success', hodId: 3, description: 'Client Retention, Onboarding, and Account Growth Operations', status: 'active' },
    { id: 4, code: 'QA', name: 'Quality Assurance (QA)', hodId: 4, description: 'Automated Testing, Manual QA Audits, and Performance Verification', status: 'active' },
    { id: 5, code: 'DEV', name: 'DevOps & Infrastructure', hodId: 5, description: 'Cloud Systems, CI/CD Pipelines, and Server Reliability', status: 'active' },
    { id: 6, code: 'DES', name: 'Product & Design', hodId: null, description: 'UI/UX Design, Design Systems, and Feature Prototyping', status: 'active' },
    { id: 7, code: 'OPS', name: 'Management & Operations', hodId: 1, description: 'Executive Operations, Compliance, and Organizational Management', status: 'active' },
];

const departmentsList = ref([...initialDepartments]);
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
    if (editingDepartment.value) {
        Object.assign(editingDepartment.value, {
            code: departmentForm.value.code.toUpperCase(),
            name: departmentForm.value.name,
            hodId: departmentForm.value.hodId ? Number(departmentForm.value.hodId) : null,
            description: departmentForm.value.description,
            status: departmentForm.value.status,
        });
        showToast(`Department "${departmentForm.value.name}" updated successfully.`);
    } else {
        const newDept = {
            id: Date.now(),
            code: departmentForm.value.code.toUpperCase(),
            name: departmentForm.value.name,
            hodId: departmentForm.value.hodId ? Number(departmentForm.value.hodId) : null,
            description: departmentForm.value.description,
            status: departmentForm.value.status,
        };
        departmentsList.value.push(newDept);
        showToast(`Department "${newDept.name}" created successfully.`);
    }
    isDepartmentModalOpen.value = false;
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
            departmentsList.value = departmentsList.value.filter(d => d.id !== dept.id);
            showToast(`Department "${dept.name}" deleted.`);
        }
    });
}

// =========================================================
// TAB 2: DESIGNATION SETUP STATE & LOGIC
// =========================================================
const seniorityBands = ['Entry', 'Mid', 'Senior', 'Lead', 'Executive'];

const initialDesignations = [
    { id: 1, title: 'Junior Software Engineer', department: 'Engineering', band: 'Entry', responsibilities: 'Bug fixes, component development, unit test authoring.' },
    { id: 2, title: 'Software Engineer', department: 'Engineering', band: 'Mid', responsibilities: 'Feature delivery, API integration, code review participation.' },
    { id: 3, title: 'Senior Software Engineer', department: 'Engineering', band: 'Senior', responsibilities: 'System design, database optimization, mentorship.' },
    { id: 4, title: 'Lead Architect', department: 'Engineering', band: 'Lead', responsibilities: 'Architecture blueprinting, framework migration, tech evaluation.' },
    { id: 5, title: 'Customer Support Representative', department: 'Technical Support', band: 'Entry', responsibilities: 'Ticket triage, real-time customer messaging, canned replies.' },
    { id: 6, title: 'Senior Support Engineer', department: 'Technical Support', band: 'Senior', responsibilities: 'Incident escalation, root cause analysis, webhook debugging.' },
    { id: 7, title: 'Client Success Manager', department: 'Customer Success', band: 'Mid', responsibilities: 'Client satisfaction surveys, milestone reviews, onboarding.' },
    { id: 8, title: 'QA Automation Engineer', department: 'Quality Assurance (QA)', band: 'Mid', responsibilities: 'Cypress/Playwright test suites, regression test runs.' },
    { id: 9, title: 'DevOps Platform Engineer', department: 'DevOps & Infrastructure', band: 'Senior', responsibilities: 'Kubernetes management, Docker builds, Redis/Postgres caching.' },
    { id: 10, title: 'VP of Engineering', department: 'Management & Operations', band: 'Executive', responsibilities: 'Organizational strategy, tech budget, executive leadership.' },
];

const designationsList = ref([...initialDesignations]);
const designationSearch = ref('');
const isDesignationModalOpen = ref(false);
const editingDesignation = ref(null);
const designationForm = ref({
    title: '',
    department: 'Engineering',
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
        department: deptName || departmentsList.value[0]?.name || 'Engineering',
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
    if (editingDesignation.value) {
        Object.assign(editingDesignation.value, {
            title: designationForm.value.title,
            department: designationForm.value.department,
            band: designationForm.value.band,
            responsibilities: designationForm.value.responsibilities,
        });
        showToast(`Designation "${designationForm.value.title}" updated.`);
    } else {
        const newDesig = {
            id: Date.now(),
            title: designationForm.value.title,
            department: designationForm.value.department,
            band: designationForm.value.band,
            responsibilities: designationForm.value.responsibilities,
        };
        designationsList.value.push(newDesig);
        showToast(`Designation "${newDesig.title}" created.`);
    }
    isDesignationModalOpen.value = false;
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
            designationsList.value = designationsList.value.filter(d => d.id !== desig.id);
            showToast(`Designation "${desig.title}" removed.`);
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
    const defaultDept = departmentsList.value[0]?.name || 'Engineering';
    const firstMatchingDesig = designationsList.value.find(d => d.department === defaultDept)?.title || 'Software Engineer';

    employeeForm.value = {
        firstName: '',
        lastName: '',
        email: '',
        phone: '+1 (555) 019-2834',
        department: defaultDept,
        designation: firstMatchingDesig,
        reportingManagerId: staffEmployees.value[0]?.id || '',
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
        email: user.email,
        phone: user.phone || '+1 (555) 019-2834',
        department: user.department || 'Engineering',
        designation: user.designation || 'Software Engineer',
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

const filteredClients = computed(() => {
    let list = clientUsers.value;
    const q = (clientSearch.value || globalSearch.value).toLowerCase().trim();
    if (q) {
        list = list.filter(u => 
            u.name.toLowerCase().includes(q) ||
            u.email.toLowerCase().includes(q) ||
            (u.member_id || '').toLowerCase().includes(q)
        );
    }
    return list;
});

function getClientActiveProjectsCount(clientId) {
    return props.projects.filter(p => p.client_id === clientId && p.status !== 'completed').length;
}

function openAddClient() {
    editingClient.value = null;
    clientForm.value = {
        orgName: '',
        regNumber: 'REG-' + Math.floor(100000 + Math.random() * 900000),
        industry: 'Enterprise SaaS',
        billingAddress: '100 Innovation Way, Suite 400',
        pocName: '',
        pocEmail: '',
        pocPhone: '+1 (555) 982-1144',
        status: 'active',
    };
    isClientModalOpen.value = true;
}

function openEditClient(client) {
    editingClient.value = client;
    clientForm.value = {
        orgName: client.name,
        regNumber: 'REG-849201',
        industry: 'Enterprise SaaS',
        billingAddress: '100 Innovation Way, Suite 400',
        pocName: client.name,
        pocEmail: client.email,
        pocPhone: '+1 (555) 982-1144',
        status: 'active',
    };
    isClientModalOpen.value = true;
}

function saveClient() {
    if (!clientForm.value.orgName || !clientForm.value.pocEmail) return;
    router.post('/members', {
        name: clientForm.value.orgName,
        email: clientForm.value.pocEmail,
        password: 'password123',
        department: 'Client Accounts',
        designation: 'Client Representative',
        role: 'Client',
        is_customer: true,
        is_team_lead: false,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            isClientModalOpen.value = false;
            showToast(`Client "${clientForm.value.orgName}" saved successfully.`);
        }
    });
}

function attemptDeleteClient(client) {
    const clientProjects = props.projects.filter(p => p.client_id === client.id);
    if (clientProjects.length > 0) {
        openSafeDeleteModal({
            title: 'Cannot Delete Client',
            message: `Client "${client.name}" cannot be deleted because it is linked to ${clientProjects.length} active project(s).`,
            canDelete: false,
            dependencies: clientProjects.map(p => `Project: "${p.name}" (Status: ${p.status})`),
        });
        return;
    }

    openSafeDeleteModal({
        title: 'Delete Client Confirmation',
        message: `Are you sure you want to delete client account "${client.name}"?`,
        canDelete: true,
        dependencies: [],
        onConfirm: () => {
            router.delete(`/members/${client.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    showToast(`Client "${client.name}" deleted.`);
                }
            });
        }
    });
}

// =========================================================
// TAB 5: CLIENT-HANDLING PERSON SETUP (ACCOUNT MAPPING)
// =========================================================
const initialAccountMappings = [
    {
        id: 1,
        clientId: null,
        clientOrg: 'Acme Corporation',
        primaryManagerId: 1,
        backupEscalationId: 2,
        slaLevel: 'VIP',
        assignmentDate: '2026-01-15',
        communicationPreference: 'Dedicated Slack Connect + Weekly Sync',
        notes: 'Executive priority client. All escalations must resolve within 2 hours.'
    },
    {
        id: 2,
        clientId: null,
        clientOrg: 'Nexus Health Systems',
        primaryManagerId: 3,
        backupEscalationId: 1,
        slaLevel: 'Gold',
        assignmentDate: '2026-03-01',
        communicationPreference: 'Email & In-App Workspace Tickets',
        notes: 'HIPAA compliance review required before software updates.'
    },
    {
        id: 3,
        clientId: null,
        clientOrg: 'Horizon Global Logistics',
        primaryManagerId: 2,
        backupEscalationId: 3,
        slaLevel: 'Standard',
        assignmentDate: '2026-04-10',
        communicationPreference: 'Portal & Bi-weekly Status Email',
        notes: 'Standard 24h SLA response time.'
    }
];

const accountManagersList = ref([...initialAccountMappings]);
const accountManagerSearch = ref('');
const isAccountManagerModalOpen = ref(false);
const editingAccountManager = ref(null);
const accountManagerForm = ref({
    clientId: '',
    clientOrgName: '',
    primaryManagerId: '',
    backupEscalationId: '',
    slaLevel: 'Gold',
    communicationPreference: 'Dedicated Slack Connect + Weekly Sync',
    notes: '',
});

const slaLevels = ['Standard', 'Gold', 'VIP'];

const filteredAccountManagers = computed(() => {
    let list = accountManagersList.value;
    const q = (accountManagerSearch.value || globalSearch.value).toLowerCase().trim();
    if (q) {
        list = list.filter(m => 
            m.clientOrg.toLowerCase().includes(q) ||
            m.slaLevel.toLowerCase().includes(q) ||
            (m.communicationPreference || '').toLowerCase().includes(q) ||
            (getStaffName(m.primaryManagerId) || '').toLowerCase().includes(q)
        );
    }
    return list;
});

function getStaffName(userId) {
    if (!userId) return 'Unassigned';
    const user = props.agents.find(u => u.id === Number(userId));
    return user ? user.name : 'Staff Agent #' + userId;
}

function openAddAccountManager() {
    editingAccountManager.value = null;
    const firstClient = clientUsers.value[0];
    const firstStaff = staffEmployees.value[0];
    const secondStaff = staffEmployees.value[1] || firstStaff;

    accountManagerForm.value = {
        clientId: firstClient?.id || '',
        clientOrgName: firstClient?.name || 'General Client',
        primaryManagerId: firstStaff?.id || '',
        backupEscalationId: secondStaff?.id || '',
        slaLevel: 'Gold',
        communicationPreference: 'Dedicated Slack Connect + Weekly Sync',
        notes: 'Client account liaison established for technical delivery.',
    };
    isAccountManagerModalOpen.value = true;
}

function openEditAccountManager(mapping) {
    editingAccountManager.value = mapping;
    accountManagerForm.value = {
        clientId: mapping.clientId || '',
        clientOrgName: mapping.clientOrg,
        primaryManagerId: mapping.primaryManagerId,
        backupEscalationId: mapping.backupEscalationId,
        slaLevel: mapping.slaLevel,
        communicationPreference: mapping.communicationPreference,
        notes: mapping.notes || '',
    };
    isAccountManagerModalOpen.value = true;
}

function onMappingClientSelect() {
    const client = clientUsers.value.find(c => String(c.id) === String(accountManagerForm.value.clientId));
    if (client) {
        accountManagerForm.value.clientOrgName = client.name;
    }
}

function saveAccountManager() {
    if (!accountManagerForm.value.clientOrgName || !accountManagerForm.value.primaryManagerId) return;

    if (editingAccountManager.value) {
        Object.assign(editingAccountManager.value, {
            clientId: accountManagerForm.value.clientId,
            clientOrg: accountManagerForm.value.clientOrgName,
            primaryManagerId: Number(accountManagerForm.value.primaryManagerId),
            backupEscalationId: accountManagerForm.value.backupEscalationId ? Number(accountManagerForm.value.backupEscalationId) : null,
            slaLevel: accountManagerForm.value.slaLevel,
            communicationPreference: accountManagerForm.value.communicationPreference,
            notes: accountManagerForm.value.notes,
        });
        showToast(`Account mapping for "${accountManagerForm.value.clientOrgName}" updated.`);
    } else {
        const newMapping = {
            id: Date.now(),
            clientId: accountManagerForm.value.clientId,
            clientOrg: accountManagerForm.value.clientOrgName,
            primaryManagerId: Number(accountManagerForm.value.primaryManagerId),
            backupEscalationId: accountManagerForm.value.backupEscalationId ? Number(accountManagerForm.value.backupEscalationId) : null,
            slaLevel: accountManagerForm.value.slaLevel,
            assignmentDate: new Date().toISOString().split('T')[0],
            communicationPreference: accountManagerForm.value.communicationPreference,
            notes: accountManagerForm.value.notes,
        };
        accountManagersList.value.push(newMapping);
        showToast(`Account manager linked for "${newMapping.clientOrg}".`);
    }
    isAccountManagerModalOpen.value = false;
}

function deleteAccountManager(mapping) {
    accountManagersList.value = accountManagersList.value.filter(m => m.id !== mapping.id);
    showToast(`Account manager mapping removed.`);
}

function getSlaBadge(sla) {
    switch (sla) {
        case 'VIP': return 'bg-rose-100 text-rose-800 border-rose-300 font-bold';
        case 'Gold': return 'bg-amber-100 text-amber-800 border-amber-300 font-bold';
        case 'Standard': return 'bg-slate-100 text-slate-700 border-slate-200';
        default: return 'bg-slate-100 text-slate-600 border-slate-200';
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
    const code = 'PRJ-' + Math.floor(100 + Math.random() * 900);
    const today = new Date().toISOString().split('T')[0];
    const target = new Date(Date.now() + 86400000 * 45).toISOString().split('T')[0];

    projectForm.value = {
        code,
        name: '',
        clientId: clientUsers.value[0]?.id || '',
        clientHandlingLead: '',
        teamLeadId: defaultLead?.id || '',
        teamMemberIds: [defaultLead?.id].filter(Boolean),
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
        code: 'PRJ-' + proj.id,
        name: proj.name,
        clientId: proj.client_id || '',
        clientHandlingLead: '',
        teamLeadId: staffEmployees.value.find(s => s.is_team_lead)?.id || staffEmployees.value[0]?.id || '',
        teamMemberIds: [],
        startDate: new Date().toISOString().split('T')[0],
        targetDate: new Date(Date.now() + 86400000 * 30).toISOString().split('T')[0],
        status: proj.status ? proj.status.charAt(0).toUpperCase() + proj.status.slice(1) : 'Active',
        description: proj.description || '',
    };
    onProjectClientChange();
    isProjectModalOpen.value = true;
}

function onProjectClientChange() {
    // Auto-fill Client-Handling Lead from Tab 5 Account Manager Mapping!
    const mapping = accountManagersList.value.find(m => {
        if (projectForm.value.clientId) {
            return String(m.clientId) === String(projectForm.value.clientId);
        }
        return false;
    });

    if (mapping) {
        projectForm.value.clientHandlingLead = getStaffName(mapping.primaryManagerId);
    } else {
        const client = clientUsers.value.find(c => String(c.id) === String(projectForm.value.clientId));
        if (client && client.assigned_agent) {
            projectForm.value.clientHandlingLead = client.assigned_agent.name;
        } else {
            projectForm.value.clientHandlingLead = staffEmployees.value[0]?.name || 'Internal Liaison';
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

    if (editingProject.value) {
        router.patch(`/projects/${editingProject.value.id}`, {
            name: projectForm.value.name,
            client_id: projectForm.value.clientId ? Number(projectForm.value.clientId) : null,
            description: projectForm.value.description,
            status: projectForm.value.status.toLowerCase(),
        }, {
            preserveScroll: true,
            onSuccess: () => {
                isProjectModalOpen.value = false;
                showToast(`Project "${projectForm.value.name}" updated successfully.`);
            }
        });
    } else {
        router.post('/projects', {
            name: projectForm.value.name,
            client_id: projectForm.value.clientId ? Number(projectForm.value.clientId) : null,
            description: projectForm.value.description,
            status: projectForm.value.status.toLowerCase(),
        }, {
            preserveScroll: true,
            onSuccess: () => {
                isProjectModalOpen.value = false;
                showToast(`Project "${projectForm.value.name}" created successfully.`);
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
        headers = ['Client Organization', 'Primary Account Manager', 'Backup Escalation', 'SLA Level', 'Assignment Date', 'Communication Preference'];
        rows = filteredAccountManagers.value.map(m => [
            `"${m.clientOrg}"`,
            `"${getStaffName(m.primaryManagerId)}"`,
            `"${getStaffName(m.backupEscalationId)}"`,
            m.slaLevel,
            m.assignmentDate,
            `"${m.communicationPreference}"`
        ]);
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
        <div class="bg-white border-b border-slate-200 px-6 py-4 shadow-2xs">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                <div>
                    <!-- Breadcrumb Navigation -->
                    <div class="flex items-center space-x-2 text-xs text-slate-400 font-medium">
                        <span>Admin Console</span>
                        <ChevronRight class="w-3.5 h-3.5 text-slate-300" />
                        <span class="text-slate-600">Master Setup</span>
                        <ChevronRight class="w-3.5 h-3.5 text-slate-300" />
                        <span class="text-indigo-600 font-bold capitalize">{{ activeTab.replace('_', ' ') }} Setup</span>
                    </div>

                    <div class="flex items-center space-x-2 mt-1">
                        <ShieldCheck class="w-5 h-5 text-indigo-600" />
                        <h1 class="text-base font-bold text-slate-900 tracking-tight">Admin Master Setup Panel</h1>
                        <span class="px-2 py-0.2 rounded-full text-[10px] font-bold bg-indigo-50 text-indigo-700 border border-indigo-200">
                            Enterprise Operations
                        </span>
                    </div>
                </div>

                <!-- Global Action Bar with Quick-Add Shortcuts & CSV Export -->
                <div class="flex items-center space-x-2 flex-wrap gap-y-1.5">
                    <!-- Quick Add Shortcuts -->
                    <button
                        @click="openAddDepartment()"
                        class="px-2.5 py-1.5 bg-slate-50 hover:bg-slate-100 border border-slate-200 text-slate-700 rounded-xl text-xs font-semibold flex items-center space-x-1 shadow-2xs transition-colors"
                        title="Add Department"
                    >
                        <Plus class="w-3 h-3 text-indigo-600" />
                        <span>Dept</span>
                    </button>
                    <button
                        @click="openAddDesignation()"
                        class="px-2.5 py-1.5 bg-slate-50 hover:bg-slate-100 border border-slate-200 text-slate-700 rounded-xl text-xs font-semibold flex items-center space-x-1 shadow-2xs transition-colors"
                        title="Add Designation"
                    >
                        <Plus class="w-3 h-3 text-indigo-600" />
                        <span>Designation</span>
                    </button>
                    <button
                        @click="openAddEmployee()"
                        class="px-2.5 py-1.5 bg-slate-50 hover:bg-slate-100 border border-slate-200 text-slate-700 rounded-xl text-xs font-semibold flex items-center space-x-1 shadow-2xs transition-colors"
                        title="Add Employee"
                    >
                        <Plus class="w-3 h-3 text-indigo-600" />
                        <span>Employee</span>
                    </button>
                    <button
                        @click="openAddClient()"
                        class="px-2.5 py-1.5 bg-slate-50 hover:bg-slate-100 border border-slate-200 text-slate-700 rounded-xl text-xs font-semibold flex items-center space-x-1 shadow-2xs transition-colors"
                        title="Add Client"
                    >
                        <Plus class="w-3 h-3 text-indigo-600" />
                        <span>Client</span>
                    </button>
                    <button
                        @click="openAddAccountManager()"
                        class="px-2.5 py-1.5 bg-slate-50 hover:bg-slate-100 border border-slate-200 text-slate-700 rounded-xl text-xs font-semibold flex items-center space-x-1 shadow-2xs transition-colors"
                        title="Add Account Mapping"
                    >
                        <Plus class="w-3 h-3 text-indigo-600" />
                        <span>Liaison</span>
                    </button>
                    <button
                        @click="openAddProject()"
                        class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-semibold flex items-center space-x-1 shadow-2xs transition-colors"
                        title="Add Project"
                    >
                        <Plus class="w-3.5 h-3.5" />
                        <span>Project</span>
                    </button>

                    <!-- Export Active Tab CSV -->
                    <button
                        @click="exportActiveTabCsv"
                        class="px-2.5 py-1.5 bg-white hover:bg-slate-50 border border-slate-200 text-slate-600 rounded-xl text-xs font-semibold flex items-center space-x-1 shadow-2xs transition-colors"
                        title="Export current table to CSV"
                    >
                        <Download class="w-3.5 h-3.5 text-slate-500" />
                        <span>Export CSV</span>
                    </button>
                </div>
            </div>

            <!-- ========================================================= -->
            <!-- 6 DEDICATED SETUP TABS (RESPONSIVE TOP BAR)               -->
            <!-- ========================================================= -->
            <div class="flex items-center space-x-1 mt-4 pt-3 border-t border-slate-100 overflow-x-auto text-xs font-semibold">
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
                            t.id === 'clients' ? clientUsers.length :
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
                    <div>
                        <h2 class="text-xs font-bold text-slate-900">Organizational Departments Setup</h2>
                        <p class="text-[11px] text-slate-500">Configure corporate departments, codes, descriptions, and assign Heads of Department (HOD).</p>
                    </div>

                    <div class="flex items-center space-x-2 w-full sm:w-auto">
                        <div class="relative w-full sm:w-64">
                            <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-2.5" />
                            <input
                                v-model="departmentSearch"
                                type="text"
                                placeholder="Search departments or code..."
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
                                <th class="px-3 py-3">Assigned Head of Department (HOD)</th>
                                <th class="px-3 py-3 text-center">Active Staff Count</th>
                                <th class="px-3 py-3">Status</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="dept in filteredDepartments" :key="dept.id" class="hover:bg-slate-50/70 transition-colors">
                                <td class="px-4 py-3 font-mono font-bold text-indigo-700">
                                    {{ dept.code }}
                                </td>
                                <td class="px-3 py-3">
                                    <div class="font-bold text-slate-900">{{ dept.name }}</div>
                                    <div class="text-[10px] text-slate-400 line-clamp-1 max-w-sm">{{ dept.description }}</div>
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
                                        {{ getDepartmentStaffCount(dept.name) }} members
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
                    <div>
                        <h2 class="text-xs font-bold text-slate-900">Job Designations & Seniority Bands</h2>
                        <p class="text-[11px] text-slate-500">Define job titles, parent departments, hierarchy bands, and role responsibility briefs.</p>
                    </div>

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
                                <th class="px-4 py-3">Designation Title</th>
                                <th class="px-3 py-3">Assigned Department</th>
                                <th class="px-3 py-3">Seniority Band</th>
                                <th class="px-3 py-3">Responsibilities Brief</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
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
            <!-- TAB 3: EMPLOYEE SETUP                                      -->
            <!-- --------------------------------------------------------- -->
            <div v-if="activeTab === 'employees'" class="space-y-4">
                <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-2xs flex flex-col sm:flex-row items-center justify-between gap-3">
                    <div>
                        <h2 class="text-xs font-bold text-slate-900">Employees Directory & Role Setup</h2>
                        <p class="text-[11px] text-slate-500">Manage internal personnel, dynamic department-designation linkage, and Team Lead status.</p>
                    </div>

                    <div class="flex items-center space-x-2 w-full sm:w-auto">
                        <div class="relative w-full sm:w-64">
                            <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-2.5" />
                            <input
                                v-model="employeeSearch"
                                type="text"
                                placeholder="Search employees by name, email..."
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
                                <th class="px-4 py-3">Avatar + Full Name</th>
                                <th class="px-3 py-3">Employee ID</th>
                                <th class="px-3 py-3">Contact Email</th>
                                <th class="px-3 py-3">Department</th>
                                <th class="px-3 py-3">Designation</th>
                                <th class="px-3 py-3">Role Tag</th>
                                <th class="px-3 py-3 text-center">Status</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
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
                    <div>
                        <h2 class="text-xs font-bold text-slate-900">Client Organizations Directory</h2>
                        <p class="text-[11px] text-slate-500">Corporate client registration, primary points of contact, and business domains.</p>
                    </div>

                    <div class="flex items-center space-x-2 w-full sm:w-auto">
                        <div class="relative w-full sm:w-64">
                            <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-2.5" />
                            <input
                                v-model="clientSearch"
                                type="text"
                                placeholder="Search client organization..."
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
                                <th class="px-4 py-3">Client Organization</th>
                                <th class="px-3 py-3">Primary POC Name & Email</th>
                                <th class="px-3 py-3">Domain / Industry</th>
                                <th class="px-3 py-3 text-center">Active Projects</th>
                                <th class="px-3 py-3 text-center">Status</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="client in filteredClients" :key="client.id" class="hover:bg-slate-50/70 transition-colors">
                                <td class="px-4 py-3">
                                    <div class="flex items-center space-x-2.5">
                                        <img :src="client.avatar || 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=50&h=50&fit=crop'" class="w-7 h-7 rounded-full object-cover" />
                                        <div>
                                            <div class="font-bold text-slate-900">{{ client.name }}</div>
                                            <div class="text-[10px] font-mono text-slate-400">{{ client.member_id || 'CUST-' + client.id }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-3 py-3">
                                    <div class="font-medium text-slate-800">{{ client.name }}</div>
                                    <div class="text-[11px] text-slate-500">{{ client.email }}</div>
                                </td>
                                <td class="px-3 py-3">
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200">
                                        Enterprise SaaS
                                    </span>
                                </td>
                                <td class="px-3 py-3 text-center font-mono font-bold text-indigo-700">
                                    {{ getClientActiveProjectsCount(client.id) }} active
                                </td>
                                <td class="px-3 py-3 text-center">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        Active
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
            <!-- TAB 5: CLIENT-HANDLING PERSON SETUP (ACCOUNT MAPPING)      -->
            <!-- --------------------------------------------------------- -->
            <div v-if="activeTab === 'account_managers'" class="space-y-4">
                <div class="bg-white border border-slate-200 rounded-2xl p-4 shadow-2xs flex flex-col sm:flex-row items-center justify-between gap-3">
                    <div>
                        <h2 class="text-xs font-bold text-slate-900">Client-Handling Person Setup (Account Mapping)</h2>
                        <p class="text-[11px] text-slate-500">Link clients with dedicated internal liaisons, escalation leads, and SLA service agreements.</p>
                    </div>

                    <div class="flex items-center space-x-2 w-full sm:w-auto">
                        <div class="relative w-full sm:w-64">
                            <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-2.5" />
                            <input
                                v-model="accountManagerSearch"
                                type="text"
                                placeholder="Search account mappings..."
                                class="w-full pl-9 pr-3 py-1.5 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:outline-none focus:bg-white focus:border-indigo-600"
                            />
                        </div>
                        <button
                            @click="openAddAccountManager()"
                            class="px-3.5 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-semibold flex items-center space-x-1 whitespace-nowrap shadow-2xs"
                        >
                            <UserCog class="w-3.5 h-3.5" />
                            <span>Link Account Manager</span>
                        </button>
                    </div>
                </div>

                <!-- Account Mapping Data Table -->
                <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden shadow-2xs">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-50 border-b border-slate-200 text-[10px] uppercase font-bold text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Client Organization</th>
                                <th class="px-3 py-3">Primary Account Manager</th>
                                <th class="px-3 py-3">Backup / Escalation Contact</th>
                                <th class="px-3 py-3">SLA Level</th>
                                <th class="px-3 py-3">Assignment Date</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="mapping in filteredAccountManagers" :key="mapping.id" class="hover:bg-slate-50/70 transition-colors">
                                <td class="px-4 py-3 font-bold text-slate-900">
                                    {{ mapping.clientOrg }}
                                </td>
                                <td class="px-3 py-3">
                                    <div class="flex items-center space-x-1.5 font-semibold text-indigo-700">
                                        <UserCheck class="w-3.5 h-3.5 text-indigo-600" />
                                        <span>{{ getStaffName(mapping.primaryManagerId) }}</span>
                                    </div>
                                    <div class="text-[10px] text-slate-400 mt-0.5 truncate max-w-xs">{{ mapping.communicationPreference }}</div>
                                </td>
                                <td class="px-3 py-3 text-slate-700 font-medium">
                                    {{ getStaffName(mapping.backupEscalationId) }}
                                </td>
                                <td class="px-3 py-3">
                                    <span :class="['px-2 py-0.5 rounded-full text-[10px] border', getSlaBadge(mapping.slaLevel)]">
                                        {{ mapping.slaLevel }} SLA
                                    </span>
                                </td>
                                <td class="px-3 py-3 font-mono text-slate-600">
                                    {{ mapping.assignmentDate }}
                                </td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <button
                                        @click="openEditAccountManager(mapping)"
                                        class="p-1 text-slate-400 hover:text-indigo-600 rounded transition-colors"
                                        title="Edit Mapping"
                                    >
                                        <Edit3 class="w-3.5 h-3.5" />
                                    </button>
                                    <button
                                        @click="deleteAccountManager(mapping)"
                                        class="p-1 text-slate-400 hover:text-rose-600 rounded transition-colors ml-1"
                                        title="Remove Mapping"
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
                    <div>
                        <h2 class="text-xs font-bold text-slate-900">Project Master Setup & Allocation</h2>
                        <p class="text-[11px] text-slate-500">Configure client projects, team leads, auto-assigned liaisons, deadlines, and members.</p>
                    </div>

                    <div class="flex items-center space-x-2 w-full sm:w-auto">
                        <div class="relative w-full sm:w-64">
                            <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-2.5" />
                            <input
                                v-model="projectSearch"
                                type="text"
                                placeholder="Search project name or client..."
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
                                <th class="px-4 py-3">Project Code</th>
                                <th class="px-3 py-3">Project Name</th>
                                <th class="px-3 py-3">Client Name</th>
                                <th class="px-3 py-3">Client-Handling Lead</th>
                                <th class="px-3 py-3 text-center">Status</th>
                                <th class="px-4 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <tr v-for="proj in filteredProjects" :key="proj.id" class="hover:bg-slate-50/70 transition-colors">
                                <td class="px-4 py-3 font-mono font-bold text-indigo-700">
                                    PRJ-{{ proj.id }}
                                </td>
                                <td class="px-3 py-3">
                                    <div class="font-bold text-slate-900">{{ proj.name }}</div>
                                    <div class="text-[10px] text-slate-400 line-clamp-1 max-w-sm">{{ proj.description }}</div>
                                </td>
                                <td class="px-3 py-3 font-medium text-slate-800">
                                    {{ proj.client?.name || 'Internal Project' }}
                                </td>
                                <td class="px-3 py-3 text-indigo-700 font-semibold">
                                    {{ proj.client?.assigned_agent?.name || 'Sarah Jenkins (CS Lead)' }}
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

                    <!-- Dynamic Department dropdown filters available Designations -->
                    <div class="grid grid-cols-2 gap-2">
                        <div class="space-y-1">
                            <label class="font-bold text-slate-700">Dynamic Department *</label>
                            <select v-model="employeeForm.department" class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl">
                                <option v-for="dept in departmentsList" :key="dept.id" :value="dept.name">{{ dept.name }}</option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label class="font-bold text-slate-700">Designation (Filtered) *</label>
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
                            <label class="font-bold text-slate-700">Direct Reporting Manager</label>
                            <select v-model="employeeForm.reportingManagerId" class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl">
                                <option value="">No Direct Manager</option>
                                <option v-for="mgr in staffEmployees" :key="mgr.id" :value="mgr.id">{{ mgr.name }} ({{ mgr.role }})</option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label class="font-bold text-slate-700">System Role Toggle</label>
                            <select v-model="employeeForm.role" class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl font-semibold">
                                <option value="Employee">Employee (Standard Staff)</option>
                                <option value="Team Lead">Team Lead (Can Assign & Lock Delays)</option>
                                <option value="Admin">System Administrator</option>
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
                            <input v-model="clientForm.regNumber" type="text" placeholder="REG-104928" class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl font-mono" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div class="space-y-1">
                            <label class="font-bold text-slate-700">Industry / Domain</label>
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
                        <input v-model="clientForm.billingAddress" type="text" placeholder="100 Innovation Way, Suite 400" class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl" />
                    </div>

                    <div class="pt-2 flex items-center justify-end space-x-2">
                        <button type="button" @click="isClientModalOpen = false" class="px-3 py-1.5 rounded-xl bg-slate-100 text-slate-700 font-semibold">Cancel</button>
                        <button type="submit" class="px-4 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold">Save Client</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ========================================================= -->
        <!-- MODAL FORM 5: CLIENT-HANDLING PERSON SETUP                -->
        <!-- ========================================================= -->
        <div v-if="isAccountManagerModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs">
            <div class="w-full max-w-lg bg-white rounded-2xl border border-slate-200 shadow-2xl p-5 space-y-4">
                <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                    <h3 class="text-sm font-bold text-slate-900 flex items-center space-x-2">
                        <UserCog class="w-4 h-4 text-indigo-600" />
                        <span>Link Client-Handling Person (Account Mapping)</span>
                    </h3>
                    <button @click="isAccountManagerModalOpen = false" class="p-1 rounded-lg text-slate-400 hover:text-slate-600">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form @submit.prevent="saveAccountManager" class="space-y-3 text-xs">
                    <div class="space-y-1">
                        <label class="font-bold text-slate-700">Client Organization *</label>
                        <select v-model="accountManagerForm.clientId" @change="onMappingClientSelect" class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl">
                            <option v-for="c in clientUsers" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div class="space-y-1">
                            <label class="font-bold text-slate-700">Primary Handling Person *</label>
                            <select v-model="accountManagerForm.primaryManagerId" class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl font-semibold text-indigo-700">
                                <option v-for="emp in staffEmployees" :key="emp.id" :value="emp.id">
                                    {{ emp.name }} ({{ emp.designation || emp.role }})
                                </option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label class="font-bold text-slate-700">Backup / Escalation Contact</label>
                            <select v-model="accountManagerForm.backupEscalationId" class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl">
                                <option value="">No Escalation Contact</option>
                                <option v-for="emp in staffEmployees" :key="emp.id" :value="emp.id">
                                    {{ emp.name }} ({{ emp.role }})
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div class="space-y-1">
                            <label class="font-bold text-slate-700">SLA Level</label>
                            <select v-model="accountManagerForm.slaLevel" class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl font-bold">
                                <option v-for="sla in slaLevels" :key="sla" :value="sla">{{ sla }} SLA</option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label class="font-bold text-slate-700">Communication Preference</label>
                            <input v-model="accountManagerForm.communicationPreference" type="text" placeholder="e.g. Dedicated Slack + Weekly Call" class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl" />
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="font-bold text-slate-700">Operational Notes</label>
                        <textarea v-model="accountManagerForm.notes" rows="2" placeholder="Special SLAs, contract notes, escalation rules..." class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl resize-none"></textarea>
                    </div>

                    <div class="pt-2 flex items-center justify-end space-x-2">
                        <button type="button" @click="isAccountManagerModalOpen = false" class="px-3 py-1.5 rounded-xl bg-slate-100 text-slate-700 font-semibold">Cancel</button>
                        <button type="submit" class="px-4 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-700 text-white font-semibold">Save Mapping</button>
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
                        <span>{{ editingProject ? 'Edit Project Master' : 'Create New Project' }}</span>
                    </h3>
                    <button @click="isProjectModalOpen = false" class="p-1 rounded-lg text-slate-400 hover:text-slate-600">
                        <X class="w-4 h-4" />
                    </button>
                </div>

                <form @submit.prevent="saveProject" class="space-y-3 text-xs">
                    <div class="grid grid-cols-3 gap-2">
                        <div class="space-y-1">
                            <label class="font-bold text-slate-700">Project Code *</label>
                            <input v-model="projectForm.code" type="text" required placeholder="PRJ-101" class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl font-mono uppercase" />
                        </div>
                        <div class="col-span-2 space-y-1">
                            <label class="font-bold text-slate-700">Project Name *</label>
                            <input v-model="projectForm.name" type="text" required placeholder="e.g. Mobile Banking v2" class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl font-bold" />
                        </div>
                    </div>

                    <!-- Client Selection with Auto-Fill of Client-Handling Lead -->
                    <div class="grid grid-cols-2 gap-2">
                        <div class="space-y-1">
                            <label class="font-bold text-slate-700">Client Dropdown *</label>
                            <select v-model="projectForm.clientId" @change="onProjectClientChange" class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl">
                                <option value="">No Client (Internal Project)</option>
                                <option v-for="c in clientUsers" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label class="font-bold text-slate-700">Client-Handling Lead (Auto-filled)</label>
                            <input v-model="projectForm.clientHandlingLead" type="text" readonly class="w-full px-3 py-1.5 bg-slate-100 border border-slate-200 rounded-xl text-slate-600 cursor-not-allowed font-medium" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div class="space-y-1">
                            <label class="font-bold text-slate-700">Assign Team Lead *</label>
                            <select v-model="projectForm.teamLeadId" class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl font-semibold text-indigo-700">
                                <option v-for="lead in staffEmployees" :key="lead.id" :value="lead.id">
                                    {{ lead.name }} ({{ lead.is_team_lead ? 'Team Lead' : lead.role }})
                                </option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label class="font-bold text-slate-700">Status</label>
                            <select v-model="projectForm.status" class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl font-bold">
                                <option v-for="st in projectStatuses" :key="st" :value="st">{{ st }}</option>
                            </select>
                        </div>
                    </div>

                    <!-- Multi-select Team Members -->
                    <div class="space-y-1">
                        <label class="font-bold text-slate-700">Multi-select Team Members</label>
                        <div class="max-h-24 overflow-y-auto p-2 bg-slate-50 rounded-xl border border-slate-200 space-y-1">
                            <div
                                v-for="emp in staffEmployees"
                                :key="emp.id"
                                @click="toggleProjectTeamMember(emp.id)"
                                :class="[
                                    'flex items-center justify-between p-1.5 rounded-lg cursor-pointer text-xs transition-colors',
                                    projectForm.teamMemberIds.includes(emp.id) ? 'bg-indigo-50 border border-indigo-200 font-semibold text-indigo-900' : 'hover:bg-slate-100 text-slate-700'
                                ]"
                            >
                                <div class="flex items-center space-x-1.5">
                                    <img :src="emp.avatar" class="w-4 h-4 rounded-full object-cover" />
                                    <span>{{ emp.name }}</span>
                                </div>
                                <span v-if="projectForm.teamMemberIds.includes(emp.id)" class="text-indigo-600 font-bold">✓</span>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2">
                        <div class="space-y-1">
                            <label class="font-bold text-slate-700">Start Date</label>
                            <input v-model="projectForm.startDate" type="date" class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl" />
                        </div>
                        <div class="space-y-1">
                            <label class="font-bold text-slate-700">Target Completion Date</label>
                            <input v-model="projectForm.targetDate" type="date" class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl" />
                        </div>
                    </div>

                    <div class="space-y-1">
                        <label class="font-bold text-slate-700">Description</label>
                        <textarea v-model="projectForm.description" rows="2" placeholder="Project goals and scope..." class="w-full px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-xl resize-none"></textarea>
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
