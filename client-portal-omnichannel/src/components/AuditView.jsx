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
    create: 'bg-green-100 text-green-700',
    update: 'bg-blue-100 text-blue-700',
    delete: 'bg-red-100 text-red-700',
    takeover: 'bg-purple-100 text-purple-700',
    release: 'bg-amber-100 text-amber-700',
    transfer: 'bg-cyan-100 text-cyan-700',
  };

  const entityIcons = {
    client: '🏢',
    channel: '📡',
    bot_config: '🤖',
    conversation: '💬',
    agent: '👤',
  };

  return (
    <div className="h-full overflow-y-auto p-4 sm:p-6">
      <div className="max-w-5xl mx-auto">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
          <div>
            <h1 className="text-lg sm:text-xl font-bold text-gray-900" style={{ fontFamily: 'Poppins, sans-serif' }}>📋 Registro de Auditoría</h1>
            <p className="text-sm text-gray-500 mt-1">Historial completo de cambios y acciones en el sistema</p>
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
              className={`inline-flex items-center gap-1 px-2 py-1 rounded text-xs font-medium transition-colors ${
                sort.orderby === col.key ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'
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
          <div className="text-center py-20 text-gray-400">
            <ClipboardList size={48} className="mx-auto mb-3 opacity-30" />
            <p>{search ? 'Sin resultados para esta búsqueda' : 'Sin registros de auditoría'}</p>
          </div>
        ) : (
          <div className="relative">
            {loading && (
              <div className="absolute inset-0 bg-white/60 flex items-center justify-center z-10 rounded-lg">
                <Loader2 size={20} className="animate-spin text-blue-500" />
              </div>
            )}
            {/* Timeline */}
            <div className="space-y-3">
              {logs.map(log => {
                const isExpanded = expandedId === log.id;
                return (
                  <div key={log.id} className="bg-white rounded-xl border border-gray-200 overflow-hidden animate-fadein">
                    <div className="px-4 sm:px-5 py-3 flex flex-col sm:flex-row items-start sm:items-center gap-3 sm:gap-4">
                      {/* Icon */}
                      <div className="text-2xl shrink-0">
                        {entityIcons[log.entity_type] || '📌'}
                      </div>

                      {/* Info */}
                      <div className="flex-1 min-w-0">
                        <div className="flex items-center gap-2 flex-wrap">
                          <span className={`px-2 py-0.5 rounded text-xs font-medium ${actionColors[log.action] || 'bg-gray-100 text-gray-600'}`}>
                            {log.action}
                          </span>
                          <span className="text-xs text-gray-400">
                            {log.entity_type} #{log.entity_id}
                          </span>
                        </div>
                        <p className="text-sm text-gray-700 mt-1 truncate">{log.description}</p>
                        <div className="flex items-center gap-3 mt-1 text-xs text-gray-400">
                          <span>👤 {log.user_email || 'Sistema'}</span>
                          {log.ip_address && <span>🌐 {log.ip_address}</span>}
                        </div>
                      </div>

                      {/* Timestamp & expand */}
                      <div className="text-right shrink-0">
                        <div className="text-xs text-gray-400 whitespace-nowrap">
                          {new Date(log.created_at).toLocaleDateString('es', { day: '2-digit', month: 'short', year: 'numeric' })}
                        </div>
                        <div className="text-[10px] text-gray-400">
                          {new Date(log.created_at).toLocaleTimeString('es', { hour: '2-digit', minute: '2-digit', second: '2-digit' })}
                        </div>
                        {(log.old_values_json || log.new_values_json) && (
                          <button
                            onClick={() => setExpandedId(isExpanded ? null : log.id)}
                            className="mt-1 text-blue-500 hover:text-blue-700 text-xs flex items-center gap-1 ml-auto"
                          >
                            <Eye size={12} /> {isExpanded ? 'Ocultar' : 'Detalles'}
                          </button>
                        )}
                      </div>
                    </div>

                    {/* Expanded Details */}
                    {isExpanded && (
                      <div className="px-5 py-3 bg-gray-50 border-t border-gray-100 space-y-3">
                        {log.old_values_json && (
                          <div>
                            <p className="text-xs font-semibold text-gray-500 mb-1">Valores Anteriores:</p>
                            <pre className="bg-red-50 rounded-lg p-3 text-xs text-red-800 overflow-x-auto">
                              {JSON.stringify(JSON.parse(log.old_values_json), null, 2)}
                            </pre>
                          </div>
                        )}
                        {log.new_values_json && (
                          <div>
                            <p className="text-xs font-semibold text-gray-500 mb-1">Valores Nuevos:</p>
                            <pre className="bg-green-50 rounded-lg p-3 text-xs text-green-800 overflow-x-auto">
                              {JSON.stringify(JSON.parse(log.new_values_json), null, 2)}
                            </pre>
                          </div>
                        )}
                        {log.user_agent && (
                          <p className="text-[10px] text-gray-400">User Agent: {log.user_agent}</p>
                        )}
                      </div>
                    )}
                  </div>
                );
              })}
            </div>

            {/* Pagination */}
            {meta.total_pages > 1 && (
              <div className="flex items-center justify-between mt-4 pt-3 border-t border-slate-100">
                <p className="text-xs text-slate-400">
                  {meta.total} registro{meta.total !== 1 ? 's' : ''} · Página {meta.page} de {meta.total_pages}
                </p>
                <div className="flex items-center gap-1">
                  <button
                    onClick={() => setPage(p => Math.max(1, p - 1))}
                    disabled={page <= 1}
                    className="p-1.5 rounded-lg bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 disabled:opacity-30 disabled:cursor-not-allowed"
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
                            : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50'
                        }`}
                      >
                        {pageNum}
                      </button>
                    );
                  })}
                  <button
                    onClick={() => setPage(p => Math.min(meta.total_pages, p + 1))}
                    disabled={page >= meta.total_pages}
                    className="p-1.5 rounded-lg bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 disabled:opacity-30 disabled:cursor-not-allowed"
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
