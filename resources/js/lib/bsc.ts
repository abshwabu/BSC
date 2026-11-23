export type Direction = 'higher_better' | 'lower_better';

export type PerspectiveKey = 'financial' | 'customers' | 'internal' | 'learning';

export interface RatingInfo {
    key: 'outstanding' | 'exceeds_target' | 'meets_target' | 'needs_improvement';
    min_score: number;
    max_score: number;
    label_am: string;
    label_en: string;
}

export interface PerspectiveMeta {
    key: PerspectiveKey;
    name_am: string;
    name_en: string;
}

export const DEFAULT_CAP = 120.0;

export const PERSPECTIVES: Record<PerspectiveKey, PerspectiveMeta> = {
    financial: {
        key: 'financial',
        name_am: 'ፋይናንስ',
        name_en: 'Financial',
    },
    customers: {
        key: 'customers',
        name_am: 'ደንበኞች',
        name_en: 'Customers',
    },
    internal: {
        key: 'internal',
        name_am: 'የውስጥ አሰራር',
        name_en: 'Internal Business Processes',
    },
    learning: {
        key: 'learning',
        name_am: 'መማር እና እድገት',
        name_en: 'Learning & Growth',
    },
};

export interface ActivityInput {
    perspective?: string | null;
    target_value?: number | string | null;
    actual_value?: number | string | null;
    direction?: Direction | string | null;
}

export interface PerspectiveSummary {
    perspective: PerspectiveKey;
    name_am: string;
    name_en: string;
    original_weight: number;
    effective_weight: number;
    activity_count?: number;
    average_percentage: number | null;
    average_percentage_rounded: number | null;
    score: number | null;
    score_summary_rounded: number | null;
    score_activity_rounded: number | null;
    rating: RatingInfo | null;
    redistributed: boolean;
    excluded: boolean;
}

export interface MemberSummary {
    perspectives: Record<PerspectiveKey, PerspectiveSummary>;
    weighted_percentage: number;
    weighted_percentage_rounded: number;
    weighted_score: number;
    weighted_score_rounded: number;
    rating: RatingInfo;
    has_redistributed_weights: boolean;
    active_perspective_count?: number;
}

export interface CompanySummary {
    member_count: number;
    company_weighted_percentage: number;
    company_weighted_percentage_rounded: number;
    company_weighted_score: number;
    company_weighted_score_rounded: number;
    rating: RatingInfo;
}

/**
 * Calculate achievement percentage for an individual activity.
 */
export function calculateAchievementPercentage(
    target: number | string | null | undefined,
    actual: number | string | null | undefined,
    direction: Direction | string = 'higher_better',
    cap: number = DEFAULT_CAP
): number {
    if (actual === null || actual === undefined || target === null || target === undefined) {
        return 0.0;
    }

    const t = typeof target === 'string' ? parseFloat(target) : target;
    const a = typeof actual === 'string' ? parseFloat(actual) : actual;

    if (isNaN(t) || isNaN(a) || t <= 0.0) {
        return 0.0;
    }

    if (direction === 'lower_better') {
        if (a <= 0.0) {
            return cap;
        }
        const pct = (t / a) * 100.0;
        return Math.min(cap, Math.max(0.0, pct));
    }

    const pct = (a / t) * 100.0;
    return Math.min(cap, Math.max(0.0, pct));
}

/**
 * Calculate score out of 5.0 from percentage using piecewise linear interpolation.
 *
 * - p >= 90: 4.5 + (p - 90) * 0.05, capped at 5.0
 * - 75 <= p < 90: 4.0 + (p - 75) * (0.4 / 14)
 * - 60 <= p < 75: 3.0 + (p - 60) * (0.9 / 14)
 * - p < 60: (p / 60) * 3.0 - epsilon (strictly below 3.0)
 */
export function calculateScoreFromPercentage(percentage: number): number {
    const p = Math.max(0.0, percentage);

    if (p >= 90.0) {
        return Math.min(5.0, 4.5 + (p - 90.0) * 0.05);
    }

    if (p >= 75.0) {
        return 4.0 + (p - 75.0) * (0.4 / 14.0);
    }

    if (p >= 60.0) {
        return 3.0 + (p - 60.0) * (0.9 / 14.0);
    }

    const raw = (p / 60.0) * 3.0;
    const epsilon = 0.00001;

    return Math.max(0.0, Math.min(2.99999, raw - epsilon));
}

/**
 * Get rating label info for score.
 */
