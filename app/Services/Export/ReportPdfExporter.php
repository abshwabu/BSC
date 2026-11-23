<?php

namespace App\Services\Export;

use App\Models\Report;
use Illuminate\Http\Response;
use Spatie\LaravelPdf\Facades\Pdf;

class ReportPdfExporter
{
    /**
     * Generate or download the PDF with Browsershot or graceful fallback to printable HTML.
     */
    public static function export(Report $report, string $lang = 'bilingual', bool $showSampleNote = true): Response|\Symfony\Component\HttpFoundation\Response
    {
        $data = ReportDataPreparer::prepare($report, $lang, $showSampleNote);
        $filename = "BSC_Report_{$report->period_label}_{$report->year}.pdf";

        try {
            // Attempt server-side Browsershot rendering and trigger output inside try block
            return Pdf::view('reports.pdf', $data)
                ->format('a4')
                ->name($filename)
                ->download();
        } catch (\Throwable $e) {
            // Graceful fallback to browser print stylesheet
            return response()->view('reports.pdf', array_merge($data, [
                'auto_print' => true,
                'fallback_notice' => true,
            ]))->header('Content-Type', 'text/html; charset=UTF-8');
        }
    }

    /**
     * Render the pure HTML preview page.
     */
    public static function previewHtml(Report $report, string $lang = 'bilingual', bool $showSampleNote = true): Response
    {
        $data = ReportDataPreparer::prepare($report, $lang, $showSampleNote);

        return response()->view('reports.pdf', array_merge($data, [
            'auto_print' => false,
        ]));
    }
}
