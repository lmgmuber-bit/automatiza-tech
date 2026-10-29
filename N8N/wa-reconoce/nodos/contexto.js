// Tech WhatsApp reconoce a quien escribe (diseño 2026-10-28-whatsapp-reconoce-cliente).
// El bloque entre las marcas lo copia N8N/wa-reconoce/aplicar.py tal cual en los nodos «Sumar Contexto» y
// «Elegir Seguimiento» del flujo de WhatsApp. Funciones puras: sin $() de n8n; las envolturas de cada nodo arman la entrada.
// Pruebas: node --test N8N/wa-reconoce/nodos/contexto.test.js

// <<WA RECONOCE: inicio>>
function waTituloCorto(texto, max) {
  const c = Array.from(String(texto || '').replace(/\s+/g, ' ').trim());
  return c.length <= max ? c.join('') : c.slice(0, max - 1).join('').trimEnd() + '…';
}

function waLineaContexto(ctx, prop) {
  ctx = ctx || {};
  prop = prop || {};
  const partes = [];
  const sit = ctx.situacion;
  const opciones = Array.isArray(ctx.opciones) ? ctx.opciones : [];
  const nombres = (tipo) => opciones.filter((o) => o.tipo === tipo).map((o) => '«' + String(o.nombre || '').replace(/\s+/g, ' ').trim() + '»');
  const diag = String(ctx.diagnostico_proximo || '');
  if (sit === 'cliente' || sit === 'prospecto' || (sit === 'nuevo' && diag)) {
    partes.push(sit === 'cliente' ? 'quien escribe es cliente de AutomatizaTech.'
      : sit === 'prospecto' ? 'quien escribe es un prospecto: ya tuvo una reunión con nosotros.'
      : 'quien escribe es una persona nueva.');
    if (ctx.nombre) partes.push('Se llama ' + ctx.nombre + '.');
    else if (opciones.length) partes.push('Este número está en más de una ficha: no lo saludes por nombre.');
    const proy = nombres('proyecto');
    const props = nombres('propuesta');
    if (proy.length) partes.push('Sus proyectos: ' + proy.join(', ') + '.');
    if (props.length) partes.push('Sus propuestas abiertas: ' + props.join(', ') + '.');
    else if (sit === 'prospecto') partes.push('No tiene propuestas abiertas.');
    if (ctx.elegida && ctx.elegida.nombre) partes.push('Escribe desde el plan del proyecto «' + ctx.elegida.nombre + '».');
    const m = /^(\d{4})-(\d{2})-(\d{2}) (\d{2}:\d{2})/.exec(diag);
    if (m) partes.push('Tiene un diagnóstico gratis agendado para el ' + m[3] + '-' + m[2] + ' a las ' + m[4] + '.');
  }
  if (prop.tiene === true && prop.url) {
    const quien = String(prop.empresa || prop.nombre || 'su empresa').replace(/\s+/g, ' ').trim();
    const propuesto = String(prop.propuesto || 'el detalle está en la propuesta').replace(/\s+/g, ' ').trim();
    partes.push((partes.length ? 'Además, tiene' : 'este número tiene') + ' la propuesta de AutomatizaTech para ' + quien
      + ' esperando respuesta. Lo propuesto para partir: ' + propuesto + '. Enlace para responder: ' + prop.url);
  }
  return partes.length ? '[Contexto interno, no la repitas: ' + partes.join(' ') + ']' : '';
}

