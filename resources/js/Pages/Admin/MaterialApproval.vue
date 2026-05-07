<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import Modal from '@/Components/Modal.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({ 
    materials: Array,
    requireApproval: Boolean 
});

const activeTab = ref('pending');
const selectedIds = ref([]);
const isProcessingBulk = ref(false); 

const showMaterialPreview = ref(false);
const selectedMaterialPath = ref(null);

const sortOrder = ref('desc');
const selectedCourseFilter = ref('all');
const showCourseDropdown = ref(false);

const openMaterialPreview = (path) => {
    selectedMaterialPath.value = path;
    showMaterialPreview.value = true;
};

const setCourseFilter = (id) => {
    selectedCourseFilter.value = id;
    showCourseDropdown.value = false;
};

const availableCourses = computed(() => {
    const courses = {};
    props.materials.forEach(m => {
        if (m.course) {
            if (!courses[m.course.id]) {
                courses[m.course.id] = { title: m.course.title, pendingCount: 0 };
            }
            if (m.approval_status === 'pending') {
                courses[m.course.id].pendingCount++;
            }
        }
    });
    return Object.entries(courses).map(([id, data]) => ({ 
        id: Number(id), 
        title: data.title,
        pendingCount: data.pendingCount
    }));
});

const showEditModal = ref(false);
const lessonToEdit = ref(null);

const editForm = useForm({
    title: '',
    available_from: '',
    available_until: ''
});

const formatForInput = (dateStr) => {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return '';
    return new Date(d.getTime() - (d.getTimezoneOffset() * 60000)).toISOString().slice(0, 16);
};

const openEditModal = (lesson) => {
    lessonToEdit.value = lesson;
    editForm.title = lesson.title;
    editForm.available_from = formatForInput(lesson.available_from) || formatForInput(new Date());
    editForm.available_until = formatForInput(lesson.available_until);
    editForm.clearErrors();
    showEditModal.value = true;
};

const submitEdit = () => {
    editForm.patch(route('admin.lessons.update', lessonToEdit.value.id), {
        preserveScroll: true,
        onSuccess: () => { showEditModal.value = false; lessonToEdit.value = null; }
    });
};

const selectedLesson = ref(null);
const rejectionForm = useForm({ reason: '' });

const openRejectModal = (lesson) => {
    selectedLesson.value = lesson;
    rejectionForm.reason = lesson.rejection_note || lesson.rejection_reason || lesson.reason || ''; 
};

const submitRejection = () => {
    rejectionForm.patch(route('admin.lessons.reject', selectedLesson.value.id), {
        preserveScroll: true,
        onSuccess: () => { selectedLesson.value = null; rejectionForm.reset(); },
    });
};

const showUnarchiveModal = ref(false);
const materialToUnarchive = ref(null);

const formUnarchive = useForm({
    available_from: '',
    available_until: ''
});

const openUnarchiveModal = (material) => {
    materialToUnarchive.value = material;
    formUnarchive.available_from = formatForInput(material.available_from) || formatForInput(new Date());
    formUnarchive.available_until = '';
    formUnarchive.clearErrors();
    showUnarchiveModal.value = true;
};

const submitUnarchive = () => {
    formUnarchive.patch(route('admin.lessons.unarchive', materialToUnarchive.value.id), {
        preserveScroll: true,
        onSuccess: () => { showUnarchiveModal.value = false; materialToUnarchive.value = null; }
    });
};

const baseFilteredMaterials = computed(() => {
    if (selectedCourseFilter.value === 'all') {
        return props.materials;
    }
    return props.materials.filter(m => m.course_id === selectedCourseFilter.value);
});

const pendingMaterials = computed(() => baseFilteredMaterials.value.filter(m => m.approval_status === 'pending'));
const rejectedMaterials = computed(() => baseFilteredMaterials.value.filter(m => m.approval_status === 'rejected'));
const approvedMaterials = computed(() => {
    const now = new Date();
    return baseFilteredMaterials.value.filter(m => m.approval_status === 'approved' && (!m.available_until || new Date(m.available_until) > now));
});
const archivedMaterials = computed(() => {
    const now = new Date();
    return baseFilteredMaterials.value.filter(m => m.approval_status === 'approved' && m.available_until && new Date(m.available_until) <= now);
});

