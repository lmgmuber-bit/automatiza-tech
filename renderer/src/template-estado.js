const { escapeHtml } = require('./escape');
const { STYLE, SCRIPT, CONTACT_ICONS, LOGO_URL, backgroundStyle, logoMark, renderDeckControls } = require('./template');
const {
  lunesDe,
  semanaIndice,
  diasEntre,
  fechaLarga,
  fechaCorta,
  rangoCorto,
  urlSegura,
  numero,
  texto,
  fondoPanel,
  porcentaje,
  FASES,
  ESTILO_PORTADA,
  ESTILO_GANTT,
  ESTILO_CIERRE,
} = require('./template-plan');

// «Estado del proyecto» (document_type 'estado'): la presentación viva del avance de un plan ya enviado (spec §7).
// Láminas: portada, dónde vamos, cronograma planificado y real, una por cierre publicado (más «Evidencias» si no caben),
// cambios de plan (si hay), qué incluye, lo que sigue y lo que necesitamos, y cierre. Sin fotos nuevas (D9): usa las del
// plan por lámina. Todos los enlaces pasan por urlSegura (solo https); WordPress ya los manda por automatizatech.cl (D8).

const ESTADOS = { listo: 'Listo', en_curso: 'En curso', por_venir: 'Por venir' };
const EVIDENCIAS_EN_CIERRE = 4;
const EVIDENCIAS_POR_LAMINA = 8;
const PUNTOS_VISIBLES = 3;
const ACTIVIDADES_VISIBLES = 6;
const BLOQUES_POR_COLUMNA = 7;
/** Tope de semanas que dibuja el cronograma (dos años). */
const TOPE_SEMANAS = 104;
const CAMBIOS_VISIBLES = 8;
const PROXIMOS_VISIBLES = 6;
const NECESITAMOS_VISIBLES = 8;
const DIA_MS = 86400000;

const objetos = (l) => (Array.isArray(l) ? l.filter((x) => x && typeof x === 'object') : []);
const cadenas = (l) => (Array.isArray(l) ? l.filter((x) => typeof x === 'string' && x.trim()) : []);
const esFecha = (f) => typeof f === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(f);
const estadoDe = (b) => (Object.prototype.hasOwnProperty.call(ESTADOS, b && b.estado) ? b.estado : 'por_venir');
const plural = (n, uno, varios) => `${n} ${n === 1 ? uno : varios}`;

function cabeza(index, eyebrow, titulo) {
  return `<div class="panel-head plan-anima"><p class="eyebrow">${numero(index)} · ${eyebrow}</p><h2>${titulo}</h2></div>`;
}

function renderPortadaEstado(d, foto) {
  const proyecto = texto(d.proyecto);
  const cliente = texto(d.client_name).trim();
  const actualizado = fechaLarga(d.actualizado);
  const detalle = [cliente ? `Preparado para ${escapeHtml(cliente)}` : '', actualizado ? `Actualizado el ${escapeHtml(actualizado)}` : '']
    .filter(Boolean)
    .join(' · ');
  return `
    <section class="slide slide-cover plan-cover estado-portada">
      <div class="slide-bg" style="${backgroundStyle(foto, 0, 'cover')}"></div>
      ${logoMark()}
      <div class="slide-body">
        <p class="eyebrow">${escapeHtml(texto(d.company_name))}</p>
        <h1${proyecto.length > 44 ? ' class="is-largo"' : ''}>Estado del proyecto — ${escapeHtml(proyecto)}</h1>
        ${detalle ? `<p class="lede">${detalle}</p>` : ''}
        <div class="accent-bar"></div>
      </div>
    </section>`;
}

function fechasBloque(b) {
  const plan = esFecha(b.plan_desde) && esFecha(b.plan_hasta) ? `Planificado ${rangoCorto(b.plan_desde, b.plan_hasta)}` : '';
  const e = estadoDe(b);
  const real =
    e === 'listo' && esFecha(b.fin_real) ? `listo el ${fechaCorta(b.fin_real)}` : e === 'en_curso' && esFecha(b.inicio_real) ? `empezó el ${fechaCorta(b.inicio_real)}` : '';
  return [plan, real].filter(Boolean).join(' · ');
}

