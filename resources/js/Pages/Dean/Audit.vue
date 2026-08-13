<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { ShieldCheck, Building2, Eye } from 'lucide-vue-next';

defineProps({
    departmentName: String,
    auditCourses: Array
});
</script>

<template>
    <Head title="Course Audit" />
    <AuthenticatedLayout>
        
        <div class="mb-4 pb-3 border-b border-slate-200 dark:border-slate-700 max-w-7xl mx-auto px-4 sm:px-6 flex items-end justify-between">
            <div>
                <h1 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
                    <ShieldCheck class="w-6 h-6 text-purple-600 dark:text-purple-500" /> Course Audit
                </h1>
                <p class="text-[10px] text-slate-500 dark:text-slate-400 font-bold uppercase tracking-widest mt-1">Silent Oversight Tool</p>
            </div>
            <div class="text-[10px] font-black uppercase tracking-widest text-slate-500 flex items-center gap-1.5 bg-slate-100 dark:bg-slate-800 px-2 py-1 rounded border border-slate-200 dark:border-slate-700">
                <Building2 class="w-3.5 h-3.5" /> {{ departmentName }}
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                
                <div v-for="course in auditCourses" :key="course.id" class="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm hover:shadow-md transition-shadow flex flex-col justify-between group">
                    <div class="mb-4">
                        <h3 class="font-black text-slate-900 dark:text-white text-base leading-tight mb-2">{{ course.title }}</h3>
                        <div class="flex items-center gap-2 text-[10px] font-bold text-slate-500 uppercase tracking-widest">
                            <span class="bg-slate-100 dark:bg-slate-900 px-2 py-1 rounded">Prof. {{ course.teacher }}</span>
                            <span class="bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 px-2 py-1 rounded border border-blue-100 dark:border-blue-900">{{ course.students }} Enrolled</span>
                        </div>
                    </div>
                    <Link :href="route('dean.courses.audit', course.id)" class="w-full flex items-center justify-center gap-2 py-2 bg-slate-50 dark:bg-slate-700 text-slate-600 dark:text-slate-300 rounded-lg text-[10px] font-black uppercase tracking-widest hover:bg-purple-600 hover:text-white transition shadow-sm border border-slate-200 dark:border-slate-600">
                        <Eye class="w-4 h-4" /> Enter Silent Audit
                    </Link>
                </div>

            </div>

            <div v-if="auditCourses.length === 0" class="text-center py-16 bg-white dark:bg-slate-800 border border-dashed border-slate-200 dark:border-slate-700 rounded-xl mt-4">
                <ShieldCheck class="w-10 h-10 mx-auto text-slate-300 dark:text-slate-600 mb-3" />
                <p class="text-slate-400 text-[10px] font-black uppercase tracking-widest">No active courses available for auditing.</p>
            </div>
        </div>

    </AuthenticatedLayout>
</template>