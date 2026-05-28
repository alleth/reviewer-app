import React from 'react';
import { Nav } from 'react-bootstrap';
import { Link } from 'react-router-dom';

const Sidebar = () => {
    return (
        <div style={{
            width: '220px',
            height: '100vh',
            backgroundColor: '#F9FAFB',
            borderRight: '1px solid #e0e0e0',
            position: 'fixed',
            top: 0,
            left: 0,
            paddingTop: '4rem'
        }}>
            <h5 className="text-center mb-4" style={{ color: '#14B8A6', fontWeight: 'bold' }}>SkillSprint</h5>
            <Nav className="flex-column px-3">
                <Nav.Link as={Link} to="/" style={{ color: '#111827' }}>
                    Home
                </Nav.Link>
                {/* Add more menu items here if needed */}
            </Nav>
        </div>
    );
};

export default Sidebar;