function renderDondeVamos(d, index, foto) {
  const bloques = objetos(d.bloques);
  let aqui = bloques.findIndex((b) => estadoDe(b) === 'en_curso');
  if (aqui < 0) aqui = bloques.findIndex((b) => estadoDe(b) !== 'listo');
  const grupos = [];
  bloques.forEach((b, i) => {
    let g = grupos.find((x) => x.fase === b.fase);
    if (!g) {
      g = { fase: b.fase, items: [] };
      grupos.push(g);
    }
    g.items.push({ b, i });
  });
  // Una fase con muchos bloques se reparte en columnas de 7 para no salirse de la lámina.
  const columnas = [];
  for (const g of grupos) {
    for (let k = 0; k < g.items.length; k += BLOQUES_POR_COLUMNA) {
      columnas.push({ titulo: (FASES[g.fase] || 'Otras actividades') + (k ? ' (sigue)' : ''), items: g.items.slice(k, k + BLOQUES_POR_COLUMNA) });
    }
  }
  const html = columnas
    .map(
      (col) =>
        `<div class="estado-fase"><h3>${escapeHtml(col.titulo)}</h3><ol>${col.items
          .map(({ b, i }) => {
            const e = estadoDe(b);
            return `<li class="estado-bloque is-${e}${i === aqui ? ' is-aqui' : ''}"><span class="estado-chip">${e === 'listo' ? '✓ ' : ''}${ESTADOS[e]}</span>${
              i === aqui ? '<span class="estado-aqui">Estás aquí</span>' : ''
            }<b title="${escapeHtml(texto(b.nombre))}">${escapeHtml(texto(b.nombre))}</b><small>${escapeHtml(fechasBloque(b))}</small></li>`;
          })
          .join('')}</ol></div>`
    )
    .join('');
  const listos = bloques.filter((b) => estadoDe(b) === 'listo').length;
  const plan = urlSegura(d.plan_url);
  return `
    <section class="slide plan-panel estado-donde">
      <div class="slide-bg" style="${fondoPanel(foto, index)}"></div>
      ${logoMark()}
      ${cabeza(index, 'Dónde vamos', 'Así va tu proyecto')}
      <div class="panel-resumen"><span><small>Partes listas</small><b>${listos} de ${bloques.length}</b></span></div>
      <div class="estado-fases">${html || '<p class="estado-vacio">Pronto verás aquí cada parte del plan.</p>'}</div>
      ${
        plan
          ? `<a class="estado-plan" href="${escapeHtml(plan)}" target="_top" rel="noopener"><b>Ver el plan de actividades inicial</b><small>El plan que firmamos, con sus fechas planificadas</small></a>`
          : ''
      }
    </section>`;
}

function renderCronogramaReal(d, index, foto) {
  const c = d.cronograma && typeof d.cronograma === 'object' ? d.cronograma : {};
  const hoy = esFecha(d.actualizado) ? d.actualizado : '';
  const bloques = objetos(d.bloques).filter((b) => esFecha(b.plan_desde) && esFecha(b.plan_hasta));
  const reales = bloques.map((b) => {
    const e = estadoDe(b);
    const desde = esFecha(b.inicio_real) ? b.inicio_real : e === 'listo' ? b.plan_desde : '';
    const hasta = esFecha(b.fin_real) ? b.fin_real : e === 'en_curso' ? hoy : '';
    // Terminado antes de lo planificado y sin inicio real: un punto en el día real, no una barra al revés que no se dibuja.
    if (desde && hasta && hasta < desde && e === 'listo' && !esFecha(b.inicio_real)) return { desde: hasta, hasta };
    return desde && hasta && hasta >= desde ? { desde, hasta } : null;
  });
  const fechas = [c.inicio, c.fin, hoy, ...bloques.flatMap((b) => [b.plan_desde, b.plan_hasta]), ...reales.filter(Boolean).flatMap((r) => [r.desde, r.hasta])]
    .filter(esFecha)
    .sort();
  let lunes0 = fechas.length ? lunesDe(fechas[0]) : '';
  let ultima = lunes0 ? semanaIndice(fechas[fechas.length - 1], lunes0) : NaN;
  if (ultima + 1 > TOPE_SEMANAS) {
    // Una fecha absurda (p. ej. un fin real en 2099) no puede dibujar miles de semanas: se parte del lunes de inicio del
    // plan —o de la primera fecha— y se recorta a TOPE_SEMANAS; las barras que caen fuera las deja recortadas pos().
    lunes0 = lunesDe(esFecha(c.inicio) ? c.inicio : fechas[0]);
    ultima = semanaIndice(fechas[fechas.length - 1], lunes0);
  }
  const semanas = Number.isNaN(ultima) || ultima < 0 ? 1 : Math.min(TOPE_SEMANAS, ultima + 1);
  const total = semanas * 7;
  const largo = semanas > 18;
  const pos = (desde, hasta) => {
    const ini = Math.max(0, Math.min(total, diasEntre(lunes0, desde)));
    const fin = Math.max(ini, Math.min(total, diasEntre(lunes0, hasta) + 1));
    return `left:${porcentaje(ini / total)};width:${porcentaje(Math.max(fin - ini, 0.35) / total)}`;
  };
  const cabecera = Array.from({ length: semanas }, (_, i) => {
    const f = lunes0 ? new Date(new Date(`${lunes0}T00:00:00Z`).getTime() + i * 7 * DIA_MS) : null;
    const etiqueta = !f ? '' : largo ? `${f.getUTCDate()}/${f.getUTCMonth() + 1}` : fechaCorta(f.toISOString().slice(0, 10));
    return `<span><b>S${i + 1}</b><small>${escapeHtml(etiqueta)}</small></span>`;
  }).join('');
  const filas = bloques
    .map((b, i) => {
      const r = reales[i];
      const e = estadoDe(b);
      return `<div class="crono-fila"><div class="crono-etiqueta"><i class="crono-punto is-${e}"></i><b title="${escapeHtml(texto(b.nombre))}">${escapeHtml(
        texto(b.nombre)
      )}</b></div><div class="crono-pista"><span class="crono-plan" style="${pos(b.plan_desde, b.plan_hasta)}" title="Planificado ${escapeHtml(
        rangoCorto(b.plan_desde, b.plan_hasta)
      )}"></span>${r ? `<span class="crono-real is-${e}" style="${pos(r.desde, r.hasta)}" title="Real ${escapeHtml(rangoCorto(r.desde, r.hasta))}"></span>` : ''}</div></div>`;
    })
    .join('');
  const linea = lunes0 && hoy ? `<span class="crono-hoy" style="left:${porcentaje((diasEntre(lunes0, hoy) + 0.5) / total)}"><em>Hoy</em></span>` : '';
  const fila = Math.max(26, Math.min(56, Math.floor(620 / Math.max(1, bloques.length))));
  return `
    <section class="slide plan-panel estado-crono">
      <div class="slide-bg" style="${fondoPanel(foto, index)}"></div>
      ${logoMark()}
      ${cabeza(index, 'Cronograma', 'Planificado y real')}
      <div class="crono${largo ? ' is-largo' : ''}" style="--semanas:${semanas};--fila:${fila}px">
        <div class="crono-cabecera"><span class="crono-esquina">Semana</span><div class="crono-semanas">${cabecera}</div></div>
        <div class="crono-cuerpo"><div class="crono-guias" aria-hidden="true">${linea}</div>${filas || '<p class="estado-vacio">Las fechas aparecen aquí cuando el plan tenga actividades.</p>'}</div>
        <div class="crono-pie"><span><i class="crono-muestra is-plan"></i>Planificado</span><span><i class="crono-muestra is-real"></i>Real</span><span><i class="crono-muestra is-curso"></i>En curso</span>${
          hoy ? `<span class="crono-nota">Fechas reales al ${escapeHtml(fechaLarga(hoy))}.</span>` : ''
        }</div>
      </div>
    </section>`;
}

