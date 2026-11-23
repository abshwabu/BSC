<?php

namespace App\Services\Bsc;

use App\Models\Setting;

class ScoreCalculator
{
    public const DEFAULT_CAP = 120.0;

    public const PERSPECTIVES = [
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
    ];

    public static function getDefaultCap(): float
    {
        try {
            if (class_exists(Setting::class)) {
                $settingCap = Setting::get('cap_percentage');
                if ($settingCap !== null && is_numeric($settingCap)) {
                    return (float) $settingCap;
                }
            }

            if (function_exists('app') && app()->has('config')) {
                return (float) config('bsc.cap_percentage', self::DEFAULT_CAP);
            }
        } catch (\Throwable) {
            // Container not initialized
        }

        return self::DEFAULT_CAP;
    }

    /**
     * Calculate achievement percentage for an individual activity.
     */
    public static function calculateAchievementPercentage(
        ?float $target,
        ?float $actual,
        string $direction = 'higher_better',
        ?float $cap = null
    ): float {
        $capValue = $cap ?? self::getDefaultCap();

        if ($actual === null || $target === null || $target <= 0.0) {
            return 0.0;
        }

        if ($direction === 'lower_better') {
            if ($actual <= 0.0) {
                return (float) $capValue;
            }
            $percentage = ($target / $actual) * 100.0;
        } else {
            $percentage = ($actual / $target) * 100.0;
        }

        return (float) min($capValue, max(0.0, $percentage));
    }

    /**
     * Calculate score out of 5.0 from percentage using piecewise linear interpolation.
     *
     * - p >= 90: 4.5 + (p - 90) * 0.05, capped at 5.0
     * - 75 <= p < 90: 4.0 + (p - 75) * (0.4 / 14)
     * - 60 <= p < 75: 3.0 + (p - 60) * (0.9 / 14)
     * - p < 60: (p / 60) * 3.0 - epsilon (strictly below 3.0)
     */
    public static function calculateScoreFromPercentage(float $percentage): float
    {
        $p = max(0.0, $percentage);

        if ($p >= 90.0) {
            return (float) min(5.0, 4.5 + ($p - 90.0) * 0.05);
        }

        if ($p >= 75.0) {
            return (float) (4.0 + ($p - 75.0) * (0.4 / 14.0));
        }

        if ($p >= 60.0) {
            return (float) (3.0 + ($p - 60.0) * (0.9 / 14.0));
        }

        $raw = ($p / 60.0) * 3.0;
        $epsilon = 0.00001;

        return (float) max(0.0, min(2.99999, $raw - $epsilon));
    }

    /**
     * Get bilingual rating label and band information for a given score (0.0 - 5.0).
     */
    public static function getRatingLabel(float $score): array
    {
        if ($score >= 4.5) {
            return [
                'key' => 'outstanding',
                'min_score' => 4.5,
                'max_score' => 5.0,
                'label_am' => 'እጅግ የላቀ',
                'label_en' => 'Outstanding',
            ];
        }

        if ($score >= 4.0) {
            return [
                'key' => 'exceeds_target',
                'min_score' => 4.0,
                'max_score' => 4.5,
                'label_am' => 'ከፍተኛ',
                'label_en' => 'Exceeds Target',
            ];
        }

        if ($score >= 3.0) {
            return [
                'key' => 'meets_target',
                'min_score' => 3.0,
                'max_score' => 4.0,
                'label_am' => 'የተሳካ',
                'label_en' => 'Meets Target',
            ];
        }

        return [
            'key' => 'needs_improvement',
            'min_score' => 0.0,
            'max_score' => 3.0,
            'label_am' => 'ትኩረት የሚሻ',
            'label_en' => 'Needs Improvement',
        ];
    }

    /**
     * Get translated rating string for score.
     */
    public static function getRatingText(float $score, string $locale = 'am'): string
    {
        $rating = self::getRatingLabel($score);

        return $locale === 'en' ? $rating['label_en'] : $rating['label_am'];
    }

    /**
     * Get localized perspective display name.
     */
    public static function getPerspectiveName(string $perspective, string $locale = 'am'): string
    {
        $persp = self::PERSPECTIVES[$perspective] ?? null;
        if (! $persp) {
            return $perspective;
        }

        return $locale === 'en' ? $persp['name_en'] : $persp['name_am'];
    }

    /**
     * Activity-level "BSC perspective & score" string.
     * e.g. "ፋይናንስ (5.0/5 - እጅግ የላቀ)"
     */
    public static function formatPerspectiveScoreString(
        string $perspective,
        float $score,
        string $locale = 'am'
    ): string {
        $name = self::getPerspectiveName($perspective, $locale);
        $rating = self::getRatingText($score, $locale);
        $formattedScore = number_format(round($score, 1), 1);

        return sprintf('%s (%s/5 - %s)', $name, $formattedScore, $rating);
    }

