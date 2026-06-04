<script setup>
import { ref, computed, onMounted, watch, onUnmounted } from 'vue';
import { Link, usePage, useForm, router } from '@inertiajs/vue3';
import AIChatWidget from '@/Components/AIChatWidget.vue';
import Modal from '@/Components/Modal.vue';
import DangerButton from '@/Components/DangerButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import InputLabel from '@/Components/InputLabel.vue';

const showingNotifications = ref(false); 

const page = usePage();
const user = page.props.auth.user;
const isDark = ref(false);

const notifications = computed(() => {
    const notifs = page.props.auth.notifications;
    if (!notifs) return [];
    return Array.isArray(notifs) ? notifs : Object.values(notifs);
});

const unreadCount = computed(() => page.props.auth.unread_count || 0);
const previousUnreadCount = ref(unreadCount.value);

const pendingMaterialsCount = computed(() => page.props.auth.pending_materials_count || 0);
const pendingGradingCount = computed(() => page.props.auth.pending_grading_count || 0);
const pendingTasksCount = computed(() => page.props.auth.pending_tasks_count || 0);

const notifTab = ref('unread');

const displayedNotifications = computed(() => {
    if (notifTab.value === 'unread') {
        return notifications.value.filter(n => n.read_at === null);
    }
    return notifications.value;
});

const deleteNotification = (id) => {
    router.delete(route('notifications.destroy', id), {
        preserveScroll: true,
        preserveState: true,
    });
};

const markAllAsRead = () => {
    router.post(route('notifications.markAllRead'), {}, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            notifTab.value = 'all'; 
        }
    });
};

// 🛡️ CRITICAL FIX: Safe Audio Player (Prevents JS Crashing if file is missing)
watch(unreadCount, (newCount) => {
    if (newCount > previousUnreadCount.value) {
        try {
            const audio = new Audio('/sounds/notification.mp3');
            audio.play().catch(() => {
            });
        } catch(e) { }
    }
    previousUnreadCount.value = newCount;
}, { immediate: true });

const toast = ref(null);

const showToast = (message, type) => {
    toast.value = { message, type };
    setTimeout(() => toast.value = null, 4000);
};

watch(() => page.props.flash, (newFlash) => {
    if (newFlash?.success) showToast(newFlash.success, 'success');
    else if (newFlash?.error) showToast(newFlash.error, 'error');
    else if (newFlash?.status) showToast(newFlash.status, 'status');
}, { deep: true, immediate: true });

onMounted(() => {
    const theme = localStorage.getItem('theme');
    if (theme === 'dark' || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        isDark.value = true;
        document.documentElement.classList.add('dark');
    }
});

const toggleTheme = () => {
    isDark.value = !isDark.value;
    if (isDark.value) {
        document.documentElement.classList.add('dark');
        localStorage.setItem('theme', 'dark');
    } else {
        document.documentElement.classList.remove('dark');
        localStorage.setItem('theme', 'light');
    }
};

const confirmingToggle = ref(false);
const toggleForm = useForm({ password: '' });

const confirmToggle = () => {
    if (page.props.requireApproval) {
        confirmingToggle.value = true;
    } else {
        submitToggle();
    }
};

const submitToggle = () => {
    toggleForm.post(route('admin.settings.material-approval'), {
        preserveScroll: true,
        onSuccess: () => {
            confirmingToggle.value = false;
            toggleForm.reset();
        },
    });
};

const icons = {
    dashboard: "M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 18v2.25A2.25 2.25 0 0118 22.5h-2.25a2.25 2.25 0 01-2.25-2.25V18z",
    courses: "M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0012 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75z",
    assignments: "M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z",
    users: "M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-3.833-6.249c-.183 0-.366.013-.547.038a5.68 5.68 0 011.174 3.59c0 .748-.145 1.46-.412 2.112zM4 19.128a9.38 9.38 0 012.625.372 9.337 9.337 0 014.121-.952 4.125 4.125 0 01-3.833-6.249 4.125 4.125 0 01-.547-.038 5.68 5.68 0 001.174 3.59c0 .748.145 1.46.412 2.112zM12 11.25a3.375 3.375 0 100-6.75 3.375 3.375 0 000 6.75zM9 1.5a2.25 2.25 0 110 4.5 2.25 2.25 0 010-4.5zm6 0a2.25 2.25 0 110 4.5 2.25 2.25 0 010-4.5z",
    calendar: "M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z",
    gradebook: "M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z",
}

