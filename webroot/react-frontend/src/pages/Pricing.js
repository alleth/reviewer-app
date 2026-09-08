import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import { FaHome } from 'react-icons/fa';
import {
    FaCircleCheck,
    FaMinus,
    FaBolt,
    FaShieldHalved,
    FaArrowRotateLeft,
} from 'react-icons/fa6';
import PublicHeader from '../components/PublicHeader';
import Modal from '../components/ui/Modal';
import MobileMenu from '../components/ui/MobileMenu';
import HandNote from '../components/ui/HandNote';
import SiteFooter from '../components/SiteFooter';
import LoginPage from './LoginPage';
import { PLANS, COMPARISON } from '../plans';

const reassurances = [
    { Icon: FaArrowRotateLeft, title: 'One-time payment', body: 'Pay once for the period you pick. No recurring subscription, nothing auto-renews.' },
    { Icon: FaBolt, title: 'Instant access', body: 'Your reviewer unlocks the moment your payment clears — no waiting.' },
    { Icon: FaShieldHalved, title: 'Secure checkout', body: 'GCash, Maya or card, handled by the payment provider — never stored by us.' },
];

const faqs = [
    {
        q: 'Is this a subscription?',
        a: 'No. Each pass is a one-time payment for a fixed number of days. Nothing renews automatically — when your access period ends, it simply ends.',
    },
    {
        q: 'When do I get access?',
        a: 'Immediately after your payment is confirmed. Your account unlocks the full reviewer and your access countdown starts from that moment.',
    },
    {
        q: 'What happens when my access expires?',
        a: 'Your progress is saved. You can buy another pass anytime to pick up where you left off — you are never charged automatically to keep it.',
    },
    {
        q: 'What payment methods do you accept?',
        a: 'GCash, Maya, credit/debit card, and over-the-counter through the payment provider.',
    },
];

const peso = (n) => `₱${Number.isInteger(n) ? n.toLocaleString('en-PH') : n.toFixed(2)}`;
const perDay = (n, days) => `≈ ₱${(n / days).toFixed(2)} / day`;

function PlanCard({ plan, selected, anySelected, onSelect, onContinue }) {
    const isSelected = selected === plan.id;

    const handleKey = (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            onSelect(plan.id);
        }
    };

    return (
        <div
            role="button"
            tabIndex={0}
            aria-pressed={isSelected}
            onClick={() => onSelect(plan.id)}
            onKeyDown={handleKey}
            className={`card group relative flex h-full cursor-pointer flex-col p-6 outline-none transition-all duration-300
                hover:-translate-y-1.5 hover:shadow-xl focus-visible:ring-2 focus-visible:ring-brand
                ${isSelected
                    ? 'animate-pop -translate-y-1.5 border-brand shadow-xl ring-2 ring-brand'
                    : 'hover:border-brand'}
                ${plan.highlight && !isSelected ? 'border-brand/40 sm:-translate-y-2' : ''}
                ${anySelected && !isSelected ? 'opacity-60 saturate-[0.6]' : ''}`}
        >
            {plan.badge && (
                <span
                    className={`absolute -top-3 left-6 rounded-full px-3 py-1 text-xs font-semibold
                        ${plan.highlight ? 'bg-brand text-white' : 'bg-brand-light text-brand-dark dark:bg-brand/20 dark:text-brand'}`}
                >
                    {plan.badge}
                </span>
            )}
            {isSelected && (
                <span className="absolute -top-3 right-6 inline-flex items-center gap-1 rounded-full bg-brand px-3 py-1 text-xs font-semibold text-white">
                    <FaCircleCheck size={12} /> Selected
                </span>
            )}

            <h3 className="mt-2 text-lg font-bold text-gray-900 dark:text-gray-100">{plan.name}</h3>
            <p className="mt-1 min-h-[2.5rem] text-sm text-gray-500 dark:text-gray-400">{plan.tagline}</p>

            <div className="mt-4 transition-transform duration-300 group-hover:scale-[1.03]">
                <span className="text-4xl font-extrabold text-gray-900 dark:text-gray-100">{peso(plan.price)}</span>
                <span className="ml-1 text-sm text-gray-500 dark:text-gray-400">/ {plan.days} days</span>
            </div>
            <p className="mt-1 text-xs text-gray-400 dark:text-gray-500">{perDay(plan.price, plan.days)}</p>

            <ul className="mt-5 space-y-2 text-sm text-gray-600 dark:text-gray-300">
                {plan.features.map((f) => (
                    <li key={f} className="flex gap-2">
                        <FaCircleCheck className="mt-0.5 shrink-0 text-brand" size={15} />
                        <span>{f}</span>
                    </li>
                ))}
            </ul>

            <div className="mt-auto pt-6">
                <button
                    type="button"
                    onClick={(e) => { e.stopPropagation(); onContinue(); }}
                    className={`w-full ${isSelected || plan.highlight ? 'btn-primary' : 'btn-outline'}`}
                >
                    {isSelected ? `Get ${plan.name}` : 'Choose this plan'}
                </button>
            </div>
        </div>
    );
}

