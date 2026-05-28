import React from 'react';
import { BrowserRouter as Router, Route, Routes } from 'react-router-dom';
import LandingPage from '../pages/LandingPage'; // Ensure correct path
import Explore from '../components/Explore'; // Import Explore component
import Navigation from '../components/Navigation'; // Update the path if needed
import Footer from '../components/Footer';
import SignUp from "../components/SignUp";
import TermsOfUse from "../components/TermOfUse";

const App_old = () => {
    return (
        <Router>
            <Navigation />
            <Routes>
                <Route path="/" element={<LandingPage />} />
                <Route path="/explore" element={<Explore />} /> {/* Add route for Explore */}
                <Route path="/signup" element={<SignUp />} /> {/* Add route for Explore */}
                <Route path="/termsofuse" element={<TermsOfUse />} /> {/* Add route for Explore */}
            </Routes>
            <Footer />
        </Router>
    );
};

export default App_old;
