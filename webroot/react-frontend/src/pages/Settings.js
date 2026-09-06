import React, { useState } from 'react';
import { Link } from 'react-router-dom';
import {
    FaCircleCheck,
    FaTriangleExclamation,
    FaDesktop,
    FaCreditCard,
    FaUser,
    FaLock,
} from 'react-icons/fa6';
import { api } from '../api';
import { getPurchases, daysLeft } from '../reviewers';

function readUser() {
    try {
        const raw = localStorage.getItem('skillsprint_user');
        return raw ? JSON.parse(raw) : null;
    } catch (e) {
        return null;
    }
}

function deviceLabel() {
    const ua = typeof navigator !== 'undefined' ? navigator.userAgent : '';
    const os =
        /Windows/.test(ua) ? 'Windows'
        : /Mac OS X/.test(ua) ? 'Mac'
        : /Android/.test(ua) ? 'Android'
        : /iPhone|iPad/.test(ua) ? 'iOS'
        : /Linux/.test(ua) ? 'Linux'
        : 'this device';
    const browser =
        /Edg\//.test(ua) ? 'Edge'
        : /OPR\//.test(ua) ? 'Opera'
        : /Firefox\//.test(ua) ? 'Firefox'
        : /Chrome\//.test(ua) ? 'Chrome'
        : /Safari\//.test(ua) ? 'Safari'
        : 'Browser';
    return `${browser} on ${os}`;
}

function Section({ icon: Icon, title, desc, children }) {
    return (
        <section className="card p-5 sm:p-6">
            <div className="flex items-start gap-3">
                <span className="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand/10 text-brand">
                    <Icon size={16} />
                </span>
                <div>
                    <h2 className="font-semibold text-gray-900 dark:text-gray-100">{title}</h2>
                    {desc && <p className="mt-0.5 text-sm text-gray-500 dark:text-gray-400">{desc}</p>}
                </div>
            </div>
            <div className="mt-5">{children}</div>
        </section>
    );
}

