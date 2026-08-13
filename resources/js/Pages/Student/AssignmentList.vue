<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue'; 
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import { Search, Filter, FileText, Clock, CheckCircle2, Eye, Download } from 'lucide-vue-next';

const props = defineProps({ courses: Array });

// Read Hidden Courses storage key
const page = usePage();
const userId = page.props.auth.user.id;
const storageKey = `lms_hidden_courses_${userId}`;
const hiddenCourses = ref(JSON.parse(localStorage.getItem(storageKey)) || []);

const visibleCourses = computed(() => {
    return props.courses.filter(c => !hiddenCourses.value.includes(c.id));
});

// ==========================================
// CRITICAL FIX: Helper functions moved to TOP
// ==========================================
const isCompleted = (a) => a.submissions && a.submissions.length > 0;

const countAssignments = (c, type) => {
    if (!c.assignments) return 0;
    const now = new Date();
    return c.assignments.filter(a => {
        const done = isCompleted(a); 
        const past = a.due_date && new Date(a.due_date) < now;
        return type === 'completed' ? done : type === 'upcoming' ? !done && !past : false;
    }).length;
};
// ==========================================

const courseSearchQuery = ref('');
const courseSortOrder = ref('tasks');

const processedCourses = computed(() => {
    let filtered = visibleCourses.value;
    
    if (courseSearchQuery.value.trim() !== '') {
        const q = courseSearchQuery.value.toLowerCase();
        filtered = filtered.filter(c => c.title.toLowerCase().includes(q));
    }

    let sorted = [...filtered].sort((a, b) => {
        if (courseSortOrder.value === 'tasks') {
            return countAssignments(b, 'upcoming') - countAssignments(a, 'upcoming');
        } else if (courseSortOrder.value === 'newest') {
            return new Date(b.created_at || 0).getTime() - new Date(a.created_at || 0).getTime();
        } else if (courseSortOrder.value === 'oldest') {
            return new Date(a.created_at || 0).getTime() - new Date(b.created_at || 0).getTime();
        } else if (courseSortOrder.value === 'a_z') {
            return a.title.localeCompare(b.title);
        } else if (courseSortOrder.value === 'z_a') {
            return b.title.localeCompare(a.title);
        }
        return 0;
    });

    return sorted;
});

const selectedCourseId = ref(processedCourses.value.length > 0 ? processedCourses.value[0].id : null);

watch(processedCourses, (newCourses) => {
    if (newCourses.length > 0 && !newCourses.find(c => c.id === selectedCourseId.value)) {
        selectedCourseId.value = newCourses[0].id;
    } else if (newCourses.length === 0) {
        selectedCourseId.value = null;
    }
});

const activeTab = ref('upcoming'); 
const taskSearchQuery = ref('');
const imageErrors = ref({});
const showDetailsModal = ref(false);
const selectedAssignment = ref(null);
const showMaterialPreview = ref(false);
const selectedMaterialPath = ref(null);

const formSubmission = useForm({ 
    files: [],
    text_content: '' 
});

const selectCourse = (id) => { selectedCourseId.value = id; };
const handleImageError = (id) => { imageErrors.value[id] = true; };
const selectedCourse = computed(() => visibleCourses.value.find(c => c.id === selectedCourseId.value));

const pendingTasksCount = computed(() => {
    if (!selectedCourse.value || !selectedCourse.value.assignments) return 0;
    const now = new Date();
    return selectedCourse.value.assignments.filter(a => {
        const done = isCompleted(a); 
        const past = a.due_date && new Date(a.due_date) < now;
        return !done && (!past || !a.due_date);
    }).length;
});

const pastDueTasksCount = computed(() => {
    if (!selectedCourse.value || !selectedCourse.value.assignments) return 0;
    const now = new Date();
    return selectedCourse.value.assignments.filter(a => {
        const done = isCompleted(a); 
        const past = a.due_date && new Date(a.due_date) < now;
        return !done && past;
    }).length;
});

const filteredAssignments = computed(() => {
    if (!selectedCourse.value) return [];
         
    let filtered = selectedCourse.value.assignments.filter(a => {
        const done = isCompleted(a); 
        const past = a.due_date && new Date(a.due_date) < new Date();
        
        let tabMatch = false;
        if (activeTab.value === 'completed') tabMatch = done;
        else if (activeTab.value === 'past') tabMatch = !done && past;
        else tabMatch = !done && !past;

        if (!tabMatch) return false;

        if (taskSearchQuery.value.trim() !== '') {
            const q = taskSearchQuery.value.toLowerCase();
            return a.title.toLowerCase().includes(q) || 
                   (a.type && a.type.replace('_', ' ').toLowerCase().includes(q));
        }

        return true;
    });

    filtered.sort((a, b) => {
        return new Date(b.created_at || 0).getTime() - new Date(a.created_at || 0).getTime();
    });

    return filtered;
});

