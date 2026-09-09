import React, { useEffect, useMemo, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { FaArrowLeft, FaArrowRight, FaCircleCheck, FaCircleXmark, FaRotateRight } from 'react-icons/fa6';
import { fetchTopics, fetchPractice } from '../api';
import { getPurchases } from '../reviewers';

const REVIEWER = 'civil-service';
const COUNTS = [10, 20, 30];

const correctId = (q) => q?.choices?.find((c) => c.is_correct)?.choice_id ?? null;

export default function Practice({ user }) {
    const owns = getPurchases(user).some((p) => p.reviewer === REVIEWER);
    const [params] = useSearchParams();
    const presetTopic = params.get('topic');

    const [topics, setTopics] = useState([]);
    const [topicId, setTopicId] = useState('');
    const [count, setCount] = useState(20);

    const [phase, setPhase] = useState('setup'); // setup | loading | quiz | done | error
    const [questions, setQuestions] = useState([]);
    const [idx, setIdx] = useState(0);
    const [answers, setAnswers] = useState([]); // choiceId | null, per question
    const [revealed, setRevealed] = useState(false);
    const [errMsg, setErrMsg] = useState('');

    useEffect(() => {
        fetchTopics()
            .then((list) => {
                setTopics(list);
                if (presetTopic) {
                    const m = list.find((t) => t.name.toLowerCase() === presetTopic.toLowerCase());
                    if (m) setTopicId(String(m.topic_id));
                }
            })
            .catch(() => { /* picker just falls back to "All topics" */ });
    }, [presetTopic]);

    const start = async () => {
        setPhase('loading');
        setErrMsg('');
        try {
            const qs = await fetchPractice({ topicId: topicId || undefined, limit: count });
            if (!qs.length) {
                setErrMsg('There are no questions available yet for this selection.');
                setPhase('error');
                return;
            }
            setQuestions(qs);
            setAnswers(new Array(qs.length).fill(null));
            setIdx(0);
            setRevealed(false);
            setPhase('quiz');
        } catch (e) {
            setErrMsg('Could not load questions. Please try again.');
            setPhase('error');
        }
    };

    const score = useMemo(
        () => answers.reduce((s, a, i) => s + (a !== null && a === correctId(questions[i]) ? 1 : 0), 0),
        [answers, questions],
    );

    // --- gate: practice is paid content ---
    if (!owns) {
        return (
            <div className="card mx-auto mt-6 max-w-md p-8 text-center">
                <p className="font-semibold text-gray-900 dark:text-gray-100">You need an active pass</p>
                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Get a pass to unlock practice sets for the Civil Service Exam reviewer.
                </p>
                <Link to="/plans" className="btn-primary mt-5 inline-block">Browse plans</Link>
            </div>
        );
    }

    const back = (
        <Link to={`/review/${REVIEWER}`} className="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-brand dark:text-gray-400">
            <FaArrowLeft size={11} /> Back to reviewer
        </Link>
    );

    // --- setup ---
    if (phase === 'setup' || phase === 'loading' || phase === 'error') {
        return (
            <section className="mx-auto max-w-md">
                {back}
                <h1 className="mt-3 text-2xl font-bold text-gray-900 dark:text-gray-100">Practice set</h1>
                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Randomized questions with instant feedback and explanations.
                </p>

                <div className="card mt-6 space-y-4 p-5">
                    <div>
                        <label className="form-label">Topic</label>
                        <select value={topicId} onChange={(e) => setTopicId(e.target.value)} className="form-input">
                            <option value="">All topics</option>
                            {topics.map((t) => (
                                <option key={t.topic_id} value={t.topic_id}>{t.name}</option>
                            ))}
                        </select>
                    </div>
                    <div>
                        <label className="form-label">Number of questions</label>
                        <div className="flex gap-2">
                            {COUNTS.map((n) => (
                                <button
                                    key={n}
                                    type="button"
                                    onClick={() => setCount(n)}
                                    className={`flex-1 rounded-lg border px-3 py-2 text-sm font-medium ${
                                        count === n
                                            ? 'border-brand bg-brand/10 text-brand-dark dark:text-brand'
                                            : 'border-gray-300 text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800'
                                    }`}
                                >
                                    {n}
                                </button>
                            ))}
                        </div>
                    </div>

                    {phase === 'error' && (
                        <p className="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700 dark:bg-red-950/40 dark:text-red-300">
                            {errMsg}
                        </p>
                    )}

                    <button type="button" onClick={start} disabled={phase === 'loading'} className="btn-primary w-full">
                        {phase === 'loading' ? 'Loading…' : 'Start practice'}
                    </button>
                </div>
            </section>
        );
    }

    // --- done ---
    if (phase === 'done') {
        const pct = Math.round((score / questions.length) * 100);
        return (
            <section className="mx-auto max-w-md">
                {back}
                <div className="card mt-6 p-8 text-center">
                    <p className="text-sm text-gray-500 dark:text-gray-400">Your score</p>
                    <p className="mt-1 text-4xl font-extrabold text-gray-900 dark:text-gray-100">
                        {score}<span className="text-2xl text-gray-400">/{questions.length}</span>
                    </p>
                    <p className="mt-1 text-sm font-medium text-brand-dark dark:text-brand">{pct}%</p>

                    <div className="mt-6 flex gap-2">
                        <button
                            type="button"
                            onClick={() => setPhase('setup')}
                            className="btn-primary flex flex-1 items-center justify-center gap-2"
                        >
                            <FaRotateRight size={13} /> Practice again
                        </button>
                        <Link to={`/review/${REVIEWER}`} className="btn-outline flex-1">Done</Link>
                    </div>
                </div>

                {/* answer review */}
                <div className="mt-6 space-y-3">
                    {questions.map((q, i) => {
                        const right = correctId(q);
                        const mine = answers[i];
                        return (
                            <div key={q.question_id} className="card p-4">
                                <p className="text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {i + 1}. {q.question_text}
                                </p>
                                <ul className="mt-2 space-y-1 text-sm">
                                    {q.choices.map((c) => {
                                        const isRight = c.choice_id === right;
                                        const isMineWrong = c.choice_id === mine && mine !== right;
                                        return (
                                            <li
                                                key={c.choice_id}
                                                className={`flex items-start gap-2 ${
                                                    isRight ? 'text-brand-dark dark:text-brand'
                                                    : isMineWrong ? 'text-red-600 dark:text-red-400'
                                                    : 'text-gray-500 dark:text-gray-400'
                                                }`}
                                            >
                                                {isRight ? <FaCircleCheck className="mt-0.5 shrink-0" size={12} />
                                                    : isMineWrong ? <FaCircleXmark className="mt-0.5 shrink-0" size={12} />
                                                    : <span className="mt-0.5 w-3 shrink-0" />}
                                                <span>{c.choice_text}</span>
                                            </li>
                                        );
                                    })}
                                </ul>
                                {q.explanation && (
                                    <p className="mt-2 border-t border-gray-100 pt-2 text-xs text-gray-500 dark:border-gray-800 dark:text-gray-400">
                                        {q.explanation}
                                    </p>
                                )}
                            </div>
                        );
                    })}
                </div>
            </section>
        );
    }

    // --- quiz ---
    const q = questions[idx];
    const chosen = answers[idx];
    const right = correctId(q);

    return (
        <section className="mx-auto max-w-md">
            <div className="flex items-center justify-between text-sm text-gray-500 dark:text-gray-400">
                {back}
                <span>Question {idx + 1} of {questions.length}</span>
            </div>

            <div className="mt-3 h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-gray-800">
                <div
                    className="h-full rounded-full bg-brand transition-all"
                    style={{ width: `${((idx + (revealed ? 1 : 0)) / questions.length) * 100}%` }}
                />
            </div>

            <div className="card mt-5 p-5">
                {q.topic?.name && (
                    <p className="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">
                        {q.topic.name}
                    </p>
                )}
                <p className="mt-1 font-medium text-gray-900 dark:text-gray-100">{q.question_text}</p>

                <div className="mt-4 space-y-2">
                    {q.choices.map((c) => {
                        const picked = chosen === c.choice_id;
                        let cls = 'border-gray-300 hover:border-brand dark:border-gray-600';
                        if (revealed) {
                            if (c.choice_id === right) cls = 'border-brand bg-brand/10';
                            else if (picked) cls = 'border-red-400 bg-red-50 dark:bg-red-950/30';
                            else cls = 'border-gray-200 opacity-60 dark:border-gray-700';
                        } else if (picked) {
                            cls = 'border-brand bg-brand/5 ring-1 ring-brand';
                        }
                        return (
                            <button
                                key={c.choice_id}
                                type="button"
                                disabled={revealed}
                                onClick={() => setAnswers((a) => { const n = [...a]; n[idx] = c.choice_id; return n; })}
                                className={`flex w-full items-start gap-2 rounded-lg border px-3 py-2.5 text-left text-sm text-gray-800 transition-colors dark:text-gray-200 ${cls}`}
                            >
                                {revealed && c.choice_id === right && <FaCircleCheck className="mt-0.5 shrink-0 text-brand" size={13} />}
                                {revealed && picked && c.choice_id !== right && <FaCircleXmark className="mt-0.5 shrink-0 text-red-500" size={13} />}
                                <span>{c.choice_text}</span>
                            </button>
                        );
                    })}
                </div>

                {revealed && q.explanation && (
                    <p className="mt-4 rounded-lg bg-gray-50 px-3 py-2 text-sm text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                        {q.explanation}
                    </p>
                )}

                <div className="mt-5">
                    {!revealed ? (
                        <button
                            type="button"
                            disabled={chosen === null}
                            onClick={() => setRevealed(true)}
                            className="btn-primary w-full"
                        >
                            Submit answer
                        </button>
                    ) : (
                        <button
                            type="button"
                            onClick={() => {
                                if (idx + 1 < questions.length) { setIdx(idx + 1); setRevealed(false); }
                                else setPhase('done');
                            }}
                            className="btn-primary flex w-full items-center justify-center gap-2"
                        >
                            {idx + 1 < questions.length ? 'Next question' : 'See results'}
                            <FaArrowRight size={13} />
                        </button>
                    )}
                </div>
            </div>
        </section>
    );
}
