import React, { useEffect } from 'react';

/**
 * Minimal centered modal used in place of react-bootstrap's <Modal>.
 * Closes on backdrop click or Escape.
 */
export default function Modal({ show, onClose, children, className = '' }) {
    useEffect(() => {
        if (!show) return undefined;
        const onKeyDown = (e) => {
            if (e.key === 'Escape') onClose?.();
        };
        document.addEventListener('keydown', onKeyDown);
        return () => document.removeEventListener('keydown', onKeyDown);
    }, [show, onClose]);

    if (!show) return null;

    return (
        <div
            className="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4"
            onClick={onClose}
        >
            <div
                className={`w-full max-w-md rounded-2xl bg-white p-6 shadow-xl ${className}`}
                onClick={(e) => e.stopPropagation()}
            >
                {children}
            </div>
        </div>
    );
}
