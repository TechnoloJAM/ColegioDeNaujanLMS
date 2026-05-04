<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { Download, ChevronDown, ChevronUp, ArrowUpDown } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import axios from 'axios';

const props = defineProps({
    course: Object,
    courses: Array,
    students: Array,
    assignments: Array,
});

// State for Mobile Accordion & Sorting
const expandedStudentId = ref(null);
const sortOrder = ref('alpha_asc');

const toggleStudent = (studentId) => {
    expandedStudentId.value = expandedStudentId.value === studentId ? null : studentId;
};

// Helper to safely find a student's submission
const getSubmission = (student, assignmentId) => {
    if (!student.submissions) return null;
    return student.submissions.find(s => s.assignment_id === assignmentId);
};

const saveGrade = async (studentId, assignmentId, event) => {
    const newGrade = event.target.value.trim();
    
    try {
        await axios.post(route('teacher.gradebook.autosave', props.course.id), {
            student_id: studentId,
            assignment_id: assignmentId,
            grade: newGrade !== '' ? parseFloat(newGrade) : null
        });
        
        event.target.classList.add('bg-emerald-50', 'text-emerald-700', 'dark:bg-emerald-900/30');
        setTimeout(() => {
            event.target.classList.remove('bg-emerald-50', 'text-emerald-700', 'dark:bg-emerald-900/30');
        }, 1000);
        
    } catch (error) {
        alert('Failed to save grade. Please check your connection.');
    }
};

const getStudentAverage = (student) => {
    let totalScore = 0;
    let totalMax = 0;
    
    props.assignments.forEach(a => {
        const sub = getSubmission(student, a.id);
        if (sub && sub.grade !== null) { 
            totalScore += parseFloat(sub.grade); 
            totalMax += parseFloat(a.points); 
        }
    });
    
    return totalMax === 0 ? 0 : ((totalScore / totalMax) * 100).toFixed(1);
};

// NEW: Dynamically sorted students list
const processedStudents = computed(() => {
    if (!props.students) return [];
    
    let list = props.students.map(student => {
        return {
            ...student,
            numericAverage: parseFloat(getStudentAverage(student))
        };
    });

    list.sort((a, b) => {
        if (sortOrder.value === 'alpha_asc') return a.name.localeCompare(b.name);
        if (sortOrder.value === 'alpha_desc') return b.name.localeCompare(a.name);
        if (sortOrder.value === 'avg_desc') return b.numericAverage - a.numericAverage;
        if (sortOrder.value === 'avg_asc') return a.numericAverage - b.numericAverage;
        return 0;
    });

    return list;
});

const switchCourse = (e) => {
    if (e.target.value) {
        router.visit(route('teacher.gradebook.index', e.target.value));
    }
};

