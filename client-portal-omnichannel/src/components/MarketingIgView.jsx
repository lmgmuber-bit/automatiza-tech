import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  Instagram, Eye, Users, Heart, Activity, RefreshCw, AlertTriangle, Info,
  CheckCircle2, XCircle, ExternalLink, Film, Image as ImageIcon, Layers, Clock, Table, BarChart3,
} from 'lucide-react';
import { getIgInsights, syncIgInsights, formatChileDateTime } from '../api';

/*
 * Marketing IG — métricas reales de Instagram de @automatizatech.cl.
 * Fuente: api-omnichannel.php → admin/ig-insights (tomas guardadas por
 * omnichannel-ig-insights-controller.php; el cron de n8n toma cada hora).
 * Si falta el permiso de insights o no hay tomas, se dice en pantalla: nunca
 * se muestran cifras de ejemplo.
 */

const PERIODS = [
  { days: 7, label: '7 días' },
  { days: 30, label: '30 días' },
  { days: 90, label: '90 días' },
];

const CHART_METRICS = [
  { key: 'views', label: 'Visualizaciones' },
  { key: 'reach', label: 'Alcance' },
  { key: 'total_interactions', label: 'Interacciones' },
  { key: 'accounts_engaged', label: 'Cuentas que interactuaron' },
];

const CHECKPOINTS = [
  { key: '24h', hours: 24 },
  { key: '48h', hours: 48 },
  { key: '7d', hours: 168 },
  { key: '30d', hours: 720 },
];
// Igual que CHECKPOINT_SLACK del backend: una toma fija se puede hacer hasta 1,5 veces su edad.
const CHECKPOINT_SLACK = 1.5;

const IG_GRADIENT = 'linear-gradient(135deg,#f09433 0%,#e6683c 25%,#dc2743 50%,#cc2366 75%,#bc1888 100%)';
const CARD = 'bg-white dark:bg-slate-800 rounded-2xl shadow-sm ring-1 ring-gray-100 dark:ring-slate-700/60';
const POPPINS = { fontFamily: 'Poppins, sans-serif' };

const fmt = n => (n === null || n === undefined ? '—' : Number(n).toLocaleString('es-CL'));
const fmtSeconds = ms => (ms === null || ms === undefined ? '—' : `${(ms / 1000).toLocaleString('es-CL', { maximumFractionDigits: 1 })} s`);
const fmtWhen = iso => (iso ? formatChileDateTime(iso, { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }) : '—');
const fmtDay = ymd => {
  const [y, m, d] = ymd.split('-').map(Number);
  return new Date(Date.UTC(y, m - 1, d, 12)).toLocaleDateString('es-CL', { day: '2-digit', month: 'short', timeZone: 'UTC' });
};

/** Todos los días del periodo, con null donde no hay toma (un hueco honesto en el gráfico). */
function fillDays(period, daily) {
  if (!period?.from || !period?.to) return [];
  const byDay = Object.fromEntries((daily || []).map(d => [d.day, d]));
  const out = [];
  const [y, m, d] = period.from.split('-').map(Number);
  const cur = new Date(Date.UTC(y, m - 1, d));
  const end = period.to;
  for (let i = 0; i < 120; i++) {
    const ymd = cur.toISOString().slice(0, 10);
    if (ymd > end) break;
    out.push(byDay[ymd] || { day: ymd });
    cur.setUTCDate(cur.getUTCDate() + 1);
  }
  return out;
}

