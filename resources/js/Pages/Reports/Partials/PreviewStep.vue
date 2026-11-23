<script setup>
import { computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { calculateMemberSummary, calculateCompanySummary, roundTo } from '@/lib/bsc';

const props = defineProps({
    report: {
        type: Object,
        required: true,
    },
});

const { t, locale } = useI18n();

// Compute member summaries live
const memberSummaries = computed(() => {
    return (props.report.reportMembers || []).map((rm) => {
        const summary = calculateMemberSummary(rm.activities || [], rm.weights || {});
        return {
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

function toggleStatus() {
    const nextStatus = props.report.status === 'final' ? 'draft' : 'final';
    router.patch(route('reports.update', props.report.id), {
        status: nextStatus,
    }, {
        preserveScroll: true,
    });
}
</script>

<template>
    <div class="space-y-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 space-y-6">

            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-gray-100 dark:border-gray-700 pb-4">
                <div>
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white flex items-center gap-2">
                        <span>📑</span>
                        <span>{{ $t('preview_step.title') }}</span>
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        {{ report.title }} ({{ report.period_label }} - {{ report.year }})
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        @click="toggleStatus"
                        class="px-3.5 py-1.5 rounded-lg text-xs font-bold border transition-colors shadow-sm"
                        :class="report.status === 'final'
                            ? 'bg-emerald-50 text-emerald-700 border-emerald-300 dark:bg-emerald-900/30 dark:text-emerald-300'
                            : 'bg-amber-50 text-amber-700 border-amber-300 dark:bg-amber-900/30 dark:text-amber-300'"
                    >
                        <span>{{ report.status === 'final' ? '✓ ' + $t('reports.final') : '✎ ' + $t('reports.draft') }}</span>
                        <span class="text-xs font-normal ml-1">({{ report.status === 'final' ? $t('preview_step.mark_as_draft') : $t('preview_step.mark_as_final') }})</span>
                    </button>
                </div>
            </div>

            <!-- Consolidated Company Score Card -->
            <div class="p-6 rounded-2xl bg-gradient-to-r from-indigo-500 to-indigo-700 text-white shadow-md">
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
                            <span class="text-indigo-200">({{ companySummary.company_weighted_percentage_rounded }}%)</span>
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            @click="window.print()"
                            class="px-4 py-2 bg-white text-indigo-700 font-bold rounded-lg text-xs shadow hover:bg-indigo-50 transition-colors flex items-center gap-1.5"
                        >
                            <span>🖨️</span>
                            <span>{{ $t('preview_step.print_export') }}</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Per-Member Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div
                    v-for="item in memberSummaries"
                    :key="item.reportMember.id"
                    class="p-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-900/40 space-y-3"
                >
                    <div class="border-b border-gray-200/80 dark:border-gray-700 pb-2">
                        <h4 class="font-bold text-gray-900 dark:text-white text-sm">
                            {{ item.reportMember.member?.name }}
                        </h4>
                        <p class="text-xs text-indigo-600 dark:text-indigo-400 font-medium">
                            {{ locale === 'en' ? item.reportMember.member?.role_title_en : item.reportMember.member?.role_title_am }}
                        </p>
                    </div>

                    <div class="flex items-baseline justify-between">
                        <span class="text-xs font-bold text-gray-500 uppercase">ውጤት / Score:</span>
                        <div class="text-right">
                            <span class="text-lg font-black text-gray-900 dark:text-white">
                                {{ item.summary.weighted_score_rounded }} / 5.0
                            </span>
                            <span class="block text-xs font-semibold text-emerald-600">
                                {{ locale === 'en' ? item.summary.rating.label_en : item.summary.rating.label_am }} ({{ item.summary.weighted_percentage_rounded }}%)
                            </span>
                        </div>
                    </div>

                    <div class="pt-2 border-t border-gray-200/60 dark:border-gray-700/60 flex items-center justify-between text-xs text-gray-500">
                        <span>ተግባራት (Activities): {{ item.reportMember.activities?.length || 0 }}</span>
                        <span v-if="item.reportMember.signed" class="text-emerald-600 font-semibold">✓ ተፈርሟል</span>
                        <span v-else class="text-amber-500">ያልተፈረመ</span>
                    </div>
                </div>
            </div>

        </div>
    </div>
</template>
