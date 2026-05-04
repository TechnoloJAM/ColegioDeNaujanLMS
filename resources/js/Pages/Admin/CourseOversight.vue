<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import { Head, router, useForm, Link, usePage } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';

const props = defineProps({
    courses: Array,
    teachers: Array
});

const page = usePage();
const userId = page.props.auth.user.id;

// --- LOCAL STORAGE HIDE FEATURE ---
const storageKey = `lms_admin_hidden_courses_${userId}`;
const hiddenCourses = ref(JSON.parse(localStorage.getItem(storageKey)) || []);
const activeTab = ref('active'); 

const searchQuery = ref('');
const isCreateModalOpen = ref(false);
const isEditModalOpen = ref(false);
const selectedCourse = ref(null);

const selectedIds = ref([]);
const isBulkDeleteModalOpen = ref(false);

const form = useForm({
    title: '',
    description: '',
    difficulty_level: 'beginner',
    teacher_id: '',
    thumbnail: null,
});

const editForm = useForm({
    title: '',
    description: '',
    difficulty_level: '',
    teacher_id: '',
    thumbnail: null,
    _method: 'patch', 
});

// Secure Delete Form
const bulkDeleteForm = useForm({ password: '', course_ids: [] });

const formatYearLevel = (level) => {
    const levels = {
        'beginner': '1st Year',
        'intermediate': '2nd Year',
        'advanced': '3rd Year',
        'final': '4th Year'
    };
    return levels[level] || level;
};

// --- DYNAMIC FILTERING (Applies Local Storage) ---
const filteredCourses = computed(() => {
    return props.courses.filter(course => {
        
        // 1. Tab Filter Logic using Local Storage array
        const isHidden = hiddenCourses.value.includes(course.id);
        if (activeTab.value === 'active' && isHidden) return false;
        if (activeTab.value === 'hidden' && !isHidden) return false;

        // 2. Search Logic
        const query = searchQuery.value.toLowerCase();
        return course.title.toLowerCase().includes(query) || 
               course.enrollment_code.toLowerCase().includes(query) ||
               (course.teacher && course.teacher.name.toLowerCase().includes(query));
    });
});

watch(activeTab, () => { selectedIds.value = []; });

const toggleSelection = (id) => {
    if (selectedIds.value.includes(id)) selectedIds.value = selectedIds.value.filter(i => i !== id);
    else selectedIds.value.push(id);
};

const isAllSelected = computed(() => {
    if (filteredCourses.value.length === 0) return false;
    return selectedIds.value.length === filteredCourses.value.length;
});

const toggleAll = () => {
    if (isAllSelected.value) selectedIds.value = [];
    else selectedIds.value = filteredCourses.value.map(c => c.id);
};

// --- INSTANT LOCAL STORAGE ACTIONS ---
const toggleHideSingle = (courseId) => {
    if (hiddenCourses.value.includes(courseId)) {
        hiddenCourses.value = hiddenCourses.value.filter(id => id !== courseId);
    } else {
        hiddenCourses.value.push(courseId);
    }
    localStorage.setItem(storageKey, JSON.stringify(hiddenCourses.value));
    selectedIds.value = []; // Clear selection after action
};

const handleBulkHide = (hide) => {
    if (hide) {
        // Add selected IDs to hidden array (avoiding duplicates)
        const newHidden = new Set([...hiddenCourses.value, ...selectedIds.value]);
        hiddenCourses.value = Array.from(newHidden);
    } else {
        // Remove selected IDs from hidden array
        hiddenCourses.value = hiddenCourses.value.filter(id => !selectedIds.value.includes(id));
    }
    localStorage.setItem(storageKey, JSON.stringify(hiddenCourses.value));
    selectedIds.value = [];
};

const handleThumbnailUpload = (e) => { form.thumbnail = e.target.files[0]; };
const handleEditThumbnailUpload = (e) => { editForm.thumbnail = e.target.files[0]; };

const submitCourse = () => {
    form.post(route('admin.courses.store'), {
        preserveScroll: true,
        onSuccess: () => { isCreateModalOpen.value = false; form.reset(); },
    });
};

