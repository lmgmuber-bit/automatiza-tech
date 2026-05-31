import { useState, useEffect, useCallback } from 'react';
import { Loader2, ClipboardList, ChevronLeft, ChevronRight, Eye, Search, ArrowUp, ArrowDown, ArrowUpDown } from 'lucide-react';
import { getAuditLogs } from '../api';

export default function AuditView() {
  const [logs, setLogs] = useState([]);
  const [meta, setMeta] = useState({ total: 0, page: 1, total_pages: 1 });
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(10);
  const [sort, setSort] = useState({ orderby: 'created_at', order: 'DESC' });
  const [search, setSearch] = useState('');
  const [searchInput, setSearchInput] = useState('');
  const [loading, setLoading] = useState(true);
  const [expandedId, setExpandedId] = useState(null);

  const loadLogs = useCallback(async () => {
    setLoading(true);
    try {
      const data = await getAuditLogs({
        page,
        per_page: perPage,
        orderby: sort.orderby,
        order: sort.order,
        search,
      });
      setLogs(data.data || []);
      setMeta({ total: data.total || 0, page: data.page || 1, total_pages: data.total_pages || 1 });
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  }, [page, perPage, sort, search]);

  useEffect(() => {
    loadLogs();
  }, [loadLogs]);

  const actionColors = {
    create:   'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/20',
    update:   'bg-amber-50 text-amber-700 ring-1 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/20',
    delete:   'bg-rose-50 text-rose-700 ring-1 ring-rose-200 dark:bg-rose-500/10 dark:text-rose-300 dark:ring-rose-500/20',
    login:    'bg-sky-50 text-sky-700 ring-1 ring-sky-200 dark:bg-sky-500/10 dark:text-sky-300 dark:ring-sky-500/20',
    takeover: 'bg-violet-50 text-violet-700 ring-1 ring-violet-200 dark:bg-violet-500/10 dark:text-violet-300 dark:ring-violet-500/20',
    release:  'bg-amber-50 text-amber-700 ring-1 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/20',
    transfer: 'bg-cyan-50 text-cyan-700 ring-1 ring-cyan-200 dark:bg-cyan-500/10 dark:text-cyan-300 dark:ring-cyan-500/20',
  };
  const actionFallback = 'bg-gray-100 text-gray-600 ring-1 ring-gray-200 dark:bg-slate-700 dark:text-slate-300 dark:ring-slate-600';

  const entityIcons = {
    client: '🏢',
    channel: '📡',
    bot_config: '🤖',
    conversation: '💬',
    agent: '👤',
  };

  return (
    <div className="h-full overflow-y-auto p-4 sm:p-6 bg-gray-50 dark:bg-slate-900">
      <div className="max-w-5xl mx-auto">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
          <div className="flex items-center gap-3">
            <span className="w-11 h-11 rounded-xl flex items-center justify-center ring-1 bg-blue-50 text-blue-600 ring-blue-200 dark:bg-blue-500/10 dark:text-blue-300 dark:ring-blue-500/20 shrink-0">
              <ClipboardList size={22} />
            </span>
            <div>
              <h1 className="text-2xl font-bold tracking-tight text-gray-900 dark:text-white" style={{ fontFamily: 'Poppins, sans-serif' }}>Registro de Auditoría</h1>
              <p className="text-sm text-gray-500 dark:text-slate-400 mt-1">Historial completo de cambios y acciones en el sistema</p>
            </div>
          </div>
        </div>

        {/* Search */}
        <div className="relative w-full sm:w-64 mb-4">
          <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" size={14} />
          <input
            type="text"
            value={searchInput}
            onChange={e => setSearchInput(e.target.value)}
            onKeyDown={e => { if (e.key === 'Enter') { setSearch(searchInput); setPage(1); } }}
            placeholder="Buscar en auditoría..."
            className="w-full pl-9 pr-3 py-1.5 bg-white dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-blue-500 text-slate-700 dark:text-slate-200"
          />
        </div>

        {/* Per-page & sort controls */}
        <div className="flex items-center gap-2 mb-4 flex-wrap">
          <span className="text-xs text-gray-400 dark:text-slate-500">Mostrar:</span>
          <select
            value={perPage}
            onChange={e => { setPerPage(Number(e.target.value)); setPage(1); }}
            className="px-2.5 py-1.5 rounded-full text-xs border border-gray-200 dark:border-slate-600 bg-white dark:bg-slate-700 text-gray-600 dark:text-slate-300 focus:ring-2 focus:ring-blue-500"
          >
            {[10, 20, 50, 100].map(n => <option key={n} value={n}>{n}</option>)}
          </select>
          <span className="text-xs text-gray-300 dark:text-slate-600 mx-1">|</span>
          <span className="text-xs text-gray-400 dark:text-slate-500">Ordenar:</span>
          {[
            { key: 'created_at', label: 'Fecha' },
            { key: 'action', label: 'Acción' },
            { key: 'entity_type', label: 'Entidad' },
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
              className={`inline-flex items-center gap-1 px-3 py-1.5 rounded-full text-xs font-medium transition-colors ${
                sort.orderby === col.key ? 'bg-blue-100 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 dark:bg-slate-700 dark:text-slate-300 dark:hover:bg-slate-600'
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

        {logs.length === 0 && !loading ? (
          <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-sm ring-1 ring-gray-100 dark:ring-slate-700/60 py-20 text-center">
            <div className="flex flex-col items-center gap-2 text-gray-400 dark:text-slate-500">
              <ClipboardList size={40} className="opacity-50" />
              <p className="text-sm font-medium">{search ? 'Sin resultados para esta búsqueda' : 'Sin registros de auditoría'}</p>
            </div>
          </div>
        ) : (
          <div className="relative bg-white dark:bg-slate-800 rounded-2xl shadow-sm ring-1 ring-gray-100 dark:ring-slate-700/60 p-3 sm:p-4">
            {loading && (
              <div className="absolute inset-0 bg-white/60 dark:bg-slate-800/60 flex items-center justify-center z-10 rounded-2xl">
                <Loader2 size={20} className="animate-spin text-blue-500" />
              </div>
            )}
            {/* Timeline */}
            <div className="divide-y divide-gray-50 dark:divide-slate-700/40">
              {logs.map(log => {
                const isExpanded = expandedId === log.id;
                return (
                  <div key={log.id} className="overflow-hidden animate-fadein rounded-xl hover:bg-gray-50 dark:hover:bg-slate-700/40 transition-colors">
                    <div className="px-3 sm:px-4 py-3 flex flex-col sm:flex-row items-start sm:items-center gap-3 sm:gap-4">
                      {/* Icon */}
                      <div className="w-10 h-10 rounded-xl flex items-center justify-center text-xl shrink-0 bg-gray-50 dark:bg-slate-700/50 ring-1 ring-gray-100 dark:ring-slate-700">
                        {entityIcons[log.entity_type] || '📌'}
                      </div>

                      {/* Info */}
                      <div className="flex-1 min-w-0">
                        <div className="flex items-center gap-2 flex-wrap">
                          <span className={`inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold capitalize ${actionColors[log.action] || actionFallback}`}>
                            {log.action}
                          </span>
                          <span className="text-xs text-gray-400 dark:text-slate-500">
                            {log.entity_type} #{log.entity_id}
                          </span>
                        </div>
                        <p className="text-sm text-gray-700 dark:text-slate-200 mt-1 truncate">{log.description}</p>
                        <div className="flex items-center gap-3 mt-1 text-xs text-gray-400 dark:text-slate-500">
                          <span>👤 {log.user_email || 'Sistema'}</span>
                          {log.ip_address && <span>🌐 {log.ip_address}</span>}
                        </div>
                      </div>

                      {/* Timestamp & expand */}
                      <div className="text-right shrink-0">
                        <div className="text-xs text-gray-500 dark:text-slate-400 whitespace-nowrap">
                          {new Date(log.created_at).toLocaleDateString('es', { day: '2-digit', month: 'short', year: 'numeric' })}
                        </div>
                        <div className="text-[10px] text-gray-400 dark:text-slate-500">
                          {new Date(log.created_at).toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit', second: '2-digit' })}
                        </div>
                        {(log.old_values_json || log.new_values_json) && (
                          <button
                            onClick={() => setExpandedId(isExpanded ? null : log.id)}
                            className="mt-1 text-blue-500 hover:text-blue-700 dark:hover:text-blue-400 text-xs flex items-center gap-1 ml-auto"
                          >
                            <Eye size={12} /> {isExpanded ? 'Ocultar' : 'Detalles'}
                          </button>
                        )}
                      </div>
                    </div>

                    {/* Expanded Details */}
                    {isExpanded && (
                      <div className="px-4 sm:px-5 pb-3 space-y-3">
                        {log.old_values_json && (
                          <div>
                            <p className="text-xs font-semibold text-gray-500 dark:text-slate-400 mb-1">Valores Anteriores:</p>
                            <pre className="bg-rose-50 dark:bg-rose-500/10 ring-1 ring-rose-100 dark:ring-rose-500/20 rounded-lg p-3 text-xs text-rose-800 dark:text-rose-300 overflow-x-auto">
                              {JSON.stringify(JSON.parse(log.old_values_json), null, 2)}
                            </pre>
                          </div>
                        )}
                        {log.new_values_json && (
                          <div>
                            <p className="text-xs font-semibold text-gray-500 dark:text-slate-400 mb-1">Valores Nuevos:</p>
                            <pre className="bg-emerald-50 dark:bg-emerald-500/10 ring-1 ring-emerald-100 dark:ring-emerald-500/20 rounded-lg p-3 text-xs text-emerald-800 dark:text-emerald-300 overflow-x-auto">
                              {JSON.stringify(JSON.parse(log.new_values_json), null, 2)}
                            </pre>
                          </div>
                        )}
                        {log.user_agent && (
                          <p className="text-[10px] text-gray-400 dark:text-slate-500">User Agent: {log.user_agent}</p>
                        )}
                      </div>
                    )}
                  </div>
                );
              })}
            </div>

            {/* Pagination */}
            {meta.total_pages > 1 && (
              <div className="flex items-center justify-between mt-4 pt-3 border-t border-slate-100 dark:border-slate-700">
                <p className="text-xs text-slate-400">
                  {meta.total} registro{meta.total !== 1 ? 's' : ''} · Página {meta.page} de {meta.total_pages}
                </p>
                <div className="flex items-center gap-1">
                  <button
                    onClick={() => setPage(p => Math.max(1, p - 1))}
                    disabled={page <= 1}
                    className="p-1.5 rounded-lg bg-white dark:bg-slate-700 border border-gray-200 dark:border-slate-600 text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-600 disabled:opacity-30 disabled:cursor-not-allowed"
                  >
                    <ChevronLeft size={14} />
                  </button>
                  {Array.from({ length: Math.min(5, meta.total_pages) }, (_, i) => {
                    let pageNum;
                    if (meta.total_pages <= 5) {
                      pageNum = i + 1;
                    } else if (page <= 3) {
                      pageNum = i + 1;
                    } else if (page >= meta.total_pages - 2) {
                      pageNum = meta.total_pages - 4 + i;
                    } else {
                      pageNum = page - 2 + i;
                    }
                    return (
                      <button
                        key={pageNum}
                        onClick={() => setPage(pageNum)}
                        className={`w-7 h-7 rounded-lg text-xs font-medium ${
                          pageNum === page
                            ? 'bg-blue-600 text-white'
                            : 'bg-white dark:bg-slate-700 border border-gray-200 dark:border-slate-600 text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-600'
                        }`}
                      >
                        {pageNum}
                      </button>
                    );
                  })}
                  <button
                    onClick={() => setPage(p => Math.min(meta.total_pages, p + 1))}
                    disabled={page >= meta.total_pages}
                    className="p-1.5 rounded-lg bg-white dark:bg-slate-700 border border-gray-200 dark:border-slate-600 text-gray-600 dark:text-slate-300 hover:bg-gray-50 dark:hover:bg-slate-600 disabled:opacity-30 disabled:cursor-not-allowed"
                  >
                    <ChevronRight size={14} />
                  </button>
                </div>
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  );
}
