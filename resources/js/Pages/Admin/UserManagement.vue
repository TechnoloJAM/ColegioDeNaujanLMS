<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';

const props = defineProps({
    users: Array
});

// --- SEARCH, FILTER & SORT STATES ---
const searchQuery = ref('');
const activeTab = ref('student');
const archiveSubTab = ref('student'); 
const filterProgram = ref('all');
const filterYear = ref('all');
const sortBy = ref('newest');

// UI States
const isCreateModalOpen = ref(false);
const selectedIds = ref([]);

// User Details Card State
const isUserDetailsModalOpen = ref(false);
const selectedUserDetails = ref(null);

// Security Modals States
const isRoleModalOpen = ref(false);
const isResetPasswordModalOpen = ref(false);
const isImpersonateModalOpen = ref(false);
const isBulkSuspendModalOpen = ref(false);
const isBulkDeleteModalOpen = ref(false);

// Forms
const form = useForm({ role: 'teacher', name: '', email: '', school_id: '', program: '', contact_number: '', password: '' });
const roleForm = useForm({ role: '', password: '' });
const resetPasswordForm = useForm({ password: '', admin_password: '' });
const impersonateForm = useForm({ user_id: null, password: '' });
const bulkSuspendForm = useForm({ action: 'suspend', reason: '', password: '', user_ids: [] });
const bulkDeleteForm = useForm({ password: '', user_ids: [] });

const selectedUserForRole = ref(null);
const selectedUserForPassword = ref(null);
const selectedUserForImpersonate = ref(null);

// --- 1. DYNAMIC BASE TAB FILTERING ---
// First, isolate the users that belong ONLY to the currently selected tab
const baseTabUsers = computed(() => {
    return props.users.filter(user => {
        if (activeTab.value === 'archive') {
            return user.status === 'suspended' && user.role === archiveSubTab.value;
        } else {
            return user.status === 'active' && user.role === activeTab.value;
        }
    });
});

// --- 2. DYNAMIC FILTER OPTIONS ---
// Scan ONLY the baseTabUsers to populate the dropdown options dynamically
const availablePrograms = computed(() => {
    const progs = new Set();
    baseTabUsers.value.forEach(u => { if (u.program) progs.add(u.program); });
    return Array.from(progs).sort();
});

const availableYears = computed(() => {
    const yrs = new Set();
    baseTabUsers.value.forEach(u => {
        if (u.school_id && u.school_id.includes('-')) {
            yrs.add(u.school_id.split('-')[0]);
        }
    });
    return Array.from(yrs).sort((a, b) => b - a); // Sort newest year first
});

// --- 3. FINAL FILTERING LOGIC ---
// Apply search, specific filters, and sorting to the isolated tab users
const filteredUsers = computed(() => {
    let result = baseTabUsers.value.filter(user => {
        
        // Search Query
        const q = searchQuery.value.toLowerCase();
        if (q) {
            const searchMatch = user.name.toLowerCase().includes(q) || 
                                user.email.toLowerCase().includes(q) || 
                                (user.school_id && user.school_id.toLowerCase().includes(q));
            if (!searchMatch) return false;
        }

        // Program Filter
        if (filterProgram.value !== 'all' && user.program !== filterProgram.value) return false;

        // Year Filter
        if (filterYear.value !== 'all' && (!user.school_id || !user.school_id.startsWith(filterYear.value + '-'))) return false;

        return true;
    });

    // Sorting Logic
    return result.sort((a, b) => {
        if (sortBy.value === 'newest') return new Date(b.created_at || 0).getTime() - new Date(a.created_at || 0).getTime();
        if (sortBy.value === 'oldest') return new Date(a.created_at || 0).getTime() - new Date(b.created_at || 0).getTime();
        if (sortBy.value === 'name_asc') return a.name.localeCompare(b.name);
        if (sortBy.value === 'name_desc') return b.name.localeCompare(a.name);
        return 0;
    });
});

// Reset selection and sub-filters when changing main tabs
watch(activeTab, () => { 
    selectedIds.value = []; 
    filterProgram.value = 'all';
    filterYear.value = 'all';
});
watch(archiveSubTab, () => { selectedIds.value = []; });

