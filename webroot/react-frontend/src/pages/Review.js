import React from 'react';
import { Link, useParams } from 'react-router-dom';
import { FaArrowRight, FaArrowLeft, FaBookOpen, FaClipboardCheck } from 'react-icons/fa6';
import { REVIEWERS, getPurchases } from '../reviewers';

/**
 * The "start reviewing" page for one reviewer — reached by clicking a card in
 * My Library. Lists the reviewer's topics and the ways to practice. Practice
 * links go to /practice (src/pages/Practice.js); mock exams aren't built yet.
 */
export default function Review({ user }) {
    const { reviewer: reviewerId } = useParams();
    const reviewer = REVIEWERS[reviewerId];
    const owns = getPurchases(user).some((p) => p.reviewer === reviewerId);

    if (!reviewer) {
        return (
            <div className="card p-8 text-center">
                <p className="font-semibold text-gray-900 dark:text-gray-100">Reviewer not found</p>
                <Link to="/library" className="btn-primary mt-4 inline-block">Back to My Library</Link>
            </div>
        );
    }

    return (
        <section>
            <Link to="/library" className="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-brand dark:text-gray-400">
                <FaArrowLeft size={11} /> My Library
            </Link>

            <h1 className="mt-3 text-2xl font-bold text-gray-900 dark:text-gray-100">{reviewer.name}</h1>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{reviewer.blurb}</p>

            {!owns && (
                <div className="mt-4 rounded-lg border border-brand/30 bg-brand/5 px-4 py-3 text-sm text-gray-600 dark:bg-brand/10 dark:text-gray-300">
                    You don't have an active pass for this reviewer.{' '}
                    <Link to="/plans" className="font-medium text-brand-dark underline dark:text-brand">Get one</Link> to unlock everything.
                </div>
            )}

            {/* Start practice */}
            <Link
                to="/practice"
                className="mt-6 flex items-center justify-between gap-4 rounded-xl bg-brand px-5 py-4 text-white transition-colors hover:bg-brand-dark"
            >
                <div>
                    <p className="font-semibold">Start a practice set</p>
                    <p className="mt-0.5 text-sm text-white/80">Randomized questions with instant scoring.</p>
                </div>
                <FaArrowRight size={16} className="shrink-0" />
            </Link>

            {/* Topics */}
            <h2 className="mt-8 flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
                <FaBookOpen size={14} className="text-brand" /> Topics
            </h2>
            <div className="mt-3 grid gap-3 sm:grid-cols-2">
                {reviewer.topics.map((topic) => (
                    <Link
                        key={topic}
                        to={`/practice?topic=${encodeURIComponent(topic)}`}
                        className="card group flex items-center justify-between p-4 transition-colors hover:border-brand"
                    >
                        <span className="text-sm font-medium text-gray-800 dark:text-gray-200">{topic}</span>
                        <FaArrowRight size={12} className="text-gray-300 transition-colors group-hover:text-brand dark:text-gray-600" />
                    </Link>
                ))}
            </div>

            {/* Mock exams */}
            <h2 className="mt-8 flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-gray-100">
                <FaClipboardCheck size={14} className="text-brand" /> Mock exams
            </h2>
            <Link
                to="/mock-exams"
                className="card group mt-3 flex items-center justify-between p-4 transition-colors hover:border-brand"
            >
                <span className="text-sm text-gray-600 dark:text-gray-300">Full-length, timed simulations that mirror the real exam.</span>
                <FaArrowRight size={12} className="shrink-0 text-gray-300 transition-colors group-hover:text-brand dark:text-gray-600" />
            </Link>
        </section>
    );
}
