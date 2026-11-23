<?php

namespace App\Services\Export;

use App\Models\Report;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\SimpleType\TblWidth;

class ReportDocxExporter
{
    protected const FONT_FAMILY = 'Noto Sans Ethiopic';

    /**
     * Generate the DOCX file for the given report and return its temporary file path.
     */
    public static function generate(Report $report, string $lang = 'bilingual', bool $showSampleNote = true): string
    {
        $data = ReportDataPreparer::prepare($report, $lang, $showSampleNote);

        $phpWord = new PhpWord;
        $phpWord->setDefaultFontName(self::FONT_FAMILY);
        $phpWord->setDefaultFontSize(10);

        // Define Reusable Styles
        $phpWord->addTitleStyle(1, [
            'name' => self::FONT_FAMILY,
            'size' => 16,
            'color' => '1E1B4B',
            'bold' => true,
        ], [
            'alignment' => Jc::CENTER,
            'spaceAfter' => 100,
        ]);

        $section = $phpWord->addSection([
            'paperSize' => 'A4',
            'marginTop' => 1000,
            'marginBottom' => 1000,
            'marginLeft' => 1000,
            'marginRight' => 1000,
        ]);

        // Add Header & Footer
        $header = $section->addHeader();
        $header->addText(
            $data['company_name'].' — Balanced Scorecard Report ('.$data['period_label'].' '.$data['year'].')',
            ['name' => self::FONT_FAMILY, 'size' => 8, 'color' => '6B7280', 'italic' => true]
        );

        $footer = $section->addFooter();
        $footer->addPreserveText(
            'Page {PAGE} of {NUMPAGES}',
            ['name' => self::FONT_FAMILY, 'size' => 8, 'color' => '6B7280'],
            ['alignment' => Jc::RIGHT]
        );

        // 1. Title Block
        $titleAm = 'የስትራቴጂክ እቅድ የግለሰብ አፈጻጸም ሪፖርት እና BSC ውጤት ካርድ';
        $titleEn = 'Strategic Plan Individual Performance Report & Balanced Scorecard';

        $mainTitle = $lang === 'en' ? $titleEn : $titleAm;
        $section->addTitle($mainTitle, 1);

        if ($lang === 'bilingual') {
            $section->addText($titleEn, [
                'name' => self::FONT_FAMILY,
                'size' => 11,
                'color' => '4338CA',
                'bold' => true,
            ], ['alignment' => Jc::CENTER, 'spaceAfter' => 120]);
        }

        $introText = $lang === 'en'
            ? 'This report evaluates the quarterly performance of co-founders across the 4 BSC perspectives (Financial, Customers, Internal Business Processes, and Learning & Growth) and outlines the consolidated strategic direction of '.$data['company_name'].'.'
            : 'ይህ ሪፖርት የመስራቾች ቡድን አባላትን የሩብ ዓመት አፈፃፀም በ 4ቱ የ BSC እይታዎች (ፋይናንስ፣ ደንበኞች፣ የውስጥ አሰራር፣ እና መማርና እድገት) በመገምገም የ '.$data['company_name'].' የጋራ አቅጣጫ የሚያሳይ ሰነድ ነው።';

        $section->addText($introText, [
            'name' => self::FONT_FAMILY,
            'size' => 9,
            'color' => '4B5563',
        ], ['alignment' => Jc::CENTER, 'spaceAfter' => 200]);

        // 2. Guidelines & Score Standard
        if ($showSampleNote) {
            $sampleNoteText = $lang === 'en'
                ? 'Sample Data Notice: The data provided in this report serves as a baseline demonstration; actual figures reflect confirmed quarterly results.'
                : 'የሪፖርት አሞላል ማስታወሻ፡ በዚህ ሪፖርት ላይ የተካተቱት መረጃዎች ለምሳሌያዊ ማሳያነት (Sample Demonstration) የቀረቡ ሲሆን፤ ተጨባጭ አሃዞች እንደየሩብ ዓመቱ ትክክለኛ ክንውን የሚሞሉ ናቸው።';

            $noteTable = $section->addTable(['borderColor' => 'F59E0B', 'borderSize' => 8, 'cellMargin' => 80]);
            $noteTable->addRow();
            $noteTable->addCell(9000, ['bgColor' => 'FEF3C7'])->addText($sampleNoteText, [
                'name' => self::FONT_FAMILY,
                'size' => 8.5,
                'color' => '92400E',
                'bold' => true,
            ]);
            $section->addTextBreak(1);
        }

        self::addSectionHeading($section, $lang === 'en' ? 'BSC Scoring Standards & Evaluation Benchmark' : 'የሪፖርት አሞላል እና የግምገማ መመሪያ — የ BSC ውጤት አሰጣጥ መመዘኛ');

        $stdTable = self::createStyledTable($section);
        self::addTableHeader($stdTable, [
            [$lang === 'en' ? 'Rating Band' : 'የደረጃ ምድብ', 2500],
            [$lang === 'en' ? 'Score Range (/5.0)' : 'የ BSC ውጤት (ከ 5.0)', 1800, Jc::CENTER],
            [$lang === 'en' ? 'Achievement %' : 'የክንውን %', 1800, Jc::CENTER],
            [$lang === 'en' ? 'Meaning' : 'ትርጓሜ እና ማብራሪያ', 3200],
        ]);

        self::addTableRow($stdTable, ['እጅግ የላቀ (Outstanding)', '4.5 – 5.0', '90% – 100%+', 'ከታቀደው በላይ እጅግ የላቀ ከፍተኛ አፈፃፀም የታየበት']);
        self::addTableRow($stdTable, ['ከፍተኛ (Exceeds Target)', '4.0 – 4.4', '75% – 89%', 'የተቀመጠውን ዒላማ በአጥጋቢ ሁኔታ ያሳካ እና የላቀ ክንውን']);
        self::addTableRow($stdTable, ['የተሳካ (Meets Target)', '3.0 – 3.9', '60% – 74%', 'መካከለኛና የታቀዱ ግቦችን ያሟላ የተሳካ አፈፃፀም']);
        self::addTableRow($stdTable, ['ትኩረት የሚሻ (Needs Improvement)', '< 3.0', '< 60%', 'ከዒላማው በታች የሆነና የማስተካከያ እርምጃ የሚሻ']);

        // 3. ክፍል 1/2/3: For Each Member
        foreach ($data['members'] as $m) {
            $section->addPageBreak();

            $partTitle = $lang === 'en'
                ? 'PART '.$m['section_number'].': '.$m['name'].' — '.$m['role_title_en']
                : 'ክፍል '.$m['section_number'].'፡ '.$m['name'].' — '.$m['role_title_am'];

            self::addMajorHeading($section, $partTitle);

            // 1.1 Identity
            self::addSectionHeading($section, $m['section_number'].'.1 '.($lang === 'en' ? 'Evaluation Identity Information' : 'የግምገማ መለያ መረጃ'));
            $idTable = self::createStyledTable($section);
            $idTable->addRow();
            $idTable->addCell(1800, ['bgColor' => 'F3F4F6'])->addText($lang === 'en' ? 'Member Name' : 'የመስራች ስም', ['bold' => true, 'size' => 8.5]);
            $idTable->addCell(2800)->addText($m['name'], ['size' => 8.5]);
            $idTable->addCell(1800, ['bgColor' => 'F3F4F6'])->addText($lang === 'en' ? 'Executive Role' : 'የሥራ ድርሻ / ማዕረግ', ['bold' => true, 'size' => 8.5]);
            $idTable->addCell(2800)->addText($lang === 'en' ? $m['role_title_en'] : $m['role_title_am'], ['size' => 8.5]);

            $idTable->addRow();
            $idTable->addCell(1800, ['bgColor' => 'F3F4F6'])->addText($lang === 'en' ? 'Period' : 'የግምገማ ወቅት', ['bold' => true, 'size' => 8.5]);
            $idTable->addCell(2800)->addText($data['period_label'].' - '.$data['year'], ['size' => 8.5]);
            $idTable->addCell(1800, ['bgColor' => 'F3F4F6'])->addText($lang === 'en' ? 'Report Date' : 'የሪፖርት ቀን', ['bold' => true, 'size' => 8.5]);
            $idTable->addCell(2800)->addText($data['report_date'], ['size' => 8.5]);

            $section->addTextBreak(1);

            // 1.2 Performance Table
            self::addSectionHeading($section, $m['section_number'].'.2 '.($lang === 'en' ? 'Activities & KPI Performance Table' : 'የእንቅስቃሴዎች እና የ KPI አፈፃፀም ሠንጠረዥ'));
            $actTable = self::createStyledTable($section);
            self::addTableHeader($actTable, [
                ['#', 450, Jc::CENTER],
                [$lang === 'en' ? 'Key Activity' : 'ዋና ዋና ተግባራት', 2350],
                [$lang === 'en' ? 'KPI Metric' : 'መለኪያ (KPI)', 1800],
                [$lang === 'en' ? 'Target' : 'ዒላማ', 1100],
                [$lang === 'en' ? 'Actual' : 'ክንውን', 1100],
                [$lang === 'en' ? 'Achv %' : 'ክንውን %', 850, Jc::CENTER],
                [$lang === 'en' ? 'Perspective & Score' : 'የ BSC እይታ እና ውጤት', 1550],
            ]);

            foreach ($m['activities'] as $act) {
                self::addTableRow($actTable, [
                    (string) $act['index'],
                    $act['activity_text'] ?: '—',
                    $act['kpi_text'] ?: '—',
                    $act['target_label'],
                    $act['actual_label'],
                    $act['achievement_percentage'].'%',
                    $act['perspective_score_str'],
                ]);
            }

            // Total Average Row
            $totalRow = $actTable->addRow(null, ['tblHeader' => false]);
            $totalRow->addCell(6800, ['gridSpan' => 5, 'bgColor' => 'EEF2FF'])->addText(
                $lang === 'en' ? 'Average Overall Performance:' : 'አማካይ አጠቃላይ አፈፃፀም:',
                ['bold' => true, 'size' => 9, 'color' => '1E1B4B']
            );
            $totalRow->addCell(850, ['bgColor' => 'EEF2FF'])->addText(
                $m['summary']['weighted_percentage_rounded'].'%',
                ['bold' => true, 'size' => 9, 'color' => '1E1B4B'],
                ['alignment' => Jc::CENTER]
            );
            $totalRow->addCell(1550, ['bgColor' => 'EEF2FF'])->addText(
                $m['summary']['weighted_score_rounded'].' / 5.0 ('.($lang === 'en' ? $m['summary']['rating']['label_en'] : $m['summary']['rating']['label_am']).')',
                ['bold' => true, 'size' => 8.5, 'color' => '1E1B4B']
            );

            $section->addTextBreak(1);

            // 1.3 Individual BSC Summary Card
            self::addSectionHeading($section, $m['section_number'].'.3 '.($lang === 'en' ? 'Individual BSC Perspective Summary Card' : 'የግል የ BSC እይታዎች ማጠቃለያ ካርድ'));
            $cardTable = self::createStyledTable($section);
            self::addTableHeader($cardTable, [
                [$lang === 'en' ? 'BSC Perspective' : 'የ BSC እይታ', 2800],
                [$lang === 'en' ? 'Weight' : 'ክብደት', 1300, Jc::CENTER],
                [$lang === 'en' ? 'Average Achv %' : 'አማካይ የክንውን %', 1600, Jc::CENTER],
                [$lang === 'en' ? 'BSC Score (/5.0)' : 'የ BSC ውጤት (ከ 5.0)', 1700, Jc::CENTER],
                [$lang === 'en' ? 'Status Rating' : 'ደረጃ (Status)', 1800, Jc::CENTER],
            ]);

            foreach (['financial', 'customers', 'internal', 'learning'] as $pKey) {
                $pSum = $m['summary']['perspectives'][$pKey] ?? null;
                $pName = $lang === 'en' ? ($pSum['name_en'] ?? $pKey) : ($pSum['name_am'] ?? $pKey);
                $weightStr = ($pSum['original_weight'] ?? 0).'%';
                $avgStr = $pSum && $pSum['average_percentage_rounded'] !== null ? $pSum['average_percentage_rounded'].'%' : '—';
                $scoreStr = $pSum && $pSum['score_summary_rounded'] !== null ? $pSum['score_summary_rounded'].' / 5.0' : '—';
                $statusStr = $pSum && $pSum['rating'] ? ($lang === 'en' ? $pSum['rating']['label_en'] : $pSum['rating']['label_am']) : '—';

                if ($pSum && $pSum['redistributed'] && $pSum['excluded']) {
                    $pName .= ' (*Redistributed)';
                }

                self::addTableRow($cardTable, [$pName, $weightStr, $avgStr, $scoreStr, $statusStr]);
            }

            // Total weighted row
            $cardTotal = $cardTable->addRow();
            $cardTotal->addCell(2800, ['bgColor' => 'EEF2FF'])->addText(
                $lang === 'en' ? 'Total Weighted Score' : 'አጠቃላይ ክብደት የተሰጠው ውጤት',
                ['bold' => true, 'size' => 9, 'color' => '1E1B4B']
            );
            $cardTotal->addCell(1300, ['bgColor' => 'EEF2FF'])->addText('100%', ['bold' => true, 'size' => 9], ['alignment' => Jc::CENTER]);
            $cardTotal->addCell(1600, ['bgColor' => 'EEF2FF'])->addText($m['summary']['weighted_percentage_rounded'].'%', ['bold' => true, 'size' => 9], ['alignment' => Jc::CENTER]);
            $cardTotal->addCell(1700, ['bgColor' => 'EEF2FF'])->addText($m['summary']['weighted_score_rounded'].' / 5.0', ['bold' => true, 'size' => 9], ['alignment' => Jc::CENTER]);
            $cardTotal->addCell(1800, ['bgColor' => 'EEF2FF'])->addText(
                $lang === 'en' ? $m['summary']['rating']['label_en'] : $m['summary']['rating']['label_am'],
                ['bold' => true, 'size' => 9, 'color' => '065F46'],
                ['alignment' => Jc::CENTER]
            );

            $section->addTextBreak(1);

            // 1.4 Comments & Signature
            self::addSectionHeading($section, $m['section_number'].'.4 '.($lang === 'en' ? 'Comments & Strategic Follow-up' : 'አስተያየት እና የቀጣይ አቅጣጫዎች'));
            self::addCommentBox($section, $lang === 'en' ? 'Key Achievements:' : 'ዋና ዋና የተከናወኑ ተግባራትና ስኬቶች (Achievements):', $m['achievements']);
            self::addCommentBox($section, $lang === 'en' ? 'Key Challenges:' : 'ያጋጠሙ ተግዳሮቶችና እንቅፋቶች (Challenges):', $m['challenges']);
            self::addCommentBox($section, $lang === 'en' ? 'Next Quarter Steps:' : 'ለቀጣይ ሩብ ዓመት የታቀዱ አቅጣጫዎች (Next Steps):', $m['next_steps']);

            $section->addTextBreak(1);
            $sigStatus = $m['signed'] ? ($lang === 'en' ? '[ ✓ Signed & Verified ]' : '[ ✓ የተረጋገጠ እና የተፈረመ ]') : ($lang === 'en' ? '[ Unsigned Draft ]' : '[ ያልተፈረመ ረቂቅ ]');
            $section->addText(
                ($lang === 'en' ? 'Member Signature: ' : 'የአባል ፊርማ: ').'________________________   '.$sigStatus,
                ['bold' => true, 'size' => 9, 'color' => $m['signed'] ? '059669' : '6B7280']
            );
        }

        // 4. ክፍል 4: Consolidated Company Report & Executive Decisions
        $section->addPageBreak();
        self::addMajorHeading($section, $lang === 'en' ? 'PART 4: CONSOLIDATED COMPANY REPORT & EXECUTIVE DECISION' : 'ክፍል 4፡ የተጠቃለለ የኩባንያ ሪፖርት እና የአመራር ውሳኔ');

        // 4.1 Comparison Table
        self::addSectionHeading($section, '4.1 '.($lang === 'en' ? 'Consolidated Founding Team Performance Comparison' : 'የመስራቾች የጋራ ውጤት ንጽጽር ሰንጠረዥ'));
        $compTable = self::createStyledTable($section);
        self::addTableHeader($compTable, [
            [$lang === 'en' ? 'Member & Executive Role' : 'የመስራች ስም እና ሚና', 3200],
            [$lang === 'en' ? 'Achievement %' : 'የክንውን %', 1600, Jc::CENTER],
            [$lang === 'en' ? 'BSC Score (/5.0)' : 'የ BSC ውጤት (ከ 5.0)', 1800, Jc::CENTER],
            [$lang === 'en' ? 'Readiness / Growth Status' : 'የዝግጁነት እና የእድገት ሁኔታ', 2600],
        ]);

        foreach ($data['members'] as $m) {
            $mRole = $lang === 'en' ? $m['role_title_en'] : $m['role_title_am'];
            $statusNote = $m['readiness_note'] ?: ($lang === 'en' ? 'Normal operational progression' : 'መደበኛ የስራ ሂደት ላይ ያለ');
            self::addTableRow($compTable, [
                $m['name'].' ('.$mRole.')',
                $m['summary']['weighted_percentage_rounded'].'%',
                $m['summary']['weighted_score_rounded'].' ('.($lang === 'en' ? $m['summary']['rating']['label_en'] : $m['summary']['rating']['label_am']).')',
                $statusNote,
            ]);
        }

        // Founding team average row
        $avgRow = $compTable->addRow();
        $avgRow->addCell(3200, ['bgColor' => 'EEF2FF'])->addText(
            $lang === 'en' ? 'Founding Team Average Score' : 'የመስራቾች ቡድን አማካይ ውጤት (Company Average)',
            ['bold' => true, 'size' => 9, 'color' => '1E1B4B']
        );
        $avgRow->addCell(1600, ['bgColor' => 'EEF2FF'])->addText(
            $data['company_summary']['company_weighted_percentage_rounded'].'%',
            ['bold' => true, 'size' => 9, 'color' => '1E1B4B'],
            ['alignment' => Jc::CENTER]
        );
        $avgRow->addCell(1800, ['bgColor' => 'EEF2FF'])->addText(
            $data['company_summary']['company_weighted_score_rounded'].' / 5.0 ('.($lang === 'en' ? $data['company_summary']['rating']['label_en'] : $data['company_summary']['rating']['label_am']).')',
            ['bold' => true, 'size' => 8.5, 'color' => '1E1B4B'],
            ['alignment' => Jc::CENTER]
        );
        $avgRow->addCell(2600, ['bgColor' => 'EEF2FF'])->addText(
            $lang === 'en' ? 'Consolidated corporate performance' : 'የተጠቃለለ የኩባንያ አጠቃላይ አፈፃፀም',
            ['italic' => true, 'size' => 8.5, 'color' => '4338CA']
        );

        $section->addTextBreak(1);

        // 4.2 Executive Decisions & Gate
        self::addSectionHeading($section, '4.2 '.($lang === 'en' ? 'Executive Management Decisions & PLC Transition Gate' : 'የኩባንያ ውሳኔዎች እና የ PLC ሽግግር መስፈርቶች'));

        $gateText = ($data['gate_evaluation']['is_met'] ? '[ ✓ Criteria Met / መስፈርቱ ተሟልቷል ]' : '[ ✕ Criteria Not Met / መስፈርቱ አልተሟላም ]');
        $section->addText(
            ($lang === 'en' ? 'PLC Readiness Benchmark Status: ' : 'የ PLC ሽግግር ቅድመ-ሁኔታ ሁኔታ፡ ').$gateText,
            ['bold' => true, 'size' => 9.5, 'color' => $data['gate_evaluation']['is_met'] ? '059669' : 'DC2626']
        );

        $decisionStatement = $data['decision']?->decision_text_am ?: $data['gate_evaluation']['suggested_decision_am'];
        self::addCommentBox($section, $lang === 'en' ? 'Joint Executive Evaluation Statement:' : 'የጋራ ውሳኔ እና የአፈፃፀም ማጠቃለያ መግለጫ፡', $decisionStatement);

        $section->addText($lang === 'en' ? 'Numbered Board & Executive Decisions:' : 'በቁጥር የተዘረዘሩ የአመራሩ የጋራ ውሳኔዎች፡', ['bold' => true, 'size' => 9]);
        foreach ($data['decisions_list'] as $idx => $decItem) {
            if (trim($decItem) !== '') {
                $section->addText(($idx + 1).'. '.$decItem, ['size' => 9]);
            }
        }
        $section->addTextBreak(1);

        $expansionText = $data['decision']?->expansion_text_am ?: ($lang === 'en' ? 'Continue expansion under strategic roadmap.' : 'በስትራቴጂክ ዕቅዱ መሰረት ተጨማሪ አዳዲስ ሰራተኞችን ማሰልጠንና አገልግሎቶችን ማስፋፋት።');
        self::addCommentBox($section, $lang === 'en' ? 'Operational & Capital Expansion Plan:' : 'የማስፋፊያ እና የካፒታል ዕቅድ (Next Steps):', $expansionText);

        // Founding signatures
        $section->addTextBreak(1);
        $section->addText($lang === 'en' ? 'Founding Team Signatures:' : 'የመስራቾች ቦርድ የጋራ ፊርማ፡', ['bold' => true, 'size' => 9.5]);

        $sigTable = $section->addTable(['cellMargin' => 60]);
        $sigRow = $sigTable->addRow();
        foreach ($data['members'] as $m) {
            $sigCell = $sigRow->addCell(3000);
            $sigCell->addText($m['name'], ['bold' => true, 'size' => 8.5]);
            $sigCell->addText($lang === 'en' ? $m['role_title_en'] : $m['role_title_am'], ['size' => 7.5, 'color' => '6B7280']);
            $sigCell->addText('_______________________', ['size' => 8]);
            $sigCell->addText($lang === 'en' ? 'Signature & Date' : 'ፊርማ እና ቀን', ['size' => 7, 'color' => '9CA3AF']);
        }

        // Save Word document to temporary file
        $tempPath = tempnam(sys_get_temp_dir(), 'bsc_docx_').'.docx';
        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($tempPath);

        return $tempPath;
    }

