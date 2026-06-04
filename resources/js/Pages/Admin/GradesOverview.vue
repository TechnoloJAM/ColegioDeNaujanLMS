<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { Download, Search, FileSpreadsheet } from 'lucide-vue-next';
import * as XLSX from 'xlsx';

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

// HELPER FORMULA: (Raw Score / Highest Possible Score) * 100
const calculatePS = (score, max) => {
    if (!max || max === 0) return '0%';
    return ((score / max) * 100).toFixed(1) + '%';
};

// FULLY FORMATTED .XLSX EXCEL EXPORT WITH PS%
const downloadExcel = () => {
    const wb = XLSX.utils.book_new();

    filteredCourses.value.forEach(course => {
        const wsData = [
            ['', '', '', 'OFFICIAL CLASS RECORD'], // Row 1
            [], // Row 2 (Empty for spacing)
            ['Course:', course.title, '', '', 'Teacher:', course.teacher], // Row 3
            [], // Row 4 (Empty for spacing)
            
            // Row 5: DepEd Aligned Headers
            [
                'Name of Student', 
                'Assignments (Raw)', 'Assign. PS (%)', 
                'Activities (Raw)', 'Activity PS (%)', 
                'Performance Tasks (Raw)', 'PT PS (%)', 
                'Initial Grade (Raw)', 'Quarterly Grade (%)'
            ],
            
            // Row 6: Highest Possible Scores
            [
                'HIGHEST POSSIBLE SCORE', 
                course.max_assignment, '100%', 
                course.max_activity, '100%', 
                course.max_pt, '100%', 
                course.total_points, '100%'
            ]
        ];

        // Row 7+: Add Students and Calculate PS dynamically
        if (course.students && course.students.length > 0) {
            course.students.forEach(student => {
                wsData.push([
                    student.name,
                    student.assignment_score,
                    calculatePS(student.assignment_score, course.max_assignment),
                    student.activity_score,
                    calculatePS(student.activity_score, course.max_activity),
                    student.pt_score,
                    calculatePS(student.pt_score, course.max_pt),
                    student.total_score,
                    `${student.percentage}%`
                ]);
            });
        } else {
            wsData.push(['No students enrolled.']);
        }

        const ws = XLSX.utils.aoa_to_sheet(wsData);

        // Set exact column widths so text is NEVER cut off and PS columns fit nicely
        ws['!cols'] = [
            { wch: 35 }, // A: Name of Student
            { wch: 18 }, // B: Assign Raw
            { wch: 15 }, // C: Assign PS
            { wch: 18 }, // D: Activity Raw
            { wch: 15 }, // E: Activity PS
            { wch: 24 }, // F: PT Raw
            { wch: 15 }, // G: PT PS
            { wch: 20 }, // H: Initial Raw
            { wch: 20 }  // I: Quarterly %
        ];

        let safeSheetName = course.title.replace(/[\\\/\?\*\[\]]/g, '').substring(0, 31);
        XLSX.utils.book_append_sheet(wb, ws, safeSheetName);
    });

    let fileName = "System_Grades_Report.xlsx";
    if (selectedCourseId.value !== 'all' && filteredCourses.value.length > 0) {
        fileName = `${filteredCourses.value[0].title.replace(/\s+/g, '_')}_Grades.xlsx`;
    }

    XLSX.writeFile(wb, fileName);
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
                    <button @click="downloadExcel" class="w-full sm:w-auto justify-center bg-emerald-600 hover:bg-emerald-500 text-white px-4 py-2.5 sm:py-2 rounded-lg text-[10px] font-black uppercase tracking-widest shadow-sm transition-all flex items-center gap-1.5 h-full">
                        <Download class="w-4 h-4 shrink-0" />
                        Export to Excel
                    </button>
                </div>
            </div>

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
                        <div class="hidden md:block overflow-x-auto custom-scrollbar">
                            <table class="w-full text-left text-[9px] sm:text-[10px] text-slate-600 dark:text-slate-400 whitespace-nowrap">
                                <thead class="uppercase font-black text-slate-400 bg-slate-50/30 dark:bg-slate-900/20 border-b border-slate-100 dark:border-slate-700/50">
                                    <tr>
                                        <th class="px-2 sm:px-3 py-1.5 text-center w-6">#</th>
                                        <th class="px-2 sm:px-3 py-1.5">Student</th>
                                        <th class="px-2 sm:px-3 py-1.5 text-right bg-blue-50/30 dark:bg-blue-900/10">Assign. <span class="text-blue-400">({{ course.max_assignment }})</span></th>
                                        <th class="px-2 sm:px-3 py-1.5 text-right bg-purple-50/30 dark:bg-purple-900/10">Act. <span class="text-purple-400">({{ course.max_activity }})</span></th>
                                        <th class="px-2 sm:px-3 py-1.5 text-right bg-orange-50/30 dark:bg-orange-900/10">P.T. <span class="text-orange-400">({{ course.max_pt }})</span></th>
                                        <th class="px-2 sm:px-3 py-1.5 text-right border-l border-slate-100 dark:border-slate-700">Total raw score <span class="text-slate-500">({{ course.total_points }})</span></th>
                                        <th class="px-2 sm:px-3 py-1.5 text-right">total %</th>
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
                                                    'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-400': student.percentage >= 75 && student.percentage < 85,
                                                    'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400': student.percentage < 75
                                                }">
                                                {{ student.percentage }}%
                                            </span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

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
                                            'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-400': student.percentage >= 75 && student.percentage < 85,
                                            'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400': student.percentage < 75
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