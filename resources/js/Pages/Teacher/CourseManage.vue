<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Modal from '@/Components/Modal.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import RichTextEditor from '@/Components/RichTextEditor.vue';
import { Eye, Download } from 'lucide-vue-next';

const props = defineProps({ course: Object });
const page = usePage();
const currentUser = page.props.auth.user;

const requireApproval = computed(() => page.props.requireApproval ?? true);

const activeTab = ref('students');
const studentSubTab = ref('accepted');
const studentSort = ref('alpha_asc'); 
const assignmentFilter = ref('upcoming');

const showLessonModal = ref(false);
const showAnnouncementModal = ref(false);
const showAssignmentModal = ref(false);

const showResubmitModal = ref(false);
const lessonToResubmit = ref(null);

const showUnarchiveModal = ref(false);
const lessonToUnarchive = ref(null);

const formResubmit = useForm({ 
    file: null,
    available_from: '',
    available_until: '' 
});

const formUnarchive = useForm({
    available_from: '',
    available_until: ''
});

const formatForInput = (dateStr) => {
    if (!dateStr) return '';
    const d = new Date(dateStr);
    if (isNaN(d.getTime())) return '';
    return new Date(d.getTime() - (d.getTimezoneOffset() * 60000)).toISOString().slice(0, 16);
};

const openResubmitModal = (lesson) => {
    lessonToResubmit.value = lesson;
    formResubmit.available_from = formatForInput(lesson.available_from) || formatForInput(new Date());
    formResubmit.available_until = formatForInput(lesson.available_until);
    formResubmit.file = null;
    formResubmit.clearErrors();
    showResubmitModal.value = true;
};

const openUnarchiveModal = (lesson) => {
    lessonToUnarchive.value = lesson;
    formUnarchive.available_from = formatForInput(lesson.available_from) || formatForInput(new Date());
    formUnarchive.available_until = '';
    formUnarchive.clearErrors();
    showUnarchiveModal.value = true;
};

const submitResubmit = () => {
    formResubmit.post(route('teacher.lessons.resubmit', lessonToResubmit.value.id), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => { showResubmitModal.value = false; lessonToResubmit.value = null; }
    });
};

const submitUnarchive = () => {
    formUnarchive.patch(route('teacher.lessons.unarchive', lessonToUnarchive.value.id), {
        preserveScroll: true,
        onSuccess: () => { showUnarchiveModal.value = false; lessonToUnarchive.value = null; }
    });
};

const formLesson = useForm({ title: '', file: null, available_from: '', available_until: '' });
const formAnnouncement = useForm({ title: '', video_link: '', content: '' });

// 🪄 ADDED: hide_from_late default state
const formAssignment = useForm({ 
    title: '', 
    type: 'assignment', 
    description: '', 
    points: 100, 
    due_date: '', 
    closing_date: '', 
    files: [],
    hide_from_late: false 
});

// The 3 Material Tabs Filter Logic
const materialFilter = ref('active');

const activeMaterials = computed(() => {
    const now = new Date();
    return props.course.lessons?.filter(l => 
        l.approval_status !== 'rejected' && 
        (l.approval_status !== 'approved' || (!l.available_until || new Date(l.available_until) > now))
    ) || [];
});

const rejectedMaterials = computed(() => {
    return props.course.lessons?.filter(l => l.approval_status === 'rejected') || [];
});

const archivedMaterials = computed(() => {
    const now = new Date();
    return props.course.lessons?.filter(l => l.approval_status === 'approved' && l.available_until && new Date(l.available_until) <= now) || [];
});

const displayedMaterials = computed(() => {
    if (materialFilter.value === 'active') return activeMaterials.value;
    if (materialFilter.value === 'rejected') return rejectedMaterials.value;
    return archivedMaterials.value;
});

