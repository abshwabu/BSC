<?php

use App\Models\Member;
use App\Models\Report;
use App\Models\User;

test('authenticated user can view trends page with historical reports', function () {
    $user = User::factory()->create();

    $member = Member::create([
        'name' => 'ዮናስ ታደሰ',
        'role_title_am' => 'የሽያጭና ንግድ ልማት ኃላፊ',
        'role_title_en' => 'Head of Sales & Business Development',
        'position_key' => 'sales',
        'default_weights' => ['financial' => 25, 'customers' => 25, 'internal' => 25, 'learning' => 25],
        'sort_order' => 1,
    ]);

    // Create 2 quarterly reports
    $r1 = Report::create([
        'title' => '2026 Q1 Report',
        'period_label' => 'Q1',
        'period_type' => 'quarter',
        'year' => 2026,
        'report_date' => '2026-03-31',
        'status' => 'final',
        'company_name' => 'EthioTech Solutions',
    ]);

    $rm1 = $r1->reportMembers()->create([
        'member_id' => $member->id,
        'weights' => ['financial' => 25, 'customers' => 25, 'internal' => 25, 'learning' => 25],
    ]);

    $rm1->activities()->create([
        'activity_text' => 'የምርት ማሳያ',
        'kpi_text' => 'ማሳያዎች',
        'target_value' => 50,
        'actual_value' => 50,
        'direction' => 'higher_better',
        'perspective' => 'customers',
    ]);

    $r2 = Report::create([
        'title' => '2026 Q2 Report',
        'period_label' => 'Q2',
        'period_type' => 'quarter',
        'year' => 2026,
        'report_date' => '2026-06-30',
        'status' => 'draft',
        'company_name' => 'EthioTech Solutions',
    ]);

    $rm2 = $r2->reportMembers()->create([
        'member_id' => $member->id,
        'weights' => ['financial' => 25, 'customers' => 25, 'internal' => 25, 'learning' => 25],
    ]);

    $rm2->activities()->create([
        'activity_text' => 'የምርት ማሳያ',
        'kpi_text' => 'ማሳያዎች',
        'target_value' => 60,
        'actual_value' => 60,
        'direction' => 'higher_better',
        'perspective' => 'customers',
    ]);

    $response = $this->actingAs($user)->get(route('trends.index'));
    $response->assertOk();
});
