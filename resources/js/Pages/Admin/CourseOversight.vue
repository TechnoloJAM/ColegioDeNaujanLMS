<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import { Head, router, useForm, Link, usePage } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import * as XLSX from 'xlsx';
import { Plus, Download, Search, Filter, BookOpen, Calendar, Trash2, ShieldAlert, Users } from 'lucide-vue-next';

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

// Bulk Status (Publish/Draft) Modal Logic
const isBulkStatusModalOpen = ref(false);
const bulkStatusForm = useForm({ password: '', course_ids: [], action: '' });

// Enter Course Password Security Modal Logic
const isEnterModalOpen = ref(false);
const targetCourseId = ref(null);
const enterForm = useForm({ password: '' });

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
        if (sortOrder.value === 'oldest') return new Date(a.created_at || 0).getTime() - new Date(a.created_at || 0).getTime();
        if (sortOrder.value === 'students_high') return (b.enrollments ? b.enrollments.length : 0) - (a.enrollments ? a.enrollments.length : 0);
        if (sortOrder.value === 'students_low') return (a.enrollments ? a.enrollments.length : 0) - (b.enrollments ? b.enrollments.length : 0);
        return 0;
    });
});

watch(activeTab, () => { selectedIds.value = []; });
watch([selectedTeacherFilter, selectedYearFilter, searchQuery, sortOrder], () => { selectedIds.value = []; });

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

// Status Change Modal
const openBulkStatus = (action, singleId = null) => {
    bulkStatusForm.action = action;
    bulkStatusForm.course_ids = singleId ? [singleId] : selectedIds.value;
    bulkStatusForm.password = '';
    bulkStatusForm.clearErrors();
    isBulkStatusModalOpen.value = true;
};

const submitBulkStatus = () => {
    bulkStatusForm.post(route('admin.courses.bulk-toggle-status'), {
        preserveScroll: true,
        onSuccess: () => {
            isBulkStatusModalOpen.value = false;
            selectedIds.value = [];
        }
    });
};

// Enter Course Security Check
const openEnterModal = (courseId) => {
    targetCourseId.value = courseId;
    enterForm.password = '';
    enterForm.clearErrors();
    isEnterModalOpen.value = true;
};