const displayedMaterials = computed(() => {
    let activeList = [];
    
    if (activeTab.value === 'pending') activeList = pendingMaterials.value;
    else if (activeTab.value === 'approved') activeList = approvedMaterials.value;
    else if (activeTab.value === 'rejected') activeList = rejectedMaterials.value;
    else if (activeTab.value === 'archived') activeList = archivedMaterials.value;

    return [...activeList].sort((a, b) => {
        const dateA = new Date(a.created_at || 0).getTime();
        const dateB = new Date(b.created_at || 0).getTime();
        return sortOrder.value === 'desc' ? dateB - dateA : dateA - dateB;
    });
});

const groupedMaterials = computed(() => {
    const groups = {};
    displayedMaterials.value.forEach(material => {
        const courseName = material.course?.title || 'Unassigned / Deleted Course';
        if (!groups[courseName]) {
            groups[courseName] = { teacher: material.course?.teacher?.name, items: [] };
        }
        groups[courseName].items.push(material);
    });
    return groups;
});

watch(activeTab, () => { selectedIds.value = []; });
watch(selectedCourseFilter, () => { selectedIds.value = []; });

const toggleSelection = (id) => {
    if (selectedIds.value.includes(id)) selectedIds.value = selectedIds.value.filter(i => i !== id);
    else selectedIds.value.push(id);
};

const isAllSelected = computed(() => {
    if (displayedMaterials.value.length === 0) return false;
    return selectedIds.value.length === displayedMaterials.value.length;
});

const toggleAll = () => {
    if (isAllSelected.value) {
        selectedIds.value = [];
    } else {
        selectedIds.value = displayedMaterials.value.map(m => m.id);
    }
};

const bulkAction = async (action, method = 'patch') => {
    if (!confirm(`Are you sure you want to ${action} ${selectedIds.value.length} selected item(s)?`)) return;
    
    isProcessingBulk.value = true;
    try {
        for (let id of selectedIds.value) {
            if (action === 'destroy') {
                await axios.delete(route(`teacher.lessons.destroy`, id));
            } else {
                await axios[method](route(`admin.lessons.${action}`, id));
            }
        }
        router.reload({ only: ['materials'], onSuccess: () => selectedIds.value = [] });
    } catch (e) {
        console.error(e);
        alert("An error occurred while processing some items.");
    } finally {
        isProcessingBulk.value = false;
    }
};

const bulkApprove = () => {
    router.post(route('admin.lessons.bulk-approve'), { lesson_ids: selectedIds.value }, { preserveScroll: true, onSuccess: () => { selectedIds.value = []; }});
};

const confirmingToggle = ref(false);
const toggleForm = useForm({ password: '' });

const openToggleConfirm = () => {
    toggleForm.password = ''; 
    toggleForm.clearErrors(); 
    confirmingToggle.value = true; 
};

const submitToggle = () => {
    toggleForm.post(route('admin.settings.material-approval'), {
        preserveScroll: true,
        onSuccess: () => {
            confirmingToggle.value = false;
            toggleForm.reset();
        },
    });
};

const approveMaterial = (id) => router.patch(route('admin.lessons.approve', id), {}, { preserveScroll: true });
const archiveMaterial = (id) => { if(confirm('Force this material into the archive?')) router.patch(route('admin.lessons.archive', id), {}, { preserveScroll: true }); };
const deleteMaterial = (id) => { if(confirm('Permanently delete this material?')) router.delete(route('teacher.lessons.destroy', id), { preserveScroll: true }); };

const inputClass = "w-full rounded-md bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:ring-2 focus:ring-blue-500 focus:border-transparent py-1.5 px-3 text-xs shadow-sm transition-colors duration-200";
</script>