const STATUS_STYLE = {
  ok:            { tone: 'emerald', Icon: CheckCircle2, title: 'Métricas al día' },
  never:         { tone: 'sky', Icon: Info, title: 'Aún no hay tomas de métricas' },
  no_permission: { tone: 'amber', Icon: AlertTriangle, title: 'Falta el permiso de métricas en el token de Instagram' },
  token_invalid: { tone: 'red', Icon: XCircle, title: 'El token de Instagram no sirve' },
  no_token:      { tone: 'red', Icon: XCircle, title: 'El canal de Instagram no tiene token' },
  error:         { tone: 'red', Icon: XCircle, title: 'La última toma falló' },
};
const TONES = {
  emerald: 'bg-emerald-50 text-emerald-800 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-200 dark:ring-emerald-500/30',
  sky:     'bg-sky-50 text-sky-800 ring-sky-200 dark:bg-sky-500/10 dark:text-sky-200 dark:ring-sky-500/30',
  amber:   'bg-amber-50 text-amber-900 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-200 dark:ring-amber-500/30',
  red:     'bg-red-50 text-red-800 ring-red-200 dark:bg-red-500/10 dark:text-red-200 dark:ring-red-500/30',
};

function StatusBanner({ status }) {
  const s = STATUS_STYLE[status?.status] || STATUS_STYLE.error;
  const { Icon } = s;
  if (status?.status === 'ok') {
    return (
      <p className="flex items-center gap-1.5 text-xs text-emerald-700 dark:text-emerald-300">
        <CheckCircle2 size={14} /> Métricas al día · última toma {fmtWhen(status.last_sync_at)}
      </p>
    );
  }
  return (
    <div className={`rounded-2xl p-4 ring-1 ${TONES[s.tone]}`} role="status">
      <div className="flex items-start gap-3">
        <Icon size={20} className="shrink-0 mt-0.5" />
        <div className="space-y-1 text-sm">
          <p className="font-bold">{s.title}</p>
          <p>{status?.message}</p>
          {status?.status === 'never' && (
            <p>Pulsa «Actualizar ahora» o espera la toma automática (cada hora).</p>
          )}
          {status?.status === 'no_permission' && (
            <p>
              Lo que sí se ve abajo (likes, comentarios, seguidores) es real. Para ver alcance y reproducciones hay que
              generar un token nuevo en Meta con el permiso <code className="font-mono text-xs">instagram_business_manage_insights</code> y
              pegarlo en Canales → Instagram.
            </p>
          )}
          {(status?.status === 'token_invalid' || status?.status === 'no_token') && (
            <p>Mientras tanto el publicador automático tampoco podrá publicar: renovar el token en Canales → Instagram.</p>
          )}
          {status?.last_errors?.length > 0 && status.status === 'error' && (
            <ul className="list-disc pl-5 text-xs opacity-90">
              {status.last_errors.map((e, i) => <li key={i}>{e}</li>)}
            </ul>
          )}
          {status?.last_sync_at && <p className="text-xs opacity-80">Última toma: {fmtWhen(status.last_sync_at)}</p>}
        </div>
      </div>
    </div>
  );
}

function KpiTile({ icon: Icon, label, value, sub }) {
  return (
    <div className={`${CARD} p-4`}>
      <div className="w-10 h-10 rounded-xl flex items-center justify-center ring-1 bg-pink-50 text-pink-600 ring-pink-200 dark:bg-pink-500/10 dark:text-pink-300 dark:ring-pink-500/20">
        <Icon size={20} />
      </div>
      <p className="mt-3 text-3xl font-bold text-gray-900 dark:text-white" style={POPPINS}>{value}</p>
      <p className="text-xs text-gray-500 dark:text-slate-400">{label}</p>
      {sub && <p className="mt-1 text-[11px] text-gray-400 dark:text-slate-500">{sub}</p>}
    </div>
  );
}

