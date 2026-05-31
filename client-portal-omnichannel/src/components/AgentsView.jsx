import { useState, useEffect, useCallback } from 'react';
import { Plus, Loader2, Users, UserCheck, ShieldCheck, Headphones, Pencil, Trash2, X, Search, ArrowUp, ArrowDown, ArrowUpDown, ChevronLeft, ChevronRight, AlertTriangle } from 'lucide-react';
import { getAgentsPaginated, createAgent, updateAgent, deleteAgent, getIsAdmin, getIsAgent, isSupervisorOrAdmin, getAgentRole, getClients, getPeriodStatus, getChannels } from '../api';
import ConfirmDeleteModal from './ConfirmDeleteModal';
import ResultModal from './ResultModal';

const roleIcons = {
  admin: ShieldCheck,
  supervisor: UserCheck,
  agent: Headphones,
};

const roleLabels = {
  admin: 'Administrador',
  supervisor: 'Supervisor',
  agent: 'Agente',
};

export default function AgentsView() {
  const [agents, setAgents] = useState([]);
  const [agentsMeta, setAgentsMeta] = useState({ total: 0, page: 1, total_pages: 1 });
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(10);
  const [sort, setSort] = useState({ orderby: 'created_at', order: 'DESC' });
  const [search, setSearch] = useState('');
  const [searchInput, setSearchInput] = useState('');
  const [loading, setLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState({ name: '', email: '', role: 'agent', max_concurrent_chats: 5, client_id: '', password: '', channel_id: '', schedule_start: '', schedule_end: '', available_days: '1,2,3,4,5' });
  const [submitting, setSubmitting] = useState(false);
  const [clientsList, setClientsList] = useState([]);
  const [editingAgent, setEditingAgent] = useState(null);
  const [editForm, setEditForm] = useState({});
  const [deleteTarget, setDeleteTarget] = useState(null);
  const [deleting, setDeleting] = useState(false);
  const [resultModal, setResultModal] = useState(null);
  const [maxAgents, setMaxAgents] = useState(null);
  const [planType, setPlanType] = useState('');
  const [channelsList, setChannelsList] = useState([]);

  const isClientLogin = !getIsAdmin() && !getIsAgent();
  const canManage = getIsAdmin() || isClientLogin || isSupervisorOrAdmin();
  // Only AT admin (WP admin) can delete agents
  const canDelete = getIsAdmin();
  // Supervisor can only assign agent/supervisor roles, NOT admin
  const agentRole = getAgentRole();
  const isAgentSupervisor = getIsAgent() && agentRole === 'supervisor';
  const availableRoles = (getIsAdmin() || isClientLogin)
    ? [{ value: 'agent', label: 'Agente' }, { value: 'supervisor', label: 'Supervisor' }, { value: 'admin', label: 'Administrador' }]
    : isAgentSupervisor
      ? [{ value: 'agent', label: 'Agente' }, { value: 'supervisor', label: 'Supervisor' }]
      : [{ value: 'agent', label: 'Agente' }, { value: 'supervisor', label: 'Supervisor' }, { value: 'admin', label: 'Administrador' }];

  useEffect(() => {
    if (getIsAdmin()) {
      getClients({ per_page: 100 }).then(data => setClientsList(data.data || [])).catch(() => {});
    }
    // Load channels
    getChannels().then(chs => setChannelsList(Array.isArray(chs) ? chs : [])).catch(() => {});
    // Fetch plan limits for client/agent
    if (!getIsAdmin()) {
      getPeriodStatus().then(data => {
        if (data && data.max_agents) setMaxAgents(data.max_agents);
        if (data && data.plan_type) setPlanType(data.plan_type);
      }).catch(() => {});
    }
  }, []);

  const loadAgents = useCallback(async () => {
    setLoading(true);
    try {
      const data = await getAgentsPaginated({
        page,
        per_page: perPage,
        orderby: sort.orderby,
        order: sort.order,
        search,
      });
      if (data.data) {
        setAgents(data.data);
        setAgentsMeta({ total: data.total, page: data.page, total_pages: data.total_pages });
      } else {
        setAgents(Array.isArray(data) ? data : []);
        setAgentsMeta({ total: 0, page: 1, total_pages: 1 });
      }
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  }, [page, perPage, sort, search]);

  useEffect(() => {
    loadAgents();
  }, [loadAgents]);

  async function handleCreate(e) {
    e.preventDefault();
    setSubmitting(true);
    try {
      const result = await createAgent(form);
      if (result.error) {
        setResultModal({ type: 'error', title: 'Error', message: result.error });
      } else {
        setShowForm(false);
        setForm({ name: '', email: '', role: 'agent', max_concurrent_chats: 5, client_id: '', password: '', channel_id: '', schedule_start: '', schedule_end: '', available_days: '1,2,3,4,5' });
        loadAgents();
      }
    } catch (err) {
      setResultModal({ type: 'error', title: 'Error', message: err.message });
    } finally {
      setSubmitting(false);
    }
  }

  function startEdit(agent) {
    setEditingAgent(agent.id);
    setEditForm({
      name: agent.name || '',
      email: agent.email || '',
      role: agent.role || 'agent',
      max_concurrent_chats: agent.max_concurrent_chats || 5,
      status: agent.status || 'active',
      department: agent.department || '',
      channel_id: agent.channel_id || '',
      password: '',
      schedule_start: agent.schedule_start || '',
      schedule_end: agent.schedule_end || '',
      available_days: agent.available_days || '1,2,3,4,5',
    });
  }

  async function handleUpdate(e) {
    e.preventDefault();
    setSubmitting(true);
    try {
      const data = { ...editForm };
      if (!data.password) delete data.password;
      await updateAgent(editingAgent, data);
      setEditingAgent(null);
      loadAgents();
    } catch (err) {
      setResultModal({ type: 'error', title: 'Error', message: err.message });
    } finally {
      setSubmitting(false);
    }
  }

  async function handleDelete(confirmApiKey) {
    setDeleting(true);
    try {
      await deleteAgent(deleteTarget.id, confirmApiKey);
      setDeleteTarget(null);
      loadAgents();
    } catch (err) {
      setDeleting(false);
      throw err;
    }
    setDeleting(false);
  }

  // Check if agent limit is reached (only for non-admin)
  const totalAgents = agentsMeta.total || agents.length;
  const isAtLimit = !getIsAdmin() && maxAgents !== null && totalAgents >= maxAgents;

  return (
    <div className="h-full overflow-y-auto p-4 sm:p-6 bg-gray-50 dark:bg-slate-900">
      <div className="max-w-4xl mx-auto">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
          <div className="flex items-center gap-3">
            <span className="w-11 h-11 rounded-2xl flex items-center justify-center bg-gradient-to-br from-sky-400 to-blue-600 text-white shadow-md shrink-0">
              <Users size={22} />
            </span>
            <div>
              <h1 className="text-2xl font-bold tracking-tight text-gray-900 dark:text-white" style={{ fontFamily: 'Poppins, sans-serif' }}>Agentes</h1>
              <p className="text-sm text-gray-500 dark:text-slate-400 mt-1">Gestiona los agentes que atienden las conversaciones</p>
              {!getIsAdmin() && maxAgents !== null && (
                <p className="text-xs text-gray-400 dark:text-slate-500 mt-1">
                  {totalAgents} de {maxAgents} agente{maxAgents > 1 ? 's' : ''} utilizado{totalAgents !== 1 ? 's' : ''} — Plan {planType.charAt(0).toUpperCase() + planType.slice(1)}
                </p>
              )}
            </div>
          </div>
          {canManage && (
            isAtLimit ? (
              <div className="self-start">
                <div className="flex items-center gap-2 px-4 py-2 bg-amber-50 border border-amber-200 text-amber-800 rounded-lg text-sm font-medium cursor-not-allowed">
                  <AlertTriangle size={16} /> Límite alcanzado
                </div>
              </div>
            ) : (
              <button
                onClick={() => setShowForm(!showForm)}
                className="flex items-center gap-2 px-4 py-2 bg-gradient-to-br from-blue-500 to-indigo-600 text-white rounded-xl text-sm font-medium shadow-md hover:shadow-lg hover:-translate-y-0.5 transition-all duration-300 self-start"
              >
                <Plus size={16} /> Agregar Agente
              </button>
            )
          )}
        </div>

        {/* Limit warning banner */}
        {isAtLimit && (
          <div className="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-4 flex items-start gap-3">
            <AlertTriangle size={20} className="text-amber-500 shrink-0 mt-0.5" />
            <div>
              <p className="text-sm font-semibold text-amber-800">Has alcanzado el límite de agentes de tu plan ({maxAgents})</p>
              <p className="text-xs text-amber-600 mt-1">
                Para agregar más agentes, actualiza tu plan a uno superior o elimina un agente existente.
              </p>
            </div>
          </div>
        )}

        {/* Search */}
        <div className="relative w-full sm:w-64 mb-4">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" size={14} />
          <input
            type="text"
            value={searchInput}
            onChange={e => setSearchInput(e.target.value)}
            onKeyDown={e => { if (e.key === 'Enter') { setSearch(searchInput); setPage(1); } }}
            placeholder="Buscar agentes..."
            className="w-full pl-9 pr-3 py-1.5 bg-white border border-gray-200 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-blue-500 text-slate-700"
          />
        </div>

        {/* Per-page & sort controls */}
        <div className="flex items-center gap-2 mb-4 flex-wrap">
          <span className="text-xs text-gray-400">Mostrar:</span>
          <select
            value={perPage}
            onChange={e => { setPerPage(Number(e.target.value)); setPage(1); }}
            className="px-2 py-1 rounded text-xs border border-gray-200 bg-white text-gray-600 focus:ring-2 focus:ring-blue-500"
          >
            {[10, 20, 50, 100].map(n => <option key={n} value={n}>{n}</option>)}
          </select>
          <span className="text-xs text-gray-300 mx-1">|</span>
          <span className="text-xs text-gray-400">Ordenar:</span>
          {[
            { key: 'created_at', label: 'Fecha' },
            { key: 'name', label: 'Nombre' },
            { key: 'role', label: 'Rol' },
            { key: 'status', label: 'Estado' },
          ].map(col => (
            <button
              key={col.key}
              onClick={() => {
                setSort(prev => ({
                  orderby: col.key,
                  order: prev.orderby === col.key && prev.order === 'DESC' ? 'ASC' : 'DESC',
                }));
                setPage(1);
              }}
              className={`inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-medium transition-all ${
                sort.orderby === col.key ? 'bg-gradient-to-br from-blue-500 to-indigo-600 text-white shadow-md' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-slate-700 dark:text-slate-300 dark:hover:bg-slate-600'
              }`}
            >
              {col.label}
              {sort.orderby === col.key
                ? (sort.order === 'ASC' ? <ArrowUp size={12} /> : <ArrowDown size={12} />)
                : <ArrowUpDown size={12} className="opacity-30" />
              }
            </button>
          ))}
        </div>

        {showForm && !isAtLimit && (
          <div className="bg-white rounded-xl border border-gray-200 p-6 mb-6 animate-fadein">
            <h3 className="font-semibold mb-4">Nuevo Agente</h3>
            <form onSubmit={handleCreate}>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {getIsAdmin() && (
                  <div className="sm:col-span-2">
                    <label className="block text-xs font-medium text-gray-500 mb-1">Cliente *</label>
                    <select
                      value={form.client_id}
                      onChange={e => setForm({ ...form, client_id: e.target.value })}
                      className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
                      required
                    >
                      <option value="">Seleccionar cliente...</option>
                      {clientsList.map(c => (
                        <option key={c.id} value={c.id}>{c.company_name} ({c.email})</option>
                      ))}
                    </select>
                  </div>
                )}
                <div>
                  <label className="block text-xs font-medium text-gray-500 mb-1">Nombre completo</label>
                  <input
                    type="text" value={form.name}
                    onChange={e => setForm({ ...form, name: e.target.value })}
                    className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
                    required
                  />
                </div>
                <div>
                  <label className="block text-xs font-medium text-gray-500 mb-1">Email</label>
                  <input
                    type="email" value={form.email}
                    onChange={e => setForm({ ...form, email: e.target.value })}
                    className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
                    required
                  />
                </div>
                <div>
                  <label className="block text-xs font-medium text-gray-500 mb-1">Rol</label>
                  <select
                    value={form.role}
                    onChange={e => setForm({ ...form, role: e.target.value })}
                    className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
                  >
                    {availableRoles.map(r => (
                      <option key={r.value} value={r.value}>{r.label}</option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-medium text-gray-500 mb-1">Max. chats simultáneos</label>
                  <input
                    type="number" value={form.max_concurrent_chats}
                    onChange={e => setForm({ ...form, max_concurrent_chats: parseInt(e.target.value) || 5 })}
                    className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
                    min="1" max="20"
                  />
                </div>
                <div>
                  <label className="block text-xs font-medium text-gray-500 mb-1">Contraseña (min. 6 caracteres)</label>
                  <input
                    type="password" value={form.password}
                    onChange={e => setForm({ ...form, password: e.target.value })}
                    className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
                    placeholder="Se enviará por correo al agente"
                    minLength={6}
                  />
                </div>
                <div>
                  <label className="block text-xs font-medium text-gray-500 mb-1">Canal asociado <span className="text-gray-400">(opcional)</span></label>
                  <select
                    value={form.channel_id}
                    onChange={e => setForm({ ...form, channel_id: e.target.value })}
                    className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
                  >
                    <option value="">— Todos los canales —</option>
                    {channelsList.map(ch => (
                      <option key={ch.id} value={ch.id}>{ch.channel_name} ({ch.channel_type})</option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-medium text-gray-500 mb-1">Horario inicio</label>
                  <input
                    type="time" value={form.schedule_start}
                    onChange={e => setForm({ ...form, schedule_start: e.target.value })}
                    className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
                  />
                </div>
                <div>
                  <label className="block text-xs font-medium text-gray-500 mb-1">Horario fin</label>
                  <input
                    type="time" value={form.schedule_end}
                    onChange={e => setForm({ ...form, schedule_end: e.target.value })}
                    className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
                  />
                </div>
                <div className="sm:col-span-2">
                  <label className="block text-xs font-medium text-gray-500 mb-1">Días disponibles</label>
                  <div className="flex flex-wrap gap-2">
                    {[{v:'1',l:'Lun'},{v:'2',l:'Mar'},{v:'3',l:'Mié'},{v:'4',l:'Jue'},{v:'5',l:'Vie'},{v:'6',l:'Sáb'},{v:'7',l:'Dom'}].map(d => {
                      const days = (form.available_days || '').split(',');
                      const checked = days.includes(d.v);
                      return (
                        <label key={d.v} className={`flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-medium cursor-pointer border transition-colors ${checked ? 'bg-blue-100 border-blue-300 text-blue-700' : 'bg-gray-50 border-gray-200 text-gray-500 hover:bg-gray-100'}`}>
                          <input type="checkbox" className="hidden" checked={checked} onChange={() => {
                            const newDays = checked ? days.filter(x => x !== d.v) : [...days, d.v].sort();
                            setForm({ ...form, available_days: newDays.filter(Boolean).join(',') });
                          }} />
                          {d.l}
                        </label>
                      );
                    })}
                  </div>
                </div>
              </div>
              <div className="flex gap-2 mt-4">
                <button type="submit" disabled={submitting} className="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
                  {submitting ? <Loader2 size={16} className="animate-spin" /> : 'Crear Agente'}
                </button>
                <button type="button" onClick={() => setShowForm(false)} className="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm">
                  Cancelar
                </button>
              </div>
            </form>
          </div>
        )}

        {agents.length === 0 && !loading ? (
          <div className="text-center py-20 text-gray-400">
            <Users size={48} className="mx-auto mb-3 opacity-30" />
            <p>{search ? 'Sin resultados para esta búsqueda' : 'No hay agentes registrados'}</p>
          </div>
        ) : (
          <div className="relative">
            {loading && (
              <div className="absolute inset-0 bg-white/60 flex items-center justify-center z-10 rounded-lg">
                <Loader2 size={20} className="animate-spin text-blue-500" />
              </div>
            )}
            <div className="grid gap-3">
            {agents.map(agent => {
              const RoleIcon = roleIcons[agent.role] || Headphones;
              const isEditing = editingAgent === agent.id;
              return (
                <div key={agent.id} className="bg-white dark:bg-slate-800 rounded-2xl ring-1 ring-gray-100 dark:ring-slate-700/60 p-4 shadow-md hover:shadow-xl hover:-translate-y-0.5 transition-all duration-300">
                  {isEditing ? (
                    <form onSubmit={handleUpdate} className="space-y-3">
                      <div className="flex items-center justify-between mb-2">
                        <h4 className="font-semibold text-sm">Editando: {agent.name}</h4>
                        <button type="button" onClick={() => setEditingAgent(null)} className="text-gray-400 hover:text-gray-600"><X size={16} /></button>
                      </div>
                      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                          <label className="block text-xs font-medium text-gray-500 mb-1">Nombre</label>
                          <input type="text" value={editForm.name} onChange={e => setEditForm({...editForm, name: e.target.value})}
                            className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm" required />
                        </div>
                        <div>
                          <label className="block text-xs font-medium text-gray-500 mb-1">Email <span className="text-gray-400">(no editable)</span></label>
                          <input type="email" value={editForm.email} readOnly disabled
                            className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm bg-gray-50 opacity-60 cursor-not-allowed" />
                        </div>
                        <div>
                          <label className="block text-xs font-medium text-gray-500 mb-1">Rol</label>
                          <select value={editForm.role} onChange={e => setEditForm({...editForm, role: e.target.value})}
                            className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
                            {availableRoles.map(r => (
                              <option key={r.value} value={r.value}>{r.label}</option>
                            ))}
                          </select>
                        </div>
                        <div>
                          <label className="block text-xs font-medium text-gray-500 mb-1">Estado</label>
                          <select value={editForm.status} onChange={e => setEditForm({...editForm, status: e.target.value})}
                            className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
                            <option value="active">Activo</option>
                            <option value="away">Ausente</option>
                            <option value="inactive">Inactivo</option>
                          </select>
                        </div>
                        <div>
                          <label className="block text-xs font-medium text-gray-500 mb-1">Departamento</label>
                          <input type="text" value={editForm.department} onChange={e => setEditForm({...editForm, department: e.target.value})}
                            className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm" placeholder="Ej: Ventas" />
                        </div>
                        <div>
                          <label className="block text-xs font-medium text-gray-500 mb-1">Canal asociado</label>
                          <select value={editForm.channel_id} onChange={e => setEditForm({...editForm, channel_id: e.target.value})}
                            className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm">
                            <option value="">— Todos los canales —</option>
                            {channelsList.map(ch => (
                              <option key={ch.id} value={ch.id}>{ch.channel_name} ({ch.channel_type})</option>
                            ))}
                          </select>
                        </div>
                        <div>
                          <label className="block text-xs font-medium text-gray-500 mb-1">Nueva contraseña (opcional)</label>
                          <input type="password" value={editForm.password} onChange={e => setEditForm({...editForm, password: e.target.value})}
                            className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm" placeholder="Dejar vacío para no cambiar" />
                        </div>
                        <div>
                          <label className="block text-xs font-medium text-gray-500 mb-1">Horario inicio</label>
                          <input type="time" value={editForm.schedule_start || ''} onChange={e => setEditForm({...editForm, schedule_start: e.target.value})}
                            className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm" />
                        </div>
                        <div>
                          <label className="block text-xs font-medium text-gray-500 mb-1">Horario fin</label>
                          <input type="time" value={editForm.schedule_end || ''} onChange={e => setEditForm({...editForm, schedule_end: e.target.value})}
                            className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm" />
                        </div>
                        <div className="sm:col-span-2">
                          <label className="block text-xs font-medium text-gray-500 mb-1">Días disponibles</label>
                          <div className="flex flex-wrap gap-2">
                            {[{v:'1',l:'Lun'},{v:'2',l:'Mar'},{v:'3',l:'Mié'},{v:'4',l:'Jue'},{v:'5',l:'Vie'},{v:'6',l:'Sáb'},{v:'7',l:'Dom'}].map(d => {
                              const days = (editForm.available_days || '').split(',');
                              const checked = days.includes(d.v);
                              return (
                                <label key={d.v} className={`flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-xs font-medium cursor-pointer border transition-colors ${checked ? 'bg-blue-100 border-blue-300 text-blue-700' : 'bg-gray-50 border-gray-200 text-gray-500 hover:bg-gray-100'}`}>
                                  <input type="checkbox" className="hidden" checked={checked} onChange={() => {
                                    const newDays = checked ? days.filter(x => x !== d.v) : [...days, d.v].sort();
                                    setEditForm({...editForm, available_days: newDays.filter(Boolean).join(',')});
                                  }} />
                                  {d.l}
                                </label>
                              );
                            })}
                          </div>
                        </div>
                      </div>
                      <div className="flex gap-2">
                        <button type="submit" disabled={submitting} className="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
                          {submitting ? <Loader2 size={14} className="animate-spin" /> : 'Guardar'}
                        </button>
                        <button type="button" onClick={() => setEditingAgent(null)} className="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm">Cancelar</button>
                      </div>
                    </form>
                  ) : (
                    <div className="flex flex-col sm:flex-row items-start sm:items-center gap-3 sm:gap-4">
                      <div className="flex items-center gap-3 sm:gap-4 flex-1 min-w-0">
                        <div className="w-12 h-12 rounded-2xl bg-gradient-to-br from-violet-400 to-purple-600 flex items-center justify-center text-white font-bold text-lg shrink-0 shadow-md" style={{ fontFamily: 'Poppins, sans-serif' }}>
                          {agent.name ? agent.name[0].toUpperCase() : '?'}
                        </div>
                        <div className="flex-1 min-w-0">
                          <h4 className="font-semibold text-sm">{agent.name}</h4>
                          <p className="text-xs text-gray-500 truncate">{agent.email}</p>
                          <div className="flex items-center gap-2 mt-1 flex-wrap">
                            {getIsAdmin() && agent.client_name && (
                              <span className="text-[10px] bg-purple-100 text-purple-700 px-1.5 py-0.5 rounded-full font-medium">{agent.client_name}</span>
                            )}
                            <span className="flex items-center gap-1 text-[10px] font-semibold bg-indigo-50 text-indigo-700 ring-1 ring-indigo-200 dark:bg-indigo-500/10 dark:text-indigo-300 dark:ring-indigo-500/20 px-2 py-0.5 rounded-full">
                              <RoleIcon size={12} /> {roleLabels[agent.role]}
                            </span>
                            <span className={`text-[10px] font-semibold px-2 py-0.5 rounded-full ring-1 ${
                              agent.status === 'active' ? 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/20' :
                              agent.status === 'away' ? 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/20' :
                              'bg-gray-100 text-gray-500 ring-gray-200 dark:bg-slate-700 dark:text-slate-400 dark:ring-slate-600'
                            }`}>
                              {agent.status === 'active' ? '🟢 Activo' : agent.status === 'away' ? '🟡 Ausente' : '⚫ Inactivo'}
                            </span>
                            {agent.department && (
                              <span className="text-[10px] bg-indigo-100 text-indigo-700 px-1.5 py-0.5 rounded-full font-medium">{agent.department}</span>
                            )}
                            {agent.channel_name && (
                              <span className="text-[10px] bg-emerald-100 text-emerald-700 px-1.5 py-0.5 rounded-full font-medium">📱 {agent.channel_name}</span>
                            )}
                            {(agent.schedule_start && agent.schedule_end) && (
                              <span className="text-[10px] bg-sky-100 text-sky-700 px-1.5 py-0.5 rounded-full font-medium">🕐 {agent.schedule_start.slice(0,5)}-{agent.schedule_end.slice(0,5)}</span>
                            )}
                          </div>
                        </div>
                      </div>
                      <div className="flex items-center gap-4 sm:gap-6 ml-auto">
                        <div className="text-center">
                          <div className="text-2xl sm:text-3xl font-extrabold text-gray-900 dark:text-white tracking-tight" style={{ fontFamily: 'Poppins, sans-serif' }}>{agent.active_chats || 0}</div>
                          <div className="text-[10px] text-gray-400 dark:text-slate-500">/ {agent.max_concurrent_chats} chats</div>
                        </div>
                        <div className="text-xs text-gray-400 hidden sm:block">
                          {agent.last_active_at ? `Activo: ${new Date(agent.last_active_at).toLocaleString('es')}` : 'Sin actividad'}
                        </div>
                        {canManage && (
                          <div className="flex gap-1">
                            <button onClick={() => startEdit(agent)} className="p-1.5 rounded-lg hover:bg-blue-100 text-gray-400 hover:text-blue-600 transition-colors" title="Editar">
                              <Pencil size={14} />
                            </button>
                            {canDelete && (
                              <button onClick={() => setDeleteTarget(agent)} className="p-1.5 rounded-lg hover:bg-red-100 text-gray-400 hover:text-red-600 transition-colors" title="Eliminar">
                                <Trash2 size={14} />
                              </button>
                            )}
                          </div>
                        )}
                      </div>
                    </div>
                  )}
                </div>
              );
            })}
          </div>

            {/* Pagination */}
            {agentsMeta.total_pages > 1 && (
              <div className="flex items-center justify-between mt-4 pt-3 border-t border-slate-100">
                <p className="text-xs text-slate-400">
                  {agentsMeta.total} agente{agentsMeta.total !== 1 ? 's' : ''} · Página {agentsMeta.page} de {agentsMeta.total_pages}
                </p>
                <div className="flex items-center gap-1">
                  <button
                    onClick={() => setPage(p => Math.max(1, p - 1))}
                    disabled={page <= 1}
                    className="p-1.5 rounded-lg bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 disabled:opacity-30 disabled:cursor-not-allowed"
                  >
                    <ChevronLeft size={14} />
                  </button>
                  {Array.from({ length: Math.min(5, agentsMeta.total_pages) }, (_, i) => {
                    let pageNum;
                    if (agentsMeta.total_pages <= 5) {
                      pageNum = i + 1;
                    } else if (page <= 3) {
                      pageNum = i + 1;
                    } else if (page >= agentsMeta.total_pages - 2) {
                      pageNum = agentsMeta.total_pages - 4 + i;
                    } else {
                      pageNum = page - 2 + i;
                    }
                    return (
                      <button
                        key={pageNum}
                        onClick={() => setPage(pageNum)}
                        className={`w-7 h-7 rounded-lg text-xs font-bold ${
                          pageNum === page
                            ? 'bg-gradient-to-br from-blue-500 to-indigo-600 text-white shadow-md'
                            : 'bg-white dark:bg-slate-700 border border-gray-200 dark:border-slate-600 text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-600'
                        }`}
                      >
                        {pageNum}
                      </button>
                    );
                  })}
                  <button
                    onClick={() => setPage(p => Math.min(agentsMeta.total_pages, p + 1))}
                    disabled={page >= agentsMeta.total_pages}
                    className="p-1.5 rounded-lg bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 disabled:opacity-30 disabled:cursor-not-allowed"
                  >
                    <ChevronRight size={14} />
                  </button>
                </div>
              </div>
            )}
          </div>
        )}

        {/* Delete confirmation modal */}
        <ConfirmDeleteModal
          isOpen={!!deleteTarget}
          onClose={() => setDeleteTarget(null)}
          onConfirm={handleDelete}
          title={`Eliminar agente: ${deleteTarget?.name}`}
          description={getIsAdmin() ? 'El agente será eliminado permanentemente.' : 'El agente será eliminado permanentemente. Confirma con tu API Key.'}
          loading={deleting}
          skipApiKey={getIsAdmin()}
        />

        {resultModal && <ResultModal {...resultModal} onClose={() => setResultModal(null)} />}
      </div>
    </div>
  );
}