    /**
     * Calculate summary for a member from activities and weights.
     * Redistributes weights proportionally if any perspective has 0 activities.
     */
    public static function calculateMemberSummary(
        array $activities,
        array $weights,
        ?float $cap = null
    ): array {
        $perspectiveKeys = array_keys(self::PERSPECTIVES);

        // Group activities by perspective and normalize
        $grouped = [];
        foreach ($perspectiveKeys as $key) {
            $grouped[$key] = [];
        }

        foreach ($activities as $activity) {
            $p = is_array($activity) ? ($activity['perspective'] ?? null) : ($activity->perspective ?? null);
            if ($p && isset($grouped[$p])) {
                $target = is_array($activity) ? ($activity['target_value'] ?? null) : ($activity->target_value ?? null);
                $actual = is_array($activity) ? ($activity['actual_value'] ?? null) : ($activity->actual_value ?? null);
                $direction = is_array($activity) ? ($activity['direction'] ?? 'higher_better') : ($activity->direction ?? 'higher_better');

                $pct = self::calculateAchievementPercentage(
                    $target !== null ? (float) $target : null,
                    $actual !== null ? (float) $actual : null,
                    (string) $direction,
                    $cap
                );

                $score = self::calculateScoreFromPercentage($pct);

                $grouped[$p][] = [
                    'target_value' => $target,
                    'actual_value' => $actual,
                    'direction' => $direction,
                    'percentage' => $pct,
                    'score' => $score,
                ];
            }
        }

        // Identify active perspectives
        $activePerspectives = [];
        $inactivePerspectives = [];

        foreach ($perspectiveKeys as $key) {
            if (count($grouped[$key]) > 0) {
                $activePerspectives[$key] = (float) ($weights[$key] ?? 0.0);
            } else {
                $inactivePerspectives[$key] = (float) ($weights[$key] ?? 0.0);
            }
        }

        $activeCount = count($activePerspectives);
        $totalCount = count($perspectiveKeys);
        $needsRedistribution = $activeCount > 0 && $activeCount < $totalCount;

        $sumActiveOriginalWeights = array_sum($activePerspectives);

        // Calculate perspective summaries
        $perspectiveSummaries = [];
        $weightedPercentage = 0.0;
        $weightedScore = 0.0;

        foreach ($perspectiveKeys as $key) {
            $originalWeight = (float) ($weights[$key] ?? 0.0);
            $items = $grouped[$key];
            $itemCount = count($items);

            if ($itemCount > 0) {
                $avgPercentage = array_sum(array_column($items, 'percentage')) / $itemCount;
                $score = self::calculateScoreFromPercentage($avgPercentage);
                $rating = self::getRatingLabel($score);

                $effectiveWeight = $originalWeight;
                if ($needsRedistribution && $sumActiveOriginalWeights > 0) {
                    $effectiveWeight = ($originalWeight / $sumActiveOriginalWeights) * 100.0;
                }

                $weightedPercentage += ($effectiveWeight * $avgPercentage) / 100.0;
                $weightedScore += ($effectiveWeight * $score) / 100.0;

                $perspectiveSummaries[$key] = [
                    'perspective' => $key,
                    'name_am' => self::PERSPECTIVES[$key]['name_am'],
                    'name_en' => self::PERSPECTIVES[$key]['name_en'],
                    'original_weight' => $originalWeight,
                    'effective_weight' => $effectiveWeight,
                    'activity_count' => $itemCount,
                    'average_percentage' => $avgPercentage,
                    'average_percentage_rounded' => round($avgPercentage, 1),
                    'score' => $score,
                    'score_summary_rounded' => round($score, 2),
                    'score_activity_rounded' => round($score, 1),
                    'rating' => $rating,
                    'redistributed' => $needsRedistribution,
                    'excluded' => false,
                ];
            } else {
                $perspectiveSummaries[$key] = [
                    'perspective' => $key,
                    'name_am' => self::PERSPECTIVES[$key]['name_am'],
                    'name_en' => self::PERSPECTIVES[$key]['name_en'],
                    'original_weight' => $originalWeight,
                    'effective_weight' => 0.0,
                    'activity_count' => 0,
                    'average_percentage' => null,
                    'average_percentage_rounded' => null,
                    'score' => null,
                    'score_summary_rounded' => null,
                    'score_activity_rounded' => null,
                    'rating' => null,
                    'redistributed' => $needsRedistribution,
                    'excluded' => true,
                ];
            }
        }

        $overallRating = self::getRatingLabel($weightedScore);

        return [
            'perspectives' => $perspectiveSummaries,
            'weighted_percentage' => $weightedPercentage,
            'weighted_percentage_rounded' => round($weightedPercentage, 1),
            'weighted_score' => $weightedScore,
            'weighted_score_rounded' => round($weightedScore, 2),
            'rating' => $overallRating,
            'has_redistributed_weights' => $needsRedistribution,
            'active_perspective_count' => $activeCount,
        ];
    }

