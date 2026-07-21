<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { useForm, Head } from '@inertiajs/vue3';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';

const props = defineProps({
    email: {
        type: String,
        required: true,
    },
});

const form = useForm({
    token: '',
    email: props.email,
    password: '',
    password_confirmation: '',
});

const resendForm = useForm({
    email: props.email,
    purpose: 'password_reset'
});

const timeLeft = ref(60);
let timerInterval = null;

const formattedTime = computed(() => {
    const minutes = Math.floor(timeLeft.value / 60);
    const seconds = timeLeft.value % 60;
    return `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
});

const startTimer = () => {
    timeLeft.value = 60;
    timerInterval = setInterval(() => {
        if (timeLeft.value > 0) {
            timeLeft.value--;
        } else {
            clearInterval(timerInterval);
        }
    }, 1000);
};

onMounted(() => startTimer());
onUnmounted(() => { if (timerInterval) clearInterval(timerInterval); });

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};

const resend = () => {
    if (timeLeft.value === 0) {
        resendForm.post(route('otp.resend'), {
            onSuccess: () => startTimer(),
        });
    }
};
</script>

<template>
    <GuestLayout>
        <Head title="Reset Password" />

        <div class="mb-6 text-center">
            <h2 class="text-2xl font-bold text-blue-900">Reset Password</h2>
            <p class="text-sm text-gray-600 mt-2">Enter the 6-digit code sent to your email along with your new password.</p>
        </div>
        
        <div v-if="$page.props.flash.success" class="mb-4 font-medium text-sm text-green-600 bg-green-50 p-3 rounded-lg border border-green-200">
            {{ $page.props.flash.success }}
        </div>

        <form @submit.prevent="submit">
            <!-- OTP Input -->
            <div class="mb-6">
                <div class="flex justify-between items-center mb-1">
                    <InputLabel for="token" value="6-Digit Reset Code" />
                    <span v-if="timeLeft > 0" class="text-xs text-blue-600 font-bold">{{ formattedTime }}</span>
                    <span v-else class="text-xs text-red-600 font-bold">Expired</span>
                </div>
                <input
                    id="token"
                    type="text"
                    class="mt-1 block w-full text-center text-2xl tracking-[0.4em] font-bold text-blue-800 border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm"
                    v-model="form.token"
                    maxlength="6"
                    required
                    autofocus
                />
                <InputError class="mt-2" :message="form.errors.token" />
            </div>

            <div class="mt-4">
                <InputLabel for="password" value="New Password" />
                <TextInput
                    id="password"
                    type="password"
                    class="mt-1 block w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                    v-model="form.password"
                    required
                    autocomplete="new-password"
                />
                <InputError class="mt-2" :message="form.errors.password" />
            </div>

            <div class="mt-4">
                <InputLabel for="password_confirmation" value="Confirm Password" />
                <TextInput
                    id="password_confirmation"
                    type="password"
                    class="mt-1 block w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500"
                    v-model="form.password_confirmation"
                    required
                    autocomplete="new-password"
                />
                <InputError class="mt-2" :message="form.errors.password_confirmation" />
            </div>

            <div class="flex items-center justify-between mt-8">
                <button 
                    type="button" 
                    @click="resend"
                    class="text-sm font-medium transition-colors"
                    :class="timeLeft > 0 ? 'text-gray-400 cursor-not-allowed' : 'text-blue-600 hover:text-blue-800 underline'"
                    :disabled="timeLeft > 0 || resendForm.processing"
                >
                    Resend Code
                </button>

                <button 
                    type="submit" 
                    class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition ease-in-out duration-150"
                    :class="{ 'opacity-25': form.processing }" 
                    :disabled="form.processing || timeLeft === 0"
                >
                    Reset Password
                </button>
            </div>
        </form>
    </GuestLayout>
</template>