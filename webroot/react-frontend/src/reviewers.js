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

/**
 * A user's active passes, as attached by the backend (`PassesTable::activeForUser()`
 * via `session()`/`login()`/`googleLogin()`). Falls back to [] for a logged-out
 * user or a stale/malformed localStorage entry — every screen degrades to an
 * empty / "get a pass" state. Shape per entry: { id, reviewer, plan, planId,
 * purchasedAt, expiresAt } — `plan` is the display name (e.g. "7-Day Access"),
 * `planId` the raw id ("7-day"/"30-day"/"90-day").
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
