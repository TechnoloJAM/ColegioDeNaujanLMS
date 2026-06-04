<script setup>
import { ref, nextTick, onMounted, watch } from 'vue';
import axios from 'axios';
import { usePage } from '@inertiajs/vue3';

const isOpen = ref(false);
const messages = ref([]);
const messagesContainer = ref(null);
const userMessage = ref('');
const isLoading = ref(false);

const page = usePage();
const userId = page.props.auth.user.id;
const storageKey = `lms_ai_chat_history_${userId}`;

// --- PERSISTENCE LOGIC ---
onMounted(() => {
    const savedChat = localStorage.getItem(storageKey);
    if (savedChat) {
        messages.value = JSON.parse(savedChat);
    } else {
        messages.value = [
            { role: 'ai', content: 'Hello! I am your AI Assistant. How can I help you today?' }
        ];
    }
});

watch(messages, (newVal) => {
    localStorage.setItem(storageKey, JSON.stringify(newVal));
}, { deep: true });

const clearChat = () => {
    if (confirm("Are you sure you want to clear your chat history?")) {
        messages.value = [
            { role: 'ai', content: 'History cleared. How can I help you now?' }
        ];
        localStorage.removeItem(storageKey);
    }
};

// --- CHAT LOGIC ---
const scrollToBottom = async () => {
    await nextTick();
    if (messagesContainer.value) {
        messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
    }
};

const toggleChat = () => {
    isOpen.value = !isOpen.value;
    if (isOpen.value) scrollToBottom();
};

// NEW: Simple text formatter to make the AI's Bold text and Line Breaks look beautiful
const formatMessage = (text) => {
    if (!text) return '';
    return text
        .replace(/\*\*(.*?)\*\*/g, '<strong class="font-black">$1</strong>') // Bold formatting
        .replace(/\n/g, '<br/>'); // Line break formatting
};

const sendMessage = async () => {
    if (!userMessage.value.trim() || isLoading.value) return;

    const currentMessage = userMessage.value;
    messages.value.push({ role: 'user', content: currentMessage });
    userMessage.value = ''; 
    scrollToBottom();

    isLoading.value = true;
    messages.value.push({ role: 'ai', content: '...', isLoading: true });
    scrollToBottom();

    try {
        // NEW: Silently pass the current screen context to the AI
        const response = await axios.post(route('ai.chat'), { 
            message: currentMessage,
            current_url: window.location.pathname,
            page_title: document.title
        });
        
        messages.value.pop(); // Remove loading dots
        messages.value.push({ role: 'ai', content: response.data.response });
    } catch (error) {
        console.error("AI CHAT ERROR:", error.response?.data || error);
        messages.value.pop();
        messages.value.push({ role: 'ai', content: 'System Error. Please try again later.' });
    } finally {
        isLoading.value = false;
        scrollToBottom();
    }
};
</script>

<template>
    <div>
        <transition
            enter-active-class="transition ease-out duration-300"
            enter-from-class="opacity-0 translate-y-10 scale-95"
            enter-to-class="opacity-100 translate-y-0 scale-100"
            leave-active-class="transition ease-in duration-200"
            leave-from-class="opacity-100 translate-y-0 scale-100"
            leave-to-class="opacity-0 translate-y-10 scale-95"
        >
            <div v-show="isOpen" class="fixed z-50 flex flex-col bg-white dark:bg-slate-800 shadow-2xl border border-slate-200 dark:border-slate-700 overflow-hidden bottom-28 left-4 right-4 h-[60vh] rounded-2xl sm:bottom-24 sm:right-6 sm:left-auto sm:w-80 sm:h-[450px] sm:rounded-xl">
                
                <div class="bg-blue-600 p-3 sm:p-4 flex justify-between items-center shrink-0 shadow-md">
                    <div class="flex items-center gap-3">
                        <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center text-white backdrop-blur-sm">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                        </div>
                        <div>
                            <h3 class="font-bold text-white text-sm">AI Assistant</h3>
                            <p class="text-blue-100 text-[10px] flex items-center gap-1.5"><span class="w-1.5 h-1.5 rounded-full bg-green-400 animate-pulse"></span> Online</p>
                        </div>
                    </div>
                    
                    <div class="flex items-center gap-1">
                        <button @click="clearChat" class="text-blue-100 hover:text-white transition p-1.5 rounded-md hover:bg-white/10" title="Clear Chat">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                        </button>
                        <button @click="toggleChat" class="text-blue-100 hover:text-white transition p-1.5 rounded-md hover:bg-white/10">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </button>
                    </div>
                </div>

                <div ref="messagesContainer" class="flex-1 overflow-y-auto p-4 space-y-4 bg-slate-50 dark:bg-slate-900/50 scroll-smooth">
                    <div v-for="(msg, index) in messages" :key="index" class="flex gap-2" :class="msg.role === 'user' ? 'justify-end' : 'justify-start'">
                        <div v-if="msg.role === 'ai'" class="w-6 h-6 rounded-full bg-blue-100 dark:bg-slate-700 flex items-center justify-center shrink-0 mt-1">
                            <svg class="w-3.5 h-3.5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        </div>
                        <div class="max-w-[85%] px-3 py-2 rounded-xl text-[12px] sm:text-[11px] leading-relaxed shadow-sm" :class="msg.role === 'user' ? 'bg-blue-600 text-white rounded-br-none' : 'bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 rounded-bl-none'">
                            <span v-if="msg.isLoading" class="flex gap-1 items-center h-4"><span class="w-1 h-1 bg-current rounded-full animate-bounce"></span><span class="w-1 h-1 bg-current rounded-full animate-bounce delay-100"></span><span class="w-1 h-1 bg-current rounded-full animate-bounce delay-200"></span></span>
                            <span v-else v-html="formatMessage(msg.content)"></span>
                        </div>
                    </div>
                </div>

                <div class="p-2 bg-white dark:bg-slate-800 border-t border-slate-200 dark:border-slate-700 shrink-0">
                    <form @submit.prevent="sendMessage" class="relative flex items-center gap-2">
                        <input v-model="userMessage" type="text" placeholder="Type a message..." class="w-full pl-4 pr-10 py-2 bg-slate-100 dark:bg-slate-900 border-0 rounded-full text-xs text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 transition-all" />
                        <button type="submit" :disabled="!userMessage.trim() || isLoading" class="absolute right-1.5 top-1/2 -translate-y-1/2 p-1.5 bg-blue-600 text-white rounded-full hover:bg-blue-500 disabled:opacity-50 transition">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M12 5l7 7-7 7"></path></svg>
                        </button>
                    </form>
                </div>
            </div>
        </transition>

        <button @click="toggleChat" class="fixed bottom-24 md:bottom-6 right-4 md:right-6 z-50 group flex items-center justify-center w-12 h-12 bg-blue-600 text-white rounded-full shadow-lg hover:scale-110 transition-all focus:ring-4 focus:ring-blue-500/30">
            <span v-if="!isOpen" class="group-hover:rotate-12 transition-transform">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path></svg>
            </span>
            <span v-else class="rotate-90 transition-transform">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </span>
        </button>
    </div>
</template>