import React, { useEffect } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { FaHome } from 'react-icons/fa';
import Logo from '../../components/ui/Logo';
import ThemeToggle from '../../components/ui/ThemeToggle';
import SiteFooter from '../../components/SiteFooter';

const DOCS = [
    { to: '/privacy', label: 'Privacy Policy' },
    { to: '/terms', label: 'Terms of Service' },
    { to: '/refund', label: 'Refund Policy' },
];

/**
 * Shared shell for the three legal pages. Wired above the auth split in
 * index.js, so these render the same whether or not someone is signed in.
 */
export default function LegalLayout({ title, updated, children }) {
    const location = useLocation();

    useEffect(() => {
        window.scrollTo(0, 0);
    }, [location.pathname]);

    return (
        <div className="flex min-h-screen flex-col bg-gray-50 dark:bg-gray-900">
            <header className="flex items-center justify-between px-4 py-3 sm:px-8">
                <Link to="/" className="ml-4 flex items-center sm:ml-8" style={{ gap: '7px' }}>
                    <Logo size={33} />
                    <span style={{ fontSize: 25, fontWeight: 700, letterSpacing: '-0.035em', color: '#00C4A7' }}>
                        CareerPass
                    </span>
                </Link>
                <div className="sm:mr-6">
                    <ThemeToggle />
                </div>
            </header>

            <main className="mx-auto w-full max-w-3xl flex-1 px-4 pt-4">
                <nav className="mb-4 flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                    <Link to="/" className="hover:text-brand"><FaHome /></Link>
                    <span>/</span>
                    <span className="font-medium text-gray-900 dark:text-gray-100">{title}</span>
                </nav>

                <div className="card p-6 sm:p-10">
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100 sm:text-3xl">{title}</h1>
                    <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">Last updated: {updated}</p>

                    <div className="mt-8 space-y-8 text-[15px] leading-relaxed text-gray-600 dark:text-gray-300">
                        {children}
                    </div>
                </div>

                <div className="mt-6 flex flex-wrap gap-x-4 gap-y-1 text-sm text-gray-500 dark:text-gray-400">
                    <span className="font-medium text-gray-700 dark:text-gray-300">Related:</span>
                    {DOCS.map((d) => (
                        <Link key={d.to} to={d.to} className="hover:text-brand">{d.label}</Link>
                    ))}
                </div>
            </main>

            <SiteFooter />
        </div>
    );
}

/** Numbered top-level section. */
export function Section({ n, title, children }) {
    return (
        <section>
            <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
                {n != null ? `${n}. ` : ''}{title}
            </h2>
            <div className="mt-2 space-y-3">{children}</div>
        </section>
    );
}

export function Bullets({ items }) {
    return (
        <ul className="list-disc space-y-1.5 pl-5">
            {items.map((it, i) => (
                <li key={i}>{it}</li>
            ))}
        </ul>
    );
}
