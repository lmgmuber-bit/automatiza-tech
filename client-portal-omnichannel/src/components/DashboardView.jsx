import { useState, useEffect, useCallback } from 'react';
import { getAdminStats, getAdminAuditLogs } from '../api';
import { Users, MessageSquare, Bot, Radio, TrendingUp, AlertCircle, ChevronLeft, ChevronRight, ArrowUpDown, ArrowUp, ArrowDown, Search, Loader2 } from 'lucide-react';

export default function DashboardView() {
  const [stats, setStats] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  // Audit state (independent async)
  const [audit, setAudit] = useState({ data: [], total: 0, page: 1, total_pages: 1 });
  const [auditPage, setAuditPage] = useState(1);
  const [auditSort, setAuditSort] = useState({ orderby: 'created_at', order: 'DESC' });
  const [auditSearch, setAuditSearch] = useState('');
  const [auditSearchInput, setAuditSearchInput] = useState('');
  const [auditLoading, setAuditLoading] = useState(false);

  useEffect(() => {
    loadStats();
  }, []);

  const loadAudit = useCallback(async () => {
    setAuditLoading(true);
    try {
      const data = await getAdminAuditLogs({
        page: auditPage,
        per_page: 15,
        orderby: auditSort.orderby,
        order: auditSort.order,
        search: auditSearch,
      });
      setAudit(data);
    } catch {}
    setAuditLoading(false);
  }, [auditPage, auditSort, auditSearch]);

  useEffect(() => {
    loadAudit();
  }, [loadAudit]);

  async function loadStats() {
    try {
      setLoading(true);
      setError(null);
      const data = await getAdminStats();
      setStats(data);
    } catch (err) {
      setError(err.message);
    } finally {
      setLoading(false);
    }
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
    { label: 'Clientes', value: stats?.total_clients ?? 0, sub: `${stats?.active_clients ?? 0} activos`, icon: Users, color: 'from-blue-500 to-blue-600' },
    { label: 'Conversaciones', value: stats?.total_conversations ?? 0, sub: `${stats?.active_conversations ?? 0} activas`, icon: MessageSquare, color: 'from-emerald-500 to-emerald-600' },
    { label: 'Mensajes hoy', value: stats?.total_messages_today ?? 0, icon: TrendingUp, color: 'from-purple-500 to-purple-600' },
    { label: 'Canales activos', value: stats?.total_channels ?? 0, icon: Radio, color: 'from-amber-500 to-amber-600' },
    { label: 'Takeovers activos', value: stats?.active_takeovers ?? 0, icon: Bot, color: 'from-pink-500 to-pink-600' },
  ];

  return (
    <div className="flex-1 overflow-auto p-6 space-y-6">
      <div>
        <h1 className="text-2xl font-bold text-slate-800 dark:text-white">Dashboard</h1>
        <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">Resumen general de la plataforma</p>
      </div>

      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">
        {cards.map(card => {
          const Icon = card.icon;
          return (
            <div key={card.label} className="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-5">
              <div className="flex items-center justify-between mb-3">
                <span className="text-sm font-medium text-slate-500 dark:text-slate-400">{card.label}</span>
                <div className={`w-9 h-9 rounded-lg bg-gradient-to-br ${card.color} flex items-center justify-center`}>
                  <Icon size={18} className="text-white" />
                </div>
              </div>
              <p className="text-2xl font-bold text-slate-800 dark:text-white">{card.value.toLocaleString()}</p>
              {card.sub && <p className="text-xs text-slate-400 mt-1">{card.sub}</p>}
            </div>
          );
        })}
      </div>

      {/* ===== AUDITORÍA PAGINADA Y ORDENABLE ===== */}
      <div className="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-5">
        <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-4">
          <h2 className="text-lg font-semibold text-slate-800 dark:text-white">Auditoría reciente</h2>
          <div className="relative w-full sm:w-64">
            <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" size={14} />
            <input
              type="text"
              value={auditSearchInput}
              onChange={e => setAuditSearchInput(e.target.value)}
              onKeyDown={e => { if (e.key === 'Enter') { setAuditSearch(auditSearchInput); setAuditPage(1); } }}
              placeholder="Buscar en auditoría..."
              className="w-full pl-9 pr-3 py-1.5 bg-white dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-blue-500 text-slate-700 dark:text-slate-200"
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
              <tr className="border-b border-slate-200 dark:border-slate-700">
                {[
                  { key: 'action', label: 'Acción' },
                  { key: 'entity_type', label: 'Entidad' },
                  { key: null, label: 'Usuario' },
                  { key: 'created_at', label: 'Fecha' },
                ].map(col => (
                  <th
                    key={col.label}
                    className={`text-left py-2 px-3 text-slate-500 font-medium text-xs ${col.key ? 'cursor-pointer select-none hover:text-blue-600 dark:hover:text-blue-400' : ''}`}
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
                  <td colSpan={4} className="py-8 text-center text-slate-400 text-xs">
                    {auditSearch ? 'Sin resultados para esta búsqueda' : 'No hay registros de auditoría'}
                  </td>
                </tr>
              ) : audit.data.map(entry => (
                <tr key={entry.id} className="border-b border-slate-100 dark:border-slate-700/50 hover:bg-slate-50 dark:hover:bg-slate-700/30">
                  <td className="py-2 px-3">
                    <span className={`inline-block px-2 py-0.5 rounded text-[10px] font-medium ${
                      entry.action === 'create' ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400' :
                      entry.action === 'delete' ? 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400' :
                      entry.action === 'update' ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400' :
                      'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300'
                    }`}>
                      {entry.action}
                    </span>
                  </td>
                  <td className="py-2 px-3 text-slate-700 dark:text-slate-300 text-xs">{entry.entity_type} #{entry.entity_id}</td>
                  <td className="py-2 px-3 text-slate-700 dark:text-slate-300 text-xs">{entry.user_name || entry.user_id || '—'}</td>
                  <td className="py-2 px-3 text-slate-500 dark:text-slate-400 text-xs whitespace-nowrap">{entry.created_at}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>

        {/* Pagination */}
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
                if (audit.total_pages <= 5) {
                  pageNum = i + 1;
                } else if (auditPage <= 3) {
                  pageNum = i + 1;
                } else if (auditPage >= audit.total_pages - 2) {
                  pageNum = audit.total_pages - 4 + i;
                } else {
                  pageNum = auditPage - 2 + i;
                }
                return (
                  <button
                    key={pageNum}
                    onClick={() => setAuditPage(pageNum)}
                    className={`w-7 h-7 rounded-lg text-xs font-medium ${
                      pageNum === auditPage
                        ? 'bg-blue-600 text-white'
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
          <div className="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-5">
            <h2 className="text-lg font-semibold text-slate-800 dark:text-white mb-3">Mensajes por canal</h2>
            <div className="space-y-2">
              {stats.messages_by_channel.map(ch => (
                <div key={ch.channel_type} className="flex items-center justify-between">
                  <span className="text-sm text-slate-600 dark:text-slate-300 capitalize">{ch.channel_type}</span>
                  <span className="text-sm font-semibold text-slate-800 dark:text-white">{Number(ch.total).toLocaleString()}</span>
                </div>
              ))}
            </div>
          </div>

          {stats?.clients_by_plan?.length > 0 && (
            <div className="bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-5">
              <h2 className="text-lg font-semibold text-slate-800 dark:text-white mb-3">Clientes por plan</h2>
              <div className="space-y-2">
                {stats.clients_by_plan.map(pl => (
                  <div key={pl.plan_type} className="flex items-center justify-between">
                    <span className="text-sm text-slate-600 dark:text-slate-300 capitalize">{pl.plan_type}</span>
                    <span className="text-sm font-semibold text-slate-800 dark:text-white">{Number(pl.total).toLocaleString()}</span>
                  </div>
                ))}
              </div>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
