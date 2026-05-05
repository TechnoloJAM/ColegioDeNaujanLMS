<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link, usePage, router } from '@inertiajs/vue3';
import { ref, onMounted, nextTick, computed, watch } from 'vue'; 
import Modal from '@/Components/Modal.vue';
import { 
    ChevronLeft, Calendar, Clock, Trophy, 
    FileText, Paperclip, ExternalLink, Send, Undo2, Filter, Eye, Download, CheckCircle2
} from 'lucide-vue-next';

const props = defineProps({ course: Object });
const currentUser = usePage().props.auth.user;

const activeTab = ref('announcements');
const assignmentFilter = ref('upcoming'); 
const sortOrder = ref('desc'); 
const highlightedId = ref(null);

const showSubmitModal = ref(false);
const selectedAssignment = ref(null);

const formComment = useForm({ content: '' });
const formSubmission = useForm({ files: [], text_content: '' });

// Material Preview State
const showMaterialPreview = ref(false);
const selectedMaterialPath = ref(null);

watch(activeTab, (newTab) => {
    const url = new URL(window.location.href);
    url.searchParams.set('tab', newTab);
    window.history.replaceState({}, '', url);
});

const getYouTubeEmbedUrl = (url) => {
    if (!url) return null;
    const regExp = /^.*(youtu\.be\/|v\/|u\/\w\/|embed\/|watch\?v=|&v=|shorts\/)([^#&?]*).*/;
    const match = url.match(regExp);
    return (match && match[2].length === 11) ? `https://www.youtube-nocookie.com/embed/${match[2]}` : null;
};

const getYouTubeVideoId = (url) => {
    if (!url) return null;
    const regExp = /^.*(youtu\.be\/|v\/|u\/\w\/|embed\/|watch\?v=|&v=|shorts\/)([^#&?]*).*/;
    const match = url.match(regExp);
    return (match && match[2].length === 11) ? match[2] : null;
};

const formatRichText = (htmlContent) => {
    if (!htmlContent) return '';
    let processed = htmlContent.replace(/<iframe/gi, '<iframe class="hidden sm:block w-full max-w-[320px] sm:max-w-[400px] aspect-video rounded-lg shadow-sm my-3 border border-slate-200 dark:border-slate-700"');
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

// NEW: Computed properties for Notification Dots
const pendingTasksCount = computed(() => {
    const now = new Date();
    if (!props.course.assignments) return 0;
    return props.course.assignments.filter(a => {
        const isSubmitted = a.submissions.length > 0;
        const isPastDue = a.due_date && new Date(a.due_date) < now;
        return !isSubmitted && (!isPastDue || !a.due_date);
    }).length;
});

const pastDueTasksCount = computed(() => {
    const now = new Date();
    if (!props.course.assignments) return 0;
    return props.course.assignments.filter(a => {
        const isSubmitted = a.submissions.length > 0;
        const isPastDue = a.due_date && new Date(a.due_date) < now;
        return !isSubmitted && isPastDue;
    }).length;
});

const totalActionNeeded = computed(() => pendingTasksCount.value + pastDueTasksCount.value);

const filteredAssignments = computed(() => {
    const now = new Date();
    if (!props.course.assignments) return [];
    
    let filtered = props.course.assignments.filter(a => {
        const isSubmitted = a.submissions.length > 0;
        const dueDate = a.due_date ? new Date(a.due_date) : null;
        const isPastDue = dueDate && dueDate < now;
        if (assignmentFilter.value === 'completed') return isSubmitted;
        if (assignmentFilter.value === 'past_due') return !isSubmitted && isPastDue;
        return !isSubmitted && (!isPastDue || !dueDate);
    });

    filtered.sort((a, b) => {
        const dateA = new Date(a.created_at || 0).getTime();
        const dateB = new Date(b.created_at || 0).getTime();
        return sortOrder.value === 'desc' ? dateB - dateA : dateA - dateB;
    });

    return filtered;
}); 

const activeMaterials = computed(() => {
    const now = new Date();
    return props.course.lessons?.filter(l => !l.available_until || new Date(l.available_until) > now) || [];
});

onMounted(async () => {
    const params = new URLSearchParams(window.location.search);
    const tab = params.get('tab');
    const target = params.get('target');

    if (tab && ['announcements', 'assignments', 'materials'].includes(tab)) {
        activeTab.value = tab;
    }
    if (target) {
        await nextTick();
        const element = document.getElementById(`item-${target}`);
        if (element) {
            element.scrollIntoView({ behavior: 'smooth', block: 'center' });
            highlightedId.value = parseInt(target);
            setTimeout(() => highlightedId.value = null, 4000); 
        }
    }
});

const linkify = (text) => text ? text.replace(/(https?:\/\/[^\s]+)/g, '<a href="$1" target="_blank" class="text-blue-600 hover:underline font-bold">$1</a>') : 'No instructions.';

const getStatus = (assignment) => {
    const sub = assignment.submissions[0];
    if (!sub) return assignment.due_date && new Date(assignment.due_date) < new Date() 
        ? { label: 'Missing', class: 'text-red-600 bg-red-50 border-red-200 dark:bg-red-900/30 dark:border-red-800' } 
        : { label: 'To Do', class: 'text-slate-500 bg-slate-100 border-slate-200 dark:bg-slate-800 dark:border-slate-700' };
    if (sub.grade !== null) return { label: `Graded: ${sub.grade}/${assignment.points}`, class: 'text-emerald-600 bg-emerald-50 border-emerald-200 dark:bg-emerald-900/30 dark:border-emerald-800' };
    return { label: 'Submitted', class: 'text-blue-600 bg-blue-50 border-blue-200 dark:bg-blue-900/30 dark:border-blue-800' };
};

const getPaths = (paths) => {
    if (!paths) return [];
    if (Array.isArray(paths)) return paths;
    try { return JSON.parse(paths) || []; } catch (e) { return []; }
};

const openMaterialPreview = (path) => {
    selectedMaterialPath.value = path;
    showMaterialPreview.value = true;
};

const toggleComments = (post) => { post.showComments = !post.showComments; };
const submitComment = (id) => formComment.post(route('comments.store', id), { onSuccess: () => { formComment.reset(); const p = props.course.announcements.find(a => a.id === id); if(p) p.showComments = true; }, preserveScroll: true });
const deleteComment = (id) => { if(confirm('Delete?')) router.delete(route('comments.destroy', id), { preserveScroll: true }); };

const openSubmitModal = (a) => { 
    selectedAssignment.value = a; 
    formSubmission.reset(); 
    if (a.submissions[0]) {
        formSubmission.text_content = a.submissions[0].text_content;
    }
    showSubmitModal.value = true; 
};
const submitWork = () => formSubmission.post(route('assignments.submit', selectedAssignment.value.id), { onSuccess: () => { showSubmitModal.value = false; formSubmission.reset(); }, preserveScroll: true });
const undoTurnIn = () => { if (confirm('Undo submission? This will remove your files and answers.')) router.post(route('assignments.unsubmit', selectedAssignment.value.id), {}, { onSuccess: () => showSubmitModal.value = false, preserveScroll: true }); };
const leaveClass = () => { if (confirm('Leave this class? You will lose access.')) router.delete(route('student.courses.leave', props.course.id)); };
</script>

<template>
    <Head :title="course.title" />
    <AuthenticatedLayout>
        <div class="mb-4 flex justify-between items-start max-w-4xl mx-auto px-2 sm:px-0">
             <div>
                 <div class="flex items-center gap-2 mb-1">
                     <h1 class="text-xl font-black text-slate-900 dark:text-white leading-tight">{{ course.title }}</h1>
                     <span class="text-[10px] font-mono font-bold bg-slate-100 dark:bg-slate-800 text-slate-500 px-1.5 py-0.5 rounded border border-slate-200 dark:border-slate-700 select-all">{{ course.enrollment_code }}</span>
                 </div>
                 <button @click="leaveClass" class="text-[10px] font-bold text-red-500 hover:text-red-700 flex items-center gap-1 transition">
                     <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                     Leave Class
                 </button>
             </div>
             
             <Link :href="route('student.courses')" class="px-3 py-1.5 text-[10px] font-bold uppercase tracking-wide text-slate-600 bg-white hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-300 border border-slate-200 dark:border-slate-700 rounded-md shadow-sm transition flex items-center gap-1">
                 <ChevronLeft class="w-3.5 h-3.5" /> Back
             </Link>
        </div>

        <div class="sticky top-0 z-20 backdrop-blur-xl bg-white/80 dark:bg-slate-900/80 pt-4 pb-0 mb-4 border-b border-slate-200 dark:border-slate-700 max-w-4xl mx-auto">
            <div class="flex gap-6 overflow-x-auto scrollbar-hide px-2 sm:px-0">
                <button @click="activeTab = 'announcements'" class="pb-3 text-xs sm:text-sm font-bold transition-all relative whitespace-nowrap" :class="activeTab === 'announcements' ? 'text-blue-600' : 'text-slate-400 hover:text-slate-600'">
                    Announcements
                    <div v-if="activeTab === 'announcements'" class="absolute bottom-0 left-0 w-full h-0.5 bg-blue-600 rounded-full"></div>
                </button>
                <button @click="activeTab = 'assignments'" class="pb-3 text-xs sm:text-sm font-bold transition-all relative whitespace-nowrap" :class="activeTab === 'assignments' ? 'text-blue-600' : 'text-slate-400 hover:text-slate-600'">
                    Tasks
                    <!-- Red Dot Indicator for Main Tab -->
                    <span v-if="totalActionNeeded > 0" class="absolute top-0 -right-2 sm:-right-3 w-2 h-2 bg-red-500 border-2 border-white dark:border-slate-800 rounded-full animate-pulse shadow-sm"></span>
                    <div v-if="activeTab === 'assignments'" class="absolute bottom-0 left-0 w-full h-0.5 bg-blue-600 rounded-full"></div>
                </button>
                <button @click="activeTab = 'materials'" class="pb-3 text-xs sm:text-sm font-bold transition-all relative whitespace-nowrap" :class="activeTab === 'materials' ? 'text-blue-600' : 'text-slate-400 hover:text-slate-600'">
                    Materials
                    <div v-if="activeTab === 'materials'" class="absolute bottom-0 left-0 w-full h-0.5 bg-blue-600 rounded-full"></div>
                </button>
            </div>
        </div>

        <div class="max-w-4xl mx-auto min-h-[400px] px-2 sm:px-0">
            
            <div v-if="activeTab === 'announcements'" class="space-y-4 animate-in fade-in duration-300">
                <div v-if="course.announcements.length === 0" class="text-center py-12 text-slate-400 text-sm italic">No updates posted yet.</div>
                
                <div v-for="post in course.announcements" :key="post.id" class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden transition-all hover:border-blue-300">
                    <div class="p-4 flex gap-3 items-center border-b border-slate-50 dark:border-slate-700 bg-slate-50/30 dark:bg-slate-900/20">
                        <div class="w-9 h-9 rounded-full bg-blue-600 text-white flex items-center justify-center font-black text-sm shrink-0">{{ post.user.name.charAt(0) }}</div>
                        <div>
                            <h4 class="text-xs font-black text-slate-900 dark:text-white uppercase tracking-wider">{{ post.user.name }}</h4>
                            <p class="text-[10px] text-slate-500 font-bold uppercase">{{ new Date(post.created_at).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' }) }}</p>
                        </div>
                    </div>
                    
                    <div class="p-5 space-y-4">
                        <h3 v-if="post.title" class="text-lg font-black text-slate-900 dark:text-white tracking-tight">{{ post.title }}</h3>
                        
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
                    <div class="px-4 py-3 bg-slate-50/50 dark:bg-slate-900/30 border-t border-slate-100 dark:border-slate-700">
                        <button @click="toggleComments(post)" class="text-[10px] font-black uppercase text-slate-500 hover:text-blue-600 flex items-center gap-2 transition tracking-widest">
                            <FileText class="w-4 h-4" />
                            Comments ({{ post.comments.length }})
                        </button>
                    </div>
                    <div v-if="post.showComments" class="bg-slate-50/30 dark:bg-slate-900/50 p-4 border-t border-slate-100 dark:border-slate-700 space-y-4">
                        <div v-for="comment in post.comments" :key="comment.id" class="flex gap-3 group items-start">
                            <div class="w-6 h-6 rounded-full bg-slate-200 dark:bg-slate-700 flex items-center justify-center text-[9px] font-black shrink-0">{{ comment.user.name.charAt(0) }}</div>
                            <div class="bg-white dark:bg-slate-800 p-3 rounded-xl border border-slate-200 dark:border-slate-700 flex-1 shadow-sm relative">
                                <div class="flex justify-between items-center mb-1">
                                    <span class="font-black text-slate-900 dark:text-white text-[10px] uppercase tracking-wide">{{ comment.user.name }}</span>
                                    <button v-if="comment.user_id === currentUser.id" @click="deleteComment(comment.id)" class="text-slate-300 hover:text-red-500 opacity-0 group-hover:opacity-100 transition-all font-black">&times;</button>
                                </div>
                                <span class="text-xs text-slate-700 dark:text-slate-300 leading-snug">{{ comment.content }}</span>
                            </div>
                        </div>
                        <input v-model="formComment.content" @keyup.enter="submitComment(post.id)" type="text" placeholder="Add a comment..." class="w-full bg-white dark:bg-slate-800 border-slate-200 dark:border-slate-700 rounded-lg py-2 px-3 text-xs focus:ring-2 focus:ring-blue-500 shadow-sm" />
                    </div>
                </div>
            </div>

            <!-- COMPACT TASK LIST -->
            <div v-if="activeTab === 'assignments'" class="space-y-4 animate-in fade-in slide-in-from-bottom-2 duration-300">
                
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                    <div class="flex p-1 bg-slate-100 dark:bg-slate-800 rounded-xl gap-1 overflow-x-auto scrollbar-hide shrink-0">
                        <button @click="assignmentFilter = 'upcoming'" 
                            class="flex-1 px-4 py-2 text-[10px] font-black uppercase tracking-widest rounded-lg transition-all whitespace-nowrap relative"
                            :class="assignmentFilter === 'upcoming' ? 'bg-white dark:bg-slate-700 text-blue-600 shadow-sm' : 'text-slate-500 hover:text-slate-700'">
                            UPCOMING
                            <!-- Red Dot Indicator -->
                            <span v-if="pendingTasksCount > 0" class="absolute top-1.5 right-1.5 w-1.5 h-1.5 bg-red-500 rounded-full animate-pulse shadow-[0_0_4px_rgba(239,68,68,0.8)]"></span>
                        </button>
                        <button @click="assignmentFilter = 'past_due'" 
                            class="flex-1 px-4 py-2 text-[10px] font-black uppercase tracking-widest rounded-lg transition-all whitespace-nowrap relative"
                            :class="assignmentFilter === 'past_due' ? 'bg-white dark:bg-slate-700 text-red-600 shadow-sm' : 'text-slate-500 hover:text-slate-700'">
                            PAST DUE
                            <!-- Red Dot Indicator -->
                            <span v-if="pastDueTasksCount > 0" class="absolute top-1.5 right-1.5 w-1.5 h-1.5 bg-red-500 rounded-full animate-pulse shadow-[0_0_4px_rgba(239,68,68,0.8)]"></span>
                        </button>
                        <button @click="assignmentFilter = 'completed'" 
                             class="flex-1 px-4 py-2 text-[10px] font-black uppercase tracking-widest rounded-lg transition-all whitespace-nowrap"
                            :class="assignmentFilter === 'completed' ? 'bg-white dark:bg-slate-700 text-emerald-600 shadow-sm' : 'text-slate-500 hover:text-slate-700'">
                            COMPLETED
                        </button>
                    </div>
                    <div class="flex items-center gap-2 shrink-0 self-end sm:self-auto">
                        <Filter class="w-4 h-4 text-slate-400 hidden sm:block" />
                        <select v-model="sortOrder" class="text-[9px] sm:text-[10px] font-black uppercase tracking-widest text-slate-500 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700 rounded-lg shadow-sm focus:ring-0 cursor-pointer py-1.5 pl-2 pr-6 transition">
                            <option value="desc">Latest to Oldest</option>
                            <option value="asc">Oldest to Latest</option>
                        </select>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto p-0 sm:p-2">
                    <div v-if="filteredAssignments.length > 0" class="flex flex-col gap-1.5 sm:gap-2">
                        <div v-for="assignment in filteredAssignments" :key="assignment.id" :id="`item-${assignment.id}`"
                              class="group flex flex-col sm:flex-row sm:items-center gap-1.5 sm:gap-2.5 p-2.5 sm:p-4 bg-white dark:bg-slate-800 border-l-2 sm:border-l-4 border border-slate-200 dark:border-slate-700 rounded-lg sm:rounded-xl transition-all duration-200 shadow-sm"
                              :class="[
                                  highlightedId === assignment.id ? 'ring-2 ring-yellow-400 border-yellow-500' : '',
                                  assignmentFilter === 'upcoming' ? 'border-l-blue-500 hover:border-blue-400' : assignmentFilter === 'completed' ? 'border-l-emerald-500 hover:border-emerald-400' : 'border-l-red-500 hover:border-red-400'
                              ]">
                            
                            <div class="hidden sm:flex shrink-0 w-8 h-8 rounded items-center justify-center transition-colors bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400">
                                <FileText class="w-4 h-4" />
                            </div>

                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between gap-2 sm:hidden mb-1">
                                    <div class="flex items-center gap-1.5 min-w-0">
                                        <span :class="getStatus(assignment).class" class="text-[7px] font-black uppercase tracking-widest px-1 py-0.5 rounded border shrink-0">
                                            {{ getStatus(assignment).label }}
                                        </span>
                                        <h4 class="text-[11px] font-black text-slate-900 dark:text-white truncate">
                                            {{ assignment.title }}
                                        </h4>
                                    </div>
                                    <span class="text-[8px] font-black whitespace-nowrap bg-slate-100 dark:bg-slate-900/50 px-1.5 py-0.5 rounded shrink-0 text-slate-500 dark:text-slate-400">
                                        {{ assignment.points }} pts
                                    </span>
                                </div>

                                <div class="hidden sm:flex items-center gap-2 mb-0.5">
                                    <span :class="getStatus(assignment).class" class="text-[8px] font-black uppercase tracking-widest px-1.5 py-0.5 rounded border shrink-0">
                                        {{ getStatus(assignment).label }}
                                    </span>
                                    <h4 class="text-sm font-black text-slate-900 dark:text-white truncate group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                                        {{ assignment.title }}
                                    </h4>
                                </div>

                                <p class="hidden sm:block text-[10px] text-slate-500 dark:text-slate-400 truncate font-medium leading-snug mt-0.5">
                                    {{ assignment.description || 'No instructions.' }}
                                </p>
                            </div>

                            <div class="flex items-center justify-between sm:justify-end gap-2 sm:gap-4 w-full sm:w-auto shrink-0 mt-0.5 sm:mt-0 pt-1.5 sm:pt-0 border-t border-dashed border-slate-100 sm:border-none dark:border-slate-700/50">
                                <div class="flex items-center gap-1 text-[8px] sm:text-[9px] font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">
                                    <Clock class="w-2.5 h-2.5 sm:hidden" />
                                    <span>
                                        Due: {{ assignment.due_date ? new Date(assignment.due_date).toLocaleDateString(undefined, {month: 'short', day: 'numeric'}) : 'No Date' }}
                                    </span>
                                </div>
                                
                                <button @click="openSubmitModal(assignment)" class="bg-blue-600 hover:bg-blue-500 text-white text-[8px] sm:text-[9px] font-black uppercase tracking-widest px-3 sm:px-4 py-1.5 sm:py-2 rounded-lg shadow-sm transition-all active:scale-95">
                                    {{ assignment.submissions[0] ? 'View / Undo' : 'Submit Work' }}
                                </button>
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-center py-12 px-4 bg-slate-50 dark:bg-slate-800/50 rounded-lg border border-dashed border-slate-200 dark:border-slate-700 mt-2">
                        <CheckCircle2 class="w-6 h-6 mx-auto text-slate-300 dark:text-slate-600 mb-2" />
                        <p class="text-[10px] sm:text-[11px] font-black uppercase tracking-widest text-slate-900 dark:text-white mb-0.5">All caught up</p>
                        <p class="text-[9px] sm:text-[10px] font-bold text-slate-500 text-center">No {{ assignmentFilter.replace('_', ' ') }} tasks found.</p>
                    </div>
                </div>
            </div>

            <div v-if="activeTab === 'materials'" class="space-y-4 animate-in fade-in slide-in-from-bottom-2 duration-300 max-w-3xl">
                
                <div v-if="activeMaterials.length > 0" class="space-y-3">
                    <div v-for="lesson in activeMaterials" :key="lesson.id" :id="`item-${lesson.id}`"
                         class="flex items-center justify-between p-4 bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 hover:border-emerald-400 transition-all shadow-sm group">
                        <div class="flex items-center gap-4 min-w-0">
                            <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-900/30 flex items-center justify-center shrink-0 text-emerald-600">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg>
                            </div>
                            <div class="flex flex-col">
                                <span class="text-sm font-black text-slate-800 dark:text-slate-100 truncate group-hover:text-emerald-600 transition">{{ lesson.title }}</span>
                                <span v-if="lesson.available_until" class="text-[9px] font-bold text-red-400 uppercase tracking-widest mt-0.5">Closes: {{ new Date(lesson.available_until).toLocaleDateString() }}</span>
                            </div>
                        </div>
                        
                        <div class="flex items-center gap-2 shrink-0">
                            <button @click="openMaterialPreview(lesson.attachment_path)" title="View Material" class="p-2 text-slate-500 bg-slate-50 hover:text-blue-600 hover:bg-blue-50 dark:bg-slate-900/50 dark:text-slate-400 dark:hover:text-blue-400 dark:hover:bg-blue-900/30 rounded-lg transition shadow-sm border border-slate-200 dark:border-slate-700">
                                <Eye class="w-4 h-4" />
                            </button>
                            <a :href="`/storage/${lesson.attachment_path}`" download title="Download Material" class="p-2 text-emerald-600 bg-emerald-50 hover:text-white hover:bg-emerald-500 dark:bg-emerald-900/30 dark:text-emerald-500 dark:hover:text-white dark:hover:bg-emerald-600 rounded-lg transition shadow-sm border border-emerald-200 dark:border-emerald-800">
                                <Download class="w-4 h-4" />
                            </a>
                        </div>
                    </div>
                </div>
                
                <div v-else class="text-center py-10 bg-slate-50 dark:bg-slate-900/30 border border-dashed border-slate-200 dark:border-slate-700 rounded-xl">
                    <p class="text-slate-400 dark:text-slate-500 text-[10px] font-black uppercase tracking-widest">No active materials available right now.</p>
                </div>
            </div>
        </div>

        <Modal :show="showMaterialPreview" @close="showMaterialPreview = false" maxWidth="4xl">
            <div class="bg-white dark:bg-slate-900 rounded-2xl overflow-hidden shadow-2xl flex flex-col h-[85vh]">
                <div class="p-4 border-b border-slate-100 dark:border-slate-800 flex justify-between items-center bg-slate-50 dark:bg-slate-900 shrink-0">
                    <h3 class="font-black text-sm text-slate-900 dark:text-white flex items-center gap-2 uppercase tracking-tight">
                        <div class="w-8 h-8 rounded-lg bg-blue-100 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0">
                            <Eye class="w-4 h-4" /> 
                        </div>
                        Material Preview
                    </h3>
                    <button @click="showMaterialPreview = false" class="w-8 h-8 flex items-center justify-center rounded-full bg-slate-200 dark:bg-slate-700 text-slate-500 dark:text-slate-300 hover:bg-slate-300 transition shrink-0">&times;</button>
                </div>
                
                <div class="flex-1 p-4 bg-slate-100 dark:bg-slate-950/50 flex flex-col items-center justify-center relative overflow-hidden">
                    <iframe v-if="selectedMaterialPath?.toLowerCase().endsWith('.pdf')" :src="`/storage/${selectedMaterialPath}`" class="w-full h-full border-none rounded-lg shadow-sm bg-white dark:bg-slate-900"></iframe>
                    <img v-else-if="selectedMaterialPath?.match(/\.(jpeg|jpg|png|gif)$/i)" :src="`/storage/${selectedMaterialPath}`" class="max-w-full max-h-full object-contain rounded-lg shadow-sm" />
                    
                    <div v-else class="text-center p-8 bg-white dark:bg-slate-800 rounded-2xl shadow-sm border border-slate-200 dark:border-slate-700 max-w-sm w-full">
                        <FileText class="w-16 h-16 text-slate-300 dark:text-slate-600 mb-4 mx-auto" />
                        <p class="text-slate-500 font-black mb-1 text-[11px] uppercase tracking-widest">Preview unavailable</p>
                        <p class="text-slate-400 text-[10px] font-bold mb-6">This file type cannot be viewed directly.</p>
                        <div class="flex flex-col gap-2">
                            <a :href="`/storage/${selectedMaterialPath}`" download class="inline-flex items-center justify-center gap-1.5 bg-blue-600 hover:bg-blue-500 text-white transition text-[10px] font-black uppercase tracking-widest px-4 py-3 rounded-lg shadow-sm w-full">
                                <Download class="w-4 h-4" /> Download File
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </Modal>

        <Modal :show="showSubmitModal" @close="showSubmitModal = false" maxWidth="2xl">
            <div class="bg-white dark:bg-slate-800 rounded-2xl overflow-hidden flex flex-col h-[85vh]">
                <div class="p-5 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center bg-slate-50/50 dark:bg-slate-900/50">
                    <div class="flex gap-4"> 
                        <div class="w-10 h-10 rounded-xl bg-blue-600 text-white flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                        </div> 
                        <div>
                            <h2 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-tight">{{ selectedAssignment?.title }}</h2>
                            <p class="text-[10px] font-bold text-slate-500 uppercase tracking-widest">Submit Your Evidence</p> 
                        </div>
                    </div>
                    <button @click="showDetailsModal = false" class="text-slate-400 hover:text-slate-600 text-2xl font-light">&times;</button>
                </div>
                <div class="p-6 overflow-y-auto flex-1 space-y-6">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="p-3 bg-slate-50 dark:bg-slate-900/40 rounded-xl border border-slate-100 dark:border-slate-700">
                            <p class="text-[9px] font-black text-slate-400 uppercase mb-1">Total Points</p>
                            <div class="flex items-center gap-2 text-blue-600 font-black">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path></svg> 
                                {{ selectedAssignment?.points }} Pts
                            </div>
                        </div>
                        <div class="p-3 bg-slate-50 dark:bg-slate-900/40 rounded-xl border border-slate-100 dark:border-slate-700">
                            <p class="text-[9px] font-black text-slate-400 uppercase mb-1">Deadline</p>
                            <div class="flex items-center gap-2 text-red-500 font-black">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> 
                                {{ selectedAssignment?.due_date ? new Date(selectedAssignment.due_date).toLocaleDateString() : 'No Deadline' }}
                            </div>
                        </div>
                    </div>
                    <div class="space-y-2">
                        <h3 class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Teacher's Instructions</h3>
                        <div class="text-xs text-slate-700 dark:text-slate-300 bg-slate-50 dark:bg-slate-900/20 p-4 rounded-xl border border-slate-100 dark:border-slate-800 leading-relaxed" v-html="linkify(selectedAssignment?.description)"></div>
                    </div>
                    
                    <hr class="border-slate-100 dark:border-slate-700" />

                    <div v-if="selectedAssignment?.submissions[0]" class="space-y-4">
                        <div class="p-5 bg-blue-50 dark:bg-blue-900/20 rounded-2xl border border-blue-100 dark:border-blue-800 text-center">
                            <span class="text-blue-600 dark:text-blue-400 text-xs font-black uppercase tracking-widest block mb-4">You turned this in on {{ new Date(selectedAssignment.submissions[0].submitted_at).toLocaleDateString() }}</span>
                            
                            <div v-if="selectedAssignment.submissions[0].text_content" class="text-left bg-white dark:bg-slate-800 p-4 rounded-xl border border-blue-100 dark:border-blue-700 mb-4 shadow-sm">
                                <p class="text-[9px] font-black text-slate-400 uppercase mb-2">Text Content:</p>
                                <p class="text-xs text-slate-700 dark:text-slate-300 whitespace-pre-wrap leading-relaxed">{{ selectedAssignment.submissions[0].text_content }}</p>
                            </div>
                            <div v-if="getPaths(selectedAssignment.submissions[0].file_paths).length" class="text-left bg-white dark:bg-slate-800 p-4 rounded-xl border border-blue-100 dark:border-blue-700 shadow-sm">
                                <p class="text-[9px] font-black text-slate-400 uppercase mb-2">Attached Files:</p>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                    <div v-for="(path, index) in getPaths(selectedAssignment.submissions[0].file_paths)" :key="index" class="flex justify-between items-center p-2 bg-slate-50 dark:bg-slate-900 rounded-lg border border-slate-100 dark:border-slate-700">
                                        <span class="text-[10px] font-bold truncate w-32">Attachment {{ index + 1 }}</span>
                                        <div class="flex gap-2">
                                            <a :href="`/storage/${path}`" target="_blank" class="text-blue-600 text-[10px] font-black uppercase hover:underline">View</a>
                                            <a :href="`/storage/${path}`" download class="text-emerald-600 text-[10px] font-black uppercase hover:underline">Save</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div v-if="selectedAssignment.submissions[0].grade" class="p-5 bg-emerald-50 dark:bg-emerald-900/20 rounded-2xl border border-emerald-100 dark:border-emerald-800 shadow-sm">
                            <div class="flex items-center gap-2 mb-2 text-emerald-700 font-black uppercase text-xs tracking-widest">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path></svg> 
                                Graded: {{ selectedAssignment.submissions[0].grade }}/{{ selectedAssignment.points }}
                            </div>
                            <div class="text-xs text-emerald-800 dark:text-emerald-300 italic leading-relaxed">"{{ selectedAssignment.submissions[0].feedback }}"</div>
                        </div>
                        <div v-else class="flex justify-end">
                            <button @click="undoTurnIn" class="flex items-center gap-2 px-5 py-2.5 bg-white dark:bg-slate-800 border border-red-200 text-red-600 rounded-xl font-black text-[10px] uppercase tracking-widest hover:bg-red-50 transition shadow-sm">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path></svg> Undo Turn In
                            </button>
                        </div>
                    </div>

                    <form v-else @submit.prevent="submitWork" class="space-y-6 animate-in slide-in-from-bottom-4 duration-500">
                        <div class="space-y-2">
                            <label class="text-[10px] font-black uppercase text-slate-400 tracking-widest">Write Answer or Links (Optional)</label>
                            <textarea v-model="formSubmission.text_content" class="w-full bg-slate-50 dark:bg-slate-900 border-slate-200 dark:border-slate-700 rounded-xl p-4 text-sm h-32 focus:ring-2 focus:ring-blue-500 resize-none shadow-inner" placeholder="Enter your text response or URLs here..."></textarea>
                        </div>
                        
                        <div class="space-y-2">
                            <label class="text-[10px] font-black uppercase text-slate-400 tracking-widest">Attach Files</label>
                            <div class="bg-slate-50 dark:bg-slate-900/50 p-8 rounded-2xl border-2 border-dashed border-slate-200 dark:border-slate-700 text-center relative hover:border-blue-400 hover:bg-blue-50/50 transition-all group">
                                <input type="file" multiple @change="handleFileSelect" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer z-10" />
                                <div class="flex flex-col items-center gap-2 text-slate-400 group-hover:text-blue-500">
                                    <svg class="w-8 h-8 opacity-40 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path></svg>
                                    <p class="text-xs font-bold uppercase tracking-wider">Drag files or Click to Upload</p>
                                </div>
                            </div>
                        </div>
                        
                        <div v-if="formSubmission.files.length" class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <div v-for="(f,i) in formSubmission.files" :key="i" class="p-3 bg-white dark:bg-slate-700 rounded-xl border border-slate-100 dark:border-slate-600 flex justify-between items-center shadow-sm">
                                <span class="text-[10px] font-bold truncate w-3/4">{{ f.name }}</span>
                                <span class="text-[9px] font-black text-slate-400">{{ (f.size/1024).toFixed(0) }} KB</span>
                            </div>
                        </div>
                        
                        <div class="flex justify-between items-center px-1 mt-auto shrink-0">
                            <span class="text-[9px] font-black uppercase tracking-widest" :class="isOverSizeLimit ? 'text-red-600' : 'text-slate-400'">
                                Total Size: {{ formatSize(totalFileSize) }} / 15 MB
                            </span>
                        </div>
                        
                        <div class="pt-2 border-t border-slate-50 dark:border-slate-700">
                            <button :disabled="formSubmission.processing || isOverSizeLimit || (formSubmission.files.length === 0 && !formSubmission.text_content.trim())"
                                 class="w-full bg-blue-600 text-white text-[10px] font-black uppercase tracking-widest py-3 rounded-lg shadow-sm hover:bg-blue-500 transition-all active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed">
                                {{ isOverSizeLimit ? 'Size Limit Exceeded' : 'Turn In Task' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<style scoped>
.announcement-content :deep(iframe) {
    width: 100% !important;
    height: auto;
    aspect-ratio: 16 / 9;
    border-radius: 0.75rem;
    margin-top: 1rem;
    margin-bottom: 1rem;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
}
.announcement-content :deep(a) { color: #2563eb; text-decoration: underline; font-weight: 700; }
.announcement-content :deep(ul) { list-style-type: disc; padding-left: 1.5rem; margin: 0.5rem 0; }
.announcement-content :deep(ol) { list-style-type: decimal; padding-left: 1.5rem; margin: 0.5rem 0; }
</style>