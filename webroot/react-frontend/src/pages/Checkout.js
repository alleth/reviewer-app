import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { FaCircleCheck, FaRegClock } from 'react-icons/fa6';
import { fetchPurchases } from '../api';

const STORAGE_KEY = 'skillsprint_user';
const REVIEWER = 'civil-service';

/** Merge the freshly-fetched passes into the stored user so the whole app sees them. */
function persistPurchases(purchases) {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) return;
        const user = JSON.parse(raw);
        user.purchases = purchases;
        localStorage.setItem(STORAGE_KEY, JSON.stringify(user));
    } catch (e) {
        /* ignore */
    }
}

/**
 * Landing page after Xendit redirects back from a completed payment. The pass is
 * granted by the webhook, which usually beats the redirect but can lag a few
 * seconds — so poll /api/purchases briefly before sending the user in.
 */
export function CheckoutSuccess() {
    const [state, setState] = useState('checking'); // checking | ready | slow

    useEffect(() => {
        let cancelled = false;
        let tries = 0;

        const poll = async () => {
            tries += 1;
            try {
                const purchases = await fetchPurchases();
                if (cancelled) return;
                if (purchases.some((p) => p.reviewer === REVIEWER)) {
                    persistPurchases(purchases);
                    setState('ready');
                    setTimeout(() => window.location.assign(`/review/${REVIEWER}`), 800);
                    return;
                }
            } catch (e) {
                /* keep trying */
            }
            if (cancelled) return;
            if (tries >= 10) {
                setState('slow');
                return;
            }
            setTimeout(poll, 2000);
        };

        poll();
        return () => { cancelled = true; };
    }, []);

    return (
        <div className="mx-auto mt-10 max-w-md">
            <div className="card flex flex-col items-center p-8 text-center">
                {state === 'slow' ? (
                    <>
                        <span className="flex h-12 w-12 items-center justify-center rounded-full bg-brand/10 text-brand">
                            <FaRegClock size={20} />
                        </span>
                        <p className="mt-4 font-semibold text-gray-900 dark:text-gray-100">Payment received</p>
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Your access is being activated — this usually takes a few seconds. It'll appear in My
                            Library shortly.
                        </p>
                        <Link to="/library" className="btn-primary mt-5">Go to My Library</Link>
                    </>
                ) : (
                    <>
                        <span className="flex h-12 w-12 items-center justify-center rounded-full bg-brand/10 text-brand">
                            <FaCircleCheck size={22} />
                        </span>
                        <p className="mt-4 font-semibold text-gray-900 dark:text-gray-100">
                            {state === 'ready' ? "You're in — opening the reviewer…" : 'Confirming your payment…'}
                        </p>
                        <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">Just a moment.</p>
                        <div className="mt-5 h-[3px] w-40 overflow-hidden rounded-full bg-brand/15 dark:bg-brand/25">
                            <div className="h-full w-1/4 rounded-full bg-brand animate-loader-sweep motion-reduce:hidden" />
                        </div>
                    </>
                )}
            </div>
        </div>
    );
}

/** Landing page when the user backs out of the Xendit checkout. */
export function CheckoutCancel() {
    return (
        <div className="mx-auto mt-10 max-w-md">
            <div className="card flex flex-col items-center p-8 text-center">
                <p className="font-semibold text-gray-900 dark:text-gray-100">Checkout cancelled</p>
                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    No payment was made. You can pick a pass whenever you're ready.
                </p>
                <div className="mt-5 flex gap-2">
                    <Link to="/plans" className="btn-primary">Back to plans</Link>
                    <Link to="/library" className="btn-outline">My Library</Link>
                </div>
            </div>
        </div>
    );
}
