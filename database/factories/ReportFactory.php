<?php

namespace Database\Factories;

use App\Models\Report;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    protected $model = Report::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => 'BSC Q1 2026 Report',
            'period_label' => 'Q1',
            'period_type' => 'quarter',
            'year' => 2026,
            'report_date' => '2026-03-31',
            'status' => 'draft',
            'company_name' => 'EthioTech Solutions',
            'notes' => fake()->sentence(),
        ];
    }

    public function final(): static
    {
        return $this->state(fn () => [
            'status' => 'final',
        ]);
    }
}
