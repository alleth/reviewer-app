import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { FaHome } from 'react-icons/fa';
import Modal from './ui/Modal';
import MobileMenu from './ui/MobileMenu';
import LoginPage from '../pages/LoginPage';

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
                <Link to="/" className="ml-4 text-xl font-bold text-brand sm:ml-8">SkillSprint</Link>
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

                <h1 className="mt-12 text-center text-3xl font-bold text-gray-900">
                    Explore Our Review Packages
                </h1>
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
            </div>
        </div>
    );
};

export default Explore;
