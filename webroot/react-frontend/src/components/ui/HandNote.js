import React from 'react';

/**
 * Playful hand-drawn annotation: a scribbly curved arrow next to a line of text
 * in a handwriting font. Purely decorative — pointer-events are off.
 *
 *   <HandNote text="most passers pick this" className="..." />        // arrow curves down-right
 *   <HandNote text="cancel anytime" arrow="left" className="..." />   // arrow curves down-left
 */
export default function HandNote({ text, arrow = 'right', className = '' }) {
    const flip = arrow === 'left';

    return (
        <div
            className={`pointer-events-none flex select-none items-end gap-1 text-gray-400 dark:text-gray-500 ${
                flip ? 'flex-row-reverse' : ''
            } ${className}`}
            aria-hidden="true"
        >
            <span className="font-hand text-2xl font-bold leading-none">{text}</span>
            <svg
                width="46"
                height="40"
                viewBox="0 0 46 40"
                fill="none"
                stroke="currentColor"
                strokeWidth="2.5"
                strokeLinecap="round"
                strokeLinejoin="round"
                className={`mb-1 ${flip ? '-scale-x-100' : ''}`}
            >
                <path d="M3 4c6 14 16 24 30 27" />
                <path d="M24 33l9 -2 -4 -9" />
            </svg>
        </div>
    );
}
