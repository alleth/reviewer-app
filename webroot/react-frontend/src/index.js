import React, { useEffect, useState } from 'react';
import ReactDOM from 'react-dom/client';
import { BrowserRouter as Router, Route, Routes } from 'react-router-dom';
import { GoogleOAuthProvider } from '@react-oauth/google';
import axios from 'axios';
import Dashboard from './pages/Dashboard';
import AccountSetup from './pages/AccountSetup';
import App from './App'; // <- Guest route handler
import './theme'; // initialise theme store (applies the `dark` class on load)
import './index.css';
import API_URL from './api';

const GOOGLE_CLIENT_ID = process.env.REACT_APP_GOOGLE_CLIENT_ID || '';

function AppWrapper() {
    const [isLoggedIn, setIsLoggedIn] = useState(null);

    useEffect(() => {
        const stored = localStorage.getItem('skillsprint_user');
        if (!stored) {
            setIsLoggedIn(false);
            return;
        }
        axios.get(`${API_URL}/api/session?t=${Date.now()}`, { withCredentials: true })
            .then(res => {
                if (res.data.loggedIn) {
                    setIsLoggedIn(true);
                } else {
                    localStorage.removeItem('skillsprint_user');
                    setIsLoggedIn(false);
                }
            })
            .catch(() => setIsLoggedIn(false));
    }, []);

    if (isLoggedIn === null) {
        return (
            <div className="flex min-h-screen items-center justify-center bg-gray-50 text-sm text-gray-500 dark:bg-gray-900 dark:text-gray-400">
                Loading...
            </div>
        );
    }

    return (
        <Router>
            <Routes>
                {/* Reachable regardless of isLoggedIn — AccountSetup redirects itself if there's no session. */}
                <Route path="/account-setup" element={<AccountSetup />} />
                <Route path="/*" element={isLoggedIn ? <Dashboard /> : <App />} />
            </Routes>
        </Router>
    );
}

const root = ReactDOM.createRoot(document.getElementById('root'));
root.render(
    <GoogleOAuthProvider clientId={GOOGLE_CLIENT_ID}>
        <AppWrapper />
    </GoogleOAuthProvider>
);
