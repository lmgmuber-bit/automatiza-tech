import { useState, useEffect } from 'react';
import { Plus, Loader2, Settings, Trash2, CheckCircle, XCircle, AlertTriangle, Pencil, Copy, Eye, EyeOff, X } from 'lucide-react';
import { getChannels, createChannel, updateChannel, deleteChannel, getIsAdmin, getClients, getChannelTypes, getPeriodStatus, API_BASE } from '../api';
import ChannelBadge from './ChannelBadge';
import ResultModal from './ResultModal';
import { getChannelIcon } from './ChannelIcons';
import ConfirmDeleteModal from './ConfirmDeleteModal';

export default function ChannelsView() {
  const [channels, setChannels] = useState([]);
  const [loading, setLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState({ channel_type: '', channel_name: '', phone_number: '', page_id: '', bot_token: '', client_id: '', ycloud_api_key: '' });
  const [submitting, setSubmitting] = useState(false);
  const [clientsList, setClientsList] = useState([]);
  const [channelTypesList, setChannelTypesList] = useState([]);
  const [resultModal, setResultModal] = useState(null);
  const [maxChannels, setMaxChannels] = useState(null);
  const [planType, setPlanType] = useState('');

  // Edit modal state
  const [editChannel, setEditChannel] = useState(null);
  const [editForm, setEditForm] = useState({});
  const [editSubmitting, setEditSubmitting] = useState(false);
  const [showApiKey, setShowApiKey] = useState(false);
  const [showEditApiKey, setShowEditApiKey] = useState(false);

  // Delete modal state
  const [deleteTarget, setDeleteTarget] = useState(null);
  const [deleteLoading, setDeleteLoading] = useState(false);

  const isAdmin = getIsAdmin();

  useEffect(() => {
    if (isAdmin) {
      getClients({ per_page: 100 }).then(data => setClientsList(data.data || [])).catch(() => {});
    } else {
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

  useEffect(() => { loadChannels(); }, []);

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
        setForm({ channel_type: channelTypesList[0]?.slug || 'whatsapp', channel_name: '', phone_number: '', page_id: '', bot_token: '', client_id: '', ycloud_api_key: '' });
        loadChannels();
      }
    } catch (err) {
      setResultModal({ type: 'error', title: 'Error', message: err.message });
    } finally {
      setSubmitting(false);
    }
  }

  function openEdit(ch) {
    setEditChannel(ch);
    setEditForm({
      channel_name: ch.channel_name || '',
      phone_number: ch.phone_number || '',
      page_id: ch.page_id || '',
      bot_token: ch.bot_token || '',
      ycloud_api_key: ch.ycloud_api_key || '',
      ycloud_waba_id: ch.ycloud_waba_id || '',
      ycloud_phone_id: ch.ycloud_phone_id || '',
      provider: ch.provider || 'ycloud',
    });
    setShowEditApiKey(false);
  }

  async function handleEdit(e) {
    e.preventDefault();
    setEditSubmitting(true);
    try {
      const result = await updateChannel(editChannel.id, editForm);
      if (result.error) {
        setResultModal({ type: 'error', title: 'Error', message: result.error });
      } else {
        setResultModal({ type: 'success', title: 'Canal actualizado', message: 'Los cambios fueron guardados exitosamente.' });
        setEditChannel(null);
        loadChannels();
      }
    } catch (err) {
      setResultModal({ type: 'error', title: 'Error', message: err.message });
    } finally {
      setEditSubmitting(false);
    }
  }

  async function handleDelete(confirmApiKey) {
    setDeleteLoading(true);
    try {
      const result = await deleteChannel(deleteTarget.id, confirmApiKey);
      if (result.error) throw new Error(result.error);
      setDeleteTarget(null);
      setResultModal({ type: 'success', title: 'Canal eliminado', message: 'El canal fue eliminado exitosamente.' });
      loadChannels();
    } catch (err) {
      throw err;
    } finally {
      setDeleteLoading(false);
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

  function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
      setResultModal({ type: 'success', title: 'Copiado', message: 'Texto copiado al portapapeles.' });
    });
  }

  // Build channelFields dynamically from API types
  const channelFields = {};
  channelTypesList.forEach(t => {
    try { channelFields[t.slug] = JSON.parse(t.fields_json || '[]'); }
    catch { channelFields[t.slug] = []; }
  });

  const typeMap = {};
  channelTypesList.forEach(t => { typeMap[t.slug] = t; });

  const activeChannels = channels.filter(ch => ch.is_active === '1').length;
  const isAtLimit = !isAdmin && maxChannels !== null && activeChannels >= maxChannels;

  return (
    <div className="h-full overflow-y-auto p-4 sm:p-6">
      <div className="max-w-4xl mx-auto">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
          <div>
            <h1 className="text-lg sm:text-xl font-bold text-gray-900" style={{ fontFamily: 'Poppins, sans-serif' }}>📡 Canales Conectados</h1>
            <p className="text-sm text-gray-500 mt-1">Configura tus canales de comunicación</p>
            {!isAdmin && maxChannels !== null && (
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
              onClick={() => { setShowForm(!showForm); setShowApiKey(false); }}
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
                {isAdmin && (
                  <div className="sm:col-span-2">
                    <label className="block text-sm font-medium text-gray-600 mb-1">Cliente *</label>
                    <select
                      value={form.client_id}
                      onChange={e => setForm({ ...form, client_id: e.target.value })}
                      title="Selecciona a qué cliente pertenece este canal. Cada canal queda asociado a un cliente específico."
                      className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
                      required
                    >
                      <option value="">Seleccionar cliente...</option>
                      {clientsList.map(c => (
                        <option key={c.id} value={c.id}>{c.company_name} ({c.email})</option>
                      ))}
                    </select>
                    <p className="text-[11px] text-gray-400 mt-1">El canal quedará asociado a este cliente</p>
                  </div>
                )}
                <div>
                  <label className="block text-sm font-medium text-gray-600 mb-1">Tipo de Canal</label>
                  <select
                    value={form.channel_type}
                    onChange={e => setForm({ ...form, channel_type: e.target.value, channel_name: '' })}
                    title="Plataforma de mensajería: WhatsApp (YCloud), Facebook Messenger, Instagram, etc."
                    className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
                  >
                    {channelTypesList.map(t => (
                      <option key={t.slug} value={t.slug}>{t.emoji} {t.label}</option>
                    ))}
                  </select>
                  <p className="text-[11px] text-gray-400 mt-1">Plataforma de mensajería a conectar</p>
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-600 mb-1">Nombre del Canal</label>
                  <input
                    type="text"
                    value={form.channel_name}
                    onChange={e => setForm({ ...form, channel_name: e.target.value })}
                    placeholder={`Ej: WhatsApp Ventas`}
                    title="Nombre interno para identificar este canal dentro del portal. Solo lo ven los agentes y admins."
                    className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
                    required
                  />
                  <p className="text-[11px] text-gray-400 mt-1">Nombre interno para identificar el canal en el portal</p>
                </div>
                {(channelFields[form.channel_type] || []).map(field => (
                  <div key={field.key}>
                    <label className="block text-sm font-medium text-gray-600 mb-1">{field.label}</label>
                    <input
                      type="text"
                      value={form[field.key] || ''}
                      onChange={e => setForm({ ...form, [field.key]: e.target.value })}
                      placeholder={field.placeholder}
                      title={field.hint || field.placeholder}
                      className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
                    />
                    {field.hint && <p className="text-[11px] text-gray-400 mt-1">{field.hint}</p>}
                  </div>
                ))}
                {/* YCloud API Key field - shown for whatsapp type or always for admin */}
                {(form.channel_type === 'whatsapp' || isAdmin) && (
                  <div className="sm:col-span-2">
                    <label className="block text-sm font-medium text-gray-600 mb-1">🔑 YCloud API Key</label>
                    <div className="relative">
                      <input
                        type={showApiKey ? 'text' : 'password'}
                        value={form.ycloud_api_key || ''}
                        onChange={e => setForm({ ...form, ycloud_api_key: e.target.value })}
                        placeholder="Pega tu API Key de YCloud aquí"
                        title="Clave secreta de autenticación de YCloud. Necesaria para enviar mensajes WhatsApp. La encuentras en YCloud Console → Settings → API Keys."
                        className="w-full px-3 py-2 pr-10 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 font-mono"
                      />
                      <button
                        type="button"
                        onClick={() => setShowApiKey(!showApiKey)}
                        className="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                      >
                        {showApiKey ? <EyeOff size={16} /> : <Eye size={16} />}
                      </button>
                    </div>
                    <p className="text-[11px] text-gray-400 mt-1">Clave de autenticación YCloud — Console → Settings → API Keys</p>
                  </div>
                )}
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
              <div key={ch.id} className="bg-white rounded-xl border border-gray-200 p-4 sm:p-5">
                <div className="flex flex-col sm:flex-row items-start sm:items-center gap-3 sm:gap-4">
                  <div className="w-12 h-12 rounded-xl flex items-center justify-center text-white text-lg font-bold shrink-0"
                    style={{ backgroundColor: colorToHex(typeMap[ch.channel_type]?.color || 'gray-500') }}
                  >
                    {getChannelIcon(ch.channel_type, 24) || typeMap[ch.channel_type]?.emoji || '📡'}
                  </div>
                  <div className="flex-1 min-w-0">
                    <h4 className="font-semibold text-gray-900">{ch.channel_name}</h4>
                    <div className="flex flex-wrap items-center gap-2 mt-1">
                      <ChannelBadge type={ch.channel_type} />
                      {isAdmin && ch.client_name && (
                        <span className="text-[10px] bg-purple-100 text-purple-700 px-1.5 py-0.5 rounded-full font-medium">{ch.client_name}</span>
                      )}
                      {ch.phone_number && <span className="text-xs text-gray-500">{ch.phone_number}</span>}
                      {ch.page_id && <span className="text-xs text-gray-500">ID: {ch.page_id}</span>}
                    </div>
                    {/* Webhook URL para YCloud - visible para admin */}
                    {isAdmin && ch.webhook_secret && (
                      <div className="mt-2 p-2 bg-blue-50 rounded-lg border border-blue-100">
                        <div className="flex items-center justify-between gap-2 mb-1">
                          <span className="text-[10px] font-semibold text-blue-700">URL Webhook YCloud</span>
                          <button
                            onClick={() => copyToClipboard(`${window.location.origin}${API_BASE}?route=webhook/ycloud&channel_id=${ch.id}&secret=${ch.webhook_secret}`)}
                            className="flex items-center gap-1 text-[10px] text-blue-600 hover:text-blue-800 font-medium"
                            title="Copiar URL completa"
                          >
                            <Copy size={11} /> Copiar URL
                          </button>
                        </div>
                        <code className="text-[10px] text-blue-800 font-mono break-all leading-relaxed">
                          {window.location.origin}{API_BASE}?route=webhook/ycloud&amp;channel_id={ch.id}&amp;secret={ch.webhook_secret}
                        </code>
                      </div>
                    )}
                    {/* Show API Key status for admin */}
                    {isAdmin && (
                      <div className="flex items-center gap-1.5 mt-1">
                        <span className="text-[10px] text-gray-400">API Key:</span>
                        {ch.ycloud_api_key ? (
                          <span className="text-[10px] text-green-600 font-medium">✓ Configurada</span>
                        ) : (
                          <span className="text-[10px] text-red-500 font-medium">✗ No configurada</span>
                        )}
                      </div>
                    )}
                    <p className="text-[11px] text-gray-400 mt-1">
                      Última sincronización: {ch.last_synced_at || 'Nunca'}
                    </p>
                  </div>
                  <div className="flex items-center gap-2 shrink-0">
                    {/* Toggle active */}
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
                    {/* Edit button */}
                    {isAdmin && (
                      <button
                        onClick={() => openEdit(ch)}
                        className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-blue-50 text-blue-700 hover:bg-blue-100 transition-colors"
                        title="Editar canal"
                      >
                        <Pencil size={14} /> Editar
                      </button>
                    )}
                    {/* Delete button */}
                    {isAdmin && (
                      <button
                        onClick={() => setDeleteTarget(ch)}
                        className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-red-50 text-red-700 hover:bg-red-100 transition-colors"
                        title="Eliminar canal"
                      >
                        <Trash2 size={14} />
                      </button>
                    )}
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}
      </div>

      {/* Edit Channel Modal */}
      {editChannel && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4" onClick={() => setEditChannel(null)}>
          <div className="absolute inset-0 bg-black/50 backdrop-blur-sm" />
          <div className="relative bg-white rounded-xl shadow-2xl w-full max-w-lg p-6 animate-fadein max-h-[90vh] overflow-y-auto" onClick={e => e.stopPropagation()}>
            <button onClick={() => setEditChannel(null)} className="absolute top-3 right-3 text-gray-400 hover:text-gray-600">
              <X size={18} />
            </button>
            <h3 className="text-lg font-semibold text-gray-900 mb-1">✏️ Editar Canal</h3>
            <p className="text-sm text-gray-500 mb-4">ID: {editChannel.id} — {typeMap[editChannel.channel_type]?.emoji} {typeMap[editChannel.channel_type]?.label || editChannel.channel_type}</p>

            <form onSubmit={handleEdit}>
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div className="sm:col-span-2">
                  <label className="block text-sm font-medium text-gray-600 mb-1">Nombre del Canal</label>
                  <input
                    type="text"
                    value={editForm.channel_name}
                    onChange={e => setEditForm({ ...editForm, channel_name: e.target.value })}
                    placeholder="Ej: WhatsApp Ventas"
                    title="Nombre interno para identificar este canal dentro del portal. Solo lo ven los agentes y admins."
                    className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
                    required
                  />
                  <p className="text-[11px] text-gray-400 mt-1">Nombre interno visible solo para agentes y admins</p>
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-600 mb-1">Teléfono</label>
                  <input
                    type="text"
                    value={editForm.phone_number}
                    onChange={e => setEditForm({ ...editForm, phone_number: e.target.value })}
                    placeholder="+56912345678"
                    title="Número de teléfono del canal con código de país. Ej: +56912345678"
                    className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
                  />
                  <p className="text-[11px] text-gray-400 mt-1">Número con código de país, ej: +56912345678</p>
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-600 mb-1">
                    Page ID
                    <span className="ml-1 text-[10px] text-gray-400 font-normal">(solo Messenger/Instagram)</span>
                  </label>
                  <input
                    type="text"
                    value={editForm.page_id}
                    onChange={e => setEditForm({ ...editForm, page_id: e.target.value })}
                    placeholder="ID numérico de tu página de Facebook"
                    title="Solo para canales Facebook Messenger o Instagram. Es el ID numérico de tu página de Facebook. Para WhatsApp déjalo vacío."
                    className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
                  />
                  <p className="text-[11px] text-gray-400 mt-1">Solo para Facebook Messenger/Instagram — déjalo vacío en WhatsApp</p>
                </div>

                {/* YCloud fields */}
                <div className="sm:col-span-2 border-t border-gray-100 pt-4 mt-1">
                  <h4 className="text-sm font-semibold text-gray-700 mb-3">🔑 Configuración YCloud</h4>
                </div>
                <div className="sm:col-span-2">
                  <label className="block text-sm font-medium text-gray-600 mb-1">API Key</label>
                  <div className="relative">
                    <input
                      type={showEditApiKey ? 'text' : 'password'}
                      value={editForm.ycloud_api_key}
                      onChange={e => setEditForm({ ...editForm, ycloud_api_key: e.target.value })}
                      placeholder="Pega tu API Key de YCloud aquí"
                      title="Clave secreta de autenticación de YCloud. Necesaria para enviar mensajes WhatsApp. La encuentras en YCloud Console → Settings → API Keys."
                      className="w-full px-3 py-2 pr-10 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 font-mono"
                    />
                    <button
                      type="button"
                      onClick={() => setShowEditApiKey(!showEditApiKey)}
                      className="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                    >
                      {showEditApiKey ? <EyeOff size={16} /> : <Eye size={16} />}
                    </button>
                  </div>
                  <p className="text-[11px] text-gray-400 mt-1">YCloud Console → Settings → API Keys</p>
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-600 mb-1">WABA ID</label>
                  <input
                    type="text"
                    value={editForm.ycloud_waba_id}
                    onChange={e => setEditForm({ ...editForm, ycloud_waba_id: e.target.value })}
                    placeholder="Ej: 102938475610293"
                    title="WhatsApp Business Account ID — ID de tu cuenta de WhatsApp Business en Meta. Lo encuentras en YCloud Console → tu canal → 'WhatsApp Business Account ID'"
                    className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 font-mono"
                  />
                  <p className="text-[11px] text-gray-400 mt-1">WhatsApp Business Account ID — YCloud Console → tu canal</p>
                </div>
                <div>
                  <label className="block text-sm font-medium text-gray-600 mb-1">Phone Number ID</label>
                  <input
                    type="text"
                    value={editForm.ycloud_phone_id}
                    onChange={e => setEditForm({ ...editForm, ycloud_phone_id: e.target.value })}
                    placeholder="Ej: 112233445566778"
                    title="ID del número de teléfono registrado en tu WABA. Lo encuentras en YCloud Console → tu canal → 'Phone Number ID'. Distinto al número de teléfono en sí."
                    className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 font-mono"
                  />
                  <p className="text-[11px] text-gray-400 mt-1">ID del número en Meta — YCloud Console → tu canal</p>
                </div>

                {/* Webhook Secret (read-only) */}
                {editChannel.webhook_secret && (
                  <div className="sm:col-span-2">
                    <label className="block text-sm font-medium text-gray-600 mb-1">URL Webhook para YCloud</label>
                    <div className="flex items-center gap-2">
                      <input
                        type="text"
                        value={`${window.location.origin}${API_BASE}?route=webhook/ycloud&channel_id=${editChannel.id}&secret=${editChannel.webhook_secret}`}
                        readOnly
                        title="Copia esta URL y pégala en YCloud Console como endpoint del webhook."
                        className="w-full px-3 py-2 border border-blue-100 rounded-lg text-xs bg-blue-50 font-mono text-blue-800"
                      />
                      <button
                        type="button"
                        onClick={() => copyToClipboard(`${window.location.origin}${API_BASE}?route=webhook/ycloud&channel_id=${editChannel.id}&secret=${editChannel.webhook_secret}`)}
                        className="px-3 py-2 text-blue-500 hover:text-blue-700 border border-blue-200 rounded-lg bg-blue-50"
                        title="Copiar URL completa"
                      >
                        <Copy size={16} />
                      </button>
                    </div>
                    <p className="text-[11px] text-gray-400 mt-1">Pégala en YCloud Console → Webhooks → Agregar punto final — Evento: <code className="bg-gray-100 px-1 rounded">whatsapp.inbound_message.received</code></p>
                  </div>
                )}
              </div>

              <div className="flex gap-2 mt-6">
                <button type="submit" disabled={editSubmitting} className="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50 flex items-center gap-2">
                  {editSubmitting ? <Loader2 size={16} className="animate-spin" /> : <><Pencil size={14} /> Guardar Cambios</>}
                </button>
                <button type="button" onClick={() => setEditChannel(null)} className="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm">
                  Cancelar
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Delete Channel Confirmation */}
      <ConfirmDeleteModal
        isOpen={!!deleteTarget}
        onClose={() => setDeleteTarget(null)}
        onConfirm={handleDelete}
        title={`Eliminar canal "${deleteTarget?.channel_name}"`}
        description="Esta acción eliminará el canal y toda su configuración asociada. Esta operación no se puede deshacer."
        loading={deleteLoading}
        skipApiKey={isAdmin}
      />

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
