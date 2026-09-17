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
const showingMoreMenu = ref(false);

const page = usePage();
const user = page.props.auth.user;
const isDark = ref(false);

const isImpersonating = computed(() => !!page.props.auth.is_impersonating);

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

watch(unreadCount, (newCount) => {
    if (newCount > previousUnreadCount.value) {
        try {
            const audio = new Audio('/sounds/notification.mp3');
            audio.play().catch(() => {});
        } catch (e) {}
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
    more: "M4 6h16M4 12h16M4 18h16",
};

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
            { name: 'Home', route: 'student.dashboard', icon: icons.dashboard },
            { name: 'Classes', route: 'student.courses', icon: icons.courses },
            { name: 'Tasks', route: 'student.assignments', icon: icons.assignments, badge: pendingTasksCount.value },
            { name: 'Grades', route: 'student.grades', icon: icons.gradebook },
            { name: 'Calendar', route: 'calendar.index', icon: icons.calendar },
        ];
    } else if (user.role === 'admin') {
        // MOBILE FIX: Shorter menu labels for responsive fit
        return [
            { name: 'Home', route: 'admin.dashboard', icon: icons.dashboard },
            { name: 'Users', route: 'admin.users.index', icon: icons.users },
            { name: 'Classes', route: 'admin.courses.index', icon: icons.courses },
            { name: 'Grades', route: 'admin.grades.index', icon: icons.gradebook },
            { name: 'Review', route: 'admin.materials', icon: icons.assignments, badge: pendingMaterialsCount.value },
            { name: 'Dates', route: 'calendar.index', icon: icons.calendar },
        ];
    } else if (user.role === 'dean') {
        return [
            { name: 'Overview', route: 'dean.dashboard', icon: icons.dashboard },
            { name: 'Faculty', route: 'dean.faculty', icon: icons.users },
            { name: 'Course Audit', route: 'dean.audit', icon: icons.courses },
        ];
    }
    return [];
});

const MAX_TABS = 5;

const primaryMenus = computed(() =>
    menus.value.length > MAX_TABS ? menus.value.slice(0, MAX_TABS - 1) : menus.value
);

const overflowMenus = computed(() =>
    menus.value.length > MAX_TABS ? menus.value.slice(MAX_TABS - 1) : []
);

const overflowBadge = computed(() =>
    overflowMenus.value.reduce((sum, item) => sum + (item.badge || 0), 0)
);

const overflowIsActive = computed(() =>
    overflowMenus.value.some(item => route().current(item.route))
);

const tabColumns = computed(() =>
    primaryMenus.value.length + (overflowMenus.value.length ? 1 : 0)
);

const showScrollButton = ref(false);

const checkScroll = () => {
    showScrollButton.value = window.scrollY > 300;
};

const scrollToTop = () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
};

const closeOverlays = () => {
    showingNotifications.value = false;
    showingMoreMenu.value = false;
};

const onKeydown = (e) => {
    if (e.key === 'Escape') closeOverlays();
};

