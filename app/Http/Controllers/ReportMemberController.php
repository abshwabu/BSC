<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Report;
use App\Models\ReportMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportMemberController extends Controller
{
    /**
     * Update report member details and synchronize activities.
     */
    public function update(Request $request, Report $report, ReportMember $reportMember): JsonResponse
    {
        // Guard check
        if ($reportMember->report_id !== $report->id) {
            abort(404, 'Report member not found for this report.');
        }

        $validated = $request->validate([
            'achievements' => 'nullable|string',
            'challenges' => 'nullable|string',
            'next_steps' => 'nullable|string',
            'signed' => 'nullable|boolean',
            'weights' => 'nullable|array',
            'activities' => 'nullable|array',
            'activities.*.id' => 'nullable|integer',
            'activities.*.sort_order' => 'nullable|integer',
            'activities.*.activity_text' => 'nullable|string',
            'activities.*.kpi_text' => 'nullable|string',
            'activities.*.target_value' => 'nullable|numeric',
            'activities.*.target_label' => 'nullable|string',
            'activities.*.actual_value' => 'nullable|numeric',
            'activities.*.actual_label' => 'nullable|string',
            'activities.*.direction' => 'required|in:higher_better,lower_better',
            'activities.*.perspective' => 'required|in:financial,customers,internal,learning',
        ]);

        if (isset($validated['weights']) && ! empty($validated['weights'])) {
            $sum = array_sum(array_map('floatval', $validated['weights']));
            if (abs($sum - 100.0) > 0.01) {
                return response()->json([
                    'message' => 'የክብደቶች ድምር በትክክል 100% መሆን አለበት። የአሁን ድምር፡ '.$sum.'%',
                    'errors' => [
                        'weights' => ['The sum of perspective weights must equal exactly 100%. Current sum: '.$sum.'%'],
                    ],
                ], 422);
            }
        }

        DB::transaction(function () use ($reportMember, $validated) {
            $reportMember->update([
                'achievements' => $validated['achievements'] ?? $reportMember->achievements,
                'challenges' => $validated['challenges'] ?? $reportMember->challenges,
                'next_steps' => $validated['next_steps'] ?? $reportMember->next_steps,
                'signed' => $validated['signed'] ?? $reportMember->signed,
                'weights' => $validated['weights'] ?? $reportMember->weights,
            ]);

            $incomingActivities = $validated['activities'] ?? [];
            $existingActivityIds = $reportMember->activities()->pluck('id')->toArray();
            $keptActivityIds = [];

            foreach ($incomingActivities as $index => $actData) {
                $activityId = $actData['id'] ?? null;
                $sortOrder = $actData['sort_order'] ?? ($index + 1);

                $payload = [
                    'sort_order' => $sortOrder,
                    'activity_text' => $actData['activity_text'] ?? '',
                    'kpi_text' => $actData['kpi_text'] ?? '',
                    'target_value' => isset($actData['target_value']) && $actData['target_value'] !== '' ? (float) $actData['target_value'] : null,
                    'target_label' => $actData['target_label'] ?? null,
                    'actual_value' => isset($actData['actual_value']) && $actData['actual_value'] !== '' ? (float) $actData['actual_value'] : null,
                    'actual_label' => $actData['actual_label'] ?? null,
                    'direction' => $actData['direction'] ?? 'higher_better',
                    'perspective' => $actData['perspective'] ?? 'customers',
                ];

                if ($activityId && in_array($activityId, $existingActivityIds, true)) {
                    Activity::where('id', $activityId)
                        ->where('report_member_id', $reportMember->id)
                        ->update($payload);
                    $keptActivityIds[] = $activityId;
                } else {
                    $newActivity = $reportMember->activities()->create($payload);
                    $keptActivityIds[] = $newActivity->id;
                }
            }

            // Delete activities that were removed
            $toDelete = array_diff($existingActivityIds, $keptActivityIds);
            if (! empty($toDelete)) {
                Activity::whereIn('id', $toDelete)->delete();
            }
        });

        $reportMember->load([
            'member',
            'activities' => fn ($q) => $q->orderBy('sort_order')->orderBy('id'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Report member updated successfully.',
            'report_member' => $reportMember,
        ]);
    }
}