export function getRatingLabel(score: number): RatingInfo {
    if (score >= 4.5) {
        return {
            key: 'outstanding',
            min_score: 4.5,
            max_score: 5.0,
            label_am: 'እጅግ የላቀ',
            label_en: 'Outstanding',
        };
    }

    if (score >= 4.0) {
        return {
            key: 'exceeds_target',
            min_score: 4.0,
            max_score: 4.5,
            label_am: 'ከፍተኛ',
            label_en: 'Exceeds Target',
        };
    }

    if (score >= 3.0) {
        return {
            key: 'meets_target',
            min_score: 3.0,
            max_score: 4.0,
            label_am: 'የተሳካ',
            label_en: 'Meets Target',
        };
    }

    return {
        key: 'needs_improvement',
        min_score: 0.0,
        max_score: 3.0,
        label_am: 'ትኩረት የሚሻ',
        label_en: 'Needs Improvement',
    };
}

/**
 * Get translated rating string for score.
 */
export function getRatingText(score: number, locale: 'am' | 'en' = 'am'): string {
    const rating = getRatingLabel(score);
    return locale === 'en' ? rating.label_en : rating.label_am;
}

/**
 * Localized perspective name.
 */
export function getPerspectiveName(perspective: string, locale: 'am' | 'en' = 'am'): string {
    const meta = PERSPECTIVES[perspective as PerspectiveKey];
    if (!meta) return perspective;
    return locale === 'en' ? meta.name_en : meta.name_am;
}

/**
 * Round numbers with floating precision safety.
 */
export function roundTo(num: number, decimals: number): number {
    const factor = Math.pow(10, decimals);
    return Math.round((num + Number.EPSILON) * factor) / factor;
}

/**
 * Activity-level "BSC perspective & score" string.
 * e.g. "ፋይናንስ (5.0/5 - እጅግ የላቀ)"
 */
export function formatPerspectiveScoreString(
    perspective: string,
    score: number,
    locale: 'am' | 'en' = 'am'
): string {
    const name = getPerspectiveName(perspective, locale);
    const rating = getRatingText(score, locale);
    const formattedScore = roundTo(score, 1).toFixed(1);

    return `${name} (${formattedScore}/5 - ${rating})`;
}

/**
 * Calculate member summary from activities and weights.
 */
export function calculateMemberSummary(
    activities: ActivityInput[],
    weights: Record<string, number>,
    cap: number = DEFAULT_CAP
): MemberSummary {
    const keys = Object.keys(PERSPECTIVES) as PerspectiveKey[];

    const grouped: Record<PerspectiveKey, { target: number; actual: number; pct: number; score: number }[]> = {
        financial: [],
        customers: [],
        internal: [],
        learning: [],
    };

    for (const act of activities) {
        const p = act.perspective as PerspectiveKey;
        if (p && grouped[p]) {
            const pct = calculateAchievementPercentage(act.target_value, act.actual_value, act.direction ?? 'higher_better', cap);
            const score = calculateScoreFromPercentage(pct);
            const t = typeof act.target_value === 'string' ? parseFloat(act.target_value) : (act.target_value ?? 0);
            const a = typeof act.actual_value === 'string' ? parseFloat(act.actual_value) : (act.actual_value ?? 0);

            grouped[p].push({
                target: t,
                actual: a,
                pct,
                score,
            });
        }
    }

    const activeKeys: PerspectiveKey[] = [];
    let sumActiveOriginalWeights = 0;

    for (const key of keys) {
        if (grouped[key].length > 0) {
            activeKeys.push(key);
            sumActiveOriginalWeights += weights[key] ?? 0;
        }
    }

    const activeCount = activeKeys.length;
    const totalCount = keys.length;
    const needsRedistribution = activeCount > 0 && activeCount < totalCount;

    const perspectiveSummaries: Record<PerspectiveKey, PerspectiveSummary> = {} as any;
    let weightedPercentage = 0.0;
    let weightedScore = 0.0;

    for (const key of keys) {
        const originalWeight = weights[key] ?? 0.0;
        const items = grouped[key];
        const count = items.length;

        if (count > 0) {
            const sumPct = items.reduce((acc, cur) => acc + cur.pct, 0);
            const avgPct = sumPct / count;
            const score = calculateScoreFromPercentage(avgPct);
            const rating = getRatingLabel(score);

            let effectiveWeight = originalWeight;
            if (needsRedistribution && sumActiveOriginalWeights > 0) {
                effectiveWeight = (originalWeight / sumActiveOriginalWeights) * 100.0;
            }

            weightedPercentage += (effectiveWeight * avgPct) / 100.0;
            weightedScore += (effectiveWeight * score) / 100.0;

            perspectiveSummaries[key] = {
                perspective: key,
                name_am: PERSPECTIVES[key].name_am,
                name_en: PERSPECTIVES[key].name_en,
                original_weight: originalWeight,
                effective_weight: effectiveWeight,
                activity_count: count,
                average_percentage: avgPct,
                average_percentage_rounded: roundTo(avgPct, 1),
                score: score,
                score_summary_rounded: roundTo(score, 2),
                score_activity_rounded: roundTo(score, 1),
                rating: rating,
                redistributed: needsRedistribution,
                excluded: false,
            };
        } else {
            perspectiveSummaries[key] = {
                perspective: key,
                name_am: PERSPECTIVES[key].name_am,
                name_en: PERSPECTIVES[key].name_en,
                original_weight: originalWeight,
                effective_weight: 0.0,
                activity_count: 0,
                average_percentage: null,
                average_percentage_rounded: null,
                score: null,
                score_summary_rounded: null,
                score_activity_rounded: null,
                rating: null,
                redistributed: needsRedistribution,
                excluded: true,
            };
        }
    }

    return {
        perspectives: perspectiveSummaries,
        weighted_percentage: weightedPercentage,
        weighted_percentage_rounded: roundTo(weightedPercentage, 1),
        weighted_score: weightedScore,
        weighted_score_rounded: roundTo(weightedScore, 2),
        rating: getRatingLabel(weightedScore),
        has_redistributed_weights: needsRedistribution,
        active_perspective_count: activeCount,
    };
}

