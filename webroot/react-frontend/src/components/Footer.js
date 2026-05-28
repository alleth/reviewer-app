// src/components/Footer.js
import React from 'react';
import {Container} from "react-bootstrap";
import {FaFacebook, FaInstagram, FaYoutube} from "react-icons/fa";

const Footer = () => {
    return (
        <footer className="bg-light py-4 mt-auto">
            <Container className="d-flex justify-content-between align-items-center">
                <span style={{color: '#6B7280'}}>SkillSprint &copy; 2025</span>
                <div>
                    <FaFacebook className="me-3"/>
                    <FaYoutube className="me-3"/>
                    <FaInstagram/>
                </div>
            </Container>
        </footer>
    );
};

export default Footer;
