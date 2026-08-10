<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import { Head, router, useForm, Link, usePage } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import * as XLSX from 'xlsx';

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

// --- DYNAMIC FILTERS ---
const selectedTeacherFilter = ref('all');
const selectedYearFilter = ref('all');
const sortOrder = ref('newest');

const isCreateModalOpen = ref(false);
const isEditModalOpen = ref(false);
const selectedCourse = ref(null);

const selectedIds = ref([]);
const isBulkDeleteModalOpen = ref(false);

const daysOfWeek = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

// ==========================================
// BATCH CREATE FORM LOGIC
// ==========================================
const activeCourseTab = ref(0);

const form = useForm({
    teacher_id: '',
    courses: [
        { title: '', description: '', difficulty_level: 'beginner', days: [], start_time: '', end_time: '', room: '' }
    ]
});

const addCourseTab = () => {
    form.courses.push({ title: '', description: '', difficulty_level: 'beginner', days: [], start_time: '', end_time: '', room: '' });
    activeCourseTab.value = form.courses.length - 1;
};

const removeCourseTab = (index) => {
    if (form.courses.length > 1) {
        form.courses.splice(index, 1);
        if (activeCourseTab.value >= index) {
            activeCourseTab.value = Math.max(0, activeCourseTab.value - 1);
        }
    }
};

const submitCourse = () => {
    form.post(route('admin.courses.store'), {
        preserveScroll: true,
        onSuccess: () => { 
            isCreateModalOpen.value = false; 
            form.reset(); 
            activeCourseTab.value = 0;
        },
    });
};
// ==========================================

const editForm = useForm({
    title: '',
    description: '',
    difficulty_level: '',
    teacher_id: '',
    thumbnail: null,
    days: [],
    start_time: '',
    end_time: '',
    room: '',
    _method: 'patch', 
});

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

const filteredCourses = computed(() => {
    let result = props.courses.filter(course => {
        const isHidden = hiddenCourses.value.includes(course.id);
        if (activeTab.value === 'active' && isHidden) return false;
        if (activeTab.value === 'hidden' && !isHidden) return false;
        if (selectedTeacherFilter.value !== 'all' && course.teacher_id !== selectedTeacherFilter.value) return false;
        if (selectedYearFilter.value !== 'all' && course.difficulty_level !== selectedYearFilter.value) return false;
        const query = searchQuery.value.toLowerCase();
        if (query) {
            return course.title.toLowerCase().includes(query) || 
                   course.enrollment_code.toLowerCase().includes(query) ||
                   (course.teacher && course.teacher.name.toLowerCase().includes(query));
        }
        return true;
    });

    return result.sort((a, b) => {
        if (sortOrder.value === 'newest') return new Date(b.created_at || 0).getTime() - new Date(a.created_at || 0).getTime();
        if (sortOrder.value === 'oldest') return new Date(a.created_at || 0).getTime() - new Date(b.created_at || 0).getTime();
        if (sortOrder.value === 'students_high') return (b.enrollments ? b.enrollments.length : 0) - (a.enrollments ? a.enrollments.length : 0);
        if (sortOrder.value === 'students_low') return (a.enrollments ? a.enrollments.length : 0) - (b.enrollments ? b.enrollments.length : 0);
        return 0;
    });
});

watch(activeTab, () => { selectedIds.value = []; });
watch([selectedTeacherFilter, selectedYearFilter], () => { selectedIds.value = []; });

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

const toggleHideSingle = (courseId) => {
    if (hiddenCourses.value.includes(courseId)) {
        hiddenCourses.value = hiddenCourses.value.filter(id => id !== courseId);
    } else {
        hiddenCourses.value.push(courseId);
    }
    localStorage.setItem(storageKey, JSON.stringify(hiddenCourses.value));
    selectedIds.value = []; 
};

const handleBulkHide = (hide) => {
    if (hide) {
        const newHidden = new Set([...hiddenCourses.value, ...selectedIds.value]);
        hiddenCourses.value = Array.from(newHidden);
    } else {
        hiddenCourses.value = hiddenCourses.value.filter(id => !selectedIds.value.includes(id));
    }
    localStorage.setItem(storageKey, JSON.stringify(hiddenCourses.value));
    selectedIds.value = [];
};

