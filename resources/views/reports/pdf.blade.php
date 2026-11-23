<!DOCTYPE html>
<html lang="{{ $lang === 'en' ? 'en' : 'am' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BSC_Report_{{ $period_label }}_{{ $year }}</title>
    <style>
        @font-face {
            font-family: 'Noto Sans Ethiopic';
            font-style: normal;
            font-weight: 400;
            src: url('{{ resource_path("fonts/NotoSansEthiopic-Regular.ttf") }}') format('truetype');
        }
        @font-face {
            font-family: 'Noto Sans Ethiopic';
            font-style: normal;
            font-weight: 700;
            src: url('{{ resource_path("fonts/NotoSansEthiopic-Bold.ttf") }}') format('truetype');
        }

        @page {
            size: A4 portrait;
            margin: 15mm 12mm 18mm 12mm;
            @bottom-right {
                content: counter(page) " / " counter(pages);
                font-family: 'Noto Sans Ethiopic', sans-serif;
                font-size: 8pt;
                color: #666;
            }
            @bottom-left {
                content: "{{ $company_name }} — Balanced Scorecard Report ({{ $period_label }} {{ $year }})";
                font-family: 'Noto Sans Ethiopic', sans-serif;
                font-size: 8pt;
                color: #666;
            }
        }

        * {
            box-sizing: border-box;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        body {
            font-family: 'Noto Sans Ethiopic', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 9.5pt;
            line-height: 1.45;
            color: #1a202c;
            background-color: #ffffff;
            margin: 0;
            padding: 0;
        }

        .page-break {
            page-break-before: always;
            break-before: page;
        }

        .header-title-box {
            text-align: center;
            border-bottom: 2px solid #312e81;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }

        h1.main-title {
            font-size: 15pt;
            font-weight: 800;
            color: #1e1b4b;
            margin: 0 0 4px 0;
        }

        .subtitle-en {
            font-size: 10pt;
            color: #4338ca;
            font-weight: 600;
            margin: 0 0 6px 0;
        }

        .intro-sentence {
            font-size: 8.5pt;
            color: #4b5563;
            margin: 0 auto;
            max-width: 90%;
            line-height: 1.35;
        }

        .section-header {
            background-color: #e0e7ff;
            color: #1e1b4b;
            padding: 6px 10px;
            font-size: 11pt;
            font-weight: 800;
            border-left: 4px solid #4338ca;
            margin: 14px 0 10px 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .sub-header {
            font-size: 10pt;
            font-weight: 700;
            color: #1f2937;
            margin: 12px 0 6px 0;
            border-bottom: 1px solid #e5e7eb;
            padding-bottom: 3px;
        }

        /* Identity Info Grid */
        .identity-table {
            width: 100%;
            margin-bottom: 10px;
            border-collapse: collapse;
            font-size: 8.5pt;
        }
        .identity-table td {
            padding: 4px 8px;
            border: 1px solid #e5e7eb;
        }
        .identity-table .id-label {
            background-color: #f9fafb;
            font-weight: 700;
            color: #4b5563;
            width: 18%;
        }
        .identity-table .id-value {
            color: #111827;
            font-weight: 600;
            width: 32%;
        }

        /* General Tables */
        table.bsc-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8.5pt;
            margin-bottom: 10px;
        }
        table.bsc-table th, table.bsc-table td {
            border: 1px solid #d1d5db;
            padding: 5px 6px;
            vertical-align: middle;
        }
        table.bsc-table th {
            background-color: #f3f4f6;
            color: #111827;
            font-weight: 700;
            text-align: left;
        }
        table.bsc-table tr:nth-child(even) td {
            background-color: #fafafa;
        }
        table.bsc-table tr.total-row td {
            background-color: #eef2ff !important;
            font-weight: 800;
            border-top: 2px solid #818cf8;
            color: #1e1b4b;
        }

        .text-center { text-align: center; }
        .text-right { text-align: right; }

        /* Rating Chips */
        .chip {
            display: inline-block;
            padding: 1px 6px;
            border-radius: 9999px;
            font-size: 7.5pt;
            font-weight: 700;
            border: 1px solid transparent;
            white-space: nowrap;
        }
        .chip-outstanding { background-color: #d1fae5; color: #065f46; border-color: #a7f3d0; }
        .chip-exceeds { background-color: #dbeafe; color: #1e40af; border-color: #bfdbfe; }
        .chip-meets { background-color: #fef3c7; color: #92400e; border-color: #fde68a; }
        .chip-needs { background-color: #ffe4e6; color: #9f1239; border-color: #fecdd3; }

        /* Sample note box */
        .sample-note-box {
            background-color: #fffbeb;
            border: 1px solid #fef3c7;
            border-left: 3px solid #f59e0b;
            padding: 6px 10px;
            font-size: 8pt;
            color: #92400e;
            margin-bottom: 12px;
            border-radius: 4px;
        }

        /* Comments Box */
        .comment-block {
            margin-bottom: 8px;
            font-size: 8.5pt;
        }
        .comment-label {
            font-weight: 700;
            color: #374151;
            margin-bottom: 2px;
        }
        .comment-content {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            padding: 5px 8px;
            min-height: 28px;
            color: #1f2937;
        }

        /* Signatures */
        .signature-row {
            margin-top: 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-top: 1px dashed #d1d5db;
            padding-top: 8px;
            font-size: 8.5pt;
        }
        .sig-stamp {
            display: inline-block;
            border: 2px solid #059669;
            color: #059669;
            padding: 2px 8px;
            font-weight: 800;
            font-size: 8pt;
            border-radius: 4px;
            text-transform: uppercase;
        }
        .sig-unsigned {
            color: #9ca3af;
            font-style: italic;
        }

        .screen-buttons {
            display: none;
        }
        @media screen {
            body {
                background-color: #f3f4f6;
                padding: 20px;
            }
            .a4-container {
                max-width: 210mm;
                margin: 0 auto;
                background-color: #ffffff;
                padding: 15mm 15mm;
                box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
                border-radius: 4px;
            }
            .screen-buttons {
                display: flex;
                align-items: center;
                justify-content: space-between;
                max-width: 210mm;
                margin: 0 auto 16px auto;
                padding: 10px 14px;
                background-color: #ffffff;
                border-radius: 8px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            }
            .page-break {
                margin-top: 24px;
                padding-top: 18px;
                border-top: 2px dashed #cbd5e1;
            }
        }
    </style>
</head>
<body>

    @if(request()->has('screen'))
    <div class="screen-buttons">
        <div style="font-weight: 700; font-size: 12pt; color: #1e1b4b;">
            📄 {{ $report->title }} — {{ $period_label }} {{ $year }}
        </div>
        <div style="display: flex; gap: 8px;">
            <button onclick="window.print()" style="padding: 6px 14px; background: #4338ca; color: white; border: none; border-radius: 6px; font-weight: 700; cursor: pointer;">
                🖨️ {{ $lang === 'en' ? 'Print / Save as PDF' : 'አትም / በ PDF አስቀምጥ' }}
            </button>
            <a href="{{ route('reports.docx', ['report' => $report->id, 'lang' => $lang, 'sample_note' => $show_sample_note ? 1 : 0]) }}" style="padding: 6px 14px; background: #0284c7; color: white; text-decoration: none; border-radius: 6px; font-weight: 700;">
                📥 {{ $lang === 'en' ? 'Download DOCX' : 'በ Word (DOCX) አውርድ' }}
            </a>
            <a href="{{ route('reports.edit', $report->id) }}" style="padding: 6px 14px; background: #e5e7eb; color: #374151; text-decoration: none; border-radius: 6px; font-weight: 600;">
                ◀ {{ $lang === 'en' ? 'Back to Wizard' : 'ወደ አርትዖት ተመለስ' }}
            </a>
        </div>
    </div>
    @endif

    <div class="a4-container">

        <!-- Title & Intro Header -->
        <div class="header-title-box">
            @php
                $companyLogo = null;
                try {
                    $companyLogo = \App\Models\Setting::get('company_logo');
                } catch (\Throwable $e) {}
            @endphp
            @if($companyLogo)
            <div style="text-align: center; margin-bottom: 8px;">
                <img src="{{ $companyLogo }}" alt="Company Logo" style="max-height: 48px; max-width: 180px; object-fit: contain;">
            </div>
            @endif
            <h1 class="main-title">
                {{ $lang === 'en' ? 'Strategic Plan Individual Performance Report & Balanced Scorecard' : 'የስትራቴጂክ እቅድ የግለሰብ አፈጻጸም ሪፖርት እና BSC ውጤት ካርድ' }}
            </h1>
            @if($lang === 'bilingual')
            <div class="subtitle-en">Strategic Plan Individual Performance Report & Balanced Scorecard</div>
            @endif
            <div class="intro-sentence">
                @if($lang === 'en')
                This report evaluates the quarterly performance of co-founders across the 4 BSC perspectives (Financial, Customers, Internal Business Processes, and Learning & Growth) and outlines the consolidated strategic direction of {{ $company_name }}.
                @else
                ይህ ሪፖርት የመስራቾች ቡድን አባላትን የሩብ ዓመት አፈፃፀም በ 4ቱ የ BSC እይታዎች (ፋይናንስ፣ ደንበኞች፣ የውስጥ አሰራር፣ እና መማርና እድገት) በመገምገም የ {{ $company_name }} የጋራ አቅጣጫ የሚያሳይ ሰነድ ነው።
                @endif
            </div>
        </div>

        <!-- Guidelines & Standard Block -->
        <div>
            @if($show_sample_note)
            <div class="sample-note-box">
                <strong>{{ $lang === 'en' ? 'Sample Data Notice:' : 'የሪፖርት አሞላል ማስታወሻ፡' }}</strong>
                @if($lang === 'en')
                The data provided in this report serves as a baseline demonstration; actual figures reflect confirmed quarterly results.
                @else
                በዚህ ሪፖርት ላይ የተካተቱት መረጃዎች ለምሳሌያዊ ማሳያነት (Sample Demonstration) የቀረቡ ሲሆን፤ ተጨባጭ አሃዞች እንደየሩብ ዓመቱ ትክክለኛ ክንውን የሚሞሉ ናቸው።
                @endif
            </div>
            @endif

            <div class="sub-header">
                {{ $lang === 'en' ? 'BSC Scoring Standard & Evaluation Benchmark' : 'የሪፖርት አሞላል እና የግምገማ መመሪያ — የ BSC ውጤት አሰጣጥ መመዘኛ' }}
            </div>

            <table class="bsc-table">
                <thead>
                    <tr>
                        <th style="width: 22%;">{{ $lang === 'en' ? 'Rating Band' : 'የደረጃ ምድብ (Rating)' }}</th>
                        <th style="width: 20%;" class="text-center">{{ $lang === 'en' ? 'Score Range (/5.0)' : 'የ BSC ውጤት (ከ 5.0)' }}</th>
                        <th style="width: 20%;" class="text-center">{{ $lang === 'en' ? 'Achievement %' : 'የክንውን %' }}</th>
                        <th>{{ $lang === 'en' ? 'Definition / Meaning' : 'ትርጓሜ እና የአፈፃፀም ማብራሪያ' }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>
                            <span class="chip chip-outstanding">
                                {{ $lang === 'en' ? 'Outstanding' : 'እጅግ የላቀ (Outstanding)' }}
                            </span>
                        </td>
                        <td class="text-center font-bold">4.5 – 5.0</td>
                        <td class="text-center">90% – 100%+</td>
                        <td>{{ $lang === 'en' ? 'Far exceeds targeted performance benchmark' : 'ከታቀደው በላይ እጅግ የላቀ ከፍተኛ አፈፃፀም የታየበት' }}</td>
                    </tr>
                    <tr>
                        <td>
                            <span class="chip chip-exceeds">
                                {{ $lang === 'en' ? 'Exceeds Target' : 'ከፍተኛ (Exceeds Target)' }}
                            </span>
                        </td>
                        <td class="text-center font-bold">4.0 – 4.4</td>
                        <td class="text-center">75% – 89%</td>
                        <td>{{ $lang === 'en' ? 'Exceeds the set KPI targets satisfactorily' : 'የተቀመጠውን ዒላማ በአጥጋቢ ሁኔታ ያሳካ እና የላቀ ክንውን' }}</td>
                    </tr>
                    <tr>
                        <td>
                            <span class="chip chip-meets">
                                {{ $lang === 'en' ? 'Meets Target' : 'የተሳካ (Meets Target)' }}
                            </span>
                        </td>
                        <td class="text-center font-bold">3.0 – 3.9</td>
                        <td class="text-center">60% – 74%</td>
                        <td>{{ $lang === 'en' ? 'Satisfactorily meets core planned targets' : 'መካከለኛና የታቀዱ ግቦችን ያሟላ የተሳካ አፈፃፀም' }}</td>
                    </tr>
                    <tr>
                        <td>
                            <span class="chip chip-needs">
                                {{ $lang === 'en' ? 'Needs Improvement' : 'ትኩረት የሚሻ (Needs Improvement)' }}
                            </span>
                        </td>
                        <td class="text-center font-bold">&lt; 3.0</td>
                        <td class="text-center">&lt; 60%</td>
                        <td>{{ $lang === 'en' ? 'Below target, requires corrective action' : 'ከዒላማው በታች የሆነና የማስተካከያ እርምጃ የሚሻ' }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- ክፍል 1, 2, 3: Per-Member Section -->
        @foreach($members as $m)
        <div class="page-break">
            <!-- Section Header -->
            <div class="section-header">
                <span>
                    {{ $lang === 'en' ? 'PART ' . $m['section_number'] . ': ' . $m['name'] . ' — ' . $m['role_title_en'] : 'ክፍል ' . $m['section_number'] . '፡ ' . $m['name'] . ' — ' . $m['role_title_am'] }}
                </span>
                <span style="font-size: 8.5pt; font-weight: normal; color: #4338ca;">
                    {{ $period_label }} - {{ $year }}
                </span>
            </div>

            <!-- 1.1 Identity Info -->
            <div class="sub-header">
                {{ $m['section_number'] }}.1 {{ $lang === 'en' ? 'Evaluation Identity Information' : 'የግምገማ መለያ መረጃ' }}
            </div>
            <table class="identity-table">
                <tr>
                    <td class="id-label">{{ $lang === 'en' ? 'Member Name' : 'የመስራች ስም' }}</td>
                    <td class="id-value">{{ $m['name'] }}</td>
                    <td class="id-label">{{ $lang === 'en' ? 'Executive Role' : 'የሥራ ድርሻ / ማዕረግ' }}</td>
                    <td class="id-value">
                        @if($lang === 'en')
                            {{ $m['role_title_en'] ?: $m['role_title_am'] }}
                        @elseif($lang === 'bilingual')
                            {{ $m['role_title_am'] }} ({{ $m['role_title_en'] }})
                        @else
                            {{ $m['role_title_am'] }}
                        @endif
                    </td>
                </tr>
                <tr>
                    <td class="id-label">{{ $lang === 'en' ? 'Period' : 'የግምገማ ወቅት' }}</td>
                    <td class="id-value">{{ $period_label }} - {{ $year }}</td>
                    <td class="id-label">{{ $lang === 'en' ? 'Report Date' : 'የሪፖርት ቀን' }}</td>
                    <td class="id-value">{{ $report_date }}</td>
                </tr>
            </table>

            <!-- 1.2 Performance Table -->
            <div class="sub-header">
                {{ $m['section_number'] }}.2 {{ $lang === 'en' ? 'Activities & KPI Performance Table' : 'የእንቅስቃሴዎች እና የ KPI አፈፃፀም ሠንጠረዥ' }}
            </div>
            <table class="bsc-table">
                <thead>
                    <tr>
                        <th style="width: 25px;" class="text-center">#</th>
                        <th>{{ $lang === 'en' ? 'Key Activity' : 'ዋና ዋና ተግባራት (Activity)' }}</th>
                        <th style="width: 22%;">{{ $lang === 'en' ? 'KPI Metric' : 'የአፈፃፀም መለኪያ (KPI)' }}</th>
                        <th style="width: 14%;">{{ $lang === 'en' ? 'Target' : 'ዒላማ (Target)' }}</th>
                        <th style="width: 14%;">{{ $lang === 'en' ? 'Actual' : 'ክንውን (Actual)' }}</th>
                        <th style="width: 10%;" class="text-center">{{ $lang === 'en' ? 'Achv %' : 'ክንውን %' }}</th>
                        <th style="width: 20%;">{{ $lang === 'en' ? 'Perspective & Score' : 'የ BSC እይታ እና ውጤት' }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($m['activities'] as $act)
                    <tr>
                        <td class="text-center">{{ $act['index'] }}</td>
                        <td>{{ $act['activity_text'] ?: '—' }}</td>
                        <td>{{ $act['kpi_text'] ?: '—' }}</td>
                        <td>{{ $act['target_label'] }}</td>
                        <td>{{ $act['actual_label'] }}</td>
                        <td class="text-center font-bold">{{ $act['achievement_percentage'] }}%</td>
                        <td>{{ $act['perspective_score_str'] }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center" style="color: #6b7280; padding: 10px;">
                            {{ $lang === 'en' ? 'No activities recorded' : 'ምንም የተመዘገበ እንቅስቃሴ የለም' }}
                        </td>
                    </tr>
                    @endforelse

                    <!-- Average overall performance row -->
                    <tr class="total-row">
                        <td colspan="5" style="text-align: right; padding-right: 10px;">
                            {{ $lang === 'en' ? 'Average Overall Performance:' : 'አማካይ አጠቃላይ አፈፃፀም (Average Overall Performance):' }}
                        </td>
                        <td class="text-center">
                            {{ $m['summary']['weighted_percentage_rounded'] }}%
                        </td>
                        <td>
                            <strong>{{ $m['summary']['weighted_score_rounded'] }} / 5.0</strong>
                            <span class="chip chip-{{ $m['summary']['rating']['key'] === 'outstanding' ? 'outstanding' : ($m['summary']['rating']['key'] === 'exceeds_target' ? 'exceeds' : ($m['summary']['rating']['key'] === 'meets_target' ? 'meets' : 'needs')) }}">
                                {{ $lang === 'en' ? $m['summary']['rating']['label_en'] : $m['summary']['rating']['label_am'] }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- 1.3 Individual BSC Summary Card -->
            <div class="sub-header">
                {{ $m['section_number'] }}.3 {{ $lang === 'en' ? 'Individual BSC Perspective Summary Card' : 'የግል የ BSC እይታዎች ማጠቃለያ ካርድ' }}
            </div>
            <table class="bsc-table">
                <thead>
                    <tr>
                        <th>{{ $lang === 'en' ? 'BSC Perspective' : 'የ BSC እይታ (Perspective)' }}</th>
                        <th style="width: 15%;" class="text-center">{{ $lang === 'en' ? 'Weight' : 'ክብደት (Weight)' }}</th>
                        <th style="width: 20%;" class="text-center">{{ $lang === 'en' ? 'Average Achv %' : 'አማካይ የክንውን %' }}</th>
                        <th style="width: 20%;" class="text-center">{{ $lang === 'en' ? 'BSC Score (/5.0)' : 'የ BSC ውጤት (ከ 5.0)' }}</th>
                        <th style="width: 22%;" class="text-center">{{ $lang === 'en' ? 'Status' : 'ደረጃ (Status)' }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(['financial', 'customers', 'internal', 'learning'] as $pKey)
                    @php $pSummary = $m['summary']['perspectives'][$pKey] ?? null; @endphp
                    <tr>
                        <td>
                            <strong>{{ $lang === 'en' ? $pSummary['name_en'] : $pSummary['name_am'] }}</strong>
                            @if($lang === 'bilingual')
                            <span style="color: #6b7280; font-size: 7.5pt;">({{ $pSummary['name_en'] }})</span>
                            @endif
                            @if($pSummary && $pSummary['redistributed'] && $pSummary['excluded'])
                            <span style="font-size: 7pt; color: #b45309; display: block;">* ክብደቱ ተከፋፍሏል (Weight redistributed)</span>
                            @endif
                        </td>
                        <td class="text-center font-bold">
                            {{ $pSummary ? $pSummary['original_weight'] : 0 }}%
                        </td>
                        <td class="text-center">
                            {{ $pSummary && $pSummary['average_percentage_rounded'] !== null ? $pSummary['average_percentage_rounded'] . '%' : '—' }}
                        </td>
                        <td class="text-center font-bold">
                            {{ $pSummary && $pSummary['score_summary_rounded'] !== null ? $pSummary['score_summary_rounded'] . ' / 5.0' : '—' }}
                        </td>
                        <td class="text-center">
                            @if($pSummary && $pSummary['rating'])
                            <span class="chip chip-{{ $pSummary['rating']['key'] === 'outstanding' ? 'outstanding' : ($pSummary['rating']['key'] === 'exceeds_target' ? 'exceeds' : ($pSummary['rating']['key'] === 'meets_target' ? 'meets' : 'needs')) }}">
                                {{ $lang === 'en' ? $pSummary['rating']['label_en'] : $pSummary['rating']['label_am'] }}
                            </span>
                            @else
                            <span style="color: #9ca3af;">—</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach

                    <!-- Total weighted score row -->
                    <tr class="total-row">
                        <td>
                            <strong>{{ $lang === 'en' ? 'Total Weighted Score' : 'አጠቃላይ ክብደት የተሰጠው ውጤት' }}</strong>
                        </td>
                        <td class="text-center font-extrabold">100%</td>
                        <td class="text-center font-extrabold">{{ $m['summary']['weighted_percentage_rounded'] }}%</td>
                        <td class="text-center font-extrabold">{{ $m['summary']['weighted_score_rounded'] }} / 5.0</td>
                        <td class="text-center">
                            <span class="chip chip-{{ $m['summary']['rating']['key'] === 'outstanding' ? 'outstanding' : ($m['summary']['rating']['key'] === 'exceeds_target' ? 'exceeds' : ($m['summary']['rating']['key'] === 'meets_target' ? 'meets' : 'needs')) }}">
                                {{ $lang === 'en' ? $m['summary']['rating']['label_en'] : $m['summary']['rating']['label_am'] }}
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- 1.4 Comments & Strategic Follow-up -->
            <div class="sub-header">
                {{ $m['section_number'] }}.4 {{ $lang === 'en' ? 'Comments & Strategic Follow-up' : 'አስተያየት እና የቀጣይ አቅጣጫዎች' }}
            </div>
            <div class="comment-block">
                <div class="comment-label">{{ $lang === 'en' ? 'Key Achievements:' : 'ዋና ዋና የተከናወኑ ተግባራትና ስኬቶች (Achievements):' }}</div>
                <div class="comment-content">{{ $m['achievements'] ?: ($lang === 'en' ? 'No comments entered.' : 'አስተያየት አልተሰጠም።') }}</div>
            </div>
            <div class="comment-block">
                <div class="comment-label">{{ $lang === 'en' ? 'Key Challenges:' : 'ያጋጠሙ ተግዳሮቶችና እንቅፋቶች (Challenges):' }}</div>
                <div class="comment-content">{{ $m['challenges'] ?: ($lang === 'en' ? 'No comments entered.' : 'አስተያየት አልተሰጠም።') }}</div>
            </div>
            <div class="comment-block">
                <div class="comment-label">{{ $lang === 'en' ? 'Next Quarter Steps:' : 'ለቀጣይ ሩብ ዓመት የታቀዱ አቅጣጫዎች (Next Steps):' }}</div>
                <div class="comment-content">{{ $m['next_steps'] ?: ($lang === 'en' ? 'No comments entered.' : 'አስተያየት አልተሰጠም።') }}</div>
            </div>

            <!-- Signature line -->
            <div class="signature-row">
                <div>
                    <span>{{ $lang === 'en' ? 'Member Signature:' : 'የአባል ፊርማ:' }}</span>
                    <span style="text-decoration: underline; padding-left: 8px;">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>
                </div>
                <div>
                    @if($m['signed'])
                    <span class="sig-stamp">✓ {{ $lang === 'en' ? 'Signed & Verified' : 'የተረጋገጠ እና የተፈረመ' }}</span>
                    @else
                    <span class="sig-unsigned">{{ $lang === 'en' ? 'Unsigned Draft' : 'ያልተፈረመ ረቂቅ' }}</span>
                    @endif
                </div>
            </div>
        </div>
        @endforeach

        <!-- ክፍል 4: Consolidated Company Report & Executive Management Decision -->
        <div class="page-break">
            <!-- Section Header -->
            <div class="section-header">
                <span>
                    {{ $lang === 'en' ? 'PART 4: CONSOLIDATED COMPANY REPORT & EXECUTIVE DECISION' : 'ክፍል 4፡ የተጠቃለለ የኩባንያ ሪፖርት እና የአመራር ውሳኔ' }}
                </span>
                <span style="font-size: 8.5pt; font-weight: normal; color: #4338ca;">
                    {{ $company_name }}
                </span>
            </div>

            <!-- 4.1 Consolidated Comparison Table -->
            <div class="sub-header">
                4.1 {{ $lang === 'en' ? 'Consolidated Founding Team Performance Comparison' : 'የመስራቾች የጋራ ውጤት ንጽጽር ሰንጠረዥ' }}
            </div>
            <table class="bsc-table">
                <thead>
                    <tr>
                        <th style="width: 32%;">{{ $lang === 'en' ? 'Member & Executive Role' : 'የመስራች ስም እና ሚና' }}</th>
                        <th style="width: 18%;" class="text-center">{{ $lang === 'en' ? 'Achievement %' : 'የክንውን %' }}</th>
                        <th style="width: 20%;" class="text-center">{{ $lang === 'en' ? 'BSC Score (/5.0)' : 'የ BSC ውጤት (ከ 5.0)' }}</th>
                        <th>{{ $lang === 'en' ? 'Readiness / Growth Status' : 'የዝግጁነት እና የእድገት ሁኔታ' }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($members as $m)
                    <tr>
                        <td>
                            <strong>{{ $m['name'] }}</strong>
                            <div style="font-size: 7.5pt; color: #6b7280;">
                                {{ $lang === 'en' ? $m['role_title_en'] : $m['role_title_am'] }}
                            </div>
                        </td>
                        <td class="text-center font-bold">
                            {{ $m['summary']['weighted_percentage_rounded'] }}%
                        </td>
                        <td class="text-center">
                            <strong>{{ $m['summary']['weighted_score_rounded'] }}</strong>
                            <span class="chip chip-{{ $m['summary']['rating']['key'] === 'outstanding' ? 'outstanding' : ($m['summary']['rating']['key'] === 'exceeds_target' ? 'exceeds' : ($m['summary']['rating']['key'] === 'meets_target' ? 'meets' : 'needs')) }}">
                                {{ $lang === 'en' ? $m['summary']['rating']['label_en'] : $m['summary']['rating']['label_am'] }}
                            </span>
                        </td>
                        <td>
                            {{ $m['readiness_note'] ?: ($lang === 'en' ? 'Normal operational progression' : 'መደበኛ የስራ ሂደት ላይ ያለ') }}
                        </td>
                    </tr>
                    @endforeach

                    <!-- Company Average Row -->
                    <tr class="total-row">
                        <td>
                            <strong>{{ $lang === 'en' ? 'Founding Team Average Score' : 'የመስራቾች ቡድን አማካይ ውጤት (Company Average)' }}</strong>
                        </td>
                        <td class="text-center font-black">
                            {{ $company_summary['company_weighted_percentage_rounded'] }}%
                        </td>
                        <td class="text-center font-black">
                            {{ $company_summary['company_weighted_score_rounded'] }} / 5.0
                            <span class="chip chip-{{ $company_summary['rating']['key'] === 'outstanding' ? 'outstanding' : ($company_summary['rating']['key'] === 'exceeds_target' ? 'exceeds' : ($company_summary['rating']['key'] === 'meets_target' ? 'meets' : 'needs')) }}">
                                {{ $lang === 'en' ? $company_summary['rating']['label_en'] : $company_summary['rating']['label_am'] }}
                            </span>
                        </td>
                        <td style="font-style: italic; font-size: 8pt; color: #4338ca;">
                            {{ $lang === 'en' ? 'Company consolidated performance' : 'የተጠቃለለ የኩባንያ አጠቃላይ አፈፃፀም' }}
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- 4.2 Company Readiness Gate & Executive Decisions -->
            <div class="sub-header">
                4.2 {{ $lang === 'en' ? 'Executive Management Decisions & PLC Transition Gate' : 'የኩባንያ ውሳኔዎች እና የ PLC ሽግግር መስፈርቶች' }}
            </div>

            <!-- Readiness Gate Card -->
            <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; padding: 8px 10px; margin-bottom: 10px;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                    <strong>{{ $lang === 'en' ? 'PLC Readiness Benchmark Status:' : 'የ PLC ሽግግር ቅድመ-ሁኔታ መስፈርት ሁኔታ፡' }}</strong>
                    @if($gate_evaluation['is_met'])
                    <span class="chip chip-outstanding">✓ {{ $lang === 'en' ? 'Criteria Met' : 'መስፈርቱ ተሟልቷል' }}</span>
                    @else
                    <span class="chip chip-needs">✕ {{ $lang === 'en' ? 'Criteria Not Yet Met' : 'መስፈርቱ አልተሟላም' }}</span>
                    @endif
                </div>

                <div style="font-size: 8pt; color: #4b5563; line-height: 1.4;">
                    <strong>{{ $lang === 'en' ? 'Target Benchmark:' : 'የታለመው መስፈርት፡' }}</strong>
                    {{ $decision?->min_paying_customers ?? 20 }} {{ $lang === 'en' ? 'paying customers' : 'የሚከፍሉ ደንበኞች' }},
                    {{ number_format($decision?->min_mrr ?? 150000, 2) }} ETB MRR |
                    <strong>{{ $lang === 'en' ? 'Actual Performance:' : 'ትክክለኛ ክንውን፡' }}</strong>
                    {{ $decision?->actual_paying_customers ?? 0 }} {{ $lang === 'en' ? 'customers' : 'ደንበኞች' }},
                    {{ number_format($decision?->actual_mrr ?? 0, 2) }} ETB MRR
                </div>
            </div>

            <!-- Joint Decision Text -->
            <div class="comment-block">
                <div class="comment-label">{{ $lang === 'en' ? 'Joint Executive Evaluation Statement:' : 'የጋራ ውሳኔ እና የአፈፃፀም ማጠቃለያ መግለጫ፡' }}</div>
                <div class="comment-content">
                    {{ $decision?->decision_text_am ?: ($gate_evaluation['is_met'] ? $gate_evaluation['suggested_decision_am'] : $gate_evaluation['suggested_decision_am']) }}
                </div>
            </div>

            <!-- Numbered Decisions List -->
            <div class="comment-block">
                <div class="comment-label">{{ $lang === 'en' ? 'Numbered Board & Executive Decisions:' : 'በቁጥር የተዘረዘሩ የአመራሩ የጋራ ውሳኔዎች፡' }}</div>
                <ol style="margin: 4px 0 10px 18px; padding: 0; font-size: 8.5pt;">
                    @foreach($decisions_list as $decItem)
                    @if(trim($decItem) !== '')
                    <li style="margin-bottom: 4px;">{{ $decItem }}</li>
                    @endif
                    @endforeach
                </ol>
            </div>

            <!-- Operational & Capital Expansion Plan -->
            <div class="comment-block">
                <div class="comment-label">{{ $lang === 'en' ? 'Operational & Capital Expansion Plan:' : 'የማስፋፊያ እና የካፒታል ዕቅድ (Next Steps & Capital Strategy):' }}</div>
                <div class="comment-content">
                    {{ $decision?->expansion_text_am ?: ($lang === 'en' ? 'Continue sustainable growth under strategic plan.' : 'በስትራቴጂክ ዕቅዱ መሰረት ተጨማሪ አዳዲስ ሰራተኞችን ማሰልጠንና አገልግሎቶችን ማስፋፋት።') }}
                </div>
            </div>

            <!-- All Founders Signatures -->
            <div style="margin-top: 18px; border-top: 1px solid #d1d5db; padding-top: 10px;">
                <div style="font-weight: 700; margin-bottom: 10px; font-size: 9pt;">
                    {{ $lang === 'en' ? 'Signatures of the Founding Team:' : 'የመስራቾች ቦርድ የጋራ ፊርማ (Founding Team Signatures):' }}
                </div>
                <table style="width: 100%; border-collapse: collapse; font-size: 8pt;">
                    <tr>
                        @foreach($members as $m)
                        <td style="width: 33.3%; vertical-align: top; padding: 4px 6px;">
                            <div style="font-weight: 700; color: #111827;">{{ $m['name'] }}</div>
                            <div style="font-size: 7.5pt; color: #6b7280; margin-bottom: 14px;">
                                {{ $lang === 'en' ? $m['role_title_en'] : $m['role_title_am'] }}
                            </div>
                            <div style="border-bottom: 1px solid #9ca3af; width: 85%; height: 16px;"></div>
                            <div style="font-size: 7pt; color: #9ca3af; margin-top: 2px;">{{ $lang === 'en' ? 'Signature & Date' : 'ፊርማ እና ቀን' }}</div>
                        </td>
                        @endforeach
                    </tr>
                </table>
            </div>

        </div>

    </div>

    @if(isset($auto_print) && $auto_print)
    <script>
        window.addEventListener('DOMContentLoaded', () => {
            window.print();
        });
    </script>
    @endif
</body>
</html>
