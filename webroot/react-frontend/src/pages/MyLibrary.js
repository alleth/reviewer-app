import React from 'react';
import { Link } from 'react-router-dom';
import { FaArrowRight, FaLock } from 'react-icons/fa6';
import { REVIEWERS, getPurchases, daysLeft } from '../reviewers';

export default function MyLibrary({ user }) {
    const purchases = getPurchases(user);

    return (
        <section>
            <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">My Library</h1>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                The reviewers you've unlocked. Open one to start reviewing.
            </p>

            {purchases.length === 0 ? (
                <div className="mt-6 card flex flex-col items-center p-8 text-center">
                    <span className="flex h-12 w-12 items-center justify-center rounded-full bg-brand/10 text-brand">
                        <FaLock size={18} />
                    </span>
                    <p className="mt-3 font-semibold text-gray-900 dark:text-gray-100">Nothing unlocked yet</p>
                    <p className="mt-1 max-w-sm text-sm text-gray-500 dark:text-gray-400">
                        Get a pass to unlock a reviewer and start practicing right away.
                    </p>
                    <Link to="/plans">
                        <button className="btn-primary mt-4">Browse plans</button>
                    </Link>
                </div>
            ) : (
                <div className="mt-6 grid gap-4 sm:grid-cols-2">
                    {purchases.map((p) => {
                        const r = REVIEWERS[p.reviewer] || { name: p.reviewer, blurb: '' };
                        const left = daysLeft(p.expiresAt);
                        return (
                            <Link
                                key={p.id}
                                to={`/review/${p.reviewer}`}
                                className="card group flex flex-col p-5 transition-colors hover:border-brand"
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <span className="rounded-full bg-brand-light px-2.5 py-0.5 text-xs font-semibold text-brand-dark dark:bg-brand/20 dark:text-brand">
                                        {p.plan}
                                    </span>
                                    <span className="text-xs text-gray-400 dark:text-gray-500">
                                        {left != null ? `${left} days left` : 'Active'}
                                    </span>
                                </div>
                                <h3 className="mt-3 font-semibold text-gray-900 dark:text-gray-100">{r.name}</h3>
                                <p className="mt-0.5 text-sm text-gray-500 dark:text-gray-400">{r.blurb}</p>
                                <span className="mt-4 inline-flex items-center gap-1.5 text-sm font-medium text-brand-dark transition-all group-hover:gap-2.5 dark:text-brand">
                                    Continue reviewing <FaArrowRight size={12} />
                                </span>
                            </Link>
                        );
                    })}
                </div>
            )}
        </section>
    );
}
