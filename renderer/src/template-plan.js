const { escapeHtml, linkify } = require('./escape');
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

// Alto (px) que la carta tiene para filas dentro de la lámina de 1920×1080, después del título, la
// cabecera de semanas y la leyenda. Se reparte entre las filas: con pocas barras cada fila es alta y
// cómoda; con muchas se achica hasta caber. Nunca se sale de la lámina, ni en pantalla ni en el PDF.
const GANTT_ALTO = 660;
const GANTT_ALTO_FASE = 34;
const GANTT_ALTO_HITOS = 72;
// Ancho aproximado (px) de la pista de barras y de una letra de la etiqueta de un hito (16 px, negrita).
const GANTT_PISTA = 1300;
const GANTT_LETRA = 9.5;

function medidasGantt(nFilas, nFases, conHitos) {
  const libre = GANTT_ALTO - nFases * GANTT_ALTO_FASE - (conHitos ? GANTT_ALTO_HITOS : 0);
  const fila = Math.max(1, Math.min(52, Math.floor(libre / Math.max(1, nFilas))));
  return { fila, denso: fila < 34 };
}

// Una fila por bloque de trabajo. La revisión del cliente va en la MISMA fila que el bloque que revisa
// (el de su fase que terminó justo antes de que ella empiece), así 16 barras son 10 filas y no 16. Una
// revisión sin bloque previo queda en su propia fila. Las barras con fechas inválidas se ignoran.
function filasGantt(cronograma) {
  const barras = (cronograma && Array.isArray(cronograma.barras) ? cronograma.barras : []).filter(
    (b) => b && typeof b === 'object' && leerFecha(b.desde) && leerFecha(b.hasta) && b.hasta >= b.desde
  );
  const trabajos = [];
  const sueltas = [];
  barras.forEach((b, orden) => {
    if (b.tipo !== 'revision') trabajos.push({ fase: String(b.fase || ''), trabajo: b, revision: null, orden });
  });
  barras.forEach((b, orden) => {
    if (b.tipo !== 'revision') return;
    let elegido = null;
    for (const f of trabajos) {
      if (f.fase !== String(b.fase || '') || f.revision || f.trabajo.hasta >= b.desde) continue;
      if (!elegido || f.trabajo.hasta > elegido.trabajo.hasta) elegido = f;
    }
    if (elegido) elegido.revision = b;
    else sueltas.push({ fase: String(b.fase || ''), trabajo: null, revision: b, orden });
  });
  const filas = trabajos.concat(sueltas).sort((a, b) => a.orden - b.orden);
  const grupos = [];
  for (const f of filas) {
    let g = grupos.find((x) => x.fase === f.fase);
    if (!g) {
      g = { fase: f.fase, titulo: FASES[f.fase] || f.fase || 'Otras actividades', filas: [] };
      grupos.push(g);
    }
    g.filas.push(f);
  }
  return grupos;
}

function porcentaje(x) {
  return `${+(Math.max(0, Math.min(1, x)) * 100).toFixed(3)}%`;
}

// Posición de una barra por días de calendario: un bloque de lunes a viernes cubre 5/7 de su semana y
// el fin de semana queda a la vista como el hueco entre barras.
function posicion(desde, hasta, lunes0, totalDias) {
  const ini = Math.max(0, Math.min(totalDias, diasEntre(lunes0, desde)));
  const fin = Math.max(ini, Math.min(totalDias, diasEntre(lunes0, hasta) + 1));
  return `left:${porcentaje(ini / totalDias)};width:${porcentaje(Math.max(fin - ini, 0.35) / totalDias)}`;
}

// Reparte las etiquetas de los hitos en dos carriles (arriba y abajo del rombo) para que no se pisen: cada
// etiqueta va al primer carril libre en su tramo; si no cabe a la derecha del rombo, va a su izquierda. Si
// no queda carril libre (tres hitos pegados), se corre a la derecha hasta donde termina la anterior.
function carrilesHitos(hitos, xs) {
  const fin = [-Infinity, -Infinity];
  return hitos.map((h, i) => {
    const ancho = ((texto(h.nombre).length + 9) * GANTT_LETRA) / GANTT_PISTA;
    const izq = xs[i] + ancho + 0.012 > 1;
    const desde = izq ? xs[i] - ancho : xs[i];
    const carril = fin[0] <= desde ? 0 : fin[1] <= desde ? 1 : fin[0] <= fin[1] ? 0 : 1;
    const corrido = izq ? 0 : Math.max(0, Math.min(fin[carril] + 0.008 - desde, 1 - ancho - desde));
    fin[carril] = Math.max(fin[carril], izq ? xs[i] : desde + corrido + ancho);
    return { carril, izq, corrido: Math.round(corrido * GANTT_PISTA) };
  });
}

