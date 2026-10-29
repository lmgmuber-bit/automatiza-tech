// Tech WhatsApp: cancelar y reprogramar con botones (diseño 2026-10-29-whatsapp-citas-botones).
// El bloque entre las marcas lo copia N8N/wa-citas/aplicar.py tal cual en los nodos «Mensaje Citas» y «Decidir Botón Cita».
// Funciones puras: sin $() de n8n; las envolturas de cada nodo arman la entrada con la respuesta de /wa-citas.
// Pruebas: node --test N8N/wa-citas/nodos/citas.test.js

// <<WA CITAS: inicio>>
function waCitaTitulo(texto, max) {
  const c = Array.from(String(texto || '').replace(/\s+/g, ' ').trim());
  return c.length <= max ? c.join('') : c.slice(0, max - 1).join('').trimEnd() + '…';
}

// 'cita_<c|r>_<d|s>_<id>' elige una cita; 'cita_ok_…' confirma; 'cita_tengo' es «Tengo una cita».
function waIdCita(cita, accion, confirmar) {
  return 'cita_' + (confirmar ? 'ok_' : '') + (accion === 'cancelar' ? 'c' : 'r') + '_'
    + (cita.tipo === 'diagnostico' ? 'd' : 's') + '_' + Number(cita.id);
}

function waLeerBotonCita(id) {
  if (id === 'cita_tengo') return { tengo: true };
  const m = /^cita_(ok_)?([cr])_([ds])_(\d+)$/.exec(String(id || ''));
  if (!m) return null;
  return { confirmar: Boolean(m[1]), accion: m[2] === 'c' ? 'cancelar' : 'reprogramar',
    tipo: m[3] === 'd' ? 'diagnostico' : 'seguimiento', id: Number(m[4]) };
}

function waTextoCita(to, body) {
  return { messaging_product: 'whatsapp', recipient_type: 'individual', to, type: 'text', text: { body } };
}

function waBotonesCita(to, body, botones) {
  return { messaging_product: 'whatsapp', recipient_type: 'individual', to, type: 'interactive',
    interactive: { type: 'button', body: { text: body }, action: { buttons: botones.map((b) => ({ type: 'reply', reply: { id: b.id, title: b.title } })) } } };
}

// «mar 03-nov 17:00»: la fecha de la etiqueta («… · mar 03-nov · 17:00»), para los títulos cortos.
function waFechaCita(cita) {
  const partes = String(cita.etiqueta || '').split(' · ');
  return partes.length >= 3 ? partes.slice(-2).join(' ') : String(cita.cuando || '');
}

function waTitulosUnicos(citas, max) {
  const usados = new Set();
  return citas.map((c) => {
    let t = waCitaTitulo(waFechaCita(c), max);
    for (let n = 2; usados.has(t); n++) t = waCitaTitulo(waFechaCita(c), max - String(n).length - 1) + ' ' + n;
    usados.add(t);
    return t;
  });
}

function waConfirmarCita(cita, accion, to) {
  const pregunta = accion === 'cancelar' ? '¿La cancelamos?' : '¿La cambiamos de día?';
  return waBotonesCita(to, 'Tienes esta cita: ' + cita.etiqueta + '. ' + pregunta, [
    { id: waIdCita(cita, accion, true), title: accion === 'cancelar' ? 'Sí, cancelar' : 'Sí, cambiar' },
    { id: 'btn_mantener_cita', title: 'No, mantenerla' },
  ]);
}

