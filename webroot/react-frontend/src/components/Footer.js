// src/components/Footer.js
import React from 'react';
import { FaFacebook, FaXTwitter, FaDiscord, FaYoutube } from 'react-icons/fa6';

const Footer = () => {
    return (
        <footer className="mt-auto bg-gray-100 py-4">
            <div className="mx-auto flex max-w-6xl items-center justify-between px-4">
                <span className="text-gray-500">SkillSprint &copy; 2025</span>
                <div className="flex gap-3 text-gray-600">
                    <FaFacebook />
                    <FaXTwitter />
                    <FaDiscord />
                    <FaYoutube />
                </div>
            </div>
        </footer>
    );
};

export default Footer;
