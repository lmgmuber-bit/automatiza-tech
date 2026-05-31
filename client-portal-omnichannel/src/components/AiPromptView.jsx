import { useState, useEffect } from 'react';
import { Settings, Loader2, RotateCcw, Save, CheckCircle, Sparkles, Info, AlertCircle } from 'lucide-react';
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
        <Loader2 className="w-8 h-8 animate-spin text-sky-500" />
      </div>
    );
  }

  return (
    <div className="flex-1 flex flex-col overflow-hidden p-4 sm:p-6 gap-4">
      {/* Header */}
      <div className="flex flex-col sm:flex-row sm:items-center gap-3">
        <div className="flex items-center gap-3 mb-1">
          <div className="w-11 h-11 rounded-xl flex items-center justify-center ring-1 bg-violet-50 text-violet-600 ring-violet-200 dark:bg-violet-500/10 dark:text-violet-300 dark:ring-violet-500/20 shrink-0">
            <Sparkles size={22} />
          </div>
          <div>
            <h1 className="text-2xl font-bold tracking-tight text-gray-900 dark:text-white" style={{ fontFamily: 'Poppins, sans-serif' }}>System Prompt — Omni Asistente IA</h1>
            <p className="text-sm text-gray-500 dark:text-slate-400">Configura las instrucciones del asistente. Solo visible para Super Admin.</p>
          </div>
        </div>
        <div className="sm:ml-auto flex items-center gap-2">
          <button
            onClick={handleReset}
            className="flex items-center gap-1.5 px-3.5 py-2 rounded-lg text-sm font-medium text-gray-600 dark:text-slate-300 bg-gray-100 dark:bg-slate-700 hover:bg-gray-200 dark:hover:bg-slate-600 ring-1 ring-gray-200 dark:ring-slate-600 transition-colors"
            style={{ fontFamily: 'Poppins, sans-serif' }}
          >
            <RotateCcw className="w-4 h-4" />
            Restaurar default
          </button>
          <button
            onClick={handleSave}
            disabled={saving}
            className="flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-semibold text-white bg-blue-600 hover:bg-blue-700 disabled:bg-blue-400 shadow-sm transition-colors"
            style={{ fontFamily: 'Poppins, sans-serif' }}
          >
            {saving ? <Loader2 className="w-4 h-4 animate-spin" /> : <Save className="w-4 h-4" />}
            {saving ? 'Guardando...' : 'Guardar Prompt'}
          </button>
        </div>
      </div>

      {/* Success / Error */}
      {saved && (
        <div className="flex items-center gap-2 text-emerald-700 dark:text-emerald-300 bg-emerald-50 dark:bg-emerald-500/10 ring-1 ring-emerald-200 dark:ring-emerald-500/20 rounded-xl px-4 py-2.5 text-sm">
          <CheckCircle className="w-4 h-4 shrink-0" />
          <span>Prompt guardado exitosamente. Los cambios aplican a todas las conversaciones nuevas.</span>
        </div>
      )}
      {error && (
        <div className="flex items-center gap-2 text-sm text-rose-700 dark:text-rose-300 bg-rose-50 dark:bg-rose-500/10 ring-1 ring-rose-200 dark:ring-rose-500/20 rounded-xl px-4 py-2.5">
          <AlertCircle className="w-4 h-4 shrink-0" />
          <span>{error}</span>
        </div>
      )}

      {/* Info panel */}
      <div className="bg-white dark:bg-slate-800 rounded-2xl p-4 md:p-5 shadow-sm ring-1 ring-gray-100 dark:ring-slate-700/60 shrink-0">
        <div className="flex items-start gap-3">
          <div className="w-9 h-9 rounded-lg flex items-center justify-center ring-1 bg-sky-50 text-sky-600 ring-sky-200 dark:bg-sky-500/10 dark:text-sky-300 dark:ring-sky-500/20 shrink-0">
            <Info className="w-4 h-4" />
          </div>
          <div>
            <p className="text-sm font-semibold text-gray-900 dark:text-white mb-2" style={{ fontFamily: 'Poppins, sans-serif' }}>Placeholders disponibles <span className="font-normal text-gray-500 dark:text-slate-400">(se reemplazan automáticamente por cada usuario)</span></p>
            <div className="flex flex-wrap gap-1.5 mb-2.5">
              {placeholders.map(p => (
                <code key={p} className="text-[11px] px-2 py-1 rounded-md bg-sky-50 dark:bg-sky-500/10 text-sky-700 dark:text-sky-300 ring-1 ring-sky-200 dark:ring-sky-500/20 font-mono">{p}</code>
              ))}
            </div>
            <p className="text-xs text-gray-500 dark:text-slate-400 leading-relaxed">
              El contexto de datos reales (canales, agentes, conversaciones, tickets, historial de transferencias) se agrega automáticamente debajo de estas instrucciones. No necesitas incluirlo aquí.
            </p>
          </div>
        </div>
      </div>

      {/* Editor */}
      <div className="flex-1 min-h-0 bg-white dark:bg-slate-800 rounded-2xl p-1.5 shadow-sm ring-1 ring-gray-100 dark:ring-slate-700/60">
        <textarea
          value={template}
          onChange={e => { setTemplate(e.target.value); setSaved(false); }}
          className="w-full h-full resize-none rounded-xl ring-1 ring-gray-200 dark:ring-slate-700 bg-gray-50/60 dark:bg-slate-900 px-4 py-3 text-[13px] font-mono text-gray-800 dark:text-slate-200 leading-relaxed focus:outline-none focus:ring-2 focus:ring-blue-500"
          placeholder="Escribe las instrucciones del system prompt aquí..."
          spellCheck={false}
        />
      </div>
    </div>
  );
}
