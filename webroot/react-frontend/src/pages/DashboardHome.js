import React from 'react';
import { Link } from 'react-router-dom';
import { FaBookOpen, FaClipboardCheck, FaChartLine, FaArrowRight } from 'react-icons/fa6';

/** Days remaining on a pass, from an ISO expiry string. null when unknown. */
function daysLeft(iso) {
    if (!iso) return null;
    const d = Math.ceil((new Date(iso).getTime() - Date.now()) / 86400000);
    return d > 0 ? d : 0;
}

const quickLinks = [
    { to: '/topics', Icon: FaBookOpen, title: 'Browse topics', body: 'Study one subject at a time.' },
    { to: '/mock-exams', Icon: FaClipboardCheck, title: 'Mock exams', body: 'Full-length, timed simulations.' },
    { to: '/progress', Icon: FaChartLine, title: 'My progress', body: 'See how each topic is going.' },
];

export default function DashboardHome({ user }) {
    const name = user?.fname || user?.user_name || 'there';
    // These fields aren't on the user object yet — the UI degrades to a
    // "no active pass" prompt until the backend provides them.
    const planName = user?.plan_name || null;
    const left = daysLeft(user?.plan_expires);

    return (
        <>
            <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100 sm:text-3xl">
                Welcome back, {name}
            </h1>
            <p className="mt-1 text-gray-500 dark:text-gray-400">
                Pick up where you left off, or jump into a fresh practice set.
            </p>

            {/* Access status */}
            {planName ? (
                <div className="card mt-6 flex items-center justify-between p-5">
                    <div>
                        <p className="text-xs font-medium uppercase tracking-wide text-gray-400 dark:text-gray-500">
                            Your access
                        </p>
                        <p className="mt-0.5 font-semibold text-gray-900 dark:text-gray-100">{planName}</p>
                    </div>
                    <div className="text-right">
                        <p className="text-2xl font-extrabold text-brand-dark dark:text-brand">{left ?? '—'}</p>
                        <p className="text-xs text-gray-400 dark:text-gray-500">days left</p>
                    </div>
                </div>
            ) : (
                <div className="mt-6 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-brand/30 bg-brand/5 p-5 dark:bg-brand/10">
                    <div>
                        <p className="font-semibold text-gray-900 dark:text-gray-100">No active pass yet</p>
                        <p className="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                            Get a pass to unlock the full reviewer, mock exams and progress tracking.
                        </p>
                    </div>
                    <Link to="/pricing">
                        <button className="btn-primary">See plans</button>
                    </Link>
                </div>
            )}

            {/* Start practice */}
            <Link
                to="/practice"
                className="group mt-6 flex items-center justify-between gap-4 rounded-2xl bg-gradient-to-br from-brand to-brand-dark p-6 text-white shadow-lg shadow-brand/25 transition-transform hover:-translate-y-0.5"
            >
                <div>
                    <p className="text-sm text-white/80">Ready to review?</p>
                    <p className="mt-0.5 text-xl font-bold">Start a practice set</p>
                    <p className="mt-1 text-sm text-white/80">Randomized questions with instant scoring.</p>
                </div>
                <span className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-white/15 transition-transform group-hover:translate-x-0.5">
                    <FaArrowRight size={20} />
                </span>
            </Link>

            {/* Quick links */}
            <div className="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
                {quickLinks.map(({ to, Icon, title, body }) => (
                    <Link
                        key={to}
                        to={to}
                        className="card group flex flex-col p-5 transition-colors hover:border-brand"
                    >
                        <span className="flex h-10 w-10 items-center justify-center rounded-lg bg-brand/10 text-brand">
                            <Icon size={18} />
                        </span>
                        <p className="mt-3 font-semibold text-gray-900 dark:text-gray-100">{title}</p>
                        <p className="mt-0.5 text-sm text-gray-500 dark:text-gray-400">{body}</p>
                    </Link>
                ))}
            </div>
        </>
    );
}
