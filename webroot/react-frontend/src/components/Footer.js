// src/components/Footer.js
import React from 'react';
import { SOCIALS } from '../socials';

const Footer = () => {
    return (
        <footer className="mt-auto bg-gray-100 py-4 dark:bg-gray-800">
            <div className="mx-auto flex max-w-6xl items-center justify-between px-4">
                <span className="text-gray-500 dark:text-gray-400">CareerPass &copy; {new Date().getFullYear()}</span>
                <div className="flex gap-3 text-gray-600 dark:text-gray-300">
                    {SOCIALS.map(({ id, name, href, Icon }) => (
                        <a
                            key={id}
                            href={href}
                            target="_blank"
                            rel="noopener noreferrer"
                            aria-label={name}
                            className="hover:text-brand"
                        >
                            <Icon />
                        </a>
                    ))}
                </div>
            </div>
        </footer>
    );
};

export default Footer;
