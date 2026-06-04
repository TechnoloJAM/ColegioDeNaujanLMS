<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import { FilePlus2, ChevronLeft, Paperclip, CheckCircle } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps({
    course: Object,
    source: String,
    backUrl: String
});

const form = useForm({
    title: '',
    type: 'assignment',
    description: '',
    points: 100,
    due_date: '',
    closing_date: '',
    files: [],
});

// Refs for UI state
const showSuccess = ref(false);
const fileInput = ref(null);

// Calculate current local datetime for the calendar restrictions
const minDateTime = computed(() => {
    const now = new Date();
    now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    return now.toISOString().slice(0, 16);
});

const goBack = () => {
    if (window.history.length > 1) {
        window.history.back();
    } else {
        router.visit(props.backUrl);
    }
};

const submit = () => {
    form.post(route('teacher.assignments.store', props.course.id), {
        preserveScroll: true,
        onSuccess: () => {
            // 1. Reset the form data back to default
            form.reset();
            
            // 2. Clear the physical file input element in the browser
            if (fileInput.value) {
                fileInput.value.value = '';
            }
            
            // 3. Show success message
            showSuccess.value = true;
            
            // 4. Hide success message after 3.5 seconds
            setTimeout(() => {
                showSuccess.value = false;
            }, 3500);
        }
    });
};
</script>

