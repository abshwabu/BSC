<?php

use App\Models\Member;
use App\Models\Report;
use App\Models\User;
use Database\Seeders\MemberSeeder;

beforeEach(function () {
    $this->seed(MemberSeeder::class);
    $this->user = User::factory()->create();
});

test('authenticated user can view reports list', function () {
    $report = Report::factory()->create(['title' => 'EthioTech Q1 2026']);

    $response = $this->actingAs($this->user)->get(route('reports.index'));

    $response->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Reports/Index')
            ->has('reports', 1)
            ->where('reports.0.title', 'EthioTech Q1 2026')
        );
});

test('creating a report attaches 3 default members and redirects to wizard edit', function () {
    $response = $this->actingAs($this->user)->post(route('reports.store'), [
        'title' => 'Q1 2026 BSC Performance Report',
        'period_label' => 'Q1',
        'period_type' => 'quarter',
        'year' => 2026,
        'report_date' => '2026-03-31',
        'company_name' => 'EthioTech Solutions',
    ]);

    $report = Report::first();

    $response->assertRedirect(route('reports.edit', $report->id));

    expect($report->reportMembers)->toHaveCount(3)
        ->and($report->decision)->not->toBeNull()
        ->and($report->status)->toBe('draft');

    // Verify member weights attached
    $salesMember = $report->reportMembers->firstWhere('member.position_key', 'sales');
    expect($salesMember->weights)->toBe([
        'financial' => 35,
        'customers' => 30,
        'internal' => 20,
        'learning' => 15,
    ]);
});

test('autosaving member activities with the 7 sample activities persists data accurately', function () {
    $report = Report::factory()->create();
    $member = Member::where('position_key', 'sales')->first();
    $reportMember = $report->reportMembers()->create([
        'member_id' => $member->id,
        'weights' => $member->default_weights,
    ]);

    // 7 sample activities from acceptance criteria:
    // 1. Demos 60/54
    // 2. New paying customers 5/6
    // 3. MRR 150000/165000
    // 4. PLC 100/100
    // 5. Custom contracts 2/2
    // 6. Churn <5 vs 2 lower_better
    // 7. Training 2/2
    $sampleActivities = [
        [
            'activity_text' => 'Product Demos',
            'kpi_text' => 'Completed demos',
            'target_value' => 60,
            'target_label' => '60 ማሳያዎች',
            'actual_value' => 54,
            'actual_label' => '54 ማሳያዎች',
            'direction' => 'higher_better',
            'perspective' => 'customers',
        ],
        [
            'activity_text' => 'New paying customers',
            'kpi_text' => 'Acquired clients',
            'target_value' => 5,
            'target_label' => '5 ደንበኞች',
            'actual_value' => 6,
            'actual_label' => '6 ደንበኞች',
            'direction' => 'higher_better',
            'perspective' => 'customers',
        ],
        [
            'activity_text' => 'Monthly Recurring Revenue',
            'kpi_text' => 'MRR growth',
            'target_value' => 150000,
            'target_label' => '150,000 ETB',
            'actual_value' => 165000,
            'actual_label' => '165,000 ETB',
            'direction' => 'higher_better',
            'perspective' => 'financial',
        ],
        [
            'activity_text' => 'PLC Transition Gate',
            'kpi_text' => 'Legal requirements completed',
            'target_value' => 100,
            'target_label' => '100%',
            'actual_value' => 100,
            'actual_label' => '100%',
            'direction' => 'higher_better',
            'perspective' => 'internal',
        ],
        [
            'activity_text' => 'Custom contracts closed',
            'kpi_text' => 'High-ticket deals',
            'target_value' => 2,
            'target_label' => '2 ውሎች',
            'actual_value' => 2,
            'actual_label' => '2 ውሎች',
            'direction' => 'higher_better',
            'perspective' => 'financial',
        ],
        [
            'activity_text' => 'Customer Churn Rate',
            'kpi_text' => 'Lost accounts percentage',
            'target_value' => 5,
            'target_label' => '< 5%',
            'actual_value' => 2,
            'actual_label' => '2%',
            'direction' => 'lower_better',
            'perspective' => 'customers',
        ],
        [
            'activity_text' => 'Team sales training',
            'kpi_text' => 'Workshops held',
            'target_value' => 2,
            'target_label' => '2 ስልጠናዎች',
            'actual_value' => 2,
            'actual_label' => '2 ስልጠናዎች',
            'direction' => 'higher_better',
            'perspective' => 'learning',
        ],
    ];

    $response = $this->actingAs($this->user)->patchJson(
        route('reports.members.update', [$report->id, $reportMember->id]),
        [
            'achievements' => 'Q1 targets achieved with outstanding MRR',
            'challenges' => 'Recruitment bottlenecks',
            'next_steps' => 'Scale sales outreach in Q2',
            'signed' => true,
            'activities' => $sampleActivities,
        ]
    );

    $response->assertOk()
        ->assertJsonPath('success', true);

    // Verify reloading preserves all data
    $reportMember->refresh();
    expect($reportMember->activities)->toHaveCount(7)
        ->and($reportMember->signed)->toBeTrue()
        ->and($reportMember->achievements)->toBe('Q1 targets achieved with outstanding MRR');

    $churnActivity = $reportMember->activities->firstWhere('activity_text', 'Customer Churn Rate');
    expect($churnActivity->direction)->toBe('lower_better')
        ->and((float) $churnActivity->target_value)->toBe(5.0)
        ->and((float) $churnActivity->actual_value)->toBe(2.0);
});

