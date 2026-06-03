<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

const props = defineProps({
    stats: Object,
    grading_queue: Array,
    upcoming_assignments: Array
});
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
        
        <div class="bg-gradient-to-r from-slate-800 to-slate-900 rounded-xl p-4 md:p-5 text-white shadow-sm mb-5 flex flex-col md:flex-row justify-between items-start md:items-center gap-4 relative overflow-hidden">
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

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 items-start">
            
            <div class="lg:col-span-2 space-y-5">
                
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-sm">
                    <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center bg-amber-50/30 dark:bg-amber-900/10">
                        <h3 class="text-xs font-black text-amber-800 dark:text-amber-500 uppercase tracking-wider flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            Needs Grading
                        </h3>
                        <span v-if="grading_queue.length > 0" class="text-[9px] font-bold bg-amber-100 dark:bg-amber-900/40 text-amber-700 dark:text-amber-400 px-2 py-0.5 rounded-full border border-amber-200 dark:border-amber-800">Priority</span>
                    </div>
                    
                    <div v-if="grading_queue.length > 0" class="divide-y divide-slate-100 dark:divide-slate-700">
                        <div v-for="item in grading_queue" :key="item.id" class="p-3 hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 group">
                            
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

                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-sm">
                    <div class="px-4 py-3 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center bg-slate-50/50 dark:bg-slate-800/50">
                        <h3 class="text-xs font-black text-slate-800 dark:text-slate-200 uppercase tracking-wider flex items-center gap-1.5">
                            <svg class="w-3.5 h-3.5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            Upcoming Deadlines
                        </h3>
                    </div>
                    
                    <div v-if="upcoming_assignments.length > 0" class="divide-y divide-slate-100 dark:divide-slate-700">
                        <Link v-for="task in upcoming_assignments" :key="task.id" :href="route('teacher.assignments.show', task.id)" class="p-3 hover:bg-slate-50 dark:hover:bg-slate-700/30 transition-colors flex items-center justify-between gap-3 group">
                            
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

            <div class="lg:col-span-1 space-y-5">
                
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
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                            </div>
                            <span class="text-xs font-bold text-slate-700 dark:text-slate-200 group-hover:text-emerald-700 dark:group-hover:text-emerald-300">Manage Active Courses ({{ stats.active_courses || 0 }})</span>
                        </Link>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>