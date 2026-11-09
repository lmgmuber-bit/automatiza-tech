import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  Megaphone, MessageCircle, Wallet, Eye, Users, Play, RefreshCw, AlertTriangle, Info,
  CheckCircle2, XCircle, CalendarDays, ImageOff, Calculator,
} from 'lucide-react';
import { getMetaAds, syncMetaAds, formatChileDateTime } from '../api';

/*
 * Marketing IG → Anuncios: anuncios pagados de la cuenta publicitaria de AutomatizaTech.
 * Fuente: api-omnichannel.php → admin/meta-ads (omnichannel-meta-ads-controller.php lee la
 * Marketing API de Meta con ads_read y guarda una copia de 30 minutos). Si falta el token o el
 * permiso, se dice en pantalla: nunca se muestran cifras de ejemplo.
 */

const CARD = 'bg-white dark:bg-slate-800 rounded-2xl shadow-sm ring-1 ring-gray-100 dark:ring-slate-700/60';
const POPPINS = { fontFamily: 'Poppins, sans-serif' };

const fmt = n => (n === null || n === undefined ? '—' : Number(n).toLocaleString('es-CL'));
const fmtWhen = iso => (iso ? formatChileDateTime(iso, { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }) : '—');
const fmtDate = iso => (iso ? formatChileDateTime(iso, { day: '2-digit', month: 'short' }) : '—');
const fmtDay = ymd => {
  const [y, m, d] = ymd.split('-').map(Number);
  return new Date(Date.UTC(y, m - 1, d, 12)).toLocaleDateString('es-CL', { day: '2-digit', month: 'short', timeZone: 'UTC' });
};
function money(n, currency) {
  if (n === null || n === undefined) return '—';
  try {
    return Number(n).toLocaleString('es-CL', { style: 'currency', currency: currency || 'CLP', maximumFractionDigits: currency === 'CLP' || !currency ? 0 : 2 });
  } catch {
    return `${fmt(n)} ${currency || ''}`.trim();
  }
}

const AD_STATUS = {
  active:               { label: 'Activo', cls: 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-500/30' },
  pending_review:       { label: 'En revisión', cls: 'bg-sky-50 text-sky-700 ring-sky-200 dark:bg-sky-500/10 dark:text-sky-300 dark:ring-sky-500/30' },
  in_process:           { label: 'En revisión', cls: 'bg-sky-50 text-sky-700 ring-sky-200 dark:bg-sky-500/10 dark:text-sky-300 dark:ring-sky-500/30' },
  preapproved:          { label: 'Preaprobado', cls: 'bg-sky-50 text-sky-700 ring-sky-200 dark:bg-sky-500/10 dark:text-sky-300 dark:ring-sky-500/30' },
  paused:               { label: 'En pausa', cls: 'bg-gray-100 text-gray-600 ring-gray-200 dark:bg-slate-700 dark:text-slate-300 dark:ring-slate-600' },
  campaign_paused:      { label: 'Campaña en pausa', cls: 'bg-gray-100 text-gray-600 ring-gray-200 dark:bg-slate-700 dark:text-slate-300 dark:ring-slate-600' },
  adset_paused:         { label: 'Conjunto en pausa', cls: 'bg-gray-100 text-gray-600 ring-gray-200 dark:bg-slate-700 dark:text-slate-300 dark:ring-slate-600' },
  archived:             { label: 'Archivado', cls: 'bg-gray-100 text-gray-600 ring-gray-200 dark:bg-slate-700 dark:text-slate-300 dark:ring-slate-600' },
  disapproved:          { label: 'Rechazado', cls: 'bg-red-50 text-red-700 ring-red-200 dark:bg-red-500/10 dark:text-red-300 dark:ring-red-500/30' },
  with_issues:          { label: 'Con problemas', cls: 'bg-amber-50 text-amber-800 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/30' },
  completed:            { label: 'Terminado', cls: 'bg-gray-100 text-gray-600 ring-gray-200 dark:bg-slate-700 dark:text-slate-300 dark:ring-slate-600' },
  pending_billing_info: { label: 'Falta el pago', cls: 'bg-amber-50 text-amber-800 ring-amber-200 dark:bg-amber-500/10 dark:text-amber-300 dark:ring-amber-500/30' },
};

const STATUS_STYLE = {
  ok:            { tone: 'emerald', Icon: CheckCircle2, title: 'Anuncios al día' },
  never:         { tone: 'sky', Icon: Info, title: 'Aún no se han leído los anuncios' },
  no_config:     { tone: 'sky', Icon: Info, title: 'Falta conectar la cuenta publicitaria' },
  no_permission: { tone: 'amber', Icon: AlertTriangle, title: 'El token no puede leer la cuenta publicitaria' },
  token_invalid: { tone: 'red', Icon: XCircle, title: 'El token de anuncios venció o no sirve' },
  rate_limited:  { tone: 'amber', Icon: AlertTriangle, title: 'Meta pidió una pausa' },
  error:         { tone: 'red', Icon: XCircle, title: 'La última lectura falló' },
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
        <CheckCircle2 size={14} aria-hidden="true" /> Anuncios al día · leídos {fmtWhen(status.last_fetch_at)}
        {status.errors?.length > 0 && <span className="text-amber-700 dark:text-amber-300"> · {status.errors.join(' · ')}</span>}
      </p>
    );
  }
  return (
    <div className={`rounded-2xl p-4 ring-1 ${TONES[s.tone]}`} role="status">
      <div className="flex items-start gap-3">
        <Icon size={20} className="shrink-0 mt-0.5" aria-hidden="true" />
        <div className="space-y-1 text-sm">
          <p className="font-bold">{s.title}</p>
          <p>{status?.message}</p>
          {status?.status === 'no_config' && (
            <p>
              Hace falta un token con el permiso <code className="font-mono text-xs">ads_read</code> sobre la cuenta «AutomatizaTech Ads»
              y el id de la cuenta, en <code className="font-mono text-xs">wp-config.php</code>. Los pasos están en la guía de Métricas IG, §11.
            </p>
          )}
          {status?.data_at && <p className="text-xs opacity-80">Se muestran los datos leídos el {fmtWhen(status.data_at)}.</p>}
        </div>
      </div>
    </div>
  );
}

