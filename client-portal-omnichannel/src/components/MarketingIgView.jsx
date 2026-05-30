import { useState } from 'react';
import {
  Instagram, Eye, MessageCircle, UserPlus, CalendarCheck,
  TrendingUp, TrendingDown, Zap, DollarSign, Film, Image as ImageIcon,
  AlertTriangle, ArrowRight, Sparkles
} from 'lucide-react';

/*
 * Marketing IG — Dashboard de marketing de Instagram para AutomatizaTech.
 * NOTA: datos de ejemplo (mock). Cablear APIs reales (Higgsfield balance,
 * Instagram Insights, CRM demos) en una segunda fase.
 */

const PERIODS = [
  { key: 'week', label: 'Semana' },
  { key: 'month', label: 'Mes' },
  { key: 'quarter', label: 'Trimestre' },
];

// Mock — reemplazar por datos reales
const KPIS = [
  { id: 'reach', label: 'Alcance', value: '48.2K', delta: 12, icon: Eye, tint: 'sky' },
  { id: 'dms', label: 'DMs recibidos', value: '327', delta: 8, icon: MessageCircle, tint: 'violet' },
  { id: 'leads', label: 'Leads', value: '64', delta: 21, icon: UserPlus, tint: 'amber' },
  { id: 'demos', label: 'Demos agendadas', value: '18', delta: 5, icon: CalendarCheck, tint: 'emerald', north: true },
];

const FUNNEL = [
  { label: 'Impresiones', value: 48200, color: '#0ea5e9' },
  { label: 'DMs', value: 327, color: '#8b5cf6' },
  { label: 'Leads', value: 64, color: '#f59e0b' },
  { label: 'Demos', value: 18, color: '#10b981' },
  { label: 'Clientes', value: 6, color: '#06d6a0' },
];

const PILLARS = [
  { name: 'Demo MAXTECH', pieces: 4, leads: 22, score: 8.4, branch: 'A', fmt: 'Reel' },
  { name: 'Educación automatización', pieces: 6, leads: 15, score: 7.6, branch: 'B', fmt: 'Carrusel' },
  { name: 'Caso de éxito', pieces: 3, leads: 14, score: 8.1, branch: 'A', fmt: 'Reel' },
  { name: 'Pain point PYME', pieces: 4, leads: 9, score: 6.9, branch: 'B', fmt: 'Post' },
  { name: 'Oferta directa CTA', pieces: 2, leads: 4, score: 6.2, branch: 'A', fmt: 'Reel' },
];

const RECENT_POSTS = [
  { pillar: 'Demo MAXTECH', branch: 'A', fmt: 'Reel', score: 8.7, status: 'Publicado', when: 'Hoy 12:30' },
  { pillar: 'Caso de éxito', branch: 'A', fmt: 'Reel', score: 8.1, status: 'Publicado', when: 'Ayer 19:00' },
  { pillar: 'Educación', branch: 'B', fmt: 'Carrusel', score: 7.4, status: 'En cola', when: 'Mañana 12:00' },
  { pillar: 'Pain point PYME', branch: 'B', fmt: 'Post', score: 5.8, status: 'Revisión', when: '—' },
];

const TINTS = {
  sky:     { bg: 'bg-sky-50 dark:bg-sky-500/10', text: 'text-sky-600 dark:text-sky-300', ring: 'ring-sky-200 dark:ring-sky-500/20' },
  violet:  { bg: 'bg-violet-50 dark:bg-violet-500/10', text: 'text-violet-600 dark:text-violet-300', ring: 'ring-violet-200 dark:ring-violet-500/20' },
  amber:   { bg: 'bg-amber-50 dark:bg-amber-500/10', text: 'text-amber-600 dark:text-amber-300', ring: 'ring-amber-200 dark:ring-amber-500/20' },
  emerald: { bg: 'bg-emerald-50 dark:bg-emerald-500/10', text: 'text-emerald-600 dark:text-emerald-300', ring: 'ring-emerald-200 dark:ring-emerald-500/20' },
};

