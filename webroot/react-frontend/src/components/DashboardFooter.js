import React from 'react';
import { Link } from 'react-router-dom';

/**
 * Slim footer for the logged-in app shell — deliberately plainer than the
 * marketing SiteFooter: no socials, muted, thin. Just the copyright and the
 * legal links.
 */
export default function DashboardFooter() {
    return (
        <footer className="border-t border-gray-200 py-4 text-xs text-gray-400 dark:border-gray-800 dark:text-gray-500">
            <div className="mx-auto flex max-w-6xl flex-col items-center gap-1.5 px-4 sm:flex-row sm:justify-between">
                <span>&copy; {new Date().getFullYear()} CareerPass</span>
                <nav className="flex gap-4">
                    <Link to="/privacy" className="hover:text-brand">Privacy</Link>
                    <Link to="/terms" className="hover:text-brand">Terms</Link>
                    <Link to="/refund" className="hover:text-brand">Refunds</Link>
                </nav>
            </div>
        </footer>
    );
}
