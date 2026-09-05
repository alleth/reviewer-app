import React from 'react';

/**
 * CareerPass logo mark: a teal rounded-square tile with three ascending white bars.
 * Everything is sized in `em` relative to the tile itself, so `size` (px) scales the
 * whole mark uniformly — set it via a wrapping font-size where the mark is used.
 */
export default function Logo({ size = 24, className = '' }) {
    const bar = {
        width: '0.085em',
        background: '#fff',
        borderRadius: '0.045em',
        transform: 'skewX(-14deg)',
    };

    return (
        <div
            className={className}
            style={{
                fontSize: size,
                width: '1em',
                height: '1em',
                flexShrink: 0,
                borderRadius: '0.18em',
                background: '#00D1B2',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                gap: '0.075em',
            }}
        >
            <span style={{ ...bar, height: '0.30em', opacity: 0.55 }} />
            <span style={{ ...bar, height: '0.42em', opacity: 0.8 }} />
            <span style={{ ...bar, height: '0.54em', opacity: 1 }} />
        </div>
    );
}