/** Fin planificado de los bloques que cubre el cierre (el último plan_hasta). */
function planificadoDe(cierre, bloques) {
  const nombres = cadenas(cierre.bloques);
  return (
    objetos(bloques)
      .filter((b) => nombres.includes(b.nombre) && esFecha(b.plan_hasta))
      .map((b) => b.plan_hasta)
      .sort()
      .pop() || ''
  );
}

function lineaFechas(cierre, bloques) {
  const plan = planificadoDe(cierre, bloques);
  const real = esFecha(cierre.fecha_real) ? cierre.fecha_real : '';
  if (plan && real) {
    const dif = diasEntre(plan, real);
    const comp =
      dif < 0 ? `${plural(-dif, 'día', 'días')} antes de lo planificado` : dif > 0 ? `${plural(dif, 'día', 'días')} después de lo planificado` : 'el día planificado';
    return `Lo teníamos planificado para el ${fechaLarga(plan)} y quedó listo el ${fechaLarga(real)} (${comp}).`;
  }
  return real ? `Quedó listo el ${fechaLarga(real)}.` : '';
}

function renderPuntos(parte) {
  const puntos = cadenas(parte && parte.puntos);
  const vis = puntos.slice(0, PUNTOS_VISIBLES);
  if (!vis.length) return '';
  const mas = puntos.length > vis.length ? `<li class="cierre-mas">y ${plural(puntos.length - vis.length, 'punto más', 'puntos más')}</li>` : '';
  return `<ul class="cierre-puntos">${vis.map((p) => `<li>${escapeHtml(p)}</li>`).join('')}${mas}</ul>`;
}

function renderActividades(durante) {
  const acts = objetos(durante && durante.actividades);
  const vis = acts.slice(0, ACTIVIDADES_VISIBLES);
  if (!vis.length) return '';
  const fila = (a) => {
    const fechas = [esFecha(a.plan_hasta) ? `plan ${fechaCorta(a.plan_hasta)}` : '', esFecha(a.real) ? `real ${fechaCorta(a.real)}` : ''].filter(Boolean).join(' · ');
    return `<li><b title="${escapeHtml(texto(a.nombre))}">${escapeHtml(texto(a.nombre))}</b><small>${escapeHtml(fechas)}</small></li>`;
  };
  const mas = acts.length > vis.length ? `<li class="cierre-mas">y ${plural(acts.length - vis.length, 'actividad más', 'actividades más')}</li>` : '';
  return `<ul class="cierre-acts">${vis.map(fila).join('')}${mas}</ul>`;
}

