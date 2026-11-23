import { describe, it, expect } from 'vitest';
import {
    evaluateGate,
    generateDecisionTemplate,
    calculateCompanySummary,
    DEFAULT_EXECUTIVE_DECISIONS,
    type MemberSummary,
} from '@/lib/bsc';

describe('GateEvaluator and Consolidated Company Logic (JS mirror)', () => {
    it('returns disabled when plc gate is not enabled', () => {
        const result = evaluateGate(false, 20, 150000, 25, 175000);
        expect(result.enabled).toBe(false);
        expect(result.is_met).toBe(false);
        expect(result.status).toBe('disabled');
    });

    it('returns met when both customers and MRR meet or exceed threshold', () => {
        const result = evaluateGate(true, 20, 150000, 20, 160000);
        expect(result.enabled).toBe(true);
        expect(result.is_met).toBe(true);
        expect(result.customers_met).toBe(true);
        expect(result.mrr_met).toBe(true);
        expect(result.status).toBe('met');
        expect(result.suggested_decision_am).toContain('20 የሚከፍሉ ደንበኞችን');
        expect(result.suggested_decision_am).toContain('160,000.00 ETB MRR');
        expect(result.suggested_decision_am).toContain('PLC');
    });

    it('returns not_met when either customer count or MRR is below target', () => {
        const custBelow = evaluateGate(true, 20, 150000, 19, 160000);
        expect(custBelow.is_met).toBe(false);
        expect(custBelow.customers_met).toBe(false);
        expect(custBelow.mrr_met).toBe(true);
        expect(custBelow.status).toBe('not_met');

        const mrrBelow = evaluateGate(true, 20, 150000, 25, 140000);
        expect(mrrBelow.is_met).toBe(false);
        expect(mrrBelow.customers_met).toBe(true);
        expect(mrrBelow.mrr_met).toBe(false);
        expect(mrrBelow.status).toBe('not_met');
    });

    it('generates proper bilingual decision templates', () => {
        const amTemplate = generateDecisionTemplate(true, 20, 150000, 22, 170000, 'am');
        expect(amTemplate).toContain('20 የሚከፍሉ ደንበኞችን');
        expect(amTemplate).toContain('170,000.00 ETB MRR');
        expect(amTemplate).toContain('አሟልቷል');

        const enTemplate = generateDecisionTemplate(false, 20, 150000, 18, 140000, 'en');
        expect(enTemplate).toContain('20 paying customers');
        expect(enTemplate).toContain('140,000.00 ETB MRR');
        expect(enTemplate).toContain('deferred until benchmarks are satisfied');
    });

    it('provides default executive decision items in both languages', () => {
        expect(DEFAULT_EXECUTIVE_DECISIONS.am.length).toBe(2);
        expect(DEFAULT_EXECUTIVE_DECISIONS.am[0]).toContain('PLC');
        expect(DEFAULT_EXECUTIVE_DECISIONS.en.length).toBe(2);
        expect(DEFAULT_EXECUTIVE_DECISIONS.en[0]).toContain('Private Limited Company (PLC)');
    });

    it('calculates company average from sample inputs (4.91, 4.92, 4.98) producing 4.94', () => {
        const dummyRating = {
            key: 'outstanding' as const,
            min_score: 4.5,
            max_score: 5.0,
            label_am: 'እጅግ የላቀ',
            label_en: 'Outstanding',
        };

        const mockMember1: Partial<MemberSummary> = {
            weighted_score: 4.91,
            weighted_percentage: 101.4,
            rating: dummyRating,
        };
        const mockMember2: Partial<MemberSummary> = {
            weighted_score: 4.92,
            weighted_percentage: 102.1,
            rating: dummyRating,
        };
        const mockMember3: Partial<MemberSummary> = {
            weighted_score: 4.98,
            weighted_percentage: 104.5,
            rating: dummyRating,
        };

        const company = calculateCompanySummary([
            mockMember1 as MemberSummary,
            mockMember2 as MemberSummary,
            mockMember3 as MemberSummary,
        ]);

        expect(company.member_count).toBe(3);
        // (4.91 + 4.92 + 4.98) / 3 = 4.936666... -> 4.94
        expect(company.company_weighted_score_rounded).toBe(4.94);
        // (101.4 + 102.1 + 104.5) / 3 = 102.6666... -> 102.7
        expect(company.company_weighted_percentage_rounded).toBe(102.7);
        expect(company.rating.key).toBe('outstanding');
    });
});
