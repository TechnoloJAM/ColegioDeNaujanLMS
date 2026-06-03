<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import Modal from '@/Components/Modal.vue';
import { Head, useForm, Link, router } from '@inertiajs/vue3';
import { ref, reactive, nextTick } from 'vue';
import { BookOpen, ChevronLeft, ImagePlus } from 'lucide-vue-next';

const form = useForm({
    title: '',
    description: '',
    difficulty_level: 'beginner', // We keep the db column name, but label it Year Level
    thumbnail: null,
});

const showCropModal = ref(false);
const imageToCrop = ref(null);
const croppedPreview = ref(null);
const cropper = reactive({
    scale: 1,
    x: 0,
    y: 0,
    isDragging: false,
    startX: 0,
    startY: 0
});
const imageRef = ref(null);

// 🛡️ CRITICAL FIX 1: Use a Vue Ref to safely select the hidden file input
const thumbnailInput = ref(null);

const triggerFileInput = () => {
    if (thumbnailInput.value) {
        thumbnailInput.value.click();
    }
};

// 1. Handle File Select -> Open Modal
const handleFileChange = (event) => {
    const file = event.target.files[0];
    if (file) {
        imageToCrop.value = URL.createObjectURL(file);
        cropper.scale = 1;
        cropper.x = 0;
        cropper.y = 0;
        showCropModal.value = true;
    }
    // Reset the input so the user can re-select the same file if they cancel
    if (event.target) {
        event.target.value = '';
    }
};

// 2. Cropper Mouse Events (Drag Logic)
const startDrag = (e) => {
    e.preventDefault();
    cropper.isDragging = true;
    cropper.startX = e.clientX - cropper.x;
    cropper.startY = e.clientY - cropper.y;
};
const onDrag = (e) => {
    if (!cropper.isDragging) return;
    e.preventDefault();
    cropper.x = e.clientX - cropper.startX;
    cropper.y = e.clientY - cropper.startY;
};
const stopDrag = () => {
    cropper.isDragging = false;
};

// 3. Apply Crop (Draw to Canvas)
const applyCrop = () => {
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');
    const img = imageRef.value;

    // Set desired output size (16:9 ratio, e.g., 800x450)
    canvas.width = 800;
    canvas.height = 450;

    ctx.fillStyle = '#0f172a';
    ctx.fillRect(0, 0, canvas.width, canvas.height);

    const scaleFactor = canvas.width / 480;

    ctx.translate(canvas.width / 2, canvas.height / 2);
    ctx.scale(cropper.scale, cropper.scale);
    ctx.translate(-canvas.width / 2, -canvas.height / 2);
    
    ctx.drawImage(img, cropper.x * scaleFactor, cropper.y * scaleFactor, img.naturalWidth * scaleFactor, img.naturalHeight * scaleFactor);
    
    canvas.toBlob((blob) => {
        const file = new File([blob], "course_thumbnail.jpg", { type: "image/jpeg" });
        form.thumbnail = file;
        croppedPreview.value = URL.createObjectURL(blob);
        showCropModal.value = false;
    }, 'image/jpeg', 0.9);
};

// 🛡️ CRITICAL FIX 2: Ensure the payload is sent as FormData so the cropped image actually uploads
const submit = () => {
    form.post(route('teacher.courses.store'), {
        forceFormData: true, 
        preserveScroll: true
    });
};
</script>

