<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, usePage, useForm, router } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import { Calendar } from 'v-calendar';
import 'v-calendar/dist/style.css';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import InputError from '@/Components/InputError.vue';
import { 
    Plus, X, Trash2, Calendar as CalIcon, ChevronRight, 
    Info, FileText, Lock, Unlock, Sparkles, Landmark, 
    AlertTriangle, Wrench, CheckCircle2 
} from 'lucide-vue-next';

const props = defineProps({ attributes: Array });
const user = usePage().props.auth.user;

// --- DYNAMIC THEMING ---
const isDarkMode = ref(false);
onMounted(() => {
    isDarkMode.value = document.documentElement.classList.contains('dark');
    const observer = new MutationObserver(() => {
        isDarkMode.value = document.documentElement.classList.contains('dark');
    });
    observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
});

// --- UI STATES ---
const viewMode = ref('monthly');
const selectedDate = ref(new Date());

// --- DYNAMIC FILTERS (SEGREGATED BY ROLE) ---
const availableFilters = computed(() => {
    if (user.role === 'admin') return [{ id: 'Institution', name: 'Global Events', color: 'purple' }];
    if (user.role === 'teacher') return [
        { id: 'Institution', name: 'School Events', color: 'purple' }, 
        { id: 'Task', name: 'Assignments', color: 'blue' }, 
        { id: 'Material', name: 'Material Timers', color: 'orange' }
    ];
    return [
        { id: 'Institution', name: 'School Events', color: 'purple' }, 
        { id: 'Task', name: 'My Tasks', color: 'blue' }, 
        { id: 'AI Study Tip', name: 'AI Tips', color: 'indigo' }
    ];
});

const activeFilters = ref(availableFilters.value.map(f => f.id));
const toggleFilter = (id) => {
    if (activeFilters.value.includes(id)) activeFilters.value = activeFilters.value.filter(f => f !== id);
    else activeFilters.value.push(id);
};

const getFilterClass = (f) => {
    if (!activeFilters.value.includes(f.id)) {
        return 'bg-slate-100 dark:bg-slate-800 text-slate-500 border-slate-200 dark:border-slate-700 hover:bg-slate-200 dark:hover:bg-slate-700';
    }
    // Dynamic Active Colors
    const colorMap = {
        'purple': 'bg-purple-600 text-white border-purple-600 shadow-md',
        'blue': 'bg-blue-600 text-white border-blue-600 shadow-md',
        'orange': 'bg-orange-500 text-white border-orange-500 shadow-md',
        'indigo': 'bg-indigo-500 text-white border-indigo-500 shadow-md'
    };
    return colorMap[f.color] || 'bg-blue-600 text-white';
};

// V-Calendar Attributes mapping
const filteredAttributes = computed(() => {
    return props.attributes
        .filter(a => activeFilters.value.includes(a.customData.type))
        .map(a => ({
            ...a,
            dot: { color: a.customData.color, class: 'scale-150 shadow-sm' }
        }));
});

// Agenda items for the selected day
const dayEvents = computed(() => {
    return filteredAttributes.value.filter(a => 
        new Date(a.dates).toDateString() === selectedDate.value.toDateString()
    );
});

// --- EXACT ROUTING LOGIC ---
const getLink = (data) => {
    if (data.is_admin_item || data.type === 'AI Study Tip') return null;
    
    // Exact Teacher Routing
    if (user.role === 'teacher') {
        if (data.is_task) return route('teacher.assignments.show', data.id);
        if (data.is_material) return route('teacher.courses.show', data.course_id) + '?tab=materials&target=' + data.id;
    }
    
    // Exact Student Routing
    if (user.role === 'student' && data.course_id) {
        return route('student.courses.show', data.course_id) + '?tab=assignments&target=' + data.id;
    }
    
    return null;
};

