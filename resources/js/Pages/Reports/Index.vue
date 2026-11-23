<script setup>
import { ref } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import Modal from '@/Components/Modal.vue';
import InputLabel from '@/Components/InputLabel.vue';
import TextInput from '@/Components/TextInput.vue';
import InputError from '@/Components/InputError.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import SecondaryButton from '@/Components/SecondaryButton.vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    reports: {
        type: Array,
        required: true,
    },
});

const { t, locale } = useI18n();

const showCreateModal = ref(false);
const showImportModal = ref(false);

const form = useForm({
    title: '',
    period_label: 'Q1',
    year: new Date().getFullYear(),
    report_date: new Date().toISOString().split('T')[0],
    company_name: 'EthioTech Solutions',
});

const importForm = useForm({
    json_file: null,
});

function handleFileSelect(event) {
    importForm.json_file = event.target.files[0] || null;
}

function submitImport() {
    importForm.post(route('reports.import-json'), {
        forceFormData: true,
        onSuccess: () => {
            showImportModal.value = false;
            importForm.reset();
        },
    });
}

function openCreateModal() {
    form.reset();
    form.title = `የ ${form.year} ${form.period_label} የ BSC አፈፃፀም ሪፖርት`;
    showCreateModal.value = true;
}

function onPeriodOrYearChange() {
    if (!form.title || form.title.includes('BSC')) {
        form.title = locale.value === 'am'
            ? `የ ${form.year} ${form.period_label} የ BSC አፈፃፀም ሪፖርት`
            : `${form.year} ${form.period_label} BSC Performance Report`;
    }
}

function submitCreate() {
    form.post(route('reports.store'), {
        onSuccess: () => {
            showCreateModal.value = false;
        },
    });
}

function duplicateReport(reportId) {
    if (confirm(t('reports.duplicate_next_quarter') + '?')) {
        router.post(route('reports.duplicate', reportId));
    }
}

function deleteReport(reportId) {
    if (confirm(t('reports.confirm_delete'))) {
        router.delete(route('reports.destroy', reportId));
    }
}
</script>

