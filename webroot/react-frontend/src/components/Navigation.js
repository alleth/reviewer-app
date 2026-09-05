// src/components/Navigation.js
import React from 'react';
import { Link } from 'react-router-dom';
import SignInModal from './SignInModal';

const Navigation = () => {
    return (
        <nav className="border-b border-gray-200 bg-gray-50 py-3 shadow-sm">
            <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4">
                <Link to="/" className="text-lg font-bold text-brand">SkillSprint</Link>
                <div className="flex items-center gap-2">
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
