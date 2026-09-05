import React from 'react';

function getInitials({ fname, lname, email }) {
    const first = (fname || '').trim().charAt(0);
    const last = (lname || '').trim().charAt(0);
    if (first || last) return `${first}${last}`.toUpperCase();
    return (email || '?').trim().charAt(0).toUpperCase() || '?';
}

/**
 * Circle avatar showing a user's initials (or first letter of their email as a
 * fallback). Used where we know a Google account's name but don't store its
 * profile photo.
 */
export default function InitialsAvatar({ fname, lname, email, size = 48, className = '' }) {
    return (
        <div
            className={`flex shrink-0 items-center justify-center rounded-full bg-brand font-semibold text-white ${className}`}
            style={{ width: size, height: size, fontSize: size * 0.4 }}
        >
            {getInitials({ fname, lname, email })}
        </div>
    );
}
