<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { ClipboardList, Plus, ChevronRight, Clock, CheckCircle2, AlertCircle, AlertTriangle } from 'lucide-vue-next';

const props = defineProps({
    courses: Array
});

const selectedCourseId = ref(props.courses.length > 0 ? props.courses[0].id : null);
const activeTab = ref('needs_grading'); // Default to Needs Grading if there's work to do
const sortOrder = ref('desc'); 
const imageErrors = ref({});

const selectCourse = (id) => { selectedCourseId.value = id; };
const handleCourseDropdownSelect = (e) => { selectCourse(Number(e.target.value)); };
const handleImageError = (id) => { imageErrors.value[id] = true; };

const selectedCourse = computed(() => props.courses.find(c => c.id === selectedCourseId.value));

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
        
        if (activeTab.value === 'needs_grading') return needsGrading;
        if (activeTab.value === 'upcoming') return !isPastDue && !closed && !needsGrading;
        if (activeTab.value === 'past') return isPastDue && !closed && !needsGrading;
        if (activeTab.value === 'completed') return closed && !needsGrading; 
        return true;
    });

    filtered.sort((a, b) => {
        const dateA = new Date(a.created_at || 0).getTime();
        const dateB = new Date(b.created_at || 0).getTime();
        return sortOrder.value === 'desc' ? dateB - dateA : dateA - dateB;
    });

    return filtered;
});
</script>

