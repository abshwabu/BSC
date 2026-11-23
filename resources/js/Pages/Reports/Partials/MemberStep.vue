<script setup>
import { ref, computed, watch, nextTick } from 'vue';
import axios from 'axios';
import { useI18n } from 'vue-i18n';
import PerspectiveSummaryCard from '@/Components/PerspectiveSummaryCard.vue';
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
const weights = ref({
    financial: 25,
    customers: 25,
    internal: 25,
    learning: 25,
});

const showWeightsEditor = ref(false);
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
    weights.value = {
        financial: Number(props.reportMember.weights?.financial ?? props.reportMember.member?.default_weights?.financial ?? 25),
        customers: Number(props.reportMember.weights?.customers ?? props.reportMember.member?.default_weights?.customers ?? 25),
        internal: Number(props.reportMember.weights?.internal ?? props.reportMember.member?.default_weights?.internal ?? 25),
        learning: Number(props.reportMember.weights?.learning ?? props.reportMember.member?.default_weights?.learning ?? 25),
    };

    saveStatus.value = 'saved';
    errorMessage.value = '';
}

watch(() => props.reportMember.id, () => {
    initData();
}, { immediate: true });

// Weights validation
const weightsSum = computed(() => {
    return (
        (Number(weights.value.financial) || 0) +
        (Number(weights.value.customers) || 0) +
        (Number(weights.value.internal) || 0) +
        (Number(weights.value.learning) || 0)
    );
});

const isWeightsValid = computed(() => {
    return Math.abs(weightsSum.value - 100) < 0.01;
});

function resetWeightsToDefault() {
    const defaults = props.reportMember.member?.default_weights || {
        financial: 25,
        customers: 25,
        internal: 25,
        learning: 25,
    };
    weights.value = {
        financial: Number(defaults.financial ?? 25),
        customers: Number(defaults.customers ?? 25),
        internal: Number(defaults.internal ?? 25),
        learning: Number(defaults.learning ?? 25),
    };
    triggerAutosave();
}

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

// Auto-Draft Helper ("Suggest from data")
function autoDraftComments() {
    const scoredActivities = [];

    for (const act of activities.value) {
        if (act.target_value !== '' && act.target_value !== null && act.actual_value !== '' && act.actual_value !== null) {
            const tVal = Number(act.target_value);
            const aVal = Number(act.actual_value);
            const pct = calculateAchievementPercentage(tVal, aVal, act.direction);
            const label = act.kpi_text || act.activity_text || 'ተግባር';
            scoredActivities.push({
                label,
                target: tVal,
                targetLabel: act.target_label || '',
                actual: aVal,
                actualLabel: act.actual_label || '',
                pct: roundTo(pct, 1),
            });
        }
    }

    if (scoredActivities.length === 0) {
        alert(locale.value === 'en' ? 'Please enter targets and actuals first to generate suggestions.' : 'እባክዎ መጀመሪያ ዒላማ እና ክንውን ቁጥሮችን ያስገቡ።');
        return;
    }

    // Sort descending by percentage
    scoredActivities.sort((a, b) => b.pct - a.pct);

    // Top 2 activities for achievements
    const topActivities = scoredActivities.slice(0, 2);
    let draftAchievements = locale.value === 'en'
        ? 'Key accomplishments recorded this quarter:\n'
        : 'በዚህ ሩብ ዓመት የተመዘገቡ ዋና ዋና ስኬቶች:\n';

    topActivities.forEach((act) => {
        const tStr = `${act.target} ${act.targetLabel}`.trim();
        const aStr = `${act.actual} ${act.actualLabel}`.trim();
        draftAchievements += `• ${act.label}፡ ${aStr} ከ ${tStr} (${act.pct}%)\n`;
    });

    // Lowest activity under 100% for challenges
    const underTargetActivities = scoredActivities.filter((a) => a.pct < 100);
    let draftChallenges = '';

    if (underTargetActivities.length > 0) {
        const lowest = underTargetActivities[underTargetActivities.length - 1];
        const tStr = `${lowest.target} ${lowest.targetLabel}`.trim();
        const aStr = `${lowest.actual} ${lowest.actualLabel}`.trim();
        draftChallenges = locale.value === 'en'
            ? `Bottlenecks requiring attention and improvement:\n• ${lowest.label} achieved ${aStr} against target of ${tStr} (${lowest.pct}%).`
            : `ትኩረት የሚሹ እና ዝቅተኛ አፈፃፀም ያሳዩ ተግባራት:\n• ${lowest.label}፡ ${aStr} ከ ${tStr} (${lowest.pct}%)።`;
    } else {
        draftChallenges = locale.value === 'en'
            ? 'All activities achieved or exceeded targets; continuous scaling required.'
            : 'ሁሉም የታቀዱ ተግባራት ከ 100% በላይ ተከናውነዋል፤ ቀጣይነት ያለው እድገት ማስጠበቅ ያስፈልጋል።';
    }

    achievements.value = draftAchievements.trim();
    challenges.value = draftChallenges.trim();

    triggerAutosave();
}

