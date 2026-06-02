import { useState } from 'react';
import { AlertTriangle, X, Loader2, Key } from 'lucide-react';

/**
 * Modal that requires API key confirmation before destructive actions.
 * Used by Client (API Key) login to confirm deletions.
 */
export default function ConfirmDeleteModal({ isOpen, onClose, onConfirm, title, description, loading, skipApiKey = false }) {
  const [apiKey, setApiKey] = useState('');
  const [error, setError] = useState('');

  if (!isOpen) return null;

  async function handleConfirm() {
    if (skipApiKey) {
      setError('');
      try {
        await onConfirm('__admin_override__');
      } catch (err) {
        setError(err.message || 'Error al eliminar');
      }
      return;
    }
    if (!apiKey.trim()) {
      setError('Ingresa tu API Key para confirmar');
      return;
    }
    setError('');
    try {
      await onConfirm(apiKey.trim());
    } catch (err) {
      setError(err.message || 'API Key inválida');
    }
  }

  function handleClose() {
    setApiKey('');
    setError('');
    onClose();
  }

  return (
    <div role="dialog" aria-modal="true" aria-label="Confirmar eliminación" className="fixed inset-0 z-50 flex items-center justify-center p-4" onClick={handleClose}>
      <div className="absolute inset-0 bg-black/50 backdrop-blur-sm" />
      <div
        className="relative bg-white dark:bg-slate-800 rounded-xl shadow-2xl w-full max-w-md p-6 animate-fadein"
        onClick={e => e.stopPropagation()}
      >
        <button
          onClick={handleClose}
          className="absolute top-3 right-3 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
        >
          <X size={18} />
        </button>

        <div className="flex items-center gap-3 mb-4">
          <div className="w-10 h-10 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
            <AlertTriangle size={20} className="text-red-600 dark:text-red-400" />
          </div>
          <div>
            <h3 className="font-semibold text-gray-900 dark:text-white">{title || 'Confirmar eliminación'}</h3>
            <p className="text-sm text-gray-500 dark:text-gray-400">{description || 'Esta acción no se puede deshacer.'}</p>
          </div>
        </div>

        {!skipApiKey && (
          <div className="mb-4">
            <label htmlFor="confirmdeletemodal-fld1" className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
              <Key size={14} className="inline mr-1" />
              Ingresa tu API Key para confirmar
            </label>
            <input id="confirmdeletemodal-fld1"
              type="password"
              value={apiKey}
              onChange={e => setApiKey(e.target.value)}
              onKeyDown={e => e.key === 'Enter' && handleConfirm()}
              placeholder="Tu API Key de cliente..."
              className="w-full px-3 py-2.5 border border-gray-300 dark:border-slate-600 rounded-lg text-sm bg-white dark:bg-slate-700 text-gray-900 dark:text-white focus:ring-2 focus:ring-red-500 focus:border-transparent"
              autoFocus
            />
            {error && (
              <p className="text-red-500 text-xs mt-1.5">{error}</p>
            )}
          </div>
        )}
        {skipApiKey && error && (
          <p className="text-red-500 text-xs mb-4">{error}</p>
        )}

        <div className="flex justify-end gap-2">
          <button
            onClick={handleClose}
            className="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-100 dark:bg-slate-700 rounded-lg hover:bg-gray-200 dark:hover:bg-slate-600 transition-colors"
          >
            Cancelar
          </button>
          <button
            onClick={handleConfirm}
            disabled={loading || (!skipApiKey && !apiKey.trim())}
            className="px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 disabled:bg-gray-300 dark:disabled:bg-slate-600 transition-colors flex items-center gap-1.5"
          >
            {loading ? <Loader2 size={14} className="animate-spin" /> : <AlertTriangle size={14} />}
            Eliminar
          </button>
        </div>
      </div>
    </div>
  );
}
