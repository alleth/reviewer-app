import React, { useState } from 'react';
import { GoogleLogin } from '@react-oauth/google';
import Modal from './ui/Modal';
import API_URL from '../api';

const SignInModal = () => {
    const [showLogin, setShowLogin] = useState(false);
    const [showForgotPassword, setShowForgotPassword] = useState(false);
    const [username, setUsername] = useState('');
    const [password, setPassword] = useState('');

    const handleLoginClose = () => setShowLogin(false);
    const handleLoginShow = () => setShowLogin(true);

    const handleForgotClose = () => setShowForgotPassword(false);
    const handleForgotShow = () => {
        setShowLogin(false);
        setShowForgotPassword(true);
    };

    const handleSubmit = async (e) => {
        e.preventDefault();

        try {
            const res = await fetch(`${API_URL}/api/login`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({ user_name: username, user_pass: password })
            });

            const data = await res.json();

            if (data.success) {
                localStorage.setItem('isLoggedIn', 'true');
                handleLoginClose();
                window.location.reload(); // ⬅️ Refresh to let CakePHP load Dashboard.js
            } else {
                alert(data.message || 'Login failed');
            }
        } catch (error) {
            console.error('Login error:', error);
            alert('Error connecting to server.');
        }
    };

    const handleGoogleSuccess = async (credentialResponse) => {
        try {
            const res = await fetch(`${API_URL}/api/google-login`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include',
                body: JSON.stringify({ credential: credentialResponse.credential })
            });

            const data = await res.json();

            if (data.success) {
                localStorage.setItem('skillsprint_user', JSON.stringify(data.user));
                handleLoginClose();
                window.location.href = data.isNewUser ? '/account-setup' : '/';
            } else {
                alert(data.message || 'Google sign-in failed');
            }
        } catch (error) {
            console.error('Google sign-in error:', error);
            alert('Error connecting to server.');
        }
    };

    const handleForgotSubmit = (e) => {
        e.preventDefault();
        alert('Reset instructions sent.');
        handleForgotClose();
    };

    return (
        <>
            <button type="button" className="btn-outline" onClick={handleLoginShow}>
                Sign In
            </button>

            {/* Sign In Modal */}
            <Modal show={showLogin} onClose={handleLoginClose}>
                <h4 className="mb-4 text-lg font-bold text-gray-900">Sign In</h4>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div>
                        <label className="form-label">Username</label>
                        <input
                            type="text"
                            placeholder="Enter your username"
                            required
                            value={username}
                            onChange={(e) => setUsername(e.target.value)}
                            className="form-input"
                        />
                    </div>

                    <div>
                        <label className="form-label">Password</label>
                        <input
                            type="password"
                            placeholder="Enter your password"
                            required
                            value={password}
                            onChange={(e) => setPassword(e.target.value)}
                            className="form-input"
                        />
                    </div>

                    <div className="flex items-center justify-between">
                        <button type="button" onClick={handleForgotShow} className="btn-link underline">
                            Forgot password?
                        </button>
                        <span className="text-sm text-gray-500">
                            No account? <a href="#signup" className="text-brand">Sign up</a>
                        </span>
                    </div>

                    <button type="submit" className="btn-primary w-full">Log In</button>

                    <div className="flex items-center gap-3 text-center text-sm text-gray-400">
                        <div className="h-px flex-1 bg-gray-200" />
                        or
                        <div className="h-px flex-1 bg-gray-200" />
                    </div>

                    <div className="flex justify-center">
                        <GoogleLogin
                            onSuccess={handleGoogleSuccess}
                            onError={() => alert('Google sign-in was cancelled.')}
                            width="320"
                        />
                    </div>
                </form>
            </Modal>

            {/* Forgot Password Modal */}
            <Modal show={showForgotPassword} onClose={handleForgotClose}>
                <h4 className="mb-4 text-lg font-bold text-gray-900">Reset Password</h4>
                <form onSubmit={handleForgotSubmit} className="space-y-4">
                    <div>
                        <label className="form-label">We'll send reset instructions to:</label>
                        <input type="email" placeholder="you@example.com" required className="form-input" />
                    </div>
                    <button type="submit" className="btn-primary w-full">Send Reset Link</button>
                </form>
            </Modal>
        </>
    );
};

export default SignInModal;
