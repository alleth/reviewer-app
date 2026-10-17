import React, { useEffect, useState } from 'react';
import ReactDOM from 'react-dom/client';
import { BrowserRouter as Router, Route, Routes } from 'react-router-dom';
import { GoogleOAuthProvider } from '@react-oauth/google';
import axios from 'axios';
import Dashboard from './pages/Dashboard';
import AccountSetup from './pages/AccountSetup';
import SplashScreen from './components/ui/SplashScreen';
import PrivacyPolicy from './pages/legal/PrivacyPolicy';
import TermsOfService from './pages/legal/TermsOfService';
import RefundPolicy from './pages/legal/RefundPolicy';
import App from './App'; // <- Guest route handler
import './theme'; // initialise theme store (applies the `dark` class on load)
import './index.css';
import API_URL from './api';
import { rememberSignedOut } from './sessionErrors';

const GOOGLE_CLIENT_ID = process.env.REACT_APP_GOOGLE_CLIENT_ID || '';

function AppWrapper() {
    const [isLoggedIn, setIsLoggedIn] = useState(null);

    useEffect(() => {
        let cancelled = false;
        let wasLoggedIn = false;

        const checkSession = () => {
            const stored = localStorage.getItem('skillsprint_user');
            if (!stored) {
                if (!cancelled) setIsLoggedIn(false);
                return;
            }
            axios.get(`${API_URL}/api/session?t=${Date.now()}`, { withCredentials: true })
                .then((res) => {
                    if (cancelled) return;
                    if (res.data.loggedIn) {
                        wasLoggedIn = true;
                        // Keep the stored user fresh (purchases, profile edits) so
                        // every screen reads current data, not just what login set.
                        try {
                            if (res.data.user) {
                                localStorage.setItem('skillsprint_user', JSON.stringify(res.data.user));
                            }
                        } catch (e) { /* ignore */ }
                        setIsLoggedIn(true);
                        return;
                    }
                    // A stored user means this device had a live session, so say why
                    // it ended: "signed in on another device", or — for any other
                    // reason (expired, a deploy, B4) — "no longer valid". Re-read
                    // storage: a sign-out clicked while this check was in flight has
                    // already cleared it, and that must not show a notice.
                    if (localStorage.getItem('skillsprint_user')) {
                        rememberSignedOut(res.data.reason);
                    }
                    localStorage.removeItem('skillsprint_user');
                    // If this device had a live session that's now gone, do a full
                    // reload to the landing page rather than leaving a logged-in
                    // route rendering with no session behind it.
                    if (wasLoggedIn) {
                        window.location.assign('/');
                        return;
                    }
                    setIsLoggedIn(false);
                })
                .catch(() => {
                    if (!cancelled) setIsLoggedIn(false);
                });
        };

        checkSession();

        // Keep the session fresh without a manual refresh: poll while the tab is
        // visible, and re-check the moment it regains focus. A superseded device
        // (someone logged in elsewhere) then signs itself out within ~25s, or
        // instantly when the user comes back to it. Polling pauses while the tab
        // is hidden so the scale-to-zero backend can still sleep.
        const onVisible = () => {
            if (document.visibilityState === 'visible') checkSession();
        };
        document.addEventListener('visibilitychange', onVisible);
        window.addEventListener('focus', onVisible);

        const interval = setInterval(() => {
            if (document.visibilityState === 'visible') checkSession();
        }, 25000);

        return () => {
            cancelled = true;
            clearInterval(interval);
            document.removeEventListener('visibilitychange', onVisible);
            window.removeEventListener('focus', onVisible);
        };
    }, []);

    if (isLoggedIn === null) {
        return <SplashScreen />;
    }

    return (
        <Router>
            <Routes>
                {/* Reachable regardless of isLoggedIn — AccountSetup redirects itself if there's no session. */}
                <Route path="/account-setup" element={<AccountSetup />} />
                {/* Legal pages render the same whether or not someone is signed in. */}
                <Route path="/privacy" element={<PrivacyPolicy />} />
                <Route path="/terms" element={<TermsOfService />} />
                <Route path="/refund" element={<RefundPolicy />} />
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