const menus = computed(() => {
    if (user.role === 'teacher') {
        return [
            { name: 'Overview', route: 'teacher.dashboard', icon: icons.dashboard },
            { name: 'Classes', route: 'teacher.courses.index', icon: icons.courses },
            { name: 'Grades', route: 'teacher.gradebook.index', icon: icons.gradebook },
            { name: 'Tasks', route: 'teacher.assignments.index', icon: icons.assignments, badge: pendingGradingCount.value },
            { name: 'Calendar', route: 'calendar.index', icon: icons.calendar },
        ];
    } else if (user.role === 'student') {
        return [
            { name: 'Home', route: 'dashboard', icon: icons.dashboard },
            { name: 'Classes', route: 'student.courses', icon: icons.courses }, 
            { name: 'Tasks', route: 'student.assignments', icon: icons.assignments, badge: pendingTasksCount.value },
            { name: 'Calendar', route: 'calendar.index', icon: icons.calendar },
        ];
    } else if (user.role === 'admin') {
        return [
            { name: 'Dashboard', route: 'admin.dashboard', icon: icons.dashboard },
            { name: 'Users', route: 'admin.users.index', icon: icons.users },
            { name: 'Classes', route: 'admin.courses.index', icon: icons.courses },
            { name: 'Grades', route: 'admin.grades.index', icon: icons.gradebook }, 
            { name: 'Approvals', route: 'admin.materials', icon: icons.assignments, badge: pendingMaterialsCount.value },
            { name: 'Calendar', route: 'calendar.index', icon: icons.calendar },
        ];
    }
    return [];
});

const showScrollButton = ref(false);

const checkScroll = () => {
    showScrollButton.value = window.scrollY > 300;
};

const scrollToTop = () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

onMounted(() => {
    window.addEventListener('scroll', checkScroll);
});

onUnmounted(() => {
    window.removeEventListener('scroll', checkScroll);
});

defineExpose({
    confirmToggle
});
</script>

