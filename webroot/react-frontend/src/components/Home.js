import React from 'react';
import { Container, Row, Col, Card } from 'react-bootstrap';
import TopNavbar from './TopNavbar';

const Home = () => {

    return (
        <div style={{ backgroundColor: '#F9FAFB', minHeight: '100vh' }}>
            <TopNavbar/>

            <Container style={{ paddingTop: '100px' }}>
                <h2 style={{ color: '#111827', fontWeight: 'bold' }} className="mb-4">
                    Welcome back, Juan!
                </h2>

                <p style={{ color: '#6B7280' }} className="mb-5">
                    Here's a quick overview of your activity and progress.
                </p>

                <Row className="g-4">
                    <Col md={6} lg={4}>
                        <Card className="shadow-sm h-100 border-0">
                            <Card.Body>
                                <h5 style={{ color: '#14B8A6' }}>Your Current Plan</h5>
                                <p style={{ color: '#6B7280' }}>Professional Reviewer</p>
                            </Card.Body>
                        </Card>
                    </Col>

                    <Col md={6} lg={4}>
                        <Card className="shadow-sm h-100 border-0">
                            <Card.Body>
                                <h5 style={{ color: '#14B8A6' }}>Progress</h5>
                                <p style={{ color: '#6B7280' }}>You’ve completed 3 out of 10 modules</p>
                            </Card.Body>
                        </Card>
                    </Col>

                    <Col md={6} lg={4}>
                        <Card className="shadow-sm h-100 border-0">
                            <Card.Body>
                                <h5 style={{ color: '#14B8A6' }}>Upcoming Exam</h5>
                                <p style={{ color: '#6B7280' }}>Scheduled on June 15, 2025</p>
                            </Card.Body>
                        </Card>
                    </Col>
                </Row>
            </Container>
        </div>
    );
};

export default Home;
