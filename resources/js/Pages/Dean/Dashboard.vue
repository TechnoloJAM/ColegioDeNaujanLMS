<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import { AlertTriangle, Clock, Activity, ShieldCheck, GraduationCap, Users, BookOpen, Award, X } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import Modal from '@/Components/Modal.vue';

const props = defineProps({
    departmentName: String,
    deptStats: Object,
    slaRadar: Array,
    heatmap: Array,
    topPerforming: Array,
    activityPulse: Array
});

// Modal States for Analytics Widgets
const showAllHeatmap = ref(false);
const showAllSLA = ref(false);
const showAllPulse = ref(false);

// 5-Item Previews to keep the UI incredibly compact!
const previewHeatmap = computed(() => props.heatmap ? props.heatmap.slice(0, 5) : []);
const previewTop = computed(() => props.topPerforming ? props.topPerforming.slice(0, 5) : []);
const previewSLA = computed(() => props.slaRadar ? props.slaRadar.slice(0, 5) : []);
const previewPulse = computed(() => props.activityPulse ? props.activityPulse.slice(0, 5) : []);
</script>

<template>
    <Head title="Dean Oversight" />
    <AuthenticatedLayout>
        
        <!-- COMPACT HEADER -->
        <div class="max-w-[1400px] mx-auto px-3 sm:px-6 mb-4">
            <div class="bg-gradient-to-r from-slate-800 to-slate-900 rounded-xl p-4 sm:p-5 text-white shadow-sm flex justify-between items-center relative overflow-hidden">
                <div class="absolute top-0 right-0 w-32 h-32 bg-purple-500 opacity-20 rounded-full blur-3xl pointer-events-none"></div>
                <div class="relative z-10 flex items-center gap-3">
                    <div class="p-2 bg-white/10 backdrop-blur-sm rounded-lg border border-white/10 shrink-0">
                        <GraduationCap class="w-5 h-5 text-purple-400" />
                    </div>
                    <div>
                        <h1 class="text-lg sm:text-xl font-extrabold tracking-tight leading-none mb-1">Oversight Dashboard</h1>
                        <p class="text-slate-300 text-[10px] font-medium uppercase tracking-widest">Dept: <span class="font-black text-white">{{ departmentName }}</span></p>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="departmentName === 'Unassigned'" class="max-w-[1400px] mx-auto px-3 sm:px-6">
            <div class="bg-red-50 border border-red-200 text-red-700 p-6 rounded-xl text-center shadow-sm">
                <ShieldCheck class="w-8 h-8 mx-auto mb-2 text-red-500 opacity-80" />
                <h3 class="font-black uppercase tracking-widest mb-1 text-xs">Account Unlinked</h3>
                <p class="text-[10px] font-bold">You must be assigned to a Department by the Administrator before viewing data.</p>
            </div>
        </div>

        <div v-else class="max-w-[1400px] mx-auto px-3 sm:px-6 pb-12">
            
            <!-- VITALS ROW -->
            <div class="grid grid-cols-3 gap-3 sm:gap-4 mb-4 sm:mb-6">
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-3 sm:p-4 shadow-sm flex items-center gap-3">
                    <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-full bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0 border border-blue-100 dark:border-blue-800">
                        <Users class="w-4 h-4 sm:w-5 sm:h-5" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-[8px] sm:text-[9px] font-black uppercase tracking-widest text-slate-400 truncate">Total Faculty</p>
                        <p class="text-sm sm:text-lg font-black text-slate-900 dark:text-white leading-none mt-0.5">{{ deptStats.total_teachers }}</p>
                    </div>
                </div>
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-3 sm:p-4 shadow-sm flex items-center gap-3">
                    <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-full bg-purple-50 dark:bg-purple-900/30 text-purple-600 dark:text-purple-400 flex items-center justify-center shrink-0 border border-purple-100 dark:border-purple-800">
                        <BookOpen class="w-4 h-4 sm:w-5 sm:h-5" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-[8px] sm:text-[9px] font-black uppercase tracking-widest text-slate-400 truncate">Active Classes</p>
                        <p class="text-sm sm:text-lg font-black text-slate-900 dark:text-white leading-none mt-0.5">{{ deptStats.active_courses }}</p>
                    </div>
                </div>
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 p-3 sm:p-4 shadow-sm flex items-center gap-3">
                    <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-full bg-emerald-50 dark:bg-emerald-900/30 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0 border border-emerald-100 dark:border-emerald-800">
                        <GraduationCap class="w-4 h-4 sm:w-5 sm:h-5" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-[8px] sm:text-[9px] font-black uppercase tracking-widest text-slate-400 truncate">Total Students</p>
                        <p class="text-sm sm:text-lg font-black text-slate-900 dark:text-white leading-none mt-0.5">{{ deptStats.total_students }}</p>
                    </div>
                </div>
            </div>

            <!-- ANALYTICS 2x2 GRID -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-5 mb-4">
                
                <!-- 1. HIGH-FAILURE HEATMAP -->
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-red-200 dark:border-red-900/50 shadow-sm overflow-hidden flex flex-col h-full">
                    <div class="px-3 py-2 border-b border-red-100 dark:border-red-900/50 bg-red-50/50 dark:bg-red-900/20 flex justify-between items-center shrink-0">
                        <h3 class="text-[10px] font-black text-red-800 dark:text-red-400 uppercase tracking-widest flex items-center gap-1.5">
                            <AlertTriangle class="w-3.5 h-3.5 text-red-500" /> Heatmap
                        </h3>
                        <button v-if="heatmap.length > 5" @click="showAllHeatmap = true" class="text-[9px] font-black uppercase tracking-widest text-red-600 dark:text-red-400 hover:text-red-800 transition flex items-center gap-1">See All ({{ heatmap.length }})</button>
                    </div>
                    <div class="divide-y divide-slate-100 dark:divide-slate-700/50 p-1.5 flex-1 flex flex-col">
                        <div v-for="course in previewHeatmap" :key="course.id" class="p-2 flex flex-col hover:bg-slate-50 dark:hover:bg-slate-700/30 transition rounded-lg">
                            <div class="flex justify-between items-start mb-1">
                                <h4 class="text-xs font-bold text-slate-900 dark:text-white leading-tight truncate pr-2">{{ course.title }}</h4>
                                <span class="text-[11px] font-black text-red-600 dark:text-red-400 shrink-0">{{ course.failure_rate }}% Fail</span>
                            </div>
                            <div class="flex justify-between items-center text-[9px] font-bold text-slate-500 uppercase tracking-widest">
                                <span>Prof. {{ course.teacher }}</span>
                                <span>{{ course.failing_students }}/{{ course.total_students }} Stds</span>
                            </div>
                        </div>
                        <div v-if="heatmap.length === 0" class="p-6 my-auto text-center text-emerald-600 dark:text-emerald-500 text-[9px] font-black uppercase tracking-widest">
                            Healthy passing rates.
                        </div>
                    </div>
                </div>

                <!-- 2. HONOR ROLL (Top Performing) -->
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-emerald-200 dark:border-emerald-900/50 shadow-sm overflow-hidden flex flex-col h-full">
                    <div class="px-3 py-2 border-b border-emerald-100 dark:border-emerald-900/50 bg-emerald-50/50 dark:bg-emerald-900/20 flex justify-between items-center shrink-0">
                        <h3 class="text-[10px] font-black text-emerald-800 dark:text-emerald-500 uppercase tracking-widest flex items-center gap-1.5">
                            <Award class="w-3.5 h-3.5 text-emerald-500" /> Honor Roll
                        </h3>
                    </div>
                    <div class="divide-y divide-slate-100 dark:divide-slate-700/50 p-1.5 flex-1 flex flex-col">
                        <div v-for="course in previewTop" :key="course.id" class="p-2 flex flex-col hover:bg-slate-50 dark:hover:bg-slate-700/30 transition rounded-lg">
                            <div class="flex justify-between items-start mb-1">
                                <h4 class="text-xs font-bold text-slate-900 dark:text-white leading-tight truncate pr-2">{{ course.title }}</h4>
                                <span class="text-[11px] font-black text-emerald-600 dark:text-emerald-400 shrink-0">{{ course.average }}% Avg</span>
                            </div>
                            <div class="text-[9px] font-bold text-slate-500 uppercase tracking-widest">
                                Prof. {{ course.teacher }}
                            </div>
                        </div>
                        <div v-if="topPerforming.length === 0" class="p-6 my-auto text-center text-slate-400 dark:text-slate-500 text-[9px] font-black uppercase tracking-widest">
                            No classes ≥ 85% yet.
                        </div>
                    </div>
                </div>

                <!-- 3. GRADING SLA -->
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-amber-200 dark:border-amber-900/50 shadow-sm overflow-hidden flex flex-col h-full">
                    <div class="px-3 py-2 border-b border-amber-100 dark:border-amber-900/50 bg-amber-50/50 dark:bg-amber-900/20 flex justify-between items-center shrink-0">
                        <h3 class="text-[10px] font-black text-amber-800 dark:text-amber-500 uppercase tracking-widest flex items-center gap-1.5">
                            <Clock class="w-3.5 h-3.5 text-amber-500" /> Grading SLA
                        </h3>
                        <button v-if="slaRadar.length > 5" @click="showAllSLA = true" class="text-[9px] font-black uppercase tracking-widest text-amber-600 dark:text-amber-400 hover:text-amber-800 transition flex items-center gap-1">See All</button>
                    </div>
                    <div class="p-1.5 space-y-1 flex-1 flex flex-col">
                        <div v-for="(teacher, index) in previewSLA" :key="index" class="flex items-center justify-between p-2 hover:bg-slate-50 dark:hover:bg-slate-900 border border-transparent hover:border-slate-100 dark:hover:border-slate-700 rounded-lg transition">
                            <span class="text-[11px] font-bold text-slate-700 dark:text-slate-200 truncate pr-2">{{ teacher.teacher_name }}</span>
                            <span class="text-[8px] shrink-0 font-black uppercase tracking-widest bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-400 px-1.5 py-0.5 rounded">
                                {{ teacher.stale_count }} Late
                            </span>
                        </div>
                        <div v-if="slaRadar.length === 0" class="my-auto text-center py-6 text-emerald-600 dark:text-emerald-500 text-[9px] font-black uppercase tracking-widest">
                            All up to date!
                        </div>
                    </div>
                </div>

                <!-- 4. ACTIVITY PULSE -->
                <div class="bg-white dark:bg-slate-800 rounded-xl border border-orange-200 dark:border-orange-900/50 shadow-sm overflow-hidden flex flex-col h-full">
                    <div class="px-3 py-2 border-b border-orange-100 dark:border-orange-900/50 bg-orange-50/50 dark:bg-orange-900/20 flex justify-between items-center shrink-0">
                        <h3 class="text-[10px] font-black text-orange-800 dark:text-orange-500 uppercase tracking-widest flex items-center gap-1.5">
                            <Activity class="w-3.5 h-3.5 text-orange-500" /> Pulse
                        </h3>
                        <button v-if="activityPulse.length > 5" @click="showAllPulse = true" class="text-[9px] font-black uppercase tracking-widest text-orange-600 dark:text-orange-400 hover:text-orange-800 transition flex items-center gap-1">See All</button>
                    </div>
                    <div class="p-1.5 space-y-1 flex-1 flex flex-col">
                        <div v-for="(course, index) in previewPulse" :key="index" class="p-2 hover:bg-slate-50 dark:hover:bg-slate-900 border border-transparent hover:border-slate-100 dark:hover:border-slate-700 rounded-lg transition">
                            <div class="flex justify-between items-start mb-0.5">
                                <h4 class="text-[11px] font-bold text-slate-900 dark:text-white leading-tight truncate pr-2">{{ course.course }}</h4>
                                <span class="shrink-0 text-[8px] font-black uppercase tracking-widest bg-orange-100 text-orange-700 dark:bg-orange-900/50 dark:text-orange-400 px-1.5 py-0.5 rounded">
                                    {{ course.days_inactive === 'Never' ? 'Empty' : course.days_inactive + 'd' }}
                                </span>
                            </div>
                            <p class="text-[9px] text-slate-500 font-bold uppercase tracking-widest truncate">Prof. {{ course.teacher }}</p>
                        </div>
                        <div v-if="activityPulse.length === 0" class="my-auto text-center py-6 text-emerald-600 dark:text-emerald-500 text-[9px] font-black uppercase tracking-widest">
                            No stagnant classes.
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- ============================================== -->
        <!-- ANALYTICS MODALS (Heatmap, SLA, Pulse)         -->
        <!-- ============================================== -->

        <!-- MODAL: VIEW ALL HEATMAP -->
        <Modal :show="showAllHeatmap" @close="showAllHeatmap = false" maxWidth="lg">
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl flex flex-col max-h-[85vh] overflow-hidden">
                <div class="p-3 border-b border-red-100 dark:border-red-900/50 flex justify-between items-center bg-red-50/50 dark:bg-red-900/20 shrink-0">
                    <h2 class="text-xs font-black text-red-800 dark:text-red-400 uppercase tracking-widest flex items-center gap-1.5">
                        <AlertTriangle class="w-3.5 h-3.5 text-red-500"/> Full Heatmap
                    </h2>
                    <button @click="showAllHeatmap = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-1 rounded-full hover:bg-slate-200 dark:hover:bg-slate-700 transition">
                        <X class="w-4 h-4"/>
                    </button>
                </div>
                <div class="flex-1 overflow-y-auto custom-scrollbar divide-y divide-slate-100 dark:divide-slate-700 p-1.5">
                    <div v-for="course in heatmap" :key="course.id" class="p-3 flex flex-col sm:flex-row sm:items-center justify-between hover:bg-slate-50 dark:hover:bg-slate-700/30 transition rounded-lg gap-2">
                        <div class="min-w-0">
                            <h4 class="text-xs font-bold text-slate-900 dark:text-white truncate">{{ course.title }}</h4>
                            <p class="text-[9px] text-slate-500 font-bold mt-0.5 uppercase tracking-widest">Instructor: {{ course.teacher }}</p>
                        </div>
                        <div class="text-left sm:text-right shrink-0">
                            <span class="block text-sm font-black text-red-600 dark:text-red-400 leading-none mb-1">{{ course.failure_rate }}% Fail</span>
                            <span class="text-[8px] font-bold text-slate-500 uppercase tracking-widest">{{ course.failing_students }} of {{ course.total_students }} Std</span>
                        </div>
                    </div>
                </div>
            </div>
        </Modal>

        <!-- MODAL: VIEW ALL SLA -->
        <Modal :show="showAllSLA" @close="showAllSLA = false" maxWidth="md">
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl flex flex-col max-h-[85vh] overflow-hidden">
                <div class="p-3 border-b border-amber-100 dark:border-amber-900/50 flex justify-between items-center bg-amber-50/50 dark:bg-amber-900/20 shrink-0">
                    <h2 class="text-xs font-black text-amber-800 dark:text-amber-500 uppercase tracking-widest flex items-center gap-1.5">
                        <Clock class="w-3.5 h-3.5 text-amber-500"/> Full SLA Report
                    </h2>
                    <button @click="showAllSLA = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-1 rounded-full hover:bg-slate-200 dark:hover:bg-slate-700 transition">
                        <X class="w-4 h-4"/>
                    </button>
                </div>
                <div class="flex-1 overflow-y-auto custom-scrollbar divide-y divide-slate-100 dark:divide-slate-700 p-1.5">
                    <div v-for="(teacher, index) in slaRadar" :key="index" class="p-3 flex items-center justify-between hover:bg-slate-50 dark:hover:bg-slate-700/30 transition rounded-lg">
                        <span class="text-xs font-bold text-slate-700 dark:text-slate-200 truncate pr-2">{{ teacher.teacher_name }}</span>
                        <span class="text-[9px] font-black uppercase tracking-widest bg-amber-100 text-amber-700 dark:bg-amber-900/50 dark:text-amber-400 px-2 py-1 rounded shadow-inner border border-amber-200 dark:border-amber-800 shrink-0">
                            {{ teacher.stale_count }} Backlogged
                        </span>
                    </div>
                </div>
            </div>
        </Modal>

        <!-- MODAL: VIEW ALL PULSE -->
        <Modal :show="showAllPulse" @close="showAllPulse = false" maxWidth="md">
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl flex flex-col max-h-[85vh] overflow-hidden">
                <div class="p-3 border-b border-orange-100 dark:border-orange-900/50 flex justify-between items-center bg-orange-50/50 dark:bg-orange-900/20 shrink-0">
                    <h2 class="text-xs font-black text-orange-800 dark:text-orange-500 uppercase tracking-widest flex items-center gap-1.5">
                        <Activity class="w-3.5 h-3.5 text-orange-500"/> Full Pulse Report
                    </h2>
                    <button @click="showAllPulse = false" class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-1 rounded-full hover:bg-slate-200 dark:hover:bg-slate-700 transition">
                        <X class="w-4 h-4"/>
                    </button>
                </div>
                <div class="flex-1 overflow-y-auto custom-scrollbar divide-y divide-slate-100 dark:divide-slate-700 p-1.5">
                    <div v-for="(course, index) in activityPulse" :key="index" class="p-3 flex items-center justify-between hover:bg-slate-50 dark:hover:bg-slate-700/30 transition rounded-lg gap-2">
                        <div class="min-w-0">
                            <h4 class="text-xs font-bold text-slate-900 dark:text-white truncate">{{ course.course }}</h4>
                            <p class="text-[9px] text-slate-500 font-bold mt-0.5 uppercase tracking-widest truncate">Prof. {{ course.teacher }}</p>
                        </div>
                        <span class="shrink-0 text-[9px] font-black uppercase tracking-widest bg-orange-100 text-orange-700 dark:bg-orange-900/50 dark:text-orange-400 px-1.5 py-0.5 rounded border border-orange-200 dark:border-orange-800">
                            {{ course.days_inactive === 'Never' ? 'Empty' : course.days_inactive + 'd' }}
                        </span>
                    </div>
                </div>
            </div>
        </Modal>

    </AuthenticatedLayout>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(148, 163, 184, 0.3); border-radius: 10px; }
</style>