export default function Pricing() {
    const [showMenu, setShowMenu] = useState(false);
    const [showModal, setShowModal] = useState(false);
    const [authMode, setAuthMode] = useState('signup');
    const [selected, setSelected] = useState('30-day');

    const openModal = (mode) => {
        setAuthMode(mode);
        setShowModal(true);
    };

    return (
        <div className="min-h-screen bg-gray-50 pb-20 dark:bg-gray-900">
            <PublicHeader
                onSignIn={() => openModal('login')}
                onSignUp={() => openModal('signup')}
                onOpenMenu={() => setShowMenu(true)}
            />

            <MobileMenu show={showMenu} onClose={() => setShowMenu(false)}>
                <div className="flex flex-col gap-2">
                    <button className="btn-outline w-full" onClick={() => { openModal('login'); setShowMenu(false); }}>Sign In</button>
                    <button className="btn-primary w-full" onClick={() => { openModal('signup'); setShowMenu(false); }}>Sign Up</button>
                </div>
            </MobileMenu>

            <Modal show={showModal} onClose={() => setShowModal(false)}>
                <LoginPage mode={authMode} onClose={() => setShowModal(false)} />
            </Modal>

            <div className="mx-auto max-w-5xl px-4 pt-4">
                <nav className="mb-6 flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                    <Link to="/" className="hover:text-brand"><FaHome /></Link>
                    <span>/</span>
                    <Link to="/explore" className="hover:text-brand">Explore</Link>
                    <span>/</span>
                    <span className="font-medium text-gray-900 dark:text-gray-100">Plans</span>
                </nav>

                {/* Hero */}
                <div className="text-center">
                    <h1 className="text-3xl font-bold text-gray-900 dark:text-gray-100 sm:text-4xl">
                        Pick how long you need.
                    </h1>
                    <p className="mx-auto mt-3 max-w-xl text-gray-500 dark:text-gray-400">
                        One price, one payment, full access for the whole period. No subscription — nothing renews on its own.
                    </p>
                    <HandNote
                        text="no subscription, ever"
                        className="mt-4 hidden -rotate-2 justify-center lg:flex"
                    />
                </div>

                {/* Plan cards */}
                <div className="mt-12 grid grid-cols-1 gap-6 sm:grid-cols-3">
                    {PLANS.map((plan) => (
                        <PlanCard
                            key={plan.id}
                            plan={plan}
                            selected={selected}
                            anySelected={Boolean(selected)}
                            onSelect={setSelected}
                            onContinue={() => openModal('signup')}
                        />
                    ))}
                </div>

                <p className="mt-6 text-center text-sm text-gray-500 dark:text-gray-400">
                    Access expires at the end of the period — your progress is kept, and you can buy another pass anytime.
                </p>
                <p className="mt-2 text-center text-xs text-gray-400 dark:text-gray-500">
                    By purchasing a pass you agree to our{' '}
                    <Link to="/terms" className="hover:text-brand">Terms of Service</Link> and{' '}
                    <Link to="/refund" className="hover:text-brand">Refund Policy</Link>.
                </p>

                {/* Reassurance strip */}
                <div className="mt-14 grid grid-cols-1 gap-6 sm:grid-cols-3">
                    {reassurances.map(({ Icon, title, body }) => (
                        <div key={title} className="flex gap-3">
                            <span className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-brand/10 text-brand">
                                <Icon size={18} />
                            </span>
                            <div>
                                <h3 className="text-sm font-semibold text-gray-900 dark:text-gray-100">{title}</h3>
                                <p className="mt-0.5 text-sm text-gray-500 dark:text-gray-400">{body}</p>
                            </div>
                        </div>
                    ))}
                </div>

                {/* Comparison */}
                <h2 className="mt-16 text-center text-2xl font-bold text-gray-900 dark:text-gray-100">
                    Compare what's included
                </h2>
                <div className="mt-8 overflow-x-auto">
                    <table className="w-full min-w-[560px] border-collapse text-sm">
                        <thead>
                            <tr className="border-b border-gray-200 dark:border-gray-700">
                                <th className="py-3 text-left font-medium text-gray-500 dark:text-gray-400">Feature</th>
                                {PLANS.map((p) => (
                                    <th
                                        key={p.id}
                                        className={`px-4 py-3 text-center font-semibold ${p.highlight ? 'text-brand' : 'text-gray-900 dark:text-gray-100'}`}
                                    >
                                        {p.name}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {COMPARISON.map((row) => (
                                <tr key={row.label} className="border-b border-gray-100 dark:border-gray-800">
                                    <td className="py-3 pr-4 text-gray-700 dark:text-gray-300">{row.label}</td>
                                    {row.values.map((v, i) => (
                                        <td key={i} className="px-4 py-3 text-center text-gray-600 dark:text-gray-300">
                                            <CompareCell value={v} />
                                        </td>
                                    ))}
                                </tr>
                            ))}
                            <tr>
                                <td className="py-3 pr-4 font-medium text-gray-900 dark:text-gray-100">Price</td>
                                {PLANS.map((p) => (
                                    <td key={p.id} className="px-4 py-3 text-center font-bold text-gray-900 dark:text-gray-100">
                                        {peso(p.price)}
                                    </td>
                                ))}
                            </tr>
                        </tbody>
                    </table>
                </div>

                {/* FAQ */}
                <h2 className="mt-16 text-center text-2xl font-bold text-gray-900 dark:text-gray-100">
                    Billing questions
                </h2>
                <div className="mx-auto mt-8 max-w-2xl divide-y divide-gray-200 dark:divide-gray-800">
                    {faqs.map((item) => (
                        <details key={item.q} className="group py-4">
                            <summary className="flex cursor-pointer list-none items-center justify-between font-medium text-gray-900 dark:text-gray-100">
                                {item.q}
                                <span className="ml-4 text-gray-400 transition-transform group-open:rotate-45">+</span>
                            </summary>
                            <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">{item.a}</p>
                        </details>
                    ))}
                </div>

                {/* Closing CTA */}
                <div className="mt-16 rounded-2xl bg-brand px-4 py-12 text-center text-white">
                    <h2 className="text-2xl font-bold sm:text-3xl">Ready when you are</h2>
                    <p className="mx-auto mt-2 max-w-xl text-white/90">
                        Create your account, pick a pass, and get straight into the reviewer.
                    </p>
                    <button
                        className="mt-5 rounded-lg bg-white px-6 py-3 text-base font-medium text-brand-dark transition-colors hover:bg-brand-light"
                        onClick={() => openModal('signup')}
                    >
                        Get Started
                    </button>
                </div>
            </div>

            <SiteFooter />
        </div>
    );
}

function CompareCell({ value }) {
    if (value === true) return <FaCircleCheck className="mx-auto text-brand" size={16} />;
    if (value === false) return <FaMinus className="mx-auto text-gray-300 dark:text-gray-600" size={14} />;
    return <span>{value}</span>;
}
