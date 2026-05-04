<script setup>
import { computed, onMounted } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import PrimaryButton from '@/Components/PrimaryButton.vue';

const props = defineProps({
    status: {
        type: String,
    },
});

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

// AUTOMATICALLY SEND THE EMAIL ON PAGE LOAD
onMounted(() => {
    // Only auto-send if it hasn't just been sent (prevents infinite loops)
    if (props.status !== 'verification-link-sent') {
        submit();
    }
});

const verificationLinkSent = computed(() => props.status === 'verification-link-sent');
</script>

<template>
    <Head title="Verify Email" />

    <div class="flex h-screen min-h-full bg-white transition-colors duration-300">
        
        <div class="relative hidden w-0 flex-1 overflow-hidden bg-blue-800 lg:block">
            <div class="flex h-full flex-col justify-center px-4 py-12 sm:px-6 lg:px-20 xl:px-24 relative z-10">
                <div class="flex flex-row items-center justify-start">
                    <img class="h100 w-100 -ml-32" src="/images/Logo2.png" alt="Colegio de Naujan" />
                    <div class="-ml-16 flex-shrink-0">
                        <h2 class="text-4xl font-bold tracking-tight text-white">Colegio de Naujan</h2>
                        <p class="mt-2 text-lg text-blue-100">Secure Account Verification.</p>
                    </div>
                </div>
            </div>
            <div class="absolute -bottom-24 -left-24 w-96 h-96 bg-blue-600 rounded-full blur-3xl opacity-50"></div>
            <div class="absolute top-12 right-12 w-64 h-64 bg-blue-400 rounded-full blur-3xl opacity-30"></div>
        </div>

        <div class="flex flex-1 flex-col justify-center px-4 py-12 sm:px-6 lg:flex-none lg:px-20 xl:px-24 bg-white">
            <div class="mx-auto w-full max-w-sm lg:w-96">
                <div>
                    <div class="w-14 h-14 rounded-full bg-blue-50 border border-blue-100 flex items-center justify-center mb-6 shadow-sm">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                    </div>
                    
                    <h2 class="text-2xl font-bold tracking-tight text-slate-900 mb-2">
                        Check your inbox
                    </h2>
                    <p class="text-sm text-slate-500 mb-6 leading-relaxed">
                        To ensure your account's security, please verify your email address by clicking on the link we just sent to you.
                    </p>
                </div>

                <div v-if="verificationLinkSent" class="mb-6 font-bold text-[10px] text-emerald-600 bg-emerald-50 p-4 rounded-lg border border-emerald-200 uppercase tracking-widest text-center shadow-sm flex items-center justify-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    A verification link has been sent!
                </div>
                <div v-else-if="form.processing" class="mb-6 font-bold text-[10px] text-blue-600 bg-blue-50 p-4 rounded-lg border border-blue-200 uppercase tracking-widest text-center shadow-sm flex items-center justify-center gap-2">
                    <svg class="animate-spin h-4 w-4 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                    Sending Email...
                </div>

                <form @submit.prevent="submit">
                    <div class="mt-6 flex flex-col gap-4">
                        <PrimaryButton class="w-full justify-center py-3 rounded-lg shadow-md bg-blue-600 hover:bg-blue-700 text-[10px] uppercase tracking-widest font-black" :class="{ 'opacity-50 cursor-not-allowed': form.processing }" :disabled="form.processing">
                            Resend Verification Email
                        </PrimaryButton>

                        <div class="flex items-center justify-center mt-4 border-t border-slate-100 pt-6">
                            <Link
                                :href="route('logout')"
                                method="post"
                                as="button"
                                class="text-[10px] font-black uppercase tracking-widest text-slate-400 hover:text-red-500 transition-colors"
                            >
                                Cancel & Log Out
                            </Link>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>