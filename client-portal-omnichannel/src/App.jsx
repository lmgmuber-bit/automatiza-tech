import { useState, useEffect, useCallback, useRef } from 'react';
import { setApiKey, isAuthenticated, clearAuth, getIsAdmin, getIsAgent, isSupervisorOrAdmin, getOpenTicketCount } from './api';
import Sidebar from './components/Sidebar';
import LoginScreen from './components/LoginScreen';
import InboxView from './components/InboxView';
import ChannelsView from './components/ChannelsView';
import BotsView from './components/BotsView';
import BotConfigUnifiedView from './components/BotConfigUnifiedView';
import AgentsView from './components/AgentsView';
import AuditView from './components/AuditView';
import ChannelTypesView from './components/ChannelTypesView';
import ClientsView from './components/ClientsView';
import DashboardView from './components/DashboardView';
import ProfileView from './components/ProfileView';
import SupportView from './components/SupportView';
import PromptsView from './components/PromptsView';
import ExpiryWarningModal from './components/ExpiryWarningModal';
import TicketNotificationModal from './components/TicketNotificationModal';
import AssignedChatsModal from './components/AssignedChatsModal';
import AiAssistantChat from './components/AiAssistantChat';
import AiPromptView from './components/AiPromptView';

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
  const [unreadMsgCount, setUnreadMsgCount] = useState(0);

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

  // Listen for unread message count from InboxView polling
  useEffect(() => {
    function handleUnread(e) {
      setUnreadMsgCount(e.detail || 0);
    }
    window.addEventListener('omniUnreadCount', handleUnread);
    return () => window.removeEventListener('omniUnreadCount', handleUnread);
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

  // Update document title with unread count
  useEffect(() => {
    document.title = unreadMsgCount > 0
      ? `(${unreadMsgCount}) OmniCliente - AutomatizaTech`
      : 'OmniCliente - AutomatizaTech';
  }, [unreadMsgCount]);

  useEffect(() => {
    const root = document.documentElement;
    root.setAttribute('data-theme', darkMode ? 'dark' : 'light');
    root.classList.toggle('dark', darkMode);
    localStorage.setItem('omni_theme', darkMode ? 'dark' : 'light');
  }, [darkMode]);

  // === Inactivity timeout (30 min) ===
  const INACTIVITY_LIMIT = 30 * 60 * 1000; // 30 minutes
  const lastActivityRef = useRef(Date.now());
  const [showInactivityModal, setShowInactivityModal] = useState(false);

  useEffect(() => {
    if (!authenticated) return;
    function resetActivity() {
      lastActivityRef.current = Date.now();
      localStorage.setItem('omni_last_activity', String(Date.now()));
    }
    // Restore from storage (covers page reloads)
    const stored = parseInt(localStorage.getItem('omni_last_activity') || '0', 10);
    if (stored > 0) lastActivityRef.current = stored;
    else resetActivity();

    const events = ['mousedown', 'keydown', 'touchstart', 'scroll', 'mousemove'];
    events.forEach(e => window.addEventListener(e, resetActivity, { passive: true }));

    const checker = setInterval(() => {
      if (Date.now() - lastActivityRef.current >= INACTIVITY_LIMIT) {
        setShowInactivityModal(true);
        clearInterval(checker);
      }
    }, 60000); // check every 60s

    return () => {
      events.forEach(e => window.removeEventListener(e, resetActivity));
      clearInterval(checker);
    };
  }, [authenticated]);

  function handleInactivityLogout() {
    setShowInactivityModal(false);
    localStorage.removeItem('omni_last_activity');
    handleLogout();
  }

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
      localStorage.removeItem('omni_last_activity');
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
          'bot-config': <BotConfigUnifiedView />,
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
        'bot-config': <BotConfigUnifiedView />,
        agents: <AgentsView />,
        audit: <AuditView />,
        ...(getIsAdmin() ? {
          clients: <ClientsView />,
          dashboard: <DashboardView />,
          'ai-prompt': <AiPromptView />,
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
        unreadMsgCount={unreadMsgCount}
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

      {/* Agent: assigned chats notification */}
      {getIsAgent() && (
        <AssignedChatsModal onNavigateToInbox={() => handleNavigate('inbox')} />
      )}

      {/* AI Assistant — chat for agents/clients, prompt editor for admin */}
      <AiAssistantChat currentView={currentView} />

      {/* Inactivity timeout modal */}
      {showInactivityModal && (
        <div className="fixed inset-0 z-[100] flex items-center justify-center bg-black/50 backdrop-blur-sm">
          <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl p-6 sm:p-8 max-w-sm mx-4 text-center">
            <div className="w-14 h-14 mx-auto mb-4 rounded-full bg-amber-100 dark:bg-amber-900/30 flex items-center justify-center">
              <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" className="text-amber-600 dark:text-amber-400">
                <circle cx="12" cy="12" r="10" />
                <polyline points="12 6 12 12 16 14" />
              </svg>
            </div>
            <h3 className="text-lg font-bold text-slate-800 dark:text-white mb-2">Sesión expirada</h3>
            <p className="text-sm text-slate-500 dark:text-slate-400 mb-6">
              Tu sesión se cerró por inactividad de más de 30 minutos. Por seguridad, debes iniciar sesión nuevamente.
            </p>
            <button
              onClick={handleInactivityLogout}
              className="w-full px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-medium rounded-xl transition-colors text-sm"
            >
              Iniciar sesión
            </button>
          </div>
        </div>
      )}
    </div>
  );
}
