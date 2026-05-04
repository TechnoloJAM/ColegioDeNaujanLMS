<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import PasswordInput from '@/Components/PasswordInput.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    email: {
        type: String,
        required: true,
    },
    token: {
        type: String,
        required: true,
    },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <Head title="Reset Password" />

    <div class="flex h-screen min-h-full bg-white transition-colors duration-300">
        
        <div class="relative hidden w-0 flex-1 overflow-hidden bg-blue-800 lg:block">
            <div class="flex h-full flex-col justify-center px-4 py-12 sm:px-6 lg:px-20 xl:px-24 relative z-10">
                <div class="flex flex-row items-center justify-start">
                    <img class="h100 w-100 -ml-32" src="/images/Logo2.png" alt="Colegio de Naujan" />
                    <div class="-ml-16 flex-shrink-0">
                        <h2 class="text-4xl font-bold tracking-tight text-white">Colegio de Naujan</h2>
                        <p class="mt-2 text-lg text-blue-100">Secure Account Recovery.</p>
                    </div>
                </div>
            </div>
            <div class="absolute -bottom-24 -left-24 w-96 h-96 bg-blue-600 rounded-full blur-3xl opacity-50"></div>
            <div class="absolute top-12 right-12 w-64 h-64 bg-blue-400 rounded-full blur-3xl opacity-30"></div>
        </div>

        <div class="flex flex-1 flex-col justify-center px-4 py-12 sm:px-6 lg:flex-none lg:px-20 xl:px-24 overflow-y-auto bg-white">
            <div class="mx-auto w-full max-w-sm lg:w-96 py-8">
                
                <div class="mb-6 sm:hidden flex items-center gap-3">
                     <img class="w-8 h-8 drop-shadow-sm rounded-full" src="/images/Logo2.png" alt="Colegio de Naujan" />
                     <h2 class="text-lg font-black tracking-tight text-slate-900">CDN LMS</h2>
                </div>

                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-slate-900">
                        Create new password
                    </h2>
                    <p class="mt-2 text-sm text-slate-600 font-medium">
                        Please enter your new secure password below to regain access to your dashboard.
                    </p>
                </div>

                <div class="mt-8">
                    <form @submit.prevent="submit" class="space-y-5">
                        
                        <div>
                            <InputLabel for="email" value="Account Email" class="font-bold text-slate-700 text-[10px] uppercase tracking-widest" />
                            <TextInput
                                id="email"
                                type="email"
                                class="mt-1.5 block w-full text-sm py-2.5 rounded-lg shadow-sm bg-slate-50 text-slate-500 cursor-not-allowed border-slate-200 focus:ring-0 focus:border-slate-200"
                                v-model="form.email"
                                required
                                readonly
                            />
                            <InputError class="mt-1 text-[11px]" :message="form.errors.email" />
                        </div>

                        <div>
                            <InputLabel for="password" value="New Password" class="font-bold text-slate-700 text-[10px] uppercase tracking-widest" />
                            <PasswordInput
                                id="password"
                                class="mt-1.5 block w-full text-sm py-2.5 rounded-lg shadow-sm"
                                v-model="form.password"
                                required
                                autofocus
                                autocomplete="new-password"
                                placeholder="Minimum 8 characters"
                            />
                            <InputError class="mt-1 text-[11px]" :message="form.errors.password" />
                        </div>

                        <div>
                            <InputLabel for="password_confirmation" value="Confirm Password" class="font-bold text-slate-700 text-[10px] uppercase tracking-widest" />
                            <PasswordInput
                                id="password_confirmation"
                                class="mt-1.5 block w-full text-sm py-2.5 rounded-lg shadow-sm"
                                v-model="form.password_confirmation"
                                required
                                autocomplete="new-password"
                            />
                            <InputError class="mt-1 text-[11px]" :message="form.errors.password_confirmation" />
                        </div>

                        <div class="pt-3">
                            <PrimaryButton class="w-full justify-center py-3 rounded-lg text-xs font-black shadow-md bg-blue-600 hover:bg-blue-700 uppercase tracking-widest" :class="{ 'opacity-50 cursor-not-allowed': form.processing }" :disabled="form.processing">
                                {{ form.processing ? 'Saving Password...' : 'Reset Password' }}
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>