function renderGantt(cronograma) {
  const c = cronograma && typeof cronograma === 'object' ? cronograma : {};
  const lunes0 = lunesDe(c.inicio);
  const ultima = semanaIndice(c.fin, lunes0);
  const semanas = Number.isNaN(ultima) || ultima < 0 ? 1 : ultima + 1;
  const totalDias = semanas * 7;
  const grupos = lunes0 ? filasGantt(c) : [];
  const hitos = lunes0
    ? (Array.isArray(c.hitos) ? c.hitos : [])
        .filter((h) => h && h.nombre && leerFecha(h.fecha))
        .sort((a, b) => (a.fecha < b.fecha ? -1 : a.fecha > b.fecha ? 1 : 0))
    : [];
  const nFilas = grupos.reduce((n, g) => n + g.filas.length, 0);
  const { fila, denso } = medidasGantt(nFilas, grupos.length, hitos.length > 0);
  const largo = semanas > 18;

  const cabecera = Array.from({ length: semanas }, (_, i) => {
    const d = lunes0 ? new Date(leerFecha(lunes0).getTime() + i * 7 * DIA_MS) : null;
    const fecha = !d ? '' : largo ? `${d.getUTCDate()}/${d.getUTCMonth() + 1}` : fechaCorta(d.toISOString().slice(0, 10));
    return `<span class="gantt-semana"><b>S${i + 1}</b><small>${escapeHtml(fecha)}</small></span>`;
  }).join('');

  const hitoX = (h) => (diasEntre(lunes0, h.fecha) + 1) / totalDias;
  const guias = hitos
    .map((h) => `<span class="gantt-guia${h.nombre === 'Entrega estimada' ? ' is-entrega' : ''}" style="left:${porcentaje(hitoX(h))}"></span>`)
    .join('');

  const cuerpo = grupos
    .map((g) => {
      const filasHtml = g.filas
        .map((f) => {
          const principal = f.trabajo || f.revision;
          const nombre = f.trabajo ? texto(f.trabajo.etiqueta) || 'Actividad' : 'Tu revisión';
          const rango = rangoCorto(principal.desde, (f.revision || principal).hasta);
          const barrasHtml = [];
          if (f.trabajo) {
            const r = responsableClave(f.trabajo.responsable);
            const titulo = `${nombre} · ${RESPONSABLES[r]} · ${rangoCorto(f.trabajo.desde, f.trabajo.hasta)}`;
            barrasHtml.push(
              `<span class="gantt-barra is-${r}" style="${posicion(f.trabajo.desde, f.trabajo.hasta, lunes0, totalDias)}" title="${escapeHtml(titulo)}"></span>`
            );
          }
          if (f.revision) {
            const titulo = `Tu revisión · ${rangoCorto(f.revision.desde, f.revision.hasta)}`;
            barrasHtml.push(
              `<span class="gantt-barra is-revision" style="${posicion(f.revision.desde, f.revision.hasta, lunes0, totalDias)}" title="${escapeHtml(titulo)}"></span>`
            );
          }
          const punto = f.trabajo ? responsableClave(f.trabajo.responsable) : 'revision';
          return `<div class="gantt-fila"><div class="gantt-etiqueta"><i class="gantt-punto is-${punto}"></i><b title="${escapeHtml(nombre)}">${escapeHtml(
            nombre
          )}</b><small>${escapeHtml(rango)}</small></div><div class="gantt-pista">${barrasHtml.join('')}</div></div>`;
        })
        .join('');
      return `<div class="gantt-fase">${escapeHtml(g.titulo)}</div>${filasHtml}`;
    })
    .join('');

  const xs = hitos.map(hitoX);
  const carriles = carrilesHitos(hitos, xs);
  const hitosHtml = hitos.length
    ? `<div class="gantt-fila gantt-fila-hitos"><div class="gantt-etiqueta"><b>Hitos</b></div><div class="gantt-pista">${hitos
        .map((h, i) => {
          const x = xs[i];
          const { carril, izq, corrido } = carriles[i];
          const clases = ['gantt-hito', h.nombre === 'Entrega estimada' ? 'is-entrega' : '', carril ? 'is-abajo' : 'is-arriba', izq ? 'is-izq' : '']
            .filter(Boolean)
            .join(' ');
          const estilo = `left:${porcentaje(x)}${corrido ? `;--corrido:${corrido}px` : ''}`;
          return `<span class="${clases}" style="${estilo}"><i></i><em>${escapeHtml(texto(h.nombre))} · ${escapeHtml(fechaCorta(h.fecha))}</em></span>`;
        })
        .join('')}</div></div>`
    : '';

  const vacio = nFilas ? '' : '<p class="gantt-vacio">Las fechas aparecen aquí cuando el plan tenga actividades.</p>';

  return `<div class="gantt${denso ? ' is-denso' : ''}${largo ? ' is-largo' : ''}" style="--semanas:${semanas};--fila:${fila}px">
      <div class="gantt-cabecera"><span class="gantt-esquina">Semana</span><div class="gantt-semanas">${cabecera}</div></div>
      <div class="gantt-cuerpo">
        <div class="gantt-rejilla" aria-hidden="true">${guias}</div>
        ${cuerpo}${hitosHtml}${vacio}
      </div>
      <div class="gantt-pie">
        <span class="gantt-leyenda"><i class="gantt-punto is-at"></i>AutomatizaTech</span>
        <span class="gantt-leyenda"><i class="gantt-punto is-cliente"></i>Tú</span>
        <span class="gantt-leyenda"><i class="gantt-punto is-ambos"></i>Ambos</span>
        <span class="gantt-leyenda"><i class="gantt-punto is-revision"></i>Tu revisión (5 días hábiles)</span>
        <span class="gantt-leyenda"><i class="gantt-rombo"></i>Hito</span>
        <span class="gantt-nota">Fechas estimadas, desde que recibimos el anticipo y tus insumos.</span>
      </div>
    </div>`;
}

