import { useState, useEffect, useRef } from 'react';
import { Search, Send, UserCheck, RotateCcw, Loader2, MessageSquare, ArrowLeft, Eye, EyeOff } from 'lucide-react';
import { getConversations, getMessages, sendMessage, takeoverConversation, releaseConversation, getAgents, getIsAdmin, getIsAgent, isSupervisorOrAdmin } from '../api';
import ChannelBadge from './ChannelBadge';
import ResultModal from './ResultModal';

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
  const messagesEndRef = useRef(null);
  const isAgentMode = getIsAgent();
  const canSupervisor = isAgentMode && isSupervisorOrAdmin();

  useEffect(() => {
    loadConversations();
    loadAgents();
  }, [filters, scope]);

  useEffect(() => {
    if (selectedConv) loadMessages(selectedConv.id);
  }, [selectedConv]);

  useEffect(() => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  }, [messages]);

  async function loadConversations() {
    setLoading(true);
    try {
      const params = { ...filters, search: searchTerm };
      if (isAgentMode && scope === 'mine') params.scope = 'mine';
      const data = await getConversations(params);
      setConversations(data.data || []);
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
      setAgents(Array.isArray(data) ? data : []);
    } catch (err) {
      console.error('Error loading agents:', err);
    }
  }

  async function handleSend(e) {
    e.preventDefault();
    if (!newMessage.trim() || !selectedConv) return;
    setSending(true);
    try {
      await sendMessage(selectedConv.id, {
        content: newMessage,
        sender_type: 'agent',
        message_type: 'text',
      });
      setNewMessage('');
      await loadMessages(selectedConv.id);
      loadConversations();
    } catch (err) {
      setResultModal({ type: 'error', title: 'Error al enviar', message: err.message });
    } finally {
      setSending(false);
    }
  }

  async function handleTakeover() {
    if (!selectedConv || agents.length === 0) return;
    const agent = agents[0];
    try {
      await takeoverConversation(selectedConv.id, agent.id, 'Toma de control manual');
      loadConversations();
      loadMessages(selectedConv.id);
    } catch (err) {
      setResultModal({ type: 'error', title: 'Error', message: err.message });
    }
  }

  async function handleRelease() {
    if (!selectedConv) return;
    const agent = agents[0];
    try {
      await releaseConversation(selectedConv.id, agent.id);
      loadConversations();
      loadMessages(selectedConv.id);
    } catch (err) {
      setResultModal({ type: 'error', title: 'Error', message: err.message });
    }
  }

  function selectConversation(conv) {
    setSelectedConv(conv);
    setMobileShowChat(true);
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

  return (
    <div className="flex h-full overflow-hidden">
      {/* Conversations List */}
      <div className={`inbox-panel ${mobileShowChat ? 'mobile-hidden' : ''} flex flex-col`}>
        {/* Search & Filters */}
        <div className="inbox-header space-y-2">
          <div className="relative">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" size={16} />
            <input
              type="text"
              placeholder="Buscar conversaciones..."
              value={searchTerm}
              onChange={e => setSearchTerm(e.target.value)}
              onKeyDown={e => e.key === 'Enter' && loadConversations()}
              className="w-full pl-9 pr-3 py-2 bg-gray-50 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
          <div className="flex gap-1 flex-wrap">
            {['', 'whatsapp', 'instagram', 'telegram', 'messenger'].map(ch => (
              <button
                key={ch}
                onClick={() => setFilters(f => ({ ...f, channel_type: ch }))}
                className={`px-2 py-1 rounded text-xs font-medium transition-colors ${
                  filters.channel_type === ch
                    ? 'bg-blue-100 text-blue-700'
                    : 'bg-gray-100 text-gray-500 hover:bg-gray-200'
                }`}
              >
                {ch ? ch.charAt(0).toUpperCase() + ch.slice(1) : 'Todos'}
              </button>
            ))}
          </div>
          <div className="flex gap-1 flex-wrap">
            {['', 'bot', 'assigned', 'open', 'closed'].map(st => (
              <button
                key={st}
                onClick={() => setFilters(f => ({ ...f, status: st }))}
                className={`px-2 py-1 rounded text-xs font-medium transition-colors ${
                  filters.status === st
                    ? 'bg-blue-100 text-blue-700'
                    : 'bg-gray-100 text-gray-500 hover:bg-gray-200'
                }`}
              >
                {st || 'Todos'}
              </button>
            ))}
          </div>
          {/* Agent scope toggle: All vs Mine */}
          {isAgentMode && (
            <div className="flex gap-1">
              <button
                onClick={() => setScope('all')}
                className={`flex items-center gap-1 px-2 py-1 rounded text-xs font-medium transition-colors ${
                  scope === 'all' ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-500 hover:bg-gray-200'
                }`}
              >
                <Eye size={12} /> Todas
              </button>
              <button
                onClick={() => setScope('mine')}
                className={`flex items-center gap-1 px-2 py-1 rounded text-xs font-medium transition-colors ${
                  scope === 'mine' ? 'bg-indigo-100 text-indigo-700' : 'bg-gray-100 text-gray-500 hover:bg-gray-200'
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
                  <div className={`w-10 h-10 rounded-full flex items-center justify-center text-white text-sm font-bold ${
                    conv.channel_type === 'whatsapp' ? 'bg-green-500' :
                    conv.channel_type === 'instagram' ? 'bg-pink-500' :
                    conv.channel_type === 'telegram' ? 'bg-sky-500' :
                    'bg-blue-500'
                  }`}>
                    {(conv.contact_name || '?')[0].toUpperCase()}
                  </div>
                  {conv.unread_count > 0 && (
                    <span className="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-[10px] font-bold rounded-full flex items-center justify-center">
                      {conv.unread_count > 9 ? '9+' : conv.unread_count}
                    </span>
                  )}
                </div>

                <div className="flex-1 min-w-0">
                  <div className="flex items-center justify-between">
                    <span className="font-medium text-sm text-gray-900 truncate">
                      {conv.contact_name || conv.contact_phone || 'Sin nombre'}
                    </span>
                    <span className="text-[10px] text-gray-400 shrink-0 ml-2">
                      {conv.last_message_at ? new Date(conv.last_message_at).toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit' }) : ''}
                    </span>
                  </div>
                  <div className="flex items-center gap-1.5 mt-0.5">
                    <ChannelBadge type={conv.channel_type} size="xs" />
                    <StatusBadge status={conv.status} />
                    {getIsAdmin() && conv.client_name && (
                      <span className="text-[10px] bg-purple-100 text-purple-700 px-1.5 py-0.5 rounded-full font-medium truncate max-w-[120px]">
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
                  <p className="text-xs text-gray-500 mt-1 truncate">
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

              <div className="flex items-center gap-2 shrink-0">
                {selectedConv.is_readonly && (
                  <span className="flex items-center gap-1 px-2 py-1 bg-gray-200 text-gray-500 rounded-lg text-xs font-medium">
                    <Eye size={12} /> Solo lectura
                  </span>
                )}
                {!selectedConv.is_readonly && (selectedConv.status === 'bot' || selectedConv.status === 'open') ? (
                  <button
                    onClick={handleTakeover}
                    className="flex items-center gap-1.5 px-3 py-1.5 bg-blue-600 text-white rounded-lg text-xs font-medium hover:bg-blue-700 transition-colors"
                  >
                    <UserCheck size={14} />
                    <span className="hidden sm:inline">Tomar Control</span>
                  </button>
                ) : !selectedConv.is_readonly && selectedConv.status === 'assigned' ? (
                  <button
                    onClick={handleRelease}
                    className="flex items-center gap-1.5 px-3 py-1.5 bg-amber-500 text-white rounded-lg text-xs font-medium hover:bg-amber-600 transition-colors"
                  >
                    <RotateCcw size={14} />
                    <span className="hidden sm:inline">Devolver al Bot</span>
                  </button>
                ) : null}
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
                        {new Date(msg.created_at).toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit' })}
                        {msg.delivery_status && msg.direction === 'outbound' && (
                          <span className="ml-1">
                            {msg.delivery_status === 'read' ? '✓✓' : msg.delivery_status === 'delivered' ? '✓✓' : '✓'}
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
                <div className="flex items-center gap-2">
                  <input
                    type="text"
                    value={newMessage}
                    onChange={e => setNewMessage(e.target.value)}
                    placeholder={selectedConv.status === 'assigned' ? 'Escribe un mensaje...' : 'Toma el control para enviar mensajes'}
                    disabled={selectedConv.status !== 'assigned'}
                    className="flex-1 px-4 py-2.5 bg-gray-50 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-50"
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
    bot: 'bg-purple-100 text-purple-700',
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
