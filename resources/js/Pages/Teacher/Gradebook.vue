<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import { Download, ChevronDown, ChevronUp, ArrowUpDown, FileText, Clock } from 'lucide-vue-next';
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import * as XLSX from 'xlsx';

const props = defineProps({
    course: Object,
    courses: Array,
    students: Array,
    assignments: Array,
    all_export_data: Array
});

// ==========================================
// NEW: Hidden Courses Sync Logic
// ==========================================
const page = usePage();
const userId = page.props.auth.user.id;
const storageKey = `lms_hidden_courses_${userId}`;
const hiddenCourses = ref(JSON.parse(localStorage.getItem(storageKey)) || []);

// Filter out courses that the teacher has hidden globally
const visibleCourses = computed(() => {
    return props.courses ? props.courses.filter(c => !hiddenCourses.value.includes(c.id)) : [];
});
// ==========================================

const expandedStudentId = ref(null);
const sortOrder = ref('alpha_asc');
const isEditMode = ref(false);
const isSaving = ref(false);
const pendingGrades = ref({});
const validationErrors = ref({}); 

onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    if (params.get('sort')) {
        sortOrder.value = params.get('sort');
    }
});

const hasErrors = computed(() => Object.keys(validationErrors.value).length > 0);

// Helper function to prevent template interpolation bugs in VS Code
const hasValidationError = (studentId, assignmentId) => {
    return Boolean(validationErrors.value[studentId + '_' + assignmentId]);
};

const toggleStudent = (studentId) => {
    expandedStudentId.value = expandedStudentId.value === studentId ? null : studentId;
};

const getSubmission = (student, assignmentId) => {
    if (!student || !student.submissions) return null;
    return student.submissions.find(s => s.assignment_id === assignmentId);
};

// Accurately evaluates late enrollees based on Approval Time (Matching PHP Controller logic)
const isLateEnrollee = (student, assignment) => {
    const desc = assignment.description || '';
    const isHiddenFromLate = desc.includes('[RESTRICT_LATE_STUDENTS]');
    
    if (!isHiddenFromLate) return false; 
    if (!assignment.due_date) return false; 
    if (!student.pivot) return false;
    
    // Uses updated_at (the moment the teacher clicked approve)
    const enrollmentDate = new Date(student.pivot.updated_at || student.pivot.created_at);
    const dueDate = new Date(assignment.due_date);
    
    return enrollmentDate > dueDate;
};

const updatePendingGrade = (studentId, assignmentId, maxPoints, courseId, event) => {
    const val = event.target.value.trim();
    const numericVal = parseFloat(val);
    const max = parseFloat(maxPoints);
    const key = studentId + '_' + assignmentId;

    if (val !== '' && !isNaN(numericVal) && numericVal > max) {
        validationErrors.value[key] = true;
    } else {
        delete validationErrors.value[key];
    }

    pendingGrades.value[key] = {
        student_id: studentId,
        assignment_id: assignmentId,
        course_id: courseId,
        grade: val
    };
};

const getInputValue = (student, assignmentId) => {
    const studentId = typeof student === 'object' ? student.id : student;
    const key = studentId + '_' + assignmentId;
    
    if (pendingGrades.value[key] !== undefined) {
        return pendingGrades.value[key].grade;
    }
    
    if (typeof student === 'object') {
        return getSubmission(student, assignmentId)?.grade ?? '';
    }
    
    const foundStudent = props.students?.find(s => s.id === studentId);
    if (!foundStudent) return '';
    
    return getSubmission(foundStudent, assignmentId)?.grade ?? '';
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
                return axios.post(route('teacher.gradebook.autosave', data.course_id), {
                    student_id: data.student_id,
                    assignment_id: data.assignment_id,
                    grade: gradeVal
                });
            });

            await Promise.all(requests);
            router.reload({ only: ['students', 'all_export_data'] });
            
            pendingGrades.value = {};
            isEditMode.value = false;
        } catch (error) {
            alert('Failed to save some grades. Please check your connection.');
        } finally {
            isSaving.value = false;
        }
    }
};

const maxCategoryPoints = computed(() => {
    let assign = 0, act = 0, pt = 0, total = 0;
    if (props.assignments) {
        props.assignments.forEach(a => {
            const pts = Number(a.points) || 0;
            total += pts;
            if (a.type === 'assignment') assign += pts;
            else if (a.type === 'activity') act += pts;
            else if (a.type === 'performance_task') pt += pts;
        });
    }
    return { assign, act, pt, total };
});

const calculatePS = (score, max) => {
    if (!max || max === 0) return 0;
    return ((score / max) * 100).toFixed(1);
};