onMounted(() => {
    const theme = localStorage.getItem('theme');
    if (theme === 'dark' || (!theme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
        isDark.value = true;
        document.documentElement.classList.add('dark');
    }

    window.addEventListener('scroll', checkScroll, { passive: true });
    window.addEventListener('keydown', onKeydown);
});

onUnmounted(() => {
    window.removeEventListener('scroll', checkScroll);
    window.removeEventListener('keydown', onKeydown);
});

watch(() => page.url, closeOverlays);

defineExpose({
    confirmToggle
});
</script>

<template>
    <!-- MOBILE FIX: Added overflow-x-hidden and w-full to root div to prevent page-breaking scrolls -->
    <div class="app-shell min-h-screen bg-slate-50 dark:bg-slate-950 text-slate-900 dark:text-slate-300 flex flex-col font-sans antialiased text-sm transition-colors duration-300 relative overflow-x-hidden w-full">

        <div v-if="isImpersonating" class="w-full bg-amber-400 dark:bg-amber-500 text-amber-950 px-3 py-2 flex items-center justify-between gap-2 z-[100] shadow-md shrink-0">
            <div class="flex items-center gap-2 min-w-0">
                <svg class="w-4 h-4 shrink-0 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                <span class="text-[11px] font-black uppercase tracking-wide truncate">Viewing as {{ $page.props.auth.user.name }}</span>
            </div>
            <Link :href="route('admin.restore-session')" method="post" as="button" class="shrink-0 bg-amber-950 hover:bg-black text-amber-400 px-3 min-h-[36px] rounded-md text-[11px] font-black uppercase tracking-wide shadow-sm transition">
                Exit view
            </Link>
        </div>

        <div v-if="toast" class="fixed top-3 inset-x-3 sm:inset-x-auto sm:right-4 sm:top-4 z-[70] sm:max-w-sm transition-all duration-300 shadow-lg rounded-lg overflow-hidden border"
             :class="{'bg-green-50 border-green-200 text-green-800 dark:bg-green-900/30 dark:border-green-800 dark:text-green-300': toast.type === 'success',
                      'bg-red-50 border-red-200 text-red-800 dark:bg-red-900/30 dark:border-red-800 dark:text-red-300': toast.type === 'error',
                      'bg-blue-50 border-blue-200 text-blue-800 dark:bg-blue-900/30 dark:border-blue-800 dark:text-blue-300': toast.type === 'status'}">
            <div class="px-4 py-3 flex items-center gap-3">
                <svg v-if="toast.type === 'success'" class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <svg v-if="toast.type === 'error'" class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <p class="font-medium text-sm flex-1 min-w-0">{{ toast.message }}</p>
                <button @click="toast = null" aria-label="Dismiss" class="shrink-0 -mr-1 p-2 opacity-50 hover:opacity-100"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg></button>
            </div>
        </div>

        <div v-if="showingNotifications || showingMoreMenu" @click="closeOverlays" class="fixed inset-0 z-[55] bg-slate-900/20 md:bg-transparent"></div>

        <div class="flex flex-1 min-h-0">
            <aside class="sidebar hidden md:flex flex-col w-56 bg-white dark:bg-slate-900 border-r border-slate-200 dark:border-slate-800 fixed z-20 transition-colors duration-300 shadow-sm"
                   :class="isImpersonating ? 'top-[52px] h-[calc(100vh-52px)]' : 'top-0 h-screen'">
                
                <!-- UPDATE: Desktop Sidebar Logo -->
                <div class="h-16 flex items-center px-5 border-b border-slate-100 dark:border-slate-800/60 shrink-0">
                    <span class="text-slate-900 dark:text-white font-black text-base tracking-tight flex items-center gap-2">
                        <img src="/images/Logo2.png" alt="Colegio de Naujan Logo" class="w-8 h-8 object-contain drop-shadow-sm shrink-0" />
                        CDN LMS
                    </span>
                </div>

                <nav class="flex-1 py-5 px-3 space-y-1 custom-scrollbar overflow-y-auto">
                    <p class="px-3 text-[11px] font-black uppercase tracking-wide text-slate-400 dark:text-slate-500 mb-2">Main menu</p>
                    <Link v-for="item in menus" :key="item.route"
                         :href="route(item.route)"
                         :class="{
                             'bg-blue-50 text-blue-700 dark:bg-blue-600/10 dark:text-blue-400 font-bold': route().current(item.route),
                             'text-slate-600 hover:bg-slate-50 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white font-medium': !route().current(item.route)
                         }"
                         class="flex items-center px-3 py-2.5 transition-all duration-200 group rounded-lg"
                    >
                        <svg class="w-4 h-4 mr-3 transition-transform group-hover:scale-110 shrink-0" :class="route().current(item.route) ? 'text-blue-600 dark:text-blue-400' : 'text-slate-400 dark:text-slate-500'" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" :d="item.icon"></path></svg>
                        <span class="text-sm truncate flex-1">{{ item.name }}</span>
                        <span v-if="item.badge > 0" class="ml-2 bg-red-500 text-white text-[11px] font-black px-2 py-0.5 rounded-full shadow-sm">
                            {{ item.badge }}
                        </span>
                    </Link>
                </nav>

                <div class="p-3 border-t border-slate-100 dark:border-slate-800/60 bg-slate-50 dark:bg-slate-900 space-y-1 shrink-0">
                    <button @click="toggleTheme" class="flex items-center justify-between w-full p-2.5 rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-900 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-white transition group">
                        <span class="text-[11px] font-bold uppercase tracking-wide">{{ isDark ? 'Light mode' : 'Dark mode' }}</span>
                        <span class="w-7 h-7 flex items-center justify-center bg-white dark:bg-slate-800 rounded shadow-sm border border-slate-200 dark:border-slate-700 text-slate-400 group-hover:text-slate-600 dark:group-hover:text-white transition">
                            <svg v-if="isDark" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                            <svg v-else class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
                        </span>
                    </button>
                    <Link :href="route('profile.edit')" class="flex items-center gap-3 hover:bg-slate-100 dark:hover:bg-slate-800 p-2.5 rounded-lg transition group w-full">
                        <span class="w-8 h-8 rounded bg-blue-600 flex items-center justify-center text-white text-xs font-black shadow-sm shrink-0 overflow-hidden">
                            <img v-if="user.avatar" :src="user.avatar" referrerpolicy="no-referrer" class="w-full h-full object-cover" />
                            <span v-else>{{ user.name.charAt(0) }}</span>
                        </span>
                        <span class="flex-1 overflow-hidden">
                            <span class="block text-sm font-bold text-slate-800 dark:text-white truncate leading-tight">{{ user.name }}</span>
                            <span class="block text-[11px] font-bold uppercase tracking-wide text-slate-400 truncate mt-0.5">{{ user.role }}</span>
                        </span>
                    </Link>
                </div>
            </aside>

            <div class="flex-1 md:ml-56 flex flex-col min-w-0">
                <header class="md:hidden bg-white/90 dark:bg-slate-900/90 backdrop-blur-md border-b border-slate-200 dark:border-slate-800 min-h-[56px] flex items-center justify-between px-3 sticky top-0 z-30 transition-colors shrink-0 shadow-sm">
                    
                    <!-- UPDATE: Mobile Header Logo -->
                    <div class="flex items-center gap-2 text-slate-900 dark:text-white min-w-0">
                        <img src="/images/Logo2.png" alt="Colegio de Naujan Logo" class="w-7 h-7 object-contain drop-shadow-sm shrink-0" />
                        <span class="font-black text-sm uppercase tracking-tight truncate">CDN LMS</span>
                    </div>

                    <div class="flex items-center shrink-0">
                        <button @click="toggleTheme" :aria-label="isDark ? 'Switch to light mode' : 'Switch to dark mode'" class="tap-target text-slate-500 dark:text-slate-400 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500">
                            <svg v-if="isDark" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                            <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path></svg>
                        </button>

                        <button v-if="user.role !== 'dean'" @click.stop="showingNotifications = !showingNotifications" aria-label="Notifications" class="tap-target relative text-slate-500 dark:text-slate-400 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-800 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                            <span v-if="unreadCount > 0" class="absolute top-2 right-2 w-2.5 h-2.5 bg-red-500 rounded-full border-2 border-white dark:border-slate-900"></span>
                        </button>

                        <Link :href="route('profile.edit')" aria-label="Profile" class="tap-target">
                            <span class="w-8 h-8 rounded-full bg-blue-600 text-white flex items-center justify-center text-[11px] font-black shadow-sm shrink-0 transition-transform active:scale-95 overflow-hidden">
                                <img v-if="user.avatar" :src="user.avatar" referrerpolicy="no-referrer" class="w-full h-full object-cover" />
                                <span v-else>{{ user.name.charAt(0) }}</span>
                            </span>
                        </Link>
                    </div>
                </header>

                <main class="main-area flex-1 w-full min-w-0 p-3 md:p-6">
                    <slot />
                </main>
            </div>
        </div>

        <transition
            enter-active-class="transition ease-out duration-200 transform"
            enter-from-class="opacity-0 scale-95 translate-y-2 md:translate-y-0"
            enter-to-class="opacity-100 scale-100 translate-y-0"
            leave-active-class="transition ease-in duration-150 transform"
            leave-from-class="opacity-100 scale-100 translate-y-0"
            leave-to-class="opacity-0 scale-95 translate-y-2 md:translate-y-0"
        >
            <div v-if="showingNotifications"
                 class="notif-panel fixed z-[60] inset-x-3 top-[68px] sm:inset-x-auto sm:right-4 sm:w-80 md:top-auto md:bottom-24 md:right-8 bg-white/98 dark:bg-slate-900/98 backdrop-blur-xl rounded-2xl shadow-2xl border border-slate-200 dark:border-slate-700 flex flex-col overflow-hidden origin-top-right md:origin-bottom-right">

                <div class="p-3 border-b border-slate-100 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/50 shrink-0">
                    <div class="flex justify-between items-center gap-2 mb-2 px-1">
                        <h3 class="font-black text-slate-900 dark:text-white uppercase tracking-wide text-[11px] flex items-center gap-2 min-w-0">
                            <svg class="w-4 h-4 text-blue-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                            Notifications
                        </h3>
                        <button v-if="unreadCount > 0" @click="markAllAsRead" class="shrink-0 min-h-[36px] px-2 -mr-2 text-[11px] font-black uppercase tracking-wide text-blue-600 hover:text-blue-700 transition">
                            Mark all read
                        </button>
                    </div>
                    <div class="flex gap-1 px-1">
                        <button @click="notifTab = 'unread'" class="min-h-[40px] px-2 text-[11px] font-black uppercase tracking-wide transition-all relative whitespace-nowrap" :class="notifTab === 'unread' ? 'text-blue-600' : 'text-slate-400 hover:text-slate-600'">
                            Unread
                            <span v-if="notifTab === 'unread'" class="absolute bottom-0 left-0 w-full h-0.5 bg-blue-600 rounded-full"></span>
                        </button>
                        <button @click="notifTab = 'all'" class="min-h-[40px] px-2 text-[11px] font-black uppercase tracking-wide transition-all relative whitespace-nowrap" :class="notifTab === 'all' ? 'text-blue-600' : 'text-slate-400 hover:text-slate-600'">
                            All
                            <span v-if="notifTab === 'all'" class="absolute bottom-0 left-0 w-full h-0.5 bg-blue-600 rounded-full"></span>
                        </button>
                    </div>
                </div>

                <div class="flex-1 overflow-y-auto overscroll-contain p-2 space-y-1.5 custom-scrollbar">
                    <div v-if="displayedNotifications.length > 0">
                        <div v-for="notif in displayedNotifications" :key="notif.id" class="relative group">
                            <Link :href="notif.data?.url || '#'" @click="showingNotifications = false"
                                  class="block p-3 pr-12 bg-transparent hover:bg-slate-50 dark:hover:bg-slate-800/80 rounded-xl transition relative overflow-hidden">
                                <span v-if="!notif.read_at" class="absolute left-0 top-3 bottom-3 w-1 rounded-r bg-blue-500"></span>

                                <span class="block text-[11px] font-black uppercase tracking-wide mb-1 flex items-center gap-1.5" :class="notif.data?.color || 'text-blue-600 dark:text-blue-400'">
                                    <svg v-if="notif.data?.icon === 'star'" class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path></svg>
                                    <svg v-else class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    <span class="truncate">{{ notif.data?.title || 'Notification' }}</span>
                                </span>
                                <span class="block text-xs text-slate-600 dark:text-slate-400 leading-snug ml-1.5">{{ notif.data?.message }}</span>
                                <span class="block text-[11px] font-bold uppercase tracking-wide text-slate-400 mt-2 ml-1.5">{{ new Date(notif.created_at).toLocaleDateString() }}</span>
                            </Link>
                            <button @click.prevent="deleteNotification(notif.id)" aria-label="Delete notification"
                                    class="absolute top-2 right-1 w-11 h-11 flex items-center justify-center text-slate-400 hover:text-red-500 opacity-100 md:opacity-0 md:group-hover:opacity-100 md:focus-visible:opacity-100 transition-opacity z-10">
                                <span class="w-7 h-7 flex items-center justify-center bg-white dark:bg-slate-700 border border-slate-200 dark:border-slate-600 rounded-full shadow-sm">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"></path></svg>
                                </span>
                            </button>
                        </div>
                    </div>
                    <div v-else class="text-center py-10">
                        <div class="w-12 h-12 bg-slate-50 dark:bg-slate-800 rounded-full flex items-center justify-center mx-auto mb-3">
                            <svg class="w-6 h-6 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path></svg>
                        </div>
                        <p class="text-[11px] font-black uppercase tracking-wide text-slate-400">
                            {{ notifTab === 'unread' ? 'Nothing unread' : 'All caught up' }}
                        </p>
                    </div>
                </div>
            </div>
        </transition>

        <transition
            enter-active-class="transition ease-out duration-200 transform" enter-from-class="opacity-0 translate-y-4" enter-to-class="opacity-100 translate-y-0"
            leave-active-class="transition ease-in duration-150 transform" leave-from-class="opacity-100 translate-y-0" leave-to-class="opacity-0 translate-y-4"
        >
            <div v-if="showingMoreMenu" class="more-sheet md:hidden fixed inset-x-0 z-[60] bg-white dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800 rounded-t-2xl shadow-2xl p-2">
                <p class="px-3 py-2 text-[11px] font-black uppercase tracking-wide text-slate-400">More</p>
                <Link v-for="item in overflowMenus" :key="item.route"
                      :href="route(item.route)"
                      @click="showingMoreMenu = false"
                      class="flex items-center gap-3 px-3 min-h-[52px] rounded-xl transition"
                      :class="route().current(item.route)
                          ? 'bg-blue-50 text-blue-700 dark:bg-blue-600/10 dark:text-blue-400 font-bold'
                          : 'text-slate-600 dark:text-slate-300 active:bg-slate-100 dark:active:bg-slate-800'">
                    <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" :d="item.icon"></path></svg>
                    <span class="text-sm flex-1 truncate">{{ item.name }}</span>
                    <span v-if="item.badge > 0" class="bg-red-500 text-white text-[11px] font-black px-2 py-0.5 rounded-full">{{ item.badge }}</span>
                </Link>
            </div>
        </transition>

        <nav class="bottom-nav md:hidden fixed bottom-0 inset-x-0 bg-white/98 dark:bg-slate-900/98 backdrop-blur-xl border-t border-slate-200 dark:border-slate-800 z-40 grid shadow-[0_-8px_15px_-3px_rgba(0,0,0,0.05)]"
             :style="{ gridTemplateColumns: `repeat(${tabColumns}, minmax(0, 1fr))` }">
            <Link v-for="item in primaryMenus" :key="item.route"
                 :href="route(item.route)"
                class="relative min-w-0 min-h-[56px] px-0.5 pt-1.5 pb-1 flex flex-col items-center justify-center gap-0.5 transition-colors duration-200"
                :class="route().current(item.route) ? 'text-blue-600 dark:text-blue-400' : 'text-slate-500 dark:text-slate-400'"
            >
                <span v-if="route().current(item.route)" class="absolute top-0 left-1/2 -translate-x-1/2 w-8 h-0.5 bg-blue-600 dark:bg-blue-400 rounded-b-full"></span>

                <span class="relative flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" :d="item.icon"></path></svg>
                    <span v-if="item.badge > 0" class="absolute -top-0.5 -right-1.5 h-2.5 w-2.5 rounded-full bg-red-500 border border-white dark:border-slate-900"></span>
                </span>

                <span class="text-[11px] font-bold tracking-tight w-full text-center truncate leading-none">{{ item.name }}</span>
            </Link>

            <button v-if="overflowMenus.length" @click.stop="showingMoreMenu = !showingMoreMenu"
                    class="relative min-w-0 min-h-[56px] px-0.5 pt-1.5 pb-1 flex flex-col items-center justify-center gap-0.5 transition-colors duration-200"
                    :class="(showingMoreMenu || overflowIsActive) ? 'text-blue-600 dark:text-blue-400' : 'text-slate-500 dark:text-slate-400'">
                <span v-if="overflowIsActive" class="absolute top-0 left-1/2 -translate-x-1/2 w-8 h-0.5 bg-blue-600 dark:bg-blue-400 rounded-b-full"></span>
                <span class="relative flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" :d="icons.more"></path></svg>
                    <span v-if="overflowBadge > 0" class="absolute -top-0.5 -right-1.5 h-2.5 w-2.5 rounded-full bg-red-500 border border-white dark:border-slate-900"></span>
                </span>
                <span class="text-[11px] font-bold tracking-tight w-full text-center truncate leading-none">More</span>
            </button>
        </nav>

        <div v-if="user.role !== 'dean'" class="hidden md:block fixed z-40 bottom-6 right-20 transition-all duration-300">
            <button @click.stop="showingNotifications = !showingNotifications" aria-label="Notifications"
                    class="relative p-3.5 bg-white dark:bg-slate-800 border border-black dark:border-white rounded-full shadow-lg hover:shadow-xl hover:bg-slate-50 dark:hover:bg-slate-700 transition hover:-translate-y-1 text-slate-600 dark:text-slate-300 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-blue-500">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                <span v-if="unreadCount > 0" class="absolute top-0 right-0 w-3.5 h-3.5 bg-red-500 rounded-full border-2 border-white dark:border-slate-800"></span>
            </button>
        </div>

        <AIChatWidget v-if="user.role !== 'dean'" />

        <Modal :show="confirmingToggle" @close="confirmingToggle = false" maxWidth="sm">
            <div class="p-5 sm:p-6 bg-white dark:bg-slate-900 rounded-xl shadow-xl border border-slate-200 dark:border-slate-800 max-h-[85dvh] overflow-y-auto">
                <h2 class="text-sm font-black uppercase tracking-tight text-slate-900 dark:text-white flex items-center gap-2 mb-2">
                    <svg class="w-5 h-5 text-red-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                    Confirm your password
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">Enter your password to turn off material approval.</p>
                <div class="space-y-4">
                    <div>
                        <InputLabel for="password" value="Password" class="sr-only" />
                        <TextInput
                            id="password"
                            v-model="toggleForm.password"
                            type="password"
                            class="block w-full text-base sm:text-sm py-2.5"
                            placeholder="Admin password"
                            @keyup.enter="submitToggle"
                        />
                    </div>
                    <div class="flex justify-end gap-2 pt-3 border-t border-slate-100 dark:border-slate-800">
                        <button @click="confirmingToggle = false" type="button" class="px-4 min-h-[44px] text-[11px] font-black uppercase tracking-wide text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 transition">Cancel</button>
                        <button @click="submitToggle" :disabled="toggleForm.processing" class="px-5 min-h-[44px] bg-red-600 hover:bg-red-500 text-white rounded text-[11px] font-black uppercase tracking-wide shadow-sm transition disabled:opacity-50">Turn off</button>
                    </div>
                </div>
            </div>
        </Modal>

        <transition
            enter-active-class="transition ease-out duration-300 transform" enter-from-class="opacity-0 translate-y-4 scale-95" enter-to-class="opacity-100 translate-y-0 scale-100"
            leave-active-class="transition ease-in duration-200 transform" leave-from-class="opacity-100 translate-y-0 scale-100" leave-to-class="opacity-0 translate-y-4 scale-95"
        >
            <button v-show="showScrollButton" @click="scrollToTop" aria-label="Back to top"
                 class="scroll-top-btn fixed z-50 right-4 md:right-8 w-12 h-12 flex items-center justify-center bg-slate-800 dark:bg-slate-700 text-white rounded-full shadow-lg hover:bg-slate-700 dark:hover:bg-slate-600 transition-all hover:-translate-y-1 focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-slate-400/50 border border-black dark:border-white">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 10l7-7m0 0l7 7m-7-7v18"></path></svg>
            </button>
        </transition>
    </div>
