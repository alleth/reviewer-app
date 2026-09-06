import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { FaHome } from 'react-icons/fa';
import {
    FaBookOpen,
    FaRegClock,
    FaChartLine,
    FaLightbulb,
    FaBullseye,
    FaArrowRight,
} from 'react-icons/fa6';
import PublicHeader from './PublicHeader';
import Modal from './ui/Modal';
import MobileMenu from './ui/MobileMenu';
import Logo from './ui/Logo';
import LoginPage from '../pages/LoginPage';
import { SOCIALS } from '../socials';
import { PLANS } from '../plans';

const helpItems = [
    {
        Icon: FaBookOpen,
        title: 'Structured review modules',
        body: 'Every CSE topic — numerical, verbal, analytical, clerical, and general information / Philippine Constitution — broken into short, focused lessons you can finish in one sitting.',
    },
    {
        Icon: FaRegClock,
        title: 'Exam-realistic mock tests',
        body: 'Timed practice exams that mirror the real CSE-PPT format and pacing, so exam day feels familiar instead of stressful.',
    },
    {
        Icon: FaChartLine,
        title: 'Instant scoring & progress tracking',
        body: 'Get your score the moment you finish, with worked explanations for every item and a dashboard that shows which topics still need work.',
    },
    {
        Icon: FaLightbulb,
        title: 'Tips, strategies & shortcuts',
        body: 'Time-management tactics, elimination techniques and the common traps that trip up first-time takers — from people who have passed.',
    },
];

