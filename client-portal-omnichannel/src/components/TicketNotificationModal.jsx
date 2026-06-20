import { useState, useEffect } from 'react';
import { LifeBuoy, X, AlertCircle } from 'lucide-react';
import { getOpenTicketCount } from '../api';

export default function TicketNotificationModal({ onNavigateToSupport }) {
  const [show, setShow] = useState(false);
  const [count, setCount] = useState(0);

  useEffect(() => {
    let cancelled = false;
    async function check() {
      try {
        const data = await getOpenTicketCount();
        if (!cancelled && data.count > 0) {
          setCount(data.count);
          setShow(true);
        }
      } catch {}
    }
    // Check on mount (after admin login)
    check();
    return () => { cancelled = true; };
  }, []);

  if (!show || count === 0) return null;

  return (
    <div role="dialog" aria-modal="true" aria-label="Notificación de ticket" className="fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4" onClick={() => setShow(false)}>
      <div className="bg-white dark:bg-slate-800 rounded-xl shadow-2xl max-w-sm w-full animate-in zoom-in" onClick={e => e.stopPropagation()}>
        <div className="p-5 text-center">
          <div className="w-14 h-14 mx-auto mb-3 rounded-full bg-red-100 dark:bg-red-900/30 flex items-center justify-center">
            <LifeBuoy size={28} className="text-red-600 dark:text-red-400" />
          </div>
          <h3 className="text-lg font-bold text-gray-900 dark:text-white mb-1">Tickets Abiertos</h3>
          <p className="text-sm text-gray-500 dark:text-gray-400 mb-4">
            Tienes <span className="font-bold text-red-600 dark:text-red-400 text-lg">{count}</span> ticket{count > 1 ? 's' : ''} abierto{count > 1 ? 's' : ''} pendiente{count > 1 ? 's' : ''} de atención.
          </p>
          <div className="flex gap-2 justify-center">
            <button
              onClick={() => setShow(false)}
              className="px-4 py-2 text-sm bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200"
            >
              Cerrar
            </button>
            <button
              onClick={() => { setShow(false); onNavigateToSupport?.(); }}
              className="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 flex items-center gap-1.5"
            >
              <LifeBuoy size={14} /> Ver Tickets
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
