import React from 'react';
import TopNavbar from './TopNavbar';

const stats = [
    { title: 'Your Current Plan', body: 'Professional Reviewer' },
    { title: 'Progress', body: "You've completed 3 out of 10 modules" },
    { title: 'Upcoming Exam', body: 'Scheduled on June 15, 2025' },
];

const Home = () => {
    return (
        <div className="min-h-screen bg-gray-50 dark:bg-gray-900">
            <TopNavbar />

            <div className="mx-auto max-w-6xl px-4 pt-24">
                <h2 className="mb-2 text-2xl font-bold text-gray-900 dark:text-gray-100">Welcome back, Juan!</h2>
                <p className="mb-8 text-gray-500 dark:text-gray-400">Here's a quick overview of your activity and progress.</p>

                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {stats.map((stat) => (
                        <div key={stat.title} className="card p-5">
                            <h5 className="font-semibold text-brand">{stat.title}</h5>
                            <p className="mt-1 text-gray-500 dark:text-gray-400">{stat.body}</p>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
};

export default Home;
