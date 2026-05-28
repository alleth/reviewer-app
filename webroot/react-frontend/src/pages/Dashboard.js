import React from 'react';
import { Container, Navbar, Nav } from 'react-bootstrap';

function Dashboard() {
    return (
        <div>
            <Navbar bg="dark" variant="dark" expand="lg">
                <Container>
                    <Navbar.Brand href="#">Reviewer Dashboard</Navbar.Brand>
                    <Nav className="ms-auto">
                        <Nav.Link href="/logout">Logout</Nav.Link>
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
