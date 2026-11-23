<?php

return [
    /*
    |--------------------------------------------------------------------------
    | BSC Percentage Cap
    |--------------------------------------------------------------------------
    |
    | Maximum percentage an individual activity or perspective can achieve.
    | Default is 120.0%.
    |
    */
    'cap_percentage' => 120.0,

    /*
    |--------------------------------------------------------------------------
    | Score Breakpoints (Out of 5.0)
    |--------------------------------------------------------------------------
    |
    | Piecewise linear formula configuration:
    | - p >= 90: 4.5 + (p - 90) * 0.05, capped at 5.0
    | - 75 <= p < 90: 4.0 + (p - 75) * (0.4 / 14)
    | - 60 <= p < 75: 3.0 + (p - 60) * (0.9 / 14)
    | - p < 60: (p / 60) * 3.0 - epsilon (strictly below 3.0)
    |
    */
    'breakpoints' => [
        'high' => [
            'min_percentage' => 90.0,
            'base_score' => 4.5,
            'multiplier' => 0.05,
            'max_score' => 5.0,
        ],
        'mid_high' => [
            'min_percentage' => 75.0,
            'max_percentage' => 90.0,
            'base_score' => 4.0,
            'multiplier' => 0.4 / 14.0,
        ],
        'mid_low' => [
            'min_percentage' => 60.0,
            'max_percentage' => 75.0,
            'base_score' => 3.0,
            'multiplier' => 0.9 / 14.0,
        ],
        'low' => [
            'max_percentage' => 60.0,
            'base_score' => 0.0,
            'multiplier' => 3.0 / 60.0,
            'epsilon' => 0.00001,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rating Bands
    |--------------------------------------------------------------------------
    |
    | Bilingual rating descriptions based on final calculated score (0.0 - 5.0).
    |
    */
    'ratings' => [
        'outstanding' => [
            'key' => 'outstanding',
            'min_score' => 4.5,
            'max_score' => 5.0,
            'label_am' => 'እጅግ የላቀ',
            'label_en' => 'Outstanding',
        ],
        'exceeds_target' => [
            'key' => 'exceeds_target',
            'min_score' => 4.0,
            'max_score' => 4.5,
            'label_am' => 'ከፍተኛ',
            'label_en' => 'Exceeds Target',
        ],
        'meets_target' => [
            'key' => 'meets_target',
            'min_score' => 3.0,
            'max_score' => 4.0,
            'label_am' => 'የተሳካ',
            'label_en' => 'Meets Target',
        ],
        'needs_improvement' => [
            'key' => 'needs_improvement',
            'min_score' => 0.0,
            'max_score' => 3.0,
            'label_am' => 'ትኩረት የሚሻ',
            'label_en' => 'Needs Improvement',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Perspectives
    |--------------------------------------------------------------------------
    |
    | 4 standard balanced scorecard perspectives.
    |
    */
    'perspectives' => [
        'financial' => [
            'key' => 'financial',
            'name_am' => 'ፋይናንስ',
            'name_en' => 'Financial',
        ],
        'customers' => [
            'key' => 'customers',
            'name_am' => 'ደንበኞች',
            'name_en' => 'Customers',
        ],
        'internal' => [
            'key' => 'internal',
            'name_am' => 'የውስጥ አሰራር',
            'name_en' => 'Internal Business Processes',
        ],
        'learning' => [
            'key' => 'learning',
            'name_am' => 'መማር እና እድገት',
            'name_en' => 'Learning & Growth',
        ],
    ],
];
