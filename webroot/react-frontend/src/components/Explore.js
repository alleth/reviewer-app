import React, { useState } from 'react';
import { Container, Row, Col, Button, Card, Badge, Breadcrumb, Offcanvas, Modal } from 'react-bootstrap';
import { Link } from 'react-router-dom';
import { FaHome } from 'react-icons/fa';
import LoginPage from '../pages/LoginPage'; // make sure this file exists


const Explore = () => {
    const [showMenu, setShowMenu] = useState(false);
    const [showModal, setShowModal] = useState(false);
    const [authMode, setAuthMode] = useState('login');

    const openModal = (mode) => {
        setAuthMode(mode);
        setShowModal(true);
    };

    const closeModal = () => {
        setShowModal(false);
    };

    return (
        <div style={{ backgroundColor: '#F9FAFB', minHeight: '100vh', paddingBottom: '4rem' }}>
            {/* Header */}
            <div className="d-flex justify-content-between align-items-center px-4 py-3">
                <Link to="/" style={{ textDecoration: 'none' }}>
                    <div className="fw-bold fs-4" style={{ color: '#14B8A6', marginLeft: '2.5rem', cursor: 'pointer' }}>SkillSprint
                    </div>
                </Link>
                <div className="d-none d-md-block" style={{ marginRight: '2.5rem' }}>
                    <Button variant="outline-dark" className="me-2" onClick={() => openModal('login')}>Sign In</Button>
                    <Button style={{ backgroundColor: '#14B8A6', border: 'none' }} onClick={() => openModal('signup')}>Sign Up</Button>
                </div>
                <div className="d-md-none">
                    <Button variant="outline-dark" onClick={() => setShowMenu(true)}>☰</Button>
                </div>
            </div>

            {/* Offcanvas Menu (Mobile) */}
            <Offcanvas show={showMenu} onHide={() => setShowMenu(false)} placement="end">
                <Offcanvas.Header closeButton>
                    <Offcanvas.Title>Menu</Offcanvas.Title>
                </Offcanvas.Header>
                <Offcanvas.Body>
                    <Button variant="outline-dark" className="w-100 mb-2" onClick={() => { openModal('login'); setShowMenu(false); }}>Sign In</Button>
                    <Button style={{ backgroundColor: '#14B8A6', border: 'none' }} className="w-100" onClick={() => { openModal('signup'); setShowMenu(false); }}>Sign Up</Button>
                </Offcanvas.Body>
            </Offcanvas>

            {/* Modal for Login / Signup */}
            <Modal show={showModal} onHide={closeModal} centered>
                <Modal.Body>
                    <LoginPage mode={authMode} onClose={closeModal} />
                </Modal.Body>
            </Modal>

            {/* Explore Content */}
            <Container style={{ paddingTop: '1rem' }}>
                <Breadcrumb className="mb-4">
                    <Breadcrumb.Item linkAs={Link} linkProps={{ to: '/' }} style={{ color: '#6B7280' }}>
                        <FaHome className="me-2" />
                    </Breadcrumb.Item>
                    <Breadcrumb.Item active style={{ color: '#111827' }}>
                        Explore
                    </Breadcrumb.Item>
                </Breadcrumb>


                {/* Hero Section */}
                <div style={{ backgroundColor: '#E0F7F5', padding: '4rem 1rem' }}>
                    <Container className="text-center">
                        <h2 style={{ color: '#111827', fontWeight: 'bold' }}>
                            Start Your Civil Service Journey with Confidence
                        </h2>
                        <p style={{ color: '#6B7280', maxWidth: '700px', margin: '0 auto' }}>
                            Sign up now and enjoy a <strong>7-day free trial</strong> to access our top-tier review content, mock exams,
                            and expert guidance — all designed to help you succeed!
                        </p>
                        <Button
                            className="mt-3"
                            size="lg"
                            style={{ backgroundColor: '#14B8A6', border: 'none' }}
                            onClick={() => openModal('signup')}
                        >
                            Start 7-Day Free Trial
                        </Button>
                    </Container>
                </div>
                <br/>

                <h1 className="text-center py-2" style={{ color: '#111827', fontWeight: 'bold' }}>
                    Explore Our Review Packages
                </h1>

                <p className="text-center mb-5" style={{ color: '#6B7280', maxWidth: '800px', margin: '0 auto' }}>
                    Whether you're preparing for the Civil Service Exam or advancing to professional government roles,
                    we've created focused review packages to help you succeed. Each plan includes carefully designed
                    modules, practice tests, and expert tips. Choose the one that best fits your goal, and you'll get
                    immediate access after purchase — no complicated steps required.
                </p>

                <Row className="g-4">
                    <Col md={6}>
                        <Card className="h-100 shadow-sm">
                            <Card.Body>
                                <Badge bg="" style={{ backgroundColor: '#14B8A6', color: 'white' }} className="mb-2">
                                    Most Popular
                                </Badge>
                                <Card.Title style={{ color: '#111827' }}>Sub-Professional Reviewer</Card.Title>
                                <Card.Text style={{ color: '#6B7280' }}>
                                    <ul>
                                        <li>Comprehensive Review Modules</li>
                                        <li>Timed Practice Exams</li>
                                        <li>Tips & Strategies</li>
                                        <li>1-Month Access</li>
                                    </ul>
                                    <div className="mt-3">
                                        <span style={{ textDecoration: 'line-through', color: '#6B7280', marginRight: '0.5rem' }}>
                                            ₱999
                                        </span>
                                        <strong style={{ color: '#111827', fontSize: '1.25rem' }}>₱350</strong>
                                    </div>
                                </Card.Text>
                                <Link to="/checkout">
                                    <Button style={{ backgroundColor: '#14B8A6', border: 'none' }}>Purchase</Button>
                                </Link>
                            </Card.Body>
                        </Card>
                    </Col>
                    <Col md={6}>
                        <Card className="h-100 shadow-sm">
                            <Card.Body>
                                <Badge bg="" style={{ backgroundColor: '#14B8A6', color: 'white' }} className="mb-2">
                                    Best Value
                                </Badge>
                                <Card.Title style={{ color: '#111827' }}>Professional Reviewer</Card.Title>
                                <Card.Text style={{ color: '#6B7280' }}>
                                    <ul>
                                        <li>Comprehensive Review Modules</li>
                                        <li>Simulated Exams</li>
                                        <li>Expert Exam Strategies</li>
                                        <li>3-Month Access</li>
                                    </ul>
                                    <div className="mt-3">
                                        <span style={{ textDecoration: 'line-through', color: '#6B7280', marginRight: '0.5rem' }}>
                                            ₱1499
                                        </span>
                                        <strong style={{ color: '#111827', fontSize: '1.25rem' }}>₱500</strong>
                                    </div>
                                </Card.Text>
                                <Link to="/checkout">
                                    <Button style={{ backgroundColor: '#14B8A6', border: 'none' }}>Purchase</Button>
                                </Link>
                            </Card.Body>
                        </Card>
                    </Col>
                </Row>
            </Container>
        </div>
    );
};

export default Explore;
