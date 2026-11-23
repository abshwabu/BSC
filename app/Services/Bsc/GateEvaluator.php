<?php

namespace App\Services\Bsc;

class GateEvaluator
{
    /**
     * Evaluate the PLC transition readiness gate.
     *
     * @return array{
     *     enabled: bool,
     *     is_met: bool,
     *     customers_met: bool,
     *     mrr_met: bool,
     *     status: 'met'|'not_met'|'disabled',
     *     suggested_decision_am: string,
     *     suggested_decision_en: string
     * }
     */
    public static function evaluate(
        bool $plcGateEnabled,
        ?int $minCustomers,
        ?float $minMrr,
        ?int $actualCustomers,
        ?float $actualMrr
    ): array {
        if (! $plcGateEnabled) {
            return [
                'enabled' => false,
                'is_met' => false,
                'customers_met' => false,
                'mrr_met' => false,
                'status' => 'disabled',
                'suggested_decision_am' => '',
                'suggested_decision_en' => '',
            ];
        }

        $minCust = $minCustomers ?? 0;
        $minRevenue = $minMrr ?? 0.0;
        $actCust = $actualCustomers ?? 0;
        $actRevenue = $actualMrr ?? 0.0;

        $customersMet = $actCust >= $minCust;
        $mrrMet = $actRevenue >= $minRevenue;
        $isMet = $customersMet && $mrrMet;

        return [
            'enabled' => true,
            'is_met' => $isMet,
            'customers_met' => $customersMet,
            'mrr_met' => $mrrMet,
            'status' => $isMet ? 'met' : 'not_met',
            'suggested_decision_am' => self::generateDecisionText($isMet, $minCust, $minRevenue, $actCust, $actRevenue, 'am'),
            'suggested_decision_en' => self::generateDecisionText($isMet, $minCust, $minRevenue, $actCust, $actRevenue, 'en'),
        ];
    }

    /**
     * Generate the decision statement text from template and parameters.
     */
    public static function generateDecisionText(
        bool $isMet,
        int $minCustomers,
        float $minMrr,
        int $actualCustomers,
        float $actualMrr,
        string $locale = 'am'
    ): string {
        $key = $isMet ? 'decision.gate_met_template' : 'decision.gate_not_met_template';

        $minMrrFormatted = number_format($minMrr, 2);
        $actualMrrFormatted = number_format($actualMrr, 2);

        $fallbackAm = $isMet
            ? "ድርጅቱ በስትራቴጂክ እቅዱ መሰረት {$minCustomers} የሚከፍሉ ደንበኞችን እና {$minMrrFormatted} ETB MRR ማሳካት በመቻሉ (በሪፖርቱ መሰረት {$actualCustomers} ደንበኞች እና {$actualMrrFormatted} ETB MRR)፤ ወደ ኃ/የተ/የግ/ማህበር (PLC) ሽግግር ለማድረግ የተቀመጠውን መስፈርት አሟልቷል።"
            : "ድርጅቱ በስትራቴጂክ እቅዱ መሰረት የተቀመጠውን {$minCustomers} የሚከፍሉ ደንበኞች እና {$minMrrFormatted} ETB MRR መስፈርት እስካሁን ሙሉ በሙሉ ያላሟላ በመሆኑ (በሪፖርቱ መሰረት {$actualCustomers} ደንበኞች እና {$actualMrrFormatted} ETB MRR)፤ የ PLC ሽግግር መስፈርቱ እስኪሟላ ድረስ በነባር ማዕቀፍ እየሰራ ይቆያል።";

        $fallbackEn = $isMet
            ? "As the company achieved {$minCustomers} paying customers and {$minMrrFormatted} ETB MRR according to its strategic plan (report shows {$actualCustomers} customers and {$actualMrrFormatted} ETB MRR); it has met the requirements for transition to a Private Limited Company (PLC)."
            : "As the company has not yet fully met the minimum benchmarks of {$minCustomers} paying customers and {$minMrrFormatted} ETB MRR (report shows {$actualCustomers} customers and {$actualMrrFormatted} ETB MRR); the PLC transition is deferred until benchmarks are satisfied.";

        $replacements = [
            'min_customers' => (string) $minCustomers,
            'min_mrr' => $minMrrFormatted,
            'actual_customers' => (string) $actualCustomers,
            'actual_mrr' => $actualMrrFormatted,
        ];

        if (function_exists('trans')) {
            $translated = trans($key, $replacements, $locale);
            if ($translated !== $key) {
                return $translated;
            }
        }

        return $locale === 'en' ? $fallbackEn : $fallbackAm;
    }

    /**
     * Get the default decision items list.
     *
     * @return array<int, string>
     */
    public static function getDefaultDecisions(string $locale = 'am'): array
    {
        if (function_exists('trans')) {
            $items = trans('decision.default_decisions', [], $locale);
            if (is_array($items) && ! empty($items)) {
                return $items;
            }
        }

        if ($locale === 'en') {
            return [
                'Complete Private Limited Company (PLC) registration and legal structure',
                'Execute service and core product expansion',
            ];
        }

        return [
            'ኃ/የተ/የግ/ማህበር (PLC) ምዝገባ እና የሕግ ማዕቀፍ ማጠናቀቅ',
            'የአገልግሎት እና የምርት መስፋፋት ማካሄድ',
        ];
    }
}
