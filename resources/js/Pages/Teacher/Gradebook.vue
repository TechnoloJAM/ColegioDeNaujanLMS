<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { Download, ChevronDown, ChevronUp, ArrowUpDown, FileText, Clock } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import axios from 'axios';
import * as XLSX from 'xlsx';

const props = defineProps({
    course: Object,
    courses: Array,
    students: Array,
    assignments: Array,
});

const expandedStudentId = ref(null);
const sortOrder = ref('alpha_asc');

const isEditMode = ref(false);
const isSaving = ref(false);
const pendingGrades = ref({});
const validationErrors = ref({}); 

const hasErrors = computed(() => Object.keys(validationErrors.value).length > 0);

const toggleStudent = (studentId) => {
    expandedStudentId.value = expandedStudentId.value === studentId ? null : studentId;
};

const getSubmission = (student, assignmentId) => {
    if (!student.submissions) return null;
    return student.submissions.find(s => s.assignment_id === assignmentId);
};

// 🪄 THE SECRET TAG LOGIC (Using the new tag!)
const isLateEnrollee = (student, assignment) => {
    const desc = assignment.description || '';
    
    // Check if the teacher clicked the checkbox (which appended the tag)
    const isHiddenFromLate = desc.includes('[RESTRICT_LATE_STUDENTS]');
    
    // If the teacher didn't check the box, it is mandatory for everyone!
    if (!isHiddenFromLate) return false; 
    
    // If they did check the box, calculate if the student is late
    if (!assignment.due_date) return false; 
    if (!student.pivot || !student.pivot.created_at) return false;
    
    const enrollmentDate = new Date(student.pivot.created_at);
    const dueDate = new Date(assignment.due_date);
    
    return enrollmentDate > dueDate;
};

const updatePendingGrade = (studentId, assignmentId, maxPoints, event) => {
    const val = event.target.value.trim();
    const numericVal = parseFloat(val);
    const max = parseFloat(maxPoints);

    if (val !== '' && !isNaN(numericVal) && numericVal > max) {
        validationErrors.value[`${studentId}_${assignmentId}`] = true;
    } else {
        delete validationErrors.value[`${studentId}_${assignmentId}`];
    }

    pendingGrades.value[`${studentId}_${assignmentId}`] = {
        student_id: studentId,
        assignment_id: assignmentId,
        grade: val
    };
};

const getInputValue = (studentId, assignmentId) => {
    const key = `${studentId}_${assignmentId}`;
    if (pendingGrades.value[key] !== undefined) {
        return pendingGrades.value[key].grade;
    }
    const student = props.students.find(s => s.id === studentId);
    return getSubmission(student, assignmentId)?.grade ?? '';
};

