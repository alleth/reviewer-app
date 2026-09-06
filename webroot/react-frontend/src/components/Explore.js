import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { FaHome } from 'react-icons/fa';
import {
    FaBookOpen,
    FaRegClock,
    FaChartLine,
    FaLightbulb,
    FaBullseye,
    FaArrowRight,
} from 'react-icons/fa6';
import Modal from './ui/Modal';
import MobileMenu from './ui/MobileMenu';
import Logo from './ui/Logo';
import LoginPage from '../pages/LoginPage';
import { SOCIALS } from '../socials';

const packages = [
    {
        id: 'sub-professional',
        badge: 'Most Popular',
        title: 'Sub-Professional Reviewer',
        features: [
            'Comprehensive Review Modules',
            'Timed Practice Exams',
            'Tips & Strategies',
            '1-Month Access',
        ],
        oldPrice: '₱999',
        price: '₱350',
    },
    {
        id: 'professional',
        badge: 'Best Value',
        title: 'Professional Reviewer',
        features: [
            'Comprehensive Review Modules',
            'Simulated Exams',
            'Expert Exam Strategies',
            '3-Month Access',
        ],
        oldPrice: '₱1499',
        price: '₱500',
    },
];

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
    const [showMenu, setShowMenu] = useState(false);
    const [showModal, setShowModal] = useState(false);
    const [authMode, setAuthMode] = useState('login');

    const openModal = (mode) => {
        setAuthMode(mode);
        setShowModal(true);
    };

    const closeModal = () => setShowModal(false);

    return (
        <div className="min-h-screen bg-gray-50 pb-16">
            {/* Header */}
            <div className="flex items-center justify-between px-4 py-3 sm:px-8">
                <Link to="/" className="ml-4 flex items-center sm:ml-8" style={{ gap: '7px' }}>
                    <Logo size={33} />
                    <span style={{ fontSize: 25, fontWeight: 700, letterSpacing: '-0.035em', color: '#00C4A7' }}>
                        CareerPass
                    </span>
                </Link>
                <div className="hidden gap-2 sm:mr-6 md:flex">
                    <button className="btn-outline" onClick={() => openModal('login')}>Sign In</button>
                    <button className="btn-primary" onClick={() => openModal('signup')}>Sign Up</button>
                </div>
                <div className="md:hidden">
                    <button className="btn-outline" onClick={() => setShowMenu(true)} aria-label="Open menu">☰</button>
                </div>
            </div>

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
                <nav className="mb-4 flex items-center gap-2 text-sm text-gray-500">
                    <Link to="/" className="hover:text-brand"><FaHome /></Link>
                    <span>/</span>
                    <span className="font-medium text-gray-900">Explore</span>
                </nav>

                {/* Hero */}
                <div className="rounded-2xl bg-brand/10 px-4 py-16 text-center">
                    <h2 className="text-2xl font-bold text-gray-900 sm:text-3xl">
                        Start Your Civil Service Journey with Confidence
                    </h2>
                    <p className="mx-auto mt-3 max-w-2xl text-gray-500">
                        Sign up now and enjoy a <strong>7-day free trial</strong> to access our top-tier review content,
                        mock exams, and expert guidance — all designed to help you succeed!
                    </p>
                    <button className="btn-primary mt-5 px-6 py-3 text-base" onClick={() => openModal('signup')}>
                        Start 7-Day Free Trial
                    </button>
                </div>

                {/* What is CareerPass */}
                <section className="mt-16 grid grid-cols-1 gap-8 lg:grid-cols-5">
                    <div className="lg:col-span-3">
                        <h2 className="text-2xl font-bold text-gray-900 sm:text-3xl">What is CareerPass?</h2>
                        <p className="mt-4 text-gray-600">
                            CareerPass is a Filipino-built online reviewer for the{' '}
                            <strong>Civil Service Examination (CSE-PPT)</strong> — both the Professional and
                            Sub-Professional levels. Instead of juggling photocopied handouts and scattered PDFs,
                            you get one place to study: organized lessons, a large bank of practice questions, and
                            full-length mock exams that behave like the real thing.
                        </p>
                        <p className="mt-3 text-gray-600">
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
                            <h3 className="text-lg font-semibold text-gray-900">Our objective</h3>
                        </div>
                        <p className="mt-3 text-sm text-gray-600">
                            Make quality Civil Service Exam preparation <strong>affordable and accessible to
                            every Filipino</strong> aiming for a career in government — and build a supportive
                            community that helps each other get there.
                        </p>
                    </div>
                </section>

                {/* How CareerPass helps you pass */}
                <section className="mt-16">
                    <h2 className="text-center text-2xl font-bold text-gray-900 sm:text-3xl">
                        How CareerPass helps you pass
                    </h2>
                    <p className="mx-auto mt-3 max-w-2xl text-center text-gray-500">
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
                                    <h3 className="font-semibold text-gray-900">{title}</h3>
                                    <p className="mt-1 text-sm text-gray-500">{body}</p>
                                </div>
                            </div>
                        ))}
                    </div>
                </section>

                {/* Packages */}
                <h2 className="mt-16 text-center text-2xl font-bold text-gray-900 sm:text-3xl">
                    Explore Our Review Packages
                </h2>
                <p className="mx-auto mb-10 mt-3 max-w-2xl text-center text-gray-500">
                    Whether you're preparing for the Civil Service Exam or advancing to professional government roles,
                    we've created focused review packages to help you succeed. Each plan includes carefully designed
                    modules, practice tests, and expert tips. Choose the one that best fits your goal, and you'll get
                    immediate access after purchase — no complicated steps required.
                </p>

                <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                    {packages.map((pkg) => (
                        <div key={pkg.id} className="card flex h-full flex-col p-6">
                            <span className="mb-3 inline-block w-fit rounded-full bg-brand px-3 py-1 text-xs font-semibold text-white">
                                {pkg.badge}
                            </span>
                            <h3 className="text-lg font-semibold text-gray-900">{pkg.title}</h3>
                            <ul className="mt-3 list-disc space-y-1 pl-5 text-sm text-gray-500">
                                {pkg.features.map((feature) => (
                                    <li key={feature}>{feature}</li>
                                ))}
                            </ul>
                            <div className="mt-4">
                                <span className="mr-2 text-gray-400 line-through">{pkg.oldPrice}</span>
                                <strong className="text-xl text-gray-900">{pkg.price}</strong>
                            </div>
                            <Link to="/checkout" className="mt-4 inline-block w-fit">
                                <button className="btn-primary">Purchase</button>
                            </Link>
                        </div>
                    ))}
                </div>

                {/* Community */}
                <section className="mt-16">
                    <h2 className="text-center text-2xl font-bold text-gray-900 sm:text-3xl">
                        Join the CareerPass community
                    </h2>
                    <p className="mx-auto mt-3 max-w-2xl text-center text-gray-500">
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
                                <h3 className="mt-4 font-semibold text-gray-900">{name}</h3>
                                <p className="text-sm text-brand">{handle}</p>
                                <p className="mt-2 flex-1 text-sm text-gray-500">{blurb}</p>
                                <span className="mt-4 inline-flex items-center gap-1.5 text-sm font-medium text-gray-700 group-hover:text-brand">
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
                        Create a free account and take your first practice exam today.
                    </p>
                    <button
                        className="mt-5 rounded-lg bg-white px-6 py-3 text-base font-medium text-brand-dark transition-colors hover:bg-brand-light"
                        onClick={() => openModal('signup')}
                    >
                        Start 7-Day Free Trial
                    </button>
                </div>
            </div>
        </div>
    );
};

export default Explore;
