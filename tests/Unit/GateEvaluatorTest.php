<?php

use App\Services\Bsc\GateEvaluator;
use Tests\TestCase;

uses(TestCase::class);

test('gate evaluation returns disabled status when plc gate is not enabled', function () {
    $eval = GateEvaluator::evaluate(
        plcGateEnabled: false,
        minCustomers: 20,
        minMrr: 150000.0,
        actualCustomers: 25,
        actualMrr: 160000.0
    );

    expect($eval['enabled'])->toBeFalse()
        ->and($eval['is_met'])->toBeFalse()
        ->and($eval['status'])->toBe('disabled');
});

test('gate evaluation returns met when actuals meet or exceed minimum criteria', function () {
    $eval = GateEvaluator::evaluate(
        plcGateEnabled: true,
        minCustomers: 20,
        minMrr: 150000.0,
        actualCustomers: 20,
        actualMrr: 165000.0
    );

    expect($eval['enabled'])->toBeTrue()
        ->and($eval['is_met'])->toBeTrue()
        ->and($eval['customers_met'])->toBeTrue()
        ->and($eval['mrr_met'])->toBeTrue()
        ->and($eval['status'])->toBe('met')
        ->and($eval['suggested_decision_am'])->toContain('20 የሚከፍሉ ደንበኞችን')
        ->and($eval['suggested_decision_am'])->toContain('150,000.00 ETB MRR')
        ->and($eval['suggested_decision_am'])->toContain('165,000.00 ETB MRR');
});

test('gate evaluation returns not_met when either customers or MRR is below minimum', function () {
    // Customers below
    $evalCustBelow = GateEvaluator::evaluate(
        plcGateEnabled: true,
        minCustomers: 20,
        minMrr: 150000.0,
        actualCustomers: 15,
        actualMrr: 160000.0
    );

    expect($evalCustBelow['is_met'])->toBeFalse()
        ->and($evalCustBelow['customers_met'])->toBeFalse()
        ->and($evalCustBelow['mrr_met'])->toBeTrue()
        ->and($evalCustBelow['status'])->toBe('not_met')
        ->and($evalCustBelow['suggested_decision_am'])->toContain('ሙሉ በሙሉ ያላሟላ');

    // MRR below
    $evalMrrBelow = GateEvaluator::evaluate(
        plcGateEnabled: true,
        minCustomers: 20,
        minMrr: 150000.0,
        actualCustomers: 25,
        actualMrr: 140000.0
    );

    expect($evalMrrBelow['is_met'])->toBeFalse()
        ->and($evalMrrBelow['customers_met'])->toBeTrue()
        ->and($evalMrrBelow['mrr_met'])->toBeFalse()
        ->and($evalMrrBelow['status'])->toBe('not_met');
});

test('generateDecisionText formats templates in both Amharic and English', function () {
    $textAm = GateEvaluator::generateDecisionText(
        isMet: true,
        minCustomers: 20,
        minMrr: 150000.0,
        actualCustomers: 22,
        actualMrr: 175000.0,
        locale: 'am'
    );
    expect($textAm)->toContain('20 የሚከፍሉ ደንበኞችን')
        ->and($textAm)->toContain('175,000.00 ETB MRR')
        ->and($textAm)->toContain('PLC');

    $textEn = GateEvaluator::generateDecisionText(
        isMet: true,
        minCustomers: 20,
        minMrr: 150000.0,
        actualCustomers: 22,
        actualMrr: 175000.0,
        locale: 'en'
    );
    expect($textEn)->toContain('20 paying customers')
        ->and($textEn)->toContain('175,000.00 ETB MRR')
        ->and($textEn)->toContain('Private Limited Company (PLC)');
});

test('returns default decision items', function () {
    $amDecisions = GateEvaluator::getDefaultDecisions('am');
    expect($amDecisions)->toBeArray()->toHaveCount(2)
        ->and($amDecisions[0])->toContain('PLC')
        ->and($amDecisions[1])->toContain('የአገልግሎት');

    $enDecisions = GateEvaluator::getDefaultDecisions('en');
    expect($enDecisions)->toBeArray()->toHaveCount(2)
        ->and($enDecisions[0])->toContain('Private Limited Company (PLC)');
});
