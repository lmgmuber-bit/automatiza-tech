/* CumpleClick Admin — design system autocontenido, sin fuentes ni CDNs remotos.
   Identidad oficial "El globo dulce": ver design/MANUAL-DE-MARCA.md y
   design/tokens.css (fuente de verdad de la paleta). */

/* Baloo 2 self-hosted, misma familia que el kiosco y el logo. Copiadas con
   nombre estable desde @fontsource para no depender del hash del build. */
@font-face {
  font-family: 'Baloo 2'; font-style: normal; font-weight: 600; font-display: swap;
  src: url('fonts/baloo2-600.woff2') format('woff2');
}
@font-face {
  font-family: 'Baloo 2'; font-style: normal; font-weight: 700; font-display: swap;
  src: url('fonts/baloo2-700.woff2') format('woff2');
}
@font-face {
  font-family: 'Baloo 2'; font-style: normal; font-weight: 800; font-display: swap;
  src: url('fonts/baloo2-800.woff2') format('woff2');
}

:root {
  /* Paleta oficial CumpleClick (design/tokens.css) */
  --primary: #8B5CF6;        /* Violeta Globo */
  --primary-dark: #6d3fd4;
  --primary-soft: #EDE4FB;
  --cta: #D6307F;            /* Fucsia Click — acción principal */
  --cta-dark: #B02566;
  --accent: #FBBF24;         /* Amarillo Lente */
  --bg: #FFF8EC;             /* Crema */
  --bg2: #F6EFFF;
  --text: #4C2882;           /* Tinta Violeta */
  --text-muted: #7c6a9c;
  --card-bg: #ffffff;
  --border: #E9D8FD;
  --shadow: 0 1px 2px rgba(76,40,130,.06), 0 6px 20px rgba(139,92,246,.10);
  --shadow-hover: 0 4px 8px rgba(76,40,130,.08), 0 14px 32px rgba(139,92,246,.20);
  --danger: #DC2626;
  --danger-soft: #FEE2E2;
  --success: #16A34A;
  --success-soft: #DCFCE7;
  --warn: #B45309;
  --warn-soft: #FEF3C7;
  --radius: 20px;
  --radius-sm: 12px;
  --font-display: 'Baloo 2', 'Segoe UI Rounded', 'Segoe UI', system-ui, sans-serif;
  --font-body: 'Baloo 2', 'Segoe UI', system-ui, -apple-system, sans-serif;
}

* { box-sizing: border-box; }
html, body { margin: 0; padding: 0; }
body {
  color: var(--text);
  font-family: var(--font-body);
  line-height: 1.55;
  /* fondo cálido con un leve degradado + puntos de confeti muy sutiles (CSS puro,
     cero peticiones de red, decorativo sin distraer del contenido) */
  background-color: var(--bg);
  background-image:
    radial-gradient(circle at 8px 8px, rgba(139,92,246,.08) 1.6px, transparent 1.6px),
    radial-gradient(circle at 26px 26px, rgba(251,191,36,.10) 1.6px, transparent 1.6px),
    linear-gradient(160deg, var(--bg) 0%, var(--bg2) 100%);
  background-size: 34px 34px, 34px 34px, 100% 100%;
  background-attachment: fixed;
  min-height: 100vh;
}
h1, h2, h3, .logo, .login-logo { font-family: var(--font-display); font-weight: 600; }
a { color: var(--primary); }
code { background: var(--primary-soft); padding: 1px 6px; border-radius: 5px; font-size: .88em; font-family: ui-monospace, 'SFMono-Regular', Consolas, monospace; }
small { color: var(--text-muted); }
.muted { color: var(--text-muted); }
.small { font-size: .85rem; }
.sr-only {
  position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px;
  overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0;
}

:focus-visible {
  outline: 3px solid var(--cta);
  outline-offset: 2px;
  border-radius: 4px;
}

.wrap { max-width: 1180px; margin: 0 auto; padding: 24px 18px 64px; }