function DailyChart({ days, metric }) {
  const [hover, setHover] = useState(null);
  const values = days.map(d => (d[metric] === null || d[metric] === undefined ? null : d[metric]));
  const max = Math.max(0, ...values.filter(v => v !== null));
  const label = CHART_METRICS.find(m => m.key === metric)?.label || metric;
  if (max === 0 && values.every(v => v === null)) {
    return <p className="py-10 text-center text-sm text-gray-400 dark:text-slate-500">Sin datos de la cuenta en este periodo.</p>;
  }
  const tickIdx = days.length > 1 ? [0, Math.floor((days.length - 1) / 2), days.length - 1] : [0];
  return (
    <div>
      <div className="flex">
        <div className="w-12 shrink-0 flex flex-col justify-between text-[10px] text-gray-400 dark:text-slate-500 pr-2 text-right h-44">
          <span>{fmt(max)}</span>
          <span>0</span>
        </div>
        <div className="relative flex-1 h-44 border-b border-gray-300 dark:border-slate-600">
          <div className="absolute inset-x-0 top-0 border-t border-gray-100 dark:border-slate-700/60" />
          <div className="absolute inset-x-0 top-1/2 border-t border-gray-100 dark:border-slate-700/60" />
          <div className="relative h-full flex items-end gap-[2px]">
            {days.map((d, i) => {
              const v = values[i];
              const pct = v === null || max === 0 ? 0 : Math.max((v / max) * 100, v > 0 ? 1.5 : 0);
              return (
                <button
                  key={d.day}
                  type="button"
                  className="relative flex-1 h-full flex items-end focus:outline-none focus-visible:ring-2 focus-visible:ring-pink-400 rounded-sm"
                  onMouseEnter={() => setHover(i)}
                  onMouseLeave={() => setHover(null)}
                  onFocus={() => setHover(i)}
                  onBlur={() => setHover(null)}
                  aria-label={`${fmtDay(d.day)}: ${v === null ? 'sin dato' : fmt(v)} ${label.toLowerCase()}`}
                >
                  {v !== null && (
                    <span
                      className={`w-full rounded-t-[4px] bg-pink-600 dark:bg-pink-500 transition-opacity ${hover === i ? 'opacity-80' : ''}`}
                      style={{ height: `${pct}%` }}
                    />
                  )}
                  {hover === i && (
                    <span className="pointer-events-none absolute bottom-full left-1/2 -translate-x-1/2 mb-1 z-10 whitespace-nowrap rounded-lg bg-gray-900 dark:bg-slate-950 px-2 py-1 text-[11px] text-white shadow-lg">
                      <strong className="block text-sm">{v === null ? 'sin dato' : fmt(v)}</strong>
                      {fmtDay(d.day)}
                    </span>
                  )}
                </button>
              );
            })}
          </div>
        </div>
      </div>
      <div className="relative ml-12 h-5 text-[10px] text-gray-400 dark:text-slate-500">
        {tickIdx.map((ti, k) => (
          <span
            key={ti}
            className="absolute top-1"
            style={k === 0 ? { left: 0 } : k === tickIdx.length - 1 ? { right: 0 } : { left: `${((ti + 0.5) / days.length) * 100}%`, transform: 'translateX(-50%)' }}
          >
            {fmtDay(days[ti].day)}
          </span>
        ))}
      </div>
    </div>
  );
}

