<script setup>
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import {
    calculateMemberSummary,
    calculateCompanySummary,
    evaluateGate,
    PERSPECTIVES,
    DEFAULT_EXECUTIVE_DECISIONS,
    roundTo,
} from '@/lib/bsc';

const props = defineProps({
    report: {
        type: Object,
        required: true,
    },
});

const { t, locale } = useI18n();

// Export options
const exportLang = ref('bilingual'); // 'bilingual' | 'am' | 'en'
const showSampleNote = ref(true);

// Compute member summaries live
const memberSummaries = computed(() => {
    return (props.report.reportMembers || []).map((rm, idx) => {
        const summary = calculateMemberSummary(rm.activities || [], rm.weights || {});
        return {
            sectionNumber: idx + 1,
            reportMember: rm,
            summary,
        };
    });
});

// Compute consolidated company summary live
const companySummary = computed(() => {
    const rawSummaries = memberSummaries.value.map((m) => m.summary);
    return calculateCompanySummary(rawSummaries);
});

// Compute gate evaluation live
const gateEvaluation = computed(() => {
    const dec = props.report.decision;
    return evaluateGate(
        Boolean(dec?.plc_gate_enabled),
        dec?.min_paying_customers ?? 20,
        dec?.min_mrr ?? 150000,
        dec?.actual_paying_customers ?? 0,
        dec?.actual_mrr ?? 0
    );
});

const decisionsList = computed(() => {
    const decs = props.report.decision?.decisions_list;
    if (Array.isArray(decs) && decs.length > 0) {
        return decs;
    }
    return exportLang.value === 'en' ? DEFAULT_EXECUTIVE_DECISIONS.en : DEFAULT_EXECUTIVE_DECISIONS.am;
});

function getRatingChipClasses(ratingKey) {
    switch (ratingKey) {
        case 'outstanding':
            return 'bg-emerald-50 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border-emerald-300 dark:border-emerald-700';
        case 'exceeds_target':
            return 'bg-blue-50 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300 border-blue-300 dark:border-blue-700';
        case 'meets_target':
            return 'bg-amber-50 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300 border-amber-300 dark:border-amber-700';
        case 'needs_improvement':
            return 'bg-rose-50 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300 border-rose-300 dark:border-rose-700';
        default:
            return 'bg-gray-50 text-gray-700 dark:bg-gray-800 dark:text-gray-300 border-gray-300 dark:border-gray-600';
    }
}

function downloadPdf() {
    props.report.status = 'final';
    const url = route('reports.pdf', props.report.id) +
        `?lang=${exportLang.value}&sample_note=${showSampleNote.value ? 1 : 0}`;
    window.location.href = url;
}

function downloadDocx() {
    props.report.status = 'final';
    const url = route('reports.docx', props.report.id) +
        `?lang=${exportLang.value}&sample_note=${showSampleNote.value ? 1 : 0}`;
    window.location.href = url;
}

function openFullPreview() {
    const url = route('reports.preview', props.report.id) +
        `?screen=1&lang=${exportLang.value}&sample_note=${showSampleNote.value ? 1 : 0}`;
    window.open(url, '_blank');
}

function printReport() {
    window.print();
}

function toggleStatus() {
    const nextStatus = props.report.status === 'final' ? 'draft' : 'final';
    router.patch(route('reports.update', props.report.id), {
        status: nextStatus,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            props.report.status = nextStatus;
        },
    });
}
</script>

