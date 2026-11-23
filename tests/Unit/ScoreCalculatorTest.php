<?php

uses(Tests\TestCase::class);

use App\Services\Bsc\ScoreCalculator;

test('calculates achievement percentage for higher_better', function () {
    // Normal calculation
    expect(ScoreCalculator::calculateAchievementPercentage(100, 50, 'higher_better'))->toBe(50.0)
        ->and(ScoreCalculator::calculateAchievementPercentage(100, 100, 'higher_better'))->toBe(100.0)
        ->and(ScoreCalculator::calculateAchievementPercentage(50, 45, 'higher_better'))->toBe(90.0);

    // Capping at default 120%
    expect(ScoreCalculator::calculateAchievementPercentage(100, 150, 'higher_better'))->toBe(120.0);

    // Custom cap
    expect(ScoreCalculator::calculateAchievementPercentage(100, 150, 'higher_better', 110.0))->toBe(110.0);

    // Null and edge cases
    expect(ScoreCalculator::calculateAchievementPercentage(null, 50, 'higher_better'))->toBe(0.0)
        ->and(ScoreCalculator::calculateAchievementPercentage(100, null, 'higher_better'))->toBe(0.0)
        ->and(ScoreCalculator::calculateAchievementPercentage(0, 50, 'higher_better'))->toBe(0.0);
});

test('calculates achievement percentage for lower_better', function () {
    // Target 10, actual 20 (worse performance) -> 50%
    expect(ScoreCalculator::calculateAchievementPercentage(10, 20, 'lower_better'))->toBe(50.0);

    // Target 10, actual 10 (target met) -> 100%
    expect(ScoreCalculator::calculateAchievementPercentage(10, 10, 'lower_better'))->toBe(100.0);

    // Target 10, actual 5 (better performance, 200%) -> capped at 120%
    expect(ScoreCalculator::calculateAchievementPercentage(10, 5, 'lower_better'))->toBe(120.0);

    // Actual is 0 -> should return cap
    expect(ScoreCalculator::calculateAchievementPercentage(10, 0, 'lower_better'))->toBe(120.0)
        ->and(ScoreCalculator::calculateAchievementPercentage(10, 0, 'lower_better', 115.0))->toBe(115.0);

    // Edge cases
    expect(ScoreCalculator::calculateAchievementPercentage(null, 5, 'lower_better'))->toBe(0.0)
        ->and(ScoreCalculator::calculateAchievementPercentage(10, null, 'lower_better'))->toBe(0.0)
        ->and(ScoreCalculator::calculateAchievementPercentage(0, 5, 'lower_better'))->toBe(0.0);
});

test('calculates score from percentage across all boundary values', function () {
    // Boundary: 59.9 (strictly below 3.0)
    $score59_9 = ScoreCalculator::calculateScoreFromPercentage(59.9);
    expect($score59_9)->toBeLessThan(3.0)
        ->and($score59_9)->toBeGreaterThan(2.99)
        ->and(ScoreCalculator::getRatingLabel($score59_9)['key'])->toBe('needs_improvement');

    // Boundary: 60.0 (exactly 3.0)
    $score60 = ScoreCalculator::calculateScoreFromPercentage(60.0);
    expect($score60)->toEqualWithDelta(3.0, 0.0001)
        ->and(ScoreCalculator::getRatingLabel($score60)['key'])->toBe('meets_target');

    // Boundary: 74.0 (3.0 + 14 * 0.9/14 = 3.9)
    $score74 = ScoreCalculator::calculateScoreFromPercentage(74.0);
    expect($score74)->toEqualWithDelta(3.9, 0.0001)
        ->and(ScoreCalculator::getRatingLabel($score74)['key'])->toBe('meets_target');

    // Boundary: 75.0 (exactly 4.0)
    $score75 = ScoreCalculator::calculateScoreFromPercentage(75.0);
    expect($score75)->toEqualWithDelta(4.0, 0.0001)
        ->and(ScoreCalculator::getRatingLabel($score75)['key'])->toBe('exceeds_target');

    // Boundary: 89.0 (4.0 + 14 * 0.4/14 = 4.4)
    $score89 = ScoreCalculator::calculateScoreFromPercentage(89.0);
    expect($score89)->toEqualWithDelta(4.4, 0.0001)
        ->and(ScoreCalculator::getRatingLabel($score89)['key'])->toBe('exceeds_target');

    // Boundary: 90.0 (exactly 4.5)
    $score90 = ScoreCalculator::calculateScoreFromPercentage(90.0);
    expect($score90)->toEqualWithDelta(4.5, 0.0001)
        ->and(ScoreCalculator::getRatingLabel($score90)['key'])->toBe('outstanding');

    // Boundary: 100.0 (4.5 + 10 * 0.05 = 5.0)
    $score100 = ScoreCalculator::calculateScoreFromPercentage(100.0);
    expect($score100)->toEqualWithDelta(5.0, 0.0001)
        ->and(ScoreCalculator::getRatingLabel($score100)['key'])->toBe('outstanding');

    // Boundary: 120.0 (capped at 5.0)
    $score120 = ScoreCalculator::calculateScoreFromPercentage(120.0);
    expect($score120)->toEqualWithDelta(5.0, 0.0001)
        ->and(ScoreCalculator::getRatingLabel($score120)['key'])->toBe('outstanding');
});

