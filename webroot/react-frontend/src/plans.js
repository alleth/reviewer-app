/**
 * Single source of truth for the CareerPass access passes. Used by the Pricing
 * page; import from here rather than re-declaring prices per component.
 *
 * These are one-time passes for a fixed period — not recurring subscriptions.
 */
export const PLANS = [
    {
        id: '7-day',
        name: '7-Day Access',
        tagline: "For when the exam is just days away and you need to cram.",
        badge: null,
        price: 49,
        days: 7,
        highlight: false,
        features: [
            'Full reviewer access',
            'Practice exams',
            'Progress tracking',
        ],
    },
    {
        id: '30-day',
        name: '30-Day Access',
        tagline: 'The steady month-long review most passers actually do.',
        badge: 'Most Popular',
        price: 174.07,
        days: 30,
        highlight: true,
        features: [
            'Full reviewer access',
            'Practice exams',
            'Mock exams',
            'Progress tracking',
            'Content updates',
        ],
    },
    {
        id: '90-day',
        name: '90-Day Access',
        tagline: 'Start early and review at your own pace — best value.',
        badge: 'Best Value',
        price: 399,
        days: 90,
        highlight: false,
        features: [
            'Everything in 30-Day Access',
            'A full quarter of access',
            'Lowest cost per day',
        ],
    },
];

/** Feature matrix for the "Compare what's included" table (one column per plan). */
export const COMPARISON = [
    { label: 'Full reviewer access', values: [true, true, true] },
    { label: 'Practice exams', values: [true, true, true] },
    { label: 'Progress tracking', values: [true, true, true] },
    { label: 'Mock exams', values: [false, true, true] },
    { label: 'Content updates', values: [false, true, true] },
    { label: 'Access period', values: ['7 days', '30 days', '90 days'] },
];
