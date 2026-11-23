<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue';
import axios from 'axios';
import { useI18n } from 'vue-i18n';
import {
    calculateAchievementPercentage,
    calculateScoreFromPercentage,
    calculateMemberSummary,
    formatPerspectiveScoreString,
    roundTo,
} from '@/lib/bsc';

const props = defineProps({
    report: {
        type: Object,
        required: true,
    },
    reportMember: {
        type: Object,
        required: true,
    },
});

const emit = defineEmits(['saved', 'save-status']);

const { t, locale } = useI18n();

// Local editable state cloned from prop
const activities = ref([]);
const achievements = ref('');
const challenges = ref('');
const nextSteps = ref('');
const signed = ref(false);
const weights = ref({});

const saveStatus = ref('saved'); // 'saved' | 'saving' | 'unsaved' | 'error'
const errorMessage = ref('');

// Initialize local state
function initData() {
    activities.value = (props.reportMember.activities || []).map((a, idx) => ({
        id: a.id || null,
        temp_id: a.id || `tmp_${Date.now()}_${idx}`,
        sort_order: a.sort_order || idx + 1,
        activity_text: a.activity_text || '',
        kpi_text: a.kpi_text || '',
        target_value: a.target_value !== null ? a.target_value : '',
        target_label: a.target_label || '',
        actual_value: a.actual_value !== null ? a.actual_value : '',
        actual_label: a.actual_label || '',
        direction: a.direction || 'higher_better',
        perspective: a.perspective || 'customers',
    }));

    achievements.value = props.reportMember.achievements || '';
    challenges.value = props.reportMember.challenges || '';
    nextSteps.value = props.reportMember.next_steps || '';
    signed.value = Boolean(props.reportMember.signed);
    weights.value = { ...(props.reportMember.weights || props.reportMember.member?.default_weights || {}) };

    saveStatus.value = 'saved';
}

watch(() => props.reportMember.id, () => {
    initData();
}, { immediate: true });

// Live calculation of member performance using TS scoring lib
const memberSummary = computed(() => {
    return calculateMemberSummary(activities.value, weights.value);
});

// Row calculations helper
function getRowStats(row) {
    const tVal = row.target_value !== '' && row.target_value !== null ? Number(row.target_value) : null;
    const aVal = row.actual_value !== '' && row.actual_value !== null ? Number(row.actual_value) : null;

    const pct = calculateAchievementPercentage(tVal, aVal, row.direction);
    const score = calculateScoreFromPercentage(pct);
    const formatted = formatPerspectiveScoreString(row.perspective, score, locale.value);

    return {
        pct: roundTo(pct, 1),
        score: roundTo(score, 1),
        formattedString: formatted,
    };
}

// Activity row manipulation
function addRow() {
    const newSort = activities.value.length + 1;
    activities.value.push({
        id: null,
        temp_id: `tmp_${Date.now()}_${Math.random()}`,
        sort_order: newSort,
        activity_text: '',
        kpi_text: '',
        target_value: '',
        target_label: '',
        actual_value: '',
        actual_label: '',
        direction: 'higher_better',
        perspective: 'customers',
    });
    triggerAutosave();

    nextTick(() => {
        const inputs = document.querySelectorAll('.activity-text-input');
        if (inputs.length > 0) {
            inputs[inputs.length - 1].focus();
        }
    });
}

function duplicateRow(index) {
    const source = activities.value[index];
    const newRow = {
        ...source,
        id: null,
        temp_id: `tmp_${Date.now()}_${Math.random()}`,
        sort_order: activities.value.length + 1,
    };
    activities.value.splice(index + 1, 0, newRow);
    triggerAutosave();
}

function deleteRow(index) {
    activities.value.splice(index, 1);
    activities.value.forEach((act, idx) => {
        act.sort_order = idx + 1;
    });
    triggerAutosave();
}

function moveRow(index, direction) {
    const targetIdx = index + direction;
    if (targetIdx < 0 || targetIdx >= activities.value.length) return;

    const temp = activities.value[index];
    activities.value[index] = activities.value[targetIdx];
    activities.value[targetIdx] = temp;

    activities.value.forEach((act, idx) => {
        act.sort_order = idx + 1;
    });
    triggerAutosave();
}

// Enter on last row adds a new row
function handleKeydown(event, index, isLastField) {
    if (event.key === 'Enter' && isLastField && index === activities.value.length - 1) {
        event.preventDefault();
        addRow();
    }
}

// Debounced Autosave
let debounceTimer = null;

function triggerAutosave() {
    saveStatus.value = 'unsaved';
    emit('save-status', 'unsaved');
    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        saveNow();
    }, 800);
}

