<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Megaphone, AlertTriangle, Sparkles, Send, X } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    stats: Object,
    grading_queue: Array,
    upcoming_assignments: Array,
    broadcast_courses: Array,
    classroom_health: Array,
    ai_insight: String
});

const broadcastForm = useForm({
    course_id: '',
    title: 'Quick Update',
    content: ''
});

const submitBroadcast = () => {
    if (!broadcastForm.course_id) {
        alert('Please select a class first.');
        return;
    }
    broadcastForm.post(route('teacher.announcements.store', broadcastForm.course_id), {
        preserveScroll: true,
        onSuccess: () => {
            broadcastForm.reset('content');
            alert('Broadcast sent successfully!');
        }
    });
};

// --- MODAL STATES ---
const showAllHealth = ref(false);
const showAllGrading = ref(false);
const showAllUpcoming = ref(false);

// --- 5-ITEM PREVIEW LIMITS ---
const previewHealth = computed(() => props.classroom_health ? props.classroom_health.slice(0, 5) : []);
const previewGrading = computed(() => props.grading_queue ? props.grading_queue.slice(0, 5) : []);
const previewUpcoming = computed(() => props.upcoming_assignments ? props.upcoming_assignments.slice(0, 5) : []);

</script>

<template>
    <Head title="Overview" />

    <AuthenticatedLayout>
        <div v-if="stats && stats.rejectedMaterials > 0" class="max-w-7xl mx-auto px-4 sm:px-6 mb-4">
            <div class="bg-red-50 border-l-4 border-red-500 p-4 rounded shadow-sm">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" /></svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-bold text-red-700">
                            Action Required: You have {{ stats.rejectedMaterials }} material(s) rejected by the Administrator. Please update and resubmit.
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- MAIN WELCOME HEADER -->
        <div class="bg-gradient-to-r from-slate-800 to-slate-900 rounded-xl p-4 md:p-5 text-white shadow-sm mb-3 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 relative overflow-hidden">
            <div class="absolute top-0 right-0 -mt-4 -mr-4 w-32 h-32 bg-blue-500 opacity-20 rounded-full blur-2xl pointer-events-none"></div>

            <div class="relative z-10 flex items-center gap-3">
                <div class="p-2 bg-white/10 backdrop-blur-sm rounded-lg shrink-0 border border-white/10">
                    <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"></path></svg>
                </div>
                <div>
                    <h1 class="text-lg md:text-xl font-extrabold tracking-tight leading-none mb-1">
                        Instructor Overview
                    </h1>
                    <p class="text-slate-300 text-xs font-medium">Hello, {{ $page.props.auth.user.name.split(' ')[0] }}! Here is your classroom summary.</p>
                </div>
            </div>
            
            <div class="flex gap-3 relative z-10 w-full md:w-auto">
                <div class="bg-white/5 backdrop-blur-sm border border-white/10 px-3 py-2 rounded-lg shadow-sm flex items-center gap-3 flex-1 md:flex-none">
                    <svg class="w-4 h-4 text-emerald-400 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                    <div>
                        <p class="text-[9px] uppercase font-bold text-slate-400 tracking-widest leading-none mb-0.5">Students</p>
                        <p class="text-sm font-black leading-none">{{ stats.total_students || 0 }}</p>
                    </div>
                </div>
                <div class="bg-white/5 backdrop-blur-sm border border-white/10 px-3 py-2 rounded-lg shadow-sm flex items-center gap-3 flex-1 md:flex-none">
                    <svg class="w-4 h-4 text-amber-400 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    <div>
                        <p class="text-[9px] uppercase font-bold text-slate-400 tracking-widest leading-none mb-0.5">To Grade</p>
                        <p class="text-sm font-black leading-none text-amber-400">{{ stats.pending_submissions || 0 }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- AI CLASSROOM INSIGHTS BANNER -->
        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 border border-blue-200 dark:bg-slate-800/50 dark:border-blue-900/50 rounded-xl p-3 md:p-4 mb-5 shadow-sm flex items-center gap-3 relative overflow-hidden">
            <div class="bg-blue-600 text-white w-8 h-8 rounded-full flex items-center justify-center shrink-0 shadow-md">
                <Sparkles class="w-4 h-4" />
            </div>
            <div>
                <p class="text-[9px] font-black uppercase tracking-widest text-blue-600 dark:text-blue-400 mb-0.5">AI Insights</p>
                <p class="text-xs sm:text-sm font-bold text-slate-800 dark:text-slate-200 leading-tight">
                    {{ ai_insight }}
                </p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 items-start">
            
            <!-- LEFT COLUMN (Main Activity) -->
            <div class="lg:col-span-2 space-y-5">
                
                <!-- CLASSROOM HEALTH / AT-RISK ROSTER -->
                <div v-if="classroom_health && classroom_health.length > 0" class="bg-white dark:bg-slate-800 rounded-xl border border-red-200 dark:border-red-900/50 overflow-hidden shadow-sm">
                    <div class="px-4 py-3 border-b border-red-100 dark:border-red-900/50 flex justify-between items-center bg-red-50/50 dark:bg-red-900/20">
                        <h3 class="text-xs font-black text-red-800 dark:text-red-400 uppercase tracking-wider flex items-center gap-1.5">
                            <AlertTriangle class="w-3.5 h-3.5 text-red-500" />
                            At-Risk Roster
                        </h3>
                        <div class="flex items-center gap-2">
                            <span class="text-[9px] font-bold bg-red-100 dark:bg-red-900/60 text-red-700 dark:text-red-400 px-2 py-0.5 rounded-full border border-red-200 dark:border-red-800">Requires Intervention</span>
                            <button v-if="classroom_health.length > 5" @click="showAllHealth = true" class="text-[9px] font-black uppercase tracking-widest text-red-600 dark:text-red-400 hover:underline">View All ({{ classroom_health.length }})</button>
                        </div>
                    </div>
                    
                    <div class="divide-y divide-slate-100 dark:divide-slate-700">
                        <Link v-for="course in previewHealth" :key="course.id" 
                              :href="`${route('teacher.gradebook.index', course.id)}?sort=avg_asc`"
                              class="p-3 sm:p-4 hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors flex items-center justify-between gap-3 group">
                            
                            <div class="min-w-0 flex-1">
                                <h4 class="text-xs sm:text-sm font-bold text-slate-900 dark:text-white truncate group-hover:text-blue-600 transition-colors">{{ course.title }}</h4>
                            </div>

                            <div class="flex flex-wrap items-center justify-end gap-1.5 sm:gap-2 shrink-0">
                                <span v-if="course.failing_count > 0" class="flex items-center gap-1 text-[9px] font-black uppercase tracking-widest bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400 px-2 py-1 rounded shadow-sm border border-red-200 dark:border-red-800">
                                    {{ course.failing_count }} Failing
                                </span>
                                <span v-if="course.missing_tasks > 0" class="flex items-center gap-1 text-[9px] font-black uppercase tracking-widest bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-400 px-2 py-1 rounded shadow-sm border border-orange-200 dark:border-orange-800">
                                    {{ course.missing_tasks }} Missing Tasks
                                </span>
                                <span class="hidden sm:inline-flex text-[9px] font-black uppercase tracking-widest bg-blue-50 text-blue-600 dark:bg-blue-900/40 dark:text-blue-400 px-2 py-1 rounded border border-blue-200 dark:border-blue-800 transition group-hover:bg-blue-600 group-hover:text-white">
                                    Review &rarr;
                                </span>
                            </div>
                        </Link>
                    </div>
                </div>

                <!-- NEEDS GRADING CARD -->
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-sm">
                    <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center bg-amber-50/30 dark:bg-amber-900/10">
                        <h3 class="text-xs font-black text-amber-800 dark:text-amber-500 uppercase tracking-wider flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            Needs Grading
                        </h3>
                        <div class="flex items-center gap-2">
                            <span v-if="grading_queue.length > 0" class="text-[9px] font-bold bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-400 px-2 py-0.5 rounded-full border border-amber-200 dark:border-amber-800">Priority</span>
                            <button v-if="grading_queue.length > 5" @click="showAllGrading = true" class="text-[9px] font-black uppercase tracking-widest text-amber-600 dark:text-amber-400 hover:underline">View All ({{ grading_queue.length }})</button>
                        </div>
                    </div>
                    
                    <div v-if="previewGrading.length > 0" class="divide-y divide-slate-100 dark:divide-slate-700">
                        <div v-for="item in previewGrading" :key="item.id" class="p-3 hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 group">
                            <div class="flex items-center gap-3 min-w-0 w-full">
                                <div class="w-10 h-10 rounded-lg bg-amber-50 dark:bg-amber-900/20 text-amber-600 dark:text-amber-400 flex flex-col items-center justify-center shrink-0 border border-amber-100 dark:border-amber-900/30">
                                    <span class="text-sm font-black leading-none">{{ item.ungraded_count }}</span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <h4 class="text-xs font-bold text-slate-900 dark:text-white truncate group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">{{ item.title }}</h4>
                                    <p class="text-[10px] font-medium text-slate-500 truncate mt-0.5">{{ item.course }}</p>
                                </div>
                            </div>
                            <Link :href="route('teacher.assignments.show', { assignment: item.id, source: 'global' })" 
                                  class="shrink-0 w-full sm:w-auto text-center text-[10px] font-bold text-white bg-blue-600 px-3 py-1.5 rounded-md hover:bg-blue-500 shadow-sm transition flex items-center justify-center gap-1">
                                Grade Now
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                            </Link>
                        </div>
                    </div>
                    
                    <div v-else class="p-8 text-center flex flex-col items-center justify-center gap-2">
                        <div class="w-10 h-10 bg-emerald-50 dark:bg-emerald-900/20 text-emerald-500 rounded-full flex items-center justify-center mb-1 border border-emerald-100 dark:border-emerald-900/30">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                        </div>
                        <h4 class="text-xs font-bold text-slate-900 dark:text-white">All caught up!</h4>
                        <p class="text-[10px] text-slate-500 max-w-xs mx-auto leading-tight">There are no pending submissions to grade right now.</p>
                    </div>
                </div>

                <!-- UPCOMING DEADLINES CARD -->
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-sm">
                    <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center bg-slate-50/50 dark:bg-slate-800/50">
                        <h3 class="text-xs font-black text-slate-800 dark:text-slate-200 uppercase tracking-wider flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            Upcoming Deadlines
                        </h3>
                        <button v-if="upcoming_assignments.length > 5" @click="showAllUpcoming = true" class="text-[9px] font-black uppercase tracking-widest text-blue-600 dark:text-blue-400 hover:underline">View All ({{ upcoming_assignments.length }})</button>
                    </div>
                    
                    <div v-if="previewUpcoming.length > 0" class="divide-y divide-slate-100 dark:divide-slate-700">
                        <Link v-for="task in previewUpcoming" :key="task.id" :href="route('teacher.assignments.show', task.id)" class="p-3 hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors flex items-center justify-between gap-3 group">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-8 h-8 rounded bg-slate-100 dark:bg-slate-900 text-slate-600 dark:text-slate-300 flex flex-col items-center justify-center shrink-0 border border-slate-200 dark:border-slate-700 group-hover:border-blue-300 dark:group-hover:border-blue-700 group-hover:bg-blue-50 dark:group-hover:bg-blue-900/30 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                                    <span class="text-[8px] font-black uppercase tracking-widest leading-none">{{ new Date(task.due_date).toLocaleString('default', { month: 'short' }) }}</span>
                                    <span class="text-xs font-black leading-none mt-0.5">{{ new Date(task.due_date).getDate() }}</span>
                                </div>
                                <div class="min-w-0">
                                    <h4 class="text-xs font-bold text-slate-900 dark:text-white truncate group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">{{ task.title }}</h4>
                                    <p class="text-[10px] font-medium text-slate-500 truncate mt-0.5">{{ task.course.title }}</p>
                                </div>
                            </div>
                            <svg class="w-4 h-4 text-slate-300 dark:text-slate-600 group-hover:text-blue-500 transition-transform group-hover:translate-x-1 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                        </Link>
                    </div>
                    <div v-else class="p-6 text-center text-[10px] text-slate-500 font-medium">No upcoming deadlines scheduled.</div>
                </div>

            </div>

            <!-- RIGHT COLUMN (Widgets) -->
            <div class="lg:col-span-1 space-y-5">
                
                <!-- QUICK BROADCAST WIDGET -->
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-sm">
                    <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700 bg-blue-50/50 dark:bg-blue-900/10">
                        <h3 class="text-xs font-black text-blue-800 dark:text-blue-500 uppercase tracking-wider flex items-center gap-1.5">
                            <Megaphone class="w-3.5 h-3.5 text-blue-500" />
                            Quick Broadcast
                        </h3>
                    </div>
                    <div class="p-3">
                        <form @submit.prevent="submitBroadcast" class="flex flex-col gap-3">
                            <select v-model="broadcastForm.course_id" class="w-full text-[10px] font-bold bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white rounded-lg p-2 focus:ring-2 focus:ring-blue-500 shadow-sm cursor-pointer" required>
                                <option value="" disabled selected>Select a Class...</option>
                                <option v-for="c in broadcast_courses" :key="c.id" :value="c.id">{{ c.title }}</option>
                            </select>
                            <textarea v-model="broadcastForm.content" class="w-full text-xs bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white rounded-lg p-2 h-20 resize-none focus:ring-2 focus:ring-blue-500 shadow-sm" placeholder="Write a quick announcement..." required></textarea>
                            <button type="submit" :disabled="broadcastForm.processing || !broadcastForm.course_id || !broadcastForm.content" class="w-full flex items-center justify-center gap-1.5 bg-blue-600 hover:bg-blue-500 text-white font-black text-[10px] uppercase tracking-widest py-2 rounded-lg shadow-sm transition disabled:opacity-50">
                                <Send class="w-3.5 h-3.5" /> Broadcast Now
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- QUICK ACTIONS CARD -->
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-sm">
                    <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50">
                        <h3 class="text-xs font-black text-slate-800 dark:text-slate-200 uppercase tracking-wider flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                            Quick Actions
                        </h3>
                    </div>
                    
                    <div class="p-3 space-y-2">
                        <Link :href="route('teacher.courses.create')" class="flex items-center gap-3 p-2.5 rounded-lg border border-slate-200 dark:border-slate-700 hover:border-blue-500 hover:bg-blue-50 dark:hover:bg-slate-700 transition group">
                            <div class="bg-blue-100 dark:bg-blue-900/40 p-1.5 rounded text-blue-600 dark:text-blue-400 group-hover:bg-blue-600 group-hover:text-white transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                            </div>
                            <span class="text-xs font-bold text-slate-700 dark:text-slate-200 group-hover:text-blue-700 dark:group-hover:text-blue-300">Create New Course</span>
                        </Link>

                        <Link :href="route('calendar.index')" class="flex items-center gap-3 p-2.5 rounded-lg border border-slate-200 dark:border-slate-700 hover:border-purple-500 hover:bg-purple-50 dark:hover:bg-slate-700 transition group">
                            <div class="bg-purple-100 dark:bg-purple-900/40 p-1.5 rounded text-purple-600 dark:text-purple-400 group-hover:bg-purple-600 group-hover:text-white transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            </div>
                            <span class="text-xs font-bold text-slate-700 dark:text-slate-200 group-hover:text-purple-700 dark:group-hover:text-purple-300">View Academic Calendar</span>
                        </Link>
                        
                        <Link :href="route('teacher.courses.index')" class="flex items-center gap-3 p-2.5 rounded-lg border border-slate-200 dark:border-slate-700 hover:border-emerald-500 hover:bg-emerald-50 dark:hover:bg-slate-700 transition group">
                            <div class="bg-emerald-100 dark:bg-emerald-900/40 p-1.5 rounded text-emerald-600 dark:text-emerald-400 group-hover:bg-emerald-600 group-hover:text-white transition">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012-2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                            </div>
                            <span class="text-xs font-bold text-slate-700 dark:text-slate-200 group-hover:text-emerald-700 dark:group-hover:text-emerald-300">Manage Active Courses</span>
                        </Link>
                    </div>
                </div>

            </div>
        </div>

        <!-- MODAL: ALL AT-RISK ROSTER -->
        <Modal :show="showAllHealth" @close="showAllHealth = false" maxWidth="lg">
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl flex flex-col max-h-[85vh] overflow-hidden">
                <div class="p-4 border-b border-red-100 dark:border-red-900/50 flex justify-between items-center bg-red-50/50 dark:bg-red-900/20 shrink-0">
                    <h2 class="text-sm font-black text-red-800 dark:text-red-400 uppercase tracking-tight flex items-center gap-2">
                        <AlertTriangle class="w-4 h-4 text-red-500"/>
                        All At-Risk Classes
                    </h2>
                    <button @click="showAllHealth = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-1.5 rounded-full hover:bg-slate-200 dark:hover:bg-slate-700 transition">
                        <X class="w-4 h-4"/>
                    </button>
                </div>
                <div class="flex-1 overflow-y-auto custom-scrollbar divide-y divide-slate-100 dark:divide-slate-700 p-2">
                    <Link v-for="course in classroom_health" :key="course.id" 
                            :href="`${route('teacher.gradebook.index', course.id)}?sort=avg_asc`"
                            @click="showAllHealth = false"
                            class="p-4 hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors flex items-center justify-between gap-3 rounded-lg group">
                        
                        <div class="min-w-0 flex-1">
                            <h4 class="text-sm font-bold text-slate-900 dark:text-white truncate group-hover:text-blue-600 transition-colors">{{ course.title }}</h4>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <span v-if="course.failing_count > 0" class="flex items-center gap-1 text-[9px] font-black uppercase tracking-widest bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400 px-2 py-1 rounded shadow-sm border border-red-200 dark:border-red-800">
                                {{ course.failing_count }} Failing
                            </span>
                            <span v-if="course.missing_tasks > 0" class="flex items-center gap-1 text-[9px] font-black uppercase tracking-widest bg-orange-100 text-orange-700 dark:bg-orange-900/40 dark:text-orange-400 px-2 py-1 rounded shadow-sm border border-orange-200 dark:border-orange-800">
                                {{ course.missing_tasks }} Missing
                            </span>
                        </div>
                    </Link>
                </div>
            </div>
        </Modal>

        <!-- MODAL: ALL NEEDS GRADING -->
        <Modal :show="showAllGrading" @close="showAllGrading = false" maxWidth="lg">
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl flex flex-col max-h-[85vh] overflow-hidden">
                <div class="p-4 border-b border-amber-100 dark:border-amber-900/50 flex justify-between items-center bg-amber-50/50 dark:bg-amber-900/20 shrink-0">
                    <h2 class="text-sm font-black text-amber-800 dark:text-amber-500 uppercase tracking-tight flex items-center gap-2">
                        <svg class="w-4 h-4 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        All Submissions To Grade
                    </h2>
                    <button @click="showAllGrading = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-1.5 rounded-full hover:bg-slate-200 dark:hover:bg-slate-700 transition">
                        <X class="w-4 h-4"/>
                    </button>
                </div>
                <div class="flex-1 overflow-y-auto custom-scrollbar divide-y divide-slate-100 dark:divide-slate-700 p-2">
                    <div v-for="item in grading_queue" :key="item.id" class="p-3 hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 group rounded-lg">
                        <div class="flex items-center gap-3 min-w-0 w-full">
                            <div class="w-10 h-10 rounded-lg bg-amber-50 dark:bg-amber-900/20 text-amber-600 dark:text-amber-400 flex flex-col items-center justify-center shrink-0 border border-amber-100 dark:border-amber-900/30">
                                <span class="text-sm font-black leading-none">{{ item.ungraded_count }}</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <h4 class="text-sm font-bold text-slate-900 dark:text-white truncate group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">{{ item.title }}</h4>
                                <p class="text-[10px] font-medium text-slate-500 truncate mt-0.5">{{ item.course }}</p>
                            </div>
                        </div>
                        <Link :href="route('teacher.assignments.show', { assignment: item.id, source: 'global' })" 
                                @click="showAllGrading = false"
                                class="shrink-0 w-full sm:w-auto text-center text-[10px] font-bold text-white bg-blue-600 px-3 py-1.5 rounded-md hover:bg-blue-500 shadow-sm transition flex items-center justify-center gap-1">
                            Grade Now
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                        </Link>
                    </div>
                </div>
            </div>
        </Modal>

        <!-- MODAL: ALL UPCOMING DEADLINES -->
        <Modal :show="showAllUpcoming" @close="showAllUpcoming = false" maxWidth="lg">
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl flex flex-col max-h-[85vh] overflow-hidden">
                <div class="p-4 border-b border-blue-100 dark:border-blue-900/50 flex justify-between items-center bg-blue-50/50 dark:bg-blue-900/20 shrink-0">
                    <h2 class="text-sm font-black text-blue-800 dark:text-blue-500 uppercase tracking-tight flex items-center gap-2">
                        <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        All Upcoming Deadlines
                    </h2>
                    <button @click="showAllUpcoming = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-1.5 rounded-full hover:bg-slate-200 dark:hover:bg-slate-700 transition">
                        <X class="w-4 h-4"/>
                    </button>
                </div>
                <div class="flex-1 overflow-y-auto custom-scrollbar divide-y divide-slate-100 dark:divide-slate-700 p-2">
                    <Link v-for="task in upcoming_assignments" :key="task.id" 
                          :href="route('teacher.assignments.show', task.id)" 
                          @click="showAllUpcoming = false"
                          class="p-3 hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors flex items-center justify-between gap-3 group rounded-lg">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-10 h-10 rounded bg-slate-100 dark:bg-slate-900 text-slate-600 dark:text-slate-300 flex flex-col items-center justify-center shrink-0 border border-slate-200 dark:border-slate-700 group-hover:border-blue-300 dark:group-hover:border-blue-700 group-hover:bg-blue-50 dark:group-hover:bg-blue-900/30 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                                <span class="text-[9px] font-black uppercase tracking-widest leading-none">{{ new Date(task.due_date).toLocaleString('default', { month: 'short' }) }}</span>
                                <span class="text-sm font-black leading-none mt-0.5">{{ new Date(task.due_date).getDate() }}</span>
                            </div>
                            <div class="min-w-0">
                                <h4 class="text-sm font-bold text-slate-900 dark:text-white truncate group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">{{ task.title }}</h4>
                                <p class="text-xs font-medium text-slate-500 truncate mt-0.5">{{ task.course.title }}</p>
                            </div>
                        </div>
                        <svg class="w-4 h-4 text-slate-300 dark:text-slate-600 group-hover:text-blue-500 transition-transform group-hover:translate-x-1 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                    </Link>
                </div>
            </div>
        </Modal>

    </AuthenticatedLayout>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(148, 163, 184, 0.2); border-radius: 10px; }
</style>