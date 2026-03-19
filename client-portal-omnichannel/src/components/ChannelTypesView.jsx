import { useState, useEffect } from 'react';
import { Plus, Loader2, Pencil, Trash2, X, Save, GripVertical } from 'lucide-react';
import { getChannelTypes, createChannelType, updateChannelType, deleteChannelType } from '../api';
import ResultModal from './ResultModal';

const COLOR_OPTIONS = [
  'green-500', 'pink-500', 'sky-500', 'blue-500', 'purple-500',
  'red-500', 'orange-500', 'yellow-500', 'teal-500', 'indigo-500', 'gray-500',
];

export default function ChannelTypesView() {
  const [types, setTypes] = useState([]);
  const [loading, setLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [editingId, setEditingId] = useState(null);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState('');
  const [resultModal, setResultModal] = useState(null);

  const emptyForm = { slug: '', label: '', emoji: '📡', color: 'gray-500', sort_order: 0, fields: [] };
  const [form, setForm] = useState(emptyForm);

  useEffect(() => { loadTypes(); }, []);

  async function loadTypes() {
    setLoading(true);
    try {
      const data = await getChannelTypes();
      setTypes(Array.isArray(data) ? data : []);
    } catch (err) {
      console.error(err);
    } finally {
      setLoading(false);
    }
  }

  function startEdit(type) {
    let fields = [];
    try { fields = JSON.parse(type.fields_json || '[]'); } catch { fields = []; }
    setForm({
      slug: type.slug,
      label: type.label,
      emoji: type.emoji || '📡',
      color: type.color || 'gray-500',
      sort_order: parseInt(type.sort_order) || 0,
      fields,
    });
    setEditingId(type.id);
    setShowForm(false);
    setError('');
  }

  function cancelEdit() {
    setEditingId(null);
    setForm(emptyForm);
    setError('');
  }

  function startCreate() {
    setEditingId(null);
    setForm(emptyForm);
    setShowForm(true);
    setError('');
  }

  async function handleSubmit(e) {
    e.preventDefault();
    if (!form.slug.trim() || !form.label.trim()) {
      setError('Slug y nombre son requeridos');
      return;
    }
    setSubmitting(true);
    setError('');
    try {
      const payload = { ...form };
      let result;
      if (editingId) {
        result = await updateChannelType(editingId, payload);
      } else {
        result = await createChannelType(payload);
      }
      if (result.error) {
        setError(result.error);
      } else {
        setShowForm(false);
        setEditingId(null);
        setForm(emptyForm);
        loadTypes();
      }
    } catch (err) {
      setError(err.message || 'Error al guardar');
    } finally {
      setSubmitting(false);
    }
  }

  const [deleteConfirm, setDeleteConfirm] = useState(null);

  function requestDelete(type) {
    setDeleteConfirm(type);
    setResultModal({ type: 'confirm', title: '¿Eliminar tipo de canal?', message: `¿Eliminar el tipo "${type.label}"? Solo se puede si no hay canales usándolo.`, onConfirm: () => executeDelete(type) });
  }

  async function executeDelete(type) {
    setResultModal(null);
    try {
      const result = await deleteChannelType(type.id);
      if (result.error) {
        setResultModal({ type: 'error', title: 'Error', message: result.error });
      } else {
        loadTypes();
      }
    } catch (err) {
      setResultModal({ type: 'error', title: 'Error', message: err.message });
    }
  }

  // Dynamic field management
  function addField() {
    setForm({ ...form, fields: [...form.fields, { key: '', label: '', placeholder: '' }] });
  }
  function updateField(index, key, value) {
    const fields = [...form.fields];
    fields[index] = { ...fields[index], [key]: value };
    setForm({ ...form, fields });
  }
  function removeField(index) {
    setForm({ ...form, fields: form.fields.filter((_, i) => i !== index) });
  }

  function renderForm(isEdit) {
    return (
      <form onSubmit={handleSubmit} className="bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-6 mb-6 animate-fadein">
        <h3 className="font-semibold mb-4 text-gray-900 dark:text-white">
          {isEdit ? `Editar: ${form.label}` : 'Nuevo Tipo de Canal'}
        </h3>
        {error && <div className="text-red-600 dark:text-red-400 text-sm mb-3 bg-red-50 dark:bg-red-900/20 p-2 rounded">{error}</div>}

        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <div>
            <label className="block text-sm font-medium text-gray-600 dark:text-gray-300 mb-1">Slug (ID único) *</label>
            <input
              type="text"
              value={form.slug}
              onChange={e => setForm({ ...form, slug: e.target.value.toLowerCase().replace(/[^a-z0-9-_]/g, '') })}
              placeholder="ej: whatsapp, email, sms"
              className="w-full px-3 py-2 border border-gray-200 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500"
              required
              disabled={isEdit}
            />
            {isEdit && <p className="text-[10px] text-gray-400 mt-1">El slug no se puede cambiar</p>}
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-600 dark:text-gray-300 mb-1">Nombre *</label>
            <input
              type="text"
              value={form.label}
              onChange={e => setForm({ ...form, label: e.target.value })}
              placeholder="WhatsApp"
              className="w-full px-3 py-2 border border-gray-200 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500"
              required
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-600 dark:text-gray-300 mb-1">Emoji</label>
            <input
              type="text"
              value={form.emoji}
              onChange={e => setForm({ ...form, emoji: e.target.value })}
              placeholder="📱"
              className="w-full px-3 py-2 border border-gray-200 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500"
              maxLength={4}
            />
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-600 dark:text-gray-300 mb-1">Color</label>
            <select
              value={form.color}
              onChange={e => setForm({ ...form, color: e.target.value })}
              className="w-full px-3 py-2 border border-gray-200 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500"
            >
              {COLOR_OPTIONS.map(c => (
                <option key={c} value={c}>{c}</option>
              ))}
            </select>
          </div>
          <div>
            <label className="block text-sm font-medium text-gray-600 dark:text-gray-300 mb-1">Orden</label>
            <input
              type="number"
              value={form.sort_order}
              onChange={e => setForm({ ...form, sort_order: parseInt(e.target.value) || 0 })}
              className="w-full px-3 py-2 border border-gray-200 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-blue-500"
              min={0}
            />
          </div>
        </div>

        {/* Dynamic Fields */}
        <div className="mt-5">
          <div className="flex items-center justify-between mb-2">
            <label className="text-sm font-medium text-gray-600 dark:text-gray-300">Campos del formulario de canal</label>
            <button type="button" onClick={addField} className="text-xs text-blue-600 hover:text-blue-800 flex items-center gap-1">
              <Plus size={12} /> Agregar campo
            </button>
          </div>
          {form.fields.length === 0 && (
            <p className="text-xs text-gray-400 italic">Sin campos adicionales. Los canales de este tipo solo pedirán nombre.</p>
          )}
          <div className="space-y-2">
            {form.fields.map((field, i) => (
              <div key={i} className="flex flex-wrap sm:flex-nowrap items-center gap-2 bg-gray-50 dark:bg-slate-700/50 p-2 rounded-lg">
                <GripVertical size={14} className="text-gray-400 shrink-0" />
                <input
                  type="text"
                  value={field.key}
                  onChange={e => updateField(i, 'key', e.target.value.toLowerCase().replace(/[^a-z0-9_]/g, ''))}
                  placeholder="key (ej: phone_number)"
                  className="flex-1 px-2 py-1.5 border border-gray-200 dark:border-slate-600 rounded text-xs bg-white dark:bg-slate-700 text-gray-900 dark:text-white"
                  required
                />
                <input
                  type="text"
                  value={field.label}
                  onChange={e => updateField(i, 'label', e.target.value)}
                  placeholder="Label (ej: Teléfono)"
                  className="flex-1 px-2 py-1.5 border border-gray-200 dark:border-slate-600 rounded text-xs bg-white dark:bg-slate-700 text-gray-900 dark:text-white"
                  required
                />
                <input
                  type="text"
                  value={field.placeholder}
                  onChange={e => updateField(i, 'placeholder', e.target.value)}
                  placeholder="Placeholder"
                  className="flex-1 px-2 py-1.5 border border-gray-200 dark:border-slate-600 rounded text-xs bg-white dark:bg-slate-700 text-gray-900 dark:text-white"
                />
                <button type="button" onClick={() => removeField(i)} className="p-1 text-red-500 hover:text-red-700">
                  <X size={14} />
                </button>
              </div>
            ))}
          </div>
        </div>

        <div className="flex gap-2 mt-5">
          <button type="submit" disabled={submitting} className="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 disabled:opacity-50">
            {submitting ? <Loader2 size={16} className="animate-spin" /> : <Save size={16} />}
            {isEdit ? 'Guardar Cambios' : 'Crear Tipo'}
          </button>
          <button type="button" onClick={isEdit ? cancelEdit : () => setShowForm(false)} className="px-4 py-2 bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-300 rounded-lg text-sm hover:bg-gray-200 dark:hover:bg-slate-600">
            Cancelar
          </button>
        </div>
      </form>
    );
  }

  return (
    <div className="h-full overflow-y-auto p-4 sm:p-6">
      <div className="max-w-4xl mx-auto">
        <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
          <div>
            <h1 className="text-lg sm:text-xl font-bold text-gray-900 dark:text-white" style={{ fontFamily: 'Poppins, sans-serif' }}>🔧 Tipos de Canal</h1>
            <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">Administra los tipos de canal disponibles en el sistema</p>
          </div>
          {!showForm && !editingId && (
            <button
              onClick={startCreate}
              className="flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 transition-colors self-start"
            >
              <Plus size={16} /> Nuevo Tipo
            </button>
          )}
        </div>

        {/* Create Form */}
        {showForm && renderForm(false)}

        {/* List */}
        {loading ? (
          <div className="flex items-center justify-center py-20 text-gray-400">
            <Loader2 className="animate-spin mr-2" size={20} /> Cargando tipos de canal...
          </div>
        ) : types.length === 0 ? (
          <div className="text-center py-20 text-gray-400">
            <p className="text-lg">No hay tipos de canal</p>
            <p className="text-sm mt-1">Crea el primero para comenzar</p>
          </div>
        ) : (
          <div className="grid gap-3">
            {types.map(type => {
              let fields = [];
              try { fields = JSON.parse(type.fields_json || '[]'); } catch { fields = []; }

              if (editingId === type.id) {
                return <div key={type.id}>{renderForm(true)}</div>;
              }

              return (
                <div key={type.id} className={`bg-white dark:bg-slate-800 rounded-xl border border-gray-200 dark:border-slate-700 p-4 sm:p-5 flex flex-col sm:flex-row items-start sm:items-center gap-3 sm:gap-4 ${type.is_active === '0' ? 'opacity-50' : ''}`}>
                  <div className={`w-12 h-12 rounded-xl flex items-center justify-center text-white text-xl font-bold bg-${type.color || 'gray-500'}`}
                    style={{ backgroundColor: colorToHex(type.color) }}
                  >
                    {type.emoji || '📡'}
                  </div>
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2">
                      <h4 className="font-semibold text-gray-900 dark:text-white">{type.label}</h4>
                      <span className="text-[10px] bg-gray-100 dark:bg-slate-700 text-gray-500 dark:text-gray-400 px-1.5 py-0.5 rounded font-mono">{type.slug}</span>
                      {type.is_active === '0' && (
                        <span className="text-[10px] bg-red-100 text-red-600 px-1.5 py-0.5 rounded font-medium">Inactivo</span>
                      )}
                    </div>
                    <div className="flex flex-wrap gap-1.5 mt-1.5">
                      {fields.length > 0 ? fields.map((f, i) => (
                        <span key={i} className="text-[10px] bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 px-1.5 py-0.5 rounded">
                          {f.label} ({f.key})
                        </span>
                      )) : (
                        <span className="text-[10px] text-gray-400 italic">Sin campos adicionales</span>
                      )}
                    </div>
                    <p className="text-[10px] text-gray-400 mt-1">Orden: {type.sort_order}</p>
                  </div>
                  <div className="flex items-center gap-2 shrink-0">
                    <button
                      onClick={() => startEdit(type)}
                      className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-300 hover:bg-amber-100 dark:hover:bg-amber-900/40 transition-colors"
                    >
                      <Pencil size={13} /> Editar
                    </button>
                    <button
                      onClick={() => requestDelete(type)}
                      className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-300 hover:bg-red-100 dark:hover:bg-red-900/40 transition-colors"
                    >
                      <Trash2 size={13} /> Eliminar
                    </button>
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </div>

      {resultModal && <ResultModal {...resultModal} onClose={() => setResultModal(null)} />}
    </div>
  );
}

// Helper: convert tailwind color name to hex for inline style fallback
function colorToHex(color) {
  const map = {
    'green-500': '#22c55e', 'pink-500': '#ec4899', 'sky-500': '#0ea5e9',
    'blue-500': '#3b82f6', 'purple-500': '#a855f7', 'red-500': '#ef4444',
    'orange-500': '#f97316', 'yellow-500': '#eab308', 'teal-500': '#14b8a6',
    'indigo-500': '#6366f1', 'gray-500': '#6b7280',
  };
  return map[color] || '#6b7280';
}