const processedStudents = computed(() => {
    if (!props.students) return [];
    
    let list = props.students.map(student => {
        let assignScore = 0, actScore = 0, ptScore = 0, totalScore = 0;
        
        if (props.assignments) {
            props.assignments.forEach(a => {
                const val = getInputValue(student, a.id);
                const pts = (val !== '' && val !== null && !isNaN(val)) ? parseFloat(val) : 0;
                
                totalScore += pts;
                if (a.type === 'assignment') assignScore += pts;
                else if (a.type === 'activity') actScore += pts;
                else if (a.type === 'performance_task') ptScore += pts;
            });
        }

        return {
            ...student,
            assignScore,
            assignPS: calculatePS(assignScore, maxCategoryPoints.value.assign),
            actScore,
            actPS: calculatePS(actScore, maxCategoryPoints.value.act),
            ptScore,
            ptPS: calculatePS(ptScore, maxCategoryPoints.value.pt),
            totalScore,
            numericAverage: maxCategoryPoints.value.total > 0 ? parseFloat(calculatePS(totalScore, maxCategoryPoints.value.total)) : 0
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

// UPDATED: Filter Export Data to exclude hidden courses
const processedAllExportData = computed(() => {
    if (!props.all_export_data) return [];
    
    return props.all_export_data
        .filter(c => !hiddenCourses.value.includes(c.id)) // HIDE IN MEGA VIEW
        .map(c => {
            let sortedStudents = [...c.students].sort((a, b) => {
                if (sortOrder.value === 'alpha_asc') return a.name.localeCompare(b.name);
                if (sortOrder.value === 'alpha_desc') return b.name.localeCompare(a.name);
                if (sortOrder.value === 'avg_desc') return b.percentage - a.percentage;
                if (sortOrder.value === 'avg_asc') return a.percentage - b.percentage;
                return 0;
            });
            return { ...c, students: sortedStudents };
        });
});

const switchCourse = (e) => {
    if (e.target.value) {
        router.visit(route('teacher.gradebook.index', e.target.value));
    }
};

const buildExcelSheet = (title, assignments, students) => {
    let maxAssign = 0, maxAct = 0, maxPt = 0, totalPts = 0;
    
    assignments.forEach(task => {
        const points = Number(task.points) || 0;
        totalPts += points;
        if (task.type === 'assignment') maxAssign += points;
        else if (task.type === 'activity') maxAct += points;
        else if (task.type === 'performance_task') maxPt += points;
    });

    const taskHeaders = assignments.map(a => a.title);
    const taskMaxes = assignments.map(a => String(a.points));

    const wsData = [
        ['OFFICIAL CLASS RECORD'], 
        [], 
        ['Course:', title], 
        [], 
        [
            'Name of Student', 
            ...taskHeaders, 
            'Assign (Raw)', 'Assign PS (%)', 
            'Activities (Raw)', 'Activity PS (%)', 
            'PT (Raw)', 'PT PS (%)', 
            'Total Raw', 'Quarterly Grade (%)'
        ],
        [
            'HIGHEST POSSIBLE SCORE', 
            ...taskMaxes, 
            String(maxAssign), '100%', 
            String(maxAct), '100%', 
            String(maxPt), '100%', 
            String(totalPts), '100%'
        ]
    ];

    if (students.length > 0) {
        const sortedStudents = [...students].sort((a, b) => a.name.localeCompare(b.name));

        sortedStudents.forEach(student => {
            let assignScore = 0, actScore = 0, ptScore = 0, totalScore = 0;
            const row = [student.name];

            assignments.forEach(task => {
                const val = getInputValue(student, task.id);
                const pts = (val !== '' && val !== null && !isNaN(val)) ? parseFloat(val) : 0;
                
                totalScore += pts;
                if (task.type === 'assignment') assignScore += pts;
                else if (task.type === 'activity') actScore += pts;
                else if (task.type === 'performance_task') ptScore += pts;

                row.push((val !== '' && val !== null) ? String(val) : '0');
            });

            const assignPS = maxAssign > 0 ? ((assignScore / maxAssign) * 100).toFixed(1) : '0.0';
            const actPS = maxAct > 0 ? ((actScore / maxAct) * 100).toFixed(1) : '0.0';
            const ptPS = maxPt > 0 ? ((ptScore / maxPt) * 100).toFixed(1) : '0.0';
            const numericAverage = totalPts > 0 ? ((totalScore / totalPts) * 100).toFixed(1) : '0.0';

            row.push(
                String(assignScore), assignPS + '%',
                String(actScore), actPS + '%',
                String(ptScore), ptPS + '%',
                String(totalScore), numericAverage + '%'
            );

            wsData.push(row);
        });
    } else {
        wsData.push(['No students enrolled.']);
    }

    return XLSX.utils.aoa_to_sheet(wsData);
};

const downloadExcel = () => {
    const wb = XLSX.utils.book_new();

    if (props.course && props.course.id === 'all') {
        const exportData = processedAllExportData.value;
        if (!exportData || exportData.length === 0) {
            alert("No data available to export. (Make sure you haven't hidden all your classes!)");
            return;
        }

        exportData.forEach(courseData => {
            const ws = buildExcelSheet(courseData.title, courseData.assignments || [], courseData.students || []);
            let safeSheetName = courseData.title.replace(/[\\\/\?\*\[\]]/g, '').substring(0, 31);
            XLSX.utils.book_append_sheet(wb, ws, safeSheetName);
        });

        XLSX.writeFile(wb, `Complete_Teacher_Gradebook.xlsx`);
    } else {
        if (!props.course || !props.assignments || !props.students) return;
        const ws = buildExcelSheet(props.course.title, props.assignments, processedStudents.value);
        let safeSheetName = props.course.title.replace(/[\\\/\?\*\[\]]/g, '').substring(0, 31);
        XLSX.utils.book_append_sheet(wb, ws, safeSheetName);
        
        XLSX.writeFile(wb, `${props.course.title.replace(/\s+/g, '_')}_Gradebook.xlsx`);
    }
};
</script>

<template>
    <Head :title="course?.id === 'all' ? 'All Courses Gradebook' : (course ? `Gradebook - ${course.title}` : 'Gradebook')" />
    
    <AuthenticatedLayout>
        <div class="max-w-[100vw] mx-auto pb-12 px-2 sm:px-4">
            
            <div v-if="course" class="flex flex-col md:flex-row justify-between md:items-end gap-2 mb-3 sm:mb-4 border-b border-slate-100 dark:border-slate-800 pb-2">
                <div class="w-full md:w-auto">
                    <h1 class="text-lg sm:text-xl font-black text-slate-900 dark:text-white flex flex-wrap items-center gap-1.5 sm:gap-2 leading-tight">
                        Gradebook
                        <!-- UPDATED: Uses visibleCourses -->
                        <select @change="switchCourse" class="text-[10px] sm:text-xs font-bold bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded py-1 pl-2 pr-6 focus:ring-2 focus:ring-blue-500 cursor-pointer shadow-sm transition max-w-[180px] sm:max-w-none truncate">
                            <option value="all" :selected="course.id === 'all'">All Courses</option>
                            <option v-for="c in visibleCourses" :key="c.id" :value="c.id" :selected="c.id === course.id">{{ c.title }}</option>
                        </select>
                    </h1>
                </div>
                
                <div class="flex items-center gap-1.5 w-full md:w-auto shrink-0 flex-wrap">
                    
                    <!-- STUDENT SORT ORDER -->
                    <div class="flex items-center bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded shadow-sm flex-1 md:flex-none">
                        <ArrowUpDown class="w-3 h-3 text-slate-400 ml-1.5 shrink-0" />
                        <select v-model="sortOrder" class="w-full text-[9px] font-black uppercase tracking-widest text-slate-600 dark:text-slate-300 bg-transparent border-none focus:ring-0 cursor-pointer py-1 pl-1 pr-5">
                            <option value="alpha_asc">Student (A-Z)</option>
                            <option value="alpha_desc">Student (Z-A)</option>
                            <option value="avg_desc">Highest Grade</option>
                            <option value="avg_asc">Lowest Grade</option>
                        </select>
                    </div>

                    <button @click="toggleEditMode"
                            :class="isEditMode ? (hasErrors ? 'bg-red-600 hover:bg-red-500 text-white' : 'bg-emerald-600 hover:bg-emerald-500 text-white') : 'bg-blue-600 hover:bg-blue-500 text-white'"
                            class="flex items-center justify-center gap-1 px-2.5 py-1 rounded font-black text-[9px] uppercase tracking-widest shadow-sm transition shrink-0 disabled:opacity-50 flex-1 md:flex-none">
                        <svg v-if="isSaving" class="animate-spin w-3 h-3 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <svg v-else-if="!isEditMode" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                        <svg v-else-if="hasErrors" class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <svg v-else class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        <span class="hidden sm:inline">
                            {{ isSaving ? 'Saving...' : (isEditMode ? (hasErrors ? 'Fix Errors' : 'Save Changes') : 'Edit') }}
                        </span>
                    </button>

                    <button @click="downloadExcel" class="flex items-center justify-center gap-1 bg-slate-800 hover:bg-slate-700 text-white px-2.5 py-1 rounded font-black text-[9px] uppercase tracking-widest shadow-sm transition shrink-0 flex-1 md:flex-none">
                        <Download class="w-3 h-3" /> <span class="hidden sm:inline">Export</span>
                    </button>
                </div>
            </div>

            <!-- VIEW: ALL COURSES SELECTED (MEGA VIEW) -->
            <div v-if="course && course.id === 'all'" class="space-y-4 sm:space-y-6 mt-3 sm:mt-4">
                <div v-if="processedAllExportData.length === 0" class="text-center py-16 text-slate-500 font-bold uppercase tracking-widest text-xs">
                    You have hidden all your active classes. Unhide them to view their gradebooks.
                </div>
                
                <div v-for="c in processedAllExportData" :key="c.id" class="bg-white dark:bg-slate-800 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
                    
                    <!-- Mega View Class Header -->
                    <div class="px-3 py-2 sm:px-4 sm:py-2.5 border-b border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/80 flex justify-between items-center">
                        <h3 class="font-black text-slate-800 dark:text-slate-200 uppercase tracking-widest text-[10px] sm:text-xs flex items-center gap-1.5">
                            <div class="w-1.5 h-1.5 rounded-full bg-blue-500"></div>
                            {{ c.title }}
                        </h3>
                    </div>
                    
                    <!-- DESKTOP MEGA VIEW TABLE -->
                    <div class="hidden md:block overflow-x-auto custom-scrollbar">
                        <table class="w-full text-left whitespace-nowrap">
                            <thead class="bg-slate-50/50 dark:bg-slate-900/40 border-b border-slate-200 dark:border-slate-700 uppercase text-[8px] font-black text-slate-500 tracking-widest">
                                <tr>
                                    <th class="px-2 py-1.5 sticky left-0 bg-slate-50/90 dark:bg-slate-900/90 backdrop-blur-sm border-r border-slate-200 dark:border-slate-700 z-10 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.05)] w-32 sm:w-48">Student Name</th>
                                    
                                    <!-- COMPACT INDIVIDUAL ASSIGNMENTS -->
                                    <th v-for="a in c.assignments" :key="a.id" class="px-1.5 py-1.5 min-w-[70px] max-w-[100px] border-r border-slate-200 dark:border-slate-700 text-center bg-white dark:bg-slate-800">
                                        <span class="block truncate text-[9px] text-slate-700 dark:text-slate-300" :title="a.title">{{ a.title }}</span>
                                        <span class="text-[7px] text-blue-600 dark:text-blue-400 mt-0.5 block">Max {{ a.points }}</span>
                                    </th>

                                    <th class="px-1.5 py-1.5 min-w-[70px] border-r border-slate-200 dark:border-slate-700 text-center bg-blue-50/30 dark:bg-blue-900/10">
                                        <span class="block text-blue-800 dark:text-blue-300">Assign</span>
                                        <span class="text-[7px] text-blue-600 dark:text-blue-400 mt-0.5 block">Max {{ c.max_assignment }}</span>
                                    </th>
                                    <th class="px-1.5 py-1.5 min-w-[70px] border-r border-slate-200 dark:border-slate-700 text-center bg-purple-50/30 dark:bg-purple-900/10">
                                        <span class="block text-purple-800 dark:text-purple-300">Act.</span>
                                        <span class="text-[7px] text-purple-600 dark:text-purple-400 mt-0.5 block">Max {{ c.max_activity }}</span>
                                    </th>
                                    <th class="px-1.5 py-1.5 min-w-[70px] border-r border-slate-200 dark:border-slate-700 text-center bg-orange-50/30 dark:bg-orange-900/10">
                                        <span class="block text-orange-800 dark:text-orange-300">PT</span>
                                        <span class="text-[7px] text-orange-600 dark:text-orange-400 mt-0.5 block">Max {{ c.max_pt }}</span>
                                    </th>
                                    <th class="px-2 py-1.5 min-w-[70px] border-l border-emerald-200 dark:border-emerald-800 text-center bg-emerald-50/40 dark:bg-emerald-900/20 text-emerald-800 dark:text-emerald-400">
                                        <span class="block">Overall</span>
                                        <span class="text-[7px] text-emerald-600 dark:text-emerald-400 mt-0.5 block">Max {{ c.total_points }}</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                <tr v-for="(student, index) in c.students" :key="student.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                                    <td class="px-2 py-1 font-bold text-[10px] text-slate-900 dark:text-white sticky left-0 bg-white dark:bg-slate-800 border-r border-slate-200 dark:border-slate-700 z-10 truncate max-w-[8rem] sm:max-w-[12rem] shadow-[2px_0_5px_-2px_rgba(0,0,0,0.05)] flex items-center gap-1.5">
                                        <div class="w-4 h-4 rounded-full bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 flex items-center justify-center text-[7px] shrink-0">
                                            {{ index + 1 }}
                                        </div>
                                        <span class="truncate">{{ student.name }}</span>
                                    </td>
                                    <td v-for="a in c.assignments" :key="a.id" class="px-1 py-1 border-r border-slate-100 dark:border-slate-800 relative" :class="!isEditMode ? 'bg-slate-50/50 dark:bg-slate-900/20' : ''">
                                        <template v-if="getSubmission(student, a.id)">
                                            <div class="flex items-center justify-center gap-1">
                                                <a :href="route('teacher.assignments.show', a.id)" target="_blank" title="View Submission"
                                                    class="text-slate-400 hover:text-blue-500 dark:text-slate-500 dark:hover:text-blue-400 transition ml-0.5">
                                                    <FileText class="w-3 h-3 shrink-0" />
                                                </a>
                                                <div class="flex-1 px-0.5">
                                                    <template v-if="isEditMode">
                                                        <input 
                                                            type="number" 
                                                            step="0.01" 
                                                            min="0" 
                                                            :max="a.points"
                                                            :value="getInputValue(student, a.id)"
                                                            @input="updatePendingGrade(student.id, a.id, a.points, c.id, $event)"
                                                            class="w-full text-center border-0 bg-transparent focus:ring-1 focus:ring-inset rounded text-[10px] font-bold transition-colors py-0.5 px-0 h-5"
                                                            :class="hasValidationError(student.id, a.id) ? 'text-red-600 focus:ring-red-500 bg-red-50 dark:bg-red-900/40 dark:text-red-400' : 'text-slate-700 dark:text-slate-200 focus:ring-blue-500 placeholder-slate-300 dark:placeholder-slate-600'"
                                                            placeholder="-"
                                                        />
                                                    </template>
                                                    <template v-else>
                                                        <div class="w-full text-center text-[10px] font-bold text-slate-500 dark:text-slate-400 cursor-not-allowed">
                                                            {{ getSubmission(student, a.id)?.grade ?? '-' }}
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>
                                        </template>
                                        <template v-else>
                                            <div class="w-full text-center text-[7px] font-black text-red-400 dark:text-red-500/80 uppercase tracking-widest cursor-not-allowed" title="No submission found">
                                                Missing
                                            </div>
                                        </template>
                                    </td>
                                    <td class="px-1.5 py-1 text-center border-r border-slate-200 dark:border-slate-700 bg-blue-50/10 dark:bg-blue-900/5">
                                        <div class="font-black text-blue-600 dark:text-blue-400 text-[10px]">{{ student.assignment_score > 0 ? ((student.assignment_score / c.max_assignment) * 100).toFixed(1) : 0 }}%</div>
                                        <div class="font-bold text-slate-500 dark:text-slate-400 text-[7px] mt-0.5">{{ student.assignment_score }} Raw</div>
                                    </td>
                                    <td class="px-1.5 py-1 text-center border-r border-slate-200 dark:border-slate-700 bg-purple-50/10 dark:bg-purple-900/5">
                                        <div class="font-black text-purple-600 dark:text-purple-400 text-[10px]">{{ student.activity_score > 0 ? ((student.activity_score / c.max_activity) * 100).toFixed(1) : 0 }}%</div>
                                        <div class="font-bold text-slate-500 dark:text-slate-400 text-[7px] mt-0.5">{{ student.activity_score }} Raw</div>
                                    </td>
                                    <td class="px-1.5 py-1 text-center border-r border-slate-200 dark:border-slate-700 bg-orange-50/30 dark:bg-orange-900/10">
                                        <div class="font-black text-orange-600 dark:text-orange-400 text-[10px]">{{ student.pt_score > 0 ? ((student.pt_score / c.max_pt) * 100).toFixed(1) : 0 }}%</div>
                                        <div class="font-bold text-slate-500 dark:text-slate-400 text-[7px] mt-0.5">{{ student.pt_score }} Raw</div>
                                    </td>
                                    <td class="px-2 py-1 text-center border-l border-emerald-200 dark:border-emerald-800 bg-emerald-50/40 dark:bg-emerald-900/20 text-emerald-800 dark:text-emerald-400">
                                        <span class="text-[10px] font-black block" :class="student.percentage >= 75 ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'">
                                            {{ student.percentage }}%
                                        </span>
                                        <span class="font-bold text-slate-500 dark:text-slate-400 text-[7px] mt-0.5 block">{{ student.total_score }} Raw</span>
                                    </td>
                                </tr>
                                <tr v-if="c.students.length === 0">
                                    <td :colspan="5 + c.assignments.length" class="p-4 text-center text-[9px] text-slate-400 uppercase font-black tracking-widest bg-slate-50/30 dark:bg-slate-800/30">No students enrolled.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- MOBILE MEGA VIEW ACCORDION -->
                    <div class="md:hidden flex flex-col gap-1 p-1 bg-slate-50/50 dark:bg-slate-900/30">
                        <div v-if="isEditMode" class="p-1 text-center text-[9px] font-black uppercase tracking-widest rounded border shadow-sm mb-0.5 transition-colors" 
                             :class="hasErrors ? 'bg-red-50 text-red-800 border-red-200 dark:bg-red-900/40 dark:text-red-400 dark:border-red-800' : 'bg-blue-50 text-blue-800 border-blue-200 dark:bg-blue-900/30 dark:text-blue-400 dark:border-blue-800'">
                            <span v-if="hasErrors">Error: Exceeds max score</span>
                            <span v-else>Edit Mode Active</span>
                        </div>

                        <div v-for="(student, index) in c.students" :key="student.id" class="bg-white dark:bg-slate-800 rounded-md border border-slate-200 dark:border-slate-700 overflow-hidden shadow-sm">
                            <button @click="toggleStudent(student.id + '-' + c.id)" class="w-full flex items-center justify-between px-2 py-1.5 focus:outline-none transition-colors hover:bg-slate-50 dark:hover:bg-slate-700/50">
                                <div class="flex items-center gap-1.5 min-w-0 pr-2">
                                    <div class="w-4 h-4 rounded bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 flex items-center justify-center text-[7px] font-black shrink-0">
                                        {{ index + 1 }}
                                    </div>
                                    <span class="block text-[10px] font-black text-slate-900 dark:text-white truncate">{{ student.name }}</span>
                                </div>
                                <div class="flex items-center gap-1.5 shrink-0">
                                    <span class="text-[8px] font-black uppercase tracking-widest px-1 py-0.5 rounded border shrink-0"
                                         :class="student.percentage >= 75 ? 'bg-emerald-100 text-emerald-700 border-emerald-200 dark:bg-emerald-900/40 dark:text-emerald-400 dark:border-emerald-800' : 'bg-red-100 text-red-700 border-red-200 dark:bg-red-900/40 dark:text-red-400 dark:border-red-800'">
                                        {{ student.percentage }}%
                                    </span>
                                    <component :is="expandedStudentId === (student.id + '-' + c.id) ? ChevronUp : ChevronDown" class="w-3 h-3 text-slate-400" />
                                </div>
                            </button>
                            
                            <div v-show="expandedStudentId === (student.id + '-' + c.id)" class="p-1.5 border-t border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/50">
                                
                                <div v-for="a in c.assignments" :key="a.id" class="flex items-center justify-between bg-white dark:bg-slate-800 p-1.5 rounded border border-slate-100 dark:border-slate-700 shadow-sm mb-1.5">
                                     <div class="flex flex-col min-w-0 flex-1 pr-1">
                                         <div class="flex items-center gap-1 min-w-0">
                                             <span class="text-[9px] font-bold text-slate-800 dark:text-slate-200 truncate" :title="a.title">{{ a.title }}</span>
                                             <span class="text-[7px] font-black text-blue-500 uppercase tracking-widest shrink-0">Max {{ a.points }}</span>
                                         </div>
                                     </div>
                                      
                                     <div class="flex items-center gap-1 w-auto shrink-0">
                                         <template v-if="getSubmission(student, a.id)">
                                             <a :href="route('teacher.assignments.show', a.id)" target="_blank" title="View Submission"
                                                 class="text-slate-400 hover:text-blue-500 dark:text-slate-500 dark:hover:text-blue-400 transition bg-slate-50 dark:bg-slate-900 p-0.5 rounded border border-slate-200 dark:border-slate-700 shadow-sm">
                                                 <FileText class="w-3 h-3" />
                                             </a>
                                              
                                             <div class="w-12 shrink-0">
                                                 <input 
                                                      v-if="isEditMode"
                                                     type="number" 
                                                      step="0.01" 
                                                      min="0" 
                                                      :max="a.points"
                                                     :value="getInputValue(student, a.id)"
                                                     @input="updatePendingGrade(student.id, a.id, a.points, c.id, $event)"
                                                     class="w-full h-5 text-center border focus:ring-1 focus:ring-inset rounded text-[9px] font-black transition-colors py-0 px-1 shadow-inner"
                                                     :class="hasValidationError(student.id, a.id) ? 'border-red-500 text-red-600 bg-red-50 focus:ring-red-500 dark:bg-red-900/40 dark:border-red-700 dark:text-red-400' : 'border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 focus:ring-blue-500 text-slate-900 dark:text-white placeholder-slate-300'"
                                                     placeholder="-"
                                                 />
                                                 <div v-else class="w-full h-5 flex items-center justify-center rounded bg-slate-100 dark:bg-slate-800 text-[9px] font-black text-slate-500 dark:text-slate-400 cursor-not-allowed border border-slate-200 dark:border-slate-700">
                                                     {{ getSubmission(student, a.id)?.grade ?? '-' }}
                                                 </div>
                                             </div>
                                         </template>
                                         <template v-else>
                                             <div class="h-5 w-12 flex items-center justify-center rounded bg-red-50 dark:bg-red-900/20 text-red-400 dark:text-red-500/80 text-[7px] font-black uppercase tracking-widest cursor-not-allowed border border-red-100 dark:border-red-900/30">
                                                 Missing
                                             </div>
                                         </template>
                                     </div>
                                </div>

                                <div class="grid grid-cols-3 gap-1.5">
                                    <div class="text-center bg-white dark:bg-slate-800 p-1.5 rounded border border-blue-100 dark:border-blue-800/30">
                                        <span class="block text-[7px] font-black uppercase text-blue-500">Assign</span>
                                        <span class="block text-[10px] font-black text-blue-600 dark:text-blue-400 mt-0.5">{{ student.assignment_score > 0 ? ((student.assignment_score / c.max_assignment) * 100).toFixed(1) : 0 }}%</span>
                                        <span class="block text-[7px] font-bold text-slate-500 mt-0.5">{{ student.assignment_score }}/{{ c.max_assignment }}</span>
                                    </div>
                                    <div class="text-center bg-white dark:bg-slate-800 p-1.5 rounded border border-purple-100 dark:border-purple-800/30">
                                        <span class="block text-[7px] font-black uppercase text-purple-500">Act.</span>
                                        <span class="block text-[10px] font-black text-purple-600 dark:text-purple-400 mt-0.5">{{ student.actPS }}%</span>
                                        <span class="block text-[7px] font-bold text-slate-500 mt-0.5">{{ student.activity_score }}/{{ c.max_activity }}</span>
                                    </div>
                                    <div class="text-center bg-white dark:bg-slate-800 p-1.5 rounded border border-orange-100 dark:border-orange-800/30">
                                        <span class="block text-[7px] font-black uppercase text-orange-500">PT.</span>
                                        <span class="block text-[10px] font-black text-orange-600 dark:text-orange-400 mt-0.5">{{ student.pt_score > 0 ? ((student.pt_score / c.max_pt) * 100).toFixed(1) : 0 }}%</span>
                                        <span class="block text-[7px] font-bold text-slate-500 mt-0.5">{{ student.pt_score }}/{{ c.max_pt }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-if="c.students.length === 0" class="text-center p-3 text-[9px] text-slate-400 uppercase font-black tracking-widest">
                            No students enrolled.
                        </div>
                    </div>
                </div>
            </div>

            <!-- VIEW: SINGLE COURSE SELECTED -->
            <div v-else-if="course && processedStudents.length > 0">
                
                <!-- DESKTOP SINGLE COURSE TABLE -->
                <div class="hidden md:block bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-x-auto custom-scrollbar relative">
                    
                    <div v-if="isEditMode" class="p-1.5 text-center text-[10px] font-black uppercase tracking-widest border-b transition-colors" 
                         :class="hasErrors ? 'bg-red-50 text-red-800 border-red-200 dark:bg-red-900/40 dark:text-red-400 dark:border-red-800' : 'bg-blue-50 text-blue-800 border-blue-200 dark:bg-blue-900/30 dark:text-blue-400 dark:border-blue-800'">
                        <span v-if="hasErrors">Cannot Save: A grade exceeds the maximum score!</span>
                        <span v-else>Edit Mode Active: Click "Save Changes" when finished.</span>
                    </div>

                    <table class="w-full text-left whitespace-nowrap">
                        <thead class="bg-slate-50 dark:bg-slate-900/80 border-b border-slate-200 dark:border-slate-700 uppercase text-[8px] font-black text-slate-500 tracking-widest">
                            <tr>
                                <th class="px-2 py-1.5 sticky left-0 bg-slate-50 dark:bg-slate-900 border-r border-slate-200 dark:border-slate-700 z-10 w-32 sm:w-48 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.05)]">
                                    Student Name
                                </th>

                                <!-- COMPACT INDIVIDUAL ASSIGNMENTS -->
                                <th v-for="a in assignments" :key="a.id" class="px-1.5 py-1.5 min-w-[70px] max-w-[100px] border-r border-slate-200 dark:border-slate-700 text-center bg-white dark:bg-slate-800">
                                    <span class="block truncate text-[9px] text-slate-700 dark:text-slate-300" :title="a.title">{{ a.title }}</span>
                                    <span class="text-[7px] text-blue-600 dark:text-blue-400 mt-0.5 block">Max {{ a.points }}</span>
                                </th>
                                
                                <!-- COMPACT CATEGORY SUMMARIES -->
                                <th class="px-1.5 py-1.5 min-w-[70px] border-r border-slate-200 dark:border-slate-700 text-center bg-blue-50/50 dark:bg-blue-900/20">
                                    <span class="block text-blue-800 dark:text-blue-300">Assign</span>
                                    <span class="text-[7px] text-blue-600 dark:text-blue-400 mt-0.5 block">Max {{ maxCategoryPoints.assign }}</span>
                                </th>
                                <th class="px-1.5 py-1.5 min-w-[70px] border-r border-slate-200 dark:border-slate-700 text-center bg-purple-50/50 dark:bg-purple-900/20">
                                    <span class="block text-purple-800 dark:text-purple-300">Act.</span>
                                    <span class="text-[7px] text-purple-600 dark:text-purple-400 mt-0.5 block">Max {{ maxCategoryPoints.act }}</span>
                                </th>
                                <th class="px-1.5 py-1.5 min-w-[70px] border-r border-slate-200 dark:border-slate-700 text-center bg-orange-50/50 dark:bg-orange-900/20">
                                    <span class="block text-orange-800 dark:text-orange-300">PT.</span>
                                    <span class="text-[7px] text-orange-600 dark:text-orange-400 mt-0.5 block">Max {{ maxCategoryPoints.pt }}</span>
                                </th>
                                <th class="px-2 py-1.5 min-w-[70px] border-l border-emerald-200 dark:border-emerald-800 text-center bg-emerald-50/40 dark:bg-emerald-900/20 text-emerald-800 dark:text-emerald-400">
                                    <span class="block">Overall</span>
                                    <span class="text-[7px] text-emerald-600 dark:text-emerald-400 mt-0.5 block">Max {{ maxCategoryPoints.total }}</span>
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            <tr v-for="(student, index) in processedStudents" :key="student.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                                
                                <td class="px-2 py-1.5 font-bold text-[10px] text-slate-900 dark:text-white sticky left-0 bg-white dark:bg-slate-800 border-r border-slate-200 dark:border-slate-700 z-10 truncate max-w-[8rem] sm:max-w-[12rem] shadow-[2px_0_5px_-2px_rgba(0,0,0,0.05)] flex items-center gap-1.5">
                                    <div class="w-4 h-4 rounded-full bg-blue-100 dark:bg-blue-900/50 text-blue-600 dark:text-blue-400 flex items-center justify-center text-[7px] shrink-0">
                                        {{ index + 1 }}
                                    </div>
                                    <span class="truncate">{{ student.name }}</span>
                                </td>
                                
                                <td v-for="a in assignments" :key="a.id" class="px-1 py-1 border-r border-slate-100 dark:border-slate-800 relative" :class="!isEditMode ? 'bg-slate-50/50 dark:bg-slate-900/20' : ''">
                                    <template v-if="getSubmission(student, a.id)">
                                        <div class="flex items-center justify-center gap-1">
                                            <a :href="route('teacher.assignments.show', a.id)" target="_blank" title="View Submission"
                                                class="text-slate-400 hover:text-blue-500 dark:text-slate-500 dark:hover:text-blue-400 transition ml-0.5">
                                                <FileText class="w-3 h-3 shrink-0" />
                                            </a>
                                            <div class="flex-1 px-0.5">
                                                <template v-if="isEditMode">
                                                    <input 
                                                         type="number" 
                                                         step="0.01" 
                                                         min="0" 
                                                         :max="a.points"
                                                        :value="getInputValue(student, a.id)"
                                                        @input="updatePendingGrade(student.id, a.id, a.points, course.id, $event)"
                                                        class="w-full text-center border-0 bg-transparent focus:ring-1 focus:ring-inset rounded text-[10px] font-bold transition-colors py-0.5 px-0 h-5"
                                                        :class="hasValidationError(student.id, a.id) ? 'text-red-600 focus:ring-red-500 bg-red-50 dark:bg-red-900/40 dark:text-red-400' : 'text-slate-700 dark:text-slate-200 focus:ring-blue-500 placeholder-slate-300 dark:placeholder-slate-600'"
                                                        placeholder="-"
                                                    />
                                                </template>
                                                <template v-else>
                                                    <div class="w-full text-center text-[10px] font-bold text-slate-500 dark:text-slate-400 cursor-not-allowed">
                                                        {{ getSubmission(student, a.id)?.grade ?? '-' }}
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </template>
                                    
                                    <template v-else>
                                        <div v-if="isLateEnrollee(student, a)" class="w-full text-center flex items-center justify-center gap-0.5 text-[6px] font-black text-slate-500 dark:text-slate-400 bg-slate-100 dark:bg-slate-800/50 rounded uppercase tracking-widest cursor-not-allowed mx-0.5 border border-slate-200 dark:border-slate-700" title="Task hidden: The deadline passed before this student enrolled.">
                                            <Clock class="w-2 h-2" /> Late
                                        </div>
                                        <div v-else class="w-full text-center text-[7px] font-black text-red-400 dark:text-red-500/80 uppercase tracking-widest cursor-not-allowed" title="No submission found">
                                            Missing
                                        </div>
                                    </template>
                                </td>

                                <!-- COMPACT CATEGORY CELLS -->
                                <td class="px-1.5 py-1 text-center border-r border-slate-200 dark:border-slate-700 bg-blue-50/20 dark:bg-blue-900/10">
                                    <div class="font-black text-blue-600 dark:text-blue-400 text-[10px]">{{ student.assignPS }}%</div>
                                    <div class="font-bold text-slate-500 dark:text-slate-400 text-[7px] mt-0.5">{{ student.assignScore }} Raw</div>
                                </td>
                                <td class="px-1.5 py-1 text-center border-r border-slate-200 dark:border-slate-700 bg-purple-50/20 dark:bg-purple-900/10">
                                    <div class="font-black text-purple-600 dark:text-purple-400 text-[10px]">{{ student.actPS }}%</div>
                                    <div class="font-bold text-slate-500 dark:text-slate-400 text-[7px] mt-0.5">{{ student.actScore }} Raw</div>
                                </td>
                                <td class="px-1.5 py-1 text-center border-r border-slate-200 dark:border-slate-700 bg-orange-50/20 dark:bg-orange-900/10">
                                    <div class="font-black text-orange-600 dark:text-orange-400 text-[10px]">{{ student.ptPS }}%</div>
                                    <div class="font-bold text-slate-500 dark:text-slate-400 text-[7px] mt-0.5">{{ student.ptScore }} Raw</div>
                                </td>
                                
                                <td class="px-2 py-1 text-center border-l border-emerald-200 dark:border-emerald-800 bg-emerald-50/40 dark:bg-emerald-900/20">
                                    <span class="text-[10px] font-black block"
                                         :class="{
                                            'text-emerald-600 dark:text-emerald-400': student.numericAverage >= 85,
                                            'text-yellow-600 dark:text-yellow-400': student.numericAverage >= 75 && student.numericAverage < 85,
                                            'text-red-600 dark:text-red-400': student.numericAverage < 75
                                        }">
                                        {{ student.numericAverage }}%
                                    </span>
                                    <span class="font-bold text-slate-500 dark:text-slate-400 text-[7px] mt-0.5 block">{{ student.totalScore }} Raw</span>
                                </td>
                                
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- MOBILE SINGLE COURSE VIEW (Ultra Compact Accordion) -->
                <div class="md:hidden flex flex-col gap-1.5">
                    
                    <div v-if="isEditMode" class="p-1.5 text-center text-[9px] font-black uppercase tracking-widest rounded border shadow-sm mb-1 transition-colors" 
                         :class="hasErrors ? 'bg-red-50 text-red-800 border-red-200 dark:bg-red-900/40 dark:text-red-400 dark:border-red-800' : 'bg-blue-50 text-blue-800 border-blue-200 dark:bg-blue-900/30 dark:text-blue-400 dark:border-blue-800'">
                        <span v-if="hasErrors">Error: Exceeds max score</span>
                        <span v-else>Edit Mode Active</span>
                    </div>

                    <div v-for="(student, index) in processedStudents" :key="student.id" class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 overflow-hidden shadow-sm">
                        <button @click="toggleStudent(student.id)" class="w-full flex items-center justify-between px-2 py-1.5 bg-slate-50/50 dark:bg-slate-900/30 transition-colors focus:outline-none">
                            <div class="flex items-center gap-1.5 min-w-0 pr-2">
                                <div class="w-4 h-4 rounded bg-blue-100 dark:bg-blue-900/50 text-blue-600 dark:text-blue-400 flex items-center justify-center text-[7px] font-black shrink-0">
                                    {{ index + 1 }}
                                </div>
                                <div class="text-left min-w-0">
                                    <span class="block text-[10px] font-black text-slate-900 dark:text-white truncate">{{ student.name }}</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-1.5 shrink-0">
                                <span class="text-[8px] font-black uppercase tracking-widest px-1 py-0.5 rounded border shrink-0"
                                     :class="{
                                        'bg-emerald-100 text-emerald-700 border-emerald-200 dark:bg-emerald-900/40 dark:text-emerald-400 dark:border-emerald-800': student.numericAverage >= 85,
                                        'bg-yellow-100 text-yellow-700 border-yellow-200 dark:bg-yellow-900/40 dark:text-yellow-400 dark:border-yellow-800': student.numericAverage >= 75 && student.numericAverage < 85,
                                        'bg-red-100 text-red-700 border-red-200 dark:bg-red-900/40 dark:text-red-400 dark:border-red-800': student.numericAverage < 75
                                    }">
                                    {{ student.numericAverage }}%
                                </span>
                                <component :is="expandedStudentId === student.id ? ChevronUp : ChevronDown" class="w-3 h-3 text-slate-400" />
                            </div>
                        </button>
                        
                        <div v-show="expandedStudentId === student.id" class="p-1.5 border-t border-slate-100 dark:border-slate-700 bg-white dark:bg-slate-800 grid grid-cols-1 sm:grid-cols-2 gap-1.5">
                            
                            <div v-for="a in assignments" :key="a.id" class="flex items-center justify-between bg-slate-50 dark:bg-slate-900/50 p-1.5 rounded border border-slate-100 dark:border-slate-700 shadow-sm">
                                
                                <div class="flex flex-col min-w-0 flex-1 pr-1">
                                    <div class="flex items-center gap-1 min-w-0">
                                        <span class="text-[9px] font-bold text-slate-800 dark:text-slate-200 truncate" :title="a.title">{{ a.title }}</span>
                                        <span class="text-[7px] font-black text-blue-500 uppercase tracking-widest shrink-0">Max {{ a.points }}</span>
                                    </div>
                                </div>
                                 
                                <div class="flex items-center gap-1 w-auto shrink-0">
                                    <template v-if="getSubmission(student, a.id)">
                                        <a :href="route('teacher.assignments.show', a.id)" target="_blank" title="View Submission"
                                            class="text-slate-400 hover:text-blue-500 dark:text-slate-500 dark:hover:text-blue-400 transition bg-white dark:bg-slate-800 p-0.5 rounded border border-slate-200 dark:border-slate-700 shadow-sm">
                                            <FileText class="w-3 h-3" />
                                        </a>
                                         
                                        <div class="w-12 shrink-0">
                                            <input 
                                                 v-if="isEditMode"
                                                type="number" 
                                                 step="0.01" 
                                                 min="0" 
                                                 :max="a.points"
                                                :value="getInputValue(student, a.id)"
                                                @input="updatePendingGrade(student.id, a.id, a.points, course.id, $event)"
                                                class="w-full h-5 text-center border focus:ring-1 focus:ring-inset rounded text-[9px] font-black transition-colors py-0 px-1 shadow-inner"
                                                :class="hasValidationError(student.id, a.id) ? 'border-red-500 text-red-600 bg-red-50 focus:ring-red-500 dark:bg-red-900/40 dark:border-red-700 dark:text-red-400' : 'border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 focus:ring-blue-500 text-slate-900 dark:text-white placeholder-slate-300'"
                                                placeholder="-"
                                            />
                                            <div v-else class="w-full h-5 flex items-center justify-center rounded bg-slate-100 dark:bg-slate-800 text-[9px] font-black text-slate-500 dark:text-slate-400 cursor-not-allowed border border-slate-200 dark:border-slate-700">
                                                {{ getSubmission(student, a.id)?.grade ?? '-' }}
                                            </div>
                                        </div>
                                    </template>
                                    <template v-else>
                                        <div v-if="isLateEnrollee(student, a)" class="h-5 px-1.5 flex items-center justify-center gap-0.5 rounded bg-slate-50 dark:bg-slate-800/50 text-slate-400 dark:text-slate-500 text-[7px] font-black uppercase tracking-widest cursor-not-allowed border border-slate-200 dark:border-slate-700">
                                            <Clock class="w-2 h-2" /> Late
                                        </div>
                                        <div v-else class="h-5 w-12 flex items-center justify-center rounded bg-red-50 dark:bg-red-900/20 text-red-400 dark:text-red-500/80 text-[7px] font-black uppercase tracking-widest cursor-not-allowed border border-red-100 dark:border-red-900/30">
                                            Missing
                                        </div>
                                    </template>
                                </div>
                            </div>

                            <div class="col-span-1 sm:col-span-2 grid grid-cols-3 gap-1.5 mt-1 pt-1 border-t border-slate-100 dark:border-slate-700">
                                <div class="text-center bg-blue-50/50 dark:bg-blue-900/10 p-1.5 rounded border border-blue-100 dark:border-blue-800/30">
                                    <span class="block text-[7px] font-black uppercase text-blue-500">Ass. PS</span>
                                    <span class="block text-[10px] font-black text-blue-600 dark:text-blue-400">{{ student.assignPS }}%</span>
                                </div>
                                <div class="text-center bg-purple-50/50 dark:bg-purple-900/10 p-1.5 rounded border border-purple-100 dark:border-purple-800/30">
                                    <span class="block text-[7px] font-black uppercase text-purple-500">Act. PS</span>
                                    <span class="block text-[10px] font-black text-purple-600 dark:text-purple-400">{{ student.actPS }}%</span>
                                </div>
                                <div class="text-center bg-orange-50/50 dark:bg-orange-900/10 p-1.5 rounded border border-orange-100 dark:border-orange-800/30">
                                    <span class="block text-[7px] font-black uppercase text-orange-500">PT. PS</span>
                                    <span class="block text-[10px] font-black text-orange-600 dark:text-orange-400">{{ student.ptPS }}%</span>
                                </div>
                            </div>
                            
                            <div v-if="assignments.length === 0" class="text-center py-2 text-slate-400 text-[8px] uppercase font-black tracking-widest col-span-1 sm:col-span-2">No assignments to grade.</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FALLBACK IF NO DATA IS AVAILABLE -->
            <div v-else-if="course && (!course.id || course.id !== 'all')" class="mt-6 bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm p-8 text-center flex flex-col items-center justify-center">
                <h3 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-tight">No Data Available</h3>
                <p class="text-[10px] text-slate-500 mt-1 uppercase font-bold tracking-widest">There are currently no students or assignments to display.</p>
            </div>
            
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { height: 6px; }
.custom-scrollbar::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 6px; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 6px; }
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
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #334155; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #475569; }
}
</style>