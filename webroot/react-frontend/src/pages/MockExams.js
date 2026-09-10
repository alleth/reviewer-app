import React from 'react';
import { Link } from 'react-router-dom';
import { FaLock, FaHourglassHalf, FaArrowRight } from 'react-icons/fa6';
import Logo from '../components/ui/Logo';
import { getPurchases } from '../reviewers';

const REVIEWER = 'civil-service';

// Must match the 'Mock exams' row in plans.js's COMPARISON matrix — keep in
// sync if that ever changes which plans include mock exams.
const MOCK_EXAM_PLAN_IDS = ['30-day', '90-day'];

/**
 * Same branded-hero treatment as Explore.js's top section (gradient + a
 * faint oversized Logo watermark bleeding off the corner + a soft glow) —
 * reused here so a "you're blocked" state still feels like CareerPass
 * instead of a bare gray dead-end.
 */
function BrandCard({ icon, children }) {
    return (
        <div className="relative mx-auto mt-6 max-w-md overflow-hidden rounded-2xl border border-brand/10 bg-gradient-to-br from-brand-light/70 via-brand/10 to-white p-8 text-center shadow-sm dark:from-brand/15 dark:via-brand/5 dark:to-gray-900">
            <Logo
                size={220}
                className="pointer-events-none absolute -bottom-14 -right-10 rotate-12 opacity-[0.08]"
            />
            <div className="pointer-events-none absolute -left-10 -top-10 h-40 w-40 rounded-full bg-brand/20 blur-3xl" />

            <div className="relative">
                <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-brand/10 text-brand dark:bg-brand/20">
                    {icon}
                </div>
                {children}
            </div>
        </div>
    );
}

function CardCta({ to, children }) {
    return (
        <Link to={to} className="btn-primary mt-5 inline-flex items-center gap-2">
            {children} <FaArrowRight size={12} />
        </Link>
    );
}

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
            <BrandCard icon={<FaLock size={18} />}>
                <p className="mt-4 font-semibold text-gray-900 dark:text-gray-100">You need an active pass</p>
                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Get a 30-Day or 90-Day pass to unlock mock exams for the Civil Service Exam reviewer.
                </p>
                <CardCta to="/plans">Browse plans</CardCta>
            </BrandCard>
        );
    }

    if (!MOCK_EXAM_PLAN_IDS.includes(pass.planId)) {
        return (
            <BrandCard icon={<FaLock size={18} />}>
                <p className="mt-4 font-semibold text-gray-900 dark:text-gray-100">Mock exams need a longer pass</p>
                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    You only have <strong className="text-gray-700 dark:text-gray-200">{pass.plan}</strong> — mock
                    exams are a 30-Day or 90-Day Access feature. Upgrade to unlock full-length, timed simulations.
                </p>
                <CardCta to="/plans">Upgrade my plan</CardCta>
            </BrandCard>
        );
    }

    return (
        <BrandCard icon={<FaHourglassHalf size={18} />}>
            <p className="mt-4 font-semibold text-gray-900 dark:text-gray-100">Coming soon</p>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Full-length, timed mock exams are on the way — your {pass.plan} already covers it once they launch.
            </p>
            <CardCta to="/library">Back to My Library</CardCta>
        </BrandCard>
    );
}
