<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Line, Doughnut } from 'vue-chartjs';
import { 
    Chart as ChartJS, CategoryScale, LinearScale, PointElement, LineElement, 
    Title, Tooltip, Legend, ArcElement, Filler 
} from 'chart.js';
import { 
    Users, BookOpen, Clock, AlertTriangle, ShieldCheck, TrendingUp, ChevronLeft, ChevronRight
} from 'lucide-vue-next';

ChartJS.register(
    CategoryScale, LinearScale, PointElement, LineElement, 
    Title, Tooltip, Legend, ArcElement, Filler
);

const props = defineProps({
    stats: Object,
    demographics: Object,
    chartData: Object,
    enrollmentTrend: Object,
    currentMonth: Number,
    currentYear: Number,
    monthName: String,
    recentCourses: Array
});

// CALENDAR-STYLE ARROW NAVIGATION
const navigateMonth = (direction) => {
    let m = props.currentMonth;
    let y = props.currentYear;

    if (direction === 'prev') {
        m--;
        if (m < 1) { m = 12; y--; }
    } else {
        m++;
        if (m > 12) { m = 1; y++; }
    }

    router.get(route('admin.dashboard'), { month: m, year: y }, { 
        preserveState: true, 
        preserveScroll: true 
    });
};

// REAL-TIME UPDATES
let pollInterval;
onMounted(() => {
    pollInterval = setInterval(() => {
        router.reload({ 
            data: { month: props.currentMonth, year: props.currentYear },
            only: ['stats', 'chartData', 'demographics', 'recentCourses'], 
            preserveScroll: true, 
            preserveState: true 
        });
    }, 10000);
});

onUnmounted(() => {
    clearInterval(pollInterval);
});

// BULLETPROOF CHART COMPUTED PROPERTY (Now tracks daily spikes)
const lineChartData = computed(() => {
    const safeData = props.chartData || props.enrollmentTrend || {};
    
    return {
        labels: safeData?.labels || [],
        datasets: [
            {
                label: 'New Accounts',
                backgroundColor: 'rgba(59, 130, 246, 0.1)', 
                borderColor: '#3b82f6', 
                pointBackgroundColor: '#ffffff',
                pointBorderColor: '#3b82f6',
                pointBorderWidth: 1.5,
                pointRadius: 1, 
                pointHoverRadius: 4,
                borderWidth: 1.5, 
                fill: false,
                tension: 0.3, // Lower tension makes daily spikes look sharper and "normal"
                data: safeData?.total || [],
            },
            {
                label: 'New Active',
                backgroundColor: 'rgba(16, 185, 129, 0.1)', 
                borderColor: '#10b981', 
                pointBackgroundColor: '#ffffff',
                pointBorderColor: '#10b981',
                pointBorderWidth: 1.5,
                pointRadius: 1, 
                pointHoverRadius: 4,
                borderWidth: 1.5, 
                fill: false,
                tension: 0.3, 
                data: safeData?.active || [],
            },
            {
                label: 'New Suspended',
                backgroundColor: 'rgba(239, 68, 68, 0.1)', 
                borderColor: '#ef4444', 
                pointBackgroundColor: '#ffffff',
                pointBorderColor: '#ef4444',
                pointBorderWidth: 1.5,
                pointRadius: 1, 
                pointHoverRadius: 4,
                borderWidth: 1.5, 
                fill: false,
                tension: 0.3, 
                data: safeData?.suspended || [],
            },
            {
                label: 'New Enrollments',
                backgroundColor: 'rgba(139, 92, 246, 0.15)', 
                borderColor: '#8b5cf6', 
                pointBackgroundColor: '#ffffff',
                pointBorderColor: '#8b5cf6',
                pointBorderWidth: 1.5,
                pointRadius: 1, 
                pointHoverRadius: 4,
                borderWidth: 1.5, 
                fill: true,
                tension: 0.3, 
                data: safeData?.enrollments || safeData?.data || [],
            }
        ]
    };
});

const lineChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: {
            backgroundColor: '#1e293b',
            padding: 6, 
            titleFont: { size: 9, family: 'Inter' },
            bodyFont: { size: 10, weight: 'bold', family: 'Inter' },
            displayColors: true, 
            callbacks: {
                label: function(context) { return ` ${context.dataset.label}: ${context.parsed.y}`; }
            }
        }
    },
    scales: {
        y: { 
            beginAtZero: true, 
            ticks: { precision: 0, color: '#94a3b8', font: { size: 8 }, stepSize: 1 }, // Ensures whole numbers only
            grid: { color: '#f1f5f9', drawBorder: false }
        },
        x: { 
            ticks: { 
                color: '#94a3b8',
                font: { size: 7 },
                maxTicksLimit: 10 
            }, 
            grid: { display: false, drawBorder: false } 
        }
    },
    interaction: { intersect: false, mode: 'index' }
};