<template>
    <div class="min-h-screen bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-300 flex font-sans antialiased text-sm transition-colors duration-300 relative pb-[68px] md:pb-0">
        
        <div v-if="$page.props.auth.is_impersonating" class="absolute top-0 left-0 right-0 bg-amber-400 dark:bg-amber-500 text-amber-950 px-3 py-2 flex items-center justify-between z-[100] shadow-md">
            <div class="flex items-center gap-2 overflow-hidden">
                <svg class="w-4 h-4 shrink-0 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                <span class="text-[10px] sm:text-xs font-black uppercase tracking-wider truncate">Viewing as {{ $page.props.auth.user.name }}</span>
            </div>
            <Link :href="route('admin.restore-session')" method="post" as="button" class="shrink-0 bg-amber-950 hover:bg-black text-amber-400 px-3 py-1.5 rounded-md text-[9px] sm:text-[10px] font-black uppercase tracking-widest shadow-sm transition">
                Exit View
            </Link>
        </div>

        <div v-if="toast" class="fixed top-4 right-4 z-[70] transition-all duration-300 shadow-lg rounded-lg overflow-hidden border" 
             :class="{'bg-green-50 border-green-200 text-green-800 dark:bg-green-900/30 dark:border-green-800 dark:text-green-300': toast.type === 'success',
                      'bg-red-50 border-red-200 text-red-800 dark:bg-red-900/30 dark:border-red-800 dark:text-red-300': toast.type === 'error',
                      'bg-blue-50 border-blue-200 text-blue-800 dark:bg-blue-900/30 dark:border-blue-800 dark:text-blue-300': toast.type === 'status'}">
            <div class="px-4 py-3 flex items-center gap-3">
                <svg v-if="toast.type === 'success'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <svg v-if="toast.type === 'error'" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <p class="font-medium text-sm">{{ toast.message }}</p>
                <button @click="toast = null" class="ml-4 opacity-50 hover:opacity-100"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
            </div>
        </div>

        <div v-if="showingNotifications" @click="showingNotifications = false" class="fixed inset-0 z-[55] bg-transparent"></div>

        <aside class="hidden md:flex flex-col w-56 bg-white dark:bg-slate-900 border-r border-slate-200 dark:border-slate-800 h-screen fixed z-20 transition-colors duration-300 shadow-sm">
            <div class="h-16 flex items-center px-5 border-b border-slate-100 dark:border-slate-800/60 shrink-0">
                <span class="text-slate-900 dark:text-white font-black text-base tracking-tight flex items-center gap-2">
                    <div class="w-8 h-8 rounded-lg bg-blue-600 flex items-center justify-center text-white shadow-sm shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"></path></svg>
                    </div>
                    CDN LMS
                </span>
            </div>

            <nav class="flex-1 py-5 px-3 space-y-1 custom-scrollbar overflow-y-auto">
                <p class="px-3 text-[10px] font-black uppercase tracking-widest text-slate-400 dark:text-slate-500 mb-2">Main Menu</p>
                <Link v-for="item in menus" :key="item.route" 
                     :href="route(item.route)"
                     :class="{
                         'bg-blue-50 text-blue-700 dark:bg-blue-600/10 dark:text-blue-400 font-bold': route().current(item.route),
                         'text-slate-600 hover:bg-slate-50 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white font-medium': !route().current(item.route)
                     }"
                     class="flex items-center px-3 py-2.5 transition-all duration-200 group rounded-lg"
                >
                    <svg class="w-4 h-4 mr-3 transition-transform group-hover:scale-110" :class="route().current(item.route) ? 'text-blue-600 dark:text-blue-400' : 'text-slate-400 dark:text-slate-500'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon"></path></svg>
                    
                    <span class="text-sm truncate flex-1">{{ item.name }}</span>

                    <span v-if="item.badge > 0" class="ml-2 bg-red-500 text-white text-[10px] font-black px-2 py-0.5 rounded-full shadow-sm animate-pulse">
                        {{ item.badge }}
                    </span>
                </Link>
            </nav>

            <div class="p-3 border-t border-slate-100 dark:border-slate-800/60 bg-slate-50 dark:bg-slate-900 space-y-1 shrink-0">
                <button @click="toggleTheme" class="flex items-center justify-between w-full p-2.5 rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white transition group">
                    <span class="text-[11px] font-bold uppercase tracking-widest">{{ isDark ? 'Light Mode' : 'Dark Mode' }}</span>
                    <div class="w-7 h-7 flex items-center justify-center bg-white dark:bg-slate-800 rounded shadow-sm border border-slate-200 dark:border-slate-700 text-slate-400 group-hover:text-slate-600 dark:group-hover:text-white transition">
                        <svg v-if="isDark" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                        <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
                    </div>
                </button>
                <Link :href="route('profile.edit')" class="flex items-center gap-3 hover:bg-slate-100 dark:hover:bg-slate-800 p-2.5 rounded-lg transition group w-full">
                    <div class="w-8 h-8 rounded bg-blue-600 flex items-center justify-center text-white text-xs font-black shadow-sm shrink-0 overflow-hidden">
                        <img v-if="user.avatar" :src="user.avatar" referrerpolicy="no-referrer" class="w-full h-full object-cover" />
                        <span v-else>{{ user.name.charAt(0) }}</span>
                    </div>
                    <div class="flex-1 overflow-hidden">
                        <p class="text-sm font-bold text-slate-800 dark:text-white truncate leading-tight">{{ user.name }}</p>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 truncate mt-0.5">{{ user.role }}</p>
                    </div>
                </Link>
            </div>
        </aside>

        <div class="flex-1 md:ml-56 flex flex-col min-h-screen">
            
            <header class="md:hidden bg-white/90 dark:bg-slate-900/90 backdrop-blur-md border-b border-slate-200 dark:border-slate-800 h-14 flex items-center justify-between px-4 sticky top-0 z-30 transition-colors shrink-0 shadow-sm">
                <div class="flex items-center gap-2 text-slate-900 dark:text-white">
                    <div class="w-7 h-7 rounded bg-blue-600 flex items-center justify-center text-white shrink-0 shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"></path></svg>
                    </div>
                    <span class="font-black text-sm uppercase tracking-tight">CDN LMS</span>
                </div>
                
                <div class="flex items-center gap-2 sm:gap-3">
                    <button @click="toggleTheme" class="p-2 text-slate-500 dark:text-slate-400 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition focus:outline-none">
                        <svg v-if="isDark" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                        <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
                    </button>
                    <button @click="showingNotifications = !showingNotifications" class="relative p-2 text-slate-500 dark:text-slate-400 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition focus:outline-none">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                        <span v-if="unreadCount > 0" class="absolute top-1.5 right-1.5 w-2 h-2 bg-red-500 rounded-full border-2 border-white dark:border-slate-900 animate-pulse"></span>
                    </button>
                    <Link :href="route('profile.edit')" class="w-7 h-7 ml-1 rounded-full bg-blue-600 text-white flex items-center justify-center text-[10px] font-black shadow-sm shrink-0 transition-transform active:scale-95 overflow-hidden">
                        <img v-if="user.avatar" :src="user.avatar" referrerpolicy="no-referrer" class="w-full h-full object-cover" />
                        <span v-else>{{ user.name.charAt(0) }}</span>
                    </Link>
                </div>
            </header>

            <main class="flex-1 p-3 md:p-6" @click="showingNotifications = false">
                <slot />
            </main>
        </div>

        <transition 
            enter-active-class="transition ease-out duration-200 transform" 
            enter-from-class="opacity-0 scale-95 translate-y-2 md:translate-y-0" 
            enter-to-class="opacity-100 scale-100 translate-y-0" 
            leave-active-class="transition ease-in duration-150 transform" 
            leave-from-class="opacity-100 scale-100 translate-y-0" 
            leave-to-class="opacity-0 scale-95 translate-y-2 md:translate-y-0"
        >
            <div v-if="showingNotifications" class="fixed top-16 md:top-auto md:bottom-24 right-4 md:right-8 z-[60] w-[calc(100vw-2rem)] sm:w-80 bg-white/95 dark:bg-slate-900/95 backdrop-blur-xl rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 flex flex-col overflow-hidden max-h-[75vh] origin-top-right md:origin-bottom-right">
                
                <div class="p-3 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/50 shrink-0">
                    <div class="flex justify-between items-center mb-3 px-1">
                        <h3 class="font-black text-slate-900 dark:text-white uppercase tracking-widest text-[10px] flex items-center gap-2">
                            <svg class="w-4 h-4 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                            Notifications
                        </h3>
                        <button v-if="unreadCount > 0" @click="markAllAsRead" class="text-[9px] font-black uppercase tracking-widest text-blue-600 hover:text-blue-700 transition">
                            Mark all as read
                        </button>
                    </div>
                    <div class="flex gap-4 px-1">
                        <button @click="notifTab = 'unread'" class="pb-1.5 text-[9px] font-black uppercase tracking-widest transition-all relative whitespace-nowrap" :class="notifTab === 'unread' ? 'text-blue-600' : 'text-slate-400 hover:text-slate-600'">
                            Unread
                            <div v-if="notifTab === 'unread'" class="absolute bottom-0 left-0 w-full h-0.5 bg-blue-600 rounded-full"></div>
                        </button>
                        <button @click="notifTab = 'all'" class="pb-1.5 text-[9px] font-black uppercase tracking-widest transition-all relative whitespace-nowrap" :class="notifTab === 'all' ? 'text-blue-600' : 'text-slate-400 hover:text-slate-600'">
                            All Notifications
                            <div v-if="notifTab === 'all'" class="absolute bottom-0 left-0 w-full h-0.5 bg-blue-600 rounded-full"></div>
                        </button>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto p-2 space-y-1.5 custom-scrollbar">
                    <div v-if="displayedNotifications.length > 0">
                        
                        <div v-for="notif in displayedNotifications" :key="notif.id" class="relative group">
                            <Link :href="notif.data?.url || '#'" @click="showingNotifications = false" 
                                  class="block p-3 pr-8 bg-transparent hover:bg-slate-50 dark:hover:bg-slate-800/80 rounded-xl transition relative overflow-hidden">
                                <div v-if="!notif.read_at" class="absolute left-0 top-3 bottom-3 w-1 rounded-r bg-blue-500"></div>
                                
                                <h4 class="text-xs font-black uppercase tracking-wider mb-1 flex items-center gap-1.5" :class="notif.data?.color || 'text-blue-600 dark:text-blue-400'">
                                    <svg v-if="notif.data?.icon === 'star'" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path></svg>
                                    <svg v-else class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    {{ notif.data?.title || 'Notification' }}
                                </h4>
                                <p class="text-xs text-slate-600 dark:text-slate-400 leading-snug ml-1.5">{{ notif.data?.message }}</p>
                                <span class="text-[9px] font-bold uppercase tracking-widest text-slate-400 mt-2 block ml-1.5">{{ new Date(notif.created_at).toLocaleDateString() }}</span>
                            </Link>
                            <button @click.prevent="deleteNotification(notif.id)" title="Delete Notification" class="absolute top-3 right-2 text-slate-300 hover:text-red-500 opacity-0 group-hover:opacity-100 transition-opacity p-1.5 bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-full shadow-sm z-10">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>
                        
                    </div>
                    <div v-else class="text-center py-10">
                        <div class="w-12 h-12 bg-slate-50 dark:bg-slate-800 rounded-full flex items-center justify-center mx-auto mb-3">
                            <svg class="w-6 h-6 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                        </div>
                        <p class="text-[10px] font-black uppercase tracking-widest text-slate-400">
                            {{ notifTab === 'unread' ? 'No unread notifications!' : 'All caught up!' }}
                        </p>
                    </div>
                </div>
            </div>
        </transition>

        <div class="md:hidden fixed bottom-0 left-0 right-0 bg-white/95 dark:bg-slate-900/95 backdrop-blur-xl border-t border-slate-200 dark:border-slate-800 z-40 flex items-center justify-between px-1 pb-safe pt-0.5 shadow-[0_-8px_15px_-3px_rgba(0,0,0,0.05)]">
            <Link v-for="item in menus" :key="item.route" 
                 :href="route(item.route)"
                class="relative p-2 transition-all duration-200 flex-1 flex justify-center items-center flex-col gap-1 group"
                :class="route().current(item.route) ? 'text-blue-600 dark:text-blue-400' : 'text-slate-400 dark:text-slate-500 hover:text-slate-600 dark:hover:text-slate-300'"
            >
                <div v-if="route().current(item.route)" class="absolute top-0 left-1/2 -translate-x-1/2 w-6 sm:w-8 h-1 bg-blue-600 dark:bg-blue-400 rounded-b-full"></div>
                
                <div class="relative flex items-center justify-center">
                    <svg class="w-5 h-5 sm:w-6 sm:h-6 transition-transform group-hover:scale-110 mt-1" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" :d="item.icon"></path></svg>
                    
                    <span v-if="item.badge > 0" class="absolute top-0 -right-1.5 flex h-2.5 w-2.5">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500 border border-white dark:border-slate-900"></span>
                    </span>
                </div>

                <span class="text-[9px] font-black tracking-widest uppercase mt-0.5 truncate w-full text-center">{{ item.name }}</span>
            </Link>
        </div>

        <div class="hidden md:block fixed z-40 bottom-6 right-20 transition-all duration-300">
            <button @click="showingNotifications = !showingNotifications" 
                    class="relative p-3.5 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-full shadow-lg hover:shadow-xl hover:bg-slate-50 dark:hover:bg-slate-700 transition hover:-translate-y-1 text-slate-600 dark:text-slate-300 focus:outline-none">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                <span v-if="unreadCount > 0" class="absolute top-0 right-0 w-3.5 h-3.5 bg-red-500 rounded-full border-2 border-white dark:border-slate-800 animate-pulse"></span>
            </button>
        </div>

        <AIChatWidget />

        <Modal :show="confirmingToggle" @close="confirmingToggle = false" maxWidth="sm">
            <div class="p-6 bg-white dark:bg-slate-900 rounded-xl shadow-xl border border-slate-200 dark:border-slate-800">
                <h2 class="text-sm font-black uppercase tracking-tight text-slate-900 dark:text-white flex items-center gap-2 mb-2">
                    <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                    Security Confirmation
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">Enter your password to disable the material approval system.</p>
                <div class="space-y-4">
                    <div>
                        <InputLabel for="password" value="Password" class="sr-only" />
                        <TextInput 
                            v-model="toggleForm.password" 
                            type="password" 
                            class="block w-full text-sm py-2.5" 
                            placeholder="Admin Password" 
                            @keyup.enter="submitToggle" 
                        />
                    </div>
                    <div class="flex justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                        <button @click="confirmingToggle = false" type="button" class="px-4 py-2 text-[10px] font-black uppercase tracking-widest text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 transition">Cancel</button>
                        <button @click="submitToggle" :disabled="toggleForm.processing" class="px-5 py-2 bg-red-600 hover:bg-red-500 text-white rounded text-[10px] font-black uppercase tracking-widest shadow-sm transition">Deactivate</button>
                    </div>
                </div>
            </div>
        </Modal>

        <transition
            enter-active-class="transition ease-out duration-300 transform" enter-from-class="opacity-0 translate-y-4 scale-95" enter-to-class="opacity-100 translate-y-0 scale-100"
            leave-active-class="transition ease-in duration-200 transform" leave-from-class="opacity-100 translate-y-0 scale-100" leave-to-class="opacity-0 translate-y-4 scale-95"
        >
            <button v-show="showScrollButton" @click="scrollToTop" 
                 class="fixed z-50 bottom-20 md:bottom-6 right-4 md:right-8 p-3 bg-slate-800 dark:bg-slate-700 text-white rounded-full shadow-lg hover:bg-slate-700 dark:hover:bg-slate-600 transition-all hover:-translate-y-1 focus:outline-none focus:ring-4 focus:ring-slate-400/50">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 10l7-7m0 0l7 7m-7-7v18"></path></svg>
            </button>
        </transition>
    </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(148, 163, 184, 0.2);
    border-radius: 10px;
}
.custom-scrollbar:hover::-webkit-scrollbar-thumb {
    background: rgba(148, 163, 184, 0.4);
}
.pb-safe {
    padding-bottom: env(safe-area-inset-bottom);
}
</style>