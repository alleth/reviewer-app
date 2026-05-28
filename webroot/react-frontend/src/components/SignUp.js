import React from 'react';
import { Container, Row, Col, Form, Button } from 'react-bootstrap';
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
        <div style={{ backgroundColor: '#F9FAFB', minHeight: '100vh', paddingTop: '80px' }}>
            <Container>
                <Row className="justify-content-center">
                    <Col md={6}>
                        <Form onSubmit={handleSubmit} className="shadow-sm p-4 bg-white rounded-4">
                            <h4 className="text-center mb-4" style={{ color: '#111827' }}>
                                Create Your Account
                            </h4>

                            <Form.Group controlId="formName" className="mb-3">
                                <Form.Label style={{ color: '#6B7280' }}>Full Name</Form.Label>
                                <Form.Control
                                    type="text"
                                    placeholder="Juan Dela Cruz"
                                    required
                                    style={{ backgroundColor: '#fff', borderColor: '#14B8A6', color: '#111827' }}
                                />
                            </Form.Group>

                            <Form.Group controlId="formEmail" className="mb-3">
                                <Form.Label style={{ color: '#6B7280' }}>Email</Form.Label>
                                <Form.Control
                                    type="email"
                                    placeholder="you@example.com"
                                    required
                                    style={{ backgroundColor: '#fff', borderColor: '#14B8A6', color: '#111827' }}
                                />
                            </Form.Group>

                            <Form.Group controlId="formUsername" className="mb-3">
                                <Form.Label style={{ color: '#6B7280' }}>Username</Form.Label>
                                <Form.Control
                                    type="text"
                                    placeholder="Choose a username"
                                    required
                                    style={{ backgroundColor: '#fff', borderColor: '#14B8A6', color: '#111827' }}
                                />
                            </Form.Group>

                            <Form.Group controlId="formPassword" className="mb-4">
                                <Form.Label style={{ color: '#6B7280' }}>Password</Form.Label>
                                <Form.Control
                                    type="password"
                                    placeholder="Enter password"
                                    required
                                    style={{ backgroundColor: '#fff', borderColor: '#14B8A6', color: '#111827' }}
                                />
                            </Form.Group>

                            <Button
                                type="submit"
                                className="w-100 mb-3"
                                style={{ backgroundColor: '#14B8A6', borderColor: '#14B8A6', color: '#fff' }}
                            >
                                Sign Up
                            </Button>

                            <div className="text-center text-muted mb-3">or</div>

                            <Button
                                className="w-100"
                                onClick={handleGoogleSignUp}
                                style={{
                                    borderColor: '#6B7280',
                                    color: '#6B7280',
                                    backgroundColor: '#F9FAFB',
                                    transition: 'all 0.2s ease-in-out',
                                }}
                                onMouseEnter={(e) => {
                                    e.target.style.backgroundColor = '#6B7280';
                                    e.target.style.color = '#F9FAFB';
                                }}
                                onMouseLeave={(e) => {
                                    e.target.style.backgroundColor = '#F9FAFB';
                                    e.target.style.color = '#6B7280';
                                }}
                            >
                                <FaGoogle className="me-2" />
                                Continue with Google
                            </Button>

                            <div className="text-center mt-4" style={{color: '#6B7280'}}>
                                I accept the SkillSprint's <a href="/termsofuse" style={{color: '#14B8A6'}}>Terms of
                                Use</a> and <a href="/signup" style={{color: '#14B8A6'}}>Privacy Notice</a>

                            </div>
                        </Form>
                    </Col>
                </Row>
            </Container>
        </div>
    );
};

export default SignUp;