// r = respuesta de /wa-citas más la situación de wa-contexto: {estado, citas, situacion}. accion: 'cancelar' | 'reprogramar'.
function waMensajeCitas(r, accion, to, aviso) {
  r = r || {};
  const pre = aviso ? aviso + ' ' : '';
  if (r.estado !== 'ok') {
    return { mensaje: waTextoCita(to, 'En este momento no pude revisar tus citas. Ya le avisamos a Luis para que te escriba y lo coordinen.'),
      avisarLuis: true, cita: null };
  }
  const citas = Array.isArray(r.citas) ? r.citas.slice(0, 10) : [];
  if (!citas.length) {
    const agendar = r.situacion === 'prospecto' || r.situacion === 'cliente'
      ? { id: 'seg_general', title: 'Agendar seguimiento' } : { id: 'btn_agendar_demo', title: 'Agendar diagnóstico' };
    return { mensaje: waBotonesCita(to, pre + 'No encontramos citas agendadas con este número.', [agendar, { id: 'cita_tengo', title: 'Tengo una cita' }]),
      avisarLuis: false, cita: null };
  }
  if (citas.length === 1) {
    const m = waConfirmarCita(citas[0], accion, to);
    m.interactive.body.text = pre + m.interactive.body.text;
    return { mensaje: m, avisarLuis: false, cita: citas[0] };
  }
  const pregunta = accion === 'cancelar' ? '¿Cuál quieres cancelar?' : '¿Cuál quieres cambiar de día?';
  if (citas.length <= 3) {
    const lista = citas.map((c, i) => (i + 1) + '. ' + c.etiqueta).join('\n');
    const t = waTitulosUnicos(citas, 20);
    return { mensaje: waBotonesCita(to, pre + 'Tienes estas citas:\n' + lista + '\n' + pregunta, citas.map((c, i) => ({ id: waIdCita(c, accion, false), title: t[i] }))),
      avisarLuis: false, cita: null };
  }
  const t = waTitulosUnicos(citas, 24);
  const filas = citas.map((c, i) => ({ id: waIdCita(c, accion, false), title: t[i],
    description: waCitaTitulo(String(c.etiqueta || '').split(' · ')[0], 72) }));
  return { mensaje: { messaging_product: 'whatsapp', recipient_type: 'individual', to, type: 'interactive',
    interactive: { type: 'list', body: { text: pre + 'Tienes estas citas. ' + pregunta }, action: { button: 'Ver citas', sections: [{ title: 'Tus citas', rows: filas }] } } },
  avisarLuis: false, cita: null };
}

// Un botón cita_… con la lista fresca de /wa-citas. paso: 'confirmar' | 'cancelar' | 'reprogramar' | 'no_disponible' | 'tengo'.
function waDecidirBoton(id, r, to) {
  r = r || {};
  const b = waLeerBotonCita(id);
  if (b && b.tengo) {
    return { paso: 'tengo', mensaje: waTextoCita(to, 'Le avisamos a Luis para que te escriba y lo revisen juntos.'), cita: null, avisarLuis: true };
  }
  if (r.estado !== 'ok') {
    const x = waMensajeCitas(r, b ? b.accion : 'cancelar', to);
    return { paso: 'no_disponible', mensaje: x.mensaje, cita: null, avisarLuis: true };
  }
  const cita = b ? (r.citas || []).find((c) => c.tipo === b.tipo && Number(c.id) === b.id) || null : null;
  if (!cita) {
    const vigentes = r.citas || [];
    const mensaje = vigentes.length
      ? waMensajeCitas(r, b ? b.accion : 'cancelar', to, 'Esa cita ya no está disponible.').mensaje
      : waTextoCita(to, 'Esa cita ya no está disponible. No encontramos otras citas agendadas con este número.');
    return { paso: 'no_disponible', mensaje, cita: null, avisarLuis: false };
  }
  if (!b.confirmar) return { paso: 'confirmar', mensaje: waConfirmarCita(cita, b.accion, to), cita, avisarLuis: false };
  return { paso: b.accion, mensaje: null, cita, avisarLuis: false };
}
// <<WA CITAS: fin>>

if (typeof module !== 'undefined' && module.exports) {
  module.exports = { waCitaTitulo, waIdCita, waLeerBotonCita, waMensajeCitas, waConfirmarCita, waDecidirBoton };
}
