<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ReportJsonController extends Controller
{
    /**
     * Export raw report data as a downloadable JSON document.
     */
    public function export(Report $report): Response
    {
        $report->load([
            'decision',
            'reportMembers.member',
            'reportMembers.activities',
        ]);

        $exportData = [
            'generator' => 'BSC Report Builder',
            'version' => '1.0',
            'exported_at' => now()->toIso8601String(),
            'report' => [
                'title' => $report->title,
                'period_label' => $report->period_label,
                'period_type' => $report->period_type,
                'year' => $report->year,
                'report_date' => $report->report_date?->format('Y-m-d'),
                'status' => $report->status,
                'company_name' => $report->company_name,
                'notes' => $report->notes,
            ],
            'decision' => $report->decision ? [
                'plc_gate_enabled' => (bool) $report->decision->plc_gate_enabled,
                'min_paying_customers' => $report->decision->min_paying_customers,
                'min_mrr' => $report->decision->min_mrr,
                'actual_paying_customers' => $report->decision->actual_paying_customers,
                'actual_mrr' => $report->decision->actual_mrr,
                'decision_text_am' => $report->decision->decision_text_am,
                'expansion_text_am' => $report->decision->expansion_text_am,
                'decisions_list' => $report->decision->decisions_list ?? [],
            ] : null,
            'members' => $report->reportMembers->map(function ($rm) {
                return [
                    'position_key' => $rm->member?->position_key ?? 'custom',
                    'member_name' => $rm->member?->name,
                    'role_title_am' => $rm->member?->role_title_am,
                    'role_title_en' => $rm->member?->role_title_en,
                    'weights' => $rm->weights,
                    'achievements' => $rm->achievements,
                    'challenges' => $rm->challenges,
                    'next_steps' => $rm->next_steps,
                    'signed' => (bool) $rm->signed,
                    'readiness_note' => $rm->readiness_note,
                    'activities' => $rm->activities->map(function ($act) {
                        return [
                            'sort_order' => $act->sort_order,
                            'activity_text' => $act->activity_text,
                            'kpi_text' => $act->kpi_text,
                            'target_value' => $act->target_value !== null ? (float) $act->target_value : null,
                            'target_label' => $act->target_label,
                            'actual_value' => $act->actual_value !== null ? (float) $act->actual_value : null,
                            'actual_label' => $act->actual_label,
                            'direction' => $act->direction,
                            'perspective' => $act->perspective,
                        ];
                    })->values()->toArray(),
                ];
            })->values()->toArray(),
        ];

        $filename = sprintf(
            'bsc-report-%s-%s-%s.json',
            str()->slug($report->company_name ?: 'report'),
            $report->period_label,
            $report->year
        );

        return response()->streamDownload(function () use ($exportData) {
            echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }

    /**
     * Import a report from an uploaded JSON file.
     */
    public function import(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'json_file' => 'required|file|max:5120', // max 5MB
        ]);

        $content = file_get_contents($request->file('json_file')->getRealPath());
        $data = json_decode($content, true);

        if (! is_array($data) || (! isset($data['report']) && ! isset($data['title']))) {
            return redirect()->back()->withErrors([
                'json_file' => 'የተመረጠው የፋይል ይዘት ትክክለኛ የ BSC ሪፖርት JSON አይደለም (Invalid JSON format).',
            ]);
        }

        $reportMeta = $data['report'] ?? $data;

        $importedReport = DB::transaction(function () use ($data, $reportMeta) {
            $report = Report::create([
                'title' => ($reportMeta['title'] ?? 'Imported BSC Report').' (Imported)',
                'period_label' => $reportMeta['period_label'] ?? 'Q1',
                'period_type' => $reportMeta['period_type'] ?? 'quarter',
                'year' => (int) ($reportMeta['year'] ?? date('Y')),
                'report_date' => $reportMeta['report_date'] ?? now()->format('Y-m-d'),
                'status' => 'draft',
                'company_name' => $reportMeta['company_name'] ?? 'EthioTech Solutions',
                'notes' => $reportMeta['notes'] ?? null,
            ]);

            // Save decision if present
            if (isset($data['decision']) && is_array($data['decision'])) {
                $dec = $data['decision'];
                $report->decision()->create([
                    'plc_gate_enabled' => (bool) ($dec['plc_gate_enabled'] ?? true),
                    'min_paying_customers' => $dec['min_paying_customers'] ?? 20,
                    'min_mrr' => $dec['min_mrr'] ?? 500000.00,
                    'actual_paying_customers' => $dec['actual_paying_customers'] ?? null,
                    'actual_mrr' => $dec['actual_mrr'] ?? null,
                    'decision_text_am' => $dec['decision_text_am'] ?? null,
                    'expansion_text_am' => $dec['expansion_text_am'] ?? null,
                    'decisions_list' => $dec['decisions_list'] ?? null,
                ]);
            } else {
                $report->decision()->create([
                    'plc_gate_enabled' => true,
                    'min_paying_customers' => 20,
                    'min_mrr' => 500000.00,
                ]);
            }

            // Available members
            $dbMembers = Member::all();

            $membersList = $data['members'] ?? [];

            // If JSON doesn't specify members, default to DB members
            if (empty($membersList)) {
                $membersList = $dbMembers->map(fn ($m) => ['position_key' => $m->position_key])->toArray();
            }

            foreach ($membersList as $index => $mItem) {
                $positionKey = $mItem['position_key'] ?? null;
                $member = null;

                if ($positionKey) {
                    $member = $dbMembers->firstWhere('position_key', $positionKey);
                }

                if (! $member && isset($mItem['member_name'])) {
                    $member = $dbMembers->firstWhere('name', $mItem['member_name']);
                }

                // If still not matched, take by order
                if (! $member) {
                    $member = $dbMembers->values()->get($index);
                }

                // If no member exists in DB, create one
                if (! $member) {
                    $member = Member::create([
                        'name' => $mItem['member_name'] ?? ('Member '.($index + 1)),
                        'role_title_am' => $mItem['role_title_am'] ?? 'መስራች',
                        'role_title_en' => $mItem['role_title_en'] ?? 'Co-Founder',
                        'position_key' => $positionKey ?? 'custom',
                        'default_weights' => $mItem['weights'] ?? ['financial' => 25, 'customers' => 25, 'internal' => 25, 'learning' => 25],
                        'sort_order' => $index + 1,
                    ]);
                }

                $reportMember = $report->reportMembers()->create([
                    'member_id' => $member->id,
                    'weights' => $mItem['weights'] ?? $member->default_weights,
                    'achievements' => $mItem['achievements'] ?? null,
                    'challenges' => $mItem['challenges'] ?? null,
                    'next_steps' => $mItem['next_steps'] ?? null,
                    'signed' => (bool) ($mItem['signed'] ?? false),
                    'readiness_note' => $mItem['readiness_note'] ?? null,
                ]);

                // Create activities
                if (isset($mItem['activities']) && is_array($mItem['activities'])) {
                    foreach ($mItem['activities'] as $actIdx => $act) {
                        $reportMember->activities()->create([
                            'sort_order' => $act['sort_order'] ?? ($actIdx + 1),
                            'activity_text' => $act['activity_text'] ?? '',
                            'kpi_text' => $act['kpi_text'] ?? '',
                            'target_value' => isset($act['target_value']) && $act['target_value'] !== '' ? $act['target_value'] : null,
                            'target_label' => $act['target_label'] ?? '',
                            'actual_value' => isset($act['actual_value']) && $act['actual_value'] !== '' ? $act['actual_value'] : null,
                            'actual_label' => $act['actual_label'] ?? '',
                            'direction' => $act['direction'] ?? 'higher_better',
                            'perspective' => $act['perspective'] ?? 'customers',
                        ]);
                    }
                }
            }

            return $report;
        });

        return redirect()->route('reports.edit', $importedReport->id)
            ->with('success', 'ሪፖርቱ ከ JSON ፋይል በተሳካ ሁኔታ ተገንብቷል!');
    }
}
