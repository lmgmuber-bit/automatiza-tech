import { useState, useEffect } from 'react';
import { Plus, Loader2, Search, Building2, Trash2, Eye, Edit3, Download, ChevronLeft, ChevronRight, X, Copy, Check, AlertTriangle } from 'lucide-react';
import { getClients, createClient, updateClient, deleteClient, getImportableWpUsers, importWpUser, getClient, getCrmProspects, importCrmProspect } from '../api';
import ResultModal from './ResultModal';

const planColors = {
  basic: 'bg-gray-100 text-gray-600 ring-1 ring-gray-200 dark:bg-slate-700 dark:text-slate-300 dark:ring-slate-600',
  professional: 'bg-blue-50 text-blue-700 ring-1 ring-blue-200 dark:bg-blue-500/10 dark:text-blue-300 dark:ring-blue-500/20',
  enterprise: 'bg-violet-50 text-violet-700 ring-1 ring-violet-200 dark:bg-violet-500/10 dark:text-violet-300 dark:ring-violet-500/20',
};

const statusColors = {
  trial: 'bg-amber-50 text-amber-700 ring-1 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/20',
  active: 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/20',
  suspended: 'bg-rose-50 text-rose-700 ring-1 ring-rose-200 dark:bg-rose-500/10 dark:text-rose-300 dark:ring-rose-500/20',
  cancelled: 'bg-gray-100 text-gray-500 ring-1 ring-gray-200 dark:bg-slate-700 dark:text-slate-400 dark:ring-slate-600',
};