// --- NO EMOJIS: LUCIDE ICON RESOLVER ---
const getIcon = (data) => {
    if (data.type === 'Institution') {
        if (data.category === 'Holiday') return AlertTriangle;
        if (data.category === 'Maintenance') return Wrench;
        return Landmark;
    }
    if (data.type === 'Material') return data.action === 'archive' ? Lock : Unlock;
    if (data.type === 'AI Study Tip') return Sparkles;
    if (data.is_done) return CheckCircle2;
    return FileText;
};

const getIconColorClass = (color) => {
    const map = {
        'purple': 'text-purple-600 dark:text-purple-400',
        'blue': 'text-blue-600 dark:text-blue-400',
        'orange': 'text-orange-500 dark:text-orange-400',
        'red': 'text-red-500 dark:text-red-400',
        'green': 'text-emerald-500 dark:text-emerald-400',
        'indigo': 'text-indigo-500 dark:text-indigo-400',
        'gray': 'text-slate-500 dark:text-slate-400'
    };
    return map[color] || 'text-blue-500';
};

// --- ADMIN CREATOR ---
const showCreate = ref(false);
const eventForm = useForm({ title: '', type: 'event', audience: 'all', start_date: '', description: '' });
const submitEvent = () => eventForm.post(route('admin.calendar.store'), { 
    preserveScroll: true,
    onSuccess: () => { showCreate.value = false; eventForm.reset(); } 
});
const deleteEvent = (id) => {
    if(confirm('Delete this event?')) router.delete(route('admin.calendar.destroy', id), { preserveScroll: true });
};
</script>

