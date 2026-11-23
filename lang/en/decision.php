<?php

return [
    'gate_met_template' => 'As the company achieved :min_customers paying customers and :min_mrr ETB MRR according to its strategic plan (report shows :actual_customers customers and :actual_mrr ETB MRR); it has met the requirements for transition to a Private Limited Company (PLC).',
    'gate_not_met_template' => 'As the company has not yet fully met the minimum benchmarks of :min_customers paying customers and :min_mrr ETB MRR (report shows :actual_customers customers and :actual_mrr ETB MRR); the PLC transition is deferred until benchmarks are satisfied.',
    'default_decisions' => [
        'Complete Private Limited Company (PLC) registration and legal structure',
        'Execute service and core product expansion',
    ],
];
