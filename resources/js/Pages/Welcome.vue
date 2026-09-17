<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted } from 'vue';

defineProps({
    canLogin: {
        type: Boolean,
    },
    canRegister: {
        type: Boolean,
    },
    studentsCount: {
        type: Number,
        default: 0
    },
    coursesCount: {
        type: Number,
        default: 0
    },
    teachersCount: {
        type: Number,
        default: 0
    },
    resourcesCount: {
        type: Number,
        default: 0
    }
});

const isMobileMenuOpen = ref(false);
const isScrolled = ref(false);

const handleScroll = () => {
    isScrolled.value = window.scrollY > 20;
};

onMounted(() => {
    window.addEventListener('scroll', handleScroll);
});

onUnmounted(() => {
    window.removeEventListener('scroll', handleScroll);
});
</script>

<template>
    <Head title="Welcome to CDN LMS" />
    
    <div class="min-h-screen bg-blue-900 flex flex-col font-sans selection:bg-yellow-400 selection:text-blue-900 overflow-x-hidden">
        
        <!-- ========================================== -->
        <!-- NAVIGATION (Sticky & Glassmorphism)        -->
        <!-- ========================================== -->
        <header 
            class="fixed top-0 inset-x-0 z-50 transition-all duration-300 border-b"
            :class="isScrolled ? 'bg-blue-950/90 backdrop-blur-md shadow-lg border-blue-800/50 py-3' : 'bg-transparent border-transparent py-5'"
        >
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex justify-between items-center">
                <!-- Actual CDN Logo -->
                <div class="flex items-center gap-2.5">
                    <img src="/images/Logo2.png" alt="Colegio de Naujan Logo" class="w-10 h-10 object-contain drop-shadow-md" />
                    <span class="text-xl font-extrabold text-white tracking-tight">CDN <span class="text-yellow-400">LMS</span></span>
                </div>

                <!-- Desktop Nav Links -->
                <nav class="hidden md:flex items-center gap-8">
                    <a href="#features" class="text-sm font-bold text-blue-100 hover:text-yellow-400 transition-colors">Features</a>
                    <a href="#about" class="text-sm font-bold text-blue-100 hover:text-yellow-400 transition-colors">About</a>
                    <a href="#contact" class="text-sm font-bold text-blue-100 hover:text-yellow-400 transition-colors">Contact</a>
                </nav>

                <!-- Auth Links (Desktop) -->
                <div v-if="canLogin" class="hidden md:flex items-center gap-4">
                    <template v-if="$page.props.auth.user">
                        <Link :href="route('dashboard')" class="text-sm font-bold text-white hover:text-yellow-400 transition-colors">
                            Dashboard
                        </Link>
                        <Link :href="route('logout')" method="post" as="button" class="text-sm font-bold text-red-400 bg-red-900/30 hover:bg-red-900/50 border border-red-800/50 px-4 py-2 rounded-lg transition-colors">
                            Log Out
                        </Link>
                    </template>
                    <template v-else>
                        <Link :href="route('login')" class="text-sm font-bold text-blue-100 hover:text-yellow-400 transition-colors">
                            Log in
                        </Link>
                        <Link v-if="canRegister" :href="route('register')" class="text-sm font-bold bg-blue-600 hover:bg-blue-500 text-white border border-blue-500 px-5 py-2.5 rounded-xl shadow-sm shadow-blue-900/50 transition-all hover:-translate-y-0.5 active:translate-y-0">
                            Register
                        </Link>
                    </template>
                </div>

                <!-- Mobile Menu Toggle -->
                <button @click="isMobileMenuOpen = !isMobileMenuOpen" class="md:hidden p-2 text-blue-100 hover:text-yellow-400 transition focus:outline-none">
                    <svg v-if="!isMobileMenuOpen" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    <svg v-else class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <!-- Mobile Menu -->
            <div v-show="isMobileMenuOpen" class="md:hidden absolute top-full left-0 w-full bg-blue-950 border-t border-blue-800 shadow-2xl py-4 px-6 flex flex-col gap-4">
                <a href="#features" @click="isMobileMenuOpen = false" class="text-base font-bold text-blue-100 block hover:text-yellow-400">Features</a>
                <a href="#about" @click="isMobileMenuOpen = false" class="text-base font-bold text-blue-100 block hover:text-yellow-400">About</a>
                <a href="#contact" @click="isMobileMenuOpen = false" class="text-base font-bold text-blue-100 block hover:text-yellow-400">Contact</a>
                <hr class="border-blue-800 my-2">
                <template v-if="canLogin">
                    <template v-if="$page.props.auth.user">
                        <Link :href="route('dashboard')" class="text-base font-bold text-yellow-400 block">Dashboard</Link>
                        <Link :href="route('logout')" method="post" as="button" class="text-base font-bold text-red-400 text-left block">Log Out</Link>
                    </template>
                    <template v-else>
                        <Link :href="route('login')" class="text-base font-bold text-blue-100 block hover:text-yellow-400">Log in</Link>
                        <Link v-if="canRegister" :href="route('register')" class="text-base font-bold text-yellow-400 block">Register</Link>
                    </template>
                </template>
            </div>
        </header>

        <!-- ========================================== -->
        <!-- HERO SECTION                               -->
        <!-- ========================================== -->
        <main class="flex-1 flex flex-col pt-32 pb-16 lg:pt-40 lg:pb-24 overflow-hidden relative">
            
            <!-- Subtle Background Gradients -->
            <div class="absolute top-0 left-1/2 -translate-x-1/2 w-full max-w-5xl h-[500px] bg-blue-600/30 rounded-full blur-3xl pointer-events-none -z-10"></div>
            
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 w-full">
                <div class="grid lg:grid-cols-2 gap-12 lg:gap-8 items-center">
                    
                    <!-- Left: Hero Content -->
                    <div class="max-w-2xl animate-fade-in-up">
                        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-blue-800/50 border border-blue-700/50 mb-6 shadow-sm backdrop-blur-sm">
                            <span class="w-2 h-2 rounded-full bg-red-500 animate-pulse"></span>
                            <span class="text-[10px] sm:text-xs font-black tracking-widest uppercase text-blue-100">Colegio de Naujan • Learning Management System</span>
                        </div>
                        
                        <h1 class="text-5xl sm:text-6xl lg:text-7xl font-extrabold text-white tracking-tight leading-[1.1] mb-6">
                            Learn. Grow. <br />
                            <!-- Brand Colors Gradient Text -->
                            <span class="text-transparent bg-clip-text bg-gradient-to-r from-yellow-400 via-red-400 to-yellow-400 drop-shadow-sm">Achieve.</span>
                        </h1>
                        
                        <p class="text-lg sm:text-xl text-blue-100 mb-8 leading-relaxed font-medium max-w-lg">
                            Your digital learning experience, designed exclusively for the Colegio de Naujan community. Access courses, connect with peers, and excel academically.
                        </p>

                        <div class="flex flex-col sm:flex-row items-center gap-4">
                            <Link
                                v-if="$page.props.auth.user"
                                :href="route('dashboard')"
                                class="w-full sm:w-auto px-8 py-3.5 bg-blue-600 text-white text-sm font-bold uppercase tracking-widest rounded-xl hover:bg-blue-500 transition-all shadow-lg shadow-blue-900/50 hover:-translate-y-1 hover:shadow-xl flex items-center justify-center gap-2 group border border-blue-500"
                            >
                                Go to Dashboard
                                <svg class="w-4 h-4 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                            </Link>
                            <Link
                                v-else
                                :href="route('login')"
                                class="w-full sm:w-auto px-8 py-3.5 bg-blue-600 text-white text-sm font-bold uppercase tracking-widest rounded-xl hover:bg-blue-500 transition-all shadow-lg shadow-blue-900/50 hover:-translate-y-1 hover:shadow-xl flex items-center justify-center gap-2 group border border-blue-500"
                            >
                                Get Started
                                <svg class="w-4 h-4 group-hover:translate-x-1 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                            </Link>

                            <a href="#features" class="w-full sm:w-auto px-8 py-3.5 bg-blue-950/50 text-blue-100 text-sm font-bold uppercase tracking-widest rounded-xl hover:bg-blue-800 transition-all shadow-sm border border-blue-800 hover:-translate-y-1 flex items-center justify-center backdrop-blur-sm">
                                Explore LMS
                            </a>
                        </div>
                    </div>

                    <!-- Right: CSS-based LMS Visual Composition (Dark Theme) -->
                    <div class="relative w-full h-[400px] sm:h-[500px] hidden md:block animate-fade-in-up delay-200">
                        
                        <!-- Main Dashboard Mockup -->
                        <div class="absolute inset-0 bg-[#0f172a] rounded-2xl shadow-[0_20px_50px_rgba(0,0,0,0.5)] border border-slate-700 flex flex-col overflow-hidden animate-float">
                            
                            <!-- Mockup Header -->
                            <div class="h-12 border-b border-slate-800 bg-[#1e293b] flex items-center justify-between px-4 shrink-0">
                                <div class="flex gap-2">
                                    <!-- Brand Color Window Controls -->
                                    <div class="w-3 h-3 rounded-full bg-red-500"></div>
                                    <div class="w-3 h-3 rounded-full bg-yellow-400"></div>
                                    <div class="w-3 h-3 rounded-full bg-blue-500"></div>
                                </div>
                                <!-- Fake Search Bar -->
                                <div class="w-48 h-6 bg-slate-800 rounded-md border border-slate-700/50"></div>
                            </div>

                            <!-- Mockup Body -->
                            <div class="flex-1 flex p-5 gap-5 bg-[#0f172a]">
                                
                                <!-- Sidebar Wireframe -->
                                <div class="w-1/4 flex flex-col gap-3 hidden sm:flex border-r border-slate-800 pr-5">
                                    <!-- Active Nav Item -->
                                    <div class="w-full h-8 bg-blue-600/20 border border-blue-500/30 rounded-lg flex items-center px-2.5 gap-2.5">
                                        <div class="w-3.5 h-3.5 rounded bg-blue-500/80"></div>
                                        <div class="w-12 h-2 bg-blue-400/80 rounded"></div>
                                    </div>
                                    <!-- Inactive Nav Items -->
                                    <div class="w-full h-8 flex items-center px-2.5 gap-2.5 mt-2">
                                        <div class="w-3.5 h-3.5 rounded bg-slate-700"></div>
                                        <div class="w-16 h-2 bg-slate-700 rounded"></div>
                                    </div>
                                    <div class="w-full h-8 flex items-center px-2.5 gap-2.5">
                                        <div class="w-3.5 h-3.5 rounded bg-slate-700"></div>
                                        <div class="w-10 h-2 bg-slate-700 rounded"></div>
                                    </div>
                                    <div class="w-full h-8 flex items-center px-2.5 gap-2.5">
                                        <div class="w-3.5 h-3.5 rounded bg-slate-700"></div>
                                        <div class="w-14 h-2 bg-slate-700 rounded"></div>
                                    </div>
                                </div>

                                <!-- Content Wireframe -->
                                <div class="flex-1 flex flex-col gap-5">
                                    
                                    <!-- Greeting & Profile Skeleton -->
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <div class="w-32 h-4 bg-slate-600 rounded mb-2"></div>
                                            <div class="w-48 h-2 bg-slate-700 rounded"></div>
                                        </div>
                                        <div class="w-10 h-10 rounded-full bg-slate-700 border-2 border-slate-600"></div>
                                    </div>

                                    <!-- Top Stats Grid -->
                                    <div class="grid grid-cols-2 gap-4">
                                        <div class="h-20 bg-slate-800/80 border border-slate-700 rounded-xl p-3 flex flex-col justify-between">
                                            <div class="w-7 h-7 rounded-full bg-blue-500/20 flex items-center justify-center">
                                                <div class="w-3 h-3 bg-blue-500 rounded-sm"></div>
                                            </div>
                                            <div>
                                                <div class="w-8 h-4 bg-slate-300 rounded mb-1.5"></div>
                                                <div class="w-16 h-2 bg-slate-500 rounded"></div>
                                            </div>
                                        </div>
                                        <div class="h-20 bg-slate-800/80 border border-slate-700 rounded-xl p-3 flex flex-col justify-between">
                                            <div class="w-7 h-7 rounded-full bg-yellow-500/20 flex items-center justify-center">
                                                <div class="w-3 h-3 bg-yellow-500 rounded-full"></div>
                                            </div>
                                            <div>
                                                <div class="w-6 h-4 bg-slate-300 rounded mb-1.5"></div>
                                                <div class="w-20 h-2 bg-slate-500 rounded"></div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Course/Task List Area -->
                                    <div class="flex-1 bg-slate-800/80 border border-slate-700 rounded-xl p-4 flex flex-col gap-3">
                                        <div class="w-24 h-3 bg-slate-600 rounded mb-2"></div>
                                        
                                        <!-- List Item 1 -->
                                        <div class="flex items-center gap-3 p-2.5 bg-slate-900/50 rounded-lg border border-slate-700/50">
                                            <div class="w-8 h-8 rounded bg-blue-600/20 border border-blue-500/30"></div>
                                            <div class="flex-1">
                                                <div class="w-32 h-2.5 bg-slate-400 rounded mb-1.5"></div>
                                                <div class="w-20 h-1.5 bg-slate-600 rounded"></div>
                                            </div>
                                            <div class="w-16 h-1.5 bg-slate-700 rounded-full overflow-hidden">
                                                <div class="w-3/4 h-full bg-blue-500"></div>
                                            </div>
                                        </div>

                                        <!-- List Item 2 -->
                                        <div class="flex items-center gap-3 p-2.5 bg-slate-900/50 rounded-lg border border-slate-700/50">
                                            <div class="w-8 h-8 rounded bg-red-600/20 border border-red-500/30"></div>
                                            <div class="flex-1">
                                                <div class="w-28 h-2.5 bg-slate-400 rounded mb-1.5"></div>
                                                <div class="w-16 h-1.5 bg-slate-600 rounded"></div>
                                            </div>
                                            <div class="w-16 h-1.5 bg-slate-700 rounded-full overflow-hidden">
                                                <div class="w-1/3 h-full bg-red-500"></div>
                                            </div>
                                        </div>
                                        
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Floating Card 1 (Red Accent) -->
                        <div class="absolute -right-6 top-16 bg-slate-800 p-4 rounded-xl shadow-xl shadow-black/40 border border-slate-700 flex items-center gap-3 animate-float-delayed z-10 w-48">
                            <div class="w-10 h-10 rounded-full bg-red-900/50 border border-red-800 flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            </div>
                            <div>
                                <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">Assignment</div>
                                <div class="text-xs font-bold text-white mt-0.5">Submitted</div>
                            </div>
                        </div>

                        <!-- Floating Card 2 (Yellow Accent) -->
                        <div class="absolute -left-8 bottom-20 bg-slate-800 p-4 rounded-xl shadow-xl shadow-black/40 border border-slate-700 flex items-center gap-3 animate-float-slow z-10 w-56">
                            <div class="w-10 h-10 rounded-xl bg-yellow-900/50 flex items-center justify-center shrink-0 shadow-sm border border-yellow-700/50">
                                <svg class="w-5 h-5 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477-4.5 1.253"></path></svg>
                            </div>
                            <div>
                                <div class="text-[10px] font-black text-slate-400 uppercase tracking-widest">CDN COURSE</div>
                                <div class="text-xs font-bold text-white mt-0.5">Introduction to CDN</div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </main>

        <!-- ========================================== -->
        <!-- FEATURES SECTION                           -->
        <!-- ========================================== -->
        <section id="features" class="py-20 bg-blue-900 border-t border-blue-800">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <div class="text-center max-w-2xl mx-auto mb-16 animate-fade-in-up delay-100">
                    <h2 class="text-3xl md:text-4xl font-extrabold text-white tracking-tight mb-4">Everything You Need to Learn Better</h2>
                    <p class="text-blue-100 text-lg font-medium">A robust, intuitive suite of tools designed to connect the CDN community and simplify academic management.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    
                    <!-- Feature 1: Blue -->
                    <div class="bg-blue-800/30 rounded-2xl p-6 border border-blue-700/50 hover:bg-blue-800/60 hover:shadow-2xl hover:shadow-blue-950/50 hover:-translate-y-1 transition-all duration-300 group backdrop-blur-sm">
                        <div class="w-12 h-12 rounded-xl bg-blue-900/80 text-blue-400 flex items-center justify-center mb-6 group-hover:bg-blue-500 group-hover:text-white transition-colors duration-300 shadow-sm border border-blue-600/50">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477-4.5 1.253"></path></svg>
                        </div>
                        <h3 class="text-lg font-extrabold text-white mb-2">Online Learning</h3>
                        <p class="text-sm text-blue-100 leading-relaxed font-medium">Access your learning materials, lectures, and required courses anytime, anywhere.</p>
                    </div>

                    <!-- Feature 2: Red -->
                    <div class="bg-blue-800/30 rounded-2xl p-6 border border-blue-700/50 hover:bg-blue-800/60 hover:shadow-2xl hover:shadow-blue-950/50 hover:-translate-y-1 transition-all duration-300 group backdrop-blur-sm">
                        <div class="w-12 h-12 rounded-xl bg-red-900/50 text-red-400 flex items-center justify-center mb-6 group-hover:bg-red-600 group-hover:text-white transition-colors duration-300 shadow-sm border border-red-700/50">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012-2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                        </div>
                        <h3 class="text-lg font-extrabold text-white mb-2">Course Management</h3>
                        <p class="text-sm text-blue-100 leading-relaxed font-medium">Stay organized with your subjects, assignments, activities, and digital resources.</p>
                    </div>

                    <!-- Feature 3: Yellow -->
                    <div class="bg-blue-800/30 rounded-2xl p-6 border border-blue-700/50 hover:bg-blue-800/60 hover:shadow-2xl hover:shadow-blue-950/50 hover:-translate-y-1 transition-all duration-300 group backdrop-blur-sm">
                        <div class="w-12 h-12 rounded-xl bg-yellow-900/50 text-yellow-400 flex items-center justify-center mb-6 group-hover:bg-yellow-500 group-hover:text-white transition-colors duration-300 shadow-sm border border-yellow-700/50">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                        </div>
                        <h3 class="text-lg font-extrabold text-white mb-2">Student Progress</h3>
                        <p class="text-sm text-blue-100 leading-relaxed font-medium">Keep strict track of your academic progress, grades, and learning activities.</p>
                    </div>

                    <!-- Feature 4: Dark Blue -->
                    <div class="bg-blue-800/30 rounded-2xl p-6 border border-blue-700/50 hover:bg-blue-800/60 hover:shadow-2xl hover:shadow-blue-950/50 hover:-translate-y-1 transition-all duration-300 group backdrop-blur-sm">
                        <div class="w-12 h-12 rounded-xl bg-blue-950 text-blue-300 flex items-center justify-center mb-6 group-hover:bg-blue-700 group-hover:text-white transition-colors duration-300 shadow-sm border border-blue-800">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        </div>
                        <h3 class="text-lg font-extrabold text-white mb-2">Connected Community</h3>
                        <p class="text-sm text-blue-100 leading-relaxed font-medium">Connect students, deans, and educators effortlessly through one centralized platform.</p>
                    </div>

                </div>
            </div>
        </section>

        <!-- ========================================== -->
        <!-- STATISTICS SECTION                         -->
        <!-- ========================================== -->
        <section class="py-20 bg-blue-950 relative overflow-hidden border-t border-blue-900/50">
            <!-- Decorative background elements using Brand Colors -->
            <div class="absolute inset-0 opacity-20 bg-[radial-gradient(circle_at_top_left,_var(--tw-gradient-stops))] from-blue-600 via-transparent to-transparent"></div>
            <div class="absolute inset-0 opacity-10 bg-[radial-gradient(circle_at_bottom_right,_var(--tw-gradient-stops))] from-red-600 via-transparent to-transparent"></div>
            
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 text-center">
                <h2 class="text-3xl font-extrabold text-white tracking-tight mb-12">Built for the CDN Community</h2>
                
                <div class="grid grid-cols-2 md:grid-cols-4 gap-8 divide-x divide-blue-900">
                    <div class="flex flex-col items-center">
                        <span class="text-4xl md:text-5xl font-black text-yellow-400 mb-2">{{ studentsCount || '2,000' }}+</span>
                        <span class="text-xs font-bold text-blue-200 uppercase tracking-widest">Students</span>
                    </div>
                    <div class="flex flex-col items-center">
                        <span class="text-4xl md:text-5xl font-black text-yellow-400 mb-2">{{ coursesCount || '150' }}+</span>
                        <span class="text-xs font-bold text-blue-200 uppercase tracking-widest">Courses</span>
                    </div>
                    <div class="flex flex-col items-center">
                        <span class="text-4xl md:text-5xl font-black text-yellow-400 mb-2">{{ teachersCount || '50' }}+</span>
                        <span class="text-xs font-bold text-blue-200 uppercase tracking-widest">Educators</span>
                    </div>
                    <div class="flex flex-col items-center">
                        <span class="text-4xl md:text-5xl font-black text-yellow-400 mb-2">{{ resourcesCount || '5,000' }}+</span>
                        <span class="text-xs font-bold text-blue-200 uppercase tracking-widest">Resources</span>
                    </div>
                </div>
            </div>
        </section>

        <!-- ========================================== -->
        <!-- ABOUT SECTION                              -->
        <!-- ========================================== -->
        <section id="about" class="py-20 bg-blue-900 border-t border-blue-800">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid lg:grid-cols-2 gap-16 items-center">
                    
                    <!-- Visual Side with Real CDN Logo -->
                    <div class="relative order-2 lg:order-1 hidden sm:block">
                        <div class="aspect-square rounded-full bg-blue-950 relative flex items-center justify-center shadow-inner border border-blue-800">
                            
                            <!-- Geometric background glows bounded by the circle -->
                            <div class="absolute inset-0 rounded-full overflow-hidden pointer-events-none">
                                <div class="w-64 h-64 bg-blue-600 rounded-3xl rotate-12 absolute opacity-10"></div>
                                <div class="w-48 h-48 bg-red-600 rounded-full -translate-x-12 translate-y-12 absolute opacity-10"></div>
                                <div class="w-32 h-32 bg-yellow-400 rounded-full translate-x-20 -translate-y-16 absolute opacity-10 blur-xl"></div>
                            </div>
                            
                            <!-- Central CDN Logo filling the circle -->
                            <img src="/images/Logo2.png" alt="Colegio de Naujan Logo" class="w-[85%] h-[85%] object-contain z-10 drop-shadow-2xl animate-float-slow" />
                        </div>
                    </div>

                    <!-- Text Side -->
                    <div class="order-1 lg:order-2">
                        <h2 class="text-3xl md:text-4xl font-extrabold text-white tracking-tight mb-6">Empowering the Future of Colegio de Naujan</h2>
                        <p class="text-lg text-blue-100 mb-6 font-medium leading-relaxed">
                            The Colegio de Naujan Learning Management System (CDN LMS) was built with a singular vision: to create a seamless, accessible, and powerful academic environment for our students and educators.
                        </p>
                        <p class="text-lg text-blue-100 mb-8 font-medium leading-relaxed">
                            We believe that technology should break down barriers to education. Whether you are tracking assignments, distributing course materials, or collaborating on projects, our platform brings the entire classroom experience directly to your screen.
                        </p>
                        
                        <div class="flex flex-col sm:flex-row gap-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-red-900/50 border border-red-700 flex items-center justify-center shrink-0">
                                    <svg class="w-5 h-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                </div>
                                <span class="text-sm font-bold text-white">Student-Focused</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-yellow-900/50 border border-yellow-700 flex items-center justify-center shrink-0">
                                    <svg class="w-5 h-5 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                                </div>
                                <span class="text-sm font-bold text-white">High Performance</span>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </section>

        <!-- ========================================== -->
        <!-- CALL TO ACTION                             -->
        <!-- ========================================== -->
        <section class="py-24 bg-blue-800 relative overflow-hidden border-t border-blue-700">
            <!-- Decorative accents -->
            <div class="absolute top-0 right-0 -mr-20 -mt-20 w-72 h-72 rounded-full bg-red-500/20 blur-3xl pointer-events-none"></div>
            <div class="absolute bottom-0 left-0 -ml-20 -mb-20 w-80 h-80 rounded-full bg-yellow-400/10 blur-3xl pointer-events-none"></div>

            <div class="max-w-4xl mx-auto px-4 sm:px-6 text-center relative z-10">
                <h2 class="text-4xl md:text-5xl font-extrabold text-white tracking-tight mb-6">Ready to Start Learning?</h2>
                <p class="text-xl text-blue-100 mb-10 font-medium max-w-2xl mx-auto">
                    Access your Colegio de Naujan learning experience today and take the next step in your academic journey.
                </p>
                
                <Link
                    v-if="$page.props.auth.user"
                    :href="route('dashboard')"
                    class="inline-flex items-center justify-center px-10 py-4 bg-white text-blue-900 text-sm font-black uppercase tracking-widest rounded-xl hover:bg-blue-50 transition-all shadow-xl hover:-translate-y-1 hover:shadow-2xl"
                >
                    Go to Dashboard
                </Link>
                <Link
                    v-else
                    :href="route('login')"
                    class="inline-flex items-center justify-center px-10 py-4 bg-white text-blue-900 text-sm font-black uppercase tracking-widest rounded-xl hover:bg-blue-50 transition-all shadow-xl hover:-translate-y-1 hover:shadow-2xl"
                >
                    Get Started Now
                </Link>
            </div>
        </section>

        <!-- ========================================== -->
        <!-- CONTACT SECTION                            -->
        <!-- ========================================== -->
        <section id="contact" class="py-20 bg-blue-900 border-t border-blue-800">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                
                <div class="text-center max-w-2xl mx-auto mb-16 animate-fade-in-up delay-100">
                    <h2 class="text-3xl md:text-4xl font-extrabold text-white tracking-tight mb-4">Get in Touch</h2>
                    <p class="text-blue-100 text-lg font-medium">Have questions or need assistance? Reach out to the Colegio de Naujan administration.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    
                    <!-- Location -->
                    <div class="bg-blue-800/30 rounded-2xl p-6 border border-blue-700/50 flex flex-col items-center text-center hover:bg-blue-800/60 transition-colors backdrop-blur-sm group">
                        <div class="w-12 h-12 rounded-full bg-red-900/50 text-red-400 flex items-center justify-center mb-4 border border-red-700/50 group-hover:bg-red-600 group-hover:text-white transition-colors">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        </div>
                        <h3 class="text-lg font-bold text-white mb-2">Visit Us</h3>
                        <p class="text-sm text-blue-200">Colegio De Naujan, Santiago,<br/>Naujan, Philippines, 5204</p>
                    </div>

                    <!-- Email -->
                    <div class="bg-blue-800/30 rounded-2xl p-6 border border-blue-700/50 flex flex-col items-center text-center hover:bg-blue-800/60 transition-colors backdrop-blur-sm group">
                        <div class="w-12 h-12 rounded-full bg-yellow-900/50 text-yellow-400 flex items-center justify-center mb-4 border border-yellow-700/50 group-hover:bg-yellow-500 group-hover:text-white transition-colors">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                        </div>
                        <h3 class="text-lg font-bold text-white mb-2">Email Us</h3>
                        <a href="mailto:colegiodenaujanlms@gmail.com" class="text-sm text-blue-200 hover:text-yellow-400 transition-colors break-all">colegiodenaujanlms@gmail.com</a>
                    </div>

                    <!-- Phone -->
                    <div class="bg-blue-800/30 rounded-2xl p-6 border border-blue-700/50 flex flex-col items-center text-center hover:bg-blue-800/60 transition-colors backdrop-blur-sm group">
                        <div class="w-12 h-12 rounded-full bg-blue-700/50 text-blue-300 flex items-center justify-center mb-4 border border-blue-600/50 group-hover:bg-blue-500 group-hover:text-white transition-colors">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path></svg>
                        </div>
                        <h3 class="text-lg font-bold text-white mb-2">Call Us</h3>
                        <p class="text-sm text-blue-200">0943 738 7755</p>
                    </div>

                    <!-- Facebook -->
                    <div class="bg-blue-800/30 rounded-2xl p-6 border border-blue-700/50 flex flex-col items-center text-center hover:bg-blue-800/60 transition-colors backdrop-blur-sm group">
                        <div class="w-12 h-12 rounded-full bg-blue-900/50 text-blue-400 flex items-center justify-center mb-4 border border-blue-800/50 group-hover:bg-blue-600 group-hover:text-white transition-colors">
                            <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M22 12c0-5.523-4.477-10-10-10S2 6.477 2 12c0 4.991 3.657 9.128 8.438 9.878v-6.987h-2.54V12h2.54V9.797c0-2.506 1.492-3.89 3.777-3.89 1.094 0 2.238.195 2.238.195v2.46h-1.26c-1.243 0-1.63.771-1.63 1.562V12h2.773l-.443 2.89h-2.33v6.988C18.343 21.128 22 16.991 22 12z"></path></svg>
                        </div>
                        <h3 class="text-lg font-bold text-white mb-2">Follow Us</h3>
                        <a href="https://www.facebook.com/share/1GePgtgTCV/" target="_blank" rel="noopener noreferrer" class="text-sm text-blue-200 hover:text-yellow-400 transition-colors">Colegio de Naujan</a>
                    </div>

                </div>
            </div>
        </section>

        <!-- ========================================== -->
        <!-- FOOTER                                     -->
        <!-- ========================================== -->
        <footer class="bg-blue-950 border-t border-blue-900 py-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="flex flex-col md:flex-row justify-between items-center gap-6">
                    
                    <div class="flex items-center gap-3">
                        <img src="/images/Logo2.png" alt="Colegio de Naujan Logo" class="w-8 h-8 object-contain drop-shadow-sm" />
                        <div>
                            <span class="text-base font-extrabold text-white tracking-tight block leading-none">CDN LMS</span>
                            <span class="text-[10px] font-bold text-blue-200 uppercase tracking-widest">Colegio de Naujan</span>
                        </div>
                    </div>

                    <nav class="flex flex-wrap justify-center gap-6 md:gap-8">
                        <a href="#" class="text-sm font-bold text-blue-200 hover:text-yellow-400 transition-colors">Home</a>
                        <a href="#about" class="text-sm font-bold text-blue-200 hover:text-yellow-400 transition-colors">About</a>
                        <a href="#features" class="text-sm font-bold text-blue-200 hover:text-yellow-400 transition-colors">Features</a>
                        <a href="#contact" class="text-sm font-bold text-blue-200 hover:text-yellow-400 transition-colors">Contact</a>
                    </nav>

                </div>
                
                <hr class="border-blue-900 my-8">
                
                <div class="text-center text-sm font-medium text-blue-200">
                    &copy; {{ new Date().getFullYear() }} Colegio de Naujan. All rights reserved.
                </div>
            </div>
        </footer>

    </div>
</template>

<style scoped>
/* Smooth scrolling for anchor links */
html {
    scroll-behavior: smooth;
}

/* Custom Animations mapped to Tailwind classes */
@keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-15px); }
}

@keyframes float-delayed {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
}

@keyframes float-slow {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-8px); }
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-float {
    animation: float 5s ease-in-out infinite;
}

.animate-float-delayed {
    animation: float-delayed 6s ease-in-out 2s infinite;
}

.animate-float-slow {
    animation: float-slow 7s ease-in-out 1s infinite;
}

.animate-fade-in-up {
    animation: fadeInUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards;
    opacity: 0;
}

.delay-100 {
    animation-delay: 100ms;
}

.delay-200 {
    animation-delay: 200ms;
}
</style>