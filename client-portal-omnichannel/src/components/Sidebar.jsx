import { MessageSquare, Radio, Bot, Users, ClipboardList, LogOut, X, Moon, Sun, Building2, BarChart3, Headphones, Settings, UserCircle, LifeBuoy, Sparkles, Instagram } from 'lucide-react';
import { getIsAdmin, getIsAgent, getAgentData, isSupervisorOrAdmin } from '../api';

const navItems = [
  { id: 'inbox', label: 'Bandeja Unificada', icon: MessageSquare },
  { id: 'channels', label: 'Canales', icon: Radio },
  { id: 'channel-types', label: 'Tipos de Canal', icon: Settings },
  { id: 'bot-config', label: 'Config y Prompts Bot', icon: Bot },
  { id: 'agents', label: 'Agentes', icon: Users },
  { id: 'audit', label: 'Auditoría', icon: ClipboardList },
];

// Regular agents: inbox + team + support (profile moved below badge)
const agentNavItems = [
  { id: 'inbox', label: 'Mis Conversaciones', icon: MessageSquare },
  { id: 'agents', label: 'Equipo', icon: Users },
  { id: 'support', label: 'Soporte', icon: LifeBuoy },
];

// Supervisor/admin agents: all views except admin-only and channels (profile moved below badge)
const supervisorNavItems = [
  { id: 'inbox', label: 'Bandeja Unificada', icon: MessageSquare },
  { id: 'bot-config', label: 'Config y Prompts Bot', icon: Bot },
  { id: 'agents', label: 'Agentes', icon: Users },
  { id: 'audit', label: 'Auditoría', icon: ClipboardList },
  { id: 'support', label: 'Soporte', icon: LifeBuoy },
];

const adminNavItems = [
  { id: 'clients', label: 'Clientes', icon: Building2 },
  { id: 'dashboard', label: 'Dashboard', icon: BarChart3 },
  { id: 'marketing-ig', label: 'Marketing IG', icon: Instagram },
  { id: 'ai-prompt', label: 'AI Prompt', icon: Sparkles },
  { id: 'support', label: 'Soporte', icon: LifeBuoy },
];

// Color de marca por módulo — chip de icono gradiente (estilo Marketing IG / Clientes)
const ITEM_GRAD = {
  inbox: 'from-blue-400 to-blue-600',
  channels: 'from-emerald-400 to-teal-600',
  'channel-types': 'from-blue-400 to-blue-600',
  'bot-config': 'from-sky-400 to-blue-600',
  agents: 'from-amber-400 to-orange-500',
  audit: 'from-rose-400 to-pink-600',
  support: 'from-cyan-400 to-sky-600',
  clients: 'from-emerald-400 to-green-600',
  dashboard: 'from-blue-500 to-blue-600',
  'marketing-ig': 'from-pink-500 to-rose-600',
  'ai-prompt': 'from-fuchsia-400 to-blue-600',
  profile: 'from-blue-400 to-blue-600',
};

