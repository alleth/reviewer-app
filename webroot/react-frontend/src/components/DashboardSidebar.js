import React from 'react';
import { NavLink } from 'react-router-dom';
import { FaBookOpenReader, FaChartPie, FaTags } from 'react-icons/fa6';

const tabs = [
    { to: '/library', label: 'My Library', Icon: FaBookOpenReader },
    { to: '/dashboard', label: 'Dashboard', Icon: FaChartPie },
    { to: '/plans', label: 'Plans', Icon: FaTags },
];

const linkClass = ({ isActive }) =>
    `flex items-center gap-3 whitespace-nowrap rounded-lg px-3 py-2 text-sm font-medium transition-colors ${
        isActive
            ? 'bg-brand/10 text-brand-dark dark:bg-brand/15 dark:text-brand'
            : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-100'
    }`;

/**
 * Left-hand tab nav for the logged-in area. Sits inside the centered
 * max-w-6xl container so the page keeps its left/right margins; collapses to a
 * horizontal scroll strip below `lg`.
 */
export default function DashboardSidebar() {
    return (
        <nav className="lg:w-52 lg:shrink-0">
            <ul className="-mx-1 flex gap-1 overflow-x-auto px-1 pb-1 lg:mx-0 lg:flex-col lg:overflow-visible lg:px-0 lg:pb-0">
                {tabs.map(({ to, label, Icon }) => (
                    <li key={to}>
                        <NavLink to={to} className={linkClass}>
                            <Icon size={16} className="shrink-0" />
                            {label}
                        </NavLink>
                    </li>
                ))}
            </ul>
        </nav>
    );
}
