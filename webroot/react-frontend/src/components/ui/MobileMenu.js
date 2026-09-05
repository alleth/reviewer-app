import React from 'react';

/**
 * Right-hand slide-in panel used in place of react-bootstrap's <Offcanvas>.
 */
export default function MobileMenu({ show, onClose, title = 'Menu', children }) {
    return (
        <>
            <div
                className={`fixed inset-0 z-40 bg-gray-900/50 transition-opacity ${
                    show ? 'opacity-100' : 'pointer-events-none opacity-0'
                }`}
                onClick={onClose}
            />
            <div
                className={`fixed right-0 top-0 z-50 h-full w-72 max-w-[85vw] transform bg-white p-5 shadow-xl transition-transform duration-200 ${
                    show ? 'translate-x-0' : 'translate-x-full'
                }`}
            >
                <div className="mb-4 flex items-center justify-between">
                    <span className="text-base font-semibold text-gray-900">{title}</span>
                    <button
                        type="button"
                        onClick={onClose}
                        aria-label="Close menu"
                        className="text-2xl leading-none text-gray-400 hover:text-gray-600"
                    >
                        &times;
                    </button>
                </div>
                {children}
            </div>
        </>
    );
}