// --- CHECKBOX LOGIC ---
const toggleSelection = (id) => {
    if (selectedIds.value.includes(id)) selectedIds.value = selectedIds.value.filter(i => i !== id);
    else selectedIds.value.push(id);
};

const isAllSelected = computed(() => {
    if (filteredUsers.value.length === 0) return false;
    return selectedIds.value.length === filteredUsers.value.length;
});

const toggleAll = () => {
    if (isAllSelected.value) selectedIds.value = [];
    else selectedIds.value = filteredUsers.value.map(u => u.id);
};

// --- OPEN USER PROFILE CARD ---
const openUserDetails = (user) => {
    selectedUserDetails.value = user;
    isUserDetailsModalOpen.value = true;
};

// --- GENERATORS ---
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

// --- SUBMIT ACTIONS ---
const submitUser = () => {
    form.post(route('admin.users.store'), {
        preserveScroll: true,
        onSuccess: () => {
            isCreateModalOpen.value = false;
            form.reset();
            alert('Account created and instantly verified! Give the user their password so they can log in.');
        },
    });
};

const openImpersonateModal = (user) => {
    selectedUserForImpersonate.value = user;
    impersonateForm.user_id = user.id;
    impersonateForm.password = '';
    impersonateForm.clearErrors();
    isImpersonateModalOpen.value = true;
};
const submitImpersonate = () => { impersonateForm.post(route('admin.users.impersonate', impersonateForm.user_id), { onSuccess: () => isImpersonateModalOpen.value = false }); };

const openBulkSuspend = (action, singleId = null) => {
    bulkSuspendForm.action = action;
    bulkSuspendForm.user_ids = singleId ? [singleId] : selectedIds.value;
    bulkSuspendForm.reason = '';
    bulkSuspendForm.password = '';
    bulkSuspendForm.clearErrors();
    isBulkSuspendModalOpen.value = true;
};
const submitBulkSuspend = () => { bulkSuspendForm.post(route('admin.users.bulk-toggle-status'), { preserveScroll: true, onSuccess: () => { isBulkSuspendModalOpen.value = false; selectedIds.value = []; } }); };

const openBulkDelete = (singleId = null) => {
    bulkDeleteForm.user_ids = singleId ? [singleId] : selectedIds.value;
    bulkDeleteForm.password = '';
    bulkDeleteForm.clearErrors();
    isBulkDeleteModalOpen.value = true;
};
const submitBulkDelete = () => { bulkDeleteForm.post(route('admin.users.bulk-destroy'), { preserveScroll: true, onSuccess: () => { isBulkDeleteModalOpen.value = false; selectedIds.value = []; } }); };

const openRoleModal = (user) => {
    selectedUserForRole.value = user;
    roleForm.role = user.role;
    roleForm.password = ''; 
    roleForm.clearErrors();
    isRoleModalOpen.value = true;
};
const submitRole = () => { roleForm.patch(route('admin.users.update-role', selectedUserForRole.value.id), { preserveScroll: true, onSuccess: () => { isRoleModalOpen.value = false; selectedUserForRole.value = null; } }); };

const openResetPasswordModal = (user) => {
    selectedUserForPassword.value = user;
    resetPasswordForm.password = '';
    resetPasswordForm.admin_password = '';
    resetPasswordForm.clearErrors();
    isResetPasswordModalOpen.value = true;
};
const submitResetPassword = () => { resetPasswordForm.patch(route('admin.users.reset-password', selectedUserForPassword.value.id), { preserveScroll: true, onSuccess: () => { isResetPasswordModalOpen.value = false; selectedUserForPassword.value = null; alert('Password reset successfully. Please securely share the new password with the user.'); } }); };