</template>

<style scoped>
.app-shell {
    --bottom-nav-h: 0px;
    --safe-bottom: env(safe-area-inset-bottom, 0px);
}

@media (max-width: 767px) {
    .app-shell {
        --bottom-nav-h: 56px;
    }
}

.bottom-nav {
    padding-bottom: var(--safe-bottom);
}

.main-area {
    padding-bottom: calc(1rem + var(--bottom-nav-h) + var(--safe-bottom));
}

@media (min-width: 768px) {
    .main-area {
        padding-bottom: 1.5rem;
    }
}

.more-sheet {
    bottom: calc(var(--bottom-nav-h) + var(--safe-bottom));
}

.scroll-top-btn {
    bottom: calc(1rem + var(--bottom-nav-h) + var(--safe-bottom));
}

@media (min-width: 768px) {
    .scroll-top-btn {
        bottom: 1.5rem;
    }
}

.notif-panel {
    max-height: 70dvh;
}

@media (min-width: 768px) {
    .notif-panel {
        max-height: 75dvh;
    }
}

.tap-target {
    min-width: 44px;
    min-height: 44px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(148, 163, 184, 0.35);
    border-radius: 10px;
}
.custom-scrollbar:hover::-webkit-scrollbar-thumb {
    background: rgba(148, 163, 184, 0.55);
}

@media (prefers-reduced-motion: reduce) {
    .app-shell * {
        animation-duration: 0.01ms !important;
        transition-duration: 0.01ms !important;
    }
}
</style>