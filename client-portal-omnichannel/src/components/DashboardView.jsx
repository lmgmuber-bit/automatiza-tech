import { useState, useEffect, useCallback } from 'react';
import { getAdminStats, getAdminAuditLogs } from '../api';
import { Users, MessageSquare, Bot, Radio, TrendingUp, AlertCircle, ChevronLeft, ChevronRight, ArrowUpDown, ArrowUp, ArrowDown, Search, Loader2, ClipboardList, Inbox, Activity } from 'lucide-react';

// Estándar visual "audaz": hero gradiente, chips de icono con gradiente + glow, profundidad.
const BOLD = {
  sky:     { grad: 'from-sky-400 to-blue-600',     glow: 'bg-sky-400/30' },
  emerald: { grad: 'from-emerald-400 to-teal-600', glow: 'bg-emerald-400/30' },
  violet:  { grad: 'from-violet-400 to-purple-600', glow: 'bg-violet-400/30' },
  amber:   { grad: 'from-amber-400 to-orange-500',  glow: 'bg-amber-400/30' },
  rose:    { grad: 'from-rose-400 to-pink-600',     glow: 'bg-rose-400/30' },
  blue:    { grad: 'from-blue-400 to-indigo-600',   glow: 'bg-blue-400/30' },
};

const HERO_GRADIENT = 'linear-gradient(120deg, #0b1220 0%, #1e3a8a 45%, #0e7490 100%)';