test('rating labels are bilingual and accurately mapped', function () {
    $outstanding = ScoreCalculator::getRatingLabel(5.0);
    expect($outstanding['label_am'])->toBe('እጅግ የላቀ')
        ->and($outstanding['label_en'])->toBe('Outstanding');

    $exceeds = ScoreCalculator::getRatingLabel(4.2);
    expect($exceeds['label_am'])->toBe('ከፍተኛ')
        ->and($exceeds['label_en'])->toBe('Exceeds Target');

    $meets = ScoreCalculator::getRatingLabel(3.5);
    expect($meets['label_am'])->toBe('የተሳካ')
        ->and($meets['label_en'])->toBe('Meets Target');

    $needs = ScoreCalculator::getRatingLabel(2.5);
    expect($needs['label_am'])->toBe('ትኩረት የሚሻ')
        ->and($needs['label_en'])->toBe('Needs Improvement');
});

test('produces correct weighted score and percentage for standard fixture', function () {
    // Fixture: weights 35/30/20/15 with perspective averages 110/95/100/100
    // Scores must be 5.0 / 4.75 / 5.0 / 5.0
    // Weighted score ≈ 4.925 and weighted % ≈ 102.0
    $weights = [
        'financial' => 35,
        'customers' => 30,
        'internal' => 20,
        'learning' => 15,
    ];

    $averages = [
        'financial' => 110.0,
        'customers' => 95.0,
        'internal' => 100.0,
        'learning' => 100.0,
    ];

    $summary = ScoreCalculator::calculateFromPerspectiveAverages($averages, $weights);

    expect($summary['perspectives']['financial']['score'])->toEqualWithDelta(5.0, 0.001)
        ->and($summary['perspectives']['customers']['score'])->toEqualWithDelta(4.75, 0.001)
        ->and($summary['perspectives']['internal']['score'])->toEqualWithDelta(5.0, 0.001)
        ->and($summary['perspectives']['learning']['score'])->toEqualWithDelta(5.0, 0.001);

    expect($summary['weighted_score'])->toEqualWithDelta(4.925, 0.001)
        ->and($summary['weighted_percentage'])->toEqualWithDelta(102.0, 0.001)
        ->and($summary['weighted_score_rounded'])->toBe(4.93)
        ->and($summary['weighted_percentage_rounded'])->toBe(102.0)
        ->and($summary['has_redistributed_weights'])->toBeFalse();
});

test('redistributes weights proportionally when a perspective has no activities', function () {
    $weights = [
        'financial' => 35,
        'customers' => 30,
        'internal' => 20,
        'learning' => 15,
    ];

    // Activities for only 3 perspectives (learning is empty)
    $activities = [
        [
            'perspective' => 'financial',
            'target_value' => 100,
            'actual_value' => 100, // 100% -> score 5.0
            'direction' => 'higher_better',
        ],
        [
            'perspective' => 'customers',
            'target_value' => 100,
            'actual_value' => 75, // 75% -> score 4.0
            'direction' => 'higher_better',
        ],
        [
            'perspective' => 'internal',
            'target_value' => 100,
            'actual_value' => 60, // 60% -> score 3.0
            'direction' => 'higher_better',
        ],
    ];

    $summary = ScoreCalculator::calculateMemberSummary($activities, $weights);

    expect($summary['has_redistributed_weights'])->toBeTrue()
        ->and($summary['active_perspective_count'])->toBe(3);

    // Sum of active weights = 35 + 30 + 20 = 85
    $expectedFinWeight = (35.0 / 85.0) * 100.0;
    $expectedCustWeight = (30.0 / 85.0) * 100.0;
    $expectedIntWeight = (20.0 / 85.0) * 100.0;

    expect($summary['perspectives']['financial']['effective_weight'])->toEqualWithDelta($expectedFinWeight, 0.001)
        ->and($summary['perspectives']['customers']['effective_weight'])->toEqualWithDelta($expectedCustWeight, 0.001)
        ->and($summary['perspectives']['internal']['effective_weight'])->toEqualWithDelta($expectedIntWeight, 0.001)
        ->and($summary['perspectives']['learning']['effective_weight'])->toBe(0.0)
        ->and($summary['perspectives']['learning']['excluded'])->toBeTrue()
        ->and($summary['perspectives']['learning']['redistributed'])->toBeTrue();

    // Verify sum of effective weights equals 100
    $effectiveSum = $summary['perspectives']['financial']['effective_weight']
        + $summary['perspectives']['customers']['effective_weight']
        + $summary['perspectives']['internal']['effective_weight'];

    expect($effectiveSum)->toEqualWithDelta(100.0, 0.001);
});

test('formats activity level bsc perspective and score string', function () {
    $formattedAm = ScoreCalculator::formatPerspectiveScoreString('financial', 5.0, 'am');
    expect($formattedAm)->toBe('ፋይናንስ (5.0/5 - እጅግ የላቀ)');

    $formattedEn = ScoreCalculator::formatPerspectiveScoreString('customers', 4.0, 'en');
    expect($formattedEn)->toBe('Customers (4.0/5 - Exceeds Target)');
});

test('calculates consolidated company average across multiple members', function () {
    $member1 = [
        'weighted_percentage' => 100.0,
        'weighted_score' => 5.0,
    ];

    $member2 = [
        'weighted_percentage' => 80.0,
        'weighted_score' => 4.0,
    ];

    $company = ScoreCalculator::calculateCompanySummary([$member1, $member2]);

    expect($company['member_count'])->toBe(2)
        ->and($company['company_weighted_percentage'])->toBe(90.0)
        ->and($company['company_weighted_percentage_rounded'])->toBe(90.0)
        ->and($company['company_weighted_score'])->toBe(4.5)
        ->and($company['company_weighted_score_rounded'])->toBe(4.5)
        ->and($company['rating']['key'])->toBe('outstanding');
});