<template>
    <Head title="Material Approval" />

    <AuthenticatedLayout>
        <div class="mb-3 flex flex-col md:flex-row md:items-end justify-between gap-2">
            <div>
                <h1 class="text-lg sm:text-xl font-black text-slate-900 dark:text-white tracking-tight">Material Approval</h1>
                <p class="text-[9px] font-bold text-slate-500 uppercase tracking-widest mt-0.5">Manage Course Uploads</p>
            </div>
            
            <div class="flex items-center gap-3">
                <div class="bg-white dark:bg-slate-800 px-2 py-1.5 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm flex items-center gap-2">
                    <div class="flex flex-col text-right">
                        <span class="text-[8px] sm:text-[9px] font-black uppercase tracking-widest text-slate-700 dark:text-slate-300 leading-none">Require Approval</span>
                        <span class="text-[7px] sm:text-[8px] font-bold text-slate-400 uppercase mt-0.5">{{ requireApproval ? 'Active' : 'Auto-Approve' }}</span>
                    </div>
                    <button @click="openToggleConfirm" :class="requireApproval ? 'bg-emerald-500' : 'bg-slate-300 dark:bg-slate-600'" class="relative inline-flex h-4 w-8 sm:h-5 sm:w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none shadow-inner">
                        <span :class="requireApproval ? 'translate-x-4' : 'translate-x-0'" class="pointer-events-none inline-block h-3 w-3 sm:h-4 sm:w-4 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span>
                    </button>
                </div>
            </div>
        </div>

        <div class="max-w-6xl space-y-3">
            
            <div class="flex flex-col lg:flex-row gap-2">
                <!-- TABS (Overflow auto enabled here) -->
                <div class="flex p-1 bg-slate-100 dark:bg-slate-800 rounded-lg gap-1 overflow-x-auto shadow-sm border border-slate-200 dark:border-slate-700 flex-1 scrollbar-hide">
                    <button @click="activeTab = 'pending'" class="shrink-0 px-3 py-1.5 text-[9px] font-black uppercase tracking-widest rounded-md transition-all flex items-center justify-center gap-1" :class="activeTab === 'pending' ? 'bg-white dark:bg-slate-700 text-orange-600 shadow-sm' : 'text-slate-500 hover:text-slate-700'">
                        Pending <span v-if="pendingMaterials.length" class="bg-orange-100 text-orange-600 px-1 rounded text-[8px]">{{ pendingMaterials.length }}</span>
                    </button>
                    <button @click="activeTab = 'approved'" class="shrink-0 px-3 py-1.5 text-[9px] font-black uppercase tracking-widest rounded-md transition-all flex items-center justify-center gap-1" :class="activeTab === 'approved' ? 'bg-white dark:bg-slate-700 text-emerald-600 shadow-sm' : 'text-slate-500 hover:text-slate-700'">
                        Approved <span v-if="approvedMaterials.length" class="bg-emerald-100 text-emerald-600 px-1 rounded text-[8px]">{{ approvedMaterials.length }}</span>
                    </button>
                    <button @click="activeTab = 'rejected'" class="shrink-0 px-3 py-1.5 text-[9px] font-black uppercase tracking-widest rounded-md transition-all flex items-center justify-center gap-1" :class="activeTab === 'rejected' ? 'bg-white dark:bg-slate-700 text-red-600 shadow-sm' : 'text-slate-500 hover:text-slate-700'">
                        Rejected <span v-if="rejectedMaterials.length" class="bg-red-100 text-red-600 px-1 rounded text-[8px]">{{ rejectedMaterials.length }}</span>
                    </button>
                    <button @click="activeTab = 'archived'" class="shrink-0 px-3 py-1.5 text-[9px] font-black uppercase tracking-widest rounded-md transition-all flex items-center justify-center gap-1" :class="activeTab === 'archived' ? 'bg-white dark:bg-slate-700 text-slate-800 dark:text-slate-200 shadow-sm' : 'text-slate-500 hover:text-slate-700'">
                        Archived <span v-if="archivedMaterials.length" class="bg-slate-200 text-slate-700 dark:bg-slate-700 dark:text-slate-300 px-1 rounded text-[8px]">{{ archivedMaterials.length }}</span>
                    </button>
                </div>

                <!-- FILTERS (Flex-wrap instead of overflow hidden to prevent cutoff dropdowns) -->
                <div class="flex gap-2 w-full lg:w-auto flex-wrap sm:flex-nowrap relative z-50">
                    
                    <!-- CUSTOM COURSE FILTER DROPDOWN -->
                    <div class="shrink-0 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg px-2 py-1 shadow-sm flex items-center gap-1.5 min-w-[140px] flex-1 sm:flex-none relative cursor-pointer select-none" @click="showCourseDropdown = !showCourseDropdown">
                        
                        <!-- Notification dot indicating dropdown has pending items -->
                        <span v-if="pendingMaterials.length > 0 && selectedCourseFilter === 'all'" class="absolute -top-1 -right-1 w-2.5 h-2.5 bg-red-500 rounded-full animate-pulse shadow-sm border border-white dark:border-slate-800"></span>
                        <span v-else-if="selectedCourseFilter !== 'all' && availableCourses.find(c => c.id === selectedCourseFilter)?.pendingCount > 0" class="absolute -top-1 -right-1 w-2.5 h-2.5 bg-red-500 rounded-full animate-pulse shadow-sm border border-white dark:border-slate-800"></span>
                        
                        <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path></svg>
                        
                        <div class="text-[9px] font-bold uppercase tracking-widest text-slate-600 dark:text-slate-300 w-full flex justify-between items-center py-0.5">
                            <span class="truncate pr-1">
                                {{ selectedCourseFilter === 'all' ? 'All Courses' : availableCourses.find(c => c.id === selectedCourseFilter)?.title }}
                            </span>
                            <!-- Active selection badge -->
                            <span v-if="selectedCourseFilter !== 'all' && availableCourses.find(c => c.id === selectedCourseFilter)?.pendingCount > 0" class="bg-red-500 text-white px-1 py-0.5 rounded text-[7px] shadow-sm shrink-0">
                                {{ availableCourses.find(c => c.id === selectedCourseFilter)?.pendingCount }}
                            </span>
                            <span v-else-if="selectedCourseFilter === 'all' && props.materials.filter(m => m.approval_status === 'pending').length > 0" class="bg-red-500 text-white px-1 py-0.5 rounded text-[7px] shadow-sm shrink-0">
                                {{ props.materials.filter(m => m.approval_status === 'pending').length }}
                            </span>
                        </div>

                        <!-- Invisible backdrop to close dropdown when clicking outside -->
                        <div v-if="showCourseDropdown" @click.stop="showCourseDropdown = false" class="fixed inset-0 z-40"></div>

                        <!-- Custom Dropdown Options -->
                        <transition enter-active-class="transition ease-out duration-100" enter-from-class="opacity-0 scale-95" enter-to-class="opacity-100 scale-100" leave-active-class="transition ease-in duration-75" leave-from-class="opacity-100 scale-100" leave-to-class="opacity-0 scale-95">
                            <div v-if="showCourseDropdown" class="absolute top-full left-0 mt-1 w-full sm:w-60 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg shadow-xl z-[60] py-1 overflow-y-auto max-h-48 custom-scrollbar">
                                <div @click.stop="setCourseFilter('all')" class="px-2.5 py-2 text-[9px] font-bold uppercase tracking-widest text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition flex justify-between items-center cursor-pointer border-b border-slate-100 dark:border-slate-700/50">
                                    <span>All Courses</span>
                                    <span v-if="props.materials.filter(m => m.approval_status === 'pending').length > 0" class="bg-red-500 text-white px-1.5 py-0.5 rounded shadow-sm shrink-0 text-[8px]">{{ props.materials.filter(m => m.approval_status === 'pending').length }}</span>
                                </div>
                                <div v-for="c in availableCourses" :key="c.id" @click.stop="setCourseFilter(c.id)" class="px-2.5 py-2 text-[9px] font-bold uppercase tracking-widest text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition flex justify-between items-center cursor-pointer">
                                    <span class="truncate pr-2">{{ c.title }}</span>
                                    <span v-if="c.pendingCount > 0" class="bg-red-500 text-white px-1.5 py-0.5 rounded shadow-sm shrink-0 text-[8px]">{{ c.pendingCount }}</span>
                                </div>
                            </div>
                        </transition>
                    </div>

                    <!-- SORT FILTER -->
                    <div class="shrink-0 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg px-2 py-1 shadow-sm flex items-center gap-1.5 min-w-[130px] flex-1 sm:flex-none relative z-[50]">
                        <svg class="w-3.5 h-3.5 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12"></path></svg>
                        <select v-model="sortOrder" class="bg-transparent border-none text-[9px] font-bold uppercase tracking-widest text-slate-600 dark:text-slate-300 w-full focus:ring-0 cursor-pointer p-0 m-0">
                            <option value="desc">Newest First</option>
                            <option value="asc">Oldest First</option>
                        </select>
                    </div>
                </div>
            </div>

            <transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0 -translate-y-2" enter-to-class="opacity-100 translate-y-0" leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100 translate-y-0" leave-to-class="opacity-0 -translate-y-2">
                <div v-if="selectedIds.length > 0" class="flex flex-wrap items-center gap-2 bg-blue-50 dark:bg-blue-900/20 p-2.5 rounded-lg border border-blue-100 dark:border-blue-800 shadow-sm relative z-0">
                    <span class="text-[10px] font-black uppercase tracking-widest text-blue-700 dark:text-blue-400 mr-auto flex items-center gap-2">
                        <svg v-if="isProcessingBulk" class="animate-spin w-3 h-3 text-blue-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        {{ selectedIds.length }} Selected
                    </span>
                    
                    <button v-if="['pending', 'rejected'].includes(activeTab)" @click="bulkApprove" :disabled="isProcessingBulk" class="text-[9px] bg-emerald-600 hover:bg-emerald-500 text-white px-3 py-1.5 rounded uppercase tracking-widest font-black shadow-sm transition disabled:opacity-50">
                        Approve All
                    </button>

                    <button v-if="['pending', 'approved'].includes(activeTab)" @click="bulkAction('reject')" :disabled="isProcessingBulk" class="text-[9px] bg-red-100 hover:bg-red-200 text-red-700 px-3 py-1.5 rounded uppercase tracking-widest font-black shadow-sm transition disabled:opacity-50">
                        Reject All
                    </button>

                    <button v-if="activeTab === 'approved'" @click="bulkAction('archive')" :disabled="isProcessingBulk" class="text-[9px] bg-orange-100 hover:bg-orange-200 text-orange-700 px-3 py-1.5 rounded uppercase tracking-widest font-black shadow-sm transition disabled:opacity-50">
                        Archive All
                    </button>

                    <button v-if="['rejected', 'archived'].includes(activeTab)" @click="bulkAction('destroy', 'delete')" :disabled="isProcessingBulk" class="text-[9px] bg-red-600 hover:bg-red-500 text-white px-3 py-1.5 rounded uppercase tracking-widest font-black shadow-sm transition disabled:opacity-50">
                        Delete All
                    </button>
                </div>
            </transition>

            <div v-if="displayedMaterials.length > 0" class="flex items-center gap-2 px-1 relative z-0">
                <input type="checkbox" :checked="isAllSelected" @change="toggleAll" id="selectAll" class="w-4 h-4 text-emerald-600 rounded border-slate-300 focus:ring-emerald-500 cursor-pointer shadow-sm">
                <label for="selectAll" class="text-[10px] font-black uppercase tracking-widest text-slate-500 cursor-pointer select-none">
                    Select All in Tab
                </label>
            </div>

            <div v-if="Object.keys(groupedMaterials).length > 0" class="space-y-4 relative z-0">
                <div v-for="(group, courseName) in groupedMaterials" :key="courseName" class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
                    
                    <div class="bg-slate-50 dark:bg-slate-900/50 p-2.5 px-3 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2 overflow-hidden">
                            <div class="w-6 h-6 rounded bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                            </div>
                            <div class="min-w-0">
                                <h3 class="font-black text-xs text-slate-800 dark:text-slate-100 uppercase tracking-tight truncate">{{ courseName }}</h3>
                                <p class="text-[8px] font-bold text-slate-500 uppercase tracking-widest mt-0.5 truncate">Tchr: {{ group.teacher || 'Unknown' }}</p>
                            </div>
                        </div>
                        <span class="text-[9px] font-black bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 px-1.5 py-0.5 rounded text-slate-500 shadow-sm shrink-0">{{ group.items.length }} Items</span>
                    </div>

                    <div class="divide-y divide-slate-100 dark:divide-slate-700/50">
                        <div v-for="material in group.items" :key="material.id" class="p-3 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 transition-colors cursor-pointer" :class="selectedIds.includes(material.id) ? 'bg-emerald-50/30 dark:bg-emerald-900/10' : 'hover:bg-slate-50/50 dark:hover:bg-slate-800/50'" @click="toggleSelection(material.id)">
                            
                            <div class="flex items-center gap-2.5 min-w-0 w-full sm:w-auto">
                                <div class="shrink-0">
                                    <input type="checkbox" :checked="selectedIds.includes(material.id)" @click.stop @change="toggleSelection(material.id)" class="w-4 h-4 text-emerald-600 rounded border-slate-300 focus:ring-emerald-500 cursor-pointer shadow-sm">
                                </div>
                                <div class="flex flex-col min-w-0 w-full">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="text-sm font-bold text-slate-900 dark:text-white truncate">{{ material.title }}</span>
                                        
                                        <span v-if="activeTab === 'archived'" class="text-[8px] bg-slate-100 text-slate-600 border border-slate-200 px-1.5 py-0.5 rounded uppercase font-black tracking-widest shrink-0">Archived</span>
                                        <span v-else-if="material.approval_status === 'pending' && requireApproval" class="text-[8px] bg-orange-100 text-orange-700 border border-orange-200 px-1.5 py-0.5 rounded uppercase font-black tracking-widest shrink-0">Pending</span>
                                        <span v-else-if="material.approval_status === 'rejected'" class="text-[8px] bg-red-100 text-red-700 border border-red-200 px-1.5 py-0.5 rounded uppercase font-black tracking-widest shrink-0">Rejected</span>
                                        <span v-else-if="material.approval_status === 'approved'" class="text-[8px] bg-emerald-100 text-emerald-700 border border-emerald-200 px-1.5 py-0.5 rounded uppercase font-black tracking-widest shrink-0">Approved</span>
                                    </div>

                                    <div class="flex items-center gap-2 text-[9px] font-bold text-slate-400 uppercase tracking-wider">
                                        <span v-if="material.available_from">From: {{ new Date(material.available_from).toLocaleDateString() }}</span>
                                        <span v-if="material.available_until && activeTab !== 'archived'" class="text-red-400">Closes: {{ new Date(material.available_until).toLocaleDateString() }}</span>
                                        <span v-if="activeTab === 'archived'" class="text-slate-500">Archived On: {{ material.available_until ? new Date(material.available_until).toLocaleDateString() : 'N/A' }}</span>
                                        <span v-if="!material.available_from && !material.available_until && activeTab !== 'archived'">Always Available</span>
                                    </div>

                                    <div v-if="material.approval_status === 'rejected'" class="mt-2 p-2 bg-red-50 dark:bg-red-900/20 border border-red-100 dark:border-red-800/30 rounded-lg max-w-lg">
                                        <div class="flex items-center gap-1.5 mb-1">
                                            <svg class="w-3 h-3 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                            <span class="text-[9px] font-black uppercase text-red-600 tracking-widest">Rejection Reason</span>
                                        </div>
                                        <p class="text-[10px] text-red-700 dark:text-red-300 italic">{{ material.rejection_reason || material.rejection_note || material.reason || 'No specific reason provided.' }}</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex items-center gap-1.5 shrink-0 self-end sm:self-auto" @click.stop>
                                <button @click="openMaterialPreview(material.attachment_path)" title="Preview Material" class="p-1.5 text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-slate-700 rounded hover:bg-blue-50 hover:text-blue-600 dark:hover:bg-blue-900/30 transition border border-transparent shadow-sm">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                </button>
                                <a :href="`/storage/${material.attachment_path}`" download title="Download Material" class="p-1.5 text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/30 rounded hover:bg-blue-100 dark:hover:bg-blue-800/50 transition border border-transparent shadow-sm">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                                </a>
                                
                                <button @click="openEditModal(material)" class="text-[9px] font-bold uppercase tracking-wide bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 px-2 py-1.5 rounded flex items-center gap-1 hover:bg-slate-200 transition">
                                    Edit
                                </button>

                                <button v-if="activeTab === 'pending' || activeTab === 'rejected'" @click="approveMaterial(material.id)" class="text-[9px] font-bold uppercase tracking-wide bg-emerald-600 text-white px-2 py-1.5 rounded hover:bg-emerald-500 shadow-sm transition">Approve</button>
                                
                                <button v-if="activeTab === 'pending' || activeTab === 'approved'" @click="openRejectModal(material)" class="text-[9px] font-bold uppercase tracking-wide bg-red-100 text-red-600 px-2 py-1.5 rounded hover:bg-red-200 shadow-sm transition">Reject</button>
                                
                                <button v-if="activeTab === 'approved'" @click="archiveMaterial(material.id)" class="text-[9px] font-bold uppercase tracking-wide bg-orange-100 text-orange-600 px-2 py-1.5 rounded hover:bg-orange-200 shadow-sm transition">Archive</button>
                                
                                <button v-if="activeTab === 'archived'" @click="openUnarchiveModal(material)" class="text-[9px] font-bold uppercase tracking-wide bg-blue-100 text-blue-600 px-2 py-1.5 rounded hover:bg-blue-200 shadow-sm transition">Unarchive</button>
                                
                                <button @click="deleteMaterial(material.id)" class="p-1.5 text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded transition" title="Delete">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div v-else class="text-center py-12 bg-slate-50 dark:bg-slate-900/30 border border-dashed border-slate-200 dark:border-slate-700 rounded-xl relative z-0">
                <p class="text-slate-400 text-[10px] font-black uppercase tracking-widest">No materials found.</p>
            </div>
        </div>

        <Modal :show="showMaterialPreview" @close="showMaterialPreview = false" maxWidth="4xl">
            <div class="bg-white dark:bg-slate-900 rounded-2xl overflow-hidden shadow-2xl flex flex-col h-[85vh]">
                <div class="p-4 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center bg-slate-50 dark:bg-slate-900 shrink-0">
                    <h3 class="font-black text-sm text-slate-900 dark:text-white flex items-center gap-2 uppercase tracking-tight">
                        <div class="w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                        </div>
                        Material Preview
                    </h3>
                    <button @click="showMaterialPreview = false" class="w-8 h-8 flex items-center justify-center rounded-full bg-slate-200 dark:bg-slate-700 text-slate-500 dark:text-slate-300 hover:bg-slate-300 transition shrink-0">&times;</button>
                </div>
                
                <div class="flex-1 p-4 bg-slate-100 dark:bg-slate-950/50 flex flex-col items-center justify-center relative overflow-hidden">
                    <iframe v-if="selectedMaterialPath?.toLowerCase().endsWith('.pdf')" :src="`/storage/${selectedMaterialPath}`" class="w-full h-full border-none rounded-lg shadow-sm bg-white dark:bg-slate-900"></iframe>
                    <img v-else-if="selectedMaterialPath?.match(/\.(jpeg|jpg|png|gif)$/i)" :src="`/storage/${selectedMaterialPath}`" class="max-w-full max-h-full object-contain rounded-lg shadow-sm" />
                    
                    <div v-else class="text-center p-8 bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 max-w-sm w-full">
                        <svg class="w-16 h-16 text-slate-300 dark:text-slate-600 mb-4 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                        <p class="text-slate-500 font-black mb-1 text-[11px] uppercase tracking-widest">Preview unavailable</p>
                        <p class="text-slate-400 text-[10px] font-bold mb-6">This file type cannot be viewed directly.</p>
                        <div class="flex flex-col gap-2">
                            <a :href="`/storage/${selectedMaterialPath}`" download class="inline-flex items-center justify-center gap-1.5 bg-blue-600 hover:bg-blue-500 text-white transition text-[10px] font-black uppercase tracking-widest px-4 py-3 rounded-lg shadow-sm w-full">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg> Download File
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </Modal>

        <Modal :show="confirmingToggle" @close="confirmingToggle = false" maxWidth="sm">
            <div class="p-6 bg-white dark:bg-slate-900 rounded-xl shadow-xl border border-slate-200 dark:border-slate-800">
                <h2 class="text-sm font-black uppercase tracking-tight text-slate-900 dark:text-white flex items-center gap-2 mb-2">
                    <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                    Security Confirmation
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">Enter your password to change the material approval system setting.</p>
                <div class="space-y-4">
                    <div>
                        <label class="sr-only">Password</label>
                        <input v-model="toggleForm.password" type="password" :class="inputClass" placeholder="Admin Password" @keyup.enter="submitToggle" />
                        <InputError class="mt-1 text-[9px]" :message="toggleForm.errors.password" />
                    </div>
                    <div class="flex justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                        <button @click="confirmingToggle = false" type="button" class="px-4 py-2 text-[10px] font-black uppercase tracking-widest text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 transition">Cancel</button>
                        <button @click="submitToggle" :disabled="toggleForm.processing" class="px-5 py-2 bg-red-600 hover:bg-red-500 text-white rounded text-[10px] font-black uppercase tracking-widest shadow-sm transition">Confirm Change</button>
                    </div>
                </div>
            </div>
        </Modal>

        <Modal :show="showUnarchiveModal" @close="showUnarchiveModal = false" maxWidth="sm">
            <div class="p-5 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-lg">
                <h3 class="font-black text-sm text-slate-900 dark:text-white uppercase tracking-tight mb-4 flex items-center gap-2">
                    <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Confirm Unarchive
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mb-4 leading-relaxed">
                    Set new dates for <span class="font-bold text-slate-800 dark:text-slate-200">"{{ materialToUnarchive?.title }}"</span>.
                </p>

                <form @submit.prevent="submitUnarchive" class="space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[9px] font-black uppercase tracking-widest text-slate-500 mb-1">Show From *</label>
                            <input v-model="formUnarchive.available_from" type="datetime-local" :class="inputClass" required>
                            <InputError class="mt-1 text-[9px]" :message="formUnarchive.errors.available_from" />
                        </div>
                        <div>
                            <label class="block text-[9px] font-black uppercase tracking-widest text-slate-500 mb-1">New Archive Date *</label>
                            <input v-model="formUnarchive.available_until" type="datetime-local" :class="inputClass" required>
                            <InputError class="mt-1 text-[9px]" :message="formUnarchive.errors.available_until" />
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 mt-4 pt-4 border-t border-slate-100 dark:border-slate-800">
                        <button type="button" @click="showUnarchiveModal = false" class="text-[10px] text-slate-500 px-3 py-1.5 font-bold hover:text-slate-700 uppercase tracking-widest">Cancel</button>
                        <button :disabled="formUnarchive.processing" class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-1.5 rounded-md text-[10px] font-black uppercase tracking-widest shadow-sm transition-colors">Unarchive Now</button>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="showEditModal" @close="showEditModal = false" maxWidth="sm">
            <div class="p-5 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-lg">
                <h3 class="font-black text-sm text-slate-900 dark:text-white uppercase tracking-tight mb-4 flex items-center gap-2">Edit Details</h3>
                <form @submit.prevent="submitEdit" class="space-y-3">
                    <div>
                        <label class="block text-[9px] font-black uppercase tracking-widest text-slate-500 mb-1">Material Title *</label>
                        <input v-model="editForm.title" type="text" :class="inputClass" required>
                        <InputError class="mt-1 text-[9px]" :message="editForm.errors.title" />
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[9px] font-black uppercase tracking-widest text-slate-500 mb-1">Show From *</label>
                            <input v-model="editForm.available_from" type="datetime-local" :class="inputClass" required>
                            <InputError class="mt-1 text-[9px]" :message="editForm.errors.available_from" />
                        </div>
                        <div>
                            <label class="block text-[9px] font-black uppercase tracking-widest text-slate-500 mb-1">Archive On *</label>
                            <input v-model="editForm.available_until" type="datetime-local" :class="inputClass" required>
                            <InputError class="mt-1 text-[9px]" :message="editForm.errors.available_until" />
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 mt-4 pt-3 border-t border-slate-100 dark:border-slate-800">
                        <button type="button" @click="showEditModal = false" class="text-[9px] text-slate-500 px-3 py-1.5 font-bold hover:text-slate-700 uppercase tracking-widest">Cancel</button>
                        <button class="bg-blue-600 hover:bg-blue-500 text-white px-3 py-1.5 rounded-md text-[9px] font-black uppercase tracking-widest shadow-sm transition-colors" :disabled="editForm.processing">Save</button>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="!!selectedLesson" @close="selectedLesson = null" maxWidth="sm">
            <div class="p-5 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-lg">
                <h2 class="text-sm font-black uppercase tracking-tight text-red-600 mb-2">Reject Material</h2>
                <p class="text-xs text-slate-600 dark:text-slate-400">Provide feedback for "{{ selectedLesson?.title }}".</p>
                <textarea v-model="rejectionForm.reason" rows="3" :class="inputClass" class="mt-3 resize-none" placeholder="Explain what needs to be fixed..."></textarea>
                <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 flex justify-end gap-2">
                    <button type="button" @click="selectedLesson = null" class="text-[9px] text-slate-500 px-3 py-1.5 font-bold hover:text-slate-700 uppercase tracking-widest">Cancel</button>
                    <button @click="submitRejection" :disabled="rejectionForm.processing" class="bg-red-600 hover:bg-red-500 text-white px-3 py-1.5 rounded-md text-[9px] font-black uppercase tracking-widest shadow-sm transition-colors">Send Feedback</button>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<style scoped>
.scrollbar-hide::-webkit-scrollbar {
    display: none;
}
.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(148, 163, 184, 0.2);
    border-radius: 10px;
}
.custom-scrollbar:hover::-webkit-scrollbar-thumb {
    background: rgba(148, 163, 184, 0.4);
}
</style>