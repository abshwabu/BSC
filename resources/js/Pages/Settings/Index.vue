<script setup>
import { ref } from 'vue';
import { Head, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useI18n } from 'vue-i18n';
import { setLocale } from '@/i18n';

const props = defineProps({
    settings: {
        type: Object,
        required: true,
    },
});

const { t } = useI18n();

const form = useForm({
    company_name: props.settings.company_name || 'EthioTech Solutions',
    cap_percentage: props.settings.cap_percentage ?? 120,
    default_language: props.settings.default_language || 'am',
    threshold_outstanding: props.settings.threshold_outstanding ?? 90,
    threshold_exceeds: props.settings.threshold_exceeds ?? 75,
    threshold_meets: props.settings.threshold_meets ?? 60,
    logo: null,
    remove_logo: false,
});

const logoPreview = ref(props.settings.company_logo || null);

function handleLogoChange(event) {
    const file = event.target.files[0];
    if (file) {
        form.logo = file;
        form.remove_logo = false;
        const reader = new FileReader();
        reader.onload = (e) => {
            logoPreview.value = e.target.result;
        };
        reader.readAsDataURL(file);
    }
}

function removeLogo() {
    form.logo = null;
    form.remove_logo = true;
    logoPreview.value = null;
}

function submitSettings() {
    form.post(route('settings.update'), {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            if (form.default_language) {
                setLocale(form.default_language);
            }
        },
    });
}
</script>