// Save immediately
async function saveNow() {
    if (debounceTimer) clearTimeout(debounceTimer);
    saveStatus.value = 'saving';
    emit('save-status', 'saving');
    errorMessage.value = '';

    try {
        const payload = {
            achievements: achievements.value,
            challenges: challenges.value,
            next_steps: nextSteps.value,
            signed: signed.value,
            weights: weights.value,
            activities: activities.value.map((a, idx) => ({
                id: a.id || null,
                sort_order: idx + 1,
                activity_text: a.activity_text,
                kpi_text: a.kpi_text,
                target_value: a.target_value !== '' ? a.target_value : null,
                target_label: a.target_label,
                actual_value: a.actual_value !== '' ? a.actual_value : null,
                actual_label: a.actual_label,
                direction: a.direction,
                perspective: a.perspective,
            })),
        };

        const res = await axios.patch(
            route('reports.members.update', [props.report.id, props.reportMember.id]),
            payload
        );

        if (res.data?.success && res.data?.report_member) {
            // Update activity IDs from DB
            const serverActs = res.data.report_member.activities || [];
            activities.value.forEach((act, idx) => {
                if (serverActs[idx]) {
                    act.id = serverActs[idx].id;
                }
            });
            saveStatus.value = 'saved';
            emit('save-status', 'saved');
            emit('saved', res.data.report_member);
        }
    } catch (err) {
        console.error('Autosave error:', err);
        saveStatus.value = 'error';
        emit('save-status', 'error');
        errorMessage.value = err.response?.data?.message || t('member_step.autosave_status.error');
    }
}
</script>

