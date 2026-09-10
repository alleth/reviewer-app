import React, { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { FaArrowLeft, FaArrowRight, FaBookOpen } from 'react-icons/fa6';
import { fetchTopic } from '../api';
import { REVIEWERS, getPurchases } from '../reviewers';

/**
 * Study/explainer page for one topic — the detailed "how this is easy to
 * answer" write-up, distinct from Practice (the randomized quiz). Reached
 * from the topic cards in Review.js.
 */
export default function TopicReview({ user }) {
    const { reviewer: reviewerId, topicId } = useParams();
    const reviewer = REVIEWERS[reviewerId];
    const owns = getPurchases(user).some((p) => p.reviewer === reviewerId);

    const [phase, setPhase] = useState('loading'); // loading | ready | error
    const [topic, setTopic] = useState(null);

    useEffect(() => {
        if (!owns) return;
        setPhase('loading');
        fetchTopic(topicId)
            .then((t) => {
                setTopic(t);
                setPhase('ready');
            })
            .catch(() => setPhase('error'));
    }, [topicId, owns]);

    if (!reviewer) {
        return (
            <div className="card p-8 text-center">
                <p className="font-semibold text-gray-900 dark:text-gray-100">Reviewer not found</p>
                <Link to="/library" className="btn-primary mt-4 inline-block">Back to My Library</Link>
            </div>
        );
    }

    const back = (
        <Link to={`/review/${reviewerId}`} className="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-brand dark:text-gray-400">
            <FaArrowLeft size={11} /> {reviewer.name}
        </Link>
    );

    if (!owns) {
        return (
            <div className="card mx-auto mt-6 max-w-md p-8 text-center">
                <p className="font-semibold text-gray-900 dark:text-gray-100">You need an active pass</p>
                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Get a pass to unlock topic reviews for the {reviewer.name}.
                </p>
                <Link to="/plans" className="btn-primary mt-5 inline-block">Browse plans</Link>
            </div>
        );
    }

    return (
        <section className="mx-auto max-w-2xl">
            {back}

            {phase === 'loading' && (
                <p className="mt-6 text-sm text-gray-500 dark:text-gray-400">Loading…</p>
            )}

            {phase === 'error' && (
                <div className="card mt-6 p-8 text-center">
                    <p className="font-semibold text-gray-900 dark:text-gray-100">Couldn't load this topic</p>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">Please try again in a moment.</p>
                </div>
            )}

            {phase === 'ready' && topic && (
                <>
                    <h1 className="mt-3 flex items-center gap-2 text-2xl font-bold text-gray-900 dark:text-gray-100">
                        <FaBookOpen size={18} className="shrink-0 text-brand" /> {topic.name}
                    </h1>

                    <div className="card mt-5 p-6">
                        {topic.topic_review ? (
                            <div className="space-y-4 text-sm leading-relaxed text-gray-700 dark:text-gray-300">
                                {topic.topic_review.content.split(/\n\s*\n/).map((para, i) => (
                                    <p key={i}>{para}</p>
                                ))}
                            </div>
                        ) : (
                            <p className="text-sm text-gray-500 dark:text-gray-400">
                                The detailed write-up for this topic isn't ready yet — check back soon.
                            </p>
                        )}
                    </div>

                    <Link
                        to={`/practice?topic=${encodeURIComponent(topic.name)}`}
                        className="mt-6 flex items-center justify-between gap-4 rounded-xl bg-brand px-5 py-4 text-white transition-colors hover:bg-brand-dark"
                    >
                        <div>
                            <p className="font-semibold">Practice this topic</p>
                            <p className="mt-0.5 text-sm text-white/80">Put it to the test with randomized questions.</p>
                        </div>
                        <FaArrowRight size={16} className="shrink-0" />
                    </Link>
                </>
            )}
        </section>
    );
}