/* --- Header --- */
.topbar {
  display: flex; align-items: center; gap: 18px; flex-wrap: wrap;
  padding: 6px 4px; margin-bottom: 22px;
}
.logo {
  display: inline-flex; align-items: center; gap: 10px;
  font-size: 1.35rem; color: var(--text); white-space: nowrap;
}
.logo-mark {
  display: inline-flex; align-items: center; justify-content: center;
  width: 38px; height: 38px; border-radius: 12px; flex-shrink: 0;
  background: linear-gradient(135deg, var(--primary), #a855f7);
  color: #fff; box-shadow: 0 4px 12px rgba(139,92,246,.35);
}
.logo span { color: var(--cta); }

.kpis { display: flex; gap: 12px; flex-wrap: wrap; margin-left: auto; }
.kpi-card {
  display: flex; align-items: center; gap: 10px;
  background: var(--card-bg); border-radius: var(--radius-sm);
  box-shadow: var(--shadow); padding: 10px 16px 10px 12px;
}
.kpi-icon {
  display: flex; align-items: center; justify-content: center;
  width: 34px; height: 34px; border-radius: 10px;
  background: var(--primary-soft); color: var(--primary); flex-shrink: 0;
}
.kpi-card:nth-of-type(2) .kpi-icon { background: #FCE7F0; color: var(--cta-dark); }
.kpi-text { display: flex; flex-direction: column; line-height: 1.15; }
.kpi-text strong { font-family: var(--font-display); font-size: 1.25rem; color: var(--text); }
.kpi-text span { font-size: .74rem; color: var(--text-muted); white-space: nowrap; font-weight: 600; }

.btn-ghost.logout-btn { background: transparent; box-shadow: none; }
.btn-ghost.logout-btn:hover { background: var(--primary-soft); }

/* --- Tabs (segmented control) --- */
.tabs {
  display: inline-flex; gap: 4px; margin: 0 0 22px; flex-wrap: wrap;
  background: var(--primary-soft); padding: 5px; border-radius: 999px;
}
.tab {
  display: inline-flex; align-items: center; gap: 8px;
  padding: 9px 18px; min-height: 40px;
  border-radius: 999px; text-decoration: none; color: var(--text);
  font-weight: 700; font-size: .92rem; cursor: pointer;
  transition: background .2s, box-shadow .2s, color .2s;
}
.tab:hover { background: rgba(139,92,246,.1); }
.tab.active { background: var(--card-bg); color: var(--primary); box-shadow: var(--shadow); }
.tab.active:hover { background: var(--card-bg); }

main { display: flex; flex-direction: column; gap: 20px; }

.card {
  background: var(--card-bg);
  border-radius: var(--radius);
  box-shadow: var(--shadow);
  padding: 22px 24px;
}
.card h2 { margin: 0 0 6px; color: var(--text); font-size: 1.3rem; }
.card > p.muted:first-of-type { margin-top: 0; }

.alert {
  display: flex; align-items: flex-start; gap: 10px;
  padding: 13px 16px; border-radius: var(--radius-sm);
  font-size: .92rem; font-weight: 700;
}
.alert ul { margin: 4px 0 0; padding-left: 18px; font-weight: 500; }
.alert-ok { background: var(--success-soft); color: #14532d; }
.alert-error { background: var(--danger-soft); color: #7f1d1d; }
.alert svg { flex-shrink: 0; margin-top: 2px; }

/* --- Botones --- */
.btn {
  display: inline-flex; align-items: center; justify-content: center; gap: 8px;
  min-height: 44px; padding: 0 18px;
  border-radius: 999px; border: none; cursor: pointer;
  font-size: .95rem; font-weight: 700; font-family: var(--font-body);
  text-decoration: none; white-space: nowrap;
  transition: background .2s, box-shadow .2s, transform .15s, color .2s;
}
.btn:active { transform: scale(.97); }
.btn-primary { background: var(--primary); color: #fff; }
.btn-primary:hover { background: var(--primary-dark); box-shadow: var(--shadow-hover); }
.btn-cta { background: var(--cta); color: #fff; box-shadow: 0 4px 14px rgba(214,48,127,.35); }
.btn-cta:hover { background: var(--cta-dark); box-shadow: 0 6px 20px rgba(214,48,127,.45); }
.btn-ghost { background: var(--primary-soft); color: var(--text); }
.btn-ghost:hover { background: #e2d3f8; }
.btn-danger { background: var(--danger-soft); color: var(--danger); }
.btn-danger:hover { background: #fecaca; }
.btn-block { width: 100%; }
.btn-icon {
  min-width: 44px; padding: 0 12px;
  background: var(--primary-soft); color: var(--text);
}
.btn-icon:hover { background: #e2d3f8; }
.btn-icon.copied { background: var(--success-soft); color: var(--success); }

/* --- Login --- */
.login-body {
  min-height: 100vh; display: flex; align-items: center; justify-content: center;
  padding: 20px;
}
.login-card {
  background: var(--card-bg); border-radius: var(--radius); box-shadow: var(--shadow-hover);
  padding: 40px 30px; width: 100%; max-width: 360px;
  display: flex; flex-direction: column; gap: 20px;
}
.login-logo { display: flex; flex-direction: column; align-items: center; gap: 12px; font-size: 1.3rem; text-align: center; }
.login-logo span { color: var(--cta); }
.login-form { display: flex; flex-direction: column; gap: 10px; }
.login-form label { font-weight: 700; font-size: .9rem; }
.input-icon {
  display: flex; align-items: center; gap: 10px;
  border: 1.5px solid var(--border); border-radius: var(--radius-sm);
  padding: 0 12px; background: #fff;
  transition: border-color .2s;
}
.input-icon:focus-within { border-color: var(--primary); }
.input-icon svg { color: var(--text-muted); flex-shrink: 0; }
.input-icon input {
  border: none; outline: none; padding: 12px 0; flex: 1;
  font-size: 1rem; font-family: var(--font-body); min-height: 44px; background: transparent;
}

/* --- Formulario de fiesta --- */
.form-card { max-width: 640px; }
.party-form { display: flex; flex-direction: column; gap: 16px; }
.field { display: flex; flex-direction: column; gap: 6px; }
.field label { font-weight: 700; font-size: .92rem; }
.field small { font-size: .8rem; }
.field input[type="text"],
.field input[type="date"],
.field select,
.field textarea {
  border: 1.5px solid var(--border); border-radius: var(--radius-sm);
  padding: 10px 14px; font-size: 1rem; font-family: var(--font-body);
  min-height: 44px; background: #fff; color: var(--text);
  transition: border-color .2s, box-shadow .2s;
}
.field textarea { min-height: 120px; resize: vertical; }
.field input:focus, .field select:focus, .field textarea:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(139,92,246,.12); }
.field input[readonly] { background: #f3ecfb; color: var(--text-muted); }
.checkbox-field {
  display: flex; align-items: center; gap: 10px; font-weight: 700; cursor: pointer;
}
.checkbox-field input { width: 20px; height: 20px; cursor: pointer; accent-color: var(--primary); }
.form-actions { display: flex; gap: 10px; flex-wrap: wrap; }

/* --- Lista de fiestas --- */
.list-header {
  display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;
  margin-bottom: 4px;
}
.list-header h2 { margin: 0; }
.empty-state {
  text-align: center; color: var(--text-muted); padding: 52px 24px;
  display: flex; flex-direction: column; align-items: center; gap: 14px;
}
.empty-state .empty-icon {
  display: flex; align-items: center; justify-content: center;
  width: 64px; height: 64px; border-radius: 50%;
  background: var(--primary-soft); color: var(--primary);
}
.empty-state p { margin: 0; font-size: .98rem; }

.party-list { display: flex; flex-direction: column; gap: 14px; }
.party-card {
  display: flex; flex-direction: column; gap: 14px;
  transition: box-shadow .2s, transform .2s;
  border-top: 3px solid var(--chip-accent, var(--primary));
}
.party-card:hover { box-shadow: var(--shadow-hover); transform: translateY(-1px); }
.party-main { display: flex; flex-direction: column; gap: 4px; }
.party-title { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
.party-title strong { font-family: var(--font-display); font-weight: 600; font-size: 1.1rem; }

.chip {
  display: inline-flex; align-items: center; gap: 6px;
  padding: 4px 12px; border-radius: 999px; font-size: .8rem; font-weight: 700;
  background: var(--primary-soft);
  color: var(--text);
  border: 1px solid var(--chip-color);
}
.badge {
  display: inline-flex; align-items: center; gap: 5px;
  padding: 4px 10px; border-radius: 999px; font-size: .78rem; font-weight: 700;
}
.badge-ok { background: var(--success-soft); color: var(--success); }
.badge-off { background: #f1f1f4; color: #6b7280; }
.badge-warn { background: var(--warn-soft); color: var(--warn); }

.party-url { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.party-url input {
  flex: 1; min-width: 180px; min-height: 44px;
  border: 1.5px solid var(--border); border-radius: var(--radius-sm);
  padding: 0 12px; font-size: .88rem; color: var(--text-muted);
  background: #f9f6fd; font-family: ui-monospace, Consolas, monospace;
}

.party-actions { display: flex; gap: 8px; flex-wrap: wrap; }
.inline-form { display: inline-flex; margin: 0; }

/* --- Temáticas: grid de tarjetas (reemplaza la tabla ancha) --- */
.themes-intro { margin: 0 0 18px; }
.themes-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
  gap: 16px;
}
.theme-card {
  background: var(--card-bg); border-radius: var(--radius);
  box-shadow: var(--shadow); padding: 18px 20px;
  border-left: 5px solid var(--chip-color, var(--primary));
  display: flex; flex-direction: column; gap: 12px;
  transition: box-shadow .2s;
}
.theme-card:hover { box-shadow: var(--shadow-hover); }
.theme-card-head { display: flex; flex-direction: column; gap: 3px; }
.theme-card-head .chip { align-self: flex-start; }
.theme-franchise { font-family: var(--font-display); font-size: 1.08rem; display: flex; align-items: baseline; gap: 6px; flex-wrap: wrap; }
.theme-generic { font-family: var(--font-body); font-size: .82rem; font-weight: 600; color: var(--text-muted); }
.theme-slug { font-size: .76rem; color: var(--text-muted); font-family: ui-monospace, Consolas, monospace; }
.theme-meta { font-size: .8rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase; letter-spacing: .03em; }

.personajes-row { display: flex; gap: 6px; flex-wrap: wrap; }
.personaje-pill {
  display: inline-flex; align-items: center; justify-content: center;
  width: 30px; height: 30px; border-radius: 50%;
  background: var(--bg); font-size: 1.05rem; cursor: default;
}

.theme-status-row { display: flex; align-items: center; }
.missing-details { width: 100%; }
.missing-details summary {
  list-style: none; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;
}
.missing-details summary::-webkit-details-marker { display: none; }
.missing-details[open] summary .badge-warn { border-radius: 999px 999px 0 0; }
.missing-list {
  margin: 0; padding: 8px 12px; font-size: .78rem; color: var(--warn);
  background: var(--warn-soft); border-radius: 0 0 var(--radius-sm) var(--radius-sm);
  list-style: none; display: flex; flex-wrap: wrap; gap: 5px 10px;
}
.missing-list li::before { content: "· "; }

.theme-upload-form { display: flex; flex-direction: column; gap: 8px; align-items: flex-start; padding-top: 4px; border-top: 1px dashed var(--border); }
.theme-upload-form input[type="file"] {
  font-size: .78rem; width: 100%; color: var(--text); font-family: var(--font-body);
}
.upload-names { font-size: .78rem; color: var(--text-muted); width: 100%; }
.upload-names summary { cursor: pointer; font-weight: 700; color: var(--primary); list-style: none; }
.upload-names summary::-webkit-details-marker { display: none; }
.upload-names summary::before { content: "▸ "; }
.upload-names[open] summary::before { content: "▾ "; }
.upload-names ul { margin: 6px 0 0; padding-left: 4px; list-style: none; }
.upload-names li { margin-bottom: 3px; }
.upload-names code { font-size: .78rem; }
.btn-sm { min-height: 36px; padding: 0 16px; font-size: .85rem; }

.upload-limits { display: inline-flex; align-items: center; gap: 6px; }

/* --- Ficha de temática: inventario visual + prompts privados --- */
.theme-detail-link { align-self: flex-start; }
.theme-detail-head {
  display: flex; align-items: flex-end; justify-content: space-between; gap: 18px;
  padding: 8px 4px 14px; border-bottom: 4px solid var(--chip-color, var(--primary));
}
.theme-detail-head h2 { margin: 6px 0 0; font-family: var(--font-display); font-size: clamp(1.55rem, 3vw, 2.2rem); color: var(--text); }
.theme-detail-head p { margin: 3px 0 0; }
.detail-back { color: var(--primary); font-weight: 800; text-decoration: none; font-size: .88rem; }
.detail-back:hover { text-decoration: underline; }
.detail-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
.detail-stat {
  min-height: 92px; padding: 16px; border: 1px solid #ede9fe; border-radius: 16px;
  background: rgba(255,255,255,.92); box-shadow: var(--shadow);
  display: flex; flex-direction: column; justify-content: center; gap: 4px;
}
.detail-stat strong { color: var(--text); font-family: var(--font-display); font-size: 1.2rem; }
.detail-stat span { color: var(--text-muted); font-size: .78rem; font-weight: 700; }
.camouflage-note { display: flex; align-items: flex-start; gap: 14px; background: #fff7ed; border: 1px solid #fed7aa; }
.camouflage-note > svg { color: #c2410c; flex: 0 0 auto; margin-top: 2px; }
.camouflage-note strong { color: #9a3412; }
.camouflage-note p { margin: 3px 0 0; color: #7c2d12; font-size: .88rem; }
.production-studio {
  display: grid; grid-template-columns: minmax(240px, .8fr) minmax(420px, 1.4fr);
  align-items: center; gap: 26px; overflow: hidden;
  color: #fff; border: 0;
  background:
    radial-gradient(circle at 92% -20%, rgba(255,255,255,.24), transparent 36%),
    linear-gradient(135deg, #35105f 0%, #7c2fa2 48%, #da297a 100%);
}
.production-studio h2 { margin: 4px 0 6px; color: #fff; }
.production-studio p { margin: 0; color: rgba(255,255,255,.82); font-size: .9rem; line-height: 1.45; }
.production-studio .eyebrow { color: #ffd44d; font-size: .7rem; font-weight: 900; letter-spacing: .16em; }
.production-steps {
  list-style: none; margin: 0; padding: 0; display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px;
}
.production-steps li {
  position: relative; min-height: 104px; padding: 12px 8px;
  display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 7px;
  text-align: center; border: 1px solid rgba(255,255,255,.24); border-radius: 16px;
  background: rgba(255,255,255,.1); backdrop-filter: blur(8px);
}
.production-steps li:not(:last-child)::after {
  content: '›'; position: absolute; right: -9px; z-index: 2; color: #ffd44d;
  font-size: 1.5rem; font-weight: 900;
}
.production-steps strong {
  width: 30px; height: 30px; display: grid; place-items: center;
  border-radius: 50%; color: #35105f; background: #ffd44d;
}
.production-steps span { font-size: .73rem; line-height: 1.3; font-weight: 800; }
.detail-section-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 18px; margin-bottom: 20px; }
.detail-section-head p { margin: 4px 0 0; }
.detail-upload-form { display: flex; align-items: center; justify-content: flex-end; gap: 8px; flex-wrap: wrap; }
.detail-upload-form input[type="file"] { max-width: 260px; font: inherit; font-size: .78rem; }
.asset-filters { display: flex; flex-wrap: wrap; gap: 8px; margin: -4px 0 18px; }
.asset-filter {
  min-height: 38px; padding: 0 14px; border: 1px solid #ddd6fe; border-radius: 999px;
  background: #fff; color: var(--text-muted); font: 800 .76rem var(--font); cursor: pointer;
}
.asset-filter:hover, .asset-filter.active { color: #fff; border-color: var(--primary); background: var(--primary); }
.asset-detail-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(360px, 1fr)); gap: 18px; }
.asset-card[hidden] { display: none; }
.asset-card { overflow: hidden; border: 1px solid #e9dff7; border-radius: 18px; background: #fff; box-shadow: 0 7px 20px rgba(76,29,149,.08); }
.asset-card.asset-missing { border-style: dashed; }
.asset-preview { min-height: 220px; aspect-ratio: 16/10; background: linear-gradient(145deg, #f5f3ff, #fff7ed); display: grid; place-items: center; overflow: hidden; position: relative; }
.asset-preview img, .asset-preview video { width: 100%; height: 100%; object-fit: contain; background: #171026; }
.asset-preview audio { position: absolute; left: 14px; right: 14px; bottom: 14px; width: calc(100% - 28px); }
.asset-media-placeholder { display: flex; flex-direction: column; align-items: center; gap: 8px; color: var(--text-muted); font-size: .82rem; font-weight: 800; }
.asset-media-placeholder svg { width: 36px; height: 36px; opacity: .65; }
.asset-card-body { padding: 16px; }
.asset-title-row { display: flex; align-items: center; justify-content: space-between; gap: 10px; }
.asset-title-row > div { min-width: 0; display: grid; gap: 3px; }
.asset-group-label { color: var(--primary); font-size: .61rem; font-weight: 900; letter-spacing: .1em; }
.asset-title-row code { overflow-wrap: anywhere; color: var(--text); font-weight: 800; }
.asset-human-label { margin: 5px 0 0; color: var(--text-muted); font-size: .82rem; font-weight: 700; }
.asset-meta-list { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px 14px; margin: 14px 0; padding: 12px; border-radius: 12px; background: #faf7ff; }
.asset-meta-list div { min-width: 0; }
.asset-meta-list dt { color: var(--text-muted); font-size: .68rem; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; }
.asset-meta-list dd { margin: 2px 0 0; color: var(--text); font-size: .78rem; overflow-wrap: anywhere; }
.asset-slot-upload {
  display: grid; grid-template-columns: minmax(0, 1fr) auto; align-items: end; gap: 8px;
  margin: 0 0 14px; padding: 12px; border: 1px solid #e9d5ff; border-radius: 13px;
  background: linear-gradient(135deg, #faf5ff, #fff7ed);
}
.asset-slot-upload label { min-width: 0; display: grid; gap: 5px; color: var(--text); font-size: .75rem; font-weight: 800; }
.asset-slot-upload input[type="file"] { min-width: 0; width: 100%; font: .72rem var(--font); }
.prompt-form { display: flex; flex-direction: column; gap: 7px; border-top: 1px solid var(--border); padding-top: 14px; }
.prompt-form label { font-size: .84rem; font-weight: 800; color: var(--text); }
.prompt-form textarea {
  width: 100%; min-height: 150px; padding: 11px 12px; resize: vertical;
  border: 1.5px solid var(--border); border-radius: 12px; background: #fff;
  color: var(--text); font: .82rem/1.5 ui-monospace, Consolas, monospace;
}
.prompt-form textarea:focus { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(139,92,246,.12); outline: none; }
.prompt-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.asset-no-prompt { border-top: 1px solid var(--border); margin: 14px 0 0; padding-top: 12px; }
.prompt-history { margin-top: 10px; border-top: 1px dashed var(--border); padding-top: 10px; }
.prompt-history summary { cursor: pointer; font-size: .82rem; font-weight: 800; color: var(--primary); list-style: none; }
.prompt-history summary::-webkit-details-marker { display: none; }
.prompt-history summary::before { content: '▸ '; }
.prompt-history[open] summary::before { content: '▾ '; }
.prompt-history-list { list-style: none; margin: 10px 0 0; padding: 0; display: flex; flex-direction: column; gap: 10px; max-height: 260px; overflow-y: auto; }
.prompt-history-list li { background: var(--bg2); border-radius: var(--radius-sm); padding: 10px 12px; }
.prompt-history-meta { display: flex; align-items: center; gap: 8px; margin-bottom: 4px; }
.prompt-history-text { margin: 0 0 8px; font: .78rem/1.4 ui-monospace, Consolas, monospace; color: var(--text-muted); white-space: pre-wrap; word-break: break-word; }

/* --- Responsive: a 480px las cards apilan --- */
@media (max-width: 480px) {
  .topbar { flex-direction: column; align-items: flex-start; }
  .kpis { margin-left: 0; width: 100%; }
  .kpi-card { flex: 1; }
  .party-title, .party-url, .party-actions { flex-direction: column; align-items: stretch; }
  .party-url input { min-width: 0; }
  .btn { width: 100%; }
  .inline-form { width: 100%; }
  .inline-form .btn { width: 100%; }
  .themes-grid { grid-template-columns: 1fr; }
  .theme-detail-head, .detail-section-head { flex-direction: column; align-items: stretch; }
  .detail-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .asset-detail-grid { grid-template-columns: 1fr; }
  .detail-upload-form { justify-content: flex-start; }
  .production-steps { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .production-steps li:nth-child(2)::after { display: none; }
  .asset-slot-upload { grid-template-columns: 1fr; }
}

@media (max-width: 820px) and (min-width: 481px) { .detail-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 900px) { .production-studio { grid-template-columns: 1fr; } }

/* Calibrador visual persistente de frameBox. */
.frame-calibrator { border: 1px solid #ddd6fe; border-radius: 16px; padding: 16px; }
.frame-calibrator legend { font-weight: 800; color: #4C1D95; padding: 0 8px; }
.frame-preview { position: relative; width: min(280px, 100%); aspect-ratio: 9/16; margin: 12px auto; background-size: cover; background-position: center; border-radius: 14px; overflow: hidden; box-shadow: inset 0 0 0 2px #ffffff99; }
.frame-preview span { position: absolute; border: 3px solid var(--cta); border-radius: 4px; background: rgba(214,48,127,.2); box-shadow: 0 0 0 2px #fff; pointer-events: none; }
.frame-grid { display: grid; grid-template-columns: repeat(4, minmax(72px, 1fr)); gap: 10px; }
.frame-grid label { display: grid; gap: 4px; font-size: .8rem; font-weight: 700; }
.frame-grid input { width: 100%; }
@media (max-width: 520px) { .frame-grid { grid-template-columns: repeat(2, 1fr); } }

/* --- Modal vista previa de imágenes en inventario de assets --- */
.asset-preview { cursor: pointer; transition: opacity .15s; }
.asset-preview:hover { opacity: .88; }
.asset-preview:has(audio) { cursor: default; }
.asset-preview:has(audio):hover { opacity: 1; }

.modal-overlay {
  position: fixed; inset: 0; z-index: 1000;
  background: rgba(23, 18, 38, .82);
  display: flex; align-items: center; justify-content: center;
  padding: 22px;
  animation: modal-fade-in .18s ease;
}
@keyframes modal-fade-in { from { opacity: 0; } to { opacity: 1; } }

.modal-content {
  position: relative; max-width: 92vw; max-height: 92vh;
  background: #171026; border-radius: var(--radius);
  overflow: hidden; box-shadow: 0 22px 64px rgba(0,0,0,.55);
}
.modal-content img {
  display: block; max-width: 92vw; max-height: 88vh;
  object-fit: contain; margin: 0 auto;
}
.modal-content video {
  display: block; max-width: 92vw; max-height: 88vh;
  margin: 0 auto; background: #171026;
}

.modal-close {
  position: absolute; top: 10px; right: 10px; z-index: 10;
  width: 42px; height: 42px; border-radius: 50%;
  background: rgba(255,255,255,.12); color: #fff;
  border: none; font-size: 1.6rem; line-height: 1;
  cursor: pointer; display: grid; place-items: center;
  transition: background .2s;
  font-family: var(--font-body);
}
.modal-close:hover { background: rgba(255,255,255,.28); }

@media (max-width: 480px) {
  .modal-overlay { padding: 8px; }
  .modal-content { max-width: 98vw; max-height: 88vh; border-radius: var(--radius-sm); }
  .modal-content img { max-width: 98vw; max-height: 84vh; }
  .modal-content video { max-width: 98vw; max-height: 84vh; }
  .modal-close { width: 36px; height: 36px; top: 6px; right: 6px; }
}

/* --- Navegación prev/next dentro del modal --- */
.modal-nav {
  position: absolute; top: 50%; transform: translateY(-50%); z-index: 10;
  width: 44px; height: 56px; border-radius: 10px;
  background: rgba(255,255,255,.10); color: #fff;
  border: none; font-size: 2rem; cursor: pointer;
  display: grid; place-items: center;
  transition: background .2s;
  font-family: var(--font-body); line-height: 1;
}
.modal-nav:hover { background: rgba(255,255,255,.24); }
.modal-prev { left: 8px; }
.modal-next { right: 8px; }

@media (max-width: 480px) {
  .modal-nav { width: 36px; height: 46px; border-radius: 8px; font-size: 1.6rem; }
  .modal-prev { left: 4px; }
  .modal-next { right: 4px; }
}
