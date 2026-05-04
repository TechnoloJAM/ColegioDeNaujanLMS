<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, Link, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Modal from '@/Components/Modal.vue';
import InputError from '@/Components/InputError.vue';
import Dropdown from '@/Components/Dropdown.vue';
import { Plus } from 'lucide-vue-next'; // Imported the Plus icon

const props = defineProps({ joinedCourses: Array });

const page = usePage();
const userId = page.props.auth.user.id;

// Hidden Courses Local Storage State
const storageKey = `lms_hidden_courses_${userId}`;
const hiddenCourses = ref(JSON.parse(localStorage.getItem(storageKey)) || []);
const activeTab = ref('active');

const toggleHide = (courseId) => {
    if (hiddenCourses.value.includes(courseId)) {
        hiddenCourses.value = hiddenCourses.value.filter(id => id !== courseId);
    } else {
        hiddenCourses.value.push(courseId);
    }
    localStorage.setItem(storageKey, JSON.stringify(hiddenCourses.value));
};

const displayedCourses = computed(() => {
    if (activeTab.value === 'active') {
        return props.joinedCourses.filter(c => !hiddenCourses.value.includes(c.id));
    } else {
        return props.joinedCourses.filter(c => hiddenCourses.value.includes(c.id));
    }
});

const showJoinModal = ref(false);
const form = useForm({ enrollment_code: '' });
const imageErrors = ref({});

const handleImageError = (id) => { imageErrors.value[id] = true; };

const submitJoin = () => {
    form.post(route('student.courses.join'), {
        onSuccess: () => { showJoinModal.value = false; form.reset(); }
    });
};

