const { escapeHtml } = require('./escape');
const { STYLE, SCRIPT, backgroundStyle, logoMark, renderDeckControls } = require('./template');

// Plan de trabajo: el documento que acompaña al contrato de servicios firmado. Usa el mismo estilo, logo
// y modo presentación que la propuesta (piezas que exporta template.js, sin cambiarlas) y suma sus
// láminas propias. Todo texto que viene del plan pasa por escapeHtml.
// El archivo va por secciones; cada sección nueva se agrega justo antes del marcador que la sigue.

// === Fechas ===

// === Ayudas ===

// === Carta Gantt ===

// === Láminas ===

function renderPlanCover(data, imageUrl) {
  const proyecto = String(data.proyecto || '');
  const cliente = String(data.client_name || '').trim();
  const semanas = Math.round(Number(data.semanas) || 0);
  const detalle = [
    cliente ? `Preparado para ${escapeHtml(cliente)}` : '',
    semanas > 0 ? `${semanas} ${semanas === 1 ? 'semana estimada' : 'semanas estimadas'}` : '',
  ]
    .filter(Boolean)
    .join(' · ');
  return `
    <section class="slide slide-cover plan-cover">
      <div class="slide-bg" style="${backgroundStyle(imageUrl, 0, 'cover')}"></div>
      ${logoMark()}
      <div class="slide-body">
        <p class="eyebrow">${escapeHtml(String(data.company_name || ''))}</p>
        <h1${proyecto.length > 48 ? ' class="is-largo"' : ''}>Plan de trabajo — ${escapeHtml(proyecto)}</h1>
        ${detalle ? `<p class="lede">${detalle}</p>` : ''}
        <div class="accent-bar"></div>
      </div>
    </section>`;
}

const ESTILO_PORTADA = `
  .plan-cover h1 { font-size: 66px; line-height: 1.12; max-width: 86%; overflow-wrap: anywhere; }
  .plan-cover h1.is-largo { font-size: 52px; }
`;

// === Documento ===

function renderPlanHtml(data, images = {}) {
  const d = data && typeof data === 'object' ? data : {};
  const fotos = images && typeof images === 'object' ? images : {};
  const preload = fotos.cover ? `<link rel="preload" as="image" href="${escapeHtml(fotos.cover)}" />` : '';
  return `<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<title>Plan de trabajo · ${escapeHtml(String(d.proyecto || ''))}</title>
${preload}
<style>${STYLE}${ESTILO_PORTADA}</style>
</head>
<body${d.draft ? ' class="is-draft"' : ''}>
${d.draft ? '<div class="at-draft-badge">Borrador · vista previa sin fotos</div>' : ''}
<div class="deck">
${renderPlanCover(d, fotos.cover)}
</div>
${renderDeckControls()}
<script>${SCRIPT}</script>
</body>
</html>`;
}

module.exports = { renderPlanHtml };
