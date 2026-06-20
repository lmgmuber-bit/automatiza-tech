import { useState, useEffect } from 'react';
import { MessageSquare, X, ArrowRight, Clock, User } from 'lucide-react';
import { getConversations } from '../api';
import ChannelBadge from './ChannelBadge';

function timeAgo(dateStr) {
  if (!dateStr) return '';
  const diff = Date.now() - new Date(dateStr).getTime();
  const mins = Math.floor(diff / 60000);
  if (mins < 1) return 'ahora';
  if (mins < 60) return `${mins}m`;
  const hrs = Math.floor(mins / 60);
  if (hrs < 24) return `${hrs}h`;
  const days = Math.floor(hrs / 24);
  return `${days}d`;
}

export default function AssignedChatsModal({ onNavigateToInbox }) {
  const [show, setShow] = useState(false);
  const [chats, setChats] = useState([]);

  useEffect(() => {
    let cancelled = false;
    async function check() {
      try {
        const data = await getConversations({ scope: 'mine', status: 'assigned' });
        const list = data.data || [];
        if (!cancelled && list.length > 0) {
          setChats(list);
          setShow(true);
        }
      } catch {}
    }
    check();
    return () => { cancelled = true; };
  }, []);

  if (!show || chats.length === 0) return null;

  return (
    <div role="dialog" aria-modal="true" aria-label="Chats asignados" className="fixed inset-0 bg-black/40 z-[60] flex items-center justify-center p-4" onClick={() => setShow(false)}>
      <div className="bg-white dark:bg-slate-800 rounded-2xl shadow-2xl max-w-md w-full animate-in zoom-in max-h-[80vh] flex flex-col" onClick={e => e.stopPropagation()}>
        {/* Header */}
        <div className="flex items-center justify-between px-5 py-4 border-b border-slate-200 dark:border-slate-700 shrink-0">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-900/30 flex items-center justify-center">
              <MessageSquare size={20} className="text-blue-600 dark:text-blue-400" />
            </div>
            <div>
              <h3 className="text-base font-bold text-gray-900 dark:text-white">Chats Asignados</h3>
              <p className="text-xs text-gray-500 dark:text-gray-400">
                Tienes <span className="font-semibold text-blue-600 dark:text-blue-400">{chats.length}</span> conversación{chats.length > 1 ? 'es' : ''} activa{chats.length > 1 ? 's' : ''}
              </p>
            </div>
          </div>
          <button onClick={() => setShow(false)} className="p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 text-slate-400">
            <X size={18} />
          </button>
        </div>

        {/* Chat list */}
        <div className="overflow-y-auto flex-1 divide-y divide-slate-100 dark:divide-slate-700">
          {chats.map(chat => (
            <div
              key={chat.id}
              className="px-5 py-3 hover:bg-slate-50 dark:hover:bg-slate-700/50 transition-colors cursor-pointer flex items-center gap-3"
              onClick={() => { setShow(false); onNavigateToInbox?.(); }}
            >
              {/* Avatar */}
              <div className="w-10 h-10 rounded-full bg-gradient-to-br from-emerald-400 to-teal-500 flex items-center justify-center text-white font-bold text-sm shrink-0">
                {(chat.contact_name || '?')[0].toUpperCase()}
              </div>

              {/* Info */}
              <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2 mb-0.5">
                  <span className="font-semibold text-sm text-gray-900 dark:text-white truncate">
                    {chat.contact_name || chat.contact_phone || 'Cliente'}
                  </span>
                  <ChannelBadge type={chat.channel_type} />
                </div>
                {chat.contact_phone && (
                  <p className="text-[11px] text-gray-400 dark:text-gray-500 mb-0.5">📱 {chat.contact_phone}</p>
                )}
                <p className="text-xs text-gray-500 dark:text-gray-400 truncate">
                  {chat.last_message_preview || 'Sin mensajes'}
                </p>
              </div>

              {/* Time + Arrow */}
              <div className="flex flex-col items-end gap-1 shrink-0">
                {chat.last_message_at && (
                  <span className="text-[10px] text-gray-400 flex items-center gap-0.5">
                    <Clock size={10} /> {timeAgo(chat.last_message_at)}
                  </span>
                )}
                {chat.unread_count > 0 && (
                  <span className="w-5 h-5 rounded-full bg-blue-600 text-white text-[10px] font-bold flex items-center justify-center">
                    {chat.unread_count > 9 ? '9+' : chat.unread_count}
                  </span>
                )}
                <ArrowRight size={14} className="text-gray-300 dark:text-gray-600" />
              </div>
            </div>
          ))}
        </div>

        {/* Footer */}
        <div className="px-5 py-3 border-t border-slate-200 dark:border-slate-700 flex gap-2 shrink-0">
          <button
            onClick={() => setShow(false)}
            className="flex-1 px-4 py-2 text-sm bg-gray-100 dark:bg-slate-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-slate-600 font-medium"
          >
            Cerrar
          </button>
          <button
            onClick={() => { setShow(false); onNavigateToInbox?.(); }}
            className="flex-1 px-4 py-2 text-sm bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 flex items-center justify-center gap-1.5"
          >
            <MessageSquare size={14} /> Ir a Bandeja
          </button>
        </div>
      </div>
    </div>
  );
}