const submitEnterCourse = () => {
    enterForm.post(route('admin.courses.enter', targetCourseId.value), {
        preserveScroll: true,
        onSuccess: () => {
            isEnterModalOpen.value = false;
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
        
        <!-- MOBILE FLOATING FAB: Placed perfectly above chat widget, with black/white borders & Tooltips -->
        <div class="md:hidden fixed bottom-[150px] right-4 z-[999] flex flex-col gap-2 items-center pointer-events-none">
            <button @click="isCreateModalOpen = true" class="group relative pointer-events-auto flex items-center justify-center w-10 h-10 bg-white dark:bg-slate-800 text-blue-600 dark:text-blue-500 rounded-full border border-black dark:border-white shadow-[0_8px_30px_rgba(0,0,0,0.15)] transition-transform active:scale-95">
                <Plus class="w-5 h-5" />
                <span class="absolute right-full mr-2 bg-slate-800 text-white text-[9px] font-bold px-2 py-1 rounded opacity-0 group-hover:opacity-100 group-active:opacity-100 pointer-events-none whitespace-nowrap transition-opacity z-50">Batch Create</span>
            </button>
            <button @click="exportToExcel" class="group relative pointer-events-auto flex items-center justify-center w-10 h-10 bg-white dark:bg-slate-800 text-emerald-600 dark:text-emerald-400 rounded-full border border-black dark:border-white shadow-[0_8px_30px_rgba(0,0,0,0.15)] transition-transform active:scale-95">
                <Download class="w-4 h-4" />
                <span class="absolute right-full mr-2 bg-slate-800 text-white text-[9px] font-bold px-2 py-1 rounded opacity-0 group-hover:opacity-100 group-active:opacity-100 pointer-events-none whitespace-nowrap transition-opacity z-50">Export Excel</span>
            </button>
        </div>

        <div class="mb-3 flex justify-between items-center max-w-7xl mx-auto px-3 sm:px-6">
             <div class="flex items-center gap-3">
                 <div>
                    <h1 class="text-lg sm:text-xl font-black text-slate-900 dark:text-white leading-tight tracking-tight">Course Oversight</h1>
                    <p class="text-slate-500 dark:text-slate-400 text-[9px] sm:text-[10px] uppercase font-bold tracking-wider">Manage curriculum and schedules</p>
                 </div>
             </div>
        </div>

        <div class="max-w-7xl mx-auto px-3 sm:px-6 flex flex-col md:flex-row gap-3 md:gap-5 items-start">
            
            <aside class="hidden md:flex w-12 shrink-0 flex-col gap-3 sticky top-6 z-10 order-1">
                <button @click="isCreateModalOpen = true" class="group relative flex items-center justify-center w-12 h-12 bg-white dark:bg-slate-800 rounded-full border-2 border-slate-200 dark:border-slate-700 text-blue-600 hover:border-blue-600 hover:bg-blue-50 dark:hover:bg-slate-700 transition shadow-sm focus:outline-none shrink-0">
                    <Plus class="w-5 h-5" />
                    <span class="absolute left-full ml-3 px-2 py-1 bg-slate-800 text-white text-[9px] font-bold rounded opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none whitespace-nowrap shadow-lg">Batch Create</span>
                </button>

                <button @click="exportToExcel" class="group relative flex items-center justify-center w-12 h-12 bg-white dark:bg-slate-800 rounded-full border-2 border-slate-200 dark:border-slate-700 text-emerald-600 hover:border-emerald-600 hover:bg-emerald-50 dark:hover:bg-slate-700 transition shadow-sm focus:outline-none shrink-0">
                    <Download class="w-5 h-5" />
                    <span class="absolute left-full ml-3 px-2 py-1 bg-slate-800 text-white text-[9px] font-bold rounded opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none whitespace-nowrap shadow-lg">Export Excel</span>
                </button>
            </aside>

            <div class="flex-1 min-w-0 w-full order-2 pb-24 md:pb-6">
                
                <!-- RESPONSIVE SEARCH & FILTER CARD -->
                <div class="bg-white dark:bg-slate-800 p-1.5 sm:p-2.5 rounded-lg sm:rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm mb-4 flex flex-row sm:flex-col lg:flex-row gap-1.5 sm:gap-2.5 items-center sm:items-stretch lg:items-center min-w-0">
                    
                    <!-- Search -->
                    <div class="relative flex-1 min-w-[120px] sm:min-w-[200px]">
                        <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none">
                            <Search class="h-3.5 w-3.5 text-slate-400" />
                        </div>
                        <input v-model="searchQuery" type="text" placeholder="Search courses..." class="w-full h-8 pl-8 rounded sm:rounded-md bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-600 sm:border-slate-700 text-slate-900 dark:text-white placeholder-slate-400 focus:ring-1 sm:focus:ring-2 focus:ring-blue-500 focus:border-transparent text-xs shadow-sm transition-colors" />
                    </div>

                    <!-- MOBILE FILTERS (Icon Only, Overlay Select, With Tooltips) -->
                    <div class="flex sm:hidden flex-row gap-1.5 shrink-0">
                        <div class="relative shrink-0 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-600 rounded flex items-center justify-center w-8 h-8 shadow-sm transition group hover:bg-slate-50 dark:hover:bg-slate-700">
                            <Users class="w-4 h-4 text-slate-500 dark:text-slate-400 pointer-events-none" />
                            <select v-model="selectedTeacherFilter" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer dark:[color-scheme:dark]">
                                <option value="all" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">All Instructors</option>
                                <option v-for="teacher in teachers" :key="teacher.id" :value="teacher.id" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">{{ teacher.name }}</option>
                            </select>
                            <span class="absolute bottom-full right-0 mb-1 bg-slate-800 text-white text-[9px] px-2 py-1 rounded opacity-0 group-hover:opacity-100 group-active:opacity-100 pointer-events-none whitespace-nowrap transition-opacity z-50">Instructor</span>
                        </div>
                        
                        <div class="relative shrink-0 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-600 rounded flex items-center justify-center w-8 h-8 shadow-sm transition group hover:bg-slate-50 dark:hover:bg-slate-700">
                            <Calendar class="w-4 h-4 text-slate-500 dark:text-slate-400 pointer-events-none" />
                            <select v-model="selectedYearFilter" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer dark:[color-scheme:dark]">
                                <option value="all" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">All Years</option>
                                <option value="beginner" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">1st Year</option>
                                <option value="intermediate" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">2nd Year</option>
                                <option value="advanced" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">3rd Year</option>
                                <option value="final" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">4th Year</option>
                            </select>
                            <span class="absolute bottom-full right-0 mb-1 bg-slate-800 text-white text-[9px] px-2 py-1 rounded opacity-0 group-hover:opacity-100 group-active:opacity-100 pointer-events-none whitespace-nowrap transition-opacity z-50">Year Level</span>
                        </div>

                        <div class="relative shrink-0 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-600 rounded flex items-center justify-center w-8 h-8 shadow-sm transition group hover:bg-slate-50 dark:hover:bg-slate-700">
                            <Filter class="w-4 h-4 text-slate-500 dark:text-slate-400 pointer-events-none" />
                            <select v-model="sortOrder" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer dark:[color-scheme:dark]">
                                <option value="newest" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">Newest First</option>
                                <option value="oldest" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">Oldest First</option>
                                <option value="students_high" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">Most Students</option>
                                <option value="students_low" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">Least Students</option>
                            </select>
                            <span class="absolute bottom-full right-0 mb-1 bg-slate-800 text-white text-[9px] px-2 py-1 rounded opacity-0 group-hover:opacity-100 group-active:opacity-100 pointer-events-none whitespace-nowrap transition-opacity z-50">Sort</span>
                        </div>
                    </div>

                    <!-- DESKTOP FILTERS (Expanded with Text) -->
                    <div class="hidden sm:grid grid-cols-3 lg:flex lg:flex-row gap-2 w-full lg:w-auto shrink-0 mt-1 lg:mt-0">
                        <div class="shrink-0 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg px-2 py-1 shadow-sm flex items-center gap-1.5 min-w-[110px]">
                            <Users class="w-3.5 h-3.5 text-slate-400 shrink-0" />
                            <select v-model="selectedTeacherFilter" class="bg-transparent border-none text-[9px] font-bold uppercase tracking-widest text-slate-600 dark:text-slate-300 w-full focus:ring-0 cursor-pointer p-0 m-0 truncate dark:[color-scheme:dark]">
                                <option value="all" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">All Instructors</option>
                                <option v-for="teacher in teachers" :key="teacher.id" :value="teacher.id" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">{{ teacher.name }}</option>
                            </select>
                        </div>

                        <div class="shrink-0 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg px-2 py-1 shadow-sm flex items-center gap-1.5 min-w-[100px]">
                            <Calendar class="w-3.5 h-3.5 text-slate-400 shrink-0" />
                            <select v-model="selectedYearFilter" class="bg-transparent border-none text-[9px] font-bold uppercase tracking-widest text-slate-600 dark:text-slate-300 w-full focus:ring-0 cursor-pointer p-0 m-0 truncate dark:[color-scheme:dark]">
                                <option value="all" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">All Years</option>
                                <option value="beginner" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">1st Year</option>
                                <option value="intermediate" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">2nd Year</option>
                                <option value="advanced" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">3rd Year</option>
                                <option value="final" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">4th Year</option>
                            </select>
                        </div>

                        <div class="shrink-0 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-lg px-2 py-1 shadow-sm flex items-center gap-1.5 min-w-[110px]">
                            <Filter class="w-3.5 h-3.5 text-slate-400 shrink-0" />
                            <select v-model="sortOrder" class="bg-transparent border-none text-[9px] font-bold uppercase tracking-widest text-slate-600 dark:text-slate-300 w-full focus:ring-0 cursor-pointer p-0 m-0 truncate dark:[color-scheme:dark]">
                                <option value="newest" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">Newest First</option>
                                <option value="oldest" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">Oldest First</option>
                                <option value="students_high" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">Most Students</option>
                                <option value="students_low" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">Least Students</option>
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
                        
                        <button @click="openBulkStatus('publish')" class="text-[8px] bg-emerald-600 hover:bg-emerald-500 text-white px-2.5 py-1 rounded uppercase tracking-widest font-black shadow-sm transition">Publish</button>
                        <button @click="openBulkStatus('draft')" class="text-[8px] bg-slate-600 hover:bg-slate-500 text-white px-2.5 py-1 rounded uppercase tracking-widest font-black shadow-sm transition">Draft</button>

                        <button @click="openBulkDelete()" class="text-[8px] bg-red-600 hover:bg-red-500 text-white px-2.5 py-1 rounded uppercase tracking-widest font-black shadow-sm transition">Delete All</button>
                    </div>
                </transition>

                <!-- DESKTOP TABLE VIEW -->
                <div class="hidden md:block bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden mb-8">
                    <div class="overflow-x-auto custom-scrollbar">
                        <table class="w-full text-left text-[10px] sm:text-xs text-slate-500 dark:text-slate-400 whitespace-nowrap">
                            <thead class="text-[8px] sm:text-[9px] uppercase font-bold text-slate-400 bg-slate-50 dark:bg-slate-900/30 border-b border-slate-100 dark:border-slate-700">
                                <tr>
                                    <th class="px-2 sm:px-3 py-1.5 w-6 sm:w-8"><input type="checkbox" :checked="isAllSelected && filteredCourses.length > 0" @change="toggleAll" class="rounded border-slate-300 dark:border-slate-600 text-blue-600 focus:ring-blue-500 dark:bg-slate-800 cursor-pointer shadow-sm" /></th>
                                    <th class="px-2 py-1.5 w-full">Course Details</th>
                                    <th class="px-2 py-1.5">Schedule & Room</th>
                                    <th class="px-2 py-1.5 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                <tr v-for="course in filteredCourses" :key="course.id" class="transition" :class="selectedIds.includes(course.id) ? 'bg-blue-50/50 dark:bg-blue-900/10' : 'hover:bg-slate-50 dark:hover:bg-slate-700/50'">
                                    
                                    <td class="px-2 sm:px-3 py-1.5"><input type="checkbox" :checked="selectedIds.includes(course.id)" @change="toggleSelection(course.id)" class="rounded border-slate-300 dark:border-slate-600 text-blue-600 focus:ring-blue-500 dark:bg-slate-800 cursor-pointer shadow-sm" /></td>

                                    <td class="px-2 py-1.5 cursor-pointer" @click="toggleSelection(course.id)">
                                        <div class="flex items-center gap-2">
                                            <div v-if="course.thumbnail" class="w-5 h-5 sm:w-6 sm:h-6 rounded bg-slate-200 shrink-0 overflow-hidden">
                                                <img :src="course.thumbnail" class="w-full h-full object-cover" />
                                            </div>
                                            <div v-else class="w-5 h-5 sm:w-6 sm:h-6 rounded bg-blue-100 dark:bg-blue-900/30 text-blue-600 flex items-center justify-center shrink-0">
                                                <span class="text-[8px] font-black uppercase">{{ formatYearLevel(course.difficulty_level).charAt(0) }}Y</span>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <div class="flex items-center gap-1.5 mb-0.5">
                                                    <span v-if="course.is_published" class="text-[6px] font-black uppercase tracking-widest bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 px-1 py-0.5 rounded border border-emerald-200 dark:border-emerald-800 shrink-0">Live</span>
                                                    <span v-else class="text-[6px] font-black uppercase tracking-widest bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-400 px-1 py-0.5 rounded border border-slate-300 dark:border-slate-600 shrink-0">Draft</span>
                                                    
                                                    <div class="font-bold text-slate-900 dark:text-white truncate max-w-[200px] sm:max-w-xs leading-tight text-[10px] sm:text-xs">{{ course.title }}</div>
                                                </div>
                                                <div class="text-[8px] sm:text-[9px] truncate max-w-[200px] sm:max-w-xs leading-tight opacity-80 text-blue-600 dark:text-blue-400">{{ course.teacher ? course.teacher.name : 'Unassigned' }}</div>
                                            </div>
                                        </div>
                                    </td>
                                    
                                    <td class="px-2 py-1.5 align-middle">
                                        <div v-if="course.days && course.days.length > 0" class="flex flex-col justify-center h-full">
                                            <div class="flex gap-1 mb-1">
                                                <span v-for="d in course.days" :key="d" class="bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400 px-1 py-0.5 rounded text-[7px] font-black uppercase tracking-widest">{{ d }}</span>
                                            </div>
                                            <span class="text-[8px] font-bold text-slate-500 flex items-center gap-1 mt-0.5">
                                                <svg class="w-2.5 h-2.5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                {{ course.start_time ? course.start_time.substring(0,5) : '' }} - {{ course.end_time ? course.end_time.substring(0,5) : '' }}
                                            </span>
                                        </div>
                                        <div v-else class="text-[8px] text-slate-400 italic">No schedule set</div>
                                    </td>
                                    
                                    <td class="px-2 py-1.5 text-right align-middle">
                                        <div class="flex items-center justify-end gap-1 min-w-[80px]">
                                            <button v-if="!hiddenCourses.includes(course.id)" @click="toggleHideSingle(course.id)" class="p-1.5 text-slate-400 hover:text-slate-700 bg-white hover:bg-slate-100 dark:bg-transparent dark:hover:bg-slate-800 rounded transition shadow-sm border border-transparent" title="Hide (Local)">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.29 3.29m0 0a10.05 10.05 0 013.825-1.542m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.29 3.29m0 0a10.05 10.05 0 013.825-1.542m5.858.908A9.97 9.97 0 0121 12c-1.274-4.057-5.064-7-9.542-7-1.274 0-2.483.253-3.582.71" /></svg>
                                            </button>
                                            <button v-else @click="toggleHideSingle(course.id)" class="p-1.5 text-emerald-500 hover:text-emerald-700 bg-white hover:bg-emerald-50 dark:bg-transparent dark:hover:bg-emerald-900/30 rounded transition shadow-sm border border-transparent" title="Restore (Local)">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                            </button>

                                            <!-- Enter Course Button with Admin Password Security -->
                                            <button @click="openEnterModal(course.id)" class="text-[8px] font-black uppercase tracking-widest bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 px-2 py-1.5 rounded flex items-center gap-1 hover:bg-blue-200 dark:hover:bg-blue-800/50 transition shadow-sm" title="Enter Class">
                                                <svg class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
                                                <span class="hidden xl:inline">Enter</span>
                                            </button>

                                            <!-- Publish/Draft Action Button -->
                                            <button v-if="!course.is_published" @click="openBulkStatus('publish', course.id)" class="text-[8px] font-black uppercase tracking-widest bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 px-1.5 sm:px-2 py-1 sm:py-1.5 rounded flex items-center gap-1 hover:bg-emerald-200 dark:hover:bg-emerald-800/50 transition shadow-sm" title="Publish Course">
                                                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                                                <span class="hidden xl:inline">Publish</span>
                                            </button>
                                            <button v-else @click="openBulkStatus('draft', course.id)" class="text-[8px] font-black uppercase tracking-widest bg-slate-100 text-slate-700 dark:bg-slate-700/50 dark:text-slate-300 px-1.5 sm:px-2 py-1 sm:py-1.5 rounded flex items-center gap-1 hover:bg-slate-200 dark:hover:bg-slate-600 transition shadow-sm" title="Set to Draft">
                                                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                                <span class="hidden xl:inline">Draft</span>
                                            </button>

                                            <button @click="openEditModal(course)" class="text-[8px] font-bold uppercase tracking-wide bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 px-2 py-1.5 rounded flex items-center gap-1 hover:bg-slate-200 transition shadow-sm">
                                                Edit
                                            </button>

                                            <button @click="openBulkDelete(course.id)" class="p-1.5 text-slate-400 hover:text-red-600 bg-white hover:bg-red-50 dark:bg-transparent dark:hover:bg-red-900/30 rounded transition shadow-sm border border-transparent hover:border-red-200" title="Delete">
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

                <!-- COMPACT MOBILE CARDS VIEW -->
                <div class="md:hidden flex flex-col gap-2 pb-8">
                    <div v-for="course in filteredCourses" :key="course.id" class="p-2 sm:p-2.5 flex items-center gap-2.5 rounded-lg border shadow-sm transition-colors" :class="selectedIds.includes(course.id) ? 'bg-blue-50/50 border-blue-200 dark:bg-blue-900/10 dark:border-blue-800' : 'bg-white border-slate-200 dark:bg-slate-800 dark:border-slate-700'">
                        
                        <input type="checkbox" :checked="selectedIds.includes(course.id)" @change="toggleSelection(course.id)" class="rounded border-slate-300 dark:border-slate-600 text-blue-600 focus:ring-blue-500 dark:bg-slate-800 cursor-pointer shadow-sm shrink-0 w-3.5 h-3.5" />
                        
                        <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-slate-200 dark:bg-slate-700 shrink-0 overflow-hidden shadow-sm flex items-center justify-center">
                            <img v-if="course.thumbnail" :src="course.thumbnail" class="w-full h-full object-cover" />
                            <span v-else class="text-[8px] sm:text-[10px] font-black text-blue-600 dark:text-blue-400">{{ formatYearLevel(course.difficulty_level).charAt(0) }}Y</span>
                        </div>
                        
                        <div class="flex-1 min-w-0 flex flex-col justify-center" @click="toggleSelection(course.id)">
                            <div class="flex items-center gap-1.5 truncate">
                                <span v-if="course.is_published" class="text-[6px] font-black uppercase tracking-widest bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 px-1 py-0.5 rounded border border-emerald-200 dark:border-emerald-800 shrink-0">Live</span>
                                <span v-else class="text-[6px] font-black uppercase tracking-widest bg-slate-200 text-slate-600 dark:bg-slate-700 dark:text-slate-400 px-1 py-0.5 rounded border border-slate-300 dark:border-slate-600 shrink-0">Draft</span>
                                <span class="font-black text-slate-900 dark:text-white truncate text-[10px] sm:text-[11px] leading-none">{{ course.title }}</span>
                            </div>
                            <div class="text-[8px] sm:text-[9px] font-bold text-slate-500 dark:text-slate-400 truncate mt-1">
                                {{ course.teacher ? course.teacher.name : 'Unassigned' }} &bull; {{ course.enrollments ? course.enrollments.length : 0 }} Studs
                            </div>
                        </div>

                        <!-- Horizontal Action Buttons with Mobile Tooltips -->
                        <div class="flex items-center gap-1 sm:gap-1.5 shrink-0 pl-1 border-l border-slate-100 dark:border-slate-700/50">
                            
                            <!-- Hide/Restore -->
                            <button v-if="!hiddenCourses.includes(course.id)" @click="toggleHideSingle(course.id)" class="group relative p-1 sm:p-1.5 text-slate-400 hover:text-slate-700 bg-slate-50 dark:bg-slate-700/50 rounded shadow-sm border border-slate-200 dark:border-slate-600">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.29 3.29m0 0a10.05 10.05 0 013.825-1.542m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.29 3.29m0 0a10.05 10.05 0 013.825-1.542m5.858.908A9.97 9.97 0 0121 12c-1.274-4.057-5.064-7-9.542-7-1.274 0-2.483.253-3.582.71" /></svg>
                                <span class="absolute bottom-full right-0 mb-1.5 bg-slate-800 text-white text-[9px] font-bold px-2 py-1 rounded opacity-0 group-hover:opacity-100 group-active:opacity-100 pointer-events-none whitespace-nowrap transition-opacity z-50">Hide</span>
                            </button>
                            <button v-else @click="toggleHideSingle(course.id)" class="group relative p-1 sm:p-1.5 text-emerald-500 hover:text-emerald-700 bg-emerald-50 dark:bg-emerald-900/30 rounded shadow-sm border border-emerald-200 dark:border-emerald-800">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>
                                <span class="absolute bottom-full right-0 mb-1.5 bg-slate-800 text-white text-[9px] font-bold px-2 py-1 rounded opacity-0 group-hover:opacity-100 group-active:opacity-100 pointer-events-none whitespace-nowrap transition-opacity z-50">Restore</span>
                            </button>

                            <!-- Enter -->
                            <button @click="openEnterModal(course.id)" class="group relative p-1 sm:p-1.5 text-blue-600 bg-blue-50 dark:bg-blue-900/30 dark:text-blue-400 rounded border border-blue-200 dark:border-blue-800 shadow-sm">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M13 7l5 5m0 0l-5 5m5-5H6" /></svg>
                                <span class="absolute bottom-full right-0 mb-1.5 bg-slate-800 text-white text-[9px] font-bold px-2 py-1 rounded opacity-0 group-hover:opacity-100 group-active:opacity-100 pointer-events-none whitespace-nowrap transition-opacity z-50">Enter</span>
                            </button>

                            <!-- Publish/Draft -->
                            <button v-if="!course.is_published" @click="openBulkStatus('publish', course.id)" class="group relative p-1 sm:p-1.5 text-emerald-600 bg-emerald-50 dark:bg-emerald-900/30 dark:text-emerald-400 rounded border border-emerald-200 dark:border-emerald-800 shadow-sm">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path></svg>
                                <span class="absolute bottom-full right-0 mb-1.5 bg-slate-800 text-white text-[9px] font-bold px-2 py-1 rounded opacity-0 group-hover:opacity-100 group-active:opacity-100 pointer-events-none whitespace-nowrap transition-opacity z-50">Publish</span>
                            </button>
                            <button v-else @click="openBulkStatus('draft', course.id)" class="group relative p-1 sm:p-1.5 text-slate-600 bg-slate-100 dark:bg-slate-700/50 rounded border border-slate-300 dark:border-slate-600 shadow-sm">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                <span class="absolute bottom-full right-0 mb-1.5 bg-slate-800 text-white text-[9px] font-bold px-2 py-1 rounded opacity-0 group-hover:opacity-100 group-active:opacity-100 pointer-events-none whitespace-nowrap transition-opacity z-50">Draft</span>
                            </button>

                            <!-- Edit -->
                            <button @click="openEditModal(course)" class="group relative p-1 sm:p-1.5 text-slate-600 bg-slate-100 dark:bg-slate-700 dark:text-slate-300 rounded border border-slate-300 dark:border-slate-600 shadow-sm">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                <span class="absolute bottom-full right-0 mb-1.5 bg-slate-800 text-white text-[9px] font-bold px-2 py-1 rounded opacity-0 group-hover:opacity-100 group-active:opacity-100 pointer-events-none whitespace-nowrap transition-opacity z-50">Edit</span>
                            </button>

                            <!-- Delete -->
                            <button @click="openBulkDelete(course.id)" class="group relative p-1 sm:p-1.5 text-red-600 bg-red-50 dark:bg-red-900/30 dark:text-red-400 rounded border border-red-200 dark:border-red-800 shadow-sm">
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                                <span class="absolute bottom-full right-0 mb-1.5 bg-slate-800 text-white text-[9px] font-bold px-2 py-1 rounded opacity-0 group-hover:opacity-100 group-active:opacity-100 pointer-events-none whitespace-nowrap transition-opacity z-50">Delete</span>
                            </button>
                        </div>
                    </div>
                    
                    <div v-if="filteredCourses.length === 0" class="px-2 py-8 text-center text-slate-400 dark:text-slate-500 text-[10px] bg-white dark:bg-slate-800 rounded-lg border border-dashed border-slate-200 dark:border-slate-700">
                        <div class="font-black uppercase tracking-widest mb-1 text-slate-300 dark:text-slate-600">No Courses Found</div>
                    </div>
                </div>

            </div>
        </div>

        <!-- ========================================== -->
        <!-- BATCH CREATE MODAL                         -->
        <!-- ========================================== -->
        <Modal :show="isCreateModalOpen" :closeable="false" @close="isCreateModalOpen = false" maxWidth="lg">
            <div class="p-4 sm:p-5 bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-slate-200 dark:border-slate-700 flex flex-col max-h-[90vh]">
                <h2 class="text-sm sm:text-base font-bold text-slate-900 dark:text-white mb-3 shrink-0">Batch Create & Distribute</h2>
                
                <form @submit.prevent="submitCourse" class="flex flex-col min-h-0">
                    
                    <div class="flex-1 overflow-y-auto custom-scrollbar pr-1 sm:pr-2 pb-2">
                        <div class="mb-3 bg-slate-50 dark:bg-slate-900 p-2.5 rounded-lg border border-slate-200 dark:border-slate-700">
                            <InputLabel value="1. Select Instructor" class="text-[9px] font-black uppercase tracking-widest text-blue-600 mb-1" />
                            <select v-model="form.teacher_id" :class="inputClass" class="cursor-pointer font-bold dark:[color-scheme:dark]" required>
                                <option value="" disabled class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">Select an instructor...</option>
                                <option v-for="teacher in teachers" :key="teacher.id" :value="teacher.id" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">{{ teacher.name }}</option>
                            </select>
                            <InputError :message="form.errors.teacher_id" class="mt-1 text-[9px]" />
                        </div>

                        <div class="flex overflow-x-auto gap-2 border-b border-slate-200 dark:border-slate-700 pb-2 mb-3 no-scrollbar">
                            <button v-for="(course, index) in form.courses" :key="index" @click="activeCourseTab = index" type="button"
                                class="px-2.5 py-1 rounded-md text-[9px] font-black uppercase tracking-widest whitespace-nowrap transition-colors"
                                :class="activeCourseTab === index ? 'bg-blue-600 text-white shadow-sm' : 'bg-slate-100 text-slate-500 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-400 dark:hover:bg-slate-600'">
                                Subj {{ index + 1 }}
                            </button>
                            <button type="button" @click="addCourseTab" class="px-2.5 py-1 rounded-md bg-emerald-100 text-emerald-600 hover:bg-emerald-200 dark:bg-emerald-900/40 dark:text-emerald-400 dark:hover:bg-emerald-900/60 text-[9px] font-black uppercase transition-colors shrink-0 flex items-center gap-1">
                                <Plus class="w-3 h-3" /> Add
                            </button>
                        </div>

                        <div v-for="(course, index) in form.courses" :key="'content-'+index" v-show="activeCourseTab === index" class="space-y-3">
                            <div class="flex justify-between items-center bg-slate-50 dark:bg-slate-900/50 px-2 py-1.5 rounded border border-slate-100 dark:border-slate-700">
                                <span class="text-[9px] font-black text-slate-500 uppercase tracking-widest">Subject {{ index + 1 }}</span>
                                <button v-if="form.courses.length > 1" @click="removeCourseTab(index)" type="button" class="text-[8px] font-bold text-red-500 hover:text-red-700 uppercase tracking-widest flex items-center gap-1">
                                    <Trash2 class="w-3 h-3" /> Remove
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
                                    <select v-model="course.difficulty_level" :class="inputClass" required class="dark:[color-scheme:dark]">
                                        <option value="beginner" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">1st Year</option>
                                        <option value="intermediate" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">2nd Year</option>
                                        <option value="advanced" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">3rd Year</option>
                                        <option value="final" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">4th Year</option>
                                    </select>
                                </div>
                                <div>
                                    <InputLabel value="Description" class="text-[8px] font-bold uppercase text-slate-500 mb-0.5" />
                                    <input v-model="course.description" type="text" :class="inputClass" />
                                </div>
                            </div>

                            <div class="border border-blue-100 dark:border-blue-900/50 rounded-lg p-2.5 bg-blue-50/30 dark:bg-blue-900/10">
                                <h4 class="text-[8px] font-black uppercase tracking-widest text-blue-600 dark:text-blue-400 mb-2 flex items-center gap-1">
                                    <Calendar class="w-3 h-3" />
                                    Schedule & Room
                                </h4>
                                
                                <div class="mb-2">
                                    <div class="flex gap-1 flex-wrap sm:flex-nowrap">
                                        <label v-for="day in daysOfWeek" :key="day" 
                                            class="flex-1 text-center border rounded cursor-pointer text-[9px] py-1 transition-colors shadow-sm min-w-[30px]"
                                            :class="course.days.includes(day) ? 'bg-blue-600 text-white border-blue-600 font-bold' : 'bg-white dark:bg-slate-800 text-slate-500 dark:text-slate-400 border-slate-200 dark:border-slate-700'">
                                            <input type="checkbox" :value="day" v-model="course.days" class="hidden">
                                            {{ day }}
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-3 gap-2">
                                    <div>
                                        <InputLabel value="Start Time" class="text-[8px] font-bold uppercase text-slate-500 mb-0.5" />
                                        <input v-model="course.start_time" type="time" :class="inputClass" class="dark:[color-scheme:dark]"/>
                                        <InputError :message="form.errors[`courses.${index}.start_time`]" class="mt-1 text-[8px] text-red-600 font-bold" />
                                    </div>
                                    <div>
                                        <InputLabel value="End Time" class="text-[8px] font-bold uppercase text-slate-500 mb-0.5" />
                                        <input v-model="course.end_time" type="time" :class="inputClass" class="dark:[color-scheme:dark]"/>
                                    </div>
                                    <div>
                                        <InputLabel value="Room" class="text-[8px] font-bold uppercase text-slate-500 mb-0.5" />
                                        <input v-model="course.room" type="text" :class="inputClass" placeholder="e.g. Lab 1" />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-700 flex justify-between items-center shrink-0">
                        <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 hidden sm:inline">Total: {{ form.courses.length }}</span>
                        <div class="flex gap-2 w-full sm:w-auto justify-end">
                            <button type="button" @click="isCreateModalOpen = false" class="text-[9px] text-slate-500 px-3 py-1.5 font-bold hover:text-slate-700 dark:hover:text-slate-300 uppercase tracking-widest transition">Cancel</button>
                            <button :disabled="form.processing" class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-1.5 rounded text-[9px] uppercase tracking-widest font-black shadow-sm transition flex-1 sm:flex-none">
                                Save All
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- ========================================== -->
        <!-- EDITED MODAL                               -->
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
                                <select v-model="editForm.teacher_id" :class="inputClass" class="cursor-pointer dark:[color-scheme:dark]" required>
                                    <option value="" disabled class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">Select a teacher...</option>
                                    <option v-for="teacher in teachers" :key="teacher.id" :value="teacher.id" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">{{ teacher.name }}</option>
                                </select>
                            </div>

                            <div>
                                <InputLabel value="Year Level" class="text-[8px] font-bold uppercase text-slate-500 mb-0.5" />
                                <select v-model="editForm.difficulty_level" :class="inputClass" class="cursor-pointer dark:[color-scheme:dark]" required>
                                    <option value="beginner" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">1st Year</option>
                                    <option value="intermediate" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">2nd Year</option>
                                    <option value="advanced" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">3rd Year</option>
                                    <option value="final" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">4th Year</option>
                                </select>
                            </div>
                        </div>

                        <div class="border border-blue-100 dark:border-blue-900/50 rounded-lg p-2.5 bg-blue-50/30 dark:bg-blue-900/10">
                            <h4 class="text-[8px] font-black uppercase tracking-widest text-blue-600 dark:text-blue-400 mb-2">Update Schedule</h4>
                            <div class="mb-2">
                                <div class="flex gap-1 flex-wrap sm:flex-nowrap">
                                    <label v-for="day in daysOfWeek" :key="day" 
                                        class="flex-1 text-center border rounded cursor-pointer text-[9px] py-1 transition-colors shadow-sm min-w-[30px]"
                                        :class="editForm.days.includes(day) ? 'bg-blue-600 text-white border-blue-600 font-bold' : 'bg-white dark:bg-slate-800 text-slate-500 dark:text-slate-400 border-slate-200 dark:border-slate-700'">
                                        <input type="checkbox" :value="day" v-model="editForm.days" class="hidden">
                                        {{ day }}
                                    </label>
                                </div>
                            </div>
                            <div class="grid grid-cols-3 gap-2">
                                <div>
                                    <InputLabel value="Start Time" class="text-[8px] font-bold uppercase text-slate-500 mb-0.5" />
                                    <input v-model="editForm.start_time" type="time" :class="inputClass" class="dark:[color-scheme:dark]" />
                                    <InputError :message="editForm.errors.start_time" class="mt-1 text-[8px] text-red-600 font-bold" />
                                </div>
                                <div>
                                    <InputLabel value="End Time" class="text-[8px] font-bold uppercase text-slate-500 mb-0.5" />
                                    <input v-model="editForm.end_time" type="time" :class="inputClass" class="dark:[color-scheme:dark]" />
                                </div>
                                <div>
                                    <InputLabel value="Room" class="text-[8px] font-bold uppercase text-slate-500 mb-0.5" />
                                    <input v-model="editForm.room" type="text" :class="inputClass" />
                                </div>
                            </div>
                        </div>

                        <div>
                            <InputLabel value="Thumbnail (Optional)" class="text-[8px] font-bold uppercase text-slate-500 mb-0.5" />
                            <input type="file" @change="handleEditThumbnailUpload" accept="image/jpeg, image/png, image/jpg" class="w-full text-[9px] text-slate-500 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-[8px] file:font-bold file:uppercase file:bg-amber-50 file:text-amber-700 dark:file:bg-amber-900/30 dark:file:text-amber-400 cursor-pointer border border-slate-200 dark:border-slate-700 rounded p-1" />
                        </div>
                    </div>

                    <div class="mt-3 pt-3 border-t border-slate-100 dark:border-slate-700 flex justify-end gap-2 shrink-0">
                        <button type="button" @click="isEditModalOpen = false" class="text-[9px] text-slate-500 px-3 py-1.5 font-bold hover:text-slate-700 dark:hover:text-slate-300 uppercase tracking-widest">Cancel</button>
                        <button :disabled="editForm.processing" class="bg-amber-500 hover:bg-amber-400 text-white px-4 py-1.5 rounded text-[9px] uppercase tracking-widest font-black shadow-sm transition">Update</button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- ========================================== -->
        <!-- ENTER COURSE CONFIRMATION MODAL            -->
        <!-- ========================================== -->
        <Modal :show="isEnterModalOpen" :closeable="false" @close="isEnterModalOpen = false" maxWidth="sm">
            <div class="p-5 bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-slate-200 dark:border-slate-700">
                <h2 class="text-sm font-black uppercase tracking-tight text-blue-600 flex items-center gap-2 mb-2">
                    <ShieldAlert class="w-5 h-5 text-blue-500" />
                    Security Confirmation
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">
                    Enter your admin password to access and override this course classroom.
                </p>
                <form @submit.prevent="submitEnterCourse" class="space-y-4">
                    <div>
                        <InputLabel value="Admin Password *" class="text-[9px] font-bold uppercase text-slate-500 mb-1" />
                        <input v-model="enterForm.password" type="password" :class="inputClass" placeholder="Enter your password" required autofocus />
                        <InputError :message="enterForm.errors.password" class="mt-1 text-[9px]" />
                    </div>
                    <div class="mt-5 pt-3 border-t border-slate-100 dark:border-slate-800 flex justify-end gap-2">
                        <button type="button" @click="isEnterModalOpen = false" class="text-[10px] text-slate-500 px-3 py-1.5 font-bold hover:text-slate-700 dark:hover:text-slate-300 uppercase tracking-widest transition">Cancel</button>
                        <button :disabled="enterForm.processing" class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-1.5 rounded text-[10px] uppercase tracking-widest font-black shadow-sm transition">
                            Confirm & Enter
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- ========================================== -->
        <!-- STATUS TOGGLE MODAL                        -->
        <!-- ========================================== -->
        <Modal :show="isBulkStatusModalOpen" :closeable="false" @close="isBulkStatusModalOpen = false" maxWidth="sm">
            <div class="p-5 bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-slate-200 dark:border-slate-700">
                <h2 class="text-sm font-black uppercase tracking-tight flex items-center gap-2 mb-2" :class="bulkStatusForm.action === 'publish' ? 'text-emerald-600' : 'text-slate-700 dark:text-slate-300'">
                    <ShieldAlert v-if="bulkStatusForm.action === 'publish'" class="w-5 h-5" />
                    <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Confirm {{ bulkStatusForm.action === 'publish' ? 'Publish' : 'Draft' }}
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">
                    You are changing the status of <strong>{{ bulkStatusForm.course_ids.length }} course(s)</strong>. 
                    <span v-if="bulkStatusForm.action === 'publish'">This will make them visible and accessible to students.</span>
                    <span v-else>This will hide them from students and move them to draft mode.</span>
                </p>
                <form @submit.prevent="submitBulkStatus" class="space-y-4">
                    <div>
                        <InputLabel value="Admin Password *" class="text-[9px] font-bold uppercase text-slate-500 mb-1" />
                        <input v-model="bulkStatusForm.password" type="password" :class="inputClass" placeholder="Enter your password" required />
                        <InputError :message="bulkStatusForm.errors.password" class="mt-1 text-[9px]" />
                    </div>
                    <div class="mt-5 pt-3 border-t border-slate-100 dark:border-slate-800 flex justify-end gap-2">
                        <button type="button" @click="isBulkStatusModalOpen = false" class="text-[10px] text-slate-500 px-3 py-1.5 font-bold hover:text-slate-700 dark:hover:text-slate-300 uppercase tracking-widest transition">Cancel</button>
                        <button :disabled="bulkStatusForm.processing" class="px-4 py-1.5 rounded text-[10px] uppercase tracking-widest font-black shadow-sm transition text-white" :class="bulkStatusForm.action === 'publish' ? 'bg-emerald-600 hover:bg-emerald-500' : 'bg-slate-600 hover:bg-slate-500'">
                            Confirm
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="isBulkDeleteModalOpen" :closeable="false" @close="isBulkDeleteModalOpen = false" maxWidth="sm">
            <div class="p-5 bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-slate-200 dark:border-slate-700">
                <h2 class="text-sm font-black uppercase tracking-tight text-red-600 flex items-center gap-2 mb-2">
                    <Trash2 class="w-5 h-5 text-red-500" />
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
                        <button type="button" @click="isBulkDeleteModalOpen = false" class="text-[10px] text-slate-500 px-3 py-1.5 font-bold hover:text-slate-700 dark:hover:text-slate-300 uppercase tracking-widest transition">Cancel</button>
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