<template>
    <Head :title="$t('reports.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold leading-tight text-gray-900 dark:text-white">
                        {{ $t('reports.title') }}
                    </h2>
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                        {{ $t('reports.subtitle') }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <SecondaryButton @click="showImportModal = true" class="gap-1.5 py-2.5 px-3 text-xs font-semibold">
                        <span>📥</span>
                        <span>{{ $t('json_sync.import_json') }}</span>
                    </SecondaryButton>
                    <PrimaryButton @click="openCreateModal" class="gap-2 shadow-sm py-2.5 px-4 text-xs font-semibold">
                        <span class="text-base leading-none">➕</span>
                        <span>{{ $t('reports.new_report') }}</span>
                    </PrimaryButton>
                </div>
            </div>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">

                <!-- Reports Table Card -->
                <div class="bg-white dark:bg-gray-800 shadow rounded-xl overflow-hidden border border-gray-200 dark:border-gray-700">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm text-left">
                            <thead class="bg-gray-50 dark:bg-gray-700/50 text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-300">
                                <tr>
                                    <th class="py-3.5 px-4">{{ $t('reports.col_title') }}</th>
                                    <th class="py-3.5 px-4">{{ $t('reports.col_period') }}</th>
                                    <th class="py-3.5 px-4">{{ $t('reports.col_date') }}</th>
                                    <th class="py-3.5 px-4">{{ $t('reports.col_status') }}</th>
                                    <th class="py-3.5 px-4 text-center">{{ $t('reports.col_company_score') }}</th>
                                    <th class="py-3.5 px-4 text-right">{{ $t('reports.col_actions') }}</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60 bg-white dark:bg-gray-800">
                                <tr
                                    v-for="report in reports"
                                    :key="report.id"
                                    class="hover:bg-gray-50/80 dark:hover:bg-gray-700/30 transition-colors"
                                >
                                    <!-- Title & Company -->
                                    <td class="py-4 px-4 font-medium text-gray-900 dark:text-white">
                                        <div class="flex items-center gap-2">
                                            <span class="text-xl">📄</span>
                                            <div>
                                                <Link
                                                    :href="route('reports.edit', report.id)"
                                                    class="font-semibold text-indigo-600 dark:text-indigo-400 hover:underline"
                                                >
                                                    {{ report.title }}
                                                </Link>
                                                <div class="text-xs text-gray-400 dark:text-gray-500">
                                                    {{ report.company_name }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Period & Year -->
                                    <td class="py-4 px-4 whitespace-nowrap">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-50 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
                                            {{ report.period_label }} - {{ report.year }}
                                        </span>
                                    </td>

                                    <!-- Date -->
                                    <td class="py-4 px-4 whitespace-nowrap text-gray-600 dark:text-gray-300">
                                        {{ report.report_date || '—' }}
                                    </td>

                                    <!-- Status -->
                                    <td class="py-4 px-4 whitespace-nowrap">
                                        <span
                                            v-if="report.status === 'final'"
                                            class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300"
                                        >
                                            <span>✓</span>
                                            <span>{{ $t('reports.final') }}</span>
                                        </span>
                                        <span
                                            v-else
                                            class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300"
                                        >
                                            <span>✎</span>
                                            <span>{{ $t('reports.draft') }}</span>
                                        </span>
                                    </td>

                                    <!-- Company Score -->
                                    <td class="py-4 px-4 text-center whitespace-nowrap">
                                        <div v-if="report.company_score > 0" class="inline-flex flex-col items-center">
                                            <div class="inline-flex items-center gap-1 font-bold text-gray-900 dark:text-white">
                                                <span class="text-base text-indigo-600 dark:text-indigo-400">{{ report.company_score }}</span>
                                                <span class="text-xs text-gray-400">/ 5.0</span>
                                                <span class="text-xs font-medium text-gray-500">({{ report.company_percentage }}%)</span>
                                            </div>
                                            <span class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                                                {{ locale === 'en' ? report.company_rating?.label_en : report.company_rating?.label_am }}
                                            </span>
                                        </div>
                                        <div v-else class="text-xs text-gray-400 italic">
                                            —
                                        </div>
                                    </td>

                                    <!-- Actions -->
                                    <td class="py-4 px-4 text-right whitespace-nowrap">
                                        <div class="flex items-center justify-end gap-2">
                                            <Link
                                                :href="route('reports.edit', report.id)"
                                                class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold rounded-md bg-indigo-50 hover:bg-indigo-100 text-indigo-700 dark:bg-indigo-900/50 dark:hover:bg-indigo-900 dark:text-indigo-200 transition-colors"
                                            >
                                                <span>✏️</span>
                                                <span>{{ $t('reports.open') }}</span>
                                            </Link>

                                            <a
                                                :href="route('reports.export-json', report.id)"
                                                class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-semibold rounded-md bg-gray-50 hover:bg-gray-100 text-gray-700 dark:bg-gray-700 dark:hover:bg-gray-600 dark:text-gray-200 border border-gray-200 dark:border-gray-600 transition-colors"
                                                :title="$t('json_sync.export_json')"
                                            >
                                                <span>💾</span>
                                                <span class="hidden xl:inline">JSON</span>
                                            </a>

                                            <button
                                                type="button"
                                                @click="duplicateReport(report.id)"
                                                class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold rounded-md bg-gray-100 hover:bg-gray-200 text-gray-700 dark:bg-gray-700 dark:hover:bg-gray-600 dark:text-gray-200 transition-colors"
                                                :title="$t('reports.duplicate_next_quarter')"
                                            >
                                                <span>📋</span>
                                                <span class="hidden md:inline">{{ $t('reports.duplicate_next_quarter') }}</span>
                                            </button>

                                            <button
                                                type="button"
                                                @click="deleteReport(report.id)"
                                                class="inline-flex items-center p-1.5 text-xs rounded-md text-red-600 hover:bg-red-50 dark:text-red-400 dark:hover:bg-red-900/30 transition-colors"
                                                :title="$t('reports.delete')"
                                            >
                                                <span>🗑️</span>
                                            </button>
                                        </div>
                                    </td>
                                </tr>

                                <tr v-if="reports.length === 0">
                                    <td colspan="6" class="py-12 text-center text-gray-500 dark:text-gray-400">
                                        <div class="flex flex-col items-center justify-center gap-2">
                                            <span class="text-4xl">📈</span>
                                            <p class="font-medium text-base">{{ $t('reports.no_reports') }}</p>
                                            <PrimaryButton @click="openCreateModal" class="mt-2 text-xs">
                                                {{ $t('reports.new_report') }}
                                            </PrimaryButton>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>

        <!-- Create Report Modal -->
        <Modal :show="showCreateModal" @close="showCreateModal = false">
            <div class="p-6">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white">
                    {{ $t('new_report_modal.title') }}
                </h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ $t('new_report_modal.description') }}
                </p>

                <form @submit.prevent="submitCreate" class="mt-6 space-y-4">
                    <!-- Title -->
                    <div>
                        <InputLabel for="report_title" :value="$t('new_report_modal.report_title')" />
                        <TextInput
                            id="report_title"
                            type="text"
                            v-model="form.title"
                            class="mt-1 block w-full"
                            :placeholder="$t('new_report_modal.report_title_placeholder')"
                            required
                        />
                        <InputError :message="form.errors.title" class="mt-1" />
                    </div>

                    <!-- Period & Year in 2 columns -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="period_label" :value="$t('new_report_modal.period_label')" />
                            <select
                                id="period_label"
                                v-model="form.period_label"
                                @change="onPeriodOrYearChange"
                                class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                            >
                                <option value="Q1">Q1 (1ኛ ሩብ ዓመት)</option>
                                <option value="Q2">Q2 (2ኛ ሩብ ዓመት)</option>
                                <option value="Q3">Q3 (3ኛ ሩብ ዓመት)</option>
                                <option value="Q4">Q4 (4ኛ ሩብ ዓመት)</option>
                            </select>
                            <InputError :message="form.errors.period_label" class="mt-1" />
                        </div>

                        <div>
                            <InputLabel for="year" :value="$t('new_report_modal.year')" />
                            <TextInput
                                id="year"
                                type="number"
                                v-model="form.year"
                                @input="onPeriodOrYearChange"
                                class="mt-1 block w-full"
                                required
                            />
                            <InputError :message="form.errors.year" class="mt-1" />
                        </div>
                    </div>

                    <!-- Report Date & Company Name -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <InputLabel for="report_date" :value="$t('new_report_modal.report_date')" />
                            <TextInput
                                id="report_date"
                                type="date"
                                v-model="form.report_date"
                                class="mt-1 block w-full"
                            />
                            <InputError :message="form.errors.report_date" class="mt-1" />
                        </div>

                        <div>
                            <InputLabel for="company_name" :value="$t('new_report_modal.company_name')" />
                            <TextInput
                                id="company_name"
                                type="text"
                                v-model="form.company_name"
                                class="mt-1 block w-full"
                                :placeholder="$t('new_report_modal.company_name_placeholder')"
                            />
                            <InputError :message="form.errors.company_name" class="mt-1" />
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="mt-6 flex justify-end gap-3">
                        <SecondaryButton @click="showCreateModal = false" :disabled="form.processing">
                            {{ $t('new_report_modal.cancel') }}
                        </SecondaryButton>
                        <PrimaryButton :disabled="form.processing">
                            {{ form.processing ? $t('new_report_modal.creating') : $t('new_report_modal.create') }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>

        <!-- Import JSON Modal -->
        <Modal :show="showImportModal" @close="showImportModal = false" max-width="md">
            <div class="p-6">
                <div class="flex items-center gap-2 border-b border-gray-100 dark:border-gray-700 pb-3">
                    <span class="text-2xl">📥</span>
                    <div>
                        <h3 class="text-base font-bold text-gray-900 dark:text-white">
                            {{ $t('json_sync.modal_title') }}
                        </h3>
                        <p class="text-xs text-gray-500 mt-0.5">
                            {{ $t('json_sync.modal_desc') }}
                        </p>
                    </div>
                </div>

                <form @submit.prevent="submitImport" class="mt-4 space-y-4">
                    <div>
                        <InputLabel for="json_file" :value="$t('json_sync.file_label')" />
                        <input
                            id="json_file"
                            type="file"
                            accept=".json,application/json"
                            @change="handleFileSelect"
                            class="mt-1 block w-full text-xs text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 dark:text-gray-400 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400 p-2"
                            required
                        />
                        <InputError :message="importForm.errors.json_file" class="mt-1" />
                    </div>

                    <div class="mt-6 flex justify-end gap-3">
                        <SecondaryButton @click="showImportModal = false" :disabled="importForm.processing">
                            {{ $t('json_sync.cancel') }}
                        </SecondaryButton>
                        <PrimaryButton :disabled="importForm.processing || !importForm.json_file">
                            {{ importForm.processing ? 'በማስመጣት ላይ...' : $t('json_sync.submit_import') }}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </Modal>
    </AuthenticatedLayout>
</template>
