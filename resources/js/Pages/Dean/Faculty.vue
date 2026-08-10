<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Users, Building2 } from 'lucide-vue-next';

defineProps({
    departmentName: String,
    facultyRoster: Array
});
</script>

<template>
    <Head title="Department Faculty" />
    <AuthenticatedLayout>
        
        <div class="mb-4 pb-3 border-b border-slate-200 dark:border-slate-700 max-w-7xl mx-auto px-4 sm:px-6 flex items-end justify-between">
            <div>
                <h1 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
                    <Users class="w-6 h-6 text-blue-600 dark:text-blue-500" /> Department Faculty
                </h1>
                <p class="text-[10px] text-slate-500 dark:text-slate-400 font-bold uppercase tracking-widest mt-1">Instructor Roster</p>
            </div>
            <div class="text-[10px] font-black uppercase tracking-widest text-slate-500 flex items-center gap-1.5 bg-slate-100 dark:bg-slate-800 px-2 py-1 rounded border border-slate-200 dark:border-slate-700">
                <Building2 class="w-3.5 h-3.5" /> {{ departmentName }}
            </div>
        </div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6">
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-500 dark:text-slate-400">
                        <thead class="text-[9px] uppercase font-bold text-slate-400 bg-slate-50 dark:bg-slate-900/30 border-b border-slate-200 dark:border-slate-700">
                            <tr>
                                <th class="px-4 py-3">Instructor</th>
                                <th class="px-4 py-3 text-center">Active Classes</th>
                                <th class="px-4 py-3 text-center">Total Students</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-700">
                            <tr v-for="teacher in facultyRoster" :key="teacher.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/50 transition">
                                <td class="px-4 py-3 flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full bg-blue-100 dark:bg-blue-900/50 text-blue-600 dark:text-blue-400 flex items-center justify-center font-black text-xs shrink-0">
                                        {{ teacher.name.charAt(0) }}
                                    </div>
                                    <div class="min-w-0">
                                        <div class="font-bold text-slate-900 dark:text-white truncate">{{ teacher.name }}</div>
                                        <div class="text-[10px] opacity-80 truncate">{{ teacher.email }}</div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="bg-slate-100 dark:bg-slate-900 text-slate-700 dark:text-slate-300 px-2.5 py-1 rounded-md text-[10px] font-black">{{ teacher.active_classes }}</span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <span class="bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400 border border-blue-100 dark:border-blue-900 px-2.5 py-1 rounded-md text-[10px] font-black">{{ teacher.total_students }}</span>
                                </td>
                            </tr>
                            <tr v-if="facultyRoster.length === 0">
                                <td colspan="3" class="px-4 py-12 text-center text-slate-400 text-[10px] font-black uppercase tracking-widest">No faculty members assigned to this department.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </AuthenticatedLayout>
</template>