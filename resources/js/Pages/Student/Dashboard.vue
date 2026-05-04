<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    stats: Object,
    upcoming: Array,
    announcements: Array,
    remedial: Array,
    recommendations: Array
});

const joinForm = useForm({ enrollment_code: '' });

const quickJoin = (code) => {
    joinForm.enrollment_code = code;
    joinForm.post(route('student.courses.join'), {
        onSuccess: () => joinForm.reset(),
    });
};
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        
        <div class="bg-gradient-to-r from-blue-600 to-indigo-700 rounded-xl p-3 md:p-4 text-white shadow-sm mb-4 flex justify-between items-center gap-3 relative overflow-hidden">
            <div class="flex items-center gap-2.5 z-10">
                <div class="w-8 h-8 rounded-full bg-white/20 backdrop-blur-sm flex items-center justify-center text-sm font-bold border border-white/20 shrink-0">
                    {{ $page.props.auth.user.name.charAt(0) }}
                </div>
                <div class="min-w-0">
                    <h1 class="text-sm md:text-base font-extrabold truncate">Hi, {{ $page.props.auth.user.name.split(' ')[0] }}!</h1>
                    <p class="text-[9px] text-blue-100 uppercase tracking-widest font-semibold hidden sm:block">Welcome back</p>
                </div>
            </div>
            
            <div class="flex gap-2 z-10 shrink-0">
                <div class="bg-black/20 backdrop-blur-sm px-2.5 py-1 rounded-lg flex items-center gap-1.5 border border-white/10">
                    <span class="text-[9px] uppercase font-bold text-blue-100">Classes:</span>
                    <span class="text-xs font-black">{{ stats.courses }}</span>
                </div>
                <div class="bg-white text-blue-700 px-2.5 py-1 rounded-lg flex items-center gap-1.5 shadow-sm">
                    <span class="text-[9px] uppercase font-bold">To-Do:</span>
                    <span class="text-xs font-black">{{ stats.pending }}</span>
                </div>
            </div>
        </div>

        <div v-if="recommendations && recommendations.length > 0" class="mb-5">
            <div class="flex items-center gap-2 mb-2">
                <h2 class="text-[10px] font-black text-slate-500 uppercase tracking-widest">Academic Insights</h2>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                <div v-for="rec in recommendations" :key="rec.id" class="bg-white border border-slate-200 rounded-xl p-4 shadow-sm">
                    <span class="text-[8px] font-black uppercase text-red-600 bg-red-50 px-2 py-0.5 rounded">{{ rec.category }}</span>
                    <p class="mt-2 text-sm font-bold text-slate-800">{{ rec.recommendation_text }}</p>
                    <p class="mt-2 text-[10px] text-slate-500 italic">Reason: {{ rec.reasoning }}</p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 md:gap-4 items-start">
            <div class="md:col-span-1 lg:col-span-2 space-y-3 md:space-y-4">
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-sm">
                    <div class="px-3 py-2 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center bg-slate-50/50 dark:bg-slate-800/50">
                        <h3 class="text-[10px] font-black text-slate-800 dark:text-slate-200 uppercase tracking-widest flex items-center gap-1.5">
                            <svg class="w-3 h-3 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            Deadlines
                        </h3>
                        <Link :href="route('student.assignments')" class="text-[9px] text-blue-600 font-bold hover:underline">View All</Link>
                    </div>
                    
                    <div v-if="upcoming.length > 0" class="divide-y divide-slate-100 dark:divide-slate-700">
                        <Link v-for="task in upcoming" :key="task.id" :href="route('student.courses.show', { course: task.course_id, tab: 'assignments', target: task.id })" 
                              class="p-2.5 hover:bg-blue-50/50 dark:hover:bg-slate-700/30 flex items-center gap-2.5 transition cursor-pointer">
                            <div class="w-9 h-9 rounded bg-slate-100 dark:bg-slate-900 flex flex-col items-center justify-center shrink-0 border border-slate-200 dark:border-slate-700">
                                <span class="text-[7px] font-black uppercase leading-none">{{ new Date(task.due_date).toLocaleString('default', { month: 'short' }) }}</span>
                                <span class="text-xs font-black leading-none mt-0.5">{{ new Date(task.due_date).getDate() }}</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <h4 class="text-[11px] font-bold text-slate-900 dark:text-white truncate leading-tight">{{ task.title }}</h4>
                                <p class="text-[9px] font-medium text-slate-500 truncate">{{ task.course.title }}</p>
                            </div>
                            <svg class="w-3.5 h-3.5 text-slate-300 dark:text-slate-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                        </Link>
                    </div>
                    <div v-else class="p-4 text-center">
                        <p class="text-[10px] text-slate-500 font-medium">No upcoming deadlines.</p>
                    </div>
                </div>
            </div>

            <div class="space-y-3 md:space-y-4">
                <div class="bg-white dark:bg-slate-800 rounded-xl p-3 border border-slate-200 dark:border-slate-700 shadow-sm relative overflow-hidden">
                    <h3 class="text-[10px] font-black text-slate-800 dark:text-slate-200 uppercase tracking-widest mb-2 flex items-center gap-1.5">
                        <svg class="w-3 h-3 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                        Join Class
                    </h3>
                    
                    <div v-if="$page.props.flash && $page.props.flash.success" class="mb-2 p-1.5 bg-emerald-50 text-emerald-600 rounded text-[9px] font-bold uppercase tracking-widest border border-emerald-100 dark:bg-emerald-900/30 dark:border-emerald-800/50">
                        {{ $page.props.flash.success }}
                    </div>

                    <form @submit.prevent="joinForm.post(route('student.courses.join'), { onSuccess: () => joinForm.reset() })" class="flex flex-col gap-1.5">
                        <div class="flex gap-1.5">
                            <input v-model="joinForm.enrollment_code" type="text" placeholder="CODE" class="flex-1 py-1 px-2 h-7 rounded border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-900 text-[10px] font-mono uppercase tracking-widest focus:ring-1 focus:ring-blue-500">
                            <button :disabled="joinForm.processing || !joinForm.enrollment_code" class="bg-slate-900 dark:bg-blue-600 text-white px-2.5 h-7 rounded text-[9px] font-bold uppercase transition disabled:opacity-50">Join</button>
                        </div>
                        
                        <div v-if="joinForm.errors.enrollment_code" class="text-red-500 font-bold text-[9px] uppercase tracking-widest mt-1">
                            {{ joinForm.errors.enrollment_code }}
                        </div>
                    </form>
                </div>

                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-sm">
                    <div class="px-3 py-2 border-b border-slate-100 dark:border-slate-700 bg-slate-50/50 dark:bg-slate-800/50">
                        <h3 class="text-[10px] font-black text-slate-800 dark:text-slate-200 uppercase tracking-widest flex items-center gap-1.5">
                            <svg class="w-3 h-3 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5.882V19.24a1.76 1.76 0 01-3.417.592l-2.147-6.15M18 13a3 3 0 100-6M5.436 13.683A4.001 4.001 0 017 6h1.832c4.1 0 7.625-1.234 9.168-3v14c-1.543-1.766-5.067-3-9.168-3H7a3.988 3.988 0 01-1.564-.317z"></path></svg>
                            Fresh Updates
                        </h3>
                    </div>
                    
                    <div v-if="announcements.length > 0" class="divide-y divide-slate-100 dark:divide-slate-700">
                        <Link v-for="post in announcements" :key="post.id" :href="route('student.courses.show', post.course_id)" 
                              class="flex flex-col p-2.5 hover:bg-slate-50 dark:hover:bg-slate-700/30 transition cursor-pointer">
                            <div class="flex items-center justify-between gap-2 mb-0.5">
                                <span class="text-[7px] font-bold text-purple-600 dark:text-purple-400 uppercase tracking-widest bg-purple-50 dark:bg-purple-900/30 px-1.5 py-0.5 rounded truncate max-w-[70%]">
                                    {{ post.course.title }}
                                </span>
                                <span class="text-[8px] font-medium text-slate-500 truncate">{{ post.user.name.split(' ')[0] }}</span>
                            </div>
                            <h4 class="text-[11px] font-bold text-slate-900 dark:text-white leading-tight truncate">{{ post.title }}</h4>
                            <p class="text-[9px] text-slate-500 dark:text-slate-400 line-clamp-1 mt-0.5 leading-relaxed">{{ post.content.replace(/<\/?[^>]+(>|$)/g, "") }}</p>
                        </Link>
                    </div>
                    <div v-else class="p-4 text-center">
                        <p class="text-[10px] text-slate-500 font-medium">No new updates this week.</p>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.pb-safe { padding-bottom: env(safe-area-inset-bottom); }
</style>