// Compact Doughnut Chart Configuration
const donutData = computed(() => ({
    labels: props.demographics?.labels || [],
    datasets: [{
        backgroundColor: ['#3b82f6', '#8b5cf6', '#10b981'],
        borderWidth: 0,
        data: props.demographics?.data || [],
    }]
}));

const donutOptions = { 
    responsive: true, 
    maintainAspectRatio: false,
    cutout: '80%', 
    plugins: { legend: { position: 'right', labels: { usePointStyle: true, padding: 8, font: { size: 8 } } } }
};
</script>

<template>
    <Head title="Admin Overview" />
    <AuthenticatedLayout>
        <div class="max-w-screen-2xl mx-auto space-y-2 sm:space-y-4 -mt-3">
            
            <div class="flex justify-between items-center gap-2 border-b border-slate-100 dark:border-slate-800 pb-2 px-2 sm:px-0">
                <h1 class="text-sm sm:text-lg font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-1.5">
                    <ShieldCheck class="w-4 h-4 text-blue-600" /> Admin Overview
                </h1>
                
                <Link v-if="stats?.pendingMaterials > 0" :href="route('admin.materials')" class="flex items-center gap-1 bg-amber-50 text-amber-700 px-2 py-1 rounded border border-amber-200 shadow-sm transition hover:bg-amber-100 dark:bg-amber-900/30 dark:border-amber-800 dark:text-amber-400 shrink-0">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                    <span class="text-[7px] sm:text-[9px] font-black uppercase tracking-widest">{{ stats.pendingMaterials }} Reviews</span>
                </Link>
            </div>

            <div v-if="stats" class="grid grid-cols-2 lg:grid-cols-4 gap-1.5 sm:gap-3 px-2 sm:px-0">
                
                <div class="bg-white dark:bg-slate-800 p-2 sm:p-3 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm flex justify-between items-center">
                    <div class="min-w-0">
                        <p class="text-[7px] sm:text-[8px] font-black text-slate-400 uppercase tracking-widest mb-0.5 truncate">Users (Active/Total)</p>
                        <h3 class="text-sm sm:text-base font-black text-slate-900 dark:text-white leading-none truncate">
                            {{ stats.activeUsers || 0 }} <span class="text-[8px] text-slate-400 font-bold">/ {{ stats.totalUsers || 0 }}</span>
                        </h3>
                        <p v-if="stats.suspendedUsers > 0" class="text-[7px] font-black text-red-500 uppercase tracking-widest mt-1">
                            {{ stats.suspendedUsers }} Suspended
                        </p>
                    </div>
                    <Users class="w-4 h-4 text-blue-500/50 shrink-0 ml-1" />
                </div>

                <div class="bg-white dark:bg-slate-800 p-2 sm:p-3 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm flex justify-between items-center">
                    <div class="min-w-0">
                        <p class="text-[7px] sm:text-[8px] font-black text-slate-400 uppercase tracking-widest mb-0.5 truncate">Active Courses</p>
                        <h3 class="text-sm sm:text-base font-black text-slate-900 dark:text-white leading-none truncate">
                            {{ stats.activeCourses || 0 }} <span class="text-[8px] text-slate-400 font-bold">/ {{ stats.totalCourses || 0 }}</span>
                        </h3>
                    </div>
                    <BookOpen class="w-4 h-4 text-emerald-500/50 shrink-0 ml-1" />
                </div>

                <div class="bg-white dark:bg-slate-800 p-2 sm:p-3 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm flex justify-between items-center">
                    <div class="min-w-0">
                        <p class="text-[7px] sm:text-[8px] font-black text-slate-400 uppercase tracking-widest mb-0.5 truncate">Enrollments</p>
                        <h3 class="text-sm sm:text-base font-black text-slate-900 dark:text-white leading-none">{{ stats.totalEnrollments || 0 }}</h3>
                    </div>
                    <TrendingUp class="w-4 h-4 text-purple-500/50 shrink-0 ml-1" />
                </div>

                <div class="bg-white dark:bg-slate-800 p-2 sm:p-3 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm flex justify-between items-center">
                    <div class="min-w-0">
                        <p class="text-[7px] sm:text-[8px] font-black text-orange-500 uppercase tracking-widest mb-0.5 truncate">Pending Enrolls</p>
                        <h3 class="text-sm sm:text-base font-black text-slate-900 dark:text-white leading-none">{{ stats.pendingEnrollments || 0 }}</h3>
                    </div>
                    <AlertTriangle class="w-4 h-4 text-orange-500/50 shrink-0 ml-1" />
                </div>

            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-2 sm:gap-4 px-2 sm:px-0">
                <div class="lg:col-span-2 bg-white dark:bg-slate-800 p-2 sm:p-3 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col">
                    <div class="flex justify-between items-center mb-1">
                        <div class="flex items-center gap-2">
                            <h3 class="text-[9px] sm:text-[10px] font-black text-slate-900 dark:text-white uppercase tracking-tight">Daily Growth</h3>
                            <div class="flex flex-wrap gap-1.5 ml-2 hidden sm:flex">
                                <span class="flex items-center gap-0.5"><span class="w-2 h-2 rounded-full bg-blue-500"></span><span class="text-[7px] font-bold text-slate-500 uppercase">Accounts</span></span>
                                <span class="flex items-center gap-0.5"><span class="w-2 h-2 rounded-full bg-emerald-500"></span><span class="text-[7px] font-bold text-slate-500 uppercase">Active</span></span>
                                <span class="flex items-center gap-0.5"><span class="w-2 h-2 rounded-full bg-red-500"></span><span class="text-[7px] font-bold text-slate-500 uppercase">Suspended</span></span>
                                <span class="flex items-center gap-0.5"><span class="w-2 h-2 rounded-full bg-purple-500"></span><span class="text-[7px] font-bold text-slate-500 uppercase">Enrolls</span></span>
                            </div>
                        </div>
                        
                        <div class="flex items-center bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded shadow-sm shrink-0">
                            <button @click="navigateMonth('prev')" class="p-1 text-slate-500 hover:text-slate-800 dark:hover:text-white transition-colors">
                                <ChevronLeft class="w-3 h-3" />
                            </button>
                            <span class="px-1 text-[8px] font-black uppercase tracking-widest text-slate-700 dark:text-slate-300 min-w-[65px] text-center">
                                {{ monthName || 'Loading...' }}
                            </span>
                            <button @click="navigateMonth('next')" class="p-1 text-slate-500 hover:text-slate-800 dark:hover:text-white transition-colors">
                                <ChevronRight class="w-3 h-3" />
                            </button>
                        </div>
                    </div>
                    <div class="flex-1 min-h-[120px] sm:min-h-[160px] w-full relative mt-2">
                        <Line :data="lineChartData" :options="lineChartOptions" />
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-800 p-2 sm:p-3 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col">
                    <h3 class="text-[9px] sm:text-[10px] font-black text-slate-900 dark:text-white uppercase tracking-tight mb-1">User Roles</h3>
                    <div class="flex-1 flex justify-center items-center min-h-[90px] sm:min-h-[120px]">
                        <Doughnut :data="donutData" :options="donutOptions" />
                    </div>
                </div>
            </div>

            <div class="px-2 sm:px-0 pb-6 sm:pb-0">
                <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
                    <div class="p-2 border-b border-slate-100 dark:border-slate-700/50 flex justify-between items-center bg-slate-50/50 dark:bg-slate-900/20">
                        <h3 class="text-[8px] sm:text-[9px] font-black uppercase tracking-widest text-slate-800 dark:text-slate-200 flex items-center gap-1">
                            <Clock class="w-3 h-3 text-blue-500" /> Recent Classes
                        </h3>
                        <Link :href="route('admin.courses.index')" class="text-[7px] sm:text-[8px] font-black text-blue-600 dark:text-blue-400 hover:text-blue-800 uppercase tracking-widest transition-colors flex items-center gap-0.5">
                            View All <span class="text-slate-400">&rarr;</span>
                        </Link>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-white dark:bg-slate-800 border-b border-slate-100 dark:border-slate-700 uppercase text-[7px] sm:text-[8px] font-black text-slate-400 tracking-widest">
                                <tr>
                                    <th class="px-2 py-1.5 whitespace-nowrap">Class Code / Name</th>
                                    <th class="px-2 py-1.5 hidden sm:table-cell">Instructor</th>
                                    <th class="px-2 py-1.5 text-right">Status</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                                <tr v-for="course in recentCourses" :key="course?.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                                    <td class="px-2 py-1.5 max-w-[120px] sm:max-w-none">
                                        <div class="flex items-center gap-1.5">
                                            <span class="text-[7px] sm:text-[8px] font-mono text-slate-400 bg-slate-100 dark:bg-slate-900 px-1 rounded shrink-0">{{ course?.enrollment_code }}</span>
                                            <span class="text-[10px] sm:text-[11px] font-bold text-slate-900 dark:text-white truncate">{{ course?.title }}</span>
                                        </div>
                                    </td>
                                    <td class="px-2 py-1.5 hidden sm:table-cell text-[10px] sm:text-[11px] font-bold text-slate-600 dark:text-slate-300">
                                        {{ course?.teacher?.name || 'Unassigned' }}
                                    </td>
                                    <td class="px-2 py-1.5 text-right">
                                        <span v-if="course?.is_published" class="text-[7px] sm:text-[8px] font-black uppercase tracking-widest text-emerald-600">Active</span>
                                        <span v-else class="text-[7px] sm:text-[8px] font-black uppercase tracking-widest text-slate-400">Draft</span>
                                    </td>
                                </tr>
                                <tr v-if="!recentCourses || recentCourses.length === 0">
                                    <td colspan="3" class="p-4 text-center text-slate-400 text-[8px] font-bold uppercase tracking-widest">No classes created yet.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </AuthenticatedLayout>
</template>