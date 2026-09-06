import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { FaChevronDown } from 'react-icons/fa6';
import { REVIEWERS, getPurchases } from '../reviewers';

/**
 * Progress-monitoring view. Built to span more than the Civil Service Exam —
 * the reviewer selector lets a user switch which program's progress they're
 * looking at once they own more than one.
 */
export default function DashboardHome({ user }) {
    const purchases = getPurchases(user);
    const owned = purchases.length
        ? purchases.map((p) => REVIEWERS[p.reviewer]).filter(Boolean)
        : [REVIEWERS['civil-service']];

    const [reviewerId, setReviewerId] = useState(owned[0]?.id || 'civil-service');
    const reviewer = REVIEWERS[reviewerId] || owned[0];

    // No real progress data yet — everything shows the "no activity" state.
    const hasActivity = false;

    return (
        <section>
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">Dashboard</h1>

                <label className="relative">
                    <select
                        value={reviewerId}
                        onChange={(e) => setReviewerId(e.target.value)}
                        className="appearance-none rounded-lg border border-gray-300 bg-white py-2 pl-3 pr-9 text-sm font-medium text-gray-700 outline-none focus:border-brand focus:ring-2 focus:ring-brand/20 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200"
                    >
                        {owned.map((r) => (
                            <option key={r.id} value={r.id}>{r.name}</option>
                        ))}
                    </select>
                    <FaChevronDown
                        size={11}
                        className="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-gray-400"
                    />
                </label>
            </div>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Your progress across the {reviewer.name}.
            </p>

            {hasActivity ? null : (
                <div className="mt-6 card flex flex-col items-center p-8 text-center">
                    <p className="font-semibold text-gray-900 dark:text-gray-100">No activity yet</p>
                    <p className="mt-1 max-w-sm text-sm text-gray-500 dark:text-gray-400">
                        Start a practice set and your scores, streak and per-topic progress will show up here.
                    </p>
                    <Link to={`/review/${reviewer.id}`}>
                        <button className="btn-primary mt-4">Start reviewing</button>
                    </Link>
                </div>
            )}

            {/* Placeholder metric tiles — populated once progress data exists */}
            <div className="mt-6 grid gap-4 sm:grid-cols-3">
                {[
                    { label: 'Questions answered', value: '—' },
                    { label: 'Average score', value: '—' },
                    { label: 'Day streak', value: '—' },
                ].map((t) => (
                    <div key={t.label} className="card p-4">
                        <p className="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                            {t.label}
                        </p>
                        <p className="mt-1 text-2xl font-bold text-gray-900 dark:text-gray-100">{t.value}</p>
                    </div>
                ))}
            </div>

            <div className="mt-6">
                <h2 className="text-sm font-semibold text-gray-900 dark:text-gray-100">Progress by topic</h2>
                <div className="mt-3 space-y-3">
                    {reviewer.topics.map((topic) => (
                        <div key={topic}>
                            <div className="flex justify-between text-sm text-gray-600 dark:text-gray-300">
                                <span>{topic}</span>
                                <span className="text-gray-400 dark:text-gray-500">0%</span>
                            </div>
                            <div className="mt-1 h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                                <div className="h-full w-0 rounded-full bg-brand" />
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </section>
    );
}
