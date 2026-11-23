<script setup>
import { ref, onMounted, onUnmounted, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';

const page = usePage();
const notifications = ref([]);

function addToast(type, message, duration = 4000) {
    if (!message) return;
    const id = Date.now() + Math.random();
    notifications.value.push({ id, type, message });

    setTimeout(() => {
        removeToast(id);
    }, duration);
}

function removeToast(id) {
    notifications.value = notifications.value.filter(n => n.id !== id);
}

// Watch Inertia flash props
watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.success) {
            addToast('success', flash.success);
        }
        if (flash?.error) {
            addToast('error', flash.error);
        }
    },
    { deep: true }
);

function handleCustomToast(event) {
    const detail = event.detail || {};
    addToast(detail.type || 'info', detail.message, detail.duration || 4000);
}

onMounted(() => {
    // Check initial flash
    if (page.props.flash?.success) {
        addToast('success', page.props.flash.success);
    }
    if (page.props.flash?.error) {
        addToast('error', page.props.flash.error);
    }

    window.addEventListener('bsc-toast', handleCustomToast);
});

onUnmounted(() => {
    window.removeEventListener('bsc-toast', handleCustomToast);
});
</script>

<template>
    <div
        aria-live="polite"
        class="fixed inset-0 z-50 pointer-events-none flex flex-col items-end px-4 py-6 sm:p-6 space-y-3"
    >
        <transition-group
            enter-active-class="transform ease-out duration-300 transition"
            enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
            enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
            leave-active-class="transition ease-in duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-for="item in notifications"
                :key="item.id"
                class="pointer-events-auto max-w-sm w-full bg-white dark:bg-gray-800 shadow-xl rounded-xl border p-4 flex items-start gap-3 transition-all"
                :class="{
                    'border-emerald-200 dark:border-emerald-800': item.type === 'success',
                    'border-rose-200 dark:border-rose-800': item.type === 'error',
                    'border-blue-200 dark:border-blue-800': item.type === 'info',
                }"
            >
                <!-- Icon -->
                <div class="shrink-0 text-xl">
                    <span v-if="item.type === 'success'" class="text-emerald-500">✅</span>
                    <span v-else-if="item.type === 'error'" class="text-rose-500">❌</span>
                    <span v-else class="text-blue-500">ℹ️</span>
                </div>

                <!-- Text -->
                <div class="flex-1 text-xs">
                    <p class="font-bold text-gray-900 dark:text-white">
                        <span v-if="item.type === 'success'">ተሳክቷል / Success</span>
                        <span v-else-if="item.type === 'error'">ስህተት / Error</span>
                        <span v-else>ማሳሰቢያ / Info</span>
                    </p>
                    <p class="text-gray-600 dark:text-gray-300 mt-0.5 leading-relaxed font-sans">
                        {{ item.message }}
                    </p>
                </div>

                <!-- Close button -->
                <button
                    type="button"
                    @click="removeToast(item.id)"
                    class="shrink-0 text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 p-0.5"
                >
                    ✕
                </button>
            </div>
        </transition-group>
    </div>
</template>
