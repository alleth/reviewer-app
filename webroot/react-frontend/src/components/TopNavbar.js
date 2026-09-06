import React, { useEffect, useRef, useState } from 'react';
import { FaChevronDown } from 'react-icons/fa6';
import { api } from '../api';
import Logo from './ui/Logo';
import ThemeToggle from './ui/ThemeToggle';

const onLogout = async () => {
    try {
        await api.post('/api/logout');
        localStorage.removeItem('skillsprint_user');
        window.location.href = '/';
    } catch (error) {
        console.error('Logout failed:', error);
    }
};

const TopNavbar = ({ userName }) => {
    const [open, setOpen] = useState(false);
    const menuRef = useRef(null);

    useEffect(() => {
        const onClickOutside = (e) => {
            if (menuRef.current && !menuRef.current.contains(e.target)) {
                setOpen(false);
            }
        };
        document.addEventListener('mousedown', onClickOutside);
        return () => document.removeEventListener('mousedown', onClickOutside);
    }, []);

    return (
        <nav className="fixed top-0 z-30 w-full border-b border-gray-200 bg-gray-50 px-4 py-2 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div className="mx-auto flex max-w-6xl items-center justify-between">
                <a href="/" className="mr-8 flex items-center" style={{ gap: '7px' }}>
                    <Logo size={33} />
                    <span style={{ fontSize: 25, fontWeight: 700, letterSpacing: '-0.035em', color: '#00C4A7' }}>
                        CareerPass
                    </span>
                </a>

                <div className="flex items-center gap-2">
                    <ThemeToggle />

                    <div className="relative" ref={menuRef}>
                        <button
                            type="button"
                            onClick={() => setOpen((v) => !v)}
                            aria-haspopup="menu"
                            aria-expanded={open}
                            className="flex items-center gap-1.5 rounded-md px-2 py-1 text-sm font-medium text-gray-900 hover:bg-gray-100 dark:text-gray-100 dark:hover:bg-gray-800"
                        >
                            {userName || 'User'}
                            <FaChevronDown
                                size={10}
                                className={`text-gray-400 transition-transform ${open ? 'rotate-180' : ''}`}
                            />
                        </button>

                        {open && (
                            <div className="absolute right-0 z-10 mt-2 w-44 rounded-lg border border-gray-200 bg-white py-1 shadow-lg dark:border-gray-700 dark:bg-gray-800">
                                <a href="#notifications" className="block px-4 py-2 text-sm text-gray-900 hover:bg-gray-50 dark:text-gray-100 dark:hover:bg-gray-700">
                                    Notifications
                                </a>
                                <a href="#settings" className="block px-4 py-2 text-sm text-gray-900 hover:bg-gray-50 dark:text-gray-100 dark:hover:bg-gray-700">
                                    Settings
                                </a>
                                <div className="my-1 h-px bg-gray-200 dark:bg-gray-700" />
                                <button
                                    type="button"
                                    onClick={onLogout}
                                    className="block w-full px-4 py-2 text-left text-sm text-gray-900 hover:bg-gray-50 dark:text-gray-100 dark:hover:bg-gray-700"
                                >
                                    Logout
                                </button>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </nav>
    );
};

export default TopNavbar;