/** Una evidencia como tarjeta; '' si no se puede mostrar (enlace o archivo que no es https). */
function renderEvidencia(e, cuando) {
  const t = escapeHtml(texto(e.titulo));
  const marca = cuando ? `<span class="ev-cuando">${cuando}</span>` : '';
  const fuente = [texto(e.fuente).trim(), esFecha(e.fecha) ? fechaLarga(e.fecha) : ''].filter(Boolean).join(' · ');
  const pie = fuente ? `<small class="ev-fuente">Fuente: ${escapeHtml(fuente)}</small>` : '';
  if (e.tipo === 'cifra') {
    if (!texto(e.valor).trim()) return '';
    return `<div class="ev ev-cifra">${marca}<b>${escapeHtml(texto(e.valor))}</b><span>${escapeHtml(texto(e.unidad))}</span><p>${t}</p>${pie}</div>`;
  }
  const url = urlSegura(e.url);
  if (!url) return '';
  if (e.tipo === 'captura') {
    return `<figure class="ev ev-captura">${marca}<a href="${escapeHtml(url)}" target="_blank" rel="noopener"><img src="${escapeHtml(url)}" alt="${t}" loading="lazy" /></a><figcaption>${t}</figcaption>${pie}</figure>`;
  }
  if (e.tipo === 'video') {
    const poster = urlSegura(e.poster);
    const p = poster ? ` poster="${escapeHtml(poster)}"` : '';
    // En el PDF un video no corre (R1): se cambia por el póster (si hay) y el enlace.
    return `<figure class="ev ev-video">${marca}<video controls preload="none" src="${escapeHtml(url)}"${p}></video><a class="ev-video-pdf" href="${escapeHtml(url)}">${
      poster ? `<img src="${escapeHtml(poster)}" alt="" />` : '<i aria-hidden="true">▶</i>'
    }<span>Ver el video</span></a><figcaption>${t}</figcaption>${pie}</figure>`;
  }
  if (e.tipo === 'enlace') {
    return `<a class="ev ev-enlace" href="${escapeHtml(url)}" target="_blank" rel="noopener">${marca}<b>${t}</b><small>${escapeHtml(url.replace(/^https:\/\//, ''))}</small>${pie}</a>`;
  }
  return '';
}

function evidenciasDe(cierre) {
  const de = (parte, cuando) => objetos(parte && parte.evidencias).map((e) => renderEvidencia(e, cuando)).filter(Boolean);
  return [...de(cierre.antes, 'Antes'), ...de(cierre.despues, 'Después')];
}

function renderCierreHito(d, cierre, index, foto, tarjetas) {
  const parrafo = (t) => (texto(t).trim() ? `<p class="hito-texto">${escapeHtml(texto(t))}</p>` : '');
  const col = (titulo, cuerpo) => `<div class="hito-col"><h3>${titulo}</h3>${cuerpo || '<p class="hito-texto">—</p>'}</div>`;
  const fechas = lineaFechas(cierre, d.bloques);
  const entrega = urlSegura(cierre.entregable_url);
  const antes = cierre.antes || {};
  const durante = cierre.durante || {};
  const despues = cierre.despues || {};
  return `
    <section class="slide plan-panel estado-hito${cierre.borrador ? ' is-borrador' : ''}">
      <div class="slide-bg" style="${fondoPanel(foto, index)}"></div>
      ${logoMark()}
      <div class="panel-head plan-anima"><p class="eyebrow">${numero(index)} · Terminamos${cierre.borrador ? ' · BORRADOR' : ''}</p><h2>${escapeHtml(
        texto(cierre.titulo)
      )}</h2>${fechas ? `<p class="hito-fechas">${escapeHtml(fechas)}</p>` : ''}</div>
      <div class="hito-cols">
        ${col('Antes', parrafo(antes.texto) + renderPuntos(antes))}
        ${col('Durante', parrafo(durante.texto) + renderActividades(durante))}
        ${col('Después', parrafo(despues.texto) + renderPuntos(despues))}
      </div>
      ${tarjetas.length ? `<div class="hito-evidencias">${tarjetas.join('')}</div>` : ''}
      ${entrega ? `<a class="hito-entrega" href="${escapeHtml(entrega)}" target="_top" rel="noopener">Ver la entrega y dejar comentarios</a>` : ''}
    </section>`;
}

function renderEvidenciasExtra(cierre, index, foto, tarjetas, parte, partes) {
  return `
    <section class="slide plan-panel estado-evidencias">
      <div class="slide-bg" style="${fondoPanel(foto, index)}"></div>
      ${logoMark()}
      ${cabeza(index, `Evidencias (${parte}/${partes})`, escapeHtml(texto(cierre.titulo)))}
      <div class="ev-grid">${tarjetas.join('')}</div>
    </section>`;
}

function renderCambios(d, index, foto) {
  const cambios = objetos(d.cambios_plan);
  const vis = cambios.slice(-CAMBIOS_VISIBLES);
  const items = vis
    .map(
      (c) =>
        `<li class="cambio"><small>${escapeHtml(esFecha(c.fecha) ? fechaLarga(c.fecha) : '')}</small><b title="${escapeHtml(texto(c.titulo))}">${escapeHtml(
          texto(c.titulo)
        )}</b>${texto(c.detalle).trim() ? `<p>${escapeHtml(texto(c.detalle))}</p>` : ''}</li>`
    )
    .join('');
  const antes = cambios.length - vis.length;
  return `
    <section class="slide plan-panel estado-cambios">
      <div class="slide-bg" style="${fondoPanel(foto, index)}"></div>
      ${logoMark()}
      ${cabeza(index, 'Cambios en el plan', 'Lo que acordamos cambiar')}
      <ul class="estado-lista">${items}</ul>
      ${antes > 0 ? `<p class="estado-nota">Y ${plural(antes, 'cambio anterior', 'cambios anteriores')}.</p>` : ''}
    </section>`;
}

