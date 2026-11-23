<script setup>
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    reports: {
        type: Array,
        required: true,
    },
    members: {
        type: Array,
        default: () => [],
    },
});

const { t, locale } = useI18n();

const activeTab = ref('score'); // 'score' | 'percentage'
const selectedSeries = ref('all'); // 'all' | 'company' | member_id

const memberColors = {
    sales: '#10b981', // emerald
    marketing: '#8b5cf6', // purple
    tech: '#3b82f6', // blue
    custom: '#f59e0b', // amber
    company: '#4f46e5', // indigo
};

// Distinct list of member IDs present across the reports
const presentMembers = computed(() => {
    const map = new Map();
    props.reports.forEach((rep) => {
        (rep.members || []).forEach((m) => {
            if (!map.has(m.member_id)) {
                map.set(m.member_id, {
                    id: m.member_id,
                    name: m.name,
                    position_key: m.position_key,
                    role_title: locale.value === 'am' ? m.role_title_am : m.role_title_en,
                    color: memberColors[m.position_key] || '#6b7280',
                });
            }
        });
    });
    return Array.from(map.values());
});

// SVG Chart Coordinates Calculation
const chartWidth = 720;
const chartHeight = 260;
const padding = { top: 30, right: 40, bottom: 40, left: 50 };

const innerWidth = chartWidth - padding.left - padding.right;
const innerHeight = chartHeight - padding.top - padding.bottom;

// X coordinates for the reports (0 to N-1)
function getX(index) {
    if (props.reports.length <= 1) {
        return padding.left + innerWidth / 2;
    }
    return padding.left + (index / (props.reports.length - 1)) * innerWidth;
}

// Y coordinates for score (0.0 to 5.0)
function getYScore(score) {
    const val = Math.max(0, Math.min(5.0, Number(score) || 0));
    return padding.top + innerHeight - (val / 5.0) * innerHeight;
}

// Y coordinates for percentage (0 to 120%)
function getYPct(pct) {
    const val = Math.max(0, Math.min(120, Number(pct) || 0));
    return padding.top + innerHeight - (val / 120.0) * innerHeight;
}

// Company points
const companyPoints = computed(() => {
    return props.reports.map((r, i) => ({
        x: getX(i),
        yScore: getYScore(r.company_score),
        yPct: getYPct(r.company_percentage),
        label: `${r.period_label} ${r.year}`,
        score: r.company_score,
        pct: r.company_percentage,
    }));
});

// Member series lines
const memberSeries = computed(() => {
    return presentMembers.value.map((m) => {
        const points = props.reports.map((r, i) => {
            const mData = (r.members || []).find((item) => item.member_id === m.id);
            const score = mData ? mData.weighted_score : 0;
            const pct = mData ? mData.weighted_percentage : 0;
            return {
                x: getX(i),
                yScore: getYScore(score),
                yPct: getYPct(pct),
                score,
                pct,
                hasData: Boolean(mData),
                reportTitle: `${r.period_label} ${r.year}`,
            };
        });

        return {
            member: m,
            points,
            pathScore: points.map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x} ${p.yScore}`).join(' '),
            pathPct: points.map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x} ${p.yPct}`).join(' '),
        };
    });
});

