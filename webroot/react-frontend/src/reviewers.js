/**
 * Catalog of reviewers CareerPass offers, plus helpers for reading a user's
 * purchases. Today there's one reviewer (Civil Service Exam); this file is the
 * place to add the rest (LET, board exams, ...) as the platform grows.
 */
export const REVIEWERS = {
    'civil-service': {
        id: 'civil-service',
        name: 'Civil Service Exam Reviewer',
        blurb: 'CSE-PPT — Professional & Sub-Professional',
        topics: [
            'Numerical Ability',
            'Verbal Ability',
            'Analytical Ability',
            'Clerical Ability',
            'General Information & the 1987 Constitution',
        ],
    },
};

/** Reviewers announced but not yet available — shown as locked teasers. */
export const UPCOMING_REVIEWERS = [
    { id: 'let', name: 'Licensure Exam for Teachers (LET)', note: 'In development' },
    { id: 'nle', name: 'Nursing Licensure Exam (NLE)', note: 'Planned' },
];

/**
 * A user's purchases. The backend doesn't send these yet, so this returns []
 * for real accounts — every screen degrades to an empty / "get a pass" state.
 * Expected shape once wired: { id, reviewer, plan, planId, purchasedAt, expiresAt }
 */
export function getPurchases(user) {
    return Array.isArray(user?.purchases) ? user.purchases : [];
}

/** Whole days left until an ISO expiry (0 when past, null when unknown). */
export function daysLeft(iso) {
    if (!iso) return null;
    const d = Math.ceil((new Date(iso).getTime() - Date.now()) / 86400000);
    return d > 0 ? d : 0;
}
