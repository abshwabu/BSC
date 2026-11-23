<script setup>
import { ref, watch } from 'vue';
import axios from 'axios';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    report: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['saved', 'save-status']);

const { t } = useI18n();

const plcGateEnabled = ref(Boolean(props.report.decision?.plc_gate_enabled));
const minPayingCustomers = ref(props.report.decision?.min_paying_customers ?? 20);
const minMrr = ref(props.report.decision?.min_mrr ?? 150000);
const decisionTextAm = ref(props.report.decision?.decision_text_am || '');
const expansionTextAm = ref(props.report.decision?.expansion_text_am || '');

const saveStatus = ref('saved'); // 'saved' | 'saving' | 'unsaved' | 'error'

let debounceTimer = null;

function triggerAutosave() {
    saveStatus.value = 'unsaved';
    emit('save-status', 'unsaved');
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        saveNow();
    }, 800);
}

async function saveNow() {
    if (debounceTimer) clearTimeout(debounceTimer);
    saveStatus.value = 'saving';
    emit('save-status', 'saving');

    try {
        const payload = {
            plc_gate_enabled: plcGateEnabled.value,
            min_paying_customers: minPayingCustomers.value !== '' ? parseInt(minPayingCustomers.value, 10) : null,
            min_mrr: minMrr.value !== '' ? parseFloat(minMrr.value) : null,
            decision_text_am: decisionTextAm.value,
            expansion_text_am: expansionTextAm.value,
        };

        const res = await axios.patch(route('reports.decision.update', props.report.id), payload);
        if (res.data?.success) {
            saveStatus.value = 'saved';
            emit('save-status', 'saved');
            emit('saved', res.data.decision);
        }
    } catch (err) {
        console.error('Decision save error:', err);
        saveStatus.value = 'error';
        emit('save-status', 'error');
    }
}
</script>

<template>
    <div class="space-y-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 space-y-6">

            <!-- Step Header & Save Status -->
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-gray-100 dark:border-gray-700 pb-4">
                <div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <span>🏢</span>
                        <span>{{ $t('decision_step.title') }}</span>
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        {{ $t('decision_step.subtitle') }}
                    </p>
                </div>

                <!-- Autosave indicator -->
                <div class="flex items-center gap-2">
                    <span
                        v-if="saveStatus === 'saved'"
                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800"
                    >
                        <span>✓</span>
                        <span>{{ $t('member_step.autosave_status.saved') }}</span>
                    </span>
                    <span
                        v-else-if="saveStatus === 'saving'"
                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400 border border-blue-200 dark:border-blue-800 animate-pulse"
                    >
                        <span class="animate-spin">⏳</span>
                        <span>{{ $t('member_step.autosave_status.saving') }}</span>
                    </span>
                    <span
                        v-else-if="saveStatus === 'unsaved'"
                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400 border border-amber-200 dark:border-amber-800"
                    >
                        <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                        <span>{{ $t('member_step.autosave_status.unsaved') }}</span>
                    </span>

                    <button
                        type="button"
                        @click="saveNow"
                        class="px-2.5 py-1 text-xs font-medium text-gray-600 hover:text-indigo-600 underline"
                    >
                        {{ $t('member_step.save_now') }}
                    </button>
                </div>
            </div>

            <!-- PLC Transition Gate Switch -->
            <div class="p-4 rounded-xl bg-indigo-50/50 dark:bg-gray-900/40 border border-indigo-100 dark:border-gray-700 space-y-4">
                <div class="flex items-start justify-between">
                    <div>
                        <label class="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-2 cursor-pointer">
                            <input
                                type="checkbox"
                                v-model="plcGateEnabled"
                                @change="triggerAutosave"
                                class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500 h-4 w-4"
                            />
                            <span>{{ $t('decision_step.plc_gate_enabled') }}</span>
                        </label>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 pl-6">
                            {{ $t('decision_step.plc_gate_help') }}
                        </p>
                    </div>
                </div>

                <!-- Benchmarks -->
                <div v-if="plcGateEnabled" class="grid grid-cols-1 sm:grid-cols-2 gap-4 pt-3 border-t border-indigo-100 dark:border-gray-700">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">
                            {{ $t('decision_step.min_paying_customers') }}
                        </label>
                        <input
                            type="number"
                            min="0"
                            v-model="minPayingCustomers"
                            @input="triggerAutosave"
                            class="block w-full text-xs rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">
                            {{ $t('decision_step.min_mrr') }}
                        </label>
                        <input
                            type="number"
                            step="any"
                            min="0"
                            v-model="minMrr"
                            @input="triggerAutosave"
                            class="block w-full text-xs rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>
                </div>
            </div>

            <!-- Decision Text -->
            <div>
                <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1.5">
                    {{ $t('decision_step.decision_text') }}
                </label>
                <textarea
                    v-model="decisionTextAm"
                    @input="triggerAutosave"
                    rows="4"
                    class="block w-full text-xs rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                    :placeholder="$t('decision_step.decision_text_placeholder')"
                ></textarea>
            </div>

            <!-- Expansion Text -->
            <div>
                <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1.5">
                    {{ $t('decision_step.expansion_text') }}
                </label>
                <textarea
                    v-model="expansionTextAm"
                    @input="triggerAutosave"
                    rows="4"
                    class="block w-full text-xs rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                    :placeholder="$t('decision_step.expansion_text_placeholder')"
                ></textarea>
            </div>

        </div>
    </div>
</template>
