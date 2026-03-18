import { AlertTriangle, X, Clock } from 'lucide-react';

export default function ExpiryWarningModal({ warning, onClose }) {
  if (!warning || !warning.warning) return null;

  const isExpired = warning.expired;
  const days = warning.days_remaining;
  const periodEnd = warning.period_end;

  return (
    <div className="fixed inset-0 z-[9999] flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
      <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl max-w-md w-full overflow-hidden animate-in fade-in">
        {/* Header */}
        <div className={`px-6 py-4 ${isExpired ? 'bg-gradient-to-r from-red-500 to-red-600' : 'bg-gradient-to-r from-amber-400 to-orange-500'}`}>
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-3 text-white">
              <div className="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center">
                {isExpired ? <AlertTriangle size={22} /> : <Clock size={22} />}
              </div>
              <div>
                <h3 className="font-bold text-lg">
                  {isExpired ? 'Período Expirado' : 'Período por Vencer'}
                </h3>
                <p className="text-sm text-white/80">
                  {isExpired ? 'Tu acceso ha sido suspendido' : `Quedan ${days} día${days !== 1 ? 's' : ''}`}
                </p>
              </div>
            </div>
            {!isExpired && (
              <button onClick={onClose} className="text-white/70 hover:text-white p-1 rounded-lg hover:bg-white/10">
                <X size={20} />
              </button>
            )}
          </div>
        </div>

        {/* Body */}
        <div className="px-6 py-5 space-y-4">
          <p className="text-sm text-gray-700 dark:text-gray-300">
            {warning.message}
          </p>

          {periodEnd && (
            <div className="bg-gray-50 dark:bg-slate-700 rounded-lg p-3 flex items-center justify-between">
              <span className="text-xs text-gray-500 dark:text-gray-400">Fecha de vencimiento</span>
              <span className={`text-sm font-semibold ${isExpired ? 'text-red-600 dark:text-red-400' : 'text-amber-600 dark:text-amber-400'}`}>
                {new Date(periodEnd + 'T12:00:00').toLocaleDateString('es-CL', { day: 'numeric', month: 'long', year: 'numeric' })}
              </span>
            </div>
          )}

          {isExpired ? (
            <div className="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-3">
              <p className="text-xs text-red-700 dark:text-red-300">
                Contacta al administrador del sistema para renovar tu período de servicio y restaurar el acceso.
              </p>
            </div>
          ) : (
            <div className="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-3">
              <p className="text-xs text-amber-700 dark:text-amber-300">
                Te recomendamos contactar al administrador para renovar tu período antes de que expire y evitar interrupciones en el servicio.
              </p>
            </div>
          )}
        </div>

        {/* Footer */}
        <div className="px-6 py-4 border-t border-gray-100 dark:border-slate-700 flex justify-end">
          {isExpired ? (
            <button
              onClick={() => window.location.reload()}
              className="px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-medium hover:bg-red-700"
            >
              Cerrar Sesión
            </button>
          ) : (
            <button
              onClick={onClose}
              className="px-4 py-2 bg-amber-500 text-white rounded-lg text-sm font-medium hover:bg-amber-600"
            >
              Entendido
            </button>
          )}
        </div>
      </div>
    </div>
  );
}
