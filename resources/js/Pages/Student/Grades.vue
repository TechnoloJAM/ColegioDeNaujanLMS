<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { ChevronDown, ChevronUp, Search, Filter, BookOpen, User } from 'lucide-vue-next';

const props = defineProps({
    courses: Array
});

const expandedCourseId = ref(null);
const searchQuery = ref('');
const sortOrder = ref('grade_desc');
const selectedCourseFilter = ref('all');
const selectedInstructorFilter = ref('all');

const toggleCourse = (courseId) => {
    expandedCourseId.value = expandedCourseId.value === courseId ? null : courseId;
};

// Dynamically generate unique filter options from the available data
const uniqueCourses = computed(() => {
    if (!props.courses) return [];
    return [...new Set(props.courses.map(c => c.title))].sort();
});

const uniqueInstructors = computed(() => {
    if (!props.courses) return [];
    return [...new Set(props.courses.map(c => c.teacher))].sort();
});

const isMissing = (task) => {
    if (task.is_submitted) return false;
    if (!task.due_date) return false;
    return new Date(task.due_date) < new Date();
};

// Smart Search & Filter Logic
const processedCourses = computed(() => {
    if (!props.courses) return [];

    let result = props.courses;

    // 1. Course Filter
    if (selectedCourseFilter.value !== 'all') {
        result = result.filter(c => c.title === selectedCourseFilter.value);
    }

    // 2. Instructor Filter
    if (selectedInstructorFilter.value !== 'all') {
        result = result.filter(c => c.teacher === selectedInstructorFilter.value);
    }

    // 3. Search Bar Filter
    if (searchQuery.value.trim() !== '') {
        const query = searchQuery.value.toLowerCase();
        result = result.filter(c => 
            c.title.toLowerCase().includes(query) || 
            c.teacher.toLowerCase().includes(query)
        );
    }

    // 4. Sort Order
    result = [...result].sort((a, b) => {
        if (sortOrder.value === 'alpha_asc') return a.title.localeCompare(b.title);
        if (sortOrder.value === 'alpha_desc') return b.title.localeCompare(a.title);
        if (sortOrder.value === 'grade_desc') return b.summary.percentage - a.summary.percentage;
        if (sortOrder.value === 'grade_asc') return a.summary.percentage - b.summary.percentage;
        return 0;
    });

    return result;
});
</script>

