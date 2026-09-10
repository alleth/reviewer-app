import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { FaCircleCheck } from 'react-icons/fa6';
import { PLANS } from '../plans';
import { getPurchases } from '../reviewers';
import { startCheckout } from '../api';

const peso = (n) => `₱${Number.isInteger(n) ? n.toLocaleString('en-PH') : n.toFixed(2)}`;

// Only reviewer for now — passes are per-reviewer (see src/Payment/Plans.php).
const REVIEWER = 'civil-service';

/**
 * In-app view of the CareerPass passes — for a logged-in user to buy, extend or
 * switch their access. Same data as the public pricing page (src/plans.js).
 */
export default function DashboardPlans({ user }) {
    const current = getPurchases(user).find((p) => p.reviewer === REVIEWER) || null;
    const currentPlanId = current?.planId || null;

    const [busyPlan, setBusyPlan] = useState(null);
    const [error, setError] = useState('');

    // startCheckout() redirects the browser away (window.location.assign) —
    // it never unmounts this page, just freezes it mid-navigation. Hitting
    // the browser Back button restores that frozen snapshot from bfcache
    // instead of re-running the page, so without this the button stays stuck
    // on "Redirecting…" forever. `pageshow`'s `persisted` flag specifically
    // means "restored from bfcache", so reset the busy state right then.
    useEffect(() => {
        const onPageShow = (e) => {
            if (e.persisted) {
                setBusyPlan(null);
                setError('');
            }
        };
        window.addEventListener('pageshow', onPageShow);
        return () => window.removeEventListener('pageshow', onPageShow);
    }, []);

    const buy = async (planId) => {
        setError('');
        setBusyPlan(planId);
        try {
            await startCheckout(REVIEWER, planId); // redirects on success
        } catch (e) {
            setError(e.userMessage || 'Could not start checkout. Please try again.');
            setBusyPlan(null);
        }
    };

    return (
        <section>
            <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">Plans</h1>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {current
                    ? 'Extend or switch your access. One-time payment — nothing renews on its own.'
                    : 'Get a pass to unlock the reviewer. One-time payment, no subscription.'}
            </p>

            {error && (
                <p className="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-300">
                    {error}
                </p>
            )}

            <div className="mt-6 grid gap-4 sm:grid-cols-3">
                {PLANS.map((plan) => {
                    const isCurrent = currentPlanId === plan.id;
                    const busy = busyPlan === plan.id;
                    return (
                        <div
                            key={plan.id}
                            className={`card relative flex h-full flex-col p-5 ${
                                isCurrent ? 'ring-2 ring-brand' : plan.highlight ? 'border-brand/40' : ''
                            }`}
                        >
                            {isCurrent ? (
                                <span className="absolute -top-3 left-5 rounded-full bg-brand px-3 py-1 text-xs font-semibold text-white">
                                    Current
                                </span>
                            ) : plan.badge ? (
                                <span className="absolute -top-3 left-5 rounded-full bg-brand-light px-3 py-1 text-xs font-semibold text-brand-dark dark:bg-brand/20 dark:text-brand">
                                    {plan.badge}
                                </span>
                            ) : null}

                            <h3 className="mt-2 font-bold text-gray-900 dark:text-gray-100">{plan.name}</h3>
                            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{plan.tagline}</p>

                            <div className="mt-4">
                                <span className="text-3xl font-extrabold text-gray-900 dark:text-gray-100">{peso(plan.price)}</span>
                                <span className="ml-1 text-sm text-gray-500 dark:text-gray-400">/ {plan.days} days</span>
                            </div>

                            <ul className="mt-4 space-y-2 text-sm text-gray-600 dark:text-gray-300">
                                {plan.features.map((f) => (
                                    <li key={f} className="flex gap-2">
                                        <FaCircleCheck className="mt-0.5 shrink-0 text-brand" size={14} />
                                        <span>{f}</span>
                                    </li>
                                ))}
                            </ul>

                            <div className="mt-auto pt-5">
                                <button
                                    type="button"
                                    disabled={busy || busyPlan !== null}
                                    onClick={() => buy(plan.id)}
                                    className={`w-full ${isCurrent ? 'btn-outline' : 'btn-primary'}`}
                                >
                                    {busy
                                        ? 'Redirecting…'
                                        : isCurrent
                                            ? 'Extend this pass'
                                            : current
                                                ? 'Switch to this'
                                                : `Get ${plan.name}`}
                                </button>
                            </div>
                        </div>
                    );
                })}
            </div>

            <p className="mt-6 text-sm text-gray-500 dark:text-gray-400">
                Need the full breakdown?{' '}
                <Link to="/pricing" className="font-medium text-brand-dark hover:underline dark:text-brand">
                    See the comparison table
                </Link>.
            </p>
            <p className="mt-2 text-xs text-gray-400 dark:text-gray-500">
                By purchasing a pass you agree to our{' '}
                <Link to="/terms" className="hover:text-brand">Terms of Service</Link> and{' '}
                <Link to="/refund" className="hover:text-brand">Refund Policy</Link>.
            </p>
        </section>
    );
}