test('autosaving decision updates company decision record', function () {
    $report = Report::factory()->create();

    $response = $this->actingAs($this->user)->patchJson(
        route('reports.decision.update', $report->id),
        [
            'plc_gate_enabled' => true,
            'min_paying_customers' => 30,
            'min_mrr' => 180000.00,
            'decision_text_am' => 'ሁሉም መስፈርቶች ተሟልተዋል።',
            'expansion_text_am' => 'አዲስ ቅርንጫፍ መክፈት።',
        ]
    );

    $response->assertOk()
        ->assertJsonPath('success', true);

    $decision = $report->decision()->first();
    expect($decision->plc_gate_enabled)->toBeTrue()
        ->and($decision->min_paying_customers)->toBe(30)
        ->and((float) $decision->min_mrr)->toBe(180000.00)
        ->and($decision->decision_text_am)->toBe('ሁሉም መስፈርቶች ተሟልተዋል።');
});

test('duplicating a report creates a new draft for the next quarter', function () {
    $report = Report::factory()->final()->create([
        'title' => 'EthioTech BSC Q1 2026',
        'period_label' => 'Q1',
        'year' => 2026,
    ]);

    $member = Member::first();
    $reportMember = $report->reportMembers()->create([
        'member_id' => $member->id,
        'weights' => $member->default_weights,
    ]);

    $reportMember->activities()->create([
        'sort_order' => 1,
        'activity_text' => 'Sales Demo',
        'kpi_text' => 'Number of demos',
        'target_value' => 60,
        'actual_value' => 54,
        'direction' => 'higher_better',
        'perspective' => 'customers',
    ]);

    $response = $this->actingAs($this->user)->post(route('reports.duplicate', $report->id));

    $newReport = Report::latest('id')->first();
    $response->assertRedirect(route('reports.edit', $newReport->id));

    expect($newReport->id)->not->toBe($report->id)
        ->and($newReport->status)->toBe('draft')
        ->and($newReport->period_label)->toBe('Q2')
        ->and($newReport->reportMembers)->toHaveCount(1);

    // Cloned activity target kept, actual reset to null
    $clonedActivity = $newReport->reportMembers->first()->activities->first();
    expect((float) $clonedActivity->target_value)->toBe(60.0)
        ->and($clonedActivity->actual_value)->toBeNull();
});

test('new report automatically copies previous quarter activities when prior report exists', function () {
    // 1. Prior Report with activities
    $priorReport = Report::factory()->create([
        'period_label' => 'Q1',
        'year' => 2026,
    ]);

    $members = Member::orderBy('sort_order')->get();
    foreach ($members as $m) {
        $rm = $priorReport->reportMembers()->create([
            'member_id' => $m->id,
            'weights' => $m->default_weights,
        ]);
        $rm->activities()->create([
            'sort_order' => 1,
            'activity_text' => "Target for {$m->position_key}",
            'kpi_text' => 'Metric',
            'target_value' => 100,
            'actual_value' => 95,
            'direction' => 'higher_better',
            'perspective' => 'internal',
        ]);
    }

    // 2. Create Next Report
    $response = $this->actingAs($this->user)->post(route('reports.store'), [
        'title' => 'Q2 2026 Report',
        'period_label' => 'Q2',
        'year' => 2026,
    ]);

    $newReport = Report::latest('id')->first();
    $response->assertRedirect(route('reports.edit', $newReport->id));

    expect($newReport->reportMembers)->toHaveCount(3);
    foreach ($newReport->reportMembers as $rm) {
        expect($rm->activities)->toHaveCount(1);
        $act = $rm->activities->first();
        expect((float) $act->target_value)->toBe(100.0)
            ->and($act->actual_value)->toBeNull();
    }
});