function renderAlcance(d, index, foto) {
  const a = d.alcance && typeof d.alcance === 'object' ? d.alcance : {};
  const lista = (l, vacio) => (l.length ? `<ul>${l.map((x) => `<li>${escapeHtml(x)}</li>`).join('')}</ul>` : `<p class="estado-vacio">${vacio}</p>`);
  return `
    <section class="slide plan-panel estado-alcance">
      <div class="slide-bg" style="${fondoPanel(foto, index)}"></div>
      ${logoMark()}
      ${cabeza(index, 'Qué incluye', 'Lo que incluye tu proyecto')}
      <div class="alcance">
        <div><h3>Fase 1 · pago único</h3>${lista(cadenas(a.fase1), 'Lo detalla tu plan de trabajo.')}</div>
        <div><h3>Servicio mensual</h3>${lista(cadenas(a.mensual), 'Por ahora no hay servicio mensual.')}</div>
      </div>
    </section>`;
}

function renderSigue(d, index, foto) {
  const proximos = objetos(d.bloques).filter((b) => estadoDe(b) !== 'listo');
  const necesitamos = cadenas(d.necesitamos);
  const vp = proximos.slice(0, PROXIMOS_VISIBLES);
  const vn = necesitamos.slice(0, NECESITAMOS_VISIBLES);
  const sigue = vp.length
    ? `<ul>${vp
        .map(
          (b) =>
            `<li class="is-${estadoDe(b)}"><b title="${escapeHtml(texto(b.nombre))}">${escapeHtml(texto(b.nombre))}</b><small>${escapeHtml(
              estadoDe(b) === 'en_curso' ? 'En curso' : esFecha(b.plan_desde) && esFecha(b.plan_hasta) ? `Planificado ${rangoCorto(b.plan_desde, b.plan_hasta)}` : 'Por venir'
            )}</small></li>`
        )
        .join('')}${proximos.length > vp.length ? `<li class="cierre-mas">y ${plural(proximos.length - vp.length, 'parte más', 'partes más')}</li>` : ''}</ul>`
    : '<p class="estado-vacio">Terminamos todas las partes del plan.</p>';
  const nec = vn.length
    ? `<ul>${vn.map((x) => `<li>${escapeHtml(x)}</li>`).join('')}${necesitamos.length > vn.length ? `<li class="cierre-mas">y ${necesitamos.length - vn.length} más</li>` : ''}</ul>`
    : '<p class="estado-vacio">Por ahora nada: vamos al día.</p>';
  return `
    <section class="slide plan-panel estado-sigue">
      <div class="slide-bg" style="${fondoPanel(foto, index)}"></div>
      ${logoMark()}
      ${cabeza(index, 'Lo que sigue', 'Lo que viene y lo que necesitamos de ti')}
      <div class="sigue">
        <div><h3>Lo que sigue</h3>${sigue}</div>
        <div><h3>Lo que necesitamos de ti</h3>${nec}</div>
      </div>
    </section>`;
}

function renderCierreEstado(d, index, foto) {
  const agenda = d.agenda && typeof d.agenda === 'object' ? d.agenda : {};
  const whatsapp = urlSegura(agenda.whatsapp_url) || 'https://wa.me/56927002984';
  const web = urlSegura(agenda.web_url);
  const enlace = (href, icono, titulo, sub) =>
    `<a class="cierre-enlace" href="${escapeHtml(href)}" target="_blank" rel="noopener noreferrer"><span class="contact-icon" aria-hidden="true">${
      CONTACT_ICONS[icono] || ''
    }</span><span><b>${titulo}</b><small>${sub}</small></span></a>`;
  const enlaces = [
    enlace(whatsapp, 'whatsapp', 'Por WhatsApp con Tech', 'Escríbenos y agendamos contigo'),
    web ? enlace(web, 'web', 'En el sitio web', 'Elige el día y la hora que te acomoden') : '',
  ].join('');
  const fondo = foto
    ? ` style="background-image: linear-gradient(180deg, rgba(10,20,32,.62), rgba(10,20,32,.9)), url('${escapeHtml(foto)}'); background-size: cover; background-position: center;"`
    : '';
  return `
    <section class="slide slide-closing plan-cierre">
      <div class="closing-left"${fondo}>
        <a href="https://automatizatech.cl" target="_blank" rel="noopener noreferrer"><img class="closing-logo" src="${LOGO_URL}" alt="AutomatizaTech" /></a>
      </div>
      <div class="closing-right">
        <p class="eyebrow">${numero(index)} · Próximo paso</p>
        <h2>¿Revisamos juntos el avance?</h2>
        <p class="cierre-texto">Agenda una llamada de seguimiento y conversamos lo que viene.</p>
        <div class="cierre-enlaces">${enlaces}</div>
        <a class="pdf-button" href="presentation.pdf" download>
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v12M7 11l5 5 5-5M4 19h16" /></svg>
          Descargar el estado en PDF
        </a>
      </div>
    </section>`;
}