const IG_GRADIENT = 'linear-gradient(135deg,#f09433 0%,#e6683c 25%,#dc2743 50%,#cc2366 75%,#bc1888 100%)';
const fmtNum = n => n.toLocaleString('es-CL');

export default function MarketingIgView() {
  const [period, setPeriod] = useState('month');

  // Higgsfield (mock) — créditos del plan
  const creditsUsed = 38;
  const creditsTotal = 50;
  const creditsLeft = creditsTotal - creditsUsed;
  const creditsPct = Math.round((creditsUsed / creditsTotal) * 100);
  const lowCredits = creditsLeft <= 14; // ~menos de 1 semana de margen
  const geminiCost = 4.8;

  const funnelMax = FUNNEL[0].value;

  return (
    <div className="flex-1 h-full overflow-y-auto custom-scrollbar bg-gray-50 dark:bg-slate-900">
      {/* Hero header con gradiente Instagram */}
      <div className="relative overflow-hidden px-6 pt-6 pb-8 text-white" style={{ backgroundImage: IG_GRADIENT }}>
        <div className="absolute inset-0 opacity-20" style={{ backgroundImage: 'radial-gradient(circle at 80% 20%, rgba(255,255,255,0.5), transparent 45%)' }} />
        <div className="relative flex flex-wrap items-start justify-between gap-4">
          <div className="flex items-center gap-3">
            <div className="w-12 h-12 rounded-2xl bg-white/20 backdrop-blur-sm flex items-center justify-center ring-1 ring-white/30">
              <Instagram size={26} />
            </div>
            <div>
              <h1 className="text-2xl font-bold tracking-tight" style={{ fontFamily: 'Poppins, sans-serif' }}>Marketing IG</h1>
              <p className="text-sm text-white/80">@automatizatech.cl · captación y contenido</p>
            </div>
          </div>
          {/* Selector de periodo */}
          <div className="flex gap-1 p-1 rounded-full bg-white/15 backdrop-blur-sm ring-1 ring-white/20">
            {PERIODS.map(p => (
              <button
                key={p.key}
                onClick={() => setPeriod(p.key)}
                className={`px-3 py-1 rounded-full text-xs font-semibold transition-all ${
                  period === p.key ? 'bg-white text-pink-700 shadow' : 'text-white/85 hover:bg-white/10'
                }`}
              >
                {p.label}
              </button>
            ))}
          </div>
        </div>
      </div>

      <div className="px-6 -mt-5 pb-8 space-y-6">
        {/* KPI cards */}
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          {KPIS.map(k => {
            const Icon = k.icon;
            const t = TINTS[k.tint];
            const up = k.delta >= 0;
            return (
              <div
                key={k.id}
                className={`relative bg-white dark:bg-slate-800 rounded-2xl p-4 shadow-sm ring-1 ring-gray-100 dark:ring-slate-700/60 ${
                  k.north ? 'ring-2 ring-emerald-300 dark:ring-emerald-500/40' : ''
                }`}
              >
                {k.north && (
                  <span className="absolute -top-2 left-4 px-2 py-0.5 rounded-full bg-emerald-500 text-white text-[10px] font-bold flex items-center gap-1 shadow">
                    <Sparkles size={10} /> North Star
                  </span>
                )}
                <div className="flex items-center justify-between">
                  <div className={`w-10 h-10 rounded-xl flex items-center justify-center ring-1 ${t.bg} ${t.text} ${t.ring}`}>
                    <Icon size={20} />
                  </div>
                  <span className={`flex items-center gap-0.5 text-xs font-semibold ${up ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-500'}`}>
                    {up ? <TrendingUp size={14} /> : <TrendingDown size={14} />}
                    {up ? '+' : ''}{k.delta}{k.id === 'demos' ? '' : '%'}
                  </span>
                </div>
                <p className="mt-3 text-3xl font-bold text-gray-900 dark:text-white" style={{ fontFamily: 'Poppins, sans-serif' }}>{k.value}</p>
                <p className="text-xs text-gray-500 dark:text-slate-400">{k.label}</p>
              </div>
            );
          })}
        </div>

        <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
          {/* Funnel */}
          <div className="lg:col-span-2 bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-sm ring-1 ring-gray-100 dark:ring-slate-700/60">
            <h2 className="text-base font-bold text-gray-900 dark:text-white mb-4" style={{ fontFamily: 'Poppins, sans-serif' }}>Embudo de conversión</h2>
            <div className="space-y-3">
              {FUNNEL.map((f, i) => {
                const pct = Math.max((f.value / funnelMax) * 100, 4);
                const conv = i > 0 ? ((f.value / FUNNEL[i - 1].value) * 100).toFixed(1) : null;
                return (
                  <div key={f.label}>
                    <div className="flex items-center justify-between text-xs mb-1">
                      <span className="font-medium text-gray-600 dark:text-slate-300">{f.label}</span>
                      <span className="font-bold text-gray-900 dark:text-white">
                        {fmtNum(f.value)}
                        {conv && <span className="ml-2 text-[10px] font-medium text-gray-400">({conv}%)</span>}
                      </span>
                    </div>
                    <div className="h-3 rounded-full bg-gray-100 dark:bg-slate-700/50 overflow-hidden">
                      <div className="h-full rounded-full transition-all duration-700" style={{ width: `${pct}%`, backgroundColor: f.color }} />
                    </div>
                  </div>
                );
              })}
            </div>
          </div>

          {/* Gastos: Higgsfield + Gemini */}
          <div className="space-y-4">
            {/* Higgsfield credits */}
            <div className="bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-sm ring-1 ring-gray-100 dark:ring-slate-700/60">
              <div className="flex items-center justify-between mb-3">
                <h3 className="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-1.5" style={{ fontFamily: 'Poppins, sans-serif' }}>
                  <Zap size={16} className="text-amber-500" /> Créditos Higgsfield
                </h3>
                {lowCredits && (
                  <span className="flex items-center gap-1 text-[10px] font-bold text-red-600 bg-red-50 dark:bg-red-500/10 px-2 py-0.5 rounded-full">
                    <AlertTriangle size={11} /> Bajo
                  </span>
                )}
              </div>
              <div className="flex items-end gap-1 mb-2">
                <span className="text-3xl font-bold text-gray-900 dark:text-white" style={{ fontFamily: 'Poppins, sans-serif' }}>{creditsLeft}</span>
                <span className="text-sm text-gray-400 mb-1">/ {creditsTotal} restantes</span>
              </div>
              <div className="h-2.5 rounded-full bg-gray-100 dark:bg-slate-700/50 overflow-hidden">
                <div className={`h-full rounded-full ${lowCredits ? 'bg-red-500' : 'bg-amber-500'}`} style={{ width: `${creditsPct}%` }} />
              </div>
              <p className="mt-2 text-[11px] text-gray-400">{creditsUsed} consumidos este mes {lowCredits && '· renueva pronto'}</p>
            </div>

            {/* Gemini cost */}
            <div className="bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-sm ring-1 ring-gray-100 dark:ring-slate-700/60">
              <h3 className="text-sm font-bold text-gray-900 dark:text-white flex items-center gap-1.5 mb-2" style={{ fontFamily: 'Poppins, sans-serif' }}>
                <DollarSign size={16} className="text-emerald-500" /> Costo Gemini (mes)
              </h3>
              <p className="text-3xl font-bold text-gray-900 dark:text-white" style={{ fontFamily: 'Poppins, sans-serif' }}>${geminiCost.toFixed(2)}</p>
              <p className="text-[11px] text-gray-400 mt-1">Generación de imágenes rama B</p>
            </div>
          </div>
        </div>

        {/* ROI por pilar */}
        <div className="bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-sm ring-1 ring-gray-100 dark:ring-slate-700/60">
          <h2 className="text-base font-bold text-gray-900 dark:text-white mb-4" style={{ fontFamily: 'Poppins, sans-serif' }}>ROI por pilar de contenido</h2>
          <div className="space-y-3">
            {PILLARS.map(p => {
              const maxLeads = Math.max(...PILLARS.map(x => x.leads));
              const pct = (p.leads / maxLeads) * 100;
              return (
                <div key={p.name} className="flex items-center gap-3">
                  <div className="w-40 shrink-0">
                    <p className="text-xs font-semibold text-gray-800 dark:text-slate-200 truncate">{p.name}</p>
                    <p className="text-[10px] text-gray-400">{p.pieces} piezas · {p.fmt}</p>
                  </div>
                  <div className="flex-1 h-6 rounded-lg bg-gray-100 dark:bg-slate-700/40 overflow-hidden relative">
                    <div className="h-full rounded-lg flex items-center justify-end pr-2 transition-all duration-700" style={{ width: `${Math.max(pct, 12)}%`, background: IG_GRADIENT }}>
                      <span className="text-[10px] font-bold text-white">{p.leads} leads</span>
                    </div>
                  </div>
                  <span className={`shrink-0 w-12 text-center text-xs font-bold px-2 py-1 rounded-lg ${
                    p.score >= 7 ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'
                    : 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300'
                  }`}>{p.score}</span>
                  <span className="shrink-0 w-7 h-7 rounded-full flex items-center justify-center text-[10px] font-bold text-white" style={{ background: p.branch === 'A' ? '#0ea5e9' : '#8b5cf6' }}>{p.branch}</span>
                </div>
              );
            })}
          </div>
        </div>

        {/* Posts recientes */}
        <div className="bg-white dark:bg-slate-800 rounded-2xl p-5 shadow-sm ring-1 ring-gray-100 dark:ring-slate-700/60">
          <div className="flex items-center justify-between mb-4">
            <h2 className="text-base font-bold text-gray-900 dark:text-white" style={{ fontFamily: 'Poppins, sans-serif' }}>Publicaciones recientes</h2>
            <button className="flex items-center gap-1 text-xs font-semibold text-pink-600 dark:text-pink-400 hover:gap-2 transition-all">
              Ver todas <ArrowRight size={14} />
            </button>
          </div>
          <div className="space-y-2">
            {RECENT_POSTS.map((post, i) => (
              <div key={i} className="flex items-center gap-3 p-2.5 rounded-xl hover:bg-gray-50 dark:hover:bg-slate-700/40 transition-colors">
                <div className="w-9 h-9 rounded-lg flex items-center justify-center text-white shrink-0" style={{ background: IG_GRADIENT }}>
                  {post.fmt === 'Reel' ? <Film size={16} /> : <ImageIcon size={16} />}
                </div>
                <div className="flex-1 min-w-0">
                  <p className="text-sm font-semibold text-gray-800 dark:text-slate-200 truncate">{post.pillar}</p>
                  <p className="text-[11px] text-gray-400">{post.fmt} · Rama {post.branch} · {post.when}</p>
                </div>
                <span className={`shrink-0 text-xs font-bold px-2 py-1 rounded-lg ${
                  post.score >= 7 ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'
                  : 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300'
                }`}>{post.score}</span>
                <span className={`shrink-0 text-[10px] font-semibold px-2 py-1 rounded-full ${
                  post.status === 'Publicado' ? 'bg-green-100 text-green-700 dark:bg-green-500/15 dark:text-green-300'
                  : post.status === 'En cola' ? 'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300'
                  : 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300'
                }`}>{post.status}</span>
              </div>
            ))}
          </div>
        </div>

        <p className="text-center text-[11px] text-gray-400 dark:text-slate-600 pt-2">
          Datos de ejemplo · pendiente cablear Higgsfield balance, Instagram Insights y CRM de demos.
        </p>
      </div>
    </div>
  );
}
