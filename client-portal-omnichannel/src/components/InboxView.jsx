import { useState, useEffect, useRef, useCallback } from 'react';
import { Search, Send, UserCheck, RotateCcw, Loader2, MessageSquare, ArrowLeft, Eye, EyeOff, ArrowRightLeft, ChevronDown, Download } from 'lucide-react';
import { getConversations, getMessages, sendMessage, takeoverConversation, releaseConversation, transferConversation, getAgents, getIsAdmin, getIsAgent, isSupervisorOrAdmin, getAgentData, exportConversationHistory, formatChileTime } from '../api';
import ChannelBadge from './ChannelBadge';
import ResultModal from './ResultModal';

const POLL_INTERVAL = 5000; // 5 seconds

// Request browser notification permission
function requestNotificationPermission() {
  if ('Notification' in window && Notification.permission === 'default') {
    Notification.requestPermission();
  }
}

function showBrowserNotification(title, body, convId) {
  if ('Notification' in window && Notification.permission === 'granted') {
    try {
      const n = new Notification(title, {
        body,
        icon: './logo-automatiza-tech.png',
        tag: `omni-msg-${convId}`,
        renotify: true,
      });
      n.onclick = () => { window.focus(); n.close(); };
      setTimeout(() => n.close(), 8000);
    } catch { /* silent — SW required on some browsers */ }
  }
}