const ESTILO_ESTADO = `
  .estado-portada h1.is-largo { font-size: 50px; }
  .estado-vacio { color: #c9d4e0; font-size: 22px; }
  .estado-fases { position: absolute; left: 72px; right: 72px; top: 236px; z-index: 2; display: grid; grid-auto-flow: column; grid-auto-columns: minmax(0, 1fr); gap: 26px; }
  .estado-fase h3 { color: #00d9c0; font-size: 17px; letter-spacing: .14em; text-transform: uppercase; margin-bottom: 16px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .estado-fase ol { list-style: none; display: flex; flex-direction: column; gap: 12px; }
  .estado-bloque { position: relative; display: grid; grid-template-columns: minmax(0, 1fr) auto; gap: 2px 12px; padding: 10px 16px; overflow: hidden;
    background: rgba(6,13,21,.72); border: 1px solid rgba(255,255,255,.1); border-radius: 14px; }
  .estado-bloque b { grid-column: 1; grid-row: 1; color: #fff; font-size: 20px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .estado-bloque small { grid-column: 1 / -1; color: #9fb3c8; font-size: 15px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .estado-chip { grid-column: 2; grid-row: 1; align-self: center; padding: 3px 10px; border-radius: 999px; font-size: 12px; font-weight: 800;
    letter-spacing: .08em; text-transform: uppercase; background: rgba(255,255,255,.08); color: #c9d4e0; white-space: nowrap; }
  .estado-bloque.is-listo .estado-chip { background: rgba(0,217,192,.16); color: #00d9c0; }
  .estado-bloque.is-en_curso { border-color: rgba(0,217,192,.75); }
  .estado-bloque.is-en_curso .estado-chip { background: #00d9c0; color: #06222a; }
  .estado-bloque.is-aqui { padding-top: 26px; }
  .estado-aqui { position: absolute; top: 6px; left: 16px; color: #00d9c0; font-size: 11px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; }
  .estado-plan { position: absolute; left: 72px; bottom: 60px; z-index: 2; display: flex; flex-direction: column; gap: 4px; padding: 16px 26px; border-radius: 16px;
    background: rgba(0,217,192,.12); border: 1px solid rgba(0,217,192,.55); color: #fff; text-decoration: none; }
  .estado-plan b { font-size: 22px; }
  .estado-plan small { color: #9fb3c8; font-size: 15px; }

  .estado-crono .crono { position: absolute; top: 216px; left: 72px; right: 72px; bottom: 50px; z-index: 2; display: flex; flex-direction: column; --col: 380px;
    background: rgba(6,13,21,.72); border: 1px solid rgba(255,255,255,.1); border-radius: 18px; padding: 18px 26px 14px; }
  .crono-cabecera { display: flex; align-items: flex-end; height: 50px; flex: none; border-bottom: 1px solid rgba(255,255,255,.14); }
  .crono-esquina { width: var(--col); flex: none; color: #9fb3c8; font-size: 15px; letter-spacing: .12em; text-transform: uppercase; padding-bottom: 10px; }
  .crono-semanas { flex: 1; display: grid; grid-template-columns: repeat(var(--semanas), 1fr); min-width: 0; }
  .crono-semanas span { display: flex; flex-direction: column; align-items: center; gap: 2px; padding-bottom: 8px; min-width: 0; overflow: hidden; }
  .crono-semanas b { color: #fff; font-size: 16px; }
  .crono-semanas small { color: #9fb3c8; font-size: 13px; white-space: nowrap; }
  .crono.is-largo .crono-semanas b { font-size: 12px; }
  .crono.is-largo .crono-semanas small { font-size: 10px; }
  .crono-cuerpo { position: relative; flex: 1; min-height: 0; }
  .crono-guias { position: absolute; top: 0; bottom: 0; left: var(--col); right: 0; pointer-events: none; z-index: 1; }
  .crono-hoy { position: absolute; top: 0; bottom: 0; width: 0; border-left: 2px dashed #f59e0b; }
  .crono-hoy em { position: absolute; top: 2px; left: 6px; color: #f59e0b; font-size: 12px; font-style: normal; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; }
  .crono-fila { display: flex; align-items: center; height: var(--fila); border-bottom: 1px solid rgba(255,255,255,.06); }
  .crono-etiqueta { width: var(--col); flex: none; display: flex; align-items: center; gap: 10px; min-width: 0; padding-right: 16px; }
  .crono-etiqueta b { color: #fff; font-size: 17px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .crono-punto { width: 12px; height: 12px; border-radius: 50%; flex: none; background: rgba(255,255,255,.3); }
  .crono-punto.is-listo { background: #00d9c0; }
  .crono-punto.is-en_curso { background: #f59e0b; }
  .crono-pista { position: relative; flex: 1; height: 100%; }
  .crono-plan, .crono-real { position: absolute; border-radius: 6px; }
  .crono-plan { top: 18%; height: 28%; background: rgba(255,255,255,.2); }
  .crono-real { top: 54%; height: 28%; background: #00d9c0; }
  .crono-real.is-en_curso { background: #f59e0b; }
  .crono-pie { display: flex; gap: 26px; align-items: center; flex: none; padding-top: 10px; color: #c9d4e0; font-size: 15px; }
  .crono-muestra { display: inline-block; width: 26px; height: 10px; border-radius: 4px; margin-right: 8px; vertical-align: middle; }
  .crono-muestra.is-plan { background: rgba(255,255,255,.2); }
  .crono-muestra.is-real { background: #00d9c0; }
  .crono-muestra.is-curso { background: #f59e0b; }
  .crono-nota { margin-left: auto; color: #9fb3c8; }

  .estado-hito .panel-head, .estado-evidencias .panel-head, .estado-cambios .panel-head, .estado-alcance .panel-head, .estado-sigue .panel-head { right: 120px; }
  .estado-hito .panel-head h2, .estado-evidencias .panel-head h2 { font-size: 40px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
  .hito-fechas { color: #c9d4e0; font-size: 18px; margin-top: 8px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
  .hito-cols { position: absolute; left: 72px; right: 72px; top: 268px; height: 392px; z-index: 2; display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 24px; }
  .hito-col { display: flex; flex-direction: column; gap: 10px; padding: 16px 22px; overflow: hidden;
    background: rgba(6,13,21,.72); border: 1px solid rgba(255,255,255,.1); border-radius: 16px; }
  .hito-col h3 { color: #00d9c0; font-size: 16px; letter-spacing: .14em; text-transform: uppercase; flex: none; }
  .hito-texto { color: #dbe4ee; font-size: 17px; line-height: 1.45; flex: none; display: -webkit-box; -webkit-line-clamp: 4; -webkit-box-orient: vertical; overflow: hidden; }
  .cierre-puntos, .cierre-acts { list-style: none; display: flex; flex-direction: column; gap: 6px; min-height: 0; }
  .cierre-puntos li { position: relative; padding-left: 18px; color: #fff; font-size: 16px; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
  .cierre-puntos li::before { content: ''; position: absolute; left: 0; top: 8px; width: 8px; height: 8px; border-radius: 50%; background: #00d9c0; }
  .cierre-acts li { display: flex; justify-content: space-between; gap: 12px; font-size: 15px; }
  .cierre-acts b { color: #fff; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .cierre-acts small { color: #9fb3c8; white-space: nowrap; }
  li.cierre-mas { color: #9fb3c8; font-size: 14px; padding-left: 0; }
  li.cierre-mas::before { display: none; }
  .hito-evidencias { position: absolute; left: 72px; right: 72px; top: 680px; height: 316px; z-index: 2; display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 20px; }
  .hito-entrega { position: absolute; right: 72px; bottom: 26px; z-index: 2; color: #00d9c0; font-size: 17px; font-weight: 700; }
  .ev-grid { position: absolute; left: 72px; right: 72px; top: 230px; bottom: 50px; z-index: 2; display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr)); grid-template-rows: repeat(2, minmax(0, 1fr)); gap: 20px; }
  .ev { display: flex; flex-direction: column; gap: 8px; min-width: 0; height: 100%; padding: 14px; overflow: hidden; color: #fff; text-decoration: none;
    background: rgba(6,13,21,.8); border: 1px solid rgba(255,255,255,.12); border-radius: 14px; }
  .ev-cuando { color: #9fb3c8; font-size: 11px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase; flex: none; }
  .ev-cifra b { color: #00d9c0; font-size: 60px; line-height: 1; font-weight: 800; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .ev-cifra span { color: #c9d4e0; font-size: 16px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .ev p, .ev figcaption, .ev-enlace b { font-size: 16px; font-weight: 600; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; flex: none; }
  .ev-fuente { margin-top: auto; color: #9fb3c8; font-size: 12px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
  .ev-captura img, .ev-video video, .ev-video-pdf img { display: block; width: 100%; height: 150px; object-fit: cover; border-radius: 10px; background: #0a1420; flex: none; }
  /* Capturas de un sitio: lo importante está arriba (menú, título y botones); recortar al centro dejaba solo un botón. */
  .ev-captura img { object-position: top center; }
  .ev-video-pdf { display: none; position: relative; color: #fff; text-decoration: none; flex: none; }
  .ev-video-pdf i { display: flex; align-items: center; justify-content: center; width: 100%; height: 150px; border-radius: 10px; background: #0a1420; font-size: 48px; font-style: normal; color: #00d9c0; }
  .ev-video-pdf span { position: absolute; left: 10px; bottom: 10px; padding: 4px 10px; border-radius: 999px; background: #00d9c0; color: #06222a; font-size: 13px; font-weight: 800; }
  .ev-enlace small { color: #00d9c0; font-size: 13px; word-break: break-all; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
  @media print { .ev-video video { display: none; } .ev-video-pdf { display: block; } }

  .estado-lista { position: absolute; left: 72px; right: 72px; top: 220px; z-index: 2; list-style: none; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 18px 28px; }
  .cambio { display: flex; flex-direction: column; gap: 4px; height: 168px; padding: 14px 20px; overflow: hidden;
    background: rgba(6,13,21,.72); border: 1px solid rgba(255,255,255,.1); border-radius: 14px; }
  .cambio small { color: #00d9c0; font-size: 13px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; }
  .cambio b { color: #fff; font-size: 20px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  .cambio p { color: #c9d4e0; font-size: 15px; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
  .estado-nota { position: absolute; left: 72px; bottom: 34px; z-index: 2; color: #9fb3c8; font-size: 16px; }

  .alcance, .sigue { position: absolute; left: 72px; right: 72px; top: 220px; bottom: 56px; z-index: 2; display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 28px; align-items: start; }
  .alcance > div, .sigue > div { padding: 22px 26px; overflow: hidden; max-height: 100%; background: rgba(6,13,21,.72); border: 1px solid rgba(255,255,255,.1); border-radius: 16px; }
  .alcance h3, .sigue h3 { color: #00d9c0; font-size: 17px; letter-spacing: .14em; text-transform: uppercase; margin-bottom: 16px; }
  .alcance ul { list-style: none; columns: 2; column-gap: 24px; }
  .alcance li { break-inside: avoid; position: relative; padding-left: 18px; margin-bottom: 10px; color: #fff; font-size: 16px; line-height: 1.35;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
  .alcance li::before { content: ''; position: absolute; left: 0; top: 8px; width: 8px; height: 8px; border-radius: 50%; background: #00d9c0; }
  .sigue ul { list-style: none; display: flex; flex-direction: column; gap: 12px; }
  .sigue li { display: flex; flex-direction: column; gap: 2px; color: #fff; font-size: 18px; line-height: 1.35; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
  .sigue li b { font-size: 19px; }
  .sigue li small { display: block; color: #9fb3c8; font-size: 15px; }
  .sigue li.is-en_curso small { color: #f59e0b; }
`;

