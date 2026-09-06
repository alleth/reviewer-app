import React from 'react';
import { Routes, Route } from 'react-router-dom';
import LandingPage from './pages/LandingPage';
import LoginPage from './pages/LoginPage';
import Pricing from './pages/Pricing';
import Explore from "./components/Explore";

console.log('Rendering App.js');

function App() {
    return (
        <Routes>
            <Route path="/" element={<LandingPage />} />
            <Route path="/login" element={<LoginPage />} />
            <Route path="/explore" element={<Explore />} />
            <Route path="/pricing" element={<Pricing />} />
        </Routes>
    );
}

export default App;
