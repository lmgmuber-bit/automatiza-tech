import { useState, useRef, useEffect, useMemo, useCallback } from 'react';
import { X, Send, Loader2, Trash2, Plus, Search, Clock, ChevronLeft, MessageCircle } from 'lucide-react';
import { aiAssistantChat, getIsAdmin, getIsAgent, getAgentData, getAiChatHistory, saveAiChatHistory, deleteAiChatHistory } from '../api';

const LEGACY_STORAGE_KEY = 'omni_ai_chats'; // kept only for one-time migration
const MAX_CHATS = 30;

// Simple markdown-to-text cleaner: strips **, ##, ### but keeps structure
function cleanMarkdown(text) {
  if (!text) return text;
  return text
    .replace(/^#{1,3}\s+/gm, '')       // Remove ### headers
    .replace(/\*\*([^*]+)\*\*/g, '$1')  // Remove **bold**
    .replace(/\*([^*]+)\*/g, '$1')      // Remove *italic*
    .replace(/`([^`]+)`/g, '$1');       // Remove `code`
}

function generateId() {
  return Date.now().toString(36) + Math.random().toString(36).slice(2, 7);
}

/** Reads chats from old localStorage key (one-time migration). */
function loadLegacyChats() {
  try {
    const raw = localStorage.getItem(LEGACY_STORAGE_KEY);
    return raw ? JSON.parse(raw) : [];
  } catch { return []; }
}

function getUserName() {
  if (getIsAgent()) {
    const d = getAgentData();
    return d?.name || 'Usuario';
  }
  if (getIsAdmin()) {
    return localStorage.getItem('omni_admin_user') || 'Admin';
  }
  return 'Usuario';
}

function getFirstName(fullName) {
  return (fullName || 'Usuario').split(' ')[0];
}

function chatTitle(chat) {
  const firstUser = chat.messages.find(m => m.role === 'user');
  if (firstUser) {
    const t = firstUser.content.slice(0, 50);
    return t.length < firstUser.content.length ? t + '…' : t;
  }
  return 'Nuevo chat';
}

function timeAgo(ts) {
  const diff = Date.now() - ts;
  const mins = Math.floor(diff / 60000);
  if (mins < 1) return 'ahora';
  if (mins < 60) return `${mins}m`;
  const hrs = Math.floor(mins / 60);
  if (hrs < 24) return `${hrs}h`;
  const days = Math.floor(hrs / 24);
  return `${days}d`;
}

// ─── Omni Robot SVG — Tech's green brother ──────────────────
function RobotIcon({ size = 28, className = '', animate = true }) {
  return (
    <svg width={size} height={size} viewBox="0 0 80 80" fill="none" className={className} style={{ overflow: 'visible' }}>
      <defs>
        <linearGradient id="omniHead" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="#34d399" />
          <stop offset="100%" stopColor="#059669" />
        </linearGradient>
        <linearGradient id="omniBody" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="#064e3b" />
          <stop offset="100%" stopColor="#022c22" />
        </linearGradient>
        <linearGradient id="omniVisor" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stopColor="#022c22" />
          <stop offset="100%" stopColor="#064e3b" />
        </linearGradient>
        <radialGradient id="omniEyeGlow" cx="50%" cy="50%" r="50%">
          <stop offset="0%" stopColor="#a7f3d0" />
          <stop offset="100%" stopColor="#6ee7b7" stopOpacity="0" />
        </radialGradient>
        <filter id="omniGlow" x="-30%" y="-30%" width="160%" height="160%">
          <feGaussianBlur in="SourceGraphic" stdDeviation="1.5" result="b" />
          <feMerge><feMergeNode in="b" /><feMergeNode in="SourceGraphic" /></feMerge>
        </filter>
      </defs>

      {/* Glow aura */}
      <circle cx="40" cy="36" r="30" fill="#34d399" opacity="0.06">
        {animate && <animate attributeName="r" values="30;33;30" dur="3s" repeatCount="indefinite" />}
      </circle>

      {/* Antenna */}
      <rect x="38.5" y="6" width="3" height="12" rx="1.5" fill="#059669">
        {animate && <animate attributeName="y" values="6;4;6" dur="2.5s" repeatCount="indefinite" />}
      </rect>
      <circle cx="40" cy="5" r="4" fill="#34d399">
        {animate && <>
          <animate attributeName="r" values="4;5;4" dur="2s" repeatCount="indefinite" />
          <animate attributeName="fill-opacity" values="1;0.5;1" dur="2s" repeatCount="indefinite" />
        </>}
      </circle>
      {/* Antenna glow ring */}
      <circle cx="40" cy="5" r="7" fill="none" stroke="#6ee7b7" strokeWidth="0.8" opacity="0.3">
        {animate && <animate attributeName="r" values="7;9;7" dur="2s" repeatCount="indefinite" />}
      </circle>

      {/* Head shell */}
      <rect x="14" y="16" width="52" height="38" rx="16" fill="url(#omniHead)" />
      {/* Head shine */}
      <rect x="14" y="16" width="52" height="18" rx="16" fill="white" opacity="0.15" />

      {/* Headphones — left */}
      <ellipse cx="12" cy="34" rx="6" ry="9" fill="#064e3b" />
      <ellipse cx="12" cy="34" rx="4" ry="7" fill="#0f5132" />
      <circle cx="12" cy="34" r="2.5" fill="#34d399" opacity="0.4">
        {animate && <animate attributeName="opacity" values="0.4;0.7;0.4" dur="2.5s" repeatCount="indefinite" />}
      </circle>
      {/* Headphones — right */}
      <ellipse cx="68" cy="34" rx="6" ry="9" fill="#064e3b" />
      <ellipse cx="68" cy="34" rx="4" ry="7" fill="#0f5132" />
      <circle cx="68" cy="34" r="2.5" fill="#34d399" opacity="0.4">
        {animate && <animate attributeName="opacity" values="0.4;0.7;0.4" dur="2.5s" repeatCount="indefinite" />}
      </circle>

      {/* Visor / face screen */}
      <rect x="20" y="22" width="40" height="26" rx="10" fill="url(#omniVisor)" />
      {/* Screen reflection */}
      <rect x="22" y="24" width="18" height="5" rx="2.5" fill="white" opacity="0.05" />

      {/* Eyes — LED style */}
      <g filter="url(#omniGlow)">
        {/* Left eye */}
        <ellipse cx="32" cy="34" rx="6" ry="7" fill="#6ee7b7" opacity="0.9">
          {animate && <animate attributeName="ry" values="7;1;7" dur="4s" begin="0s" repeatCount="indefinite" keyTimes="0;0.04;0.08;1" values="7;1;7;7" />}
        </ellipse>
        <ellipse cx="32" cy="34" rx="3.5" ry="4" fill="white" opacity="0.4" />
        {/* Eye scan lines */}
        <line x1="27" y1="33" x2="37" y2="33" stroke="#34d399" strokeWidth="0.4" opacity="0.3" />
        <line x1="27" y1="35" x2="37" y2="35" stroke="#34d399" strokeWidth="0.4" opacity="0.3" />

        {/* Right eye */}
        <ellipse cx="48" cy="34" rx="6" ry="7" fill="#6ee7b7" opacity="0.9">
          {animate && <animate attributeName="ry" values="7;1;7" dur="4s" begin="0s" repeatCount="indefinite" keyTimes="0;0.04;0.08;1" values="7;1;7;7" />}
        </ellipse>
        <ellipse cx="48" cy="34" rx="3.5" ry="4" fill="white" opacity="0.4" />
        <line x1="43" y1="33" x2="53" y2="33" stroke="#34d399" strokeWidth="0.4" opacity="0.3" />
        <line x1="43" y1="35" x2="53" y2="35" stroke="#34d399" strokeWidth="0.4" opacity="0.3" />
      </g>

      {/* Smile — LED glow */}
      <path d="M 32 44 Q 40 50 48 44" stroke="#6ee7b7" strokeWidth="1.8" strokeLinecap="round" fill="none" filter="url(#omniGlow)">
        {animate && <animate attributeName="d" values="M 32 44 Q 40 50 48 44;M 33 45 Q 40 48 47 45;M 32 44 Q 40 50 48 44" dur="4s" repeatCount="indefinite" />}
      </path>

      {/* Neck */}
      <rect x="35" y="54" width="10" height="5" rx="2" fill="#064e3b" />
      <rect x="34" y="55.5" width="12" height="2" rx="1" fill="#0f5132" />

      {/* Body */}
      <rect x="22" y="58" width="36" height="18" rx="8" fill="url(#omniBody)" />
      {/* Shoulder accent */}
      <rect x="22" y="58" width="36" height="4" rx="2" fill="#34d399" opacity="0.3" />
      {/* Body shine */}
      <rect x="22" y="58" width="36" height="9" rx="8" fill="white" opacity="0.06" />

      {/* Chest — "O" for Omni */}
      <circle cx="40" cy="67" r="5" fill="none" stroke="#34d399" strokeWidth="1.2" opacity="0.6">
        {animate && <animate attributeName="opacity" values="0.6;1;0.6" dur="2s" repeatCount="indefinite" />}
      </circle>
      <circle cx="40" cy="67" r="2.5" fill="#34d399" opacity="0.4">
        {animate && <animate attributeName="r" values="2.5;3;2.5" dur="2s" repeatCount="indefinite" />}
      </circle>

      {/* Arms */}
      <rect x="6" y="60" width="14" height="7" rx="3.5" fill="#064e3b">
        {animate && <animate attributeName="y" values="60;58;60" dur="3s" repeatCount="indefinite" />}
      </rect>
      <circle cx="7" cy="63.5" r="4" fill="#0f5132" />
      <circle cx="7" cy="63.5" r="2" fill="#34d399" opacity="0.2" />

      <rect x="60" y="60" width="14" height="7" rx="3.5" fill="#064e3b">
        {animate && <animate attributeName="y" values="60;58;60" dur="3s" begin="0.5s" repeatCount="indefinite" />}
      </rect>
      <circle cx="73" cy="63.5" r="4" fill="#0f5132" />
      <circle cx="73" cy="63.5" r="2" fill="#34d399" opacity="0.2" />
    </svg>
  );
}

// ─── Rotating Tooltip Messages ───────────────────────────────
function useRotatingTooltip(firstName) {
  const messages = useMemo(() => [
    `¡Hola ${firstName}! ¿Tienes alguna duda? 💡`,
    `${firstName}, puedo darte info de tu empresa 📊`,
    `¿Dudas del portal OmniCliente? ¡Pregúntame! 🎯`,
    `${firstName}, consulta tus métricas conmigo ✨`,
    `¿Necesitas ayuda con el portal? Puedo guiarte 🤖`,
    `${firstName}, revisa tus tickets o conversaciones 📋`,
    `¡Pregúntame lo que quieras, ${firstName}! 🚀`,
    `${firstName}, antes de crear un ticket ¡consúltame! 💬`,
  ], [firstName]);

  const [index, setIndex] = useState(0);
  const [visible, setVisible] = useState(false);

  useEffect(() => {
    // Show first tooltip after 5s
    const firstShow = setTimeout(() => setVisible(true), 5000);
    // Then cycle every 12s: show for 6s, hide for 6s
    const cycle = setInterval(() => {
      setVisible(false);
      setTimeout(() => {
        setIndex(prev => (prev + 1) % messages.length);
        setVisible(true);
      }, 800);
    }, 12000);
    return () => { clearTimeout(firstShow); clearInterval(cycle); };
  }, [messages.length]);

  return { text: messages[index], visible };
}

// ─── Floating Robot Button with tooltip ──────────────────────
function FloatingButton({ onClick, hasChats, firstName }) {
  const tooltip = useRotatingTooltip(firstName);

  return (
    <div className="fixed right-4 sm:right-5 z-[55] flex items-end gap-2 transition-all duration-300 bottom-3 sm:bottom-4">
      {/* Tooltip bubble */}
      <div className={`max-w-[200px] sm:max-w-[220px] px-3 py-2 rounded-xl rounded-br-sm bg-white dark:bg-slate-800 shadow-lg border border-gray-200 dark:border-slate-700 transition-all duration-500 ${
        tooltip.visible ? 'opacity-100 translate-y-0 scale-100' : 'opacity-0 translate-y-2 scale-95 pointer-events-none'
      }`}>
        <p className="text-[11px] sm:text-xs text-gray-700 dark:text-gray-200 leading-snug">{tooltip.text}</p>
        {/* Tail arrow */}
        <div className="absolute -right-1.5 bottom-2 w-3 h-3 bg-white dark:bg-slate-800 border-r border-b border-gray-200 dark:border-slate-700 rotate-[-45deg]" />
      </div>

      {/* Button */}
      <button
        onClick={onClick}
        className="relative w-14 h-14 sm:w-16 sm:h-16 rounded-full bg-gradient-to-br from-emerald-500 via-green-500 to-teal-500 text-white shadow-lg hover:shadow-2xl hover:scale-110 active:scale-95 transition-all flex items-center justify-center group"
        title="Omni Asistente IA"
      >
        {/* Pulse rings */}
        <span className="absolute inset-0 rounded-full bg-gradient-to-br from-emerald-400 to-teal-400 animate-ping opacity-20" />
        <span className="absolute inset-[-4px] rounded-full border-2 border-green-300/30 animate-pulse" />
        <RobotIcon size={32} className="relative z-10 drop-shadow-sm group-hover:scale-110 transition-transform" />
        {hasChats && (
          <span className="absolute -top-0.5 -right-0.5 w-3.5 h-3.5 rounded-full bg-green-400 border-2 border-white shadow-sm" />
        )}
      </button>
    </div>
  );
}

// ─── History Panel ───────────────────────────────────────────
function HistoryPanel({ chats, onSelect, onNew, onDelete, searchQuery, onSearchChange }) {
  const filtered = useMemo(() => {
    if (!searchQuery.trim()) return chats;
    const q = searchQuery.toLowerCase();
    return chats.filter(c =>
      c.messages.some(m => m.content.toLowerCase().includes(q))
    );
  }, [chats, searchQuery]);

  return (
    <div className="flex flex-col h-full">
      {/* Search */}
      <div className="px-3 pt-3 pb-2 shrink-0">
        <div className="relative">
          <Search className="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-gray-400" />
          <input
            type="text"
            value={searchQuery}
            onChange={e => onSearchChange(e.target.value)}
            placeholder="Buscar en historial..."
            className="w-full pl-8 pr-3 py-2 text-xs rounded-lg border border-gray-200 dark:border-slate-600 bg-gray-50 dark:bg-slate-900 text-gray-700 dark:text-gray-200 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-emerald-400"
          />
        </div>
      </div>

      {/* New Chat */}
      <div className="px-3 pb-2 shrink-0">
        <button
          onClick={onNew}
          className="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-medium text-white bg-gradient-to-r from-emerald-500 to-green-600 hover:from-emerald-600 hover:to-green-700 transition-all shadow-sm"
        >
          <Plus className="w-3.5 h-3.5" />
          Nuevo chat
        </button>
      </div>

      {/* Chat list */}
      <div className="flex-1 overflow-y-auto px-3 pb-3 space-y-1">
        {filtered.length === 0 ? (
          <div className="text-center py-8">
            <MessageCircle className="w-8 h-8 mx-auto text-gray-300 dark:text-slate-600 mb-2" />
            <p className="text-xs text-gray-400 dark:text-gray-500">
              {searchQuery ? 'Sin resultados' : 'Sin conversaciones aún'}
            </p>
          </div>
        ) : (
          filtered.map(chat => (
            <div
              key={chat.id}
              className="group flex items-start gap-2 px-2.5 py-2 rounded-lg hover:bg-emerald-50 dark:hover:bg-emerald-900/20 cursor-pointer transition-colors border border-transparent hover:border-emerald-200 dark:hover:border-emerald-800"
              onClick={() => onSelect(chat.id)}
            >
              <div className="flex-1 min-w-0">
                <p className="text-xs font-medium text-gray-700 dark:text-gray-200 truncate">{chatTitle(chat)}</p>
                <div className="flex items-center gap-1.5 mt-0.5">
                  <Clock className="w-2.5 h-2.5 text-gray-400" />
                  <span className="text-[10px] text-gray-400">{timeAgo(chat.updatedAt)}</span>
                  <span className="text-[10px] text-gray-400">• {chat.messages.length} msgs</span>
                </div>
              </div>
              <button
                onClick={e => { e.stopPropagation(); onDelete(chat.id); }}
                className="shrink-0 p-1 rounded opacity-0 group-hover:opacity-100 hover:bg-red-100 dark:hover:bg-red-900/30 text-gray-400 hover:text-red-500 transition-all"
                title="Eliminar"
              >
                <Trash2 className="w-3 h-3" />
              </button>
            </div>
          ))
        )}
      </div>
    </div>
  );
}

// ─── Main Export ─────────────────────────────────────────────
export default function AiAssistantChat({ currentView }) {
  const [isOpen, setIsOpen] = useState(false);
  const [chats, setChats] = useState([]);
  const [chatsLoaded, setChatsLoaded] = useState(false);
  const [activeChatId, setActiveChatId] = useState(null);
  const [showHistory, setShowHistory] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [input, setInput] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState(null);
  const messagesEndRef = useRef(null);
  const inputRef = useRef(null);

  // Listen for external "open Omni" events (e.g. from SupportView intercept)
  useEffect(() => {
    const handler = () => { setIsOpen(true); setShowHistory(false); };
    window.addEventListener('openOmniAssistant', handler);
    return () => window.removeEventListener('openOmniAssistant', handler);
  }, []);

  // Load chats from backend on mount; migrate legacy localStorage once
  useEffect(() => {
    getAiChatHistory()
      .then(data => {
        const serverChats = data?.chats || [];
        if (serverChats.length === 0) {
          // One-time migration: move old localStorage chats to backend
          const legacy = loadLegacyChats();
          if (legacy.length > 0) {
            const limited = legacy.slice(0, MAX_CHATS);
            setChats(limited);
            Promise.all(limited.map(c => saveAiChatHistory(c).catch(() => {})));
            localStorage.removeItem(LEGACY_STORAGE_KEY);
            return;
          }
        }
        setChats(serverChats);
      })
      .catch(() => {
        // Fallback to legacy if backend unreachable
        setChats(loadLegacyChats());
      })
      .finally(() => setChatsLoaded(true));
  }, []);


  const firstName = useMemo(() => getFirstName(getUserName()), []);

  const activeChat = chats.find(c => c.id === activeChatId);
  const messages = activeChat?.messages || [];

  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages, loading]);

  useEffect(() => {
    if (isOpen && !showHistory) setTimeout(() => inputRef.current?.focus(), 200);
  }, [isOpen, showHistory, activeChatId]);

  const updateChats = useCallback((fn, chatIdToSave) => {
    setChats(prev => {
      const next = fn(prev);
      if (chatIdToSave) {
        const chatToSave = next.find(c => c.id === chatIdToSave);
        if (chatToSave) saveAiChatHistory(chatToSave).catch(() => {});
      }
      return next;
    });
  }, []);

  function startNewChat() {
    const newChat = { id: generateId(), messages: [], createdAt: Date.now(), updatedAt: Date.now() };
    updateChats(prev => [newChat, ...prev], newChat.id);
    setActiveChatId(newChat.id);
    setShowHistory(false);
    setError(null);
    setInput('');
  }

  function openChat(id) {
    setActiveChatId(id);
    setShowHistory(false);
    setError(null);
  }

  function deleteChat(id) {
    updateChats(prev => prev.filter(c => c.id !== id));
    deleteAiChatHistory(id).catch(() => {});
    if (activeChatId === id) setActiveChatId(null);
  }

  function handleOpen() {
    setIsOpen(true);
    if (chats.length > 0 && !activeChatId) {
      setActiveChatId(chats[0].id);
    } else if (chats.length === 0) {
      startNewChat();
    }
  }

  async function handleSend() {
    const text = input.trim();
    if (!text || loading) return;

    let chatId = activeChatId;
    if (!chatId) {
      const newChat = { id: generateId(), messages: [], createdAt: Date.now(), updatedAt: Date.now() };
      updateChats(prev => [newChat, ...prev], newChat.id);
      chatId = newChat.id;
      setActiveChatId(chatId);
    }

    setInput('');
    setError(null);

    const userMsg = { role: 'user', content: text };
    updateChats(prev => prev.map(c =>
      c.id === chatId ? { ...c, messages: [...c.messages, userMsg], updatedAt: Date.now() } : c
    ), chatId);

    setLoading(true);
    try {
      const currentChat = chats.find(c => c.id === chatId);
      const history = (currentChat?.messages || []).map(m => ({ role: m.role, content: m.content }));
      const data = await aiAssistantChat(text, history);
      if (data.error) {
        setError(data.error);
      } else {
        const assistantMsg = { role: 'assistant', content: data.reply };
        updateChats(prev => prev.map(c =>
          c.id === chatId ? { ...c, messages: [...c.messages, assistantMsg], updatedAt: Date.now() } : c
        ), chatId);
      }
    } catch (err) {
      setError(err.message || 'Error al comunicarse con el asistente');
    } finally {
      setLoading(false);
    }
  }

  function handleKeyDown(e) {
    if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); handleSend(); }
  }

  // Greeting based on time of day
  const greeting = useMemo(() => {
    const h = new Date().getHours();
    if (h < 12) return '¡Buenos días';
    if (h < 19) return '¡Buenas tardes';
    return '¡Buenas noches';
  }, []);

  // Hide OmniAssistant entirely when in inbox/chat view
  if (currentView === 'inbox') return null;

  if (!isOpen) {
    return <FloatingButton onClick={handleOpen} hasChats={chats.length > 0} firstName={firstName} />;
  }

  return (
    <div className="fixed inset-4 sm:inset-auto sm:bottom-5 sm:right-5 z-50 sm:w-[400px] sm:h-[580px] sm:max-h-[calc(100vh-6rem)] flex flex-col bg-white dark:bg-slate-800 rounded-2xl shadow-2xl border border-gray-200 dark:border-slate-700 overflow-hidden"
      style={{ animation: 'omniSlideUp 0.3s ease-out' }}>

      {/* Header */}
      <div className="flex items-center gap-2.5 px-3 py-2.5 bg-gradient-to-r from-emerald-500 via-green-500 to-teal-500 text-white shrink-0">
        {showHistory ? (
          <button onClick={() => setShowHistory(false)} className="p-1 rounded-lg hover:bg-white/20 transition-colors">
            <ChevronLeft className="w-5 h-5" />
          </button>
        ) : (
          <div className="w-9 h-9 sm:w-8 sm:h-8 rounded-full bg-white/20 flex items-center justify-center shrink-0">
            <RobotIcon size={22} animate={false} />
          </div>
        )}
        <div className="flex-1 min-w-0">
          <h3 className="font-semibold text-sm leading-tight">{showHistory ? 'Historial' : 'Omni Asistente'}</h3>
          <p className="text-[10px] text-emerald-100 leading-tight">{showHistory ? `${chats.length} conversaciones` : 'IA con datos de tu empresa'}</p>
        </div>
        {!showHistory && (
          <>
            <button onClick={() => { setShowHistory(true); setSearchQuery(''); }} className="p-1.5 rounded-lg hover:bg-white/20 transition-colors" title="Historial">
              <Clock className="w-4 h-4" />
            </button>
            <button onClick={startNewChat} className="p-1.5 rounded-lg hover:bg-white/20 transition-colors" title="Nuevo chat">
              <Plus className="w-4 h-4" />
            </button>
          </>
        )}
        <button onClick={() => setIsOpen(false)} className="p-1.5 rounded-lg hover:bg-white/20 transition-colors" title="Cerrar">
          <X className="w-4 h-4" />
        </button>
      </div>

      {showHistory ? (
        <HistoryPanel
          chats={chats}
          onSelect={openChat}
          onNew={startNewChat}
          onDelete={deleteChat}
          searchQuery={searchQuery}
          onSearchChange={setSearchQuery}
        />
      ) : (
        <>
          {/* Messages */}
          <div className="flex-1 overflow-y-auto px-3 sm:px-4 py-3 space-y-3 bg-gray-50 dark:bg-slate-900/50">
            {messages.length === 0 && !loading && (
              <div className="flex flex-col items-center justify-center h-full text-center px-2 sm:px-4 gap-3">
                <div className="w-20 h-20 sm:w-24 sm:h-24 rounded-full bg-gradient-to-br from-emerald-50 via-green-50 to-teal-50 dark:from-emerald-900/30 dark:via-green-900/30 dark:to-teal-900/30 flex items-center justify-center shadow-inner">
                  <RobotIcon size={52} className="text-emerald-600 dark:text-emerald-400" />
                </div>
                <div>
                  <p className="font-bold text-sm sm:text-base text-gray-800 dark:text-gray-100">{greeting}, {firstName}! 👋</p>
                  <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">Soy tu <span className="font-semibold text-emerald-600">Omni Asistente</span>. Puedo ayudarte con conversaciones, agentes, canales, tickets y más.</p>
                </div>
                <div className="grid grid-cols-1 gap-1.5 w-full mt-1">
                  {[
                    '¿Cuántas conversaciones abiertas/asignadas tengo?',
                    '¿Cuál es mi tasa de resolución?',
                    'Resumen de actividad de agentes',
                    '¿Qué tickets están pendientes?',
                  ].map((q, i) => (
                    <button
                      key={i}
                      onClick={() => { setInput(q); setTimeout(() => inputRef.current?.focus(), 50); }}
                      className="text-left text-[11px] sm:text-xs px-3 py-2 rounded-lg bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 text-gray-600 dark:text-gray-300 hover:border-emerald-300 dark:hover:border-emerald-600 hover:bg-emerald-50 dark:hover:bg-emerald-900/20 transition-colors"
                    >
                      {q}
                    </button>
                  ))}
                </div>
              </div>
            )}

            {messages.map((msg, i) => (
              <div key={i} className={`flex ${msg.role === 'user' ? 'justify-end' : 'justify-start'} items-end gap-1.5`}>
                {msg.role === 'assistant' && (
                  <div className="w-6 h-6 rounded-full bg-gradient-to-br from-emerald-100 to-green-100 dark:from-emerald-900/40 dark:to-green-900/40 flex items-center justify-center shrink-0 mb-0.5">
                    <RobotIcon size={15} className="text-emerald-600 dark:text-emerald-400" animate={false} />
                  </div>
                )}
                <div className={`max-w-[82%] sm:max-w-[80%] px-3 py-2 rounded-2xl text-[13px] leading-relaxed whitespace-pre-wrap ${
                  msg.role === 'user'
                    ? 'bg-gradient-to-br from-emerald-500 to-green-600 text-white rounded-br-md shadow-sm'
                    : 'bg-white dark:bg-slate-800 text-gray-800 dark:text-gray-200 border border-gray-200 dark:border-slate-700 rounded-bl-md shadow-sm'
                }`}>
                  {msg.role === 'assistant' ? cleanMarkdown(msg.content) : msg.content}
                </div>
              </div>
            ))}

            {loading && (
              <div className="flex justify-start items-end gap-1.5">
                <div className="w-6 h-6 rounded-full bg-gradient-to-br from-emerald-100 to-green-100 dark:from-emerald-900/40 dark:to-green-900/40 flex items-center justify-center shrink-0 mb-0.5">
                  <RobotIcon size={15} className="text-emerald-600 dark:text-emerald-400" animate={false} />
                </div>
                <div className="px-4 py-3 rounded-2xl rounded-bl-md bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 shadow-sm">
                  <div className="flex items-center gap-2 text-gray-400">
                    <Loader2 className="w-4 h-4 animate-spin" />
                    <span className="text-xs">Analizando datos...</span>
                  </div>
                </div>
              </div>
            )}

            {error && (
              <div className="text-center">
                <p className="text-xs text-red-500 bg-red-50 dark:bg-red-900/20 rounded-lg px-3 py-2 inline-block">{error}</p>
              </div>
            )}
            <div ref={messagesEndRef} />
          </div>

          {/* Input */}
          <div className="shrink-0 px-3 py-2 border-t border-gray-200 dark:border-slate-700 bg-white dark:bg-slate-800">
            <div className="flex items-end gap-2">
              <textarea
                ref={inputRef}
                value={input}
                onChange={e => setInput(e.target.value)}
                onKeyDown={handleKeyDown}
                placeholder={`Pregunta lo que necesites, ${firstName}...`}
                rows={1}
                className="flex-1 min-w-0 resize-none rounded-xl border border-gray-300 dark:border-slate-600 bg-gray-50 dark:bg-slate-900 px-3 py-2 text-sm text-gray-800 dark:text-gray-200 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-emerald-400 focus:border-transparent max-h-24"
                style={{ lineHeight: '1.4' }}
                onInput={e => { e.target.style.height = 'auto'; e.target.style.height = Math.min(e.target.scrollHeight, 96) + 'px'; }}
                disabled={loading}
              />
              <button
                onClick={handleSend}
                disabled={!input.trim() || loading}
                className="shrink-0 w-10 h-10 sm:w-9 sm:h-9 rounded-xl bg-gradient-to-br from-emerald-500 to-green-600 hover:from-emerald-600 hover:to-green-700 disabled:from-gray-300 disabled:to-gray-300 dark:disabled:from-slate-600 dark:disabled:to-slate-600 text-white flex items-center justify-center transition-all"
              >
                <Send className="w-4 h-4" />
              </button>
            </div>
            <p className="text-[9px] text-gray-400 dark:text-gray-500 text-center mt-1">Omni Asistente puede cometer errores. Verifica la información importante.</p>
          </div>
        </>
      )}

      <style>{`
        @keyframes omniSlideUp {
          from { opacity: 0; transform: translateY(16px) scale(0.95); }
          to { opacity: 1; transform: translateY(0) scale(1); }
        }
      `}</style>
    </div>
  );
}
