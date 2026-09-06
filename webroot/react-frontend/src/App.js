import React from 'react';
import { Routes, Route, Navigate } from 'react-router-dom';
import LandingPage from './pages/LandingPage';
import LoginPage from './pages/LoginPage';
import Pricing from './pages/Pricing';
import Explore from './components/Explore';

// Guest routes. Anything else (e.g. a logged-in deep link like /library that's
// hit while signed out) falls through to the landing page instead of rendering
// a blank screen.
function App() {
    return (
        <Routes>
            <Route path="/" element={<LandingPage />} />
            <Route path="/login" element={<LoginPage />} />
            <Route path="/explore" element={<Explore />} />
            <Route path="/pricing" element={<Pricing />} />
            <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
    );
}

export default App;
