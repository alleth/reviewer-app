import React from 'react';
import { Link } from 'react-router-dom';
import { getPurchases } from '../reviewers';

const REVIEWER = 'civil-service';

// Must match the 'Mock exams' row in plans.js's COMPARISON matrix — keep in
// sync if that ever changes which plans include mock exams.
const MOCK_EXAM_PLAN_IDS = ['30-day', '90-day'];

/**
 * Destination for the "Mock exams" card in Review.js. The card itself is
 * always shown/clickable regardless of plan — this page is where plan-tier
 * gating actually happens, so a 7-day holder sees *why* (and how to upgrade)
 * rather than the card just silently not working.
 *
 * Mock exams itself isn't built yet, so even an entitled (30/90-day) holder
 * currently lands on a "coming soon" state — but a non-entitled (7-day)
 * holder gets the upgrade prompt regardless of that, since the plan
 * restriction is real today even though the feature isn't.
 */
export default function MockExams({ user }) {
    const pass = getPurchases(user).find((p) => p.reviewer === REVIEWER);

    if (!pass) {
        return (
            <div className="card mx-auto mt-6 max-w-md p-8 text-center">
                <p className="font-semibold text-gray-900 dark:text-gray-100">You need an active pass</p>
                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Get a 30-Day or 90-Day pass to unlock mock exams for the Civil Service Exam reviewer.
                </p>
                <Link to="/plans" className="btn-primary mt-5 inline-block">Browse plans</Link>
            </div>
        );
    }

    if (!MOCK_EXAM_PLAN_IDS.includes(pass.planId)) {
        return (
            <div className="card mx-auto mt-6 max-w-md p-8 text-center">
                <p className="font-semibold text-gray-900 dark:text-gray-100">Mock exams need a longer pass</p>
                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    You only have <strong>{pass.plan}</strong> — mock exams are a 30-Day or 90-Day Access feature.
                    Upgrade to unlock full-length, timed simulations.
                </p>
                <Link to="/plans" className="btn-primary mt-5 inline-block">Upgrade my plan</Link>
            </div>
        );
    }

    return (
        <div className="card mx-auto mt-6 max-w-md p-8 text-center">
            <p className="font-semibold text-gray-900 dark:text-gray-100">Coming soon</p>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Full-length, timed mock exams are on the way — your {pass.plan} already covers it once they launch.
            </p>
            <Link to="/library" className="btn-primary mt-5 inline-block">Back to My Library</Link>
        </div>
    );
}