function renderGanttSlide(data, index, imageUrl) {
  const c = data && data.cronograma && typeof data.cronograma === 'object' ? data.cronograma : {};
  const entrega = (Array.isArray(c.hitos) ? c.hitos : []).find((h) => h && h.nombre === 'Entrega estimada' && leerFecha(h.fecha));
  const lunes0 = lunesDe(c.inicio);
  const ultima = semanaIndice(c.fin, lunes0);
  const semanas = Number.isNaN(ultima) || ultima < 0 ? 0 : ultima + 1;
  const resumen = [
    leerFecha(c.inicio) ? `<span><small>Inicio estimado</small><b>${escapeHtml(fechaLarga(c.inicio))}</b></span>` : '',
    entrega ? `<span><small>Entrega estimada</small><b>${escapeHtml(fechaLarga(entrega.fecha))}</b></span>` : '',
    semanas ? `<span><small>Duración</small><b>${semanas} ${semanas === 1 ? 'semana' : 'semanas'}</b></span>` : '',
  ].join('');
  return `
    <section class="slide plan-panel plan-gantt">
      <div class="slide-bg" style="${fondoPanel(imageUrl, index)}"></div>
      ${logoMark()}
      <div class="panel-head plan-anima">
        <p class="eyebrow">${numero(index)} · Carta Gantt</p>
        <h2>Tu proyecto, semana a semana</h2>
      </div>
      <div class="panel-resumen">${resumen}</div>
      ${renderGantt(c)}
    </section>`;
}