const getYouTubeVideoId = (url) => {
    if (!url) return null;
    const regExp = /^.*(youtu\.be\/|v\/|u\/\w\/|embed\/|watch\?v=|&v=|shorts\/)([^#&?]*).*/;
    const match = url.match(regExp);
    return (match && match[2].length === 11) ? match[2] : null;
};

const formatRichText = (htmlContent) => {
    if (!htmlContent) return '';
    
    let processed = htmlContent.replace(/<iframe/gi, '<iframe class="hidden sm:block w-full max-w-[320px] sm:max-w-[400px] aspect-video rounded-lg shadow-sm my-3 border border-slate-200 dark:border-slate-700"');

    // Step B: Convert pasted YouTube links into a split Desktop/Mobile layout
    const ytRegex = /(?<!href="|src=")(?:https?:\/\/)?(?:www\.)?(?:youtube\.com\/(?:[^\/\n\s]+\/\S+\/|(?:v|e(?:mbed)?)\/|\S*?[?&]v=|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})(?=[^\w-]|$)/gi;
    
    processed = processed.replace(ytRegex, (match, videoId) => {
        return `
        <div class="my-3 w-full max-w-[320px] sm:max-w-[400px] not-prose">
            <iframe class="hidden sm:block w-full aspect-video rounded-lg shadow-sm border border-slate-200 dark:border-slate-700" src="https://www.youtube-nocookie.com/embed/${videoId}" frameborder="0" allowfullscreen></iframe>
            
            <a href="https://www.youtube.com/watch?v=${videoId}" target="_blank" class="sm:hidden relative flex flex-col items-center justify-center w-full aspect-video bg-slate-900 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 transition group !no-underline overflow-hidden">
                <img src="https://img.youtube.com/vi/${videoId}/hqdefault.jpg" class="absolute inset-0 w-full h-full object-cover opacity-70 group-hover:opacity-90 transition-opacity" alt="Video Thumbnail" />
                <div class="absolute inset-0 bg-black/30 group-hover:bg-black/10 transition-colors"></div>
                <div class="relative z-10 w-10 h-10 bg-red-600 rounded-full flex items-center justify-center mb-2 group-hover:scale-110 transition-transform shadow-md">
                    <svg class="w-4 h-4 text-white ml-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                </div>
                <span class="relative z-10 text-[9px] font-black uppercase tracking-widest text-white drop-shadow-md">Watch on YouTube</span>
            </a>
        </div>`;
    });

    return processed;
};

const pendingStudents = computed(() => props.course.enrollments ? props.course.enrollments.filter(e => e.status === 'pending' && e.user) : []);
const approvedStudentsRaw = computed(() => props.course.enrollments ? props.course.enrollments.filter(e => e.status === 'approved' && e.user) : []);

const processedStudents = computed(() => {
    if (!approvedStudentsRaw.value.length) return [];
    let studentsWithScores = approvedStudentsRaw.value.map(enrollment => {
        let totalScore = 0;
        let totalPossible = 0;
        if (props.course.assignments) {
            props.course.assignments.forEach(assignment => {
                totalPossible += parseInt(assignment.points || 0);
                const sub = assignment.submissions ? assignment.submissions.find(s => s.user_id === enrollment.user_id) : null;
                if (sub && sub.grade !== null) totalScore += parseFloat(sub.grade);
            });
        }
        return { ...enrollment, totalScore, totalPossible, percentage: totalPossible > 0 ? ((totalScore / totalPossible) * 100).toFixed(1) : 0 };
    });
    let sortedForRank = [...studentsWithScores].sort((a, b) => b.totalScore - a.totalScore);
    sortedForRank.forEach((s, index) => s.rank = index + 1);
    return studentsWithScores.sort((a, b) => {
        if (studentSort.value === 'alpha_asc') return a.user.name.localeCompare(b.user.name);
        if (studentSort.value === 'rank_desc') return b.totalScore - a.totalScore; 
        if (studentSort.value === 'rank_asc') return a.totalScore - b.totalScore; 
        return 0;
    });
});

const getRankClass = (rank) => {
    if (rank === 1) return 'bg-yellow-100 text-yellow-700 border-yellow-300 border shadow-sm'; 
    if (rank === 2) return 'bg-slate-200 text-slate-700 border-slate-300 border shadow-sm'; 
    if (rank === 3) return 'bg-orange-100 text-orange-800 border-orange-300 border shadow-sm'; 
    return 'bg-blue-50 text-blue-600 border border-blue-100'; 
};

const filteredAssignments = computed(() => {
    const now = new Date();
    if (!props.course.assignments) return [];
    return props.course.assignments.filter(a => {
        const dueDate = a.due_date ? new Date(a.due_date) : null;
        const isPastDue = dueDate && dueDate < now;
        if (assignmentFilter.value === 'upcoming') return !isPastDue;
        if (assignmentFilter.value === 'past_due') return isPastDue;
        if (assignmentFilter.value === 'completed') return isPastDue; 
        return true;
    });
});

const approveStudent = (userId) => router.patch(route('teacher.courses.enrollments.approve', { course: props.course.id, user: userId }), {}, { preserveScroll: true });
const removeStudent = (userId) => { if(confirm('Remove this student from the class?')) router.delete(route('teacher.courses.enrollments.destroy', { course: props.course.id, user: userId }), { preserveScroll: true }); };
const toggleComments = (announcement) => { announcement.showComments = !announcement.showComments; };
const copyCode = () => { navigator.clipboard.writeText(props.course.enrollment_code); alert('Course Code Copied!'); };

const deleteItem = (url, skipConfirm = false) => { 
    if (skipConfirm || confirm('Are you sure you want to delete this?')) router.delete(url, { preserveScroll: true }); 
};

const togglePublish = () => {
    if (confirm(`Are you sure you want to ${props.course.is_published ? 'hide this course from' : 'publish this course for'} students?`)) {
        router.patch(route('teacher.courses.toggle-publish', props.course.id), {}, { preserveScroll: true });
    }
};

const submitAnnouncement = () => formAnnouncement.post(route('teacher.announcements.store', props.course.id), { onSuccess: () => { showAnnouncementModal.value = false; formAnnouncement.reset(); }});
const submitComment = (announcementId) => formComment.post(route('comments.store', announcementId), { onSuccess: () => { formComment.reset(); const post = props.course.announcements.find(a => a.id === announcementId); if(post) post.showComments = true; }, preserveScroll: true });

// 🪄 ADDED: The Secret Tag Logic
const submitAssignment = () => {
    formAssignment.transform((data) => ({
        ...data,
        description: data.hide_from_late 
            ? (data.description ? data.description + '\n[RESTRICT_LATE_STUDENTS]' : '[RESTRICT_LATE_STUDENTS]')
            : data.description,
    })).post(route('teacher.assignments.store', props.course.id), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => { 
            showAssignmentModal.value = false; 
            formAssignment.reset(); 
        }
    });
};

const submitLesson = () => formLesson.post(route('teacher.lessons.store', props.course.id), { forceFormData: true, onSuccess: () => { showLessonModal.value = false; formLesson.reset(); }});

const inputClass = "w-full rounded-md bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:ring-2 focus:ring-blue-500 focus:border-transparent py-1.5 px-3 text-base sm:text-xs h-10 sm:h-8 shadow-sm transition-colors duration-200";
</script>

<template>
    <Head :title="`Manage: ${course.title}`" />

    <AuthenticatedLayout>
        
        <div class="mb-4 flex justify-between items-center max-w-7xl mx-auto px-4 sm:px-6">
             <div class="flex items-center gap-3 flex-wrap">
                 <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-lg sm:text-xl font-bold text-slate-900 dark:text-white leading-tight">{{ course.title }}</h1>
                        <span v-if="currentUser.role === 'admin'" class="bg-red-600 text-white text-[8px] font-black uppercase tracking-widest px-2 py-0.5 rounded animate-pulse shadow-sm">
                            Admin Override
                        </span>
                    </div>
                    <div class="flex items-center gap-2 mt-0.5">
                        <p class="text-slate-500 dark:text-slate-400 text-[9px] uppercase font-bold tracking-wider">Class Code:</p>
                        <span class="text-[9px] font-mono bg-slate-200 dark:bg-slate-700 px-1.5 py-0.5 rounded text-slate-700 dark:text-slate-300 cursor-pointer hover:bg-slate-300 transition shadow-sm" @click="copyCode" title="Click to copy code">{{ course.enrollment_code }}</span>
                    </div>
                 </div>
             </div>
             
             <div class="flex items-center gap-2">
                 <button @click="togglePublish" 
                         class="text-[10px] font-black uppercase tracking-widest px-3 py-1.5 rounded transition shadow-sm border flex items-center gap-1.5"
                         :class="course.is_published ? 'bg-emerald-50 text-emerald-700 border-emerald-200 hover:bg-emerald-100 dark:bg-emerald-900/30 dark:border-emerald-800 dark:text-emerald-400' : 'bg-slate-50 text-slate-500 border-slate-200 hover:bg-slate-100 dark:bg-slate-800 dark:border-slate-700'">
                     <span v-if="course.is_published" class="flex items-center gap-1.5">
                         <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse shadow-[0_0_5px_rgba(16,185,129,0.8)]"></span> LIVE
                     </span>
                     <span v-else class="flex items-center gap-1.5">
                         <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span> DRAFT
                     </span>
                 </button>

                 <Link :href="route('teacher.courses.edit', { course: course.id, source: 'manage' })" class="text-[10px] font-black uppercase tracking-widest text-blue-600 hover:text-blue-800 bg-blue-50 hover:bg-blue-100 dark:bg-blue-900/30 dark:text-blue-400 px-3 py-1.5 rounded transition shadow-sm border border-blue-100 dark:border-blue-800">
                     Settings
                 </Link>
             </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 flex flex-col md:flex-row gap-4 md:gap-6 items-start pb-[300px] md:pb-6">
            
            <aside class="fixed bottom-[156px] right-4 z-[45] flex flex-col gap-3 md:relative md:bottom-auto md:right-auto md:z-10 md:w-12 md:sticky md:top-6 order-2 md:order-1 transition-all duration-300">
                
                <button @click="showAnnouncementModal = true" class="group relative flex items-center justify-center w-12 h-12 bg-white dark:bg-slate-800 rounded-full border border-slate-200 dark:border-slate-700 text-red-600 hover:text-white hover:bg-red-600 hover:border-red-600 transition-all shadow-[0_8px_30px_rgb(0,0,0,0.12)] hover:shadow-xl focus:outline-none shrink-0 md:shadow-sm md:hover:shadow-md">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path></svg>
                    <span class="hidden md:block absolute left-full ml-3 px-2 py-1 bg-slate-800 text-white text-[10px] font-bold rounded opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none whitespace-nowrap shadow-lg">Post Announcement</span>
                </button>

                <button @click="showAssignmentModal = true" class="group relative flex items-center justify-center w-12 h-12 bg-white dark:bg-slate-800 rounded-full border border-slate-200 dark:border-slate-700 text-blue-600 hover:text-white hover:bg-blue-600 hover:border-blue-600 transition-all shadow-[0_8px_30px_rgb(0,0,0,0.12)] hover:shadow-xl focus:outline-none shrink-0 md:shadow-sm md:hover:shadow-md">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    <span class="hidden md:block absolute left-full ml-3 px-2 py-1 bg-slate-800 text-white text-[10px] font-bold rounded opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none whitespace-nowrap shadow-lg">Create Task</span>
                </button>

                <button @click="showLessonModal = true" class="group relative flex items-center justify-center w-12 h-12 bg-white dark:bg-slate-800 rounded-full border border-slate-200 dark:border-slate-700 text-yellow-600 hover:text-white hover:bg-yellow-500 hover:border-yellow-500 transition-all shadow-[0_8px_30px_rgb(0,0,0,0.12)] hover:shadow-xl focus:outline-none shrink-0 md:shadow-sm md:hover:shadow-md">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                    <span class="hidden md:block absolute left-full ml-3 px-2 py-1 bg-slate-800 text-white text-[10px] font-bold rounded opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none whitespace-nowrap shadow-lg">Upload Materials</span>
                </button>

            </aside>

            <div class="flex-1 min-w-0 w-full order-1 md:order-2">
                
                <div class="flex gap-4 border-b border-slate-200 dark:border-slate-700 mb-4 overflow-x-auto no-scrollbar">
                    <button @click="activeTab = 'students'" class="pb-1.5 text-xs sm:text-sm font-bold border-b-2 transition-colors flex items-center gap-1.5 whitespace-nowrap" :class="activeTab === 'students' ? 'border-blue-600 text-blue-600 dark:text-blue-400 dark:border-blue-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300'">
                        Students <span v-if="pendingStudents.length" class="bg-red-500 text-white text-[8px] px-1.5 py-0.5 rounded-full shadow-sm animate-pulse">{{ pendingStudents.length }}</span>
                    </button>
                    <button @click="activeTab = 'announcements'" class="pb-1.5 text-xs sm:text-sm font-bold border-b-2 transition-colors whitespace-nowrap" :class="activeTab === 'announcements' ? 'border-blue-600 text-blue-600 dark:text-blue-400 dark:border-blue-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300'">Announcements</button>
                    <button @click="activeTab = 'assignments'" class="pb-1.5 text-xs sm:text-sm font-bold border-b-2 transition-colors whitespace-nowrap" :class="activeTab === 'assignments' ? 'border-blue-600 text-blue-600 dark:text-blue-400 dark:border-blue-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300'">Tasks</button>
                    <button @click="activeTab = 'materials'" class="pb-1.5 text-xs sm:text-sm font-bold border-b-2 transition-colors whitespace-nowrap" :class="activeTab === 'materials' ? 'border-blue-600 text-blue-600 dark:text-blue-400 dark:border-blue-400' : 'border-transparent text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-300'">Materials</button>
                </div>

                <div v-if="activeTab === 'students'" class="space-y-3">
                    
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 mb-2">
                        <div class="flex flex-wrap gap-1.5">
                            <button @click="studentSubTab = 'accepted'" :class="[ studentSubTab === 'accepted' ? 'bg-blue-100 text-blue-800 border-blue-200 dark:bg-blue-900/40 dark:text-blue-300 dark:border-blue-800/50' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50 dark:bg-slate-800/50 dark:text-slate-400 dark:border-slate-700/50' ]" class="px-3 py-1 text-[10px] font-bold rounded-full transition-all border shadow-sm">
                                Accepted ({{ processedStudents.length }})
                            </button>
                            <button @click="studentSubTab = 'pending'" :class="[ studentSubTab === 'pending' ? 'bg-yellow-100 text-yellow-800 border-yellow-200 dark:bg-yellow-900/40 dark:text-yellow-300 dark:border-yellow-800/50' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50 dark:bg-slate-800/50 dark:text-slate-400 dark:border-slate-700/50' ]" class="px-3 py-1 text-[10px] font-bold rounded-full transition-all border shadow-sm flex items-center gap-1">
                                Pending <span v-if="pendingStudents.length > 0" class="bg-red-500 text-white text-[8px] px-1 rounded-full animate-pulse">{{ pendingStudents.length }}</span>
                            </button>
                            <button 
                                @click="studentSubTab = 'ranking'" 
                                class="px-3 py-1 text-[10px] font-bold rounded-full transition-all border shadow-sm flex items-center gap-1"
                                :class="studentSubTab === 'ranking' ? 'bg-orange-100 text-orange-800 border-orange-200 dark:bg-orange-900/40 dark:text-orange-300 dark:border-orange-800/50' : 'bg-white text-slate-600 border border-slate-200 hover:bg-slate-50 dark:bg-slate-800/50 dark:text-slate-400 dark:border-slate-700/50'">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 9V2h12v7M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2M6 18a2 2 0 002 2h8a2 2 0 002-2M6 18V9m12 9V9M12 11a3 3 0 100 6 3 3 0 000-6z"></path>
                                </svg>
                                Leaderboard
                            </button>
                        </div>

                        <div v-if="studentSubTab !== 'pending'" class="flex items-center gap-2 w-full sm:w-auto">
                            <label class="text-[9px] font-bold text-slate-500 uppercase tracking-wider whitespace-nowrap">Sort By:</label>
                            <select v-model="studentSort" class="h-7 py-0 pl-2 pr-6 rounded-md bg-white dark:bg-slate-900 border border-slate-300 dark:border-slate-700 text-slate-700 dark:text-slate-300 text-[10px] font-bold uppercase tracking-wider focus:ring-2 focus:ring-blue-500 focus:border-transparent shadow-sm cursor-pointer transition-colors w-full sm:w-auto">
                                <option value="alpha_asc">Alphabetical (A - Z)</option>
                                <option value="rank_desc">Rank (1 to Bottom)</option>
                                <option value="rank_asc">Rank (Bottom to 1)</option>
                            </select>
                        </div>
                    </div>

                    <div v-if="studentSubTab === 'accepted'">
                        <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
                            <div v-if="processedStudents.length > 0" class="overflow-x-auto no-scrollbar">
                                <table class="w-full text-left text-[11px] sm:text-xs text-slate-500 dark:text-slate-400">
                                    <thead class="text-[9px] uppercase font-bold text-slate-400 bg-slate-50 dark:bg-slate-900/30 border-b border-slate-100 dark:border-slate-700">
                                        <tr>
                                            <th class="px-2 py-2">Student Name</th>
                                            <th class="px-2 py-2 text-center">Rank</th>
                                            <th class="px-2 py-2 text-right">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                                        <tr v-for="enrollment in processedStudents" :key="enrollment.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition">
                                            <td class="px-2 py-1.5 flex items-center gap-2">
                                                <div class="w-6 h-6 rounded bg-blue-100 dark:bg-blue-900/50 text-blue-600 dark:text-blue-300 flex items-center justify-center font-bold text-[9px] shrink-0">{{ enrollment.user.name.charAt(0) }}</div>
                                                <div class="min-w-0">
                                                    <div class="font-bold text-slate-900 dark:text-white truncate max-w-[150px] sm:max-w-xs">{{ enrollment.user.name }}</div>
                                                    <div class="text-[9px] text-slate-400 truncate">{{ enrollment.user.school_id || 'No ID' }}</div>
                                                </div>
                                            </td>
                                            <td class="px-2 py-1.5 text-center align-middle font-black" :class="enrollment.rank <= 3 ? 'text-yellow-600' : 'text-slate-500'">#{{ enrollment.rank }}</td>
                                            <td class="px-2 py-1.5 text-right align-middle">
                                                <button @click="removeStudent(enrollment.user_id)" class="text-red-500 hover:text-red-700 text-[9px] font-bold bg-red-50 dark:bg-red-900/20 px-2 py-1 rounded border border-red-100 dark:border-red-900/30 transition shadow-sm">Remove</button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div v-else class="text-center py-8 text-slate-400 text-[10px] italic">
                                No students enrolled yet. Share the code: <span class="font-mono font-bold text-slate-600 dark:text-slate-300">{{ course.enrollment_code }}</span>
                            </div>
                        </div>
                    </div>

                    <div v-if="studentSubTab === 'ranking'" class="space-y-2">
                        <div v-if="processedStudents.length > 0">
                            <div v-for="student in processedStudents" :key="student.id" class="flex items-center justify-between bg-white dark:bg-slate-800 p-2 sm:p-3 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm transition hover:border-orange-300">
                                <div class="flex items-center gap-2 sm:gap-3">
                                    <div class="w-6 h-6 sm:w-8 sm:h-8 rounded-full flex items-center justify-center font-black text-[10px] sm:text-xs" :class="getRankClass(student.rank)">
                                        #{{ student.rank }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-[11px] sm:text-sm font-bold text-slate-900 dark:text-white leading-none mb-0.5 sm:mb-1 truncate max-w-[120px] sm:max-w-xs">{{ student.user.name }}</p>
                                        <p class="text-[8px] sm:text-[9px] font-bold text-slate-500 uppercase tracking-wider truncate">{{ student.user.email }}</p>
                                    </div>
                                </div>
                                <div class="text-right shrink-0">
                                    <div class="font-black text-sm sm:text-lg text-slate-800 dark:text-slate-200 leading-none">{{ student.totalScore }} <span class="text-[8px] sm:text-[9px] font-bold text-slate-400 uppercase">Pts</span></div>
                                    <div class="text-[9px] sm:text-[10px] font-bold mt-0.5" :class="student.percentage > 75 ? 'text-emerald-500' : 'text-slate-500'">{{ student.percentage }}% Avg</div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-8 bg-slate-50 dark:bg-slate-900/30 border border-dashed border-slate-200 dark:border-slate-700 rounded-xl text-slate-400 text-[10px] font-black uppercase tracking-widest">No data to rank yet.</div>
                    </div>

                    <div v-if="studentSubTab === 'pending'">
                        <div v-if="pendingStudents.length > 0" class="space-y-2">
                            <div v-for="enrollment in pendingStudents" :key="enrollment.id" class="flex items-center justify-between bg-white dark:bg-slate-800 p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm">
                                <div class="flex items-center gap-2 min-w-0">
                                    <div class="w-7 h-7 rounded bg-yellow-100 dark:bg-yellow-900/50 text-yellow-600 dark:text-yellow-400 flex items-center justify-center font-bold text-[10px] shrink-0">{{ enrollment.user.name.charAt(0) }}</div>
                                    <div class="leading-tight min-w-0">
                                        <p class="text-[11px] sm:text-xs font-bold text-slate-900 dark:text-white truncate">{{ enrollment.user.name }}</p>
                                        <p class="text-[9px] text-slate-500 truncate">{{ enrollment.user.email }}</p>
                                    </div>
                                </div>
                                <div class="flex gap-1.5 shrink-0">
                                    <button @click="removeStudent(enrollment.user_id)" class="text-[9px] font-black uppercase tracking-widest text-red-600 bg-red-50 hover:bg-red-100 dark:bg-red-900/20 dark:hover:bg-red-900/40 border border-red-100 dark:border-red-800/30 px-3 py-1.5 rounded shadow-sm transition">Reject</button>
                                    <button @click="approveStudent(enrollment.user_id)" class="text-[9px] font-black uppercase tracking-widest text-white bg-blue-600 hover:bg-blue-500 px-3 py-1.5 rounded shadow-sm transition">Accept</button>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-8 bg-slate-50 dark:bg-slate-900/30 rounded-xl border border-dashed border-slate-200 dark:border-slate-700 text-slate-400 text-[10px] font-black uppercase tracking-widest shadow-sm">
                            No pending requests.
                        </div>
                    </div>
                </div>

                <div v-if="activeTab === 'announcements'" class="space-y-4 w-full">
                    <div v-for="post in course.announcements" :key="post.id" class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden hover:border-red-300 dark:hover:border-red-500 transition-colors duration-200">
                        
                        <div class="p-2.5 flex justify-between items-center border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/30">
                            <div class="flex items-center gap-2">
                                <div class="w-6 h-6 shrink-0 rounded bg-red-100 dark:bg-red-900/50 text-red-600 dark:text-red-400 flex items-center justify-center text-[10px] font-bold">{{ post.user?.name?.charAt(0) || 'D' }}</div>
                                <div class="leading-tight">
                                    <h4 class="text-[11px] font-bold text-slate-900 dark:text-white">{{ post.user?.name || 'Deleted User' }}</h4>
                                    <p class="text-[9px] text-slate-500 mt-0.5">{{ new Date(post.created_at).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' }) }}</p>
                                </div>
                            </div>
                            <button @click="deleteItem(route('teacher.announcements.destroy', post.id))" class="text-slate-400 hover:text-red-500 bg-white hover:bg-red-50 dark:bg-slate-800 dark:hover:bg-red-900/20 p-1.5 rounded transition shadow-sm border border-slate-100 dark:border-slate-700" title="Delete post">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                        </div>
                        
                        <div class="p-3 space-y-2">
                            <h3 v-if="post.title" class="text-[13px] sm:text-sm font-bold text-slate-900 dark:text-white">{{ post.title }}</h3>
                            
                            <div v-if="post.video_link" class="w-full max-w-[320px] sm:max-w-[400px] mb-3">
                                <template v-if="getYouTubeVideoId(post.video_link)">
                                    <iframe 
                                        class="hidden sm:block w-full aspect-video rounded-lg shadow-sm border border-slate-200 dark:border-slate-700"
                                        :src="`https://www.youtube-nocookie.com/embed/${getYouTubeVideoId(post.video_link)}`" 
                                        frameborder="0" 
                                        allowfullscreen>
                                    </iframe>
                                    <a :href="post.video_link" target="_blank" class="sm:hidden relative flex flex-col items-center justify-center w-full aspect-video bg-slate-900 rounded-lg shadow-sm border border-slate-200 dark:border-slate-700 transition group !no-underline overflow-hidden">
                                        <img :src="`https://img.youtube.com/vi/${getYouTubeVideoId(post.video_link)}/hqdefault.jpg`" class="absolute inset-0 w-full h-full object-cover opacity-70 group-hover:opacity-90 transition-opacity" alt="Video Thumbnail" />
                                        <div class="absolute inset-0 bg-black/30 group-hover:bg-black/10 transition-colors"></div>
                                        <div class="relative z-10 w-10 h-10 bg-red-600 rounded-full flex items-center justify-center mb-2 group-hover:scale-110 transition-transform shadow-md">
                                            <svg class="w-4 h-4 text-white ml-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                        </div>
                                        <span class="relative z-10 text-[9px] font-black uppercase tracking-widest text-white drop-shadow-md">Watch on YouTube</span>
                                    </a>
                                </template>
                                <a v-else :href="post.video_link" target="_blank" class="relative flex flex-col items-center justify-center w-full aspect-video bg-gradient-to-br from-slate-800 to-slate-900 rounded-lg shadow-sm border border-slate-700 transition group !no-underline overflow-hidden">
                                    <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_center,_var(--tw-gradient-stops))] from-white to-transparent"></div>
                                    <div class="relative z-10 w-10 h-10 bg-white/20 backdrop-blur-md rounded-full flex items-center justify-center mb-2 group-hover:scale-110 group-hover:bg-blue-600 transition-all shadow-lg border border-white/30 group-hover:border-blue-500">
                                        <svg class="w-4 h-4 text-white ml-0.5" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                    </div>
                                    <span class="relative z-10 text-[9px] font-black uppercase tracking-widest text-white drop-shadow-md text-center px-4 truncate w-full">Open External Video</span>
                                </a>
                            </div>

                            <div class="prose dark:prose-invert max-w-none prose-p:my-1 prose-a:text-blue-600 prose-a:font-bold hover:prose-a:underline text-[11px] sm:text-xs text-slate-800 dark:text-slate-200 leading-relaxed announcement-content" v-html="formatRichText(post.content)"></div>
                        </div>
                        
                        <div class="px-3 py-1.5 flex items-center gap-2 border-t border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-900/30">
                            <button @click="toggleComments(post)" class="text-[9px] font-black uppercase tracking-widest text-slate-500 hover:text-red-600 flex items-center gap-1 transition">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"></path></svg>
                                Comments <span v-if="post.comments.length" class="ml-0.5 text-[9px] bg-red-100 text-red-700 dark:bg-red-900/50 dark:text-red-400 px-1.5 py-0.5 rounded-full">{{ post.comments.length }}</span>
                            </button>
                        </div>

                        <div v-if="post.showComments" class="bg-slate-50 dark:bg-slate-900/50 p-2.5 border-t border-slate-100 dark:border-slate-700">
                            <div v-if="post.comments.length > 0" class="space-y-1.5 mb-2">
                                <div v-for="comment in post.comments" :key="comment.id" class="flex gap-1.5 group">
                                    <div class="w-5 h-5 shrink-0 rounded bg-slate-200 dark:bg-slate-700 flex items-center justify-center text-[8px] font-bold text-slate-600 dark:text-slate-300">{{ comment.user?.name?.charAt(0) || 'D' }}</div>
                                    <div class="bg-white dark:bg-slate-800 px-2 py-1.5 rounded border border-slate-200 dark:border-slate-700 flex-1 relative shadow-sm leading-tight">
                                        <div class="flex justify-between items-start mb-0.5">
                                            <span class="font-bold text-slate-900 dark:text-white text-[9px]">{{ comment.user?.name || 'Deleted User' }}</span>
                                            <button @click="deleteItem(route('comments.destroy', comment.id))" class="text-slate-300 hover:text-red-500 opacity-0 group-hover:opacity-100 transition-opacity p-0.5">
                                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                            </button>
                                        </div>
                                        <span class="text-[10px] text-slate-700 dark:text-slate-300 block">{{ comment.content }}</span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex gap-1.5 items-center">
                                <div class="w-5 h-5 shrink-0 rounded bg-red-600 flex items-center justify-center text-white text-[8px] font-bold">{{ currentUser.name.charAt(0) }}</div>
                                <div class="flex-1 relative">
                                    <input v-model="formComment.content" @keyup.enter="submitComment(post.id)" type="text" placeholder="Write a reply..." class="w-full bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-600 rounded py-1 px-2 text-[10px] focus:ring-1 focus:ring-red-500 h-7 pr-7 shadow-sm transition" />
                                    <button @click="submitComment(post.id)" class="absolute right-0.5 top-0.5 text-red-600 hover:text-red-500 bg-red-50 hover:bg-red-100 dark:bg-red-900/20 dark:hover:bg-red-900/40 p-1 rounded transition">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div v-if="course.announcements.length === 0" class="text-center py-8 bg-slate-50 dark:bg-slate-900/30 border border-dashed border-slate-200 dark:border-slate-700 rounded-xl">
                        <p class="text-slate-400 dark:text-slate-500 text-[10px] font-black uppercase tracking-widest">No announcements yet. Click the red icon to post.</p>
                    </div>
                </div>

                <div v-if="activeTab === 'assignments'" class="space-y-3 w-full">
                    
                    <div class="flex p-1 bg-slate-100 dark:bg-slate-800 rounded-lg gap-1 mb-2">
                        <button @click="assignmentFilter = 'upcoming'" class="flex-1 py-1.5 text-[10px] font-black uppercase tracking-widest rounded-md flex items-center justify-center gap-1 transition-all" :class="assignmentFilter === 'upcoming' ? 'bg-white dark:bg-slate-700 text-blue-600 shadow-sm' : 'text-slate-500 hover:text-slate-700'">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg Upcoming>
                        </button>
                        <button @click="assignmentFilter = 'past_due'" class="flex-1 py-1.5 text-[10px] font-black uppercase tracking-widest rounded-md flex items-center justify-center gap-1 transition-all" :class="assignmentFilter === 'past_due' ? 'bg-white dark:bg-slate-700 text-red-600 shadow-sm' : 'text-slate-500 hover:text-slate-700'">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> Past Due
                        </button>
                        <button @click="assignmentFilter = 'completed'" class="flex-1 py-1.5 text-[10px] font-black uppercase tracking-widest rounded-md flex items-center justify-center gap-1 transition-all" :class="assignmentFilter === 'completed' ? 'bg-white dark:bg-slate-700 text-emerald-600 shadow-sm' : 'text-slate-500 hover:text-slate-700'">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> Completed
                        </button>
                    </div>

                    <div v-if="filteredAssignments.length > 0" class="space-y-2">
                        <Link v-for="assignment in filteredAssignments" :key="assignment.id" 
                              :href="route('teacher.assignments.show', { assignment: assignment.id, source: 'course' })"
                              class="group bg-white dark:bg-slate-800 rounded-xl p-3 border border-slate-200 dark:border-slate-700 hover:border-blue-600 dark:hover:border-blue-500 shadow-sm transition-all duration-200 flex items-center gap-3">
                            <div class="shrink-0 p-2.5 rounded bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h4 class="text-[11px] sm:text-xs font-bold text-slate-900 dark:text-white truncate group-hover:text-blue-600 dark:group-hover:text-blue-400 transition">{{ assignment.title }}</h4>
                                <div class="flex flex-wrap items-center gap-2 mt-1">
                                    <span class="text-[8px] font-black text-slate-500 uppercase bg-slate-100 dark:bg-slate-700 px-1.5 py-0.5 rounded">{{ assignment.type.replace('_', ' ') }}</span>
                                    <span class="text-[9px] font-bold text-slate-500 dark:text-slate-400 bg-slate-100 dark:bg-slate-700 px-1.5 py-0.5 rounded">Due: {{ assignment.due_date ? new Date(assignment.due_date).toLocaleDateString() : 'No Date' }}</span>
                                    <span class="text-[9px] font-black text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-900/20 px-1.5 py-0.5 rounded border border-blue-100 dark:border-blue-800">{{ assignment.points }} pts</span>
                                </div>
                            </div>
                        </Link>
                    </div>
                    <div v-else class="text-center py-8 bg-slate-50 dark:bg-slate-900/30 border border-dashed border-slate-200 dark:border-slate-700 rounded-xl">
                        <p class="text-slate-400 dark:text-slate-500 text-[10px] font-black uppercase tracking-widest">No {{ assignmentFilter.replace('_', ' ') }} assignments.</p>
                    </div>
                </div>

                <div v-if="activeTab === 'materials'" class="space-y-4 w-full">
                    
                    <div class="flex p-1 bg-slate-100 dark:bg-slate-800 rounded-lg gap-1 mb-2">
                        <button @click="materialFilter = 'active'" 
                            class="flex-1 py-1.5 text-[10px] font-black uppercase tracking-widest rounded-md transition-all"
                            :class="materialFilter === 'active' ? 'bg-white dark:bg-slate-700 text-blue-600 shadow-sm' : 'text-slate-500 hover:text-slate-700'">
                            Active
                        </button>
                        <button @click="materialFilter = 'rejected'" 
                            class="flex-1 py-1.5 text-[10px] font-black uppercase tracking-widest rounded-md transition-all flex items-center justify-center gap-1.5"
                            :class="materialFilter === 'rejected' ? 'bg-white dark:bg-slate-700 text-red-600 shadow-sm' : 'text-slate-500 hover:text-slate-700'">
                            Rejected <span v-if="rejectedMaterials.length" class="bg-red-100 text-red-600 dark:bg-red-900/50 dark:text-red-400 px-1.5 py-0.5 rounded-full text-[8px]">{{ rejectedMaterials.length }}</span>
                        </button>
                        <button @click="materialFilter = 'archived'" 
                            class="flex-1 py-1.5 text-[10px] font-black uppercase tracking-widest rounded-md transition-all flex items-center justify-center gap-1.5"
                            :class="materialFilter === 'archived' ? 'bg-white dark:bg-slate-700 text-slate-800 dark:text-white shadow-sm' : 'text-slate-500 hover:text-slate-700'">
                            Archive <span v-if="archivedMaterials.length" class="bg-slate-200 text-slate-600 dark:bg-slate-900/40 dark:text-slate-400 px-1.5 py-0.5 rounded-full text-[8px]">{{ archivedMaterials.length }}</span>
                        </button>
                    </div>

                    <div v-if="displayedMaterials.length > 0" class="divide-y divide-slate-100 dark:divide-slate-700 bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-sm">
                        <div v-for="lesson in displayedMaterials" :key="lesson.id" class="flex flex-col sm:flex-row sm:items-center justify-between p-3 sm:p-4 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition group gap-3">
                            <div class="flex items-center gap-3 sm:gap-4 min-w-0 w-full sm:w-auto">
                                <div class="shrink-0 p-2.5 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg text-yellow-600 dark:text-yellow-400">
                                    <svg class="w-4 h-4 sm:w-5 sm:h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                                </div>
                                <div class="flex flex-col min-w-0 w-full">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="text-[11px] sm:text-xs font-bold text-slate-900 dark:text-white truncate">{{ lesson.title }}</span>
                                        
                                        <span v-if="materialFilter === 'archived'" class="text-[8px] bg-slate-100 text-slate-600 border border-slate-200 px-1.5 py-0.5 rounded uppercase font-black tracking-widest shrink-0">Archived</span>
                                        <span v-else-if="lesson.approval_status === 'pending' && requireApproval" class="text-[8px] bg-orange-100 text-orange-700 border border-orange-200 px-1.5 py-0.5 rounded uppercase font-black tracking-widest shrink-0">Pending Admin</span>
                                        <span v-else-if="lesson.approval_status === 'rejected'" class="text-[8px] bg-red-100 text-red-700 border border-red-200 px-1.5 py-0.5 rounded uppercase font-black tracking-widest shrink-0">Rejected</span>
                                        <span v-else-if="lesson.approval_status === 'approved'" class="text-[8px] bg-emerald-100 text-emerald-700 border border-emerald-200 px-1.5 py-0.5 rounded uppercase font-black tracking-widest shrink-0">Approved</span>
                                    </div>

                                    <div class="flex items-center gap-2 text-[8px] sm:text-[9px] font-bold text-slate-400 uppercase tracking-wider">
                                        <span v-if="lesson.available_from">From: {{ new Date(lesson.available_from).toLocaleDateString() }}</span>
                                        <span v-if="lesson.available_until && materialFilter !== 'archived'" class="text-red-400">Closes: {{ new Date(lesson.available_until).toLocaleDateString() }}</span>
                                        <span v-if="materialFilter === 'archived'" class="text-slate-500">Archived On: {{ new Date(lesson.available_until).toLocaleDateString() }}</span>
                                        <span v-if="!lesson.available_from && !lesson.available_until && materialFilter === 'active'">Always Available</span>
                                    </div>

                                    <div v-if="lesson.approval_status === 'rejected'" class="mt-2 p-2 bg-red-50 dark:bg-red-900/20 border border-red-100 dark:border-red-800/30 rounded max-w-lg">
                                        <div class="flex items-center gap-1.5 mb-1">
                                            <svg class="w-3 h-3 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                            <span class="text-[9px] font-black uppercase text-red-600 tracking-widest">Admin Feedback</span>
                                        </div>
                                        <p class="text-[10px] text-red-700 dark:text-red-300 italic">{{ lesson.rejection_note || lesson.rejection_reason || lesson.reason || 'No specific reason provided. Please review guidelines.' }}</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="flex flex-wrap items-center justify-end gap-1.5 w-full sm:w-auto shrink-0 mt-2 sm:mt-0">
                                
                                <a :href="`/storage/${lesson.attachment_path}`" target="_blank" class="p-1.5 text-slate-500 bg-slate-50 hover:text-blue-600 hover:bg-blue-50 dark:bg-slate-900/50 dark:text-slate-400 dark:hover:text-blue-400 dark:hover:bg-blue-900/30 rounded transition shadow-sm border border-slate-200 dark:border-slate-700" title="View">
                                    <Eye class="w-3.5 h-3.5" />
                                </a>
                                <a :href="`/storage/${lesson.attachment_path}`" download class="p-1.5 text-emerald-600 bg-emerald-50 hover:text-white hover:bg-emerald-500 dark:bg-emerald-900/30 dark:text-emerald-500 dark:hover:text-white dark:hover:bg-emerald-600 rounded transition shadow-sm border border-emerald-200 dark:border-emerald-800" title="Download">
                                    <Download class="w-3.5 h-3.5" />
                                </a>                             

                                <button v-if="lesson.approval_status === 'rejected'" @click="openResubmitModal(lesson)" class="text-[9px] font-black uppercase tracking-widest bg-blue-600 text-white border border-blue-600 px-3 py-1.5 rounded hover:bg-blue-500 transition shadow-sm">
                                    Resubmit
                                </button>
                                
                                <button v-if="materialFilter === 'archived'" @click="openUnarchiveModal(lesson)" class="text-[9px] font-black uppercase tracking-widest bg-emerald-100 text-emerald-700 border border-emerald-200 px-3 py-1.5 rounded hover:bg-emerald-200 transition shadow-sm">
                                    Req Unarchive
                                </button>
                                
                                <button @click="deleteItem(route('teacher.lessons.destroy', lesson.id), true)" class="p-1.5 text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 rounded transition border border-transparent hover:border-red-200 dark:hover:border-red-800" title="Delete Permanently">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </div>

                        </div>
                    </div>
                    <div v-else class="text-center py-10 bg-slate-50 dark:bg-slate-900/30 border border-dashed border-slate-200 dark:border-slate-700 rounded-xl">
                        <p class="text-slate-400 dark:text-slate-500 text-[10px] font-black uppercase tracking-widest">No {{ materialFilter }} materials found.</p>
                    </div>
                </div>

            </div>
        </div>

        <Modal :show="showUnarchiveModal" @close="showUnarchiveModal = false" maxWidth="sm">
            <div class="p-5 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-lg">
                <h3 class="font-black text-sm text-slate-900 dark:text-white uppercase tracking-tight mb-4 flex items-center gap-2">
                    <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Request Unarchive
                </h3>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 mb-4 leading-relaxed">
                    Set a new archive date for <span class="font-bold text-slate-800 dark:text-slate-200">"{{ lessonToUnarchive?.title }}"</span> before sending the request to the admin.
                </p>

                <form @submit.prevent="submitUnarchive" class="space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <InputLabel value="Show From *" class="text-[9px] font-black uppercase tracking-widest text-slate-500 mb-1" />
                            <input v-model="formUnarchive.available_from" type="datetime-local" :class="inputClass" required>
                            <InputError class="mt-1 text-[9px]" :message="formUnarchive.errors.available_from" />
                        </div>
                        <div>
                            <InputLabel value="New Archive Date *" class="text-[9px] font-black uppercase tracking-widest text-slate-500 mb-1" />
                            <input v-model="formUnarchive.available_until" type="datetime-local" :class="inputClass" required>
                            <InputError class="mt-1 text-[9px]" :message="formUnarchive.errors.available_until" />
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 mt-4 pt-4 border-t border-slate-100 dark:border-slate-800">
                        <button type="button" @click="showUnarchiveModal = false" class="text-[10px] text-slate-500 px-3 py-1.5 font-bold hover:text-slate-700 uppercase tracking-widest transition">Cancel</button>
                        <button :disabled="formUnarchive.processing" class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-1.5 rounded-md text-[10px] font-black uppercase tracking-widest shadow-sm transition-colors">Send Request</button>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="showLessonModal" @close="showLessonModal = false" maxWidth="sm">
            <div class="p-5 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-lg">
                <h3 class="font-black text-sm text-slate-900 dark:text-white uppercase tracking-tight mb-4 flex items-center gap-2">
                    <svg class="w-4 h-4 text-yellow-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                    Upload Material
                </h3>
                <form @submit.prevent="submitLesson" class="space-y-4">
                    <div>
                        <InputLabel value="Material Title *" class="text-[9px] font-black uppercase tracking-widest text-slate-500 mb-1" />
                        <input v-model="formLesson.title" type="text" placeholder="e.g., Chapter 1 Presentation" :class="inputClass" required>
                        <InputError class="mt-1 text-[9px]" :message="formLesson.errors.title" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <InputLabel value="Show From *" class="text-[9px] font-black uppercase tracking-widest text-slate-500 mb-1" />
                            <input v-model="formLesson.available_from" type="datetime-local" :class="inputClass" required>
                            <InputError class="mt-1 text-[9px]" :message="formLesson.errors.available_from" />
                        </div>
                        <div>
                            <InputLabel value="Archive On *" class="text-[9px] font-black uppercase tracking-widest text-slate-500 mb-1" />
                            <input v-model="formLesson.available_until" type="datetime-local" :class="inputClass" required>
                            <InputError class="mt-1 text-[9px]" :message="formLesson.errors.available_until" />
                        </div>
                    </div>

                    <div>
                        <InputLabel value="Select File (Max: 15MB) *" class="text-[9px] font-black uppercase tracking-widest text-slate-500 mb-1" />
                        <input type="file" @input="formLesson.file = $event.target.files[0]" accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.png,.zip" class="w-full text-[10px] text-slate-500 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-[10px] file:font-bold file:bg-yellow-50 file:text-yellow-700 hover:file:bg-yellow-100 cursor-pointer border border-slate-200 dark:border-slate-800 rounded-md p-1 transition" required>
                        <InputError class="mt-1 text-[9px]" :message="formLesson.errors.file" />
                    </div>

                    <div class="flex justify-end gap-2 mt-4 pt-4 border-t border-slate-100 dark:border-slate-800">
                        <button type="button" @click="showLessonModal = false" class="text-[10px] text-slate-500 px-3 py-1.5 font-bold hover:text-slate-700 uppercase tracking-widest transition">Cancel</button>
                        <button class="bg-yellow-500 hover:bg-yellow-400 text-white px-4 py-1.5 rounded-md text-[10px] font-black uppercase tracking-widest shadow-sm transition-colors" :disabled="formLesson.processing">Upload</button>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="showResubmitModal" @close="showResubmitModal = false" maxWidth="sm">
            <div class="p-5 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-lg">
                <h3 class="font-black text-sm text-slate-900 dark:text-white uppercase tracking-tight mb-4 flex items-center gap-2">
                    <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                    Resubmit Material
                </h3>
                <p class="text-[11px] text-slate-500 dark:text-slate-400 mb-4 leading-relaxed">Upload a corrected file and confirm the active dates for <span class="font-bold text-slate-800 dark:text-slate-200">"{{ lessonToResubmit?.title }}"</span>.</p>
                
                <form @submit.prevent="submitResubmit" class="space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <InputLabel value="Show From *" class="text-[9px] font-black uppercase tracking-widest text-slate-500 mb-1" />
                            <input v-model="formResubmit.available_from" type="datetime-local" :class="inputClass" required>
                            <InputError class="mt-1 text-[9px]" :message="formResubmit.errors.available_from" />
                        </div>
                        <div>
                            <InputLabel value="Archive On *" class="text-[9px] font-black uppercase tracking-widest text-slate-500 mb-1" />
                            <input v-model="formResubmit.available_until" type="datetime-local" :class="inputClass" required>
                            <InputError class="mt-1 text-[9px]" :message="formResubmit.errors.available_until" />
                        </div>
                    </div>

                    <div>
                        <InputLabel value="Select New File (Max: 15MB) *" class="text-[9px] font-black uppercase tracking-widest text-slate-500 mb-1" />
                        <input type="file" @input="formResubmit.file = $event.target.files[0]" accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.png,.zip" class="w-full text-[10px] text-slate-500 file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-[10px] file:font-bold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer border border-slate-200 dark:border-slate-800 rounded-md p-1 transition" required>
                        <InputError class="mt-1 text-[9px]" :message="formResubmit.errors.file" />
                    </div>

                    <div class="flex justify-end gap-2 mt-4 pt-4 border-t border-slate-100 dark:border-slate-800">
                        <button type="button" @click="showResubmitModal = false" class="text-[10px] text-slate-500 px-3 py-1.5 font-bold hover:text-slate-700 uppercase tracking-widest transition">Cancel</button>
                        <button :disabled="formResubmit.processing" class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-1.5 rounded-md text-[10px] font-black uppercase tracking-widest shadow-sm transition-colors">Submit Fix</button>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="showAnnouncementModal" @close="showAnnouncementModal = false" maxWidth="2xl">
            <div class="p-5 bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-slate-200 dark:border-slate-700">
                <h3 class="font-black text-sm text-slate-900 dark:text-white uppercase tracking-tight mb-4 flex items-center gap-2">
                    <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path></svg>
                    Post Announcement
                </h3>
                <form @submit.prevent="submitAnnouncement" class="space-y-4">
                    <div>
                        <InputLabel value="Post Title (Optional)" class="text-[9px] font-black uppercase text-slate-500 mb-1" />
                        <input v-model="formAnnouncement.title" type="text" placeholder="e.g., Lesson 4 Updates" :class="inputClass" />
                    </div>
                    <div>
                        <InputLabel value="External Video Link (Optional)" class="text-[9px] font-black uppercase text-slate-500 mb-1" />
                        <input v-model="formAnnouncement.video_link" type="url" placeholder="Paste YouTube or Vimeo URL here..." :class="inputClass" />
                    </div>
                    <div>
                        <InputLabel value="Description (Optional)" class="text-[9px] font-black uppercase text-slate-500 mb-1" />
                        <div class="border border-slate-300 dark:border-slate-700 rounded-md overflow-hidden focus-within:ring-2 focus-within:ring-blue-500">
                            <RichTextEditor v-model="formAnnouncement.content" placeholder="Write your announcement details here..." class="text-xs" />
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 mt-4 pt-3 border-t border-slate-100 dark:border-slate-700">
                        <button type="button" @click="showAnnouncementModal = false" class="text-[10px] text-slate-500 px-3 py-1.5 font-bold hover:text-slate-700 uppercase tracking-widest transition">Cancel</button>
                        <button :disabled="formAnnouncement.processing || (!formAnnouncement.title && !formAnnouncement.video_link && (!formAnnouncement.content || formAnnouncement.content === '<p></p>'))" 
                                class="bg-red-600 hover:bg-red-500 text-white px-4 py-1.5 rounded-md text-[10px] font-black uppercase tracking-widest shadow-sm transition disabled:opacity-50 disabled:cursor-not-allowed">
                            Post Update
                        </button>
                    </div>
                </form>
            </div>
        </Modal>

        <Modal :show="showAssignmentModal" @close="showAssignmentModal = false" maxWidth="md">
            <div class="p-5 bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-slate-200 dark:border-slate-700">
                <h3 class="font-black text-sm text-slate-900 dark:text-white uppercase tracking-tight mb-4 flex items-center gap-2">
                    <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    Create Task
                </h3>
                <form @submit.prevent="submitAssignment" class="space-y-3.5">
                    
                    <div>
                        <InputLabel value="Title *" class="text-[9px] font-black uppercase tracking-widest text-slate-500 mb-1" />
                        <input v-model="formAssignment.title" type="text" :class="inputClass" required autofocus />
                        <InputError class="mt-1 text-[9px]" :message="formAssignment.errors.title" />
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <InputLabel value="Task Type *" class="text-[9px] font-black uppercase tracking-widest text-slate-500 mb-1" />
                            <select v-model="formAssignment.type" :class="inputClass" class="cursor-pointer" required>
                                <option value="assignment">Assignment</option>
                                <option value="activity">Activity</option>
                                <option value="performance_task">Performance Task</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel value="Points *" class="text-[9px] font-black uppercase tracking-widest text-slate-500 mb-1" />
                            <input v-model="formAssignment.points" type="number" min="1" :class="inputClass" required />
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <InputLabel value="Due Date (Soft Deadline) *" class="text-[9px] font-black uppercase tracking-widest text-slate-500 mb-1" />
                            <input v-model="formAssignment.due_date" type="datetime-local" :class="inputClass" required />
                        </div>
                        <div>
                            <InputLabel value="Closing Date (Hard Deadline)" class="text-[9px] font-black uppercase tracking-widest text-slate-500 mb-1" />
                            <input v-model="formAssignment.closing_date" type="datetime-local" :class="inputClass" />
                        </div>
                    </div>

                    <div class="flex items-center gap-2 p-3 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-lg shadow-sm">
                        <input type="checkbox" id="modal_hide_late" v-model="formAssignment.hide_from_late" class="rounded text-blue-600 focus:ring-blue-500 bg-white dark:bg-slate-800 border-slate-300 dark:border-slate-600 cursor-pointer w-4 h-4" />
                        <label for="modal_hide_late" class="text-[10px] font-black uppercase tracking-widest text-slate-700 dark:text-slate-300 cursor-pointer select-none">
                            Hide from Late Enrollees
                        </label>
                    </div>

                    <div>
                        <InputLabel value="Instructions" class="text-[9px] font-black uppercase tracking-widest text-slate-500 mb-1" />
                        <textarea v-model="formAssignment.description" :class="inputClass" class="h-20 resize-none" placeholder="Write instructions here..."></textarea>
                        <InputError class="mt-1 text-[9px]" :message="formAssignment.errors.description" />
                    </div>

                    <div>
                        <InputLabel value="Attachments (Optional)" class="text-[9px] font-black uppercase tracking-widest text-slate-500 mb-1" />
                        <input type="file" multiple @change="e => formAssignment.files = Array.from(e.target.files)" class="block w-full text-[10px] text-slate-500 file:mr-2 file:py-1.5 file:px-3 file:rounded file:border-0 file:text-[9px] file:font-bold file:uppercase file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 cursor-pointer border border-slate-200 dark:border-slate-700 rounded-md p-1 transition" />
                        
                        <div v-if="formAssignment.files && formAssignment.files.length > 0" class="mt-2 space-y-1 max-h-24 overflow-y-auto pr-1 no-scrollbar">
                            <div v-for="(file, index) in formAssignment.files" :key="index" class="text-[9px] font-bold text-slate-600 dark:text-slate-300 flex items-center gap-1.5 bg-slate-50 dark:bg-slate-800 p-1.5 rounded border border-slate-100 dark:border-slate-700">
                                <svg class="w-3 h-3 text-blue-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg>
                                <span class="truncate">{{ file.name }}</span>
                            </div>
                        </div>
                        <InputError class="mt-1 text-[9px]" :message="formAssignment.errors.files" />
                    </div>

                    <div class="flex justify-end gap-2 pt-3 mt-4 border-t border-slate-100 dark:border-slate-800">
                        <button type="button" @click="showAssignmentModal = false" class="text-[10px] text-slate-500 px-3 py-1.5 font-bold uppercase tracking-widest hover:text-slate-700 transition">Cancel</button>
                        <button :disabled="formAssignment.processing" class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-1.5 rounded-md text-[10px] font-black uppercase tracking-widest shadow-sm transition">Create Task</button>
                    </div>
                </form>
            </div>
        </Modal>

    </AuthenticatedLayout>
</template>

<style scoped>
.announcement-content :deep(iframe) {
    width: 100% !important;
    max-width: 320px !important; 
    height: auto;
    aspect-ratio: 16 / 9;
    border-radius: 0.5rem;
    margin-top: 0.5rem;
    margin-bottom: 0.5rem;
    display: block;
}
@media (min-width: 640px) {
    .announcement-content :deep(iframe) {
        max-width: 400px !important; 
    }
}
.announcement-content :deep(a) {
    color: #2563eb;
    text-decoration: underline;
    cursor: pointer;
}
.announcement-content :deep(ul) {
    list-style-type: disc;
    padding-left: 1rem;
    margin-top: 0.25rem;
    margin-bottom: 0.25rem;
}
.announcement-content :deep(ol) {
    list-style-type: decimal;
    padding-left: 1rem;
    margin-top: 0.25rem;
    margin-bottom: 0.25rem;
}
.scrollbar-hide::-webkit-scrollbar {
    display: none;
}
.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>