export default function DashboardView() {
  const [stats, setStats] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  const [audit, setAudit] = useState({ data: [], total: 0, page: 1, total_pages: 1 });
  const [auditPage, setAuditPage] = useState(1);
  const [auditSort, setAuditSort] = useState({ orderby: 'created_at', order: 'DESC' });
  const [auditSearch, setAuditSearch] = useState('');
  const [auditSearchInput, setAuditSearchInput] = useState('');
  const [auditLoading, setAuditLoading] = useState(false);

  useEffect(() => { loadStats(); }, []);

  const loadAudit = useCallback(async () => {
    setAuditLoading(true);
    try {
      const data = await getAdminAuditLogs({
        page: auditPage, per_page: 15,
        orderby: auditSort.orderby, order: auditSort.order, search: auditSearch,
      });
      setAudit(data);
    } catch {}
    setAuditLoading(false);
  }, [auditPage, auditSort, auditSearch]);

  useEffect(() => { loadAudit(); }, [loadAudit]);

  async function loadStats() {
    try {
      setLoading(true); setError(null);
      const data = await getAdminStats();
      setStats(data);
    } catch (err) { setError(err.message); }
    finally { setLoading(false); }
  }

  if (loading) {
    return (
      <div className="flex-1 flex items-center justify-center p-8">
        <div className="animate-spin w-8 h-8 border-2 border-blue-500 border-t-transparent rounded-full" />
      </div>
    );
  }
  if (error) {
    return (
      <div className="flex-1 flex items-center justify-center p-8">
        <div className="text-center space-y-3">
          <AlertCircle size={40} className="mx-auto text-red-400" />
          <p className="text-red-500">{error}</p>
          <button onClick={loadStats} className="text-sm text-blue-500 hover:underline">Reintentar</button>
        </div>
      </div>
    );
  }

  const cards = [
    { label: 'Clientes', value: stats?.total_clients ?? 0, sub: `${stats?.active_clients ?? 0} activos`, icon: Users, tint: 'sky' },
    { label: 'Conversaciones', value: stats?.total_conversations ?? 0, sub: `${stats?.active_conversations ?? 0} activas`, icon: MessageSquare, tint: 'emerald' },
    { label: 'Mensajes hoy', value: stats?.total_messages_today ?? 0, icon: TrendingUp, tint: 'violet' },
    { label: 'Canales activos', value: stats?.total_channels ?? 0, icon: Radio, tint: 'amber' },
    { label: 'Takeovers activos', value: stats?.active_takeovers ?? 0, icon: Bot, tint: 'rose' },
  ];

  return (
    <div className="flex-1 overflow-auto bg-gradient-to-b from-slate-50 to-blue-50/40 dark:from-slate-900 dark:to-slate-900">
      {/* HERO */}
      <div className="relative overflow-hidden m-5 mb-0 rounded-3xl px-7 py-8 text-white shadow-2xl" style={{ backgroundImage: HERO_GRADIENT }}>
        <div className="absolute -top-16 -right-10 w-72 h-72 rounded-full blur-3xl" style={{ background: 'radial-gradient(circle, rgba(6,214,160,0.45), transparent 60%)' }} />
        <div className="absolute -bottom-24 left-1/3 w-72 h-72 rounded-full blur-3xl" style={{ background: 'radial-gradient(circle, rgba(59,130,246,0.4), transparent 60%)' }} />
        <div className="absolute inset-0 opacity-[0.07]" style={{ backgroundImage: 'radial-gradient(white 1px, transparent 1px)', backgroundSize: '22px 22px' }} />
        <div className="relative flex flex-wrap items-center justify-between gap-4">
          <div>
            <div className="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-white/10 backdrop-blur-sm ring-1 ring-white/20 text-[11px] font-semibold mb-3">
              <span className="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse" /> Sistema operativo
            </div>
            <h1 className="text-3xl sm:text-4xl font-extrabold tracking-tight" style={{ fontFamily: 'Poppins, sans-serif' }}>Centro de Control</h1>
            <p className="text-sm text-white/70 mt-1">Resumen general de la plataforma omnicanal</p>
          </div>
          <div className="flex items-center gap-6 pr-2">
            <div className="text-right">
              <p className="text-4xl font-extrabold leading-none" style={{ fontFamily: 'Poppins, sans-serif' }}>{(stats?.total_conversations ?? 0).toLocaleString()}</p>
              <p className="text-[11px] uppercase tracking-wider text-white/60 mt-1">Conversaciones</p>
            </div>
            <div className="w-px h-12 bg-white/15" />
            <div className="w-14 h-14 rounded-2xl bg-white/10 backdrop-blur-sm ring-1 ring-white/20 flex items-center justify-center">
              <Activity size={26} className="text-emerald-300" />
            </div>
          </div>
        </div>
      </div>

      <div className="p-5 space-y-6">
        {/* STAT CARDS */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">
          {cards.map(card => {
            const Icon = card.icon;
            const b = BOLD[card.tint];
            return (
              <div key={card.label} className="group relative bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-md ring-1 ring-gray-100 dark:ring-slate-700/60 hover:shadow-xl hover:-translate-y-1 transition-all duration-300 overflow-hidden">
                <div className={`absolute -top-8 -right-8 w-24 h-24 rounded-full blur-2xl ${b.glow} group-hover:scale-150 transition-transform duration-500`} />
                <div className={`relative w-12 h-12 rounded-2xl bg-gradient-to-br ${b.grad} flex items-center justify-center text-white shadow-lg`}>
                  <Icon size={22} />
                </div>
                <p className="relative mt-4 text-4xl font-extrabold text-gray-900 dark:text-white tracking-tight" style={{ fontFamily: 'Poppins, sans-serif' }}>{card.value.toLocaleString()}</p>
                <p className="relative text-sm font-medium text-gray-500 dark:text-slate-400 mt-0.5">{card.label}</p>
                {card.sub && <p className="relative text-[11px] text-gray-400 dark:text-slate-500 mt-0.5">{card.sub}</p>}
              </div>
            );
          })}
        </div>

        {/* AUDITORÍA */}
        <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-md ring-1 ring-gray-100 dark:ring-slate-700/60 p-5">
          <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-4">
            <h2 className="flex items-center gap-2.5 text-lg font-bold text-gray-900 dark:text-white" style={{ fontFamily: 'Poppins, sans-serif' }}>
              <span className="w-9 h-9 rounded-xl flex items-center justify-center bg-gradient-to-br from-blue-500 to-indigo-600 text-white shadow-md">
                <ClipboardList size={18} />
              </span>
              Auditoría reciente
            </h2>
            <div className="relative w-full sm:w-64">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" size={14} />
              <input
                type="text"
                value={auditSearchInput}
                onChange={e => setAuditSearchInput(e.target.value)}
                onKeyDown={e => { if (e.key === 'Enter') { setAuditSearch(auditSearchInput); setAuditPage(1); } }}
                placeholder="Buscar en auditoría..."
                className="w-full pl-9 pr-3 py-2 bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-xl text-xs focus:outline-none focus:ring-2 focus:ring-blue-500 text-slate-700 dark:text-slate-200"
              />
            </div>
          </div>

          <div className="overflow-x-auto relative">
            {auditLoading && (
              <div className="absolute inset-0 bg-white/60 dark:bg-slate-800/60 flex items-center justify-center z-10 rounded-lg">
                <Loader2 size={20} className="animate-spin text-blue-500" />
              </div>
            )}
            <table className="w-full text-sm">
              <thead>
                <tr className="bg-gradient-to-r from-slate-50 to-blue-50/50 dark:from-slate-700/40 dark:to-slate-700/20 border-b border-gray-100 dark:border-slate-700">
                  {[
                    { key: 'action', label: 'Acción' },
                    { key: 'entity_type', label: 'Entidad' },
                    { key: null, label: 'Usuario' },
                    { key: 'created_at', label: 'Fecha' },
                  ].map(col => (
                    <th
                      key={col.label}
                      className={`text-left py-3 px-3 text-gray-500 dark:text-slate-400 font-bold uppercase tracking-wide text-[11px] first:rounded-l-lg last:rounded-r-lg ${col.key ? 'cursor-pointer select-none hover:text-blue-600 dark:hover:text-blue-400' : ''}`}
                      onClick={() => {
                        if (!col.key) return;
                        setAuditSort(prev => ({
                          orderby: col.key,
                          order: prev.orderby === col.key && prev.order === 'DESC' ? 'ASC' : 'DESC',
                        }));
                        setAuditPage(1);
                      }}
                    >
                      <span className="inline-flex items-center gap-1">
                        {col.label}
                        {col.key && (
                          auditSort.orderby === col.key
                            ? (auditSort.order === 'ASC'
                              ? <ArrowUp size={12} className="text-blue-500" />
                              : <ArrowDown size={12} className="text-blue-500" />)
                            : <ArrowUpDown size={12} className="opacity-30" />
                        )}
                      </span>
                    </th>
                  ))}
                </tr>
              </thead>
              <tbody>
                {audit.data.length === 0 ? (
                  <tr>
                    <td colSpan={4} className="py-12 text-center">
                      <div className="flex flex-col items-center gap-2 text-gray-400 dark:text-slate-500">
                        <Inbox size={32} className="opacity-50" />
                        <p className="text-xs font-medium">
                          {auditSearch ? 'Sin resultados para esta búsqueda' : 'No hay registros de auditoría'}
                        </p>
                      </div>
                    </td>
                  </tr>
                ) : audit.data.map(entry => (
                  <tr key={entry.id} className="border-b border-gray-50 dark:border-slate-700/40 hover:bg-blue-50/40 dark:hover:bg-slate-700/40 transition-colors">
                    <td className="py-3 px-3">
                      <span className={`inline-flex items-center px-2.5 py-1 rounded-full text-[10px] font-bold capitalize ${
                        entry.action === 'create' ? 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/20' :
                        entry.action === 'delete' ? 'bg-rose-50 text-rose-700 ring-1 ring-rose-200 dark:bg-rose-500/10 dark:text-rose-300 dark:ring-rose-500/20' :
                        entry.action === 'update' ? 'bg-amber-50 text-amber-700 ring-1 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/20' :
                        entry.action === 'login' ? 'bg-sky-50 text-sky-700 ring-1 ring-sky-200 dark:bg-sky-500/10 dark:text-sky-300 dark:ring-sky-500/20' :
                        'bg-gray-100 text-gray-600 ring-1 ring-gray-200 dark:bg-slate-700 dark:text-slate-300 dark:ring-slate-600'
                      }`}>
                        {entry.action}
                      </span>
                    </td>
                    <td className="py-3 px-3 text-gray-700 dark:text-slate-300 text-xs">{entry.entity_type} #{entry.entity_id}</td>
                    <td className="py-3 px-3 text-gray-700 dark:text-slate-300 text-xs">{entry.user_name || entry.user_id || '—'}</td>
                    <td className="py-3 px-3 text-gray-500 dark:text-slate-400 text-xs whitespace-nowrap">{entry.created_at}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>

          {audit.total_pages > 1 && (
            <div className="flex items-center justify-between mt-4 pt-3 border-t border-slate-100 dark:border-slate-700">
              <p className="text-xs text-slate-400">
                {audit.total} registro{audit.total !== 1 ? 's' : ''} · Página {audit.page} de {audit.total_pages}
              </p>
              <div className="flex items-center gap-1">
                <button
                  onClick={() => setAuditPage(p => Math.max(1, p - 1))}
                  disabled={auditPage <= 1}
                  className="p-1.5 rounded-lg bg-white dark:bg-slate-700 border border-gray-200 dark:border-slate-600 text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-600 disabled:opacity-30 disabled:cursor-not-allowed"
                >
                  <ChevronLeft size={14} />
                </button>
                {Array.from({ length: Math.min(5, audit.total_pages) }, (_, i) => {
                  let pageNum;
                  if (audit.total_pages <= 5) pageNum = i + 1;
                  else if (auditPage <= 3) pageNum = i + 1;
                  else if (auditPage >= audit.total_pages - 2) pageNum = audit.total_pages - 4 + i;
                  else pageNum = auditPage - 2 + i;
                  return (
                    <button
                      key={pageNum}
                      onClick={() => setAuditPage(pageNum)}
                      className={`w-7 h-7 rounded-lg text-xs font-bold ${
                        pageNum === auditPage
                          ? 'bg-gradient-to-br from-blue-500 to-indigo-600 text-white shadow-md'
                          : 'bg-white dark:bg-slate-700 border border-gray-200 dark:border-slate-600 text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-600'
                      }`}
                    >
                      {pageNum}
                    </button>
                  );
                })}
                <button
                  onClick={() => setAuditPage(p => Math.min(audit.total_pages, p + 1))}
                  disabled={auditPage >= audit.total_pages}
                  className="p-1.5 rounded-lg bg-white dark:bg-slate-700 border border-gray-200 dark:border-slate-600 text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-600 disabled:opacity-30 disabled:cursor-not-allowed"
                >
                  <ChevronRight size={14} />
                </button>
              </div>
            </div>
          )}
        </div>

        {stats?.messages_by_channel?.length > 0 && (
          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-md ring-1 ring-gray-100 dark:ring-slate-700/60 p-5">
              <h2 className="flex items-center gap-2.5 text-lg font-bold text-gray-900 dark:text-white mb-4" style={{ fontFamily: 'Poppins, sans-serif' }}>
                <span className="w-9 h-9 rounded-xl flex items-center justify-center bg-gradient-to-br from-violet-400 to-purple-600 text-white shadow-md">
                  <Radio size={18} />
                </span>
                Mensajes por canal
              </h2>
              <div className="space-y-1">
                {stats.messages_by_channel.map(ch => (
                  <div key={ch.channel_type} className="flex items-center justify-between py-2 px-2 -mx-2 rounded-lg hover:bg-violet-50/50 dark:hover:bg-slate-700/40 transition-colors">
                    <span className="text-sm text-gray-600 dark:text-slate-300 capitalize">{ch.channel_type}</span>
                    <span className="text-base font-bold text-gray-900 dark:text-white" style={{ fontFamily: 'Poppins, sans-serif' }}>{Number(ch.total).toLocaleString()}</span>
                  </div>
                ))}
              </div>
            </div>

            {stats?.clients_by_plan?.length > 0 && (
              <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-md ring-1 ring-gray-100 dark:ring-slate-700/60 p-5">
                <h2 className="flex items-center gap-2.5 text-lg font-bold text-gray-900 dark:text-white mb-4" style={{ fontFamily: 'Poppins, sans-serif' }}>
                  <span className="w-9 h-9 rounded-xl flex items-center justify-center bg-gradient-to-br from-emerald-400 to-teal-600 text-white shadow-md">
                    <Users size={18} />
                  </span>
                  Clientes por plan
                </h2>
                <div className="space-y-1">
                  {stats.clients_by_plan.map(pl => (
                    <div key={pl.plan_type} className="flex items-center justify-between py-2 px-2 -mx-2 rounded-lg hover:bg-emerald-50/50 dark:hover:bg-slate-700/40 transition-colors">
                      <span className="text-sm text-gray-600 dark:text-slate-300 capitalize">{pl.plan_type}</span>
                      <span className="text-base font-bold text-gray-900 dark:text-white" style={{ fontFamily: 'Poppins, sans-serif' }}>{Number(pl.total).toLocaleString()}</span>
                    </div>
                  ))}
                </div>
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  );
}
