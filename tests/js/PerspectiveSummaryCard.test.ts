import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import PerspectiveSummaryCard from '../../resources/js/Components/PerspectiveSummaryCard.vue';
import { calculateMemberSummary, calculateFromPerspectiveAverages } from '../../resources/js/lib/bsc';
import { i18n } from '../../resources/js/i18n';

describe('PerspectiveSummaryCard Component (Vitest)', () => {
    const mountCard = (summary: any, weights: any, locale = 'am') => {
        i18n.global.locale.value = locale;
        return mount(PerspectiveSummaryCard, {
            props: {
                summary,
                weights,
            },
            global: {
                plugins: [i18n],
            },
        });
    };

    it('renders Member 1 fixture with weights 35/30/20/15 showing weighted score ≈ 4.92 / 4.93 (Outstanding)', () => {
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
        const wrapper = mountCard(summary, weights, 'am');

        expect(summary.weighted_score).toBeCloseTo(4.925, 2);
        expect(wrapper.text()).toContain('4.93');
        expect(wrapper.text()).toContain('102%');
        expect(wrapper.text()).toContain('እጅግ የላቀ');
        expect(wrapper.find('.bg-emerald-100').exists()).toBe(true);
    });

    it('renders color chips for all four rating bands', () => {
        // 1. Outstanding (Score >= 4.5)
        const summaryOutstanding = calculateFromPerspectiveAverages(
            { financial: 100, customers: 100, internal: 100, learning: 100 },
            { financial: 25, customers: 25, internal: 25, learning: 25 }
        );
        const wrapper1 = mountCard(summaryOutstanding, { financial: 25, customers: 25, internal: 25, learning: 25 });
        expect(wrapper1.find('.bg-emerald-100').exists()).toBe(true);
        expect(wrapper1.text()).toContain('እጅግ የላቀ');

        // 2. Exceeds Target (4.0 <= Score < 4.5, e.g. p = 80 -> score 4.14)
        const summaryExceeds = calculateFromPerspectiveAverages(
            { financial: 80, customers: 80, internal: 80, learning: 80 },
            { financial: 25, customers: 25, internal: 25, learning: 25 }
        );
        const wrapper2 = mountCard(summaryExceeds, { financial: 25, customers: 25, internal: 25, learning: 25 });
        expect(wrapper2.find('.bg-blue-100').exists()).toBe(true);
        expect(wrapper2.text()).toContain('ከፍተኛ');

        // 3. Meets Target (3.0 <= Score < 4.0, e.g. p = 70 -> score 3.64)
        const summaryMeets = calculateFromPerspectiveAverages(
            { financial: 70, customers: 70, internal: 70, learning: 70 },
            { financial: 25, customers: 25, internal: 25, learning: 25 }
        );
        const wrapper3 = mountCard(summaryMeets, { financial: 25, customers: 25, internal: 25, learning: 25 });
        expect(wrapper3.find('.bg-amber-100').exists()).toBe(true);
        expect(wrapper3.text()).toContain('የተሳካ');

        // 4. Needs Improvement (Score < 3.0, e.g. p = 40 -> score < 2.0)
        const summaryNeeds = calculateFromPerspectiveAverages(
            { financial: 40, customers: 40, internal: 40, learning: 40 },
            { financial: 25, customers: 25, internal: 25, learning: 25 }
        );
        const wrapper4 = mountCard(summaryNeeds, { financial: 25, customers: 25, internal: 25, learning: 25 });
        expect(wrapper4.find('.bg-rose-100').exists()).toBe(true);
        expect(wrapper4.text()).toContain('ትኩረት የሚሻ');
    });

    it('displays dash and redistribution note when a perspective has no activities', () => {
        const weights = {
            financial: 35,
            customers: 30,
            internal: 20,
            learning: 15,
        };

        const activities = [
            { perspective: 'financial', target_value: 100, actual_value: 100, direction: 'higher_better' },
            { perspective: 'customers', target_value: 100, actual_value: 80, direction: 'higher_better' },
            { perspective: 'internal', target_value: 100, actual_value: 70, direction: 'higher_better' },
            // learning is empty
        ];

        const summary = calculateMemberSummary(activities, weights);
        const wrapper = mountCard(summary, weights, 'am');

        expect(summary.has_redistributed_weights).toBe(true);
        expect(wrapper.text()).toContain('ክብደት በቅደም ተከተል ተከፋፍሏል');
        expect(wrapper.text()).toContain('ክብደት ተከፋፍሏል');
    });
});
