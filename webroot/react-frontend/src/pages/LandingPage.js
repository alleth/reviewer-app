import React, { useEffect, useState } from 'react';
import { Container, Button, Row, Col, ListGroup, Modal, Offcanvas } from 'react-bootstrap';
import { useLocation, useNavigate } from 'react-router-dom';
import { FaFacebookF, FaInstagram, FaYoutube } from 'react-icons/fa';
import LoginPage from './LoginPage';
import { Link } from 'react-router-dom';

const LandingPage = () => {
    const location = useLocation();
    const navigate = useNavigate();
    const [showModal, setShowModal] = useState(false);
    const [authMode, setAuthMode] = useState('login');
    const [showMenu, setShowMenu] = useState(false);

    useEffect(() => {
        window.scrollTo(0, 0);
    }, [location.pathname]);

    const openModal = (mode) => {
        setAuthMode(mode);
        setShowModal(true);
    };

    const closeModal = () => {
        setShowModal(false);
    };

    return (
        <div style={{ display: 'flex', flexDirection: 'column', minHeight: '100vh', position: 'relative', overflow: 'hidden' }}>
            {/* Background Circles */}
            <div style={{ position: 'absolute', top: 0, left: 0, width: '100%', height: '100%', zIndex: 1 }}>
                <div className="circle circle1"></div>
                <div className="circle circle2"></div>
            </div>

            {/* Glass Overlay */}
            <div
                style={{
                    position: 'absolute',
                    top: 0,
                    left: 0,
                    width: '100%',
                    height: '100%',
                    background: 'rgba(249, 250, 251, 0.7)',
                    backdropFilter: 'blur(20px)',
                    WebkitBackdropFilter: 'blur(20px)',
                    zIndex: 2,
                }}
            ></div>

            {/* Page Content */}
            <div style={{ position: 'relative', zIndex: 3, flex: 1 }}>
                {/* Header */}
                <div className="d-flex justify-content-between align-items-center px-4 py-3">
                    <Link to="/" style={{ textDecoration: 'none' }}>
                        <div className="fw-bold fs-4" style={{ color: '#14B8A6', marginLeft: '3rem', cursor: 'pointer' }}>
                            SkillSprint
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

                {/* Offcanvas Menu */}
                <Offcanvas show={showMenu} onHide={() => setShowMenu(false)} placement="end">
                    <Offcanvas.Header closeButton>
                        <Offcanvas.Title>Menu</Offcanvas.Title>
                    </Offcanvas.Header>
                    <Offcanvas.Body>
                        <Button variant="outline-dark" className="w-100 mb-2" onClick={() => { openModal('login'); setShowMenu(false); }}>Sign In</Button>
                        <Button style={{ backgroundColor: '#14B8A6', border: 'none' }} className="w-100" onClick={() => { openModal('signup'); setShowMenu(false); }}>Sign Up</Button>
                    </Offcanvas.Body>
                </Offcanvas>

                {/* Hero Section */}
                <Container className="text-center py-5">
                    <h1 className="display-4 fw-bold">Pass the Civil Service<br /> Exam with confidence.</h1>
                    <p style={{ color: '#6B7280' }}>Unleash Your Potential with Our Tools, Anytime, Anywhere</p>
                    <div className="d-flex justify-content-center mt-4">
                        <Button style={{ backgroundColor: '#14B8A6', border: 'none' }} className="me-3">Test Yourself</Button>
                        <Button variant="outline-dark" onClick={() => navigate('/explore')}>Explore</Button>
                    </div>
                </Container>

                {/* Info Section */}
                <Container className="py-5">
                    <Row className="text-center g-4">
                        <Col>
                            <h5>News & Trending</h5>
                            <ListGroup variant="flush">
                                <ListGroup.Item action href="#news-passers" className="text-start">List of Passers for CSC March 2025</ListGroup.Item>
                                <ListGroup.Item action href="#news-subjects" className="text-start">Upcoming new subjects</ListGroup.Item>
                            </ListGroup>
                        </Col>
                        <Col>
                            <h5>Popular Subjects</h5>
                            <ListGroup variant="flush">
                                <ListGroup.Item action href="#subject-csc-pro" className="text-start">CSC - Professional</ListGroup.Item>
                                <ListGroup.Item action href="#subject-csc-subpro" className="text-start">CSC - Sub-Professional</ListGroup.Item>
                            </ListGroup>
                        </Col>
                        <Col>
                            <h5>Popular Articles</h5>
                            <ListGroup variant="flush">
                                <ListGroup.Item action href="#article-cse2025" className="text-start">
                                    Examination Announcement No. 04s 2025 - CSE PPT Exam Calendar CY 2025
                                </ListGroup.Item>
                            </ListGroup>
                        </Col>
                        <Col>
                            <h5>FAQ</h5>
                            <ListGroup variant="flush">
                                <ListGroup.Item action href="#faq-pasasure" className="text-start">What is SkillSprint?</ListGroup.Item>
                                <ListGroup.Item action href="#faq-job" className="text-start">Will SkillSprint help to find a job?</ListGroup.Item>
                                <ListGroup.Item action href="#faq-software" className="text-start">Do I need any special software?</ListGroup.Item>
                                <ListGroup.Item action href="#faq-fees" className="text-start">Are there any fees?</ListGroup.Item>
                            </ListGroup>
                        </Col>
                    </Row>
                </Container>
            </div>

            {/* Modal */}
            <Modal show={showModal} onHide={closeModal} centered>
                <Modal.Body>
                    <LoginPage mode={authMode} onClose={closeModal} />
                </Modal.Body>
            </Modal>

            {/* Footer */}
            <footer style={{ backgroundColor: '#F9FAFB', borderTop: '1px solid #E5E7EB', padding: '1.5rem 0', textAlign: 'center', color: '#6B7280' }}>
                <Container>
                    <Row className="align-items-center">
                        <Col md={6} className="mb-2 mb-md-0">
                            <small>&copy; {new Date().getFullYear()} SkillSprint. All rights reserved.</small>
                        </Col>
                        <Col md={6}>
                            <div className="d-flex justify-content-center justify-content-md-end gap-3">
                                <a href="https://facebook.com" target="_blank" rel="noopener noreferrer" style={{ color: '#6B7280' }}>
                                    <FaFacebookF size={18} />
                                </a>
                                <a href="https://instagram.com" target="_blank" rel="noopener noreferrer" style={{ color: '#6B7280' }}>
                                    <FaInstagram size={18} />
                                </a>
                                <a href="https://youtube.com" target="_blank" rel="noopener noreferrer" style={{ color: '#6B7280' }}>
                                    <FaYoutube size={18} />
                                </a>
                            </div>
                        </Col>
                    </Row>
                </Container>
            </footer>

            {/* Background Animations */}
            <style>
                {`
                    .circle {
                        position: absolute;
                        width: 300px;
                        height: 300px;
                        border-radius: 50%;
                        background-color: #14B8A6;
                        opacity: 0.4;
                        filter: blur(30px);
                    }

                    .circle1 {
                        top: 10%;
                        left: 10%;
                        animation: moveCircle1 20s ease-in-out infinite alternate;
                    }

                    .circle2 {
                        top: 60%;
                        left: 70%;
                        animation: moveCircle2 25s ease-in-out infinite alternate;
                    }

                    @keyframes moveCircle1 {
                        0%   { transform: translate(0, 0); }
                        25%  { transform: translate(100px, 150px); }
                        50%  { transform: translate(200px, -100px); }
                        75%  { transform: translate(-50px, 50px); }
                        100% { transform: translate(0, 0); }
                    }

                    @keyframes moveCircle2 {
                        0%   { transform: translate(0, 0); }
                        25%  { transform: translate(-100px, -150px); }
                        50%  { transform: translate(-200px, 100px); }
                        75%  { transform: translate(50px, -50px); }
                        100% { transform: translate(0, 0); }
                    }
                `}
            </style>
        </div>
    );
};

export default LandingPage;
