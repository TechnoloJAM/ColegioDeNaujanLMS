<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import { Head, router, useForm, Link } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import * as XLSX from 'xlsx';
import { Users, Building2, Plus, ShieldAlert, Key, UserCog, Trash2, X, Search, Filter, BookOpen, Calendar, Download } from 'lucide-vue-next';

const props = defineProps({
    users: [Array, Object],
    departments: Array
});

// Normalize the data whether the backend sends an array or a paginated object
const usersList = computed(() => {
    return Array.isArray(props.users) ? props.users : (props.users.data || []);
});

const mainTab = ref('users'); 
const searchQuery = ref('');
const userRoleTab = ref('student');
const archiveSubTab = ref('student'); 

const filterProgram = ref('all');
const filterYear = ref('all');
const sortBy = ref('newest');

const isCreateModalOpen = ref(false);
const isCreateDeptModalOpen = ref(false);
const isDeleteDeptModalOpen = ref(false);
const selectedDepartment = ref(null);
const selectedIds = ref([]);
const isUserDetailsModalOpen = ref(false);
const selectedUserDetails = ref(null);

const isRoleModalOpen = ref(false);
const isResetPasswordModalOpen = ref(false);
const isImpersonateModalOpen = ref(false);
const isBulkSuspendModalOpen = ref(false);
const isBulkDeleteModalOpen = ref(false);

const form = useForm({ role: 'student', name: '', email: '', department_id: '', school_id: '', program: '', contact_number: '', password: '' });
const deptForm = useForm({ name: '' });
const deleteDeptForm = useForm({ password: '' });
const roleForm = useForm({ role: '', department_id: '', password: '' }); 
const resetPasswordForm = useForm({ password: '', admin_password: '' });
const impersonateForm = useForm({ user_id: null, password: '' });
const bulkSuspendForm = useForm({ action: 'suspend', reason: '', password: '', user_ids: [] });
const bulkDeleteForm = useForm({ password: '', user_ids: [] });

const selectedUserForRole = ref(null);
const selectedUserForPassword = ref(null);
const selectedUserForImpersonate = ref(null);

// ==========================================
// Client-Side Pagination Logic
// ==========================================
const currentPage = ref(1);
const itemsPerPage = 15;

watch(() => form.role, (newRole) => {
    if (newRole === 'student' || newRole === 'admin') form.department_id = '';
});

watch(() => roleForm.role, (newRole) => {
    if (newRole === 'student' || newRole === 'admin') roleForm.department_id = '';
});

const baseTabUsers = computed(() => {
    return usersList.value.filter(user => {
        if (userRoleTab.value === 'archive') return user.status === 'suspended' && user.role === archiveSubTab.value;
        return user.status === 'active' && user.role === userRoleTab.value;
    });
});

const availablePrograms = computed(() => {
    const progs = new Set();
    baseTabUsers.value.forEach(u => { if (u.program) progs.add(u.program); });
    return Array.from(progs).sort();
});

const availableYears = computed(() => {
    const yrs = new Set();
    baseTabUsers.value.forEach(u => {
        if (u.school_id && u.school_id.includes('-')) yrs.add(u.school_id.split('-')[0]);
    });
    return Array.from(yrs).sort((a, b) => b - a);
});

const filteredUsers = computed(() => {
    let result = baseTabUsers.value.filter(user => {
        const q = searchQuery.value.toLowerCase();
        if (q) {
            const searchMatch = user.name.toLowerCase().includes(q) || 
                                user.email.toLowerCase().includes(q) || 
                                (user.school_id && user.school_id.toLowerCase().includes(q));
            if (!searchMatch) return false;
        }
        if (filterProgram.value !== 'all' && user.program !== filterProgram.value) return false;
        if (filterYear.value !== 'all' && (!user.school_id || !user.school_id.startsWith(filterYear.value + '-'))) return false;
        return true;
    });

    return result.sort((a, b) => {
        if (sortBy.value === 'newest') return new Date(b.created_at || 0).getTime() - new Date(a.created_at || 0).getTime();
        if (sortBy.value === 'oldest') return new Date(a.created_at || 0).getTime() - new Date(b.created_at || 0).getTime();
        if (sortBy.value === 'name_asc') return a.name.localeCompare(b.name);
        if (sortBy.value === 'name_desc') return b.name.localeCompare(a.name);
        return 0;
    });
});

// Sliced array for current page
const paginatedUsers = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage;
    return filteredUsers.value.slice(start, start + itemsPerPage);
});

const totalPages = computed(() => Math.ceil(filteredUsers.value.length / itemsPerPage));

// Reset page and selection when navigating tabs/filters
watch([userRoleTab, mainTab, searchQuery, filterProgram, filterYear, sortBy], () => { 
    currentPage.value = 1; 
    selectedIds.value = []; 
});
watch(archiveSubTab, () => { selectedIds.value = []; currentPage.value = 1; });

const toggleSelection = (id) => {
    if (selectedIds.value.includes(id)) selectedIds.value = selectedIds.value.filter(i => i !== id);
    else selectedIds.value.push(id);
};

const isAllSelected = computed(() => {
    if (paginatedUsers.value.length === 0) return false;
    return selectedIds.value.length === paginatedUsers.value.length;
});

const toggleAll = () => {
    if (isAllSelected.value) selectedIds.value = [];
    else selectedIds.value = paginatedUsers.value.map(u => u.id);
};

const openUserDetails = (user) => { selectedUserDetails.value = user; isUserDetailsModalOpen.value = true; };

const generateString = () => {
    const chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*";
    let pwd = "";
    for (let i = 0; i < 10; i++) pwd += chars.charAt(Math.floor(Math.random() * chars.length));
    return pwd;
};

const generatePassword = () => { form.password = generateString(); };
const generateResetPassword = () => { resetPasswordForm.password = generateString(); };

const generateSchoolId = () => {
    const year = new Date().getFullYear();
    const randomNums = Math.floor(10000 + Math.random() * 90000); 
    form.school_id = `${year}-${randomNums}`;
};

const submitUser = () => {
    form.post(route('admin.users.store'), { preserveScroll: true, onSuccess: () => { isCreateModalOpen.value = false; form.reset(); alert('Account created and verified!'); } });
};

const submitDept = () => {
    deptForm.post(route('admin.departments.store'), { preserveScroll: true, onSuccess: () => { isCreateDeptModalOpen.value = false; deptForm.reset(); } });
};

const openDeleteDept = (dept) => { selectedDepartment.value = dept; deleteDeptForm.reset(); isDeleteDeptModalOpen.value = true; };
const submitDeleteDept = () => {
    deleteDeptForm.delete(route('admin.departments.destroy', selectedDepartment.value.id), { preserveScroll: true, onSuccess: () => { isDeleteDeptModalOpen.value = false; selectedDepartment.value = null; } });
};

const openImpersonateModal = (user) => { selectedUserForImpersonate.value = user; impersonateForm.user_id = user.id; impersonateForm.password = ''; impersonateForm.clearErrors(); isImpersonateModalOpen.value = true; };
const submitImpersonate = () => { impersonateForm.post(route('admin.users.impersonate', impersonateForm.user_id), { onSuccess: () => isImpersonateModalOpen.value = false }); };

const openBulkSuspend = (action, singleId = null) => { bulkSuspendForm.action = action; bulkSuspendForm.user_ids = singleId ? [singleId] : selectedIds.value; bulkSuspendForm.reason = ''; bulkSuspendForm.password = ''; bulkSuspendForm.clearErrors(); isBulkSuspendModalOpen.value = true; };
const submitBulkSuspend = () => { bulkSuspendForm.post(route('admin.users.bulk-toggle-status'), { preserveScroll: true, onSuccess: () => { isBulkSuspendModalOpen.value = false; selectedIds.value = []; } }); };

const openBulkDelete = (singleId = null) => { bulkDeleteForm.user_ids = singleId ? [singleId] : selectedIds.value; bulkDeleteForm.password = ''; bulkDeleteForm.clearErrors(); isBulkDeleteModalOpen.value = true; };
const submitBulkDelete = () => { bulkDeleteForm.post(route('admin.users.bulk-destroy'), { preserveScroll: true, onSuccess: () => { isBulkDeleteModalOpen.value = false; selectedIds.value = []; } }); };

const openRoleModal = (user) => {
    selectedUserForRole.value = user;
    roleForm.role = user.role;
    roleForm.department_id = user.department_id || ''; 
    roleForm.password = ''; 
    roleForm.clearErrors();
    isRoleModalOpen.value = true;
};
const submitRole = () => { roleForm.patch(route('admin.users.update-role', selectedUserForRole.value.id), { preserveScroll: true, onSuccess: () => { isRoleModalOpen.value = false; selectedUserForRole.value = null; } }); };

