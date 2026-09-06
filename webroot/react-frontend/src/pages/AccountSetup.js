import React, { useEffect, useState } from 'react';
import InitialsAvatar from '../components/ui/InitialsAvatar';
import ThemeToggle from '../components/ui/ThemeToggle';
import { api } from '../api';

export default function AccountSetup() {
    const [form, setForm] = useState({ fname: '', lname: '', username: '', password: '', confirmPassword: '' });
    const [email, setEmail] = useState('');
    const [error, setError] = useState('');
    const [ready, setReady] = useState(false);

    useEffect(() => {
        const stored = localStorage.getItem('skillsprint_user');
        if (!stored) {
            window.location.href = '/';
            return;
        }
        try {
            const user = JSON.parse(stored);
            setForm((f) => ({
                ...f,
                fname: user.fname || '',
                lname: user.lname || '',
                username: user.user_name || '',
            }));
            setEmail(user.email || '');
            setReady(true);
        } catch {
            window.location.href = '/';
        }
    }, []);

    const handleChange = (e) => {
        setForm({ ...form, [e.target.name]: e.target.value });
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
        setError('');

        if (form.password !== form.confirmPassword) {
            setError('Passwords do not match');
            return;
        }

        try {
            const res = await api.post('/api/account/setup', {
                user_pass: form.password,
                fname: form.fname,
                lname: form.lname,
                user_name: form.username,
            });

            if (res.data.success) {
                localStorage.setItem('skillsprint_user', JSON.stringify(res.data.user));
                window.location.href = '/';
            } else {
                const errors = res.data.errors || {};
                if (errors.user_name) {
                    setError(errors.user_name[Object.keys(errors.user_name)[0]]);
                } else {
                    setError(res.data.message || 'Could not save your account. Please try again.');
                }
            }
        } catch (err) {
            setError(err.response?.data?.message || 'Could not save your account. Please try again.');
        }
    };

    if (!ready) return null;

    return (
        <div className="relative flex min-h-screen items-center justify-center bg-gray-50 px-4 py-12 dark:bg-gray-900">
            <ThemeToggle className="absolute right-4 top-4" />
            <div className="card w-full max-w-md p-6 sm:p-8">
                <div className="flex flex-col items-center text-center">
                    <InitialsAvatar fname={form.fname} lname={form.lname} email={email} size={56} />
                    <h1 className="mt-4 text-xl font-bold text-gray-900 dark:text-gray-100">Finish setting up your account</h1>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">{email}</p>
                    <p className="mt-3 text-sm text-gray-500 dark:text-gray-400">
                        You signed up with Google, so no password has been set yet. Add one now so you can also log
                        in manually next time.
                    </p>
                </div>

                {error && (
                    <div className="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-300">
                        {error}
                    </div>
                )}

                <form onSubmit={handleSubmit} className="mt-5 space-y-4">
                    <div className="flex gap-3">
                        <div className="w-1/2">
                            <label className="form-label">First Name</label>
                            <input
                                type="text"
                                name="fname"
                                value={form.fname}
                                onChange={handleChange}
                                required
                                className="form-input"
                            />
                        </div>
                        <div className="w-1/2">
                            <label className="form-label">Last Name</label>
                            <input
                                type="text"
                                name="lname"
                                value={form.lname}
                                onChange={handleChange}
                                required
                                className="form-input"
                            />
                        </div>
                    </div>

                    <div>
                        <label className="form-label">Username</label>
                        <input
                            type="text"
                            name="username"
                            value={form.username}
                            onChange={handleChange}
                            required
                            className="form-input"
                        />
                    </div>

                    <div>
                        <label className="form-label">Password</label>
                        <input
                            type="password"
                            name="password"
                            value={form.password}
                            onChange={handleChange}
                            required
                            className="form-input"
                        />
                    </div>

                    <div>
                        <label className="form-label">Re-enter Password</label>
                        <input
                            type="password"
                            name="confirmPassword"
                            value={form.confirmPassword}
                            onChange={handleChange}
                            required
                            className="form-input"
                        />
                    </div>

                    <button type="submit" className="btn-primary w-full py-2.5 text-base">
                        Save and continue
                    </button>
                </form>

                <button
                    type="button"
                    className="btn-link mt-3 w-full text-center text-gray-500 dark:text-gray-400"
                    onClick={() => { window.location.href = '/'; }}
                >
                    Set up later
                </button>
            </div>
        </div>
    );
}
