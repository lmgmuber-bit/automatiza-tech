import { useState } from 'react';
import { CheckCircle, AlertTriangle, XCircle, Copy, Check, X, ShieldAlert } from 'lucide-react';

/**
 * ResultModal — Modal elegante para mostrar resultados, confirmaciones y errores.
 * Mobile-ready, dark mode, con opción de copiar textos.
 * 
 * Props:
 *   type: 'success' | 'error' | 'warning' | 'confirm'
 *   title: string
 *   message: string (texto principal)
 *   detail: string (texto secundario copiable, ej: API Key)
 *   detailLabel: string (etiqueta del detail)
 *   onClose: () => void
 *   onConfirm: () => void (solo para type='confirm')
 *   confirmText: string (default: 'Confirmar')
 *   cancelText: string (default: 'Cancelar')
 */
export default function ResultModal({ type = 'success', title, message, detail, detailLabel, onClose, onConfirm, confirmText = 'Confirmar', cancelText = 'Cancelar' }) {
  const [copied, setCopied] = useState(false);

  const icons = {
    success: <CheckCircle size={40} className="text-green-500" />,
    error: <XCircle size={40} className="text-red-500" />,
    warning: <AlertTriangle size={40} className="text-amber-500" />,
    confirm: <ShieldAlert size={40} className="text-amber-500" />,
  };

  const bgColors = {
    success: 'from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20',
    error: 'from-red-50 to-rose-50 dark:from-red-900/20 dark:to-rose-900/20',
    warning: 'from-amber-50 to-yellow-50 dark:from-amber-900/20 dark:to-yellow-900/20',
    confirm: 'from-amber-50 to-orange-50 dark:from-amber-900/20 dark:to-orange-900/20',
  };

  function handleCopy() {
    if (detail) {
      navigator.clipboard.writeText(detail).then(() => {
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
      });
    }
  }

  return (
    <div className="fixed inset-0 z-[60] flex items-center justify-center p-4" onClick={type !== 'confirm' ? onClose : undefined}>
      <div className="absolute inset-0 bg-black/50 backdrop-blur-sm" />
      <div
        className="relative bg-white dark:bg-slate-800 rounded-2xl shadow-2xl w-full max-w-sm max-h-[90vh] overflow-hidden animate-fadein flex flex-col"
        onClick={e => e.stopPropagation()}
      >
        {/* Icon header */}
        <div className={`bg-gradient-to-br ${bgColors[type]} flex justify-center py-6`}>
          {icons[type]}
        </div>

        {/* Content */}
        <div className="px-6 py-5 text-center overflow-y-auto flex-1">
          {title && <h3 className="text-lg font-bold text-gray-900 dark:text-white mb-2">{title}</h3>}
          {message && <p className="text-sm text-gray-600 dark:text-gray-300 mb-3 whitespace-pre-line">{message}</p>}

          {/* Copiable detail (e.g. API Key) */}
          {detail && (
            <div className="bg-gray-50 dark:bg-slate-700 border border-gray-200 dark:border-slate-600 rounded-xl p-3 mt-3">
              {detailLabel && <p className="text-[10px] uppercase tracking-wider text-gray-400 dark:text-gray-500 font-semibold mb-1">{detailLabel}</p>}
              <div className="flex items-center gap-2">
                <code className="flex-1 text-xs text-gray-800 dark:text-gray-200 break-all font-mono select-all">{detail}</code>
                <button
                  onClick={handleCopy}
                  className="p-1.5 rounded-lg hover:bg-gray-200 dark:hover:bg-slate-600 text-gray-500 dark:text-gray-400 transition-colors shrink-0"
                  title="Copiar"
                >
                  {copied ? <Check size={14} className="text-green-500" /> : <Copy size={14} />}
                </button>
              </div>
              {copied && <p className="text-[10px] text-green-600 dark:text-green-400 mt-1">¡Copiado al portapapeles!</p>}
            </div>
          )}
        </div>

        {/* Actions */}
        <div className="px-6 pb-5 flex gap-2">
          {type === 'confirm' ? (
            <>
              <button
                onClick={onClose}
                className="flex-1 px-4 py-2.5 bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-300 rounded-xl text-sm font-medium hover:bg-gray-200 dark:hover:bg-slate-600 transition-colors"
              >
                {cancelText}
              </button>
              <button
                onClick={onConfirm}
                className="flex-1 px-4 py-2.5 bg-red-600 text-white rounded-xl text-sm font-medium hover:bg-red-700 transition-colors"
              >
                {confirmText}
              </button>
            </>
          ) : (
            <button
              onClick={onClose}
              className="w-full px-4 py-2.5 bg-blue-600 text-white rounded-xl text-sm font-medium hover:bg-blue-700 transition-colors"
            >
              Aceptar
            </button>
          )}
        </div>
      </div>
    </div>
  );
}
