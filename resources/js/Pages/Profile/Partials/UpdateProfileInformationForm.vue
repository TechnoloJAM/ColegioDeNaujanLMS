<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: { type: Boolean },
    status: { type: String },
});

const user = usePage().props.auth.user;

const form = useForm({
    name: user.name,
    email: user.email,
});

const submit = () => {
    form.patch(route('profile.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <section>
        <header>
            <h2 class="text-sm font-bold text-slate-900 dark:text-white">
                Profile Information
            </h2>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                Update your account's profile information.
            </p>
        </header>

        <form @submit.prevent="submit" class="mt-6 space-y-5">
            
            <div class="flex items-center gap-4 p-4 bg-slate-50 dark:bg-slate-900/50 border border-slate-200 dark:border-slate-800 rounded-xl">
                <div class="w-16 h-16 shrink-0">
                    <img v-if="user.avatar" :src="user.avatar" referrerpolicy="no-referrer" class="w-16 h-16 rounded-full object-cover border-4 border-white dark:border-slate-800 shadow-sm bg-white" />
                    <div v-else class="w-16 h-16 rounded-full border-4 border-white dark:border-slate-800 shadow-sm bg-blue-600 flex items-center justify-center text-white text-xl font-black">
                        {{ user.name.charAt(0) }}
                    </div>
                </div>
                <div>
                    <p class="text-xs font-bold text-slate-800 dark:text-slate-200">Profile Photo</p>
                    <p class="text-[10px] font-black uppercase tracking-widest text-emerald-600 mt-0.5">Synced securely from Google</p>
                </div>
            </div>

            <div>
                <InputLabel for="name" value="Name" class="text-xs uppercase text-slate-500 font-bold" />
                <TextInput
                    id="name"
                    type="text"
                    class="mt-1 block w-full text-sm p-2 rounded bg-slate-50 dark:bg-slate-900 border-slate-300 dark:border-slate-700"
                    v-model="form.name"
                    required
                    autofocus
                    autocomplete="name"
                />
                <InputError class="mt-1" :message="form.errors.name" />
            </div>

            <div>
                <InputLabel for="email" value="Email" class="text-xs uppercase text-slate-500 font-bold" />
                <TextInput
                    id="email"
                    type="email"
                    class="mt-1 block w-full text-sm p-2 rounded bg-slate-100 dark:bg-slate-900/50 text-slate-500 cursor-not-allowed border-slate-200 dark:border-slate-800"
                    v-model="form.email"
                    required
                    autocomplete="username"
                    disabled
                />
                <p class="mt-1 text-[10px] text-slate-400">
                    Email address cannot be changed.
                </p>
                <InputError class="mt-1" :message="form.errors.email" />
            </div>

            <div>
                <InputLabel for="role" value="Role" class="text-xs uppercase text-slate-500 font-bold" />
                <TextInput
                    id="role"
                    type="text"
                    class="mt-1 block w-full text-sm p-2 rounded bg-slate-100 dark:bg-slate-900/50 text-slate-500 cursor-not-allowed capitalize border-slate-200 dark:border-slate-800"
                    :model-value="user.role"
                    disabled
                />
            </div>

            <div v-if="mustVerifyEmail && user.email_verified_at === null">
                <p class="mt-2 text-sm text-slate-800 dark:text-slate-200">
                    Your email address is unverified.
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="underline text-sm text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-100 rounded-md"
                    >
                        Click here to re-send the verification email.
                    </Link>
                </p>
            </div>

            <div class="flex items-center gap-4 pt-2">
                <PrimaryButton :disabled="form.processing" class="text-xs px-4 py-2 bg-blue-600 border-transparent w-full sm:w-auto justify-center shadow-md">
                    Save Changes
                </PrimaryButton>
                <Transition enter-active-class="transition ease-in-out" enter-from-class="opacity-0" leave-active-class="transition ease-in-out" leave-to-class="opacity-0">
                    <p v-if="form.recentlySuccessful" class="text-xs font-bold text-emerald-500 uppercase tracking-widest">Saved!</p>
                </Transition>
            </div>
        </form>
    </section>
</template>