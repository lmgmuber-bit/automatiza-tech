import { useState, useEffect } from 'react';
import { Plus, Loader2, Settings, Trash2, CheckCircle, XCircle, AlertTriangle } from 'lucide-react';
import { getChannels, createChannel, updateChannel, getIsAdmin, getClients, getChannelTypes, getPeriodStatus } from '../api';
import ChannelBadge from './ChannelBadge';
import ResultModal from './ResultModal';

export default function ChannelsView() {
  const [channels, setChannels] = useState([]);
  const [loading, setLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState({ channel_type: '', channel_name: '', phone_number: '', page_id: '', bot_token: '', client_id: '' });
  const [submitting, setSubmitting] = useState(false);
  const [clientsList, setClientsList] = useState([]);
  const [channelTypesList, setChannelTypesList] = useState([]);
  const [resultModal, setResultModal] = useState(null);
  const [maxChannels, setMaxChannels] = useState(null);
  const [planType, setPlanType] = useState('');

  useEffect(() => {
    if (getIsAdmin()) {
      getClients({ per_page: 100 }).then(data => setClientsList(data.data || [])).catch(() => {});
    } else {
      // Fetch plan limits for client
      getPeriodStatus().then(data => {
        if (data && data.max_channels) setMaxChannels(data.max_channels);
        if (data && data.plan_type) setPlanType(data.plan_type);
      }).catch(() => {});
    }
    getChannelTypes().then(data => {
      const types = Array.isArray(data) ? data : [];
      setChannelTypesList(types);
      if (types.length > 0 && !form.channel_type) {
        setForm(f => ({ ...f, channel_type: types[0].slug }));
      }
    }).catch(() => {});
  }, []);

  useEffect(() => {
    loadChannels();
  }, []);

  async function loadChannels() {
    setLoading(true);
    try {
      const data = await getChannels();
      setChannels(Array.isArray(data) ? data : []);
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  }

  async function handleCreate(e) {
    e.preventDefault();
    setSubmitting(true);
    try {
      const result = await createChannel(form);
      if (result.error) {
        setResultModal({ type: 'error', title: 'Error', message: result.error });
      } else {
        setResultModal({ type: 'success', title: 'Canal creado', message: 'El canal fue creado exitosamente.', detail: result.webhook_secret, detailLabel: 'Webhook Secret' });
        setShowForm(false);
        setForm({ channel_type: 'whatsapp', channel_name: '', phone_number: '', page_id: '', bot_token: '', client_id: '' });
        loadChannels();
      }
    } catch (err) {
      setResultModal({ type: 'error', title: 'Error', message: err.message });
    } finally {
      setSubmitting(false);
    }
  }

  async function toggleActive(channel) {
    try {
      await updateChannel(channel.id, { is_active: channel.is_active === '1' ? '0' : '1' });
      loadChannels();
    } catch (err) {
      setResultModal({ type: 'error', title: 'Error', message: err.message });
    }
  }

  // Build channelFields dynamically from API types
  const channelFields = {};
  channelTypesList.forEach(t => {
    try {
      channelFields[t.slug] = JSON.parse(t.fields_json || '[]');
    } catch { channelFields[t.slug] = []; }
  });

  // Build type lookup for emoji/color
  const typeMap = {};
  channelTypesList.forEach(t => { typeMap[t.slug] = t; });

  // Check if channel limit is reached (only for clients, not admin)
  const activeChannels = channels.filter(ch => ch.is_active === '1').length;
  const isAtLimit = !getIsAdmin() && maxChannels !== null && activeChannels >= maxChannels;

  return (
    <div className="h-full overflow-y-auto p-4 sm:p-6">
      <div className="max-w-4xl mx-auto">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
          <div>
            <h1 className="text-lg sm:text-xl font-bold text-gray-900" style={{ fontFamily: 'Poppins, sans-serif' }}>📡 Canales Conectados</h1>
            <p className="text-sm text-gray-500 mt-1">Configura tus canales de comunicación</p>
            {!getIsAdmin() && maxChannels !== null && (
              <p className="text-xs text-gray-400 mt-1">
                {activeChannels} de {maxChannels} canal{maxChannels > 1 ? 'es' : ''} utilizado{activeChannels !== 1 ? 's' : ''} — Plan {planType.charAt(0).toUpperCase() + planType.slice(1)}
              </p>
            )}
          </div>
          {isAtLimit ? (
            <div className="self-start">
              <div className="flex items-center gap-2 px-4 py-2 bg-amber-50 border border-amber-200 text-amber-800 rounded-lg text-sm font-medium cursor-not-allowed">
                <AlertTriangle size={16} /> Límite alcanzado
              </div>
            </div>
          ) : (
            <button
              onClick={() => setShowForm(!showForm)}
              className="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors self-start"
            >
              <Plus size={16} /> Agregar Canal
            </button>
          )}
        </div>

        {/* Limit warning banner */}
        {isAtLimit && (
          <div className="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-6 flex items-start gap-3">
            <AlertTriangle size={20} className="text-amber-500 shrink-0 mt-0.5" />
            <div>
              <p className="text-sm font-semibold text-amber-800">Has alcanzado el límite de canales de tu plan ({maxChannels})</p>
              <p className="text-xs text-amber-600 mt-1">
                Para agregar más canales, actualiza tu plan a uno superior o elimina/desactiva un canal existente.
              </p>
            </div>
          </div>
        )}

        {/* Create Form */}
        {showForm && !isAtLimit && (
          <div className="bg-white rounded-xl border border-gray-200 p-6 mb-6 animate-fadein">
            <h3 className="font-semibold mb-4">Nuevo Canal</h3>
            <form onSubmit={handleCreate}>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {getIsAdmin() && (
                  <div className="sm:col-span-2">
                    <label className="block text-sm font-medium text-gray-600 mb-1">Cliente *</label>
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
                  <label className="block text-sm font-medium text-gray-600 mb-1">Tipo de Canal</label>
                  <select
                    value={form.channel_type}
                    onChange={e => setForm({ ...form, channel_type: e.target.value, channel_name: '' })}
                    className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
                  >
                    {channelTypesList.map(t => (
                      <option key={t.slug} value={t.slug}>{t.emoji} {t.label}</option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-600 mb-1">Nombre del Canal</label>
                  <input
                    type="text"
                    value={form.channel_name}
                    onChange={e => setForm({ ...form, channel_name: e.target.value })}
                    placeholder={`Mi ${form.channel_type}`}
                    className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
                    required
                  />
                </div>
                {(channelFields[form.channel_type] || []).map(field => (
                  <div key={field.key}>
                    <label className="block text-sm font-medium text-gray-600 mb-1">{field.label}</label>
                    <input
                      type="text"
                      value={form[field.key] || ''}
                      onChange={e => setForm({ ...form, [field.key]: e.target.value })}
                      placeholder={field.placeholder}
                      className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
                    />
                  </div>
                ))}
              </div>
              <div className="flex gap-2 mt-4">
                <button type="submit" disabled={submitting} className="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
                  {submitting ? <Loader2 size={16} className="animate-spin" /> : 'Crear Canal'}
                </button>
                <button type="button" onClick={() => setShowForm(false)} className="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm">
                  Cancelar
                </button>
              </div>
            </form>
          </div>
        )}

        {/* Channel Cards */}
        {loading ? (
          <div className="flex items-center justify-center py-20 text-gray-400">
            <Loader2 className="animate-spin mr-2" size={20} /> Cargando canales...
          </div>
        ) : channels.length === 0 ? (
          <div className="text-center py-20 text-gray-400">
            <p className="text-lg">No tienes canales configurados</p>
            <p className="text-sm mt-1">Agrega tu primer canal para comenzar</p>
          </div>
        ) : (
          <div className="grid gap-4">
            {channels.map(ch => (
              <div key={ch.id} className="bg-white rounded-xl border border-gray-200 p-4 sm:p-5 flex flex-col sm:flex-row items-start sm:items-center gap-3 sm:gap-4">
                <div className="w-12 h-12 rounded-xl flex items-center justify-center text-white text-lg font-bold"
                  style={{ backgroundColor: colorToHex(typeMap[ch.channel_type]?.color || 'gray-500') }}
                >
                  {typeMap[ch.channel_type]?.emoji || '📡'}
                </div>
                <div className="flex-1">
                  <h4 className="font-semibold text-gray-900">{ch.channel_name}</h4>
                  <div className="flex items-center gap-2 mt-1">
                    <ChannelBadge type={ch.channel_type} />
                    {getIsAdmin() && ch.client_name && (
                      <span className="text-[10px] bg-purple-100 text-purple-700 px-1.5 py-0.5 rounded-full font-medium">{ch.client_name}</span>
                    )}
                    {ch.phone_number && <span className="text-xs text-gray-500">{ch.phone_number}</span>}
                    {ch.page_id && <span className="text-xs text-gray-500">ID: {ch.page_id}</span>}
                  </div>
                  <p className="text-[11px] text-gray-400 mt-1">
                    Última sincronización: {ch.last_synced_at || 'Nunca'}
                  </p>
                </div>
                <div className="flex items-center gap-2">
                  <button
                    onClick={() => toggleActive(ch)}
                    className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium transition-colors ${
                      ch.is_active === '1'
                        ? 'bg-green-50 text-green-700 hover:bg-green-100'
                        : 'bg-red-50 text-red-700 hover:bg-red-100'
                    }`}
                  >
                    {ch.is_active === '1' ? <><CheckCircle size={14} /> Activo</> : <><XCircle size={14} /> Inactivo</>}
                  </button>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      {resultModal && <ResultModal {...resultModal} onClose={() => setResultModal(null)} />}
    </div>
  );
}

function colorToHex(color) {
  const map = {
    'green-500': '#22c55e', 'pink-500': '#ec4899', 'sky-500': '#0ea5e9',
    'blue-500': '#3b82f6', 'purple-500': '#a855f7', 'red-500': '#ef4444',
    'orange-500': '#f97316', 'yellow-500': '#eab308', 'teal-500': '#14b8a6',
    'indigo-500': '#6366f1', 'gray-500': '#6b7280',
  };
  return map[color] || '#6b7280';
}