const ESTILO_GANTT = `
  .panel-head { position: absolute; left: 300px; top: 52px; right: 800px; z-index: 2; }
  .panel-head h2 { color: #fff; font-size: 44px; font-weight: 800; margin-top: 8px; }
  .panel-resumen { position: absolute; top: 60px; right: 72px; z-index: 2; display: flex; gap: 34px; }
  .panel-resumen span { display: flex; flex-direction: column; gap: 4px; text-align: right; }
  .panel-resumen small { color: #9fb3c8; font-size: 15px; letter-spacing: .1em; text-transform: uppercase; }
  .panel-resumen b { color: #fff; font-size: 24px; font-weight: 700; }
  .plan-gantt .gantt { position: absolute; top: 196px; left: 72px; right: 72px; max-height: 844px; z-index: 2;
    display: flex; flex-direction: column; background: rgba(6,13,21,.72); border: 1px solid rgba(255,255,255,.1);
    border-radius: 18px; padding: 20px 26px 16px; }
  .gantt { --col: 420px; }
  .gantt-cabecera { display: flex; align-items: flex-end; height: 56px; flex: none; border-bottom: 1px solid rgba(255,255,255,.14); }
  .gantt-esquina { width: var(--col); flex: none; color: #9fb3c8; font-size: 15px; letter-spacing: .12em; text-transform: uppercase; padding-bottom: 10px; }
  .gantt-semanas { flex: 1; display: grid; grid-template-columns: repeat(var(--semanas), 1fr); min-width: 0; }
  .gantt-semana { display: flex; flex-direction: column; align-items: center; gap: 2px; padding-bottom: 8px; min-width: 0; overflow: hidden; }
  .gantt-semana b { color: #fff; font-size: 17px; font-weight: 700; }
  .gantt-semana small { color: #9fb3c8; font-size: 14px; white-space: nowrap; }
  .gantt.is-largo .gantt-semana b { font-size: 13px; }
  .gantt.is-largo .gantt-semana small { font-size: 11px; }
  .gantt-cuerpo { position: relative; flex: none; }
  .gantt-rejilla { position: absolute; top: 0; bottom: 0; left: var(--col); right: 0; pointer-events: none;
    background-image: linear-gradient(to right, rgba(255,255,255,.1) 0 1px, transparent 1px 71.4286%, rgba(255,255,255,.035) 71.4286% 100%);
    background-size: calc(100% / var(--semanas)) 100%; }
  .gantt-guia { position: absolute; top: 0; bottom: 0; width: 0; border-left: 2px dashed rgba(255,255,255,.28); }
  .gantt-guia.is-entrega { border-left-color: rgba(0,217,192,.75); }
  .gantt-fase { position: relative; height: 34px; display: flex; align-items: flex-end; padding-bottom: 5px; color: #00d9c0;
    font-size: 15px; font-weight: 800; letter-spacing: .14em; text-transform: uppercase; }
  .gantt-fila { position: relative; display: flex; height: var(--fila); }
  .gantt-etiqueta { width: var(--col); flex: none; display: flex; align-items: center; gap: 12px; padding-right: 16px; min-width: 0; overflow: hidden; }
  .gantt-etiqueta b { color: #fff; font-size: clamp(11px, calc(var(--fila) * .42), 21px); font-weight: 700; white-space: nowrap; overflow: hidden;
    text-overflow: ellipsis; min-width: 0; }
  .gantt-etiqueta small { margin-left: auto; flex: none; color: #9fb3c8; font-size: clamp(10px, calc(var(--fila) * .33), 16px); white-space: nowrap; }
  .gantt.is-denso .gantt-etiqueta small { display: none; }
  .gantt-pista { position: relative; flex: 1; min-width: 0; }
  .gantt-barra { position: absolute; top: 50%; height: max(4px, calc(var(--fila) * .56)); transform: translateY(-50%); border-radius: 7px; min-width: 4px; }
  .gantt-barra.is-at, .gantt-punto.is-at { background: #00d9c0; }
  .gantt-barra.is-cliente, .gantt-punto.is-cliente { background: #38bdf8; }
  .gantt-barra.is-ambos, .gantt-punto.is-ambos { background: #a78bfa; }
  .gantt-barra.is-revision, .gantt-punto.is-revision {
    background: repeating-linear-gradient(135deg, rgba(245,158,11,.95) 0 7px, rgba(245,158,11,.4) 7px 14px); box-shadow: inset 0 0 0 2px #f59e0b; }
  .gantt-punto { flex: none; width: 14px; height: 14px; border-radius: 4px; display: inline-block; }
  .gantt-fila-hitos { height: 72px; border-top: 1px solid rgba(255,255,255,.14); }
  .gantt-fila-hitos .gantt-etiqueta b { font-size: 19px; }
  .gantt-hito { position: absolute; top: 50%; width: 0; height: 0; }
  .gantt-hito i { position: absolute; left: -9px; top: -9px; width: 18px; height: 18px; transform: rotate(45deg); background: #0a1420; border: 3px solid #fff; }
  .gantt-hito.is-entrega i { background: #00d9c0; border-color: #00d9c0; box-shadow: 0 0 0 5px rgba(0,217,192,.22); }
  .gantt-hito em { position: absolute; left: calc(14px + var(--corrido, 0px)); font-style: normal; font-size: 16px; font-weight: 700; color: #fff; white-space: nowrap;
    background: #0a1520; padding: 1px 6px; border-radius: 5px; }
  .gantt-hito.is-arriba em { bottom: 14px; }
  .gantt-hito.is-abajo em { top: 14px; }
  .gantt-hito.is-izq em { left: auto; right: 14px; }
  .gantt-hito.is-entrega em { color: #00d9c0; }
  .gantt-rombo { flex: none; width: 13px; height: 13px; transform: rotate(45deg); border: 2px solid #fff; display: inline-block; }
  .gantt-vacio { color: #c9d4e0; font-size: 22px; padding: 40px 0; }
  .gantt-pie { flex: none; display: flex; align-items: center; flex-wrap: wrap; gap: 10px 26px; margin-top: 14px; padding-top: 12px;
    border-top: 1px solid rgba(255,255,255,.14); }
  .gantt-leyenda { display: inline-flex; align-items: center; gap: 9px; color: #c9d4e0; font-size: 17px; }
  .gantt-nota { margin-left: auto; color: #9fb3c8; font-size: 16px; font-style: italic; }
`;

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

// Mismas seis fases y los mismos textos que la sección «Método» de automatizatech.cl
// (assets/home-premium/body.html): el plan no puede contar el método distinto que la web.
const METODO = [
  { clave: 'diagnostico', titulo: 'Diagnóstico', texto: 'Revisamos tu negocio y detectamos dónde estás perdiendo tiempo y ventas.' },
  { clave: 'priorizacion', titulo: 'Priorización', texto: 'Definimos qué conviene implementar primero para tener impacto rápido.' },
  { clave: 'propuesta', titulo: 'Propuesta por fases', texto: 'Un plan claro, por etapas, sin sorpresas ni inversiones a ciegas.' },
  { clave: 'diseno_desarrollo', titulo: 'Diseño y desarrollo', texto: 'Construimos con diseño premium y tecnología sólida, pensada para crecer.' },
  { clave: 'implementacion', titulo: 'Implementación', texto: 'Lo dejamos funcionando y conectado a tu operación real, sin fricción.' },
  { clave: 'soporte', titulo: 'Soporte y mejora continua', texto: 'No desaparecemos. Medimos, ajustamos y seguimos optimizando contigo.' },
];