/**
 * Calculate member summary from pre-computed perspective averages.
 */
export function calculateFromPerspectiveAverages(
    perspectiveAverages: Record<string, number | null | undefined>,
    weights: Record<string, number>
): MemberSummary {
    const keys = Object.keys(PERSPECTIVES) as PerspectiveKey[];
    const activeKeys: PerspectiveKey[] = [];
    let sumActiveWeights = 0;

    for (const key of keys) {
        if (perspectiveAverages[key] !== null && perspectiveAverages[key] !== undefined) {
            activeKeys.push(key);
            sumActiveWeights += weights[key] ?? 0;
        }
    }

    const activeCount = activeKeys.length;
    const totalCount = keys.length;
    const needsRedistribution = activeCount > 0 && activeCount < totalCount;

    const perspectiveSummaries: Record<PerspectiveKey, PerspectiveSummary> = {} as any;
    let weightedPercentage = 0.0;
    let weightedScore = 0.0;

    for (const key of keys) {
        const originalWeight = weights[key] ?? 0.0;
        const avg = perspectiveAverages[key];

        if (avg !== null && avg !== undefined) {
            const avgPct = Number(avg);
            const score = calculateScoreFromPercentage(avgPct);
            const rating = getRatingLabel(score);

            let effectiveWeight = originalWeight;
            if (needsRedistribution && sumActiveWeights > 0) {
                effectiveWeight = (originalWeight / sumActiveWeights) * 100.0;
            }

            weightedPercentage += (effectiveWeight * avgPct) / 100.0;
            weightedScore += (effectiveWeight * score) / 100.0;

            perspectiveSummaries[key] = {
                perspective: key,
                name_am: PERSPECTIVES[key].name_am,
                name_en: PERSPECTIVES[key].name_en,
                original_weight: originalWeight,
                effective_weight: effectiveWeight,
                average_percentage: avgPct,
                average_percentage_rounded: roundTo(avgPct, 1),
                score: score,
                score_summary_rounded: roundTo(score, 2),
                score_activity_rounded: roundTo(score, 1),
                rating: rating,
                redistributed: needsRedistribution,
                excluded: false,
            };
        } else {
            perspectiveSummaries[key] = {
                perspective: key,
                name_am: PERSPECTIVES[key].name_am,
                name_en: PERSPECTIVES[key].name_en,
                original_weight: originalWeight,
                effective_weight: 0.0,
                average_percentage: null,
                average_percentage_rounded: null,
                score: null,
                score_summary_rounded: null,
                score_activity_rounded: null,
                rating: null,
                redistributed: needsRedistribution,
                excluded: true,
            };
        }
    }

    return {
        perspectives: perspectiveSummaries,
        weighted_percentage: weightedPercentage,
        weighted_percentage_rounded: roundTo(weightedPercentage, 1),
        weighted_score: weightedScore,
        weighted_score_rounded: roundTo(weightedScore, 2),
        rating: getRatingLabel(weightedScore),
        has_redistributed_weights: needsRedistribution,
    };
}

/**
 * Calculate company summary from member summaries.
 */
export function calculateCompanySummary(memberSummaries: MemberSummary[]): CompanySummary {
    const count = memberSummaries.length;
    if (count === 0) {
        return {
            member_count: 0,
            company_weighted_percentage: 0.0,
            company_weighted_percentage_rounded: 0.0,
            company_weighted_score: 0.0,
            company_weighted_score_rounded: 0.0,
            rating: getRatingLabel(0.0),
        };
    }

    let sumPct = 0.0;
    let sumScore = 0.0;

    for (const m of memberSummaries) {
        sumPct += m.weighted_percentage;
        sumScore += m.weighted_score;
    }

    const avgPct = sumPct / count;
    const avgScore = sumScore / count;

    return {
        member_count: count,
        company_weighted_percentage: avgPct,
        company_weighted_percentage_rounded: roundTo(avgPct, 1),
        company_weighted_score: avgScore,
        company_weighted_score_rounded: roundTo(avgScore, 2),
        rating: getRatingLabel(avgScore),
    };
}