const openResetPasswordModal = (user) => { selectedUserForPassword.value = user; resetPasswordForm.password = ''; resetPasswordForm.admin_password = ''; resetPasswordForm.clearErrors(); isResetPasswordModalOpen.value = true; };
const submitResetPassword = () => { resetPasswordForm.patch(route('admin.users.reset-password', selectedUserForPassword.value.id), { preserveScroll: true, onSuccess: () => { isResetPasswordModalOpen.value = false; selectedUserForPassword.value = null; alert('Password reset successfully.'); } }); };

const exportToExcel = () => {
    const wb = XLSX.utils.book_new();
    const exportCategories = [
        { id: 'student', name: 'Students', title: 'ACTIVE STUDENT ACCOUNTS' },
        { id: 'teacher', name: 'Teachers', title: 'ACTIVE TEACHER ACCOUNTS' },
        { id: 'dean', name: 'Deans', title: 'ACTIVE DEAN ACCOUNTS' },
        { id: 'admin', name: 'Admins', title: 'SYSTEM ADMINISTRATORS' },
        { id: 'archive', name: 'Suspended', title: 'SUSPENDED ACCOUNTS' }
    ];

    exportCategories.forEach(category => {
        const sheetUsers = usersList.value.filter(user => {
            if (category.id === 'archive') return user.status === 'suspended';
            return user.status === 'active' && user.role === category.id;
        });

        const wsData = [
            ['COLEGIO DE NAUJAN - LMS SYSTEM REPORT', '', '', '', '', '', ''],
            [], 
            ['Report Generated:', String(new Date().toLocaleString()), '', '', '', '', ''],
            ['Category Scope:', String(category.title), '', '', '', '', ''],
            ['Total Records:', String(sheetUsers.length), '', '', '', '', ''],
            [], 
            [
                'ID / Employee No.', 'Full Name', 'Email Address', 
                'Program/Department', 'System Role', 'Status', 'Date Registered'
            ]
        ];

        if (sheetUsers.length > 0) {
            sheetUsers.forEach(user => {
                wsData.push([
                    String(user.school_id || 'N/A'),
                    String(user.name),
                    String(user.email),
                    String(user.department ? user.department.name : (user.program || 'N/A')),
                    String(user.role.toUpperCase()),
                    String(user.status.toUpperCase()),
                    String(new Date(user.created_at).toLocaleDateString())
                ]);
            });
        } else {
            wsData.push(['No records found for this category.']);
        }

        const ws = XLSX.utils.aoa_to_sheet(wsData);
        ws['!merges'] = [ { s: { r: 0, c: 0 }, e: { r: 0, c: 6 } } ];
        ws['!cols'] = [ { wch: 18 }, { wch: 30 }, { wch: 30 }, { wch: 35 }, { wch: 15 }, { wch: 15 }, { wch: 20 } ];
        XLSX.utils.book_append_sheet(wb, ws, category.name);
    });

    XLSX.writeFile(wb, `CDN_LMS_Master_User_List_${new Date().toISOString().slice(0,10)}.xlsx`);
};

const roleTabs = [
    { id: 'student', name: 'Students' },
    { id: 'teacher', name: 'Teachers' },
    { id: 'dean', name: 'Deans' },
    { id: 'admin', name: 'Admins' },
    { id: 'archive', name: 'Archive' }
];

const subTabs = [
    { id: 'student', name: 'Suspended Students' },
    { id: 'teacher', name: 'Suspended Teachers' },
    { id: 'dean', name: 'Suspended Deans' },
    { id: 'admin', name: 'Suspended Admins' }
];

const inputClass = "w-full rounded-md bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:ring-2 focus:ring-blue-500 focus:border-transparent py-1.5 px-3 text-xs shadow-sm transition-colors duration-200";
</script>

