<script setup>
import { useForm, Head, Link } from '@inertiajs/vue3'; 
import { ref } from 'vue';
import TextInput from '@/Components/TextInput.vue';
import PasswordInput from '@/Components/PasswordInput.vue'; 
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import Checkbox from '@/Components/Checkbox.vue';
import InputError from '@/Components/InputError.vue';

const props = defineProps({
    user: Object
});

const currentStep = ref(1);

const currentYear = new Date().getFullYear();
const years = ref(Array.from({ length: currentYear - 2016 + 1 }, (_, i) => currentYear - i));

const idYear = ref(currentYear.toString());
const idNumber = ref('');

const form = useForm({
    school_id: '',
    program: '',
    contact_number: '',
    password: '',
    password_confirmation: '',
    terms: false,
});

const nextStep = () => { currentStep.value++; };
const prevStep = () => { currentStep.value--; };

const submit = () => {
    form.school_id = `${idYear.value}-${idNumber.value}`;
    form.post(route('register.complete'));
};
</script>

<template>
    <Head title="Complete Setup" />

    <div class="flex h-screen min-h-full bg-white">
        
        <div class="relative hidden w-0 flex-1 overflow-hidden bg-blue-800 lg:block">
            <div class="flex h-full flex-col justify-center px-4 py-12 sm:px-6 lg:px-20 xl:px-24">
                <div class="flex flex-row items-center justify-start">
                    <img class="h100 w-100 -ml-32" src="/images/Logo2.png" alt="Colegio de Naujan" />
                    <div class="-ml-16 flex-shrink-0">
                        <h2 class="text-4xl font-bold tracking-tight text-white">
                            Colegio de Naujan
                        </h2>
                        <p class="mt-2 text-lg text-blue-100">
                            Complete your profile to <br> access your personalized dashboard.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex flex-1 flex-col justify-center px-4 py-12 sm:px-6 lg:flex-none lg:px-20 xl:px-24 overflow-y-auto">
            <div class="mx-auto w-full max-w-sm lg:w-96 py-8">
                
                <div class="mb-8">
                    <div class="flex items-center justify-between text-[10px] font-black uppercase tracking-widest text-slate-400 mb-2">
                        <span :class="{'text-blue-600': currentStep >= 1}">1. Basics</span>
                        <span :class="{'text-blue-600': currentStep >= 2}">2. Academics</span>
                        <span :class="{'text-blue-600': currentStep >= 3}">3. Security</span>
                    </div>
                    <div class="w-full bg-slate-100 rounded-full h-1.5 overflow-hidden">
                        <div class="bg-blue-600 h-1.5 rounded-full transition-all duration-500 ease-out" :style="`width: ${(currentStep / 3) * 100}%`"></div>
                    </div>
                </div>

                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-slate-900">
                        Welcome, {{ user.name.split(' ')[0] }}!
                    </h2>
                    <p class="mt-2 text-sm text-slate-600 font-medium">
                        Let's get your student account ready.
                    </p>
                </div>

                <div class="mt-8">
                    <form @submit.prevent="submit" class="flex-1">
                        
                        <transition enter-active-class="transition duration-300 ease-out" enter-from-class="opacity-0 translate-x-4" enter-to-class="opacity-100 translate-x-0" leave-active-class="hidden">
                            <div v-if="currentStep === 1" class="space-y-5">
                                <div>
                                    <InputLabel value="Student ID Number" class="font-bold text-slate-700" />
                                    <div class="flex items-center mt-1.5 gap-2">
                                        <select v-model="idYear" class="block w-2/5 border-slate-300 rounded-lg shadow-sm py-2.5 text-sm focus:border-blue-500 focus:ring-blue-500 cursor-pointer font-bold text-slate-700 bg-slate-50">
                                            <option v-for="year in years" :key="year" :value="year">{{ year }}</option>
                                        </select>
                                        <span class="text-slate-400 font-bold">-</span>
                                        <TextInput id="school_id" v-model="idNumber" type="text" class="block w-3/5 py-2.5 text-sm rounded-lg shadow-sm" placeholder="e.g. 0123" required autofocus />
                                    </div>
                                    <InputError :message="form.errors.school_id" class="mt-1 text-[11px]" />
                                </div>

                                <div>
                                    <InputLabel for="contact" value="Mobile Number" class="font-bold text-slate-700" />
                                    <TextInput id="contact" v-model="form.contact_number" type="text" class="mt-1.5 block w-full py-2.5 text-sm rounded-lg shadow-sm" placeholder="e.g. 09123456789" required />
                                    <InputError :message="form.errors.contact_number" class="mt-1" />
                                </div>

                                <div class="pt-2">
                                    <PrimaryButton type="button" @click="nextStep" class="flex w-full justify-center text-sm font-bold py-3 rounded-lg bg-blue-600 hover:bg-blue-700 transition-colors shadow-md" :disabled="!idNumber || !form.contact_number">
                                        Next Step
                                    </PrimaryButton>
                                </div>
                            </div>
                        </transition>

                        <transition enter-active-class="transition duration-300 ease-out" enter-from-class="opacity-0 translate-x-4" enter-to-class="opacity-100 translate-x-0" leave-active-class="hidden">
                            <div v-if="currentStep === 2" class="space-y-5">
                                <div>
                                    <InputLabel for="program" value="Academic Program" class="font-bold text-slate-700" />
                                    <select id="program" v-model="form.program" class="mt-1.5 block w-full border-slate-300 rounded-lg shadow-sm py-2.5 text-sm focus:border-blue-500 focus:ring-blue-500 cursor-pointer" required>
                                        <option value="" disabled>Select your program...</option>
                                        <option value="BTVTEd - Computer Hardware Servicing">BTVTEd - Major Computer Hardware Servicing</option>
                                        <option value="BTVTEd - Welding and Steel Fabrication">BTVTEd - Welding and Steel Fabrication</option>
                                        <option value="BPA">BPA</option>
                                        <option value="BSIS">BSIS</option>
                                    </select>
                                    <InputError :message="form.errors.program" class="mt-1" />
                                </div>

                                <div class="flex gap-3 pt-2">
                                    <button type="button" @click="prevStep" class="w-1/3 py-3 rounded-lg bg-slate-100 text-slate-600 font-bold text-sm hover:bg-slate-200 transition text-center shadow-sm">Back</button>
                                    <PrimaryButton type="button" @click="nextStep" class="w-2/3 flex justify-center text-sm font-bold py-3 rounded-lg bg-blue-600 hover:bg-blue-700 transition-colors shadow-md" :disabled="!form.program">
                                        Next Step
                                    </PrimaryButton>
                                </div>
                            </div>
                        </transition>

                        <transition enter-active-class="transition duration-300 ease-out" enter-from-class="opacity-0 translate-x-4" enter-to-class="opacity-100 translate-x-0" leave-active-class="hidden">
                            <div v-if="currentStep === 3" class="space-y-4">
                                
                                <div class="flex flex-col items-center justify-center p-4 bg-slate-50 border border-slate-200 rounded-xl mb-2">
                                    <div class="relative w-16 h-16 mb-2">
                                        <img v-if="user.avatar" :src="user.avatar" referrerpolicy="no-referrer" class="w-16 h-16 rounded-full object-cover border-4 border-white shadow-md bg-white" />
                                        <div v-else class="w-16 h-16 rounded-full border-4 border-white shadow-md bg-blue-600 flex items-center justify-center text-white text-xl font-black">
                                            {{ user.name.charAt(0) }}
                                        </div>
                                    </div>
                                    <span class="text-[9px] font-black uppercase tracking-widest text-blue-600 bg-blue-100 px-2 py-0.5 rounded-full">Synced from Google</span>
                                </div>

                                <div>
                                    <InputLabel for="password" value="Create LMS Password" class="font-bold text-slate-700" />
                                    <PasswordInput id="password" v-model="form.password" class="mt-1.5 block w-full py-2.5 text-sm shadow-sm" placeholder="Minimum 8 characters" required />
                                    <InputError :message="form.errors.password" class="mt-1" />
                                </div>

                                <div>
                                    <InputLabel for="password_confirmation" value="Confirm Password" class="font-bold text-slate-700" />
                                    <PasswordInput id="password_confirmation" v-model="form.password_confirmation" class="mt-1.5 block w-full py-2.5 text-sm shadow-sm" required />
                                </div>

                                <div class="flex items-start pt-1">
                                    <Checkbox name="terms" v-model:checked="form.terms" required />
                                    <div class="ml-3 text-xs">
                                        <label for="terms" class="font-medium text-slate-600">I agree to the <a href="#" class="text-blue-600 underline">Terms</a> and <a href="#" class="text-blue-600 underline">Privacy Policy</a>.</label>
                                    </div>
                                </div>

                                <div class="flex gap-3 pt-2">
                                    <button type="button" @click="prevStep" class="w-1/3 py-3 rounded-lg bg-slate-100 text-slate-600 font-bold text-sm hover:bg-slate-200 transition text-center shadow-sm">Back</button>
                                    <PrimaryButton type="submit" class="w-2/3 flex justify-center text-sm font-bold py-3 rounded-lg bg-emerald-600 hover:bg-emerald-700 transition-colors shadow-md" :class="{ 'opacity-50 cursor-not-allowed': form.processing }" :disabled="form.processing || !form.terms">
                                        {{ form.processing ? 'Saving...' : 'Finish Setup' }}
                                    </PrimaryButton>
                                </div>
                            </div>
                        </transition>

                        <div class="mt-6 text-center pt-4">
                            <Link :href="route('logout')" method="post" as="button" class="text-[11px] font-bold uppercase tracking-widest text-slate-400 hover:text-red-500 transition-colors">
                                Cancel & Sign Out
                            </Link>
                        </div>

                    </form>
                </div>
            </div>
        </div>
    </div>
</template>