<template>
    <Head title="My Grades" />
    
    <AuthenticatedLayout>
        <div class="max-w-4xl mx-auto pb-12 px-2 sm:px-4">
            
            <div class="mb-3 sm:mb-4 border-b border-slate-100 dark:border-slate-800 pb-2.5">
                <h1 class="text-lg sm:text-xl font-black text-slate-900 dark:text-white leading-tight">My Official Grades</h1>
                <p class="text-[9px] sm:text-[10px] font-bold text-slate-500 uppercase tracking-widest mt-0.5">
                    Your academic performance across all classes.
                </p>
            </div>

            <div v-if="courses && courses.length > 0">
                
                <!-- ULTRA-COMPACT SEARCH & FILTER BAR -->
                <div class="flex flex-col sm:flex-row gap-2 mb-3 sm:mb-4 bg-slate-50 dark:bg-slate-900/50 p-2 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm">
                    
                    <!-- Search -->
                    <div class="relative flex-1 min-w-0">
                        <Search class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-400" />
                        <input v-model="searchQuery" type="text" placeholder="Search class or instructor..." 
                               class="w-full h-8 pl-8 pr-3 py-1 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 rounded text-[10px] font-bold text-slate-900 dark:text-white focus:ring-1 focus:ring-blue-500 focus:border-transparent transition shadow-inner" />
                    </div>
                    
                    <!-- Filters Grid -->
                    <div class="grid grid-cols-2 sm:flex gap-2 shrink-0">
                        <div class="relative min-w-0">
                            <BookOpen class="absolute left-2 top-1/2 -translate-y-1/2 w-3 h-3 text-slate-400" />
                            <select v-model="selectedCourseFilter" class="w-full h-8 pl-6 pr-6 py-1 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 rounded text-[9px] font-black uppercase tracking-widest text-slate-600 dark:text-slate-300 focus:ring-1 focus:ring-blue-500 focus:border-transparent shadow-sm cursor-pointer transition truncate">
                                <option value="all">All Classes</option>
                                <option v-for="c in uniqueCourses" :key="c" :value="c">{{ c }}</option>
                            </select>
                        </div>
                        
                        <div class="relative min-w-0">
                            <User class="absolute left-2 top-1/2 -translate-y-1/2 w-3 h-3 text-slate-400" />
                            <select v-model="selectedInstructorFilter" class="w-full h-8 pl-6 pr-6 py-1 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 rounded text-[9px] font-black uppercase tracking-widest text-slate-600 dark:text-slate-300 focus:ring-1 focus:ring-blue-500 focus:border-transparent shadow-sm cursor-pointer transition truncate">
                                <option value="all">All Instructors</option>
                                <option v-for="i in uniqueInstructors" :key="i" :value="i">{{ i }}</option>
                            </select>
                        </div>

                        <div class="relative col-span-2 sm:col-span-1 min-w-0">
                            <Filter class="absolute left-2 top-1/2 -translate-y-1/2 w-3 h-3 text-slate-400" />
                            <select v-model="sortOrder" class="w-full h-8 pl-6 pr-6 py-1 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 rounded text-[9px] font-black uppercase tracking-widest text-slate-600 dark:text-slate-300 focus:ring-1 focus:ring-blue-500 focus:border-transparent shadow-sm cursor-pointer transition truncate">
                                <option value="grade_desc">Highest Grade</option>
                                <option value="grade_asc">Lowest Grade</option>
                                <option value="alpha_asc">A to Z</option>
                                <option value="alpha_desc">Z to A</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div v-if="processedCourses.length > 0" class="flex flex-col gap-2 sm:gap-3">
                    
                    <div v-for="course in processedCourses" :key="course.id" class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden transition-all hover:border-blue-300 dark:hover:border-blue-600">
                        
                        <!-- Accordion Header -->
                        <button @click="toggleCourse(course.id)" class="w-full flex items-center justify-between p-2.5 sm:p-3 bg-slate-50/50 dark:bg-slate-900/30 transition-colors focus:outline-none">
                            <div class="flex flex-col items-start min-w-0 pr-3 text-left w-full">
                                <h3 class="text-xs sm:text-sm font-black text-slate-900 dark:text-white truncate w-full">{{ course.title }}</h3>
                                <span class="text-[8px] sm:text-[9px] font-bold text-slate-500 uppercase tracking-widest mt-0.5 truncate w-full">Instructor: {{ course.teacher }}</span>
                            </div>
                            <div class="flex items-center gap-2 sm:gap-3 shrink-0">
                                <div class="text-right">
                                    <span class="block text-[11px] sm:text-xs font-black" :class="course.summary.percentage >= 75 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
                                        {{ course.summary.percentage }}%
                                    </span>
                                    <span class="block text-[7px] sm:text-[8px] font-bold text-slate-400 uppercase tracking-widest mt-0.5">
                                        {{ course.summary.earned_total }} / {{ course.summary.max_total }} Pts
                                    </span>
                                </div>
                                <component :is="expandedCourseId === course.id ? ChevronUp : ChevronDown" class="w-3.5 h-3.5 text-slate-400" />
                            </div>
                        </button>

                        <!-- Accordion Body (Details) -->
                        <div v-show="expandedCourseId === course.id" class="p-2 sm:p-3 border-t border-slate-100 dark:border-slate-700 bg-white dark:bg-slate-800">
                            
                            <div class="grid grid-cols-3 gap-1.5 mb-3">
                                <div class="text-center bg-blue-50/50 dark:bg-blue-900/10 p-1.5 rounded border border-blue-100 dark:border-blue-800/30">
                                    <span class="block text-[7px] font-black uppercase tracking-widest text-blue-500">Assign</span>
                                    <span class="block text-[10px] sm:text-[11px] font-black text-blue-600 dark:text-blue-400 mt-0.5">{{ course.summary.assign_ps }}%</span>
                                    <span class="block text-[7px] font-bold text-slate-500 mt-0.5">{{ course.summary.earned_assign }} / {{ course.summary.max_assign }}</span>
                                </div>
                                <div class="text-center bg-purple-50/50 dark:bg-purple-900/10 p-1.5 rounded border border-purple-100 dark:border-purple-800/30">
                                    <span class="block text-[7px] font-black uppercase tracking-widest text-purple-500">Activity</span>
                                    <span class="block text-[10px] sm:text-[11px] font-black text-purple-600 dark:text-purple-400 mt-0.5">{{ course.summary.act_ps }}%</span>
                                    <span class="block text-[7px] font-bold text-slate-500 mt-0.5">{{ course.summary.earned_act }} / {{ course.summary.max_act }}</span>
                                </div>
                                <div class="text-center bg-orange-50/50 dark:bg-orange-900/10 p-1.5 rounded border border-orange-100 dark:border-orange-800/30">
                                    <span class="block text-[7px] font-black uppercase tracking-widest text-orange-500">PT</span>
                                    <span class="block text-[10px] sm:text-[11px] font-black text-orange-600 dark:text-orange-400 mt-0.5">{{ course.summary.pt_ps }}%</span>
                                    <span class="block text-[7px] font-bold text-slate-500 mt-0.5">{{ course.summary.earned_pt }} / {{ course.summary.max_pt }}</span>
                                </div>
                            </div>

                            <h4 class="text-[8px] font-black uppercase tracking-widest text-slate-400 mb-1.5 px-1">Task Breakdown</h4>
                            <div class="space-y-1">
                                <Link v-for="task in course.assignments" :key="task.id" 
                                      :href="route('student.courses.show', { course: course.id, tab: 'assignments', target: task.id })"
                                      class="flex flex-col sm:flex-row sm:items-center justify-between bg-slate-50 dark:bg-slate-900/50 p-2 rounded border border-slate-100 dark:border-slate-700/50 hover:border-blue-300 dark:hover:border-blue-600 hover:shadow-sm gap-1.5 transition-all group">
                                    
                                    <div class="flex flex-col min-w-0 flex-1 w-full">
                                        <div class="flex items-center gap-1.5 min-w-0">
                                            <span class="text-[6px] font-black uppercase tracking-widest bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 px-1 py-0.5 rounded text-slate-500 shrink-0">{{ task.type }}</span>
                                            <span class="text-[9px] sm:text-[10px] font-bold text-slate-800 dark:text-slate-200 truncate group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors w-full">{{ task.title }}</span>
                                        </div>
                                        <span v-if="task.feedback" class="text-[8px] italic text-emerald-600 dark:text-emerald-400 mt-0.5 pl-0.5 line-clamp-1 w-full truncate">"{{ task.feedback }}"</span>
                                    </div>
                                    
                                    <div class="flex items-center justify-between sm:justify-end shrink-0 pl-1 border-t sm:border-none border-dashed border-slate-200 dark:border-slate-700 pt-1.5 sm:pt-0 w-full sm:w-auto">
                                        <div v-if="task.grade !== null" class="flex items-center gap-1 bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-400 px-1.5 py-0.5 rounded border border-emerald-100 dark:border-emerald-800/30">
                                            <span class="text-[9px] sm:text-[10px] font-black">{{ task.grade }}</span>
                                            <span class="text-[7px] font-bold uppercase tracking-widest">/ {{ task.points }}</span>
                                        </div>
                                        <div v-else-if="task.is_submitted" class="flex items-center gap-1 bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400 px-1.5 py-0.5 rounded border border-blue-100 dark:border-blue-800/30">
                                            <span class="text-[8px] font-black uppercase tracking-widest">Submitted</span>
                                            <span class="text-[7px] font-bold uppercase tracking-widest">/ {{ task.points }}</span>
                                        </div>
                                        <div v-else-if="isMissing(task)" class="flex items-center gap-1 bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 px-1.5 py-0.5 rounded border border-red-100 dark:border-red-800/30">
                                            <span class="text-[8px] font-black uppercase tracking-widest">Missing</span>
                                            <span class="text-[7px] font-bold uppercase tracking-widest">/ {{ task.points }}</span>
                                        </div>
                                        <div v-else class="flex items-center gap-1 bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 px-1.5 py-0.5 rounded border border-slate-200 dark:border-slate-700">
                                            <span class="text-[8px] font-black uppercase tracking-widest">To Do</span>
                                            <span class="text-[7px] font-bold uppercase tracking-widest">/ {{ task.points }}</span>
                                        </div>
                                    </div>
                                </Link>
                                
                                <div v-if="course.assignments.length === 0" class="text-center py-3 text-[8px] text-slate-400 uppercase font-black tracking-widest border border-dashed border-slate-200 dark:border-slate-700 rounded bg-slate-50/50 dark:bg-slate-900/30">
                                    No tasks assigned yet.
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
                
                <div v-else class="text-center py-10 bg-white dark:bg-slate-800 rounded-xl border border-dashed border-slate-200 dark:border-slate-700 shadow-sm mt-4">
                    <Search class="w-6 h-6 mx-auto text-slate-300 dark:text-slate-600 mb-2" />
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">No classes matched your search.</p>
                </div>

            </div>

            <!-- Empty State -->
            <div v-else class="mt-6 sm:mt-8 bg-white dark:bg-slate-800 rounded-xl border border-dashed border-slate-200 dark:border-slate-700 shadow-sm p-8 sm:p-12 text-center flex flex-col items-center justify-center">
                <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-tight">No Grades Available</h3>
                <p class="text-[9px] sm:text-[10px] text-slate-500 mt-1 uppercase font-bold tracking-widest">Enroll in a class to see your grades.</p>
            </div>

        </div>
    </AuthenticatedLayout>
</template>