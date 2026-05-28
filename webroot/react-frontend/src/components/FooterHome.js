import React from 'react';

const Footer = () => {
    return (
        <footer
            style={{
                backgroundColor: '#F9FAFB',
                padding: '1rem',
                textAlign: 'center',
                color: '#6B7280',
                position: 'fixed', // Make it fixed at the bottom
                bottom: '0',
                left: '0',
                width: '100%', // Full width across the screen
                boxShadow: '0 -1px 0 rgba(0, 0, 0, 0.1)', // Optional shadow for separation
            }}
        >
            © {new Date().getFullYear()} SkillSprint. All rights reserved.
        </footer>
    );
};

export default Footer;