const handleEditThumbnailUpload = (e) => { editForm.thumbnail = e.target.files[0]; };

const openEditModal = (course) => {
    selectedCourse.value = course;
    editForm.title = course.title;
    editForm.description = course.description;
    editForm.difficulty_level = course.difficulty_level;
    editForm.teacher_id = course.teacher_id;
    editForm.thumbnail = null; 
    
    // Schedule Data mapping
    editForm.days = course.days || [];
    editForm.start_time = course.start_time ? course.start_time.substring(0, 5) : '';
    editForm.end_time = course.end_time ? course.end_time.substring(0, 5) : '';
    editForm.room = course.room || '';

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
            hiddenCourses.value = hiddenCourses.value.filter(id => !bulkDeleteForm.course_ids.includes(id));
            localStorage.setItem(storageKey, JSON.stringify(hiddenCourses.value));
            isBulkDeleteModalOpen.value = false; 
            selectedIds.value = []; 
        }
    });
};

const exportToExcel = () => {
    const wb = XLSX.utils.book_new();

    const wsData = [
        ['COLEGIO DE NAUJAN - COURSE OVERSIGHT REPORT', '', '', '', '', '', '', ''],
        [],
        ['Report Generated:', String(new Date().toLocaleString()), '', '', '', '', '', ''],
        ['List Category:', String(activeTab.value.toUpperCase()), '', '', '', '', '', ''],
        ['Total Records:', String(filteredCourses.value.length), '', '', '', '', '', ''],
        [],
        [
            'Course Title', 'Code', 'Teacher', 'Year Level', 
            'Admin Visibility', 'Students Enrolled', 'Lessons', 'Assignments'
        ]
    ];

    if (filteredCourses.value.length > 0) {
        filteredCourses.value.forEach(course => {
            wsData.push([
                String(course.title), String(course.enrollment_code), String(course.teacher ? course.teacher.name : 'Unassigned'),
                String(formatYearLevel(course.difficulty_level)), String(hiddenCourses.value.includes(course.id) ? 'HIDDEN (Local)' : 'ACTIVE'),
                String(course.enrollments ? course.enrollments.length : 0), String(course.lessons_count), String(course.assignments_count)
            ]);
        });
    } else {
        wsData.push(['No courses found.']);
    }

    const ws = XLSX.utils.aoa_to_sheet(wsData);
    ws['!merges'] = [ { s: { r: 0, c: 0 }, e: { r: 0, c: 7 } } ];
    ws['!cols'] = [ { wch: 35 }, { wch: 15 }, { wch: 25 }, { wch: 15 }, { wch: 20 }, { wch: 18 }, { wch: 15 }, { wch: 15 } ];

    XLSX.utils.book_append_sheet(wb, ws, "Courses");
    XLSX.writeFile(wb, `LMS_Courses_${activeTab.value}_${new Date().toISOString().slice(0,10)}.xlsx`);
};

const inputClass = "w-full rounded-md bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:ring-2 focus:ring-blue-500 focus:border-transparent py-1.5 px-3 text-xs shadow-sm transition-colors duration-200";
</script>

