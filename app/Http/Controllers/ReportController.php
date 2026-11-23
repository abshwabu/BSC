<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Report;
use App\Services\Bsc\ScoreCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    /**
     * Display a listing of reports.
     */
    public function index(): Response
    {
        $reports = Report::with(['reportMembers.activities'])
            ->latest('id')
            ->get()
            ->map(function (Report $report) {
                $memberSummaries = [];
                foreach ($report->reportMembers as $rm) {
                    $memberSummaries[] = ScoreCalculator::calculateMemberSummary(
                        $rm->activities->toArray(),
                        $rm->weights ?? []
                    );
                }

                $companySummary = ScoreCalculator::calculateCompanySummary($memberSummaries);

                return [
                    'id' => $report->id,
                    'title' => $report->title,
                    'period_label' => $report->period_label,
                    'period_type' => $report->period_type,
                    'year' => $report->year,
                    'report_date' => $report->report_date?->format('Y-m-d'),
                    'status' => $report->status,
                    'company_name' => $report->company_name,
                    'company_score' => $companySummary['company_weighted_score_rounded'],
                    'company_percentage' => $companySummary['company_weighted_percentage_rounded'],
                    'company_rating' => $companySummary['rating'],
                    'created_at' => $report->created_at->format('Y-m-d'),
                ];
            });

        return Inertia::render('Reports/Index', [
            'reports' => $reports,
        ]);
    }

    /**
     * Store a newly created report in storage.
     * Auto-attaches default members and copies previous-quarter activities if available.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'period_label' => 'required|string|max:50',
            'period_type' => 'nullable|string|max:50',
            'year' => 'required|integer|min:2000|max:2100',
            'report_date' => 'nullable|date',
            'company_name' => 'nullable|string|max:255',
        ]);

        $newReport = DB::transaction(function () use ($validated) {
            $priorReport = Report::with(['reportMembers.activities', 'decision'])->latest('id')->first();

            $report = Report::create([
                'title' => $validated['title'],
                'period_label' => $validated['period_label'],
                'period_type' => $validated['period_type'] ?? 'quarter',
                'year' => $validated['year'],
                'report_date' => $validated['report_date'] ?? null,
                'status' => 'draft',
                'company_name' => $validated['company_name'] ?? 'EthioTech Solutions',
            ]);

            $members = Member::orderBy('sort_order')->get();

            // If members haven't been seeded, run seeder
            if ($members->isEmpty()) {
                artisan('db:seed', ['--class' => 'MemberSeeder']);
                $members = Member::orderBy('sort_order')->get();
            }

            foreach ($members as $member) {
                $priorMember = $priorReport?->reportMembers->firstWhere('member_id', $member->id);

                $reportMember = $report->reportMembers()->create([
                    'member_id' => $member->id,
                    'weights' => $priorMember?->weights ?? $member->default_weights,
                    'achievements' => null,
                    'challenges' => null,
                    'next_steps' => null,
                    'signed' => false,
                ]);

                // Copy activities from prior quarter if available (targets kept, actuals blank)
                if ($priorMember && $priorMember->activities->isNotEmpty()) {
                    foreach ($priorMember->activities as $act) {
                        $reportMember->activities()->create([
                            'sort_order' => $act->sort_order,
                            'activity_text' => $act->activity_text,
                            'kpi_text' => $act->kpi_text,
                            'target_value' => $act->target_value,
                            'target_label' => $act->target_label,
                            'actual_value' => null,
                            'actual_label' => null,
                            'direction' => $act->direction,
                            'perspective' => $act->perspective,
                        ]);
                    }
                }
            }

            // Create decision record
            $report->decision()->create([
                'plc_gate_enabled' => $priorReport?->decision?->plc_gate_enabled ?? true,
                'min_paying_customers' => $priorReport?->decision?->min_paying_customers ?? 20,
                'min_mrr' => $priorReport?->decision?->min_mrr ?? 150000.00,
                'decision_text_am' => $priorReport?->decision?->decision_text_am ?? 'ወደ ኃላፊነቱ የተወሰነ የግል ማህበር (PLC) ለመሸጋገር የተጣሉ ቅድመ ሁኔታዎች ተሟልተዋል።',
                'expansion_text_am' => $priorReport?->decision?->expansion_text_am ?? 'በሚቀጥለው ሩብ ዓመት ተጨማሪ የሽያጭ ባለሙያ መቅጠርና ቢሮ ማስፋፋት።',
            ]);

            return $report;
        });

        return redirect()->route('reports.edit', $newReport->id)
            ->with('success', 'Report created successfully.');
    }

    /**
     * Show the entry wizard for editing the report.
     */
    public function edit(Report $report): Response
    {
        $report->load([
            'reportMembers.member',
            'reportMembers.activities' => fn ($q) => $q->orderBy('sort_order')->orderBy('id'),
            'decision',
        ]);

        // Ensure reportMembers are sorted by member sort_order
        $sortedMembers = $report->reportMembers->sortBy(fn ($rm) => $rm->member?->sort_order ?? 0)->values();
        $report->setRelation('reportMembers', $sortedMembers);

        // Ensure decision exists
        if (! $report->decision) {
            $report->decision()->create([
                'plc_gate_enabled' => true,
                'min_paying_customers' => 20,
                'min_mrr' => 150000.00,
                'decision_text_am' => 'ወደ ኃላፊነቱ የተወሰነ የግል ማህበር (PLC) ለመሸጋገር የተጣሉ ቅድመ ሁኔታዎች ተሟልተዋል።',
                'expansion_text_am' => 'በሚቀጥለው ሩብ ዓመት ተጨማሪ የሽያጭ ባለሙያ መቅጠርና ቢሮ ማስፋፋት።',
            ]);
            $report->load('decision');
        }

        return Inertia::render('Reports/Edit', [
            'report' => $report,
        ]);
    }

    /**
     * Update top-level report properties (status, notes, etc.).
     */
    public function update(Request $request, Report $report): RedirectResponse
    {
        $validated = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'period_label' => 'sometimes|required|string|max:50',
            'year' => 'sometimes|required|integer|min:2000|max:2100',
            'report_date' => 'nullable|date',
            'status' => 'sometimes|required|in:draft,final',
            'company_name' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $report->update($validated);

        return back()->with('success', 'Report updated successfully.');
    }

    /**
     * Duplicate a report for the next quarter.
     */
    public function duplicate(Report $report): RedirectResponse
    {
        $cloned = $report->duplicate();

        return redirect()->route('reports.edit', $cloned->id)
            ->with('success', 'Report duplicated successfully for the next quarter.');
    }

    /**
     * Delete a report.
     */
    public function destroy(Report $report): RedirectResponse
    {
        $report->delete();

        return redirect()->route('reports.index')
            ->with('success', 'Report deleted successfully.');
    }
}
