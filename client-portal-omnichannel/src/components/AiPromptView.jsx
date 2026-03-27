import { useState, useEffect } from 'react';
import { Settings, Loader2, RotateCcw, Save, CheckCircle, Sparkles, Info } from 'lucide-react';
import { getAiPromptTemplate, saveAiPromptTemplate } from '../api';

export default function AiPromptView() {
  const [template, setTemplate] = useState('');
  const [defaultTemplate, setDefaultTemplate] = useState('');
  const [placeholders, setPlaceholders] = useState([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [saved, setSaved] = useState(false);
  const [error, setError] = useState(null);

  useEffect(() => {
    setLoading(true);
    getAiPromptTemplate()
      .then(data => {
        setTemplate(data.template || '');
        setDefaultTemplate(data.default || '');
        setPlaceholders(data.placeholders || []);
      })
      .catch(err => setError(err.message))
      .finally(() => setLoading(false));
  }, []);

  async function handleSave() {
    setSaving(true);
    setError(null);
    setSaved(false);
    try {
      const result = await saveAiPromptTemplate(template);
      if (result.error) throw new Error(result.error);
      setSaved(true);
      setTimeout(() => setSaved(false), 4000);
    } catch (err) {
      setError(err.message);
    } finally {
      setSaving(false);
    }
  }

  function handleReset() {
    setTemplate(defaultTemplate);
    setSaved(false);
  }

  if (loading) {
    return (
      <div className="flex-1 flex items-center justify-center">
        <Loader2 className="w-8 h-8 animate-spin text-indigo-500" />
      </div>
    );
  }

  return (
    <div className="flex-1 flex flex-col overflow-hidden p-4 sm:p-6 gap-4">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center gap-3">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 flex items-center justify-center text-white shrink-0">
            <Sparkles className="w-5 h-5" />
          </div>
          <div>
            <h1 className="text-lg font-bold text-gray-900 dark:text-white">System Prompt — Omni Asistente IA</h1>
            <p className="text-xs text-gray-500 dark:text-gray-400">Configura las instrucciones del asistente. Solo visible para Super Admin.</p>
          </div>
        </div>
        <div className="sm:ml-auto flex items-center gap-2">
          <button
            onClick={handleReset}
            className="flex items-center gap-1.5 px-3 py-2 rounded-lg text-xs font-medium text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-slate-700 hover:bg-gray-200 dark:hover:bg-slate-600 transition-colors"
          >
            <RotateCcw className="w-3.5 h-3.5" />
            Restaurar default
          </button>
          <button
            onClick={handleSave}
            disabled={saving}
            className="flex items-center gap-1.5 px-4 py-2 rounded-lg text-xs font-semibold text-white bg-indigo-500 hover:bg-indigo-600 disabled:bg-indigo-300 transition-colors"
          >
            {saving ? <Loader2 className="w-3.5 h-3.5 animate-spin" /> : <Save className="w-3.5 h-3.5" />}
            {saving ? 'Guardando...' : 'Guardar Prompt'}
          </button>
        </div>
      </div>

      {/* Success / Error */}
      {saved && (
        <div className="flex items-center gap-2 text-green-700 dark:text-green-400 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg px-4 py-2.5 text-sm">
          <CheckCircle className="w-4 h-4 shrink-0" />
          <span>Prompt guardado exitosamente. Los cambios aplican a todas las conversaciones nuevas.</span>
        </div>
      )}
      {error && (
        <div className="text-sm text-red-600 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg px-4 py-2.5">
          {error}
        </div>
      )}

      {/* Info panel */}
      <div className="bg-indigo-50 dark:bg-indigo-900/15 border border-indigo-200 dark:border-indigo-800/50 rounded-xl px-4 py-3 shrink-0">
        <div className="flex items-start gap-2">
          <Info className="w-4 h-4 text-indigo-500 mt-0.5 shrink-0" />
          <div>
            <p className="text-xs font-semibold text-indigo-700 dark:text-indigo-300 mb-1.5">Placeholders disponibles (se reemplazan automáticamente por cada usuario):</p>
            <div className="flex flex-wrap gap-1.5 mb-2">
              {placeholders.map(p => (
                <code key={p} className="text-[11px] px-2 py-0.5 rounded-md bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300 font-mono">{p}</code>
              ))}
            </div>
            <p className="text-[11px] text-indigo-600/70 dark:text-indigo-400/70 leading-relaxed">
              El contexto de datos reales (canales, agentes, conversaciones, tickets, historial de transferencias) se agrega automáticamente debajo de estas instrucciones. No necesitas incluirlo aquí.
            </p>
          </div>
        </div>
      </div>

      {/* Editor */}
      <div className="flex-1 min-h-0">
        <textarea
          value={template}
          onChange={e => { setTemplate(e.target.value); setSaved(false); }}
          className="w-full h-full resize-none rounded-xl border border-gray-300 dark:border-slate-600 bg-white dark:bg-slate-900 px-4 py-3 text-[13px] font-mono text-gray-800 dark:text-gray-200 leading-relaxed focus:outline-none focus:ring-2 focus:ring-indigo-400 focus:border-transparent"
          placeholder="Escribe las instrucciones del system prompt aquí..."
          spellCheck={false}
        />
      </div>
    </div>
  );
}
