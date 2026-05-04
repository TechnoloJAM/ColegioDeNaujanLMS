<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import PasswordInput from '@/Components/PasswordInput.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
});

// Dedicated form object just for handling the secure background reset
const resetForm = useForm({
    email: '',
});

const submit = () => {
    // We removed the 'onFinish: () => form.reset()' so the fields NEVER clear automatically
    form.post(route('login'));
};

// HIGH SECURITY FORGOT PASSWORD TRIGGER
const triggerReset = () => {
    // 1. Ensure they actually typed an email in the login field first
    if (!form.email) {
        form.setError('email', 'Please enter your email address above first to reset your password.');
        document.getElementById('email').focus();
        return;
    }
    
    // 2. Securely grab the typed email
    resetForm.email = form.email;
    
    // 3. Fire the request directly to the backend without opening a vulnerable standalone form
    resetForm.post(route('password.email'), {
        preserveScroll: true,
        preserveState: true,
    });
};
</script>

<template>
    <Head title="Log in" />

    <div class="flex h-screen min-h-full bg-white transition-colors duration-300">
        
        <div class="relative hidden w-0 flex-1 overflow-hidden bg-blue-800 lg:block">
            <div class="flex h-full flex-col justify-center px-4 py-12 sm:px-6 lg:px-20 xl:px-24 relative z-10">
                <div class="flex flex-row items-center justify-start">
                    <img class="h100 w-100 -ml-32" src="/images/Logo2.png" alt="Colegio de Naujan" />
                    <div class="-ml-16 flex-shrink-0">
                        <h2 class="text-4xl font-bold tracking-tight text-white">Colegio de Naujan</h2>
                        <p class="mt-2 text-lg text-blue-100">Welcome back to your learning space.</p>
                    </div>
                </div>
            </div>
            <div class="absolute -bottom-24 -left-24 w-96 h-96 bg-blue-600 rounded-full blur-3xl opacity-50"></div>
            <div class="absolute top-12 right-12 w-64 h-64 bg-blue-400 rounded-full blur-3xl opacity-30"></div>
        </div>

        <div class="flex flex-1 flex-col justify-center px-4 py-12 sm:px-6 lg:flex-none lg:px-20 xl:px-24 overflow-y-auto bg-white">
            
            <div v-if="status" class="mx-auto w-full max-w-sm lg:w-96 py-8 flex flex-col items-center text-center">
                <div class="w-16 h-16 rounded-full bg-emerald-50 border border-emerald-100 flex items-center justify-center mb-6 shadow-sm">
                    <svg class="w-8 h-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                </div>
                <h2 class="text-2xl font-bold tracking-tight text-slate-900 mb-2">Reset Link Sent!</h2>
                <p class="text-sm text-slate-500 mb-6 leading-relaxed">
                    We have securely sent a password reset link to <strong>{{ resetForm.email || form.email }}</strong>. Please check your inbox to continue.
                </p>
                <a :href="route('login')" class="w-full inline-flex justify-center py-3 rounded-lg shadow-md bg-blue-600 hover:bg-blue-700 text-white text-[10px] uppercase tracking-widest font-black transition-colors">
                    Return to Login
                </a>
            </div>

            <div v-else class="mx-auto w-full max-w-sm lg:w-96 py-8">
                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-slate-900">
                        Sign in to your account
                    </h2>
                    <p class="mt-2 text-sm text-slate-600 font-medium">
                        Don't have an account?
                        <Link :href="route('register')" class="font-bold text-blue-700 hover:text-blue-600 underline transition-colors">
                            Register here
                        </Link>
                    </p>
                </div>

                <div class="mt-8">
                    <div class="mb-6">
                        <a :href="route('auth.google')" class="flex w-full justify-center items-center gap-3 rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50 transition-all">
                            <svg class="h-5 w-5" viewBox="0 0 24 24"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.84z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>
                            <span>Sign in with Google</span>
                        </a>
                    </div>

                    <div class="relative mt-6 mb-6">
                        <div class="absolute inset-0 flex items-center" aria-hidden="true">
                            <div class="w-full border-t border-slate-300"></div>
                        </div>
                        <div class="relative flex justify-center">
                            <span class="bg-white px-3 text-xs font-medium text-slate-500 uppercase tracking-widest">Or continue with email</span>
                        </div>
                    </div>

                    <form @submit.prevent="submit" class="space-y-4">
                        <div>
                            <InputLabel for="email" value="Email Address" class="font-bold text-slate-700" />
                            <TextInput id="email" type="email" class="mt-1.5 block w-full text-sm py-2 rounded-lg shadow-sm" v-model="form.email" required autofocus autocomplete="username" />
                            <InputError class="mt-1" :message="form.errors.email" />
                            <InputError class="mt-1" :message="resetForm.errors.email" />
                        </div>

                        <div>
                            <InputLabel for="password" value="Password" class="font-bold text-slate-700" />
                            <PasswordInput id="password" class="mt-1.5 block w-full text-sm py-2 rounded-lg shadow-sm" v-model="form.password" required autocomplete="current-password" />
                            <InputError class="mt-1" :message="form.errors.password" />
                        </div>

                        <div class="flex items-center justify-end pt-1">
                            <button v-if="canResetPassword" type="button" @click.prevent="triggerReset" class="text-xs font-bold text-blue-600 hover:text-blue-500 transition-colors flex items-center gap-1" :disabled="resetForm.processing">
                                <svg v-if="resetForm.processing" class="animate-spin h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                                <span>{{ resetForm.processing ? 'Sending...' : 'Forgot your password?' }}</span>
                            </button>
                        </div>

                        <div class="pt-2">
                            <PrimaryButton class="w-full justify-center py-3 rounded-lg text-sm font-bold shadow-md bg-blue-600 hover:bg-blue-700" :class="{ 'opacity-50 cursor-not-allowed': form.processing }" :disabled="form.processing">
                                {{ form.processing ? 'Signing in...' : 'Sign in' }}
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>