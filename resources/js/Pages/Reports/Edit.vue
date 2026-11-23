<script setup>
import { ref, computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import MemberStep from './Partials/MemberStep.vue';
import DecisionStep from './Partials/DecisionStep.vue';
import PreviewStep from './Partials/PreviewStep.vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    report: {
        type: Object,
        required: true,
    },
});

const { t, locale } = useI18n();

// 1: Member 1, 2: Member 2, 3: Member 3, 4: Decision, 5: Preview
const currentStep = ref(1);
const globalSaveStatus = ref('saved');

const members = computed(() => {
    return props.report.reportMembers || [];
});

const currentMember = computed(() => {
    if (currentStep.value >= 1 && currentStep.value <= 3) {
        return members.value[currentStep.value - 1] || null;
    }
    return null;
});

const steps = computed(() => [
    {
        num: 1,
        title: members.value[0]?.member?.name || t('wizard.step_1'),
        subtitle: locale.value === 'en' ? members.value[0]?.member?.role_title_en : members.value[0]?.member?.role_title_am,
        icon: '💼',
    },
    {
        num: 2,
        title: members.value[1]?.member?.name || t('wizard.step_2'),
        subtitle: locale.value === 'en' ? members.value[1]?.member?.role_title_en : members.value[1]?.member?.role_title_am,
        icon: '📣',
    },
    {
        num: 3,
        title: members.value[2]?.member?.name || t('wizard.step_3'),
        subtitle: locale.value === 'en' ? members.value[2]?.member?.role_title_en : members.value[2]?.member?.role_title_am,
        icon: '💻',
    },
    {
        num: 4,
        title: t('wizard.step_4'),
        subtitle: 'PLC & Capital Gate',
        icon: '🏢',
    },
    {
        num: 5,
        title: t('wizard.step_5'),
        subtitle: 'Scorecard & PDF',
        icon: '📑',
    },
]);

function setStep(stepNum) {
    currentStep.value = stepNum;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

function nextStep() {
    if (currentStep.value < 5) {
        setStep(currentStep.value + 1);
    }
}

function prevStep() {
    if (currentStep.value > 1) {
        setStep(currentStep.value - 1);
    }
}

function onMemberSaved(updatedMember) {
    const idx = members.value.findIndex((m) => m.id === updatedMember.id);
    if (idx !== -1) {
        members.value[idx] = updatedMember;
    }
}

function onDecisionSaved(updatedDecision) {
    props.report.decision = updatedDecision;
}
</script>

<template>
    <Head :title="`${report.title} - ${$t('app_name')}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <Link
                        :href="route('reports.index')"
                        class="p-2 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-200 transition-colors"
                        :title="$t('wizard.back_to_reports')"
                    >
                        ◀
                    </Link>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-xl font-bold text-gray-900 dark:text-white leading-tight">
                                {{ report.title }}
                            </h2>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-50 text-indigo-700 dark:bg-indigo-900/40 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
                                {{ report.period_label }} - {{ report.year }}
                            </span>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">
                            {{ report.company_name }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2">
                    <span
                        v-if="globalSaveStatus === 'saved'"
                        class="text-xs text-emerald-600 dark:text-emerald-400 font-semibold flex items-center gap-1"
                    >
                        <span>✓</span>
                        <span>{{ $t('member_step.autosave_status.saved') }}</span>
                    </span>
                    <span
                        v-else-if="globalSaveStatus === 'saving'"
                        class="text-xs text-blue-600 dark:text-blue-400 font-semibold flex items-center gap-1 animate-pulse"
                    >
                        <span>⏳</span>
                        <span>{{ $t('member_step.autosave_status.saving') }}</span>
                    </span>
                    <span
                        v-else-if="globalSaveStatus === 'unsaved'"
                        class="text-xs text-amber-600 dark:text-amber-400 font-semibold flex items-center gap-1"
                    >
                        <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                        <span>{{ $t('member_step.autosave_status.unsaved') }}</span>
                    </span>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8 space-y-6">

                <!-- Stepper Progress Header -->
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-2 overflow-x-auto">
                    <nav class="flex items-center justify-between min-w-[650px] gap-2">
                        <button
                            v-for="step in steps"
                            :key="step.num"
                            type="button"
                            @click="setStep(step.num)"
                            class="flex-1 py-2.5 px-3 rounded-lg text-left transition-all border text-xs"
                            :class="currentStep === step.num
                                ? 'bg-indigo-50 border-indigo-300 text-indigo-700 dark:bg-indigo-900/40 dark:border-indigo-700 dark:text-indigo-300 shadow-xs'
                                : 'bg-transparent border-transparent text-gray-600 hover:bg-gray-50 dark:text-gray-400 dark:hover:bg-gray-700/40'"
                        >
                            <div class="flex items-center gap-2">
                                <span
                                    class="w-6 h-6 rounded-full flex items-center justify-center font-bold text-xs"
                                    :class="currentStep === step.num
                                        ? 'bg-indigo-600 text-white'
                                        : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300'"
                                >
                                    {{ step.num }}
                                </span>
                                <div class="truncate">
                                    <span class="font-bold block truncate">{{ step.title }}</span>
                                    <span class="text-[10px] text-gray-400 block truncate">{{ step.subtitle }}</span>
                                </div>
                            </div>
                        </button>
                    </nav>
                </div>

                <!-- Wizard Step Content -->
                <div>
                    <!-- Step 1 to 3: Co-Founder Member Steps -->
                    <MemberStep
                        v-if="currentStep >= 1 && currentStep <= 3 && currentMember"
                        :key="currentMember.id"
                        :report="report"
                        :report-member="currentMember"
                        @saved="onMemberSaved"
                        @save-status="globalSaveStatus = $event"
                    />

                    <!-- Step 4: Company Decision -->
                    <DecisionStep
                        v-else-if="currentStep === 4"
                        :report="report"
                        @saved="onDecisionSaved"
                        @save-status="globalSaveStatus = $event"
                    />

                    <!-- Step 5: Preview & Export -->
                    <PreviewStep
                        v-else-if="currentStep === 5"
                        :report="report"
                    />
                </div>

                <!-- Stepper Navigation Footer -->
                <div class="flex items-center justify-between pt-4 border-t border-gray-200 dark:border-gray-700">
                    <button
                        type="button"
                        @click="prevStep"
                        :disabled="currentStep === 1"
                        class="px-4 py-2 rounded-lg text-xs font-semibold border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-200 hover:bg-gray-50 disabled:opacity-40 disabled:cursor-not-allowed shadow-sm transition-colors"
                    >
                        ← {{ $t('wizard.previous_step') }}
                    </button>

                    <div class="text-xs text-gray-400 font-semibold">
                        {{ currentStep }} / 5
                    </div>

                    <button
                        v-if="currentStep < 5"
                        type="button"
                        @click="nextStep"
                        class="px-4 py-2 rounded-lg text-xs font-bold bg-indigo-600 text-white hover:bg-indigo-700 shadow-sm transition-colors"
                    >
                        {{ $t('wizard.next_step') }} →
                    </button>
                    <button
                        v-else
                        type="button"
                        @click="setStep(1)"
                        class="px-4 py-2 rounded-lg text-xs font-bold bg-emerald-600 text-white hover:bg-emerald-700 shadow-sm transition-colors"
                    >
                        ✓ {{ $t('wizard.step_1') }}
                    </button>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
