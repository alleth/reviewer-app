import React from 'react';
import { FaGoogle } from 'react-icons/fa';

const SignUp = () => {
    const handleSubmit = (e) => {
        e.preventDefault();
        alert('Account created!');
    };

    const handleGoogleSignUp = () => {
        alert('Redirecting to Google sign-up...');
    };

    return (
        <div className="min-h-screen bg-gray-50 pt-20">
            <div className="mx-auto max-w-md px-4">
                <form onSubmit={handleSubmit} className="card space-y-4 p-6">
                    <h4 className="text-center text-lg font-bold text-gray-900">Create Your Account</h4>

                    <div>
                        <label className="form-label">Full Name</label>
                        <input type="text" placeholder="Juan Dela Cruz" required className="form-input" />
                    </div>

                    <div>
                        <label className="form-label">Email</label>
                        <input type="email" placeholder="you@example.com" required className="form-input" />
                    </div>

                    <div>
                        <label className="form-label">Username</label>
                        <input type="text" placeholder="Choose a username" required className="form-input" />
                    </div>

                    <div>
                        <label className="form-label">Password</label>
                        <input type="password" placeholder="Enter password" required className="form-input" />
                    </div>

                    <button type="submit" className="btn-primary w-full">Sign Up</button>

                    <div className="flex items-center gap-3 text-center text-sm text-gray-400">
                        <div className="h-px flex-1 bg-gray-200" />
                        or
                        <div className="h-px flex-1 bg-gray-200" />
                    </div>

                    <button
                        type="button"
                        onClick={handleGoogleSignUp}
                        className="btn-outline w-full border-gray-400 text-gray-500 transition-colors hover:bg-gray-500 hover:text-gray-50"
                    >
                        <FaGoogle className="mr-2" />
                        Continue with Google
                    </button>

                    <div className="text-center text-sm text-gray-500">
                        I accept the SkillSprint's{' '}
                        <a href="/termsofuse" className="text-brand">Terms of Use</a> and{' '}
                        <a href="/signup" className="text-brand">Privacy Notice</a>
                    </div>
                </form>
            </div>
        </div>
    );
};

export default SignUp;
