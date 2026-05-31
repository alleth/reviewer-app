import React from 'react';
import { Container, Navbar, Nav } from 'react-bootstrap';
import API_URL from '../api';

function Dashboard() {
    const handleLogout = async (e) => {
        e.preventDefault();
        try {
            await fetch(`${API_URL}/api/logout`, {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json' }
            });
        } catch (err) {
            console.error('Logout request failed:', err);
        }
        localStorage.removeItem('skillsprint_user');
        window.location.href = '/';
    };

    return (
        <div>
            <Navbar bg="dark" variant="dark" expand="lg">
                <Container>
                    <Navbar.Brand href="#">Reviewer Dashboard</Navbar.Brand>
                    <Nav className="ms-auto">
                        <Nav.Link href="#" onClick={handleLogout}>Logout</Nav.Link>
                    </Nav>
                </Container>
            </Navbar>
            <Container className="mt-4">
                <h2>Welcome to the Dashboard</h2>
                <p>This is a protected area for logged-in users.</p>
            </Container>
        </div>
    );
}

export default Dashboard;