export default function Sidebar({ currentView, onNavigate, onLogout, isOpen, onClose, isMobile, darkMode, onToggleDark, agentDataVersion, openTicketCount, unreadMsgCount }) {
  const isAgentMode = getIsAgent();
  const agentData = isAgentMode ? getAgentData() : null;
  const activeNav = isAgentMode
    ? (isSupervisorOrAdmin() ? supervisorNavItems : agentNavItems)
    : navItems;

  return (
    <aside className={`sidebar ${isMobile && isOpen ? 'mobile-open' : ''}`}>
      {/* Header */}
      <div className="sidebar-header">
        <img
          src="./logo-automatiza-tech.png"
          alt="AutomatizaTech"
          className="w-9 h-9 rounded-lg object-cover shrink-0"
        />
        <div className="flex-1 min-w-0">
          <h2>AutomatizaTech</h2>
          <p>{isAgentMode ? agentData?.client_name || 'Portal Agente' : 'Portal Omnicanal'}</p>
        </div>
        {isMobile && (
          <button onClick={onClose} className="mobile-close-btn text-slate-400 hover:text-slate-800 dark:hover:text-white p-1 rounded-lg hover:bg-slate-200 dark:hover:bg-slate-700">
            <X size={20} />
          </button>
        )}
      </div>

      {/* Agent profile badge */}
      {isAgentMode && agentData && (
        <div className="mx-3 mb-2 p-2.5 rounded-lg bg-blue-50 dark:bg-blue-500/10 border border-blue-200 dark:border-blue-500/20">
          <div className="flex items-center gap-2">
            {agentData.avatar_url ? (
              <img src={agentData.avatar_url} alt={agentData.name} className="w-8 h-8 rounded-full object-cover shrink-0" />
            ) : (
              <div className="w-8 h-8 rounded-full bg-blue-600 flex items-center justify-center text-white text-xs font-bold">
                {agentData.name?.charAt(0)?.toUpperCase() || '?'}
              </div>
            )}
            <div className="flex-1 min-w-0">
              <p className="text-sm font-medium text-slate-800 dark:text-slate-200 truncate">{agentData.name}</p>
              <p className="text-[10px] text-blue-600 dark:text-blue-400 truncate">{agentData.role} · {agentData.department || 'Sin depto.'}</p>
            </div>
          </div>
          {agentData.skills?.length > 0 && (
            <div className="flex flex-wrap gap-1 mt-1.5">
              {agentData.skills.map(s => (
                <span key={s} className="px-1.5 py-0.5 text-[9px] rounded bg-blue-100 dark:bg-blue-500/20 text-blue-700 dark:text-blue-300 font-medium">{s}</span>
              ))}
            </div>
          )}
          {/* Mi Perfil button below badge */}
          <button
            onClick={() => onNavigate('profile')}
            className={`mt-2 w-full flex items-center gap-2 px-2 py-1.5 rounded-md text-xs font-medium transition-colors ${currentView === 'profile' ? 'bg-blue-200 dark:bg-blue-500/30 text-blue-800 dark:text-blue-200' : 'text-blue-600 dark:text-blue-400 hover:bg-blue-100 dark:hover:bg-blue-500/20'}`}
          >
            <UserCircle size={14} /> Mi Perfil
          </button>
        </div>
      )}

      {/* Nav */}
      <nav className="sidebar-nav custom-scrollbar">
        {activeNav.map(item => {
          const Icon = item.icon;
          const active = currentView === item.id;
          return (
            <button
              key={item.id}
              onClick={() => onNavigate(item.id)}
              className={active ? 'active' : ''}
            >
              <span className={`w-8 h-8 rounded-lg flex items-center justify-center shrink-0 bg-gradient-to-br ${ITEM_GRAD[item.id] || 'from-slate-400 to-slate-600'} text-white shadow-sm`}>
                <Icon size={16} />
              </span>
              <span className="flex-1 text-left">{item.label}</span>
              {item.id === 'inbox' && unreadMsgCount > 0 && (
                <span className="ml-auto px-1.5 py-0.5 text-[10px] font-bold rounded-full bg-red-500 text-white min-w-[20px] text-center animate-pulse">{unreadMsgCount > 99 ? '99+' : unreadMsgCount}</span>
              )}
              {item.id === 'support' && openTicketCount > 0 && (
                <span className="ml-auto px-1.5 py-0.5 text-[10px] font-bold rounded-full bg-red-500 text-white min-w-[20px] text-center">{openTicketCount}</span>
              )}
            </button>
          );
        })}

        {getIsAdmin() && (
          <>
            <div className="mx-3 my-2 border-t border-slate-200 dark:border-slate-700/50" />
            <p className="px-3 text-[10px] uppercase tracking-wider text-slate-400 dark:text-slate-500 font-semibold mb-1">Admin</p>
            {adminNavItems.map(item => {
              const Icon = item.icon;
              const active = currentView === item.id;
              return (
                <button
                  key={item.id}
                  onClick={() => onNavigate(item.id)}
                  className={active ? 'active' : ''}
                >
                  <span className={`w-8 h-8 rounded-lg flex items-center justify-center shrink-0 bg-gradient-to-br ${ITEM_GRAD[item.id] || 'from-slate-400 to-slate-600'} text-white shadow-sm`}>
                <Icon size={16} />
              </span>
                  <span className="flex-1 text-left">{item.label}</span>
                  {item.id === 'support' && openTicketCount > 0 && (
                    <span className="ml-auto px-1.5 py-0.5 text-[10px] font-bold rounded-full bg-red-500 text-white min-w-[20px] text-center">{openTicketCount}</span>
                  )}
                </button>
              );
            })}
          </>
        )}
      </nav>

      {/* Footer */}
      <div className="sidebar-footer space-y-1">
        <button
          onClick={onToggleDark}
          className="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm transition-colors"
        >
          {darkMode ? <Sun size={18} /> : <Moon size={18} />}
          <span>{darkMode ? 'Modo Claro' : 'Modo Oscuro'}</span>
        </button>
        <button
          onClick={onLogout}
          className="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm hover:bg-red-100 dark:hover:bg-red-900/30 hover:text-red-600 dark:hover:text-red-400 transition-colors"
        >
          <LogOut size={18} className="shrink-0" />
          <span>Cerrar Sesión</span>
        </button>
      </div>
    </aside>
  );
}
