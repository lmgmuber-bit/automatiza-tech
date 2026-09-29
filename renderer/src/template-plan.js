const { escapeHtml } = require('./escape');
const { STYLE, SCRIPT, backgroundStyle, logoMark, renderDeckControls } = require('./template');

// Plan de trabajo: el documento que acompaña al contrato de servicios firmado. Usa el mismo estilo, logo
// y modo presentación que la propuesta (piezas que exporta template.js, sin cambiarlas) y suma sus
// láminas propias. Todo texto que viene del plan pasa por escapeHtml.
// El archivo va por secciones; cada sección nueva se agrega justo antes del marcador que la sigue.

const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
const MESES_CORTOS = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
const DIA_MS = 86400000;

// Las fechas llegan como 'YYYY-MM-DD' y se leen en UTC: así el resultado no depende de la zona horaria
// del servidor (Easypanel corre en UTC, un equipo en Chile no). Una fecha imposible (2026-02-30) es null.
function leerFecha(fecha) {
  if (typeof fecha !== 'string' || !/^\d{4}-\d{2}-\d{2}$/.test(fecha)) return null;
  const d = new Date(`${fecha}T00:00:00Z`);
  if (Number.isNaN(d.getTime()) || d.toISOString().slice(0, 10) !== fecha) return null;
  return d;
}

function lunesDe(fecha) {
  const d = leerFecha(fecha);
  if (!d) return '';
  const desdeLunes = (d.getUTCDay() + 6) % 7; // 0 = lunes … 6 = domingo
  return new Date(d.getTime() - desdeLunes * DIA_MS).toISOString().slice(0, 10);
}

// Días de calendario de `desde` a `hasta` (negativo si `hasta` es anterior); NaN si alguna no es fecha.
function diasEntre(desde, hasta) {
  const a = leerFecha(desde);
  const b = leerFecha(hasta);
  if (!a || !b) return NaN;
  return Math.round((b.getTime() - a.getTime()) / DIA_MS);
}

// Semana (0 = la del lunes de inicio) en que cae `fecha`; las semanas van de lunes a domingo.
function semanaIndice(fecha, lunesInicio) {
  const n = diasEntre(lunesInicio, fecha);
  return Number.isNaN(n) ? NaN : Math.floor(n / 7);
}

function fechaLarga(fecha) {
  const d = leerFecha(fecha);
  return d ? `${d.getUTCDate()} de ${MESES[d.getUTCMonth()]} de ${d.getUTCFullYear()}` : '';
}

function fechaCorta(fecha) {
  const d = leerFecha(fecha);
  return d ? `${d.getUTCDate()} ${MESES_CORTOS[d.getUTCMonth()]}` : '';
}

function rangoCorto(desde, hasta) {
  const a = leerFecha(desde);
  const b = leerFecha(hasta);
  if (!a) return '';
  if (!b || desde === hasta) return fechaCorta(desde);
  if (a.getUTCFullYear() === b.getUTCFullYear() && a.getUTCMonth() === b.getUTCMonth()) {
    return `${a.getUTCDate()} – ${fechaCorta(hasta)}`;
  }
  return `${fechaCorta(desde)} – ${fechaCorta(hasta)}`;
}

// === Ayudas ===

const RESPONSABLES = { at: 'AutomatizaTech', cliente: 'Tú', ambos: 'Ambos' };
const FASES = { diseno_desarrollo: 'Diseño y desarrollo', implementacion: 'Implementación', soporte: 'Soporte y mejora continua' };

// Un responsable desconocido se muestra como AutomatizaTech: WordPress ya los valida, esto es la red.
function responsableClave(r) {
  return Object.prototype.hasOwnProperty.call(RESPONSABLES, r) ? r : 'at';
}

function diasTexto(n) {
  const k = Math.max(0, Math.round(Number(n) || 0));
  return k === 1 ? '1 día hábil' : `${k} días hábiles`;
}

function numero(n) {
  return String(n).padStart(2, '0');
}

// Solo enlaces https sin comillas ni espacios: un portal_url o un enlace de agenda raro (javascript:,
// http:, vacío) no se vuelve un enlace; la lámina sale igual, sin él.
function urlSegura(url) {
  const u = typeof url === 'string' ? url.trim() : '';
  return /^https:\/\/[^\s"'<>]+$/.test(u) ? u : '';
}

function texto(v) {
  return typeof v === 'string' ? v : v === null || v === undefined ? '' : String(v);
}

// Fondo de las láminas de contenido: la foto queda a la derecha y el texto sobre la parte oscura. Sin
// foto (vista previa del borrador), el mismo degradado de marca que la propuesta.
function fondoContenido(imageUrl, index) {
  if (!imageUrl) return backgroundStyle('', index);
  return `background-image: linear-gradient(100deg, rgba(13,27,42,.97) 0%, rgba(13,27,42,.95) 55%, rgba(13,27,42,.7) 74%, rgba(13,27,42,.32) 100%), url('${escapeHtml(
    imageUrl
  )}'); background-size: cover; background-position: center;`;
}

// Fondo de las láminas anchas (Método y carta Gantt): la foto apenas se asoma, porque el contenido ocupa
// todo el ancho y tiene que leerse sin pelear con ella.
function fondoPanel(imageUrl, index) {
  if (!imageUrl) return backgroundStyle('', index);
  return `background-image: linear-gradient(180deg, rgba(10,20,32,.88) 0%, rgba(10,20,32,.94) 100%), url('${escapeHtml(
    imageUrl
  )}'); background-size: cover; background-position: center;`;
}

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

module.exports = { renderPlanHtml, lunesDe, semanaIndice, diasEntre, fechaLarga, fechaCorta, rangoCorto, diasTexto, urlSegura };
