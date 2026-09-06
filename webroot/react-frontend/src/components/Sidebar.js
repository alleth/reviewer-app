import React from 'react';
import { Link } from 'react-router-dom';

const Sidebar = () => {
    return (
        <div className="fixed left-0 top-0 h-screen w-56 border-r border-gray-200 bg-gray-50 pt-16 dark:border-gray-700 dark:bg-gray-900">
            <h5 className="mb-4 text-center font-bold text-brand">CareerPass</h5>
            <nav className="flex flex-col px-3">
                <Link to="/" className="rounded-md px-3 py-2 text-sm text-gray-900 hover:bg-gray-100 dark:text-gray-100 dark:hover:bg-gray-800">
                    Home
                </Link>
                {/* Add more menu items here if needed */}
            </nav>
        </div>
    );
};

export default Sidebar;
