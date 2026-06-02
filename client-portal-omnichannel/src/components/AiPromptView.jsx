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
    <div className="flex-1 flex flex-col overflow-hidden">
      {/* HERO FULL-BLEED */}
      <div className="relative overflow-hidden px-6 py-6 text-white shadow-lg" style={{ backgroundImage: 'linear-gradient(120deg, #d946ef, #9333ea)' }}>
        <div className="absolute -top-12 -right-8 w-52 h-52 rounded-full blur-3xl bg-white/20" />
        <div className="absolute inset-0 opacity-[0.06]" style={{ backgroundImage: 'radial-gradient(white 1px, transparent 1px)', backgroundSize: '20px 20px' }} />
        <div className="relative flex flex-wrap items-center justify-between gap-3">
          <div className="flex items-center gap-3">
            <div className="w-12 h-12 rounded-2xl bg-white/15 backdrop-blur-sm ring-1 ring-white/25 flex items-center justify-center">
              <Sparkles size={26} />
            </div>
            <div>
              <h1 className="text-2xl font-bold tracking-tight" style={{ fontFamily: 'Poppins, sans-serif' }}>System Prompt — Omni Asistente IA</h1>
              <p className="text-sm text-white/75">Configura las instrucciones del asistente. Solo visible para Super Admin.</p>
            </div>
          </div>
          <div className="flex items-center gap-2">
            <button
              onClick={handleReset}
              className="flex items-center gap-1.5 px-3.5 py-2 rounded-lg text-sm font-medium bg-white/15 hover:bg-white/25 ring-1 ring-white/25 text-white backdrop-blur-sm transition-colors"
              style={{ fontFamily: 'Poppins, sans-serif' }}
            >
              <RotateCcw className="w-4 h-4" />
              Restaurar default
            </button>
            <button
              onClick={handleSave}
              disabled={saving}
              className="flex items-center gap-1.5 px-4 py-2 rounded-lg text-sm font-semibold bg-white/15 hover:bg-white/25 ring-1 ring-white/25 text-white backdrop-blur-sm disabled:opacity-60 transition-all duration-300"
              style={{ fontFamily: 'Poppins, sans-serif' }}
            >
              {saving ? <Loader2 className="w-4 h-4 animate-spin" /> : <Save className="w-4 h-4" />}
              {saving ? 'Guardando...' : 'Guardar Prompt'}
            </button>
          </div>
        </div>
      </div>

      {/* CONTENIDO */}
      <div className="flex-1 min-h-0 flex flex-col p-4 sm:p-6 gap-4">
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
      <div className="bg-white dark:bg-slate-800 rounded-2xl p-4 md:p-5 shadow-md ring-1 ring-gray-100 dark:ring-slate-700/60 hover:shadow-xl transition-all duration-300 shrink-0">
        <div className="flex items-start gap-3">
          <div className="w-9 h-9 rounded-2xl flex items-center justify-center bg-gradient-to-br from-sky-400 to-blue-600 text-white shadow-md shrink-0">
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
      <div className="flex-1 min-h-0 bg-white dark:bg-slate-800 rounded-2xl p-1.5 shadow-md ring-1 ring-gray-100 dark:ring-slate-700/60 hover:shadow-xl transition-all duration-300">
        <textarea
          value={template}
          onChange={e => { setTemplate(e.target.value); setSaved(false); }}
          className="w-full h-full resize-none rounded-xl ring-1 ring-gray-200 dark:ring-slate-700 bg-gray-50/60 dark:bg-slate-900 px-4 py-3 text-[13px] font-mono text-gray-800 dark:text-slate-200 leading-relaxed focus:outline-none focus:ring-2 focus:ring-blue-500"
          placeholder="Escribe las instrucciones del system prompt aquí..."
          spellCheck={false}
        />
      </div>
      </div>
    </div>
  );
}
