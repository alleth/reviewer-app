import React, { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { FaArrowLeft, FaPrint } from 'react-icons/fa6';
import Logo from '../components/ui/Logo';
import { fetchBillingHistory } from '../api';
import { COMPANY, COMPANY_ADDRESS, CONTACT_EMAIL } from '../legal';

const peso = (amount, currency = 'PHP') => {
    const n = Number(amount);
    return `${currency === 'PHP' ? '₱' : ''}${n.toLocaleString('en-PH', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`;
};

const fmtDate = (iso) => {
    if (!iso) return '—';
    try {
        return new Date(iso).toLocaleString('en-PH', {
            year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit',
        });
    } catch (e) {
        return iso;
    }
};

/**
 * A printable payment receipt for one pass. Not a BIR Official Receipt — an
 * honest record of the transaction the buyer can print or save as PDF. Routed
 * full-bleed (outside the dashboard shell) so it prints clean.
 */
export default function Receipt({ user }) {
    const { reference } = useParams();
    const [row, setRow] = useState(undefined); // undefined = loading, null = not found

    useEffect(() => {
        let cancelled = false;
        fetchBillingHistory()
            .then((list) => {
                if (cancelled) return;
                setRow(list.find((p) => p.reference === reference) || null);
            })
            .catch(() => { if (!cancelled) setRow(null); });
        return () => { cancelled = true; };
    }, [reference]);

    const buyerName = [user?.fname, user?.lname].filter(Boolean).join(' ') || user?.user_name || '—';

    return (
        <div className="min-h-screen bg-gray-50 py-8 dark:bg-gray-900 print:bg-white print:py-0">
            <div className="mx-auto max-w-lg px-4">
                <div className="mb-4 flex items-center justify-between print:hidden">
                    <Link to="/settings" className="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-brand dark:text-gray-400">
                        <FaArrowLeft size={11} /> Settings
                    </Link>
                    {row && (
                        <button type="button" onClick={() => window.print()} className="btn-outline inline-flex items-center gap-2">
                            <FaPrint size={13} /> Print / Save PDF
                        </button>
                    )}
                </div>

                {row === undefined && (
                    <div className="card p-8 text-center text-sm text-gray-500 dark:text-gray-400">Loading…</div>
                )}
                {row === null && (
                    <div className="card p-8 text-center">
                        <p className="font-semibold text-gray-900 dark:text-gray-100">Receipt not found</p>
                        <Link to="/settings" className="btn-primary mt-4 inline-block">Back to Settings</Link>
                    </div>
                )}

                {row && (
                    <div className="card p-6 sm:p-8 print:border-0 print:shadow-none">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <Logo size={28} />
                                <span className="text-lg font-bold tracking-tight" style={{ color: '#00C4A7' }}>CareerPass</span>
                            </div>
                            <div className="text-right">
                                <p className="text-sm font-semibold text-gray-900 dark:text-gray-100">Payment receipt</p>
                                <p className="text-xs text-gray-500 dark:text-gray-400">
                                    {row.status === 'pending' ? 'Awaiting payment' : 'Paid'}
                                </p>
                            </div>
                        </div>

                        <div className="mt-6 grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">From</p>
                                <p className="mt-1 font-medium text-gray-900 dark:text-gray-100">{COMPANY}</p>
                                <p className="text-gray-500 dark:text-gray-400">{COMPANY_ADDRESS}</p>
                                <p className="text-gray-500 dark:text-gray-400">{CONTACT_EMAIL}</p>
                            </div>
                            <div>
                                <p className="text-xs font-semibold uppercase tracking-wide text-gray-400 dark:text-gray-500">Billed to</p>
                                <p className="mt-1 font-medium text-gray-900 dark:text-gray-100">{buyerName}</p>
                                <p className="text-gray-500 dark:text-gray-400">{user?.email || '—'}</p>
                            </div>
                        </div>

                        <div className="mt-6 border-y border-gray-200 py-4 dark:border-gray-700">
                            <div className="flex items-start justify-between text-sm">
                                <div>
                                    <p className="font-medium text-gray-900 dark:text-gray-100">
                                        CareerPass — {row.plan}
                                    </p>
                                    <p className="text-gray-500 dark:text-gray-400">
                                        Civil Service Exam Reviewer{row.days ? ` · ${row.days} days access` : ''}
                                    </p>
                                </div>
                                <p className="font-medium text-gray-900 dark:text-gray-100">{peso(row.amount, row.currency)}</p>
                            </div>
                        </div>

                        <div className="mt-4 flex items-center justify-between">
                            <p className="text-sm font-semibold text-gray-900 dark:text-gray-100">Total paid</p>
                            <p className="text-lg font-bold text-gray-900 dark:text-gray-100">
                                {peso(row.amount, row.currency)} {row.currency}
                            </p>
                        </div>

                        <dl className="mt-6 space-y-1.5 text-xs text-gray-500 dark:text-gray-400">
                            <div className="flex justify-between"><dt>Date paid</dt><dd>{fmtDate(row.purchasedAt)}</dd></div>
                            <div className="flex justify-between"><dt>Access until</dt><dd>{fmtDate(row.expiresAt)}</dd></div>
                            <div className="flex justify-between"><dt>Reference</dt><dd className="font-mono">{row.reference}</dd></div>
                            {row.invoiceId && (
                                <div className="flex justify-between"><dt>Xendit invoice</dt><dd className="font-mono">{row.invoiceId}</dd></div>
                            )}
                            <div className="flex justify-between"><dt>Payment processor</dt><dd>Xendit</dd></div>
                        </dl>

                        <p className="mt-6 text-xs text-gray-400 dark:text-gray-500">
                            This is a payment confirmation, not a BIR Official Receipt. If you need an
                            official receipt for reimbursement, email {CONTACT_EMAIL} with this reference.
                        </p>
                    </div>
                )}
            </div>
        </div>
    );
}