const isClosed = (assignment) => {
    if (!assignment || !assignment.closing_date) return false;
    return new Date().getTime() > new Date(assignment.closing_date).getTime();
};

const linkify = (t) => t ? t.replace(/(https?:\/\/[^\s]+)/g, '<a href="$1" target="_blank" class="text-blue-600 hover:underline">$1</a>') : '';

const getPaths = (paths) => { 
    if (Array.isArray(paths)) return paths; 
    try { return JSON.parse(paths) || []; } catch (e) { return []; } 
};

const MAX_TOTAL_SIZE = 15 * 1024 * 1024;
const totalFileSize = computed(() => formSubmission.files.reduce((total, file) => total + file.size, 0));
const isOverSizeLimit = computed(() => totalFileSize.value > MAX_TOTAL_SIZE);

const formatSize = (bytes) => {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
};

const handleFileSelect = (e) => {
    const newFiles = Array.from(e.target.files);
    formSubmission.files = [...formSubmission.files, ...newFiles];
    e.target.value = ''; 
};

const removeFile = (index) => { formSubmission.files.splice(index, 1); };

const openDetails = (a) => { 
    selectedAssignment.value = a; 
    formSubmission.reset(); 
    formSubmission.files = []; 
    showDetailsModal.value = true; 
};

const openMaterialPreview = (path) => {
    selectedMaterialPath.value = path;
    showMaterialPreview.value = true;
};

const submitWork = () => {
    if (isOverSizeLimit.value) return;
    formSubmission.post(route('assignments.submit', selectedAssignment.value.id), { 
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => { 
            showDetailsModal.value = false; 
            formSubmission.reset(); 
            formSubmission.files = []; 
        }
    });
};

const undoTurnIn = () => { 
    if (confirm('Undo submission? You can resubmit as long as the task is not locked.')) {
        router.post(route('assignments.unsubmit', selectedAssignment.value.id), {}, { onSuccess: () => showDetailsModal.value = false, preserveScroll: true }); 
    }
};

const formatDate = (dateString) => {
    if (!dateString) return 'None';
    return new Date(dateString).toLocaleString([], {month:'short', day:'numeric', hour:'2-digit', minute:'2-digit'});
};
</script>