    private static function addMajorHeading(Section $section, string $text): void
    {
        $table = $section->addTable(['cellMargin' => 100, 'borderColor' => '4338CA', 'borderSize' => 12]);
        $table->addRow();
        $table->addCell(9200, ['bgColor' => 'E0E7FF'])->addText($text, [
            'name' => self::FONT_FAMILY,
            'size' => 12,
            'bold' => true,
            'color' => '1E1B4B',
        ]);
        $section->addTextBreak(1);
    }

    private static function addSectionHeading(Section $section, string $text): void
    {
        $section->addText($text, [
            'name' => self::FONT_FAMILY,
            'size' => 10.5,
            'bold' => true,
            'color' => '1F2937',
        ], ['spaceAfter' => 80, 'spaceBefore' => 80]);
    }

    private static function addCommentBox(Section $section, string $label, ?string $content): void
    {
        $section->addText($label, ['name' => self::FONT_FAMILY, 'bold' => true, 'size' => 8.5, 'color' => '374151']);
        $table = $section->addTable(['borderColor' => 'E5E7EB', 'borderSize' => 6, 'cellMargin' => 60]);
        $table->addRow();
        $table->addCell(9200, ['bgColor' => 'F9FAFB'])->addText($content ?: '—', ['name' => self::FONT_FAMILY, 'size' => 8.5, 'color' => '1F2937']);
        $section->addTextBreak(1);
    }

    private static function createStyledTable(Section $section): Table
    {
        return $section->addTable([
            'borderColor' => 'D1D5DB',
            'borderSize' => 6,
            'cellMarginTop' => 60,
            'cellMarginBottom' => 60,
            'cellMarginLeft' => 80,
            'cellMarginRight' => 80,
            'unit' => TblWidth::TWIP,
        ]);
    }

    /**
     * @param  array<int, array{0: string, 1: int, 2?: string}>  $headers
     */
    private static function addTableHeader(Table $table, array $headers): void
    {
        $row = $table->addRow(null, ['tblHeader' => true]);
        foreach ($headers as $h) {
            $align = $h[2] ?? Jc::LEFT;
            $row->addCell($h[1], ['bgColor' => 'F3F4F6'])->addText($h[0], [
                'name' => self::FONT_FAMILY,
                'bold' => true,
                'size' => 8.5,
                'color' => '111827',
            ], ['alignment' => $align]);
        }
    }

    /**
     * @param  array<int, string>  $cells
     */
    private static function addTableRow(Table $table, array $cells): void
    {
        $row = $table->addRow();
        foreach ($cells as $c) {
            $row->addCell()->addText($c, ['name' => self::FONT_FAMILY, 'size' => 8.5]);
        }
    }
}
