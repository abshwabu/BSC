<?php

use App\Models\Activity;
use App\Models\Member;
use App\Models\Report;
use App\Models\ReportDecision;
use App\Models\ReportMember;

test('report can be duplicated to the next quarter with actuals reset', function () {
    $report = Report::factory()->final()->create([
        'title' => 'EthioTech BSC Q1 2026',
        'period_label' => 'Q1',
        'year' => 2026,
        'company_name' => 'EthioTech Solutions',
    ]);

    $member = Member::factory()->create();

    $reportMember = ReportMember::factory()->signed()->create([
        'report_id' => $report->id,
        'member_id' => $member->id,
        'weights' => ['financial' => 35, 'customers' => 30, 'internal' => 20, 'learning' => 15],
        'achievements' => 'Q1 record sales achieved',
        'challenges' => 'Hiring delay',
        'next_steps' => 'Recruit 2 reps',
    ]);

    $activity = Activity::factory()->create([
        'report_member_id' => $reportMember->id,
        'sort_order' => 1,
        'activity_text' => 'Demo calls',
        'kpi_text' => 'Number of closed deals',
        'target_value' => 50,
        'target_label' => '50 deals',
        'actual_value' => 48,
        'actual_label' => '48 deals',
        'direction' => 'higher_better',
        'perspective' => 'customers',
    ]);

    $decision = ReportDecision::factory()->create([
        'report_id' => $report->id,
        'plc_gate_enabled' => true,
        'min_paying_customers' => 25,
        'min_mrr' => 200000.00,
        'decision_text_am' => 'ቅድመ ሁኔታ ተሟልቷል',
        'expansion_text_am' => 'አዲስ ቢሮ መክፈት',
    ]);

    $cloned = $report->duplicate();

    // Verify cloned report attributes
    expect($cloned->id)->not->toBe($report->id)
        ->and($cloned->status)->toBe('draft')
        ->and($cloned->period_label)->toBe('Q2')
        ->and($cloned->year)->toBe(2026)
        ->and($cloned->company_name)->toBe('EthioTech Solutions')
        ->and($cloned->report_date)->toBeNull();

    // Verify cloned report member attributes
    expect($cloned->reportMembers)->toHaveCount(1);
    $clonedMember = $cloned->reportMembers->first();

    expect($clonedMember->member_id)->toBe($member->id)
        ->and($clonedMember->weights)->toBe(['financial' => 35, 'customers' => 30, 'internal' => 20, 'learning' => 15])
        ->and($clonedMember->signed)->toBeFalse()
        ->and($clonedMember->achievements)->toBeNull()
        ->and($clonedMember->challenges)->toBeNull()
        ->and($clonedMember->next_steps)->toBeNull();

    // Verify cloned activities and actuals reset
    expect($clonedMember->activities)->toHaveCount(1);
    $clonedActivity = $clonedMember->activities->first();

    expect($clonedActivity->sort_order)->toBe(1)
        ->and($clonedActivity->activity_text)->toBe('Demo calls')
        ->and($clonedActivity->kpi_text)->toBe('Number of closed deals')
        ->and((float) $clonedActivity->target_value)->toBe(50.0)
        ->and($clonedActivity->target_label)->toBe('50 deals')
        ->and($clonedActivity->actual_value)->toBeNull()
        ->and($clonedActivity->actual_label)->toBeNull()
        ->and($clonedActivity->direction)->toBe('higher_better')
        ->and($clonedActivity->perspective)->toBe('customers');

    // Verify decision cloning
    expect($cloned->decision)->not->toBeNull()
        ->and($cloned->decision->id)->not->toBe($decision->id)
        ->and($cloned->decision->plc_gate_enabled)->toBeTrue()
        ->and($cloned->decision->min_paying_customers)->toBe(25)
        ->and((float) $cloned->decision->min_mrr)->toBe(200000.0)
        ->and($cloned->decision->decision_text_am)->toBe('ቅድመ ሁኔታ ተሟልቷል');

    // Original records remain untouched
    $report->refresh();
    expect($report->status)->toBe('final')
        ->and($reportMember->fresh()->signed)->toBeTrue()
        ->and((float) $activity->fresh()->actual_value)->toBe(48.0);
});

test('duplicate advances Q4 to Q1 of the following year', function () {
    $report = Report::factory()->create([
        'title' => 'EthioTech BSC Q4 2026',
        'period_label' => 'Q4',
        'year' => 2026,
    ]);

    $cloned = $report->duplicate();

    expect($cloned->period_label)->toBe('Q1')
        ->and($cloned->year)->toBe(2027)
        ->and($cloned->status)->toBe('draft');
});

test('duplicate accepts custom attribute overrides', function () {
    $report = Report::factory()->create([
        'title' => 'Initial Report',
        'period_label' => 'Q1',
        'year' => 2026,
    ]);

    $cloned = $report->duplicate([
        'title' => 'Custom Target Report',
        'period_label' => 'Q3',
        'year' => 2028,
        'notes' => 'Custom note',
    ]);

    expect($cloned->title)->toBe('Custom Target Report')
        ->and($cloned->period_label)->toBe('Q3')
        ->and($cloned->year)->toBe(2028)
        ->and($cloned->notes)->toBe('Custom note')
        ->and($cloned->status)->toBe('draft');
});
