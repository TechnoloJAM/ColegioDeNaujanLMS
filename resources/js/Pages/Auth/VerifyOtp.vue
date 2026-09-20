<script setup>
import { Head, useForm, router, Link } from '@inertiajs/vue3';
import InputError from '@/Components/InputError.vue';
import { ref, onUnmounted } from 'vue';

const props = defineProps({ 
    email: String 
});

const form = useForm({
    email: props.email,
    token: '',
});

// UI State & Timer Logic
const countdown = ref(0);
const otpSent = ref(false);
const isSending = ref(false);
let timer = null;

const startTimer = () => {
    countdown.value = 90;
    otpSent.value = true;
    if (timer) clearInterval(timer);
    
    timer = setInterval(() => {
        if (countdown.value > 0) {
            countdown.value--;
        } else {
            clearInterval(timer);
        }
    }, 1000);
};

onUnmounted(() => {
    if (timer) clearInterval(timer);
});

const submit = () => {
    form.post(route('verification.verify_otp'), {
        onFinish: () => form.reset('token'),
    });
};

const requestOtp = () => {
    if (countdown.value > 0 || isSending.value) return;

    isSending.value = true;
    router.post(route('otp.resend'), { 
        email: props.email, 
        purpose: 'registration' 
    }, {
        onSuccess: () => {
            isSending.value = false;
            startTimer();
        },
        onError: () => {
            isSending.value = false;
        }
    });
};
</script>

