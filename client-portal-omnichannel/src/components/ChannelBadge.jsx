export default function ChannelBadge({ type, size = 'sm' }) {
  const config = {
    whatsapp:  { bg: 'bg-channel-whatsapp', label: 'WhatsApp', icon: '💬' },
    instagram: { bg: 'bg-channel-instagram', label: 'Instagram', icon: '📸' },
    telegram:  { bg: 'bg-channel-telegram', label: 'Telegram', icon: '✈️' },
    messenger: { bg: 'bg-channel-messenger', label: 'Messenger', icon: '💬' },
  };
  const ch = config[type] || config.whatsapp;
  const sizeClass = size === 'xs' ? 'text-[10px] px-1.5 py-0.5' : 'text-xs px-2 py-0.5';

  return (
    <span className={`inline-flex items-center gap-1 rounded-full font-medium ${ch.bg} ${sizeClass}`}>
      <span>{ch.icon}</span>
      {size !== 'xs' && ch.label}
    </span>
  );
}
