<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import { ClipboardList, Plus, ChevronRight, Clock, CheckCircle2, AlertTriangle, Search, Filter } from 'lucide-vue-next';

const props = defineProps({
    courses: Array
});

// Read Hidden Courses storage key
const page = usePage();
const userId = page.props.auth.user.id;
const storageKey = `lms_hidden_courses_${userId}`;
const hiddenCourses = ref(JSON.parse(localStorage.getItem(storageKey)) || []);

// Filter out any course that the teacher has hidden globally
const visibleCourses = computed(() => {
    return props.courses.filter(c => !hiddenCourses.value.includes(c.id));
});

// ==========================================
// COURSE SIDEBAR SEARCH & SORT LOGIC
// ==========================================
const courseSearchQuery = ref('');
const courseSortOrder = ref('needs_grading'); // Default: show classes with most ungraded work first

const processedCourses = computed(() => {
    let filtered = visibleCourses.value;
    
    // 1. Search Filter
    if (courseSearchQuery.value.trim() !== '') {
        const q = courseSearchQuery.value.toLowerCase();
        filtered = filtered.filter(c => 
            c.title.toLowerCase().includes(q) || 
            (c.enrollment_code && c.enrollment_code.toLowerCase().includes(q))
        );
    }

    // 2. Sort Filter
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

// Set default selected course
const selectedCourseId = ref(processedCourses.value.length > 0 ? processedCourses.value[0].id : null);

// Smart Watcher: Auto-select the first course in new search results
watch(processedCourses, (newCourses) => {
    if (newCourses.length > 0 && !newCourses.find(c => c.id === selectedCourseId.value)) {
        selectedCourseId.value = newCourses[0].id;
    } else if (newCourses.length === 0) {
        selectedCourseId.value = null;
    }
});
// ==========================================

const activeTab = ref('needs_grading'); 

// Task Search State (Sort was removed to default to Latest)
const taskSearchQuery = ref('');

const imageErrors = ref({});

const selectCourse = (id) => { selectedCourseId.value = id; };

const handleCourseDropdownSelect = (e) => { selectCourse(Number(e.target.value)); };

const handleImageError = (id) => { imageErrors.value[id] = true; }; 

// HELPERS: Scrub the tag and check for restriction
const cleanDescription = (text) => text ? text.replace('[RESTRICT_LATE_STUDENTS]', '').trim() : '';
const isRestricted = (text) => text ? text.includes('[RESTRICT_LATE_STUDENTS]') : false;

const selectedCourse = computed(() => visibleCourses.value.find(c => c.id === selectedCourseId.value));

const countUpcoming = (assignments) => {
    if (!assignments) return 0;
    const now = new Date();
    return assignments.filter(a => {
        const dueDate = a.due_date ? new Date(a.due_date) : null;
        return !dueDate || dueDate >= now;
    }).length;
};

// Calculate total assignments that need grading in the selected course
const totalNeedsGrading = computed(() => {
    if (!selectedCourse.value || !selectedCourse.value.assignments) return 0;
    return selectedCourse.value.assignments.reduce((sum, a) => sum + (a.ungraded_count || 0), 0);
});

const isClosed = (assignment) => {
    if (!assignment || !assignment.closing_date) return false;
    const closingTime = new Date(assignment.closing_date).getTime();
    const currentTime = new Date().getTime();
    return currentTime > closingTime;
};

const filteredAssignments = computed(() => {
    if (!selectedCourse.value) return [];
    const now = new Date();
    
    let filtered = selectedCourse.value.assignments.filter(assignment => {
        const dueDate = assignment.due_date ? new Date(assignment.due_date) : null;
        const isPastDue = dueDate && dueDate < now;
        const closed = isClosed(assignment); 
        const needsGrading = assignment.ungraded_count > 0;
        
        // 1. Tab Filter
        let tabMatch = false;
        if (activeTab.value === 'needs_grading') tabMatch = needsGrading;
        else if (activeTab.value === 'upcoming') tabMatch = !isPastDue && !closed && !needsGrading;
        else if (activeTab.value === 'past') tabMatch = isPastDue && !closed && !needsGrading;
        else if (activeTab.value === 'completed') tabMatch = closed && !needsGrading; 
        else tabMatch = true;

        if (!tabMatch) return false;

        // 2. Search Filter
        if (taskSearchQuery.value.trim() !== '') {
            const q = taskSearchQuery.value.toLowerCase();
            return assignment.title.toLowerCase().includes(q) || 
                   (assignment.type && assignment.type.replace('_', ' ').toLowerCase().includes(q));
        }

        return true;
    });

    // 3. Sort Results (Always Latest First)
    filtered.sort((a, b) => {
        return new Date(b.created_at || 0).getTime() - new Date(a.created_at || 0).getTime();
    });

    return filtered;
});
</script>

<template>
    <Head title="Assignments" />
    <AuthenticatedLayout>
        
        <!-- MOBILE FLOATING FAB -->
        <Link v-if="selectedCourseId" :href="route('teacher.assignments.create', { course: selectedCourseId, source: 'global' })" class="md:hidden fixed bottom-[156px] right-4 z-[9999] flex items-center justify-center w-12 h-12 bg-white dark:bg-slate-800 rounded-full border border-slate-200 dark:border-slate-700 text-blue-600 hover:text-white hover:bg-blue-600 transition-all shadow-[0_8px_30px_rgb(0,0,0,0.15)] active:scale-95 cursor-pointer">
            <Plus class="w-6 h-6" />
        </Link>

        <div class="h-full md:h-[calc(100vh-80px)] flex flex-col max-w-screen-2xl mx-auto -mt-2">
            
            <!-- Header -->
            <div class="mb-2 md:mb-3 shrink-0 px-2 sm:px-0 flex justify-between items-center border-b border-slate-100 dark:border-slate-800 pb-2 md:pb-3">
                <div>
                    <h1 class="text-lg sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-1.5 sm:gap-2">
                        <ClipboardList class="w-5 h-5 sm:w-6 sm:h-6 text-blue-600 dark:text-blue-500" />
                        Tasks
                    </h1>
                    <p class="hidden sm:block text-[9px] font-bold text-slate-500 uppercase tracking-widest mt-0.5 ml-8">Manage tasks across your active classes</p>
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
                        <option value="needs_grading">To Grade</option>
                        <option value="newest">Newest</option>
                        <option value="oldest">Oldest</option>
                        <option value="a_z">A-Z</option>
                        <option value="z_a">Z-A</option>
                    </select>
                </div>
                
                <select @change="handleCourseDropdownSelect" class="w-full text-xs font-black uppercase tracking-widest bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 text-blue-800 dark:text-blue-300 rounded-lg p-2.5 focus:ring-2 focus:ring-blue-500 shadow-sm cursor-pointer truncate transition-colors">
                    <option v-if="processedCourses.length === 0" disabled selected>No classes match search.</option>
                    <option v-for="course in processedCourses" :key="course.id" :value="course.id" :selected="course.id === selectedCourseId">
                        {{ course.title }} ({{ course.ungraded_count }} To Grade)
                    </option>
                </select>
            </div>

            <div class="flex-1 flex flex-col md:flex-row gap-0 md:gap-4 overflow-hidden bg-slate-50/30 md:bg-transparent rounded-none md:rounded-lg relative">
                
                <!-- DESKTOP FLOATING FAB -->
                <div class="hidden md:flex flex-col w-12 shrink-0 gap-3 pt-1 z-10">
                    <Link v-if="selectedCourseId" :href="route('teacher.assignments.create', { course: selectedCourseId, source: 'global' })" class="group relative flex items-center justify-center w-12 h-12 bg-white dark:bg-slate-800 rounded-full border border-slate-200 dark:border-slate-700 text-blue-600 hover:text-white hover:bg-blue-600 transition-all shadow-sm hover:shadow-md focus:outline-none active:scale-95 cursor-pointer">
                        <Plus class="w-5 h-5" />
                        <span class="absolute left-full ml-3 px-2 py-1 bg-slate-800 text-white text-[10px] font-bold rounded opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none whitespace-nowrap shadow-lg z-[9999]">Create Task</span>
                    </Link>
                </div>

                <!-- DESKTOP SIDEBAR COURSE LIST -->
                <aside class="hidden md:flex w-56 lg:w-64 bg-slate-50/50 md:bg-white dark:bg-slate-900 md:dark:bg-slate-800 flex-col shrink-0 md:border border-slate-200 dark:border-slate-700 md:rounded-lg overflow-hidden md:h-full shadow-sm">
                    
                    <div class="p-3 border-b border-slate-100 dark:border-slate-700/50 flex flex-col gap-2.5 shrink-0 bg-white dark:bg-slate-800">
                        <h3 class="text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest">Active Classes</h3>
                        
                        <!-- COURSE SEARCH -->
                        <div class="relative w-full">
                            <div class="absolute inset-y-0 left-0 pl-2 flex items-center pointer-events-none">
                                <Search class="h-3 w-3 text-slate-400" />
                            </div>
                            <input v-model="courseSearchQuery" type="text" placeholder="Search class..." class="w-full h-8 pl-7 rounded-md bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 text-xs shadow-sm transition-colors placeholder-slate-400" />
                        </div>
                        
                        <!-- COURSE SORT -->
                        <div class="relative w-full">
                            <div class="absolute inset-y-0 left-0 pl-2 flex items-center pointer-events-none">
                                <Filter class="h-3 w-3 text-slate-400" />
                            </div>
                            <select v-model="courseSortOrder" class="w-full h-8 pl-7 rounded-md bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 text-[9px] font-bold uppercase tracking-widest text-slate-600 dark:text-slate-300 shadow-sm transition-colors cursor-pointer py-0 pr-6">
                                <option value="needs_grading">Most To Grade</option>
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

                        <button 
                             v-for="course in processedCourses" 
                             :key="course.id"
                            @click="selectCourse(course.id)"
                            class="w-full text-left transition-colors duration-150 flex items-center justify-between group border-l-4 px-2 py-2.5"
                            :class="selectedCourseId === course.id 
                                ? 'bg-blue-50 dark:bg-blue-900/20 border-blue-600' 
                                : 'bg-transparent border-transparent hover:bg-slate-100 dark:hover:bg-slate-700/50'"
                        >
                            <div class="flex items-center gap-2.5 overflow-hidden w-full">
                                <div class="relative w-7 h-7 rounded border border-slate-200 dark:border-slate-700 bg-slate-100 dark:bg-slate-800 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0 overflow-hidden text-[10px] font-black uppercase">
                                    <img v-if="course.thumbnail && !imageErrors[course.id]" 
                                         :src="course.thumbnail" 
                                         @error="handleImageError(course.id)"
                                        class="w-full h-full object-cover" />
                                    <span v-else>{{ course.title.substring(0, 2) }}</span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <span class="block truncate text-xs"
                                          :class="selectedCourseId === course.id ? 'font-black text-blue-900 dark:text-blue-100' : 'font-bold text-slate-700 dark:text-slate-200'">
                                        {{ course.title }}
                                    </span>
                                    <div class="flex items-center gap-1.5 mt-0.5">
                                        <span class="text-[9px] font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
                                            {{ countUpcoming(course.assignments) }} active
                                        </span>
                                        <span v-if="course.ungraded_count > 0" class="flex items-center gap-1 text-amber-600 dark:text-amber-400 text-[9px] font-black">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-600 animate-pulse shadow-[0_0_5px_rgba(217,119,6,0.8)]"></span>
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
                    
                    <div v-if="selectedCourse" class="flex flex-col h-full pt-0 md:pt-1">
                        
                        <!-- Header with Task Search and Tabs -->
                        <div class="border-b border-slate-200 dark:border-slate-700 shrink-0 bg-white dark:bg-slate-800 flex flex-col gap-2 pt-2 px-2 sm:px-4">
                            
                            <!-- Task Search Bar -->
                            <div class="flex flex-col sm:flex-row gap-2 w-full mt-1">
                                <!-- Search -->
                                <div class="relative flex-1">
                                    <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none">
                                        <Search class="h-3.5 w-3.5 text-slate-400" />
                                    </div>
                                    <input v-model="taskSearchQuery" type="text" placeholder="Search tasks by name or type..." class="w-full h-8 pl-8 rounded-md bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-blue-500 focus:border-transparent text-xs shadow-sm transition-colors" />
                                </div>
                            </div>

                            <!-- Scrollable Tabs -->
                            <div class="flex overflow-x-auto scrollbar-hide w-full pb-1 sm:pb-0 mt-1">
                                <button @click="activeTab = 'needs_grading'"
                                    class="px-2 sm:px-3 py-2 sm:py-3 text-[9px] sm:text-[10px] font-black uppercase tracking-widest mr-1 sm:mr-6 transition-all border-b-2 whitespace-nowrap flex items-center gap-1.5 flex-1 sm:flex-none justify-center relative"
                                    :class="activeTab === 'needs_grading' ? 'border-amber-500 text-amber-600 dark:text-amber-500' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-300'">
                                    To Grade
                                    <span v-if="totalNeedsGrading > 0" class="absolute top-1 sm:top-2 right-0 sm:right-1 w-2 h-2 bg-red-500 border-2 border-white dark:border-slate-800 rounded-full animate-pulse shadow-sm"></span>
                                </button>
                                
                                <button @click="activeTab = 'upcoming'"
                                    class="px-2 sm:px-3 py-2 sm:py-3 text-[9px] sm:text-[10px] font-black uppercase tracking-widest mr-1 sm:mr-6 transition-all border-b-2 whitespace-nowrap flex items-center gap-1 flex-1 sm:flex-none justify-center"
                                    :class="activeTab === 'upcoming' ? 'border-blue-600 text-blue-600 dark:text-blue-400' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-300'">
                                    Upcoming
                                </button>
                                
                                <button @click="activeTab = 'past'"
                                    class="px-2 sm:px-3 py-2 sm:py-3 text-[9px] sm:text-[10px] font-black uppercase tracking-widest mr-1 sm:mr-6 transition-all border-b-2 whitespace-nowrap flex items-center gap-1 flex-1 sm:flex-none justify-center"
                                    :class="activeTab === 'past' ? 'border-blue-600 text-blue-600 dark:text-blue-400' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-300'">
                                    Past Due
                                </button>
                                
                                <button @click="activeTab = 'completed'"
                                    class="px-2 sm:px-3 py-2 sm:py-3 text-[9px] sm:text-[10px] font-black uppercase tracking-widest sm:mr-6 transition-all border-b-2 whitespace-nowrap flex items-center gap-1 flex-1 sm:flex-none justify-center"
                                    :class="activeTab === 'completed' ? 'border-emerald-600 text-emerald-600 dark:text-emerald-400' : 'border-transparent text-slate-500 hover:text-slate-800 dark:hover:text-slate-300'">
                                    Completed
                                </button>
                            </div>
                        </div>

                        <!-- ULTRA COMPACT TASK LIST -->
                        <div class="flex-1 overflow-y-auto p-1.5 sm:p-3 custom-scrollbar pb-24">
                            <div v-if="filteredAssignments.length > 0" class="flex flex-col gap-1.5 sm:gap-2">
                                
                                <Link v-for="assignment in filteredAssignments" :key="assignment.id" 
                                      :href="route('teacher.assignments.show', { assignment: assignment.id, source: 'global' })"
                                     class="group flex flex-col sm:flex-row sm:items-center gap-1.5 sm:gap-2.5 p-2 sm:p-4 bg-white dark:bg-slate-800 border-l-2 sm:border-l-4 border border-slate-200 dark:border-slate-700 rounded-md sm:rounded-xl transition-all duration-200 shadow-sm"
                                     :class="[
                                         activeTab === 'needs_grading' ? 'border-l-amber-500 hover:border-amber-400 bg-amber-50/10 dark:bg-amber-900/5' :
                                         activeTab === 'upcoming' ? 'border-l-blue-500 hover:border-blue-400' : 
                                         activeTab === 'completed' ? 'border-l-emerald-500 hover:border-emerald-400' : 'border-l-red-500 hover:border-red-400'
                                     ]">
                                     
                                    <div class="hidden sm:flex shrink-0 w-8 h-8 rounded items-center justify-center transition-colors"
                                         :class="[
                                             activeTab === 'needs_grading' ? 'bg-amber-100 text-amber-600 dark:bg-amber-900/40 dark:text-amber-400 group-hover:bg-amber-500 group-hover:text-white' :
                                             activeTab === 'upcoming' ? 'bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 group-hover:bg-blue-600 group-hover:text-white' : 
                                             activeTab === 'completed' ? 'bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 group-hover:bg-emerald-600 group-hover:text-white' : 'bg-red-50 text-red-600 group-hover:bg-red-600 group-hover:text-white'
                                         ]">
                                        <AlertTriangle class="w-4 h-4" v-if="activeTab === 'needs_grading'" />
                                        <ClipboardList class="w-4 h-4" v-else />
                                    </div>

                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between gap-2">
                                            <div class="flex items-center gap-1.5 min-w-0">
                                                <span class="text-[7px] sm:text-[8px] font-black uppercase tracking-widest px-1 sm:px-1.5 py-0.5 rounded border border-slate-200 dark:border-slate-700 text-slate-500 shrink-0 bg-slate-50 dark:bg-slate-900">
                                                    {{ assignment.type ? assignment.type.replace('_', ' ') : 'Task' }}
                                                </span>
                                                <h4 class="text-[10px] sm:text-sm font-black text-slate-900 dark:text-white truncate transition-colors"
                                                    :class="[
                                                        activeTab === 'needs_grading' ? 'group-hover:text-amber-600 dark:group-hover:text-amber-400' :
                                                        activeTab === 'upcoming' ? 'group-hover:text-blue-600' : 
                                                        activeTab === 'completed' ? 'group-hover:text-emerald-600' : 'group-hover:text-red-600'
                                                    ]">
                                                    {{ assignment.title }}
                                                </h4>
                                            </div>
                                            
                                            <div class="flex gap-1.5 shrink-0">
                                                <!-- RESTRICTED LABEL -->
                                                <span v-if="isRestricted(assignment.description)" class="text-[8px] sm:text-[10px] font-black whitespace-nowrap bg-orange-100 text-orange-700 dark:bg-orange-900/50 dark:text-orange-400 px-1.5 py-0.5 rounded shadow-sm border border-orange-200 dark:border-orange-800">
                                                    Hidden to Late Enrollees
                                                </span>
                                                <!-- "TO GRADE" BADGE -->
                                                <span v-if="assignment.ungraded_count > 0" class="flex items-center gap-1 text-[8px] sm:text-[10px] font-black whitespace-nowrap bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-400 px-1.5 py-0.5 rounded shadow-sm border border-amber-200 dark:border-amber-800">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                                    {{ assignment.ungraded_count }} To Grade
                                                </span>
                                                <!-- TURN-IN RATE BADGE -->
                                                <span class="text-[8px] sm:text-[10px] font-black whitespace-nowrap bg-indigo-50 text-indigo-700 dark:bg-indigo-900/50 dark:text-indigo-400 px-1.5 py-0.5 rounded shadow-sm border border-indigo-200 dark:border-indigo-800">
                                                    {{ assignment.submissions_count || 0 }} / {{ selectedCourse.enrollments_count || 0 }} Submitted
                                                </span>
                                                <!-- Points Badge -->
                                                <span class="text-[8px] sm:text-[10px] font-black whitespace-nowrap bg-slate-100 dark:bg-slate-900/50 px-1.5 py-0.5 rounded text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-slate-700">
                                                    {{ assignment.points }} pts
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Uses the cleaner helper to strip the tag -->
                                        <p class="hidden sm:block text-[10px] text-slate-500 dark:text-slate-400 truncate font-medium leading-snug mt-0.5">
                                            {{ cleanDescription(assignment.description) || 'No instructions provided.' }}
                                        </p>
                                    </div>

                                    <div class="flex items-center justify-between sm:justify-end gap-2 sm:gap-4 w-full sm:w-auto shrink-0 mt-0.5 sm:mt-0 pt-1.5 sm:pt-0 border-t border-dashed border-slate-100 sm:border-none dark:border-slate-700/50">
                                        <div class="flex items-center gap-1 text-[8px] sm:text-[9px] font-black uppercase tracking-widest">
                                            <Clock class="w-2.5 h-2.5 text-slate-400 sm:hidden" />
                                            <span :class="activeTab === 'past' ? 'text-red-600' : 'text-slate-500'">
                                                {{ activeTab === 'completed' ? 'Closed' : 'Due' }}: {{ assignment.closing_date && activeTab === 'completed' ? new Date(assignment.closing_date).toLocaleDateString(undefined, {month: 'short', day: 'numeric'}) : assignment.due_date ? new Date(assignment.due_date).toLocaleDateString(undefined, {month: 'short', day: 'numeric'}) : 'No Date' }}
                                            </span>
                                        </div>
                                        
                                        <!-- Mobile Status/Action -->
                                        <div v-if="isClosed(assignment) && activeTab !== 'completed'" class="text-[7px] sm:text-[9px] font-black text-red-600 bg-red-50 dark:bg-red-900/20 px-1 py-0.5 rounded uppercase tracking-widest border border-red-100">Locked</div>
                                        <div v-else-if="activeTab === 'completed'" class="text-[7px] sm:text-[9px] font-black text-emerald-600 bg-emerald-50 dark:bg-emerald-900/20 px-1 py-0.5 rounded uppercase tracking-widest border border-emerald-100">Done</div>
                                        
                                        <ChevronRight class="w-3 h-3 text-slate-300 transition-transform group-hover:translate-x-0.5 sm:hidden" />
                                        <ChevronRight class="w-4 h-4 text-slate-300 transition-transform group-hover:translate-x-0.5 hidden sm:block" />
                                    </div>
                                </Link>

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
    width: 3px;
    height: 3px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(148, 163, 184, 0.3);
    border-radius: 10px;
}
</style>