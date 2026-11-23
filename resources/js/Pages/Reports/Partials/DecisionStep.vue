<script setup>
import { ref, computed, watch } from 'vue';
import axios from 'axios';
import { useI18n } from 'vue-i18n';
import {
    calculateMemberSummary,
    calculateCompanySummary,
    evaluateGate,
    generateDecisionTemplate,
    DEFAULT_EXECUTIVE_DECISIONS,
    roundTo,
} from '@/lib/bsc';

const props = defineProps({
    report: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['saved', 'save-status']);

const { t, locale } = useI18n();

// 1. Reactive form state for report decision
const plcGateEnabled = ref(Boolean(props.report.decision?.plc_gate_enabled));
const minPayingCustomers = ref(props.report.decision?.min_paying_customers ?? 20);
const minMrr = ref(props.report.decision?.min_mrr ?? 150000);
const actualPayingCustomers = ref(props.report.decision?.actual_paying_customers ?? 0);
const actualMrr = ref(props.report.decision?.actual_mrr ?? 0);
const decisionTextAm = ref(props.report.decision?.decision_text_am || '');
const expansionTextAm = ref(props.report.decision?.expansion_text_am || '');

// Executive decisions list
const defaultDecisions = locale.value === 'en'
    ? DEFAULT_EXECUTIVE_DECISIONS.en
    : DEFAULT_EXECUTIVE_DECISIONS.am;

const decisionsList = ref(
    Array.isArray(props.report.decision?.decisions_list) && props.report.decision.decisions_list.length > 0
        ? [...props.report.decision.decisions_list]
        : [...defaultDecisions]
);

// Member readiness notes map: { [memberId]: note }
const readinessNotes = ref({});
(props.report.reportMembers || []).forEach((rm) => {
    readinessNotes.value[rm.id] = rm.readiness_note || '';
});

// Collapsible scoring legend state
const showScoringLegend = ref(false);

const saveStatus = ref('saved'); // 'saved' | 'saving' | 'unsaved' | 'error'

let debounceTimer = null;
const memberDebounceTimers = {};

// 2. Computed Live Member & Company Summaries
const memberSummaries = computed(() => {
    return (props.report.reportMembers || []).map((rm) => {
        const summary = calculateMemberSummary(rm.activities || [], rm.weights || {});
        return {
            reportMember: rm,
            summary,
        };
    });
});

const companySummary = computed(() => {
    const rawSummaries = memberSummaries.value.map((m) => m.summary);
    return calculateCompanySummary(rawSummaries);
});

// 3. Computed Gate Evaluation (live reactive)
const gateEvaluation = computed(() => {
    return evaluateGate(
        plcGateEnabled.value,
        minPayingCustomers.value,
        minMrr.value,
        actualPayingCustomers.value,
        actualMrr.value
    );
});

// Rating chip styling helper
function getRatingChipClasses(ratingKey) {
    switch (ratingKey) {
        case 'outstanding':
            return 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-300 border-emerald-300 dark:border-emerald-700';
        case 'exceeds_target':
            return 'bg-blue-50 text-blue-800 dark:bg-blue-950/50 dark:text-blue-300 border-blue-300 dark:border-blue-700';
        case 'meets_target':
            return 'bg-amber-50 text-amber-800 dark:bg-amber-950/50 dark:text-amber-300 border-amber-300 dark:border-amber-700';
        case 'needs_improvement':
            return 'bg-rose-50 text-rose-800 dark:bg-rose-950/50 dark:text-rose-300 border-rose-300 dark:border-rose-700';
        default:
            return 'bg-gray-50 text-gray-700 dark:bg-gray-800 dark:text-gray-300 border-gray-300 dark:border-gray-600';
    }
}

function getRatingBarColor(ratingKey) {
    switch (ratingKey) {
        case 'outstanding':
            return 'bg-emerald-500';
        case 'exceeds_target':
            return 'bg-blue-500';
        case 'meets_target':
            return 'bg-amber-500';
        case 'needs_improvement':
            return 'bg-rose-500';
        default:
            return 'bg-indigo-500';
    }
}

// 4. Autosave logic
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
            min_paying_customers: minPayingCustomers.value !== '' && minPayingCustomers.value !== null ? parseInt(minPayingCustomers.value, 10) : null,
            min_mrr: minMrr.value !== '' && minMrr.value !== null ? parseFloat(minMrr.value) : null,
            actual_paying_customers: actualPayingCustomers.value !== '' && actualPayingCustomers.value !== null ? parseInt(actualPayingCustomers.value, 10) : null,
            actual_mrr: actualMrr.value !== '' && actualMrr.value !== null ? parseFloat(actualMrr.value) : null,
            decision_text_am: decisionTextAm.value,
            expansion_text_am: expansionTextAm.value,
            decisions_list: decisionsList.value.filter((d) => d && d.trim() !== ''),
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

// Member readiness note autosave
function onReadinessNoteChange(reportMemberId) {
    saveStatus.value = 'unsaved';
    emit('save-status', 'unsaved');

    if (memberDebounceTimers[reportMemberId]) {
        clearTimeout(memberDebounceTimers[reportMemberId]);
    }

    memberDebounceTimers[reportMemberId] = setTimeout(async () => {
        try {
            const note = readinessNotes.value[reportMemberId] || '';
            await axios.patch(route('reports.members.update', [props.report.id, reportMemberId]), {
                readiness_note: note,
            });
            saveStatus.value = 'saved';
            emit('save-status', 'saved');
        } catch (err) {
            console.error('Failed to update member readiness note:', err);
            saveStatus.value = 'error';
            emit('save-status', 'error');
        }
    }, 800);
}

// Pre-fill decision template from criteria
function generateDecisionFromGate() {
    const isMet = gateEvaluation.value.is_met;
    const minCust = minPayingCustomers.value || 0;
    const minRevenue = minMrr.value || 0;
    const actCust = actualPayingCustomers.value || 0;
    const actRevenue = actualMrr.value || 0;

    decisionTextAm.value = generateDecisionTemplate(
        isMet,
        minCust,
        minRevenue,
        actCust,
        actRevenue,
        locale.value === 'en' ? 'en' : 'am'
    );
    triggerAutosave();
}

// Decisions list actions
function addDecisionItem() {
    decisionsList.value.push('');
    triggerAutosave();
}

function removeDecisionItem(index) {
    decisionsList.value.splice(index, 1);
    triggerAutosave();
}

function moveDecisionUp(index) {
    if (index > 0) {
        const item = decisionsList.value.splice(index, 1)[0];
        decisionsList.value.splice(index - 1, 0, item);
        triggerAutosave();
    }
}

function moveDecisionDown(index) {
    if (index < decisionsList.value.length - 1) {
        const item = decisionsList.value.splice(index, 1)[0];
        decisionsList.value.splice(index + 1, 0, item);
        triggerAutosave();
    }
}

function resetDefaultDecisions() {
    decisionsList.value = [...defaultDecisions];
    triggerAutosave();
}
</script>

<template>
    <div class="space-y-8">
        <!-- Main Card -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 space-y-8">

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
                    <span
                        v-else-if="saveStatus === 'error'"
                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400 border border-rose-200 dark:border-rose-800"
                    >
                        <span>✕</span>
                        <span>{{ $t('member_step.autosave_status.error') }}</span>
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

            <!-- 1. Consolidated Comparison Table (Live) -->
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <h4 class="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <span>📊</span>
                        <span>{{ $t('decision_step.comparison_table_title') }}</span>
                    </h4>
                    <span class="text-xs text-gray-500 dark:text-gray-400 font-medium">
                        {{ report.period_label }} - {{ report.year }}
                    </span>
                </div>

                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm">
                    <table class="w-full text-left border-collapse text-xs">
                        <thead>
                            <tr class="bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-bold border-b border-gray-200 dark:border-gray-700 uppercase tracking-wider text-[11px]">
                                <th class="p-3">{{ $t('decision_step.col_member') }}</th>
                                <th class="p-3 text-center w-28">{{ $t('decision_step.col_weighted_pct') }}</th>
                                <th class="p-3 text-center w-36">{{ $t('decision_step.col_bsc_score') }}</th>
                                <th class="p-3">{{ $t('decision_step.col_readiness') }}</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800 text-gray-800 dark:text-gray-200">
                            <!-- Member Rows -->
                            <tr
                                v-for="(item, idx) in memberSummaries"
                                :key="item.reportMember.id"
                                class="hover:bg-gray-50/60 dark:hover:bg-gray-750 transition-colors"
                            >
                                <td class="p-3 font-semibold">
                                    <div class="flex items-center gap-2">
                                        <span class="h-6 w-6 rounded-full bg-indigo-100 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 font-bold flex items-center justify-center text-xs">
                                            {{ idx + 1 }}
                                        </span>
                                        <div>
                                            <div class="text-gray-900 dark:text-white font-bold text-sm">
                                                {{ item.reportMember.member?.name }}
                                            </div>
                                            <div class="text-[11px] text-gray-500 dark:text-gray-400">
                                                {{ locale === 'en' ? item.reportMember.member?.role_title_en : item.reportMember.member?.role_title_am }}
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td class="p-3 text-center font-bold text-sm">
                                    {{ item.summary.weighted_percentage_rounded }}%
                                </td>

                                <td class="p-3 text-center">
                                    <div class="flex flex-col items-center gap-1">
                                        <span class="font-extrabold text-sm text-gray-900 dark:text-white">
                                            {{ item.summary.weighted_score_rounded }} <span class="text-[11px] font-normal text-gray-400">/ 5.0</span>
                                        </span>
                                        <span
                                            class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold border shadow-xs"
                                            :class="getRatingChipClasses(item.summary.rating.key)"
                                        >
                                            {{ locale === 'en' ? item.summary.rating.label_en : item.summary.rating.label_am }}
                                        </span>
                                    </div>
                                </td>

                                <td class="p-3">
                                    <textarea
                                        v-model="readinessNotes[item.reportMember.id]"
                                        @input="onReadinessNoteChange(item.reportMember.id)"
                                        rows="2"
                                        class="w-full text-xs rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                                        :placeholder="$t('decision_step.readiness_placeholder')"
                                    ></textarea>
                                </td>
                            </tr>

                            <!-- Company Average Row -->
                            <tr class="bg-indigo-50/70 dark:bg-indigo-950/40 font-bold border-t-2 border-indigo-200 dark:border-indigo-800">
                                <td class="p-3">
                                    <div class="flex items-center gap-2">
                                        <span class="text-indigo-600 dark:text-indigo-400 text-sm">★</span>
                                        <div>
                                            <div class="text-indigo-950 dark:text-indigo-100 font-extrabold text-sm">
                                                {{ $t('decision_step.company_average_label') }}
                                            </div>
                                            <div class="text-[10px] text-indigo-700 dark:text-indigo-300 font-medium">
                                                {{ $t('preview_step.company_score_badge') }}
                                            </div>
                                        </div>
                                    </div>
                                </td>

                                <td class="p-3 text-center text-sm font-black text-indigo-950 dark:text-indigo-100">
                                    {{ companySummary.company_weighted_percentage_rounded }}%
                                </td>

                                <td class="p-3 text-center">
                                    <div class="flex flex-col items-center gap-1">
                                        <span class="font-black text-sm text-indigo-950 dark:text-indigo-100">
                                            {{ companySummary.company_weighted_score_rounded }} <span class="text-[11px] font-normal text-indigo-400">/ 5.0</span>
                                        </span>
                                        <span
                                            class="inline-block px-2.5 py-0.5 rounded-full text-[10px] font-black border shadow-xs"
                                            :class="getRatingChipClasses(companySummary.rating.key)"
                                        >
                                            {{ locale === 'en' ? companySummary.rating.label_en : companySummary.rating.label_am }}
                                        </span>
                                    </div>
                                </td>

                                <td class="p-3 text-xs text-indigo-800 dark:text-indigo-200 italic font-normal">
                                    {{ $t('reports.subtitle') }}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 2. Mini Bar Chart (Pure SVG/Tailwind vs 5.0 Ceiling) -->
            <div class="p-4 rounded-xl bg-gray-50 dark:bg-gray-900/50 border border-gray-200 dark:border-gray-700 space-y-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h4 class="text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider flex items-center gap-1.5">
                            <span>📈</span>
                            <span>{{ $t('decision_step.chart_title') }}</span>
                        </h4>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400">
                            {{ $t('decision_step.chart_subtitle') }}
                        </p>
                    </div>

                    <!-- Company average badge -->
                    <span class="px-2.5 py-1 rounded-lg text-xs font-extrabold bg-indigo-100 dark:bg-indigo-900/60 text-indigo-800 dark:text-indigo-200 border border-indigo-200 dark:border-indigo-700">
                        Avg: {{ companySummary.company_weighted_score_rounded }} / 5.0
                    </span>
                </div>

                <!-- SVG and Bars Container -->
                <div class="space-y-3 pt-2">
                    <div
                        v-for="item in memberSummaries"
                        :key="'bar-' + item.reportMember.id"
                        class="space-y-1"
                    >
                        <div class="flex items-center justify-between text-xs">
                            <span class="font-bold text-gray-800 dark:text-gray-200 truncate max-w-[200px] sm:max-w-xs">
                                {{ item.reportMember.member?.name }}
                                <span class="text-gray-400 font-normal">({{ locale === 'en' ? item.reportMember.member?.role_title_en : item.reportMember.member?.role_title_am }})</span>
                            </span>
                            <span class="font-black text-gray-900 dark:text-white">
                                {{ item.summary.weighted_score_rounded }} <span class="text-[10px] font-normal text-gray-400">/ 5.0</span>
                                <span class="text-gray-400 font-normal text-[10px] ml-1">({{ item.summary.weighted_percentage_rounded }}%)</span>
                            </span>
                        </div>

                        <!-- Progress Bar Track -->
                        <div class="relative w-full h-5 bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden p-0.5">
                            <!-- 5.0 Max Marker -->
                            <div class="absolute right-0 top-0 bottom-0 w-0.5 bg-gray-400 z-10" title="5.0 Max Ceiling"></div>
                            <!-- Bar fill -->
                            <div
                                class="h-full rounded-full transition-all duration-500 ease-out flex items-center justify-end pr-2 text-[10px] font-black text-white"
                                :class="getRatingBarColor(item.summary.rating.key)"
                                :style="{ width: `${Math.min(100, Math.max(5, (item.summary.weighted_score / 5.0) * 100))}%` }"
                            >
                                <span v-if="(item.summary.weighted_score / 5.0) >= 0.25">{{ item.summary.weighted_score_rounded }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Benchmark Axis Indicators -->
                    <div class="flex justify-between text-[10px] text-gray-400 font-mono pt-1 px-0.5">
                        <span>0.0</span>
                        <span>1.5</span>
                        <span>3.0 (Meets)</span>
                        <span>4.0 (Exceeds)</span>
                        <span>4.5 (Outstanding)</span>
                        <span class="font-bold text-gray-600 dark:text-gray-300">5.0 (Ceiling)</span>
                    </div>
                </div>
            </div>

            <!-- 3. Company Readiness Gate (PLC Gate) -->
            <div class="p-5 rounded-xl bg-indigo-50/40 dark:bg-gray-900/40 border border-indigo-100 dark:border-gray-700 space-y-5">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
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
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 pl-6">
                            {{ $t('decision_step.plc_gate_help') }}
                        </p>
                    </div>

                    <!-- Instant Criteria Status Badge -->
                    <div>
                        <span
                            v-if="!plcGateEnabled"
                            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400 border border-gray-300 dark:border-gray-600"
                        >
                            <span>○</span>
                            <span>{{ $t('decision_step.gate_disabled_badge') }}</span>
                        </span>
                        <span
                            v-else-if="gateEvaluation.is_met"
                            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-700 shadow-sm"
                        >
                            <span>✓</span>
                            <span>{{ $t('decision_step.gate_met_badge') }}</span>
                        </span>
                        <span
                            v-else
                            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300 border border-rose-300 dark:border-rose-700 shadow-sm"
                        >
                            <span>✕</span>
                            <span>{{ $t('decision_step.gate_not_met_badge') }}</span>
                        </span>
                    </div>
                </div>

                <!-- 4 Input Fields: Targets & Actuals -->
                <div v-if="plcGateEnabled" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 pt-4 border-t border-indigo-100 dark:border-gray-700">
                    <!-- Target Customers -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">
                            {{ $t('decision_step.target_customers') }}
                        </label>
                        <input
                            type="number"
                            min="0"
                            v-model="minPayingCustomers"
                            @input="triggerAutosave"
                            class="block w-full text-xs rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>

                    <!-- Target MRR -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">
                            {{ $t('decision_step.target_mrr') }}
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

                    <!-- Actual Customers -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1 flex items-center justify-between">
                            <span>{{ $t('decision_step.actual_customers') }}</span>
                            <span
                                v-if="actualPayingCustomers >= minPayingCustomers"
                                class="text-[10px] text-emerald-600 font-bold"
                            >✓ Met</span>
                            <span v-else class="text-[10px] text-rose-600 font-bold">✕ Below</span>
                        </label>
                        <input
                            type="number"
                            min="0"
                            v-model="actualPayingCustomers"
                            @input="triggerAutosave"
                            class="block w-full text-xs rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>

                    <!-- Actual MRR -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1 flex items-center justify-between">
                            <span>{{ $t('decision_step.actual_mrr') }}</span>
                            <span
                                v-if="actualMrr >= minMrr"
                                class="text-[10px] text-emerald-600 font-bold"
                            >✓ Met</span>
                            <span v-else class="text-[10px] text-rose-600 font-bold">✕ Below</span>
                        </label>
                        <input
                            type="number"
                            step="any"
                            min="0"
                            v-model="actualMrr"
                            @input="triggerAutosave"
                            class="block w-full text-xs rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                        />
                    </div>
                </div>

                <!-- Pre-fill Decision Action Button -->
                <div v-if="plcGateEnabled" class="flex items-center justify-between pt-1">
                    <p class="text-[11px] text-gray-500 dark:text-gray-400">
                        {{ gateEvaluation.is_met ? '✓ ደንበኞች እና ወርሃዊ ገቢ መስፈርቱን አሟልተዋል።' : '⚠ ደንበኞች ወይም ወርሃዊ ገቢ መስፈርቱን አላሟሉም።' }}
                    </p>
                    <button
                        type="button"
                        @click="generateDecisionFromGate"
                        class="px-3 py-1.5 rounded-lg text-xs font-bold bg-indigo-600 hover:bg-indigo-700 text-white shadow-xs transition-colors flex items-center gap-1.5"
                    >
                        <span>✨</span>
                        <span>{{ $t('decision_step.generate_decision') }}</span>
                    </button>
                </div>

                <!-- Decision Text (Editable) -->
                <div>
                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1.5">
                        {{ $t('decision_step.decision_text') }}
                    </label>
                    <textarea
                        v-model="decisionTextAm"
                        @input="triggerAutosave"
                        rows="3"
                        class="block w-full text-xs rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                        :placeholder="$t('decision_step.decision_text_placeholder')"
                    ></textarea>
                </div>
            </div>

            <!-- 4. Executive Management Decisions (Numbered List: Add / Remove / Reorder) -->
            <div class="p-5 rounded-xl bg-gray-50 dark:bg-gray-900/40 border border-gray-200 dark:border-gray-700 space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-gray-200 dark:border-gray-700 pb-3">
                    <div>
                        <h4 class="text-xs font-bold text-gray-900 dark:text-white uppercase tracking-wider flex items-center gap-1.5">
                            <span>⚖️</span>
                            <span>{{ $t('decision_step.executive_decisions_title') }}</span>
                        </h4>
                        <p class="text-[11px] text-gray-500 dark:text-gray-400 mt-0.5">
                            {{ $t('decision_step.executive_decisions_subtitle') }}
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            @click="resetDefaultDecisions"
                            class="px-2.5 py-1 text-xs text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 underline"
                        >
                            {{ $t('decision_step.reset_default_decisions') }}
                        </button>
                        <button
                            type="button"
                            @click="addDecisionItem"
                            class="px-3 py-1.5 rounded-lg text-xs font-bold bg-indigo-50 text-indigo-700 hover:bg-indigo-100 dark:bg-indigo-900/40 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 transition-colors flex items-center gap-1"
                        >
                            <span>+</span>
                            <span>{{ $t('decision_step.add_decision') }}</span>
                        </button>
                    </div>
                </div>

                <!-- Numbered items list -->
                <div class="space-y-2">
                    <div
                        v-for="(dec, idx) in decisionsList"
                        :key="'dec-' + idx"
                        class="flex items-center gap-2 bg-white dark:bg-gray-800 p-2 rounded-lg border border-gray-200 dark:border-gray-700 shadow-xs"
                    >
                        <span class="h-6 w-6 rounded-full bg-indigo-100 dark:bg-indigo-900/60 text-indigo-700 dark:text-indigo-300 font-bold flex items-center justify-center text-xs shrink-0">
                            {{ idx + 1 }}
                        </span>

                        <input
                            type="text"
                            v-model="decisionsList[idx]"
                            @input="triggerAutosave"
                            class="flex-1 text-xs rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                            :placeholder="$t('decision_step.decision_placeholder')"
                        />

                        <!-- Reorder / Remove buttons -->
                        <div class="flex items-center gap-1 shrink-0">
                            <button
                                type="button"
                                @click="moveDecisionUp(idx)"
                                :disabled="idx === 0"
                                class="p-1 rounded text-gray-400 hover:text-indigo-600 disabled:opacity-30 disabled:cursor-not-allowed"
                                title="Move Up"
                            >
                                ▲
                            </button>
                            <button
                                type="button"
                                @click="moveDecisionDown(idx)"
                                :disabled="idx === decisionsList.length - 1"
                                class="p-1 rounded text-gray-400 hover:text-indigo-600 disabled:opacity-30 disabled:cursor-not-allowed"
                                title="Move Down"
                            >
                                ▼
                            </button>
                            <button
                                type="button"
                                @click="removeDecisionItem(idx)"
                                class="p-1 rounded text-gray-400 hover:text-rose-600"
                                title="Remove"
                            >
                                ✕
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Expansion Text -->
                <div class="pt-2">
                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1.5">
                        {{ $t('decision_step.expansion_text') }}
                    </label>
                    <textarea
                        v-model="expansionTextAm"
                        @input="triggerAutosave"
                        rows="3"
                        class="block w-full text-xs rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                        :placeholder="$t('decision_step.expansion_text_placeholder')"
                    ></textarea>
                </div>
            </div>

            <!-- 5. Scoring Standard Legend (Collapsible Reference Card) -->
            <div class="rounded-xl border border-gray-200 dark:border-gray-700 overflow-hidden shadow-xs">
                <button
                    type="button"
                    @click="showScoringLegend = !showScoringLegend"
                    class="w-full flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-850 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors text-left"
                >
                    <div class="flex items-center gap-2">
                        <span>ℹ️</span>
                        <div>
                            <span class="text-xs font-bold text-gray-900 dark:text-white block">
                                {{ $t('decision_step.scoring_legend_title') }}
                            </span>
                            <span class="text-[11px] text-gray-500 dark:text-gray-400 block">
                                {{ $t('decision_step.scoring_legend_desc') }}
                            </span>
                        </div>
                    </div>

                    <span class="text-xs font-bold text-indigo-600 dark:text-indigo-400">
                        {{ showScoringLegend ? $t('decision_step.hide_legend') : $t('decision_step.show_legend') }}
                        {{ showScoringLegend ? '▲' : '▼' }}
                    </span>
                </button>

                <div v-show="showScoringLegend" class="p-4 bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-700 space-y-3">
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 text-xs">
                        <!-- Outstanding -->
                        <div class="p-3 rounded-lg border border-emerald-200 dark:border-emerald-800 bg-emerald-50/50 dark:bg-emerald-950/20">
                            <div class="flex items-center justify-between mb-1">
                                <span class="font-extrabold text-emerald-800 dark:text-emerald-300">እጅግ የላቀ</span>
                                <span class="text-[10px] font-bold text-emerald-700 dark:text-emerald-400 uppercase">Outstanding</span>
                            </div>
                            <div class="text-sm font-black text-emerald-900 dark:text-emerald-200">4.5 – 5.0</div>
                            <div class="text-[11px] text-emerald-700 dark:text-emerald-400 mt-1">ክንውን፡ 90% – 100%+ (Capped at 120%)</div>
                        </div>

                        <!-- Exceeds Target -->
                        <div class="p-3 rounded-lg border border-blue-200 dark:border-blue-800 bg-blue-50/50 dark:bg-blue-950/20">
                            <div class="flex items-center justify-between mb-1">
                                <span class="font-extrabold text-blue-800 dark:text-blue-300">ከፍተኛ</span>
                                <span class="text-[10px] font-bold text-blue-700 dark:text-blue-400 uppercase">Exceeds Target</span>
                            </div>
                            <div class="text-sm font-black text-blue-900 dark:text-blue-200">4.0 – 4.4</div>
                            <div class="text-[11px] text-blue-700 dark:text-blue-400 mt-1">ክንውን፡ 75% – 89%</div>
                        </div>

                        <!-- Meets Target -->
                        <div class="p-3 rounded-lg border border-amber-200 dark:border-amber-800 bg-amber-50/50 dark:bg-amber-950/20">
                            <div class="flex items-center justify-between mb-1">
                                <span class="font-extrabold text-amber-800 dark:text-amber-300">የተሳካ</span>
                                <span class="text-[10px] font-bold text-amber-700 dark:text-amber-400 uppercase">Meets Target</span>
                            </div>
                            <div class="text-sm font-black text-amber-900 dark:text-amber-200">3.0 – 3.9</div>
                            <div class="text-[11px] text-amber-700 dark:text-amber-400 mt-1">ክንውን፡ 60% – 74%</div>
                        </div>

                        <!-- Needs Improvement -->
                        <div class="p-3 rounded-lg border border-rose-200 dark:border-rose-800 bg-rose-50/50 dark:bg-rose-950/20">
                            <div class="flex items-center justify-between mb-1">
                                <span class="font-extrabold text-rose-800 dark:text-rose-300">ትኩረት የሚሻ</span>
                                <span class="text-[10px] font-bold text-rose-700 dark:text-rose-400 uppercase">Needs Improvement</span>
                            </div>
                            <div class="text-sm font-black text-rose-900 dark:text-rose-200">&lt; 3.0</div>
                            <div class="text-[11px] text-rose-700 dark:text-rose-400 mt-1">ክንውን፡ ከ 60% በታች (&lt; 60%)</div>
                        </div>
                    </div>

                    <p class="text-[11px] text-gray-500 dark:text-gray-400 italic">
                        * በ BSC ስሌት መሰረት የእያንዳንዱ እንቅስቃሴ ውጤት በክብደት ተባዝቶ ወደ 5.0 ሙሉ ነጥብ ይቀየራል።
                    </p>
                </div>
            </div>

        </div>
    </div>
</template>