function renderEstadoHtml(data, images = {}) {
  const d = data && typeof data === 'object' ? data : {};
  const fotos = images && typeof images === 'object' ? images : {};
  const slides = [renderPortadaEstado(d, fotos.cover)];
  const usadas = [fotos.cover];
  let n = 1;
  const agregar = (html, foto) => {
    slides.push(html);
    usadas.push(foto);
  };
  n += 1;
  agregar(renderDondeVamos(d, n, fotos.metodo), fotos.metodo);
  n += 1;
  agregar(renderCronogramaReal(d, n, fotos.gantt), fotos.gantt);
  objetos(d.cierres).forEach((cierre, i) => {
    const foto = fotos[`fase_${(i % 3) + 1}`];
    const tarjetas = evidenciasDe(cierre);
    n += 1;
    agregar(renderCierreHito(d, cierre, n, foto, tarjetas.slice(0, EVIDENCIAS_EN_CIERRE)), foto);
    const resto = tarjetas.slice(EVIDENCIAS_EN_CIERRE);
    const partes = Math.ceil(resto.length / EVIDENCIAS_POR_LAMINA);
    for (let p = 0; p < partes; p++) {
      n += 1;
      agregar(renderEvidenciasExtra(cierre, n, foto, resto.slice(p * EVIDENCIAS_POR_LAMINA, (p + 1) * EVIDENCIAS_POR_LAMINA), p + 1, partes), foto);
    }
  });
  if (objetos(d.cambios_plan).length) {
    n += 1;
    agregar(renderCambios(d, n, fotos.portal), fotos.portal);
  }
  n += 1;
  agregar(renderAlcance(d, n, fotos.reuniones), fotos.reuniones);
  n += 1;
  agregar(renderSigue(d, n, fotos.necesitamos), fotos.necesitamos);
  n += 1;
  agregar(renderCierreEstado(d, n, fotos.cierre), fotos.cierre);

  const preload = [...new Set(usadas.filter(Boolean))].map((url) => `<link rel="preload" as="image" href="${escapeHtml(url)}" />`).join('\n');
  return `<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta name="robots" content="noindex, nofollow" />
<title>Estado del proyecto · ${escapeHtml(texto(d.proyecto))}</title>
${preload}
<style>${STYLE}${ESTILO_PORTADA}${ESTILO_GANTT}${ESTILO_CIERRE}${ESTILO_ESTADO}</style>
</head>
<body${d.draft ? ' class="is-draft"' : ''}>
${d.draft ? '<div class="at-draft-badge">Vista previa con borradores · el cliente no la ve</div>' : ''}
<div class="deck">
${slides.join('\n')}
</div>
${renderDeckControls()}
<script>${SCRIPT}</script>
</body>
</html>`;
}

module.exports = { renderEstadoHtml, lineaFechas, fechasBloque, renderEvidencia };
