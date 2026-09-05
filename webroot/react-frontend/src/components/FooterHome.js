import React from 'react';

const FooterHome = () => {
    return (
        <footer className="fixed bottom-0 left-0 w-full bg-gray-50 p-4 text-center text-gray-500 shadow-[0_-1px_0_rgba(0,0,0,0.1)]">
            © {new Date().getFullYear()} CareerPass. All rights reserved.
        </footer>
    );
};

export default FooterHome;
