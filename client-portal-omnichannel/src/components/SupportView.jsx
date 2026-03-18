import { useState, useEffect, useRef } from 'react';
import { LifeBuoy, Plus, Search, ChevronLeft, Send, Loader2, AlertCircle, CheckCircle, Clock, Filter, X, Paperclip, Image as ImageIcon } from 'lucide-react';
import { getTickets, createTicket, getTicket, addTicketMessage, updateTicketStatus, getIsAdmin, uploadTicketImages } from '../api';

const statusConfig = {
  open:          { label: 'Abierto',     color: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300' },
  'in-progress': { label: 'En Progreso', color: 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/40 dark:text-yellow-300' },
  resolved:      { label: 'Resuelto',    color: 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300' },
  closed:        { label: 'Cerrado',     color: 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300' },
};

const priorityConfig = {
  low:    { label: 'Baja',    color: 'bg-slate-100 text-slate-600' },
  medium: { label: 'Media',   color: 'bg-blue-100 text-blue-600' },
  high:   { label: 'Alta',    color: 'bg-orange-100 text-orange-600' },
  urgent: { label: 'Urgente', color: 'bg-red-100 text-red-600' },
};

const categoryLabels = {
  general: 'General',
  technical: 'Técnico',
  billing: 'Facturación',
  'feature-request': 'Solicitud',
  bug: 'Error/Bug',
};

export default function SupportView() {
  const isAdmin = getIsAdmin();
  const [tickets, setTickets] = useState([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(15);
  const [loading, setLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('');

  // Mobile detection
  const [isMobile, setIsMobile] = useState(window.innerWidth <= 768);
  useEffect(() => {
    const onResize = () => setIsMobile(window.innerWidth <= 768);
    window.addEventListener('resize', onResize);
    return () => window.removeEventListener('resize', onResize);
  }, []);

  // Detail
  const [selectedTicket, setSelectedTicket] = useState(null);
  const [detailLoading, setDetailLoading] = useState(false);
  const [newMessage, setNewMessage] = useState('');
  const [sendingMsg, setSendingMsg] = useState(false);
  const messagesEndRef = useRef(null);

  // Create form
  const [showCreate, setShowCreate] = useState(false);
  const [createForm, setCreateForm] = useState({ subject: '', description: '', category: 'general', priority: 'medium' });
  const [creating, setCreating] = useState(false);
  const [createError, setCreateError] = useState('');
  const [createImages, setCreateImages] = useState([]); // File[]
  const createFileRef = useRef(null);

  // Message reply images
  const [msgImages, setMsgImages] = useState([]); // File[]
  const msgFileRef = useRef(null);

  // Image lightbox
  const [lightboxImg, setLightboxImg] = useState(null);

  // Admin status update
  const [updatingStatus, setUpdatingStatus] = useState(false);

  useEffect(() => { loadTickets(); }, [page, perPage, statusFilter]);

  async function loadTickets() {
    setLoading(true);
    try {
      const params = { page, per_page: perPage };
      if (search) params.search = search;
      if (statusFilter) params.status = statusFilter;
      const data = await getTickets(params);
      setTickets(data.data || []);
      setTotal(data.total || 0);
    } catch (err) {
      console.error('Error loading tickets:', err);
    } finally {
      setLoading(false);
    }
  }

  async function handleSearch(e) {
    e.preventDefault();
    setPage(1);
    loadTickets();
  }

  async function openTicket(id) {
    setDetailLoading(true);
    try {
      const data = await getTicket(id);
      setSelectedTicket(data);
      setTimeout(() => messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' }), 100);
    } catch (err) {
      console.error(err);
    } finally {
      setDetailLoading(false);
    }
  }

  async function handleSendMessage(e) {
    e.preventDefault();
    if ((!newMessage.trim() && msgImages.length === 0) || !selectedTicket) return;
    setSendingMsg(true);
    try {
      let attachments = null;
      if (msgImages.length > 0) {
        const uploaded = await uploadTicketImages(msgImages);
        if (uploaded.urls?.length) attachments = uploaded.urls;
      }
      await addTicketMessage(selectedTicket.id, newMessage.trim() || '(imágenes adjuntas)', attachments);
      setNewMessage('');
      setMsgImages([]);
      await openTicket(selectedTicket.id);
    } catch (err) {
      console.error(err);
    } finally {
      setSendingMsg(false);
    }
  }

  async function handleCreate(e) {
    e.preventDefault();
    setCreateError('');
    if (!createForm.subject.trim() || !createForm.description.trim()) {
      setCreateError('Asunto y descripción son requeridos');
      return;
    }
    setCreating(true);
    try {
      let attachments = null;
      if (createImages.length > 0) {
        const uploaded = await uploadTicketImages(createImages);
        if (uploaded.urls?.length) attachments = uploaded.urls;
      }
      await createTicket({ ...createForm, ...(attachments ? { attachments } : {}) });
      setShowCreate(false);
      setCreateForm({ subject: '', description: '', category: 'general', priority: 'medium' });
      setCreateImages([]);
      setPage(1);
      loadTickets();
    } catch (err) {
      setCreateError(err.message);
    } finally {
      setCreating(false);
    }
  }

  async function handleStatusChange(newStatus) {
    if (!selectedTicket) return;
    setUpdatingStatus(true);
    try {
      await updateTicketStatus(selectedTicket.id, { status: newStatus });
      await openTicket(selectedTicket.id);
      loadTickets();
    } catch (err) {
      console.error(err);
    } finally {
      setUpdatingStatus(false);
    }
  }

  const totalPages = Math.ceil(total / perPage);

  // =============================================
  // HELPER: Ticket list panel (shared by admin master-detail and agent full-page)
  // =============================================
  function renderTicketList(compact = false) {
    return (
      <>
        {loading ? (
          <div className="flex items-center justify-center h-32 text-gray-400">
            <Loader2 className="animate-spin mr-2" size={18} /> Cargando...
          </div>
        ) : tickets.length === 0 ? (
          <div className="flex flex-col items-center justify-center h-32 text-gray-400">
            <LifeBuoy size={32} className="mb-2 opacity-40" />
            <p className="text-sm">No hay tickets</p>
          </div>
        ) : (
          <div className="divide-y divide-gray-100 dark:divide-slate-700/50">
            {tickets.map(t => {
              const st = statusConfig[t.status] || statusConfig.open;
              const pr = priorityConfig[t.priority] || priorityConfig.medium;
              const isActive = selectedTicket && selectedTicket.id === t.id;
              return (
                <button
                  key={t.id}
                  onClick={() => openTicket(t.id)}
                  className={`w-full text-left px-4 py-3 transition-colors ${isActive ? 'bg-indigo-50 dark:bg-indigo-900/20 border-l-2 border-indigo-500' : 'hover:bg-gray-50 dark:hover:bg-slate-800/50 border-l-2 border-transparent'}`}
                >
                  <div className="flex items-center gap-2 mb-1">
                    <span className="text-xs font-mono text-gray-400 dark:text-gray-500">{t.ticket_number}</span>
                    <span className={`text-[10px] px-2 py-0.5 rounded-full font-medium ${st.color}`}>{st.label}</span>
                    {!compact && <span className={`text-[10px] px-2 py-0.5 rounded-full font-medium ${pr.color}`}>{pr.label}</span>}
                    <span className="text-[10px] text-gray-400 ml-auto">{new Date(t.created_at).toLocaleDateString('es-CL')}</span>
                  </div>
                  <p className="text-sm font-medium text-gray-900 dark:text-white truncate">{t.subject}</p>
                  {!compact && <p className="text-xs text-gray-500 dark:text-gray-400 truncate mt-0.5">{t.description}</p>}
                  {isAdmin && t.agent_name && (
                    <p className="text-[10px] text-gray-400 mt-1">De: {t.agent_name} ({t.agent_email})</p>
                  )}
                </button>
              );
            })}
          </div>
        )}
      </>
    );
  }

  // =============================================
  // HELPER: Detail panel
  // =============================================
  function renderDetail() {
    if (!selectedTicket) {
      return (
        <div className="flex-1 flex flex-col items-center justify-center text-gray-400 dark:text-gray-500">
          <LifeBuoy size={48} className="mb-3 opacity-30" />
          <p className="text-sm">Selecciona un ticket para ver los detalles</p>
        </div>
      );
    }
    const st = statusConfig[selectedTicket.status] || statusConfig.open;
    const pr = priorityConfig[selectedTicket.priority] || priorityConfig.medium;
    return (
      <div className="flex-1 flex flex-col overflow-hidden">
        {/* Detail header */}
        <div className="p-4 border-b border-gray-200 dark:border-slate-700">
          {(!isAdmin || isMobile) && (
            <button onClick={() => setSelectedTicket(null)} className="p-1 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-lg mb-2 -ml-1">
              <ChevronLeft size={18} />
            </button>
          )}
          <div className="flex items-center gap-2 flex-wrap mb-1">
            <span className="text-xs font-mono text-gray-400">{selectedTicket.ticket_number}</span>
            <span className={`text-[10px] px-2 py-0.5 rounded-full font-medium ${st.color}`}>{st.label}</span>
            <span className={`text-[10px] px-2 py-0.5 rounded-full font-medium ${pr.color}`}>{pr.label}</span>
          </div>
          <h2 className="font-semibold text-gray-900 dark:text-white text-sm">{selectedTicket.subject}</h2>
          <div className="mt-2 text-xs text-gray-500 dark:text-gray-400 flex flex-wrap gap-x-3 gap-y-1">
            <span>Categoría: <strong>{categoryLabels[selectedTicket.category] || selectedTicket.category}</strong></span>
            <span>De: <strong>{selectedTicket.agent_name}</strong></span>
            <span>{new Date(selectedTicket.created_at).toLocaleString('es-CL')}</span>
          </div>

          {/* Admin: status buttons */}
          {isAdmin && (
            <div className="flex items-center gap-1.5 mt-3">
              <span className="text-[10px] text-gray-400 mr-1">Cambiar estado:</span>
              {['open', 'in-progress', 'resolved', 'closed'].map(s => {
                const sc = statusConfig[s];
                const isCurrent = selectedTicket.status === s;
                return (
                  <button
                    key={s}
                    onClick={() => !isCurrent && handleStatusChange(s)}
                    disabled={isCurrent || updatingStatus}
                    className={`text-[10px] px-2.5 py-1 rounded-full font-medium transition-all ${isCurrent ? sc.color + ' ring-2 ring-indigo-400 ring-offset-1 dark:ring-offset-slate-800' : 'bg-gray-100 dark:bg-slate-700 text-gray-500 dark:text-gray-400 hover:bg-gray-200 dark:hover:bg-slate-600'} disabled:cursor-default`}
                  >
                    {updatingStatus && isCurrent ? '...' : sc.label}
                  </button>
                );
              })}
            </div>
          )}
        </div>

        {/* Messages */}
        <div className="flex-1 overflow-y-auto p-4 space-y-3 custom-scrollbar">
          {(selectedTicket.messages || []).map(msg => {
            const isAdminMsg = msg.sender_type === 'admin';
            const imgs = msg.attachments ? (typeof msg.attachments === 'string' ? JSON.parse(msg.attachments) : msg.attachments) : [];
            return (
              <div key={msg.id} className={`flex ${isAdminMsg ? 'justify-start' : 'justify-end'}`}>
                <div className={`max-w-[80%] rounded-xl px-3.5 py-2.5 text-sm ${isAdminMsg ? 'bg-gray-100 dark:bg-slate-700 text-gray-800 dark:text-gray-200' : 'bg-indigo-600 text-white'}`}>
                  <p className={`text-[10px] font-semibold mb-1 ${isAdminMsg ? 'text-gray-500 dark:text-gray-400' : 'text-indigo-200'}`}>
                    {msg.sender_name || (isAdminMsg ? 'Soporte' : 'Tú')}
                  </p>
                  <p className="whitespace-pre-wrap">{msg.message}</p>
                  {Array.isArray(imgs) && imgs.length > 0 && (
                    <div className="flex flex-wrap gap-1.5 mt-2">
                      {imgs.map((url, idx) => (
                        <img
                          key={idx}
                          src={url}
                          alt={`Adjunto ${idx + 1}`}
                          className="w-20 h-20 object-cover rounded-lg cursor-pointer border border-white/20 hover:opacity-80 transition-opacity"
                          onClick={() => setLightboxImg(url)}
                        />
                      ))}
                    </div>
                  )}
                  <p className={`text-[9px] mt-1 ${isAdminMsg ? 'text-gray-400' : 'text-indigo-200'}`}>
                    {new Date(msg.created_at).toLocaleString('es-CL')}
                  </p>
                </div>
              </div>
            );
          })}
          <div ref={messagesEndRef} />
        </div>

        {/* Send message */}
        {selectedTicket.status !== 'closed' && (
          <div className="border-t border-gray-200 dark:border-slate-700">
            {msgImages.length > 0 && (
              <div className="px-3 pt-2 flex flex-wrap gap-1.5">
                {msgImages.map((f, i) => (
                  <div key={i} className="relative group">
                    <img src={URL.createObjectURL(f)} alt="" className="w-14 h-14 object-cover rounded-lg border border-gray-200 dark:border-slate-600" />
                    <button onClick={() => setMsgImages(prev => prev.filter((_, idx) => idx !== i))} className="absolute -top-1.5 -right-1.5 w-4 h-4 bg-red-500 text-white rounded-full text-[10px] flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">×</button>
                  </div>
                ))}
              </div>
            )}
            <form onSubmit={handleSendMessage} className="p-3 flex gap-2 items-center">
              <input ref={msgFileRef} type="file" accept="image/jpeg,image/png,image/webp,image/gif" multiple className="hidden" onChange={e => {
                const files = Array.from(e.target.files || []);
                setMsgImages(prev => [...prev, ...files].slice(0, 5));
                e.target.value = '';
              }} />
              <button type="button" onClick={() => msgFileRef.current?.click()} disabled={msgImages.length >= 5} className="p-2 text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 disabled:opacity-30" title="Adjuntar imágenes (máx. 5)">
                <Paperclip size={18} />
              </button>
              <input
                type="text"
                value={newMessage}
                onChange={e => setNewMessage(e.target.value)}
                placeholder="Escribe un mensaje..."
                className="flex-1 px-3 py-2 text-sm border border-gray-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500"
              />
              <button
                type="submit"
                disabled={(!newMessage.trim() && msgImages.length === 0) || sendingMsg}
                className="px-3 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700 disabled:opacity-50 flex items-center gap-1.5"
              >
                {sendingMsg ? <Loader2 size={14} className="animate-spin" /> : <Send size={14} />}
              </button>
            </form>
          </div>
        )}
      </div>
    );
  }

  // =============================================
  // ADMIN: Master-detail two-panel layout
  // =============================================
  if (isAdmin) {
    return (
      <div className="h-full flex flex-col overflow-hidden">
        {/* Top header */}
        <div className="p-4 border-b border-gray-200 dark:border-slate-700">
          <h1 className="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2" style={{ fontFamily: 'Poppins, sans-serif' }}>
            <LifeBuoy size={22} className="text-indigo-600" /> Soporte — Administración
          </h1>
          <p className="text-sm text-gray-500 dark:text-gray-400">{total} ticket{total !== 1 ? 's' : ''}</p>

          {/* Filters row */}
          <div className="flex gap-2 flex-wrap mt-3 items-center">
            <form onSubmit={handleSearch} className="flex-1 min-w-[180px] relative">
              <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
              <input
                type="text"
                value={search}
                onChange={e => setSearch(e.target.value)}
                placeholder="Buscar tickets..."
                className="w-full pl-9 pr-3 py-1.5 text-sm border border-gray-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-gray-900 dark:text-white"
              />
            </form>
            <select
              value={statusFilter}
              onChange={e => { setStatusFilter(e.target.value); setPage(1); }}
              className="px-2.5 py-1.5 text-sm border border-gray-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-gray-700 dark:text-gray-300"
            >
              <option value="">Todos los estados</option>
              <option value="open">Abierto</option>
              <option value="in-progress">En Progreso</option>
              <option value="resolved">Resuelto</option>
              <option value="closed">Cerrado</option>
            </select>
            <div className="flex items-center gap-1.5">
              <span className="text-xs text-gray-400">Mostrar:</span>
              <select
                value={perPage}
                onChange={e => { setPerPage(Number(e.target.value)); setPage(1); }}
                className="px-2 py-1 rounded text-xs border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-gray-600 dark:text-gray-300"
              >
                {[10, 15, 25, 50].map(n => <option key={n} value={n}>{n}</option>)}
              </select>
            </div>
          </div>
        </div>

        {/* Two-panel area (desktop) / single-panel toggle (mobile) */}
        <div className="flex-1 flex overflow-hidden">
          {/* Left: ticket list — hidden on mobile when a ticket is selected */}
          <div className={`${isMobile ? 'w-full' : 'w-[380px] min-w-[320px]'} border-r border-gray-200 dark:border-slate-700 flex flex-col overflow-hidden ${isMobile && selectedTicket ? 'hidden' : ''}`}>
            <div className="flex-1 overflow-y-auto custom-scrollbar">
              {renderTicketList(isMobile ? false : true)}
            </div>
            {/* Pagination */}
            {totalPages > 1 && (
              <div className="p-2 border-t border-gray-200 dark:border-slate-700 flex items-center justify-center gap-2">
                <button onClick={() => setPage(p => Math.max(1, p - 1))} disabled={page === 1} className="px-2.5 py-1 text-xs rounded-lg border border-gray-200 dark:border-slate-600 disabled:opacity-40">Anterior</button>
                <span className="text-xs text-gray-500">{page} / {totalPages}</span>
                <button onClick={() => setPage(p => Math.min(totalPages, p + 1))} disabled={page === totalPages} className="px-2.5 py-1 text-xs rounded-lg border border-gray-200 dark:border-slate-600 disabled:opacity-40">Siguiente</button>
              </div>
            )}
          </div>

          {/* Right: detail — hidden on mobile when no ticket selected */}
          <div className={`flex-1 flex flex-col overflow-hidden ${isMobile && !selectedTicket ? 'hidden' : ''}`}>
            {detailLoading ? (
              <div className="flex-1 flex items-center justify-center">
                <Loader2 size={28} className="animate-spin text-indigo-600" />
              </div>
            ) : renderDetail()}
          </div>
        </div>

        {/* Image lightbox */}
        {lightboxImg && (
          <div className="fixed inset-0 bg-black/80 z-[60] flex items-center justify-center p-4" onClick={() => setLightboxImg(null)}>
            <button onClick={() => setLightboxImg(null)} className="absolute top-4 right-4 p-2 bg-black/50 text-white rounded-full hover:bg-black/70"><X size={20} /></button>
            <img src={lightboxImg} alt="Vista ampliada" className="max-w-full max-h-[90vh] object-contain rounded-lg shadow-2xl" />
          </div>
        )}
      </div>
    );
  }

  // =============================================
  // AGENT: Full-page list → detail flow
  // =============================================

  // Agent detail view
  if (selectedTicket && !detailLoading) {
    return (
      <div className="h-full flex flex-col overflow-hidden">
        {renderDetail()}

        {/* Image lightbox */}
        {lightboxImg && (
          <div className="fixed inset-0 bg-black/80 z-[60] flex items-center justify-center p-4" onClick={() => setLightboxImg(null)}>
            <button onClick={() => setLightboxImg(null)} className="absolute top-4 right-4 p-2 bg-black/50 text-white rounded-full hover:bg-black/70"><X size={20} /></button>
            <img src={lightboxImg} alt="Vista ampliada" className="max-w-full max-h-[90vh] object-contain rounded-lg shadow-2xl" />
          </div>
        )}

        {detailLoading && (
          <div className="fixed inset-0 bg-black/20 z-40 flex items-center justify-center">
            <Loader2 size={32} className="animate-spin text-indigo-600" />
          </div>
        )}
      </div>
    );
  }

  // Agent list view
  return (
    <div className="h-full flex flex-col overflow-hidden">
      {/* Header */}
      <div className="p-4 border-b border-gray-200 dark:border-slate-700">
        <div className="flex items-center justify-between mb-3">
          <div>
            <h1 className="text-lg font-bold text-gray-900 dark:text-white flex items-center gap-2" style={{ fontFamily: 'Poppins, sans-serif' }}>
              <LifeBuoy size={22} className="text-indigo-600" /> Soporte
            </h1>
            <p className="text-sm text-gray-500 dark:text-gray-400">
              {total} ticket{total !== 1 ? 's' : ''} {statusFilter && `(${statusConfig[statusFilter]?.label || statusFilter})`}
            </p>
          </div>
          <button
            onClick={() => setShowCreate(true)}
            className="flex items-center gap-1.5 px-3.5 py-2 bg-indigo-600 text-white rounded-lg text-sm font-medium hover:bg-indigo-700"
          >
            <Plus size={16} /> Nuevo Ticket
          </button>
        </div>

        {/* Filters */}
        <div className="flex gap-2 flex-wrap">
          <form onSubmit={handleSearch} className="flex-1 min-w-[200px] relative">
            <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
            <input
              type="text"
              value={search}
              onChange={e => setSearch(e.target.value)}
              placeholder="Buscar tickets..."
              className="w-full pl-9 pr-3 py-2 text-sm border border-gray-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-gray-900 dark:text-white"
            />
          </form>
          <select
            value={statusFilter}
            onChange={e => { setStatusFilter(e.target.value); setPage(1); }}
            className="px-3 py-2 text-sm border border-gray-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-gray-700 dark:text-gray-300"
          >
            <option value="">Todos los estados</option>
            <option value="open">Abierto</option>
            <option value="in-progress">En Progreso</option>
            <option value="resolved">Resuelto</option>
            <option value="closed">Cerrado</option>
          </select>
        </div>
      </div>

      {/* Ticket list */}
      <div className="flex-1 overflow-y-auto custom-scrollbar">
        {renderTicketList(false)}
      </div>

      {/* Pagination */}
      {totalPages > 1 && (
        <div className="p-3 border-t border-gray-200 dark:border-slate-700 flex items-center justify-center gap-2">
          <button onClick={() => setPage(p => Math.max(1, p - 1))} disabled={page === 1} className="px-3 py-1.5 text-xs rounded-lg border border-gray-200 dark:border-slate-600 disabled:opacity-40">Anterior</button>
          <span className="text-xs text-gray-500">{page} / {totalPages}</span>
          <button onClick={() => setPage(p => Math.min(totalPages, p + 1))} disabled={page === totalPages} className="px-3 py-1.5 text-xs rounded-lg border border-gray-200 dark:border-slate-600 disabled:opacity-40">Siguiente</button>
        </div>
      )}

      {/* Create ticket modal */}
      {showCreate && (
        <div className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" onClick={() => setShowCreate(false)}>
          <div className="bg-white dark:bg-slate-800 rounded-xl shadow-xl max-w-lg w-full max-h-[90vh] overflow-y-auto" onClick={e => e.stopPropagation()}>
            <div className="p-5 border-b border-gray-200 dark:border-slate-700 flex items-center justify-between">
              <h3 className="font-semibold text-gray-900 dark:text-white flex items-center gap-2"><Plus size={18} /> Nuevo Ticket</h3>
              <button onClick={() => setShowCreate(false)} className="p-1 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-lg"><X size={18} /></button>
            </div>
            <form onSubmit={handleCreate} className="p-5 space-y-4">
              {createError && (
                <div className="flex items-center gap-2 p-3 rounded-lg text-sm bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 border border-red-200 dark:border-red-800">
                  <AlertCircle size={16} /> {createError}
                </div>
              )}
              <div>
                <label className="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Asunto *</label>
                <input
                  type="text"
                  value={createForm.subject}
                  onChange={e => setCreateForm(f => ({ ...f, subject: e.target.value }))}
                  className="w-full px-3 py-2 text-sm border border-gray-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500"
                  placeholder="Resumen del problema"
                  required
                />
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Descripción *</label>
                <textarea
                  value={createForm.description}
                  onChange={e => setCreateForm(f => ({ ...f, description: e.target.value }))}
                  rows={4}
                  className="w-full px-3 py-2 text-sm border border-gray-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-indigo-500 resize-none"
                  placeholder="Describe el problema con detalle..."
                  required
                />
              </div>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Categoría</label>
                  <select
                    value={createForm.category}
                    onChange={e => setCreateForm(f => ({ ...f, category: e.target.value }))}
                    className="w-full px-3 py-2 text-sm border border-gray-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-gray-900 dark:text-white"
                  >
                    <option value="general">General</option>
                    <option value="technical">Técnico</option>
                    <option value="billing">Facturación</option>
                    <option value="feature-request">Solicitud de Función</option>
                    <option value="bug">Error/Bug</option>
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Prioridad</label>
                  <select
                    value={createForm.priority}
                    onChange={e => setCreateForm(f => ({ ...f, priority: e.target.value }))}
                    className="w-full px-3 py-2 text-sm border border-gray-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-700 text-gray-900 dark:text-white"
                  >
                    <option value="low">Baja</option>
                    <option value="medium">Media</option>
                    <option value="high">Alta</option>
                    <option value="urgent">Urgente</option>
                  </select>
                </div>
              </div>
              {/* Image attachments */}
              <div>
                <label className="block text-xs font-medium text-gray-600 dark:text-gray-400 mb-1">Capturas / Imágenes (máx. 5)</label>
                <input ref={createFileRef} type="file" accept="image/jpeg,image/png,image/webp,image/gif" multiple className="hidden" onChange={e => {
                  const files = Array.from(e.target.files || []);
                  setCreateImages(prev => [...prev, ...files].slice(0, 5));
                  e.target.value = '';
                }} />
                <button type="button" onClick={() => createFileRef.current?.click()} disabled={createImages.length >= 5}
                  className="w-full flex items-center justify-center gap-2 px-3 py-2.5 text-sm border-2 border-dashed border-gray-200 dark:border-slate-600 rounded-lg text-gray-500 dark:text-gray-400 hover:border-indigo-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors disabled:opacity-30">
                  <ImageIcon size={16} /> {createImages.length === 0 ? 'Agregar imágenes' : `${createImages.length}/5 imágenes`}
                </button>
                {createImages.length > 0 && (
                  <div className="flex flex-wrap gap-2 mt-2">
                    {createImages.map((f, i) => (
                      <div key={i} className="relative group">
                        <img src={URL.createObjectURL(f)} alt="" className="w-16 h-16 object-cover rounded-lg border border-gray-200 dark:border-slate-600" />
                        <button type="button" onClick={() => setCreateImages(prev => prev.filter((_, idx) => idx !== i))} className="absolute -top-1.5 -right-1.5 w-5 h-5 bg-red-500 text-white rounded-full text-xs flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">×</button>
                      </div>
                    ))}
                  </div>
                )}
              </div>
              <div className="flex justify-end gap-2 pt-2">
                <button type="button" onClick={() => { setShowCreate(false); setCreateImages([]); }} className="px-4 py-2 text-sm bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200">Cancelar</button>
                <button type="submit" disabled={creating} className="px-4 py-2 text-sm bg-indigo-600 text-white rounded-lg font-medium hover:bg-indigo-700 disabled:opacity-50 flex items-center gap-1.5">
                  {creating ? <Loader2 size={14} className="animate-spin" /> : <Plus size={14} />} Crear Ticket
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Detail loading overlay */}
      {detailLoading && (
        <div className="fixed inset-0 bg-black/20 z-40 flex items-center justify-center">
          <Loader2 size={32} className="animate-spin text-indigo-600" />
        </div>
      )}

      {/* Image lightbox */}
      {lightboxImg && (
        <div className="fixed inset-0 bg-black/80 z-[60] flex items-center justify-center p-4" onClick={() => setLightboxImg(null)}>
          <button onClick={() => setLightboxImg(null)} className="absolute top-4 right-4 p-2 bg-black/50 text-white rounded-full hover:bg-black/70"><X size={20} /></button>
          <img src={lightboxImg} alt="Vista ampliada" className="max-w-full max-h-[90vh] object-contain rounded-lg shadow-2xl" />
        </div>
      )}
    </div>
  );
}
