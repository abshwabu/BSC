<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Services\Export\ReportDocxExporter;
use App\Services\Export\ReportPdfExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Spatie\LaravelPdf\PdfBuilder;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportExportController extends Controller
{
    /**
     * Display the full HTML preview styled for A4 print.
     */
    public function preview(Request $request, Report $report): Response
    {
        $lang = $request->query('lang', 'bilingual');
        $showSampleNote = filter_var($request->query('sample_note', true), FILTER_VALIDATE_BOOLEAN);

        return ReportPdfExporter::previewHtml($report, $lang, $showSampleNote);
    }

    /**
     * Export the report as PDF, marking it final.
     */
    public function pdf(Request $request, Report $report): \Symfony\Component\HttpFoundation\Response
    {
        $lang = $request->query('lang', 'bilingual');
        $showSampleNote = filter_var($request->query('sample_note', true), FILTER_VALIDATE_BOOLEAN);

        // Mark report final upon export
        $report->update(['status' => 'final']);

        return ReportPdfExporter::export($report, $lang, $showSampleNote);
    }

    /**
     * Export the report as DOCX, marking it final.
     */
    public function docx(Request $request, Report $report): BinaryFileResponse
    {
        $lang = $request->query('lang', 'bilingual');
        $showSampleNote = filter_var($request->query('sample_note', true), FILTER_VALIDATE_BOOLEAN);

        // Mark report final upon export
        $report->update(['status' => 'final']);

        $filePath = ReportDocxExporter::generate($report, $lang, $showSampleNote);
        $filename = "BSC_Report_{$report->period_label}_{$report->year}.docx";

        return response()->download($filePath, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Unfinalize the report back to draft status to allow editing.
     */
    public function unfinalize(Request $request, Report $report): JsonResponse|RedirectResponse
    {
        $report->update(['status' => 'draft']);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Report reverted to draft status.',
                'status' => 'draft',
            ]);
        }

        return back()->with('success', 'Report reverted to draft status.');
    }
}
