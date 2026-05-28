import React, { useState } from 'react';
import axios from 'axios';
import { Form, Button, Alert } from 'react-bootstrap';
import { FcGoogle } from 'react-icons/fc';

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

    const handleChange = (e) => {
        setFormData({ ...formData, [e.target.name]: e.target.value });
    };

    const handleLogin = async (e) => {
        e.preventDefault();
        try {
            const res = await axios.post('http://localhost/reviewer_app/api/login', {
                username: formData.username,
                password: formData.password
            }, { withCredentials: true });

            if (res.data.success) {
                window.location.href = '/pages';
            } else {
                setError('Invalid credentials');
            }
        } catch (err) {
            setError('Login failed');
        }
    };

    const handleSignup = async (e) => {
        e.preventDefault();

        if (formData.password !== formData.confirmPassword) {
            setError('Passwords do not match');
            return;
        }

        try {
            const res = await axios.post('http://localhost/reviewer_app/api/register', {
                fname: formData.fname,
                lname: formData.lname,
                email: formData.email,
                password: formData.password
            });

            if (res.data.success) {
                window.location.href = '/pages';
            } else {
                setError('Registration failed');
            }
        } catch (err) {
            setError('Error during registration');
        }
    };

    const handleGoogleAuth = () => {
        alert('Google authentication not yet implemented.');
    };

    return (
        <div
            style={{
                padding: '2rem',
                borderRadius: '1rem',
                boxShadow: '0 0 0 rgba(0,0,0,0.05)',
                color: '#111827'
            }}
        >
            <h4 className="mb-3 text-center fw-bold" style={{ color: '#111827' }}>
                {mode === 'signup' ? 'Create an Account' : 'Welcome Back'}
            </h4>

            {error && (
                <Alert variant="danger" className="py-2 px-3" style={{ fontSize: '0.9rem', borderRadius: '0.5rem' }}>
                    {error}
                </Alert>
            )}

            <Form onSubmit={mode === 'signup' ? handleSignup : handleLogin}>
                {mode === 'signup' && (
                    <>
                        <div className="d-flex gap-2">
                            <Form.Group className="mb-3 w-50">
                                <Form.Label style={{ color: '#6B7280', fontSize: '0.9rem' }}>First Name</Form.Label>
                                <Form.Control
                                    type="text"
                                    name="fname"
                                    value={formData.fname}
                                    onChange={handleChange}
                                    required
                                    style={{ borderRadius: '0.5rem', fontSize: '0.95rem' }}
                                />
                            </Form.Group>
                            <Form.Group className="mb-3 w-50">
                                <Form.Label style={{ color: '#6B7280', fontSize: '0.9rem' }}>Last Name</Form.Label>
                                <Form.Control
                                    type="text"
                                    name="lname"
                                    value={formData.lname}
                                    onChange={handleChange}
                                    required
                                    style={{ borderRadius: '0.5rem', fontSize: '0.95rem' }}
                                />
                            </Form.Group>
                        </div>

                        <Form.Group className="mb-3">
                            <Form.Label style={{ color: '#6B7280', fontSize: '0.9rem' }}>Email</Form.Label>
                            <Form.Control
                                type="email"
                                name="email"
                                value={formData.email}
                                onChange={handleChange}
                                required
                                style={{ borderRadius: '0.5rem', fontSize: '0.95rem' }}
                            />
                        </Form.Group>
                    </>
                )}

                <Form.Group className="mb-3">
                    <Form.Label style={{ color: '#6B7280', fontSize: '0.9rem' }}>
                        {mode === 'signup' ? 'Username' : 'Username or Email'}
                    </Form.Label>
                    <Form.Control
                        type="text"
                        name="username"
                        value={formData.username}
                        onChange={handleChange}
                        required
                        style={{ borderRadius: '0.5rem', fontSize: '0.95rem' }}
                    />
                </Form.Group>

                <Form.Group className="mb-3">
                    <Form.Label style={{ color: '#6B7280', fontSize: '0.9rem' }}>Password</Form.Label>
                    <Form.Control
                        type="password"
                        name="password"
                        value={formData.password}
                        onChange={handleChange}
                        required
                        style={{ borderRadius: '0.5rem', fontSize: '0.95rem' }}
                    />
                </Form.Group>

                {mode === 'signup' && (
                    <Form.Group className="mb-3">
                        <Form.Label style={{ color: '#6B7280', fontSize: '0.9rem' }}>Re-enter Password</Form.Label>
                        <Form.Control
                            type="password"
                            name="confirmPassword"
                            value={formData.confirmPassword}
                            onChange={handleChange}
                            required
                            style={{ borderRadius: '0.5rem', fontSize: '0.95rem' }}
                        />
                    </Form.Group>
                )}

                {mode === 'login' && (
                    <div className="text-end mb-3">
                        <Button
                            variant="link"
                            style={{
                                color: '#14B8A6',
                                fontSize: '0.85rem',
                                textDecoration: 'none',
                                padding: 0
                            }}
                            onClick={() => alert('Redirect to password recovery')}
                        >
                            Forgot Password?
                        </Button>
                    </div>
                )}

                <Button
                    type="submit"
                    style={{
                        backgroundColor: '#14B8A6',
                        border: 'none',
                        borderRadius: '0.5rem',
                        width: '100%',
                        fontSize: '1rem'
                    }}
                >
                    {mode === 'signup' ? 'Sign Up' : 'Login'}
                </Button>
            </Form>

            <div className="text-center mt-3 mb-2" style={{ fontSize: '0.85rem', color: '#6B7280' }}>
                OR
            </div>

            <Button
                variant="outline-dark"
                onClick={handleGoogleAuth}
                style={{
                    borderRadius: '0.5rem',
                    width: '100%',
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'center',
                    fontSize: '0.95rem',
                    borderColor: '#D1D5DB'
                }}
            >
                <FcGoogle style={{ marginRight: '8px', fontSize: '1.2rem' }} />
                Continue with Google
            </Button>

            {mode === 'signup' && (
                <div className="text-center mt-4" style={{ fontSize: '0.75rem', color: '#6B7280' }}>
                    By signing up, you agree to our{' '}
                    <a href="/terms" style={{ color: '#14B8A6', textDecoration: 'none' }}>Terms Policy</a> and{' '}
                    <a href="/privacy" style={{ color: '#14B8A6', textDecoration: 'none' }}>Privacy Notice</a>.
                </div>
            )}

            {onClose && (
                <Button
                    variant="link"
                    className="mt-3 w-100 text-center"
                    onClick={onClose}
                    style={{
                        color: '#6B7280',
                        textDecoration: 'none',
                        fontSize: '0.9rem'
                    }}
                >
                    Cancel
                </Button>
            )}
        </div>
    );
}

export default LoginPage;
