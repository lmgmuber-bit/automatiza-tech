import { useState, useEffect, useRef } from 'react';
import { LifeBuoy, Plus, Search, ChevronLeft, Send, Loader2, AlertCircle, CheckCircle, Clock, Filter, X, Paperclip, Image as ImageIcon, MessageCircle, Sparkles } from 'lucide-react';
import { getTickets, createTicket, getTicket, addTicketMessage, updateTicketStatus, getIsAdmin, uploadTicketImages } from '../api';
import ResultModal from './ResultModal';

const statusConfig = {
  open:          { label: 'Abierto',     color: 'bg-sky-50 text-sky-700 ring-1 ring-sky-200 dark:bg-sky-500/10 dark:text-sky-300 dark:ring-sky-500/20' },
  'in-progress': { label: 'En Progreso', color: 'bg-amber-50 text-amber-700 ring-1 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/20' },
  resolved:      { label: 'Resuelto',    color: 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/20' },
  closed:        { label: 'Cerrado',     color: 'bg-gray-50 text-gray-600 ring-1 ring-gray-200 dark:bg-slate-700/60 dark:text-slate-300 dark:ring-slate-600' },
};

const priorityConfig = {
  low:    { label: 'Baja',    color: 'bg-slate-50 text-slate-600 ring-1 ring-slate-200 dark:bg-slate-700/60 dark:text-slate-300 dark:ring-slate-600' },
  medium: { label: 'Media',   color: 'bg-blue-50 text-blue-700 ring-1 ring-blue-200 dark:bg-blue-500/10 dark:text-blue-300 dark:ring-blue-500/20' },
  high:   { label: 'Alta',    color: 'bg-orange-50 text-orange-700 ring-1 ring-orange-200 dark:bg-orange-500/10 dark:text-orange-300 dark:ring-orange-500/20' },
  urgent: { label: 'Urgente', color: 'bg-rose-50 text-rose-700 ring-1 ring-rose-200 dark:bg-rose-500/10 dark:text-rose-300 dark:ring-rose-500/20' },
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

  // Omni intercept — ask user to try Omni before creating a ticket
  const [showOmniPrompt, setShowOmniPrompt] = useState(false);

  // Message reply images
  const [msgImages, setMsgImages] = useState([]); // File[]
  const msgFileRef = useRef(null);

  // Image lightbox
  const [lightboxImg, setLightboxImg] = useState(null);

  // Admin status update
  const [updatingStatus, setUpdatingStatus] = useState(false);
  const [statusModal, setStatusModal] = useState(null);

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
    const statusLabel = statusConfig[newStatus]?.label || newStatus;
    setUpdatingStatus(true);
    try {
      await updateTicketStatus(selectedTicket.id, { status: newStatus });
      await openTicket(selectedTicket.id);
      loadTickets();
      setStatusModal({ type: 'success', title: 'Estado actualizado', message: `El ticket se actualizó a "${statusLabel}" correctamente.` });
    } catch (err) {
      console.error(err);
      setStatusModal({ type: 'error', title: 'Error al actualizar', message: err.message || 'No se pudo actualizar el estado del ticket.' });
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
          <div className="flex items-center justify-center h-32 text-gray-400 dark:text-slate-500">
            <Loader2 className="animate-spin mr-2 text-blue-500" size={18} /> Cargando...
          </div>
        ) : tickets.length === 0 ? (
          <div className="flex flex-col items-center justify-center h-40 text-gray-400 dark:text-slate-500">
            <div className="w-14 h-14 rounded-full flex items-center justify-center bg-gradient-to-br from-sky-400 to-blue-600 text-white shadow-md mb-3">
              <LifeBuoy size={26} />
            </div>
            <p className="text-sm font-medium text-gray-500 dark:text-slate-400">No hay tickets</p>
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
                  className={`w-full text-left px-4 py-3 transition-all duration-200 ${isActive ? 'bg-gradient-to-r from-sky-50 to-blue-50/40 dark:from-sky-500/10 dark:to-blue-500/5 border-l-2 border-sky-500 shadow-sm' : 'hover:bg-blue-50/40 dark:hover:bg-slate-700/40 border-l-2 border-transparent'}`}
                >
                  <div className="flex items-center gap-2 mb-1">
                    <span className="text-xs font-mono text-gray-400 dark:text-slate-500">{t.ticket_number}</span>
                    <span className={`text-[10px] px-2 py-0.5 rounded-full font-medium ${st.color}`}>{st.label}</span>
                    {!compact && <span className={`text-[10px] px-2 py-0.5 rounded-full font-medium ${pr.color}`}>{pr.label}</span>}
                    <span className="text-[10px] text-gray-400 dark:text-slate-500 ml-auto">{new Date(t.created_at).toLocaleDateString('es-CL')}</span>
                  </div>
                  <p className="text-sm font-medium text-gray-900 dark:text-white truncate">{t.subject}</p>
                  {!compact && <p className="text-xs text-gray-500 dark:text-slate-400 truncate mt-0.5">{t.description}</p>}
                  {isAdmin && t.agent_name && (
                    <p className="text-[10px] text-gray-400 dark:text-slate-500 mt-1">De: {t.agent_name} ({t.agent_email})</p>
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
        <div className="flex-1 flex flex-col items-center justify-center text-gray-400 dark:text-slate-500">
          <div className="w-16 h-16 rounded-full flex items-center justify-center ring-1 bg-sky-50 text-sky-500 ring-sky-200 dark:bg-sky-500/10 dark:text-sky-300 dark:ring-sky-500/20 mb-3">
            <LifeBuoy size={30} />
          </div>
          <p className="text-sm font-medium text-gray-500 dark:text-slate-400">Selecciona un ticket para ver los detalles</p>
        </div>
      );
    }
    const st = statusConfig[selectedTicket.status] || statusConfig.open;
    const pr = priorityConfig[selectedTicket.priority] || priorityConfig.medium;
    return (
      <div className="flex-1 flex flex-col overflow-hidden">
        {/* Detail header */}
        <div className="p-4 border-b border-gray-100 dark:border-slate-700/60">
          {(!isAdmin || isMobile) && (
            <button onClick={() => setSelectedTicket(null)} className="p-1.5 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-lg mb-2 -ml-1 text-gray-500 dark:text-slate-300">
              <ChevronLeft size={18} />
            </button>
          )}
          <div className="flex items-center gap-2 flex-wrap mb-1.5">
            <span className="text-xs font-mono text-gray-400 dark:text-slate-500">{selectedTicket.ticket_number}</span>
            <span className={`text-[10px] px-2 py-0.5 rounded-full font-medium ${st.color}`}>{st.label}</span>
            <span className={`text-[10px] px-2 py-0.5 rounded-full font-medium ${pr.color}`}>{pr.label}</span>
          </div>
          <h2 className="font-semibold text-gray-900 dark:text-white text-base" style={{ fontFamily: 'Poppins, sans-serif' }}>{selectedTicket.subject}</h2>
          <div className="mt-2 text-xs text-gray-500 dark:text-slate-400 flex flex-wrap gap-x-3 gap-y-1">
            <span>Categoría: <strong className="text-gray-700 dark:text-slate-200">{categoryLabels[selectedTicket.category] || selectedTicket.category}</strong></span>
            <span>De: <strong className="text-gray-700 dark:text-slate-200">{selectedTicket.agent_name}</strong></span>
            <span>{new Date(selectedTicket.created_at).toLocaleString('es-CL')}</span>
          </div>

          {/* Admin: status buttons */}
          {isAdmin && (
            <div className="flex items-center gap-1.5 mt-3 flex-wrap">
              <span className="text-[10px] text-gray-400 dark:text-slate-500 mr-1">Cambiar estado:</span>
              {['open', 'in-progress', 'resolved', 'closed'].map(s => {
                const sc = statusConfig[s];
                const isCurrent = selectedTicket.status === s;
                return (
                  <button
                    key={s}
                    onClick={() => !isCurrent && handleStatusChange(s)}
                    disabled={isCurrent || updatingStatus}
                    className={`text-[10px] px-2.5 py-1 rounded-full font-medium transition-all ${isCurrent ? sc.color + ' ring-2 ring-offset-1 dark:ring-offset-slate-800' : 'bg-gray-100 dark:bg-slate-700 text-gray-500 dark:text-slate-300 hover:bg-gray-200 dark:hover:bg-slate-600'} disabled:cursor-default`}
                  >
                    {updatingStatus && isCurrent ? '...' : sc.label}
                  </button>
                );
              })}
            </div>
          )}
        </div>

        {/* Messages */}
        <div className="flex-1 overflow-y-auto p-4 space-y-3 custom-scrollbar bg-gray-50/50 dark:bg-slate-900/30">
          {(selectedTicket.messages || []).map(msg => {
            const isAdminMsg = msg.sender_type === 'admin';
            const imgs = msg.attachments ? (typeof msg.attachments === 'string' ? JSON.parse(msg.attachments) : msg.attachments) : [];
            return (
              <div key={msg.id} className={`flex ${isAdminMsg ? 'justify-start' : 'justify-end'}`}>
                <div className={`max-w-[80%] rounded-2xl px-3.5 py-2.5 text-sm shadow-md ${isAdminMsg ? 'bg-white dark:bg-slate-700 text-gray-800 dark:text-slate-200 ring-1 ring-gray-100 dark:ring-slate-600' : 'bg-gradient-to-br from-blue-500 to-blue-600 text-white'}`}>
                  <p className={`text-[10px] font-semibold mb-1 ${isAdminMsg ? 'text-gray-500 dark:text-slate-400' : 'text-blue-100'}`}>
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
                          className="w-20 h-20 object-cover rounded-lg cursor-pointer ring-1 ring-black/5 hover:opacity-80 transition-opacity"
                          onClick={() => setLightboxImg(url)}
                        />
                      ))}
                    </div>
                  )}
                  <p className={`text-[9px] mt-1 ${isAdminMsg ? 'text-gray-400 dark:text-slate-500' : 'text-blue-100'}`}>
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
          <div className="border-t border-gray-100 dark:border-slate-700/60 bg-white dark:bg-slate-800">
            {msgImages.length > 0 && (
              <div className="px-3 pt-2 flex flex-wrap gap-1.5">
                {msgImages.map((f, i) => (
                  <div key={i} className="relative group">
                    <img src={URL.createObjectURL(f)} alt="" className="w-14 h-14 object-cover rounded-lg ring-1 ring-gray-200 dark:ring-slate-600" />
                    <button onClick={() => setMsgImages(prev => prev.filter((_, idx) => idx !== i))} className="absolute -top-1.5 -right-1.5 w-4 h-4 bg-rose-500 text-white rounded-full text-[10px] flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">×</button>
                  </div>
                ))}
              </div>
            )}
            <form onSubmit={handleSendMessage} className="p-3 flex gap-2 items-center">
              <input aria-label="Escribe un mensaje" ref={msgFileRef} type="file" accept="image/jpeg,image/png,image/webp,image/gif" multiple className="hidden" onChange={e => {
                const files = Array.from(e.target.files || []);
                setMsgImages(prev => [...prev, ...files].slice(0, 5));
                e.target.value = '';
              }} />
              <button type="button" onClick={() => msgFileRef.current?.click()} disabled={msgImages.length >= 5} className="p-2 text-gray-400 hover:text-blue-600 dark:hover:text-blue-400 disabled:opacity-30" title="Adjuntar imágenes (máx. 5)">
                <Paperclip size={18} />
              </button>
              <input aria-label="Adjuntar archivo"
                type="text"
                value={newMessage}
                onChange={e => setNewMessage(e.target.value)}
                placeholder="Escribe un mensaje..."
                className="flex-1 px-3 py-2 text-sm rounded-lg ring-1 ring-gray-200 dark:ring-slate-700 bg-white dark:bg-slate-900 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
              <button
                type="submit"
                disabled={(!newMessage.trim() && msgImages.length === 0) || sendingMsg}
                className="px-3 py-2 bg-gradient-to-br from-blue-500 to-blue-600 text-white rounded-xl text-sm font-semibold hover:shadow-lg shadow-md transition-all duration-300 disabled:opacity-50 flex items-center gap-1.5"
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
        {/* HERO FULL-BLEED */}
        <div className="relative overflow-hidden px-6 py-6 text-white shadow-lg" style={{ backgroundImage: 'linear-gradient(120deg, #06b6d4, #0284c7)' }}>
          <div className="absolute -top-12 -right-8 w-52 h-52 rounded-full blur-3xl bg-white/20" />
          <div className="absolute inset-0 opacity-[0.06]" style={{ backgroundImage: 'radial-gradient(white 1px, transparent 1px)', backgroundSize: '20px 20px' }} />
          <div className="relative flex flex-wrap items-center justify-between gap-3">
            <div className="flex items-center gap-3">
              <div className="w-12 h-12 rounded-2xl bg-white/15 ring-1 ring-white/25 flex items-center justify-center">
                <LifeBuoy size={26} />
              </div>
              <div>
                <h1 className="text-2xl font-bold tracking-tight" style={{ fontFamily: 'Poppins, sans-serif' }}>Soporte — Administración</h1>
                <p className="text-sm text-white/75">{total} ticket{total !== 1 ? 's' : ''}</p>
              </div>
            </div>
          </div>
        </div>

        {/* Filters bar */}
        <div className="p-4 border-b border-gray-100 dark:border-slate-700/60 bg-white dark:bg-slate-800">
          {/* Filters row */}
          <div className="flex gap-2 flex-wrap items-center">
            <form onSubmit={handleSearch} className="flex-1 min-w-[180px] relative">
              <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
              <input aria-label="Buscar"
                type="text"
                value={search}
                onChange={e => setSearch(e.target.value)}
                placeholder="Buscar tickets..."
                className="w-full pl-9 pr-3 py-2 text-sm rounded-lg ring-1 ring-gray-200 dark:ring-slate-700 bg-white dark:bg-slate-900 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </form>
            <select aria-label="Filtrar por estado"
              value={statusFilter}
              onChange={e => { setStatusFilter(e.target.value); setPage(1); }}
              className="px-3 py-2 text-sm rounded-lg ring-1 ring-gray-200 dark:ring-slate-700 bg-white dark:bg-slate-900 text-gray-700 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
              <option value="">Todos los estados</option>
              <option value="open">Abierto</option>
              <option value="in-progress">En Progreso</option>
              <option value="resolved">Resuelto</option>
              <option value="closed">Cerrado</option>
            </select>
            <div className="flex items-center gap-1.5">
              <span className="text-xs text-gray-400 dark:text-slate-500">Mostrar:</span>
              <select aria-label="Resultados por página"
                value={perPage}
                onChange={e => { setPerPage(Number(e.target.value)); setPage(1); }}
                className="px-2 py-1.5 rounded-lg text-xs ring-1 ring-gray-200 dark:ring-slate-700 bg-white dark:bg-slate-900 text-gray-600 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                {[10, 15, 25, 50].map(n => <option key={n} value={n}>{n}</option>)}
              </select>
            </div>
          </div>
        </div>

        {/* Two-panel area (desktop) / single-panel toggle (mobile) */}
        <div className="flex-1 flex overflow-hidden">
          {/* Left: ticket list — hidden on mobile when a ticket is selected */}
          <div className={`${isMobile ? 'w-full' : 'w-[380px] min-w-[320px]'} border-r border-gray-100 dark:border-slate-700/60 flex flex-col overflow-hidden bg-white dark:bg-slate-800 ${isMobile && selectedTicket ? 'hidden' : ''}`}>
            <div className="flex-1 overflow-y-auto custom-scrollbar">
              {renderTicketList(isMobile ? false : true)}
            </div>
            {/* Pagination */}
            {totalPages > 1 && (
              <div className="p-2 border-t border-gray-100 dark:border-slate-700/60 flex items-center justify-center gap-2">
                <button onClick={() => setPage(p => Math.max(1, p - 1))} disabled={page === 1} className="px-2.5 py-1 text-xs rounded-lg ring-1 ring-gray-200 dark:ring-slate-700 text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700 disabled:opacity-40">Anterior</button>
                <span className="text-xs text-gray-500 dark:text-slate-400">{page} / {totalPages}</span>
                <button onClick={() => setPage(p => Math.min(totalPages, p + 1))} disabled={page === totalPages} className="px-2.5 py-1 text-xs rounded-lg ring-1 ring-gray-200 dark:ring-slate-700 text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700 disabled:opacity-40">Siguiente</button>
              </div>
            )}
          </div>

          {/* Right: detail — hidden on mobile when no ticket selected */}
          <div className={`flex-1 flex flex-col overflow-hidden bg-white dark:bg-slate-800 ${isMobile && !selectedTicket ? 'hidden' : ''}`}>
            {detailLoading ? (
              <div className="flex-1 flex items-center justify-center">
                <Loader2 size={28} className="animate-spin text-blue-600" />
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

        {statusModal && <ResultModal {...statusModal} onClose={() => setStatusModal(null)} />}
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
            <Loader2 size={32} className="animate-spin text-blue-600" />
          </div>
        )}

        {statusModal && <ResultModal {...statusModal} onClose={() => setStatusModal(null)} />}
      </div>
    );
  }

  // Agent list view
  return (
    <div className="h-full flex flex-col overflow-hidden">
      {/* HERO FULL-BLEED */}
      <div className="relative overflow-hidden px-6 py-6 text-white shadow-lg" style={{ backgroundImage: 'linear-gradient(120deg, #06b6d4, #0284c7)' }}>
        <div className="absolute -top-12 -right-8 w-52 h-52 rounded-full blur-3xl bg-white/20" />
        <div className="absolute inset-0 opacity-[0.06]" style={{ backgroundImage: 'radial-gradient(white 1px, transparent 1px)', backgroundSize: '20px 20px' }} />
        <div className="relative flex flex-wrap items-center justify-between gap-3">
          <div className="flex items-center gap-3">
            <div className="w-12 h-12 rounded-2xl bg-white/15 ring-1 ring-white/25 flex items-center justify-center">
              <LifeBuoy size={26} />
            </div>
            <div>
              <h1 className="text-2xl font-bold tracking-tight" style={{ fontFamily: 'Poppins, sans-serif' }}>Soporte</h1>
              <p className="text-sm text-white/75">
                {total} ticket{total !== 1 ? 's' : ''} {statusFilter && `(${statusConfig[statusFilter]?.label || statusFilter})`}
              </p>
            </div>
          </div>
          <button
            onClick={() => setShowOmniPrompt(true)}
            className="flex items-center gap-1.5 px-3.5 py-2 bg-white/15 hover:bg-white/25 ring-1 ring-white/25 text-white rounded-lg text-sm font-semibold transition-all duration-300"
            style={{ fontFamily: 'Poppins, sans-serif' }}
          >
            <Plus size={16} /> Nuevo Ticket
          </button>
        </div>
      </div>

      {/* Filters bar */}
      <div className="p-4 border-b border-gray-100 dark:border-slate-700/60 bg-white dark:bg-slate-800">
        {/* Filters */}
        <div className="flex gap-2 flex-wrap">
          <form onSubmit={handleSearch} className="flex-1 min-w-[200px] relative">
            <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
            <input aria-label="Buscar"
              type="text"
              value={search}
              onChange={e => setSearch(e.target.value)}
              placeholder="Buscar tickets..."
              className="w-full pl-9 pr-3 py-2 text-sm rounded-lg ring-1 ring-gray-200 dark:ring-slate-700 bg-white dark:bg-slate-900 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </form>
          <select aria-label="Filtrar por estado"
            value={statusFilter}
            onChange={e => { setStatusFilter(e.target.value); setPage(1); }}
            className="px-3 py-2 text-sm rounded-lg ring-1 ring-gray-200 dark:ring-slate-700 bg-white dark:bg-slate-900 text-gray-700 dark:text-slate-300 focus:outline-none focus:ring-2 focus:ring-blue-500"
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
      <div className="flex-1 overflow-y-auto custom-scrollbar bg-white dark:bg-slate-800">
        {renderTicketList(false)}
      </div>

      {/* Pagination */}
      {totalPages > 1 && (
        <div className="p-3 border-t border-gray-100 dark:border-slate-700/60 flex items-center justify-center gap-2 bg-white dark:bg-slate-800">
          <button onClick={() => setPage(p => Math.max(1, p - 1))} disabled={page === 1} className="px-3 py-1.5 text-xs rounded-lg ring-1 ring-gray-200 dark:ring-slate-700 text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700 disabled:opacity-40">Anterior</button>
          <span className="text-xs text-gray-500 dark:text-slate-400">{page} / {totalPages}</span>
          <button onClick={() => setPage(p => Math.min(totalPages, p + 1))} disabled={page === totalPages} className="px-3 py-1.5 text-xs rounded-lg ring-1 ring-gray-200 dark:ring-slate-700 text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700 disabled:opacity-40">Siguiente</button>
        </div>
      )}

      {/* Omni intercept modal */}
      {showOmniPrompt && (
        <div role="dialog" aria-modal="true" aria-label="Prompt del asistente" className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" onClick={() => setShowOmniPrompt(false)}>
          <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl max-w-md w-full overflow-hidden" onClick={e => e.stopPropagation()}>
            {/* Header gradient */}
            <div className="bg-gradient-to-br from-blue-500 via-blue-500 to-fuchsia-500 px-6 py-5 text-center">
              <div className="w-16 h-16 mx-auto mb-3 bg-white/20 rounded-full flex items-center justify-center">
                <Sparkles size={32} className="text-white" />
              </div>
              <h3 className="text-lg font-bold text-white">¿Ya le preguntaste a Omni?</h3>
              <p className="text-sm text-white/80 mt-1">Nuestro asistente IA puede resolver la mayoría de dudas</p>
            </div>
            {/* Body */}
            <div className="px-6 py-5">
              <p className="text-sm text-gray-600 dark:text-gray-300 mb-1">
                <strong>Omni Asistente</strong> puede ayudarte con:
              </p>
              <ul className="text-sm text-gray-500 dark:text-gray-400 space-y-1.5 mb-5">
                <li className="flex items-start gap-2"><CheckCircle size={14} className="text-green-500 mt-0.5 shrink-0" /> Dudas sobre cómo usar el portal</li>
                <li className="flex items-start gap-2"><CheckCircle size={14} className="text-green-500 mt-0.5 shrink-0" /> Información de tu empresa y métricas</li>
                <li className="flex items-start gap-2"><CheckCircle size={14} className="text-green-500 mt-0.5 shrink-0" /> Configuración de canales, bots y agentes</li>
                <li className="flex items-start gap-2"><CheckCircle size={14} className="text-green-500 mt-0.5 shrink-0" /> Resolución de problemas comunes</li>
              </ul>
              <div className="flex flex-col gap-2.5">
                <button
                  onClick={() => { setShowOmniPrompt(false); window.dispatchEvent(new Event('openOmniAssistant')); }}
                  className="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-xl text-sm font-semibold hover:from-blue-600 hover:to-blue-700 transition-all shadow-md"
                >
                  <MessageCircle size={16} /> Preguntar a Omni primero
                </button>
                <button
                  onClick={() => { setShowOmniPrompt(false); setShowCreate(true); }}
                  className="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-gray-100 dark:bg-slate-700 text-gray-600 dark:text-gray-300 rounded-xl text-sm font-medium hover:bg-gray-200 dark:hover:bg-slate-600 transition-colors"
                >
                  <Plus size={16} /> Ya pregunté, crear ticket
                </button>
              </div>
              <p className="text-[11px] text-gray-400 dark:text-gray-500 text-center mt-3">
                Si Omni no puede resolver tu caso, te indicará crear un ticket.
              </p>
            </div>
          </div>
        </div>
      )}

      {/* Create ticket modal */}
      {showCreate && (
        <div role="dialog" aria-modal="true" aria-label="Crear solicitud de soporte" className="fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4" onClick={() => setShowCreate(false)}>
          <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl ring-1 ring-gray-100 dark:ring-slate-700/60 max-w-lg w-full max-h-[90vh] overflow-y-auto" onClick={e => e.stopPropagation()}>
            <div className="p-5 border-b border-gray-100 dark:border-slate-700/60 flex items-center justify-between">
              <h3 className="font-semibold text-gray-900 dark:text-white flex items-center gap-2" style={{ fontFamily: 'Poppins, sans-serif' }}>
                <span className="w-8 h-8 rounded-xl flex items-center justify-center bg-gradient-to-br from-sky-400 to-blue-600 text-white shadow-md"><Plus size={16} /></span>
                Nuevo Ticket
              </h3>
              <button onClick={() => setShowCreate(false)} className="p-1.5 hover:bg-gray-100 dark:hover:bg-slate-700 rounded-lg text-gray-500 dark:text-slate-300"><X size={18} /></button>
            </div>
            <form onSubmit={handleCreate} className="p-5 space-y-4">
              {createError && (
                <div className="flex items-center gap-2 p-3 rounded-xl text-sm bg-rose-50 dark:bg-rose-500/10 text-rose-700 dark:text-rose-300 ring-1 ring-rose-200 dark:ring-rose-500/20">
                  <AlertCircle size={16} /> {createError}
                </div>
              )}
              <div>
                <label htmlFor="supportview-fld1" className="block text-xs font-medium text-gray-600 dark:text-slate-300 mb-1" style={{ fontFamily: 'Poppins, sans-serif' }}>Asunto *</label>
                <input id="supportview-fld1"
                  type="text"
                  value={createForm.subject}
                  onChange={e => setCreateForm(f => ({ ...f, subject: e.target.value }))}
                  className="w-full px-3 py-2 text-sm rounded-lg ring-1 ring-gray-200 dark:ring-slate-700 bg-white dark:bg-slate-900 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="Resumen del problema"
                  required
                />
              </div>
              <div>
                <label htmlFor="supportview-fld2" className="block text-xs font-medium text-gray-600 dark:text-slate-300 mb-1" style={{ fontFamily: 'Poppins, sans-serif' }}>Descripción *</label>
                <textarea id="supportview-fld2"
                  value={createForm.description}
                  onChange={e => setCreateForm(f => ({ ...f, description: e.target.value }))}
                  rows={4}
                  className="w-full px-3 py-2 text-sm rounded-lg ring-1 ring-gray-200 dark:ring-slate-700 bg-white dark:bg-slate-900 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
                  placeholder="Describe el problema con detalle..."
                  required
                />
              </div>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                  <label htmlFor="supportview-fld3" className="block text-xs font-medium text-gray-600 dark:text-slate-300 mb-1" style={{ fontFamily: 'Poppins, sans-serif' }}>Categoría</label>
                  <select id="supportview-fld3"
                    value={createForm.category}
                    onChange={e => setCreateForm(f => ({ ...f, category: e.target.value }))}
                    className="w-full px-3 py-2 text-sm rounded-lg ring-1 ring-gray-200 dark:ring-slate-700 bg-white dark:bg-slate-900 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
                  >
                    <option value="general">General</option>
                    <option value="technical">Técnico</option>
                    <option value="billing">Facturación</option>
                    <option value="feature-request">Solicitud de Función</option>
                    <option value="bug">Error/Bug</option>
                  </select>
                </div>
                <div>
                  <label htmlFor="supportview-fld4" className="block text-xs font-medium text-gray-600 dark:text-slate-300 mb-1" style={{ fontFamily: 'Poppins, sans-serif' }}>Prioridad</label>
                  <select id="supportview-fld4"
                    value={createForm.priority}
                    onChange={e => setCreateForm(f => ({ ...f, priority: e.target.value }))}
                    className="w-full px-3 py-2 text-sm rounded-lg ring-1 ring-gray-200 dark:ring-slate-700 bg-white dark:bg-slate-900 text-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500"
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
                <label htmlFor="supportview-fld5" className="block text-xs font-medium text-gray-600 dark:text-slate-300 mb-1" style={{ fontFamily: 'Poppins, sans-serif' }}>Capturas / Imágenes (máx. 5)</label>
                <input id="supportview-fld5" ref={createFileRef} type="file" accept="image/jpeg,image/png,image/webp,image/gif" multiple className="hidden" onChange={e => {
                  const files = Array.from(e.target.files || []);
                  setCreateImages(prev => [...prev, ...files].slice(0, 5));
                  e.target.value = '';
                }} />
                <button type="button" onClick={() => createFileRef.current?.click()} disabled={createImages.length >= 5}
                  className="w-full flex items-center justify-center gap-2 px-3 py-2.5 text-sm border-2 border-dashed border-gray-200 dark:border-slate-600 rounded-lg text-gray-500 dark:text-slate-400 hover:border-blue-400 hover:text-blue-600 dark:hover:text-blue-400 transition-colors disabled:opacity-30">
                  <ImageIcon size={16} /> {createImages.length === 0 ? 'Agregar imágenes' : `${createImages.length}/5 imágenes`}
                </button>
                {createImages.length > 0 && (
                  <div className="flex flex-wrap gap-2 mt-2">
                    {createImages.map((f, i) => (
                      <div key={i} className="relative group">
                        <img src={URL.createObjectURL(f)} alt="" className="w-16 h-16 object-cover rounded-lg ring-1 ring-gray-200 dark:ring-slate-600" />
                        <button type="button" onClick={() => setCreateImages(prev => prev.filter((_, idx) => idx !== i))} className="absolute -top-1.5 -right-1.5 w-5 h-5 bg-rose-500 text-white rounded-full text-xs flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">×</button>
                      </div>
                    ))}
                  </div>
                )}
              </div>
              <div className="flex justify-end gap-2 pt-2">
                <button type="button" onClick={() => { setShowCreate(false); setCreateImages([]); }} className="px-4 py-2 text-sm bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-slate-200 rounded-lg hover:bg-gray-200 dark:hover:bg-slate-600">Cancelar</button>
                <button type="submit" disabled={creating} className="px-4 py-2 text-sm bg-gradient-to-br from-blue-500 to-blue-600 text-white rounded-xl font-semibold hover:shadow-lg hover:-translate-y-0.5 shadow-md transition-all duration-300 disabled:opacity-50 flex items-center gap-1.5" style={{ fontFamily: 'Poppins, sans-serif' }}>
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
          <Loader2 size={32} className="animate-spin text-blue-600" />
        </div>
      )}

      {/* Image lightbox */}
      {lightboxImg && (
        <div className="fixed inset-0 bg-black/80 z-[60] flex items-center justify-center p-4" onClick={() => setLightboxImg(null)}>
          <button onClick={() => setLightboxImg(null)} className="absolute top-4 right-4 p-2 bg-black/50 text-white rounded-full hover:bg-black/70"><X size={20} /></button>
          <img src={lightboxImg} alt="Vista ampliada" className="max-w-full max-h-[90vh] object-contain rounded-lg shadow-2xl" />
        </div>
      )}

      {statusModal && <ResultModal {...statusModal} onClose={() => setStatusModal(null)} />}
    </div>
  );
}