const companyPathScore = computed(() => {
    return companyPoints.value.map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x} ${p.yScore}`).join(' ');
});

const companyPathPct = computed(() => {
    return companyPoints.value.map((p, i) => `${i === 0 ? 'M' : 'L'} ${p.x} ${p.yPct}`).join(' ');
});

// Quarter over quarter change helper
function getDiff(current, prev) {
    if (prev === undefined || prev === null) return null;
    const diff = current - prev;
    return Math.round(diff * 100) / 100;
}
</script>

<template>
    <Head :title="$t('trends_page.title')" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-xl font-bold leading-tight text-gray-800 dark:text-gray-100 flex items-center gap-2">
                        <span class="text-2xl">📈</span>
                        <span>{{ $t('trends_page.title') }}</span>
                    </h2>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        {{ $t('trends_page.subtitle') }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Link
                        :href="route('reports.index')"
                        class="px-3 py-1.5 rounded-lg text-xs font-semibold bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-200 hover:bg-gray-50 shadow-xs"
                    >
                        ← ወደ ሪፖርቶች
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <!-- Check if reports exist -->
                <div v-if="reports.length === 0" class="bg-white dark:bg-gray-800 rounded-xl border border-gray-200 dark:border-gray-700 p-12 text-center space-y-3">
                    <span class="text-5xl block">📊</span>
                    <h3 class="text-sm font-bold text-gray-800 dark:text-gray-200">
                        {{ $t('trends_page.no_data') }}
                    </h3>
                    <p class="text-xs text-gray-500 max-w-sm mx-auto">
                        የአዝማሚያ ገበታዎችን ለመመልከት በመጀመሪያ አዲስ ሪፖርት አዘጋጅተው ያጠናቁ።
                    </p>
                    <div class="pt-2">
                        <Link
                            :href="route('reports.index')"
                            class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-xs font-bold rounded-lg shadow-sm"
                        >
                            ➕ አዲስ ሪፖርት አዘጋጅ
                        </Link>
                    </div>
                </div>

                <div v-else class="space-y-6">
                    <!-- Chart Container Card -->
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6 space-y-4">
                        <!-- Chart Controls Header -->
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-gray-100 dark:border-gray-700 pb-4">
                            <!-- Toggle tabs: Score vs Percentage -->
                            <div class="inline-flex p-1 bg-gray-100 dark:bg-gray-700/60 rounded-xl text-xs font-bold">
                                <button
                                    type="button"
                                    @click="activeTab = 'score'"
                                    class="px-4 py-1.5 rounded-lg transition-all"
                                    :class="activeTab === 'score'
                                        ? 'bg-white dark:bg-gray-800 text-indigo-600 dark:text-indigo-400 shadow-xs'
                                        : 'text-gray-600 dark:text-gray-300 hover:text-gray-900'"
                                >
                                    ⭐ የ BSC ውጤት (0.0 - 5.0)
                                </button>
                                <button
                                    type="button"
                                    @click="activeTab = 'percentage'"
                                    class="px-4 py-1.5 rounded-lg transition-all"
                                    :class="activeTab === 'percentage'
                                        ? 'bg-white dark:bg-gray-800 text-indigo-600 dark:text-indigo-400 shadow-xs'
                                        : 'text-gray-600 dark:text-gray-300 hover:text-gray-900'"
                                >
                                    🎯 የአፈፃፀም መቶኛ (%)
                                </button>
                            </div>

                            <!-- Legend / Series Toggles -->
                            <div class="flex flex-wrap items-center gap-3 text-xs">
                                <div class="flex items-center gap-1.5">
                                    <span class="w-3 h-3 rounded-full bg-indigo-600 shadow-xs"></span>
                                    <span class="font-bold text-gray-800 dark:text-gray-200">የኩባንያ አማካይ</span>
                                </div>
                                <div
                                    v-for="m in presentMembers"
                                    :key="m.id"
                                    class="flex items-center gap-1.5"
                                >
                                    <span
                                        class="w-3 h-3 rounded-full shadow-xs"
                                        :style="{ backgroundColor: m.color }"
                                    ></span>
                                    <span class="text-gray-700 dark:text-gray-300 font-medium">
                                        {{ m.name }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- SVG Chart Render Area -->
                        <div class="overflow-x-auto">
                            <div class="min-w-[640px]">
                                <svg
                                    :viewBox="`0 0 ${chartWidth} ${chartHeight}`"
                                    class="w-full h-auto text-xs select-none"
                                >
                                    <!-- Grid lines & Y-axis labels -->
                                    <g class="grid-lines opacity-20 dark:opacity-30">
                                        <template v-if="activeTab === 'score'">
                                            <!-- Threshold bands: 3.0, 4.0, 4.5, 5.0 -->
                                            <line
                                                v-for="val in [0, 1, 2, 3, 4, 5]"
                                                :key="val"
                                                :x1="padding.left"
                                                :y1="getYScore(val)"
                                                :x2="chartWidth - padding.right"
                                                :y2="getYScore(val)"
                                                stroke="currentColor"
                                                stroke-dasharray="3 3"
                                            />
                                        </template>
                                        <template v-else>
                                            <!-- Pct grid: 0, 30, 60, 90, 120 -->
                                            <line
                                                v-for="val in [0, 30, 60, 90, 120]"
                                                :key="val"
                                                :x1="padding.left"
                                                :y1="getYPct(val)"
                                                :x2="chartWidth - padding.right"
                                                :y2="getYPct(val)"
                                                stroke="currentColor"
                                                stroke-dasharray="3 3"
                                            />
                                        </template>
                                    </g>

                                    <!-- Y-Axis Labels -->
                                    <g class="text-[10px] fill-gray-400 dark:fill-gray-500 font-mono">
                                        <template v-if="activeTab === 'score'">
                                            <text
                                                v-for="val in [0, 1, 2, 3, 4, 5]"
                                                :key="val"
                                                :x="padding.left - 10"
                                                :y="getYScore(val) + 3"
                                                text-anchor="end"
                                            >
                                                {{ val }}.0
                                            </text>
                                        </template>
                                        <template v-else>
                                            <text
                                                v-for="val in [0, 30, 60, 90, 120]"
                                                :key="val"
                                                :x="padding.left - 10"
                                                :y="getYPct(val) + 3"
                                                text-anchor="end"
                                            >
                                                {{ val }}%
                                            </text>
                                        </template>
                                    </g>

                                    <!-- X-Axis Quarter Labels -->
                                    <g class="text-xs fill-gray-600 dark:fill-gray-300 font-bold">
                                        <text
                                            v-for="(r, idx) in reports"
                                            :key="r.id"
                                            :x="getX(idx)"
                                            :y="chartHeight - 12"
                                            text-anchor="middle"
                                        >
                                            {{ r.period_label }} {{ r.year }}
                                        </text>
                                    </g>

                                    <!-- Individual Member Trend Lines -->
                                    <g v-for="s in memberSeries" :key="s.member.id">
                                        <path
                                            :d="activeTab === 'score' ? s.pathScore : s.pathPct"
                                            fill="none"
                                            :stroke="s.member.color"
                                            stroke-width="2"
                                            stroke-dasharray="4 2"
                                            class="opacity-70 hover:opacity-100 transition-opacity"
                                        />
                                        <!-- Data points -->
                                        <circle
                                            v-for="(pt, pIdx) in s.points"
                                            :key="pIdx"
                                            :cx="pt.x"
                                            :cy="activeTab === 'score' ? pt.yScore : pt.yPct"
                                            r="4"
                                            :fill="s.member.color"
                                            class="cursor-pointer"
                                        >
                                            <title>{{ s.member.name }} ({{ pt.reportTitle }}): {{ activeTab === 'score' ? pt.score + ' / 5.0' : pt.pct + '%' }}</title>
                                        </circle>
                                    </g>

                                    <!-- Consolidated Company Average Line (Prominent solid indigo) -->
                                    <path
                                        :d="activeTab === 'score' ? companyPathScore : companyPathPct"
                                        fill="none"
                                        stroke="#4f46e5"
                                        stroke-width="3.5"
                                        stroke-linecap="round"
                                        class="shadow-sm"
                                    />

                                    <!-- Company Data Points & Score Badges -->
                                    <g v-for="(pt, idx) in companyPoints" :key="idx">
                                        <circle
                                            :cx="pt.x"
                                            :cy="activeTab === 'score' ? pt.yScore : pt.yPct"
                                            r="6"
                                            fill="#4f46e5"
                                            stroke="#ffffff"
                                            stroke-width="2"
                                        />
                                        <!-- Point label badge -->
                                        <text
                                            :x="pt.x"
                                            :y="(activeTab === 'score' ? pt.yScore : pt.yPct) - 12"
                                            text-anchor="middle"
                                            class="text-[11px] font-black fill-indigo-700 dark:fill-indigo-300"
                                        >
                                            {{ activeTab === 'score' ? pt.score : pt.pct + '%' }}
                                        </text>
                                    </g>
                                </svg>
                            </div>
                        </div>
                    </div>

                    <!-- Quarter-over-Quarter Comparison Table -->
                    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                        <div class="p-5 border-b border-gray-100 dark:border-gray-700">
                            <h3 class="font-bold text-gray-900 dark:text-white text-base flex items-center gap-2">
                                <span>📋</span>
                                <span>{{ $t('trends_page.member_comparison') }}</span>
                            </h3>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-xs">
                                <thead class="bg-gray-50 dark:bg-gray-700/50 text-gray-600 dark:text-gray-300 uppercase font-bold tracking-wider">
                                    <tr>
                                        <th class="py-3 px-4 text-left">{{ $t('trends_page.quarter') }}</th>
                                        <th class="py-3 px-4 text-center bg-indigo-50/70 dark:bg-indigo-950/30 text-indigo-800 dark:text-indigo-300">
                                            🏢 {{ $t('trends_page.company_avg') }}
                                        </th>
                                        <th
                                            v-for="m in presentMembers"
                                            :key="m.id"
                                            class="py-3 px-4 text-center"
                                        >
                                            <div class="flex flex-col items-center">
                                                <span class="font-bold text-gray-900 dark:text-white">{{ m.name }}</span>
                                                <span class="text-[10px] font-normal text-gray-400">{{ m.role_title }}</span>
                                            </div>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr
                                        v-for="(rep, idx) in reports"
                                        :key="rep.id"
                                        class="hover:bg-gray-50/80 dark:hover:bg-gray-750 transition-colors"
                                    >
                                        <!-- Quarter Title -->
                                        <td class="py-3.5 px-4 font-bold text-gray-900 dark:text-white whitespace-nowrap">
                                            <div class="flex items-center gap-2">
                                                <span class="text-base">📅</span>
                                                <div>
                                                    <div>{{ rep.period_label }} {{ rep.year }}</div>
                                                    <div class="text-[10px] text-gray-400 font-normal">{{ rep.title }}</div>
                                                </div>
                                            </div>
                                        </td>

                                        <!-- Company Score & Difference -->
                                        <td class="py-3.5 px-4 text-center bg-indigo-50/40 dark:bg-indigo-950/20 whitespace-nowrap">
                                            <div class="flex flex-col items-center">
                                                <div class="flex items-center gap-1.5 font-black text-indigo-700 dark:text-indigo-300 text-sm">
                                                    <span>{{ rep.company_score }}</span>
                                                    <span class="text-[10px] font-normal text-gray-500">({{ rep.company_percentage }}%)</span>
                                                </div>
                                                <!-- QoQ Change -->
                                                <span
                                                    v-if="idx > 0"
                                                    class="text-[10px] font-bold"
                                                    :class="getDiff(rep.company_score, reports[idx - 1].company_score) >= 0 ? 'text-emerald-600' : 'text-rose-600'"
                                                >
                                                    {{ getDiff(rep.company_score, reports[idx - 1].company_score) >= 0 ? '▲ +' : '▼ ' }}
                                                    {{ getDiff(rep.company_score, reports[idx - 1].company_score) }}
                                                </span>
                                            </div>
                                        </td>

                                        <!-- Founders Columns -->
                                        <td
                                            v-for="m in presentMembers"
                                            :key="m.id"
                                            class="py-3.5 px-4 text-center whitespace-nowrap"
                                        >
                                            <template v-if="(rep.members || []).find(x => x.member_id === m.id)">
                                                <div class="flex flex-col items-center">
                                                    <div class="font-bold text-gray-800 dark:text-gray-200 text-xs">
                                                        <span>{{ (rep.members.find(x => x.member_id === m.id)).weighted_score }}</span>
                                                        <span class="text-[10px] font-normal text-gray-400 ml-1">
                                                            ({{ (rep.members.find(x => x.member_id === m.id)).weighted_percentage }}%)
                                                        </span>
                                                    </div>
                                                    <!-- Member QoQ Change -->
                                                    <span
                                                        v-if="idx > 0 && reports[idx - 1].members?.find(x => x.member_id === m.id)"
                                                        class="text-[10px] font-bold"
                                                        :class="getDiff(
                                                            rep.members.find(x => x.member_id === m.id).weighted_score,
                                                            reports[idx - 1].members.find(x => x.member_id === m.id).weighted_score
                                                        ) >= 0 ? 'text-emerald-600' : 'text-rose-600'"
                                                    >
                                                        {{ getDiff(
                                                            rep.members.find(x => x.member_id === m.id).weighted_score,
                                                            reports[idx - 1].members.find(x => x.member_id === m.id).weighted_score
                                                        ) >= 0 ? '▲ +' : '▼ ' }}
                                                        {{ getDiff(
                                                            rep.members.find(x => x.member_id === m.id).weighted_score,
                                                            reports[idx - 1].members.find(x => x.member_id === m.id).weighted_score
                                                        ) }}
                                                    </span>
                                                </div>
                                            </template>
                                            <span v-else class="text-gray-400 italic">—</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