export default function Settings() {
    const user = readUser();
    const [form, setForm] = useState({
        fname: user?.fname || '',
        lname: user?.lname || '',
        user_name: user?.user_name || '',
        email: user?.email || '',
    });
    const [status, setStatus] = useState(null); // { type: 'ok' | 'err', msg }
    const [saving, setSaving] = useState(false);

    const change = (e) => setForm({ ...form, [e.target.name]: e.target.value });

    const save = async (e) => {
        e.preventDefault();
        setSaving(true);
        setStatus(null);
        try {
            const res = await api.post('/api/account', form);
            if (res.data.success) {
                localStorage.setItem('skillsprint_user', JSON.stringify(res.data.user));
                setStatus({ type: 'ok', msg: 'Profile saved.' });
            } else {
                const errs = res.data.errors || {};
                const first = Object.values(errs)[0];
                setStatus({
                    type: 'err',
                    msg: first ? Object.values(first)[0] : res.data.message || 'Could not save.',
                });
            }
        } catch (err) {
            setStatus({
                type: 'err',
                msg: err.response?.data?.message || 'Could not reach the server. Try again.',
            });
        } finally {
            setSaving(false);
        }
    };

    const purchase = getPurchases(user)[0] || null;
    // Prefer the backend flag; before it's wired, infer from whether this is a
    // Google-only account (those start without a password).
    const passwordSet = user?.password_set ?? !user?.google_id;

    return (
        <div className="mx-auto max-w-2xl">
            <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">Settings</h1>
            <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Manage your profile, security, plan and devices.
            </p>

            <div className="mt-6 space-y-6">
                {/* Profile */}
                <Section icon={FaUser} title="Profile" desc="This is what shows up around CareerPass.">
                    <form onSubmit={save} className="space-y-4">
                        <div className="flex gap-3">
                            <div className="w-1/2">
                                <label className="form-label">First name</label>
                                <input name="fname" value={form.fname} onChange={change} className="form-input" required />
                            </div>
                            <div className="w-1/2">
                                <label className="form-label">Last name</label>
                                <input name="lname" value={form.lname} onChange={change} className="form-input" required />
                            </div>
                        </div>
                        <div>
                            <label className="form-label">Username</label>
                            <input name="user_name" value={form.user_name} onChange={change} className="form-input" required />
                        </div>
                        <div>
                            <label className="form-label">Email</label>
                            <input name="email" type="email" value={form.email} onChange={change} className="form-input" required />
                        </div>

                        {status && (
                            <p className={`text-sm ${status.type === 'ok' ? 'text-brand-dark dark:text-brand' : 'text-red-600 dark:text-red-400'}`}>
                                {status.msg}
                            </p>
                        )}

                        <button type="submit" className="btn-primary" disabled={saving}>
                            {saving ? 'Saving…' : 'Save changes'}
                        </button>
                    </form>
                </Section>

                {/* Security */}
                <Section icon={FaLock} title="Security" desc="A password is required to keep your account secure.">
                    {passwordSet ? (
                        <div className="flex items-center gap-3 rounded-lg border border-brand/30 bg-brand/5 px-4 py-3 text-sm dark:bg-brand/10">
                            <FaCircleCheck className="shrink-0 text-brand" />
                            <span className="text-gray-700 dark:text-gray-200">
                                Your account is complete — a password is set.
                            </span>
                        </div>
                    ) : (
                        <div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm dark:border-amber-900/50 dark:bg-amber-950/30">
                            <span className="flex items-center gap-3 text-amber-800 dark:text-amber-300">
                                <FaTriangleExclamation className="shrink-0" />
                                Your account isn't finished — no password is set yet.
                            </span>
                            <Link to="/account-setup">
                                <button className="btn-primary">Set a password</button>
                            </Link>
                        </div>
                    )}
                </Section>

                {/* Plan */}
                <Section icon={FaCreditCard} title="Plan &amp; billing" desc="One-time passes — nothing renews automatically.">
                    {purchase ? (
                        <div>
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="font-medium text-gray-900 dark:text-gray-100">{purchase.plan}</p>
                                    <p className="text-sm text-gray-500 dark:text-gray-400">
                                        {daysLeft(purchase.expiresAt) != null
                                            ? `${daysLeft(purchase.expiresAt)} days of access left`
                                            : 'Active'}
                                    </p>
                                </div>
                                <Link to="/plans">
                                    <button className="btn-outline">Change plan</button>
                                </Link>
                            </div>
                            <button
                                type="button"
                                onClick={() =>
                                    setStatus({
                                        type: 'err',
                                        msg: 'Plan cancellation isn’t available here yet — contact support to cancel.',
                                    })
                                }
                                className="btn-link mt-4 text-red-600 dark:text-red-400"
                            >
                                Cancel this plan
                            </button>
                        </div>
                    ) : (
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <p className="text-sm text-gray-500 dark:text-gray-400">You don't have an active plan.</p>
                            <Link to="/plans">
                                <button className="btn-primary">Browse plans</button>
                            </Link>
                        </div>
                    )}
                </Section>

                {/* Devices */}
                <Section
                    icon={FaDesktop}
                    title="Devices"
                    desc="Signing in on a new device signs you out of the previous one."
                >
                    <div className="flex items-center justify-between rounded-lg border border-gray-200 px-4 py-3 dark:border-gray-700">
                        <div>
                            <p className="text-sm font-medium text-gray-900 dark:text-gray-100">{deviceLabel()}</p>
                            <p className="text-xs text-gray-500 dark:text-gray-400">This device · active now</p>
                        </div>
                        <span className="rounded-full bg-brand/10 px-2.5 py-0.5 text-xs font-semibold text-brand-dark dark:text-brand">
                            Current
                        </span>
                    </div>
                    <p className="mt-3 text-xs text-gray-400 dark:text-gray-500">
                        A full history of where you've signed in is on the way.
                    </p>
                </Section>
            </div>
        </div>
    );
}
