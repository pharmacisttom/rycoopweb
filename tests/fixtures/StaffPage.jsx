import React from 'react';
import {createRoot} from 'react-dom/client';
import {BrowserRouter} from 'react-router-dom';
import App from '../../src/App';
import {AuthProvider} from '../../src/context/AuthContext';
import {ThemeProvider} from '../../src/context/ThemeContext';
import {ToastProvider} from '../../src/context/ToastContext';
import '../../src/index.css';
import '../../src/styles/mobile-responsive.css';
// Real App, AuthProvider and protected route; identity comes from a PHP fixture session.
createRoot(document.getElementById('root')).render(<BrowserRouter><ThemeProvider><AuthProvider><ToastProvider><App/></ToastProvider></AuthProvider></ThemeProvider></BrowserRouter>);
