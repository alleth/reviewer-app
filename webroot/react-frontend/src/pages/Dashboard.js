import React from 'react';
import { Routes, Route, Navigate, Link } from 'react-router-dom';
import TopNavbar from '../components/TopNavbar';
import DashboardSidebar from '../components/DashboardSidebar';
import SiteFooter from '../components/SiteFooter';
import DashboardHome from './DashboardHome';
import MyLibrary from './MyLibrary';
import DashboardPlans from './DashboardPlans';
import Review from './Review';
import Settings from './Settings';
import Pricing from './Pricing';
import Receipt from './Receipt';
import { CheckoutSuccess, CheckoutCancel } from './Checkout';

function readUser() {
    try {
        const raw = localStorage.getItem('skillsprint_user');
        return raw ? JSON.parse(raw) : null;
    } catch (e) {
        return null;
    }
}

/** Placeholder for reviewer areas that aren't built yet (practice, mock exams). */
function ComingSoon() {
    return (
        <div className="card mx-auto mt-6 max-w-lg p-8 text-center">
            <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Coming soon</h2>
            <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">
                This part of the reviewer is on the way — hang tight.
            </p>
            <Link to="/library" className="btn-primary mt-5 inline-block">Back to My Library</Link>
        </div>
    );
}

function DashboardShell({ user }) {
    return (
        <div className="flex min-h-screen flex-col bg-gray-50 dark:bg-gray-900">
            <TopNavbar userName={user?.fname || user?.user_name} />
            {/* Centered container keeps the left/right margins; sidebar lives inside it. */}
            <div className="mx-auto w-full max-w-6xl flex-1 px-4 pb-12 pt-24">
                <div className="flex flex-col gap-6 lg:flex-row lg:gap-10">
                    <DashboardSidebar />
                    <main className="min-w-0 flex-1">
                        <Routes>
                            <Route path="/" element={<Navigate to="/library" replace />} />
                            <Route path="/library" element={<MyLibrary user={user} />} />
                            <Route path="/dashboard" element={<DashboardHome user={user} />} />
                            <Route path="/plans" element={<DashboardPlans user={user} />} />
                            <Route path="/checkout/success" element={<CheckoutSuccess />} />
                            <Route path="/checkout/cancel" element={<CheckoutCancel />} />
                            <Route path="/settings" element={<Settings />} />
                            <Route path="/review/:reviewer" element={<Review user={user} />} />
                            <Route path="*" element={<ComingSoon />} />
                        </Routes>
                    </main>
                </div>
            </div>
            <SiteFooter />
        </div>
    );
}

export default function Dashboard() {
    const user = readUser();

    return (
        <Routes>
            {/* Full-bleed pricing page stays reachable while logged in — pass `user`
                so its plan buttons start checkout instead of the signup modal. */}
            <Route path="/pricing" element={<Pricing user={user} />} />
            {/* Receipt is full-bleed so it prints without the dashboard chrome. */}
            <Route path="/billing/:reference" element={<Receipt user={user} />} />
            <Route path="/*" element={<DashboardShell user={user} />} />
        </Routes>
    );
}
