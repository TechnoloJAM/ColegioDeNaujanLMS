<script setup>
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Register" />

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
                            Join our learning community today.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex flex-1 flex-col justify-center px-4 py-12 sm:px-6 lg:flex-none lg:px-20 xl:px-24">
            <div class="mx-auto w-full max-w-sm lg:w-96">
                <div>
                    <h2 class="mt-8 text-2xl font-bold tracking-tight text-slate-900">
                        Create your account
                    </h2>
                    <p class="mt-2 text-sm text-slate-600 font-medium">
                        Already have an account?
                        <Link :href="route('login')" class="font-bold text-blue-700 hover:text-blue-600 underline transition-colors">
                            Sign in here
                        </Link>
                    </p>
                </div>

                <div class="mt-10">
                    <div class="bg-slate-50 p-6 rounded-xl border border-slate-200 text-center shadow-sm">
                        <h3 class="text-slate-900 font-black text-lg mb-2">Step 1: Verification</h3>
                        <p class="text-sm text-slate-500 mb-6 leading-relaxed">
                            We use Google to securely verify your identity before setting up your student profile.
                        </p>
                        
                        <a :href="route('auth.google')" class="flex w-full justify-center items-center gap-3 rounded-lg bg-blue-600 px-4 py-3 text-white font-bold shadow-md hover:bg-blue-700 hover:shadow-lg transition-all transform hover:-translate-y-0.5">
                            <div class="bg-white p-1 rounded-full shrink-0">
                                <svg class="h-4 w-4" viewBox="0 0 24 24"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.84z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>
                            </div>
                            <span>Verify with Google</span>
                        </a>
                    </div>
                    
                    <div class="mt-6 flex items-center justify-center gap-2 text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                        <span class="text-xs font-medium">Your information is secure.</span>
                    </div>

                </div>
            </div>
        </div>
    </div>
</template>