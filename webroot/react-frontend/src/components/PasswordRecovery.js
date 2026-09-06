import React, { useState } from 'react';
import { api } from '../api';

/**
 * "Forgot password" flow: email → emailed 6-digit code + new password → done.
 * Rendered by LoginPage in place of the sign-in form.
 */
export default function PasswordRecovery({ initialEmail = '', onBackToLogin }) {
    const [step, setStep] = useState('email'); // 'email' | 'code' | 'done'
    const [email, setEmail] = useState(initialEmail);
    const [code, setCode] = useState('');
    const [password, setPassword] = useState('');
    const [confirm, setConfirm] = useState('');
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);

    const requestCode = async (e) => {
        e.preventDefault();
        setBusy(true);
        setError('');
        try {
            await api.post('/api/password/forgot', { email });
            setStep('code');
        } catch (err) {
            setError('Could not reach the server. Please try again.');
        } finally {
            setBusy(false);
        }
    };

    const submitReset = async (e) => {
        e.preventDefault();
        if (password !== confirm) {
            setError('Passwords do not match.');
            return;
        }
        if (password.length < 8) {
            setError('Password must be at least 8 characters.');
            return;
        }
        setBusy(true);
        setError('');
        try {
            const res = await api.post('/api/password/reset', { email, code, password });
            if (res.data.success) {
                setStep('done');
            } else {
                setError(res.data.message || 'Could not reset your password.');
            }
        } catch (err) {
            setError(err.response?.data?.message || 'Could not reset your password.');
        } finally {
            setBusy(false);
        }
    };

    return (
        <div className="p-2">
            <h4 className="mb-1 text-center text-xl font-bold text-gray-900 dark:text-gray-100">
                Reset your password
            </h4>
            <p className="mb-4 text-center text-sm text-gray-500 dark:text-gray-400">
                {step === 'email' && "Enter your email and we'll send you a code."}
                {step === 'code' && `Enter the code sent to ${email} and choose a new password.`}
                {step === 'done' && 'All set.'}
            </p>

            {error && (
                <div className="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-300">
                    {error}
                </div>
            )}

            {step === 'email' && (
                <form onSubmit={requestCode} className="space-y-4">
                    <div>
                        <label className="form-label">Email</label>
                        <input
                            type="email"
                            value={email}
                            onChange={(e) => setEmail(e.target.value)}
                            required
                            className="form-input"
                        />
                    </div>
                    <button type="submit" className="btn-primary w-full py-2.5 text-base" disabled={busy}>
                        {busy ? 'Sending…' : 'Send code'}
                    </button>
                </form>
            )}

            {step === 'code' && (
                <form onSubmit={submitReset} className="space-y-4">
                    <div>
                        <label className="form-label">6-digit code</label>
                        <input
                            inputMode="numeric"
                            pattern="[0-9]*"
                            maxLength={6}
                            value={code}
                            onChange={(e) => setCode(e.target.value.replace(/\D/g, ''))}
                            required
                            className="form-input tracking-[0.4em]"
                        />
                    </div>
                    <div>
                        <label className="form-label">New password</label>
                        <input
                            type="password"
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                            required
                            className="form-input"
                        />
                    </div>
                    <div>
                        <label className="form-label">Re-enter new password</label>
                        <input
                            type="password"
                            value={confirm}
                            onChange={(e) => setConfirm(e.target.value)}
                            required
                            className="form-input"
                        />
                    </div>
                    <button type="submit" className="btn-primary w-full py-2.5 text-base" disabled={busy}>
                        {busy ? 'Saving…' : 'Reset password'}
                    </button>
                    <button
                        type="button"
                        className="btn-link w-full text-center text-gray-500 dark:text-gray-400"
                        onClick={requestCode}
                        disabled={busy}
                    >
                        Resend code
                    </button>
                </form>
            )}

            {step === 'done' && (
                <div className="text-center">
                    <p className="text-sm text-gray-600 dark:text-gray-300">
                        Your password has been updated. You can sign in with it now.
                    </p>
                    <button className="btn-primary mt-4 w-full py-2.5 text-base" onClick={onBackToLogin}>
                        Back to sign in
                    </button>
                </div>
            )}

            {step !== 'done' && (
                <button
                    type="button"
                    className="btn-link mt-4 w-full text-center text-gray-500 dark:text-gray-400"
                    onClick={onBackToLogin}
                >
                    Back to sign in
                </button>
            )}
        </div>
    );
}