<template>
    <Head title="Assignments" />
    <AuthenticatedLayout>
        
        <!-- MOBILE FLOATING FAB (Extracted to Root so it never gets blocked) -->
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
                    <p class="hidden sm:block text-[9px] font-bold text-slate-500 uppercase tracking-widest mt-0.5 ml-8">Manage tasks across your classes</p>
                </div>
            </div>

            <!-- Mobile Class Selector Dropdown -->
            <div class="md:hidden w-full px-2 mb-2 z-20">
                <select @change="handleCourseDropdownSelect" class="w-full text-xs font-bold bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white rounded-lg p-2 focus:ring-2 focus:ring-blue-500 shadow-sm cursor-pointer truncate">
                    <option v-for="course in courses" :key="course.id" :value="course.id" :selected="course.id === selectedCourseId">
                        {{ course.title }} ({{ course.ungraded_count }} To Grade)
                    </option>
                </select>
            </div>

            <div class="flex-1 flex flex-col md:flex-row gap-0 md:gap-4 overflow-hidden bg-slate-50/30 md:bg-transparent rounded-none md:rounded-lg relative">
                
                <!-- DESKTOP FLOATING FAB (Left Side, Extracted safely) -->
                <div class="hidden md:flex flex-col w-12 shrink-0 gap-3 pt-1 z-10">
                    <Link v-if="selectedCourseId" :href="route('teacher.assignments.create', { course: selectedCourseId, source: 'global' })" class="group relative flex items-center justify-center w-12 h-12 bg-white dark:bg-slate-800 rounded-full border border-slate-200 dark:border-slate-700 text-blue-600 hover:text-white hover:bg-blue-600 transition-all shadow-sm hover:shadow-md focus:outline-none active:scale-95 cursor-pointer">
                        <Plus class="w-5 h-5" />
                        <span class="absolute left-full ml-3 px-2 py-1 bg-slate-800 text-white text-[10px] font-bold rounded opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none whitespace-nowrap shadow-lg z-[9999]">Create Task</span>
                    </Link>
                </div>

                <!-- Desktop Sidebar Class List -->
                <aside class="hidden md:flex w-56 lg:w-64 bg-slate-50/50 md:bg-white dark:bg-slate-900 md:dark:bg-slate-800 flex-col shrink-0 md:border border-slate-200 dark:border-slate-700 md:rounded-lg overflow-hidden md:h-full shadow-sm">
                    <div class="p-3 border-b border-slate-100 dark:border-slate-700/50 shrink-0 items-center justify-between">
                        <h3 class="text-[10px] font-black text-slate-500 dark:text-slate-400 uppercase tracking-widest">Your Classes</h3>
                    </div>
                    <div class="flex-col overflow-y-auto w-full p-2 gap-1 custom-scrollbar">
                        <button 
                            v-for="course in courses" 
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

                <!-- Main Task Area -->
                <main class="flex-1 bg-transparent md:bg-white dark:bg-slate-800 flex flex-col md:border border-slate-200 dark:border-slate-700 md:rounded-lg overflow-hidden h-full min-h-[400px] md:shadow-sm relative">
                    
                    <div v-if="selectedCourse" class="flex flex-col h-full pt-0 md:pt-1">
                        
                        <div class="border-b border-slate-200 dark:border-slate-700 shrink-0 bg-white dark:bg-slate-800 flex justify-between items-center px-1 sm:px-4">
                            <!-- Scrollable Tabs -->
                            <div class="flex overflow-x-auto scrollbar-hide w-full sm:w-auto pb-1 sm:pb-0">
                                <!-- NEEDS GRADING TAB -->
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
                            
                            <!-- Desktop Sort -->
                            <div class="hidden sm:flex shrink-0 py-2 ml-2 items-center bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded pr-1 shadow-sm">
                                <select v-model="sortOrder" class="text-[9px] font-black uppercase tracking-widest text-slate-500 bg-transparent border-none focus:ring-0 cursor-pointer py-1.5 pl-2 pr-6">
                                    <option value="desc">Latest</option>
                                    <option value="asc">Oldest</option>
                                </select>
                            </div>
                        </div>

                        <!-- Mobile Sort Bar (Extremely thin) -->
                        <div class="sm:hidden flex justify-end px-2 py-1.5 bg-slate-100/50 dark:bg-slate-900/30 border-b border-slate-200 dark:border-slate-700">
                             <select v-model="sortOrder" class="text-[8px] font-black uppercase tracking-widest text-slate-500 bg-transparent border-none focus:ring-0 cursor-pointer py-0 pl-1 pr-6 h-5">
                                <option value="desc">Sort: Latest</option>
                                <option value="asc">Sort: Oldest</option>
                            </select>
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
                                                <!-- "TO GRADE" BADGE -->
                                                <span v-if="assignment.ungraded_count > 0" class="flex items-center gap-1 text-[8px] sm:text-[10px] font-black whitespace-nowrap bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-400 px-1.5 py-0.5 rounded shadow-sm border border-amber-200 dark:border-amber-800">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                                    {{ assignment.ungraded_count }} To Grade
                                                </span>

                                                <!-- Points Badge -->
                                                <span class="text-[8px] sm:text-[10px] font-black whitespace-nowrap bg-slate-100 dark:bg-slate-900/50 px-1.5 py-0.5 rounded text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-slate-700">
                                                    {{ assignment.points }} pts
                                                </span>
                                            </div>
                                        </div>

                                        <p class="hidden sm:block text-[10px] text-slate-500 dark:text-slate-400 truncate font-medium leading-snug mt-0.5">
                                            {{ assignment.description || 'No instructions provided.' }}
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
                                    {{ activeTab === 'needs_grading' ? 'No pending submissions left to grade.' : `No ${activeTab.replace('_', ' ')} assignments found.` }}
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div v-else class="flex flex-col items-center justify-center h-full p-6 text-slate-500 pt-1 bg-white md:bg-transparent rounded-lg m-2 md:m-0 border md:border-none border-slate-200">
                        <ClipboardList class="w-8 h-8 mb-2 text-slate-300 dark:text-slate-600" />
                        <p class="text-[9px] font-black uppercase tracking-widest">Select a class.</p>
                    </div>
                </main>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.scrollbar-hide::-webkit-scrollbar { display: none; }
.scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }

.custom-scrollbar::-webkit-scrollbar { width: 3px; height: 3px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(148, 163, 184, 0.3); border-radius: 10px; }
</style>