function KpiTile({ icon: Icon, label, value, sub }) {
  return (
    <div className={`${CARD} p-4`}>
      <div className="w-10 h-10 rounded-xl flex items-center justify-center ring-1 bg-blue-50 text-blue-600 ring-blue-200 dark:bg-blue-500/10 dark:text-blue-300 dark:ring-blue-500/20">
        <Icon size={20} aria-hidden="true" />
      </div>
      <p className="mt-3 text-3xl font-bold text-gray-900 dark:text-white" style={POPPINS}>{value}</p>
      <p className="text-xs text-gray-500 dark:text-slate-400">{label}</p>
      {sub && <p className="mt-1 text-[11px] text-gray-400 dark:text-slate-500">{sub}</p>}
    </div>
  );
}

/** Los últimos 30 días (hora de Chile), con hueco donde Meta no informó gasto. */
function last30(daily) {
  const byDay = Object.fromEntries((daily || []).map(d => [d.day, d]));
  const todayChile = new Date().toLocaleDateString('en-CA', { timeZone: 'America/Santiago' });
  const [y, m, d] = todayChile.split('-').map(Number);
  const cur = new Date(Date.UTC(y, m - 1, d));
  cur.setUTCDate(cur.getUTCDate() - 29);
  const out = [];
  for (let i = 0; i < 30; i++) {
    const ymd = cur.toISOString().slice(0, 10);
    out.push(byDay[ymd] || { day: ymd, spend: null });
    cur.setUTCDate(cur.getUTCDate() + 1);
  }
  return out;
}

function SpendChart({ days, currency }) {
  const max = Math.max(0, ...days.map(d => d.spend || 0));
  if (!days.length || max === 0) {
    return <p className="text-sm text-gray-400 dark:text-slate-500 py-6 text-center">Todavía no hay gasto registrado en los últimos 30 días.</p>;
  }
  return (
    <div>
      <div className="flex items-end gap-1 h-40" role="img" aria-label="Gasto por día">
      {days.map(d => (
        <div key={d.day} className="flex-1 min-w-[6px] flex flex-col items-center justify-end h-full group relative">
          <div
            className="w-full rounded-t bg-blue-500/80 dark:bg-blue-400/70 group-hover:bg-blue-600"
            style={{ height: d.spend ? `${Math.max(2, (d.spend / max) * 100)}%` : '0%' }}
          />
          <span className="pointer-events-none absolute -top-9 hidden group-hover:block whitespace-nowrap rounded-md bg-gray-900 px-2 py-1 text-[10px] text-white">
            {fmtDay(d.day)} · {money(d.spend, currency)}{d.conversations ? ` · ${d.conversations} conv.` : ''}
          </span>
        </div>
      ))}
      </div>
      <div className="flex justify-between mt-1 text-[10px] text-gray-400 dark:text-slate-500">
        <span>{fmtDay(days[0].day)}</span><span>{fmtDay(days[days.length - 1].day)}</span>
      </div>
    </div>
  );
}

