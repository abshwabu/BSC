<?php

use App\Models\Member;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\UploadedFile;

test('authenticated user can export report raw data as json', function () {
    $user = User::factory()->create();

    $member = Member::create([
        'name' => 'ዳዊት ግርማ',
        'role_title_am' => 'የቴክኖሎጂና ምርት ልማት ኃላፊ',
        'role_title_en' => 'Chief Technology Officer',
        'position_key' => 'tech',
        'default_weights' => ['financial' => 25, 'customers' => 25, 'internal' => 25, 'learning' => 25],
        'sort_order' => 1,
    ]);

    $report = Report::create([
        'title' => '2026 Q1 Tech Report',
        'period_label' => 'Q1',
        'period_type' => 'quarter',
        'year' => 2026,
        'report_date' => '2026-03-31',
        'status' => 'draft',
        'company_name' => 'EthioTech Solutions',
    ]);

    $rm = $report->reportMembers()->create([
        'member_id' => $member->id,
        'weights' => ['financial' => 25, 'customers' => 25, 'internal' => 25, 'learning' => 25],
        'achievements' => 'ስኬት',
        'challenges' => 'ተግዳሮት',
        'next_steps' => 'ቀጣይ እርምጃ',
        'signed' => true,
        'readiness_note' => 'ዝግጁ',
    ]);

    $rm->activities()->create([
        'sort_order' => 1,
        'activity_text' => 'የሰርቨር ዝግጁነት',
        'kpi_text' => 'Uptime %',
        'target_value' => 99.5,
        'target_label' => '%',
        'actual_value' => 99.8,
        'actual_label' => '%',
        'direction' => 'higher_better',
        'perspective' => 'internal',
    ]);

    $report->decision()->create([
        'plc_gate_enabled' => true,
        'min_paying_customers' => 20,
        'min_mrr' => 500000.00,
        'actual_paying_customers' => 25,
        'actual_mrr' => 600000.00,
        'decision_text_am' => 'የተሳካ ውሳኔ',
    ]);

    $response = $this->actingAs($user)->get(route('reports.export-json', $report->id));
    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/json');

    $content = $response->streamedContent();
    $data = json_decode($content, true);

    expect($data)->toBeArray()
        ->and($data['generator'])->toBe('BSC Report Builder')
        ->and($data['report']['title'])->toBe('2026 Q1 Tech Report')
        ->and($data['decision']['actual_paying_customers'])->toBe(25)
        ->and($data['members'])->toHaveCount(1)
        ->and($data['members'][0]['activities'][0]['activity_text'])->toBe('የሰርቨር ዝግጁነት');
});

test('authenticated user can import report from json file', function () {
    $user = User::factory()->create();

    $jsonPayload = [
        'generator' => 'BSC Report Builder',
        'version' => '1.0',
        'report' => [
            'title' => 'Imported 2026 Q3 Strategic Report',
            'period_label' => 'Q3',
            'period_type' => 'quarter',
            'year' => 2026,
            'report_date' => '2026-09-30',
            'company_name' => 'Acme Ethiopia',
        ],
        'decision' => [
            'plc_gate_enabled' => true,
            'min_paying_customers' => 30,
            'min_mrr' => 600000.00,
            'actual_paying_customers' => 32,
            'actual_mrr' => 650000.00,
            'decision_text_am' => 'የውሳኔ ጽሑፍ',
        ],
        'members' => [
            [
                'position_key' => 'sales',
                'member_name' => 'ዮናስ ታደሰ',
                'role_title_am' => 'የሽያጭ ኃላፊ',
                'weights' => ['financial' => 25, 'customers' => 25, 'internal' => 25, 'learning' => 25],
                'achievements' => 'ከፍተኛ ሽያጭ',
                'challenges' => 'የገበያ ሁኔታ',
                'next_steps' => 'አዳዲስ ደንበኞች',
                'signed' => true,
                'activities' => [
                    [
                        'sort_order' => 1,
                        'activity_text' => 'ማሳያ ማቅረብ',
                        'kpi_text' => 'ማሳያዎች',
                        'target_value' => 70,
                        'target_label' => 'ማሳያዎች',
                        'actual_value' => 75,
                        'actual_label' => 'ማሳያዎች',
                        'direction' => 'higher_better',
                        'perspective' => 'customers',
                    ],
                ],
            ],
        ],
    ];

    $file = UploadedFile::fake()->createWithContent(
        'report-backup.json',
        json_encode($jsonPayload)
    );

    $response = $this->actingAs($user)->post(route('reports.import-json'), [
        'json_file' => $file,
    ]);

    $importedReport = Report::where('title', 'like', '%Imported 2026 Q3 Strategic Report%')->first();
    expect($importedReport)->not->toBeNull();

    $response->assertRedirect(route('reports.edit', $importedReport->id));

    $this->assertDatabaseHas('reports', [
        'id' => $importedReport->id,
        'period_label' => 'Q3',
        'year' => 2026,
        'company_name' => 'Acme Ethiopia',
    ]);

    $this->assertDatabaseHas('report_decisions', [
        'report_id' => $importedReport->id,
        'actual_paying_customers' => 32,
    ]);

    $this->assertDatabaseHas('activities', [
        'activity_text' => 'ማሳያ ማቅረብ',
        'target_value' => 70,
        'actual_value' => 75,
    ]);
});
