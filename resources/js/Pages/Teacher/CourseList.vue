<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ref, computed, watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import InputError from '@/Components/InputError.vue';
import Dropdown from '@/Components/Dropdown.vue';
import { Plus, Search, Filter } from 'lucide-vue-next'; 

const props = defineProps({
    courses: Array
});

const page = usePage();
const userId = page.props.auth.user.id;

const getFileUrl = (path) => {
    if (!path) return '';
    const cleanPath = path.replace(/^\/storage\//, '');
    return `${usePage().props.env.AWS_URL}/${cleanPath}`;
};

const storageKey = `lms_hidden_courses_${userId}`;
const hiddenCourses = ref(JSON.parse(localStorage.getItem(storageKey)) || []);
const activeTab = ref('active');

const searchQuery = ref('');
const sortOption = ref('newest');

const toggleHide = (courseId) => {
    if (hiddenCourses.value.includes(courseId)) {
        hiddenCourses.value = hiddenCourses.value.filter(id => id !== courseId);
    } else {
        hiddenCourses.value.push(courseId);
    }
    localStorage.setItem(storageKey, JSON.stringify(hiddenCourses.value));
};

const displayedCourses = computed(() => {
    let filtered = props.courses.filter(c => {
        const isHidden = hiddenCourses.value.includes(c.id);
        return activeTab.value === 'active' ? !isHidden : isHidden;
    });

    if (searchQuery.value.trim() !== '') {
        const query = searchQuery.value.toLowerCase();
        filtered = filtered.filter(c => 
            c.title.toLowerCase().includes(query) || 
            (c.enrollment_code && c.enrollment_code.toLowerCase().includes(query))
        );
    }

    filtered.sort((a, b) => {
        if (sortOption.value === 'newest') {
            return new Date(b.created_at || 0).getTime() - new Date(a.created_at || 0).getTime();
        } else if (sortOption.value === 'oldest') {
            return new Date(a.created_at || 0).getTime() - new Date(b.created_at || 0).getTime();
        } else if (sortOption.value === 'a_z') {
            return a.title.localeCompare(b.title);
        } else if (sortOption.value === 'z_a') {
            return b.title.localeCompare(a.title);
        }
        return 0;
    });

    return filtered;
});

const imageErrors = ref({});
const showCreateModal = ref(false);

const form = useForm({
    title: '',
    description: '',
    difficulty_level: 'beginner',
    thumbnail: null
});

const handleImageError = (id) => { imageErrors.value[id] = true; };

const copyCode = (code) => {
    if (code) {
        navigator.clipboard.writeText(code);
        alert('Class code copied: ' + code);
    }
};

const submitCourse = () => {
    form.post(route('teacher.courses.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showCreateModal.value = false;
            form.reset();
        }
    });
};

const formatYearLevel = (level) => {
    const levels = {
        'beginner': '1st Year',
        'intermediate': '2nd Year',
        'advanced': '3rd Year',
        'final': '4th Year'
    };
    return levels[level] || level;
};
</script>