<template>
    <Head title="Create Course" />

    <AuthenticatedLayout>
        
        <div class="mb-4 flex flex-col md:flex-row md:justify-between md:items-end border-b border-slate-100 dark:border-slate-800 pb-3">
            <div class="min-w-0">
                <nav class="flex items-center gap-1.5 mb-1.5">
                    <span class="text-[9px] font-black uppercase text-slate-400 tracking-widest truncate max-w-[150px] sm:max-w-none">My Classes</span>
                    <ChevronLeft class="w-3 h-3 text-slate-300 shrink-0" />
                    <span class="text-[9px] font-black uppercase text-blue-500 tracking-widest shrink-0">New</span>
                </nav>
                <div class="flex items-center gap-2">
                    <BookOpen class="w-5 h-5 text-blue-600 dark:text-blue-500 shrink-0" />
                    <h1 class="text-lg sm:text-xl font-black text-slate-900 dark:text-white tracking-tight leading-tight truncate">Create New Class</h1>
                </div>
            </div>
            
            <Link :href="route('teacher.courses.index')" class="mt-3 md:mt-0 flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 text-[10px] font-bold hover:bg-slate-50 dark:hover:bg-slate-700 transition shadow-sm w-full md:w-auto shrink-0">
                <ChevronLeft class="w-3 h-3" /> Back
            </Link>
        </div>

        <div class="max-w-2xl mx-auto pb-8">
            <div class="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm p-4 sm:p-6">
                
                <form @submit.prevent="submit" class="space-y-5">
                    
                    <div>
                        <label class="block text-[9px] font-black uppercase tracking-widest text-slate-500 mb-1.5">Cover Image</label>
                        
                        <div v-if="croppedPreview" class="mb-3 relative w-full sm:w-2/3 md:w-1/2 aspect-video bg-slate-200 dark:bg-slate-900 rounded-lg overflow-hidden border border-slate-200 dark:border-slate-700 group shadow-sm">
                            <img :src="croppedPreview" class="w-full h-full object-cover" />
                            <div class="absolute inset-0 bg-black/50 flex flex-col items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity cursor-pointer backdrop-blur-sm"
                                 @click="triggerFileInput"> <ImagePlus class="w-6 h-6 text-white mb-1" />
                                <span class="text-white text-[9px] font-black uppercase tracking-widest">Change Image</span>
                            </div>
                        </div>

                        <div v-else @click="triggerFileInput" class="w-full sm:w-2/3 md:w-1/2 aspect-video bg-slate-50 dark:bg-slate-900/50 border-2 border-dashed border-slate-300 dark:border-slate-700 rounded-lg flex flex-col items-center justify-center cursor-pointer hover:border-blue-400 hover:bg-blue-50/50 dark:hover:bg-slate-800 transition-all group mb-3"> <ImagePlus class="w-6 h-6 text-slate-400 group-hover:text-blue-500 mb-2 transition-colors" />
                            <span class="text-[9px] font-black text-slate-500 uppercase tracking-widest group-hover:text-blue-600 transition-colors">Upload Cover</span>
                        </div>

                        <input 
                            ref="thumbnailInput"
                            id="course_thumbnail"
                            type="file" 
                            @change="handleFileChange"
                            accept="image/*"
                            class="hidden"
                        />
                        <InputError class="mt-1 text-[9px]" :message="form.errors.thumbnail" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="sm:col-span-2">
                            <label for="course_title" class="block text-[9px] font-black uppercase tracking-widest text-slate-500 mb-1.5">Class Title <span class="text-red-500">*</span></label>
                            <input id="course_title" v-model="form.title" type="text" class="w-full rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white p-2.5 text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent transition shadow-sm" placeholder="e.g., Data Structures & Algorithms" required />
                            <InputError class="mt-1 text-[9px]" :message="form.errors.title" />
                        </div>

                        <div>
                            <label for="course_difficulty" class="block text-[9px] font-black uppercase tracking-widest text-slate-500 mb-1.5">Year Level <span class="text-red-500">*</span></label>
                            <select id="course_difficulty" v-model="form.difficulty_level" class="w-full rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white p-2.5 text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent transition shadow-sm cursor-pointer" required>
                                <option value="beginner">1st Year</option>
                                <option value="intermediate">2nd Year</option>
                                <option value="advanced">3rd Year</option>
                                <option value="final">4th Year</option>
                            </select>
                            <InputError class="mt-1 text-[9px]" :message="form.errors.difficulty_level" />
                        </div>
                    </div>

                    <div>
                         <label for="course_description" class="block text-[9px] font-black uppercase tracking-widest text-slate-500 mb-1.5">Description (Optional)</label>
                        <textarea id="course_description" v-model="form.description" class="w-full rounded-lg bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-700 text-slate-900 dark:text-white p-2.5 text-xs focus:ring-2 focus:ring-blue-500 focus:border-transparent transition shadow-sm h-28 resize-none" placeholder="Brief overview of the subject..."></textarea>
                        <InputError class="mt-1 text-[9px]" :message="form.errors.description" />
                    </div>

                    <div class="flex items-center justify-end gap-2 pt-4 mt-2 border-t border-slate-100 dark:border-slate-700">
                        <Link :href="route('teacher.courses.index')" class="px-4 py-2 text-[10px] font-black uppercase tracking-widest text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 transition rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800">
                            Cancel
                        </Link>
                        <button type="submit" :disabled="form.processing" class="px-6 py-2 bg-blue-600 hover:bg-blue-500 text-white text-[10px] font-black uppercase tracking-widest rounded-lg shadow-sm transition disabled:opacity-50 disabled:cursor-not-allowed">
                            <span v-if="form.processing">Creating...</span>
                            <span v-else>Create Class</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <Modal :show="showCropModal" @close="showCropModal = false" maxWidth="md">
            <div class="p-6 bg-white dark:bg-slate-900 rounded-xl border border-slate-200 dark:border-slate-800 shadow-2xl">
                <h3 class="text-sm font-black uppercase tracking-tight text-slate-900 dark:text-white mb-2">Adjust Cover Image</h3>
                <p class="text-[10px] text-slate-500 dark:text-slate-400 mb-4 font-bold uppercase tracking-widest">Drag to position, slide to zoom.</p>
                
                <div class="relative w-full overflow-hidden bg-slate-100 dark:bg-slate-950 rounded-lg border border-slate-200 dark:border-slate-700 cursor-move mb-5 shadow-inner"
                     style="height: 250px;" 
                     @mousedown="startDrag" @mousemove="onDrag" @mouseup="stopDrag" @mouseleave="stopDrag">
                    
                    <img ref="imageRef" 
                         :src="imageToCrop" 
                         class="absolute max-w-none origin-center pointer-events-none"
                         :style="{ 
                             transform: `translate(${cropper.x}px, ${cropper.y}px) scale(${cropper.scale})`,
                             left: '50%', top: '50%', 
                             marginLeft: '-50%', marginTop: '-50%'
                         }" />
                    
                    <div class="absolute inset-0 pointer-events-none border border-white/40 rounded-lg shadow-[0_0_0_9999px_rgba(0,0,0,0.6)] z-10"></div>
                </div>

                <div class="flex items-center gap-3 mb-6 bg-slate-50 dark:bg-slate-800/50 p-3 rounded-lg border border-slate-100 dark:border-slate-700/50">
                    <span class="text-[9px] font-black uppercase tracking-widest text-slate-500">Zoom</span>
                    <input type="range" min="0.5" max="3" step="0.1" v-model.number="cropper.scale" class="w-full h-1.5 bg-slate-200 rounded-lg appearance-none cursor-pointer dark:bg-slate-700 accent-blue-600">
                </div>

                <div class="flex justify-end gap-2 border-t border-slate-100 dark:border-slate-800 pt-4">
                    <button type="button" @click="showCropModal = false" class="px-4 py-2 text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 font-black text-[10px] uppercase tracking-widest transition rounded-lg hover:bg-slate-50 dark:hover:bg-slate-800">Cancel</button>
                    <button type="button" @click="applyCrop" class="px-5 py-2 bg-blue-600 hover:bg-blue-500 text-white rounded-lg font-black text-[10px] uppercase tracking-widest shadow-sm transition">Apply Crop</button>
                </div>
            </div>
        </Modal>

    </AuthenticatedLayout>
</template>