function renderMetodoSlide(data, index, imageUrl) {
  const m = data && data.metodo && typeof data.metodo === 'object' ? data.metodo : {};
  const hechas = new Set(Array.isArray(m.hechas) ? m.hechas : ['diagnostico', 'priorizacion']);
  const actual = typeof m.actual === 'string' && m.actual ? m.actual : 'propuesta';
  const firma = texto(data && data.fecha_firma_larga).trim();
  const pasos = METODO.map((p, i) => {
    const estado = p.clave === actual ? 'actual' : hechas.has(p.clave) ? 'hecha' : 'proxima';
    const marca = estado === 'hecha' ? '✓' : numero(i + 1);
    const extra =
      estado === 'actual'
        ? `<span class="metodo-aqui">Estás aquí</span><p class="metodo-firma">${
            firma ? `Cerrada con tu firma del ${escapeHtml(firma)}` : 'Cerrada con tu firma del contrato'
          }</p>`
        : `<p class="metodo-estado">${estado === 'hecha' ? 'Hecho' : 'Lo que viene'}</p>`;
    return `<li class="metodo-paso is-${estado}"><span class="metodo-marca">${marca}</span><h3>${escapeHtml(p.titulo)}</h3><p class="metodo-texto">${escapeHtml(
      p.texto
    )}</p>${extra}</li>`;
  }).join('');
  const proximas = METODO.filter((p) => p.clave !== actual && !hechas.has(p.clave)).map((p) => escapeHtml(p.titulo));
  return `
    <section class="slide plan-panel plan-metodo">
      <div class="slide-bg" style="${fondoPanel(imageUrl, index)}"></div>
      ${logoMark()}
      <div class="panel-head plan-anima">
        <p class="eyebrow">${numero(index)} · El Método AT</p>
        <h2>Dónde estamos en tu proyecto</h2>
      </div>
      <ol class="metodo">${pasos}</ol>
      ${proximas.length ? `<p class="metodo-pie">Lo que viene: ${proximas.join(' → ')}. Este plan detalla esas fases.</p>` : ''}
    </section>`;
}

// Alto (px) de cada fila de la tabla de una fase según la densidad, medido en Chrome a 1920×1080 con
// nombres y detalles en una sola línea (normal 59,5/51/+23; denso 49,5/41/+23; muy denso 39,5/32) y
// redondeado hacia arriba. La tabla tiene ALTO_TABLA px bajo el título, menos lo que ocupe la descripción.
// Con eso se reparte la fase en láminas y se elige la letra más grande que cabe: nada se sale por abajo,
// ni en pantalla ni en el PDF.
const FILAS_FASE = {
  normal: { clase: '', bloque: 62, act: 53, detalle: 25 },
  denso: { clase: ' is-denso', bloque: 52, act: 43, detalle: 24 },
  muyDenso: { clase: ' is-muy-denso', bloque: 40, act: 33, detalle: 0 },
};
const ALTO_TABLA = 560;
// La tarjeta «Qué aprobamos juntos» (470 px de ancho) cabe con hasta 5 entregas de dos líneas; con 7 termina
// a 1118 px (medido en Chrome a 1920×1080 con el peor caso de la Task 3). Por eso una lámina lleva a lo más
// MAX_ENTREGAS_LAMINA bloques con entrega, aunque la tabla tenga espacio para más.
const MAX_ENTREGAS_LAMINA = 5;

// Lo que queda para la tabla después de la descripción: hasta 3 líneas de unas 80 letras (38 px cada una).
function altoDisponible(descripcion) {
  const d = texto(descripcion).trim();
  return d ? ALTO_TABLA - 18 - Math.min(3, Math.ceil(d.length / 80)) * 38 : ALTO_TABLA;
}

function altoBloques(bloques, m) {
  return bloques.reduce(
    (alto, b) =>
      alto +
      m.bloque +
      (Array.isArray(b.actividades) ? b.actividades : []).reduce((s, a) => s + m.act + (m.detalle && texto(a.detalle).trim() ? m.detalle : 0), 0),
    0
  );
}

function densidadFase(bloques, disponible) {
  for (const m of [FILAS_FASE.normal, FILAS_FASE.denso]) {
    if (altoBloques(bloques, m) <= disponible) return m.clase;
  }
  return FILAS_FASE.muyDenso.clase;
}

