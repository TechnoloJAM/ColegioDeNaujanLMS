<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Head, useForm, Link } from '@inertiajs/vue3';

defineProps({
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <Head title="Forgot Password" />

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
                            Welcome to Colegio de Naujan <br> Learning Management System.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex flex-1 flex-col justify-center px-4 py-12 sm:px-6 lg:flex-none lg:px-20 xl:px-24">
            <div class="mx-auto w-full max-w-sm lg:w-96">
                
                <Link :href="route('login')" class="inline-flex items-center gap-2 text-sm font-bold text-slate-500 hover:text-blue-600 transition-colors mb-6">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    Back to Login
                </Link>

                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-slate-900">
                        Forgot your password?
                    </h2>
                    <p class="mt-2 text-sm text-slate-500 leading-relaxed">
                        No problem. Enter your email address below and we will send you a secure link to reset it.
                    </p>
                </div>

                <div class="mt-8">
                    <div v-if="status" class="mb-4 p-3 rounded-lg bg-green-50 text-sm font-medium text-green-700 border border-green-200 shadow-sm">
                        {{ status }}
                    </div>

                    <form @submit.prevent="submit" class="space-y-6">
                        <div>
                            <InputLabel for="email" value="Email Address" class="font-bold text-slate-700" />

                            <TextInput
                                id="email"
                                type="email"
                                class="mt-1.5 block w-full py-2 text-sm rounded-lg"
                                v-model="form.email"
                                required
                                autofocus
                                placeholder="name@example.com"
                                autocomplete="username"
                            />

                            <InputError class="mt-1.5" :message="form.errors.email" />
                        </div>

                        <div>
                            <PrimaryButton
                                class="flex w-full justify-center text-sm font-bold py-3 rounded-lg bg-blue-600 hover:bg-blue-700 transition-colors"
                                :class="{ 'opacity-50 cursor-not-allowed': form.processing }"
                                :disabled="form.processing"
                            >
                                <svg v-if="form.processing" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                {{ form.processing ? 'Sending Link...' : 'Email Reset Link' }}
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>