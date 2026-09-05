import React from 'react';
import { api } from '../api';

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
        <div className="min-h-screen bg-gray-50">
            <nav className="bg-gray-900">
                <div className="mx-auto flex max-w-6xl items-center justify-between px-4 py-3">
                    <span className="text-lg font-semibold text-white">Reviewer Dashboard</span>
                    <a href="#logout" onClick={handleLogout} className="text-sm font-medium text-gray-300 hover:text-white">
                        Logout
                    </a>
                </div>
            </nav>
            <div className="mx-auto max-w-6xl px-4 py-8">
                <h2 className="text-2xl font-bold text-gray-900">Welcome to the Dashboard</h2>
                <p className="mt-2 text-gray-500">This is a protected area for logged-in users.</p>
            </div>
        </div>
    );
}

export default Dashboard;