<template>
    <Head title="My Tasks" />
    <AuthenticatedLayout>
        <div class="h-full md:h-[calc(100vh-80px)] flex flex-col max-w-screen-2xl mx-auto -mt-2">
            
            <div class="mb-2 md:mb-3 shrink-0 px-2 sm:px-0 flex justify-between items-center border-b border-slate-100 dark:border-slate-800 pb-2 md:pb-3">
                <div>
                    <h1 class="text-lg sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-1.5 sm:gap-2">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6 text-blue-600 dark:text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                        Tasks
                    </h1>
                    <p class="hidden sm:block text-[9px] font-bold text-slate-500 uppercase tracking-widest mt-0.5 ml-8">Manage your deadlines</p>
                </div>
            </div>

            <!-- MOBILE COURSE SELECTOR & FILTERS -->
            <div class="md:hidden w-full px-2 mb-3 z-20 flex flex-col gap-2">
                <div class="flex gap-2">
                    <div class="relative flex-1">
                        <div class="absolute inset-y-0 left-0 pl-2 flex items-center pointer-events-none">
                            <Search class="h-3.5 w-3.5 text-slate-400" />
                        </div>
                        <input v-model="courseSearchQuery" type="text" placeholder="Search class..." class="w-full h-8 pl-7 rounded-lg bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-500 text-xs shadow-sm transition-colors placeholder-slate-400" />
                    </div>
                    <select v-model="courseSortOrder" class="w-1/3 min-w-[100px] h-8 rounded-lg bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-500 text-[9px] font-bold uppercase tracking-widest text-slate-600 dark:text-slate-300 shadow-sm transition-colors cursor-pointer py-0 pl-2 pr-6">
                        <option value="tasks">Most Tasks</option>
                        <option value="newest">Newest</option>
                        <option value="oldest">Oldest</option>
                        <option value="a_z">A-Z</option>
                        <option value="z_a">Z-A</option>
                    </select>
                </div>

                <select @change="(e) => selectCourse(Number(e.target.value))" class="w-full text-xs font-black uppercase tracking-widest bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 text-blue-800 dark:text-blue-300 rounded-lg p-2.5 focus:ring-2 focus:ring-blue-500 shadow-sm cursor-pointer truncate transition-colors">
                    <option v-if="processedCourses.length === 0" disabled selected>No classes match search.</option>
                    <option v-for="c in processedCourses" :key="c.id" :value="c.id" :selected="c.id === selectedCourseId">
                        {{ c.title }} ({{ countAssignments(c, 'upcoming') }} To Do)
                    </option>
                </select>
            </div>

            <div class="flex-1 flex flex-col md:flex-row gap-0 md:gap-4 overflow-hidden bg-slate-50/30 md:bg-transparent rounded-none md:rounded-lg relative">
                
                <!-- DESKTOP SIDEBAR COURSE LIST -->
                <aside class="hidden md:flex w-56 lg:w-64 bg-slate-50/50 md:bg-white dark:bg-slate-900 md:dark:bg-slate-800 flex-col shrink-0 md:border border-slate-200 dark:border-slate-700 md:rounded-lg overflow-hidden md:h-full shadow-sm">
                    
                    <div class="p-3 border-b border-slate-100 dark:border-slate-700/50 flex flex-col gap-2.5 shrink-0 bg-white dark:bg-slate-800">
                        <h3 class="text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest">Active Classes</h3>
                        
                        <div class="relative w-full">
                            <div class="absolute inset-y-0 left-0 pl-2 flex items-center pointer-events-none">
                                <Search class="h-3 w-3 text-slate-400" />
                            </div>
                            <input v-model="courseSearchQuery" type="text" placeholder="Search class..." class="w-full h-8 pl-7 rounded-md bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 text-xs shadow-sm transition-colors placeholder-slate-400" />
                        </div>
                        
                        <div class="relative w-full">
                            <div class="absolute inset-y-0 left-0 pl-2 flex items-center pointer-events-none">
                                <Filter class="h-3 w-3 text-slate-400" />
                            </div>
                            <select v-model="courseSortOrder" class="w-full h-8 pl-7 rounded-md bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 text-[9px] font-bold uppercase tracking-widest text-slate-600 dark:text-slate-300 shadow-sm transition-colors cursor-pointer py-0 pr-6">
                                <option value="tasks">Most Tasks</option>
                                <option value="newest">Newest First</option>
                                <option value="oldest">Oldest First</option>
                                <option value="a_z">Name (A-Z)</option>
                                <option value="z_a">Name (Z-A)</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="flex-col overflow-y-auto w-full p-2 gap-1 custom-scrollbar">
                        <div v-if="processedCourses.length === 0" class="p-6 text-center text-[9px] font-black text-slate-400 uppercase tracking-widest">
                            No classes match your search.
                        </div>

                        <button v-for="c in processedCourses" :key="c.id" @click="selectCourse(c.id)"
                             class="w-full text-left transition-colors duration-150 flex items-center justify-between group border-l-4 px-2 py-2.5"
                            :class="selectedCourseId === c.id 
                                 ? 'bg-blue-50 dark:bg-blue-900/20 border-blue-600 shadow-sm' 
                                 : 'bg-transparent border-transparent hover:bg-slate-100 dark:hover:bg-slate-700/50'"
                        >
                            <div class="flex items-center gap-2.5 overflow-hidden w-full">
                                <div class="w-7 h-7 rounded border border-slate-200 dark:border-slate-700 bg-slate-100 dark:bg-slate-800 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0 overflow-hidden text-[10px] font-black">
                                    <img v-if="c.thumbnail && !imageErrors[c.id]" :src="c.thumbnail" @error="handleImageError(c.id)" class="w-full h-full object-cover" />
                                    <span v-else>IMG</span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <span class="block truncate text-xs" :class="selectedCourseId === c.id ? 'font-black text-blue-900 dark:text-blue-100' : 'font-bold text-slate-700 dark:text-slate-200'">{{ c.title }}</span>
                                    
                                    <div class="flex items-center gap-1.5 mt-0.5">
                                        <span v-if="countAssignments(c, 'upcoming') > 0" class="flex items-center gap-1 text-red-600 dark:text-red-400 text-[9px] font-black uppercase tracking-wider">
                                            <span class="w-1.5 h-1.5 rounded-full bg-red-600 animate-pulse shadow-[0_0_5px_rgba(220,38,38,0.8)]"></span>
                                            {{ countAssignments(c, 'upcoming') }} To Do
                                        </span>
                                        <span v-else class="text-[9px] font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                            All caught up
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </button>
                    </div>
                </aside>

                <main class="flex-1 bg-transparent md:bg-white dark:bg-slate-800 flex flex-col md:border border-slate-200 dark:border-slate-700 md:rounded-lg overflow-hidden h-full min-h-[400px] md:shadow-sm relative">
                    <div v-if="selectedCourse" class="flex flex-col h-full pt-0 md:pt-1">
                        
                        <div class="border-b border-slate-200 dark:border-slate-700 shrink-0 bg-white dark:bg-slate-800 flex flex-col gap-2 pt-2 px-2 sm:px-4">
                            
                            <div class="flex flex-col sm:flex-row gap-2 w-full mt-1">
                                <div class="relative flex-1">
                                    <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none">
                                        <Search class="h-3.5 w-3.5 text-slate-400" />
                                    </div>
                                    <input v-model="taskSearchQuery" type="text" placeholder="Search tasks by name or type..." class="w-full h-8 pl-8 rounded-md bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-blue-500 focus:border-transparent text-xs shadow-sm transition-colors" />
                                </div>
                            </div>

                            <div class="flex overflow-x-auto scrollbar-hide w-full pb-1 sm:pb-0 mt-1">
                                <button @click="activeTab = 'upcoming'"
                                    class="px-2 sm:px-3 py-2 sm:py-3 text-[9px] sm:text-[10px] font-black uppercase tracking-widest mr-1 sm:mr-6 transition-all border-b-2 whitespace-nowrap flex items-center gap-1.5 flex-1 sm:flex-none justify-center relative"
                                    :class="activeTab === 'upcoming' ? 'border-blue-600 text-blue-600 dark:text-blue-400' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-300'">
                                    Upcoming
                                    <span v-if="pendingTasksCount > 0" class="absolute top-1 sm:top-2 right-0 sm:right-1 w-2 h-2 bg-red-500 border-2 border-white dark:border-slate-800 rounded-full animate-pulse shadow-sm"></span>
                                </button>
                                <button @click="activeTab = 'past'"
                                    class="px-2 sm:px-3 py-2 sm:py-3 text-[9px] sm:text-[10px] font-black uppercase tracking-widest mr-1 sm:mr-6 transition-all border-b-2 whitespace-nowrap flex items-center gap-1.5 flex-1 sm:flex-none justify-center relative"
                                    :class="activeTab === 'past' ? 'border-red-600 text-red-600 dark:text-red-400' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-300'">
                                    Past Due
                                    <span v-if="pastDueTasksCount > 0" class="absolute top-1 sm:top-2 right-0 sm:right-1 w-2 h-2 bg-red-500 border-2 border-white dark:border-slate-800 rounded-full animate-pulse shadow-sm"></span>
                                </button>
                                <button @click="activeTab = 'completed'"
                                    class="px-2 sm:px-3 py-2 sm:py-3 text-[9px] sm:text-[10px] font-black uppercase tracking-widest sm:mr-6 transition-all border-b-2 whitespace-nowrap flex items-center gap-1 flex-1 sm:flex-none justify-center relative"
                                    :class="activeTab === 'completed' ? 'border-emerald-600 text-emerald-600 dark:text-emerald-400' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-300'">
                                    Completed
                                </button>
                            </div>
                        </div>

                        <div class="flex-1 overflow-y-auto p-1.5 sm:p-3 custom-scrollbar pb-24">
                            <div v-if="filteredAssignments.length > 0" class="flex flex-col gap-1.5 sm:gap-2">
                                
                                <div v-for="a in filteredAssignments" :key="a.id" @click="openDetails(a)"
                                     class="group flex flex-col sm:flex-row sm:items-center gap-1.5 sm:gap-2.5 p-3 sm:p-4 bg-white dark:bg-slate-800 border-l-2 sm:border-l-4 border border-slate-200 dark:border-slate-700 rounded-md sm:rounded-xl hover:shadow-md cursor-pointer transition-all duration-200 shadow-sm"
                                     :class="activeTab === 'upcoming' ? 'border-l-blue-500 hover:border-blue-400' : activeTab === 'completed' ? 'border-l-emerald-500 hover:border-emerald-400' : 'border-l-red-500 hover:border-red-400'">
                                     
                                    <div class="hidden sm:flex shrink-0 w-8 h-8 rounded items-center justify-center transition-colors"
                                         :class="activeTab === 'upcoming' ? 'bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400 group-hover:bg-blue-600 group-hover:text-white' : activeTab === 'completed' ? 'bg-emerald-50 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400 group-hover:bg-emerald-600 group-hover:text-white' : 'bg-red-50 text-red-600 dark:bg-red-900/30 dark:text-red-400 group-hover:bg-red-600 group-hover:text-white'">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                    </div>

                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="flex items-center gap-1.5 min-w-0">
                                                <span class="text-[7px] sm:text-[8px] font-black uppercase tracking-widest px-1 sm:px-1.5 py-0.5 rounded border border-slate-200 dark:border-slate-700 text-slate-500 shrink-0 bg-slate-50 dark:bg-slate-900">
                                                    {{ a.type ? a.type.replace('_', ' ') : 'Task' }}
                                                </span>
                                                <h4 class="text-[10px] sm:text-sm font-black text-slate-900 dark:text-white truncate transition-colors"
                                                    :class="activeTab === 'upcoming' ? 'group-hover:text-blue-600' : activeTab === 'completed' ? 'group-hover:text-emerald-600' : 'group-hover:text-red-600'">
                                                    {{ a.title }}
                                                </h4>
                                            </div>
                                            <span class="text-[8px] sm:text-[10px] font-black whitespace-nowrap bg-slate-100 dark:bg-slate-900/50 px-1.5 py-0.5 rounded shrink-0"
                                                  :class="activeTab === 'upcoming' ? 'text-blue-600 dark:text-blue-400' : activeTab === 'completed' ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
                                                {{ a.points }} pts
                                            </span>
                                        </div>
                                        <p class="hidden sm:block text-[10px] sm:text-xs text-slate-500 dark:text-slate-400 truncate font-medium leading-snug mt-0.5">
                                            {{ a.description || 'No instructions provided.' }}
                                        </p>
                                    </div>

                                    <div class="flex items-center justify-between sm:justify-end gap-2 sm:gap-4 w-full sm:w-auto shrink-0 mt-1 sm:mt-0 pt-2 sm:pt-0 border-t border-dashed border-slate-100 sm:border-none dark:border-slate-700/50">
                                        <div class="flex items-center gap-1 text-[8px] sm:text-[9px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">
                                            <svg class="w-2.5 h-2.5 sm:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            <span :class="activeTab === 'past' ? 'text-red-600 dark:text-red-400' : 'text-slate-500 dark:text-slate-400'">
                                                {{ activeTab === 'completed' ? 'Closed' : 'Due' }}: {{ a.closing_date && activeTab === 'completed' ? new Date(a.closing_date).toLocaleDateString(undefined, {month: 'short', day: 'numeric'}) : a.due_date ? new Date(a.due_date).toLocaleDateString(undefined, {month: 'short', day: 'numeric'}) : 'No Date' }}
                                            </span>
                                        </div>
                                        
                                        <div v-if="isClosed(a) && activeTab !== 'completed'" class="text-[7px] sm:text-[9px] font-black text-red-600 bg-red-50 dark:bg-red-900/20 px-1 py-0.5 rounded uppercase tracking-widest border border-red-100 dark:border-red-800/50">Locked</div>
                                        <div v-else-if="activeTab === 'completed'" class="text-[7px] sm:text-[9px] font-black text-emerald-600 bg-emerald-50 dark:bg-emerald-900/20 px-1 py-0.5 rounded uppercase tracking-widest border border-emerald-100 dark:border-emerald-800/50">Done</div>
                                        
                                        <div v-if="activeTab === 'completed' && a.submissions[0]?.grade" class="hidden sm:flex text-[10px] font-black text-emerald-600 bg-emerald-50 dark:bg-emerald-900/30 px-2 py-1 rounded border border-emerald-200 dark:border-emerald-800/50">
                                            {{ a.submissions[0].grade }} / {{ a.points }}
                                        </div>
                                        <svg class="w-3 h-3 text-slate-300 transition-transform group-hover:translate-x-0.5 sm:hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                        <svg class="w-4 h-4 text-slate-300 transition-transform group-hover:translate-x-0.5 hidden sm:block" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                    </div>
                                </div>
                            </div>
                            
                            <div v-else class="flex flex-col items-center justify-center h-full py-12 px-4 text-slate-400 border border-dashed border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 mt-2 sm:mt-0 shadow-sm">
                                <CheckCircle2 class="w-5 h-5 text-slate-300 dark:text-slate-600 mb-2" v-if="activeTab !== 'needs_grading'" />
                                <div class="w-10 h-10 bg-emerald-50 dark:bg-emerald-900/20 text-emerald-500 rounded-full flex items-center justify-center mb-2 border border-emerald-100 dark:border-emerald-900/30" v-else>
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                </div>
                                <h3 class="text-[9px] sm:text-[11px] font-black uppercase tracking-widest text-slate-900 dark:text-white mb-0.5">All clear</h3>
                                <p class="text-[8px] sm:text-[9px] font-bold text-center">No {{ activeTab }} tasks found.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div v-else class="flex flex-col items-center justify-center h-full p-6 text-slate-500 pt-1 bg-white md:bg-transparent rounded-lg m-2 md:m-0 border md:border-none border-slate-200">
                        <FileText class="w-8 h-8 mb-2 text-slate-300 dark:text-slate-600" />
                        <p class="text-[9px] font-black uppercase tracking-widest text-center max-w-[200px] leading-relaxed">Select a class to view assignments.</p>
                    </div>
                </main>
            </div>
        </div>

        <!-- MATERIAL PREVIEW MODAL -->
        <Modal :show="showMaterialPreview" @close="showMaterialPreview = false" maxWidth="4xl">
            <div class="bg-white dark:bg-slate-900 rounded-2xl overflow-hidden shadow-2xl flex flex-col h-[85vh]">
                <div class="p-4 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center bg-slate-50 dark:bg-slate-900 shrink-0">
                    <h3 class="font-black text-sm text-slate-900 dark:text-white flex items-center gap-2 uppercase tracking-tight">
                        <div class="w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0">
                            <Eye class="w-4 h-4" />
                        </div>
                        Material Preview
                    </h3>
                    <button @click="showMaterialPreview = false" class="w-8 h-8 flex items-center justify-center rounded-full bg-slate-200 dark:bg-slate-700 text-slate-500 dark:text-slate-300 hover:bg-slate-300 transition shrink-0">&times;</button>
                </div>
                
                <div class="flex-1 p-4 bg-slate-100 dark:bg-slate-950/50 flex flex-col items-center justify-center relative overflow-hidden">
                    <iframe v-if="selectedMaterialPath?.toLowerCase().endsWith('.pdf')" :src="`/storage/${selectedMaterialPath}`" class="w-full h-full border-none rounded-lg shadow-sm bg-white dark:bg-slate-900"></iframe>
                    <img v-else-if="selectedMaterialPath?.match(/\.(jpeg|jpg|png|gif)$/i)" :src="`/storage/${selectedMaterialPath}`" class="max-w-full max-h-full object-contain rounded-lg shadow-sm" />
                    
                    <div v-else class="text-center p-8 bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 max-w-sm w-full">
                        <FileText class="w-16 h-16 text-slate-300 dark:text-slate-600 mb-4 mx-auto" />
                        <p class="text-slate-500 font-black mb-1 text-[11px] uppercase tracking-widest">Preview unavailable</p>
                        <p class="text-slate-400 text-[10px] font-bold mb-6">This file type cannot be viewed directly.</p>
                        <div class="flex flex-col gap-2">
                            <a :href="`/storage/${selectedMaterialPath}`" download class="inline-flex items-center justify-center gap-1.5 bg-blue-600 hover:bg-blue-500 text-white transition text-[10px] font-black uppercase tracking-widest px-4 py-3 rounded-lg shadow-sm w-full">
                                <Download class="w-4 h-4" /> Download File
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </Modal>

        <!-- SUBMIT WORK MODAL -->
        <Modal :show="showDetailsModal" @close="showDetailsModal = false" maxWidth="2xl">
            <div class="bg-white dark:bg-slate-800 rounded-2xl overflow-hidden flex flex-col h-[85vh]">
                
                <div v-if="$page.props.flash?.error" class="bg-red-50 border-b border-red-200 text-red-600 p-4 text-xs font-bold shadow-sm">
                    <div class="flex items-center gap-2 mb-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        <span class="font-black uppercase tracking-widest">Upload Failed</span>
                    </div>
                    {{ $page.props.flash.error }}
                </div>

                <div class="p-5 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center bg-slate-50/50 dark:bg-slate-900/50">
                    <div class="flex gap-4 min-w-0">
                         <div class="w-10 h-10 rounded-xl bg-blue-600 text-white flex items-center justify-center shrink-0">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                        </div>
                         <div class="min-w-0">
                            <h2 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-tight truncate">{{ selectedAssignment?.title }}</h2>
                            <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest truncate">Submit Your Evidence</p>
                         </div>
                    </div>
                    <button @click="showDetailsModal = false" class="text-slate-400 hover:text-slate-600 text-2xl font-light shrink-0">&times;</button>
                </div>

                <div class="p-6 overflow-y-auto flex-1 space-y-6 custom-scrollbar">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="p-3 bg-slate-50 dark:bg-slate-900/40 rounded-xl border border-slate-100 dark:border-slate-700">
                            <p class="text-[9px] font-black text-slate-400 uppercase mb-1">Total Points</p>
                            <div class="flex items-center gap-2 text-blue-600 font-black truncate">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path></svg> 
                                {{ selectedAssignment?.points }} Pts
                            </div>
                        </div>
                        <div class="p-3 bg-slate-50 dark:bg-slate-900/40 rounded-xl border border-slate-100 dark:border-slate-700">
                            <p class="text-[9px] font-black text-slate-400 uppercase mb-1">Deadline</p>
                            <div class="flex items-center gap-2 text-red-500 font-black truncate">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> 
                                {{ selectedAssignment?.due_date ? new Date(selectedAssignment.due_date).toLocaleDateString() : 'No Deadline' }}
                            </div>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Teacher's Instructions</h3>
                        <div class="text-xs text-slate-700 dark:text-slate-300 bg-slate-50 dark:bg-slate-900/20 p-4 rounded-xl border border-slate-100 dark:border-slate-800 leading-relaxed" v-html="linkify(selectedAssignment?.description)"></div>
                    </div>

                    <div v-if="selectedAssignment?.attachment_paths && getPaths(selectedAssignment.attachment_paths).length" class="space-y-2 mt-4">
                        <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Attached Materials</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <div v-for="(path, index) in getPaths(selectedAssignment.attachment_paths)" :key="index" class="flex justify-between items-center p-3 bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm">
                                <div class="flex items-center gap-2 overflow-hidden">
                                    <FileText class="w-4 h-4 text-blue-500 shrink-0" />
                                    <span class="text-[10px] font-bold text-slate-700 dark:text-slate-300 truncate">Material {{ index + 1 }}</span>
                                </div>
                                <div class="flex gap-3 shrink-0 ml-2">
                                    <button type="button" @click.prevent="openMaterialPreview(path)" class="text-blue-600 hover:text-blue-500 text-[10px] font-black uppercase tracking-widest transition">View</button>
                                    <a :href="`/storage/${path}`" download class="text-emerald-600 hover:text-emerald-500 text-[10px] font-black uppercase tracking-widest transition">Save</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <hr class="border-slate-100 dark:border-slate-700" />

                    <div v-if="selectedAssignment?.submissions[0]" class="space-y-4">
                        <div class="p-5 bg-blue-50 dark:bg-blue-900/20 rounded-2xl border border-blue-100 dark:border-blue-800 text-center">
                            <span class="text-blue-600 dark:text-blue-400 text-xs font-black uppercase tracking-widest block mb-4">You turned this in on {{ new Date(selectedAssignment.submissions[0].submitted_at).toLocaleDateString() }}</span>
                            
                            <div v-if="selectedAssignment.submissions[0].text_content" class="text-left bg-white dark:bg-slate-800 p-4 rounded-xl border border-blue-100 dark:border-blue-700 mb-4 shadow-sm">
                                <p class="text-[9px] font-black text-slate-400 uppercase mb-2">Text Content:</p>
                                <p class="text-xs text-slate-700 dark:text-slate-300 whitespace-pre-wrap leading-relaxed">{{ selectedAssignment.submissions[0].text_content }}</p>
                            </div>

                            <div v-if="getPaths(selectedAssignment.submissions[0].file_paths).length" class="text-left bg-white dark:bg-slate-800 p-4 rounded-xl border border-blue-100 dark:border-blue-700 shadow-sm">
                                <p class="text-[9px] font-black text-slate-400 uppercase mb-2">Attached Files:</p>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    <div v-for="(path, index) in getPaths(selectedAssignment.submissions[0].file_paths)" :key="index" class="flex justify-between items-center p-2 bg-slate-50 dark:bg-slate-900 rounded-lg border border-slate-100 dark:border-slate-700">
                                        <span class="text-[10px] font-bold truncate w-32">Attachment {{ index + 1 }}</span>
                                        <div class="flex gap-2">
                                            <button type="button" @click.prevent="openMaterialPreview(path)" class="text-blue-600 hover:text-blue-500 text-[10px] font-black uppercase tracking-widest transition">View</button>
                                            <a :href="`/storage/${path}`" download class="text-emerald-600 hover:text-emerald-500 text-[10px] font-black uppercase tracking-widest transition">Save</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="selectedAssignment.submissions[0].grade" class="p-5 bg-emerald-50 dark:bg-emerald-900/20 rounded-2xl border border-emerald-100 dark:border-emerald-800 shadow-sm">
                            <div class="flex items-center gap-2 mb-2 text-emerald-700 font-black uppercase text-xs tracking-widest">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path></svg> 
                                Graded: {{ selectedAssignment.submissions[0].grade }}/{{ selectedAssignment.points }}
                            </div>
                            <div class="text-xs text-emerald-800 dark:text-emerald-300 italic leading-relaxed">"{{ selectedAssignment.submissions[0].feedback }}"</div>
                        </div>

                        <div v-else class="flex justify-end">
                            <button @click="undoTurnIn" class="flex items-center gap-2 px-5 py-2.5 bg-white dark:bg-slate-800 border border-red-200 text-red-600 rounded-xl font-black text-[10px] uppercase tracking-widest hover:bg-red-50 transition shadow-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path></svg> Undo Turn In
                            </button>
                        </div>
                    </div>

                    <form v-else @submit.prevent="submitWork" class="space-y-6 animate-in slide-in-from-bottom-4 duration-500">
                        <div class="space-y-2">
                            <label class="text-[10px] font-black uppercase text-slate-400 tracking-widest">Write Answer or Links (Optional)</label>
                            <textarea v-model="formSubmission.text_content" class="w-full bg-slate-50 dark:bg-slate-900 border-slate-200 dark:border-slate-700 rounded-xl p-4 text-sm h-32 focus:ring-2 focus:ring-blue-500 resize-none shadow-inner" placeholder="Enter your text response or URLs here..."></textarea>
                            <InputError :message="formSubmission.errors.text_content" class="mt-1" />
                        </div>
                        
                        <div class="space-y-2">
                            <label class="text-[10px] font-black uppercase text-slate-400 tracking-widest">Attach Files</label>
                            <div class="bg-slate-50 dark:bg-slate-900/50 p-8 rounded-2xl border-2 border-dashed border-slate-200 dark:border-slate-700 text-center relative hover:border-blue-400 hover:bg-blue-50/50 transition-all group">
                                <input type="file" multiple @change="handleFileSelect" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" />
                                <div class="flex flex-col items-center gap-2 text-slate-400 group-hover:text-blue-500">
                                    <svg class="w-8 h-8 opacity-40 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg>
                                    <p class="text-xs font-bold uppercase tracking-wider">Drag files or Click to Upload</p>
                                </div>
                            </div>
                            <InputError :message="formSubmission.errors.files" class="mt-2 text-center" />
                        </div>
                        
                        <div v-if="formSubmission.files.length" class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <div v-for="(f,i) in formSubmission.files" :key="i" class="p-3 bg-white dark:bg-slate-700 rounded-xl border border-slate-100 dark:border-slate-600 flex justify-between items-center shadow-sm">
                                <div class="flex items-center gap-2 overflow-hidden pr-2">
                                    <button type="button" @click="removeFile(i)" class="text-red-500 hover:text-red-700 transition shrink-0">&times;</button>
                                    <span class="text-[10px] font-bold truncate">{{ f.name }}</span>
                                </div>
                                <span class="text-[9px] font-black text-slate-400 shrink-0">{{ (f.size/1024).toFixed(0) }} KB</span>
                            </div>
                        </div>
                        
                        <div class="flex justify-between items-center px-1 mt-auto shrink-0">
                            <span class="text-[9px] font-black uppercase tracking-widest" :class="isOverSizeLimit ? 'text-red-600' : 'text-slate-400'">
                                Total Size: {{ formatSize(totalFileSize) }} / 15 MB
                            </span>
                        </div>
                        
                        <div class="pt-2 border-t border-slate-50 dark:border-slate-700">
                            <button :disabled="formSubmission.processing || isOverSizeLimit || (formSubmission.files.length === 0 && !formSubmission.text_content.trim())" 
                                 class="w-full bg-blue-600 text-white text-[10px] font-black uppercase tracking-widest py-3 rounded-lg shadow-sm hover:bg-blue-500 transition-all active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed">
                                {{ isOverSizeLimit ? 'Size Limit Exceeded' : 'Turn In Task' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<style scoped>
.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(148, 163, 184, 0.2); border-radius: 10px; }
</style>