<template>
    <div class="space-y-6">

        <!-- Export Toolbar Card -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-5 space-y-4">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 border-b border-gray-100 dark:border-gray-700 pb-4">
                <div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <span>📑</span>
                        <span>{{ $t('preview_step.title') }}</span>
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                        {{ report.title }} ({{ report.period_label }} - {{ report.year }})
                    </p>
                </div>

                <!-- Status Badge & Unfinalize button -->
                <div class="flex items-center gap-2">
                    <span
                        class="px-3 py-1 rounded-full text-xs font-bold border"
                        :class="report.status === 'final'
                            ? 'bg-emerald-50 text-emerald-700 border-emerald-300 dark:bg-emerald-950/50 dark:text-emerald-300'
                            : 'bg-amber-50 text-amber-700 border-amber-300 dark:bg-amber-950/50 dark:text-amber-300'"
                    >
                        {{ report.status === 'final' ? '✓ ' + $t('reports.final') : '✎ ' + $t('reports.draft') }}
                    </span>

                    <button
                        type="button"
                        @click="toggleStatus"
                        class="px-3 py-1 text-xs font-semibold rounded-lg border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 transition-colors"
                    >
                        {{ report.status === 'final' ? $t('preview_step.mark_as_draft') : $t('preview_step.mark_as_final') }}
                    </button>
                </div>
            </div>

            <!-- Controls & Export Buttons Row -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pt-1">
                <!-- Left: Export Language & Sample Note Toggle -->
                <div class="flex flex-wrap items-center gap-4">
                    <!-- Language selector -->
                    <div class="flex items-center gap-2">
                        <label class="text-xs font-bold text-gray-700 dark:text-gray-300">
                            🌐 ቋንቋ / Language:
                        </label>
                        <select
                            v-model="exportLang"
                            class="text-xs rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-white py-1 px-2.5 focus:border-indigo-500 focus:ring-indigo-500"
                        >
                            <option value="bilingual">ሁለት ቋንቋ / Bilingual (Default)</option>
                            <option value="am">አማርኛ ብቻ (Amharic Only)</option>
                            <option value="en">English Only</option>
                        </select>
                    </div>

                    <!-- Sample note toggle -->
                    <label class="flex items-center gap-2 cursor-pointer text-xs font-medium text-gray-700 dark:text-gray-300">
                        <input
                            type="checkbox"
                            v-model="showSampleNote"
                            class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500 h-3.5 w-3.5"
                        />
                        <span>የናሙና መረጃ ማስታወሻ አሳይ (Sample Note)</span>
                    </label>
                </div>

                <!-- Right: Action Buttons -->
                <div class="flex flex-wrap items-center gap-2">
                    <!-- Download PDF -->
                    <button
                        type="button"
                        @click="downloadPdf"
                        class="px-3.5 py-2 bg-rose-600 hover:bg-rose-700 text-white font-bold rounded-lg text-xs shadow-xs transition-colors flex items-center gap-1.5"
                    >
                        <span>📥</span>
                        <span>Download PDF</span>
                    </button>

                    <!-- Download DOCX -->
                    <button
                        type="button"
                        @click="downloadDocx"
                        class="px-3.5 py-2 bg-sky-600 hover:bg-sky-700 text-white font-bold rounded-lg text-xs shadow-xs transition-colors flex items-center gap-1.5"
                    >
                        <span>📄</span>
                        <span>Download DOCX</span>
                    </button>

                    <!-- Print -->
                    <button
                        type="button"
                        @click="printReport"
                        class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold rounded-lg text-xs shadow-xs transition-colors flex items-center gap-1.5"
                    >
                        <span>🖨️</span>
                        <span>Print</span>
                    </button>

                    <!-- Full Preview in New Tab -->
                    <button
                        type="button"
                        @click="openFullPreview"
                        class="px-3 py-2 bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 text-gray-700 dark:text-gray-200 font-semibold rounded-lg text-xs transition-colors flex items-center gap-1"
                        title="Open full page preview in a new window"
                    >
                        <span>↗️</span>
                        <span>{{ $t('preview_step.view_full_report') }}</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Consolidated Company Score Card Banner -->
        <div class="p-6 rounded-2xl bg-gradient-to-r from-indigo-600 to-indigo-800 text-white shadow-md">
            <div class="flex flex-col md:flex-row items-center justify-between gap-4">
                <div>
                    <span class="text-xs font-bold uppercase tracking-wider text-indigo-200">
                        {{ $t('preview_step.company_score_badge') }}
                    </span>
                    <h2 class="text-3xl font-black mt-1">
                        {{ companySummary.company_weighted_score_rounded }} <span class="text-lg font-normal text-indigo-200">/ 5.0</span>
                    </h2>
                    <p class="text-sm text-indigo-100 font-semibold mt-1">
                        {{ locale === 'en' ? companySummary.rating.label_en : companySummary.rating.label_am }}
                        <span class="text-indigo-200 font-normal">({{ companySummary.company_weighted_percentage_rounded }}%)</span>
                    </p>
                </div>

                <div class="text-right text-xs text-indigo-200 space-y-1">
                    <div>🏢 {{ report.company_name }}</div>
                    <div>📅 {{ report.period_label }} - {{ report.year }}</div>
                    <div>👥 3 Founding Members Verified</div>
                </div>
            </div>
        </div>

        <!-- Embedded Interactive A4 Preview Document -->
        <div class="bg-white dark:bg-gray-900 rounded-xl shadow border border-gray-200 dark:border-gray-800 p-8 max-w-4xl mx-auto space-y-8 font-sans text-xs">

            <!-- Title & Intro -->
            <div class="text-center border-b-2 border-indigo-900 dark:border-indigo-500 pb-4 space-y-2">
                <h1 class="text-lg font-extrabold text-gray-900 dark:text-white">
                    {{ exportLang === 'en' ? 'Strategic Plan Individual Performance Report & Balanced Scorecard' : 'የስትራቴጂክ እቅድ የግለሰብ አፈጻጸም ሪፖርት እና BSC ውጤት ካርድ' }}
                </h1>
                <p v-if="exportLang === 'bilingual'" class="text-xs font-semibold text-indigo-600 dark:text-indigo-400">
                    Strategic Plan Individual Performance Report & Balanced Scorecard
                </p>
                <p class="text-[11px] text-gray-600 dark:text-gray-300 max-w-2xl mx-auto leading-relaxed">
                    {{ exportLang === 'en'
                        ? `This report evaluates the quarterly performance of co-founders across the 4 BSC perspectives (Financial, Customers, Internal Business Processes, and Learning & Growth) and outlines the consolidated strategic direction of ${report.company_name}.`
                        : `ይህ ሪፖርት የመስራቾች ቡድን አባላትን የሩብ ዓመት አፈፃፀም በ 4ቱ የ BSC እይታዎች (ፋይናንስ፣ ደንበኞች፣ የውስጥ አሰራር፣ እና መማርና እድገት) በመገምገም የ ${report.company_name} የጋራ አቅጣጫ የሚያሳይ ሰነድ ነው።`
                    }}
                </p>
            </div>

            <!-- Guidelines & Score Standard -->
            <div class="space-y-3">
                <div v-if="showSampleNote" class="p-2.5 rounded-lg bg-amber-50 dark:bg-amber-950/30 border-l-4 border-amber-500 text-[11px] text-amber-800 dark:text-amber-300">
                    <strong>{{ exportLang === 'en' ? 'Sample Data Notice:' : 'የሪፖርት አሞላል ማስታወሻ፡' }}</strong>
                    {{ exportLang === 'en'
                        ? 'The data provided in this report serves as a baseline demonstration; actual figures reflect confirmed quarterly results.'
                        : 'በዚህ ሪፖርት ላይ የተካተቱት መረጃዎች ለምሳሌያዊ ማሳያነት (Sample Demonstration) የቀረቡ ሲሆን፤ ተጨባጭ አሃዞች እንደየሩብ ዓመቱ ትክክለኛ ክንውን የሚሞሉ ናቸው።'
                    }}
                </div>

                <div class="font-bold text-gray-800 dark:text-gray-200 text-xs border-b border-gray-200 dark:border-gray-700 pb-1">
                    {{ exportLang === 'en' ? 'BSC Scoring Standards & Evaluation Benchmark' : 'የሪፖርት አሞላል እና የግምገማ መመሪያ — የ BSC ውጤት አሰጣጥ መመዘኛ' }}
                </div>

                <table class="w-full border-collapse border border-gray-200 dark:border-gray-700 text-[11px]">
                    <thead>
                        <tr class="bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-bold">
                            <th class="border border-gray-200 dark:border-gray-700 p-2 text-left">{{ exportLang === 'en' ? 'Rating Band' : 'የደረጃ ምድብ' }}</th>
                            <th class="border border-gray-200 dark:border-gray-700 p-2 text-center">{{ exportLang === 'en' ? 'Score Range (/5.0)' : 'የ BSC ውጤት (ከ 5.0)' }}</th>
                            <th class="border border-gray-200 dark:border-gray-700 p-2 text-center">{{ exportLang === 'en' ? 'Achievement %' : 'የክንውን %' }}</th>
                            <th class="border border-gray-200 dark:border-gray-700 p-2 text-left">{{ exportLang === 'en' ? 'Meaning' : 'ትርጓሜ እና ማብራሪያ' }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800 text-gray-800 dark:text-gray-200">
                        <tr>
                            <td class="border border-gray-200 dark:border-gray-700 p-2 font-bold text-emerald-700">እጅግ የላቀ (Outstanding)</td>
                            <td class="border border-gray-200 dark:border-gray-700 p-2 text-center font-bold">4.5 – 5.0</td>
                            <td class="border border-gray-200 dark:border-gray-700 p-2 text-center">90% – 100%+</td>
                            <td class="border border-gray-200 dark:border-gray-700 p-2">ከታቀደው በላይ እጅግ የላቀ ከፍተኛ አፈፃፀም የታየበት</td>
                        </tr>
                        <tr>
                            <td class="border border-gray-200 dark:border-gray-700 p-2 font-bold text-blue-700">ከፍተኛ (Exceeds Target)</td>
                            <td class="border border-gray-200 dark:border-gray-700 p-2 text-center font-bold">4.0 – 4.4</td>
                            <td class="border border-gray-200 dark:border-gray-700 p-2 text-center">75% – 89%</td>
                            <td class="border border-gray-200 dark:border-gray-700 p-2">የተቀመጠውን ዒላማ በአጥጋቢ ሁኔታ ያሳካ እና የላቀ ክንውን</td>
                        </tr>
                        <tr>
                            <td class="border border-gray-200 dark:border-gray-700 p-2 font-bold text-amber-700">የተሳካ (Meets Target)</td>
                            <td class="border border-gray-200 dark:border-gray-700 p-2 text-center font-bold">3.0 – 3.9</td>
                            <td class="border border-gray-200 dark:border-gray-700 p-2 text-center">60% – 74%</td>
                            <td class="border border-gray-200 dark:border-gray-700 p-2">መካከለኛና የታቀዱ ግቦችን ያሟላ የተሳካ አፈፃፀም</td>
                        </tr>
                        <tr>
                            <td class="border border-gray-200 dark:border-gray-700 p-2 font-bold text-rose-700">ትኩረት የሚሻ (Needs Improvement)</td>
                            <td class="border border-gray-200 dark:border-gray-700 p-2 text-center font-bold">&lt; 3.0</td>
                            <td class="border border-gray-200 dark:border-gray-700 p-2 text-center">&lt; 60%</td>
                            <td class="border border-gray-200 dark:border-gray-700 p-2">ከዒላማው በታች የሆነና የማስተካከያ እርምጃ የሚሻ</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- ክፍል 1, 2, 3: Members Sections -->
            <div
                v-for="item in memberSummaries"
                :key="'sec-' + item.reportMember.id"
                class="pt-6 border-t-2 border-dashed border-gray-300 dark:border-gray-700 space-y-4"
            >
                <!-- Section Header -->
                <div class="p-2.5 rounded-lg bg-indigo-50 dark:bg-indigo-950/40 border-l-4 border-indigo-600 flex items-center justify-between">
                    <span class="font-bold text-sm text-indigo-950 dark:text-indigo-200">
                        {{ exportLang === 'en' ? `PART ${item.sectionNumber}: ${item.reportMember.member?.name} — ${item.reportMember.member?.role_title_en}` : `ክፍል ${item.sectionNumber}፡ ${item.reportMember.member?.name} — ${item.reportMember.member?.role_title_am}` }}
                    </span>
                    <span class="text-[11px] text-indigo-600 dark:text-indigo-400 font-semibold">
                        {{ report.period_label }} - {{ report.year }}
                    </span>
                </div>

                <!-- 1.1 Identity -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-[11px] p-2.5 rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/40">
                    <div>
                        <span class="text-gray-500 block">የመስራች ስም:</span>
                        <strong class="text-gray-900 dark:text-white">{{ item.reportMember.member?.name }}</strong>
                    </div>
                    <div>
                        <span class="text-gray-500 block">የሥራ ድርሻ:</span>
                        <strong class="text-gray-900 dark:text-white">{{ exportLang === 'en' ? item.reportMember.member?.role_title_en : item.reportMember.member?.role_title_am }}</strong>
                    </div>
                    <div>
                        <span class="text-gray-500 block">የግምገማ ወቅት:</span>
                        <strong class="text-gray-900 dark:text-white">{{ report.period_label }} - {{ report.year }}</strong>
                    </div>
                    <div>
                        <span class="text-gray-500 block">የሪፖርት ቀን:</span>
                        <strong class="text-gray-900 dark:text-white">{{ report.report_date || '—' }}</strong>
                    </div>
                </div>

                <!-- 1.2 Performance Table -->
                <div class="space-y-1">
                    <div class="font-bold text-gray-800 dark:text-gray-200 text-xs">
                        {{ item.sectionNumber }}.2 {{ exportLang === 'en' ? 'Activities & KPI Performance Table' : 'የእንቅስቃሴዎች እና የ KPI አፈፃፀም ሠንጠረዥ' }}
                    </div>
                    <div class="overflow-x-auto rounded border border-gray-200 dark:border-gray-700">
                        <table class="w-full text-left text-[11px]">
                            <thead class="bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-bold border-b border-gray-200 dark:border-gray-700">
                                <tr>
                                    <th class="p-1.5 text-center w-8">#</th>
                                    <th class="p-1.5">ዋና ተግባራት</th>
                                    <th class="p-1.5">መለኪያ (KPI)</th>
                                    <th class="p-1.5">ዒላማ</th>
                                    <th class="p-1.5">ክንውን</th>
                                    <th class="p-1.5 text-center">ክንውን %</th>
                                    <th class="p-1.5">የ BSC እይታ እና ውጤት</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                <tr v-for="(act, aIdx) in item.reportMember.activities" :key="act.id">
                                    <td class="p-1.5 text-center">{{ aIdx + 1 }}</td>
                                    <td class="p-1.5 font-medium">{{ act.activity_text || '—' }}</td>
                                    <td class="p-1.5 text-gray-500">{{ act.kpi_text || '—' }}</td>
                                    <td class="p-1.5">{{ act.target_label || act.target_value }}</td>
                                    <td class="p-1.5 font-semibold">{{ act.actual_label || act.actual_value || '—' }}</td>
                                    <td class="p-1.5 text-center font-bold">
                                        {{ act.target_value ? Math.round((act.actual_value / act.target_value) * 100) : 0 }}%
                                    </td>
                                    <td class="p-1.5">
                                        {{ act.perspective }}
                                    </td>
                                </tr>
                                <tr class="bg-indigo-50/70 dark:bg-indigo-950/40 font-bold border-t-2 border-indigo-200 dark:border-indigo-800 text-indigo-950 dark:text-indigo-100">
                                    <td colspan="5" class="p-2 text-right">አማካይ አጠቃላይ አፈፃፀም:</td>
                                    <td class="p-2 text-center">{{ item.summary.weighted_percentage_rounded }}%</td>
                                    <td class="p-2">
                                        {{ item.summary.weighted_score_rounded }} / 5.0 ({{ locale === 'en' ? item.summary.rating.label_en : item.summary.rating.label_am }})
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 1.3 Individual BSC Summary Card -->
                <div class="space-y-1">
                    <div class="font-bold text-gray-800 dark:text-gray-200 text-xs">
                        {{ item.sectionNumber }}.3 {{ exportLang === 'en' ? 'Individual BSC Perspective Summary Card' : 'የግል የ BSC እይታዎች ማጠቃለያ ካርድ' }}
                    </div>
                    <div class="overflow-x-auto rounded border border-gray-200 dark:border-gray-700">
                        <table class="w-full text-left text-[11px]">
                            <thead class="bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-bold border-b border-gray-200 dark:border-gray-700">
                                <tr>
                                    <th class="p-1.5">የ BSC እይታ</th>
                                    <th class="p-1.5 text-center">ክብደት</th>
                                    <th class="p-1.5 text-center">አማካይ የክንውን %</th>
                                    <th class="p-1.5 text-center">የ BSC ውጤት (ከ 5.0)</th>
                                    <th class="p-1.5 text-center">ደረጃ</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                <tr v-for="pKey in ['financial', 'customers', 'internal', 'learning']" :key="pKey">
                                    <td class="p-1.5 font-semibold">
                                        {{ item.summary.perspectives[pKey]?.name_am }}
                                        <span class="text-gray-400 font-normal">({{ item.summary.perspectives[pKey]?.name_en }})</span>
                                    </td>
                                    <td class="p-1.5 text-center font-bold">{{ item.summary.perspectives[pKey]?.original_weight }}%</td>
                                    <td class="p-1.5 text-center">{{ item.summary.perspectives[pKey]?.average_percentage_rounded !== null ? item.summary.perspectives[pKey]?.average_percentage_rounded + '%' : '—' }}</td>
                                    <td class="p-1.5 text-center font-bold">{{ item.summary.perspectives[pKey]?.score_summary_rounded !== null ? item.summary.perspectives[pKey]?.score_summary_rounded + ' / 5.0' : '—' }}</td>
                                    <td class="p-1.5 text-center">
                                        <span v-if="item.summary.perspectives[pKey]?.rating" class="px-2 py-0.5 rounded-full text-[10px] font-bold border" :class="getRatingChipClasses(item.summary.perspectives[pKey]?.rating?.key)">
                                            {{ locale === 'en' ? item.summary.perspectives[pKey]?.rating?.label_en : item.summary.perspectives[pKey]?.rating?.label_am }}
                                        </span>
                                        <span v-else class="text-gray-400">—</span>
                                    </td>
                                </tr>
                                <tr class="bg-indigo-50/70 dark:bg-indigo-950/40 font-bold border-t-2 border-indigo-200 dark:border-indigo-800 text-indigo-950 dark:text-indigo-100">
                                    <td class="p-2">አጠቃላይ ክብደት የተሰጠው ውጤት:</td>
                                    <td class="p-2 text-center">100%</td>
                                    <td class="p-2 text-center">{{ item.summary.weighted_percentage_rounded }}%</td>
                                    <td class="p-2 text-center">{{ item.summary.weighted_score_rounded }} / 5.0</td>
                                    <td class="p-2 text-center">
                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold border" :class="getRatingChipClasses(item.summary.rating.key)">
                                            {{ locale === 'en' ? item.summary.rating.label_en : item.summary.rating.label_am }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 1.4 Comments & Signature -->
                <div class="space-y-2 pt-1">
                    <div class="font-bold text-gray-800 dark:text-gray-200 text-xs">
                        {{ item.sectionNumber }}.4 {{ exportLang === 'en' ? 'Comments & Strategic Follow-up' : 'አስተያየት እና የቀጣይ አቅጣጫዎች' }}
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                        <div class="p-2 rounded bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                            <span class="text-[10px] font-bold text-gray-500 uppercase block mb-1">ዋና ዋና ስኬቶች:</span>
                            <div class="text-[11px] text-gray-800 dark:text-gray-200 whitespace-pre-line">{{ item.reportMember.achievements || '—' }}</div>
                        </div>
                        <div class="p-2 rounded bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                            <span class="text-[10px] font-bold text-gray-500 uppercase block mb-1">ያጋጠሙ ተግዳሮቶች:</span>
                            <div class="text-[11px] text-gray-800 dark:text-gray-200 whitespace-pre-line">{{ item.reportMember.challenges || '—' }}</div>
                        </div>
                        <div class="p-2 rounded bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                            <span class="text-[10px] font-bold text-gray-500 uppercase block mb-1">የቀጣይ እርምጃዎች:</span>
                            <div class="text-[11px] text-gray-800 dark:text-gray-200 whitespace-pre-line">{{ item.reportMember.next_steps || '—' }}</div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-2 border-t border-gray-200 dark:border-gray-700 text-[11px]">
                        <div>የአባል ፊርማ: _________________________</div>
                        <div>
                            <span v-if="item.reportMember.signed" class="px-2.5 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                                ✓ የተረጋገጠ እና የተፈረመ (Signed & Verified)
                            </span>
                            <span v-else class="text-gray-400 italic">ያልተፈረመ ረቂቅ</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ክፍል 4: Consolidated Company Report & Executive Decision -->
            <div class="pt-6 border-t-2 border-indigo-900 dark:border-indigo-500 space-y-4">
                <div class="p-2.5 rounded-lg bg-indigo-900 text-white font-bold text-sm flex items-center justify-between">
                    <span>{{ exportLang === 'en' ? 'PART 4: CONSOLIDATED COMPANY REPORT & EXECUTIVE DECISION' : 'ክፍል 4፡ የተጠቃለለ የኩባንያ ሪፖርት እና የአመራር ውሳኔ' }}</span>
                    <span class="text-xs font-normal text-indigo-200">{{ report.company_name }}</span>
                </div>

                <!-- 4.1 Consolidated Table -->
                <div class="space-y-1">
                    <div class="font-bold text-gray-800 dark:text-gray-200 text-xs">
                        4.1 {{ exportLang === 'en' ? 'Consolidated Founding Team Performance Comparison' : 'የመስራቾች የጋራ ውጤት ንጽጽር ሰንጠረዥ' }}
                    </div>
                    <div class="overflow-x-auto rounded border border-gray-200 dark:border-gray-700">
                        <table class="w-full text-left text-[11px]">
                            <thead class="bg-gray-50 dark:bg-gray-800 text-gray-700 dark:text-gray-300 font-bold border-b border-gray-200 dark:border-gray-700">
                                <tr>
                                    <th class="p-2">የመስራች ስም እና ሚና</th>
                                    <th class="p-2 text-center">የክንውን %</th>
                                    <th class="p-2 text-center">የ BSC ውጤት (ከ 5.0)</th>
                                    <th class="p-2">የዝግጁነት እና የእድገት ሁኔታ</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                <tr v-for="item in memberSummaries" :key="'c-' + item.reportMember.id">
                                    <td class="p-2 font-semibold">
                                        {{ item.reportMember.member?.name }}
                                        <div class="text-[10px] text-gray-500 font-normal">
                                            {{ exportLang === 'en' ? item.reportMember.member?.role_title_en : item.reportMember.member?.role_title_am }}
                                        </div>
                                    </td>
                                    <td class="p-2 text-center font-bold">{{ item.summary.weighted_percentage_rounded }}%</td>
                                    <td class="p-2 text-center">
                                        <strong>{{ item.summary.weighted_score_rounded }}</strong>
                                        <span class="ml-1 px-1.5 py-0.5 rounded-full text-[9px] font-bold border" :class="getRatingChipClasses(item.summary.rating.key)">
                                            {{ locale === 'en' ? item.summary.rating.label_en : item.summary.rating.label_am }}
                                        </span>
                                    </td>
                                    <td class="p-2 text-gray-600 dark:text-gray-300">{{ item.reportMember.readiness_note || 'መደበኛ የስራ ሂደት ላይ ያለ' }}</td>
                                </tr>
                                <tr class="bg-indigo-50/70 dark:bg-indigo-950/40 font-bold border-t-2 border-indigo-200 dark:border-indigo-800 text-indigo-950 dark:text-indigo-100">
                                    <td class="p-2">የመስራቾች ቡድን አማካይ ውጤት:</td>
                                    <td class="p-2 text-center font-black">{{ companySummary.company_weighted_percentage_rounded }}%</td>
                                    <td class="p-2 text-center font-black">{{ companySummary.company_weighted_score_rounded }} / 5.0</td>
                                    <td class="p-2 italic text-indigo-800 dark:text-indigo-200">የተጠቃለለ የኩባንያ አጠቃላይ አፈፃፀም</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 4.2 Executive Decisions -->
                <div class="space-y-3">
                    <div class="font-bold text-gray-800 dark:text-gray-200 text-xs">
                        4.2 {{ exportLang === 'en' ? 'Executive Management Decisions & PLC Transition Gate' : 'የኩባንያ ውሳኔዎች እና የ PLC ሽግግር መስፈርቶች' }}
                    </div>

                    <!-- Gate Status Badge -->
                    <div class="p-2.5 rounded bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 flex items-center justify-between text-[11px]">
                        <span>የ PLC ሽግግር ቅድመ-ሁኔታ መስፈርት፡</span>
                        <span v-if="gateEvaluation.is_met" class="px-2.5 py-0.5 rounded-full font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                            ✓ መስፈርቱ ተሟልቷል (Criteria Met)
                        </span>
                        <span v-else class="px-2.5 py-0.5 rounded-full font-bold bg-rose-100 text-rose-800 border border-rose-300">
                            ✕ መስፈርቱ አልተሟላም (Criteria Not Met)
                        </span>
                    </div>

                    <!-- Joint Statement -->
                    <div class="p-2.5 rounded bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 space-y-1">
                        <span class="text-[10px] font-bold text-gray-500 uppercase block">የጋራ ውሳኔ እና የአፈፃፀም ማጠቃለያ፡</span>
                        <p class="text-[11px] text-gray-800 dark:text-gray-200 leading-relaxed">
                            {{ report.decision?.decision_text_am || gateEvaluation.suggested_decision_am }}
                        </p>
                    </div>

                    <!-- Numbered Decisions List -->
                    <div class="p-2.5 rounded bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 space-y-1">
                        <span class="text-[10px] font-bold text-gray-500 uppercase block">በቁጥር የተዘረዘሩ የአመራሩ የጋራ ውሳኔዎች፡</span>
                        <ol class="list-decimal list-inside text-[11px] text-gray-800 dark:text-gray-200 space-y-1">
                            <li v-for="(dec, dIdx) in decisionsList" :key="dIdx">{{ dec }}</li>
                        </ol>
                    </div>

                    <!-- Signatures of all founding members -->
                    <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                        <div class="font-bold text-xs text-gray-900 dark:text-white mb-3">የመስራቾች ቦርድ የጋራ ፊርማ (Founding Team Signatures):</div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-[11px]">
                            <div v-for="item in memberSummaries" :key="'sig-' + item.reportMember.id" class="space-y-1">
                                <strong>{{ item.reportMember.member?.name }}</strong>
                                <div class="text-[10px] text-gray-500">{{ exportLang === 'en' ? item.reportMember.member?.role_title_en : item.reportMember.member?.role_title_am }}</div>
                                <div class="border-b border-gray-400 w-3/4 pt-3"></div>
                                <span class="text-[9px] text-gray-400">ፊርማ እና ቀን</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

        </div>

    </div>
</template>
