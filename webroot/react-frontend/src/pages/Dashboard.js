import React from 'react';
import { Routes, Route, Link } from 'react-router-dom';
import TopNavbar from '../components/TopNavbar';
import DashboardHome from './DashboardHome';
import Pricing from './Pricing';

function readUser() {
    try {
        const raw = localStorage.getItem('skillsprint_user');
        return raw ? JSON.parse(raw) : null;
    } catch (e) {
        return null;
    }
}

/** Placeholder for reviewer areas that aren't built yet (practice, topics, ...). */
function ComingSoon() {
    return (
        <div className="card mx-auto mt-10 max-w-lg p-8 text-center">
            <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Coming soon</h2>
            <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                This part of the reviewer is on the way — hang tight.
            </p>
            <Link to="/" className="btn-primary mt-5 inline-block">Back to home</Link>
        </div>
    );
}

function DashboardShell({ user }) {
    return (
        <div className="min-h-screen bg-gray-50 dark:bg-gray-900">
            <TopNavbar userName={user?.fname || user?.user_name} />
            <main className="mx-auto max-w-6xl px-4 pb-16 pt-24">
                <Routes>
                    <Route path="/" element={<DashboardHome user={user} />} />
                    <Route path="*" element={<ComingSoon />} />
                </Routes>
            </main>
        </div>
    );
}

export default function Dashboard() {
    const user = readUser();

    return (
        <Routes>
            {/* Logged-in users can still reach the plans page to buy a pass. */}
            <Route path="/pricing" element={<Pricing />} />
            <Route path="/*" element={<DashboardShell user={user} />} />
        </Routes>
    );
}
