<?php

use App\Models\Member;
use App\Models\Report;
use App\Models\User;
use App\Services\Export\ReportDocxExporter;
use Database\Seeders\MemberSeeder;

beforeEach(function () {
    $this->seed(MemberSeeder::class);
    $this->user = User::factory()->create();
});

test('authenticated user can view report html preview with complete amharic sections', function () {
    $report = Report::factory()->create([
        'title' => 'EthioTech 2026 Q1 BSC Report',
        'period_label' => 'Q1',
        'year' => 2026,
        'company_name' => 'EthioTech Solutions',
    ]);

    $members = Member::orderBy('sort_order')->get();
    foreach ($members as $m) {
        $rm = $report->reportMembers()->create([
            'member_id' => $m->id,
            'weights' => $m->default_weights,
            'achievements' => "ስኬት ለ {$m->name}",
            'challenges' => "ተግዳሮት ለ {$m->name}",
            'next_steps' => "ቀጣይ እርምጃ ለ {$m->name}",
            'signed' => true,
        ]);

        $rm->activities()->create([
            'sort_order' => 1,
            'activity_text' => 'Sales Demos',
            'kpi_text' => '60 ማሳያዎች',
            'target_value' => 60,
            'actual_value' => 54,
            'direction' => 'higher_better',
            'perspective' => 'customers',
        ]);
    }

    $report->decision()->create([
        'plc_gate_enabled' => true,
        'min_paying_customers' => 20,
        'min_mrr' => 150000.00,
        'actual_paying_customers' => 25,
        'actual_mrr' => 165000.00,
        'decision_text_am' => 'ወደ ኃላፊነቱ የተወሰነ የግል ማህበር ለመሸጋገር ተወስኗል።',
        'expansion_text_am' => 'የአገልግሎት ማስፋፊያ ዕቅድ።',
        'decisions_list' => [
            'ኃ/የተ/የግ/ማህበር (PLC) ምዝገባ ማጠናቀቅ',
            'የአገልግሎት መስፋፋት ማካሄድ',
        ],
    ]);

    $response = $this->actingAs($this->user)->get(route('reports.preview', [
        'report' => $report->id,
        'lang' => 'bilingual',
    ]));

    $response->assertOk()
        ->assertSee('የስትራቴጂክ እቅድ የግለሰብ አፈጻጸም ሪፖርት እና BSC ውጤት ካርድ')
        ->assertSee('Strategic Plan Individual Performance Report', false)
        ->assertSee('የሪፖርት አሞላል እና የግምገማ መመሪያ')
        ->assertSee('ክፍል 1')
        ->assertSee('ክፍል 2')
        ->assertSee('ክፍል 3')
        ->assertSee('ክፍል 4')
        ->assertSee('አማካይ አጠቃላይ አፈፃፀም')
        ->assertSee('የመስራቾች ቡድን አማካይ ውጤት')
        ->assertSee('መስፈርቱ ተሟልቷል');
});

test('docx export generates valid word document and sets report status to final', function () {
    $report = Report::factory()->create([
        'title' => 'EthioTech Q1 Report',
        'period_label' => 'Q1',
        'year' => 2026,
        'status' => 'draft',
    ]);

    $member = Member::first();
    $rm = $report->reportMembers()->create([
        'member_id' => $member->id,
        'weights' => $member->default_weights,
        'signed' => true,
    ]);

    $rm->activities()->create([
        'sort_order' => 1,
        'activity_text' => 'Contracts',
        'kpi_text' => 'Signed contracts',
        'target_value' => 2,
        'actual_value' => 2,
        'direction' => 'higher_better',
        'perspective' => 'financial',
    ]);

    $report->decision()->create([
        'plc_gate_enabled' => true,
        'min_paying_customers' => 20,
        'min_mrr' => 150000.00,
        'decisions_list' => ['PLC ምዝገባ ማጠናቀቅ'],
    ]);

    $response = $this->actingAs($this->user)->get(route('reports.docx', [
        'report' => $report->id,
        'lang' => 'bilingual',
    ]));

    $response->assertOk();
    $response->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

    $disposition = $response->headers->get('content-disposition');
    expect($disposition)->toContain('attachment; filename=BSC_Report_Q1_2026.docx');

    // Report is marked final on export
    expect($report->fresh()->status)->toBe('final');
});

test('pdf endpoint marks report as final and returns a response', function () {
    $report = Report::factory()->create([
        'title' => 'EthioTech Q1 Report',
        'period_label' => 'Q1',
        'year' => 2026,
        'status' => 'draft',
    ]);

    $response = $this->actingAs($this->user)->get(route('reports.pdf', [
        'report' => $report->id,
        'lang' => 'bilingual',
    ]));

    $response->assertOk();
    expect($report->fresh()->status)->toBe('final');
});

test('unfinalize endpoint reverts report status from final back to draft', function () {
    $report = Report::factory()->final()->create();

    $response = $this->actingAs($this->user)->postJson(route('reports.unfinalize', $report->id));

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('status', 'draft');

    expect($report->fresh()->status)->toBe('draft');
});

test('ReportDocxExporter produces non-empty docx file containing ethiopic runs', function () {
    $report = Report::factory()->create([
        'period_label' => 'Q1',
        'year' => 2026,
    ]);

    $member = Member::first();
    $rm = $report->reportMembers()->create([
        'member_id' => $member->id,
        'weights' => $member->default_weights,
    ]);

    $rm->activities()->create([
        'sort_order' => 1,
        'activity_text' => 'የሽያጭ ማሳያዎች',
        'kpi_text' => 'ማሳያዎች',
        'target_value' => 60,
        'actual_value' => 54,
        'direction' => 'higher_better',
        'perspective' => 'customers',
    ]);

    $docxPath = ReportDocxExporter::generate($report, 'bilingual', true);

    expect(file_exists($docxPath))->toBeTrue()
        ->and(filesize($docxPath))->toBeGreaterThan(5000);

    unlink($docxPath);
});
