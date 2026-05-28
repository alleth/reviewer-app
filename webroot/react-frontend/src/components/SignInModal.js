import React, { useState } from 'react';
import { Modal, Button, Form } from 'react-bootstrap';
import { FaGoogle } from 'react-icons/fa';

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
            const res = await fetch('http://localhost/reviewer_app/api/login/login', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'include', // ⬅️ Important for session cookies
                body: JSON.stringify({ username, password })
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

    const handleGoogleLogin = () => {
        alert('Redirecting to Google login...');
    };

    const handleForgotSubmit = (e) => {
        e.preventDefault();
        alert('Reset instructions sent.');
        handleForgotClose();
    };

    return (
        <>
            <Button
                variant="outline-dark"
                className="me-2"
                onClick={handleLoginShow}
            >
                Sign In
            </Button>

            {/* Sign In Modal */}
            <Modal show={showLogin} onHide={handleLoginClose} centered>
                <Modal.Header closeButton style={{ backgroundColor: '#F9FAFB' }}>
                    <Modal.Title style={{ color: '#111827' }}>Sign In</Modal.Title>
                </Modal.Header>
                <Modal.Body style={{ backgroundColor: '#F9FAFB' }}>
                    <Form onSubmit={handleSubmit}>
                        <Form.Group controlId="formUsername" className="mb-3">
                            <Form.Label style={{ color: '#6B7280' }}>Username</Form.Label>
                            <Form.Control
                                type="text"
                                placeholder="Enter your username"
                                required
                                value={username}
                                onChange={(e) => setUsername(e.target.value)}
                                style={{ backgroundColor: '#fff', borderColor: '#14B8A6' }}
                            />
                        </Form.Group>

                        <Form.Group controlId="formPassword" className="mb-4">
                            <Form.Label style={{ color: '#6B7280' }}>Password</Form.Label>
                            <Form.Control
                                type="password"
                                placeholder="Enter your password"
                                required
                                value={password}
                                onChange={(e) => setPassword(e.target.value)}
                                style={{ backgroundColor: '#fff', borderColor: '#14B8A6' }}
                            />
                        </Form.Group>

                        <div className="d-flex justify-content-between align-items-center mb-4">
                            <button
                                type="button"
                                onClick={handleForgotShow}
                                className="btn btn-link p-0"
                                style={{ textDecoration: 'underline', color: '#14B8A6' }}
                            >
                                Forgot password?
                            </button>
                            <span style={{ color: '#6B7280', fontSize: '0.9rem' }}>
                                No account? <a href="#signup" style={{ color: '#14B8A6' }}>Sign up</a>
                            </span>
                        </div>

                        <Button
                            type="submit"
                            className="w-100 mb-3"
                            style={{ backgroundColor: '#14B8A6', borderColor: '#14B8A6' }}
                        >
                            Log In
                        </Button>

                        <div className="text-center text-muted mb-3">or</div>

                        <Button
                            className="w-100"
                            onClick={handleGoogleLogin}
                            style={{
                                borderColor: '#6B7280',
                                color: '#6B7280',
                                backgroundColor: '#F9FAFB',
                                transition: 'all 0.2s ease-in-out',
                            }}
                            onMouseEnter={e => {
                                e.target.style.backgroundColor = '#6B7280';
                                e.target.style.color = '#F9FAFB';
                            }}
                            onMouseLeave={e => {
                                e.target.style.backgroundColor = '#F9FAFB';
                                e.target.style.color = '#6B7280';
                            }}
                        >
                            <FaGoogle className="me-2" />
                            Continue with Google
                        </Button>
                    </Form>
                </Modal.Body>
            </Modal>

            {/* Forgot Password Modal */}
            <Modal show={showForgotPassword} onHide={handleForgotClose} centered>
                <Modal.Header closeButton style={{ backgroundColor: '#F9FAFB' }}>
                    <Modal.Title style={{ color: '#111827' }}>Reset Password</Modal.Title>
                </Modal.Header>
                <Modal.Body style={{ backgroundColor: '#F9FAFB' }}>
                    <Form onSubmit={handleForgotSubmit}>
                        <Form.Group controlId="formResetEmail" className="mb-4">
                            <Form.Label style={{ color: '#6B7280' }}>
                                We'll send reset instructions to:
                            </Form.Label>
                            <Form.Control
                                type="email"
                                placeholder="you@example.com"
                                required
                                style={{ backgroundColor: '#fff', borderColor: '#14B8A6' }}
                            />
                        </Form.Group>
                        <Button
                            type="submit"
                            className="w-100"
                            style={{ backgroundColor: '#14B8A6', borderColor: '#14B8A6' }}
                        >
                            Send Reset Link
                        </Button>
                    </Form>
                </Modal.Body>
            </Modal>
        </>
    );
};

export default SignInModal;
