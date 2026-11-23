<?php

namespace App\Http\Controllers;

use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportDecisionController extends Controller
{
    /**
     * Update the company decision record for the report.
     */
    public function update(Request $request, Report $report): JsonResponse
    {
        $validated = $request->validate([
            'plc_gate_enabled' => 'required|boolean',
            'min_paying_customers' => 'nullable|integer|min:0',
            'min_mrr' => 'nullable|numeric|min:0',
            'actual_paying_customers' => 'nullable|integer|min:0',
            'actual_mrr' => 'nullable|numeric|min:0',
            'decision_text_am' => 'nullable|string',
            'expansion_text_am' => 'nullable|string',
            'decisions_list' => 'nullable|array',
            'decisions_list.*' => 'nullable|string',
        ]);

        $decision = $report->decision()->updateOrCreate(
            ['report_id' => $report->id],
            $validated
        );

        return response()->json([
            'success' => true,
            'message' => 'Report decision updated successfully.',
            'decision' => $decision,
        ]);
    }
}
