<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { Download, Search, FileSpreadsheet } from 'lucide-vue-next';

const props = defineProps({
    courses: Array
});

const searchQuery = ref('');
const selectedCourseId = ref('all');

const filteredCourses = computed(() => {
    let result = props.courses;

    if (selectedCourseId.value !== 'all') {
        result = result.filter(c => c.id === selectedCourseId.value);
    }

    if (searchQuery.value) {
        const lowerSearch = searchQuery.value.toLowerCase();
        result = result.filter(c => 
            c.title.toLowerCase().includes(lowerSearch) || 
            c.teacher.toLowerCase().includes(lowerSearch)
        );
    }

    return result;
});

const formatYearLevel = (level) => {
    const levels = { 'beginner': '1st Year', 'intermediate': '2nd Year', 'advanced': '3rd Year', 'final': '4th Year' };
    return levels[level] || level;
};

// FULLY DETAILED EXCEL/CSV EXPORT
const downloadCSV = () => {
    let csvContent = "SYSTEM-WIDE GRADES REPORT\n";
    csvContent += `Generated on: ${new Date().toLocaleDateString()}\n\n`;

    filteredCourses.value.forEach(course => {
        csvContent += `=================================================\n`;
        csvContent += `COURSE DETAILS\n`;
        csvContent += `=================================================\n`;
        csvContent += `Course Name:,"${course.title}"\n`;
        csvContent += `Course Code:,"${course.enrollment_code}"\n`;
        csvContent += `Teacher:,"${course.teacher}"\n`;
        csvContent += `Year Level:,"${formatYearLevel(course.difficulty_level)}"\n`;
        csvContent += `Max Points:,Total: ${course.total_points} | Assign: ${course.max_assignment} | Act: ${course.max_activity} | PT: ${course.max_pt}\n`;
        csvContent += `Total Enrolled:,${course.students.length}\n`;
        csvContent += `-------------------------------------------------\n\n`;

        if (course.students.length === 0) {
            csvContent += `No students are currently enrolled in this course.\n\n\n\n`;
        } else {
            csvContent += `Rank,Student Name,Email Address,Assignments (${course.max_assignment}),Activities (${course.max_activity}),Performance Tasks (${course.max_pt}),Total Score (${course.total_points}),Percentage\n`;
            
            course.students.forEach((student, index) => {
                csvContent += `${index + 1},"${student.name}","${student.email}",${student.assignment_score},${student.activity_score},${student.pt_score},${student.total_score},"${student.percentage}%"\n`;
            });
            
            csvContent += `\n\n\n`;
        }
    });

    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement("a");
    const url = URL.createObjectURL(blob);
    link.setAttribute("href", url);
    link.setAttribute("download", `LMS_Detailed_Grades_${new Date().toISOString().split('T')[0]}.csv`);
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
};
</script>