<template>
    <Head title="Create Assignment" />

    <AuthenticatedLayout>
        
        <div class="mb-4 flex flex-col md:flex-row md:justify-between md:items-end border-b border-slate-100 dark:border-slate-800 pb-3">
            <div class="min-w-0">
                <nav class="flex items-center gap-1.5 mb-1.5">
                    <span class="text-[9px] font-black uppercase text-slate-400 tracking-widest truncate max-w-[150px] sm:max-w-none">{{ course.title }}</span>
                    <ChevronLeft class="w-3 h-3 text-slate-300 shrink-0" />
                    <span class="text-[9px] font-black uppercase text-blue-500 tracking-widest shrink-0">New Task</span>
                </nav>
                <div class="flex items-center gap-2">
                    <FilePlus2 class="w-5 h-5 text-blue-600 dark:text-blue-500 shrink-0" />
                    <h1 class="text-lg sm:text-xl font-black text-slate-900 dark:text-white tracking-tight leading-tight truncate">Create Assignment</h1>
                </div>
            </div>
            
            <button @click="goBack" class="mt-3 md:mt-0 flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 text-[10px] font-bold hover:bg-slate-50 dark:hover:bg-slate-700 transition shadow-sm w-full md:w-auto shrink-0 cursor-pointer">
                <ChevronLeft class="w-3 h-3" /> Back
            </button>
        </div>

        <div class="max-w-2xl mx-auto pb-8">
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm p-4 sm:p-6">
                
                <transition 
                    enter-active-class="transition ease-out duration-300" 
                    enter-from-class="opacity-0 -translate-y-2" 
                    enter-to-class="opacity-100 translate-y-0"
                    leave-active-class="transition ease-in duration-200" 
                    leave-from-class="opacity-100 translate-y-0" 
                    leave-to-class="opacity-0 -translate-y-2"
                >
                    <div v-if="showSuccess" class="mb-4 p-3 bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-800 rounded-lg flex items-center gap-2 text-emerald-700 dark:text-emerald-400">
                        <CheckCircle class="w-4 h-4 shrink-0" />
                        <span class="text-[10px] font-black uppercase tracking-widest">Assignment created successfully!</span>
                    </div>
                </transition>
                
                <form @submit.prevent="submit" class="space-y-4">
                    
                    <div>
                        <label class="block text-[9px] font-black uppercase tracking-widest text-slate-500 mb-1">Title <span class="text-red-500">*</span></label>
                        <input v-model="form.title" type="text" class="w-full rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white p-2 text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent transition shadow-sm" placeholder="e.g., Chapter 1 Quiz" required autofocus />
                        <InputError class="mt-1 text-[9px]" :message="form.errors.title" />
                    </div>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[9px] font-black uppercase tracking-widest text-slate-500 mb-1">Task Type <span class="text-red-500">*</span></label>
                            <select v-model="form.type" class="w-full rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white p-2 text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent transition shadow-sm cursor-pointer" required>
                                <option value="assignment">Assignment</option>
                                <option value="activity">Activity</option>
                                <option value="performance_task">Performance Task</option>
                            </select>
                            <InputError class="mt-1 text-[9px]" :message="form.errors.type" />
                        </div>
                        
                        <div>
                            <label class="block text-[9px] font-black uppercase tracking-widest text-slate-500 mb-1">Points <span class="text-red-500">*</span></label>
                            <input v-model="form.points" type="number" min="0" class="w-full rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white p-2 text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent transition shadow-sm" required />
                            <InputError class="mt-1 text-[9px]" :message="form.errors.points" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[9px] font-black uppercase tracking-widest text-slate-500 mb-1">Due Date (Soft Deadline) <span class="text-red-500">*</span></label>
                            <input v-model="form.due_date" :min="minDateTime" type="datetime-local" class="w-full rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white p-2 text-[10px] font-bold focus:ring-2 focus:ring-blue-500 focus:border-transparent transition shadow-sm dark:[color-scheme:dark]" required />
                            <InputError class="mt-1 text-[9px]" :message="form.errors.due_date" />
                        </div>
                        
                        <div>
                            <label class="block text-[9px] font-black uppercase tracking-widest text-slate-500 mb-1">Closing Date (Hard Deadline)</label>
                            <input v-model="form.closing_date" :min="form.due_date || minDateTime" type="datetime-local" class="w-full rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white p-2 text-[10px] font-bold focus:ring-2 focus:ring-blue-500 focus:border-transparent transition shadow-sm dark:[color-scheme:dark]" />
                            <InputError class="mt-1 text-[9px]" :message="form.errors.closing_date" />
                        </div>
                    </div>

                    <div>
                         <label class="block text-[9px] font-black uppercase tracking-widest text-slate-500 mb-1">Instructions (Optional)</label>
                        <textarea v-model="form.description" class="w-full rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white p-2 text-xs h-24 focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none transition shadow-sm" placeholder="Write instructions or guidelines for the students here..."></textarea>
                        <InputError class="mt-1 text-[9px]" :message="form.errors.description" />
                    </div>

                    <div>
                        <label class="block text-[9px] font-black uppercase tracking-widest text-slate-500 mb-1">Attachments (Optional)</label>
                        <input type="file" ref="fileInput" multiple @change="e => form.files = Array.from(e.target.files)" class="block w-full text-[10px] text-slate-500 file:mr-2 file:py-1 file:px-2 file:rounded-md file:border-0 file:text-[9px] file:font-black file:uppercase file:tracking-widest file:bg-blue-50 file:text-blue-600 hover:file:bg-blue-100 cursor-pointer bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 rounded-lg p-1.5 transition shadow-sm" />
                        
                        <div v-if="form.files && form.files.length > 0" class="mt-2 space-y-1.5 max-h-28 overflow-y-auto custom-scrollbar">
                            <div v-for="(file, index) in form.files" :key="index" class="text-[9px] font-bold text-slate-600 dark:text-slate-300 flex items-center justify-between bg-slate-50 dark:bg-slate-900/50 p-2 rounded-lg border border-slate-100 dark:border-slate-800">
                                <div class="flex items-center gap-1.5 truncate pr-2">
                                    <Paperclip class="w-3 h-3 text-blue-500 shrink-0" />
                                    <span class="truncate">{{ file.name }}</span>
                                </div>
                                <span class="shrink-0 text-slate-400">{{ (file.size / 1024).toFixed(0) }} KB</span>
                            </div>
                        </div>
                        <InputError class="mt-1 text-[9px]" :message="form.errors.files" />
                    </div>

                    <div class="pt-5 mt-2 border-t border-slate-100 dark:border-slate-700 flex justify-end gap-2">
                        <button type="button" @click="goBack" class="px-4 py-2 text-[10px] font-black uppercase tracking-widest text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 transition rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800">
                            Cancel
                        </button>
                        <button type="submit" :disabled="form.processing" class="px-6 py-2 bg-blue-600 hover:bg-blue-500 text-white text-[10px] font-black uppercase tracking-widest rounded-lg shadow-sm transition disabled:opacity-50 disabled:cursor-not-allowed">
                            <span v-if="form.processing">Creating...</span>
                            <span v-else>Create Task</span>
                        </button>
                    </div>

                </form>

            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(148, 163, 184, 0.2);
    border-radius: 10px;
}
</style>