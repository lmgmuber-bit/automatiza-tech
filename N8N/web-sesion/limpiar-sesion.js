// Nodo «Limpiar sesión» de «Agente Tech Web (PROD)» (3A5Y1NPBxpTjhHdE), entre «Switch Action» (saveLead) y
// «Check Availability» (16-nov-2026). WordPress deduce el canal de la agenda por el session_id (inc/lead-origen.php):
// «ig:» = Instagram, «whatsapp» = WhatsApp. Una agenda que entra por la web siempre queda como web: el identificador se
// rehace como «web-<letras, números, - y _>» y no se deja pasar un session_id ni un source paralelos.
const salida = [];
for (const item of $input.all()) {
  const j = JSON.parse(JSON.stringify(item.json || {}));
  const cuerpo = (j.body && typeof j.body === 'object') ? j.body : null;
  const crudo = String((cuerpo && cuerpo.sessionId) || j.sessionId || '');
  const limpio = crudo.replace(/whatsapp/gi, '').replace(/^ig/i, '').replace(/[^A-Za-z0-9_-]/g, '').slice(0, 60);
  const sesion = 'web-' + (limpio || 'sin-sesion');
  if (cuerpo) {
    cuerpo.sessionId = sesion;
    delete cuerpo.session_id;
    delete cuerpo.source;
  }
  j.sessionId = sesion;
  delete j.session_id;
  delete j.source;
  salida.push({ json: j });
}
return salida;