<template>
    <Head title="Grades Overview" />

    <AuthenticatedLayout>
        <div class="max-w-screen-2xl mx-auto flex flex-col h-full">
            
            <div class="mb-4 pb-3 border-b border-slate-200 dark:border-slate-700 shrink-0 px-1 sm:px-0 flex flex-col sm:flex-row sm:items-end justify-between gap-4">
                <div>
                    <h1 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
                        <FileSpreadsheet class="w-6 h-6 text-emerald-600 dark:text-emerald-500" />
                        Grades Overview
                    </h1>
                    <p class="text-[9px] sm:text-[10px] text-slate-500 dark:text-slate-400 font-bold uppercase tracking-widest mt-1 ml-8">System-wide academic performance</p>
                </div>

                <div class="flex items-center gap-3">
                    <button @click="downloadCSV" class="w-full sm:w-auto justify-center bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-2.5 sm:py-2 rounded-lg text-[10px] font-black uppercase tracking-widest shadow-sm transition-all flex items-center gap-1.5 h-full">
                        <Download class="w-4 h-4 shrink-0" />
                        Export to CSV
                    </button>
                </div>
            </div>

            <!-- FILTERS: Full width on mobile, side-by-side on desktop -->
            <div class="flex flex-col sm:flex-row gap-3 mb-4">
                <div class="relative flex-1">
                    <Search class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400" />
                    <input v-model="searchQuery" type="text" placeholder="Search by course or teacher name..." 
                        class="w-full pl-9 pr-4 py-2 sm:py-1.5 text-xs rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-900 dark:text-white focus:ring-2 focus:ring-emerald-500 transition shadow-sm" />
                </div>
                
                <select v-model="selectedCourseId" class="w-full sm:w-auto sm:min-w-[200px] py-2 sm:py-1.5 px-3 text-[10px] sm:text-xs font-bold uppercase tracking-widest sm:tracking-normal sm:normal-case rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 focus:ring-2 focus:ring-emerald-500 shadow-sm cursor-pointer">
                    <option value="all">All Courses</option>
                    <option v-for="c in courses" :key="c.id" :value="c.id">{{ c.title }}</option>
                </select>
            </div>

            <div class="flex-1 overflow-y-auto space-y-4 pb-8 custom-scrollbar pr-1">
                
                <div v-if="filteredCourses.length === 0" class="text-center py-12 bg-white dark:bg-slate-800 rounded-xl border border-dashed border-slate-200 dark:border-slate-700">
                    <FileSpreadsheet class="w-12 h-12 mx-auto text-slate-300 dark:text-slate-600 mb-3" />
                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-500">No courses match your search.</p>
                </div>

                <div v-for="course in filteredCourses" :key="course.id" class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
                    
                    <!-- Course Header -->
                    <div class="bg-slate-50 dark:bg-slate-900/50 p-2.5 px-3 border-b border-slate-200 dark:border-slate-700 flex flex-wrap items-center justify-between gap-2">
                        <div class="flex items-center gap-2 min-w-0">
                            <h2 class="font-black text-[11px] sm:text-xs text-slate-900 dark:text-white uppercase tracking-tight truncate">{{ course.title }}</h2>
                            <span class="text-[7px] sm:text-[8px] font-mono bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300 px-1.5 py-0.5 rounded shrink-0">{{ course.enrollment_code }}</span>
                            <span class="text-[8px] font-bold uppercase tracking-widest text-slate-400 hidden sm:inline shrink-0">| {{ course.teacher }} | {{ formatYearLevel(course.difficulty_level) }}</span>
                        </div>
                        <div class="text-[8px] font-black bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-600 px-1.5 py-0.5 rounded text-slate-600 dark:text-slate-300 shadow-sm shrink-0">
                            {{ course.students.length }} Enrolled
                        </div>
                    </div>

                    <div v-if="course.students.length > 0">
                        <!-- DESKTOP VIEW: Standard Horizontal Table -->
                        <div class="hidden md:block overflow-x-auto custom-scrollbar">
                            <table class="w-full text-left text-[9px] sm:text-[10px] text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                <thead class="uppercase font-black text-slate-400 bg-slate-50/30 dark:bg-slate-900/20 border-b border-slate-100 dark:border-slate-700/50">
                                    <tr>
                                        <th class="px-2 sm:px-3 py-1.5 text-center w-6">#</th>
                                        <th class="px-2 sm:px-3 py-1.5">Student</th>
                                        <th class="px-2 sm:px-3 py-1.5 text-right bg-blue-50/30 dark:bg-blue-900/10">Assign. <span class="text-blue-400">({{ course.max_assignment }})</span></th>
                                        <th class="px-2 sm:px-3 py-1.5 text-right bg-purple-50/30 dark:bg-purple-900/10">Act. <span class="text-purple-400">({{ course.max_activity }})</span></th>
                                        <th class="px-2 sm:px-3 py-1.5 text-right bg-orange-50/30 dark:bg-orange-900/10">P.T. <span class="text-orange-400">({{ course.max_pt }})</span></th>
                                        <th class="px-2 sm:px-3 py-1.5 text-right border-l border-slate-100 dark:border-slate-700">Total <span class="text-slate-500">({{ course.total_points }})</span></th>
                                        <th class="px-2 sm:px-3 py-1.5 text-right">%</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-50 dark:divide-slate-700/30">
                                    <tr v-for="(student, index) in course.students" :key="student.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                                        <td class="px-2 sm:px-3 py-1 text-center font-bold text-slate-400">{{ index + 1 }}</td>
                                        <td class="px-2 sm:px-3 py-1">
                                            <div class="font-bold text-slate-900 dark:text-white truncate max-w-[180px]">{{ student.name }}</div>
                                        </td>
                                        <td class="px-2 sm:px-3 py-1 text-right font-medium bg-blue-50/10 dark:bg-blue-900/5">{{ student.assignment_score }}</td>
                                        <td class="px-2 sm:px-3 py-1 text-right font-medium bg-purple-50/10 dark:bg-purple-900/5">{{ student.activity_score }}</td>
                                        <td class="px-2 sm:px-3 py-1 text-right font-medium bg-orange-50/10 dark:bg-orange-900/5">{{ student.pt_score }}</td>
                                        <td class="px-2 sm:px-3 py-1 text-right font-black text-slate-800 dark:text-slate-200 border-l border-slate-50 dark:border-slate-700/50">{{ student.total_score }}</td>
                                        <td class="px-2 sm:px-3 py-1 text-right">
                                            <span class="text-[9px] font-black px-1.5 py-0.5 rounded"
                                                :class="{
                                                    'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400': student.percentage >= 85,
                                                    'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-400': student.percentage >= 60 && student.percentage < 85,
                                                    'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400': student.percentage < 60
                                                }">
                                                {{ student.percentage }}%
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- MOBILE VIEW: Ultra Compact Vertical List -->
                        <div class="md:hidden flex flex-col divide-y divide-slate-50 dark:divide-slate-700/30">
                            <div v-for="(student, index) in course.students" :key="student.id" class="p-2.5 hover:bg-slate-50 dark:hover:bg-slate-700/30 transition flex flex-col gap-1.5">
                                <div class="flex justify-between items-center gap-2">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <span class="text-[9px] font-black text-slate-400 w-4 text-center shrink-0">{{ index + 1 }}</span>
                                        <span class="text-[11px] font-bold text-slate-900 dark:text-white truncate leading-tight">{{ student.name }}</span>
                                    </div>
                                    <span class="text-[9px] font-black px-1.5 py-0.5 rounded shrink-0" 
                                        :class="{
                                            'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400': student.percentage >= 85,
                                            'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-400': student.percentage >= 60 && student.percentage < 85,
                                            'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400': student.percentage < 60
                                        }">
                                        {{ student.percentage }}%
                                    </span>
                                </div>
                                <div class="flex items-center justify-between text-[8px] font-bold uppercase tracking-widest ml-6">
                                    <div class="flex gap-2.5 text-slate-500 dark:text-slate-400">
                                        <span>Asg: <span class="text-blue-600 dark:text-blue-400">{{ student.assignment_score }}</span></span>
                                        <span>Act: <span class="text-purple-600 dark:text-purple-400">{{ student.activity_score }}</span></span>
                                        <span>PT: <span class="text-orange-600 dark:text-orange-400">{{ student.pt_score }}</span></span>
                                    </div>
                                    <span class="text-slate-700 dark:text-slate-300">Tot: {{ student.total_score }}/{{ course.total_points }}</span>
                                </div>
                            </div>
                        </div>

                    </div>
                    
                    <div v-else class="p-4 text-center text-[9px] font-bold uppercase tracking-widest text-slate-400 dark:text-slate-500">
                        No students enrolled.
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { height: 4px; width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(148, 163, 184, 0.2); border-radius: 10px; }
</style>