const openEditModal = (course) => {
    selectedCourse.value = course;
    editForm.title = course.title;
    editForm.description = course.description;
    editForm.difficulty_level = course.difficulty_level;
    editForm.teacher_id = course.teacher_id;
    editForm.thumbnail = null; 
    editForm.clearErrors();
    isEditModalOpen.value = true;
};

const submitEdit = () => {
    editForm.post(route('admin.courses.update', selectedCourse.value.id), {
        preserveScroll: true, forceFormData: true,
        onSuccess: () => { isEditModalOpen.value = false; selectedCourse.value = null; }
    });
};

const openBulkDelete = (singleId = null) => {
    bulkDeleteForm.course_ids = singleId ? [singleId] : selectedIds.value;
    bulkDeleteForm.password = '';
    bulkDeleteForm.clearErrors();
    isBulkDeleteModalOpen.value = true;
};

const submitBulkDelete = () => {
    bulkDeleteForm.post(route('admin.courses.bulk-destroy'), {
        preserveScroll: true,
        onSuccess: () => { 
            // Cleanup local storage if deleted
            hiddenCourses.value = hiddenCourses.value.filter(id => !bulkDeleteForm.course_ids.includes(id));
            localStorage.setItem(storageKey, JSON.stringify(hiddenCourses.value));
            
            isBulkDeleteModalOpen.value = false; 
            selectedIds.value = []; 
        }
    });
};