<template>
    <Head title="Verify Account" />

    <div class="min-h-screen flex flex-col justify-center items-center bg-slate-50 dark:bg-slate-950 p-4 transition-colors duration-300">
        <!-- Main Card -->
        <div class="w-full max-w-md bg-white dark:bg-slate-900 rounded-2xl shadow-xl border border-slate-200 dark:border-slate-800 overflow-hidden">
            
            <!-- Header Section -->
            <div class="bg-blue-600 p-8 text-center flex flex-col items-center">
                <!-- Custom CDN Logo -->
                <div class="bg-white p-3 rounded-full shadow-md mb-4 inline-block">
                    <img src="/images/Logo2.png" alt="CDN Logo" class="h-14 w-auto object-contain" />
                </div>
                <h2 class="text-2xl font-black text-white tracking-tight">Verify Your Account</h2>
                <p class="text-blue-100 text-sm mt-2 font-medium">
                    <template v-if="otpSent">
                        We've sent a 6-digit code to <br/>
                        <span class="font-bold text-white">{{ email }}</span>
                    </template>
                    <template v-else>
                        Please request your secure verification code for <br/>
                        <span class="font-bold text-white">{{ email }}</span>
                    </template>
                </p>
            </div>

            <!-- Form Section -->
            <div class="p-8">
                
                <!-- STATE 1: WAITING FOR USER TO CLICK SEND -->
                <div v-if="!otpSent" class="text-center space-y-6">
                    <div class="p-4 bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-800 rounded-xl">
                        <p class="text-sm font-bold text-blue-800 dark:text-blue-300">
                            Click the button below to send your one-time password (OTP) to your email address.
                        </p>
                    </div>
                    
                    <button 
                        @click="requestOtp" 
                        :disabled="isSending"
                        class="w-full bg-blue-600 hover:bg-blue-700 text-white font-black text-sm uppercase tracking-widest py-4 rounded-xl shadow-md transition-all active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed flex justify-center items-center gap-2"
                    >
                        <svg v-if="isSending" class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span v-if="isSending">Sending Code...</span>
                        <span v-else>Send Verification Code</span>
                    </button>
                </div>

                <!-- STATE 2: OTP SENT, SHOW INPUT FORM -->
                <div v-else>
                    <!-- Spam Folder Reminder -->
                    <div class="mb-6 flex items-start gap-2.5 p-3.5 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800/50 rounded-xl shadow-sm">
                        <svg class="w-5 h-5 text-amber-600 dark:text-amber-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <p class="text-xs font-bold text-amber-800 dark:text-amber-400 leading-snug">
                            Can't find the email? Please check your <span class="font-black uppercase tracking-wider text-amber-900 dark:text-amber-300">Spam</span> or <span class="font-black uppercase tracking-wider text-amber-900 dark:text-amber-300">Junk</span> folder.
                        </p>
                    </div>

                    <form @submit.prevent="submit" class="space-y-6">
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <label for="token" class="block text-xs font-black uppercase tracking-widest text-slate-500 dark:text-slate-400">
                                    Enter Security Code
                                </label>
                                
                                <!-- 90-Second Timer Badge -->
                                <span 
                                    class="text-[10px] font-black uppercase tracking-widest px-2 py-0.5 rounded-md"
                                    :class="countdown > 0 ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300' : 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400'"
                                >
                                    <template v-if="countdown > 0">Expires in: {{ countdown }}s</template>
                                    <template v-else>Code Expired</template>
                                </span>
                            </div>
                            
                            <input
                                id="token"
                                v-model="form.token"
                                type="text"
                                inputmode="numeric"
                                maxlength="6"
                                class="w-full text-center text-3xl font-black tracking-[0.5em] text-blue-900 dark:text-blue-400 bg-slate-50 dark:bg-slate-950 border-2 border-slate-200 dark:border-slate-800 focus:border-blue-600 dark:focus:border-blue-500 focus:ring-0 rounded-xl py-4 transition-colors shadow-inner"
                                placeholder="••••••"
                                required
                                autofocus
                                autocomplete="one-time-code"
                            />
                            <InputError class="mt-2 text-center text-xs font-bold uppercase tracking-wide" :message="form.errors.token" />
                        </div>

                        <button 
                            type="submit" 
                            :disabled="form.processing || form.token.length < 6"
                            class="w-full bg-blue-600 hover:bg-blue-700 text-white font-black text-sm uppercase tracking-widest py-4 rounded-xl shadow-md transition-all active:scale-[0.98] disabled:opacity-50 disabled:cursor-not-allowed flex justify-center items-center gap-2"
                        >
                            <svg v-if="form.processing" class="animate-spin h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span v-if="form.processing">Verifying...</span>
                            <span v-else>Confirm & Login</span>
                        </button>
                    </form>

                    <!-- Resend Action & Countdown Indicator -->
                    <div class="mt-8 text-center border-t border-slate-100 dark:border-slate-800 pt-6">
                        <p class="text-xs text-slate-500 dark:text-slate-400 font-medium mb-2">
                            Didn't receive the code?
                        </p>
                        
                        <button 
                            @click="requestOtp" 
                            :disabled="countdown > 0 || isSending"
                            class="text-xs font-black uppercase tracking-widest transition-colors flex justify-center items-center gap-1.5 mx-auto"
                            :class="(countdown > 0 || isSending) ? 'text-slate-400 dark:text-slate-600 cursor-not-allowed' : 'text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 cursor-pointer'"
                        >
                            <svg v-if="isSending" class="animate-spin h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                            <span v-if="isSending">Sending...</span>
                            <span v-else-if="countdown > 0">Resend Code (Wait {{ countdown }}s)</span>
                            <span v-else>Resend Code Now</span>
                        </button>
                        
                        <div v-if="$page.props.flash?.success" class="mt-4 p-3 bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-800 rounded-lg text-emerald-600 dark:text-emerald-400 text-[10px] font-bold uppercase tracking-widest">
                            {{ $page.props.flash.success }}
                        </div>
                    </div>
                </div>

                <!-- Cancel Action -->
                <div class="mt-6 pt-4 border-t border-slate-100 dark:border-slate-800 text-center">
                    <Link 
                        :href="route('logout')" 
                        method="post" 
                        as="button" 
                        class="text-[10px] font-bold uppercase tracking-widest text-slate-400 hover:text-red-500 transition-colors"
                    >
                        Cancel & Sign Out
                    </Link>
                </div>

            </div>
        </div>
    </div>
</template>

<style scoped>
input[type="text"] {
    font-variant-numeric: tabular-nums;
}
</style>