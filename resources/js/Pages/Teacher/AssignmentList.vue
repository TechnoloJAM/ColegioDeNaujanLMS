<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import { ClipboardList, Plus, ChevronRight, Clock, CheckCircle2, AlertTriangle, Search, Filter, Globe } from 'lucide-vue-next';

const props = defineProps({
    courses: Array
});

const getFileUrl = (path) => {
    if (!path) return '';
    const cleanPath = path.replace(/^\/storage\//, '');
    return `${usePage().props.env.AWS_URL}/${cleanPath}`;
};

const page = usePage();
const userId = page.props.auth.user.id;
const storageKey = `lms_hidden_courses_${userId}`;
const hiddenCourses = ref(JSON.parse(localStorage.getItem(storageKey)) || []);

// Courses available for selecting/displaying
const visibleCourses = computed(() => {
    return props.courses.filter(c => !hiddenCourses.value.includes(c.id));
});

const courseSearchQuery = ref('');
const courseSortOrder = ref('needs_grading');

const processedCourses = computed(() => {
    let filtered = visibleCourses.value;
    
    if (courseSearchQuery.value.trim() !== '') {
        const q = courseSearchQuery.value.toLowerCase();
        filtered = filtered.filter(c => 
            c.title.toLowerCase().includes(q) || 
            (c.enrollment_code && c.enrollment_code.toLowerCase().includes(q))
        );
    }

    let sorted = [...filtered].sort((a, b) => {
        if (courseSortOrder.value === 'needs_grading') {
            return (b.ungraded_count || 0) - (a.ungraded_count || 0);
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

const selectedCourseId = ref('all');

watch(processedCourses, (newCourses) => {
    if (selectedCourseId.value !== 'all' && !newCourses.find(c => c.id === selectedCourseId.value)) {
        selectedCourseId.value = 'all';
    }
});

const activeTab = ref('needs_grading'); 
const taskSearchQuery = ref('');
const imageErrors = ref({});

const selectCourse = (id) => { selectedCourseId.value = id; };
const handleImageError = (id) => { imageErrors.value[id] = true; };

const cleanDescription = (text) => text ? text.replace(/\[RESTRICT_LATE_STUDENTS\]/gi, '').trim() : '';
const isRestricted = (text) => text ? text.includes('[RESTRICT_LATE_STUDENTS]') : false;

const selectedCourse = computed(() => visibleCourses.value.find(c => c.id === selectedCourseId.value));
const isMainAreaVisible = computed(() => selectedCourseId.value === 'all' || selectedCourse.value);

// ==========================================
// CREATE TASK MODAL LOGIC
// ==========================================
const showCreateModal = ref(false);
const courseForNewTask = ref('');

const openCreateModal = () => {
    // Auto-select the course if they are currently inside a specific class
    courseForNewTask.value = selectedCourseId.value !== 'all' ? selectedCourseId.value : '';
    showCreateModal.value = true;
};

const proceedToCreateTask = () => {
    if (courseForNewTask.value) {
        router.visit(route('teacher.assignments.create', { course: courseForNewTask.value, source: 'global' }));
    }
};
// ==========================================

const allAssignments = computed(() => {
    let coursesToMap = selectedCourseId.value === 'all' 
        ? processedCourses.value 
        : processedCourses.value.filter(c => c.id === selectedCourseId.value);
        
    return coursesToMap.flatMap(c => 
        (c.assignments || []).map(a => ({
            ...a,
            course_title: c.title,
            enrollments_count: c.enrollments_count
        }))
    );
});

const countUpcoming = (assignments) => {
    if (!assignments) return 0;
    const now = new Date();
    return assignments.filter(a => {
        const dueDate = a.due_date ? new Date(a.due_date) : null;
        return !dueDate || dueDate >= now;
    }).length;
};

const totalNeedsGrading = computed(() => {
    return allAssignments.value.reduce((sum, a) => sum + (a.ungraded_count || 0), 0);
});

const isClosed = (assignment) => {
    if (!assignment || !assignment.closing_date) return false;
    return new Date().getTime() > new Date(assignment.closing_date).getTime();
};

const filteredAssignments = computed(() => {
    const now = new Date();
    
    let filtered = allAssignments.value.filter(assignment => {
        const dueDate = assignment.due_date ? new Date(assignment.due_date) : null;
        const isPastDue = dueDate && dueDate < now;
        const closed = isClosed(assignment); 
        const needsGrading = assignment.ungraded_count > 0;
        
        let tabMatch = false;
        if (activeTab.value === 'needs_grading') tabMatch = needsGrading;
        else if (activeTab.value === 'upcoming') tabMatch = !isPastDue && !closed && !needsGrading;
        else if (activeTab.value === 'past') tabMatch = isPastDue && !closed && !needsGrading;
        else if (activeTab.value === 'completed') tabMatch = closed && !needsGrading; 
        else tabMatch = true;

        if (!tabMatch) return false;

        if (taskSearchQuery.value.trim() !== '') {
            const q = taskSearchQuery.value.toLowerCase();
            return assignment.title.toLowerCase().includes(q) || 
                   (assignment.type && assignment.type.replace('_', ' ').toLowerCase().includes(q));
        }

        return true;
    });

    filtered.sort((a, b) => {
        const dateA = a.due_date ? new Date(a.due_date).getTime() : Infinity;
        const dateB = b.due_date ? new Date(b.due_date).getTime() : Infinity;
        
        if (activeTab.value === 'past' || activeTab.value === 'completed') {
            return dateB - dateA; 
        }
        return dateA - dateB; 
    });

    return filtered;
});

const getOrdinalNum = (n) => {
    const s = ["th", "st", "nd", "rd"];
    const v = n % 100;
    return n + (s[(v - 20) % 10] || s[v] || s[0]);
};

const formatGroupHeader = (dateStr) => {
    if (!dateStr) return { label: 'No Due Date', relative: '' };
    const d = new Date(dateStr);
    const month = d.toLocaleString('en-US', { month: 'short' });
    return {
        label: `${month} ${getOrdinalNum(d.getDate())}`,
        relative: getRelativeLabel(d)
    };
};

const getRelativeLabel = (targetDate) => {
    const now = new Date();
    now.setHours(0,0,0,0);
    const target = new Date(targetDate);
    target.setHours(0,0,0,0);
    
    const diffTime = target - now;
    const diffDays = Math.round(diffTime / (1000 * 60 * 60 * 24));
    
    if (diffDays === 0) return 'Due today';
    if (diffDays === 1) return 'Due tomorrow';
    if (diffDays === -1) return 'Due yesterday';
    
    if (diffDays > 1) {
        if (diffDays < 7) return `Due in ${diffDays} days`;
        if (diffDays < 30) return `Due in ${Math.floor(diffDays/7)} weeks`;
        return `Due in ${Math.floor(diffDays/30)} months`;
    } else {
        const absDays = Math.abs(diffDays);
        if (absDays < 7) return `Due ${absDays} days ago`;
        if (absDays < 30) return `Due ${Math.floor(absDays/7)} weeks ago`;
        return `Due ${Math.floor(absDays/30)} months ago`;
    }
};

const groupedAssignments = computed(() => {
    const groups = {};
    
    filteredAssignments.value.forEach(a => {
        let key = 'no-date';
        if (a.due_date) {
            const d = new Date(a.due_date);
            key = `${d.getFullYear()}-${String(d.getMonth()+1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
        }
        
        if (!groups[key]) {
            const headerInfo = a.due_date ? formatGroupHeader(a.due_date) : { label: 'No Due Date', relative: '' };
            groups[key] = {
                key: key,
                timestamp: a.due_date ? new Date(key).getTime() : Infinity,
                dateLabel: headerInfo.label,
                relativeLabel: headerInfo.relative,
                tasks: []
            };
        }
        groups[key].tasks.push(a);
    });

    return Object.values(groups).sort((a, b) => {
        if (a.key === 'no-date') return 1;
        if (b.key === 'no-date') return -1;
        
        if (activeTab.value === 'past' || activeTab.value === 'completed') {
            return b.timestamp - a.timestamp;
        }
        return a.timestamp - b.timestamp; 
    });
});
</script>

<template>
    <Head title="Assignments" />
    <AuthenticatedLayout>
        
        <!-- ALWAYS VISIBLE MOBILE FLOATING FAB -->
        <button @click="openCreateModal" class="md:hidden fixed bottom-[156px] right-4 z-[9999] flex items-center justify-center w-12 h-12 bg-white dark:bg-white text-blue-600 dark:text-blue-600 rounded-full border border-black dark:border-white shadow-[0_8px_30px_rgba(0,0,0,0.15)] dark:shadow-[0_8px_30px_rgba(255,255,255,0.2)] transition-all duration-300 ease-out hover:bg-slate-100 dark:hover:bg-slate-100 hover:scale-[1.03] active:scale-[0.95] focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900 cursor-pointer">
            <Plus class="w-5 h-5" />
        </button>

        <div class="h-full md:h-[calc(100vh-80px)] flex flex-col max-w-screen-2xl mx-auto -mt-2">
            
            <div class="mb-2 md:mb-3 shrink-0 px-2 sm:px-0 flex justify-between items-center border-b border-slate-100 dark:border-slate-800 pb-2 md:pb-3">
                <div>
                    <h1 class="text-lg sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-1.5 sm:gap-2">
                        <ClipboardList class="w-5 h-5 sm:w-6 sm:h-6 text-blue-600 dark:text-blue-500" />
                        Tasks
                    </h1>
                    <p class="hidden sm:block text-[9px] font-bold text-slate-500 uppercase tracking-widest mt-0.5 ml-8">Manage tasks across your active classes</p>
                </div>
            </div>

            <div class="md:hidden w-full px-2 mb-2 z-20 flex flex-col gap-2">
                <div class="flex gap-2">
                    <select @change="(e) => selectCourse(e.target.value === 'all' ? 'all' : Number(e.target.value))" class="w-full text-xs font-black uppercase tracking-widest bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 text-blue-800 dark:text-blue-300 rounded-lg p-2.5 focus:ring-2 focus:ring-blue-500 shadow-sm cursor-pointer truncate transition-colors">
                        <option value="all" :selected="selectedCourseId === 'all'">ALL CLASSES</option>
                        <option v-for="c in processedCourses" :key="c.id" :value="c.id" :selected="c.id === selectedCourseId">
                            {{ c.title }} ({{ c.ungraded_count || 0 }} To Grade)
                        </option>
                    </select>
                    <select v-model="courseSortOrder" class="w-1/3 min-w-[100px] text-[9px] font-bold uppercase tracking-widest bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 focus:border-blue-500 focus:ring-2 focus:ring-blue-500 rounded-lg p-2.5 shadow-sm cursor-pointer transition-colors dark:[color-scheme:dark]">
                        <option value="needs_grading">Most To Grade</option>
                        <option value="newest">Newest</option>
                        <option value="oldest">Oldest</option>
                        <option value="a_z">A-Z</option>
                        <option value="z_a">Z-A</option>
                    </select>
                </div>
            </div>

            <div class="flex-1 flex flex-col md:flex-row gap-0 md:gap-4 overflow-hidden bg-slate-50/30 md:bg-transparent rounded-none md:rounded-lg relative">
                
                <!-- ALWAYS VISIBLE DESKTOP FLOATING FAB -->
                <div class="hidden md:flex flex-col w-10 shrink-0 gap-3 pt-1 z-10">
                    <button @click="openCreateModal" class="group relative flex items-center justify-center w-10 h-10 bg-white dark:bg-white rounded-full border border-black dark:border-white text-blue-600 dark:text-blue-600 hover:bg-slate-100 dark:hover:bg-slate-100 transition shadow-sm">
                        <Plus class="w-5 h-5" />
                        <span class="absolute left-full ml-2 px-2 py-1 bg-slate-800 text-white text-[9px] font-bold rounded opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none whitespace-nowrap shadow-lg z-[9999]">Create Task</span>
                    </button>
                </div>

                <!-- DESKTOP SIDEBAR COURSE LIST -->
                <aside class="hidden md:flex w-52 lg:w-60 bg-slate-50/50 md:bg-white dark:bg-slate-900 md:dark:bg-slate-800 flex-col shrink-0 md:border border-slate-200 dark:border-slate-700 md:rounded-lg overflow-hidden md:h-full shadow-sm">
                    <div class="p-2.5 border-b border-slate-100 dark:border-slate-700/50 flex flex-col gap-2 shrink-0 bg-white dark:bg-slate-800">
                        <h3 class="text-[9px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest px-1">Active Classes</h3>
                        
                        <div class="relative w-full">
                            <div class="absolute inset-y-0 left-0 pl-2 flex items-center pointer-events-none">
                                <Filter class="h-3 w-3 text-slate-400" />
                            </div>
                            <select v-model="courseSortOrder" class="w-full h-7 pl-6 rounded bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 text-[9px] font-bold uppercase tracking-widest text-slate-600 dark:text-slate-300 shadow-sm transition-colors cursor-pointer py-0 pr-5 dark:[color-scheme:dark]">
                                <option value="needs_grading" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">Most To Grade</option>
                                <option value="newest" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">Newest First</option>
                                <option value="oldest" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">Oldest First</option>
                                <option value="a_z" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">Name (A-Z)</option>
                                <option value="z_a" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">Name (Z-A)</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="flex-col overflow-y-auto w-full p-2 gap-1 custom-scrollbar">
                        <!-- ALL CLASSES BUTTON -->
                        <button @click="selectCourse('all')" 
                            class="w-full text-left transition-colors duration-150 flex items-center justify-between group border-l-4 px-2 py-2.5 mb-1"
                            :class="selectedCourseId === 'all' ? 'bg-blue-50 dark:bg-blue-900/20 border-blue-600 shadow-sm' : 'bg-transparent border-transparent hover:bg-slate-100 dark:hover:bg-slate-700/50'"
                        >
                            <div class="flex items-center gap-2.5 overflow-hidden w-full">
                                <div class="w-7 h-7 rounded border border-slate-200 dark:border-slate-700 bg-slate-100 dark:bg-slate-800 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0">
                                    <Globe class="w-4 h-4" />
                                </div>
                                <div class="flex-1 min-w-0">
                                    <span class="block truncate text-xs font-black" :class="selectedCourseId === 'all' ? 'text-blue-900 dark:text-blue-100' : 'text-slate-700 dark:text-slate-200'">All Classes</span>
                                    <span class="text-[9px] font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">Global Task List</span>
                                </div>
                            </div>
                        </button>

                        <div v-if="processedCourses.length === 0" class="p-6 text-center text-[9px] font-black text-slate-400 uppercase tracking-widest">
                            No active classes.
                        </div>

                        <button v-for="course in processedCourses" :key="course.id" @click="selectCourse(course.id)"
                            class="w-full text-left transition-colors duration-150 flex items-center justify-between group border-l-4 px-2 py-2.5"
                            :class="selectedCourseId === course.id ? 'bg-blue-50 dark:bg-blue-900/20 border-blue-600 shadow-sm' : 'bg-transparent border-transparent hover:bg-slate-100 dark:hover:bg-slate-700/50'"
                        >
                            <div class="flex items-center gap-2.5 overflow-hidden w-full">
                                <div class="relative w-7 h-7 rounded border border-slate-200 dark:border-slate-700 bg-slate-100 dark:bg-slate-800 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0 overflow-hidden text-[10px] font-black uppercase">
                                    <img v-if="course.thumbnail && !imageErrors[course.id]" :src="getFileUrl(course.thumbnail)" @error="handleImageError(course.id)" class="w-full h-full object-cover" />
                                    <span v-else>{{ course.title.substring(0, 2) }}</span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <span class="block truncate text-xs" :class="selectedCourseId === course.id ? 'font-black text-blue-900 dark:text-blue-100' : 'font-bold text-slate-700 dark:text-slate-200'">
                                        {{ course.title }}
                                    </span>
                                    <div class="flex items-center gap-1.5 mt-0.5">
                                        <span class="text-[9px] font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                            {{ countUpcoming(course.assignments) }} active
                                        </span>
                                        <span v-if="course.ungraded_count > 0" class="flex items-center gap-1 text-red-600 dark:text-red-400 text-[9px] font-black">
                                            <span class="w-1.5 h-1.5 rounded-full bg-red-600 animate-pulse shadow-[0_0_5px_rgba(220,38,38,0.8)]"></span>
                                            {{ course.ungraded_count }} To Grade
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </button>
                    </div>
                </aside>

                <!-- MAIN TASK AREA -->
                <main class="flex-1 bg-transparent md:bg-white dark:bg-slate-800 flex flex-col md:border border-slate-200 dark:border-slate-700 md:rounded-lg overflow-hidden h-full min-h-[400px] md:shadow-sm relative">
                    <div v-if="isMainAreaVisible" class="flex flex-col h-full pt-0 md:pt-1">
                        
                        <div class="border-b border-slate-200 dark:border-slate-700 shrink-0 bg-white dark:bg-slate-800 flex flex-col gap-2 pt-2 px-2 sm:px-4">
                            
                            <!-- GLOBAL TASK SEARCH BAR -->
                            <div class="flex flex-col sm:flex-row gap-2 w-full mt-1">
                                <div class="relative flex-1">
                                    <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none">
                                        <Search class="h-3.5 w-3.5 text-slate-400" />
                                    </div>
                                    <input v-model="taskSearchQuery" type="text" placeholder="Search tasks by name or type..." class="w-full h-8 pl-8 rounded-md bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-blue-500 focus:border-transparent text-xs shadow-sm transition-colors" />
                                </div>
                            </div>

                            <div class="flex overflow-x-auto scrollbar-hide w-full pb-1 sm:pb-0 mt-1">
                                <button @click="activeTab = 'needs_grading'"
                                    class="px-2 sm:px-3 py-1.5 sm:py-3 text-[9px] sm:text-[10px] font-black uppercase tracking-widest mr-1 sm:mr-6 transition-all border-b-2 whitespace-nowrap flex items-center gap-1.5 flex-1 sm:flex-none justify-center relative"
                                    :class="activeTab === 'needs_grading' ? 'border-red-500 text-red-600 dark:text-red-500' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-300'">
                                    To Grade
                                    <span v-if="totalNeedsGrading > 0" class="absolute top-1.5 sm:top-2 right-0 sm:right-1 w-2 h-2 bg-red-500 border-2 border-white dark:border-slate-800 rounded-full animate-pulse shadow-sm"></span>
                                </button>
                                
                                <button @click="activeTab = 'upcoming'"
                                    class="px-2 sm:px-3 py-1.5 sm:py-3 text-[9px] sm:text-[10px] font-black uppercase tracking-widest mr-1 sm:mr-6 transition-all border-b-2 whitespace-nowrap flex items-center gap-1 flex-1 sm:flex-none justify-center"
                                    :class="activeTab === 'upcoming' ? 'border-blue-600 text-blue-600 dark:text-blue-400' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-300'">
                                    Upcoming
                                </button>
                                
                                <button @click="activeTab = 'past'"
                                    class="px-2 sm:px-3 py-1.5 sm:py-3 text-[9px] sm:text-[10px] font-black uppercase tracking-widest mr-1 sm:mr-6 transition-all border-b-2 whitespace-nowrap flex items-center gap-1 flex-1 sm:flex-none justify-center"
                                    :class="activeTab === 'past' ? 'border-red-600 text-red-600 dark:text-red-400' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-300'">
                                    Past Due
                                </button>
                                
                                <button @click="activeTab = 'completed'"
                                    class="px-2 sm:px-3 py-1.5 sm:py-3 text-[9px] sm:text-[10px] font-black uppercase tracking-widest sm:mr-6 transition-all border-b-2 whitespace-nowrap flex items-center gap-1 flex-1 sm:flex-none justify-center"
                                    :class="activeTab === 'completed' ? 'border-emerald-600 text-emerald-600 dark:text-emerald-400' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-300'">
                                    Completed
                                </button>
                            </div>
                        </div>

                        <div class="flex-1 overflow-y-auto p-1.5 sm:p-3 custom-scrollbar pb-24">
                            <!-- MS TEAMS STYLE TASK GROUPS -->
                            <div v-if="groupedAssignments.length > 0" class="flex flex-col gap-6">
                                <div v-for="group in groupedAssignments" :key="group.key" class="flex flex-col">
                                    
                                    <!-- DATE HEADER -->
                                    <div class="flex items-baseline gap-2 mb-2.5 px-1">
                                        <h3 class="text-[13px] sm:text-sm font-black text-slate-900 dark:text-white">{{ group.dateLabel }}</h3>
                                        <span v-if="group.relativeLabel" class="text-[10px] sm:text-[11px] font-bold text-slate-500">{{ group.relativeLabel }}</span>
                                    </div>

                                    <!-- TASKS UNDER THIS DATE -->
                                    <div class="flex flex-col gap-1.5 sm:gap-2">
                                        <Link v-for="assignment in group.tasks" :key="assignment.id"
                                            :href="route('teacher.assignments.show', { assignment: assignment.id, source: 'global' })"
                                            class="group flex flex-col sm:flex-row sm:items-center gap-1.5 sm:gap-2.5 p-3 sm:p-4 bg-white dark:bg-slate-800 border-l-2 sm:border-l-4 border border-slate-200 dark:border-slate-700 rounded-md sm:rounded-xl hover:shadow-md transition-all duration-200 shadow-sm"
                                            :class="[
                                                activeTab === 'needs_grading' ? 'border-l-red-500 hover:border-red-400 bg-red-50/10 dark:bg-red-900/5' :
                                                activeTab === 'upcoming' ? 'border-l-blue-500 hover:border-blue-400' : 
                                                activeTab === 'completed' ? 'border-l-emerald-500 hover:border-emerald-400' : 'border-l-red-500 hover:border-red-400'
                                            ]">
                                            
                                            <div class="hidden sm:flex shrink-0 w-8 h-8 rounded items-center justify-center transition-colors"
                                                :class="[
                                                    activeTab === 'needs_grading' ? 'bg-red-100 text-red-600 dark:bg-red-900/40 dark:text-red-400 group-hover:bg-red-600 group-hover:text-white' :
                                                    activeTab === 'upcoming' ? 'bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 group-hover:bg-blue-600 group-hover:text-white' : 
                                                    activeTab === 'completed' ? 'bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 group-hover:bg-emerald-600 group-hover:text-white' : 'bg-red-50 text-red-600 group-hover:bg-red-600 group-hover:text-white'
                                                ]">
                                                <AlertTriangle class="w-4 h-4" v-if="activeTab === 'needs_grading'" />
                                                <ClipboardList class="w-4 h-4" v-else />
                                            </div>

                                            <div class="flex-1 min-w-0">
                                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1 sm:gap-2">
                                                    <div class="flex items-center gap-1.5 min-w-0">
                                                        <span class="text-[7px] sm:text-[8px] font-black uppercase tracking-widest px-1 sm:px-1.5 py-0.5 rounded border border-slate-200 dark:border-slate-700 text-slate-500 shrink-0 bg-slate-50 dark:bg-slate-900">
                                                            {{ assignment.type ? assignment.type.replace('_', ' ') : 'Task' }}
                                                        </span>
                                                        <h4 class="text-[10px] sm:text-sm font-black text-slate-900 dark:text-white truncate transition-colors"
                                                            :class="[
                                                                activeTab === 'needs_grading' ? 'group-hover:text-red-600 dark:group-hover:text-red-400' :
                                                                activeTab === 'upcoming' ? 'group-hover:text-blue-600' : 
                                                                activeTab === 'completed' ? 'group-hover:text-emerald-600' : 'group-hover:text-red-600'
                                                            ]">
                                                            {{ assignment.title }}
                                                        </h4>
                                                    </div>
                                                    
                                                    <div class="flex flex-wrap gap-1.5 shrink-0 mt-1 sm:mt-0">
                                                        <span v-if="isRestricted(assignment.description)" class="text-[8px] sm:text-[10px] font-black whitespace-nowrap bg-orange-100 text-orange-700 dark:bg-orange-900/50 dark:text-orange-400 px-1.5 py-0.5 rounded shadow-sm border border-orange-200 dark:border-orange-800">
                                                            Hidden to Late Enrollees
                                                        </span>
                                                        <span v-if="assignment.ungraded_count > 0" class="flex items-center gap-1 text-[8px] sm:text-[10px] font-black whitespace-nowrap bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-400 px-1.5 py-0.5 rounded shadow-sm border border-red-200 dark:border-red-800">
                                                            <span class="w-1.5 h-1.5 rounded-full bg-red-500 animate-pulse"></span>
                                                            {{ assignment.ungraded_count }} To Grade
                                                        </span>
                                                        <span class="text-[8px] sm:text-[10px] font-black whitespace-nowrap bg-indigo-50 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-400 px-1.5 py-0.5 rounded shadow-sm border border-indigo-200 dark:border-indigo-800">
                                                            {{ assignment.submissions_count || 0 }} / {{ assignment.enrollments_count || 0 }} Submitted
                                                        </span>
                                                        <span class="text-[8px] sm:text-[10px] font-black whitespace-nowrap bg-slate-100 dark:bg-slate-900/50 px-1.5 py-0.5 rounded text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-slate-700">
                                                            {{ assignment.points }} pts
                                                        </span>
                                                    </div>
                                                </div>
                                                <p class="text-[9px] font-bold text-slate-500 mt-1 truncate">
                                                    {{ assignment.course_title }}
                                                </p>
                                            </div>

                                            <div class="flex items-center justify-between sm:justify-end gap-2 sm:gap-4 w-full sm:w-auto shrink-0 mt-1.5 sm:mt-0 pt-1.5 sm:pt-0 border-t border-dashed border-slate-100 sm:border-none dark:border-slate-700/50">
                                                <div class="flex items-center gap-1 text-[8px] sm:text-[9px] font-black uppercase tracking-widest">
                                                    <Clock class="w-2.5 h-2.5 text-slate-400 sm:hidden" />
                                                    <span :class="activeTab === 'past' ? 'text-red-600' : 'text-slate-500'">
                                                        {{ assignment.due_date ? new Date(assignment.due_date).toLocaleTimeString([], {hour: 'numeric', minute: '2-digit'}) : 'No Time Set' }}
                                                    </span>
                                                </div>
                                                
                                                <div v-if="isClosed(assignment) && activeTab !== 'completed'" class="text-[7px] sm:text-[9px] font-black text-red-600 bg-red-50 dark:bg-red-900/20 px-1 py-0.5 rounded uppercase tracking-widest border border-red-100 dark:border-red-800/50">Locked</div>
                                                <div v-else-if="activeTab === 'completed'" class="text-[7px] sm:text-[9px] font-black text-emerald-600 bg-emerald-50 dark:bg-emerald-900/20 px-1 py-0.5 rounded uppercase tracking-widest border border-emerald-100 dark:border-emerald-800/50">Done</div>
                                                
                                                <ChevronRight class="w-3 h-3 text-slate-300 transition-transform group-hover:translate-x-0.5 sm:hidden" />
                                                <ChevronRight class="w-4 h-4 text-slate-300 transition-transform group-hover:translate-x-0.5 hidden sm:block" />
                                            </div>
                                        </Link>
                                    </div>
                                </div>
                            </div>
                            
                            <div v-else class="flex flex-col items-center justify-center h-full py-12 px-4 text-slate-400 border border-dashed border-slate-200 dark:border-slate-700 rounded-lg bg-white dark:bg-slate-800 mt-2 sm:mt-0 shadow-sm">
                                <CheckCircle2 class="w-5 h-5 text-slate-300 dark:text-slate-600 mb-2" v-if="activeTab !== 'needs_grading'" />
                                <div class="w-10 h-10 bg-emerald-50 dark:bg-emerald-900/20 text-emerald-500 rounded-full flex items-center justify-center mb-2 border border-emerald-100 dark:border-emerald-900/30" v-else>
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                </div>
                                <h3 class="text-[9px] sm:text-[11px] font-black uppercase tracking-widest text-slate-900 dark:text-white mb-0.5">
                                    {{ activeTab === 'needs_grading' ? "You're completely caught up!" : "All clear" }}
                                </h3>
                                <p class="text-[8px] sm:text-[9px] font-bold text-center">
                                    {{ activeTab === 'needs_grading' ? 'No pending submissions left to grade.' : `No ${activeTab.replace('_', ' ')} tasks found.` }}
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div v-else class="flex flex-col items-center justify-center h-full p-6 text-slate-500 pt-1 bg-white md:bg-transparent rounded-lg m-2 md:m-0 border md:border-none border-slate-200">
                        <ClipboardList class="w-8 h-8 mb-2 text-slate-300 dark:text-slate-600" />
                        <p class="text-[9px] font-black uppercase tracking-widest text-center max-w-[200px] leading-relaxed">Select a class or unhide a class from your main dashboard.</p>
                    </div>
                </main>
            </div>
        </div>

        <!-- CREATE TASK MODAL (From Global Task List) -->
        <Modal :show="showCreateModal" @close="showCreateModal = false" maxWidth="sm">
            <div class="p-6 bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-slate-200 dark:border-slate-700">
                <h3 class="font-black text-sm text-slate-900 dark:text-white mb-4 uppercase tracking-tight flex items-center gap-2">
                    <div class="w-6 h-6 rounded bg-blue-100 text-blue-600 flex items-center justify-center">
                        <Plus class="w-3.5 h-3.5" />
                    </div>
                    Create New Task
                </h3>
                <div class="space-y-4">
                    <div>
                        <label class="block text-[9px] font-black uppercase text-slate-500 mb-1.5 tracking-widest">Select Class *</label>
                        <select v-model="courseForNewTask" class="w-full rounded-md bg-slate-50 dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white p-2 text-xs focus:ring-2 focus:ring-blue-500 shadow-sm transition cursor-pointer dark:[color-scheme:dark]">
                            <option value="" disabled>Choose a class...</option>
                            <option v-for="c in visibleCourses" :key="c.id" :value="c.id">{{ c.title }}</option>
                        </select>
                    </div>
                    
                    <div class="flex justify-end gap-3 pt-4 mt-2 border-t border-slate-100 dark:border-slate-700">
                        <button @click="showCreateModal = false" type="button" class="text-[10px] text-slate-500 px-4 py-2 font-black uppercase tracking-widest hover:text-slate-700 dark:hover:text-slate-300 transition-colors">Cancel</button>
                        <button @click="proceedToCreateTask" :disabled="!courseForNewTask" class="px-5 py-2 text-[10px] font-black uppercase tracking-widest text-white transition-colors duration-300 ease-out bg-blue-600 rounded-lg shadow-sm hover:bg-blue-500 disabled:opacity-50 disabled:cursor-not-allowed">
                            Continue
                        </button>
                    </div>
                </div>
            </div>
        </Modal>

    </AuthenticatedLayout>
</template>

<style scoped>
/* Mobile Tab Scroll Hider */
.scrollbar-hide::-webkit-scrollbar {
    display: none;
}
.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>