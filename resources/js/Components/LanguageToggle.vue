<script setup>
import { useI18n } from 'vue-i18n';
import { setLocale } from '@/i18n';

const { locale } = useI18n();

function toggleLanguage() {
    const nextLocale = locale.value === 'am' ? 'en' : 'am';
    setLocale(nextLocale);
}
</script>

<template>
    <button
        type="button"
        @click="toggleLanguage"
        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 shadow-sm transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500"
        :title="locale === 'am' ? 'Switch to English' : 'ወደ አማርኛ ቀይር'"
    >
        <span class="text-sm">🌐</span>
        <span :class="locale === 'am' ? 'font-bold text-indigo-600 dark:text-indigo-400' : 'text-gray-500'">አማ</span>
        <span class="text-gray-300 dark:text-gray-600">/</span>
        <span :class="locale === 'en' ? 'font-bold text-indigo-600 dark:text-indigo-400' : 'text-gray-500'">EN</span>
    </button>
</template>