<template>
    <Head title="Academic Hub" />
    <AuthenticatedLayout>
        <div class="max-w-7xl mx-auto px-3 sm:px-6 flex flex-col h-full md:h-[calc(100vh-80px)] pb-12 md:pb-6">
            
            <div class="flex justify-between items-center mb-3 shrink-0 mt-1 sm:mt-0">
                <div>
                    <h1 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white flex items-center gap-2 tracking-tight">
                        <CalIcon class="text-blue-600 dark:text-blue-500 w-5 h-5 sm:w-6 sm:h-6"/> 
                        Academic Hub
                    </h1>
                    <p class="text-[9px] sm:text-[10px] font-bold uppercase tracking-widest text-slate-500 mt-0.5 ml-7 sm:ml-8">
                        {{ user.role === 'admin' ? 'Master Schedule' : 'Your Agenda' }}
                    </p>
                </div>
                
                <div class="flex gap-2">
                    <button v-if="user.role === 'admin'" @click="showCreate = true" class="p-2 sm:px-3 sm:py-2 bg-purple-600 text-white rounded-lg shadow-sm active:scale-95 transition-all flex items-center gap-1.5">
                        <Plus class="w-4 h-4"/><span class="hidden sm:inline text-[10px] font-black uppercase tracking-widest">New Event</span>
                    </button>
                    <div class="flex bg-slate-100 dark:bg-slate-800 p-1 rounded-lg border border-slate-200 dark:border-slate-700 shadow-sm">
                        <button @click="viewMode = 'monthly'" :class="{'bg-white dark:bg-slate-600 shadow-sm text-slate-900 dark:text-white': viewMode === 'monthly', 'text-slate-500': viewMode !== 'monthly'}" class="px-2.5 sm:px-3 py-1 text-[9px] font-black rounded-md uppercase transition-all">Month</button>
                        <button @click="viewMode = 'weekly'" :class="{'bg-white dark:bg-slate-600 shadow-sm text-slate-900 dark:text-white': viewMode === 'weekly', 'text-slate-500': viewMode !== 'weekly'}" class="px-2.5 sm:px-3 py-1 text-[9px] font-black rounded-md uppercase transition-all">Week</button>
                    </div>
                </div>
            </div>

            <div v-if="availableFilters.length > 1" class="flex gap-2 mb-3 overflow-x-auto scrollbar-hide shrink-0 pb-1">
                <button v-for="f in availableFilters" :key="f.id" @click="toggleFilter(f.id)" 
                    :class="getFilterClass(f)"
                    class="px-3 py-1.5 rounded-md text-[9px] font-black uppercase tracking-widest transition-colors whitespace-nowrap">
                    {{ f.name }}
                </button>
            </div>

            <div class="flex flex-col lg:flex-row gap-4 flex-1 min-h-0 overflow-hidden pb-4">
                
                <div class="w-full lg:w-7/12 xl:w-2/3 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl p-2 sm:p-4 shadow-sm shrink-0 lg:shrink flex flex-col min-h-[350px]">
                    <Calendar expanded borderless transparent :view="viewMode" :attributes="filteredAttributes" 
                        @dayclick="d => selectedDate = d.date" :is-dark="isDarkMode" 
                        class="w-full h-full custom-calendar" />
                </div>

                <div class="flex-1 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-700/50 rounded-2xl flex flex-col overflow-hidden h-[400px] lg:h-auto shrink-0 shadow-inner">
                    
                    <div class="p-3 sm:p-4 bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 shrink-0 flex justify-between items-center">
                        <div>
                            <h2 class="text-xs sm:text-sm font-black text-slate-900 dark:text-white leading-none mb-1 tracking-tight">Daily Agenda</h2>
                            <p class="text-[9px] sm:text-[10px] font-bold text-blue-600 dark:text-blue-400 uppercase tracking-widest">{{ selectedDate.toDateString() }}</p>
                        </div>
                        <span class="text-[9px] font-black bg-slate-100 dark:bg-slate-900 text-slate-500 px-2 py-1 rounded">{{ dayEvents.length }} items</span>
                    </div>

                    <div class="flex-1 overflow-y-auto p-3 space-y-3 custom-scrollbar">
                        
                        <div v-if="dayEvents.length === 0" class="flex flex-col items-center justify-center h-full opacity-40 py-8">
                            <CalIcon class="mb-3 w-8 h-8 text-slate-400"/> 
                            <p class="text-[10px] font-bold uppercase tracking-widest text-slate-500">No events scheduled</p>
                        </div>

                        <div v-for="e in dayEvents" :key="e.key" class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-3 shadow-sm relative overflow-hidden group">
                            
                            <div class="absolute right-[-10px] top-1/2 -translate-y-1/2 opacity-5 pointer-events-none">
                                <component :is="getIcon(e.customData)" class="w-24 h-24" :class="getIconColorClass(e.customData.color)" />
                            </div>

                            <div class="flex justify-between items-start relative z-10">
                                <div class="min-w-0 flex-1 pr-2">
                                    <div class="flex items-center gap-2 mb-1.5">
                                        <component :is="getIcon(e.customData)" class="w-3.5 h-3.5" :class="getIconColorClass(e.customData.color)" />
                                        <span class="text-[8px] font-black uppercase tracking-widest text-slate-500">{{ e.customData.category || e.customData.type }}</span>
                                    </div>

                                    <h3 class="text-xs sm:text-sm font-black text-slate-900 dark:text-white leading-tight" :class="{'opacity-50 line-through': e.customData.is_done}">
                                        {{ e.customData.title }}
                                    </h3>
                                    
                                    <p v-if="e.customData.description" class="text-[10px] text-slate-600 dark:text-slate-400 mt-1 leading-snug">{{ e.customData.description }}</p>
                                    
                                    <div v-if="e.customData.course_title" class="mt-2 text-[8px] font-bold text-slate-600 dark:text-slate-300 bg-slate-100 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 inline-block px-1.5 py-0.5 rounded">
                                        {{ e.customData.course_title }}
                                    </div>
                                </div>

                                <div class="shrink-0 flex flex-col items-end gap-2">
                                    <button v-if="user.role === 'admin' && e.customData.is_admin_item" @click="deleteEvent(e.customData.id)" class="text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 p-1.5 rounded-md transition-colors border border-transparent hover:border-red-200">
                                        <Trash2 class="w-3.5 h-3.5"/>
                                    </button>

                                    <Link v-if="getLink(e.customData)" :href="getLink(e.customData)" class="p-1.5 bg-blue-50 hover:bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:hover:bg-blue-900/50 dark:text-blue-400 rounded-md transition-colors border border-transparent shadow-sm flex items-center" title="Open Task / Material">
                                        <ChevronRight class="w-4 h-4"/>
                                    </Link>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <Modal :show="showCreate" @close="showCreate = false" maxWidth="sm">
            <div class="p-5 bg-white dark:bg-slate-900 rounded-2xl shadow-xl border border-slate-200 dark:border-slate-800">
                <h2 class="text-sm font-black uppercase dark:text-white mb-4 flex items-center gap-2">
                    <span class="p-1 bg-purple-100 text-purple-600 dark:bg-purple-900/30 dark:text-purple-400 rounded"><Landmark class="w-4 h-4"/></span>
                    Global Calendar Event
                </h2>
                <form @submit.prevent="submitEvent" class="space-y-3">
                    <div>
                        <InputLabel value="Event Title" class="text-[9px] font-black uppercase text-slate-500 mb-1"/>
                        <input v-model="eventForm.title" type="text" class="w-full rounded-md bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-xs py-2 shadow-sm text-slate-900 dark:text-white" required />
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <InputLabel value="Event Type" class="text-[9px] font-black uppercase text-slate-500 mb-1"/>
                            <select v-model="eventForm.type" class="w-full text-[10px] rounded-md bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 font-bold text-slate-900 dark:text-white cursor-pointer">
                                <option value="event">Campus Event</option><option value="academic">Academic</option><option value="holiday">Holiday</option><option value="maintenance">Maintenance</option>
                            </select>
                        </div>
                        <div>
                            <InputLabel value="Audience" class="text-[9px] font-bold uppercase text-slate-500 mb-1"/>
                            <select v-model="eventForm.audience" class="w-full text-[10px] rounded-md bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 font-bold text-slate-900 dark:text-white cursor-pointer">
                                <option value="all">Everyone</option><option value="teacher">Teachers</option><option value="student">Students</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <InputLabel value="Date & Time" class="text-[9px] font-black uppercase text-slate-500 mb-1"/>
                        <input v-model="eventForm.start_date" type="datetime-local" class="w-full rounded-md bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-[10px] py-2 text-slate-900 dark:text-white" required />
                    </div>
                    <div>
                        <InputLabel value="Description" class="text-[9px] font-black uppercase text-slate-500 mb-1"/>
                        <textarea v-model="eventForm.description" rows="2" class="w-full rounded-md bg-slate-50 dark:bg-slate-800 border-slate-200 dark:border-slate-700 text-xs resize-none text-slate-900 dark:text-white"></textarea>
                    </div>
                    <div class="flex justify-end gap-2 border-t border-slate-100 dark:border-slate-800 pt-3 mt-2">
                        <button type="button" @click="showCreate = false" class="text-[10px] font-black uppercase text-slate-400 hover:text-slate-600 transition">Cancel</button>
                        <button type="submit" :disabled="eventForm.processing" class="bg-purple-600 text-white px-4 py-1.5 rounded-md text-[10px] font-black uppercase shadow-sm transition-all active:scale-95">Publish</button>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>

<style>
/* Modern typography overrides for V-Calendar to keep it incredibly compact and uniform */
.custom-calendar { font-family: inherit; }
.custom-calendar .vc-header { padding: 8px 12px; margin-bottom: 4px; }
.custom-calendar .vc-title { font-weight: 900; font-size: 0.95rem; color: #3b82f6; text-transform: uppercase; letter-spacing: -0.02em; }
.custom-calendar .vc-weekday { font-size: 0.6rem; font-weight: 800; text-transform: uppercase; color: #94a3b8; padding-bottom: 8px; }
.custom-calendar .vc-day { min-height: 44px; }
.scrollbar-hide::-webkit-scrollbar { display: none; }
.custom-scrollbar::-webkit-scrollbar { width: 4px; }
.custom-scrollbar::-webkit-scrollbar-track { background: transparent; }
.custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(148, 163, 184, 0.25); border-radius: 10px; }
</style>