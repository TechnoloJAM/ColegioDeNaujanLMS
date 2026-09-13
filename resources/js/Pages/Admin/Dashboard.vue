<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ref, computed, onMounted, nextTick } from 'vue';
import { Line, Doughnut, Bar } from 'vue-chartjs';
import { 
    Chart as ChartJS, CategoryScale, LinearScale, PointElement, LineElement, 
    BarElement, Title, Tooltip, Legend, ArcElement, Filler 
} from 'chart.js';
import { 
    Users, BookOpen, Activity, Hourglass, Zap, Info, ShieldCheck, 
    ChevronLeft, ChevronRight, RefreshCw, BarChart3,
    History, UserPlus, Megaphone, Settings, ChevronDown, X, AlertTriangle, ChevronRightSquare
} from 'lucide-vue-next';

// Registered BarElement for the new chart
ChartJS.register(
    CategoryScale, LinearScale, PointElement, LineElement, 
    BarElement, Title, Tooltip, Legend, ArcElement, Filler
);

const props = defineProps({
    stats: Object,
    demographics: Object,
    chartData: Object,
    coursePopulation: Object,
    enrollmentTrend: Object,
    currentMonth: Number,
    currentYear: Number,
    monthName: String,
    actionItems: Array,
    activityLogs: Array
});

// ==========================================
// FIX: Force Layout Recalculation on SPA Load
// ==========================================
onMounted(() => {
    nextTick(() => {
        // This triggers a silent resize event to force Chart.js and Tailwind grids 
        // to snap into the correct mobile layout without requiring a manual page refresh.
        setTimeout(() => {
            window.dispatchEvent(new Event('resize'));
        }, 50);
    });
});

// ==========================================
// Modal State and Forms
// ==========================================
const isCreateModalOpen = ref(false);
const isBroadcastModalOpen = ref(false);

const createForm = useForm({
    name: '',
    email: '',
    password: '',
    role: 'student'
});

const broadcastForm = useForm({
    subject: '',
    message: '',
    target: 'all'
});

const submitCreate = () => {
    createForm.post(route('admin.users.store'), {
        onSuccess: () => {
            isCreateModalOpen.value = false;
            createForm.reset();
        }
    });
};

const submitBroadcast = () => {
    broadcastForm.post(route('admin.broadcast'), {
        onSuccess: () => {
            isBroadcastModalOpen.value = false;
            broadcastForm.reset();
        }
    });
};

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

const isRefreshing = ref(false);

const refreshData = () => {
    isRefreshing.value = true;
    router.reload({ 
        only: ['stats', 'chartData', 'demographics', 'actionItems', 'coursePopulation', 'activityLogs'], 
        preserveScroll: true, 
        preserveState: true,
        onFinish: () => {
            setTimeout(() => { isRefreshing.value = false; }, 500);
        }
    });
};

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
                tension: 0.3, 
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
            ticks: { precision: 0, color: '#94a3b8', font: { size: 8 }, stepSize: 1 }, 
            grid: { color: '#f1f5f9', drawBorder: false }
        },
        x: { 
            ticks: { color: '#94a3b8', font: { size: 7 }, maxTicksLimit: 10 }, 
            grid: { display: false, drawBorder: false } 
        }
    },
    interaction: { intersect: false, mode: 'index' }
};