    /**
     * Calculate summary for a member using pre-calculated perspective averages.
     * Useful for direct calculation or tests.
     */
    public static function calculateFromPerspectiveAverages(
        array $perspectiveAverages,
        array $weights
    ): array {
        $perspectiveKeys = array_keys(self::PERSPECTIVES);

        $activePerspectives = [];
        foreach ($perspectiveKeys as $key) {
            if (isset($perspectiveAverages[$key]) && $perspectiveAverages[$key] !== null) {
                $activePerspectives[$key] = (float) ($weights[$key] ?? 0.0);
            }
        }

        $activeCount = count($activePerspectives);
        $totalCount = count($perspectiveKeys);
        $needsRedistribution = $activeCount > 0 && $activeCount < $totalCount;
        $sumActiveWeights = array_sum($activePerspectives);

        $perspectiveSummaries = [];
        $weightedPercentage = 0.0;
        $weightedScore = 0.0;

        foreach ($perspectiveKeys as $key) {
            $originalWeight = (float) ($weights[$key] ?? 0.0);
            $avgPct = $perspectiveAverages[$key] ?? null;

            if ($avgPct !== null) {
                $avgPercentage = (float) $avgPct;
                $score = self::calculateScoreFromPercentage($avgPercentage);
                $rating = self::getRatingLabel($score);

                $effectiveWeight = $originalWeight;
                if ($needsRedistribution && $sumActiveWeights > 0) {
                    $effectiveWeight = ($originalWeight / $sumActiveWeights) * 100.0;
                }

                $weightedPercentage += ($effectiveWeight * $avgPercentage) / 100.0;
                $weightedScore += ($effectiveWeight * $score) / 100.0;

                $perspectiveSummaries[$key] = [
                    'perspective' => $key,
                    'name_am' => self::PERSPECTIVES[$key]['name_am'],
                    'name_en' => self::PERSPECTIVES[$key]['name_en'],
                    'original_weight' => $originalWeight,
                    'effective_weight' => $effectiveWeight,
                    'average_percentage' => $avgPercentage,
                    'average_percentage_rounded' => round($avgPercentage, 1),
                    'score' => $score,
                    'score_summary_rounded' => round($score, 2),
                    'score_activity_rounded' => round($score, 1),
                    'rating' => $rating,
                    'redistributed' => $needsRedistribution,
                    'excluded' => false,
                ];
            } else {
                $perspectiveSummaries[$key] = [
                    'perspective' => $key,
                    'name_am' => self::PERSPECTIVES[$key]['name_am'],
                    'name_en' => self::PERSPECTIVES[$key]['name_en'],
                    'original_weight' => $originalWeight,
                    'effective_weight' => 0.0,
                    'average_percentage' => null,
                    'average_percentage_rounded' => null,
                    'score' => null,
                    'score_summary_rounded' => null,
                    'score_activity_rounded' => null,
                    'rating' => null,
                    'redistributed' => $needsRedistribution,
                    'excluded' => true,
                ];
            }
        }

        return [
            'perspectives' => $perspectiveSummaries,
            'weighted_percentage' => $weightedPercentage,
            'weighted_percentage_rounded' => round($weightedPercentage, 1),
            'weighted_score' => $weightedScore,
            'weighted_score_rounded' => round($weightedScore, 2),
            'rating' => self::getRatingLabel($weightedScore),
            'has_redistributed_weights' => $needsRedistribution,
        ];
    }

    /**
     * Calculate consolidated company average from an array of member summaries.
     */
    public static function calculateCompanySummary(array $memberSummaries): array
    {
        $count = count($memberSummaries);
        if ($count === 0) {
            return [
                'member_count' => 0,
                'company_weighted_percentage' => 0.0,
                'company_weighted_percentage_rounded' => 0.0,
                'company_weighted_score' => 0.0,
                'company_weighted_score_rounded' => 0.0,
                'rating' => self::getRatingLabel(0.0),
            ];
        }

        $sumPercentage = 0.0;
        $sumScore = 0.0;

        foreach ($memberSummaries as $summary) {
            $sumPercentage += (float) ($summary['weighted_percentage'] ?? 0.0);
            $sumScore += (float) ($summary['weighted_score'] ?? 0.0);
        }

        $avgPercentage = $sumPercentage / $count;
        $avgScore = $sumScore / $count;

        return [
            'member_count' => $count,
            'company_weighted_percentage' => $avgPercentage,
            'company_weighted_percentage_rounded' => round($avgPercentage, 1),
            'company_weighted_score' => $avgScore,
            'company_weighted_score_rounded' => round($avgScore, 2),
            'rating' => self::getRatingLabel($avgScore),
        ];
    }
}