<template>
    <Head :title="$t('settings_page.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-gray-800 dark:text-gray-100 flex items-center gap-2">
                        <span class="text-2xl">⚙️</span>
                        <span>{{ $t('settings_page.title') }}</span>
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        {{ $t('settings_page.subtitle') }}
                    </p>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <form @submit.prevent="submitSettings" class="space-y-6">
                    <!-- Company Profile Block -->
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 space-y-5">
                        <div class="border-b border-gray-100 dark:border-gray-700 pb-3 flex items-center gap-2">
                            <span class="text-lg">🏢</span>
                            <h3 class="font-bold text-gray-900 dark:text-white text-base">
                                {{ $t('settings_page.company_info') }}
                            </h3>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-5 items-start">
                            <div>
                                <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">
                                    {{ $t('settings_page.company_name') }}
                                </label>
                                <input
                                    type="text"
                                    v-model="form.company_name"
                                    class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white py-2"
                                    required
                                />
                                <span v-if="form.errors.company_name" class="text-xs text-rose-500 mt-1 block">
                                    {{ form.errors.company_name }}
                                </span>
                            </div>

                            <!-- Logo Upload -->
                            <div>
                                <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">
                                    {{ $t('settings_page.company_logo') }}
                                </label>
                                <div class="flex items-center gap-4">
                                    <div
                                        class="w-20 h-20 rounded-xl border border-dashed border-gray-300 dark:border-gray-600 flex items-center justify-center bg-gray-50 dark:bg-gray-900 overflow-hidden relative"
                                    >
                                        <img
                                            v-if="logoPreview"
                                            :src="logoPreview"
                                            alt="Company Logo Preview"
                                            class="w-full h-full object-contain p-1"
                                        />
                                        <span v-else class="text-gray-400 text-xs text-center px-1">ምንም አርማ የለም</span>
                                    </div>

                                    <div class="space-y-2">
                                        <label
                                            class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-indigo-50 text-indigo-700 dark:bg-indigo-950/40 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 cursor-pointer hover:bg-indigo-100 transition-colors"
                                        >
                                            <span>📷</span>
                                            <span>{{ $t('settings_page.upload_logo') }}</span>
                                            <input
                                                type="file"
                                                accept="image/*"
                                                @change="handleLogoChange"
                                                class="hidden"
                                            />
                                        </label>

                                        <button
                                            v-if="logoPreview"
                                            type="button"
                                            @click="removeLogo"
                                            class="block text-[11px] font-semibold text-rose-600 hover:underline"
                                        >
                                            {{ $t('settings_page.remove_logo') }}
                                        </button>
                                    </div>
                                </div>
                                <span v-if="form.errors.logo" class="text-xs text-rose-500 mt-1 block">
                                    {{ form.errors.logo }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Scoring Engine Configuration Block -->
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 space-y-5">
                        <div class="border-b border-gray-100 dark:border-gray-700 pb-3 flex items-center gap-2">
                            <span class="text-lg">🧮</span>
                            <h3 class="font-bold text-gray-900 dark:text-white text-base">
                                {{ $t('settings_page.scoring_config') }}
                            </h3>
                        </div>

                        <div class="space-y-4">
                            <!-- Cap Percentage -->
                            <div class="max-w-xs">
                                <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">
                                    {{ $t('settings_page.cap_percentage') }}
                                </label>
                                <div class="flex items-center gap-2">
                                    <input
                                        type="number"
                                        step="1"
                                        min="50"
                                        max="200"
                                        v-model.number="form.cap_percentage"
                                        class="w-32 text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white py-2"
                                        required
                                    />
                                    <span class="text-xs text-gray-500">%</span>
                                </div>
                                <p class="text-[11px] text-gray-400 mt-1">
                                    {{ $t('settings_page.cap_percentage_help') }}
                                </p>
                                <span v-if="form.errors.cap_percentage" class="text-xs text-rose-500 mt-1 block">
                                    {{ form.errors.cap_percentage }}
                                </span>
                            </div>

                            <!-- Score Bands / Thresholds -->
                            <div class="pt-3 border-t border-gray-100 dark:border-gray-700 space-y-3">
                                <h4 class="text-xs font-bold text-gray-800 dark:text-gray-200 uppercase tracking-wider">
                                    {{ $t('settings_page.breakpoints_title') }}
                                </h4>

                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    <div class="p-3 rounded-xl bg-emerald-50/70 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-800/50 space-y-1">
                                        <label class="block text-xs font-bold text-emerald-800 dark:text-emerald-300">
                                            🌟 {{ $t('settings_page.outstanding_threshold') }}
                                        </label>
                                        <div class="flex items-center gap-1.5">
                                            <input
                                                type="number"
                                                step="1"
                                                v-model.number="form.threshold_outstanding"
                                                class="w-full text-xs rounded-md border-emerald-300 dark:border-emerald-700 dark:bg-gray-900 dark:text-white py-1.5"
                                            />
                                            <span class="text-xs font-bold text-emerald-700">%</span>
                                        </div>
                                        <span class="text-[10px] text-emerald-600 dark:text-emerald-400 block">4.5 - 5.0 ነጥብ</span>
                                    </div>

                                    <div class="p-3 rounded-xl bg-blue-50/70 dark:bg-blue-950/20 border border-blue-200 dark:border-blue-800/50 space-y-1">
                                        <label class="block text-xs font-bold text-blue-800 dark:text-blue-300">
                                            📈 {{ $t('settings_page.exceeds_threshold') }}
                                        </label>
                                        <div class="flex items-center gap-1.5">
                                            <input
                                                type="number"
                                                step="1"
                                                v-model.number="form.threshold_exceeds"
                                                class="w-full text-xs rounded-md border-blue-300 dark:border-blue-700 dark:bg-gray-900 dark:text-white py-1.5"
                                            />
                                            <span class="text-xs font-bold text-blue-700">%</span>
                                        </div>
                                        <span class="text-[10px] text-blue-600 dark:text-blue-400 block">4.0 - 4.4 ነጥብ</span>
                                    </div>

                                    <div class="p-3 rounded-xl bg-amber-50/70 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-800/50 space-y-1">
                                        <label class="block text-xs font-bold text-amber-800 dark:text-amber-300">
                                            ⚖️ {{ $t('settings_page.meets_threshold') }}
                                        </label>
                                        <div class="flex items-center gap-1.5">
                                            <input
                                                type="number"
                                                step="1"
                                                v-model.number="form.threshold_meets"
                                                class="w-full text-xs rounded-md border-amber-300 dark:border-amber-700 dark:bg-gray-900 dark:text-white py-1.5"
                                            />
                                            <span class="text-xs font-bold text-amber-700">%</span>
                                        </div>
                                        <span class="text-[10px] text-amber-600 dark:text-amber-400 block">3.0 - 3.9 ነጥብ</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Localization Preference Block -->
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 space-y-4">
                        <div class="border-b border-gray-100 dark:border-gray-700 pb-3 flex items-center gap-2">
                            <span class="text-lg">🌐</span>
                            <h3 class="font-bold text-gray-900 dark:text-white text-base">
                                {{ $t('settings_page.localization') }}
                            </h3>
                        </div>

                        <div class="max-w-xs">
                            <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">
                                {{ $t('settings_page.default_language') }}
                            </label>
                            <select
                                v-model="form.default_language"
                                class="w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white py-2"
                            >
                                <option value="am">አማርኛ (Amharic - Default)</option>
                                <option value="en">English</option>
                            </select>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="flex items-center justify-end gap-3 pt-2">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="inline-flex items-center gap-2 px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-md hover:shadow-lg transition-all disabled:opacity-50"
                        >
                            <span>💾</span>
                            <span>{{ form.processing ? 'በማስቀመጥ ላይ...' : $t('settings_page.save_changes') }}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