// Reparte los bloques de una fase en láminas llenando cada una hasta su alto con la letra más chica (la
// primera lleva la descripción) y con a lo más MAX_ENTREGAS_LAMINA entregas, para que una fase larga siga
// en una lámina de «continuación» en vez de salirse por abajo. Un bloque que no cabe solo en una lámina se
// corta en trozos; el trozo que sigue lleva `sigue: true` y la entrega queda en el último trozo. Cada bloque
// lleva `ocurrencia` (cuántos bloques anteriores de la fase se llaman igual) para encontrar su revisión
// aunque dos bloques tengan el mismo nombre; los trozos de un bloque comparten la suya.
function paginarBloques(bloques, descripcion = '') {
  const m = FILAS_FASE.muyDenso;
  const porTrozo = Math.max(1, Math.floor((altoDisponible('x'.repeat(240)) - m.bloque) / m.act));
  const lista = [];
  const vistos = new Map();
  for (const b of (Array.isArray(bloques) ? bloques : []).filter((x) => x && typeof x === 'object')) {
    const acts = (Array.isArray(b.actividades) ? b.actividades : []).filter((a) => a && typeof a === 'object');
    const ocurrencia = vistos.get(texto(b.nombre)) || 0;
    vistos.set(texto(b.nombre), ocurrencia + 1);
    if (acts.length <= porTrozo) {
      lista.push({ ...b, actividades: acts, ocurrencia });
      continue;
    }
    for (let i = 0; i < acts.length; i += porTrozo) {
      lista.push({
        ...b,
        actividades: acts.slice(i, i + porTrozo),
        entrega: Boolean(b.entrega) && i + porTrozo >= acts.length,
        sigue: i > 0,
        ocurrencia,
      });
    }
  }
  const paginas = [];
  let actual = [];
  for (const b of lista) {
    const disponible = paginas.length === 0 ? altoDisponible(descripcion) : ALTO_TABLA;
    const entregas = actual.filter((x) => x.entrega).length + (b.entrega ? 1 : 0);
    if (actual.length && (altoBloques(actual.concat([b]), m) > disponible || entregas > MAX_ENTREGAS_LAMINA)) {
      paginas.push(actual);
      actual = [];
    }
    actual.push(b);
  }
  if (actual.length) paginas.push(actual);
  return paginas.length ? paginas : [[]];
}

// La revisión del cliente de cada bloque con entrega, emparejada igual que en la carta Gantt. La clave es
// «nombre#ocurrencia» (0 para el primer bloque con ese nombre en la fase, 1 para el segundo…): dos bloques
// «Desarrollo» en la misma fase no se pisan la revisión.
function revisionesPorBloque(cronograma, faseClave) {
  const mapa = new Map();
  const vistos = new Map();
  for (const g of filasGantt(cronograma)) {
    if (g.fase !== faseClave) continue;
    for (const f of g.filas) {
      if (!f.trabajo) continue;
      const nombre = texto(f.trabajo.etiqueta);
      const k = vistos.get(nombre) || 0;
      vistos.set(nombre, k + 1);
      if (f.revision) mapa.set(`${nombre}#${k}`, f.revision);
    }
  }
  return mapa;
}

// Clave de cada bloque para revisionesPorBloque: la `ocurrencia` que puso paginarBloques o, si la lámina se
// dibuja con los bloques de la fase sin paginar, la que se cuenta aquí en orden.
function clavesBloques(lista) {
  const vistos = new Map();
  return lista.map((b) => {
    const nombre = texto(b.nombre);
    if (Number.isInteger(b.ocurrencia)) return `${nombre}#${b.ocurrencia}`;
    const k = vistos.get(nombre) || 0;
    vistos.set(nombre, k + 1);
    return `${nombre}#${k}`;
  });
}

function renderFaseSlide({ fase, numeroFase, totalFases, bloques, continuacion, cronograma }, index, imageUrl) {
  const f = fase && typeof fase === 'object' ? fase : {};
  const titulo = texto(f.titulo).trim() || FASES[f.clave] || 'Fase';
  const revisiones = revisionesPorBloque(cronograma, f.clave);
  const lista = (Array.isArray(bloques) ? bloques : []).filter((b) => b && typeof b === 'object');
  const conDescripcion = !continuacion && Boolean(texto(f.descripcion).trim());
  const densidad = densidadFase(lista, conDescripcion ? altoDisponible(f.descripcion) : ALTO_TABLA);

  const filas = lista
    .map((b) => {
      const acts = (Array.isArray(b.actividades) ? b.actividades : []).filter((a) => a && typeof a === 'object');
      const entregable = b.entrega && texto(b.entregable).trim() ? `<span>Entrega: ${escapeHtml(b.entregable)}</span>` : '';
      const nombreBloque = `${escapeHtml(texto(b.nombre))}${b.sigue ? ' (sigue)' : ''}`;
      const cabeza = `<tr class="plan-bloque"><td colspan="4"><b>${nombreBloque}</b>${entregable}</td></tr>`;
      const cuerpo = acts
        .map((a) => {
          const r = responsableClave(a.responsable);
          const paralelo = a.en_paralelo ? '<em class="plan-paralelo">en paralelo</em>' : '';
          const detalle = texto(a.detalle).trim() ? `<small>${escapeHtml(a.detalle)}</small>` : '';
          return `<tr><td class="plan-act">${escapeHtml(texto(a.nombre))}${paralelo}${detalle}</td><td><span class="quien is-${r}">${
            RESPONSABLES[r]
          }</span></td><td>${diasTexto(a.dias_habiles)}</td><td>${escapeHtml(rangoCorto(a.desde, a.hasta))}</td></tr>`;
        })
        .join('');
      return cabeza + cuerpo;
    })
    .join('');

  const claves = clavesBloques(lista);
  const entregas = lista
    .map((b, i) => {
      if (!b.entrega) return '';
      const rev = revisiones.get(claves[i]);
      const cuando = rev ? `Tu revisión: ${escapeHtml(rangoCorto(rev.desde, rev.hasta))}.` : 'Lo revisas apenas te lo entregamos.';
      return `<li><b>${escapeHtml(texto(b.entregable).trim() || texto(b.nombre))}</b><span>${cuando}</span></li>`;
    })
    .join('');
  const aprobamos = entregas
    ? `<ul class="aprobamos-lista">${entregas}</ul><p class="aprobamos-nota">Tienes 5 días hábiles para aprobar cada entrega o enviarnos tus observaciones (cláusula 6.1 del contrato).</p>`
    : '<p class="aprobamos-nota">En esta parte no hay entregas que aprobar: te contamos el avance en cada reunión de seguimiento.</p>';

  return `
    <section class="slide plan-contenido plan-fase">
      <div class="slide-bg" style="${fondoContenido(imageUrl, index)}"></div>
      ${logoMark()}
      <div class="plan-texto plan-anima">
        <p class="eyebrow">${numero(index)} · Fase ${numeroFase} de ${totalFases}</p>
        <div class="accent-bar"></div>
        <h2>${escapeHtml(titulo)}${continuacion ? ' <span class="plan-cont">(continuación)</span>' : ''}</h2>
        ${conDescripcion ? `<p class="plan-desc">${linkify(escapeHtml(f.descripcion))}</p>` : ''}
        <table class="plan-tabla${densidad}"><thead><tr><th>Actividad</th><th>Quién</th><th>Duración</th><th>Fechas</th></tr></thead><tbody>${filas}</tbody></table>
      </div>
      <aside class="plan-tarjeta"><h3>Qué aprobamos juntos</h3>${aprobamos}</aside>
    </section>`;
}

