import React, { useEffect, useState } from 'react';
import ReactDOM from 'react-dom/client';
import { BrowserRouter as Router, Route, Routes } from 'react-router-dom';
import { GoogleOAuthProvider } from '@react-oauth/google';
import axios from 'axios';
import Dashboard from './pages/Dashboard';
import App from './App'; // <- Guest route handler
import 'bootstrap/dist/css/bootstrap.min.css';
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

    if (isLoggedIn === null) return <div>Loading...</div>;

    return (
        <Router>
            <Routes>
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