function AdsTable({ ads, currency }) {
  if (!ads.length) {
    return <p className="text-sm text-gray-400 dark:text-slate-500 py-6 text-center">La cuenta no tiene anuncios.</p>;
  }
  return (
    <div className="overflow-x-auto">
      <table className="w-full text-xs min-w-[960px]">
        <thead>
          <tr className="text-left text-gray-400 dark:text-slate-500 border-b border-gray-100 dark:border-slate-700">
            <th className="py-2 pr-2 font-semibold">Anuncio</th>
            <th className="py-2 px-2 font-semibold">Estado</th>
            <th className="py-2 px-2 font-semibold">Fechas</th>
            <th className="py-2 px-2 font-semibold text-right">Gasto</th>
            <th className="py-2 px-2 font-semibold text-right">Impresiones</th>
            <th className="py-2 px-2 font-semibold text-right">Alcance</th>
            <th className="py-2 px-2 font-semibold text-right" title="Reproducciones de 3 segundos o más">Reprod.</th>
            <th className="py-2 px-2 font-semibold text-right" title="Vistas completas o de al menos 15 segundos">ThruPlays</th>
            <th className="py-2 px-2 font-semibold text-right">Conversaciones</th>
            <th className="py-2 pl-2 font-semibold text-right">Costo por conv.</th>
          </tr>
        </thead>
        <tbody>
          {ads.map(ad => {
            const m = ad.metrics || {};
            // Meta deja «ACTIVE» a un anuncio cuya fecha de término ya pasó: se muestra como terminado.
            const ended = ad.end_at && new Date(ad.end_at) < new Date();
            const st = (ended && ad.status === 'active' ? AD_STATUS.completed : AD_STATUS[ad.status]) || { label: ad.status || '—', cls: 'bg-gray-100 text-gray-600 ring-gray-200 dark:bg-slate-700 dark:text-slate-300 dark:ring-slate-600' };
            return (
              <tr key={ad.id} className="border-b border-gray-50 dark:border-slate-700/50 align-top">
                <td className="py-2.5 pr-2">
                  <div className="flex items-start gap-2.5 max-w-xs">
                    {ad.thumbnail
                      ? <img src={ad.thumbnail} alt="" className="w-10 h-10 rounded-lg object-cover ring-1 ring-gray-100 dark:ring-slate-700" loading="lazy" />
                      : <div className="w-10 h-10 rounded-lg bg-gray-100 dark:bg-slate-700 flex items-center justify-center text-gray-400"><ImageOff size={16} aria-hidden="true" /></div>}
                    <div className="min-w-0">
                      <p className="font-semibold text-gray-800 dark:text-slate-100 truncate">{ad.title || ad.name}</p>
                      <p className="text-gray-400 dark:text-slate-500 line-clamp-2">{ad.body}</p>
                    </div>
                  </div>
                </td>
                <td className="py-2.5 px-2"><span className={`inline-block px-2 py-0.5 rounded-full ring-1 text-[11px] font-semibold ${st.cls}`}>{st.label}</span></td>
                <td className="py-2.5 px-2 whitespace-nowrap text-gray-500 dark:text-slate-400">{fmtDate(ad.start_at)} → {ad.end_at ? fmtDate(ad.end_at) : 'sin fin'}</td>
                <td className="py-2.5 px-2 text-right tabular-nums font-semibold">{money(m.spend, currency)}</td>
                <td className="py-2.5 px-2 text-right tabular-nums">{fmt(m.impressions)}</td>
                <td className="py-2.5 px-2 text-right tabular-nums">{fmt(m.reach)}</td>
                <td className="py-2.5 px-2 text-right tabular-nums">{fmt(m.video_views)}</td>
                <td className="py-2.5 px-2 text-right tabular-nums">{fmt(m.thruplays)}</td>
                <td className="py-2.5 px-2 text-right tabular-nums font-semibold">{fmt(m.conversations)}</td>
                <td className="py-2.5 pl-2 text-right tabular-nums">{money(m.cost_per_conversation, currency)}</td>
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}

export default function MetaAdsPanel() {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [syncing, setSyncing] = useState(false);

  const load = useCallback(async (force) => {
    force ? setSyncing(true) : setLoading(true);
    try {
      setData(force ? await syncMetaAds() : await getMetaAds());
      setError('');
    } catch (e) {
      setError(e.message || 'No se pudieron cargar los anuncios');
    } finally {
      setLoading(false);
      setSyncing(false);
    }
  }, []);

  useEffect(() => { load(false); }, [load]);

  const currency = data?.account?.currency || 'CLP';
  const t = data?.totals || {};
  const days = useMemo(() => last30(data?.daily), [data]);
  const status = data?.ads_status;

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="flex items-center gap-2">
          <Megaphone size={18} className="text-blue-600 dark:text-blue-300" aria-hidden="true" />
          <h2 className="text-base font-bold text-gray-900 dark:text-white" style={POPPINS}>
            Anuncios pagados{data?.account?.name ? ` · ${data.account.name}` : ''}
          </h2>
        </div>
        <button
          onClick={() => load(true)}
          disabled={syncing || !data?.configured}
          className="flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold bg-blue-600 text-white shadow hover:bg-blue-700 disabled:opacity-50"
        >
          <RefreshCw size={14} className={syncing ? 'animate-spin' : ''} aria-hidden="true" /> {syncing ? 'Leyendo…' : 'Actualizar anuncios'}
        </button>
      </div>
      {data?.note && <p className="text-xs text-gray-500 dark:text-slate-400">{data.note}</p>}

      {error && (
        <div className={`rounded-2xl p-4 ring-1 ${TONES.red}`}>
          <p className="font-bold text-sm">No se pudieron cargar los anuncios</p>
          <p className="text-sm">{error}</p>
        </div>
      )}
      {!data && loading && <div className={`${CARD} p-8 text-center text-sm text-gray-400`}>Cargando anuncios…</div>}

      {data && (
        <>
          <StatusBanner status={status} />

          {data.totals && (
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
              <KpiTile icon={Wallet} label="Gasto total" value={money(t.spend, currency)} sub="sin IVA · todo el historial" />
              <KpiTile icon={MessageCircle} label="Conversaciones iniciadas" value={fmt(t.conversations)} sub="mensajes que abrieron un chat" />
              <KpiTile icon={Calculator} label="Costo por conversación" value={money(t.cost_per_conversation, currency)} sub={t.conversations ? 'gasto ÷ conversaciones' : 'aún sin conversaciones'} />
              <KpiTile icon={Users} label="Alcance (suma)" value={fmt(t.reach)} sub="una cuenta que vio dos anuncios cuenta dos veces" />
              <KpiTile icon={Play} label="Reproducciones" value={fmt(t.video_views)} sub={`${fmt(t.impressions)} impresiones`} />
            </div>
          )}

          {data.totals && (<>
          <div className={`${CARD} p-5`}>
            <div className="flex items-center gap-2 mb-4">
              <CalendarDays size={16} className="text-gray-400" aria-hidden="true" />
              <h3 className="text-sm font-bold text-gray-900 dark:text-white" style={POPPINS}>Gasto por día (últimos 30 días)</h3>
            </div>
            <SpendChart days={days} currency={currency} />
          </div>

          <div className={`${CARD} p-5`}>
            <div className="flex flex-wrap items-baseline justify-between gap-2 mb-3">
              <div className="flex items-center gap-2">
                <Eye size={16} className="text-gray-400" aria-hidden="true" />
                <h3 className="text-sm font-bold text-gray-900 dark:text-white" style={POPPINS}>Por anuncio</h3>
              </div>
              <p className="text-[11px] text-gray-400 dark:text-slate-500">Resultados de toda la vida de cada anuncio</p>
            </div>
            <AdsTable ads={data.ads || []} currency={currency} />
          </div>
          </>)}

          <p className="text-center text-[11px] text-gray-400 dark:text-slate-600 pt-2">
            Fuente: Marketing API de Meta (solo lectura), copia de 30 minutos. El gasto no incluye IVA; Meta puede tardar unas horas en mostrar las cifras.
          </p>
        </>
      )}
    </div>
  );
}
