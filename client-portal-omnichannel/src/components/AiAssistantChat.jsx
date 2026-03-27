import { useState, useRef, useEffect, useMemo, useCallback } from 'react';
import { X, Send, Loader2, Trash2, Plus, Search, Clock, ChevronLeft, MessageCircle } from 'lucide-react';
import { aiAssistantChat, getIsAdmin, getIsAgent, getAgentData } from '../api';

const STORAGE_KEY = 'omni_ai_chats';
const MAX_CHATS = 30;

function generateId() {
  return Date.now().toString(36) + Math.random().toString(36).slice(2, 7);
}

function loadChats() {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    return raw ? JSON.parse(raw) : [];
  } catch { return []; }
}

function saveChats(chats) {
  try {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(chats.slice(0, MAX_CHATS)));
  } catch {}
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

// ─── Cute Animated Robot SVG ─────────────────────────────────
function RobotIcon({ size = 28, className = '', animate = true }) {
  return (
    <svg width={size} height={size} viewBox="0 0 80 80" fill="none" className={className}>
      {/* Glow behind head */}
      <circle cx="40" cy="36" r="26" fill="currentColor" opacity="0.06">
        {animate && <animate attributeName="r" values="26;28;26" dur="3s" repeatCount="indefinite" />}
      </circle>
      {/* Antenna stem */}
      <line x1="40" y1="8" x2="40" y2="18" stroke="currentColor" strokeWidth="2" strokeLinecap="round" opacity="0.6">
        {animate && <animate attributeName="y1" values="8;6;8" dur="2.5s" repeatCount="indefinite" />}
      </line>
      {/* Antenna ball */}
      <circle cx="40" cy="7" r="3.5" fill="currentColor">
        {animate && (
          <>
            <animate attributeName="r" values="3.5;4.5;3.5" dur="2.5s" repeatCount="indefinite" />
            <animate attributeName="fill-opacity" values="1;0.5;1" dur="2.5s" repeatCount="indefinite" />
          </>
        )}
      </circle>
      {/* Head — rounded friendly */}
      <rect x="16" y="18" width="48" height="34" rx="14" fill="currentColor" opacity="0.12" stroke="currentColor" strokeWidth="2" />
      {/* Left ear */}
      <rect x="8" y="28" width="7" height="14" rx="3.5" fill="currentColor" opacity="0.2" stroke="currentColor" strokeWidth="1.5">
        {animate && <animate attributeName="opacity" values="0.2;0.4;0.2" dur="3s" repeatCount="indefinite" />}
      </rect>
      {/* Right ear */}
      <rect x="65" y="28" width="7" height="14" rx="3.5" fill="currentColor" opacity="0.2" stroke="currentColor" strokeWidth="1.5">
        {animate && <animate attributeName="opacity" values="0.2;0.4;0.2" dur="3s" repeatCount="indefinite" />}
      </rect>
      {/* Left eye — big & cute */}
      <ellipse cx="30" cy="34" rx="6" ry="6.5" fill="currentColor">
        {animate && <animate attributeName="ry" values="6.5;6;6.5" dur="3s" repeatCount="indefinite" />}
      </ellipse>
      {/* Left eye highlight */}
      <circle cx="27.5" cy="31.5" r="2.5" fill="white" opacity="0.85" />
      <circle cx="32" cy="33" r="1" fill="white" opacity="0.5" />
      {/* Right eye — big & cute */}
      <ellipse cx="50" cy="34" rx="6" ry="6.5" fill="currentColor">
        {animate && <animate attributeName="ry" values="6.5;6;6.5" dur="3s" repeatCount="indefinite" />}
      </ellipse>
      {/* Right eye highlight */}
      <circle cx="47.5" cy="31.5" r="2.5" fill="white" opacity="0.85" />
      <circle cx="52" cy="33" r="1" fill="white" opacity="0.5" />
      {/* Blushing cheeks */}
      <ellipse cx="22" cy="40" rx="4" ry="2.5" fill="currentColor" opacity="0.1" />
      <ellipse cx="58" cy="40" rx="4" ry="2.5" fill="currentColor" opacity="0.1" />
      {/* Happy mouth */}
      <path d="M 32 43 Q 40 50 48 43" stroke="currentColor" strokeWidth="2.2" strokeLinecap="round" fill="none">
        {animate && <animate attributeName="d" values="M 32 43 Q 40 50 48 43;M 33 44 Q 40 48 47 44;M 32 43 Q 40 50 48 43" dur="4s" repeatCount="indefinite" />}
      </path>
      {/* Neck */}
      <rect x="36" y="52" width="8" height="4" rx="2" fill="currentColor" opacity="0.2" />
      {/* Body — rounded */}
      <rect x="24" y="56" width="32" height="16" rx="6" fill="currentColor" opacity="0.1" stroke="currentColor" strokeWidth="1.5" />
      {/* Body buttons */}
      <circle cx="35" cy="64" r="2" fill="currentColor" opacity="0.25">
        {animate && <animate attributeName="opacity" values="0.25;0.5;0.25" dur="2s" repeatCount="indefinite" />}
      </circle>
      <circle cx="45" cy="64" r="2" fill="currentColor" opacity="0.25">
        {animate && <animate attributeName="opacity" values="0.25;0.5;0.25" dur="2s" begin="0.5s" repeatCount="indefinite" />}
      </circle>
      {/* Heart on chest */}
      <path d="M 40 60 C 38 58 35 58.5 35 61 C 35 63 40 66 40 66 C 40 66 45 63 45 61 C 45 58.5 42 58 40 60 Z" fill="currentColor" opacity="0.3">
        {animate && <animate attributeName="opacity" values="0.3;0.5;0.3" dur="1.5s" repeatCount="indefinite" />}
      </path>
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
    <div className="fixed bottom-4 right-4 sm:bottom-5 sm:right-5 z-50 flex items-end gap-2">
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
        className="relative w-14 h-14 sm:w-16 sm:h-16 rounded-full bg-gradient-to-br from-indigo-500 via-purple-500 to-fuchsia-500 text-white shadow-lg hover:shadow-2xl hover:scale-110 active:scale-95 transition-all flex items-center justify-center group"
        title="Omni Asistente IA"
      >
        {/* Pulse rings */}
        <span className="absolute inset-0 rounded-full bg-gradient-to-br from-indigo-400 to-fuchsia-400 animate-ping opacity-20" />
        <span className="absolute inset-[-4px] rounded-full border-2 border-purple-300/30 animate-pulse" />
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
            className="w-full pl-8 pr-3 py-2 text-xs rounded-lg border border-gray-200 dark:border-slate-600 bg-gray-50 dark:bg-slate-900 text-gray-700 dark:text-gray-200 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-400"
          />
        </div>
      </div>

      {/* New Chat */}
      <div className="px-3 pb-2 shrink-0">
        <button
          onClick={onNew}
          className="w-full flex items-center gap-2 px-3 py-2 rounded-lg text-xs font-medium text-white bg-gradient-to-r from-indigo-500 to-purple-500 hover:from-indigo-600 hover:to-purple-600 transition-all shadow-sm"
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
              className="group flex items-start gap-2 px-2.5 py-2 rounded-lg hover:bg-indigo-50 dark:hover:bg-indigo-900/20 cursor-pointer transition-colors border border-transparent hover:border-indigo-200 dark:hover:border-indigo-800"
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
export default function AiAssistantChat() {
  const [isOpen, setIsOpen] = useState(false);
  const [chats, setChats] = useState(loadChats);
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

  const fullName = useMemo(getUserName, []);
  const firstName = useMemo(() => getFirstName(fullName), [fullName]);

  const activeChat = chats.find(c => c.id === activeChatId);
  const messages = activeChat?.messages || [];

  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages, loading]);

  useEffect(() => {
    if (isOpen && !showHistory) setTimeout(() => inputRef.current?.focus(), 200);
  }, [isOpen, showHistory, activeChatId]);

  const updateChats = useCallback((fn) => {
    setChats(prev => {
      const next = fn(prev);
      saveChats(next);
      return next;
    });
  }, []);

  function startNewChat() {
    const newChat = { id: generateId(), messages: [], createdAt: Date.now(), updatedAt: Date.now() };
    updateChats(prev => [newChat, ...prev]);
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
      updateChats(prev => [newChat, ...prev]);
      chatId = newChat.id;
      setActiveChatId(chatId);
    }

    setInput('');
    setError(null);

    const userMsg = { role: 'user', content: text };
    updateChats(prev => prev.map(c =>
      c.id === chatId ? { ...c, messages: [...c.messages, userMsg], updatedAt: Date.now() } : c
    ));

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
        ));
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

  if (!isOpen) {
    return <FloatingButton onClick={handleOpen} hasChats={chats.length > 0} firstName={firstName} />;
  }

  return (
    <div className="fixed inset-4 sm:inset-auto sm:bottom-5 sm:right-5 z-50 sm:w-[400px] sm:h-[580px] sm:max-h-[calc(100vh-6rem)] flex flex-col bg-white dark:bg-slate-800 rounded-2xl shadow-2xl border border-gray-200 dark:border-slate-700 overflow-hidden"
      style={{ animation: 'omniSlideUp 0.3s ease-out' }}>

      {/* Header */}
      <div className="flex items-center gap-2.5 px-3 py-2.5 bg-gradient-to-r from-indigo-500 via-purple-500 to-fuchsia-500 text-white shrink-0">
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
          <p className="text-[10px] text-indigo-100 leading-tight">{showHistory ? `${chats.length} conversaciones` : 'IA con datos de tu empresa'}</p>
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
                <div className="w-20 h-20 sm:w-24 sm:h-24 rounded-full bg-gradient-to-br from-indigo-50 via-purple-50 to-fuchsia-50 dark:from-indigo-900/30 dark:via-purple-900/30 dark:to-fuchsia-900/30 flex items-center justify-center shadow-inner">
                  <RobotIcon size={52} className="text-indigo-500 dark:text-indigo-400" />
                </div>
                <div>
                  <p className="font-bold text-sm sm:text-base text-gray-800 dark:text-gray-100">{greeting}, {firstName}! 👋</p>
                  <p className="text-xs text-gray-500 dark:text-gray-400 mt-1">Soy tu <span className="font-semibold text-indigo-500">Omni Asistente</span>. Puedo ayudarte con conversaciones, agentes, canales, tickets y más.</p>
                </div>
                <div className="grid grid-cols-1 gap-1.5 w-full mt-1">
                  {[
                    '¿Cuántas conversaciones abiertas tengo?',
                    '¿Cuál es mi tasa de resolución?',
                    'Resumen de actividad de agentes',
                    '¿Qué tickets están pendientes?',
                  ].map((q, i) => (
                    <button
                      key={i}
                      onClick={() => { setInput(q); setTimeout(() => inputRef.current?.focus(), 50); }}
                      className="text-left text-[11px] sm:text-xs px-3 py-2 rounded-lg bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 text-gray-600 dark:text-gray-300 hover:border-indigo-300 dark:hover:border-indigo-600 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition-colors"
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
                  <div className="w-6 h-6 rounded-full bg-gradient-to-br from-indigo-100 to-purple-100 dark:from-indigo-900/40 dark:to-purple-900/40 flex items-center justify-center shrink-0 mb-0.5">
                    <RobotIcon size={15} className="text-indigo-500 dark:text-indigo-400" animate={false} />
                  </div>
                )}
                <div className={`max-w-[82%] sm:max-w-[80%] px-3 py-2 rounded-2xl text-[13px] leading-relaxed whitespace-pre-wrap ${
                  msg.role === 'user'
                    ? 'bg-gradient-to-br from-indigo-500 to-purple-500 text-white rounded-br-md shadow-sm'
                    : 'bg-white dark:bg-slate-800 text-gray-800 dark:text-gray-200 border border-gray-200 dark:border-slate-700 rounded-bl-md shadow-sm'
                }`}>
                  {msg.content}
                </div>
              </div>
            ))}

            {loading && (
              <div className="flex justify-start items-end gap-1.5">
                <div className="w-6 h-6 rounded-full bg-gradient-to-br from-indigo-100 to-purple-100 dark:from-indigo-900/40 dark:to-purple-900/40 flex items-center justify-center shrink-0 mb-0.5">
                  <RobotIcon size={15} className="text-indigo-500 dark:text-indigo-400" animate={false} />
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
                className="flex-1 min-w-0 resize-none rounded-xl border border-gray-300 dark:border-slate-600 bg-gray-50 dark:bg-slate-900 px-3 py-2 text-sm text-gray-800 dark:text-gray-200 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent max-h-24"
                style={{ lineHeight: '1.4' }}
                onInput={e => { e.target.style.height = 'auto'; e.target.style.height = Math.min(e.target.scrollHeight, 96) + 'px'; }}
                disabled={loading}
              />
              <button
                onClick={handleSend}
                disabled={!input.trim() || loading}
                className="shrink-0 w-10 h-10 sm:w-9 sm:h-9 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-500 hover:from-indigo-600 hover:to-purple-600 disabled:from-gray-300 disabled:to-gray-300 dark:disabled:from-slate-600 dark:disabled:to-slate-600 text-white flex items-center justify-center transition-all"
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