function waElegirSeguimiento(ctx, entrada) {
  ctx = ctx || {};
  const e = entrada || {};
  const opciones = Array.isArray(ctx.opciones) ? ctx.opciones : [];
  const sit = ctx.situacion || '';
  let opcion = null;
  const b = /^seg_([cp])_(\d+)$/.exec(String(e.buttonId || ''));
  if (b) {
    const tipo = b[1] === 'c' ? 'proyecto' : 'propuesta';
    opcion = opciones.find((o) => o.tipo === tipo && Number(o.id) === Number(b[2])) || null;
  }
  if (!opcion && ctx.elegida) opcion = ctx.elegida;
  if (!opcion && opciones.length === 1) opcion = opciones[0];
  const top = ctx.agenda || {};
  const ag = (opcion && opcion.agenda) || top;
  const correo = String((opcion && opcion.agenda && opcion.agenda.correo) || top.correo || '');
  const datos = {
    phoneNumber: e.phoneNumber || '',
    phoneNumberId: e.phoneNumberId || '',
    contactName: ag.nombre_completo || ctx.nombre || e.contactName || '',
    appointmentType: 'Seguimiento',
    output: e.output || '',
    clientEmail: correo,
    clientType: sit,
    companyName: ag.empresa || '',
  };
  if (!opcion && opciones.length >= 2) {
    return { modo: 'elegir', datos, guardar: null, mensaje: waMensajeOpciones(ctx, opciones, e.phoneNumber || ''), opcion: null };
  }
  if ((sit === 'cliente' || sit === 'prospecto') && !correo) {
    return { modo: 'sin_correo', datos, guardar: null, mensaje: null, opcion };
  }
  let subject = 'Reunión de Seguimiento';
  let notes = 'Agendada desde whatsapp';
  if (opcion && opcion.tipo === 'proyecto') {
    subject = 'Seguimiento: ' + opcion.nombre;
    notes = opcion.plan_codigo ? 'Plan de trabajo ' + opcion.plan_codigo + '. Agendada por el cliente por WhatsApp.'
      : 'Seguimiento del proyecto «' + opcion.nombre + '». Agendada por WhatsApp.';
  } else if (opcion && opcion.tipo === 'propuesta') {
    subject = 'Seguimiento: ' + opcion.nombre;
    notes = 'Seguimiento de la propuesta «' + opcion.nombre + '». Agendada por WhatsApp.';
  }
  const guardar = {
    name: ag.nombre_completo || datos.contactName,
    email: correo,
    company: ag.empresa || '',
    subject,
    notes,
    contrato_id: opcion && opcion.tipo === 'proyecto' ? Number(opcion.id) : 0,
    propuesta_id: opcion && opcion.tipo === 'propuesta' ? Number(opcion.id) : 0,
  };
  return { modo: 'directo', datos, guardar, mensaje: null, opcion };
}

function waMensajeOpciones(ctx, opciones, to) {
  const hayProy = opciones.some((o) => o.tipo === 'proyecto');
  const hayProp = opciones.some((o) => o.tipo === 'propuesta');
  const pregunta = hayProy && hayProp ? '¿Sobre cuál quieres agendar el seguimiento?'
    : hayProy ? '¿De cuál proyecto quieres agendar el seguimiento?' : '¿Sobre cuál propuesta quieres conversar?';
  const text = (ctx.nombre ? 'Hola ' + ctx.nombre + ' 👋 ' : 'Hola 👋 ') + pregunta;
  const lista = opciones.length > 3;
  const max = lista ? 24 : 20;
  const vistos = {};
  const items = opciones.slice(0, 10).map((o) => {
    let t = waTituloCorto(o.nombre, max);
    if (vistos[t]) {
      const n = ++vistos[t];
      t = waTituloCorto(o.nombre, max - String(n).length - 1) + ' ' + n;
    } else {
      vistos[t] = 1;
    }
    return { id: (o.tipo === 'proyecto' ? 'seg_c_' : 'seg_p_') + Number(o.id), title: t, tipo: o.tipo };
  });
  const interactive = lista
    ? { type: 'list', body: { text }, action: { button: 'Ver opciones', sections: [{ title: 'Elige una', rows: items.map((i) => ({ id: i.id, title: i.title, description: i.tipo === 'proyecto' ? 'Proyecto' : 'Propuesta' })) }] } }
    : { type: 'button', body: { text }, action: { buttons: items.map((i) => ({ type: 'reply', reply: { id: i.id, title: i.title } })) } };
  return { messaging_product: 'whatsapp', recipient_type: 'individual', to, type: 'interactive', interactive };
}
// <<WA RECONOCE: fin>>

if (typeof module !== 'undefined' && module.exports) {
  module.exports = { waTituloCorto, waLineaContexto, waElegirSeguimiento, waMensajeOpciones };
}
