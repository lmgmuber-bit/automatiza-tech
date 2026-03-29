import { getChannelIcon } from './ChannelIcons';

export default function ChannelBadge({ type, size = 'sm' }) {
  const config = {
    whatsapp:  { bg: 'bg-channel-whatsapp', label: 'WhatsApp' },
    instagram: { bg: 'bg-channel-instagram', label: 'Instagram' },
    telegram:  { bg: 'bg-channel-telegram', label: 'Telegram' },
    messenger: { bg: 'bg-channel-messenger', label: 'Messenger' },
    email:     { bg: 'bg-channel-email', label: 'Email' },
  };
  const ch = config[type] || config.whatsapp;
  const sizeClass = size === 'xs' ? 'text-[10px] px-1.5 py-0.5' : 'text-xs px-2 py-0.5';
  const iconSize = size === 'xs' ? 12 : 14;

  return (
    <span className={`inline-flex items-center gap-1 rounded-full font-medium ${ch.bg} ${sizeClass}`}>
      <span className="flex items-center">{getChannelIcon(type, iconSize) || '📡'}</span>
      {size !== 'xs' && ch.label}
    </span>
  );
}