const Explore = () => {
    const navigate = useNavigate();
    const [showMenu, setShowMenu] = useState(false);
    const [showModal, setShowModal] = useState(false);
    const [authMode, setAuthMode] = useState('login');

    const openModal = (mode) => {
        setAuthMode(mode);
        setShowModal(true);
    };

    const closeModal = () => setShowModal(false);

    return (
        <div className="min-h-screen bg-gray-50 pb-16 dark:bg-gray-900">
            <PublicHeader
                onSignIn={() => openModal('login')}
                onSignUp={() => openModal('signup')}
                onOpenMenu={() => setShowMenu(true)}
            />

            <MobileMenu show={showMenu} onClose={() => setShowMenu(false)}>
                <div className="flex flex-col gap-2">
                    <button className="btn-outline w-full" onClick={() => { openModal('login'); setShowMenu(false); }}>Sign In</button>
                    <button className="btn-primary w-full" onClick={() => { openModal('signup'); setShowMenu(false); }}>Sign Up</button>
                </div>
            </MobileMenu>

            <Modal show={showModal} onClose={closeModal}>
                <LoginPage mode={authMode} onClose={closeModal} />
            </Modal>

            <div className="mx-auto max-w-6xl px-4 pt-4">
                {/* Breadcrumb */}
                <nav className="mb-4 flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                    <Link to="/" className="hover:text-brand"><FaHome /></Link>
                    <span>/</span>
                    <span className="font-medium text-gray-900 dark:text-gray-100">Explore</span>
                </nav>

                {/* Hero */}
                <div className="relative overflow-hidden rounded-2xl bg-gradient-to-br from-brand-light/70 via-brand/10 to-white px-4 py-16 text-center dark:from-brand/15 dark:via-brand/5 dark:to-gray-900">
                    {/* faint logo watermark bleeding off the corner */}
                    <Logo
                        size={340}
                        className="pointer-events-none absolute -right-16 -bottom-24 rotate-12 opacity-[0.08]"
                    />
                    {/* soft brand glow, top-left */}
                    <div className="pointer-events-none absolute -left-16 -top-16 h-56 w-56 rounded-full bg-brand/20 blur-3xl" />

                    <div className="relative">
                        <h2 className="text-2xl font-bold text-gray-900 dark:text-gray-100 sm:text-3xl">
                            Start Your Civil Service Journey with Confidence
                        </h2>
                        <p className="mx-auto mt-3 max-w-2xl text-gray-500 dark:text-gray-400">
                            Get full access to our review library, mock exams, and expert guidance —
                            <strong> passes start at ₱49</strong>, one-time payment, no subscription.
                        </p>
                        <button className="btn-primary mt-5 px-6 py-3 text-base" onClick={() => navigate('/pricing')}>
                            Get Started
                        </button>
                    </div>
                </div>

                {/* What is CareerPass */}
                <section className="mt-16 grid grid-cols-1 gap-8 lg:grid-cols-5">
                    <div className="lg:col-span-3">
                        <h2 className="text-2xl font-bold text-gray-900 dark:text-gray-100 sm:text-3xl">What is CareerPass?</h2>
                        <p className="mt-4 text-gray-600 dark:text-gray-300">
                            CareerPass is a Filipino-built online reviewer for the{' '}
                            <strong>Civil Service Examination (CSE-PPT)</strong> — both the Professional and
                            Sub-Professional levels. Instead of juggling photocopied handouts and scattered PDFs,
                            you get one place to study: organized lessons, a large bank of practice questions, and
                            full-length mock exams that behave like the real thing.
                        </p>
                        <p className="mt-3 text-gray-600 dark:text-gray-300">
                            It's made for people reviewing around a full-time job or school — working parents,
                            fresh graduates, and government hopefuls in the provinces who don't have access to a
                            face-to-face review center. Everything runs in your browser, on any device, at your
                            own pace.
                        </p>
                    </div>

                    <div className="card flex flex-col justify-center p-6 lg:col-span-2">
                        <div className="flex items-center gap-3">
                            <span className="flex h-10 w-10 items-center justify-center rounded-lg bg-brand/10 text-brand">
                                <FaBullseye size={20} />
                            </span>
                            <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Our objective</h3>
                        </div>
                        <p className="mt-3 text-sm text-gray-600 dark:text-gray-300">
                            Make quality Civil Service Exam preparation <strong>affordable and accessible to
                            every Filipino</strong> aiming for a career in government — and build a supportive
                            community that helps each other get there.
                        </p>
                    </div>
                </section>

                {/* How CareerPass helps you pass */}
                <section className="mt-16">
                    <h2 className="text-center text-2xl font-bold text-gray-900 dark:text-gray-100 sm:text-3xl">
                        How CareerPass helps you pass
                    </h2>
                    <p className="mx-auto mt-3 max-w-2xl text-center text-gray-500 dark:text-gray-400">
                        Passing the CSE is about consistent, focused practice — not cramming. CareerPass is built
                        around that.
                    </p>

                    <div className="mt-10 grid grid-cols-1 gap-6 sm:grid-cols-2">
                        {helpItems.map(({ Icon, title, body }) => (
                            <div key={title} className="card flex gap-4 p-6">
                                <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-brand/10 text-brand">
                                    <Icon size={20} />
                                </span>
                                <div>
                                    <h3 className="font-semibold text-gray-900 dark:text-gray-100">{title}</h3>
                                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{body}</p>
                                </div>
                            </div>
                        ))}
                    </div>

                    <div className="mt-12 overflow-hidden rounded-2xl border border-brand/20 bg-brand/5 p-6 text-center sm:p-10 dark:border-brand/25 dark:bg-brand/10">
                        <p className="text-xs font-semibold uppercase tracking-widest text-brand">Simple, one-time pricing</p>
                        <h3 className="mt-2 text-2xl font-bold text-gray-900 dark:text-gray-100 sm:text-3xl">
                            Full reviewer access from <span className="text-brand">₱49</span>
                        </h3>
                        <p className="mx-auto mt-2 max-w-md text-sm text-gray-500 dark:text-gray-400">
                            Pick the pass that fits your timeline. One payment, instant access, nothing auto-renews.
                        </p>
                        <div className="mt-5 flex flex-wrap justify-center gap-2">
                            {PLANS.map((p) => (
                                <span
                                    key={p.id}
                                    className="rounded-full bg-white px-3.5 py-1.5 text-sm font-medium text-gray-700 shadow-sm ring-1 ring-gray-200 dark:bg-gray-800 dark:text-gray-200 dark:ring-gray-700"
                                >
                                    {p.days} days
                                    <span className="mx-1.5 text-gray-300 dark:text-gray-600">·</span>
                                    ₱{Number.isInteger(p.price) ? p.price : p.price.toFixed(2)}
                                </span>
                            ))}
                        </div>
                        <button className="btn-primary mt-6 px-7 py-3 text-base" onClick={() => navigate('/pricing')}>
                            See all plans
                        </button>
                    </div>
                </section>

                {/* Community */}
                <section className="mt-16">
                    <h2 className="text-center text-2xl font-bold text-gray-900 dark:text-gray-100 sm:text-3xl">
                        Join the CareerPass community
                    </h2>
                    <p className="mx-auto mt-3 max-w-2xl text-center text-gray-500 dark:text-gray-400">
                        Reviewing is easier when you're not doing it alone. Follow our pages for updates and join the
                        group and Discord to study with thousands of fellow examinees.
                    </p>

                    <div className="mt-10 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        {SOCIALS.map(({ id, name, handle, blurb, href, Icon }) => (
                            <a
                                key={id}
                                href={href}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="card group flex flex-col p-6 transition-colors hover:border-brand"
                            >
                                <span className="flex h-11 w-11 items-center justify-center rounded-lg bg-brand/10 text-brand">
                                    <Icon size={20} />
                                </span>
                                <h3 className="mt-4 font-semibold text-gray-900 dark:text-gray-100">{name}</h3>
                                <p className="text-sm text-brand">{handle}</p>
                                <p className="mt-2 flex-1 text-sm text-gray-500 dark:text-gray-400">{blurb}</p>
                                <span className="mt-4 inline-flex items-center gap-1.5 text-sm font-medium text-gray-700 group-hover:text-brand dark:text-gray-300">
                                    Open <FaArrowRight size={12} />
                                </span>
                            </a>
                        ))}
                    </div>
                </section>

                {/* Closing CTA */}
                <div className="mt-16 rounded-2xl bg-brand px-4 py-12 text-center text-white">
                    <h2 className="text-2xl font-bold sm:text-3xl">Ready to start reviewing?</h2>
                    <p className="mx-auto mt-2 max-w-xl text-white/90">
                        Create a free account, pick a pass, and get straight into the reviewer.
                    </p>
                    <button
                        className="mt-5 rounded-lg bg-white px-6 py-3 text-base font-medium text-brand-dark transition-colors hover:bg-brand-light"
                        onClick={() => navigate('/pricing')}
                    >
                        Get Started
                    </button>
                </div>
            </div>
        </div>
    );
};

export default Explore;