<template>
    <Head title="My Classes" />
    <AuthenticatedLayout>
        
        <!-- MOBILE FLOATING FAB: White in both light & dark mode -->
        <button @click="showCreateModal = true" class="md:hidden fixed bottom-[156px] right-4 z-[9999] flex items-center justify-center w-12 h-12 bg-white dark:bg-white text-blue-600 dark:text-blue-600 rounded-full border border-black dark:border-white shadow-[0_8px_30px_rgba(0,0,0,0.15)] dark:shadow-[0_8px_30px_rgba(255,255,255,0.2)] transition-all duration-300 ease-out hover:bg-slate-100 dark:hover:bg-slate-100 hover:scale-[1.03] active:scale-[0.95] focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900 cursor-pointer">
            <Plus class="w-5 h-5" />
        </button>

        <div class="max-w-screen-2xl mx-auto flex flex-col h-full relative">
            
            <div class="mb-4 pb-3 border-b border-slate-200 dark:border-slate-700 shrink-0 px-1 sm:px-0">
                <h1 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight">My Classes</h1>
                <p class="text-slate-500 dark:text-slate-400 text-[9px] sm:text-[10px] mt-1 font-bold uppercase tracking-widest">Manage your course content</p>
            </div>

            <div class="flex gap-4 items-start flex-1 min-h-0">
                
                <aside class="hidden md:flex w-10 shrink-0 flex-col gap-3 sticky top-4 z-30">
                    <!-- DESKTOP SIDEBAR BUTTON: White in both light & dark mode -->
                    <button @click="showCreateModal = true" class="group relative flex items-center justify-center w-10 h-10 bg-white dark:bg-white rounded-full border border-black dark:border-white text-blue-600 dark:text-blue-600 hover:bg-slate-100 dark:hover:bg-slate-100 transition shadow-sm">
                        <Plus class="w-5 h-5" />
                        <span class="absolute left-full ml-2 px-2 py-1 bg-slate-800 text-white text-[9px] font-bold rounded opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none whitespace-nowrap shadow-lg z-50">Create Class</span>
                    </button>
                </aside>

                <div class="flex-1 min-w-0 flex flex-col h-full">
                    
                    <!-- RESTRUCTURED SEARCH & FILTER BAR -->
                    <div class="bg-white dark:bg-slate-800 p-2.5 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm mb-4 flex flex-row items-center gap-2 mx-1 sm:mx-0">
                        
                        <div class="relative flex-1 min-w-0">
                            <div class="absolute inset-y-0 left-0 pl-2.5 flex items-center pointer-events-none">
                                <Search class="h-3.5 w-3.5 text-slate-400" />
                            </div>
                            <input v-model="searchQuery" type="text" placeholder="Search classes by name or code..." class="w-full h-8 pl-8 rounded-md bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-blue-500 focus:border-transparent text-xs shadow-sm transition-colors" />
                        </div>

                        <!-- Dark Mode Native Select Fix & Mobile Inline Display -->
                        <div class="shrink-0 bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg px-2 py-1 shadow-sm flex items-center gap-1 sm:gap-1.5 w-auto">
                            <Filter class="w-3.5 h-3.5 text-slate-500 dark:text-slate-400 shrink-0" />
                            <select v-model="sortOption" class="bg-transparent border-none text-[9px] font-bold uppercase tracking-widest text-slate-600 dark:text-slate-300 focus:ring-0 cursor-pointer p-0 m-0 truncate dark:[color-scheme:dark] w-16 sm:w-auto">
                                <option value="newest" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">Newest First</option>
                                <option value="oldest" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">Oldest First</option>
                                <option value="a_z" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">Name (A-Z)</option>
                                <option value="z_a" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">Name (Z-A)</option>
                            </select>
                        </div>

                    </div>

                    <div class="flex gap-4 border-b border-slate-200 dark:border-slate-700 mb-4 px-1 sm:px-0 overflow-x-auto scrollbar-hide shrink-0">
                        <button @click="activeTab = 'active'" class="pb-2.5 text-[10px] sm:text-xs font-black uppercase tracking-widest transition-all relative whitespace-nowrap" :class="activeTab === 'active' ? 'text-blue-600' : 'text-slate-400 hover:text-slate-600'">
                            Active Classes
                            <div v-if="activeTab === 'active'" class="absolute bottom-0 left-0 w-full h-0.5 bg-blue-600 rounded-full"></div>
                        </button>
                        <button @click="activeTab = 'hidden'" class="pb-2.5 text-[10px] sm:text-xs font-black uppercase tracking-widest transition-all relative whitespace-nowrap" :class="activeTab === 'hidden' ? 'text-blue-600' : 'text-slate-400 hover:text-slate-600'">
                            Hidden Classes
                            <div v-if="activeTab === 'hidden'" class="absolute bottom-0 left-0 w-full h-0.5 bg-blue-600 rounded-full"></div>
                        </button>
                    </div>

                    <div class="flex-1 min-w-0 pb-6 overflow-y-auto">
                        <div v-if="displayedCourses.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4 px-1 sm:px-0">
                            
                            <!-- COURSE CARD -->
                            <div v-for="course in displayedCourses" :key="course.id"
                                  class="group bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 transition-all duration-200 flex flex-row sm:flex-col shadow-sm h-full min-h-[120px] sm:min-h-[260px] hover:border-blue-500 dark:hover:border-blue-500 hover:shadow-md relative">
                                
                                <div class="absolute top-2 right-2 z-40">
                                    <Dropdown align="right" width="48" contentClasses="bg-white dark:bg-slate-800">
                                        <template #trigger>
                                            <!-- UPDATED: Fully solid 3-Dot Button synced to Theme Mode -->
                                            <button class="p-1 rounded-md bg-white dark:bg-slate-900 hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-slate-700 transition shadow-sm focus:outline-none">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path></svg>
                                            </button>
                                        </template>
                                        <template #content>
                                            <!-- UPDATED: Extra dark mode wrapper to ensure dropdown content is themed correctly -->
                                            <div class="bg-white dark:bg-slate-800">
                                                <button @click.prevent="toggleHide(course.id)" class="block w-full text-left px-4 py-2 text-[10px] font-black uppercase tracking-widest text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition">
                                                    {{ activeTab === 'active' ? 'Hide Class' : 'Unhide Class' }}
                                                </button>
                                                <Link :href="route('teacher.courses.edit', course.id)" class="block w-full text-left px-4 py-2 text-[10px] font-black uppercase tracking-widest text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 transition">
                                                    Settings
                                                </Link>
                                            </div>
                                        </template>
                                    </Dropdown>
                                </div>

                                <div class="w-[110px] sm:w-full h-auto sm:aspect-video relative bg-slate-100 dark:bg-slate-900 shrink-0 border-r sm:border-r-0 sm:border-b border-slate-200 dark:border-slate-700 rounded-l-xl sm:rounded-t-xl sm:rounded-bl-none overflow-hidden">
                                    <img v-if="course.thumbnail && !imageErrors[course.id]"
                                          :src="getFileUrl(course.thumbnail)"
                                          @error="handleImageError(course.id)"
                                         class="absolute inset-0 w-full h-full object-cover z-0 transition-transform duration-700 group-hover:scale-105" />
                                    
                                    <div class="absolute inset-0 bg-slate-200 dark:bg-slate-900 flex items-center justify-center" v-else>
                                         <svg class="w-8 h-8 text-slate-400 dark:text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    </div>
                                    
                                    <div class="absolute inset-0 bg-gradient-to-t from-slate-900/60 to-transparent hidden sm:block z-10 pointer-events-none"></div>
                                    <div class="absolute sm:bottom-2 sm:left-2 top-2 left-2 z-20">
                                        <span class="text-[8px] font-black uppercase tracking-widest bg-white/90 text-blue-600 px-1.5 py-0.5 rounded shadow-sm">
                                            {{ formatYearLevel(course.difficulty_level) }}
                                        </span>
                                    </div>
                                </div>

                                <div class="p-3 flex-1 flex flex-col justify-between min-w-0">
                                    <div class="min-w-0">
                                        <Link :href="route('teacher.courses.edit', course.id)" class="block font-black text-sm sm:text-base text-slate-900 dark:text-white group-hover:text-blue-600 transition truncate leading-tight mb-1 pr-6 sm:pr-0">
                                            {{ course.title }}
                                        </Link>

                                        <div class="bg-slate-50 dark:bg-slate-900/50 rounded inline-flex px-1.5 py-1 border border-slate-100 dark:border-slate-700/50 items-center group/code cursor-pointer hover:border-blue-400 transition"
                                              @click="copyCode(course.enrollment_code)" title="Click to copy code">
                                            <span class="text-[8px] sm:text-[9px] text-slate-400 dark:text-slate-500 uppercase font-black tracking-widest mr-1.5">Code:</span>
                                            <span class="text-[10px] sm:text-xs font-mono font-bold text-blue-600 dark:text-blue-400 tracking-widest leading-none">{{ course.enrollment_code }}</span>
                                        </div>
                                        
                                        <p class="hidden sm:block text-slate-500 dark:text-slate-400 text-[10px] line-clamp-2 leading-relaxed mt-2">
                                            {{ course.description || 'No description provided.' }}
                                        </p>
                                    </div>

                                    <div class="flex items-center gap-1.5 sm:gap-2 mt-2 sm:pt-3 sm:border-t border-slate-100 dark:border-slate-700">
                                        <Link :href="route('teacher.courses.show', course.id)"
                                             class="flex-1 flex items-center justify-center py-2 bg-blue-600 text-white hover:bg-blue-500 rounded-md transition font-black text-[9px] sm:text-[10px] uppercase tracking-wider shadow-sm">
                                            Manage Class
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- EMPTY STATE -->
                        <div v-else class="text-center py-16 bg-white dark:bg-slate-800 rounded-xl border border-dashed border-slate-300 dark:border-slate-700 shadow-sm mx-1 sm:mx-0">
                            <svg class="mx-auto h-10 w-10 text-slate-300 dark:text-slate-600 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                            </svg>
                            <h3 class="text-[11px] font-black uppercase tracking-widest text-slate-900 dark:text-white">No classes found</h3>
                            <p class="mt-1 text-[9px] text-slate-500 dark:text-slate-400 font-bold uppercase tracking-widest">
                                {{ searchQuery ? 'Adjust your search filters.' : (activeTab === 'active' ? 'Get started by creating your first class.' : 'No hidden classes.') }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <Modal :show="showCreateModal" @close="showCreateModal = false" maxWidth="sm">
            <div class="p-6 bg-white dark:bg-slate-800 rounded-xl shadow-lg border border-slate-200 dark:border-slate-700">
                <h3 class="font-black text-sm text-slate-900 dark:text-white mb-4 uppercase tracking-tight flex items-center gap-2">
                    <div class="w-6 h-6 rounded bg-blue-100 text-blue-600 flex items-center justify-center">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                    </div>
                    Create New Class
                </h3>
                <form @submit.prevent="submitCourse" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="col-span-2 sm:col-span-1">
                            <label class="block text-[9px] font-black uppercase text-slate-500 mb-1.5 tracking-widest">Class Name *</label>
                            <input v-model="form.title" type="text" class="w-full rounded-md bg-slate-50 dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white p-2 text-xs focus:ring-2 focus:ring-blue-500 shadow-sm transition" required autofocus />
                            <InputError class="mt-1 text-[9px]" :message="form.errors.title" />
                        </div>
                        <div class="col-span-2 sm:col-span-1">
                            <label class="block text-[9px] font-black uppercase text-slate-500 mb-1.5 tracking-widest">Year Level</label>
                            <select v-model="form.difficulty_level" class="w-full rounded-md bg-slate-50 dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white p-2 text-xs focus:ring-2 focus:ring-blue-500 shadow-sm transition cursor-pointer dark:[color-scheme:dark]">
                                <option value="beginner" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">1st Year</option>
                                <option value="intermediate" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">2nd Year</option>
                                <option value="advanced" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">3rd Year</option>
                                <option value="final" class="bg-white dark:bg-slate-800 text-slate-900 dark:text-white">4th Year</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-[9px] font-black uppercase text-slate-500 mb-1.5 tracking-widest">Description</label>
                        <textarea v-model="form.description" class="w-full rounded-md bg-slate-50 dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white p-2 text-xs h-24 resize-none focus:ring-2 focus:ring-blue-500 shadow-sm transition" placeholder="Brief overview of the class..."></textarea>
                        <InputError class="mt-1 text-[9px]" :message="form.errors.description" />
                    </div>
                    
                    <div class="flex justify-end gap-3 pt-4 mt-2 border-t border-slate-100 dark:border-slate-700">
                        <button type="button" @click="showCreateModal = false" class="text-[10px] text-slate-500 px-4 py-2 font-black uppercase tracking-widest hover:text-slate-700 dark:hover:text-slate-300 transition-colors">Cancel</button>
                        
                        <!-- PREMIUM BUTTON IMPLEMENTATION: White in both light & dark mode -->
                        <button :disabled="form.processing" class="relative inline-flex items-center justify-center min-w-[130px] px-5 py-2.5 text-[10px] font-black uppercase tracking-widest text-slate-900 dark:text-slate-900 transition-all duration-300 ease-out bg-white dark:bg-white border border-slate-200 dark:border-slate-300 rounded-lg shadow-[0_4px_12px_rgba(0,0,0,0.05)] dark:shadow-[0_4px_15px_rgba(255,255,255,0.1)] hover:bg-slate-50 dark:hover:bg-slate-50 hover:scale-[1.02] hover:-translate-y-[1px] hover:shadow-[0_6px_15px_rgba(0,0,0,0.08)] dark:hover:shadow-[0_6px_20px_rgba(255,255,255,0.15)] active:scale-[0.97] active:translate-y-0 active:shadow-sm focus:outline-none focus:ring-2 focus:ring-slate-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900 disabled:opacity-80 disabled:cursor-not-allowed disabled:hover:scale-100 disabled:hover:translate-y-0 overflow-hidden group">
                            
                            <span :class="{'opacity-0 scale-95': form.processing, 'opacity-100 scale-100': !form.processing}" class="transition-all duration-300 transform">
                                Create Class
                            </span>
                            
                            <div v-if="form.processing" class="absolute inset-0 flex items-center justify-center gap-2 animate-in fade-in duration-300">
                                <svg class="w-4 h-4 animate-spin text-slate-900 dark:text-slate-900" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <span class="text-[9px] font-black uppercase tracking-widest text-slate-900 dark:text-slate-900">Saving...</span>
                            </div>

                        </button>
                    </div>
                </form>
            </div>
        </Modal>

    </AuthenticatedLayout>
</template>

<style scoped>
.scrollbar-hide::-webkit-scrollbar {
    display: none;
}
.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>