export default function ClientsView() {
  const [clients, setClients] = useState([]);
  const [loading, setLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [totalPages, setTotalPages] = useState(1);
  const [searchTerm, setSearchTerm] = useState('');
  const [statusFilter, setStatusFilter] = useState('');

  // Modal states
  const [showCreate, setShowCreate] = useState(false);
  const [showImport, setShowImport] = useState(false);
  const [showDetail, setShowDetail] = useState(null);
  const [showEdit, setShowEdit] = useState(null);

  // Create form
  const [form, setForm] = useState({
    company_name: '', contact_name: '', email: '', phone: '',
    plan_type: 'basic', max_channels: 2, max_agents: 3,
    business_type: '', website: '', timezone: 'America/Santiago',
    period_start: '', period_end: '', is_free: false,
  });
  const [submitting, setSubmitting] = useState(false);

  // Import WP
  const [wpUsers, setWpUsers] = useState([]);
  const [wpSearch, setWpSearch] = useState('');
  const [wpLoading, setWpLoading] = useState(false);
  const [importing, setImporting] = useState(null);

  // Import CRM prospects
  const [showImportCrm, setShowImportCrm] = useState(false);
  const [crmProspects, setCrmProspects] = useState([]);
  const [crmSearch, setCrmSearch] = useState('');
  const [crmLoading, setCrmLoading] = useState(false);
  const [importingCrm, setImportingCrm] = useState(null);

  // Result modal state
  const [resultModal, setResultModal] = useState(null);
  // Confirm modal state
  const [confirmModal, setConfirmModal] = useState(null);
  const [copiedKey, setCopiedKey] = useState(false);

  useEffect(() => { loadClients(); }, [page, statusFilter]);

  async function loadClients() {
    setLoading(true);
    try {
      const params = { page, per_page: 15 };
      if (searchTerm) params.search = searchTerm;
      if (statusFilter) params.status = statusFilter;
      const data = await getClients(params);
      setClients(data.data || []);
      setTotalPages(data.total_pages || 1);
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  }

  async function handleCreate(e) {
    e.preventDefault();
    if (form.period_start && form.period_end && form.period_end < form.period_start) {
      setResultModal({ type: 'error', title: 'Error de fechas', message: 'La fecha de fin no puede ser anterior a la fecha de inicio.' });
      return;
    }
    setSubmitting(true);
    try {
      const result = await createClient(form);
      if (result.error) { setResultModal({ type: 'error', title: 'Error', message: result.error }); return; }
      setShowCreate(false);
      setForm({ company_name: '', contact_name: '', email: '', phone: '', plan_type: 'basic', max_channels: 2, max_agents: 3, business_type: '', website: '', timezone: 'America/Santiago', period_start: '', period_end: '', is_free: false });
      loadClients();
      setResultModal({ type: 'success', title: 'Cliente Creado', message: 'Guarda esta API Key, solo se muestra una vez.', detail: result.api_key, detailLabel: 'API Key' });
    } catch (err) {
      setResultModal({ type: 'error', title: 'Error', message: err.message });
    } finally {
      setSubmitting(false);
    }
  }

  async function handleUpdate(e) {
    e.preventDefault();
    if (!showEdit) return;
    if (showEdit.period_start && showEdit.period_end && showEdit.period_end < showEdit.period_start) {
      setResultModal({ type: 'error', title: 'Error de fechas', message: 'La fecha de fin no puede ser anterior a la fecha de inicio.' });
      return;
    }
    setSubmitting(true);
    try {
      await updateClient(showEdit.id, showEdit);
      setShowEdit(null);
      loadClients();
      setResultModal({ type: 'success', title: 'Actualizado', message: 'Los datos del cliente se guardaron correctamente.' });
    } catch (err) {
      setResultModal({ type: 'error', title: 'Error', message: err.message });
    } finally {
      setSubmitting(false);
    }
  }

  function handleDelete(id, name) {
    setConfirmModal({
      title: '¿Eliminar cliente?',
      message: `Se eliminará "${name}" y TODOS sus datos.\nEsta acción no se puede deshacer.`,
      onConfirm: async () => {
        setConfirmModal(null);
        try {
          await deleteClient(id);
          loadClients();
          setResultModal({ type: 'success', title: 'Eliminado', message: `El cliente "${name}" fue eliminado.` });
        } catch (err) {
          setResultModal({ type: 'error', title: 'Error', message: err.message });
        }
      },
    });
  }

  async function loadWpUsers() {
    setWpLoading(true);
    try {
      const users = await getImportableWpUsers(wpSearch);
      setWpUsers(Array.isArray(users) ? users : []);
    } catch (err) {
      console.error(err);
    } finally {
      setWpLoading(false);
    }
  }

  async function handleImport(wpUser) {
    setImporting(wpUser.id);
    try {
      const result = await importWpUser({
        wp_user_id: wpUser.id,
        company_name: wpUser.display_name,
        contact_name: wpUser.display_name,
        plan_type: 'basic',
      });
      if (result.error) {
        setResultModal({ type: 'error', title: 'Error', message: result.error });
      } else {
        loadWpUsers();
        setStatusFilter('');
        setPage(1);
        setResultModal({ type: 'success', title: 'Importado', message: `"${wpUser.display_name}" importado correctamente.\nGuarda esta API Key.\n\n⚠️ Recuerda configurar las fechas de período (inicio/fin) desde la opción Editar del cliente.`, detail: result.api_key, detailLabel: 'API Key' });
      }
    } catch (err) {
      setResultModal({ type: 'error', title: 'Error', message: err.message });
    } finally {
      setImporting(null);
    }
  }

  async function handleViewDetail(id) {
    try {
      const data = await getClient(id);
      setShowDetail(data);
    } catch (err) {
      setResultModal({ type: 'error', title: 'Error', message: err.message });
    }
  }

  async function loadCrmProspects() {
    setCrmLoading(true);
    try {
      const data = await getCrmProspects(crmSearch);
      setCrmProspects(Array.isArray(data) ? data : []);
    } catch (err) {
      console.error(err);
    } finally {
      setCrmLoading(false);
    }
  }

  async function handleImportCrm(prospect) {
    setImportingCrm(prospect.id);
    try {
      const result = await importCrmProspect({
        crm_id: prospect.id,
        company_name: prospect.empresa || prospect.nombre,
        plan_type: 'basic',
      });
      if (result.error) {
        setResultModal({ type: 'error', title: 'Error', message: result.error });
      } else {
        loadCrmProspects();
        setStatusFilter('');
        setPage(1);
        setResultModal({ type: 'success', title: 'Importado', message: `"${prospect.nombre}" importado correctamente.\nGuarda esta API Key.\n\n⚠️ Recuerda configurar las fechas de período (inicio/fin) desde la opción Editar del cliente.`, detail: result.api_key, detailLabel: 'API Key' });
      }
    } catch (err) {
      setResultModal({ type: 'error', title: 'Error', message: err.message });
    } finally {
      setImportingCrm(null);
    }
  }

  return (
    <div className="flex-1 h-full overflow-y-auto bg-gray-50 dark:bg-slate-900">
      {/* HERO FULL-BLEED */}
      <div className="relative overflow-hidden px-6 py-6 text-white shadow-lg" style={{ backgroundImage: 'linear-gradient(120deg, #10b981, #16a34a)' }}>
        <div className="absolute -top-12 -right-8 w-52 h-52 rounded-full blur-3xl bg-white/20" />
        <div className="absolute inset-0 opacity-[0.06]" style={{ backgroundImage: 'radial-gradient(white 1px, transparent 1px)', backgroundSize: '20px 20px' }} />
        <div className="relative flex flex-wrap items-center justify-between gap-3">
          <div className="flex items-center gap-3">
            <div className="w-12 h-12 rounded-2xl bg-white/15 backdrop-blur-sm ring-1 ring-white/25 flex items-center justify-center">
              <Building2 size={26} />
            </div>
            <div>
              <h1 className="text-2xl font-bold tracking-tight" style={{ fontFamily: 'Poppins, sans-serif' }}>Gestión de Clientes</h1>
              <p className="text-sm text-white/75">Administra los clientes de AutomatizaTech</p>
            </div>
          </div>
          <div className="flex gap-2 flex-wrap">
            <button onClick={() => { setShowImportCrm(true); loadCrmProspects(); }} className="flex items-center gap-2 px-3 py-2 bg-white/15 hover:bg-white/25 ring-1 ring-white/25 text-white backdrop-blur-sm rounded-lg shadow-sm text-sm font-medium transition-all duration-300">
              <Download size={16} /> Importar Prospectos
            </button>
            <button onClick={() => { setShowImport(true); loadWpUsers(); }} className="flex items-center gap-2 px-3 py-2 bg-white/15 hover:bg-white/25 ring-1 ring-white/25 text-white backdrop-blur-sm rounded-lg shadow-sm text-sm font-medium transition-all duration-300">
              <Download size={16} /> Importar WP
            </button>
            <button onClick={() => setShowCreate(true)} className="flex items-center gap-2 px-3 py-2 bg-white/15 hover:bg-white/25 ring-1 ring-white/25 text-white backdrop-blur-sm rounded-lg shadow-sm text-sm font-semibold transition-all duration-300">
              <Plus size={16} /> Nuevo Cliente
            </button>
          </div>
        </div>
      </div>

      {/* CONTENIDO */}
      <div className="p-4 sm:p-6">
      <div className="max-w-6xl mx-auto">
        {/* Filters */}
        <div className="flex flex-col sm:flex-row gap-3 mb-4">
          <div className="relative flex-1">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" size={16} />
            <input
              type="text" value={searchTerm}
              onChange={e => setSearchTerm(e.target.value)}
              onKeyDown={e => e.key === 'Enter' && loadClients()}
              placeholder="Buscar por nombre, email..."
              className="w-full pl-9 pr-3 py-2 bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 rounded-lg text-sm text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>
          <div className="flex gap-1.5 flex-wrap">
            {['', 'trial', 'active', 'suspended'].map(s => (
              <button key={s} onClick={() => { setStatusFilter(s); setPage(1); }}
                className={`px-3.5 py-2 rounded-full text-xs font-medium transition-all capitalize ${statusFilter === s ? 'bg-gradient-to-br from-blue-500 to-indigo-600 text-white shadow-md' : 'bg-white text-gray-500 ring-1 ring-gray-200 hover:bg-gray-100 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700 dark:hover:bg-slate-700'}`}>
                {s || 'Todos'}
              </button>
            ))}
          </div>
        </div>

        {/* Client Cards */}
        {loading ? (
          <div className="flex items-center justify-center py-20 text-gray-400 dark:text-slate-500">
            <Loader2 className="animate-spin mr-2" size={20} /> Cargando clientes...
          </div>
        ) : clients.length === 0 ? (
          <div className="flex flex-col items-center justify-center py-20 text-center">
            <span className="w-16 h-16 rounded-2xl flex items-center justify-center bg-gradient-to-br from-sky-400 to-blue-600 text-white shadow-lg mb-4">
              <Building2 size={30} />
            </span>
            <p className="text-sm font-medium text-gray-600 dark:text-slate-300">No hay clientes registrados</p>
            <p className="text-xs text-gray-400 dark:text-slate-500 mt-1">Crea o importa tu primer cliente para comenzar</p>
          </div>
        ) : (
          <div className="grid gap-3">
            {clients.map(c => (
              <div key={c.id} className="group bg-white dark:bg-slate-800 rounded-2xl p-4 md:p-5 shadow-md ring-1 ring-gray-100 dark:ring-slate-700/60 hover:shadow-xl hover:-translate-y-0.5 hover:bg-blue-50/40 dark:hover:bg-slate-700/40 transition-all duration-300 flex flex-col sm:flex-row items-start sm:items-center gap-3">
                <div className="w-12 h-12 rounded-2xl bg-gradient-to-br from-sky-400 to-blue-600 flex items-center justify-center text-white font-bold text-lg shrink-0 shadow-md group-hover:scale-105 transition-transform duration-300" style={{ fontFamily: 'Poppins, sans-serif' }}>
                  {(c.company_name || '?')[0].toUpperCase()}
                </div>
                <div className="flex-1 min-w-0">
                  <h4 className="font-semibold text-sm text-gray-900 dark:text-white" style={{ fontFamily: 'Poppins, sans-serif' }}>{c.company_name}</h4>
                  <p className="text-xs text-gray-500 dark:text-slate-400">{c.contact_name} · {c.email}</p>
                  <div className="flex items-center gap-2 mt-1.5 flex-wrap">
                    <span className={`px-2 py-0.5 rounded-full text-[10px] font-semibold capitalize ${statusColors[c.status] || ''}`}>{c.status}</span>
                    <span className={`px-2 py-0.5 rounded-full text-[10px] font-semibold capitalize ${planColors[c.plan_type] || ''}`}>{c.plan_type}</span>
                    {c.is_free === '1' ? (
                      <span className="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/20">🆓 Free</span>
                    ) : c.period_end ? (() => {
                      const days = Math.ceil((new Date(c.period_end + 'T23:59:59') - new Date()) / 86400000);
                      if (days <= 0) return <span className="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-rose-50 text-rose-700 ring-1 ring-rose-200 dark:bg-rose-500/10 dark:text-rose-300 dark:ring-rose-500/20">⛔ Expirado</span>;
                      if (days <= 10) return <span className="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-50 text-amber-700 ring-1 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/20">⏳ {days}d restantes</span>;
                      return <span className="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/20">📅 {new Date(c.period_end + 'T12:00:00').toLocaleDateString('es')}</span>;
                    })() : (
                      <span className="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-gray-100 text-gray-500 ring-1 ring-gray-200 dark:bg-slate-700 dark:text-slate-400 dark:ring-slate-600" title="Sin fechas de período">⚠️ Sin período</span>
                    )}
                    <span className="text-[10px] text-gray-400 dark:text-slate-500">📡 {c.active_channels || 0} canales · 💬 {c.total_conversations || 0} conv · 👤 {c.active_agents || 0} agentes</span>
                  </div>
                </div>
                <div className="flex items-center gap-1 shrink-0">
                  <button onClick={() => handleViewDetail(c.id)} className="p-2 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700 text-gray-400 hover:text-gray-600 dark:hover:text-slate-200 transition-colors" title="Ver detalle"><Eye size={16} /></button>
                  <button onClick={() => setShowEdit({ ...c })} className="p-2 rounded-lg hover:bg-blue-50 dark:hover:bg-blue-500/10 text-gray-400 hover:text-blue-600 dark:hover:text-blue-300 transition-colors" title="Editar"><Edit3 size={16} /></button>
                  <button onClick={() => handleDelete(c.id, c.company_name)} className="p-2 rounded-lg hover:bg-rose-50 dark:hover:bg-rose-500/10 text-rose-400 hover:text-rose-600 dark:hover:text-rose-300 transition-colors" title="Eliminar"><Trash2 size={16} /></button>
                </div>
              </div>
            ))}
          </div>
        )}

        {/* Pagination */}
        {totalPages > 1 && (
          <div className="flex items-center justify-center gap-2 mt-6">
            <button onClick={() => setPage(p => Math.max(1, p - 1))} disabled={page === 1}
              className="p-2 rounded-lg bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700 disabled:opacity-30 transition-colors">
              <ChevronLeft size={16} />
            </button>
            <span className="text-sm text-gray-500 dark:text-slate-400">Página {page} de {totalPages}</span>
            <button onClick={() => setPage(p => Math.min(totalPages, p + 1))} disabled={page === totalPages}
              className="p-2 rounded-lg bg-white dark:bg-slate-800 border border-gray-200 dark:border-slate-700 text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-700 disabled:opacity-30 transition-colors">
              <ChevronRight size={16} />
            </button>
          </div>
        )}

        {/* ============ CREATE MODAL ============ */}
        {showCreate && (
          <Modal title="Nuevo Cliente" onClose={() => setShowCreate(false)}>
            <form onSubmit={handleCreate} className="space-y-4">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <FormField label="Nombre empresa" value={form.company_name} onChange={v => setForm({...form, company_name: v})} required />
                <FormField label="Contacto" value={form.contact_name} onChange={v => setForm({...form, contact_name: v})} required />
                <FormField label="Email" type="email" value={form.email} onChange={v => setForm({...form, email: v})} required />
                <FormField label="Teléfono" value={form.phone} onChange={v => setForm({...form, phone: v})} />
                <div>
                  <label className="block text-xs font-medium text-gray-500 mb-1">Plan</label>
                  <select value={form.plan_type} onChange={e => setForm({...form, plan_type: e.target.value})}
                    className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                    <option value="basic">Basic</option>
                    <option value="professional">Professional</option>
                    <option value="enterprise">Enterprise</option>
                  </select>
                </div>
                <FormField label="Tipo de negocio" value={form.business_type} onChange={v => setForm({...form, business_type: v})} placeholder="Ej: Peluquería, Restaurante..." />
                <FormField label="Website" value={form.website} onChange={v => setForm({...form, website: v})} />
                <FormField label="Max canales" type="number" value={form.max_channels} onChange={v => setForm({...form, max_channels: parseInt(v) || 2})} />
                <FormField label="Max agentes" type="number" value={form.max_agents} onChange={v => setForm({...form, max_agents: parseInt(v) || 3})} />
              </div>
              <div className="border-t border-gray-200 dark:border-slate-700 pt-4 mt-2">
                <p className="text-xs font-semibold text-gray-600 dark:text-gray-300 mb-3">📅 Período de servicio</p>
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <FormField label="Fecha inicio" type="date" value={form.period_start} onChange={v => setForm({...form, period_start: v})} max={form.period_end || undefined} />
                  <FormField label="Fecha fin" type="date" value={form.period_end} onChange={v => setForm({...form, period_end: v})} min={form.period_start || undefined} error={form.period_start && form.period_end && form.period_end < form.period_start ? 'No puede ser anterior a la fecha inicio' : ''} />
                </div>
                <label className="flex items-center gap-2 mt-3 cursor-pointer">
                  <input type="checkbox" checked={form.is_free} onChange={e => setForm({...form, is_free: e.target.checked})} className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                  <span className="text-xs text-gray-600 dark:text-gray-300">Período gratuito (free) — sin vencimiento</span>
                </label>
              </div>
              <div className="flex gap-2 pt-2">
                <button type="submit" disabled={submitting} className="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
                  {submitting ? <Loader2 size={16} className="animate-spin" /> : 'Crear Cliente'}
                </button>
                <button type="button" onClick={() => setShowCreate(false)} className="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm">Cancelar</button>
              </div>
            </form>
          </Modal>
        )}

        {/* ============ EDIT MODAL ============ */}
        {showEdit && (
          <Modal title={`Editar: ${showEdit.company_name}`} onClose={() => setShowEdit(null)}>
            <form onSubmit={handleUpdate} className="space-y-4">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <FormField label="Nombre empresa" value={showEdit.company_name || ''} onChange={v => setShowEdit({...showEdit, company_name: v})} />
                <FormField label="Contacto" value={showEdit.contact_name || ''} onChange={v => setShowEdit({...showEdit, contact_name: v})} />
                <FormField label="Email" value={showEdit.email || ''} onChange={v => setShowEdit({...showEdit, email: v})} />
                <FormField label="Teléfono" value={showEdit.phone || ''} onChange={v => setShowEdit({...showEdit, phone: v})} />
                <div>
                  <label className="block text-xs font-medium text-gray-500 mb-1">Plan</label>
                  <select value={showEdit.plan_type || 'basic'} onChange={e => setShowEdit({...showEdit, plan_type: e.target.value})}
                    className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                    <option value="basic">Basic</option>
                    <option value="professional">Professional</option>
                    <option value="enterprise">Enterprise</option>
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-medium text-gray-500 mb-1">Estado</label>
                  <select value={showEdit.status || 'trial'} onChange={e => setShowEdit({...showEdit, status: e.target.value})}
                    className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500">
                    <option value="trial">Trial</option>
                    <option value="active">Active</option>
                    <option value="suspended">Suspended</option>
                    <option value="cancelled">Cancelled</option>
                  </select>
                </div>
                <FormField label="Website" value={showEdit.website || ''} onChange={v => setShowEdit({...showEdit, website: v})} />
                <FormField label="Tipo de negocio" value={showEdit.business_type || ''} onChange={v => setShowEdit({...showEdit, business_type: v})} />
                <FormField label="Max canales" type="number" value={showEdit.max_channels || 2} onChange={v => setShowEdit({...showEdit, max_channels: parseInt(v) || 2})} />
                <FormField label="Max agentes" type="number" value={showEdit.max_agents || 3} onChange={v => setShowEdit({...showEdit, max_agents: parseInt(v) || 3})} />
              </div>
              <div className="border-t border-gray-200 dark:border-slate-700 pt-4 mt-2">
                <p className="text-xs font-semibold text-gray-600 dark:text-gray-300 mb-3">📅 Período de servicio</p>
                {!showEdit.period_start && !showEdit.period_end && !showEdit.is_free && (
                  <div className="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-lg p-3 mb-3 flex items-start gap-2">
                    <AlertTriangle size={16} className="text-amber-500 shrink-0 mt-0.5" />
                    <p className="text-xs text-amber-700 dark:text-amber-300">Este cliente no tiene fechas de período configuradas. Configúralas para activar el control de vencimiento y recordatorios automáticos.</p>
                  </div>
                )}
                <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <FormField label="Fecha inicio" type="date" value={showEdit.period_start || ''} onChange={v => setShowEdit({...showEdit, period_start: v})} max={showEdit.period_end || undefined} />
                  <FormField label="Fecha fin" type="date" value={showEdit.period_end || ''} onChange={v => setShowEdit({...showEdit, period_end: v})} min={showEdit.period_start || undefined} error={showEdit.period_start && showEdit.period_end && showEdit.period_end < showEdit.period_start ? 'No puede ser anterior a la fecha inicio' : ''} />
                </div>
                <label className="flex items-center gap-2 mt-3 cursor-pointer">
                  <input type="checkbox" checked={!!showEdit.is_free || showEdit.is_free === '1'} onChange={e => setShowEdit({...showEdit, is_free: e.target.checked ? '1' : '0'})} className="w-4 h-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500" />
                  <span className="text-xs text-gray-600 dark:text-gray-300">Período gratuito (free) — sin vencimiento</span>
                </label>
              </div>
              <div>
                <label className="block text-xs font-medium text-gray-500 mb-1">Notas internas</label>
                <textarea value={showEdit.notes || ''} onChange={e => setShowEdit({...showEdit, notes: e.target.value})} rows={3}
                  className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500" />
              </div>
              <div className="flex gap-2 pt-2">
                <button type="submit" disabled={submitting} className="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
                  {submitting ? <Loader2 size={16} className="animate-spin" /> : 'Guardar'}
                </button>
                <button type="button" onClick={() => setShowEdit(null)} className="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm">Cancelar</button>
              </div>
            </form>
          </Modal>
        )}

        {/* ============ DETAIL MODAL ============ */}
        {showDetail && (
          <Modal title={showDetail.company_name} onClose={() => setShowDetail(null)}>
            <div className="space-y-3 text-sm">
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <Detail label="Contacto" value={showDetail.contact_name} />
                <Detail label="Email" value={showDetail.email} />
                <Detail label="Teléfono" value={showDetail.phone || '-'} />
                <Detail label="Plan" value={showDetail.plan_type} />
                <Detail label="Estado" value={showDetail.status} />
                <Detail label="Max Canales" value={showDetail.max_channels} />
                <Detail label="Max Agentes" value={showDetail.max_agents} />
                <Detail label="Tipo Negocio" value={showDetail.business_type || '-'} />
                <Detail label="Website" value={showDetail.website || '-'} />
                <Detail label="Timezone" value={showDetail.timezone || '-'} />
                <Detail label="Creado" value={showDetail.created_at ? new Date(showDetail.created_at).toLocaleDateString('es') : '-'} />
                <Detail label="Trial hasta" value={showDetail.trial_ends_at ? new Date(showDetail.trial_ends_at).toLocaleDateString('es') : '-'} />
                <Detail label="Período inicio" value={showDetail.period_start ? new Date(showDetail.period_start + 'T12:00:00').toLocaleDateString('es') : '-'} />
                <Detail label="Período fin" value={showDetail.period_end ? new Date(showDetail.period_end + 'T12:00:00').toLocaleDateString('es') : '-'} />
                <Detail label="Free" value={showDetail.is_free === '1' || showDetail.is_free === true ? '✅ Sí' : '❌ No'} />
              </div>
              {!showDetail.period_start && !showDetail.period_end && showDetail.is_free !== '1' && (
                <div className="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-lg p-3 flex items-start gap-2">
                  <AlertTriangle size={16} className="text-amber-500 shrink-0 mt-0.5" />
                  <p className="text-xs text-amber-700 dark:text-amber-300">⚠️ Este cliente no tiene fechas de período configuradas. Edítalo para agregar fechas y activar recordatorios de vencimiento.</p>
                </div>
              )}
              {showDetail.api_key && (
                <div className="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-700 rounded-lg p-3">
                  <div className="flex items-center justify-between mb-1">
                    <p className="text-xs font-semibold text-yellow-700 dark:text-yellow-400">API Key</p>
                    <button
                      onClick={() => { navigator.clipboard.writeText(showDetail.api_key); setCopiedKey(true); setTimeout(() => setCopiedKey(false), 2000); }}
                      className="flex items-center gap-1 px-2 py-0.5 text-xs rounded-md bg-yellow-200 dark:bg-yellow-800 text-yellow-800 dark:text-yellow-200 hover:bg-yellow-300 dark:hover:bg-yellow-700 transition-colors">
                      {copiedKey ? <><Check size={12} /> Copiado</> : <><Copy size={12} /> Copiar</>}
                    </button>
                  </div>
                  <code className="text-xs text-yellow-800 dark:text-yellow-300 break-all">{showDetail.api_key}</code>
                </div>
              )}
              {showDetail.notes && (
                <div>
                  <p className="text-xs font-semibold text-gray-500 mb-1">Notas</p>
                  <p className="text-xs text-gray-600 whitespace-pre-wrap">{showDetail.notes}</p>
                </div>
              )}
            </div>
          </Modal>
        )}

        {/* ============ IMPORT WP USERS MODAL ============ */}
        {showImport && (
          <Modal title="Importar Usuario WordPress" onClose={() => setShowImport(false)} wide>
            <div className="space-y-4">
              <div className="relative">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" size={16} />
                <input
                  type="text" value={wpSearch}
                  onChange={e => setWpSearch(e.target.value)}
                  onKeyDown={e => e.key === 'Enter' && loadWpUsers()}
                  placeholder="Buscar usuarios WP..."
                  className="w-full pl-9 pr-3 py-2 bg-white border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>

              {wpLoading ? (
                <div className="flex items-center justify-center py-8 text-gray-400">
                  <Loader2 className="animate-spin mr-2" size={16} /> Buscando...
                </div>
              ) : wpUsers.length === 0 ? (
                <p className="text-center text-sm text-gray-400 py-8">No hay usuarios disponibles para importar</p>
              ) : (
                <div className="max-h-[400px] overflow-y-auto space-y-2">
                  {wpUsers.map(u => (
                    <div key={u.id} className="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                      <div className="w-10 h-10 rounded-full bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center text-white font-bold text-sm shrink-0">
                        {u.display_name[0].toUpperCase()}
                      </div>
                      <div className="flex-1 min-w-0">
                        <p className="font-medium text-sm truncate">{u.display_name}</p>
                        <p className="text-xs text-gray-500 truncate">{u.email} · {(u.roles || []).join(', ')}</p>
                      </div>
                      <button
                        onClick={() => handleImport(u)}
                        disabled={importing === u.id}
                        className="px-3 py-1.5 bg-amber-500 text-white rounded-lg text-xs font-medium hover:bg-amber-600 disabled:opacity-50 shrink-0"
                      >
                        {importing === u.id ? <Loader2 size={14} className="animate-spin" /> : 'Importar'}
                      </button>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </Modal>
        )}

        {/* ============ IMPORT CRM PROSPECTS MODAL ============ */}
        {showImportCrm && (
          <Modal title="Importar Prospectos AutomatizaTech" onClose={() => setShowImportCrm(false)} wide>
            <div className="space-y-4">
              <div className="relative">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" size={16} />
                <input
                  type="text" value={crmSearch}
                  onChange={e => setCrmSearch(e.target.value)}
                  onKeyDown={e => e.key === 'Enter' && loadCrmProspects()}
                  placeholder="Buscar por nombre, email, empresa..."
                  className="w-full pl-9 pr-3 py-2 bg-white border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>

              {crmLoading ? (
                <div className="flex items-center justify-center py-8 text-gray-400">
                  <Loader2 className="animate-spin mr-2" size={16} /> Buscando prospectos...
                </div>
              ) : crmProspects.length === 0 ? (
                <p className="text-center text-sm text-gray-400 py-8">No hay prospectos disponibles para importar</p>
              ) : (
                <div className="max-h-[400px] overflow-y-auto space-y-2">
                  {crmProspects.map(p => (
                    <div key={p.id} className="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                      <div className={`w-10 h-10 rounded-full flex items-center justify-center text-white font-bold text-sm shrink-0 ${
                        p.tipo === 'cliente' ? 'bg-gradient-to-br from-green-400 to-emerald-600' :
                        p.tipo === 'cerrado' ? 'bg-gradient-to-br from-gray-400 to-gray-600' :
                        'bg-gradient-to-br from-blue-400 to-indigo-600'
                      }`}>
                        {(p.nombre || '?')[0].toUpperCase()}
                      </div>
                      <div className="flex-1 min-w-0">
                        <p className="font-medium text-sm truncate">{p.nombre}</p>
                        <p className="text-xs text-gray-500 truncate">
                          {p.email || 'Sin email'} {p.empresa ? `· ${p.empresa}` : ''} {p.rubro ? `· ${p.rubro}` : ''}
                        </p>
                        <div className="flex gap-1 mt-0.5">
                          <span className={`text-[10px] px-1.5 py-0.5 rounded-full font-medium ${
                            p.tipo === 'cliente' ? 'bg-green-100 text-green-700' :
                            p.tipo === 'cerrado' ? 'bg-gray-100 text-gray-500' :
                            'bg-blue-100 text-blue-700'
                          }`}>{p.tipo}</span>
                          {p.origen && <span className="text-[10px] px-1.5 py-0.5 rounded-full bg-purple-100 text-purple-700 font-medium">{p.origen}</span>}
                        </div>
                      </div>
                      <button
                        onClick={() => handleImportCrm(p)}
                        disabled={importingCrm === p.id}
                        className="px-3 py-1.5 bg-emerald-500 text-white rounded-lg text-xs font-medium hover:bg-emerald-600 disabled:opacity-50 shrink-0"
                      >
                        {importingCrm === p.id ? <Loader2 size={14} className="animate-spin" /> : 'Importar'}
                      </button>
                    </div>
                  ))}
                </div>
              )}
            </div>
          </Modal>
        )}
        
        {/* ============ RESULT MODAL ============ */}
        {resultModal && (
          <ResultModal
            type={resultModal.type}
            title={resultModal.title}
            message={resultModal.message}
            detail={resultModal.detail}
            detailLabel={resultModal.detailLabel}
            onClose={() => setResultModal(null)}
          />
        )}

        {/* ============ CONFIRM MODAL ============ */}
        {confirmModal && (
          <ResultModal
            type="confirm"
            title={confirmModal.title}
            message={confirmModal.message}
            onClose={() => setConfirmModal(null)}
            onConfirm={confirmModal.onConfirm}
            confirmText="Sí, eliminar"
            cancelText="Cancelar"
          />
        )}
      </div>
      </div>
    </div>
  );
}

// ============ HELPER COMPONENTS ============

function Modal({ title, onClose, children, wide }) {
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4" onClick={onClose}>
      <div className="absolute inset-0 bg-black/40 backdrop-blur-sm" />
      <div className={`relative bg-white dark:bg-slate-800 rounded-2xl shadow-xl w-full ${wide ? 'max-w-2xl' : 'max-w-lg'} max-h-[90vh] overflow-y-auto`} onClick={e => e.stopPropagation()}>
        <div className="flex items-center justify-between px-6 py-4 border-b border-gray-100 dark:border-slate-700">
          <h3 className="font-semibold text-gray-900 dark:text-white">{title}</h3>
          <button onClick={onClose} className="p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-slate-700 text-gray-400"><X size={18} /></button>
        </div>
        <div className="px-6 py-4">{children}</div>
      </div>
    </div>
  );
}

function FormField({ label, value, onChange, type = 'text', required, placeholder, min, max, error }) {
  return (
    <div>
      <label className="block text-xs font-medium text-gray-500 mb-1">{label}</label>
      <input
        type={type} value={value} onChange={e => onChange(e.target.value)}
        required={required} placeholder={placeholder} min={min} max={max}
        className={`w-full px-3 py-2 border rounded-lg text-sm focus:ring-2 focus:ring-blue-500 ${error ? 'border-red-400 bg-red-50 dark:bg-red-900/10' : 'border-gray-200'}`}
      />
      {error && <p className="text-[10px] text-red-500 mt-0.5">{error}</p>}
    </div>
  );
}

function Detail({ label, value }) {
  return (
    <div>
      <p className="text-[10px] text-gray-400 uppercase tracking-wide">{label}</p>
      <p className="text-sm font-medium text-gray-700">{value}</p>
    </div>
  );
}