<template>
    <Head title="Course Oversight" />
    <AuthenticatedLayout>
        
        <div class="mb-3 flex justify-between items-center max-w-7xl mx-auto px-3 sm:px-6">
             <div class="flex items-center gap-3">
                 <div>
                    <h1 class="text-lg sm:text-xl font-black text-slate-900 dark:text-white leading-tight tracking-tight">Course Oversight</h1>
                    <p class="text-slate-500 dark:text-slate-400 text-[9px] sm:text-[10px] uppercase font-bold tracking-wider">Manage curriculum and schedules</p>
                 </div>
             </div>
        </div>

        <div class="max-w-7xl mx-auto px-3 sm:px-6 flex flex-col md:flex-row gap-3 md:gap-5 items-start">
            
            <!-- FIXED: ASIDE ORDER-1 KEEPS BUTTONS ON TOP FOR MOBILE -->
            <aside class="w-full md:w-12 shrink-0 flex flex-row md:flex-col gap-2 justify-end md:justify-start sticky top-2 md:top-6 z-10 order-1 md:order-1 mb-4 md:mb-0">
                <button @click="isCreateModalOpen = true" class="group relative flex items-center justify-center w-9 h-9 sm:w-10 sm:h-10 md:w-12 md:h-12 bg-white dark:bg-slate-800 rounded-full border-2 border-slate-200 dark:border-slate-700 text-blue-600 hover:border-blue-600 hover:bg-blue-50 dark:hover:bg-slate-700 transition shadow-sm focus:outline-none shrink-0">
                    <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" /></svg>
                    <span class="absolute bottom-full mb-2 md:bottom-auto md:left-full md:ml-3 md:mb-0 px-2 py-1 bg-slate-800 text-white text-[9px] font-bold rounded opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none whitespace-nowrap shadow-lg">Batch Create</span>
                </button>

                <button @click="exportToExcel" class="group relative flex items-center justify-center w-9 h-9 sm:w-10 sm:h-10 md:w-12 md:h-12 bg-white dark:bg-slate-800 rounded-full border-2 border-slate-200 dark:border-slate-700 text-emerald-600 hover:border-emerald-600 hover:bg-emerald-50 dark:hover:bg-slate-700 transition shadow-sm focus:outline-none shrink-0">
                    <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
                    <span class="absolute bottom-full mb-2 md:bottom-auto md:left-full md:ml-3 md:mb-0 px-2 py-1 bg-slate-800 text-white text-[9px] font-bold rounded opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none whitespace-nowrap shadow-lg">Export Excel</span>
                </button>
            </aside>

            <div class="flex-1 min-w-0 w-full order-2 md:order-2">
                <!-- COMPACT FILTERS -->
                <div class="flex flex-col lg:flex-row gap-2 mb-3">
                    <div class="relative flex-1">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-3.5 w-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
                        </div>
                        <input v-model="searchQuery" type="text" placeholder="Search courses..." class="w-full pl-8 pr-3 py-1.5 text-xs rounded-md border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 transition shadow-sm" />
                    </div>

                    <div class="flex gap-2 w-full lg:w-auto overflow-x-auto no-scrollbar pb-1 lg:pb-0">
                        <div class="shrink-0 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-md px-2 py-1 shadow-sm flex items-center gap-1.5 min-w-[110px]">
                            <svg class="w-3 h-3 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                            <select v-model="selectedTeacherFilter" class="bg-transparent border-none text-[8px] sm:text-[9px] font-bold uppercase tracking-widest text-slate-600 dark:text-slate-300 w-full focus:ring-0 cursor-pointer p-0 m-0">
                                <option value="all">All Teachers</option>
                                <option v-for="teacher in teachers" :key="teacher.id" :value="teacher.id">{{ teacher.name }}</option>
                            </select>
                        </div>

                        <div class="shrink-0 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-md px-2 py-1 shadow-sm flex items-center gap-1.5 min-w-[100px]">
                            <svg class="w-3 h-3 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"></path></svg>
                            <select v-model="selectedYearFilter" class="bg-transparent border-none text-[8px] sm:text-[9px] font-bold uppercase tracking-widest text-slate-600 dark:text-slate-300 w-full focus:ring-0 cursor-pointer p-0 m-0">
                                <option value="all">All Years</option>
                                <option value="beginner">1st Year</option>
                                <option value="intermediate">2nd Year</option>
                                <option value="advanced">3rd Year</option>
                                <option value="final">4th Year</option>
                            </select>
                        </div>

                        <div class="shrink-0 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-md px-2 py-1 shadow-sm flex items-center gap-1.5 min-w-[110px]">
                            <svg class="w-3 h-3 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12"></path></svg>
                            <select v-model="sortOrder" class="bg-transparent border-none text-[8px] sm:text-[9px] font-bold uppercase tracking-widest text-slate-600 dark:text-slate-300 w-full focus:ring-0 cursor-pointer p-0 m-0">
                                <option value="newest">Newest First</option>
                                <option value="oldest">Oldest First</option>
                                <option value="students_high">Most Students</option>
                                <option value="students_low">Least Students</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="flex gap-4 border-b border-slate-200 dark:border-slate-700 mb-3 overflow-x-auto no-scrollbar">
                    <button @click="activeTab = 'active'" class="pb-1.5 text-[10px] sm:text-xs font-bold border-b-2 transition-colors flex items-center gap-1.5 whitespace-nowrap uppercase tracking-widest" :class="activeTab === 'active' ? 'border-blue-600 text-blue-600 dark:text-blue-400 dark:border-blue-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300'">
                        Active Classes
                    </button>
                    <button @click="activeTab = 'hidden'" class="pb-1.5 text-[10px] sm:text-xs font-bold border-b-2 transition-colors whitespace-nowrap uppercase tracking-widest" :class="activeTab === 'hidden' ? 'border-slate-800 text-slate-800 dark:text-slate-300 dark:border-slate-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300'">
                        Hidden Classes
                    </button>
                </div>

                <transition enter-active-class="transition ease-out duration-200" enter-from-class="opacity-0 -translate-y-2" enter-to-class="opacity-100 translate-y-0" leave-active-class="transition ease-in duration-150" leave-from-class="opacity-100 translate-y-0" leave-to-class="opacity-0 -translate-y-2">
                    <div v-if="selectedIds.length > 0" class="flex flex-wrap items-center gap-2 bg-blue-50 dark:bg-blue-900/20 p-2 rounded-md border border-blue-100 dark:border-blue-800 mb-3 shadow-sm">
                        <span class="text-[9px] font-black uppercase tracking-widest text-blue-700 dark:text-blue-400 mr-auto">{{ selectedIds.length }} Selected</span>
                        <button v-if="activeTab === 'hidden'" @click="handleBulkHide(false)" class="text-[8px] bg-white dark:bg-slate-800 text-emerald-600 border border-slate-200 dark:border-slate-700 px-2.5 py-1 rounded uppercase tracking-widest font-black shadow-sm hover:bg-emerald-50 transition">Restore</button>
                        <button v-if="activeTab === 'active'" @click="handleBulkHide(true)" class="text-[8px] bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 px-2.5 py-1 rounded uppercase tracking-widest font-black shadow-sm hover:bg-slate-100 transition">Hide</button>
                        <button @click="openBulkDelete()" class="text-[8px] bg-red-600 hover:bg-red-500 text-white px-2.5 py-1 rounded uppercase tracking-widest font-black shadow-sm transition">Delete All</button>
                    </div>
                </transition>

                <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden mb-8">
                    <div class="overflow-x-auto custom-scrollbar">
                        <table class="w-full text-left text-[10px] sm:text-xs text-slate-500 dark:text-slate-400 whitespace-nowrap sm:whitespace-normal">
                            <thead class="text-[8px] sm:text-[9px] uppercase font-bold text-slate-400 bg-slate-50 dark:bg-slate-900/30 border-b border-slate-100 dark:border-slate-700">
                                <tr>
                                    <th class="px-2 sm:px-3 py-1.5 w-6 sm:w-8"><input type="checkbox" :checked="isAllSelected && filteredCourses.length > 0" @change="toggleAll" class="rounded border-slate-300 dark:border-slate-600 text-blue-600 focus:ring-blue-500 dark:bg-slate-800 cursor-pointer shadow-sm" /></th>
                                    <th class="px-2 py-1.5 w-full sm:w-auto">Course Details</th>
                                    <th class="px-2 py-1.5 hidden sm:table-cell">Schedule & Room</th>
                                    <th class="px-2 py-1.5 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                <tr v-for="course in filteredCourses" :key="course.id" class="transition" :class="selectedIds.includes(course.id) ? 'bg-blue-50/50 dark:bg-blue-900/10' : 'hover:bg-slate-50 dark:hover:bg-slate-700/50'">
                                    
                                    <td class="px-2 sm:px-3 py-1.5"><input type="checkbox" :checked="selectedIds.includes(course.id)" @change="toggleSelection(course.id)" class="rounded border-slate-300 dark:border-slate-600 text-blue-600 focus:ring-blue-500 dark:bg-slate-800 cursor-pointer shadow-sm" /></td>

                                    <td class="px-2 py-1.5 flex flex-col sm:table-cell cursor-pointer" @click="toggleSelection(course.id)">
                                        <div class="flex items-center gap-2">
                                            <div v-if="course.thumbnail" class="w-5 h-5 sm:w-6 sm:h-6 rounded bg-slate-200 shrink-0 overflow-hidden">
                                                <img :src="course.thumbnail" class="w-full h-full object-cover" />
                                            </div>
                                            <div v-else class="w-5 h-5 sm:w-6 sm:h-6 rounded bg-blue-100 dark:bg-blue-900/30 text-blue-600 flex items-center justify-center shrink-0">
                                                <span class="text-[8px] font-black uppercase">{{ formatYearLevel(course.difficulty_level).charAt(0) }}Y</span>
                                            </div>
                                            <div class="min-w-0">
                                                <div class="font-bold text-slate-900 dark:text-white truncate max-w-[140px] sm:max-w-xs leading-tight text-[10px] sm:text-xs">{{ course.title }}</div>
                                                <div class="text-[8px] sm:text-[9px] mt-0.5 truncate max-w-[140px] sm:max-w-xs leading-tight opacity-80 text-blue-600 dark:text-blue-400">{{ course.teacher ? course.teacher.name : 'Unassigned' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <!-- SCHEDULE DISPLAY BLOCK -->
                                    <td class="px-2 py-1.5 hidden sm:table-cell align-top">
                                        <div v-if="course.days && course.days.length > 0" class="flex flex-col">
                                            <div class="flex gap-1 mb-1 flex-wrap">
                                                <span v-for="d in course.days" :key="d" class="bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400 px-1 py-0.5 rounded text-[7px] font-black uppercase tracking-widest">{{ d }}</span>
                                            </div>
                                            <span class="text-[8px] font-bold text-slate-500 flex items-center gap-1">
                                                <svg class="w-2.5 h-2.5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                {{ course.start_time ? course.start_time.substring(0,5) : '' }} - {{ course.end_time ? course.end_time.substring(0,5) : '' }}
                                            </span>
                                            <span class="text-[8px] font-bold text-slate-500 flex items-center gap-1 mt-0.5">
                                                <svg class="w-2.5 h-2.5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                                                {{ course.room || 'TBA' }}
                                            </span>
                                        </div>
                                        <div v-else class="text-[8px] text-slate-400 italic">No schedule set</div>
                                    </td>
                                    
                                    <td class="px-2 py-1.5 text-right align-middle">
                                        <div class="flex items-center justify-end gap-1 min-w-[80px]">
                                            <button v-if="!hiddenCourses.includes(course.id)" @click="toggleHideSingle(course.id)" class="p-1 sm:p-1.5 text-slate-400 hover:text-slate-700 bg-white hover:bg-slate-100 dark:bg-transparent dark:hover:bg-slate-800 rounded transition shadow-sm border border-transparent" title="Hide">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.29 3.29m0 0a10.05 10.05 0 013.825-1.542m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.29 3.29m0 0a10.05 10.05 0 013.825-1.542m5.858.908A9.97 9.97 0 0121 12c-1.274-4.057-5.064-7-9.542-7-1.274 0-2.483.253-3.582.71" /></svg>
                                            </button>
                                            <button v-else @click="toggleHideSingle(course.id)" class="p-1 sm:p-1.5 text-emerald-500 hover:text-emerald-700 bg-white hover:bg-emerald-50 dark:bg-transparent dark:hover:bg-emerald-900/30 rounded transition shadow-sm border border-transparent" title="Restore">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                            </button>

                                            <Link :href="route('teacher.courses.show', course.id)" class="text-[8px] font-black uppercase tracking-widest bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 px-1.5 sm:px-2 py-1 sm:py-1.5 rounded flex items-center gap-1 hover:bg-blue-200 dark:hover:bg-blue-800/50 transition shadow-sm" title="Enter Class">
                                                <svg class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
                                                <span class="hidden sm:inline">Enter</span>
                                            </Link>

                                            <button @click="openEditModal(course)" class="text-[8px] font-bold uppercase tracking-wide bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 px-1.5 sm:px-2 py-1 sm:py-1.5 rounded flex items-center gap-1 hover:bg-slate-200 transition shadow-sm">
                                                Edit
                                            </button>

                                            <button @click="openBulkDelete(course.id)" class="p-1 sm:p-1.5 text-slate-400 hover:text-red-600 bg-white hover:bg-red-50 dark:bg-transparent dark:hover:bg-red-900/30 rounded transition shadow-sm border border-transparent hover:border-red-200" title="Delete">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="filteredCourses.length === 0">
                                    <td colspan="4" class="px-2 py-8 text-center text-slate-400 dark:text-slate-500 text-[10px]">
                                        <div class="font-black uppercase tracking-widest mb-1 text-slate-300 dark:text-slate-600">No Courses Found</div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>

        <!-- ========================================== -->
        <!-- BATCH CREATE MODAL (NOW SCROLLABLE)        -->
        <!-- ========================================== -->
        <Modal :show="isCreateModalOpen" :closeable="false" @close="isCreateModalOpen = false" maxWidth="lg">
            <div class="p-4 sm:p-5 bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-slate-200 dark:border-slate-700 flex flex-col max-h-[90vh]">
                <h2 class="text-sm sm:text-base font-bold text-slate-900 dark:text-white mb-3 shrink-0">Batch Create & Distribute</h2>
                
                <form @submit.prevent="submitCourse" class="flex flex-col min-h-0">
                    
                    <!-- SCROLLABLE BODY -->
                    <div class="flex-1 overflow-y-auto custom-scrollbar pr-1 sm:pr-2 pb-2">
                        <div class="mb-3 bg-slate-50 dark:bg-slate-900 p-2.5 rounded-lg border border-slate-200 dark:border-slate-700">
                            <InputLabel value="1. Select Instructor" class="text-[9px] font-black uppercase tracking-widest text-blue-600 mb-1" />
                            <select v-model="form.teacher_id" :class="inputClass" class="cursor-pointer font-bold" required>
                                <option value="" disabled>Select an instructor...</option>
                                <option v-for="teacher in teachers" :key="teacher.id" :value="teacher.id">{{ teacher.name }}</option>
                            </select>
                            <InputError :message="form.errors.teacher_id" class="mt-1 text-[9px]" />
                        </div>

                        <!-- SLIDABLE TAB BAR -->
                        <div class="flex overflow-x-auto gap-2 border-b border-slate-200 dark:border-slate-700 pb-2 mb-3 no-scrollbar">
                            <button v-for="(course, index) in form.courses" :key="index" @click="activeCourseTab = index" type="button"
                                class="px-2.5 py-1 rounded-md text-[9px] font-black uppercase tracking-widest whitespace-nowrap transition-colors"
                                :class="activeCourseTab === index ? 'bg-blue-600 text-white shadow-sm' : 'bg-slate-100 text-slate-500 hover:bg-slate-200'">
                                Subj {{ index + 1 }}
                            </button>
                            <button type="button" @click="addCourseTab" class="px-2.5 py-1 rounded-md bg-emerald-100 text-emerald-600 hover:bg-emerald-200 text-[9px] font-black uppercase transition-colors shrink-0 flex items-center gap-1">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M12 4v16m8-8H4"></path></svg> Add
                            </button>
                        </div>

                        <!-- DYNAMIC TAB CONTENT -->
                        <div v-for="(course, index) in form.courses" :key="'content-'+index" v-show="activeCourseTab === index" class="space-y-3">
                            <div class="flex justify-between items-center bg-slate-50 dark:bg-slate-900/50 px-2 py-1.5 rounded border border-slate-100 dark:border-slate-700">
                                <span class="text-[9px] font-black text-slate-500 uppercase tracking-widest">Subject {{ index + 1 }}</span>
                                <button v-if="form.courses.length > 1" @click="removeCourseTab(index)" type="button" class="text-[8px] font-bold text-red-500 hover:text-red-700 uppercase tracking-widest flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg> Remove
                                </button>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                                <div class="sm:col-span-2">
                                    <InputLabel value="Course Title *" class="text-[8px] font-bold uppercase text-slate-500 mb-0.5" />
                                    <input v-model="course.title" type="text" :class="inputClass" required />
                                    <InputError :message="form.errors[`courses.${index}.title`]" class="mt-1 text-[9px]" />
                                </div>
                                <div>
                                    <InputLabel value="Year Level *" class="text-[8px] font-bold uppercase text-slate-500 mb-0.5" />
                                    <select v-model="course.difficulty_level" :class="inputClass" required>
                                        <option value="beginner">1st Year</option>
                                        <option value="intermediate">2nd Year</option>
                                        <option value="advanced">3rd Year</option>
                                        <option value="final">4th Year</option>
                                    </select>
                                </div>
                                <div>
                                    <InputLabel value="Description" class="text-[8px] font-bold uppercase text-slate-500 mb-0.5" />
                                    <input v-model="course.description" type="text" :class="inputClass" />
                                </div>
                            </div>

                            <div class="border border-blue-100 dark:border-blue-900/50 rounded-lg p-2.5 bg-blue-50/30 dark:bg-blue-900/10">
                                <h4 class="text-[8px] font-black uppercase tracking-widest text-blue-600 dark:text-blue-400 mb-2 flex items-center gap-1">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    Schedule & Room
                                </h4>
                                
                                <div class="mb-2">
                                    <div class="flex gap-1 flex-wrap sm:flex-nowrap">
                                        <label v-for="day in daysOfWeek" :key="day" 
                                            class="flex-1 text-center border rounded cursor-pointer text-[9px] py-1 transition-colors shadow-sm min-w-[30px]"
                                            :class="course.days.includes(day) ? 'bg-blue-600 text-white border-blue-600 font-bold' : 'bg-white text-slate-500'">
                                            <input type="checkbox" :value="day" v-model="course.days" class="hidden">
                                            {{ day }}
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-3 gap-2">
                                    <div>
                                        <InputLabel value="Start Time" class="text-[8px] font-bold uppercase text-slate-500 mb-0.5" />
                                        <input v-model="course.start_time" type="time" :class="inputClass" />
                                        <InputError :message="form.errors[`courses.${index}.start_time`]" class="mt-1 text-[8px] text-red-600 font-bold" />
                                    </div>
                                    <div>
                                        <InputLabel value="End Time" class="text-[8px] font-bold uppercase text-slate-500 mb-0.5" />
                                        <input v-model="course.end_time" type="time" :class="inputClass" />
                                    </div>
                                    <div>
                                        <InputLabel value="Room" class="text-[8px] font-bold uppercase text-slate-500 mb-0.5" />
                                        <input v-model="course.room" type="text" :class="inputClass" placeholder="e.g. Lab 1" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- FIXED FOOTER -->
                    <div class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-700 flex justify-between items-center shrink-0">
                        <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 hidden sm:inline">Total: {{ form.courses.length }}</span>
                        <div class="flex gap-2 w-full sm:w-auto justify-end">
                            <button type="button" @click="isCreateModalOpen = false" class="text-[9px] text-slate-500 px-3 py-1.5 font-bold hover:text-slate-700 uppercase tracking-widest transition">Cancel</button>
                            <button :disabled="form.processing" class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-1.5 rounded text-[9px] uppercase tracking-widest font-black shadow-sm transition flex-1 sm:flex-none">
                                Save All
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- ========================================== -->
        <!-- EDITED MODAL (NOW SCROLLABLE)              -->
        <!-- ========================================== -->
        <Modal :show="isEditModalOpen" :closeable="false" @close="isEditModalOpen = false" maxWidth="md">
            <div class="p-4 sm:p-5 bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-slate-200 dark:border-slate-700 flex flex-col max-h-[90vh]">
                <h2 class="text-sm sm:text-base font-bold text-slate-900 dark:text-white mb-3 shrink-0">Edit Course</h2>
                
                <form @submit.prevent="submitEdit" class="flex flex-col min-h-0">
                    <div class="flex-1 overflow-y-auto custom-scrollbar pr-1 sm:pr-2 pb-2 space-y-3">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                            <div class="sm:col-span-2">
                                <InputLabel value="Course Title" class="text-[8px] font-bold uppercase text-slate-500 mb-0.5" />
                                <input v-model="editForm.title" type="text" :class="inputClass" required />
                                <InputError :message="editForm.errors.title" class="mt-1 text-[9px]" />
                            </div>

                            <div>
                                <InputLabel value="Teacher" class="text-[8px] font-bold uppercase text-slate-500 mb-0.5" />
                                <select v-model="editForm.teacher_id" :class="inputClass" class="cursor-pointer" required>
                                    <option value="" disabled>Select a teacher...</option>
                                    <option v-for="teacher in teachers" :key="teacher.id" :value="teacher.id">{{ teacher.name }}</option>
                                </select>
                            </div>

                            <div>
                                <InputLabel value="Year Level" class="text-[8px] font-bold uppercase text-slate-500 mb-0.5" />
                                <select v-model="editForm.difficulty_level" :class="inputClass" class="cursor-pointer" required>
                                    <option value="beginner">1st Year</option>
                                    <option value="intermediate">2nd Year</option>
                                    <option value="advanced">3rd Year</option>
                                    <option value="final">4th Year</option>
                                </select>
                            </div>
                        </div>

                        <div class="border border-blue-100 dark:border-blue-900/50 rounded-lg p-2.5 bg-blue-50/30 dark:bg-blue-900/10">
                            <h4 class="text-[8px] font-black uppercase tracking-widest text-blue-600 dark:text-blue-400 mb-2">Update Schedule</h4>
                            <div class="mb-2">
                                <div class="flex gap-1 flex-wrap sm:flex-nowrap">
                                    <label v-for="day in daysOfWeek" :key="day" 
                                        class="flex-1 text-center border rounded cursor-pointer text-[9px] py-1 transition-colors shadow-sm min-w-[30px]"
                                        :class="editForm.days.includes(day) ? 'bg-blue-600 text-white border-blue-600 font-bold' : 'bg-white text-slate-500'">
                                        <input type="checkbox" :value="day" v-model="editForm.days" class="hidden">
                                        {{ day }}
                                    </label>
                                </div>
                            </div>
                            <div class="grid grid-cols-3 gap-2">
                                <div>
                                    <InputLabel value="Start Time" class="text-[8px] font-bold uppercase text-slate-500 mb-0.5" />
                                    <input v-model="editForm.start_time" type="time" :class="inputClass" />
                                    <InputError :message="editForm.errors.start_time" class="mt-1 text-[8px] text-red-600 font-bold" />
                                </div>
                                <div>
                                    <InputLabel value="End Time" class="text-[8px] font-bold uppercase text-slate-500 mb-0.5" />
                                    <input v-model="editForm.end_time" type="time" :class="inputClass" />
                                </div>
                                <div>
                                    <InputLabel value="Room" class="text-[8px] font-bold uppercase text-slate-500 mb-0.5" />
                                    <input v-model="editForm.room" type="text" :class="inputClass" />
                                </div>
                            </div>
                        </div>

                        <div>
                            <InputLabel value="Thumbnail (Optional)" class="text-[8px] font-bold uppercase text-slate-500 mb-0.5" />
                            <input type="file" @change="handleEditThumbnailUpload" accept="image/jpeg, image/png, image/jpg" class="w-full text-[9px] text-slate-500 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-[8px] file:font-bold file:uppercase file:bg-amber-50 file:text-amber-700 cursor-pointer border border-slate-200 rounded p-1" />
                        </div>
                    </div>

                    <div class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-700 flex justify-end gap-2 shrink-0">
                        <button type="button" @click="isEditModalOpen = false" class="text-[9px] text-slate-500 px-3 py-1.5 font-bold hover:text-slate-700 uppercase tracking-widest">Cancel</button>
                        <button :disabled="editForm.processing" class="bg-amber-500 hover:bg-amber-400 text-white px-4 py-1.5 rounded text-[9px] uppercase tracking-widest font-black shadow-sm transition">Update</button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- ... [Bulk Delete Modal remains at bottom] ... -->
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
.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
.custom-scrollbar::-webkit-scrollbar { width: 3px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(148, 163, 184, 0.3); border-radius: 10px; }
</style>