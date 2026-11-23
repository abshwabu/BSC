<?php

namespace App\Services\Export;

use App\Models\Report;
use App\Services\Bsc\GateEvaluator;
use App\Services\Bsc\ScoreCalculator;

class ReportDataPreparer
{
    /**
     * Prepare complete report presentation data for PDF, DOCX, and HTML preview.
     *
     * @return array<string, mixed>
     */
    public static function prepare(Report $report, string $lang = 'bilingual', bool $showSampleNote = true): array
    {
        $report->loadMissing([
            'reportMembers.member',
            'reportMembers.activities' => fn ($q) => $q->orderBy('sort_order')->orderBy('id'),
            'decision',
        ]);

        $sortedMembers = $report->reportMembers->sortBy(fn ($rm) => $rm->member?->sort_order ?? 0)->values();

        $memberData = [];
        $rawMemberSummaries = [];

        foreach ($sortedMembers as $index => $rm) {
            $member = $rm->member;

            // Prepare activity inputs for scoring calculator
            $activitiesArray = $rm->activities->map(fn ($act) => [
                'id' => $act->id,
                'perspective' => $act->perspective,
                'target_value' => $act->target_value,
                'actual_value' => $act->actual_value,
                'direction' => $act->direction,
            ])->toArray();

            $weights = $rm->weights ?? ($member?->default_weights ?? []);
            $summary = ScoreCalculator::calculateMemberSummary($activitiesArray, $weights);
            $rawMemberSummaries[] = $summary;

            // Formatted activity rows for tables
            $formattedActivities = [];
            foreach ($rm->activities as $actIdx => $act) {
                $pct = ScoreCalculator::calculateAchievementPercentage(
                    $act->target_value,
                    $act->actual_value,
                    $act->direction
                );

                $actScore = ScoreCalculator::calculateScoreFromPercentage($pct);
                $perspectiveScoreStr = ScoreCalculator::formatPerspectiveScoreString(
                    $act->perspective,
                    $actScore,
                    $lang === 'en' ? 'en' : 'am'
                );

                $formattedActivities[] = [
                    'index' => $actIdx + 1,
                    'activity_text' => $act->activity_text,
                    'kpi_text' => $act->kpi_text,
                    'target_label' => $act->target_label ?: ($act->target_value !== null ? (string) $act->target_value : '—'),
                    'actual_label' => $act->actual_label ?: ($act->actual_value !== null ? (string) $act->actual_value : '—'),
                    'direction' => $act->direction,
                    'perspective' => $act->perspective,
                    'achievement_percentage' => round($pct, 1),
                    'perspective_score_str' => $perspectiveScoreStr,
                ];
            }

            $memberData[] = [
                'section_number' => $index + 1,
                'report_member' => $rm,
                'member' => $member,
                'name' => $member?->name ?? 'Co-Founder',
                'role_title_am' => $member?->role_title_am ?? '',
                'role_title_en' => $member?->role_title_en ?? '',
                'activities' => $formattedActivities,
                'summary' => $summary,
                'readiness_note' => $rm->readiness_note,
                'achievements' => $rm->achievements,
                'challenges' => $rm->challenges,
                'next_steps' => $rm->next_steps,
                'signed' => (bool) $rm->signed,
            ];
        }

        $companySummary = ScoreCalculator::calculateCompanySummary($rawMemberSummaries);

        // Gate evaluation
        $decision = $report->decision;
        $gateEvaluation = GateEvaluator::evaluate(
            plcGateEnabled: (bool) ($decision?->plc_gate_enabled ?? true),
            minCustomers: $decision?->min_paying_customers ?? 20,
            minMrr: $decision?->min_mrr !== null ? (float) $decision->min_mrr : 150000.0,
            actualCustomers: $decision?->actual_paying_customers,
            actualMrr: $decision?->actual_mrr !== null ? (float) $decision->actual_mrr : null
        );

        // Decisions list
        $decisionsList = $decision?->decisions_list ?: GateEvaluator::getDefaultDecisions($lang === 'en' ? 'en' : 'am');

        return [
            'report' => $report,
            'company_name' => $report->company_name ?: 'EthioTech Solutions',
            'period_label' => $report->period_label,
            'year' => $report->year,
            'report_date' => $report->report_date ? $report->report_date->format('Y-m-d') : date('Y-m-d'),
            'lang' => $lang, // 'am' | 'en' | 'bilingual'
            'show_sample_note' => $showSampleNote,
            'members' => $memberData,
            'company_summary' => $companySummary,
            'decision' => $decision,
            'gate_evaluation' => $gateEvaluation,
            'decisions_list' => $decisionsList,
            'status' => $report->status,
        ];
    }
}
