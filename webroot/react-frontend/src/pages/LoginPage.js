import React, { useState } from 'react';
import { GoogleLogin } from '@react-oauth/google';
import InitialsAvatar from '../components/ui/InitialsAvatar';
import { api } from '../api';

function LoginPage({ mode = 'login', onClose }) {
    const [formData, setFormData] = useState({
        fname: '',
        lname: '',
        email: '',
        username: '',
        password: '',
        confirmPassword: ''
    });
    const [error, setError] = useState('');
    // Set when login() reports the matched account has no password yet (Google-only
    // account) — swaps the form for a personalized "continue with Google" prompt.
    const [pendingAccount, setPendingAccount] = useState(null);

    const handleChange = (e) => {
        setFormData({ ...formData, [e.target.name]: e.target.value });
    };

    const handleLogin = async (e) => {
        e.preventDefault();
        try {
            const res = await api.post('/api/login', {
                user_name: formData.username,
                user_pass: formData.password
            });

            if (res.data.success) {
                localStorage.setItem('skillsprint_user', JSON.stringify(res.data.user));
                window.location.href = '/';
            } else if (res.data.needsGoogleSetup) {
                setPendingAccount(res.data.account);
                setError('');
            } else {
                setError(res.data.message || 'Invalid credentials');
            }
        } catch (err) {
            setError(err.response?.data?.message || err.response?.data?.error || 'Login failed');
        }
    };

    const handleSignup = async (e) => {
        e.preventDefault();

        if (formData.password !== formData.confirmPassword) {
            setError('Passwords do not match');
            return;
        }

        try {
            const res = await api.post('/api/register', {
                fname: formData.fname,
                lname: formData.lname,
                email: formData.email,
                user_name: formData.username,
                user_pass: formData.password
            });

            if (res.data.success) {
                localStorage.setItem('skillsprint_user', JSON.stringify(res.data.user));
                window.location.href = '/';
            } else {
                const errors = res.data.errors || {};
                if (errors.email) {
                    setError(errors.email[Object.keys(errors.email)[0]]);
                } else if (errors.user_name) {
                    setError(errors.user_name[Object.keys(errors.user_name)[0]]);
                } else {
                    setError('Registration failed. Please check your details.');
                }
            }
        } catch (err) {
            setError('Could not connect to server. Please try again.');
        }
    };

    const handleGoogleSuccess = async (credentialResponse) => {
        try {
            const res = await api.post('/api/google-login', {
                credential: credentialResponse.credential,
            });

            if (res.data.success) {
                localStorage.setItem('skillsprint_user', JSON.stringify(res.data.user));
                // Brand-new Google sign-up: give them the option to set a password now,
                // instead of only finding out later when a manual login attempt fails.
                window.location.href = res.data.isNewUser ? '/account-setup' : '/';
            } else {
                setError(res.data.message || 'Google sign-in failed');
            }
        } catch (err) {
            setError('Google sign-in failed. Please try again.');
        }
    };

    const handleGoogleError = () => {
        setError('Google sign-in was cancelled or failed.');
    };

    // Used only from the "this account needs setup" prompt below — always sends the
    // user to finish setting a password instead of straight to the dashboard.
    const handleGoogleSetupSuccess = async (credentialResponse) => {
        try {
            const res = await api.post('/api/google-login', {
                credential: credentialResponse.credential,
            });

            if (res.data.success) {
                localStorage.setItem('skillsprint_user', JSON.stringify(res.data.user));
                window.location.href = '/account-setup';
            } else {
                setError(res.data.message || 'Google sign-in failed');
            }
        } catch (err) {
            setError('Google sign-in failed. Please try again.');
        }
    };

    if (pendingAccount) {
        return (
            <div className="p-2 text-center">
                <InitialsAvatar
                    fname={pendingAccount.fname}
                    lname={pendingAccount.lname}
                    email={pendingAccount.email}
                    size={56}
                    className="mx-auto"
                />
                <h4 className="mt-4 text-lg font-bold text-gray-900">
                    {[pendingAccount.fname, pendingAccount.lname].filter(Boolean).join(' ') || pendingAccount.email}
                </h4>
                <p className="text-sm text-gray-500">{pendingAccount.email}</p>

                <div className="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                    For your security, this account needs a one-time setup. Continue with Google to finish it.
                </div>

                {error && (
                    <div className="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                        {error}
                    </div>
                )}

                <div className="mt-5 flex justify-center">
                    <GoogleLogin onSuccess={handleGoogleSetupSuccess} onError={handleGoogleError} width="320" />
                </div>

                <button
                    type="button"
                    className="btn-link mt-4 w-full text-center text-gray-500"
                    onClick={() => { setPendingAccount(null); setError(''); }}
                >
                    Use a different account
                </button>
            </div>
        );
    }

    return (
        <div className="p-2">
            <h4 className="mb-4 text-center text-xl font-bold text-gray-900">
                {mode === 'signup' ? 'Create an Account' : 'Welcome Back'}
            </h4>

            {error && (
                <div className="mb-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                    {error}
                </div>
            )}

            <form onSubmit={mode === 'signup' ? handleSignup : handleLogin} className="space-y-4">
                {mode === 'signup' && (
                    <>
                        <div className="flex gap-3">
                            <div className="w-1/2">
                                <label className="form-label">First Name</label>
                                <input
                                    type="text"
                                    name="fname"
                                    value={formData.fname}
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
                                    value={formData.lname}
                                    onChange={handleChange}
                                    required
                                    className="form-input"
                                />
                            </div>
                        </div>

                        <div>
                            <label className="form-label">Email</label>
                            <input
                                type="email"
                                name="email"
                                value={formData.email}
                                onChange={handleChange}
                                required
                                className="form-input"
                            />
                        </div>
                    </>
                )}

                <div>
                    <label className="form-label">
                        {mode === 'signup' ? 'Username' : 'Username or Email'}
                    </label>
                    <input
                        type="text"
                        name="username"
                        value={formData.username}
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
                        value={formData.password}
                        onChange={handleChange}
                        required
                        className="form-input"
                    />
                </div>

                {mode === 'signup' && (
                    <div>
                        <label className="form-label">Re-enter Password</label>
                        <input
                            type="password"
                            name="confirmPassword"
                            value={formData.confirmPassword}
                            onChange={handleChange}
                            required
                            className="form-input"
                        />
                    </div>
                )}

                {mode === 'login' && (
                    <div className="text-right">
                        <button
                            type="button"
                            className="btn-link"
                            onClick={() => alert('Redirect to password recovery')}
                        >
                            Forgot Password?
                        </button>
                    </div>
                )}

                <button type="submit" className="btn-primary w-full py-2.5 text-base">
                    {mode === 'signup' ? 'Sign Up' : 'Login'}
                </button>
            </form>

            <div className="my-4 flex items-center gap-3 text-xs text-gray-400">
                <div className="h-px flex-1 bg-gray-200" />
                OR
                <div className="h-px flex-1 bg-gray-200" />
            </div>

            <div className="flex justify-center">
                <GoogleLogin
                    onSuccess={handleGoogleSuccess}
                    onError={handleGoogleError}
                    text={mode === 'signup' ? 'signup_with' : 'continue_with'}
                    width="320"
                />
            </div>

            {mode === 'signup' && (
                <div className="mt-4 text-center text-xs text-gray-500">
                    By signing up, you agree to our{' '}
                    <a href="/terms" className="text-brand hover:text-brand-dark">Terms Policy</a> and{' '}
                    <a href="/privacy" className="text-brand hover:text-brand-dark">Privacy Notice</a>.
                </div>
            )}

            {onClose && (
                <button type="button" className="btn-link mt-4 w-full text-center text-gray-500" onClick={onClose}>
                    Cancel
                </button>
            )}
        </div>
    );
}

export default LoginPage;