const exportToCSV = () => {
    let csv = "data:text/csv;charset=utf-8,Student Name,";
    props.assignments.forEach(a => csv += `"${a.title} (/${a.points})",`);
    csv += "Overall Average (%)\r\n";
    
    processedStudents.value.forEach(student => {
        let row = [`"${student.name}"`];
        props.assignments.forEach(a => {
            const sub = getSubmission(student, a.id);
            row.push(sub && sub.grade !== null ? sub.grade : "0");
        });
        row.push(student.numericAverage + "%");
        csv += row.join(",") + "\r\n";
    });
    
    const link = document.createElement("a");
    link.setAttribute("href", encodeURI(csv));
    link.setAttribute("download", `${props.course?.title || 'Course'}_Gradebook.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
};
</script>

<template>
    <Head :title="course ? `Gradebook - ${course.title}` : 'Gradebook'" />
    
    <AuthenticatedLayout>
        <div class="max-w-screen-2xl mx-auto pb-12 px-4 sm:px-6">
            
            <div v-if="course" class="flex flex-col md:flex-row justify-between md:items-end gap-3 mb-4 sm:mb-6 border-b border-slate-100 dark:border-slate-800 pb-3">
                <div class="w-full md:w-auto">
                    <h1 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white flex flex-wrap items-center gap-2 sm:gap-3 leading-tight">
                        Gradebook
                        <select @change="switchCourse" class="text-xs sm:text-sm font-bold bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg py-1.5 pl-2.5 pr-8 focus:ring-2 focus:ring-blue-500 cursor-pointer shadow-sm transition max-w-[200px] sm:max-w-none truncate">
                            <option v-for="c in courses" :key="c.id" :value="c.id" :selected="c.id === course.id">{{ c.title }}</option>
                        </select>
                    </h1>
                </div>
                
                <div class="flex items-center gap-2 w-full md:w-auto shrink-0">
                    <div class="flex items-center bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg shadow-sm flex-1 md:flex-none">
                        <ArrowUpDown class="w-3.5 h-3.5 text-slate-400 ml-2 shrink-0" />
                        <select v-model="sortOrder" class="w-full text-[10px] font-black uppercase tracking-widest text-slate-600 dark:text-slate-300 bg-transparent border-none focus:ring-0 cursor-pointer py-2 pl-1.5 pr-6">
                            <option value="alpha_asc">A to Z</option>
                            <option value="alpha_desc">Z to A</option>
                            <option value="avg_desc">Highest Grade</option>
                            <option value="avg_asc">Lowest Grade</option>
                        </select>
                    </div>
                    <button @click="exportToCSV" class="flex items-center justify-center gap-1.5 bg-emerald-600 hover:bg-emerald-500 text-white px-3 py-2 rounded-lg font-black text-[10px] uppercase tracking-widest shadow-sm transition shrink-0">
                        <Download class="w-3.5 h-3.5" /> <span class="hidden sm:inline">Export</span>
                    </button>
                </div>
            </div>

            <div v-if="course && processedStudents.length > 0">
                
                <!-- DESKTOP VIEW: Standard Table Layout -->
                <div class="hidden md:block bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-x-auto custom-scrollbar">
                    <table class="w-full text-left text-sm whitespace-nowrap">
                        <thead class="bg-slate-50 dark:bg-slate-900/80 border-b border-slate-200 dark:border-slate-700 uppercase text-[9px] font-black text-slate-500 tracking-widest">
                            <tr>
                                <th class="p-4 sticky left-0 bg-slate-50 dark:bg-slate-900 border-r border-slate-200 dark:border-slate-700 z-10 w-48 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.05)]">
                                    Student Name
                                </th>
                                <th v-for="a in assignments" :key="a.id" class="p-3 min-w-[120px] border-r border-slate-100 dark:border-slate-800 text-center">
                                    <span class="block truncate max-w-[150px] text-slate-700 dark:text-slate-300" :title="a.title">{{ a.title }}</span>
                                    <span class="text-[9px] text-blue-600 dark:text-blue-400 mt-1 block">Out of {{ a.points }}</span>
                                </th>
                                <th class="p-4 bg-blue-50/50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400 text-center w-24">
                                    Average
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="(student, index) in processedStudents" :key="student.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                                
                                <td class="p-4 font-bold text-xs text-slate-900 dark:text-white sticky left-0 bg-white dark:bg-slate-800 border-r border-slate-200 dark:border-slate-700 z-10 truncate max-w-[12rem] shadow-[2px_0_5px_-2px_rgba(0,0,0,0.05)] flex items-center gap-2">
                                    <div class="w-6 h-6 rounded-full bg-blue-100 dark:bg-blue-900/50 text-blue-600 dark:text-blue-400 flex items-center justify-center text-[9px] shrink-0">
                                        {{ index + 1 }}
                                    </div>
                                    {{ student.name }}
                                </td>
                                
                                <td v-for="a in assignments" :key="a.id" class="p-2 border-r border-slate-100 dark:border-slate-800 relative">
                                    <input 
                                        type="number" 
                                        step="0.01" 
                                        min="0" 
                                        :max="a.points"
                                        :value="getSubmission(student, a.id)?.grade ?? ''"
                                        @blur="saveGrade(student.id, a.id, $event)"
                                        class="w-full text-center border-0 bg-transparent focus:ring-2 focus:ring-inset focus:ring-blue-500 rounded text-xs font-bold text-slate-700 dark:text-slate-200 placeholder-slate-300 dark:placeholder-slate-600 transition-colors"
                                        placeholder="-"
                                    />
                                </td>
                                
                                <td class="p-4 text-center text-xs font-black border-l border-slate-200 dark:border-slate-700" 
                                    :class="student.numericAverage < 75 ? 'text-red-600 bg-red-50 dark:bg-red-900/20' : 'text-emerald-600 bg-emerald-50 dark:bg-emerald-900/20'">
                                    {{ student.numericAverage }}%
                                </td>
                                
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- ULTRA COMPACT MOBILE VIEW -->
                <div class="md:hidden flex flex-col gap-2">
                    <div v-for="(student, index) in processedStudents" :key="student.id" class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 overflow-hidden shadow-sm">
                        <!-- Super Compact Accordion Header -->
                        <button @click="toggleStudent(student.id)" class="w-full flex items-center justify-between p-2.5 bg-slate-50/50 dark:bg-slate-900/30 transition-colors focus:outline-none">
                            <div class="flex items-center gap-2 min-w-0 pr-2">
                                <div class="w-6 h-6 rounded bg-blue-100 dark:bg-blue-900/50 text-blue-600 dark:text-blue-400 flex items-center justify-center text-[9px] font-black shrink-0">
                                    {{ index + 1 }}
                                </div>
                                <div class="text-left min-w-0">
                                    <span class="block text-xs font-black text-slate-900 dark:text-white truncate">{{ student.name }}</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <span class="text-[9px] font-black uppercase tracking-widest px-1.5 py-0.5 rounded border" 
                                      :class="student.numericAverage < 75 ? 'bg-red-50 text-red-600 border-red-200' : 'bg-emerald-50 text-emerald-600 border-emerald-200'">
                                    {{ student.numericAverage }}%
                                </span>
                                <component :is="expandedStudentId === student.id ? ChevronUp : ChevronDown" class="w-4 h-4 text-slate-400" />
                            </div>
                        </button>

                        <!-- Compact Assignments Grid -->
                        <div v-show="expandedStudentId === student.id" class="p-2 border-t border-slate-100 dark:border-slate-700 bg-white dark:bg-slate-800 grid grid-cols-2 gap-2">
                            <div v-for="a in assignments" :key="a.id" class="flex flex-col bg-slate-50 dark:bg-slate-900/50 p-2 rounded border border-slate-100 dark:border-slate-700 shadow-sm">
                                <div class="flex justify-between items-start mb-1.5 min-w-0">
                                    <span class="text-[10px] font-bold text-slate-800 dark:text-slate-200 truncate pr-1" :title="a.title">{{ a.title }}</span>
                                    <span class="text-[8px] font-black text-blue-500 uppercase tracking-widest shrink-0">/{{ a.points }}</span>
                                </div>
                                <input 
                                    type="number" 
                                    step="0.01" 
                                    min="0" 
                                    :max="a.points"
                                    :value="getSubmission(student, a.id)?.grade ?? ''"
                                    @blur="saveGrade(student.id, a.id, $event)"
                                    class="w-full h-7 text-center border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 focus:ring-2 focus:ring-inset focus:ring-blue-500 rounded text-[11px] font-black text-slate-900 dark:text-white placeholder-slate-300 transition-colors shadow-inner px-1"
                                    placeholder="Score"
                                />
                            </div>
                            <div v-if="assignments.length === 0" class="text-center py-3 text-slate-400 text-[9px] uppercase font-black tracking-widest col-span-full">No assignments to grade.</div>
                        </div>
                    </div>
                </div>

            </div>

            <div v-else-if="!course" class="p-10 text-center border-2 border-dashed border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-800 shadow-sm">
                <p class="text-slate-500 font-black text-[10px] uppercase tracking-widest">No classes available for grading.</p>
            </div>
            
            <div v-else class="p-10 text-center border-2 border-dashed border-slate-200 dark:border-slate-700 rounded-xl bg-white dark:bg-slate-800 shadow-sm">
                <div class="w-10 h-10 mx-auto bg-slate-100 dark:bg-slate-900 rounded-full flex items-center justify-center mb-2">
                    <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                </div>
                <p class="text-slate-500 font-black text-[10px] uppercase tracking-widest">No enrolled students.</p>
            </div>
            
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { height: 8px; }
.custom-scrollbar::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 8px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 8px; border: 2px solid #f1f5f9; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

input[type="number"]::-webkit-inner-spin-button, 
input[type="number"]::-webkit-outer-spin-button { 
    -webkit-appearance: none; 
    margin: 0; 
}
input[type="number"] { 
    -moz-appearance: textfield; 
}

@media (prefers-color-scheme: dark) {
    .custom-scrollbar::-webkit-scrollbar-track { background: #0f172a; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #334155; border-color: #0f172a; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #475569; }
}
</style>