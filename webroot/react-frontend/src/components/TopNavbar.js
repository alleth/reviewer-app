import React from 'react';
import { Navbar, Nav, Container, NavDropdown } from 'react-bootstrap';
import API_URL from '../api';

// This is your logout logic
const onLogout = async () => {
    try {
        await fetch(`${API_URL}/api/logout`, {
            method: 'POST',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json'
            }
        });
        window.location.reload(); // Reloads page to show App_old.js (guest view)
    } catch (error) {
        console.error('Logout failed:', error);
    }
};

// Just remove `onLogout` from the props!
const TopNavbar = ({ userName }) => {
    return (
        <Navbar
            bg="light"
            expand="lg"
            fixed="top"
            style={{
                backgroundColor: '#F9FAFB',
                borderBottom: '1px solid #E5E7EB',
                boxShadow: '0 2px 5px rgba(0, 0, 0, 0.1)',
                padding: '0.5rem 1rem',
            }}
        >
            <Container fluid>
                <Navbar.Brand
                    href="/"
                    style={{
                        color: '#14B8A6',
                        fontWeight: 'bold',
                        fontSize: '1.25rem',
                        marginRight: '2rem',
                    }}
                >
                    SkillSprint
                </Navbar.Brand>
                <Navbar.Toggle aria-controls="basic-navbar-nav" />
                <Navbar.Collapse id="basic-navbar-nav" className="justify-content-end">
                    <Nav>
                        <NavDropdown
                            title={
                                <span style={{ color: '#111827', fontSize: '1rem', fontWeight: '500' }}>
                                    {userName || 'User'}
                                </span>
                            }
                            id="user-nav-dropdown"
                            align="end"
                        >
                            <NavDropdown.Item href="#notifications" style={{ color: '#111827' }}>
                                Notifications
                            </NavDropdown.Item>
                            <NavDropdown.Item href="#settings" style={{ color: '#111827' }}>
                                Settings
                            </NavDropdown.Item>
                            <NavDropdown.Divider />
                            <NavDropdown.Item
                                onClick={onLogout}
                                style={{ color: '#111827', cursor: 'pointer' }}
                            >
                                Logout
                            </NavDropdown.Item>
                        </NavDropdown>
                    </Nav>
                </Navbar.Collapse>
            </Container>
        </Navbar>
    );
};

export default TopNavbar;
