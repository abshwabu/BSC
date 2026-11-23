import { describe, it, expect } from 'vitest';
import {
    calculateAchievementPercentage,
    calculateScoreFromPercentage,
    getRatingLabel,
    formatPerspectiveScoreString,
    calculateMemberSummary,
    calculateFromPerspectiveAverages,
    calculateCompanySummary,
} from '../../resources/js/lib/bsc';

describe('Bsc Scoring Engine (TypeScript)', () => {
    it('calculates achievement percentage for higher_better', () => {
        expect(calculateAchievementPercentage(100, 50, 'higher_better')).toBe(50.0);
        expect(calculateAchievementPercentage(100, 100, 'higher_better')).toBe(100.0);
        expect(calculateAchievementPercentage(50, 45, 'higher_better')).toBe(90.0);

        // Cap at 120
        expect(calculateAchievementPercentage(100, 150, 'higher_better')).toBe(120.0);
        // Custom cap
        expect(calculateAchievementPercentage(100, 150, 'higher_better', 110.0)).toBe(110.0);

        // Null and edge cases
        expect(calculateAchievementPercentage(null, 50, 'higher_better')).toBe(0.0);
        expect(calculateAchievementPercentage(100, null, 'higher_better')).toBe(0.0);
        expect(calculateAchievementPercentage(0, 50, 'higher_better')).toBe(0.0);
    });

    it('calculates achievement percentage for lower_better', () => {
        expect(calculateAchievementPercentage(10, 20, 'lower_better')).toBe(50.0);
        expect(calculateAchievementPercentage(10, 10, 'lower_better')).toBe(100.0);
        expect(calculateAchievementPercentage(10, 5, 'lower_better')).toBe(120.0);

        // Actual is 0 -> return cap
        expect(calculateAchievementPercentage(10, 0, 'lower_better')).toBe(120.0);
        expect(calculateAchievementPercentage(10, 0, 'lower_better', 115.0)).toBe(115.0);

        // Edge cases
        expect(calculateAchievementPercentage(null, 5, 'lower_better')).toBe(0.0);
        expect(calculateAchievementPercentage(10, null, 'lower_better')).toBe(0.0);
        expect(calculateAchievementPercentage(0, 5, 'lower_better')).toBe(0.0);
    });

    it('calculates score from percentage across all score band boundaries', () => {
        // Boundary 59.9 (strictly below 3.0)
        const score59_9 = calculateScoreFromPercentage(59.9);
        expect(score59_9).toBeLessThan(3.0);
        expect(score59_9).toBeGreaterThan(2.99);
        expect(getRatingLabel(score59_9).key).toBe('needs_improvement');

        // Boundary 60.0 (3.0)
        const score60 = calculateScoreFromPercentage(60.0);
        expect(score60).toBeCloseTo(3.0, 4);
        expect(getRatingLabel(score60).key).toBe('meets_target');

        // Boundary 74.0 (3.0 + 14 * 0.9/14 = 3.9)
        const score74 = calculateScoreFromPercentage(74.0);
        expect(score74).toBeCloseTo(3.9, 4);
        expect(getRatingLabel(score74).key).toBe('meets_target');

        // Boundary 75.0 (4.0)
        const score75 = calculateScoreFromPercentage(75.0);
        expect(score75).toBeCloseTo(4.0, 4);
        expect(getRatingLabel(score75).key).toBe('exceeds_target');

        // Boundary 89.0 (4.0 + 14 * 0.4/14 = 4.4)
        const score89 = calculateScoreFromPercentage(89.0);
        expect(score89).toBeCloseTo(4.4, 4);
        expect(getRatingLabel(score89).key).toBe('exceeds_target');

        // Boundary 90.0 (4.5)
        const score90 = calculateScoreFromPercentage(90.0);
        expect(score90).toBeCloseTo(4.5, 4);
        expect(getRatingLabel(score90).key).toBe('outstanding');

        // Boundary 100.0 (5.0)
        const score100 = calculateScoreFromPercentage(100.0);
        expect(score100).toBeCloseTo(5.0, 4);
        expect(getRatingLabel(score100).key).toBe('outstanding');

        // Boundary 120.0 (capped at 5.0)
        const score120 = calculateScoreFromPercentage(120.0);
        expect(score120).toBeCloseTo(5.0, 4);
        expect(getRatingLabel(score120).key).toBe('outstanding');
    });

    it('returns bilingual rating labels', () => {
        expect(getRatingLabel(5.0).label_am).toBe('እጅግ የላቀ');
        expect(getRatingLabel(5.0).label_en).toBe('Outstanding');

        expect(getRatingLabel(4.2).label_am).toBe('ከፍተኛ');
        expect(getRatingLabel(4.2).label_en).toBe('Exceeds Target');

        expect(getRatingLabel(3.5).label_am).toBe('የተሳካ');
        expect(getRatingLabel(3.5).label_en).toBe('Meets Target');

        expect(getRatingLabel(2.5).label_am).toBe('ትኩረት የሚሻ');
        expect(getRatingLabel(2.5).label_en).toBe('Needs Improvement');
    });

    it('matches the required fixture: weights 35/30/20/15 with averages 110/95/100/100', () => {
        const weights = {
            financial: 35,
            customers: 30,
            internal: 20,
            learning: 15,
        };

        const averages = {
            financial: 110.0,
            customers: 95.0,
            internal: 100.0,
            learning: 100.0,
        };

        const summary = calculateFromPerspectiveAverages(averages, weights);

        expect(summary.perspectives.financial.score).toBeCloseTo(5.0, 3);
        expect(summary.perspectives.customers.score).toBeCloseTo(4.75, 3);
        expect(summary.perspectives.internal.score).toBeCloseTo(5.0, 3);
        expect(summary.perspectives.learning.score).toBeCloseTo(5.0, 3);

        expect(summary.weighted_score).toBeCloseTo(4.925, 3);
        expect(summary.weighted_percentage).toBeCloseTo(102.0, 3);
        expect(summary.weighted_score_rounded).toBe(4.93);
        expect(summary.weighted_percentage_rounded).toBe(102.0);
        expect(summary.has_redistributed_weights).toBe(false);
    });

    it('redistributes weights proportionally when a perspective has no activities', () => {
        const weights = {
            financial: 35,
            customers: 30,
            internal: 20,
            learning: 15,
        };

        const activities = [
            { perspective: 'financial', target_value: 100, actual_value: 100, direction: 'higher_better' },
            { perspective: 'customers', target_value: 100, actual_value: 75, direction: 'higher_better' },
            { perspective: 'internal', target_value: 100, actual_value: 60, direction: 'higher_better' },
        ];

        const summary = calculateMemberSummary(activities, weights);

        expect(summary.has_redistributed_weights).toBe(true);
        expect(summary.active_perspective_count).toBe(3);

        const expectedFinWeight = (35.0 / 85.0) * 100.0;
        const expectedCustWeight = (30.0 / 85.0) * 100.0;
        const expectedIntWeight = (20.0 / 85.0) * 100.0;

        expect(summary.perspectives.financial.effective_weight).toBeCloseTo(expectedFinWeight, 3);
        expect(summary.perspectives.customers.effective_weight).toBeCloseTo(expectedCustWeight, 3);
        expect(summary.perspectives.internal.effective_weight).toBeCloseTo(expectedIntWeight, 3);
        expect(summary.perspectives.learning.effective_weight).toBe(0.0);
        expect(summary.perspectives.learning.excluded).toBe(true);
        expect(summary.perspectives.learning.redistributed).toBe(true);

        const sumEffective =
            summary.perspectives.financial.effective_weight +
            summary.perspectives.customers.effective_weight +
            summary.perspectives.internal.effective_weight;

        expect(sumEffective).toBeCloseTo(100.0, 3);
    });

    it('formats activity-level bsc perspective and score string', () => {
        expect(formatPerspectiveScoreString('financial', 5.0, 'am')).toBe('ፋይናንስ (5.0/5 - እጅግ የላቀ)');
        expect(formatPerspectiveScoreString('customers', 4.0, 'en')).toBe('Customers (4.0/5 - Exceeds Target)');
    });

    it('calculates company average across member summaries', () => {
        const member1 = { weighted_percentage: 100.0, weighted_score: 5.0 } as any;
        const member2 = { weighted_percentage: 80.0, weighted_score: 4.0 } as any;

        const company = calculateCompanySummary([member1, member2]);

        expect(company.member_count).toBe(2);
        expect(company.company_weighted_percentage).toBe(90.0);
        expect(company.company_weighted_percentage_rounded).toBe(90.0);
        expect(company.company_weighted_score).toBe(4.5);
        expect(company.company_weighted_score_rounded).toBe(4.5);
        expect(company.rating.key).toBe('outstanding');
    });
});