export interface GateEvaluation {
    enabled: boolean;
    is_met: boolean;
    customers_met: boolean;
    mrr_met: boolean;
    status: 'met' | 'not_met' | 'disabled';
    suggested_decision_am: string;
    suggested_decision_en: string;
}

/**
 * Evaluate the PLC transition readiness gate.
 */
export function evaluateGate(
    plcGateEnabled: boolean,
    minCustomers: number | null | undefined,
    minMrr: number | null | undefined,
    actualCustomers: number | null | undefined,
    actualMrr: number | null | undefined
): GateEvaluation {
    if (!plcGateEnabled) {
        return {
            enabled: false,
            is_met: false,
            customers_met: false,
            mrr_met: false,
            status: 'disabled',
            suggested_decision_am: '',
            suggested_decision_en: '',
        };
    }

    const minCust = minCustomers != null && !isNaN(Number(minCustomers)) ? Number(minCustomers) : 0;
    const minRev = minMrr != null && !isNaN(Number(minMrr)) ? Number(minMrr) : 0.0;
    const actCust = actualCustomers != null && !isNaN(Number(actualCustomers)) ? Number(actualCustomers) : 0;
    const actRev = actualMrr != null && !isNaN(Number(actualMrr)) ? Number(actualMrr) : 0.0;

    const customersMet = actCust >= minCust;
    const mrrMet = actRev >= minRev;
    const isMet = customersMet && mrrMet;

    return {
        enabled: true,
        is_met: isMet,
        customers_met: customersMet,
        mrr_met: mrrMet,
        status: isMet ? 'met' : 'not_met',
        suggested_decision_am: generateDecisionTemplate(isMet, minCust, minRev, actCust, actRev, 'am'),
        suggested_decision_en: generateDecisionTemplate(isMet, minCust, minRev, actCust, actRev, 'en'),
    };
}

/**
 * Generate decision statement text for PLC transition.
 */
export function generateDecisionTemplate(
    isMet: boolean,
    minCustomers: number,
    minMrr: number,
    actualCustomers: number,
    actualMrr: number,
    locale: 'am' | 'en' = 'am'
): string {
    const minMrrFormatted = Number(minMrr).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const actualMrrFormatted = Number(actualMrr).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    if (locale === 'en') {
        return isMet
            ? `As the company achieved ${minCustomers} paying customers and ${minMrrFormatted} ETB MRR according to its strategic plan (report shows ${actualCustomers} customers and ${actualMrrFormatted} ETB MRR); it has met the requirements for transition to a Private Limited Company (PLC).`
            : `As the company has not yet fully met the minimum benchmarks of ${minCustomers} paying customers and ${minMrrFormatted} ETB MRR (report shows ${actualCustomers} customers and ${actualMrrFormatted} ETB MRR); the PLC transition is deferred until benchmarks are satisfied.`;
    }

    return isMet
        ? `ድርጅቱ በስትራቴጂክ እቅዱ መሰረት ${minCustomers} የሚከፍሉ ደንበኞችን እና ${minMrrFormatted} ETB MRR ማሳካት በመቻሉ (በሪፖርቱ መሰረት ${actualCustomers} ደንበኞች እና ${actualMrrFormatted} ETB MRR)፤ ወደ ኃ/የተ/የግ/ማህበር (PLC) ሽግግር ለማድረግ የተቀመጠውን መስፈርት አሟልቷል።`
        : `ድርጅቱ በስትራቴጂክ እቅዱ መሰረት የተቀመጠውን ${minCustomers} የሚከፍሉ ደንበኞች እና ${minMrrFormatted} ETB MRR መስፈርት እስካሁን ሙሉ በሙሉ ያላሟላ በመሆኑ (በሪፖርቱ መሰረት ${actualCustomers} ደንበኞች እና ${actualMrrFormatted} ETB MRR)፤ የ PLC ሽግግር መስፈርቱ እስኪሟላ ድረስ በነባር ማዕቀፍ እየሰራ ይቆያል።`;
}

export const DEFAULT_EXECUTIVE_DECISIONS: Record<'am' | 'en', string[]> = {
    am: [
        'ኃ/የተ/የግ/ማህበር (PLC) ምዝገባ እና የሕግ ማዕቀፍ ማጠናቀቅ',
        'የአገልግሎት እና የምርት መስፋፋት ማካሄድ',
    ],
    en: [
        'Complete Private Limited Company (PLC) registration and legal structure',
        'Execute service and core product expansion',
    ],
};