function DailyTable({ days }) {
  return (
    <div className="overflow-x-auto max-h-80 custom-scrollbar">
      <table className="w-full text-xs">
        <thead className="sticky top-0 bg-white dark:bg-slate-800 text-gray-500 dark:text-slate-400">
          <tr>
            <th className="text-left py-2 pr-3 font-semibold">Día</th>
            {CHART_METRICS.map(m => <th key={m.key} className="text-right py-2 px-2 font-semibold">{m.label}</th>)}
            <th className="text-right py-2 pl-2 font-semibold">Seguidores</th>
          </tr>
        </thead>
        <tbody className="text-gray-800 dark:text-slate-200">
          {[...days].reverse().map(d => (
            <tr key={d.day} className="border-t border-gray-100 dark:border-slate-700/60">
              <td className="py-1.5 pr-3">{fmtDay(d.day)}</td>
              {CHART_METRICS.map(m => <td key={m.key} className="text-right py-1.5 px-2 tabular-nums">{fmt(d[m.key])}</td>)}
              <td className="text-right py-1.5 pl-2 tabular-nums">{fmt(d.followers_count)}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function KindIcon({ kind, mediaType }) {
  if (kind === 'REELS' || kind === 'STORY') return <Film size={16} />;
  if (mediaType === 'CAROUSEL_ALBUM') return <Layers size={16} />;
  return <ImageIcon size={16} />;
}

const KIND_LABEL = { REELS: 'Reel', FEED: 'Publicación', STORY: 'Historia' };
const POST_STATUS = {
  published: { label: 'Publicado', cls: 'bg-green-100 text-green-700 dark:bg-green-500/15 dark:text-green-300' },
  deleted:   { label: 'Eliminado', cls: 'bg-gray-100 text-gray-600 dark:bg-slate-700 dark:text-slate-300' },
  expired:   { label: 'Historia vencida', cls: 'bg-gray-100 text-gray-600 dark:bg-slate-700 dark:text-slate-300' },
};
const INSIGHTS_STATE = {
  no_permission: 'sin permiso de métricas',
  token_invalid: 'token vencido',
  not_enough_viewers: 'Meta no da cifras: menos de 5 espectadores',
  pending: 'métricas pendientes',
  error: 'error al leer métricas',
};

function CheckpointChips({ post }) {
  const ageH = post.posted_at ? (Date.now() - new Date(post.posted_at).getTime()) / 3600000 : 0;
  if (post.kind === 'STORY') return <span className="text-[10px] text-gray-400 dark:text-slate-500">solo 24 h</span>;
  return (
    <div className="flex gap-1">
      {CHECKPOINTS.map(cp => {
        const snap = post.checkpoints?.[cp.key];
        if (snap) {
          return (
            <span
              key={cp.key}
              title={`A las ${cp.key}: ${fmt(snap.views)} visualizaciones · ${fmt(snap.reach)} alcance · ${fmt(snap.likes)} likes (toma ${fmtWhen(snap.taken_at)})`}
              className="px-1.5 py-0.5 rounded-md text-[10px] font-semibold bg-pink-50 text-pink-700 ring-1 ring-pink-200 dark:bg-pink-500/10 dark:text-pink-200 dark:ring-pink-500/30"
            >
              {cp.key} ✓
            </span>
          );
        }
        const pending = ageH <= cp.hours * CHECKPOINT_SLACK;
        return (
          <span
            key={cp.key}
            title={pending
              ? (ageH < cp.hours ? `La toma de ${cp.key} se hará cuando la publicación cumpla esa edad` : `La toma de ${cp.key} sigue pendiente (se puede hacer hasta las ${Math.round(cp.hours * CHECKPOINT_SLACK)} h)`)
              : `No se alcanzó a tomar a las ${cp.key}`}
            className="px-1.5 py-0.5 rounded-md text-[10px] font-medium text-gray-400 ring-1 ring-gray-200 dark:text-slate-500 dark:ring-slate-600"
          >
            {cp.key} {pending ? '…' : '—'}
          </span>
        );
      })}
    </div>
  );
}

function PostsTable({ posts, accountStatus }) {
  if (!posts?.length) {
    return <p className="py-8 text-center text-sm text-gray-400 dark:text-slate-500">Todavía no hay publicaciones registradas. Aparecen tras la primera toma.</p>;
  }
  return (
    <div className="overflow-x-auto custom-scrollbar">
      <table className="w-full text-xs min-w-[900px]">
        <thead className="text-gray-500 dark:text-slate-400">
          <tr>
            <th className="text-left py-2 pr-3 font-semibold">Publicación</th>
            <th className="text-left py-2 px-2 font-semibold">Estado</th>
            <th className="text-right py-2 px-2 font-semibold">Visualiz.</th>
            <th className="text-right py-2 px-2 font-semibold">Alcance</th>
            <th className="text-right py-2 px-2 font-semibold">Likes</th>
            <th className="text-right py-2 px-2 font-semibold">Coment.</th>
            <th className="text-right py-2 px-2 font-semibold">Guardados</th>
            <th className="text-right py-2 px-2 font-semibold">Compartidos</th>
            <th className="text-right py-2 px-2 font-semibold">Seguidores</th>
            <th className="text-right py-2 px-2 font-semibold">Vista media</th>
            <th className="text-left py-2 pl-3 font-semibold">Tomas</th>
          </tr>
        </thead>
        <tbody className="text-gray-800 dark:text-slate-200">
          {posts.map(p => {
            const l = p.latest || {};
            const st = POST_STATUS[p.status] || POST_STATUS.published;
            // La toma corta al primer «sin permiso»: el resto queda pendiente por la misma causa.
            const insightsState = p.insights_state === 'pending' && ['no_permission', 'token_invalid'].includes(accountStatus)
              ? accountStatus : p.insights_state;
            return (
              <tr key={p.media_id} className="border-t border-gray-100 dark:border-slate-700/60 align-top">
                <td className="py-2.5 pr-3">
                  <div className="flex items-start gap-2.5 max-w-xs">
                    <div className="w-8 h-8 rounded-lg flex items-center justify-center text-white shrink-0" style={{ background: IG_GRADIENT }}>
                      <KindIcon kind={p.kind} mediaType={p.media_type} />
                    </div>
                    <div className="min-w-0">
                      <p className="font-semibold truncate" title={p.caption || ''}>{p.caption?.split('\n')[0] || (p.kind === 'STORY' ? 'Historia' : 'Sin texto')}</p>
                      <p className="text-[11px] text-gray-400 dark:text-slate-500 flex items-center gap-1">
                        {KIND_LABEL[p.kind] || p.kind} · {fmtWhen(p.posted_at)}
                        {p.permalink && (
                          <a href={p.permalink} target="_blank" rel="noopener noreferrer" className="inline-flex items-center gap-0.5 text-pink-600 dark:text-pink-400 hover:underline">
                            ver <ExternalLink size={11} />
                          </a>
                        )}
                      </p>
                      {insightsState !== 'ok' && INSIGHTS_STATE[insightsState] && p.status === 'published' && (
                        <p className="text-[11px] text-amber-700 dark:text-amber-300">{INSIGHTS_STATE[insightsState]}</p>
                      )}
                    </div>
                  </div>
                </td>
                <td className="py-2.5 px-2"><span className={`text-[10px] font-semibold px-2 py-1 rounded-full whitespace-nowrap ${st.cls}`}>{st.label}</span></td>
                <td className="py-2.5 px-2 text-right tabular-nums font-semibold">{fmt(l.views)}</td>
                <td className="py-2.5 px-2 text-right tabular-nums">{fmt(l.reach)}</td>
                <td className="py-2.5 px-2 text-right tabular-nums">{fmt(l.likes ?? p.like_count)}</td>
                <td className="py-2.5 px-2 text-right tabular-nums">{fmt(l.comments ?? p.comments_count)}</td>
                <td className="py-2.5 px-2 text-right tabular-nums">{fmt(l.saved)}</td>
                <td className="py-2.5 px-2 text-right tabular-nums">{fmt(l.shares)}</td>
                <td className="py-2.5 px-2 text-right tabular-nums" title={p.kind === 'REELS' ? 'Meta no entrega seguidores ganados por Reel' : ''}>{fmt(l.follows)}</td>
                <td className="py-2.5 px-2 text-right tabular-nums">{fmtSeconds(l.avg_watch_time_ms)}</td>
                <td className="py-2.5 pl-3">
                  <CheckpointChips post={p} />
                  {p.latest && <p className="mt-1 text-[10px] text-gray-400 dark:text-slate-500 flex items-center gap-1"><Clock size={10} /> {fmtWhen(p.latest.taken_at)}</p>}
                </td>
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}

export default function MarketingIgView() {
  const [days, setDays] = useState(30);
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [syncing, setSyncing] = useState(false);
  const [syncNote, setSyncNote] = useState('');
  const [metric, setMetric] = useState('views');
  const [showTable, setShowTable] = useState(false);

  const load = useCallback(async (d) => {
    setLoading(true);
    try {
      setData(await getIgInsights({ days: d }));
      setError('');
    } catch (e) {
      setError(e.message || 'No se pudieron cargar las métricas');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { load(days); }, [days, load]);

  async function handleSync() {
    setSyncing(true);
    setSyncNote('');
    try {
      const r = await syncIgInsights();
      if (r.skipped) setSyncNote(r.reason || 'Hubo una toma hace muy poco.');
      else setSyncNote(`Toma lista: ${r.snapshots ?? 0} lecturas de publicaciones y ${r.account_days ?? 0} días de la cuenta.`);
      await load(days);
    } catch (e) {
      setSyncNote(e.message || 'No se pudo tomar ahora');
    } finally {
      setSyncing(false);
    }
  }

  const filledDays = useMemo(() => fillDays(data?.period, data?.daily), [data]);
  const status = data?.insights_status;
  const totals = data?.period?.totals || {};
  const acc = data?.account || {};
  const followersDelta = data?.period?.followers_net_change;

  return (
    <div className="flex-1 h-full overflow-y-auto custom-scrollbar bg-gray-50 dark:bg-slate-900">
      {/* Hero con gradiente Instagram */}
      <div className="relative overflow-hidden px-6 pt-6 pb-8 text-white" style={{ backgroundImage: IG_GRADIENT }}>
        <div className="absolute inset-0 opacity-20" style={{ backgroundImage: 'radial-gradient(circle at 80% 20%, rgba(255,255,255,0.5), transparent 45%)' }} />
        <div className="relative flex flex-wrap items-start justify-between gap-4">
          <div className="flex items-center gap-3">
            <div className="w-12 h-12 rounded-2xl bg-white/20 flex items-center justify-center ring-1 ring-white/30">
              <Instagram size={26} />
            </div>
            <div>
              <h1 className="text-2xl font-bold tracking-tight" style={POPPINS}>Marketing IG</h1>
              <p className="text-sm text-white/80">@{acc.username || 'automatizatech.cl'} · métricas reales de Instagram</p>
            </div>
          </div>
          <div className="flex flex-wrap items-center gap-2">
            <div className="flex gap-1 p-1 rounded-full bg-white/15 ring-1 ring-white/20" role="group" aria-label="Periodo">
              {PERIODS.map(p => (
                <button
                  key={p.days}
                  onClick={() => setDays(p.days)}
                  aria-pressed={days === p.days}
                  className={`px-3 py-1 rounded-full text-xs font-semibold transition-all ${
                    days === p.days ? 'bg-white text-pink-700 shadow' : 'text-white/85 hover:bg-white/10'
                  }`}
                >
                  {p.label}
                </button>
              ))}
            </div>
            <button
              onClick={handleSync}
              disabled={syncing}
              className="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-white text-pink-700 shadow hover:bg-pink-50 disabled:opacity-60"
            >
              <RefreshCw size={14} className={syncing ? 'animate-spin' : ''} /> {syncing ? 'Tomando…' : 'Actualizar ahora'}
            </button>
          </div>
        </div>
        {syncNote && <p className="relative mt-3 text-xs text-white/90">{syncNote}</p>}
      </div>

      <div className={`px-6 -mt-5 pb-8 space-y-6 transition-opacity ${loading && data ? 'opacity-60' : ''}`}>
        {error && (
          <div className={`rounded-2xl p-4 ring-1 ${TONES.red}`}>
            <p className="font-bold text-sm">No se pudieron cargar las métricas</p>
            <p className="text-sm">{error}</p>
          </div>
        )}
        {!data && loading && <div className={`${CARD} p-8 text-center text-sm text-gray-400`}>Cargando métricas…</div>}

        {data && (
          <>
            <StatusBanner status={status} />

            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
              <KpiTile
                icon={Users}
                label="Seguidores"
                value={fmt(acc.followers_count)}
                sub={followersDelta !== null && followersDelta !== undefined
                  ? `${followersDelta >= 0 ? '+' : ''}${fmt(followersDelta)} en el periodo`
                  : (acc.snapshot_at ? `al ${fmtWhen(acc.snapshot_at)}` : 'sin toma')}
              />
              <KpiTile icon={Eye} label="Visualizaciones" value={fmt(totals.views)} sub={`${data.period.days_with_data} de ${data.period.days} días con datos`} />
              <KpiTile icon={Activity} label="Alcance (suma diaria)" value={fmt(totals.reach)} sub="una cuenta que vio dos días cuenta dos veces" />
              <KpiTile icon={Heart} label="Interacciones" value={fmt(totals.total_interactions)} sub={totals.total_interactions !== null ? `${fmt(totals.likes)} likes · ${fmt(totals.comments)} coment. · ${fmt(totals.shares)} compart.` : ''} />
            </div>

            {/* Cuenta por día */}
            <div className={`${CARD} p-5`}>
              <div className="flex flex-wrap items-center justify-between gap-3 mb-4">
                <h2 className="text-base font-bold text-gray-900 dark:text-white" style={POPPINS}>
                  {CHART_METRICS.find(m => m.key === metric)?.label} por día
                </h2>
                <div className="flex flex-wrap items-center gap-2">
                  <div className="flex gap-1 p-1 rounded-full bg-gray-100 dark:bg-slate-700/50" role="group" aria-label="Métrica del gráfico">
                    {CHART_METRICS.map(m => (
                      <button
                        key={m.key}
                        onClick={() => setMetric(m.key)}
                        aria-pressed={metric === m.key}
                        className={`px-2.5 py-1 rounded-full text-[11px] font-semibold ${
                          metric === m.key ? 'bg-white dark:bg-slate-800 text-pink-700 dark:text-pink-300 shadow' : 'text-gray-500 dark:text-slate-400 hover:text-gray-800 dark:hover:text-slate-200'
                        }`}
                      >
                        {m.label}
                      </button>
                    ))}
                  </div>
                  <button
                    onClick={() => setShowTable(t => !t)}
                    className="flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold text-gray-600 dark:text-slate-300 ring-1 ring-gray-200 dark:ring-slate-600 hover:bg-gray-50 dark:hover:bg-slate-700/50"
                  >
                    {showTable ? <><BarChart3 size={12} /> Gráfico</> : <><Table size={12} /> Tabla</>}
                  </button>
                </div>
              </div>
              {showTable ? <DailyTable days={filledDays} /> : <DailyChart days={filledDays} metric={metric} />}
            </div>

            {/* Publicaciones */}
            <div className={`${CARD} p-5`}>
              <div className="flex flex-wrap items-baseline justify-between gap-2 mb-3">
                <h2 className="text-base font-bold text-gray-900 dark:text-white" style={POPPINS}>Publicaciones</h2>
                <p className="text-[11px] text-gray-400 dark:text-slate-500">Cifras de la última toma · tomas fijas a las 24 h, 48 h, 7 y 30 días</p>
              </div>
              <PostsTable posts={data.posts} accountStatus={status?.status} />
            </div>

            <p className="text-center text-[11px] text-gray-400 dark:text-slate-600 pt-2">
              Fuente: API de Instagram (Instagram Login), toma automática cada hora. Meta puede tardar hasta 48 h en consolidar las cifras.
            </p>
          </>
        )}
      </div>
    </div>
  );
}
