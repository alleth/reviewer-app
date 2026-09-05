import React, { useEffect, useState } from 'react';
import { useLocation, useNavigate, Link } from 'react-router-dom';
import { FaFacebookF, FaInstagram, FaYoutube } from 'react-icons/fa';
import Modal from '../components/ui/Modal';
import MobileMenu from '../components/ui/MobileMenu';
import LoginPage from './LoginPage';

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
            { id: 'faq-pasasure', label: 'What is SkillSprint?' },
            { id: 'faq-job', label: 'Will SkillSprint help to find a job?' },
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

    useEffect(() => {
        window.scrollTo(0, 0);
    }, [location.pathname]);

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
            <div className="absolute inset-0 z-[1] bg-gray-50/70 backdrop-blur-2xl" />

            {/* Page Content */}
            <div className="relative z-[2] flex-1">
                {/* Header */}
                <div className="flex items-center justify-between px-4 py-3 sm:px-8">
                    <Link to="/" className="ml-4 text-xl font-bold text-brand sm:ml-8">
                        SkillSprint
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

                {/* Hero Section */}
                <div className="mx-auto max-w-3xl px-4 py-16 text-center">
                    <h1 className="text-4xl font-bold leading-tight text-gray-900 sm:text-5xl">
                        Pass the Civil Service<br /> Exam with confidence.
                    </h1>
                    <p className="mt-4 text-gray-500">Unleash Your Potential with Our Tools, Anytime, Anywhere</p>
                    <div className="mt-6 flex justify-center gap-3">
                        <button className="btn-primary px-6">Test Yourself</button>
                        <button className="btn-outline px-6" onClick={() => navigate('/explore')}>Explore</button>
                    </div>
                </div>

                {/* Info Section */}
                <div className="mx-auto max-w-6xl px-4 py-12">
                    <div className="grid grid-cols-1 gap-8 text-center sm:grid-cols-2 lg:grid-cols-4">
                        {newsColumns.map((col) => (
                            <div key={col.title}>
                                <h5 className="mb-2 font-semibold text-gray-900">{col.title}</h5>
                                <ul className="divide-y divide-gray-200 text-left">
                                    {col.items.map((item) => (
                                        <li key={item.id}>
                                            <a
                                                href={`#${item.id}`}
                                                className="block py-2 text-sm text-gray-600 hover:text-brand"
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
            <footer className="relative z-[2] border-t border-gray-200 bg-gray-50 py-6 text-center text-gray-500">
                <div className="mx-auto flex max-w-6xl flex-col items-center gap-3 px-4 sm:flex-row sm:justify-between">
                    <small>&copy; {new Date().getFullYear()} SkillSprint. All rights reserved.</small>
                    <div className="flex gap-4">
                        <a href="https://facebook.com" target="_blank" rel="noopener noreferrer" className="text-gray-500 hover:text-brand">
                            <FaFacebookF size={18} />
                        </a>
                        <a href="https://instagram.com" target="_blank" rel="noopener noreferrer" className="text-gray-500 hover:text-brand">
                            <FaInstagram size={18} />
                        </a>
                        <a href="https://youtube.com" target="_blank" rel="noopener noreferrer" className="text-gray-500 hover:text-brand">
                            <FaYoutube size={18} />
                        </a>
                    </div>
                </div>
            </footer>
        </div>
    );
};

export default LandingPage;
