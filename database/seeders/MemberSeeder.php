<?php

namespace Database\Seeders;

use App\Models\Member;
use Illuminate\Database\Seeder;

class MemberSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $members = [
            [
                'name' => 'Co-Founder (Sales & BD)',
                'role_title_en' => 'Sales & Business Development',
                'role_title_am' => 'የሽያጭ እና የንግድ ልማት',
                'position_key' => 'sales',
                'default_weights' => [
                    'financial' => 35,
                    'customers' => 30,
                    'internal' => 20,
                    'learning' => 15,
                ],
                'sort_order' => 1,
            ],
            [
                'name' => 'Co-Founder (Marketing & Brand)',
                'role_title_en' => 'Marketing & Brand Management',
                'role_title_am' => 'የግብይት እና የብራንድ አስተዳደር',
                'position_key' => 'marketing',
                'default_weights' => [
                    'financial' => 25,
                    'customers' => 40,
                    'internal' => 20,
                    'learning' => 15,
                ],
                'sort_order' => 2,
            ],
            [
                'name' => 'Co-Founder (Tech & Product)',
                'role_title_en' => 'Technology & Product Management',
                'role_title_am' => 'የቴክኖሎጂ እና የምርት አስተዳደር',
                'position_key' => 'tech',
                'default_weights' => [
                    'financial' => 20,
                    'customers' => 30,
                    'internal' => 35,
                    'learning' => 15,
                ],
                'sort_order' => 3,
            ],
        ];

        foreach ($members as $memberData) {
            Member::updateOrCreate(
                ['position_key' => $memberData['position_key']],
                $memberData
            );
        }
    }
}
