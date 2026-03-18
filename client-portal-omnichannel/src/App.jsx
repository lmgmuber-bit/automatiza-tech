import { useState, useEffect, useCallback } from 'react';
import { setApiKey, isAuthenticated, clearAuth, getIsAdmin, getIsAgent, isSupervisorOrAdmin, getOpenTicketCount } from './api';
import Sidebar from './components/Sidebar';
import LoginScreen from './components/LoginScreen';
import InboxView from './components/InboxView';
import ChannelsView from './components/ChannelsView';
import BotsView from './components/BotsView';
import AgentsView from './components/AgentsView';
import AuditView from './components/AuditView';
import ChannelTypesView from './components/ChannelTypesView';
import ClientsView from './components/ClientsView';
import DashboardView from './components/DashboardView';
import ProfileView from './components/ProfileView';
import SupportView from './components/SupportView';
import ExpiryWarningModal from './components/ExpiryWarningModal';
import TicketNotificationModal from './components/TicketNotificationModal';

export default function App() {
  const [authenticated, setAuthenticated] = useState(isAuthenticated());
  const [currentView, setCurrentView] = useState(() => getIsAdmin() ? 'clients' : 'inbox');
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [loggingOut, setLoggingOut] = useState(false);
  const [darkMode, setDarkMode] = useState(() => localStorage.getItem('omni_theme') === 'dark');
  const [isMobile, setIsMobile] = useState(window.innerWidth <= 768);
  const [periodWarning, setPeriodWarning] = useState(null);
  const [agentDataVersion, setAgentDataVersion] = useState(0);
  const [openTicketCount, setOpenTicketCount] = useState(0);

  useEffect(() => {
    function handleResize() {
      const mobile = window.innerWidth <= 768;
      setIsMobile(mobile);
      if (!mobile) setSidebarOpen(false);
    }
    window.addEventListener('resize', handleResize);
    return () => window.removeEventListener('resize', handleResize);
  }, []);

  // Listen for profile data updates to refresh sidebar immediately
  useEffect(() => {
    function handleAgentDataUpdated() {
      setAgentDataVersion(v => v + 1);
    }
    window.addEventListener('agentDataUpdated', handleAgentDataUpdated);
    return () => window.removeEventListener('agentDataUpdated', handleAgentDataUpdated);
  }, []);

  // Fetch open ticket count for admin (poll every 60s)
  useEffect(() => {
    if (!getIsAdmin()) return;
    let cancelled = false;
    async function fetchCount() {
      try {
        const data = await getOpenTicketCount();
        if (!cancelled) setOpenTicketCount(data.count || 0);
      } catch {}
    }
    fetchCount();
    const interval = setInterval(fetchCount, 60000);
    return () => { cancelled = true; clearInterval(interval); };
  }, [authenticated]);

  useEffect(() => {
    const root = document.documentElement;
    root.setAttribute('data-theme', darkMode ? 'dark' : 'light');
    root.classList.toggle('dark', darkMode);
    localStorage.setItem('omni_theme', darkMode ? 'dark' : 'light');
  }, [darkMode]);

  function handleLogin(key) {
    setApiKey(key);
    setAuthenticated(true);
    // Check for period warning set during login
    const pw = localStorage.getItem('omni_period_warning');
    if (pw) {
      try {
        const parsed = JSON.parse(pw);
        if (parsed && parsed.warning) setPeriodWarning(parsed);
      } catch {};
    }
  }

  function handleLogout() {
    setLoggingOut(true);
    setTimeout(() => {
      clearAuth();
      localStorage.removeItem('omni_period_warning');
      setAuthenticated(false);
      setPeriodWarning(null);
      setLoggingOut(false);
    }, 600);
  }

  const handleNavigate = useCallback((view) => {
    setCurrentView(view);
    if (isMobile) setSidebarOpen(false);
  }, [isMobile]);

  if (!authenticated) {
    return <LoginScreen onLogin={handleLogin} />;
  }

  const views = getIsAgent()
    ? (isSupervisorOrAdmin()
      ? {
          inbox: <InboxView />,
          bots: <BotsView />,
          agents: <AgentsView />,
          audit: <AuditView />,
          profile: <ProfileView />,
          support: <SupportView />,
        }
      : {
          inbox: <InboxView />,
          agents: <AgentsView />,
          profile: <ProfileView />,
          support: <SupportView />,
        })
    : {
        inbox: <InboxView />,
        channels: <ChannelsView />,
        'channel-types': <ChannelTypesView />,
        bots: <BotsView />,
        agents: <AgentsView />,
        audit: <AuditView />,
        ...(getIsAdmin() ? {
          clients: <ClientsView />,
          dashboard: <DashboardView />,
          support: <SupportView />,
        } : {
          support: <SupportView />,
        }),
      };

  return (
    <div className={`dashboard-layout ${loggingOut ? 'dashboard-log-out' : ''}`}>
      {/* Mobile overlay */}
      {isMobile && sidebarOpen && (
        <div className="sidebar-overlay" onClick={() => setSidebarOpen(false)} />
      )}

      <Sidebar
        currentView={currentView}
        onNavigate={handleNavigate}
        onLogout={handleLogout}
        isOpen={sidebarOpen}
        onClose={() => setSidebarOpen(false)}
        isMobile={isMobile}
        darkMode={darkMode}
        onToggleDark={() => setDarkMode(d => !d)}
        agentDataVersion={agentDataVersion}
        openTicketCount={openTicketCount}
      />

      <div className="main-content">
        {/* Mobile top bar */}
        {isMobile && (
          <div className="fixed top-0 left-0 right-0 z-30 bg-white dark:bg-slate-800 border-b border-gray-200 dark:border-slate-700 px-3 py-2 flex items-center gap-3" style={{ paddingTop: 'calc(0.5rem + var(--sat))' }}>
            <button
              onClick={() => setSidebarOpen(true)}
              className="mobile-menu-btn p-2 rounded-lg hover:bg-gray-100"
            >
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round">
                <line x1="3" y1="6" x2="21" y2="6" />
                <line x1="3" y1="12" x2="21" y2="12" />
                <line x1="3" y1="18" x2="21" y2="18" />
              </svg>
            </button>
            <div className="flex items-center gap-2">
              <div className="w-7 h-7 rounded-lg bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white font-bold text-[10px]">AT</div>
              <span className="font-semibold text-sm" style={{ fontFamily: 'Poppins, sans-serif' }}>AutomatizaTech</span>
            </div>
          </div>
        )}

        <div className={`flex-1 flex flex-col overflow-hidden ${isMobile ? 'pt-12' : ''}`}>
          {views[currentView] || views.inbox}
        </div>
      </div>

      {/* Period expiry warning modal */}
      <ExpiryWarningModal
        warning={periodWarning}
        onClose={() => { setPeriodWarning(null); localStorage.removeItem('omni_period_warning'); }}
      />

      {/* Admin: open ticket notification */}
      {getIsAdmin() && (
        <TicketNotificationModal onNavigateToSupport={() => handleNavigate('support')} />
      )}
    </div>
  );
}
