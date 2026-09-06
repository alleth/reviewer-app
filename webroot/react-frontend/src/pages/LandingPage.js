import React, { useEffect, useState } from 'react';
import { useLocation, useNavigate, Link } from 'react-router-dom';
import Modal from '../components/ui/Modal';
import MobileMenu from '../components/ui/MobileMenu';
import Logo from '../components/ui/Logo';
import ThemeToggle from '../components/ui/ThemeToggle';
import LoginPage from './LoginPage';
import { SOCIALS } from '../socials';

const newsColumns = [
    {
        title: 'News & Trending',
        items: [
            { id: 'news-passers', label: 'List of Passers for CSC March 2025' },
            { id: 'news-subjects', label: 'Upcoming new subjects' },
        ],
    },
    {
        title: 'Popular Subjects',
        items: [
            { id: 'subject-csc-pro', label: 'CSC - Professional' },
            { id: 'subject-csc-subpro', label: 'CSC - Sub-Professional' },
        ],
    },
    {
        title: 'Popular Articles',
        items: [
            { id: 'article-cse2025', label: 'Examination Announcement No. 04s 2025 - CSE PPT Exam Calendar CY 2025' },
        ],
    },
    {
        title: 'FAQ',
        items: [
            { id: 'faq-pasasure', label: 'What is CareerPass?' },
            { id: 'faq-job', label: 'Will CareerPass help to find a job?' },
            { id: 'faq-software', label: 'Do I need any special software?' },
            { id: 'faq-fees', label: 'Are there any fees?' },
        ],
    },
];

const LandingPage = () => {
    const location = useLocation();
    const navigate = useNavigate();
    const [showModal, setShowModal] = useState(false);
    const [authMode, setAuthMode] = useState('login');
    const [showMenu, setShowMenu] = useState(false);
    const [signedOutNotice, setSignedOutNotice] = useState(false);

    useEffect(() => {
        window.scrollTo(0, 0);
    }, [location.pathname]);

    useEffect(() => {
        try {
            if (sessionStorage.getItem('careerpass_signed_out') === 'elsewhere') {
                setSignedOutNotice(true);
                sessionStorage.removeItem('careerpass_signed_out');
            }
        } catch (e) { /* ignore */ }
    }, []);

    const openModal = (mode) => {
        setAuthMode(mode);
        setShowModal(true);
    };

    const closeModal = () => setShowModal(false);

    return (
        <div className="relative flex min-h-screen flex-col overflow-hidden">
            {/* Background Circles */}
            <div className="pointer-events-none absolute inset-0 z-0">
                <div className="absolute left-[10%] top-[10%] h-[300px] w-[300px] animate-drift1 rounded-full bg-brand opacity-40 blur-3xl" />
                <div className="absolute left-[70%] top-[60%] h-[300px] w-[300px] animate-drift2 rounded-full bg-brand opacity-40 blur-3xl" />
            </div>

            {/* Glass Overlay */}
            <div className="absolute inset-0 z-[1] bg-gray-50/70 backdrop-blur-2xl dark:bg-gray-900/70" />

            {/* Page Content */}
            <div className="relative z-[2] flex-1">
                {signedOutNotice && (
                    <div className="border-b border-amber-300 bg-amber-50 px-4 py-2.5 text-center text-sm text-amber-800 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-300">
                        You were signed out here because your account signed in on another device.
                    </div>
                )}
                {/* Header */}
                <div className="flex items-center justify-between px-4 py-3 sm:px-8">
                    <Link to="/" className="ml-4 flex items-center sm:ml-8" style={{ gap: '7px' }}>
                        <Logo size={33} />
                        <span style={{ fontSize: 25, fontWeight: 700, letterSpacing: '-0.035em', color: '#00C4A7' }}>
                            CareerPass
                        </span>
                    </Link>
                    <div className="flex items-center gap-2 sm:mr-6">
                        <ThemeToggle />
                        <div className="hidden gap-2 md:flex">
                            <button className="btn-outline" onClick={() => openModal('login')}>Sign In</button>
                            <button className="btn-primary" onClick={() => openModal('signup')}>Sign Up</button>
                        </div>
                        <div className="md:hidden">
                            <button className="btn-outline" onClick={() => setShowMenu(true)} aria-label="Open menu">☰</button>
                        </div>
                    </div>
                </div>

                <MobileMenu show={showMenu} onClose={() => setShowMenu(false)}>
                    <div className="flex flex-col gap-2">
                        <button className="btn-outline w-full" onClick={() => { openModal('login'); setShowMenu(false); }}>Sign In</button>
                        <button className="btn-primary w-full" onClick={() => { openModal('signup'); setShowMenu(false); }}>Sign Up</button>
                    </div>
                </MobileMenu>

                {/* Hero Section */}
                <div className="mx-auto max-w-3xl px-4 py-16 text-center">
                    <h1 className="text-4xl font-bold leading-tight text-gray-900 sm:text-5xl dark:text-gray-100">
                        Pass the Civil Service<br /> Exam with confidence.
                    </h1>
                    <p className="mt-4 text-gray-500 dark:text-gray-400">Unleash Your Potential with Our Tools, Anytime, Anywhere</p>
                    <div className="mt-6 flex justify-center gap-3">
                        <button className="btn-primary px-6" onClick={() => navigate('/pricing')}>Get Started</button>
                        <button className="btn-outline px-6" onClick={() => navigate('/explore')}>Explore</button>
                    </div>
                </div>

                {/* Info Section */}
                <div className="mx-auto max-w-6xl px-4 py-12">
                    <div className="grid grid-cols-1 gap-8 text-center sm:grid-cols-2 lg:grid-cols-4">
                        {newsColumns.map((col) => (
                            <div key={col.title}>
                                <h5 className="mb-2 font-semibold text-gray-900 dark:text-gray-100">{col.title}</h5>
                                <ul className="divide-y divide-gray-200 text-left dark:divide-gray-700">
                                    {col.items.map((item) => (
                                        <li key={item.id}>
                                            <a
                                                href={`#${item.id}`}
                                                className="block py-2 text-sm text-gray-600 hover:text-brand dark:text-gray-300"
                                            >
                                                {item.label}
                                            </a>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        ))}
                    </div>
                </div>
            </div>

            {/* Auth Modal */}
            <Modal show={showModal} onClose={closeModal}>
                <LoginPage mode={authMode} onClose={closeModal} />
            </Modal>

            {/* Footer */}
            <footer className="relative z-[2] border-t border-gray-200 bg-gray-50 py-6 text-center text-gray-500 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-400">
                <div className="mx-auto flex max-w-6xl flex-col items-center gap-3 px-4 sm:flex-row sm:justify-between">
                    <small>&copy; {new Date().getFullYear()} CareerPass. All rights reserved.</small>
                    <div className="flex gap-4">
                        {SOCIALS.map(({ id, name, href, Icon }) => (
                            <a
                                key={id}
                                href={href}
                                target="_blank"
                                rel="noopener noreferrer"
                                aria-label={name}
                                className="text-gray-500 hover:text-brand dark:text-gray-400"
                            >
                                <Icon size={18} />
                            </a>
                        ))}
                    </div>
                </div>
            </footer>
        </div>
    );
};

export default LandingPage;