const toggleEditMode = async () => {
    if (!isEditMode.value) {
        isEditMode.value = true;
        pendingGrades.value = {}; 
        validationErrors.value = {}; 
    } else {
        if (hasErrors.value) {
            alert('Cannot save: One or more grades exceed the maximum allowed score. Please fix the highlighted errors.');
            return;
        }

        const keys = Object.keys(pendingGrades.value);
        if (keys.length === 0) {
            isEditMode.value = false; 
            return;
        }

        isSaving.value = true;
        try {
            const requests = keys.map(key => {
                const data = pendingGrades.value[key];
                const gradeVal = data.grade !== '' ? parseFloat(data.grade) : null;
                return axios.post(route('teacher.gradebook.autosave', props.course.id), {
                    student_id: data.student_id,
                    assignment_id: data.assignment_id,
                    grade: gradeVal
                });
            });

            await Promise.all(requests);
            router.reload({ only: ['students'] });
            
            pendingGrades.value = {};
            isEditMode.value = false;
        } catch (error) {
            alert('Failed to save some grades. Please check your connection.');
        } finally {
            isSaving.value = false;
        }
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

const calculatePS = (score, max) => {
    if (!max || max === 0) return '0%';
    return ((score / max) * 100).toFixed(1) + '%';
};

const downloadExcel = () => {
    if (!props.course || !props.assignments || !props.students) return;

    let maxAssignment = 0, maxActivity = 0, maxPt = 0, totalPoints = 0;
    props.assignments.forEach(task => {
        const points = Number(task.points) || 0;
        totalPoints += points;
        if (task.type === 'assignment') maxAssignment += points;
        else if (task.type === 'activity') maxActivity += points;
        else if (task.type === 'performance_task') maxPt += points;
    });

    const wb = XLSX.utils.book_new();

    const wsData = [
        ['', '', '', 'OFFICIAL CLASS RECORD'], 
        [], 
        ['Course:', props.course.title], 
        [], 
        [
            'Name of Student', 
            'Assignments (Raw)', 'Assign. PS (%)', 
            'Activities (Raw)', 'Activity PS (%)', 
            'Performance Tasks (Raw)', 'PT PS (%)', 
            'Initial Grade (Raw)', 'Quarterly Grade (%)'
        ],
        [
            'HIGHEST POSSIBLE SCORE', 
            maxAssignment, '100%', 
            maxActivity, '100%', 
            maxPt, '100%', 
            totalPoints, '100%'
        ]
    ];

    if (props.students.length > 0) {
        props.students.forEach(student => {
            let assignmentScore = 0, activityScore = 0, ptScore = 0, totalScore = 0;

            const submissionsMap = {};
            if (student.submissions) {
                student.submissions.forEach(sub => {
                    submissionsMap[sub.assignment_id] = Number(sub.grade) || 0;
                });
            }

            props.assignments.forEach(task => {
                const earned = submissionsMap[task.id] || 0;
                totalScore += earned;
                if (task.type === 'assignment') assignmentScore += earned;
                else if (task.type === 'activity') activityScore += earned;
                else if (task.type === 'performance_task') ptScore += earned;
            });

            const percentage = totalPoints > 0 ? ((totalScore / totalPoints) * 100).toFixed(1) : 0;

            wsData.push([
                student.name,
                assignmentScore,
                calculatePS(assignmentScore, maxAssignment),
                activityScore,
                calculatePS(activityScore, maxActivity),
                ptScore,
                calculatePS(ptScore, maxPt),
                totalScore,
                `${percentage}%`
            ]);
        });
    } else {
        wsData.push(['No students enrolled.']);
    }

    const ws = XLSX.utils.aoa_to_sheet(wsData);

    ws['!cols'] = [
        { wch: 35 }, { wch: 18 }, { wch: 15 }, { wch: 18 }, { wch: 15 }, { wch: 24 }, { wch: 15 }, { wch: 20 }, { wch: 20 }
    ];

    let safeSheetName = props.course.title.replace(/[\\\/\?\*\[\]]/g, '').substring(0, 31);
    XLSX.utils.book_append_sheet(wb, ws, safeSheetName);

    XLSX.writeFile(wb, `${props.course.title.replace(/\s+/g, '_')}_Gradebook.xlsx`);
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
                
                <div class="flex items-center gap-2 w-full md:w-auto shrink-0 flex-wrap">
                    
                    <div class="flex items-center bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg shadow-sm flex-1 md:flex-none">
                        <ArrowUpDown class="w-3.5 h-3.5 text-slate-400 ml-2 shrink-0" />
                        <select v-model="sortOrder" class="w-full text-[10px] font-black uppercase tracking-widest text-slate-600 dark:text-slate-300 bg-transparent border-none focus:ring-0 cursor-pointer py-2 pl-1.5 pr-6">
                            <option value="alpha_asc">A to Z</option>
                            <option value="alpha_desc">Z to A</option>
                            <option value="avg_desc">Highest Grade</option>
                            <option value="avg_asc">Lowest Grade</option>
                        </select>
                    </div>

                    <button @click="toggleEditMode"
                            :class="isEditMode ? (hasErrors ? 'bg-red-600 hover:bg-red-500 text-white' : 'bg-emerald-600 hover:bg-emerald-500 text-white') : 'bg-blue-600 hover:bg-blue-500 text-white'"
                            class="flex items-center justify-center gap-1.5 px-3 py-2 rounded-lg font-black text-[10px] uppercase tracking-widest shadow-sm transition shrink-0 disabled:opacity-50">
                        
                        <svg v-if="isSaving" class="animate-spin w-3.5 h-3.5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <svg v-else-if="!isEditMode" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                        <svg v-else-if="hasErrors" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        
                        <span class="hidden sm:inline">
                            {{ isSaving ? 'Saving...' : (isEditMode ? (hasErrors ? 'Fix Errors to Save' : 'Save Changes') : 'Edit Grades') }}
                        </span>
                    </button>

                    <button @click="downloadExcel" class="flex items-center justify-center gap-1.5 bg-slate-800 hover:bg-slate-700 text-white px-3 py-2 rounded-lg font-black text-[10px] uppercase tracking-widest shadow-sm transition shrink-0">
                        <Download class="w-3.5 h-3.5" /> <span class="hidden sm:inline">Export</span>
                    </button>
                </div>
            </div>

            <div v-if="course && processedStudents.length > 0">
                
                <div class="hidden md:block bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-x-auto custom-scrollbar relative">
                    
                    <div v-if="isEditMode" class="p-2 text-center text-xs font-black uppercase tracking-widest border-b transition-colors"
                         :class="hasErrors ? 'bg-red-50 text-red-800 border-red-200 dark:bg-red-900/40 dark:text-red-400 dark:border-red-800' : 'bg-blue-50 text-blue-800 border-blue-200 dark:bg-blue-900/30 dark:text-blue-400 dark:border-blue-800'">
                        <span v-if="hasErrors">❌ Cannot Save: A grade exceeds the maximum score!</span>
                        <span v-else>✏️ Edit Mode Active: Click "Save Changes" when finished.</span>
                    </div>

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
                                
                                <td v-for="a in assignments" :key="a.id" class="p-2 border-r border-slate-100 dark:border-slate-800 relative" :class="!isEditMode ? 'bg-slate-50/50 dark:bg-slate-900/20' : ''">
                                    
                                    <template v-if="getSubmission(student, a.id)">
                                        <div class="flex items-center justify-center gap-1.5">
                                            <a :href="route('teacher.assignments.show', a.id)" target="_blank" title="View Submission" 
                                               class="text-slate-400 hover:text-blue-500 dark:text-slate-500 dark:hover:text-blue-400 transition">
                                                <FileText class="w-4 h-4 shrink-0" />
                                            </a>

                                            <div class="flex-1">
                                                <template v-if="isEditMode">
                                                    <input 
                                                        type="number" 
                                                        step="0.01" 
                                                        min="0" 
                                                        :max="a.points"
                                                        :value="getInputValue(student.id, a.id)"
                                                        @input="updatePendingGrade(student.id, a.id, a.points, $event)"
                                                        class="w-full text-center border-0 bg-transparent focus:ring-2 focus:ring-inset rounded text-xs font-bold transition-colors"
                                                        :class="validationErrors[`${student.id}_${a.id}`] ? 'text-red-600 focus:ring-red-500 bg-red-50 dark:bg-red-900/40 dark:text-red-400' : 'text-slate-700 dark:text-slate-200 focus:ring-blue-500 placeholder-slate-300 dark:placeholder-slate-600'"
                                                        placeholder="-"
                                                    />
                                                </template>
                                                <template v-else>
                                                    <div class="w-full text-center py-1.5 text-xs font-bold text-slate-500 dark:text-slate-400 cursor-not-allowed">
                                                        {{ getSubmission(student, a.id)?.grade ?? '-' }}
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                    
                                    <template v-else>
                                        <div v-if="isLateEnrollee(student, a)" class="w-full text-center py-1 flex items-center justify-center gap-1 text-[8px] font-black text-slate-500 dark:text-slate-400 bg-slate-100 dark:bg-slate-800/50 rounded uppercase tracking-widest cursor-not-allowed border border-slate-200 dark:border-slate-700" title="Task hidden: The deadline passed before this student enrolled.">
                                            <Clock class="w-3 h-3" /> Late Enrollee
                                        </div>
                                        <div v-else class="w-full text-center py-1.5 text-[10px] font-black text-red-400 dark:text-red-500/80 uppercase tracking-widest cursor-not-allowed" title="No submission found">
                                            Missing
                                        </div>
                                    </template>

                                </td>
                                
                                <td class="p-4 text-center text-xs font-black border-l border-slate-200 dark:border-slate-700">
                                    <span class="text-[9px] font-black px-1.5 py-0.5 rounded shrink-0" 
                                        :class="{
                                            'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400': student.numericAverage >= 85,
                                            'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-400': student.numericAverage >= 75 && student.numericAverage < 85,
                                            'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400': student.numericAverage < 75
                                        }">
                                        {{ student.numericAverage }}%
                                    </span>
                                </td>
                                
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="md:hidden flex flex-col gap-2">
                    
                    <div v-if="isEditMode" class="p-2 text-center text-[10px] font-black uppercase tracking-widest rounded-lg border shadow-sm mb-2 transition-colors"
                         :class="hasErrors ? 'bg-red-50 text-red-800 border-red-200 dark:bg-red-900/40 dark:text-red-400 dark:border-red-800' : 'bg-blue-50 text-blue-800 border-blue-200 dark:bg-blue-900/30 dark:text-blue-400 dark:border-blue-800'">
                        <span v-if="hasErrors">❌ A grade exceeds max score</span>
                        <span v-else>✏️ Edit Mode Active</span>
                    </div>

                    <div v-for="(student, index) in processedStudents" :key="student.id" class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 overflow-hidden shadow-sm">
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
                                <span class="text-[9px] font-black uppercase tracking-widest px-1.5 py-0.5 rounded border shrink-0" 
                                    :class="{
                                        'bg-emerald-100 text-emerald-700 border-emerald-200 dark:bg-emerald-900/40 dark:text-emerald-400 dark:border-emerald-800': student.numericAverage >= 85,
                                        'bg-yellow-100 text-yellow-700 border-yellow-200 dark:bg-yellow-900/40 dark:text-yellow-400 dark:border-yellow-800': student.numericAverage >= 75 && student.numericAverage < 85,
                                        'bg-red-100 text-red-700 border-red-200 dark:bg-red-900/40 dark:text-red-400 dark:border-red-800': student.numericAverage < 75
                                    }">
                                    {{ student.numericAverage }}%
                                </span>
                                <component :is="expandedStudentId === student.id ? ChevronUp : ChevronDown" class="w-4 h-4 text-slate-400" />
                            </div>
                        </button>

                        <div v-show="expandedStudentId === student.id" class="p-2 border-t border-slate-100 dark:border-slate-700 bg-white dark:bg-slate-800 grid grid-cols-2 gap-2">
                            <div v-for="a in assignments" :key="a.id" class="flex flex-col bg-slate-50 dark:bg-slate-900/50 p-2 rounded border border-slate-100 dark:border-slate-700 shadow-sm">
                                
                                <div class="flex justify-between items-start mb-1.5 min-w-0">
                                    <span class="text-[10px] font-bold text-slate-800 dark:text-slate-200 truncate pr-1" :title="a.title">{{ a.title }}</span>
                                    <span class="text-[8px] font-black text-blue-500 uppercase tracking-widest shrink-0">/{{ a.points }}</span>
                                </div>
                                
                                <template v-if="getSubmission(student, a.id)">
                                    <div class="flex items-center gap-1.5 w-full">
                                        <a :href="route('teacher.assignments.show', a.id)" target="_blank" title="View Submission" 
                                           class="text-slate-400 hover:text-blue-500 dark:text-slate-500 dark:hover:text-blue-400 transition bg-white dark:bg-slate-800 p-1 rounded border border-slate-200 dark:border-slate-700 shadow-sm">
                                            <FileText class="w-4 h-4 shrink-0" />
                                        </a>
                                        
                                        <div class="flex-1">
                                            <input 
                                                v-if="isEditMode"
                                                type="number" 
                                                step="0.01" 
                                                min="0" 
                                                :max="a.points"
                                                :value="getInputValue(student.id, a.id)"
                                                @input="updatePendingGrade(student.id, a.id, a.points, $event)"
                                                class="w-full h-7 text-center border focus:ring-2 focus:ring-inset rounded text-[11px] font-black transition-colors shadow-inner px-1"
                                                :class="validationErrors[`${student.id}_${a.id}`] ? 'border-red-500 text-red-600 bg-red-50 focus:ring-red-500 dark:bg-red-900/40 dark:border-red-700 dark:text-red-400' : 'border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 focus:ring-blue-500 text-slate-900 dark:text-white placeholder-slate-300'"
                                                placeholder="Score"
                                            />
                                            <div v-else class="w-full h-7 flex items-center justify-center rounded bg-slate-100 dark:bg-slate-800 text-[11px] font-black text-slate-500 dark:text-slate-400 cursor-not-allowed border border-slate-200 dark:border-slate-700">
                                                {{ getSubmission(student, a.id)?.grade ?? '-' }}
                                            </div>
                                        </div>
                                    </div>
                                </template>

                                <template v-else>
                                    <div v-if="isLateEnrollee(student, a)" class="w-full h-7 flex items-center justify-center gap-1.5 rounded bg-slate-50 dark:bg-slate-800/50 text-slate-400 dark:text-slate-500 text-[9px] font-black uppercase tracking-widest cursor-not-allowed border border-slate-200 dark:border-slate-700" title="Task hidden: The deadline passed before this student enrolled.">
                                        <Clock class="w-3 h-3" /> Late Enrollee
                                    </div>
                                    <div v-else class="w-full h-7 flex items-center justify-center rounded bg-red-50 dark:bg-red-900/20 text-red-400 dark:text-red-500/80 text-[9px] font-black uppercase tracking-widest cursor-not-allowed border border-red-100 dark:border-red-900/30">
                                        Missing
                                    </div>
                                </template>

                            </div>
                            <div v-if="assignments.length === 0" class="text-center py-3 text-slate-400 text-[9px] uppercase font-black tracking-widest col-span-full">No assignments to grade.</div>
                        </div>
                    </div>
                </div>

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
    appearance: none;
    margin: 0; 
}
input[type="number"] { 
    -moz-appearance: textfield; 
    appearance: textfield;
}

@media (prefers-color-scheme: dark) {
    .custom-scrollbar::-webkit-scrollbar-track { background: #0f172a; }
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #334155; border-color: #0f172a; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #475569; }
}
</style>