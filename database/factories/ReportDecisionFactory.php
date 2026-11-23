<?php

namespace Database\Factories;

use App\Models\Report;
use App\Models\ReportDecision;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ReportDecision>
 */
class ReportDecisionFactory extends Factory
{
    protected $model = ReportDecision::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'report_id' => Report::factory(),
            'plc_gate_enabled' => true,
            'min_paying_customers' => 20,
            'min_mrr' => 150000.00,
            'decision_text_am' => 'ወደ ኃላፊነቱ የተወሰነ የግል ማህበር (PLC) ለመሸጋገር የተጣሉ ቅድመ ሁኔታዎች ተሟልተዋል።',
            'expansion_text_am' => 'በሚቀጥለው ሩብ ዓመት ተጨማሪ የሽያጭ ባለሙያ መቅጠር።',
        ];
    }
}
