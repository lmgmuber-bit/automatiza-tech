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

module.exports = { renderPlanHtml, lunesDe, semanaIndice, diasEntre, fechaLarga, fechaCorta, rangoCorto, diasTexto, urlSegura, filasGantt, medidasGantt, renderGantt, renderGanttSlide };
