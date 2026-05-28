// src/components/Navigation.js
import React from 'react';
import {Navbar, Nav, Container, Button} from 'react-bootstrap';
import SignInModal from "./SignInModal";
import { Link } from 'react-router-dom';
const Navigation = () => {
    return (
        <Navbar bg="light" expand="lg" className="shadow-sm py-3">
            <Container>
                <Navbar.Brand as={Link} to="/" style={{ color: '#14B8A6', fontWeight: 'bold' }}>SkillSprint</Navbar.Brand>
                <Navbar.Toggle aria-controls="basic-navbar-nav" />
                <Navbar.Collapse id="basic-navbar-nav" className="justify-content-end">
                    <Nav>
                        <SignInModal className="me-2">Sign In</SignInModal>
                        <Button
                            as={Link}
                            to="/signup"
                            className="ms-2"
                            style={{backgroundColor: '#14B8A6', borderColor: '#14B8A6', color: '#ffffff' }}
                        >
                            Sign Up
                        </Button>
                    </Nav>
                </Navbar.Collapse>
            </Container>
        </Navbar>
    );
};

export default Navigation;