// Ensure 4th color (Amber) is ready for the "Dean" role
const donutData = computed(() => ({
    labels: props.demographics?.labels || [],
    datasets: [{
        backgroundColor: ['#3b82f6', '#8b5cf6', '#10b981', '#f59e0b', '#ef4444'], // Blue, Purple, Emerald, Amber (Dean), Red
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

// Bar Chart Pagination Logic (10 items max)
const barPage = ref(0);
const barItemsPerPage = 10;

const maxBarPage = computed(() => {
    if (!props.coursePopulation?.labels) return 0;
    return Math.max(0, Math.ceil(props.coursePopulation.labels.length / barItemsPerPage) - 1);
});

const nextBarPage = () => { if (barPage.value < maxBarPage.value) barPage.value++; };
const prevBarPage = () => { if (barPage.value > 0) barPage.value--; };

const barChartData = computed(() => {
    const startIndex = barPage.value * barItemsPerPage;
    const endIndex = startIndex + barItemsPerPage;
    
    return {
        labels: (props.coursePopulation?.labels || []).slice(startIndex, endIndex),
        datasets: [{
            label: 'Enrolled Students',
            backgroundColor: '#3b82f6',
            hoverBackgroundColor: '#2563eb',
            borderRadius: 4,
            data: (props.coursePopulation?.data || []).slice(startIndex, endIndex),
        }]
    };
});

const barChartOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
        legend: { display: false },
        tooltip: {
            backgroundColor: '#1e293b',
            padding: 8,
            titleFont: { size: 10, family: 'Inter' },
            bodyFont: { size: 11, weight: 'bold', family: 'Inter' },
            displayColors: false,
        }
    },
    scales: {
        y: {
            beginAtZero: true,
            ticks: { precision: 0, color: '#94a3b8', font: { size: 9 }, stepSize: 1 },
            grid: { color: '#f1f5f9', drawBorder: false }
        },
        x: {
            ticks: { 
                color: '#94a3b8', 
                font: { size: 8 },
                callback: function(value) {
                    let label = this.getLabelForValue(value);
                    return label.length > 15 ? label.substr(0, 15) + '...' : label;
                }
            },
            grid: { display: false, drawBorder: false }
        }
    }
};
</script>

<template>
    <Head title="Admin Command Center" />
    <AuthenticatedLayout>
        
        <div class="max-w-screen-2xl mx-auto space-y-3 sm:space-y-4 w-full px-2 sm:px-4 min-w-0 pb-10 overflow-hidden relative">
            
            <div class="flex justify-between items-center gap-2 border-b border-slate-100 dark:border-slate-800 pb-2">
                <h1 class="text-sm sm:text-lg font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-1.5 truncate">
                    <ShieldCheck class="w-4 h-4 text-blue-600 shrink-0" /> <span class="truncate">Admin Command</span>
                </h1>
                
                <div class="flex items-center gap-2 shrink-0">
                    
                    <!-- Quick Action Dropdown -->
                    <div class="relative group">
                        <button class="flex items-center gap-1 bg-blue-600 text-white px-2.5 py-1 rounded border border-blue-700 shadow-sm transition hover:bg-blue-500 text-[9px] font-black uppercase tracking-widest">
                            <Zap class="w-3 h-3" /> <span class="hidden sm:inline">Quick Action</span> <ChevronDown class="w-3 h-3 ml-0.5" />
                        </button>
                        <!-- Dropdown Menu -->
                        <div class="absolute right-0 top-full mt-1 w-48 bg-white dark:bg-slate-800 rounded-lg shadow-xl border border-slate-200 dark:border-slate-700 opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all z-[990] overflow-hidden flex flex-col py-1">
                            <button @click="isCreateModalOpen = true" class="w-full text-left flex items-center gap-2 px-3 py-2 text-[10px] font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition">
                                <UserPlus class="w-3.5 h-3.5 text-blue-500" /> Create Account
                            </button>
                            <button @click="isBroadcastModalOpen = true" class="w-full text-left flex items-center gap-2 px-3 py-2 text-[10px] font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition">
                                <Megaphone class="w-3.5 h-3.5 text-amber-500" /> Broadcast Update
                            </button>
                        </div>
                    </div>

                    <button 
                        @click="refreshData" 
                        :disabled="isRefreshing"
                        class="flex items-center gap-1.5 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 px-2.5 py-1 rounded border border-slate-200 dark:border-slate-700 shadow-sm transition hover:bg-slate-50 dark:hover:bg-slate-700 text-[9px] font-black uppercase tracking-widest disabled:opacity-50"
                    >
                        <RefreshCw class="w-3 h-3" :class="{'animate-spin text-slate-500': isRefreshing}" />
                        <span class="hidden sm:inline">{{ isRefreshing ? 'Updating...' : 'Refresh Data' }}</span>
                        <span class="sm:hidden">{{ isRefreshing ? '...' : 'Refresh' }}</span>
                    </button>
                </div>
            </div>

            <!-- ULTRA COMPACT 4-CARD METRICS GRID -->
            <div class="grid grid-cols-2 xl:grid-cols-4 gap-1.5 sm:gap-3 w-full min-w-0">
                <!-- CARD 1 -->
                <div class="bg-white dark:bg-slate-800 p-2 sm:p-3 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col gap-1 sm:gap-1.5 justify-center min-w-0">
                    <div class="flex justify-between items-center border-b border-slate-100 dark:border-slate-700/50 pb-1 relative">
                        <div class="flex items-center gap-1 min-w-0">
                            <span class="text-[7px] sm:text-[9px] font-black text-slate-400 uppercase tracking-widest truncate">Users</span>
                            <div class="group flex items-center z-50">
                                <Info class="w-2.5 h-2.5 sm:w-3 sm:h-3 text-slate-400 cursor-help hover:text-blue-500 transition-colors hidden sm:block" />
                                <div class="absolute top-full -left-1 sm:left-1/2 sm:-translate-x-1/2 mt-2.5 w-[140px] sm:w-48 p-2 bg-slate-900 dark:bg-slate-700 text-white text-[9px] font-medium rounded-lg shadow-xl opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity text-left sm:text-center leading-relaxed z-[100]">
                                    Total system accounts. "Active" means fully verified. "Pending" means missing ID or verification.
                                </div>
                            </div>
                        </div>
                        <Users class="w-3 h-3 sm:w-3.5 sm:h-3.5 text-blue-500/50 shrink-0" />
                    </div>
                    <div class="flex items-end justify-between mt-0.5 sm:mt-1">
                        <h3 class="text-sm sm:text-xl font-black text-slate-900 dark:text-white leading-none">{{ (stats?.activeMembers || 0) + (stats?.pendingOnboarding || 0) + (stats?.suspendedUsers || 0) }}</h3>
                        <span class="text-[6px] sm:text-[8px] font-bold text-slate-400 uppercase tracking-widest">Total</span>
                    </div>
                    <div class="flex flex-wrap gap-0.5 sm:gap-1 mt-0.5 sm:mt-1">
                        <span class="text-[6px] sm:text-[8px] font-black px-1 sm:px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400 shrink-0">{{ stats?.activeMembers || 0 }} Act</span>
                        <span class="text-[6px] sm:text-[8px] font-black px-1 sm:px-1.5 py-0.5 rounded bg-amber-50 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400 shrink-0">{{ stats?.pendingOnboarding || 0 }} Pnd</span>
                        <span class="text-[6px] sm:text-[8px] font-black px-1 sm:px-1.5 py-0.5 rounded bg-red-50 text-red-600 dark:bg-red-900/30 dark:text-red-400 shrink-0">{{ stats?.suspendedUsers || 0 }} Sus</span>
                    </div>
                </div>

                <!-- CARD 2 -->
                <div class="bg-white dark:bg-slate-800 p-2 sm:p-3 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col gap-1 sm:gap-1.5 justify-center min-w-0">
                    <div class="flex justify-between items-center border-b border-slate-100 dark:border-slate-700/50 pb-1 relative">
                        <div class="flex items-center gap-1 min-w-0">
                            <span class="text-[7px] sm:text-[9px] font-black text-slate-400 uppercase tracking-widest truncate">Activity</span>
                            <div class="group flex items-center z-50">
                                <Info class="w-2.5 h-2.5 sm:w-3 sm:h-3 text-slate-400 cursor-help hover:text-indigo-500 transition-colors hidden sm:block" />
                                <div class="absolute top-full -left-1 sm:left-1/2 sm:-translate-x-1/2 mt-2.5 w-[140px] sm:w-48 p-2 bg-slate-900 dark:bg-slate-700 text-white text-[9px] font-medium rounded-lg shadow-xl opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity text-left sm:text-center leading-relaxed z-[100]">
                                    System usage. Shows total assignments submitted this month, and unique students active in the last 7 days.
                                </div>
                            </div>
                        </div>
                        <Activity class="w-3 h-3 sm:w-3.5 sm:h-3.5 text-indigo-500/50 shrink-0" />
                    </div>
                    <div class="flex items-end justify-between mt-0.5 sm:mt-1">
                        <h3 class="text-sm sm:text-xl font-black text-slate-900 dark:text-white leading-none">{{ stats?.submissionsProcessed || 0 }}</h3>
                        <span class="text-[6px] sm:text-[8px] font-bold text-slate-400 uppercase tracking-widest">Subs</span>
                    </div>
                    <div class="flex flex-wrap gap-0.5 sm:gap-1 mt-0.5 sm:mt-1">
                        <span class="text-[6px] sm:text-[8px] font-black px-1 sm:px-1.5 py-0.5 rounded bg-blue-50 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400 shrink-0">{{ stats?.activeLearners || 0 }} Act(7d)</span>
                        <span class="text-[6px] sm:text-[8px] font-black px-1 sm:px-1.5 py-0.5 rounded bg-indigo-50 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400 shrink-0 flex items-center gap-0.5"><Zap class="w-2 h-2 sm:w-2.5 sm:h-2.5"/> {{ stats?.aiInterventions || 0 }} AI</span>
                    </div>
                </div>

                <!-- CARD 3 -->
                <div class="bg-white dark:bg-slate-800 p-2 sm:p-3 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col gap-1 sm:gap-1.5 justify-center min-w-0">
                    <div class="flex justify-between items-center border-b border-slate-100 dark:border-slate-700/50 pb-1 relative">
                        <div class="flex items-center gap-1 min-w-0">
                            <span class="text-[7px] sm:text-[9px] font-black text-slate-400 uppercase tracking-widest truncate">Classes</span>
                            <div class="group flex items-center z-50">
                                <Info class="w-2.5 h-2.5 sm:w-3 sm:h-3 text-slate-400 cursor-help hover:text-emerald-500 transition-colors hidden sm:block" />
                                <div class="absolute top-full -right-1 sm:left-1/2 sm:-translate-x-1/2 mt-2.5 w-[140px] sm:w-48 p-2 bg-slate-900 dark:bg-slate-700 text-white text-[9px] font-medium rounded-lg shadow-xl opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity text-right sm:text-center leading-relaxed z-[100]">
                                    A "Healthy" course had materials updated within 14 days. "Stagnant" courses are abandoned.
                                </div>
                            </div>
                        </div>
                        <BookOpen class="w-3 h-3 sm:w-3.5 sm:h-3.5 text-emerald-500/50 shrink-0" />
                    </div>
                    <div class="flex items-end justify-between mt-0.5 sm:mt-1">
                        <h3 class="text-sm sm:text-xl font-black text-slate-900 dark:text-white leading-none">{{ (stats?.healthyCourses || 0) + (stats?.stagnantCourses || 0) }}</h3>
                        <span class="text-[6px] sm:text-[8px] font-bold text-slate-400 uppercase tracking-widest">Pubs</span>
                    </div>
                    <div class="flex flex-wrap gap-0.5 sm:gap-1 mt-0.5 sm:mt-1">
                        <span class="text-[6px] sm:text-[8px] font-black px-1 sm:px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400 shrink-0">{{ stats?.healthyCourses || 0 }} OK</span>
                        <span class="text-[6px] sm:text-[8px] font-black px-1 sm:px-1.5 py-0.5 rounded shrink-0 transition-colors" 
                              :class="stats?.stagnantCourses > 0 ? 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400 animate-pulse' : 'bg-slate-50 text-slate-500 dark:bg-slate-900/50'">
                            {{ stats?.stagnantCourses || 0 }} Idle
                        </span>
                    </div>
                </div>

                <!-- CARD 4 -->
                <div class="p-2 sm:p-3 rounded-lg border shadow-sm flex flex-col gap-1 sm:gap-1.5 justify-center transition-colors min-w-0"
                     :class="stats?.criticalBottlenecks > 0 ? 'border-orange-300 dark:border-orange-800/50 bg-orange-50/50 dark:bg-orange-900/10' : 'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800'">
                    <div class="flex justify-between items-center border-b pb-1 relative" 
                         :class="stats?.criticalBottlenecks > 0 ? 'border-orange-200 dark:border-orange-800/50' : 'border-slate-100 dark:border-slate-700/50'">
                        <div class="flex items-center gap-1 min-w-0">
                            <span class="text-[7px] sm:text-[9px] font-black uppercase tracking-widest truncate" 
                                  :class="stats?.criticalBottlenecks > 0 ? 'text-orange-600 dark:text-orange-500' : 'text-slate-400'">Pending</span>
                            <div class="group flex items-center z-50">
                                <Info class="w-2.5 h-2.5 sm:w-3 sm:h-3 cursor-help transition-colors hidden sm:block" :class="stats?.criticalBottlenecks > 0 ? 'text-orange-500 hover:text-orange-700' : 'text-slate-400 hover:text-slate-600'" />
                                <div class="absolute top-full -right-1 sm:left-1/2 sm:-translate-x-1/2 mt-2.5 w-[140px] sm:w-48 p-2 bg-slate-900 dark:bg-slate-700 text-white text-[9px] font-medium rounded-lg shadow-xl opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity text-right sm:text-center leading-relaxed z-[100]">
                                    Queue aging monitor. Counts enrollments and materials pending your approval for > 48 hours.
                                </div>
                            </div>
                        </div>
                        <Hourglass class="w-3 h-3 sm:w-3.5 sm:h-3.5 shrink-0" :class="stats?.criticalBottlenecks > 0 ? 'text-orange-500' : 'text-slate-400'" />
                    </div>
                    <div class="flex items-end justify-between mt-0.5 sm:mt-1">
                        <h3 class="text-sm sm:text-xl font-black leading-none" 
                            :class="stats?.criticalBottlenecks > 0 ? 'text-orange-700 dark:text-orange-400' : 'text-slate-900 dark:text-white'">
                            {{ stats?.criticalBottlenecks || 0 }}
                        </h3>
                        <span class="text-[6px] sm:text-[8px] font-bold uppercase tracking-widest" :class="stats?.criticalBottlenecks > 0 ? 'text-orange-600 dark:text-orange-400' : 'text-slate-400'">Over 48h</span>
                    </div>
                    <div class="flex mt-0.5 sm:mt-1">
                        <span class="text-[6px] sm:text-[8px] font-black px-1 sm:px-1.5 py-0.5 rounded w-full text-center truncate" 
                              :class="stats?.criticalBottlenecks > 0 ? 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400' : 'bg-slate-100 text-slate-500 dark:bg-slate-700 dark:text-slate-400'">
                            Needs Action
                        </span>
                    </div>
                </div>
            </div>

            <!-- CHARTS SECTION -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-3 sm:gap-4 w-full min-w-0">
                <div class="lg:col-span-2 bg-white dark:bg-slate-800 p-3 sm:p-4 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col min-w-0">
                    <div class="flex justify-between items-center mb-2">
                        <div class="flex items-center gap-2 min-w-0">
                            <h3 class="text-[10px] sm:text-xs font-black text-slate-900 dark:text-white uppercase tracking-tight truncate">Daily Growth</h3>
                            <div class="flex flex-wrap gap-2 md:gap-3 ml-1 sm:ml-3">
                                <span class="flex items-center gap-1"><span class="w-2 h-2 sm:w-2.5 sm:h-2.5 rounded-full bg-blue-500"></span><span class="text-[8px] sm:text-[9px] font-bold text-slate-500 uppercase">Accounts</span></span>
                                <span class="flex items-center gap-1 hidden sm:flex"><span class="w-2 h-2 sm:w-2.5 sm:h-2.5 rounded-full bg-emerald-500"></span><span class="text-[8px] sm:text-[9px] font-bold text-slate-500 uppercase">Active</span></span>
                                <span class="flex items-center gap-1 hidden md:flex"><span class="w-2 h-2 sm:w-2.5 sm:h-2.5 rounded-full bg-red-500"></span><span class="text-[8px] sm:text-[9px] font-bold text-slate-500 uppercase">Suspended</span></span>
                                <span class="flex items-center gap-1"><span class="w-2 h-2 sm:w-2.5 sm:h-2.5 rounded-full bg-purple-500"></span><span class="text-[8px] sm:text-[9px] font-bold text-slate-500 uppercase">Enrolls</span></span>
                            </div>
                        </div>
                        
                        <div class="flex items-center bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded shadow-sm shrink-0">
                            <button @click="navigateMonth('prev')" class="p-1 sm:p-1.5 text-slate-500 hover:text-slate-800 dark:hover:text-white transition-colors">
                                <ChevronLeft class="w-3 h-3 sm:w-4 sm:h-4" />
                            </button>
                            <span class="px-1.5 sm:px-3 text-[8px] sm:text-[10px] font-black uppercase tracking-widest text-slate-700 dark:text-slate-300 min-w-[70px] sm:min-w-[90px] text-center truncate">
                                {{ monthName || 'Loading...' }}
                            </span>
                            <button @click="navigateMonth('next')" class="p-1 sm:p-1.5 text-slate-500 hover:text-slate-800 dark:hover:text-white transition-colors">
                                <ChevronRight class="w-3 h-3 sm:w-4 sm:h-4" />
                            </button>
                        </div>
                    </div>
                    <div class="flex-1 min-h-[180px] sm:min-h-[220px] w-full relative mt-2 overflow-hidden">
                        <Line :data="lineChartData" :options="lineChartOptions" />
                    </div>
                </div>

                <div class="bg-white dark:bg-slate-800 p-3 sm:p-4 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col min-w-0">
                    <h3 class="text-[10px] sm:text-xs font-black text-slate-900 dark:text-white uppercase tracking-tight mb-2 truncate">User Roles</h3>
                    <div class="flex-1 flex justify-center items-center min-h-[150px] sm:min-h-[200px] relative overflow-hidden">
                        <Doughnut :data="donutData" :options="donutOptions" />
                    </div>
                </div>
            </div>

            <!-- COURSE POPULATION BAR CHART -->
            <div class="w-full min-w-0">
                <div class="bg-white dark:bg-slate-800 p-3 sm:p-4 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col w-full overflow-hidden">
                    <div class="flex justify-between items-center border-b border-slate-100 dark:border-slate-700/50 pb-2 mb-3">
                        <div class="flex items-center gap-1.5 sm:gap-2">
                            <BarChart3 class="w-4 h-4 sm:w-5 sm:h-5 text-blue-500" />
                            <h3 class="text-[10px] sm:text-xs font-black text-slate-900 dark:text-white uppercase tracking-widest">Published Course Population</h3>
                        </div>
                        
                        <!-- Bar Chart Pagination Controls -->
                        <div class="flex items-center bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded shadow-sm shrink-0">
                            <button @click="prevBarPage" :disabled="barPage === 0" class="p-1 sm:p-1.5 text-slate-500 hover:text-slate-800 dark:hover:text-white transition-colors disabled:opacity-30 disabled:cursor-not-allowed">
                                <ChevronLeft class="w-3 h-3 sm:w-4 sm:h-4" />
                            </button>
                            <span class="px-1.5 sm:px-3 text-[8px] sm:text-[10px] font-black uppercase tracking-widest text-slate-700 dark:text-slate-300 min-w-[50px] sm:min-w-[70px] text-center truncate">
                                Pg {{ barPage + 1 }} / {{ maxBarPage + 1 }}
                            </span>
                            <button @click="nextBarPage" :disabled="barPage >= maxBarPage" class="p-1 sm:p-1.5 text-slate-500 hover:text-slate-800 dark:hover:text-white transition-colors disabled:opacity-30 disabled:cursor-not-allowed">
                                <ChevronRight class="w-3 h-3 sm:w-4 sm:h-4" />
                            </button>
                        </div>
                    </div>
                    <div class="w-full relative h-[200px] sm:h-[300px]">
                        <Bar v-if="barChartData.labels.length > 0" :data="barChartData" :options="barChartOptions" />
                        <div v-else class="absolute inset-0 flex items-center justify-center text-[10px] sm:text-xs font-black uppercase tracking-widest text-slate-400">
                            No published courses found.
                        </div>
                    </div>
                </div>
            </div>

            <!-- ACTION CENTER AND ACTIVITY LOG -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-3 sm:gap-4 w-full min-w-0 pb-6 sm:pb-0">
                
                <!-- ACTION CENTER (Left Column) -->
                <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden w-full flex flex-col">
                    <div class="p-3 sm:p-4 border-b border-slate-100 dark:border-slate-700/50 flex justify-between items-center bg-slate-50/50 dark:bg-slate-900/20 shrink-0">
                        <h3 class="text-[10px] sm:text-xs font-black uppercase tracking-widest text-slate-800 dark:text-slate-200 flex items-center gap-1.5 truncate">
                            <AlertTriangle class="w-4 h-4 text-red-500 shrink-0" /> Action Center
                        </h3>
                    </div>
                    
                    <div class="hidden sm:block w-full overflow-x-auto pb-1 flex-1">
                        <table class="w-full text-left text-xs">
                            <thead class="bg-white dark:bg-slate-800 border-b border-slate-100 dark:border-slate-700 uppercase text-[9px] font-black text-slate-400 tracking-widest">
                                <tr>
                                    <th class="px-4 py-3 whitespace-nowrap">Severity</th>
                                    <th class="px-4 py-3 w-full">Issue Description</th>
                                    <th class="px-4 py-3 text-right">Target</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                                <tr v-for="item in actionItems" :key="item?.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                                    <td class="px-4 py-3 whitespace-nowrap">
                                        <span class="text-[9px] font-black uppercase tracking-widest px-2 py-1 rounded shadow-sm"
                                              :class="{
                                                  'bg-red-100 text-red-700 border border-red-200 dark:bg-red-900/30 dark:border-red-800': item.severity === 'high',
                                                  'bg-orange-100 text-orange-700 border border-orange-200 dark:bg-orange-900/30 dark:border-orange-800': item.severity === 'medium',
                                                  'bg-slate-100 text-slate-600 border border-slate-200 dark:bg-slate-700 dark:text-slate-400 dark:border-slate-600': item.severity === 'low'
                                              }">
                                            {{ item.severity }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex items-center gap-1.5 min-w-0">
                                            <span class="text-[11px] font-bold text-slate-900 dark:text-white line-clamp-2 leading-tight">{{ item.description }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        <Link :href="item.link" class="inline-flex items-center justify-center p-2 bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 rounded hover:bg-blue-100 dark:hover:bg-blue-800 transition shadow-sm border border-transparent">
                                            <ChevronRightSquare class="w-4 h-4" />
                                        </Link>
                                    </td>
                                </tr>
                                <tr v-if="!actionItems || actionItems.length === 0">
                                    <td colspan="3" class="p-8 text-center text-slate-400 flex flex-col items-center justify-center gap-2">
                                        <ShieldCheck class="w-8 h-8 text-emerald-500/50" />
                                        <span class="text-[10px] font-black uppercase tracking-widest">No pending administrative actions.</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="sm:hidden flex flex-col divide-y divide-slate-100 dark:divide-slate-700/50">
                        <div v-for="item in actionItems" :key="item?.id" class="p-3 hover:bg-slate-50 dark:hover:bg-slate-700/30 transition flex flex-col gap-2">
                            <div class="flex items-center justify-between gap-2">
                                <span class="text-[8px] font-black uppercase tracking-widest px-1.5 py-0.5 rounded shadow-sm shrink-0"
                                      :class="{
                                          'bg-red-100 text-red-700 border border-red-200 dark:bg-red-900/30 dark:border-red-800': item.severity === 'high',
                                          'bg-orange-100 text-orange-700 border border-orange-200 dark:bg-orange-900/30 dark:border-orange-800': item.severity === 'medium',
                                          'bg-slate-100 text-slate-600 border border-slate-200 dark:bg-slate-700 dark:text-slate-400 dark:border-slate-600': item.severity === 'low'
                                      }">
                                    {{ item.severity }}
                                </span>
                                <Link :href="item.link" class="inline-flex items-center justify-center p-1 bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 rounded hover:bg-blue-100 dark:hover:bg-blue-800 transition shadow-sm border border-transparent">
                                    <ChevronRightSquare class="w-3.5 h-3.5" />
                                </Link>
                            </div>
                            <span class="text-[10px] font-bold text-slate-900 dark:text-white leading-tight">{{ item.description }}</span>
                        </div>
                        <div v-if="!actionItems || actionItems.length === 0" class="p-6 text-center text-slate-400 flex flex-col items-center justify-center gap-2">
                            <ShieldCheck class="w-6 h-6 text-emerald-500/50" />
                            <span class="text-[9px] font-black uppercase tracking-widest">No pending administrative actions.</span>
                        </div>
                    </div>
                </div>

                <!-- SYSTEM ACTIVITY LOG (Right Column) -->
                <div class="bg-white dark:bg-slate-800 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden w-full flex flex-col h-[280px] sm:h-[350px]">
                    <div class="p-3 sm:p-4 border-b border-slate-100 dark:border-slate-700/50 flex justify-between items-center bg-slate-50/50 dark:bg-slate-900/20 shrink-0">
                        <h3 class="text-[10px] sm:text-xs font-black uppercase tracking-widest text-slate-800 dark:text-slate-200 flex items-center gap-1.5 truncate">
                            <History class="w-4 h-4 text-purple-500 shrink-0" /> System Activity Log
                        </h3>
                    </div>
                    <div class="flex-1 overflow-y-auto p-2 custom-scrollbar relative">
                        <div v-if="activityLogs && activityLogs.length > 0" class="flex flex-col gap-1">
                            <div v-for="(log, idx) in activityLogs" :key="idx" class="flex gap-2.5 p-2 rounded hover:bg-slate-50 dark:hover:bg-slate-700/30 transition">
                                <div class="mt-0.5 shrink-0">
                                    <div class="w-2 h-2 rounded-full mt-1" :class="log.colorClass || 'bg-slate-400'"></div>
                                </div>
                                <div class="flex flex-col min-w-0">
                                    <span class="text-[10px] font-bold text-slate-900 dark:text-white leading-tight break-words">{{ log.action }}</span>
                                    <div class="flex items-center gap-1 mt-0.5">
                                        <span class="text-[8px] font-black uppercase tracking-widest text-slate-500">{{ log.user }}</span>
                                        <span class="text-[8px] text-slate-400">•</span>
                                        <span class="text-[8px] font-medium text-slate-400">{{ log.time }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="absolute inset-0 flex flex-col items-center justify-center text-slate-400 gap-2 p-4">
                            <History class="w-6 h-6 text-slate-300 dark:text-slate-600" />
                            <span class="text-[9px] font-black uppercase tracking-widest text-center">No recent activity logged.</span>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- ==========================================
             QUICK ACTION MODALS 
        =========================================== -->
        
        <!-- Create Account Modal -->
        <div v-if="isCreateModalOpen" class="fixed inset-0 z-[9999] flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-sm border border-slate-200 dark:border-slate-700 overflow-hidden flex flex-col">
                <div class="p-3 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center bg-slate-50 dark:bg-slate-900/50">
                    <h3 class="text-xs font-black uppercase tracking-widest text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
                        <UserPlus class="w-4 h-4 text-blue-500" /> Create Account
                    </h3>
                    <button @click="isCreateModalOpen = false" class="text-slate-400 hover:text-red-500 transition"><X class="w-4 h-4" /></button>
                </div>
                <form @submit.prevent="submitCreate" class="p-4 space-y-3">
                    <div>
                        <label class="block text-[9px] font-black uppercase tracking-widest text-slate-500 mb-1">Full Name</label>
                        <input type="text" v-model="createForm.name" required class="w-full text-xs rounded border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white px-2 py-1.5 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-[9px] font-black uppercase tracking-widest text-slate-500 mb-1">Email</label>
                        <input type="email" v-model="createForm.email" required class="w-full text-xs rounded border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white px-2 py-1.5 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-[9px] font-black uppercase tracking-widest text-slate-500 mb-1">Password</label>
                        <input type="password" v-model="createForm.password" required class="w-full text-xs rounded border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white px-2 py-1.5 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label class="block text-[9px] font-black uppercase tracking-widest text-slate-500 mb-1">Role</label>
                        <select v-model="createForm.role" class="w-full text-xs rounded border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white px-2 py-1.5 focus:ring-blue-500 focus:border-blue-500 cursor-pointer">
                            <option value="student">Student</option>
                            <option value="teacher">Teacher</option>
                            <option value="dean">Dean</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="pt-2 flex justify-end gap-2">
                        <button type="button" @click="isCreateModalOpen = false" class="px-3 py-1.5 text-[9px] font-black uppercase tracking-widest text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700 rounded transition">Cancel</button>
                        <button type="submit" :disabled="createForm.processing" class="px-3 py-1.5 text-[9px] font-black uppercase tracking-widest bg-blue-600 hover:bg-blue-500 text-white rounded transition disabled:opacity-50">Create</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Broadcast Update Modal -->
        <div v-if="isBroadcastModalOpen" class="fixed inset-0 z-[9999] flex items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
            <div class="bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-sm border border-slate-200 dark:border-slate-700 overflow-hidden flex flex-col">
                <div class="p-3 border-b border-slate-100 dark:border-slate-700 flex justify-between items-center bg-slate-50 dark:bg-slate-900/50">
                    <h3 class="text-xs font-black uppercase tracking-widest text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
                        <Megaphone class="w-4 h-4 text-amber-500" /> Broadcast System Update
                    </h3>
                    <button @click="isBroadcastModalOpen = false" class="text-slate-400 hover:text-red-500 transition"><X class="w-4 h-4" /></button>
                </div>
                <form @submit.prevent="submitBroadcast" class="p-4 space-y-3">
                    <div>
                        <label class="block text-[9px] font-black uppercase tracking-widest text-slate-500 mb-1">Subject / Title</label>
                        <input type="text" v-model="broadcastForm.subject" required class="w-full text-xs rounded border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white px-2 py-1.5 focus:ring-amber-500 focus:border-amber-500">
                    </div>
                    <div>
                        <label class="block text-[9px] font-black uppercase tracking-widest text-slate-500 mb-1">Message</label>
                        <textarea v-model="broadcastForm.message" required rows="3" class="w-full text-xs rounded border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white px-2 py-1.5 focus:ring-amber-500 focus:border-amber-500 custom-scrollbar"></textarea>
                    </div>
                    <div>
                        <label class="block text-[9px] font-black uppercase tracking-widest text-slate-500 mb-1">Target Audience</label>
                        <select v-model="broadcastForm.target" class="w-full text-xs rounded border border-slate-300 dark:border-slate-600 bg-white dark:bg-slate-900 text-slate-900 dark:text-white px-2 py-1.5 focus:ring-amber-500 focus:border-amber-500 cursor-pointer">
                            <option value="all">Everyone</option>
                            <option value="teachers">Teachers Only</option>
                            <option value="students">Students Only</option>
                        </select>
                    </div>
                    <div class="pt-2 flex justify-end gap-2">
                        <button type="button" @click="isBroadcastModalOpen = false" class="px-3 py-1.5 text-[9px] font-black uppercase tracking-widest text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700 rounded transition">Cancel</button>
                        <button type="submit" :disabled="broadcastForm.processing" class="px-3 py-1.5 text-[9px] font-black uppercase tracking-widest bg-amber-500 hover:bg-amber-400 text-white rounded transition disabled:opacity-50">Send Alert</button>
                    </div>
                </form>
            </div>
        </div>

    </AuthenticatedLayout>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 4px; }
.custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
@media (prefers-color-scheme: dark) {
    .custom-scrollbar::-webkit-scrollbar-thumb { background: #334155; }
    .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: #475569; }
}
</style>