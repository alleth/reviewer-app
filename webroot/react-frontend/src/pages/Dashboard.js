import React from 'react';
import { api } from '../api';
import ThemeToggle from '../components/ui/ThemeToggle';

function Dashboard() {
    const handleLogout = async (e) => {
        e.preventDefault();
        try {
            await api.post('/api/logout');
        } catch (err) {
            console.error('Logout request failed:', err);
        }
        localStorage.removeItem('skillsprint_user');
        window.location.href = '/';
    };

    return (
        <div className="min-h-screen bg-gray-50 dark:bg-gray-900">
            <nav className="bg-gray-900 dark:bg-gray-950">
                <div className="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
                    <span className="text-lg font-semibold text-white">Reviewer Dashboard</span>
                    <div className="flex items-center gap-3">
                        <ThemeToggle className="border-gray-700 text-gray-300 hover:bg-gray-800" />
                        <a href="#logout" onClick={handleLogout} className="text-sm font-medium text-gray-300 hover:text-white">
                            Logout
                        </a>
                    </div>
                </div>
            </nav>
            <div className="mx-auto max-w-6xl px-4 py-8">
                <h2 className="text-2xl font-bold text-gray-900 dark:text-gray-100">Welcome to the Dashboard</h2>
                <p className="mt-2 text-gray-500 dark:text-gray-400">This is a protected area for logged-in users.</p>
            </div>
        </div>
    );
}

export default Dashboard;