// --- PROFESSIONAL CSV EXPORT ---
const exportToCSV = () => {
    const lines = [];
    
    lines.push(`"COLEGIO DE NAUJAN - LMS SYSTEM REPORT"`);
    lines.push(`"Report Generated:","${new Date().toLocaleString()}"`);
    lines.push(`"List Category:","${activeTab.value.toUpperCase()}"`);
    lines.push(`"Total Records:","${filteredUsers.value.length}"`);
    lines.push(""); 
    
    const headers = ['School ID', 'Full Name', 'Email Address', 'Program/Department', 'System Role', 'Status', 'Date Registered'];
    lines.push(headers.map(h => `"${h}"`).join(','));

    filteredUsers.value.forEach(user => {
        const row = [
            user.school_id || 'N/A',
            user.name.replace(/"/g, '""'), 
            user.email,
            user.program || 'N/A',
            user.role.toUpperCase(),
            user.status.toUpperCase(),
            new Date(user.created_at).toLocaleDateString()
        ];
        lines.push(row.map(val => `"${val}"`).join(','));
    });

    const csvContent = lines.join("\n");
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.setAttribute("href", url);
    link.setAttribute("download", `CDN_LMS_Users_${activeTab.value}_${new Date().toISOString().slice(0,10)}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
};

const tabs = [
    { id: 'student', name: 'Students' },
    { id: 'teacher', name: 'Teachers' },
    { id: 'admin', name: 'Admins' },
    { id: 'archive', name: 'Archive' }
];

const subTabs = [
    { id: 'student', name: 'Suspended Students' },
    { id: 'teacher', name: 'Suspended Teachers' },
    { id: 'admin', name: 'Suspended Admins' }
];

const inputClass = "w-full rounded-md bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:ring-2 focus:ring-blue-500 focus:border-transparent py-1.5 px-3 text-xs shadow-sm transition-colors duration-200";
</script>

<template>
    <Head title="User Management" />
    <AuthenticatedLayout>
        
        <div class="mb-4 flex justify-between items-center max-w-7xl mx-auto px-4 sm:px-6">
             <div class="flex items-center gap-3">
                 <div>
                    <h1 class="text-lg sm:text-xl font-bold text-slate-900 dark:text-white leading-tight">User Management</h1>
                    <p class="text-slate-500 dark:text-slate-400 text-[9px] sm:text-[10px] uppercase font-bold tracking-wider">Control system access</p>
                 </div>
             </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 flex flex-col md:flex-row gap-4 md:gap-6 items-start">
            
            <aside class="w-full md:w-12 shrink-0 flex flex-row md:flex-col gap-3 sticky top-4 md:top-6 z-10 order-2 md:order-1">
                <button @click="isCreateModalOpen = true" class="group relative flex items-center justify-center w-10 h-10 md:w-12 md:h-12 bg-white dark:bg-slate-800 rounded-full border-2 border-slate-200 dark:border-slate-700 text-blue-600 hover:border-blue-600 hover:bg-blue-50 dark:hover:bg-slate-700 transition shadow-sm focus:outline-none shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" /></svg>
                    <span class="absolute bottom-full mb-2 md:bottom-auto md:left-full md:ml-3 md:mb-0 px-2 py-1 bg-slate-800 text-white text-[10px] font-bold rounded opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none whitespace-nowrap shadow-lg">Add New User</span>
                </button>

                <button @click="exportToCSV" class="group relative flex items-center justify-center w-10 h-10 md:w-12 md:h-12 bg-white dark:bg-slate-800 rounded-full border-2 border-slate-200 dark:border-slate-700 text-emerald-600 hover:border-emerald-600 hover:bg-emerald-50 dark:hover:bg-slate-700 transition shadow-sm focus:outline-none shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                    <span class="absolute bottom-full mb-2 md:bottom-auto md:left-full md:ml-3 md:mb-0 px-2 py-1 bg-slate-800 text-white text-[10px] font-bold rounded opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none whitespace-nowrap shadow-lg">Export CSV</span>
                </button>
            </aside>

            <div class="flex-1 min-w-0 w-full order-1 md:order-2">
                
                <div class="bg-white dark:bg-slate-800 p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm mb-4 flex flex-col lg:flex-row gap-2.5 items-stretch lg:items-center">
                    
                    <div class="relative flex-1 min-w-[200px]">
                        <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none">
                            <svg class="h-3.5 w-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                        </div>
                        <input v-model="searchQuery" type="text" placeholder="Search by name, email, or ID..." class="w-full h-8 pl-8 rounded-md bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-blue-500 focus:border-transparent text-xs shadow-sm transition-colors" />
                    </div>

                    <!-- DYNAMIC FILTER GRID: Snaps into a beautiful grid on mobile -->
                    <div class="grid grid-cols-2 sm:flex sm:flex-row gap-2 w-full lg:w-auto shrink-0 mt-1 lg:mt-0">
                        
                        <!-- Program Filter dynamically shows ONLY if the active tab has programs available -->
                        <div v-if="availablePrograms.length > 0" class="col-span-2 sm:col-span-1 shrink-0 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg px-2 py-1 shadow-sm flex items-center gap-1.5 min-w-[140px]">
                            <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                            <select v-model="filterProgram" class="bg-transparent border-none text-[9px] font-bold uppercase tracking-widest text-slate-600 dark:text-slate-300 w-full focus:ring-0 cursor-pointer p-0 m-0 truncate">
                                <option value="all">All Programs</option>
                                <option v-for="prog in availablePrograms" :key="prog" :value="prog">{{ prog }}</option>
                            </select>
                        </div>
                        
                        <!-- Year Filter dynamically shows ONLY if the active tab has users with year IDs -->
                        <div v-if="availableYears.length > 0" class="shrink-0 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg px-2 py-1 shadow-sm flex items-center gap-1.5 min-w-[120px]">
                            <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            <select v-model="filterYear" class="bg-transparent border-none text-[9px] font-bold uppercase tracking-widest text-slate-600 dark:text-slate-300 w-full focus:ring-0 cursor-pointer p-0 m-0 truncate">
                                <option value="all">All Years</option>
                                <option v-for="year in availableYears" :key="year" :value="year">{{ year }} Batches</option>
                            </select>
                        </div>
                        
                        <div class="shrink-0 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg px-2 py-1 shadow-sm flex items-center gap-1.5 min-w-[130px]" :class="{'col-span-2': availablePrograms.length === 0 && availableYears.length === 0}">
                            <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12"></path></svg>
                            <select v-model="sortBy" class="bg-transparent border-none text-[9px] font-bold uppercase tracking-widest text-slate-600 dark:text-slate-300 w-full focus:ring-0 cursor-pointer p-0 m-0 truncate">
                                <option value="newest">Newest First</option>
                                <option value="oldest">Oldest First</option>
                                <option value="name_asc">Name (A-Z)</option>
                                <option value="name_desc">Name (Z-A)</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="flex gap-4 border-b border-slate-200 dark:border-slate-700 mb-4 overflow-x-auto no-scrollbar">
                    <button v-for="tab in tabs" :key="tab.id" @click="activeTab = tab.id" class="pb-1.5 text-xs sm:text-sm font-bold border-b-2 transition-colors flex items-center gap-1.5 whitespace-nowrap" :class="activeTab === tab.id ? 'border-blue-600 text-blue-600 dark:text-blue-400 dark:border-blue-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300'">
                        {{ tab.name }}
                    </button>
                </div>

                <div v-if="activeTab === 'archive'" class="flex flex-wrap gap-2 mb-3">
                    <button v-for="subTab in subTabs" :key="subTab.id" @click="archiveSubTab = subTab.id" :class="[archiveSubTab === subTab.id ? 'bg-red-100 text-red-800 border-red-200 dark:bg-red-900/40 dark:text-red-300 dark:border-red-800/50' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50 dark:bg-slate-800/50 dark:text-slate-400 dark:border-slate-700/50']" class="px-3 py-1 text-[10px] font-bold rounded-full transition-all border shadow-sm">
                        {{ subTab.name }}
                    </button>
                </div>

                <transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0 -translate-y-2" enter-to-class="opacity-100 translate-y-0" leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100 translate-y-0" leave-to-class="opacity-0 -translate-y-2">
                    <div v-if="selectedIds.length > 0" class="flex flex-wrap items-center gap-2 bg-blue-50 dark:bg-blue-900/20 p-2.5 rounded-lg border border-blue-100 dark:border-blue-800 mb-4 shadow-sm">
                        <span class="text-[10px] font-black uppercase tracking-widest text-blue-700 dark:text-blue-400 mr-auto">{{ selectedIds.length }} Users Selected</span>
                        
                        <button v-if="activeTab === 'archive'" @click="openBulkSuspend('reactivate')" class="text-[9px] bg-white dark:bg-slate-800 text-emerald-600 border border-slate-200 dark:border-slate-700 px-3 py-1.5 rounded uppercase tracking-widest font-black shadow-sm hover:bg-emerald-50 transition">
                            Reactivate All
                        </button>
                        
                        <button v-if="activeTab !== 'archive'" @click="openBulkSuspend('suspend')" class="text-[9px] bg-white dark:bg-slate-800 text-red-600 border border-slate-200 dark:border-slate-700 px-3 py-1.5 rounded uppercase tracking-widest font-black shadow-sm hover:bg-red-50 transition">
                            Suspend All
                        </button>
                        
                        <button @click="openBulkDelete()" class="text-[9px] bg-red-600 hover:bg-red-500 text-white px-3 py-1.5 rounded uppercase tracking-widest font-black shadow-sm transition">
                            Delete All
                        </button>
                    </div>
                </transition>

                <div class="text-[9px] font-bold uppercase tracking-widest text-slate-400 mb-2 flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Tip: Double-click any user row to view their full profile card.
                </div>

                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden mb-8">
                    <div class="overflow-x-auto no-scrollbar">
                        <table class="w-full text-left text-[11px] sm:text-xs text-slate-500 dark:text-slate-400">
                            <thead class="text-[9px] uppercase font-bold text-slate-400 bg-slate-50 dark:bg-slate-900/30 border-b border-slate-100 dark:border-slate-700">
                                <tr>
                                    <th class="px-3 py-2 w-8">
                                        <input type="checkbox" :checked="isAllSelected && filteredUsers.length > 0" @change="toggleAll" class="rounded border-slate-300 dark:border-slate-600 text-blue-600 focus:ring-blue-500 dark:bg-slate-800 cursor-pointer shadow-sm" />
                                    </th>
                                    <th class="px-2 py-2 w-full sm:w-auto">User Details</th>
                                    <th class="px-2 py-2 hidden sm:table-cell">Status / ID</th>
                                    <th class="px-2 py-2 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                <tr v-for="user in filteredUsers" :key="user.id" @dblclick="openUserDetails(user)" class="transition select-none cursor-pointer" :class="selectedIds.includes(user.id) ? 'bg-blue-50/50 dark:bg-blue-900/10' : 'hover:bg-slate-50 dark:hover:bg-slate-700/50'" title="Double-click to view full profile">
                                    
                                    <td class="px-3 py-1.5" @dblclick.stop>
                                        <input type="checkbox" :checked="selectedIds.includes(user.id)" @change="toggleSelection(user.id)" class="rounded border-slate-300 dark:border-slate-600 text-blue-600 focus:ring-blue-500 dark:bg-slate-800 cursor-pointer shadow-sm" />
                                    </td>

                                    <td class="px-2 py-1.5 flex flex-col sm:table-cell">
                                        <div class="font-bold text-slate-900 dark:text-white truncate max-w-[150px] sm:max-w-xs leading-tight">{{ user.name }}</div>
                                        <div class="text-[10px] mt-0.5 truncate max-w-[150px] sm:max-w-xs leading-tight opacity-80">{{ user.email }}</div>
                                        
                                        <div class="sm:hidden mt-1 flex gap-2 items-center">
                                            <span class="font-mono text-[9px] text-slate-400">{{ user.school_id || 'No ID' }}</span>
                                            <span :class="user.status === 'suspended' ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700'" class="px-1 py-0.5 rounded text-[8px] font-bold uppercase">
                                                {{ user.status }}
                                            </span>
                                        </div>

                                        <div v-if="user.status === 'suspended'" class="mt-1 text-[9px] text-red-600 dark:text-red-400 font-medium max-w-[150px] sm:max-w-xs truncate bg-red-50 dark:bg-red-900/10 px-1 py-0.5 rounded inline-block border border-red-100 dark:border-red-900/30">
                                            Reason: {{ user.suspension_reason }}
                                        </div>
                                    </td>
                                    
                                    <td class="px-2 py-1.5 whitespace-nowrap hidden sm:table-cell">
                                        <div class="text-slate-700 dark:text-slate-300 font-mono text-[10px] mb-0.5">{{ user.school_id || 'N/A' }}</div>
                                        <span :class="[
                                            user.status === 'suspended' ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                                        ]" class="px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-tight leading-none">
                                            {{ user.status === 'suspended' ? 'Suspended' : 'Active' }}
                                        </span>
                                    </td>
                                    
                                    <td class="px-2 py-1.5 text-right align-middle" @dblclick.stop>
                                        <div class="flex items-center justify-end gap-1 flex-wrap sm:flex-nowrap min-w-[80px]">
                                            
                                            <button @click="openResetPasswordModal(user)" class="p-1.5 text-indigo-400 hover:text-indigo-600 bg-white hover:bg-indigo-50 dark:bg-transparent dark:hover:bg-indigo-900/30 rounded transition shadow-sm border border-transparent hover:border-indigo-200 dark:hover:border-indigo-800" title="Reset Password">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4v-3.252a1 1 0 01.293-.707l8.96-8.96A6 6 0 0115 5v2z" /></svg>
                                            </button>

                                            <button @click="openRoleModal(user)" class="p-1.5 text-blue-400 hover:text-blue-600 bg-white hover:bg-blue-50 dark:bg-transparent dark:hover:bg-blue-900/30 rounded transition shadow-sm border border-transparent hover:border-blue-200 dark:hover:border-blue-800" title="Change User Role">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" /></svg>
                                            </button>

                                            <button @click="openImpersonateModal(user)" class="flex items-center gap-1 rounded bg-amber-50 px-1.5 py-1 text-[9px] font-bold text-amber-600 transition hover:bg-amber-100" title="Login as this user">
                                                <svg class="h-3 w-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                                <span class="hidden lg:inline">Impersonate</span>
                                            </button>

                                            <button v-if="user.status === 'suspended'" @click="openBulkSuspend('reactivate', user.id)" class="bg-emerald-600 hover:bg-emerald-500 text-white px-2 py-1 rounded text-[9px] font-bold shadow-sm transition">
                                                Unsuspend
                                            </button>
                                            
                                            <button v-else @click="openBulkSuspend('suspend', user.id)" class="text-red-500 hover:text-red-700 text-[9px] font-bold bg-red-50 dark:bg-red-900/20 px-2 py-1 rounded border border-red-100 dark:border-red-900/30 transition">
                                                Suspend
                                            </button>

                                            <button @click="openBulkDelete(user.id)" class="p-1.5 text-slate-400 hover:text-red-600 bg-white hover:bg-red-50 dark:bg-transparent dark:hover:bg-red-900/30 rounded transition shadow-sm border border-transparent hover:border-red-200 dark:hover:border-red-800" title="Permanently Delete Account">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="filteredUsers.length === 0">
                                    <td colspan="4" class="px-2 py-8 text-center text-slate-400 dark:text-slate-500 text-[10px]">
                                        <div class="font-black uppercase tracking-widest mb-1 text-slate-300 dark:text-slate-600">No Records Found</div>
                                        <div class="font-medium">Try adjusting your search or filters.</div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>

        <!-- USER DETAILS CARD MODAL -->
        <Modal :show="isUserDetailsModalOpen" @close="isUserDetailsModalOpen = false" maxWidth="sm">
            <div class="relative bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                <div class="h-16 bg-gradient-to-r from-blue-500 to-indigo-600"></div>
                
                <button @click="isUserDetailsModalOpen = false" class="absolute top-2 right-2 text-white/80 hover:text-white bg-black/20 hover:bg-black/40 rounded-full p-1.5 transition z-10">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
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
                            <span :class="selectedUserDetails?.status === 'suspended' ? 'bg-red-100 text-red-700' : 'bg-emerald-100 text-emerald-700'" class="px-2 py-0.5 rounded text-[8px] font-black uppercase tracking-widest">
                                {{ selectedUserDetails?.status }}
                            </span>
                        </div>
                    </div>

                    <div class="bg-slate-50 dark:bg-slate-900/50 rounded-lg border border-slate-100 dark:border-slate-700/50 p-3 space-y-2.5">
                        <div class="flex justify-between items-center border-b border-slate-200 dark:border-slate-700 pb-2">
                            <span class="text-[9px] font-bold uppercase tracking-widest text-slate-400">School ID</span>
                            <span class="text-[10px] font-black text-slate-800 dark:text-slate-200 font-mono">{{ selectedUserDetails?.school_id || 'N/A' }}</span>
                        </div>
                        <div class="flex justify-between items-center border-b border-slate-200 dark:border-slate-700 pb-2">
                            <span class="text-[9px] font-bold uppercase tracking-widest text-slate-400">Program / Dept</span>
                            <span class="text-[10px] font-black text-slate-800 dark:text-slate-200 text-right max-w-[60%] truncate">{{ selectedUserDetails?.program || 'N/A' }}</span>
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

        <Modal :show="isCreateModalOpen" :closeable="false" @close="isCreateModalOpen = false">
            <div class="p-5 bg-white dark:bg-slate-800 rounded-lg">
                <h2 class="text-base font-bold text-slate-900 dark:text-white mb-4">Create New Account</h2>
                
                <div class="flex gap-4 border-b border-slate-200 dark:border-slate-700 mb-4 overflow-x-auto no-scrollbar">
                    <button type="button" @click="form.role = 'teacher'" :class="{'border-blue-600 text-blue-600 dark:text-blue-400': form.role === 'teacher', 'border-transparent text-slate-500': form.role !== 'teacher'}" class="pb-1.5 text-xs font-bold border-b-2 transition-colors whitespace-nowrap">Teacher</button>
                    <button type="button" @click="form.role = 'student'" :class="{'border-blue-600 text-blue-600 dark:text-blue-400': form.role === 'student', 'border-transparent text-slate-500': form.role !== 'student'}" class="pb-1.5 text-xs font-bold border-b-2 transition-colors whitespace-nowrap">Student</button>
                    <button type="button" @click="form.role = 'admin'" :class="{'border-blue-600 text-blue-600 dark:text-blue-400': form.role === 'admin', 'border-transparent text-slate-500': form.role !== 'admin'}" class="pb-1.5 text-xs font-bold border-b-2 transition-colors whitespace-nowrap">Admin</button>
                </div>

                <form @submit.prevent="submitUser" class="space-y-3">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <InputLabel for="name" value="Full Name" class="text-[9px] font-bold uppercase text-slate-500 mb-1" />
                            <input id="name" v-model="form.name" type="text" :class="inputClass" required />
                            <InputError :message="form.errors.name" class="mt-1 text-[10px]" />
                        </div>
                        <div>
                            <InputLabel for="email" value="Email Address" class="text-[9px] font-bold uppercase text-slate-500 mb-1" />
                            <input id="email" v-model="form.email" type="email" :class="inputClass" required />
                            <InputError :message="form.errors.email" class="mt-1 text-[10px]" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3" v-if="form.role !== 'admin'">
                        <div>
                            <div class="flex justify-between items-end mb-1">
                                <InputLabel for="school_id" value="ID Number" class="text-[9px] font-bold uppercase text-slate-500" />
                                <button type="button" @click="generateSchoolId" class="text-[9px] text-blue-600 dark:text-blue-400 font-bold hover:underline">Auto-Year ID</button>
                            </div>
                            <input id="school_id" v-model="form.school_id" type="text" :class="inputClass" :required="form.role !== 'admin'" />
                            <InputError :message="form.errors.school_id" class="mt-1 text-[10px]" />
                        </div>
                        
                        <div v-if="form.role === 'student'">
                            <InputLabel for="program" value="Program / Course" class="text-[9px] font-bold uppercase text-slate-500 mb-1" />
                            <select id="program" v-model="form.program" :class="inputClass" required class="cursor-pointer">
                                <option value="" disabled>Select a Program...</option>
                                <option value="BS Information Technology">BS Information Technology</option>
                                <option value="BS Computer Science">BS Computer Science</option>
                                <option value="BS Education">BS Education</option>
                                <option value="BS Business Administration">BS Business Administration</option>
                                <option value="BS Accountancy">BS Accountancy</option>
                                <option value="Other">Other</option>
                            </select>
                            <InputError :message="form.errors.program" class="mt-1 text-[10px]" />
                        </div>

                        <div :class="{'col-span-2': form.role === 'teacher'}">
                            <InputLabel for="contact_number" value="Mobile Number" class="text-[9px] font-bold uppercase text-slate-500 mb-1" />
                            <input id="contact_number" v-model="form.contact_number" type="text" :class="inputClass" />
                            <InputError :message="form.errors.contact_number" class="mt-1 text-[10px]" />
                        </div>
                    </div>

                    <div>
                        <div class="flex justify-between items-end mb-1">
                            <InputLabel for="password" value="Temporary Password" class="text-[9px] font-bold uppercase text-slate-500" />
                            <button type="button" @click="generatePassword" class="text-[9px] text-blue-600 dark:text-blue-400 font-bold hover:underline">Auto-Generate</button>
                        </div>
                        <input id="password" v-model="form.password" type="text" :class="inputClass" placeholder="Type or generate a secure password" required />
                        <InputError :message="form.errors.password" class="mt-1 text-[10px]" />
                        <p class="text-[9px] text-slate-500 mt-1">This account will be auto-verified. The user can log in immediately with this password.</p>
                    </div>

                    <div class="mt-5 flex justify-end gap-2">
                        <button type="button" @click="isCreateModalOpen = false" class="text-xs text-slate-500 px-3 py-1.5 font-bold hover:text-slate-700">Cancel</button>
                        <button :disabled="form.processing" class="bg-blue-600 hover:bg-blue-500 text-white px-3 py-1.5 rounded text-xs font-bold shadow-sm">Create Verified Account</button>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="isImpersonateModalOpen" :closeable="false" @close="isImpersonateModalOpen = false" maxWidth="sm">
            <div class="p-5 bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-slate-200 dark:border-slate-700">
                <h2 class="text-sm font-black uppercase tracking-tight text-amber-600 flex items-center gap-2 mb-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                    Impersonate Security Check
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">
                    You are about to log in as <strong class="text-amber-600">{{ selectedUserForImpersonate?.name }}</strong>. Please provide your admin password to proceed.
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

        <Modal :show="isBulkDeleteModalOpen" :closeable="false" @close="isBulkDeleteModalOpen = false" maxWidth="sm">
            <div class="p-5 bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-slate-200 dark:border-slate-700">
                <h2 class="text-sm font-black uppercase tracking-tight text-red-600 flex items-center gap-2 mb-2">
                    <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    Confirm Permanent Deletion
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
                        <button :disabled="bulkDeleteForm.processing" class="bg-red-600 hover:bg-red-500 text-white px-4 py-1.5 rounded text-[10px] uppercase tracking-widest font-black shadow-sm transition">Permanently Delete</button>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="isBulkSuspendModalOpen" :closeable="false" @close="isBulkSuspendModalOpen = false" maxWidth="sm">
            <div class="p-5 bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-slate-200 dark:border-slate-700">
                <h2 class="text-sm font-black uppercase tracking-tight mb-2 flex items-center gap-2" :class="bulkSuspendForm.action === 'suspend' ? 'text-red-600' : 'text-emerald-600'">
                    <svg v-if="bulkSuspendForm.action === 'suspend'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
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

        <Modal :show="isResetPasswordModalOpen" :closeable="false" @close="isResetPasswordModalOpen = false" maxWidth="sm">
            <div class="p-5 bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-slate-200 dark:border-slate-700">
                <h2 class="text-sm font-black uppercase tracking-tight text-slate-900 dark:text-white flex items-center gap-2 mb-2">
                    <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4v-3.252a1 1 0 01.293-.707l8.96-8.96A6 6 0 0115 5v2z"></path></svg>
                    Reset User Password
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">
                    Generate a new password for <strong class="text-indigo-600 dark:text-indigo-400">{{ selectedUserForPassword?.name }}</strong>.
                </p>
                
                <form @submit.prevent="submitResetPassword" class="space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="col-span-2">
                            <div class="flex justify-between items-end mb-1">
                                <InputLabel value="New Temporary Password *" class="text-[9px] font-bold uppercase text-slate-500" />
                                <button type="button" @click="generateResetPassword" class="text-[9px] text-indigo-600 dark:text-indigo-400 font-bold hover:underline">Auto-Generate</button>
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
                <h2 class="text-sm font-black uppercase tracking-tight text-slate-900 dark:text-white flex items-center gap-2 mb-2">
                    <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                    Role Security Check
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">
                    Updating <strong class="text-blue-600 dark:text-blue-400">{{ selectedUserForRole?.name }}</strong>. Please confirm with your admin password.
                </p>
                
                <form @submit.prevent="submitRole" class="space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div class="col-span-2">
                            <InputLabel value="Select New Role" class="text-[9px] font-bold uppercase text-slate-500 mb-1" />
                            <select v-model="roleForm.role" :class="inputClass" class="cursor-pointer">
                                <option value="student">Student</option>
                                <option value="teacher">Teacher</option>
                                <option value="admin">Administrator</option>
                            </select>
                            <InputError :message="roleForm.errors.role" class="mt-1 text-[9px]" />
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
</style>