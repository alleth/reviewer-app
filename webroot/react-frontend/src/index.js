import React, { useEffect, useState } from 'react';
import ReactDOM from 'react-dom/client';
import { BrowserRouter as Router, Route, Routes } from 'react-router-dom';
import axios from 'axios';
import Dashboard from './pages/Dashboard';
import App from './App'; // <- Guest route handler
import 'bootstrap/dist/css/bootstrap.min.css';
import API_URL from './api';

function AppWrapper() {
    const [isLoggedIn, setIsLoggedIn] = useState(null);

    useEffect(() => {
        axios.get(`${API_URL}/api/session`, { withCredentials: true })
            .then(res => setIsLoggedIn(res.data.loggedIn))
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
root.render(<AppWrapper />);
