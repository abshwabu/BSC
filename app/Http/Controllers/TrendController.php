<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Report;
use App\Services\Bsc\ScoreCalculator;
use Inertia\Inertia;
use Inertia\Response;

class TrendController extends Controller
{
    /**
     * Display historical performance trends across quarterly reports.
     */
    public function index(): Response
    {
        // Fetch up to 4 most recent reports, ordered chronologically (oldest to newest for trend display)
        $reports = Report::with(['reportMembers.member', 'reportMembers.activities'])
            ->orderBy('year', 'asc')
            ->orderBy('period_label', 'asc')
            ->orderBy('id', 'asc')
            ->take(4)
            ->get()
            ->map(function (Report $report) {
                $memberSummaries = [];
                $memberTrendData = [];

                foreach ($report->reportMembers as $rm) {
                    $summary = ScoreCalculator::calculateMemberSummary(
                        $rm->activities->toArray(),
                        $rm->weights ?? []
                    );

                    $memberSummaries[] = $summary;

                    $memberTrendData[] = [
                        'report_member_id' => $rm->id,
                        'member_id' => $rm->member_id,
                        'name' => $rm->member?->name ?? 'Co-Founder',
                        'position_key' => $rm->member?->position_key ?? 'custom',
                        'role_title_am' => $rm->member?->role_title_am ?? '',
                        'role_title_en' => $rm->member?->role_title_en ?? '',
                        'weighted_score' => $summary['weighted_score_rounded'],
                        'weighted_percentage' => $summary['weighted_percentage_rounded'],
                        'rating' => $summary['rating'],
                        'activities_count' => count($rm->activities),
                    ];
                }

                $companySummary = ScoreCalculator::calculateCompanySummary($memberSummaries);

                return [
                    'id' => $report->id,
                    'title' => $report->title,
                    'period_label' => $report->period_label,
                    'year' => $report->year,
                    'report_date' => $report->report_date?->format('Y-m-d'),
                    'status' => $report->status,
                    'company_name' => $report->company_name,
                    'company_score' => $companySummary['company_weighted_score_rounded'],
                    'company_percentage' => $companySummary['company_weighted_percentage_rounded'],
                    'company_rating' => $companySummary['rating'],
                    'members' => $memberTrendData,
                ];
            });

        $allMembers = Member::orderBy('sort_order')->get(['id', 'name', 'role_title_am', 'role_title_en', 'position_key']);

        return Inertia::render('Trends/Index', [
            'reports' => $reports,
            'members' => $allMembers,
        ]);
    }
}
