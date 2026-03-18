import { useState, useEffect } from 'react';
import { Loader2, Save, Bot, Settings } from 'lucide-react';
import { getBotConfigs, updateBotConfig, getIsAdmin } from '../api';
import ChannelBadge from './ChannelBadge';

export default function BotsView() {
  const [configs, setConfigs] = useState([]);
  const [loading, setLoading] = useState(true);
  const [editingId, setEditingId] = useState(null);
  const [editForm, setEditForm] = useState({});
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    loadConfigs();
  }, []);

  async function loadConfigs() {
    setLoading(true);
    try {
      const data = await getBotConfigs();
      setConfigs(Array.isArray(data) ? data : []);
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  }

  function startEdit(config) {
    setEditingId(config.id);
    setEditForm({
      bot_name: config.bot_name || '',
      is_active: config.is_active || '1',
      ai_model: config.ai_model || 'gpt-4o-mini',
      system_prompt: config.system_prompt || '',
      welcome_message: config.welcome_message || '',
      fallback_message: config.fallback_message || '',
      escalation_keywords: config.escalation_keywords || '',
      max_response_tokens: config.max_response_tokens || 500,
      temperature: config.temperature || 0.7,
      outside_hours_message: config.outside_hours_message || '',
      auto_reply_outside_hours: config.auto_reply_outside_hours || '1',
      n8n_webhook_url: config.n8n_webhook_url || '',
    });
  }

  async function handleSave(configId) {
    setSaving(true);
    try {
      await updateBotConfig(configId, editForm);
      setEditingId(null);
      loadConfigs();
    } catch (err) {
      alert('Error: ' + err.message);
    } finally {
      setSaving(false);
    }
  }

  return (
    <div className="h-full overflow-y-auto p-4 sm:p-6">
      <div className="max-w-4xl mx-auto">
        <div className="mb-6">
          <h1 className="text-lg sm:text-xl font-bold text-gray-900" style={{ fontFamily: 'Poppins, sans-serif' }}>🤖 Configuración de Bots</h1>
          <p className="text-sm text-gray-500 mt-1">Personaliza el comportamiento de los bots de cada canal</p>
        </div>

        {loading ? (
          <div className="flex items-center justify-center py-20 text-gray-400">
            <Loader2 className="animate-spin mr-2" size={20} /> Cargando configuraciones...
          </div>
        ) : configs.length === 0 ? (
          <div className="text-center py-20 text-gray-400">
            <Bot size={48} className="mx-auto mb-3 opacity-30" />
            <p>No hay bots configurados. Agrega primero un canal.</p>
          </div>
        ) : (
          <div className="space-y-4">
            {configs.map(config => (
              <div key={config.id} className="bg-white rounded-xl border border-gray-200 overflow-hidden">
                {/* Header */}
                <div className="px-5 py-4 flex items-center justify-between border-b border-gray-100">
                  <div className="flex items-center gap-3">
                    <div className={`w-10 h-10 rounded-lg flex items-center justify-center text-white ${
                      config.channel_type === 'whatsapp' ? 'bg-green-500' :
                      config.channel_type === 'instagram' ? 'bg-pink-500' :
                      config.channel_type === 'telegram' ? 'bg-sky-500' :
                      'bg-blue-500'
                    }`}>
                      🤖
                    </div>
                    <div>
                      <h3 className="font-semibold text-sm">{config.bot_name}</h3>
                      <div className="flex items-center gap-2 mt-0.5">
                        <ChannelBadge type={config.channel_type} />
                        <span className="text-xs text-gray-400">{config.channel_name}</span>
                        {getIsAdmin() && config.client_name && (
                          <span className="text-[10px] bg-purple-100 text-purple-700 px-1.5 py-0.5 rounded-full font-medium">{config.client_name}</span>
                        )}
                        <span className={`text-xs px-1.5 py-0.5 rounded ${config.is_active === '1' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'}`}>
                          {config.is_active === '1' ? '✅ Activo' : '❌ Inactivo'}
                        </span>
                      </div>
                    </div>
                  </div>
                  <button
                    onClick={() => editingId === config.id ? setEditingId(null) : startEdit(config)}
                    className="flex items-center gap-1.5 px-3 py-1.5 bg-gray-100 text-gray-700 rounded-lg text-xs font-medium hover:bg-gray-200 transition-colors"
                  >
                    <Settings size={14} />
                    {editingId === config.id ? 'Cerrar' : 'Configurar'}
                  </button>
                </div>

                {/* Quick Info */}
                {editingId !== config.id && (
                  <div className="px-4 sm:px-5 py-3 grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4 text-sm">
                    <div>
                      <span className="text-gray-400 text-xs">Modelo AI</span>
                      <p className="font-medium">{config.ai_model}</p>
                    </div>
                    <div>
                      <span className="text-gray-400 text-xs">Max Tokens</span>
                      <p className="font-medium">{config.max_response_tokens}</p>
                    </div>
                    <div>
                      <span className="text-gray-400 text-xs">Temperatura</span>
                      <p className="font-medium">{config.temperature}</p>
                    </div>
                  </div>
                )}

                {/* Edit Form */}
                {editingId === config.id && (
                  <div className="p-4 sm:p-5 space-y-4 animate-fadein">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                      <div>
                        <label className="block text-xs font-medium text-gray-500 mb-1">Nombre del Bot</label>
                        <input
                          type="text" value={editForm.bot_name}
                          onChange={e => setEditForm({ ...editForm, bot_name: e.target.value })}
                          className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
                        />
                      </div>
                      <div>
                        <label className="block text-xs font-medium text-gray-500 mb-1">Modelo AI</label>
                        <select
                          value={editForm.ai_model}
                          onChange={e => setEditForm({ ...editForm, ai_model: e.target.value })}
                          className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
                        >
                          <option value="gpt-4o-mini">GPT-4o Mini (Económico)</option>
                          <option value="gpt-4o">GPT-4o (Potente)</option>
                          <option value="gpt-4-turbo">GPT-4 Turbo</option>
                        </select>
                      </div>
                      <div>
                        <label className="block text-xs font-medium text-gray-500 mb-1">Max Tokens Respuesta</label>
                        <input
                          type="number" value={editForm.max_response_tokens}
                          onChange={e => setEditForm({ ...editForm, max_response_tokens: e.target.value })}
                          className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
                          min="100" max="4000"
                        />
                      </div>
                      <div>
                        <label className="block text-xs font-medium text-gray-500 mb-1">Temperatura ({editForm.temperature})</label>
                        <input
                          type="range" min="0" max="1" step="0.05" value={editForm.temperature}
                          onChange={e => setEditForm({ ...editForm, temperature: e.target.value })}
                          className="w-full mt-2"
                        />
                        <div className="flex justify-between text-[10px] text-gray-400">
                          <span>Preciso</span><span>Creativo</span>
                        </div>
                      </div>
                    </div>

                    <div>
                      <label className="block text-xs font-medium text-gray-500 mb-1">System Prompt (Instrucciones del bot)</label>
                      <textarea
                        value={editForm.system_prompt}
                        onChange={e => setEditForm({ ...editForm, system_prompt: e.target.value })}
                        rows={4}
                        className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
                        placeholder="Eres un asistente virtual de [empresa]..."
                      />
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                      <div>
                        <label className="block text-xs font-medium text-gray-500 mb-1">Mensaje de Bienvenida</label>
                        <textarea
                          value={editForm.welcome_message}
                          onChange={e => setEditForm({ ...editForm, welcome_message: e.target.value })}
                          rows={2}
                          className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
                        />
                      </div>
                      <div>
                        <label className="block text-xs font-medium text-gray-500 mb-1">Mensaje de Fallback</label>
                        <textarea
                          value={editForm.fallback_message}
                          onChange={e => setEditForm({ ...editForm, fallback_message: e.target.value })}
                          rows={2}
                          className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
                        />
                      </div>
                    </div>

                    <div>
                      <label className="block text-xs font-medium text-gray-500 mb-1">Palabras clave de escalamiento (JSON array)</label>
                      <input
                        type="text" value={editForm.escalation_keywords}
                        onChange={e => setEditForm({ ...editForm, escalation_keywords: e.target.value })}
                        className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
                        placeholder='["hablar con humano","agente","ejecutivo"]'
                      />
                    </div>

                    <div>
                      <label className="block text-xs font-medium text-gray-500 mb-1">Webhook N8N (opcional)</label>
                      <input
                        type="url" value={editForm.n8n_webhook_url}
                        onChange={e => setEditForm({ ...editForm, n8n_webhook_url: e.target.value })}
                        className="w-full px-3 py-2 border border-gray-200 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
                        placeholder="https://n8n.tudominio.com/webhook/..."
                      />
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                      <div>
                        <label className="flex items-center gap-2">
                          <input
                            type="checkbox" checked={editForm.auto_reply_outside_hours === '1'}
                            onChange={e => setEditForm({ ...editForm, auto_reply_outside_hours: e.target.checked ? '1' : '0' })}
                            className="rounded"
                          />
                          <span className="text-sm text-gray-600">Auto-responder fuera de horario</span>
                        </label>
                      </div>
                      <div>
                        <label className="flex items-center gap-2">
                          <input
                            type="checkbox" checked={editForm.is_active === '1'}
                            onChange={e => setEditForm({ ...editForm, is_active: e.target.checked ? '1' : '0' })}
                            className="rounded"
                          />
                          <span className="text-sm text-gray-600">Bot activo</span>
                        </label>
                      </div>
                    </div>

                    <div className="flex gap-2 pt-2">
                      <button
                        onClick={() => handleSave(config.id)}
                        disabled={saving}
                        className="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50"
                      >
                        {saving ? <Loader2 size={16} className="animate-spin" /> : <Save size={16} />}
                        Guardar Cambios
                      </button>
                      <button
                        onClick={() => setEditingId(null)}
                        className="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm"
                      >
                        Cancelar
                      </button>
                    </div>
                  </div>
                )}
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