const ESTILO_FASES = `
  .metodo { position: absolute; left: 72px; right: 72px; top: 320px; z-index: 2; list-style: none; display: grid;
    grid-template-columns: repeat(6, 1fr); gap: 22px; }
  .metodo::before { content: ''; position: absolute; left: 4%; right: 4%; top: 36px; height: 2px; background: rgba(255,255,255,.16); }
  .metodo-paso { position: relative; padding: 0 6px; }
  .metodo-marca { position: relative; display: flex; align-items: center; justify-content: center; width: 72px; height: 72px;
    border-radius: 50%; font-size: 24px; font-weight: 800; color: #9fb3c8; background: #0a1420; border: 2px solid rgba(255,255,255,.22); }
  .metodo-paso.is-hecha .metodo-marca { color: #06222a; background: #00d9c0; border-color: #00d9c0; font-size: 32px; }
  .metodo-paso.is-actual .metodo-marca { color: #fff; border-color: #00d9c0; box-shadow: 0 0 0 8px rgba(0,217,192,.18); }
  .metodo-paso.is-actual::after { content: ''; position: absolute; inset: -24px -14px -30px; z-index: -1; border-radius: 20px;
    background: rgba(0,217,192,.07); border: 1px solid rgba(0,217,192,.32); }
  .metodo-paso h3 { color: #fff; font-size: 27px; font-weight: 800; margin-top: 26px; line-height: 1.2; }
  .metodo-texto { color: #b8c6d6; font-size: 19px; line-height: 1.5; margin-top: 12px; }
  .metodo-paso.is-hecha h3, .metodo-paso.is-hecha .metodo-texto { opacity: .72; }
  .metodo-paso.is-proxima .metodo-texto { color: #9fb3c8; }
  .metodo-estado { color: #9fb3c8; font-size: 16px; letter-spacing: .1em; text-transform: uppercase; margin-top: 18px; font-weight: 700; }
  .metodo-paso.is-hecha .metodo-estado { color: #00d9c0; }
  .metodo-aqui { display: inline-block; margin-top: 18px; padding: 7px 16px; border-radius: 999px; background: #00d9c0; color: #06222a;
    font-size: 16px; font-weight: 800; letter-spacing: .06em; text-transform: uppercase; }
  .metodo-firma { color: #fff; font-size: 18px; line-height: 1.45; margin-top: 12px; }
  .metodo-pie { position: absolute; left: 72px; right: 72px; bottom: 120px; z-index: 2; color: #c9d4e0; font-size: 24px;
    border-top: 1px solid rgba(255,255,255,.14); padding-top: 26px; }
  .plan-contenido .plan-texto { position: absolute; left: 72px; top: 240px; width: 1180px; z-index: 2; }
  .plan-texto h2 { color: #fff; font-size: 46px; font-weight: 800; margin-bottom: 18px; text-shadow: 0 2px 18px rgba(6,13,21,.7); }
  .plan-cont { color: #9fb3c8; font-size: 30px; font-weight: 600; }
  .plan-desc { color: #dbe4ee; font-size: 25px; line-height: 1.5; margin-bottom: 18px; max-width: 1080px;
    display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
  .plan-desc a { color: #00d9c0; }
  .plan-tabla { width: 100%; border-collapse: collapse; table-layout: fixed; }
  .plan-tabla th { color: #9fb3c8; font-size: 15px; font-weight: 700; letter-spacing: .12em; text-transform: uppercase; text-align: left;
    padding: 0 12px 10px 0; border-bottom: 1px solid rgba(255,255,255,.2); }
  .plan-tabla th:nth-child(1) { width: 52%; }
  .plan-tabla th:nth-child(2) { width: 17%; }
  .plan-tabla th:nth-child(3) { width: 16%; }
  .plan-tabla th:nth-child(4) { width: 15%; }
  .plan-tabla td { color: #dbe4ee; font-size: 21px; padding: 10px 12px 10px 0; border-bottom: 1px solid rgba(255,255,255,.1); vertical-align: top;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .plan-tabla tr.plan-bloque td { color: #fff; padding-top: 18px; border-bottom: 0; }
  .plan-tabla tr.plan-bloque b { font-size: 23px; }
  .plan-tabla tr.plan-bloque span { margin-left: 16px; color: #00d9c0; font-size: 18px; }
  .plan-act small { display: block; color: #9fb3c8; font-size: 17px; margin-top: 3px; overflow: hidden; text-overflow: ellipsis; }
  .plan-paralelo { margin-left: 10px; font-style: normal; font-size: 14px; color: #06222a; background: #9fb3c8; border-radius: 999px;
    padding: 2px 10px; vertical-align: 2px; }
  .quien { display: inline-block; font-size: 17px; font-weight: 700; padding: 3px 12px; border-radius: 999px; color: #06222a; }
  .quien.is-at { background: #00d9c0; }
  .quien.is-cliente { background: #38bdf8; }
  .quien.is-ambos { background: #a78bfa; }
  .plan-tabla.is-denso td { font-size: 19px; padding: 6px 12px 6px 0; }
  .plan-tabla.is-denso tr.plan-bloque td { padding-top: 12px; }
  .plan-tabla.is-muy-denso td { font-size: 17px; padding: 4px 12px 4px 0; }
  .plan-tabla.is-muy-denso tr.plan-bloque td { padding-top: 9px; }
  .plan-tabla.is-muy-denso tr.plan-bloque b { font-size: 19px; }
  .plan-tabla.is-muy-denso .plan-act small { display: none; }
  .plan-tabla.is-muy-denso .quien { font-size: 15px; padding: 1px 10px; }
  .plan-tarjeta { position: absolute; right: 72px; top: 240px; width: 470px; z-index: 2; background: rgba(6,13,21,.8);
    border: 1px solid rgba(0,217,192,.35); border-radius: 18px; padding: 30px 32px; }
  .plan-tarjeta h3 { color: #00d9c0; font-size: 20px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; margin-bottom: 18px; }
  .aprobamos-lista { list-style: none; display: flex; flex-direction: column; gap: 16px; }
  .aprobamos-lista li { display: flex; flex-direction: column; gap: 4px; padding-left: 18px; border-left: 3px solid #f59e0b; }
  .aprobamos-lista b { color: #fff; font-size: 21px; line-height: 1.3; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
  .aprobamos-lista span { color: #c9d4e0; font-size: 18px; }
  .aprobamos-nota { color: #9fb3c8; font-size: 17px; line-height: 1.5; margin-top: 20px; }
  @media screen {
    body.mode-deck .slide.is-active .plan-anima > *,
    body.mode-deck .slide.is-active .plan-tarjeta,
    body.mode-deck .slide.is-active .metodo-paso,
    body.mode-deck .slide.is-active .gantt { animation: at-rise .65s cubic-bezier(.22,.9,.3,1) both; }
    body.mode-deck .slide.is-active .plan-anima > *:nth-child(2) { animation-delay: .1s; }
    body.mode-deck .slide.is-active .plan-anima > *:nth-child(3) { animation-delay: .18s; }
    body.mode-deck .slide.is-active .plan-anima > *:nth-child(n+4) { animation-delay: .26s; }
    body.mode-deck .slide.is-active .plan-tarjeta, body.mode-deck .slide.is-active .gantt { animation-delay: .3s; }
    body.mode-deck .slide.is-active .metodo-paso:nth-child(2) { animation-delay: .08s; }
    body.mode-deck .slide.is-active .metodo-paso:nth-child(3) { animation-delay: .16s; }
    body.mode-deck .slide.is-active .metodo-paso:nth-child(4) { animation-delay: .24s; }
    body.mode-deck .slide.is-active .metodo-paso:nth-child(5) { animation-delay: .32s; }
    body.mode-deck .slide.is-active .metodo-paso:nth-child(6) { animation-delay: .4s; }
    @media (prefers-reduced-motion: reduce) {
      body.mode-deck .slide.is-active .plan-anima > *, body.mode-deck .slide.is-active .plan-tarjeta,
      body.mode-deck .slide.is-active .metodo-paso, body.mode-deck .slide.is-active .gantt { animation: none !important; }
    }
  }
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

module.exports = { renderPlanHtml, lunesDe, semanaIndice, diasEntre, fechaLarga, fechaCorta, rangoCorto, diasTexto, urlSegura, filasGantt, medidasGantt, renderGantt, renderGanttSlide, renderMetodoSlide, paginarBloques, renderFaseSlide };