export default function InboxView() {
  const [conversations, setConversations] = useState([]);
  const [selectedConv, setSelectedConv] = useState(null);
  const [messages, setMessages] = useState([]);
  const [agents, setAgents] = useState([]);
  const [newMessage, setNewMessage] = useState('');
  const [loading, setLoading] = useState(true);
  const [msgLoading, setMsgLoading] = useState(false);
  const [sending, setSending] = useState(false);
  const [filters, setFilters] = useState({ channel_type: '', status: '' });
  const [searchTerm, setSearchTerm] = useState('');
  const [scope, setScope] = useState('all'); // 'all' or 'mine' (for agents)
  const [mobileShowChat, setMobileShowChat] = useState(false);
  const [resultModal, setResultModal] = useState(null);
  const [showAgentDropdown, setShowAgentDropdown] = useState(false);
  const [exportingHistory, setExportingHistory] = useState(false);
  const messagesEndRef = useRef(null);
  const selectedConvRef = useRef(null);
  const prevConvsRef = useRef([]); // previous conversations snapshot for diff
  const seenMsgIdsRef = useRef({}); // { convId: lastSeenMsgTimestamp }
  const pollConversationsRef = useRef(null);
  const pollMessagesRef = useRef(null);
  const isAgentMode = getIsAgent();
  const canSupervisor = isAgentMode && isSupervisorOrAdmin();

  // Keep ref in sync for polling callbacks
  selectedConvRef.current = selectedConv;

  // Request notification permission on mount
  useEffect(() => {
    requestNotificationPermission();
  }, []);

  // Close agent dropdown on outside click
  useEffect(() => {
    if (!showAgentDropdown) return;
    const close = () => setShowAgentDropdown(false);
    const timer = setTimeout(() => document.addEventListener('click', close), 0);
    return () => { clearTimeout(timer); document.removeEventListener('click', close); };
  }, [showAgentDropdown]);

  // Close dropdown when switching conversations
  useEffect(() => { setShowAgentDropdown(false); }, [selectedConv]);

  useEffect(() => {
    loadConversations();
    loadAgents();
  }, [filters, scope]);

  useEffect(() => {
    if (selectedConv) loadMessages(selectedConv.id);
  }, [selectedConv]);

  // Mark current conversation messages as seen when we view them
  useEffect(() => {
    if (selectedConv && messages.length > 0) {
      const lastMsg = messages[messages.length - 1];
      seenMsgIdsRef.current[selectedConv.id] = lastMsg.created_at || lastMsg.id;
    }
  }, [selectedConv, messages]);

  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages]);

  // Auto-polling: refresh conversations + active chat messages every 5s
  useEffect(() => {
    const interval = setInterval(() => {
      pollConversationsRef.current?.();
      if (selectedConvRef.current) {
        pollMessagesRef.current?.(selectedConvRef.current.id);
      }
    }, POLL_INTERVAL);
    return () => clearInterval(interval);
  }, []);

  // Silent poll — no loading spinners
  const pollConversations = useCallback(async () => {
    try {
      const params = { ...filters, search: searchTerm };
      if (isAgentMode && scope === 'mine') params.scope = 'mine';
      const data = await getConversations(params);
      const newConvs = data.data || [];

      // Detect new incoming messages for browser notifications
      const prevMap = {};
      prevConvsRef.current.forEach(c => { prevMap[c.id] = c; });

      let totalUnread = 0;
      newConvs.forEach(c => {
        // Count unread: conversations with newer messages than last seen
        const lastSeen = seenMsgIdsRef.current[c.id];
        if (c.last_message_at && lastSeen && c.last_message_at > lastSeen) {
          totalUnread++;
        } else if (!lastSeen && c.unread_count > 0) {
          totalUnread += 1;
        }

        const prev = prevMap[c.id];
        if (!prev) {
          // New conversation appeared in this agent's list — likely a new assignment
          if (isAgentMode && c.assigned_agent_id && c.status === 'assigned') {
            showBrowserNotification(
              '🧑‍💼 Chat asignado',
              `${c.contact_name || c.contact_phone || 'Cliente'}: ${c.last_message_preview || 'Nueva conversación asignada'}`,
              c.id
            );
          }
          return;
        }
        // New message detected: last_message_at changed and it's inbound
        const hasNewMsg = c.last_message_at && prev.last_message_at && c.last_message_at > prev.last_message_at;
        if (hasNewMsg && c.id !== selectedConvRef.current?.id) {
          showBrowserNotification(
            c.contact_name || c.contact_phone || 'Nuevo mensaje',
            c.last_message_preview || 'Tienes un nuevo mensaje',
            c.id
          );
        }
      });

      prevConvsRef.current = newConvs;
      setConversations(newConvs);

      // Emit unread count for sidebar badge
      window.dispatchEvent(new CustomEvent('omniUnreadCount', { detail: totalUnread }));
    } catch { /* silent */ }
  }, [filters, searchTerm, scope, isAgentMode]);

  const pollMessages = useCallback(async (convId) => {
    try {
      const data = await getMessages(convId);
      const newMsgs = data.data || [];
      if (!newMsgs.length) return;

      setMessages(prev => {
        const prevLastId = prev.length > 0 ? prev[prev.length - 1].id : null;
        const newLastId = newMsgs[newMsgs.length - 1].id;

        // Same count + same last id = no changes
        if (newMsgs.length === prev.length && newLastId === prevLastId) return prev;

        // New message — notify if tab hidden
        if (newMsgs.length > prev.length && prev.length > 0) {
          const latest = newMsgs[newMsgs.length - 1];
          if (latest.direction === 'inbound' && document.hidden) {
            const conv = selectedConvRef.current;
            showBrowserNotification(
              conv?.contact_name || conv?.contact_phone || 'Nuevo mensaje',
              latest.content || 'Tienes un nuevo mensaje',
              convId
            );
          }
        }
        return newMsgs;
      });
    } catch { /* silent */ }
  }, []);

  // Keep polling refs updated so setInterval always uses latest callbacks
  pollConversationsRef.current = pollConversations;
  pollMessagesRef.current = pollMessages;

  async function loadConversations() {
    setLoading(true);
    try {
      const params = { ...filters, search: searchTerm };
      if (isAgentMode && scope === 'mine') params.scope = 'mine';
      const data = await getConversations(params);
      const convs = data.data || [];
      setConversations(convs);
      prevConvsRef.current = convs; // Initialize baseline for notification diff
    } catch (err) {
      console.error('Error loading conversations:', err);
    } finally {
      setLoading(false);
    }
  }

  async function loadMessages(convId) {
    setMsgLoading(true);
    try {
      const data = await getMessages(convId);
      setMessages(data.data || []);
    } catch (err) {
      console.error('Error loading messages:', err);
    } finally {
      setMsgLoading(false);
    }
  }

  async function loadAgents() {
    try {
      const data = await getAgents();
      // Handle both raw array and paginated {data: [...]} responses
      const list = Array.isArray(data) ? data : (Array.isArray(data?.data) ? data.data : []);
      console.log('[InboxView] loadAgents:', list.length, 'agents loaded', list.map(a => `${a.id}:${a.name}:${a.role}:${a.status}`));
      setAgents(list);
      return list;
    } catch (err) {
      console.error('Error loading agents:', err);
      return [];
    }
  }

  async function openAgentDropdown() {
    await loadAgents();
    setShowAgentDropdown(true);
  }

  async function handleSend(e) {
    e.preventDefault();
    if (!newMessage.trim() || !selectedConv) return;
    setSending(true);
    try {
      const agentData = getIsAgent() ? getAgentData() : null;
      const result = await sendMessage(selectedConv.id, {
        content: newMessage,
        sender_type: 'agent',
        message_type: 'text',
        agent_id: agentData?.id || '',
        agent_name: agentData?.name || '',
      });
      setNewMessage('');
      // Check for YCloud delivery failure
      if (result?.ycloud && result.ycloud.error) {
        setResultModal({ type: 'error', title: 'Mensaje guardado pero no enviado', message: `El mensaje se guardó pero no se pudo enviar al cliente: ${result.ycloud.error}` });
      }
      // Check for Instagram delivery failure
      if (result?.instagram && result.instagram.error) {
        setResultModal({ type: 'error', title: 'Mensaje guardado pero no enviado', message: `El mensaje se guardó pero no se pudo enviar al cliente: ${result.instagram.error}` });
      }
      // Check for delivery note (non-whatsapp channels)
      if (result?.delivery_note) {
        setResultModal({ type: 'warning', title: 'Solo guardado', message: result.delivery_note });
      }
      await loadMessages(selectedConv.id);
      loadConversations();
    } catch (err) {
      setResultModal({ type: 'error', title: 'Error al enviar', message: err.message });
    } finally {
      setSending(false);
    }
  }

  // Assign conversation to a specific agent (admin/supervisor only)
  async function handleAssignToAgent(agentId) {
    if (!selectedConv || !agentId) return;
    try {
      await takeoverConversation(selectedConv.id, agentId, 'Asignado por supervisor');
      setShowAgentDropdown(false);
      await loadConversations();
      await loadMessages(selectedConv.id);
      setResultModal({ type: 'success', title: 'Asignado', message: `Conversación asignada al agente.` });
    } catch (err) {
      setResultModal({ type: 'error', title: 'Error al asignar', message: err.message });
    }
  }

  // Transfer to another agent (agent transferring their own conv)
  async function handleTransfer(toAgentId) {
    if (!selectedConv || !toAgentId) return;
    const agentData = getAgentData();
    const myAgentId = agentData?.id || selectedConv.assigned_agent_id;
    if (!myAgentId) return;
    try {
      await transferConversation(selectedConv.id, myAgentId, toAgentId, 'Transferido por agente');
      setShowAgentDropdown(false);
      await loadConversations();
      await loadMessages(selectedConv.id);
      setResultModal({ type: 'success', title: 'Transferido', message: 'Conversación transferida.' });
    } catch (err) {
      setResultModal({ type: 'error', title: 'Error al transferir', message: err.message });
    }
  }

  async function handleRelease() {
    if (!selectedConv) return;
    const agentId = selectedConv.assigned_agent_id || (agents[0]?.id);
    if (!agentId) return;
    try {
      await releaseConversation(selectedConv.id, agentId);
      setShowAgentDropdown(false);
      await loadConversations();
      await loadMessages(selectedConv.id);
    } catch (err) {
      setResultModal({ type: 'error', title: 'Error', message: err.message });
    }
  }

  function selectConversation(conv) {
    setSelectedConv(conv);
    setMobileShowChat(true);
  }

  async function handleExportHistory() {
    if (!selectedConv || exportingHistory) return;
    setExportingHistory(true);
    try {
      const result = await exportConversationHistory(selectedConv.id);
      if (result.error) throw new Error(result.error);
      const blob = new Blob([result.content], { type: 'text/plain;charset=utf-8' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = result.filename || `historial-conv-${selectedConv.id}.txt`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
    } catch (err) {
      setResultModal({ type: 'error', title: 'Error al exportar', message: err.message });
    } finally {
      setExportingHistory(false);
    }
  }

  function handleMobileBack() {
    setMobileShowChat(false);
    setSelectedConv(null);
  }

  const filteredConvs = conversations.filter(c => {
    if (!searchTerm) return true;
    const term = searchTerm.toLowerCase();
    return (c.contact_name || '').toLowerCase().includes(term) ||
           (c.contact_phone || '').toLowerCase().includes(term) ||
           (c.contact_email || '').toLowerCase().includes(term);
  });

  // Filtros de canal con color de marca por plataforma
  const CHANNEL_FILTERS = [
    { key: '', label: 'Todos', dot: '', active: 'bg-gradient-to-br from-blue-500 to-blue-600 text-white shadow-md' },
    { key: 'whatsapp', label: 'WhatsApp', dot: '#25D366', active: 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-400/40 dark:bg-emerald-500/10 dark:text-emerald-300' },
    { key: 'instagram', label: 'Instagram', dot: '#E4405F', active: 'bg-pink-50 text-pink-700 ring-1 ring-pink-400/40 dark:bg-pink-500/10 dark:text-pink-300' },
    { key: 'telegram', label: 'Telegram', dot: '#0088cc', active: 'bg-sky-50 text-sky-700 ring-1 ring-sky-400/40 dark:bg-sky-500/10 dark:text-sky-300' },
    { key: 'messenger', label: 'Messenger', dot: '#0084FF', active: 'bg-blue-50 text-blue-700 ring-1 ring-blue-400/40 dark:bg-blue-500/10 dark:text-blue-300' },
  ];
  const STATUS_FILTERS = [
    { key: '', label: 'Todos' },
    { key: 'bot', label: 'Bot' },
    { key: 'assigned', label: 'Asignados' },
    { key: 'open', label: 'Abiertos' },
    { key: 'closed', label: 'Cerrados' },
  ];

  return (
    <div className="flex h-full overflow-hidden">
      {/* Conversations List */}
      <div className={`inbox-panel ${mobileShowChat ? 'mobile-hidden' : ''} flex flex-col`}>
        {/* Hero de color */}
        <div className="px-3 py-3 bg-gradient-to-r from-blue-500 to-blue-600 text-white flex items-center gap-2.5">
          <span className="w-9 h-9 rounded-xl bg-white/15 ring-1 ring-white/25 flex items-center justify-center shrink-0">
            <MessageSquare size={18} />
          </span>
          <div className="min-w-0">
            <h2 className="text-sm font-bold leading-tight" style={{ fontFamily: 'Poppins, sans-serif' }}>Bandeja Unificada</h2>
            <p className="text-[11px] text-white/70 leading-tight">{filteredConvs.length} conversación{filteredConvs.length === 1 ? '' : 'es'}</p>
          </div>
        </div>
        {/* Search & Filters */}
        <div className="inbox-header space-y-2">
          <div className="relative">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" size={16} />
            <input aria-label="Buscar conversaciones..."
              type="text"
              placeholder="Buscar conversaciones..."
              value={searchTerm}
              onChange={e => setSearchTerm(e.target.value)}
              onKeyDown={e => e.key === 'Enter' && loadConversations()}
              className="w-full pl-9 pr-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
          <div className="flex gap-1.5 flex-wrap">
            {CHANNEL_FILTERS.map(ch => {
              const isActive = filters.channel_type === ch.key;
              return (
                <button
                  key={ch.key}
                  onClick={() => setFilters(f => ({ ...f, channel_type: ch.key }))}
                  className={`flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold transition-all duration-200 ${
                    isActive
                      ? ch.active
                      : 'bg-gray-100 text-gray-500 hover:bg-gray-200 dark:bg-slate-700/60 dark:text-slate-400 dark:hover:bg-slate-700'
                  }`}
                >
                  {ch.dot && (
                    <span
                      className="w-2 h-2 rounded-full shrink-0"
                      style={ch.key === 'instagram'
                        ? { backgroundImage: 'linear-gradient(45deg,#f09433,#dc2743,#bc1888)' }
                        : { backgroundColor: ch.dot }}
                    />
                  )}
                  {ch.label}
                </button>
              );
            })}
          </div>
          <div className="flex gap-1.5 flex-wrap">
            {STATUS_FILTERS.map(st => (
              <button
                key={st.key}
                onClick={() => setFilters(f => ({ ...f, status: st.key }))}
                className={`px-2.5 py-1 rounded-full text-xs font-semibold transition-all duration-200 ${
                  filters.status === st.key
                    ? 'bg-gradient-to-br from-blue-500 to-blue-600 text-white shadow-md'
                    : 'bg-gray-100 text-gray-500 hover:bg-gray-200 dark:bg-slate-700/60 dark:text-slate-400 dark:hover:bg-slate-700'
                }`}
              >
                {st.label}
              </button>
            ))}
          </div>
          {/* Agent scope toggle: All vs Mine */}
          {isAgentMode && (
            <div className="flex gap-1">
              <button
                onClick={() => setScope('all')}
                className={`flex items-center gap-1 px-2 py-1 rounded text-xs font-medium transition-colors ${
                  scope === 'all' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500 hover:bg-gray-200'
                }`}
              >
                <Eye size={12} /> Todas
              </button>
              <button
                onClick={() => setScope('mine')}
                className={`flex items-center gap-1 px-2 py-1 rounded text-xs font-medium transition-colors ${
                  scope === 'mine' ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500 hover:bg-gray-200'
                }`}
              >
                <MessageSquare size={12} /> Mis chats
              </button>
            </div>
          )}
        </div>

        {/* Conversations */}
        <div className="flex-1 overflow-y-auto custom-scrollbar">
          {loading ? (
            <div className="flex items-center justify-center py-12 text-gray-400">
              <Loader2 className="animate-spin mr-2" size={20} /> Cargando...
            </div>
          ) : filteredConvs.length === 0 ? (
            <div className="text-center py-12 text-gray-400 text-sm">
              <MessageSquare size={32} className="mx-auto mb-2 opacity-50" />
              Sin conversaciones
            </div>
          ) : (
            filteredConvs.map(conv => (
              <div
                key={conv.id}
                onClick={() => selectConversation(conv)}
                className={`chat-item ${selectedConv?.id === conv.id ? 'active' : ''}`}
              >
                <div className="relative shrink-0">
                  <div
                    className={`w-10 h-10 rounded-full flex items-center justify-center text-white text-sm font-bold shadow-md ring-2 ring-white dark:ring-slate-800 ${
                      conv.channel_type === 'whatsapp' ? 'bg-green-500' :
                      conv.channel_type === 'instagram' ? '' :
                      conv.channel_type === 'telegram' ? 'bg-sky-500' :
                      'bg-blue-500'
                    }`}
                    style={conv.channel_type === 'instagram'
                      ? { backgroundImage: 'linear-gradient(45deg,#f09433,#e6683c,#dc2743,#cc2366,#bc1888)' }
                      : undefined}
                  >
                    {(conv.contact_name || '?')[0].toUpperCase()}
                  </div>
                  {conv.unread_count > 0 && (
                    <span className="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center animate-bounce-subtle">
                      {conv.unread_count > 9 ? '9+' : conv.unread_count}
                    </span>
                  )}
                </div>

                <div className="flex-1 min-w-0">
                  <div className="flex items-center justify-between">
                    <span className={`font-medium text-sm truncate ${conv.unread_count > 0 ? 'text-gray-900 dark:text-white font-semibold' : 'text-gray-900 dark:text-slate-200'}`}>
                      {conv.contact_name || conv.contact_phone || 'Sin nombre'}
                    </span>
                    <span className="text-[10px] text-gray-400 shrink-0 ml-2">
                      {formatChileTime(conv.last_message_at)}
                    </span>
                  </div>
                  <div className="flex items-center gap-1.5 mt-0.5">
                    <ChannelBadge type={conv.channel_type} size="xs" />
                    <StatusBadge status={conv.status} />
                    {getIsAdmin() && conv.client_name && (
                      <span className="text-[10px] bg-blue-100 text-blue-700 px-1.5 py-0.5 rounded-full font-medium truncate max-w-[120px]">
                        {conv.client_name}
                      </span>
                    )}
                    {isAgentMode && conv.assigned_agent_name && !conv.is_mine && (
                      <span className="text-[10px] bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-full font-medium truncate max-w-[100px]">
                        {conv.assigned_agent_name}
                      </span>
                    )}
                    {conv.is_readonly && (
                      <span className="text-[10px] bg-gray-200 text-gray-500 px-1.5 py-0.5 rounded-full font-medium flex items-center gap-0.5">
                        <EyeOff size={8} /> Solo lectura
                      </span>
                    )}
                    {isAgentMode && conv.is_mine && (
                      <span className="text-[10px] bg-green-100 text-green-700 px-1.5 py-0.5 rounded-full font-medium">
                        Mía
                      </span>
                    )}
                  </div>
                  <p className={`text-xs mt-1 truncate ${conv.unread_count > 0 ? 'text-gray-700 dark:text-slate-300 font-medium' : 'text-gray-500'}`}>
                    {conv.last_message_preview || 'Sin mensajes'}
                  </p>
                </div>
              </div>
            ))
          )}
        </div>
      </div>

      {/* Chat Area */}
      <div className={`chat-window-panel ${!mobileShowChat ? 'mobile-hidden' : ''}`}>
        {selectedConv ? (
          <>
            {/* Chat Header */}
            <div className="chat-window-header">
              <div className="flex items-center gap-3">
                <button
                  onClick={handleMobileBack}
                  className="mobile-back-btn items-center p-1.5 rounded-lg hover:bg-gray-100 text-gray-500"
                  style={{ display: 'none' }}
                >
                  <ArrowLeft size={20} />
                </button>
                <div className={`w-10 h-10 rounded-full flex items-center justify-center text-white font-bold shrink-0 ${
                  selectedConv.channel_type === 'whatsapp' ? 'bg-green-500' :
                  selectedConv.channel_type === 'instagram' ? 'bg-pink-500' :
                  selectedConv.channel_type === 'telegram' ? 'bg-sky-500' :
                  'bg-blue-500'
                }`}>
                  {(selectedConv.contact_name || '?')[0].toUpperCase()}
                </div>
                <div className="min-w-0">
                  <h3 className="font-semibold text-sm truncate">{selectedConv.contact_name || 'Sin nombre'}</h3>
                  <div className="flex items-center gap-2 text-xs text-gray-500 flex-wrap">
                    <ChannelBadge type={selectedConv.channel_type} size="xs" />
                    <span className="truncate">{selectedConv.contact_phone || selectedConv.contact_email || ''}</span>
                    <StatusBadge status={selectedConv.status} />
                  </div>
                </div>
              </div>

              <div className="flex items-center gap-1.5 sm:gap-2 shrink-0 relative flex-wrap justify-end">
                {/* Export conversation history */}
                <button
                  onClick={handleExportHistory}
                  disabled={exportingHistory}
                  className="flex items-center gap-1 px-2 py-1.5 bg-slate-100 dark:bg-slate-700 text-slate-600 dark:text-slate-300 rounded-lg text-xs font-medium hover:bg-slate-200 dark:hover:bg-slate-600 transition-colors disabled:opacity-50"
                  title="Descargar historial completo"
                >
                  {exportingHistory ? <Loader2 size={13} className="animate-spin" /> : <Download size={13} />}
                  <span className="hidden sm:inline text-[10px]">Historial</span>
                </button>

                {selectedConv.is_readonly && (
                  <span className="flex items-center gap-1 px-2 py-1 bg-gray-200 text-gray-500 rounded-lg text-xs font-medium">
                    <Eye size={12} /> Solo lectura
                  </span>
                )}
                {!selectedConv.is_readonly && (() => {
                  const isAdminOrSup = getIsAdmin() || canSupervisor;
                  const isConvUnassigned = selectedConv.status === 'bot' || selectedConv.status === 'open';
                  const isConvAssigned = selectedConv.status === 'assigned' || selectedConv.status === 'active';
                  const isMyConv = isAgentMode && selectedConv.is_mine;

                  return (
                    <>
                      {/* Admin/Supervisor: Assign button (for unassigned convs) */}
                      {isAdminOrSup && isConvUnassigned && (
                        <div className="relative">
                          <button
                            onClick={() => showAgentDropdown ? setShowAgentDropdown(false) : openAgentDropdown()}
                            className="flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 text-white rounded-lg text-xs font-medium hover:bg-blue-700 transition-colors"
                          >
                            <UserCheck size={14} />
                            <span className="text-[10px] sm:text-xs">Asignar</span>
                            <ChevronDown size={12} />
                          </button>
                          {showAgentDropdown && (
                            <div className="absolute right-0 top-full mt-1 w-56 bg-white border border-gray-200 rounded-lg shadow-lg z-50 py-1 max-h-60 overflow-y-auto">
                              <p className="px-3 py-1.5 text-[10px] font-semibold text-gray-400 uppercase">Seleccionar agente</p>
                              {agents.map(a => (
                                <button
                                  key={a.id}
                                  onClick={() => handleAssignToAgent(a.id)}
                                  className="w-full text-left px-3 py-2 text-sm hover:bg-blue-50 flex items-center justify-between"
                                >
                                  <span>{a.name}</span>
                                  <span className="text-[10px] text-gray-400">{a.active_chats}/{a.max_concurrent_chats}</span>
                                </button>
                              ))}
                              {agents.length === 0 && (
                                <p className="px-3 py-2 text-xs text-gray-400">No hay agentes activos</p>
                              )}
                            </div>
                          )}
                        </div>
                      )}

                      {/* Admin/Supervisor: Reassign + Release (for assigned convs) */}
                      {isAdminOrSup && isConvAssigned && (
                        <div className="flex flex-col sm:flex-row items-end sm:items-center gap-1 sm:gap-2">
                          <div className="relative">
                            <button
                            onClick={() => showAgentDropdown ? setShowAgentDropdown(false) : openAgentDropdown()}
                              className="flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 text-white rounded-lg text-xs font-medium hover:bg-blue-700 transition-colors"
                            >
                              <ArrowRightLeft size={14} />
                              <span className="text-[10px] sm:text-xs">Reasignar</span>
                              <ChevronDown size={12} />
                            </button>
                            {showAgentDropdown && (
                              <div className="absolute right-0 top-full mt-1 w-64 bg-white border border-gray-200 rounded-lg shadow-lg z-50 py-1 max-h-72 overflow-y-auto">
                                <p className="px-3 py-1.5 text-[10px] font-semibold text-gray-400 uppercase">Reasignar a</p>
                                {agents.filter(a => String(a.id) !== String(selectedConv.assigned_agent_id)).map(a => (
                                  <button
                                    key={a.id}
                                    onClick={() => handleAssignToAgent(a.id)}
                                    className="w-full text-left px-3 py-2 text-sm hover:bg-blue-50 flex items-center justify-between"
                                  >
                                    <span className="flex items-center gap-1.5">
                                      <span>{a.name}</span>
                                      <span className={`text-[9px] px-1 py-0.5 rounded font-medium ${a.role === 'supervisor' || a.role === 'admin' ? 'bg-blue-100 text-blue-600' : 'bg-blue-100 text-blue-600'}`}>{a.role === 'admin' ? 'Admin' : a.role === 'supervisor' ? 'Sup' : 'Agente'}</span>
                                    </span>
                                    <span className="text-[10px] text-gray-400">{a.active_chats}/{a.max_concurrent_chats}</span>
                                  </button>
                                ))}
                                {agents.filter(a => String(a.id) !== String(selectedConv.assigned_agent_id)).length === 0 && (
                                  <p className="px-3 py-2 text-xs text-gray-400">No hay agentes disponibles</p>
                                )}
                              </div>
                            )}
                          </div>
                          <button
                            onClick={handleRelease}
                            className="flex items-center gap-1.5 px-3 py-1.5 bg-amber-500 text-white rounded-lg text-xs font-medium hover:bg-amber-600 transition-colors"
                          >
                            <RotateCcw size={14} />
                            <span className="text-[10px] sm:text-xs">Devolver</span>
                          </button>
                        </div>
                      )}

                      {/* Agent (not supervisor): Transfer to colleague/supervisor + Release (only if MY conv) */}
                      {isAgentMode && !isAdminOrSup && isMyConv && isConvAssigned && (
                        <div className="flex flex-col sm:flex-row items-end sm:items-center gap-1 sm:gap-2">
                          <div className="relative">
                            <button
                            onClick={() => showAgentDropdown ? setShowAgentDropdown(false) : openAgentDropdown()}
                              className="flex items-center gap-1.5 px-3 py-1.5 bg-blue-500 text-white rounded-lg text-xs font-medium hover:bg-blue-600 transition-colors"
                            >
                              <ArrowRightLeft size={14} />
                              <span className="text-[10px] sm:text-xs">Transferir</span>
                              <ChevronDown size={12} />
                            </button>
                            {showAgentDropdown && (
                              <div className="absolute right-0 top-full mt-1 w-64 bg-white border border-gray-200 rounded-lg shadow-lg z-50 py-1 max-h-72 overflow-y-auto">
                                <p className="px-3 py-1.5 text-[10px] font-semibold text-gray-400 uppercase">Transferir a</p>
                                {(() => {
                                  const myId = String(selectedConv.assigned_agent_id || '');
                                  const transferable = agents.filter(a => String(a.id) !== myId);
                                  if (transferable.length === 0) {
                                    return <p className="px-3 py-2 text-xs text-gray-400">No hay agentes o supervisores disponibles</p>;
                                  }
                                  const supervisors = transferable.filter(a => a.role === 'supervisor' || a.role === 'admin');
                                  const regularAgents = transferable.filter(a => a.role !== 'supervisor' && a.role !== 'admin');
                                  return (
                                    <>
                                      {supervisors.length > 0 && (
                                        <>
                                          <p className="px-3 pt-1.5 pb-0.5 text-[9px] font-bold text-blue-500 uppercase tracking-wide">Supervisores</p>
                                          {supervisors.map(a => (
                                            <button
                                              key={a.id}
                                              onClick={() => handleTransfer(a.id)}
                                              className="w-full text-left px-3 py-2 text-sm hover:bg-blue-50 flex items-center justify-between"
                                            >
                                              <span className="flex items-center gap-1.5">
                                                <span>{a.name}</span>
                                                <span className="text-[9px] bg-blue-100 text-blue-600 px-1 py-0.5 rounded font-medium">{a.role === 'admin' ? 'Admin' : 'Sup'}</span>
                                              </span>
                                              <span className="text-[10px] text-gray-400">{a.department || ''}</span>
                                            </button>
                                          ))}
                                        </>
                                      )}
                                      {regularAgents.length > 0 && (
                                        <>
                                          <p className="px-3 pt-1.5 pb-0.5 text-[9px] font-bold text-blue-500 uppercase tracking-wide">Agentes</p>
                                          {regularAgents.map(a => (
                                            <button
                                              key={a.id}
                                              onClick={() => handleTransfer(a.id)}
                                              className="w-full text-left px-3 py-2 text-sm hover:bg-blue-50 flex items-center justify-between"
                                            >
                                              <span className="flex items-center gap-1.5">
                                                <span>{a.name}</span>
                                                {a.department && <span className="text-[9px] bg-gray-100 text-gray-500 px-1 py-0.5 rounded">{a.department}</span>}
                                              </span>
                                              <span className="text-[10px] text-gray-400">{a.active_chats}/{a.max_concurrent_chats}</span>
                                            </button>
                                          ))}
                                        </>
                                      )}
                                    </>
                                  );
                                })()}
                              </div>
                            )}
                          </div>
                          <button
                            onClick={handleRelease}
                            className="flex items-center gap-1.5 px-3 py-1.5 bg-amber-500 text-white rounded-lg text-xs font-medium hover:bg-amber-600 transition-colors"
                          >
                            <RotateCcw size={14} />
                            <span className="text-[10px] sm:text-xs">Devolver</span>
                          </button>
                        </div>
                      )}
                    </>
                  );
                })()}
              </div>
            </div>

            {/* Messages */}
            <div className="chat-messages-container custom-scrollbar space-y-3">
              {msgLoading ? (
                <div className="flex items-center justify-center h-full text-gray-400">
                  <Loader2 className="animate-spin mr-2" size={20} /> Cargando mensajes...
                </div>
              ) : messages.length === 0 ? (
                <div className="flex items-center justify-center h-full text-gray-400 text-sm">
                  Sin mensajes en esta conversación
                </div>
              ) : (
                messages.map(msg => (
                  <div
                    key={msg.id}
                    className={`animate-fadein max-w-[85%] sm:max-w-[75%] ${
                      msg.direction === 'outbound' ? 'ml-auto' :
                      msg.sender_type === 'system' ? 'mx-auto max-w-md' : ''
                    }`}
                  >
                    <div className={`px-4 py-2.5 ${
                      msg.sender_type === 'system' ? 'msg-system' :
                      msg.direction === 'inbound' ? 'msg-inbound' :
                      msg.sender_type === 'bot' ? 'msg-outbound-bot' : 'msg-outbound'
                    }`}>
                      {msg.sender_type !== 'system' && msg.sender_name && (
                        <p className="text-[10px] font-semibold text-gray-500 mb-0.5">
                          {msg.sender_name}
                          {msg.sender_type === 'bot' && ' 🤖'}
                        </p>
                      )}
                      <p className="text-sm whitespace-pre-wrap break-words">{msg.content}</p>
                      <p className="text-[10px] text-gray-400 mt-1 text-right">
                        {formatChileTime(msg.created_at)}
                        {msg.delivery_status && msg.direction === 'outbound' && (
                          <span className={`ml-1 ${msg.delivery_status === 'failed' ? 'text-red-500' : ''}`}>
                            {msg.delivery_status === 'failed' ? '✕ No enviado' :
                             msg.delivery_status === 'read' || msg.delivery_status === 'delivered' ? '✓✓' : '✓'}
                          </span>
                        )}
                      </p>
                    </div>
                  </div>
                ))
              )}
              <div ref={messagesEndRef} />
            </div>

            {/* Message Input */}
            <form onSubmit={handleSend} className="chat-input-area">
              {selectedConv.is_readonly ? (
                <div className="flex items-center justify-center gap-2 py-2 text-gray-400 text-sm">
                  <Eye size={16} /> Conversación en modo solo lectura
                </div>
              ) : (
                <div className="flex items-center gap-2 w-full">
                  <input aria-label="Escribir mensaje"
                    type="text"
                    value={newMessage}
                    onChange={e => setNewMessage(e.target.value)}
                    placeholder={selectedConv.status === 'assigned' ? 'Escribe un mensaje...' : 'Toma el control para enviar mensajes'}
                    disabled={selectedConv.status !== 'assigned'}
                    className="flex-1 min-w-0 px-3 sm:px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50"
                  />
                  <button
                    type="submit"
                    disabled={sending || !newMessage.trim() || selectedConv.status !== 'assigned'}
                    className="p-2.5 bg-blue-600 text-white rounded-xl hover:bg-blue-700 disabled:bg-gray-300 transition-colors shrink-0"
                  >
                    {sending ? <Loader2 size={18} className="animate-spin" /> : <Send size={18} />}
                  </button>
                </div>
              )}
            </form>
          </>
        ) : (
          <div className="empty-chat-state">
            <MessageSquare size={48} className="mb-3 opacity-30" />
            <p className="text-lg font-medium" style={{ fontFamily: 'Poppins, sans-serif' }}>Bandeja Unificada</p>
            <p className="text-sm mt-1">Selecciona una conversación para ver los mensajes</p>
            <div className="flex justify-center gap-3 mt-4 flex-wrap">
              {['WhatsApp', 'Instagram', 'Telegram', 'Messenger'].map(ch => (
                <span key={ch} className={`px-2 py-1 rounded text-xs bg-channel-${ch.toLowerCase()}`}>{ch}</span>
              ))}
            </div>
          </div>
        )}
      </div>

      {resultModal && <ResultModal {...resultModal} onClose={() => setResultModal(null)} />}
    </div>
  );
}

function StatusBadge({ status }) {
  const styles = {
    bot: 'bg-blue-100 text-blue-700',
    assigned: 'bg-blue-100 text-blue-700',
    open: 'bg-green-100 text-green-700',
    closed: 'bg-gray-100 text-gray-500',
    archived: 'bg-gray-100 text-gray-400',
  };
  const labels = { bot: '🤖 Bot', assigned: '👤 Asignado', open: '🟢 Abierto', closed: 'Cerrado', archived: 'Archivado' };
  return (
    <span className={`px-1.5 py-0.5 rounded text-[10px] font-medium ${styles[status] || styles.open}`}>
      {labels[status] || status}
    </span>
  );
}
