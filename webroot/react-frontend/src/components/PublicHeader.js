import React from 'react';
import { Link } from 'react-router-dom';
import Logo from './ui/Logo';
import ThemeToggle from './ui/ThemeToggle';

/**
 * Shared top bar for the logged-out marketing pages (Explore, Pricing, ...).
 * The page owns the auth modal / mobile menu; this just renders the bar and
 * calls back.
 */
export default function PublicHeader({ onSignIn, onSignUp, onOpenMenu }) {
    return (
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
                    <button className="btn-outline" onClick={onSignIn}>Sign In</button>
                    <button className="btn-primary" onClick={onSignUp}>Sign Up</button>
                </div>
                <div className="md:hidden">
                    <button className="btn-outline" onClick={onOpenMenu} aria-label="Open menu">☰</button>
                </div>
            </div>
        </div>
    );
}
