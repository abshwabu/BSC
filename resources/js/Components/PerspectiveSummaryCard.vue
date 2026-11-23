<script setup>
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';
import { roundTo } from '@/lib/bsc';

const props = defineProps({
    summary: {
        type: Object,
        required: true,
    },
    weights: {
        type: Object,
        default: () => ({}),
    },
});

const { t, locale } = useI18n();

const perspectiveOrder = ['financial', 'customers', 'internal', 'learning'];

function getRatingBadgeClasses(ratingKey) {
    switch (ratingKey) {
        case 'outstanding':
            return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300 border-emerald-300 dark:border-emerald-700';
        case 'exceeds_target':
            return 'bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300 border-blue-300 dark:border-blue-700';
        case 'meets_target':
            return 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 border-amber-300 dark:border-amber-700';
        case 'needs_improvement':
            return 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300 border-rose-300 dark:border-rose-700';
        default:
            return 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300 border-gray-300';
    }
}

function getRatingLabelText(rating) {
    if (!rating) return '—';
    return locale.value === 'en' ? rating.label_en : rating.label_am;
}

const totalWeight = computed(() => {
    return Object.values(props.weights || {}).reduce((acc, w) => acc + (Number(w) || 0), 0);
});
</script>

<template>
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="p-4 border-b border-gray-100 dark:border-gray-700 bg-gray-50/70 dark:bg-gray-900/40 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <span class="text-lg">📊</span>
                <h4 class="font-bold text-gray-900 dark:text-white text-sm sm:text-base">
                    የግል BSC ማጠቃለያ ሰንጠረዥ (Individual BSC Summary)
                </h4>
            </div>
            <div v-if="summary.has_redistributed_weights" class="text-xs font-semibold px-2.5 py-1 rounded bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300 border border-amber-200 dark:border-amber-800">
                ⚠️ ክብደት በቅደም ተከተል ተከፋፍሏል (Weight Redistributed)
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-xs text-left">
                <thead class="bg-gray-50 dark:bg-gray-700/50 text-gray-600 dark:text-gray-300 uppercase font-bold tracking-wider">
                    <tr>
                        <th class="py-3 px-4">የ BSC እይታ (Perspective)</th>
                        <th class="py-3 px-3 text-center">ክብደት (Weight)</th>
                        <th class="py-3 px-3 text-center">አማካይ ክንውን (Avg %)</th>
                        <th class="py-3 px-3 text-center">BSC ውጤት (Score / 5.0)</th>
                        <th class="py-3 px-4 text-center">ደረጃ (Rating / Status)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700/60 bg-white dark:bg-gray-800 font-medium">
                    <tr
                        v-for="pKey in perspectiveOrder"
                        :key="pKey"
                        class="hover:bg-gray-50/60 dark:hover:bg-gray-700/30 transition-colors"
                        :class="summary.perspectives[pKey]?.excluded ? 'opacity-50' : ''"
                    >
                        <!-- Perspective Name -->
                        <td class="py-3 px-4 text-gray-900 dark:text-white font-bold whitespace-nowrap">
                            <span>{{ locale === 'en' ? summary.perspectives[pKey]?.name_en : summary.perspectives[pKey]?.name_am }}</span>
                            <span class="text-gray-400 font-normal ml-1">
                                ({{ locale === 'en' ? summary.perspectives[pKey]?.name_am : summary.perspectives[pKey]?.name_en }})
                            </span>
                        </td>

                        <!-- Weight -->
                        <td class="py-3 px-3 text-center whitespace-nowrap">
                            <span class="font-bold text-gray-800 dark:text-gray-200">
                                {{ weights[pKey] ?? 0 }}%
                            </span>
                            <span
                                v-if="summary.has_redistributed_weights && !summary.perspectives[pKey]?.excluded"
                                class="block text-[10px] text-indigo-600 dark:text-indigo-400 font-semibold"
                            >
                                (ተግባራዊ: {{ roundTo(summary.perspectives[pKey]?.effective_weight, 1) }}%)
                            </span>
                        </td>

                        <!-- Average Achievement % -->
                        <td class="py-3 px-3 text-center whitespace-nowrap">
                            <span v-if="!summary.perspectives[pKey]?.excluded" class="font-bold text-gray-800 dark:text-gray-200">
                                {{ summary.perspectives[pKey]?.average_percentage_rounded }}%
                            </span>
                            <span v-else class="text-gray-400 italic">
                                —
                                <span class="block text-[10px] text-amber-600">ክብደት ተከፋፍሏል</span>
                            </span>
                        </td>

                        <!-- BSC Score -->
                        <td class="py-3 px-3 text-center whitespace-nowrap font-black">
                            <span v-if="!summary.perspectives[pKey]?.excluded" class="text-indigo-600 dark:text-indigo-400 text-sm">
                                {{ summary.perspectives[pKey]?.score_summary_rounded }}
                            </span>
                            <span v-else class="text-gray-400 italic font-normal">
                                —
                            </span>
                        </td>

                        <!-- Status / Rating chip -->
                        <td class="py-3 px-4 text-center whitespace-nowrap">
                            <span
                                v-if="!summary.perspectives[pKey]?.excluded && summary.perspectives[pKey]?.rating"
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold border shadow-xs"
                                :class="getRatingBadgeClasses(summary.perspectives[pKey]?.rating?.key)"
                            >
                                {{ getRatingLabelText(summary.perspectives[pKey]?.rating) }}
                            </span>
                            <span v-else class="text-gray-400 italic text-[11px]">
                                አልተገመገመም
                            </span>
                        </td>
                    </tr>
                </tbody>

                <!-- Total Weighted Score Row -->
                <tfoot class="bg-indigo-50 dark:bg-gray-700/80 border-t-2 border-indigo-200 dark:border-indigo-800 text-xs font-bold text-gray-900 dark:text-white">
                    <tr>
                        <td class="py-3.5 px-4 font-black">
                            ጠቅላላ ክብደት የተሰጠው ውጤት (Total Weighted Score)
                        </td>
                        <td class="py-3.5 px-3 text-center font-black">
                            {{ totalWeight }}%
                        </td>
                        <td class="py-3.5 px-3 text-center font-black text-indigo-700 dark:text-indigo-300">
                            {{ summary.weighted_percentage_rounded }}%
                        </td>
                        <td class="py-3.5 px-3 text-center font-black text-indigo-700 dark:text-indigo-300 text-sm">
                            {{ summary.weighted_score_rounded }} / 5.0
                        </td>
                        <td class="py-3.5 px-4 text-center whitespace-nowrap">
                            <span
                                class="inline-flex items-center px-3 py-1 rounded-full text-xs font-black border shadow-xs"
                                :class="getRatingBadgeClasses(summary.rating?.key)"
                            >
                                {{ getRatingLabelText(summary.rating) }}
                            </span>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</template>