<template>
    <div class="space-y-6">

        <!-- Identity Block -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 border-b border-gray-100 dark:border-gray-700/60 pb-4">
                <div>
                    <div class="flex items-center gap-2">
                        <span class="text-2xl">👤</span>
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white">
                            {{ reportMember.member?.name }}
                        </h3>
                    </div>
                    <p class="text-sm text-indigo-600 dark:text-indigo-400 font-semibold mt-0.5">
                        {{ locale === 'en' ? reportMember.member?.role_title_en : reportMember.member?.role_title_am }}
                        <span class="text-gray-400 dark:text-gray-500 font-normal">
                            ({{ locale === 'en' ? reportMember.member?.role_title_am : reportMember.member?.role_title_en }})
                        </span>
                    </p>
                </div>

                <!-- Report Period & Date -->
                <div class="flex flex-wrap items-center gap-3">
                    <div class="bg-gray-50 dark:bg-gray-700/50 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-600">
                        <span class="text-xs text-gray-400 uppercase font-semibold block">{{ $t('member_step.report_period') }}</span>
                        <span class="text-sm font-bold text-gray-800 dark:text-gray-200">{{ report.period_label }} - {{ report.year }}</span>
                    </div>

                    <div class="bg-gray-50 dark:bg-gray-700/50 px-3 py-1.5 rounded-lg border border-gray-200 dark:border-gray-600">
                        <span class="text-xs text-gray-400 uppercase font-semibold block">{{ $t('member_step.report_date') }}</span>
                        <span class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ report.report_date || '—' }}</span>
                    </div>

                    <!-- Autosave Indicator -->
                    <div class="flex items-center gap-2 ml-auto">
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
                            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-400 border border-red-200 dark:border-red-800"
                        >
                            <span>⚠️</span>
                            <span>{{ $t('member_step.autosave_status.error') }}</span>
                        </span>

                        <button
                            type="button"
                            @click="saveNow"
                            class="px-2.5 py-1 text-xs font-medium text-gray-600 hover:text-indigo-600 dark:text-gray-300 dark:hover:text-indigo-400 underline"
                        >
                            {{ $t('member_step.save_now') }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Perspective Weights Badges -->
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <span class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    {{ $t('member_step.weights_label') }}:
                </span>
                <span class="px-2.5 py-1 rounded-md text-xs font-semibold bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300 border border-blue-100 dark:border-blue-800">
                    {{ $t('perspectives.financial') }}: {{ weights.financial ?? 0 }}%
                </span>
                <span class="px-2.5 py-1 rounded-md text-xs font-semibold bg-purple-50 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300 border border-purple-100 dark:border-purple-800">
                    {{ $t('perspectives.customers') }}: {{ weights.customers ?? 0 }}%
                </span>
                <span class="px-2.5 py-1 rounded-md text-xs font-semibold bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300 border border-amber-100 dark:border-amber-800">
                    {{ $t('perspectives.internal') }}: {{ weights.internal ?? 0 }}%
                </span>
                <span class="px-2.5 py-1 rounded-md text-xs font-semibold bg-teal-50 text-teal-700 dark:bg-teal-900/30 dark:text-teal-300 border border-teal-100 dark:border-teal-800">
                    {{ $t('perspectives.learning') }}: {{ weights.learning ?? 0 }}%
                </span>
            </div>
        </div>

        <!-- Activities Table Card -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
            <div class="p-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <span class="text-lg">🎯</span>
                    <h4 class="font-bold text-gray-900 dark:text-white text-base">
                        {{ $t('member_step.activities_title') }}
                    </h4>
                    <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 font-semibold text-gray-600 dark:text-gray-300">
                        {{ activities.length }}
                    </span>
                </div>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="addRow"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-indigo-600 text-white hover:bg-indigo-700 shadow-sm transition-colors"
                    >
                        <span>➕</span>
                        <span>{{ $t('member_step.add_row') }}</span>
                    </button>
                </div>
            </div>

            <!-- Responsive Table with Horizontal Scroll -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-xs">
                    <thead class="bg-gray-50 dark:bg-gray-700/50 text-gray-600 dark:text-gray-300 uppercase font-bold tracking-wider">
                        <tr>
                            <th class="py-3 px-2 text-center w-12">{{ $t('table.col_num') }}</th>
                            <th class="py-3 px-3 min-w-[200px]">{{ $t('table.col_activity') }}</th>
                            <th class="py-3 px-3 min-w-[180px]">{{ $t('table.col_kpi') }}</th>
                            <th class="py-3 px-3 min-w-[170px]">{{ $t('table.col_target') }}</th>
                            <th class="py-3 px-3 min-w-[170px]">{{ $t('table.col_actual') }}</th>
                            <th class="py-3 px-2 text-center min-w-[100px]">{{ $t('table.col_direction') }}</th>
                            <th class="py-3 px-3 min-w-[140px]">{{ $t('table.col_perspective') }}</th>
                            <th class="py-3 px-3 text-center min-w-[90px]">{{ $t('table.col_achievement_pct') }}</th>
                            <th class="py-3 px-3 text-center min-w-[160px]">{{ $t('table.col_score') }}</th>
                            <th class="py-3 px-2 text-center w-24">{{ $t('table.col_actions') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60 bg-white dark:bg-gray-800">
                        <tr
                            v-for="(row, idx) in activities"
                            :key="row.temp_id"
                            class="hover:bg-gray-50/60 dark:hover:bg-gray-700/30 transition-colors"
                        >
                            <!-- Row Order & Drag/Reorder buttons -->
                            <td class="py-2.5 px-2 text-center whitespace-nowrap">
                                <div class="flex items-center justify-center gap-0.5 text-gray-400">
                                    <button
                                        type="button"
                                        @click="moveRow(idx, -1)"
                                        :disabled="idx === 0"
                                        class="hover:text-indigo-600 disabled:opacity-30 p-0.5"
                                        title="Move up"
                                    >▲</button>
                                    <span class="font-bold text-gray-700 dark:text-gray-300 w-4 text-center">{{ idx + 1 }}</span>
                                    <button
                                        type="button"
                                        @click="moveRow(idx, 1)"
                                        :disabled="idx === activities.length - 1"
                                        class="hover:text-indigo-600 disabled:opacity-30 p-0.5"
                                        title="Move down"
                                    >▼</button>
                                </div>
                            </td>

                            <!-- Activity Text -->
                            <td class="py-2 px-2">
                                <input
                                    type="text"
                                    v-model="row.activity_text"
                                    @input="triggerAutosave"
                                    class="activity-text-input block w-full text-xs rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500 py-1.5 px-2"
                                    :placeholder="$t('table.col_activity')"
                                />
                            </td>

                            <!-- KPI Text -->
                            <td class="py-2 px-2">
                                <input
                                    type="text"
                                    v-model="row.kpi_text"
                                    @input="triggerAutosave"
                                    class="block w-full text-xs rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500 py-1.5 px-2"
                                    :placeholder="$t('table.col_kpi')"
                                />
                            </td>

                            <!-- Target: Value + Label -->
                            <td class="py-2 px-2">
                                <div class="flex items-center gap-1.5">
                                    <input
                                        type="number"
                                        step="any"
                                        v-model="row.target_value"
                                        @input="triggerAutosave"
                                        class="w-20 text-xs rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500 py-1.5 px-2 text-right font-medium"
                                        placeholder="0.00"
                                    />
                                    <input
                                        type="text"
                                        v-model="row.target_label"
                                        @input="triggerAutosave"
                                        class="flex-1 text-xs rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500 py-1.5 px-2"
                                        :placeholder="$t('table.target_label')"
                                    />
                                </div>
                            </td>

                            <!-- Actual: Value + Label -->
                            <td class="py-2 px-2">
                                <div class="flex items-center gap-1.5">
                                    <input
                                        type="number"
                                        step="any"
                                        v-model="row.actual_value"
                                        @input="triggerAutosave"
                                        class="w-20 text-xs rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500 py-1.5 px-2 text-right font-medium"
                                        placeholder="0.00"
                                    />
                                    <input
                                        type="text"
                                        v-model="row.actual_label"
                                        @input="triggerAutosave"
                                        class="flex-1 text-xs rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500 py-1.5 px-2"
                                        :placeholder="$t('table.actual_label')"
                                    />
                                </div>
                            </td>

                            <!-- Direction Toggle -->
                            <td class="py-2 px-2 text-center whitespace-nowrap">
                                <button
                                    type="button"
                                    @click="row.direction = row.direction === 'higher_better' ? 'lower_better' : 'higher_better'; triggerAutosave();"
                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-bold transition-colors border"
                                    :class="row.direction === 'higher_better'
                                        ? 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/30 dark:text-emerald-300 dark:border-emerald-800'
                                        : 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-900/30 dark:text-amber-300 dark:border-amber-800'"
                                    :title="row.direction === 'higher_better' ? $t('table.higher_better_tooltip') : $t('table.lower_better_tooltip')"
                                >
                                    <span>{{ row.direction === 'higher_better' ? '📈 ' + $t('table.higher_short') : '📉 ' + $t('table.lower_short') }}</span>
                                </button>
                            </td>

                            <!-- BSC Perspective Select -->
                            <td class="py-2 px-2">
                                <select
                                    v-model="row.perspective"
                                    @change="triggerAutosave"
                                    @keydown="handleKeydown($event, idx, true)"
                                    class="block w-full text-xs rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500 py-1.5 px-2"
                                >
                                    <option value="financial">{{ $t('perspectives.financial') }}</option>
                                    <option value="customers">{{ $t('perspectives.customers') }}</option>
                                    <option value="internal">{{ $t('perspectives.internal') }}</option>
                                    <option value="learning">{{ $t('perspectives.learning') }}</option>
                                </select>
                            </td>

                            <!-- Live Computed Achievement % -->
                            <td class="py-2 px-3 text-center whitespace-nowrap">
                                <span
                                    class="inline-flex items-center justify-center px-2 py-0.5 rounded-full font-bold text-xs"
                                    :class="{
                                        'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300': getRowStats(row).pct >= 90,
                                        'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300': getRowStats(row).pct >= 75 && getRowStats(row).pct < 90,
                                        'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300': getRowStats(row).pct >= 60 && getRowStats(row).pct < 75,
                                        'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300': getRowStats(row).pct < 60,
                                    }"
                                >
                                    {{ getRowStats(row).pct }}%
                                </span>
                            </td>

                            <!-- Live Computed Score String -->
                            <td class="py-2 px-3 text-center whitespace-nowrap">
                                <span class="text-xs font-semibold text-gray-800 dark:text-gray-200">
                                    {{ getRowStats(row).formattedString }}
                                </span>
                            </td>

                            <!-- Row Actions -->
                            <td class="py-2 px-2 text-center whitespace-nowrap">
                                <div class="flex items-center justify-center gap-1">
                                    <button
                                        type="button"
                                        @click="duplicateRow(idx)"
                                        class="p-1 text-gray-400 hover:text-indigo-600 rounded transition-colors"
                                        :title="$t('member_step.duplicate_row')"
                                    >📋</button>
                                    <button
                                        type="button"
                                        @click="deleteRow(idx)"
                                        class="p-1 text-gray-400 hover:text-red-600 rounded transition-colors"
                                        :title="$t('member_step.delete_row')"
                                    >🗑️</button>
                                </div>
                            </td>
                        </tr>

                        <!-- Empty state -->
                        <tr v-if="activities.length === 0">
                            <td colspan="10" class="py-8 text-center text-gray-400 text-xs italic">
                                {{ $t('table.empty_table') }}
                            </td>
                        </tr>
                    </tbody>

                    <!-- Bottom Live Summary Row in Table -->
                    <tfoot class="bg-indigo-50/70 dark:bg-gray-700/80 border-t-2 border-indigo-200 dark:border-indigo-900">
                        <tr>
                            <td colspan="7" class="py-3 px-4 font-bold text-gray-800 dark:text-white text-right">
                                {{ $t('member_step.overall_summary') }}:
                            </td>
                            <td class="py-3 px-3 text-center whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-black bg-indigo-600 text-white shadow-sm">
                                    {{ memberSummary.weighted_percentage_rounded }}%
                                </span>
                            </td>
                            <td class="py-3 px-3 text-center whitespace-nowrap">
                                <div class="inline-flex items-center gap-1.5 font-bold text-gray-900 dark:text-white">
                                    <span class="text-sm text-indigo-700 dark:text-indigo-300 font-black">{{ memberSummary.weighted_score_rounded }} / 5.0</span>
                                    <span class="text-xs px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                                        {{ locale === 'en' ? memberSummary.rating?.label_en : memberSummary.rating?.label_am }}
                                    </span>
                                </div>
                            </td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Perspective Sub-Cards -->
            <div class="p-4 bg-gray-50/70 dark:bg-gray-900/40 border-t border-gray-100 dark:border-gray-700">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                    <div
                        v-for="(meta, pKey) in memberSummary.perspectives"
                        :key="pKey"
                        class="p-3 rounded-lg border bg-white dark:bg-gray-800 shadow-xs"
                        :class="meta.excluded ? 'border-dashed border-gray-300 dark:border-gray-700 opacity-60' : 'border-gray-200 dark:border-gray-700'"
                    >
                        <div class="flex items-center justify-between text-xs mb-1">
                            <span class="font-bold text-gray-800 dark:text-gray-200">
                                {{ locale === 'en' ? meta.name_en : meta.name_am }}
                            </span>
                            <span class="text-xs font-semibold px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                                {{ roundTo(meta.effective_weight, 1) }}%
                            </span>
                        </div>

                        <div v-if="!meta.excluded" class="flex items-baseline justify-between mt-2">
                            <span class="text-xs font-semibold text-gray-500">
                                {{ meta.average_percentage_rounded }}%
                            </span>
                            <span class="text-sm font-black text-indigo-600 dark:text-indigo-400">
                                {{ meta.score_summary_rounded }} / 5.0
                            </span>
                        </div>
                        <div v-else class="text-xs text-gray-400 italic mt-2">
                            (ምንም ተግባር አልተመዘገበም - ክብደት ተከፋፍሏል)
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reflection, Challenges & Next Steps -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 space-y-4">
            <h4 class="font-bold text-gray-900 dark:text-white text-base flex items-center gap-2">
                <span>📝</span>
                <span>የመስራቹ ማጠቃለያ ማስታወሻዎች (Reflection & Notes)</span>
            </h4>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Achievements -->
                <div>
                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">
                        {{ $t('member_step.achievements_label') }}
                    </label>
                    <textarea
                        v-model="achievements"
                        @input="triggerAutosave"
                        rows="3"
                        class="block w-full text-xs rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                        :placeholder="$t('member_step.achievements_placeholder')"
                    ></textarea>
                </div>

                <!-- Challenges -->
                <div>
                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">
                        {{ $t('member_step.challenges_label') }}
                    </label>
                    <textarea
                        v-model="challenges"
                        @input="triggerAutosave"
                        rows="3"
                        class="block w-full text-xs rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                        :placeholder="$t('member_step.challenges_placeholder')"
                    ></textarea>
                </div>

                <!-- Next Steps -->
                <div>
                    <label class="block text-xs font-bold text-gray-700 dark:text-gray-300 mb-1">
                        {{ $t('member_step.next_steps_label') }}
                    </label>
                    <textarea
                        v-model="nextSteps"
                        @input="triggerAutosave"
                        rows="3"
                        class="block w-full text-xs rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                        :placeholder="$t('member_step.next_steps_placeholder')"
                    ></textarea>
                </div>
            </div>

            <!-- Signed checkbox -->
            <div class="pt-3 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between">
                <label class="inline-flex items-center gap-2.5 cursor-pointer">
                    <input
                        type="checkbox"
                        v-model="signed"
                        @change="triggerAutosave"
                        class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500 h-4 w-4"
                    />
                    <span class="text-sm font-semibold text-gray-800 dark:text-gray-200">
                        {{ $t('member_step.signed_label') }}
                    </span>
                </label>

                <button
                    type="button"
                    @click="addRow"
                    class="inline-flex items-center gap-1 text-xs font-semibold text-indigo-600 hover:text-indigo-800 dark:text-indigo-400"
                >
                    <span>➕ {{ $t('member_step.add_row_short') }}</span>
                </button>
            </div>
        </div>

    </div>
</template>
