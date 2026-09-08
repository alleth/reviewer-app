import React from 'react';
import { Link } from 'react-router-dom';
import { SOCIALS } from '../socials';

/**
 * Shared footer for the public marketing pages and the legal pages. Carries the
 * copyright line, the legal links (Privacy / Terms / Refunds) and the community
 * icons. Replaces the per-page inline footers that used to drift apart.
 */
export default function SiteFooter() {
    return (
        <footer className="mt-16 border-t border-gray-200 bg-gray-50 py-8 text-sm text-gray-500 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-400">
            <div className="mx-auto flex max-w-6xl flex-col items-center gap-4 px-4 sm:flex-row sm:justify-between">
                <div className="flex flex-col items-center gap-2 sm:flex-row sm:gap-4">
                    <span>&copy; {new Date().getFullYear()} CareerPass. All rights reserved.</span>
                    <nav className="flex gap-4">
                        <Link to="/privacy" className="hover:text-brand">Privacy</Link>
                        <Link to="/terms" className="hover:text-brand">Terms</Link>
                        <Link to="/refund" className="hover:text-brand">Refunds</Link>
                    </nav>
                </div>
                <div className="flex gap-4">
                    {SOCIALS.map(({ id, name, href, Icon }) => (
                        <a
                            key={id}
                            href={href}
                            target="_blank"
                            rel="noopener noreferrer"
                            aria-label={name}
                            className="text-gray-500 hover:text-brand dark:text-gray-400"
                        >
                            <Icon size={18} />
                        </a>
                    ))}
                </div>
            </div>
        </footer>
    );
}