test('saving member is blocked when weights do not total 100', function () {
    $report = Report::factory()->create();
    $member = Member::first();
    $reportMember = $report->reportMembers()->create([
        'member_id' => $member->id,
        'weights' => $member->default_weights,
    ]);

    // Invalid weights totaling 90% (30 + 30 + 20 + 10)
    $response = $this->actingAs($this->user)->patchJson(
        route('reports.members.update', [$report->id, $reportMember->id]),
        [
            'weights' => [
                'financial' => 30,
                'customers' => 30,
                'internal' => 20,
                'learning' => 10,
            ],
            'activities' => [],
        ]
    );

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['weights']);
});

test('autosaving decision updates actuals, gate criteria and decisions list', function () {
    $report = Report::factory()->create();

    $payload = [
        'plc_gate_enabled' => true,
        'min_paying_customers' => 20,
        'min_mrr' => 150000.00,
        'actual_paying_customers' => 25,
        'actual_mrr' => 165000.00,
        'decision_text_am' => 'መስፈርቱን ስላሟላን ወደ PLC እንሸጋገራለን።',
        'expansion_text_am' => 'በቀጣይ ሩብ ዓመት ተጨማሪ ካፒታል ማሰባሰብ።',
        'decisions_list' => [
            'ኃ/የተ/የግ/ማህበር (PLC) ምዝገባ ማጠናቀቅ',
            'የአገልግሎት መስፋፋት ማካሄድ',
            'ተጨማሪ 2 የቴክኖሎጂ ባለሙያዎችን መቅጠር',
        ],
    ];

    $response = $this->actingAs($this->user)->patchJson(
        route('reports.decision.update', $report->id),
        $payload
    );

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('decision.actual_paying_customers', 25)
        ->assertJsonPath('decision.decisions_list.2', 'ተጨማሪ 2 የቴክኖሎጂ ባለሙያዎችን መቅጠር');

    // Reload from fresh database query
    $decision = $report->fresh()->decision;
    expect($decision->plc_gate_enabled)->toBeTrue()
        ->and($decision->min_paying_customers)->toBe(20)
        ->and((float) $decision->min_mrr)->toBe(150000.00)
        ->and($decision->actual_paying_customers)->toBe(25)
        ->and((float) $decision->actual_mrr)->toBe(165000.00)
        ->and($decision->decisions_list)->toHaveCount(3)
        ->and($decision->decisions_list[0])->toBe('ኃ/የተ/የግ/ማህበር (PLC) ምዝገባ ማጠናቀቅ');
});

test('member readiness_note persists and does not wipe activities when omitted', function () {
    $report = Report::factory()->create();
    $member = Member::first();
    $reportMember = $report->reportMembers()->create([
        'member_id' => $member->id,
        'weights' => $member->default_weights,
    ]);

    $activity = $reportMember->activities()->create([
        'sort_order' => 1,
        'activity_text' => 'Sales Meetings',
        'kpi_text' => 'Meetings conducted',
        'target_value' => 20,
        'actual_value' => 22,
        'direction' => 'higher_better',
        'perspective' => 'customers',
    ]);

    // Update only readiness_note
    $response = $this->actingAs($this->user)->patchJson(
        route('reports.members.update', [$report->id, $reportMember->id]),
        [
            'readiness_note' => 'ለ PLC ሽግግር ሙሉ በሙሉ ዝግጁ ነው፤ አዳዲስ ሰራተኞችን ማሰልጠን ይቀጥላል።',
        ]
    );

    $response->assertOk()
        ->assertJsonPath('success', true);

    $refreshed = $reportMember->fresh();
    expect($refreshed->readiness_note)->toBe('ለ PLC ሽግግር ሙሉ በሙሉ ዝግጁ ነው፤ አዳዲስ ሰራተኞችን ማሰልጠን ይቀጥላል።')
        // Ensure activities were not deleted!
        ->and($refreshed->activities)->toHaveCount(1)
        ->and($refreshed->activities->first()->id)->toBe($activity->id);
});
