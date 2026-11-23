<?php

namespace Database\Factories;

use App\Models\Activity;
use App\Models\ReportMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Activity>
 */
class ActivityFactory extends Factory
{
    protected $model = Activity::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'report_member_id' => ReportMember::factory(),
            'sort_order' => 1,
            'activity_text' => 'የደንበኞች ማሳያ ማካሄድ',
            'kpi_text' => 'የተካሄዱ ማሳያዎች ብዛት',
            'target_value' => 60.00,
            'target_label' => '60 ማሳያዎች',
            'actual_value' => 54.00,
            'actual_label' => '54 ማሳያዎች',
            'direction' => 'higher_better',
            'perspective' => 'customers',
        ];
    }
}