const exportToCSV = () => {
    const headers = ['Course Title', 'Code', 'Teacher', 'Year Level', 'Admin Visibility', 'Students Enrolled', 'Lessons', 'Assignments'];
    const rows = filteredCourses.value.map(course => [
        `"${course.title}"`, `"${course.enrollment_code}"`, `"${course.teacher ? course.teacher.name : 'Unassigned'}"`,
        `"${formatYearLevel(course.difficulty_level)}"`, `"${hiddenCourses.value.includes(course.id) ? 'HIDDEN (Local)' : 'ACTIVE'}"`,
        `"${course.enrollments ? course.enrollments.length : 0}"`, `"${course.lessons_count}"`, `"${course.assignments_count}"`
    ]);

    const csvContent = [headers.join(','), ...rows.map(e => e.join(","))].join("\n");
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.setAttribute("href", url);
    link.setAttribute("download", `LMS_Courses_${activeTab.value}_${new Date().toISOString().slice(0,10)}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
};

const inputClass = "w-full rounded-md bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:ring-2 focus:ring-blue-500 focus:border-transparent py-1.5 px-3 text-xs shadow-sm transition-colors duration-200";
</script>

<template>
    <Head title="Course Oversight" />
    <AuthenticatedLayout>
        
        <div class="mb-4 flex justify-between items-center max-w-7xl mx-auto px-4 sm:px-6">
             <div class="flex items-center gap-3">
                 <div>
                    <h1 class="text-lg sm:text-xl font-bold text-slate-900 dark:text-white leading-tight">Course Oversight</h1>
                    <p class="text-slate-500 dark:text-slate-400 text-[9px] sm:text-[10px] uppercase font-bold tracking-wider">Manage curriculum and assignments</p>
                 </div>
             </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 flex flex-col md:flex-row gap-4 md:gap-6 items-start">
            
            <aside class="w-full md:w-12 shrink-0 flex flex-row md:flex-col gap-3 sticky top-4 md:top-6 z-10 order-2 md:order-1">
                <button @click="isCreateModalOpen = true" class="group relative flex items-center justify-center w-10 h-10 md:w-12 md:h-12 bg-white dark:bg-slate-800 rounded-full border-2 border-slate-200 dark:border-slate-700 text-blue-600 hover:border-blue-600 hover:bg-blue-50 dark:hover:bg-slate-700 transition shadow-sm focus:outline-none shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
                    <span class="absolute bottom-full mb-2 md:bottom-auto md:left-full md:ml-3 md:mb-0 px-2 py-1 bg-slate-800 text-white text-[10px] font-bold rounded opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none whitespace-nowrap shadow-lg">Create New Course</span>
                </button>

                <button @click="exportToCSV" class="group relative flex items-center justify-center w-10 h-10 md:w-12 md:h-12 bg-white dark:bg-slate-800 rounded-full border-2 border-slate-200 dark:border-slate-700 text-emerald-600 hover:border-emerald-600 hover:bg-emerald-50 dark:hover:bg-slate-700 transition shadow-sm focus:outline-none shrink-0">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                    <span class="absolute bottom-full mb-2 md:bottom-auto md:left-full md:ml-3 md:mb-0 px-2 py-1 bg-slate-800 text-white text-[10px] font-bold rounded opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none whitespace-nowrap shadow-lg">Export CSV</span>
                </button>
            </aside>

            <div class="flex-1 min-w-0 w-full order-1 md:order-2">
                
                <div class="relative w-full md:max-w-sm mb-4">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                    </div>
                    <input v-model="searchQuery" type="text" placeholder="Search courses by title, teacher, or code..." :class="inputClass" class="pl-9" />
                </div>

                <div class="flex gap-4 border-b border-slate-200 dark:border-slate-700 mb-4 overflow-x-auto no-scrollbar">
                    <button @click="activeTab = 'active'" class="pb-1.5 text-xs sm:text-sm font-bold border-b-2 transition-colors flex items-center gap-1.5 whitespace-nowrap" :class="activeTab === 'active' ? 'border-blue-600 text-blue-600 dark:text-blue-400 dark:border-blue-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300'">
                        Active Classes
                    </button>
                    <button @click="activeTab = 'hidden'" class="pb-1.5 text-xs sm:text-sm font-bold border-b-2 transition-colors whitespace-nowrap" :class="activeTab === 'hidden' ? 'border-slate-800 text-slate-800 dark:text-slate-300 dark:border-slate-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300'">
                        Hidden Classes
                    </button>
                </div>

                <transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0 -translate-y-2" enter-to-class="opacity-100 translate-y-0" leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100 translate-y-0" leave-to-class="opacity-0 -translate-y-2">
                    <div v-if="selectedIds.length > 0" class="flex flex-wrap items-center gap-2 bg-blue-50 dark:bg-blue-900/20 p-2.5 rounded-lg border border-blue-100 dark:border-blue-800 mb-4 shadow-sm">
                        <span class="text-[10px] font-black uppercase tracking-widest text-blue-700 dark:text-blue-400 mr-auto">{{ selectedIds.length }} Courses Selected</span>
                        
                        <button v-if="activeTab === 'hidden'" @click="handleBulkHide(false)" class="text-[9px] bg-white dark:bg-slate-800 text-emerald-600 border border-slate-200 dark:border-slate-700 px-3 py-1.5 rounded uppercase tracking-widest font-black shadow-sm hover:bg-emerald-50 transition">
                            Restore to Active
                        </button>
                        
                        <button v-if="activeTab === 'active'" @click="handleBulkHide(true)" class="text-[9px] bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 px-3 py-1.5 rounded uppercase tracking-widest font-black shadow-sm hover:bg-slate-100 dark:hover:bg-slate-700 transition">
                            Hide from Admin View
                        </button>
                        
                        <button @click="openBulkDelete()" class="text-[9px] bg-red-600 hover:bg-red-500 text-white px-3 py-1.5 rounded uppercase tracking-widest font-black shadow-sm transition">
                            Delete All
                        </button>
                    </div>
                </transition>

                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden mb-8">
                    <div class="overflow-x-auto no-scrollbar">
                        <table class="w-full text-left text-[11px] sm:text-xs text-slate-500 dark:text-slate-400">
                            <thead class="text-[9px] uppercase font-bold text-slate-400 bg-slate-50 dark:bg-slate-900/30 border-b border-slate-100 dark:border-slate-700">
                                <tr>
                                    <th class="px-3 py-2 w-8">
                                        <input type="checkbox" :checked="isAllSelected && filteredCourses.length > 0" @change="toggleAll" class="rounded border-slate-300 dark:border-slate-600 text-blue-600 focus:ring-blue-500 dark:bg-slate-800 cursor-pointer shadow-sm" />
                                    </th>
                                    <th class="px-2 py-2 w-full sm:w-auto">Course Details</th>
                                    <th class="px-2 py-2 hidden sm:table-cell">Statistics</th>
                                    <th class="px-2 py-2 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                <tr v-for="course in filteredCourses" :key="course.id" class="transition" :class="selectedIds.includes(course.id) ? 'bg-blue-50/50 dark:bg-blue-900/10' : 'hover:bg-slate-50 dark:hover:bg-slate-700/50'">
                                    
                                    <td class="px-3 py-1.5">
                                        <input type="checkbox" :checked="selectedIds.includes(course.id)" @change="toggleSelection(course.id)" class="rounded border-slate-300 dark:border-slate-600 text-blue-600 focus:ring-blue-500 dark:bg-slate-800 cursor-pointer shadow-sm" />
                                    </td>

                                    <td class="px-2 py-1.5 flex flex-col sm:table-cell cursor-pointer" @click="toggleSelection(course.id)">
                                        <div class="flex items-center gap-2">
                                            <div v-if="course.thumbnail" class="w-6 h-6 rounded bg-slate-200 shrink-0 overflow-hidden">
                                                <img :src="course.thumbnail" class="w-full h-full object-cover" />
                                            </div>
                                            <div v-else class="w-6 h-6 rounded bg-blue-100 dark:bg-blue-900/30 text-blue-600 flex items-center justify-center shrink-0">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                                            </div>
                                            
                                            <div class="min-w-0">
                                                <div class="font-bold text-slate-900 dark:text-white truncate max-w-[150px] sm:max-w-xs leading-tight">{{ course.title }}</div>
                                                <div class="text-[10px] mt-0.5 truncate max-w-[150px] sm:max-w-xs leading-tight opacity-80 text-blue-600 dark:text-blue-400">{{ course.teacher ? course.teacher.name : 'Unassigned Teacher' }}</div>
                                            </div>
                                        </div>

                                        <div class="sm:hidden mt-1.5 flex gap-2 items-center flex-wrap">
                                            <span class="font-mono text-[9px] text-slate-400 font-bold">Code: {{ course.enrollment_code }}</span>
                                            <span class="bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400 px-1 py-0.5 rounded text-[8px] font-bold uppercase">{{ formatYearLevel(course.difficulty_level) }}</span>
                                        </div>
                                    </td>
                                    
                                    <td class="px-2 py-1.5 hidden sm:table-cell align-top">
                                        <div class="flex items-center gap-1.5 mb-1">
                                            <span class="font-mono text-[9px] text-slate-400 font-bold tracking-widest bg-slate-100 dark:bg-slate-800 px-1.5 py-0.5 rounded">ID: {{ course.enrollment_code }}</span>
                                            <span class="bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400 px-1.5 py-0.5 rounded text-[8px] font-bold uppercase tracking-widest">{{ formatYearLevel(course.difficulty_level) }}</span>
                                        </div>
                                        <div class="text-[9px] font-bold text-slate-500 uppercase tracking-widest flex items-center gap-2">
                                            <span><b class="text-emerald-600">{{ course.enrollments ? course.enrollments.length : 0 }}</b> Students</span>
                                            <span><b class="text-slate-700 dark:text-slate-300">{{ course.lessons_count }}</b> Lessons</span>
                                            <span><b class="text-slate-700 dark:text-slate-300">{{ course.assignments_count }}</b> Tasks</span>
                                        </div>
                                    </td>
                                    
                                    <td class="px-2 py-1.5 text-right align-middle">
                                        <div class="flex items-center justify-end gap-1 flex-wrap sm:flex-nowrap min-w-[60px]">
                                            
                                            <button v-if="!hiddenCourses.includes(course.id)" @click="toggleHideSingle(course.id)" class="p-1.5 text-slate-400 hover:text-slate-700 bg-white hover:bg-slate-100 dark:bg-transparent dark:hover:bg-slate-800 rounded transition shadow-sm border border-transparent" title="Hide from Admin View">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.29 3.29m0 0a10.05 10.05 0 013.825-1.542m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.29 3.29m0 0a10.05 10.05 0 013.825-1.542m5.858.908A9.97 9.97 0 0121 12c-1.274-4.057-5.064-7-9.542-7-1.274 0-2.483.253-3.582.71" /></svg>
                                            </button>
                                            <button v-else @click="toggleHideSingle(course.id)" class="p-1.5 text-emerald-500 hover:text-emerald-700 bg-white hover:bg-emerald-50 dark:bg-transparent dark:hover:bg-emerald-900/30 rounded transition shadow-sm border border-transparent" title="Restore to Active">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                            </button>

                                            <Link :href="route('teacher.courses.show', course.id)" class="text-[9px] font-black uppercase tracking-widest bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 px-2 py-1.5 rounded flex items-center gap-1 hover:bg-blue-200 dark:hover:bg-blue-800/50 transition shadow-sm border border-transparent" title="Enter Class (God Mode)">
                                                <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
                                                <span class="hidden sm:inline">Enter</span>
                                            </Link>

                                            <button @click="openEditModal(course)" class="text-[9px] font-bold uppercase tracking-wide bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 px-2 py-1.5 rounded flex items-center gap-1 hover:bg-slate-200 transition shadow-sm border border-transparent">
                                                Edit
                                            </button>

                                            <button @click="openBulkDelete(course.id)" class="p-1.5 text-slate-400 hover:text-red-600 bg-white hover:bg-red-50 dark:bg-transparent dark:hover:bg-red-900/30 rounded transition shadow-sm border border-transparent hover:border-red-200 dark:hover:border-red-800" title="Permanently Delete Course">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="filteredCourses.length === 0">
                                    <td colspan="4" class="px-2 py-8 text-center text-slate-400 dark:text-slate-500 text-[10px]">
                                        <div class="font-black uppercase tracking-widest mb-1 text-slate-300 dark:text-slate-600">No Courses Found</div>
                                        <div class="font-medium">Try adjusting your search or filters.</div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>

        <Modal :show="isCreateModalOpen" :closeable="false" @close="isCreateModalOpen = false" maxWidth="md">
            <div class="p-5 bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-slate-200 dark:border-slate-700">
                <h2 class="text-base font-bold text-slate-900 dark:text-white mb-4">Create New Course</h2>
                
                <form @submit.prevent="submitCourse" class="space-y-3">
                    <div>
                        <InputLabel value="Course Title" class="text-[9px] font-bold uppercase text-slate-500 mb-1" />
                        <input v-model="form.title" type="text" :class="inputClass" placeholder="e.g. Intro to Programming" required />
                        <InputError :message="form.errors.title" class="mt-1 text-[10px]" />
                    </div>

                    <div>
                        <InputLabel value="Course Description" class="text-[9px] font-bold uppercase text-slate-500 mb-1" />
                        <textarea v-model="form.description" rows="2" :class="inputClass" class="resize-none" placeholder="Briefly describe what this course covers..."></textarea>
                        <InputError :message="form.errors.description" class="mt-1 text-[10px]" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <InputLabel value="Assign Teacher" class="text-[9px] font-bold uppercase text-slate-500 mb-1" />
                            <select v-model="form.teacher_id" :class="inputClass" class="cursor-pointer" required>
                                <option value="" disabled>Select a teacher...</option>
                                <option v-for="teacher in teachers" :key="teacher.id" :value="teacher.id">{{ teacher.name }}</option>
                            </select>
                            <InputError :message="form.errors.teacher_id" class="mt-1 text-[10px]" />
                            <p v-if="teachers.length === 0" class="text-[9px] text-red-500 mt-1 font-bold">No active teachers available. Create one first.</p>
                        </div>

                        <div>
                            <InputLabel value="Year Level" class="text-[9px] font-bold uppercase text-slate-500 mb-1" />
                            <select v-model="form.difficulty_level" :class="inputClass" class="cursor-pointer" required>
                                <option value="beginner">1st Year</option>
                                <option value="intermediate">2nd Year</option>
                                <option value="advanced">3rd Year</option>
                                <option value="final">4th Year</option>
                            </select>
                            <InputError :message="form.errors.difficulty_level" class="mt-1 text-[10px]" />
                        </div>
                    </div>

                    <div>
                        <InputLabel value="Course Thumbnail (Optional)" class="text-[9px] font-bold uppercase text-slate-500 mb-1" />
                        <input type="file" @change="handleThumbnailUpload" accept="image/jpeg, image/png, image/jpg" class="w-full text-[10px] text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-[9px] file:font-bold file:uppercase file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer border border-slate-200 dark:border-slate-700 rounded p-1 dark:bg-slate-900" />
                        <InputError :message="form.errors.thumbnail" class="mt-1 text-[10px]" />
                    </div>

                    <div class="mt-5 pt-3 border-t border-slate-100 dark:border-slate-700 flex justify-end gap-2">
                        <button type="button" @click="isCreateModalOpen = false" class="text-[10px] text-slate-500 px-3 py-1.5 font-bold hover:text-slate-700 uppercase tracking-widest">Cancel</button>
                        <button :disabled="form.processing" class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-1.5 rounded text-[10px] uppercase tracking-widest font-black shadow-sm transition">Create Course</button>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="isEditModalOpen" :closeable="false" @close="isEditModalOpen = false" maxWidth="md">
            <div class="p-5 bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-slate-200 dark:border-slate-700">
                <h2 class="text-base font-bold text-slate-900 dark:text-white mb-4">Edit Course Settings</h2>
                
                <form @submit.prevent="submitEdit" class="space-y-3">
                    <div>
                        <InputLabel value="Course Title" class="text-[9px] font-bold uppercase text-slate-500 mb-1" />
                        <input v-model="editForm.title" type="text" :class="inputClass" required />
                        <InputError :message="editForm.errors.title" class="mt-1 text-[10px]" />
                    </div>

                    <div>
                        <InputLabel value="Course Description" class="text-[9px] font-bold uppercase text-slate-500 mb-1" />
                        <textarea v-model="editForm.description" rows="2" :class="inputClass" class="resize-none"></textarea>
                        <InputError :message="editForm.errors.description" class="mt-1 text-[10px]" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <InputLabel value="Assigned Teacher" class="text-[9px] font-bold uppercase text-slate-500 mb-1" />
                            <select v-model="editForm.teacher_id" :class="inputClass" class="cursor-pointer" required>
                                <option value="" disabled>Select a teacher...</option>
                                <option v-for="teacher in teachers" :key="teacher.id" :value="teacher.id">{{ teacher.name }}</option>
                            </select>
                            <InputError :message="editForm.errors.teacher_id" class="mt-1 text-[10px]" />
                        </div>

                        <div>
                            <InputLabel value="Year Level" class="text-[9px] font-bold uppercase text-slate-500 mb-1" />
                            <select v-model="editForm.difficulty_level" :class="inputClass" class="cursor-pointer" required>
                                <option value="beginner">1st Year</option>
                                <option value="intermediate">2nd Year</option>
                                <option value="advanced">3rd Year</option>
                                <option value="final">4th Year</option>
                            </select>
                            <InputError :message="editForm.errors.difficulty_level" class="mt-1 text-[10px]" />
                        </div>
                    </div>

                    <div>
                        <InputLabel value="Update Thumbnail (Optional)" class="text-[9px] font-bold uppercase text-slate-500 mb-1" />
                        <input type="file" @change="handleEditThumbnailUpload" accept="image/jpeg, image/png, image/jpg" class="w-full text-[10px] text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-[9px] file:font-bold file:uppercase file:bg-amber-50 file:text-amber-700 hover:file:bg-amber-100 cursor-pointer border border-slate-200 dark:border-slate-700 rounded p-1 dark:bg-slate-900" />
                        <InputError :message="editForm.errors.thumbnail" class="mt-1 text-[10px]" />
                    </div>

                    <div class="mt-5 pt-3 border-t border-slate-100 dark:border-slate-700 flex justify-end gap-2">
                        <button type="button" @click="isEditModalOpen = false" class="text-[10px] text-slate-500 px-3 py-1.5 font-bold hover:text-slate-700 uppercase tracking-widest">Cancel</button>
                        <button :disabled="editForm.processing" class="bg-amber-500 hover:bg-amber-400 text-white px-4 py-1.5 rounded text-[10px] uppercase tracking-widest font-black shadow-sm transition">Save Changes</button>
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
                    You are permanently deleting <strong class="text-red-500">{{ bulkDeleteForm.course_ids.length }} course(s)</strong> and all related files. This cannot be undone. Enter your admin password to confirm.
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

    </AuthenticatedLayout>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>