// Helper function to map DB difficulty levels to Year Level text
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
        <div class="max-w-screen-2xl mx-auto flex flex-col h-full">
            
            <div class="mb-4 pb-3 border-b border-slate-200 dark:border-slate-700 shrink-0 px-1 sm:px-0">
                <h1 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight">My Classes</h1>
                <p class="text-[9px] sm:text-[10px] text-slate-500 dark:text-slate-400 font-bold uppercase tracking-widest mt-1">Access your learning materials.</p>
            </div>

            <div class="flex gap-4 items-start flex-1 min-h-0">
                
                <aside class="w-10 shrink-0 flex flex-col gap-3 sticky top-4 z-30">
                    <button @click="showJoinModal = true" class="group relative flex items-center justify-center w-10 h-10 bg-white dark:bg-slate-800 rounded-full border border-slate-200 dark:border-slate-700 text-blue-600 hover:border-blue-600 hover:bg-blue-50 dark:hover:bg-slate-700 transition shadow-sm">
                        <Plus class="w-5 h-5" />
                        <span class="absolute left-full ml-2 px-2 py-1 bg-slate-800 text-white text-[9px] font-bold rounded opacity-0 group-hover:opacity-100 transition-opacity duration-200 pointer-events-none whitespace-nowrap shadow-lg z-50">Join Class</span>
                    </button>
                </aside>

                <div class="flex-1 min-w-0 flex flex-col h-full">
                    
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
                        <div v-if="displayedCourses.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-3 sm:gap-4">
                            
                            <div v-for="course in displayedCourses" :key="course.id" 
                                 class="group bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 transition-all duration-200 flex flex-row sm:flex-col shadow-sm h-[110px] sm:h-[250px] hover:border-blue-500 dark:hover:border-blue-500 hover:shadow-md relative">
                                
                                <div class="absolute top-2 right-2 z-40">
                                    <Dropdown align="right" width="48">
                                        <template #trigger>
                                            <button class="p-1 rounded-md bg-black/40 text-white hover:bg-black/60 backdrop-blur-sm transition shadow-sm focus:outline-none">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z"></path></svg>
                                            </button>
                                        </template>
                                        <template #content>
                                            <button @click.prevent="toggleHide(course.id)" class="block w-full text-left px-4 py-2 text-[10px] font-black uppercase tracking-widest text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 transition">
                                                {{ activeTab === 'active' ? 'Hide Class' : 'Unhide Class' }}
                                            </button>
                                            <Link :href="route('student.courses.leave', course.id)" method="delete" as="button" class="block w-full text-left px-4 py-2 text-[10px] font-black uppercase tracking-widest text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 transition">
                                                Leave Class
                                            </Link>
                                        </template>
                                    </Dropdown>
                                </div>

                                <div class="w-[100px] sm:w-full h-full sm:h-28 relative bg-slate-100 dark:bg-slate-900 shrink-0 border-r sm:border-r-0 sm:border-b border-slate-200 dark:border-slate-700 rounded-l-xl sm:rounded-t-xl sm:rounded-bl-none overflow-hidden">
                                    <img v-if="course.thumbnail && !imageErrors[course.id]" 
                                         :src="course.thumbnail" 
                                         @error="handleImageError(course.id)"
                                         class="absolute inset-0 w-full h-full object-cover z-0 transition-transform duration-700 group-hover:scale-105" />
                                    
                                    <div class="absolute inset-0 bg-slate-200 dark:bg-slate-900 flex items-center justify-center" v-else>
                                         <svg class="w-8 h-8 text-slate-400 dark:text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                                    </div>

                                    <div v-if="course.pivot.status === 'pending'" class="absolute inset-0 bg-black/60 flex items-center justify-center backdrop-blur-sm z-20">
                                        <span class="bg-yellow-500 text-white text-[8px] sm:text-[9px] font-black px-2 py-1 rounded uppercase tracking-widest shadow-lg text-center">Pending</span>
                                    </div>
                                    
                                    <div v-else class="absolute sm:bottom-2 sm:left-2 top-2 left-2 z-20">
                                        <span class="text-[8px] font-black uppercase tracking-widest bg-white/90 text-blue-600 px-1.5 py-0.5 rounded shadow-sm">
                                            {{ formatYearLevel(course.difficulty_level) }}
                                        </span>
                                    </div>
                                </div>

                                <div class="p-2 sm:p-3 flex-1 flex flex-col justify-between min-w-0">
                                    <div class="min-w-0">
                                        <h3 class="font-black text-sm sm:text-base text-slate-900 dark:text-white truncate leading-tight pr-6 sm:pr-0 mb-1">
                                            {{ course.title }}
                                        </h3>
                                        <p class="text-[9px] sm:text-[10px] text-slate-500 dark:text-slate-400 line-clamp-2 sm:line-clamp-3 leading-relaxed mt-0.5">
                                            {{ course.description || 'No description provided.' }}
                                        </p>
                                    </div>
                                    
                                    <div class="flex items-center gap-1.5 sm:gap-2 mt-2 sm:pt-3 sm:border-t border-slate-100 dark:border-slate-700">
                                        <Link v-if="course.pivot.status === 'approved'" :href="route('student.courses.show', course.id)" 
                                            class="flex-1 flex items-center justify-center py-1.5 sm:py-2 bg-blue-600 text-white hover:bg-blue-500 rounded sm:rounded-md transition font-black text-[9px] sm:text-[10px] uppercase tracking-widest shadow-sm">
                                            Enter Class
                                        </Link>
                                        <button v-else disabled 
                                            class="flex-1 bg-slate-100 dark:bg-slate-700 text-slate-400 dark:text-slate-500 text-[9px] sm:text-[10px] font-black py-1.5 sm:py-2 rounded sm:rounded-md text-center cursor-not-allowed uppercase tracking-widest">
                                            Waiting...
                                        </button>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <div v-else class="text-center py-16 bg-slate-50 dark:bg-slate-800/50 rounded-xl border border-dashed border-slate-300 dark:border-slate-700 shadow-sm mx-1 sm:mx-0">
                            <svg class="mx-auto h-10 w-10 text-slate-300 dark:text-slate-600 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                            </svg>
                            <h3 class="text-[11px] font-black text-slate-900 dark:text-white uppercase tracking-widest">No {{ activeTab }} classes</h3>
                            <p v-if="activeTab === 'active'" class="mt-1 text-[9px] text-slate-500 dark:text-slate-400 font-bold uppercase tracking-widest">Join a class to get started.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <Modal :show="showJoinModal" @close="showJoinModal = false" maxWidth="sm">
            <div class="p-6 bg-white dark:bg-slate-800 rounded-xl shadow-lg">
                <h3 class="font-black text-sm text-slate-900 dark:text-white mb-4 uppercase tracking-tight flex items-center gap-2">
                    <div class="w-6 h-6 rounded bg-blue-100 text-blue-600 flex items-center justify-center">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"></path></svg>
                    </div>
                    Join a Class
                </h3>
                <form @submit.prevent="submitJoin" class="space-y-4">
                    <div>
                        <label class="block text-[9px] font-black uppercase tracking-widest text-slate-500 mb-1.5">Class Code *</label>
                        <input v-model="form.enrollment_code" type="text" class="w-full rounded-md bg-slate-50 dark:bg-slate-900 border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white text-center text-lg font-mono tracking-widest uppercase py-3 focus:ring-2 focus:ring-blue-500 shadow-sm transition" placeholder="A1B2C3" maxlength="6" required autofocus />
                        <InputError class="mt-1 text-center" :message="form.errors.enrollment_code" />
                    </div>
                    <p class="text-[10px] font-bold text-slate-400 text-center uppercase tracking-widest">Ask your teacher for the 6-character code.</p>
                    <div class="flex justify-end gap-2 pt-4 mt-2 border-t border-slate-100 dark:border-slate-700">
                        <button type="button" @click="showJoinModal = false" class="text-[10px] text-slate-500 px-3 py-2 font-black uppercase tracking-widest hover:text-slate-700 dark:hover:text-slate-300 transition">Cancel</button>
                        <button :disabled="form.processing" class="bg-blue-600 hover:bg-blue-500 text-white px-4 py-2 rounded-md text-[10px] font-black uppercase tracking-widest shadow-sm transition">Join Class</button>
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