<template>
    <Head title="System Directory" />
    <AuthenticatedLayout>

        <!-- MOBILE FLOATING FAB: Placed perfectly above chat widget, with black/white borders -->
        <div class="md:hidden fixed bottom-[150px] right-4 z-[999] flex flex-col gap-2 items-center pointer-events-none">
            <button v-if="mainTab === 'users'" @click="isCreateModalOpen = true" class="pointer-events-auto flex items-center justify-center w-10 h-10 bg-white dark:bg-slate-800 text-blue-600 dark:text-blue-500 rounded-full border border-black dark:border-white shadow-[0_8px_30px_rgba(0,0,0,0.15)] transition-transform active:scale-95">
                <Plus class="w-5 h-5" />
            </button>
            <button v-if="mainTab === 'departments'" @click="isCreateDeptModalOpen = true" class="pointer-events-auto flex items-center justify-center w-10 h-10 bg-white dark:bg-slate-800 text-purple-600 dark:text-purple-400 rounded-full border border-black dark:border-white shadow-[0_8px_30px_rgba(0,0,0,0.15)] transition-transform active:scale-95">
                <Plus class="w-5 h-5" />
            </button>
            <button @click="exportToExcel" class="pointer-events-auto flex items-center justify-center w-10 h-10 bg-white dark:bg-slate-800 text-emerald-600 dark:text-emerald-400 rounded-full border border-black dark:border-white shadow-[0_8px_30px_rgba(0,0,0,0.15)] transition-transform active:scale-95">
                <Download class="w-4 h-4" />
            </button>
        </div>

        <div class="mb-3 flex justify-between items-center max-w-7xl mx-auto px-3 sm:px-6">
             <div class="flex items-center gap-3">
                 <div>
                    <h1 class="text-lg sm:text-xl font-black text-slate-900 dark:text-white leading-tight tracking-tight">System Directory</h1>
                    <p class="text-slate-500 dark:text-slate-400 text-[9px] sm:text-[10px] uppercase font-bold tracking-wider">Manage accounts & departments</p>
                 </div>
             </div>
        </div>

        <div class="max-w-7xl mx-auto px-3 sm:px-6 flex flex-col md:flex-row gap-3 md:gap-5 items-start relative">
            
            <!-- DESKTOP FLOATING ACTIONS SIDEBAR -->
            <aside class="hidden md:flex w-12 shrink-0 flex-col gap-3 sticky top-6 z-10 order-1">
                <button v-if="mainTab === 'users'" @click="isCreateModalOpen = true" class="group relative flex items-center justify-center w-12 h-12 bg-white dark:bg-slate-800 rounded-full border-2 border-slate-200 dark:border-slate-700 text-blue-600 hover:border-blue-600 hover:bg-blue-50 dark:hover:bg-slate-700 transition shadow-sm focus:outline-none shrink-0">
                    <Plus class="w-5 h-5" />
                    <span class="absolute left-full ml-3 px-2 py-1 bg-slate-800 text-white text-[9px] font-bold rounded opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none whitespace-nowrap shadow-lg">New User</span>
                </button>
                <button v-if="mainTab === 'departments'" @click="isCreateDeptModalOpen = true" class="group relative flex items-center justify-center w-12 h-12 bg-white dark:bg-slate-800 rounded-full border-2 border-slate-200 dark:border-slate-700 text-purple-600 hover:border-purple-600 hover:bg-purple-50 dark:hover:bg-slate-700 transition shadow-sm focus:outline-none shrink-0">
                    <Plus class="w-5 h-5" />
                    <span class="absolute left-full ml-3 px-2 py-1 bg-slate-800 text-white text-[9px] font-bold rounded opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none whitespace-nowrap shadow-lg">Add Dept</span>
                </button>
                <button @click="exportToExcel" class="group relative flex items-center justify-center w-12 h-12 bg-white dark:bg-slate-800 rounded-full border-2 border-slate-200 dark:border-slate-700 text-emerald-600 hover:border-emerald-600 hover:bg-emerald-50 dark:hover:bg-slate-700 transition shadow-sm focus:outline-none shrink-0">
                    <Download class="w-5 h-5" />
                    <span class="absolute left-full ml-3 px-2 py-1 bg-slate-800 text-white text-[9px] font-bold rounded opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none whitespace-nowrap shadow-lg">Export Excel</span>
                </button>
            </aside>

            <div class="flex-1 min-w-0 w-full order-2 pb-24 md:pb-6">
                
                <div class="flex gap-4 border-b border-slate-200 dark:border-slate-700 mb-4 overflow-x-auto no-scrollbar pb-1">
                    <button @click="mainTab = 'users'" class="pb-1.5 text-sm sm:text-base font-black border-b-2 transition-colors flex items-center gap-1.5 whitespace-nowrap uppercase tracking-widest" :class="mainTab === 'users' ? 'border-blue-600 text-blue-600 dark:text-blue-400 dark:border-blue-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300'">
                        <Users class="w-4 h-4" /> User Directory
                    </button>
                    <button @click="mainTab = 'departments'" class="pb-1.5 text-sm sm:text-base font-black border-b-2 transition-colors flex items-center gap-1.5 whitespace-nowrap uppercase tracking-widest" :class="mainTab === 'departments' ? 'border-purple-600 text-purple-600 dark:text-purple-400 dark:border-purple-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300'">
                        <Building2 class="w-4 h-4" /> Departments
                    </button>
                </div>

                <!-- ========================================== -->
                <!-- VIEW 1: USER DIRECTORY                     -->
                <!-- ========================================== -->
                <div v-if="mainTab === 'users'" class="animate-in fade-in slide-in-from-bottom-2 duration-300">
                    
                    <!-- RESPONSIVE SEARCH & FILTER CARD -->
                    <div class="bg-white dark:bg-slate-800 p-1.5 sm:p-2.5 rounded-lg sm:rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm mb-4 flex flex-row sm:flex-col lg:flex-row gap-1.5 sm:gap-2.5 items-center sm:items-stretch lg:items-center min-w-0">
                        
                        <!-- Search (Common to both) -->
                        <div class="relative flex-1 min-w-[120px] sm:min-w-[200px]">
                            <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none">
                                <Search class="h-3.5 w-3.5 text-slate-400" />
                            </div>
                            <input v-model="searchQuery" type="text" placeholder="Search accounts..." class="w-full h-8 pl-8 rounded sm:rounded-md bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-600 sm:border-slate-700 text-slate-900 dark:text-white placeholder-slate-400 focus:ring-1 sm:focus:ring-2 focus:ring-blue-500 focus:border-transparent text-xs shadow-sm transition-colors" />
                        </div>

                        <!-- MOBILE FILTERS (Icon Only, Overlay Select) -->
                        <div class="flex sm:hidden flex-row gap-1.5 w-full overflow-x-auto no-scrollbar pb-1 snap-x">
                            <div v-if="availablePrograms.length > 0" class="relative shrink-0 w-10 h-8 snap-start bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-600 rounded flex items-center justify-center shadow-sm transition">
                                <BookOpen class="w-4 h-4 text-slate-500 dark:text-slate-400 pointer-events-none" />
                                <select v-model="filterProgram" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer dark:[color-scheme:dark]" title="Filter by Program">
                                    <option value="all" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">All Programs</option>
                                    <option v-for="prog in availablePrograms" :key="prog" :value="prog" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">{{ prog }}</option>
                                </select>
                            </div>
                            
                            <div v-if="availableYears.length > 0" class="relative shrink-0 w-10 h-8 snap-start bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-600 rounded flex items-center justify-center shadow-sm transition">
                                <Calendar class="w-4 h-4 text-slate-500 dark:text-slate-400 pointer-events-none" />
                                <select v-model="filterYear" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer dark:[color-scheme:dark]" title="Filter by Year">
                                    <option value="all" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">All Batches</option>
                                    <option v-for="year in availableYears" :key="year" :value="year" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">{{ year }} Batches</option>
                                </select>
                            </div>

                            <div class="relative shrink-0 w-10 h-8 snap-start bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-600 rounded flex items-center justify-center shadow-sm transition">
                                <Filter class="w-4 h-4 text-slate-500 dark:text-slate-400 pointer-events-none" />
                                <select v-model="sortBy" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer dark:[color-scheme:dark]" title="Sort Users">
                                    <option value="newest" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">Newest First</option>
                                    <option value="oldest" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">Oldest First</option>
                                    <option value="name_asc" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">Name (A-Z)</option>
                                    <option value="name_desc" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">Name (Z-A)</option>
                                </select>
                            </div>
                        </div>

                        <!-- DESKTOP FILTERS (Expanded with Text) -->
                        <div class="hidden sm:grid grid-cols-2 lg:flex lg:flex-row gap-2 w-full lg:w-auto shrink-0 mt-1 lg:mt-0">
                            <div v-if="availablePrograms.length > 0" class="col-span-2 lg:col-span-1 shrink-0 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg px-2 py-1 shadow-sm flex items-center gap-1.5 min-w-[140px]">
                                <BookOpen class="w-3.5 h-3.5 text-slate-400 shrink-0" />
                                <select v-model="filterProgram" class="bg-transparent border-none text-[9px] font-bold uppercase tracking-widest text-slate-600 dark:text-slate-300 w-full focus:ring-0 cursor-pointer p-0 m-0 truncate dark:[color-scheme:dark]">
                                    <option value="all" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">All Programs</option>
                                    <option v-for="prog in availablePrograms" :key="prog" :value="prog" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">{{ prog }}</option>
                                </select>
                            </div>
                            
                            <div v-if="availableYears.length > 0" class="shrink-0 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg px-2 py-1 shadow-sm flex items-center gap-1.5 min-w-[120px]">
                                <Calendar class="w-3.5 h-3.5 text-slate-400 shrink-0" />
                                <select v-model="filterYear" class="bg-transparent border-none text-[9px] font-bold uppercase tracking-widest text-slate-600 dark:text-slate-300 w-full focus:ring-0 cursor-pointer p-0 m-0 truncate dark:[color-scheme:dark]">
                                    <option value="all" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">All Batches</option>
                                    <option v-for="year in availableYears" :key="year" :value="year" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">{{ year }} Batches</option>
                                </select>
                            </div>
                            
                            <div class="shrink-0 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg px-2 py-1 shadow-sm flex items-center gap-1.5 min-w-[130px]" :class="{'col-span-2 lg:col-span-1': availablePrograms.length === 0 && availableYears.length === 0}">
                                <Filter class="w-3.5 h-3.5 text-slate-400 shrink-0" />
                                <select v-model="sortBy" class="bg-transparent border-none text-[9px] font-bold uppercase tracking-widest text-slate-600 dark:text-slate-300 w-full focus:ring-0 cursor-pointer p-0 m-0 truncate dark:[color-scheme:dark]">
                                    <option value="newest" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">Newest First</option>
                                    <option value="oldest" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">Oldest First</option>
                                    <option value="name_asc" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">Name (A-Z)</option>
                                    <option value="name_desc" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">Name (Z-A)</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Role Tabs -->
                    <div class="flex gap-4 border-b border-slate-200 dark:border-slate-700 mb-4 overflow-x-auto no-scrollbar">
                        <button v-for="tab in roleTabs" :key="tab.id" @click="userRoleTab = tab.id" class="pb-1.5 text-xs sm:text-sm font-bold border-b-2 transition-colors flex items-center gap-1.5 whitespace-nowrap" :class="userRoleTab === tab.id ? 'border-blue-600 text-blue-600 dark:text-blue-400 dark:border-blue-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300'">
                            {{ tab.name }}
                        </button>
                    </div>

                    <div v-if="userRoleTab === 'archive'" class="flex flex-wrap gap-2 mb-3">
                        <button v-for="subTab in subTabs" :key="subTab.id" @click="archiveSubTab = subTab.id" :class="[archiveSubTab === subTab.id ? 'bg-red-100 text-red-800 border-red-200 dark:bg-red-900/40 dark:text-red-300 dark:border-red-800/50' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50 dark:bg-slate-800/50 dark:text-slate-400 dark:border-slate-700/50']" class="px-3 py-1 text-[10px] font-bold rounded-full transition-all border shadow-sm">
                            {{ subTab.name }}
                        </button>
                    </div>

                    <transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0 -translate-y-2" enter-to-class="opacity-100 translate-y-0" leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100 translate-y-0" leave-to-class="opacity-0 -translate-y-2">
                        <div v-if="selectedIds.length > 0" class="flex flex-wrap items-center gap-2 bg-blue-50 dark:bg-blue-900/20 p-2.5 rounded-lg border border-blue-100 dark:border-blue-800 mb-4 shadow-sm">
                            <span class="text-[10px] font-black uppercase tracking-widest text-blue-700 dark:text-blue-400 mr-auto">{{ selectedIds.length }} Selected</span>
                            
                            <button v-if="userRoleTab === 'archive'" @click="openBulkSuspend('reactivate')" class="text-[9px] bg-white dark:bg-slate-800 text-emerald-600 border border-slate-200 dark:border-slate-700 px-3 py-1.5 rounded uppercase tracking-widest font-black shadow-sm hover:bg-emerald-50 transition">
                                Reactivate
                            </button>
                            <button v-if="userRoleTab !== 'archive'" @click="openBulkSuspend('suspend')" class="text-[9px] bg-white dark:bg-slate-800 text-red-600 border border-slate-200 dark:border-slate-700 px-3 py-1.5 rounded uppercase tracking-widest font-black shadow-sm hover:bg-red-50 transition">
                                Suspend
                            </button>
                            <button @click="openBulkDelete()" class="text-[9px] bg-red-600 hover:bg-red-500 text-white px-3 py-1.5 rounded uppercase tracking-widest font-black shadow-sm transition">
                                Delete All
                            </button>
                        </div>
                    </transition>

                    <div class="text-[9px] font-bold uppercase tracking-widest text-slate-400 mb-2 flex items-center gap-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Tip: Click any user row or card to view their full profile.
                    </div>

                    <!-- ========================================== -->
                    <!-- DESKTOP TABLE VIEW                         -->
                    <!-- ========================================== -->
                    <div class="hidden md:block bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden mb-4">
                        <div class="overflow-x-auto no-scrollbar">
                            <table class="w-full text-left text-[11px] sm:text-xs text-slate-500 dark:text-slate-400">
                                <thead class="text-[9px] uppercase font-bold text-slate-400 bg-slate-50 dark:bg-slate-900/30 border-b border-slate-100 dark:border-slate-700">
                                    <tr>
                                        <th class="px-3 py-2 w-8">
                                            <input type="checkbox" :checked="isAllSelected && paginatedUsers.length > 0" @change="toggleAll" class="rounded border-slate-300 dark:border-slate-600 text-blue-600 focus:ring-blue-500 dark:bg-slate-800 cursor-pointer shadow-sm" />
                                        </th>
                                        <th class="px-2 py-2 w-full sm:w-auto">User Details</th>
                                        <th class="px-2 py-2 hidden sm:table-cell">Role / Dept</th>
                                        <th class="px-2 py-2 hidden sm:table-cell">Status</th>
                                        <th class="px-2 py-2 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                    <tr v-for="user in paginatedUsers" :key="user.id" @click="openUserDetails(user)" class="transition select-none cursor-pointer group" :class="selectedIds.includes(user.id) ? 'bg-blue-50/50 dark:bg-blue-900/10' : 'hover:bg-slate-50 dark:hover:bg-slate-700/50'" title="Click to view full profile">
                                        <td class="px-2 py-1 w-6 sm:px-3 sm:py-1.5 sm:w-8" @click.stop>
                                            <input type="checkbox" :checked="selectedIds.includes(user.id)" @change="toggleSelection(user.id)" class="rounded border-slate-300 dark:border-slate-600 text-blue-600 focus:ring-blue-500 dark:bg-slate-800 cursor-pointer shadow-sm w-3 h-3 sm:w-4 sm:h-4" />
                                        </td>
                                        <td class="px-1 py-1 sm:px-2 sm:py-1.5 w-full sm:w-auto">
                                            <div class="flex items-center gap-2">
                                                <div class="w-6 h-6 rounded-full bg-slate-200 dark:bg-slate-700 shrink-0 overflow-hidden text-slate-500 dark:text-slate-400 flex items-center justify-center text-[10px] font-black">
                                                    <img v-if="user.avatar" :src="user.avatar" class="w-full h-full object-cover" />
                                                    <span v-else>{{ user.name.charAt(0) }}</span>
                                                </div>
                                                <div class="min-w-0 flex-1 flex flex-col justify-center">
                                                    <div class="flex items-center gap-1.5 truncate">
                                                        <span class="font-bold text-slate-900 dark:text-white truncate text-[10px] sm:text-xs leading-none">{{ user.name }}</span>
                                                        <span v-if="user.id === $page.props.auth.user.id" class="hidden sm:inline-block ml-1 text-[8px] bg-blue-100 text-blue-700 px-1 py-0.5 rounded font-black uppercase leading-none">(You)</span>
                                                    </div>
                                                    <div class="text-[8px] sm:text-[9px] text-slate-500 truncate leading-none mt-0.5">{{ user.email }}</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-2 py-1.5 hidden sm:table-cell align-top">
                                            <span class="text-[9px] font-black uppercase tracking-widest px-1.5 py-0.5 rounded inline-block mb-1 border"
                                                :class="user.role === 'admin' ? 'bg-purple-50 text-purple-700 border-purple-200 dark:border-purple-800 dark:bg-purple-900/30 dark:text-purple-400' : user.role === 'dean' ? 'bg-amber-50 text-amber-700 border-amber-200 dark:border-amber-800 dark:bg-amber-900/30 dark:text-amber-400' : user.role === 'teacher' ? 'bg-blue-50 text-blue-700 border-blue-200 dark:border-blue-800 dark:bg-blue-900/30 dark:text-blue-400' : 'bg-slate-50 text-slate-600 border-slate-200 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-400'">
                                                {{ user.role }}
                                            </span>
                                            <div v-if="user.department" class="text-[8px] font-bold text-slate-500 uppercase tracking-widest truncate max-w-[150px]"><Building2 class="w-3 h-3 inline pb-0.5"/> {{ user.department.name }}</div>
                                            <div v-else-if="user.school_id" class="text-[8px] font-bold text-slate-500 uppercase tracking-widest truncate max-w-[150px]">ID: {{ user.school_id }}</div>
                                        </td>
                                        <td class="px-2 py-1.5 hidden sm:table-cell align-top">
                                            <span v-if="user.status === 'active'" class="text-[9px] font-black uppercase tracking-widest text-emerald-600 flex items-center gap-1"><div class="w-1.5 h-1.5 rounded-full bg-emerald-500"></div> Active</span>
                                            <span v-else class="text-[9px] font-black uppercase tracking-widest text-red-600 flex items-center gap-1" :title="user.suspension_reason"><div class="w-1.5 h-1.5 rounded-full bg-red-500"></div> Suspended</span>
                                        </td>
                                        <td class="px-1 py-1 sm:px-2 sm:py-1.5 text-right align-middle" @click.stop>
                                            <div class="flex items-center justify-end gap-1 flex-nowrap min-w-[80px]">
                                                <button @click="openResetPasswordModal(user)" class="p-1 sm:p-1.5 text-indigo-400 hover:text-indigo-600 bg-white hover:bg-indigo-50 dark:bg-transparent dark:hover:bg-indigo-900/30 rounded transition shadow-sm border border-transparent hover:border-indigo-200 dark:hover:border-indigo-800" title="Reset Password">
                                                    <Key class="w-3 h-3 sm:w-3.5 sm:h-3.5" />
                                                </button>
                                                <button @click="openRoleModal(user)" class="p-1 sm:p-1.5 text-blue-400 hover:text-blue-600 bg-white hover:bg-blue-50 dark:bg-transparent dark:hover:bg-blue-900/30 rounded transition shadow-sm border border-transparent hover:border-blue-200 dark:hover:border-blue-800" title="Change User Role">
                                                    <UserCog class="w-3 h-3 sm:w-3.5 sm:h-3.5" />
                                                </button>
                                                <button @click="openImpersonateModal(user)" class="flex items-center gap-1 rounded bg-amber-50 dark:bg-amber-900/20 p-1 sm:px-1.5 sm:py-1 text-amber-600 dark:text-amber-400 transition hover:bg-amber-100 dark:hover:bg-amber-900/40" title="Login as this user">
                                                    <svg class="w-3 h-3 sm:w-3.5 sm:h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                                    <span class="hidden lg:inline text-[9px] font-bold uppercase tracking-widest">Impersonate</span>
                                                </button>
                                                <button v-if="user.status === 'suspended'" @click="openBulkSuspend('reactivate', user.id)" class="p-1 sm:px-2 sm:py-1 bg-emerald-50 sm:bg-emerald-600 hover:bg-emerald-100 sm:hover:bg-emerald-500 text-emerald-600 sm:text-white rounded transition border border-emerald-100 sm:border-transparent dark:bg-emerald-900/30 dark:border-emerald-800 dark:text-emerald-400 shadow-sm" title="Unsuspend">
                                                    <span class="hidden sm:inline text-[9px] uppercase tracking-widest font-bold">Unsuspend</span>
                                                </button>
                                                <button v-else @click="openBulkSuspend('suspend', user.id)" class="p-1 sm:px-2 sm:py-1 bg-red-50 hover:bg-red-100 dark:bg-red-900/20 dark:hover:bg-red-900/40 text-red-500 hover:text-red-700 rounded border border-red-100 dark:border-red-900/30 transition shadow-sm" title="Suspend">
                                                    <span class="hidden sm:inline text-[9px] font-bold uppercase tracking-widest">Suspend</span>
                                                </button>
                                                <button @click="openBulkDelete(user.id)" class="p-1 sm:p-1.5 text-slate-400 hover:text-red-600 bg-white hover:bg-red-50 dark:bg-transparent dark:hover:bg-red-900/30 rounded transition shadow-sm border border-transparent hover:border-red-200 dark:hover:border-red-800" title="Permanently Delete Account">
                                                    <Trash2 class="w-3 h-3 sm:w-3.5 sm:h-3.5" />
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-if="paginatedUsers.length === 0">
                                        <td colspan="5" class="px-2 py-8 text-center text-slate-400 dark:text-slate-500 text-[10px]">
                                            <div class="font-black uppercase tracking-widest mb-1 text-slate-300 dark:text-slate-600">No Records Found</div>
                                            <div class="font-medium">Try adjusting your search or filters.</div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- ========================================== -->
                    <!-- COMPACT MOBILE CARDS VIEW                  -->
                    <!-- ========================================== -->
                    <div class="md:hidden flex flex-col gap-2 mb-4">
                        <div v-for="user in paginatedUsers" :key="user.id" 
                             class="p-2.5 flex flex-col gap-2.5 rounded-lg border shadow-sm transition-colors cursor-pointer relative" 
                             :class="selectedIds.includes(user.id) ? 'bg-blue-50/50 border-blue-200 dark:bg-blue-900/10 dark:border-blue-800' : 'bg-white border-slate-200 dark:bg-slate-800 dark:border-slate-700'"
                             @click="openUserDetails(user)">
                            
                            <!-- Selection Checkbox -->
                            <div class="absolute top-2.5 right-2.5 z-10" @click.stop>
                                <input type="checkbox" :checked="selectedIds.includes(user.id)" @change="toggleSelection(user.id)" class="rounded border-slate-300 dark:border-slate-600 text-blue-600 focus:ring-blue-500 dark:bg-slate-800 cursor-pointer shadow-sm w-4 h-4" />
                            </div>

                            <div class="flex items-center gap-2.5 pr-6">
                                <div class="w-10 h-10 rounded-full bg-slate-200 dark:bg-slate-700 shrink-0 overflow-hidden text-slate-500 dark:text-slate-400 flex items-center justify-center text-sm font-black shadow-sm relative">
                                    <img v-if="user.avatar" :src="user.avatar" class="w-full h-full object-cover" />
                                    <span v-else>{{ user.name.charAt(0) }}</span>
                                    <!-- Mobile Status Dot -->
                                    <span v-if="user.status === 'suspended'" class="absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full bg-red-500 border border-white dark:border-slate-800"></span>
                                    <span v-else class="absolute bottom-0 right-0 w-2.5 h-2.5 rounded-full bg-emerald-500 border border-white dark:border-slate-800"></span>
                                </div>
                                
                                <div class="flex-1 min-w-0 flex flex-col justify-center">
                                    <div class="flex items-center gap-1.5 truncate">
                                        <span class="font-black text-slate-900 dark:text-white truncate text-[11px] leading-none">{{ user.name }}</span>
                                        <span class="text-[7px] font-black uppercase tracking-widest px-1 py-0.5 rounded leading-none shrink-0" 
                                              :class="user.role === 'admin' ? 'bg-purple-100 text-purple-700' : user.role === 'dean' ? 'bg-amber-100 text-amber-700' : user.role === 'teacher' ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-600'">
                                            {{ user.role }}
                                        </span>
                                    </div>
                                    <div class="text-[9px] text-slate-500 truncate mt-1 leading-tight">{{ user.email }}</div>
                                    <div class="text-[8px] font-bold text-slate-400 uppercase tracking-widest truncate mt-0.5">
                                        {{ user.department ? user.department.name : (user.school_id ? `ID: ${user.school_id}` : 'N/A') }}
                                    </div>
                                </div>
                            </div>

                            <!-- Horizontal Action Buttons -->
                            <div class="flex flex-wrap items-center gap-1.5 shrink-0 pt-2 border-t border-slate-100 dark:border-slate-700/50" @click.stop>
                                <button @click="openResetPasswordModal(user)" class="flex-1 flex items-center justify-center p-1.5 text-indigo-600 bg-indigo-50 dark:bg-indigo-900/30 dark:text-indigo-400 rounded shadow-sm border border-indigo-100 dark:border-indigo-800">
                                    <Key class="w-3.5 h-3.5" />
                                </button>
                                <button @click="openRoleModal(user)" class="flex-1 flex items-center justify-center p-1.5 text-blue-600 bg-blue-50 dark:bg-blue-900/30 dark:text-blue-400 rounded shadow-sm border border-blue-100 dark:border-blue-800">
                                    <UserCog class="w-3.5 h-3.5" />
                                </button>
                                <button @click="openImpersonateModal(user)" class="flex-1 flex items-center justify-center p-1.5 text-amber-600 bg-amber-50 dark:bg-amber-900/30 dark:text-amber-400 rounded shadow-sm border border-amber-100 dark:border-amber-800">
                                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                </button>
                                
                                <button v-if="user.status === 'suspended'" @click="openBulkSuspend('reactivate', user.id)" class="flex-[2] flex items-center justify-center p-1.5 text-emerald-600 bg-emerald-50 dark:bg-emerald-900/30 dark:text-emerald-400 rounded shadow-sm border border-emerald-200 dark:border-emerald-800">
                                    <span class="text-[9px] font-black uppercase tracking-widest">Restore</span>
                                </button>
                                <button v-else @click="openBulkSuspend('suspend', user.id)" class="flex-[2] flex items-center justify-center p-1.5 text-slate-600 bg-slate-100 dark:bg-slate-700 dark:text-slate-300 rounded shadow-sm border border-slate-300 dark:border-slate-600">
                                    <ShieldAlert class="w-3.5 h-3.5 mr-1" />
                                    <span class="text-[9px] font-black uppercase tracking-widest">Suspend</span>
                                </button>

                                <button @click="openBulkDelete(user.id)" class="flex-1 flex items-center justify-center p-1.5 text-red-600 bg-red-50 dark:bg-red-900/30 dark:text-red-400 rounded shadow-sm border border-red-200 dark:border-red-800">
                                    <Trash2 class="w-3.5 h-3.5" />
                                </button>
                            </div>
                        </div>
                        
                        <div v-if="paginatedUsers.length === 0" class="px-2 py-8 text-center bg-white dark:bg-slate-800 rounded-lg border border-dashed border-slate-200 dark:border-slate-700">
                            <div class="font-black text-[10px] uppercase tracking-widest text-slate-400 dark:text-slate-500">No Records Found</div>
                        </div>
                    </div>

                    <!-- CLIENT-SIDE PAGINATION UI -->
                    <div v-if="totalPages > 0" class="flex items-center justify-between px-4 py-3 border-t border-slate-200 dark:border-slate-700 sm:px-6 bg-slate-50 dark:bg-slate-900/50 shrink-0">
                        <!-- Mobile Pagination -->
                        <div class="flex flex-1 justify-between sm:hidden">
                            <button @click="currentPage--" :disabled="currentPage === 1" class="relative inline-flex items-center px-4 py-2 text-[10px] font-black uppercase tracking-widest text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 rounded-md hover:bg-slate-50 dark:hover:bg-slate-700 disabled:opacity-50 disabled:cursor-not-allowed shadow-sm transition">
                                Previous
                            </button>
                            <button @click="currentPage++" :disabled="currentPage === totalPages" class="relative inline-flex items-center px-4 py-2 text-[10px] font-black uppercase tracking-widest text-slate-700 dark:text-slate-300 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 rounded-md hover:bg-slate-50 dark:hover:bg-slate-700 disabled:opacity-50 disabled:cursor-not-allowed shadow-sm transition">
                                Next
                            </button>
                        </div>
                        <!-- Desktop Pagination -->
                        <div class="hidden sm:flex sm:flex-1 sm:items-center sm:justify-between">
                            <div>
                                <p class="text-[10px] text-slate-500 dark:text-slate-400 font-bold uppercase tracking-widest">
                                    Showing <span class="font-black">{{ filteredUsers.length > 0 ? ((currentPage - 1) * itemsPerPage) + 1 : 0 }}</span> to 
                                    <span class="font-black">{{ Math.min(currentPage * itemsPerPage, filteredUsers.length) }}</span> of 
                                    <span class="font-black">{{ filteredUsers.length }}</span> users
                                </p>
                            </div>
                            <div>
                                <nav class="isolate inline-flex -space-x-px rounded-md shadow-sm" aria-label="Pagination">
                                    <button v-for="page in totalPages" :key="page" @click="currentPage = page"
                                            class="relative inline-flex items-center px-3 py-1.5 text-[10px] font-bold ring-1 ring-inset ring-slate-300 dark:ring-slate-600 focus:z-20 focus:outline-offset-0 transition"
                                            :class="page === currentPage ? 'z-10 bg-blue-600 text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600' : 'text-slate-500 dark:text-slate-400 bg-white dark:bg-slate-800 hover:bg-slate-50 dark:hover:bg-slate-700'">
                                        {{ page }}
                                    </button>
                                </nav>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ========================================== -->
                <!-- VIEW 2: DEPARTMENT DIRECTORY               -->
                <!-- ========================================== -->
                <div v-if="mainTab === 'departments'" class="animate-in fade-in slide-in-from-bottom-2 duration-300">
                    <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden mb-8">
                        <div class="overflow-x-auto custom-scrollbar">
                            <table class="w-full text-left text-[10px] sm:text-xs text-slate-500 dark:text-slate-400 whitespace-nowrap sm:whitespace-normal">
                                <thead class="text-[8px] sm:text-[9px] uppercase font-bold text-slate-400 bg-slate-50 dark:bg-slate-900/30 border-b border-slate-100 dark:border-slate-700">
                                    <tr>
                                        <th class="px-4 py-2 w-16">ID</th>
                                        <th class="px-4 py-2 w-full">Department Name</th>
                                        <th class="px-4 py-2 hidden sm:table-cell">Assigned Dean(s)</th>
                                        <th class="px-4 py-2 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                    <tr v-for="dept in departments" :key="dept.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition">
                                        <td class="px-4 py-2 font-mono font-bold text-slate-400">#{{ dept.id }}</td>
                                        <td class="px-4 py-2 font-black text-slate-900 dark:text-white text-xs">{{ dept.name }}</td>
                                        
                                        <td class="px-4 py-2 hidden sm:table-cell">
                                            <div v-if="dept.users && dept.users.length > 0" class="flex flex-wrap gap-1">
                                                <span v-for="dean in dept.users" :key="dean.id" class="text-[9px] font-bold uppercase tracking-widest bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400 px-2 py-0.5 rounded border border-amber-200 dark:border-amber-800">
                                                    {{ dean.name }}
                                                </span>
                                            </div>
                                            <span v-else class="text-[9px] font-bold text-slate-400 uppercase tracking-widest italic">No Dean Assigned</span>
                                        </td>
                                        <td class="px-4 py-2 text-right">
                                            <button @click="openDeleteDept(dept)" class="p-1.5 text-slate-400 hover:text-red-600 bg-white hover:bg-red-50 dark:bg-transparent dark:hover:bg-red-900/30 rounded transition shadow-sm border border-transparent hover:border-red-200" title="Delete Department">
                                                <Trash2 class="w-3.5 h-3.5 inline" />
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="!departments || departments.length === 0">
                                        <td colspan="4" class="px-4 py-8 text-center text-slate-400 dark:text-slate-500 text-[10px]">
                                            <div class="font-black uppercase tracking-widest mb-1 text-slate-300 dark:text-slate-600">No Departments Established</div>
                                            <div class="font-bold">Click the purple button below to create one.</div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- ========================================== -->
        <!-- MODALS (Details, Create User, Create Dept) -->
        <!-- ========================================== -->
        <Modal :show="isUserDetailsModalOpen" @close="isUserDetailsModalOpen = false" maxWidth="sm">
            <div class="relative bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                <div class="h-16 bg-gradient-to-r from-blue-500 to-indigo-600"></div>
                
                <button @click="isUserDetailsModalOpen = false" class="absolute top-2 right-2 text-white/80 hover:text-white bg-black/20 hover:bg-black/40 rounded-full p-1.5 transition z-10">
                    <X class="w-4 h-4" />
                </button>

                <div class="absolute top-8 left-1/2 -translate-x-1/2">
                    <div class="w-16 h-16 rounded-full border-4 border-white dark:border-slate-800 bg-slate-200 dark:bg-slate-700 flex items-center justify-center overflow-hidden shadow-sm">
                        <img v-if="selectedUserDetails?.avatar" :src="selectedUserDetails.avatar" referrerpolicy="no-referrer" class="w-full h-full object-cover" />
                        <span v-else class="text-xl font-black text-slate-500 dark:text-slate-400 uppercase">{{ selectedUserDetails?.name?.charAt(0) }}</span>
                    </div>
                </div>

                <div class="pt-12 pb-5 px-5">
                    <div class="text-center mb-5">
                        <h3 class="text-base font-black text-slate-900 dark:text-white leading-tight">{{ selectedUserDetails?.name }}</h3>
                        <p class="text-[10px] font-bold text-slate-500 mt-0.5">{{ selectedUserDetails?.email }}</p>

                        <div class="flex items-center justify-center gap-1.5 mt-2.5">
                            <span class="px-2 py-0.5 rounded text-[8px] font-black uppercase tracking-widest bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400">{{ selectedUserDetails?.role }}</span>
                            <span :class="[
                                'px-2 py-0.5 rounded text-[8px] font-black uppercase tracking-widest border border-transparent shadow-sm',
                                selectedUserDetails?.status === 'suspended'
                                    ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400 border-red-200 dark:border-red-800'
                                    : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800'
                            ]">
                                {{ selectedUserDetails?.status }}
                            </span>
                        </div>
                    </div>

                    <div class="bg-slate-50 dark:bg-slate-900/50 rounded-lg border border-slate-100 dark:border-slate-700/50 p-3 space-y-2.5">
                        <div class="flex justify-between items-center border-b border-slate-200 dark:border-slate-700 pb-2">
                            <span class="text-[9px] font-bold uppercase tracking-widest text-slate-400">School ID / Emp. No.</span>
                            <span class="text-[10px] font-black text-slate-800 dark:text-slate-200 font-mono">{{ selectedUserDetails?.school_id || 'N/A' }}</span>
                        </div>
                        <div class="flex justify-between items-center border-b border-slate-200 dark:border-slate-700 pb-2">
                            <span class="text-[9px] font-bold uppercase tracking-widest text-slate-400">Program / Dept</span>
                            <span class="text-[10px] font-black text-slate-800 dark:text-slate-200 text-right max-w-[60%] truncate">
                                {{ selectedUserDetails?.department ? selectedUserDetails.department.name : (selectedUserDetails?.program || 'N/A') }}
                            </span>
                        </div>
                        <div class="flex justify-between items-center border-b border-slate-200 dark:border-slate-700 pb-2">
                            <span class="text-[9px] font-bold uppercase tracking-widest text-slate-400">Contact No.</span>
                            <span class="text-[10px] font-black text-slate-800 dark:text-slate-200">{{ selectedUserDetails?.contact_number || 'N/A' }}</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-[9px] font-bold uppercase tracking-widest text-slate-400">Joined Date</span>
                            <span class="text-[10px] font-black text-slate-800 dark:text-slate-200">{{ selectedUserDetails ? new Date(selectedUserDetails.created_at).toLocaleDateString() : '' }}</span>
                        </div>
                    </div>
                    
                    <div v-if="selectedUserDetails?.status === 'suspended'" class="mt-3 p-3 bg-red-50 dark:bg-red-900/20 border border-red-100 dark:border-red-800/30 rounded-lg text-center">
                        <span class="text-[9px] font-black uppercase text-red-600 tracking-widest block mb-1">Suspension Reason</span>
                        <span class="text-xs font-medium text-red-700 dark:text-red-400 leading-snug">{{ selectedUserDetails?.suspension_reason }}</span>
                    </div>
                </div>
            </div>
        </Modal>

        <Modal :show="isCreateModalOpen" :closeable="false" @close="isCreateModalOpen = false" maxWidth="md">
            <div class="p-4 sm:p-5 bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-slate-200 dark:border-slate-700 flex flex-col max-h-[90vh]">
                <h2 class="text-sm sm:text-base font-bold text-slate-900 dark:text-white mb-3 shrink-0 flex items-center gap-2">
                    <div class="w-6 h-6 rounded bg-blue-100 dark:bg-blue-900/30 text-blue-600 flex items-center justify-center">
                        <Plus class="w-3.5 h-3.5" />
                    </div>
                    Create New Account
                </h2>
                
                <form @submit.prevent="submitUser" class="flex flex-col min-h-0">
                    <div class="flex-1 overflow-y-auto custom-scrollbar pr-1 sm:pr-2 pb-2 space-y-3">
                        <div>
                            <InputLabel value="Full Name *" class="text-[8px] font-bold uppercase text-slate-500 mb-0.5" />
                            <input v-model="form.name" type="text" :class="inputClass" required />
                            <InputError :message="form.errors.name" class="mt-1 text-[9px]" />
                        </div>
                        <div>
                            <InputLabel value="Email Address *" class="text-[8px] font-bold uppercase text-slate-500 mb-0.5" />
                            <input v-model="form.email" type="email" :class="inputClass" required />
                            <InputError :message="form.errors.email" class="mt-1 text-[9px]" />
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <InputLabel value="Account Role *" class="text-[8px] font-bold uppercase text-slate-500 mb-0.5" />
                                <select v-model="form.role" :class="inputClass" class="cursor-pointer font-bold uppercase tracking-widest dark:[color-scheme:dark]" required>
                                    <option value="student" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">Student</option>
                                    <option value="teacher" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">Teacher</option>
                                    <option value="dean" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">Dean (Oversight)</option>
                                    <option value="admin" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">Administrator</option>
                                </select>
                            </div>
                            
                            <div v-if="form.role === 'teacher' || form.role === 'dean'">
                                <InputLabel value="Assign Department *" class="text-[8px] font-bold uppercase text-purple-500 mb-0.5" />
                                <select v-model="form.department_id" :class="inputClass" class="cursor-pointer border-purple-200 dark:border-purple-800 focus:ring-purple-500 dark:[color-scheme:dark]" required>
                                    <option value="" disabled class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">Select Dept...</option>
                                    <option v-for="dept in departments" :key="dept.id" :value="dept.id" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">{{ dept.name }}</option>
                                </select>
                                <InputError :message="form.errors.department_id" class="mt-1 text-[9px]" />
                            </div>
                            <div v-else>
                                <div class="flex justify-between items-end mb-0.5">
                                    <InputLabel value="School ID / No." class="text-[8px] font-bold uppercase text-slate-500" />
                                    <button type="button" @click="generateSchoolId" class="text-[8px] text-blue-600 dark:text-blue-400 font-black uppercase tracking-widest hover:underline">
                                        Auto-ID
                                    </button>
                                </div>
                                <input v-model="form.school_id" type="text" :class="inputClass" :required="form.role !== 'admin'" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div v-if="form.role === 'student'">
                                <InputLabel value="Program / Course" class="text-[8px] font-bold uppercase text-slate-500 mb-0.5" />
                                <select v-model="form.program" :class="inputClass" required class="cursor-pointer dark:[color-scheme:dark]">
                                    <option value="" disabled class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">Select a Program...</option>
                                    <option value="BS Information Technology" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">BS Information Technology</option>
                                    <option value="BS Computer Science" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">BS Computer Science</option>
                                    <option value="BS Education" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">BS Education</option>
                                    <option value="BS Business Administration" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">BS Business Administration</option>
                                    <option value="BS Accountancy" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">BS Accountancy</option>
                                    <option value="Other" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">Other</option>
                                </select>
                                <InputError :message="form.errors.program" class="mt-1 text-[9px]" />
                            </div>
                            <div :class="{'col-span-2': form.role !== 'student'}">
                                <InputLabel value="Mobile Number" class="text-[8px] font-bold uppercase text-slate-500 mb-0.5" />
                                <input v-model="form.contact_number" type="text" :class="inputClass" required />
                                <InputError :message="form.errors.contact_number" class="mt-1 text-[9px]" />
                            </div>
                        </div>

                        <div class="p-3 bg-slate-50 dark:bg-slate-900/50 rounded-lg border border-slate-200 dark:border-slate-700/50 mt-4">
                            <div class="flex justify-between items-end mb-1">
                                <InputLabel value="Temporary Password *" class="text-[8px] font-bold uppercase text-slate-500" />
                                <button type="button" @click="generatePassword" class="text-[8px] text-blue-600 dark:text-blue-400 font-black uppercase tracking-widest hover:underline">Auto-Generate</button>
                            </div>
                            <input v-model="form.password" type="text" :class="inputClass" placeholder="Type or generate a secure password" required />
                            <InputError :message="form.errors.password" class="mt-1 text-[9px]" />
                            <p class="text-[8px] text-slate-500 dark:text-slate-400 mt-1.5 font-bold uppercase tracking-widest">This account will be auto-verified upon creation.</p>
                        </div>
                    </div>

                    <div class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-700 flex justify-end gap-2 shrink-0">
                        <button type="button" @click="isCreateModalOpen = false" class="text-[9px] text-slate-500 px-3 py-1.5 font-bold hover:text-slate-700 uppercase tracking-widest transition">Cancel</button>
                        <button :disabled="form.processing" class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-1.5 rounded text-[9px] uppercase tracking-widest font-black shadow-sm transition">Create Account</button>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="isCreateDeptModalOpen" @close="isCreateDeptModalOpen = false" maxWidth="sm">
            <div class="p-5 bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-slate-200 dark:border-slate-700">
                <h2 class="text-sm font-black uppercase tracking-tight text-slate-900 dark:text-white flex items-center gap-2 mb-4">
                    <div class="w-6 h-6 rounded bg-purple-100 dark:bg-purple-900/30 text-purple-600 flex items-center justify-center">
                        <Building2 class="w-3.5 h-3.5" />
                    </div>
                    Establish Department
                </h2>
                
                <form @submit.prevent="submitDept">
                    <div>
                        <InputLabel value="Department Name *" class="text-[9px] font-bold uppercase text-slate-500 mb-1" />
                        <input v-model="deptForm.name" type="text" :class="inputClass" placeholder="e.g. College of Computer Studies" required autofocus />
                        <InputError :message="deptForm.errors.name" class="mt-1 text-[9px]" />
                    </div>

                    <div class="mt-5 pt-3 border-t border-slate-100 dark:border-slate-700 flex justify-end gap-2">
                        <button type="button" @click="isCreateDeptModalOpen = false" class="text-[10px] text-slate-500 px-3 py-1.5 font-bold hover:text-slate-700 uppercase tracking-widest transition">Cancel</button>
                        <button :disabled="deptForm.processing" class="bg-purple-600 hover:bg-purple-500 text-white px-4 py-1.5 rounded text-[10px] uppercase tracking-widest font-black shadow-sm transition">Create</button>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="isDeleteDeptModalOpen" @close="isDeleteDeptModalOpen = false" maxWidth="sm">
            <div class="p-5 bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-slate-200 dark:border-slate-700">
                <h2 class="text-sm font-black uppercase tracking-tight text-red-600 flex items-center gap-2 mb-2">
                    <ShieldAlert class="w-5 h-5" /> Delete Department
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">
                    Deleting <strong>{{ selectedDepartment?.name }}</strong> will remove the tag from all assigned Deans and Teachers. This cannot be undone.
                </p>
                
                <form @submit.prevent="submitDeleteDept">
                    <div>
                        <InputLabel value="Admin Password *" class="text-[9px] font-bold uppercase text-slate-500 mb-1" />
                        <input v-model="deleteDeptForm.password" type="password" :class="inputClass" required />
                        <InputError :message="deleteDeptForm.errors.password" class="mt-1 text-[9px]" />
                    </div>

                    <div class="mt-5 pt-3 border-t border-slate-100 dark:border-slate-800 flex justify-end gap-2">
                        <button type="button" @click="isDeleteDeptModalOpen = false" class="text-[10px] text-slate-500 px-3 py-1.5 font-bold hover:text-slate-700 uppercase tracking-widest transition">Cancel</button>
                        <button :disabled="deleteDeptForm.processing" class="bg-red-600 hover:bg-red-500 text-white px-4 py-1.5 rounded text-[10px] uppercase tracking-widest font-black shadow-sm transition">Delete</button>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="isImpersonateModalOpen" :closeable="false" @close="isImpersonateModalOpen = false" maxWidth="sm">
            <div class="p-5 bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-slate-200 dark:border-slate-700">
                <h2 class="text-sm font-black uppercase tracking-tight text-amber-600 flex items-center gap-2 mb-2">
                    <ShieldAlert class="w-5 h-5" /> Impersonate Security Check
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">
                    You are about to log in as <strong class="text-amber-600 dark:text-amber-400">{{ selectedUserForImpersonate?.name }}</strong>. Please provide your admin password to proceed.
                </p>
                
                <form @submit.prevent="submitImpersonate" class="space-y-4">
                    <div>
                        <InputLabel value="Admin Password *" class="text-[9px] font-bold uppercase text-slate-500 mb-1" />
                        <input v-model="impersonateForm.password" type="password" :class="inputClass" placeholder="Enter your password" required />
                        <InputError :message="impersonateForm.errors.password" class="mt-1 text-[9px]" />
                    </div>

                    <div class="mt-5 pt-3 border-t border-slate-100 dark:border-slate-800 flex justify-end gap-2">
                        <button type="button" @click="isImpersonateModalOpen = false" class="text-[10px] text-slate-500 px-3 py-1.5 font-bold hover:text-slate-700 uppercase tracking-widest transition">Cancel</button>
                        <button :disabled="impersonateForm.processing" class="bg-amber-500 hover:bg-amber-400 text-white px-4 py-1.5 rounded text-[10px] uppercase tracking-widest font-black shadow-sm transition">Impersonate User</button>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="isBulkSuspendModalOpen" :closeable="false" @close="isBulkSuspendModalOpen = false" maxWidth="sm">
            <div class="p-5 bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-slate-200 dark:border-slate-700">
                <h2 class="text-sm font-black uppercase tracking-tight mb-2 flex items-center gap-2" :class="bulkSuspendForm.action === 'suspend' ? 'text-red-600' : 'text-emerald-600'">
                    <ShieldAlert v-if="bulkSuspendForm.action === 'suspend'" class="w-5 h-5" />
                    <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    {{ bulkSuspendForm.action === 'suspend' ? 'Suspend Users' : 'Reactivate Users' }}
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">
                    You are changing the status of <strong>{{ bulkSuspendForm.user_ids.length }} user account(s)</strong>. Provide your admin password to authorize.
                </p>
                
                <form @submit.prevent="submitBulkSuspend" class="space-y-4">
                    <div v-if="bulkSuspendForm.action === 'suspend'">
                        <InputLabel value="Reason for Suspension *" class="text-[9px] font-bold uppercase text-slate-500 mb-1" />
                        <textarea v-model="bulkSuspendForm.reason" rows="2" :class="inputClass" class="resize-none" placeholder="Will be shown to users if they try to log in" required></textarea>
                        <InputError :message="bulkSuspendForm.errors.reason" class="mt-1 text-[9px]" />
                    </div>
                    <div>
                        <InputLabel value="Admin Password *" class="text-[9px] font-bold uppercase text-slate-500 mb-1" />
                        <input v-model="bulkSuspendForm.password" type="password" :class="inputClass" placeholder="Enter your password" required />
                        <InputError :message="bulkSuspendForm.errors.password" class="mt-1 text-[9px]" />
                    </div>

                    <div class="mt-5 pt-3 border-t border-slate-100 dark:border-slate-800 flex justify-end gap-2">
                        <button type="button" @click="isBulkSuspendModalOpen = false" class="text-[10px] text-slate-500 px-3 py-1.5 font-bold hover:text-slate-700 uppercase tracking-widest transition">Cancel</button>
                        <button :disabled="bulkSuspendForm.processing" :class="bulkSuspendForm.action === 'suspend' ? 'bg-red-600 hover:bg-red-500' : 'bg-emerald-600 hover:bg-emerald-500'" class="text-white px-4 py-1.5 rounded text-[10px] uppercase tracking-widest font-black shadow-sm transition">
                            {{ bulkSuspendForm.action === 'suspend' ? 'Suspend Accounts' : 'Reactivate Accounts' }}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="isBulkDeleteModalOpen" :closeable="false" @close="isBulkDeleteModalOpen = false" maxWidth="sm">
            <div class="p-5 bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-slate-200 dark:border-slate-700">
                <h2 class="text-sm font-black uppercase tracking-tight text-red-600 flex items-center gap-2 mb-2">
                    <Trash2 class="w-5 h-5" /> Permanent Deletion
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">
                    You are permanently deleting <strong class="text-red-500">{{ bulkDeleteForm.user_ids.length }} user account(s)</strong>. This cannot be undone. Enter your admin password to confirm.
                </p>
                
                <form @submit.prevent="submitBulkDelete" class="space-y-4">
                    <div>
                        <InputLabel value="Admin Password *" class="text-[9px] font-bold uppercase text-slate-500 mb-1" />
                        <input v-model="bulkDeleteForm.password" type="password" :class="inputClass" placeholder="Enter your password" required />
                        <InputError :message="bulkDeleteForm.errors.password" class="mt-1 text-[9px]" />
                    </div>

                    <div class="mt-5 pt-3 border-t border-slate-100 dark:border-slate-800 flex justify-end gap-2">
                        <button type="button" @click="isBulkDeleteModalOpen = false" class="text-[10px] text-slate-500 px-3 py-1.5 font-bold hover:text-slate-700 uppercase tracking-widest transition">Cancel</button>
                        <button :disabled="bulkDeleteForm.processing" class="bg-red-600 hover:bg-red-500 text-white px-4 py-1.5 rounded text-[10px] uppercase tracking-widest font-black shadow-sm transition">Delete Forever</button>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="isResetPasswordModalOpen" :closeable="false" @close="isResetPasswordModalOpen = false" maxWidth="sm">
            <div class="p-5 bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-slate-200 dark:border-slate-700">
                <h2 class="text-sm font-black uppercase tracking-tight text-slate-900 dark:text-white flex items-center gap-2 mb-4">
                    <Key class="w-4 h-4 text-indigo-500" /> Reset User Password
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">
                    Generate a new password for <strong class="text-indigo-600 dark:text-indigo-400">{{ selectedUserForPassword?.name }}</strong>.
                </p>
                
                <form @submit.prevent="submitResetPassword" class="space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="col-span-2">
                            <div class="flex justify-between items-end mb-1">
                                <InputLabel value="New Temporary Password *" class="text-[9px] font-bold uppercase text-slate-500" />
                                <button type="button" @click="generateResetPassword" class="text-[9px] text-indigo-600 dark:text-indigo-400 font-bold uppercase tracking-widest hover:underline">Auto-Generate</button>
                            </div>
                            <input v-model="resetPasswordForm.password" type="text" :class="inputClass" placeholder="Enter or generate new password" required />
                            <InputError :message="resetPasswordForm.errors.password" class="mt-1 text-[9px]" />
                        </div>
                        
                        <div class="col-span-2 mt-2">
                            <InputLabel value="Admin Password (Security Check) *" class="text-[9px] font-bold uppercase text-slate-500 mb-1" />
                            <input v-model="resetPasswordForm.admin_password" type="password" :class="inputClass" placeholder="Enter your admin password" required />
                            <InputError :message="resetPasswordForm.errors.admin_password" class="mt-1 text-[9px]" />
                        </div>
                    </div>

                    <div class="mt-5 pt-3 border-t border-slate-100 dark:border-slate-800 flex justify-end gap-2">
                        <button type="button" @click="isResetPasswordModalOpen = false" class="text-[10px] text-slate-500 px-3 py-1.5 font-bold hover:text-slate-700 uppercase tracking-widest transition">Cancel</button>
                        <button :disabled="resetPasswordForm.processing" class="bg-indigo-600 hover:bg-indigo-500 text-white px-4 py-1.5 rounded text-[10px] uppercase tracking-widest font-black shadow-sm transition">Force Reset</button>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="isRoleModalOpen" :closeable="false" @close="isRoleModalOpen = false" maxWidth="sm">
            <div class="p-5 bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-slate-200 dark:border-slate-700">
                <h2 class="text-sm font-black uppercase tracking-tight text-slate-900 dark:text-white flex items-center gap-2 mb-4">
                    <UserCog class="w-4 h-4 text-blue-500" /> Role Security Check
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">
                    Updating <strong class="text-blue-600 dark:text-blue-400">{{ selectedUserForRole?.name }}</strong>. Please confirm with your admin password.
                </p>
                
                <form @submit.prevent="submitRole" class="space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="col-span-2">
                            <InputLabel value="Select New Role" class="text-[9px] font-bold uppercase text-slate-500 mb-1" />
                            <select v-model="roleForm.role" :class="inputClass" class="cursor-pointer font-bold uppercase tracking-widest dark:[color-scheme:dark]">
                                <option value="student" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">Student</option>
                                <option value="teacher" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">Teacher</option>
                                <option value="dean" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">Dean</option>
                                <option value="admin" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">Administrator</option>
                            </select>
                            <InputError :message="roleForm.errors.role" class="mt-1 text-[9px]" />
                        </div>
                        
                        <div v-if="roleForm.role === 'teacher' || roleForm.role === 'dean'" class="col-span-2">
                            <InputLabel value="Assign Department *" class="text-[9px] font-bold uppercase text-purple-500 mb-1" />
                            <select v-model="roleForm.department_id" :class="inputClass" class="cursor-pointer border-purple-200 dark:border-slate-700 focus:ring-purple-500 dark:[color-scheme:dark]" required>
                                <option value="" disabled class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">Select Dept...</option>
                                <option v-for="dept in departments" :key="dept.id" :value="dept.id" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">{{ dept.name }}</option>
                            </select>
                            <InputError :message="roleForm.errors.department_id" class="mt-1 text-[9px]" />
                        </div>

                        <div class="col-span-2">
                            <InputLabel value="Admin Password *" class="text-[9px] font-bold uppercase text-slate-500 mb-1" />
                            <input v-model="roleForm.password" type="password" :class="inputClass" placeholder="Enter your password" required />
                            <InputError :message="roleForm.errors.password" class="mt-1 text-[9px]" />
                        </div>
                    </div>

                    <div class="mt-5 pt-3 border-t border-slate-100 dark:border-slate-800 flex justify-end gap-2">
                        <button type="button" @click="isRoleModalOpen = false" class="text-[10px] text-slate-500 px-3 py-1.5 font-bold hover:text-slate-700 uppercase tracking-widest transition">Cancel</button>
                        <button :disabled="roleForm.processing" class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-1.5 rounded text-[10px] uppercase tracking-widest font-black shadow-sm transition">Authorize Change</button>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

.custom-scrollbar::-webkit-scrollbar { width: 4px; height: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(148, 163, 184, 0.3); border-radius: 10px; }
</style>