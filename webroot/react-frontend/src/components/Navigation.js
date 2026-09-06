// src/components/Navigation.js
import React from 'react';
import { Link } from 'react-router-dom';
import SignInModal from './SignInModal';
import Logo from './ui/Logo';
import ThemeToggle from './ui/ThemeToggle';

const Navigation = () => {
    return (
        <nav className="border-b border-gray-200 bg-gray-50 py-3 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4">
                <Link to="/" className="flex items-center" style={{ gap: '7px' }}>
                    <Logo size={33} />
                    <span style={{ fontSize: 25, fontWeight: 700, letterSpacing: '-0.035em', color: '#00C4A7' }}>
                        CareerPass
                    </span>
                </Link>
                <div className="flex items-center gap-2">
                    <ThemeToggle />
                    <SignInModal />
                    <Link to="/signup">
                        <button className="btn-primary">Sign Up</button>
                    </Link>
                </div>
            </div>
        </nav>
    );
};

export default Navigation;
