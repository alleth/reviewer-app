import React from 'react';
import { Link } from 'react-router-dom';
import { FaCircleCheck } from 'react-icons/fa6';
import { PLANS } from '../plans';
import { getPurchases } from '../reviewers';

const peso = (n) => `₱${Number.isInteger(n) ? n.toLocaleString('en-PH') : n.toFixed(2)}`;

/**
 * In-app view of the CareerPass passes — for a logged-in user to extend or
 * switch their access. Same data as the public pricing page (src/plans.js).
 */
export default function DashboardPlans({ user }) {
    // Backend doesn't report the active plan yet; when it does, set this from
    // the user's current purchase so the matching card shows "Current".
    const currentPlanId = getPurchases(user)[0]?.planId || null;

    return (
        <section>
            <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">Plans</h1>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Extend or switch your access. One-time payment, no subscription — nothing renews on its own.
            </p>

            <div className="mt-6 grid gap-4 sm:grid-cols-3">
                {PLANS.map((plan) => {
                    const isCurrent = currentPlanId === plan.id;
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
                                    className={`w-full ${isCurrent ? 'btn-outline' : 'btn-primary'}`}
                                >
                                    {isCurrent ? 'Extend this pass' : 'Switch to this'}
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
        </section>
    );
}
