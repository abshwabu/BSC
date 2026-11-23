<?php

namespace Database\Factories;

use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Member>
 */
class MemberFactory extends Factory
{
    protected $model = Member::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'role_title_am' => 'የሽያጭ እና የንግድ ልማት',
            'role_title_en' => 'Sales & Business Development',
            'position_key' => 'sales',
            'default_weights' => [
                'financial' => 35,
                'customers' => 30,
                'internal' => 20,
                'learning' => 15,
            ],
            'sort_order' => 1,
        ];
    }
}
