import React, { useState, useEffect } from 'react';
import Login from './components/Login';
import Dashboard from './components/Dashboard';

function App() {
  const [isAuthenticated, setIsAuthenticated] = useState(false);
  const [theme, setTheme] = useState('light');
  const [isTransitioning, setIsTransitioning] = useState(false);

  // Load theme preference
  useEffect(() => {
    const savedTheme = localStorage.getItem('omnichannel_theme');
    if (savedTheme) {
      setTheme(savedTheme);
      document.documentElement.setAttribute('data-theme', savedTheme);
    }
  }, []);

  const toggleTheme = () => {
    const newTheme = theme === 'light' ? 'dark' : 'light';
    setTheme(newTheme);
    localStorage.setItem('omnichannel_theme', newTheme);
    document.documentElement.setAttribute('data-theme', newTheme);
  };

  const handleLogin = () => {
    setIsTransitioning(true);
    setTimeout(() => {
      setIsAuthenticated(true);
      setIsTransitioning(false);
    }, 800); // Wait for fade out
  };

  const handleLogout = () => {
    setIsTransitioning(true);
    setTimeout(() => {
      setIsAuthenticated(false);
      setIsTransitioning(false);
    }, 800); // Wait for fade out
  };

  return (
    <div className={`app-container ${isTransitioning ? 'fade-out' : 'fade-in'}`}>
      {isAuthenticated ? (
        <Dashboard onLogout={handleLogout} theme={theme} toggleTheme={toggleTheme} />
      ) : (
        <Login onLogin={handleLogin} theme={theme} toggleTheme={toggleTheme} />
      )}
    </div>
  );
}

export default App;