// Debounced Autosave
let debounceTimer = null;

function triggerAutosave() {
    if (!isWeightsValid.value) {
        saveStatus.value = 'error';
        errorMessage.value = locale.value === 'en'
            ? `Weights must total 100%. Current sum: ${weightsSum.value}%`
            : `የክብደቶች ድምር በትክክል 100% መሆን አለበት። የአሁን ድምር፡ ${weightsSum.value}%`;
        emit('save-status', 'error');
        return;
    }

    saveStatus.value = 'unsaved';
    errorMessage.value = '';
    emit('save-status', 'unsaved');

    if (debounceTimer) clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
        saveNow();
    }, 800);
}

// Save immediately
async function saveNow() {
    if (!isWeightsValid.value) {
        saveStatus.value = 'error';
        errorMessage.value = locale.value === 'en'
            ? `Weights must total 100%. Current sum: ${weightsSum.value}%`
            : `የክብደቶች ድምር በትክክል 100% መሆን አለበት። የአሁን ድምር፡ ${weightsSum.value}%`;
        emit('save-status', 'error');
        return;
    }

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
            weights: {
                financial: Number(weights.value.financial),
                customers: Number(weights.value.customers),
                internal: Number(weights.value.internal),
                learning: Number(weights.value.learning),
            },
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

// Template Integration
const showTemplateModal = ref(false);
const availableTemplates = ref([]);
const isLoadingTemplates = ref(false);
const templateRoleFilter = ref('member'); // 'member' | 'all'
const selectedTemplateIds = ref([]);
const templateSearch = ref('');

async function openTemplateModal() {
    showTemplateModal.value = true;
    selectedTemplateIds.value = [];
    if (availableTemplates.value.length === 0) {
        isLoadingTemplates.value = true;
        try {
            const res = await axios.get('/activity-templates');
            availableTemplates.value = res.data || [];
        } catch (e) {
            console.error('Failed to load activity templates', e);
        } finally {
            isLoadingTemplates.value = false;
        }
    }
}

const filteredTemplates = computed(() => {
    const memberPos = props.reportMember.member?.position_key || 'sales';
    return availableTemplates.value.filter((tpl) => {
        const matchesRole = templateRoleFilter.value === 'all' || tpl.position_key === memberPos;
        const q = templateSearch.value.toLowerCase().trim();
        const matchesSearch = !q ||
            tpl.title.toLowerCase().includes(q) ||
            tpl.activity_text.toLowerCase().includes(q) ||
            tpl.kpi_text.toLowerCase().includes(q);
        return matchesRole && matchesSearch;
    });
});

function insertSingleTemplate(tpl) {
    const newSort = activities.value.length + 1;
    activities.value.push({
        id: null,
        temp_id: `tmp_${Date.now()}_${Math.random()}`,
        sort_order: newSort,
        activity_text: tpl.activity_text || tpl.title || '',
        kpi_text: tpl.kpi_text || '',
        target_value: tpl.default_target_value !== null ? tpl.default_target_value : '',
        target_label: tpl.default_target_label || '',
        actual_value: '',
        actual_label: tpl.default_target_label || '',
        direction: tpl.direction || 'higher_better',
        perspective: tpl.perspective || 'customers',
    });
    triggerAutosave();
    window.dispatchEvent(new CustomEvent('bsc-toast', {
        detail: {
            type: 'success',
            message: 'ተግባሩ ከቴምፕሌት ገብቷል! (Activity inserted from template)',
        },
    }));
}

function insertSelectedTemplates() {
    const toInsert = availableTemplates.value.filter((tpl) => selectedTemplateIds.value.includes(tpl.id));
    toInsert.forEach((tpl) => {
        const newSort = activities.value.length + 1;
        activities.value.push({
            id: null,
            temp_id: `tmp_${Date.now()}_${Math.random()}`,
            sort_order: newSort,
            activity_text: tpl.activity_text || tpl.title || '',
            kpi_text: tpl.kpi_text || '',
            target_value: tpl.default_target_value !== null ? tpl.default_target_value : '',
            target_label: tpl.default_target_label || '',
            actual_value: '',
            actual_label: tpl.default_target_label || '',
            direction: tpl.direction || 'higher_better',
            perspective: tpl.perspective || 'customers',
        });
    });
    triggerAutosave();
    showTemplateModal.value = false;
    window.dispatchEvent(new CustomEvent('bsc-toast', {
        detail: {
            type: 'success',
            message: `${toInsert.length} ተግባራት ከቴምፕሌት ገብተዋል!`,
        },
    }));
}

async function saveRowAsTemplate(row) {
    if (!row.activity_text && !row.kpi_text) {
        alert('እባክዎ የተግባር ወይም የ KPI መግለጫ ያስገቡ።');
        return;
    }

    try {
        await axios.post('/templates', {
            position_key: props.reportMember.member?.position_key || 'custom',
            title: row.activity_text.slice(0, 50) || 'አዲስ ቴምፕሌት',
            activity_text: row.activity_text,
            kpi_text: row.kpi_text,
            default_target_value: row.target_value !== '' ? row.target_value : null,
            default_target_label: row.target_label || '',
            direction: row.direction || 'higher_better',
            perspective: row.perspective || 'customers',
        });
        window.dispatchEvent(new CustomEvent('bsc-toast', {
            detail: {
                type: 'success',
                message: 'ተግባሩ በቴምፕሌት ላይብረሪ ውስጥ ተቀምጧል! (Saved to template library)',
            },
        }));
    } catch (e) {
        window.dispatchEvent(new CustomEvent('bsc-toast', {
            detail: {
                type: 'error',
                message: 'ቴምፕሌቱን ማስቀመጥ አልተቻለም።',
            },
        }));
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
                            <span>{{ errorMessage || $t('member_step.autosave_status.error') }}</span>
                        </span>

                        <button
                            type="button"
                            @click="saveNow"
                            :disabled="!isWeightsValid"
                            class="px-2.5 py-1 text-xs font-medium text-gray-600 hover:text-indigo-600 dark:text-gray-300 dark:hover:text-indigo-400 underline disabled:opacity-30 disabled:cursor-not-allowed"
                        >
                            {{ $t('member_step.save_now') }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Perspective Weights Badges & Edit Button -->
            <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2.5">
                    <span class="text-xs font-bold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                        {{ $t('member_step.weights_label') }}:
                    </span>
                    <span class="px-2.5 py-1 rounded-md text-xs font-bold bg-blue-50 text-blue-700 dark:bg-blue-900/30 dark:text-blue-300 border border-blue-100 dark:border-blue-800">
                        {{ $t('perspectives.financial') }}: {{ weights.financial }}%
                    </span>
                    <span class="px-2.5 py-1 rounded-md text-xs font-bold bg-purple-50 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300 border border-purple-100 dark:border-purple-800">
                        {{ $t('perspectives.customers') }}: {{ weights.customers }}%
                    </span>
                    <span class="px-2.5 py-1 rounded-md text-xs font-bold bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300 border border-amber-100 dark:border-amber-800">
                        {{ $t('perspectives.internal') }}: {{ weights.internal }}%
                    </span>
                    <span class="px-2.5 py-1 rounded-md text-xs font-bold bg-teal-50 text-teal-700 dark:bg-teal-900/30 dark:text-teal-300 border border-teal-100 dark:border-teal-800">
                        {{ $t('perspectives.learning') }}: {{ weights.learning }}%
                    </span>
                </div>

                <div>
                    <button
                        type="button"
                        @click="showWeightsEditor = !showWeightsEditor"
                        class="text-xs font-semibold text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1"
                    >
                        <span>⚙️</span>
                        <span>{{ showWeightsEditor ? 'ክብደቶችን ደብቅ' : 'ክብደቶችን አርትዕ (Edit Weights)' }}</span>
                    </button>
                </div>
            </div>

            <!-- Task 2: Weights Editor with 4 inputs and 100% check -->
            <div
                v-if="showWeightsEditor"
                class="mt-4 p-4 rounded-xl bg-gray-50 dark:bg-gray-900/60 border border-gray-200 dark:border-gray-700 space-y-3"
            >
                <div class="flex items-center justify-between">
                    <h5 class="text-xs font-bold text-gray-800 dark:text-gray-200 uppercase tracking-wider">
                        የ BSC እይታዎች ክብደት ማስተካከያ (Perspective Weights Editor)
                    </h5>
                    <button
                        type="button"
                        @click="resetWeightsToDefault"
                        class="text-xs font-semibold text-gray-600 hover:text-indigo-600 dark:text-gray-400 dark:hover:text-indigo-300 underline"
                    >
                        🔄 ወደ ነባሪ መልስ (Reset to member default)
                    </button>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                    <div>
                        <label class="block text-[11px] font-bold text-gray-600 dark:text-gray-400 mb-1">
                            {{ $t('perspectives.financial') }} (%)
                        </label>
                        <input
                            type="number"
                            min="0"
                            max="100"
                            v-model.number="weights.financial"
                            @input="triggerAutosave"
                            class="block w-full text-xs rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                        />
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-gray-600 dark:text-gray-400 mb-1">
                            {{ $t('perspectives.customers') }} (%)
                        </label>
                        <input
                            type="number"
                            min="0"
                            max="100"
                            v-model.number="weights.customers"
                            @input="triggerAutosave"
                            class="block w-full text-xs rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                        />
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-gray-600 dark:text-gray-400 mb-1">
                            {{ $t('perspectives.internal') }} (%)
                        </label>
                        <input
                            type="number"
                            min="0"
                            max="100"
                            v-model.number="weights.internal"
                            @input="triggerAutosave"
                            class="block w-full text-xs rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                        />
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-gray-600 dark:text-gray-400 mb-1">
                            {{ $t('perspectives.learning') }} (%)
                        </label>
                        <input
                            type="number"
                            min="0"
                            max="100"
                            v-model.number="weights.learning"
                            @input="triggerAutosave"
                            class="block w-full text-xs rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                        />
                    </div>
                </div>

                <!-- Total Indicator & Validation Error -->
                <div class="flex items-center justify-between pt-2 border-t border-gray-200/80 dark:border-gray-700 text-xs">
                    <span class="font-bold text-gray-700 dark:text-gray-300">
                        ድምር ክብደት (Total Weight):
                        <span
                            class="px-2 py-0.5 rounded-full font-black text-xs ml-1"
                            :class="isWeightsValid ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300' : 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300'"
                        >
                            {{ weightsSum }}%
                        </span>
                    </span>

                    <span v-if="!isWeightsValid" class="text-xs font-bold text-red-600 dark:text-red-400">
                        ⚠️ የክብደቶች ድምር በትክክል 100% መሆን አለበት። (Total must equal 100%)
                    </span>
                    <span v-else class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">
                        ✓ ክብደቶች በትክክል 100% ደርሰዋል
                    </span>
                </div>
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
                        @click="openTemplateModal"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-indigo-50 text-indigo-700 hover:bg-indigo-100 dark:bg-indigo-950/40 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 transition-colors shadow-xs"
                    >
                        <span>📋</span>
                        <span>{{ $t('templates_page.insert_from_template') }}</span>
                    </button>
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
                                        @click="saveRowAsTemplate(row)"
                                        class="p-1 text-gray-400 hover:text-amber-600 rounded transition-colors"
                                        :title="$t('templates_page.save_as_template')"
                                    >💾</button>
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
        </div>

        <!-- Task 1: Individual BSC summary card (read-only, live) -->
        <PerspectiveSummaryCard :summary="memberSummary" :weights="weights" />

        <!-- Task 3, 4 & 5: Reflection, Challenges, Next Steps & Signature -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 space-y-5">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-gray-100 dark:border-gray-700 pb-3">
                <h4 class="font-bold text-gray-900 dark:text-white text-base flex items-center gap-2">
                    <span>📝</span>
                    <span>የመስራቹ ማጠቃለያ ማስታወሻዎች (Comments & Follow-up)</span>
                </h4>

                <!-- Task 4: Auto-draft helper button -->
                <button
                    type="button"
                    @click="autoDraftComments"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold bg-indigo-50 text-indigo-700 hover:bg-indigo-100 dark:bg-indigo-900/40 dark:text-indigo-300 dark:hover:bg-indigo-900/60 border border-indigo-200 dark:border-indigo-800 transition-colors shadow-xs"
                >
                    <span>✨</span>
                    <span>ከመረጃው ረቂቅ አመንጭ (Suggest from data)</span>
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                <!-- Achievements -->
                <div class="space-y-1">
                    <div class="flex items-center justify-between">
                        <label class="block text-xs font-bold text-gray-800 dark:text-gray-200">
                            {{ $t('member_step.achievements_label') }}
                        </label>
                        <span class="text-[10px] text-gray-400 font-semibold">
                            {{ achievements.length }} ፊደላት
                        </span>
                    </div>
                    <textarea
                        v-model="achievements"
                        @input="triggerAutosave"
                        rows="4"
                        class="block w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                        :placeholder="$t('member_step.achievements_placeholder')"
                    ></textarea>
                    <span class="text-[10px] text-gray-400 block italic">
                        ምክር፡ ከ 50 - 500 ፊደላት (Recommended: 50–500 chars)
                    </span>
                </div>

                <!-- Challenges -->
                <div class="space-y-1">
                    <div class="flex items-center justify-between">
                        <label class="block text-xs font-bold text-gray-800 dark:text-gray-200">
                            {{ $t('member_step.challenges_label') }}
                        </label>
                        <span class="text-[10px] text-gray-400 font-semibold">
                            {{ challenges.length }} ፊደላት
                        </span>
                    </div>
                    <textarea
                        v-model="challenges"
                        @input="triggerAutosave"
                        rows="4"
                        class="block w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                        :placeholder="$t('member_step.challenges_placeholder')"
                    ></textarea>
                    <span class="text-[10px] text-gray-400 block italic">
                        ምክር፡ ከ 50 - 500 ፊደላት (Recommended: 50–500 chars)
                    </span>
                </div>

                <!-- Next Steps -->
                <div class="space-y-1">
                    <div class="flex items-center justify-between">
                        <label class="block text-xs font-bold text-gray-800 dark:text-gray-200">
                            {{ $t('member_step.next_steps_label') }}
                        </label>
                        <span class="text-[10px] text-gray-400 font-semibold">
                            {{ nextSteps.length }} ፊደላት
                        </span>
                    </div>
                    <textarea
                        v-model="nextSteps"
                        @input="triggerAutosave"
                        rows="4"
                        class="block w-full text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white focus:border-indigo-500 focus:ring-indigo-500"
                        :placeholder="$t('member_step.next_steps_placeholder')"
                    ></textarea>
                    <span class="text-[10px] text-gray-400 block italic">
                        ምክር፡ ከ 50 - 500 ፊደላት (Recommended: 50–500 chars)
                    </span>
                </div>
            </div>

            <!-- Task 5: Signature line and verified stamp -->
            <div class="pt-4 border-t border-gray-200 dark:border-gray-700 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                <label class="inline-flex items-center gap-2.5 cursor-pointer">
                    <input
                        type="checkbox"
                        v-model="signed"
                        @change="triggerAutosave"
                        class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500 h-4 w-4"
                    />
                    <span class="text-xs sm:text-sm font-bold text-gray-800 dark:text-gray-200">
                        {{ $t('member_step.signed_label') }}
                    </span>
                </label>

                <!-- Static Signature Line vs Visual Verified Stamp -->
                <div class="flex items-center gap-3">
                    <div
                        v-if="signed"
                        class="inline-flex items-center gap-2 px-3 py-1.5 border-2 border-emerald-600 dark:border-emerald-500 rounded-lg text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-950/40 font-black text-xs uppercase tracking-wider shadow-xs"
                    >
                        <span>✓</span>
                        <span>የተረጋገጠ እና የተፈረመ (VERIFIED & SIGNED)</span>
                        <span class="text-[10px] text-emerald-600 font-semibold">• {{ reportMember.member?.name }}</span>
                    </div>

                    <div
                        v-else
                        class="text-xs font-semibold text-gray-500 dark:text-gray-400 border-b-2 border-dashed border-gray-400 dark:border-gray-500 pb-1 px-4"
                    >
                        የአባል ፊርማ (Member Signature): ___________________________
                    </div>
                </div>
            </div>
        </div>

        <!-- Insert from Template Modal -->
        <div v-if="showTemplateModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-xs">
            <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl max-w-2xl w-full p-6 space-y-4 border border-gray-200 dark:border-gray-700 max-h-[85vh] flex flex-col">
                <div class="flex items-center justify-between border-b border-gray-100 dark:border-gray-700 pb-3 shrink-0">
                    <div class="flex items-center gap-2">
                        <span class="text-xl">📋</span>
                        <div>
                            <h3 class="text-base font-bold text-gray-900 dark:text-white">
                                {{ $t('templates_page.insert_from_template') }}
                            </h3>
                            <p class="text-xs text-gray-400">
                                ተግባራትን በቀጥታ ወደ ሰንጠረዡ ለማስገባት ይምረጡ
                            </p>
                        </div>
                    </div>
                    <button type="button" @click="showTemplateModal = false" class="text-gray-400 hover:text-gray-600">✕</button>
                </div>

                <!-- Filters & Search -->
                <div class="flex flex-col sm:flex-row items-center justify-between gap-3 shrink-0">
                    <div class="inline-flex p-1 bg-gray-100 dark:bg-gray-700 rounded-lg text-xs font-bold w-full sm:w-auto">
                        <button
                            type="button"
                            @click="templateRoleFilter = 'member'"
                            class="px-3 py-1 rounded-md transition-all"
                            :class="templateRoleFilter === 'member'
                                ? 'bg-white dark:bg-gray-800 text-indigo-600 dark:text-indigo-400 shadow-xs'
                                : 'text-gray-600 dark:text-gray-300'"
                        >
                            የዚህ ዘርፍ ብቻ (Role-Specific)
                        </button>
                        <button
                            type="button"
                            @click="templateRoleFilter = 'all'"
                            class="px-3 py-1 rounded-md transition-all"
                            :class="templateRoleFilter === 'all'
                                ? 'bg-white dark:bg-gray-800 text-indigo-600 dark:text-indigo-400 shadow-xs'
                                : 'text-gray-600 dark:text-gray-300'"
                        >
                            {{ $t('templates_page.all_roles') }}
                        </button>
                    </div>

                    <input
                        type="text"
                        v-model="templateSearch"
                        :placeholder="$t('templates_page.search_placeholder')"
                        class="w-full sm:w-64 text-xs rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white py-1.5"
                    />
                </div>

                <!-- Template list -->
                <div class="flex-1 overflow-y-auto space-y-2 pr-1 min-h-[220px]">
                    <div v-if="isLoadingTemplates" class="py-12 text-center text-xs text-gray-400 animate-pulse">
                        ቴምፕሌቶችን በመጫን ላይ...
                    </div>

                    <div
                        v-else-if="filteredTemplates.length > 0"
                        v-for="tpl in filteredTemplates"
                        :key="tpl.id"
                        class="p-3 rounded-xl border border-gray-200 dark:border-gray-700 hover:border-indigo-300 dark:hover:border-indigo-600 bg-gray-50/50 dark:bg-gray-750 transition-all flex items-start justify-between gap-3"
                    >
                        <div class="flex items-start gap-2.5">
                            <input
                                type="checkbox"
                                :value="tpl.id"
                                v-model="selectedTemplateIds"
                                class="mt-1 rounded text-indigo-600 focus:ring-indigo-500 border-gray-300"
                            />
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-bold text-gray-900 dark:text-white">
                                        {{ tpl.title }}
                                    </span>
                                    <span class="text-[10px] font-semibold px-2 py-0.5 rounded bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                                        {{ $t(`perspectives.${tpl.perspective}`) }}
                                    </span>
                                </div>
                                <p class="text-xs text-gray-600 dark:text-gray-300">
                                    {{ tpl.activity_text }}
                                </p>
                                <div class="text-[11px] text-gray-500 dark:text-gray-400 flex items-center gap-3">
                                    <span>KPI: <strong class="text-gray-700 dark:text-gray-200">{{ tpl.kpi_text }}</strong></span>
                                    <span>ዒላማ: <strong>{{ tpl.default_target_value ?? '' }} {{ tpl.default_target_label ?? '' }}</strong></span>
                                </div>
                            </div>
                        </div>

                        <button
                            type="button"
                            @click="insertSingleTemplate(tpl)"
                            class="shrink-0 px-2.5 py-1 text-xs font-bold rounded-md bg-indigo-600 hover:bg-indigo-700 text-white shadow-xs transition-colors"
                        >
                            {{ $t('templates_page.insert_btn') }}
                        </button>
                    </div>

                    <div v-else class="py-12 text-center text-xs text-gray-400">
                        {{ $t('templates_page.no_templates') }}
                    </div>
                </div>

                <!-- Modal Actions -->
                <div class="pt-3 border-t border-gray-100 dark:border-gray-700 flex items-center justify-between shrink-0">
                    <span class="text-xs text-gray-500">
                        {{ selectedTemplateIds.length }} ቴምፕሌት ተመርጧል
                    </span>

                    <div class="flex items-center gap-2">
                        <button
                            type="button"
                            @click="showTemplateModal = false"
                            class="px-3 py-1.5 text-xs font-bold text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg"
                        >
                            {{ $t('new_report_modal.cancel') }}
                        </button>
                        <button
                            type="button"
                            @click="insertSelectedTemplates"
                            :disabled="selectedTemplateIds.length === 0"
                            class="px-4 py-1.5 text-xs font-bold bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg shadow-sm disabled:opacity-40"
                        >
                            {{ $t('templates_page.insert_selected') }} ({{ selectedTemplateIds.length }})
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
