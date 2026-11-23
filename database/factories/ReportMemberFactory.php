<?php

namespace Database\Factories;

use App\Models\Member;
use App\Models\Report;
use App\Models\ReportMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ReportMember>
 */
class ReportMemberFactory extends Factory
{
    protected $model = ReportMember::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'report_id' => Report::factory(),
            'member_id' => Member::factory(),
            'weights' => [
                'financial' => 35,
                'customers' => 30,
                'internal' => 20,
                'learning' => 15,
            ],
            'achievements' => fake()->paragraph(),
            'challenges' => fake()->paragraph(),
            'next_steps' => fake()->paragraph(),
            'signed' => false,
        ];
    }

    public function signed(): static
    {
        return $this->state(fn () => [
            'signed' => true,
        ]);
    }
}
