// Arma el asunto y el HTML del resumen de envios de resenas.
// Sin require, sin red. Pensado para pegarse en un nodo Code de n8n.
function armarCorreo(resumen, origen) {
  function esc(v) {
    return String(v === null || v === undefined ? '' : v)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }
  var filas = Array.isArray(resumen.filas) ? resumen.filas : [];
  var total = Number(resumen.total) || 0;
  var ok = Number(resumen.ok) || 0;
  var asunto = 'Reseñas enviadas: ' + ok + ' de ' + total + ' con todo OK';
  if (origen === 'manual') {
    asunto += ' (envío manual)';
  }

  function celdaCanal(c) {
    c = c || { simbolo: '—', texto: '' };
    return '<td style="padding:8px 10px;border-bottom:1px solid #E5E7EB;vertical-align:top;">' +
      '<div style="font-size:18px;line-height:22px;">' + esc(c.simbolo) + '</div>' +
      '<div style="font-size:12px;color:#6B7280;">' + esc(c.texto) + '</div></td>';
  }

  var cuerpo = '';
  for (var i = 0; i < filas.length; i++) {
    var f = filas[i];
    var cli = esc(f.cliente) + (f.modo === 'prueba' ? ' (prueba)' : '');
    cuerpo += '<tr>' +
      '<td style="padding:8px 10px;border-bottom:1px solid #E5E7EB;vertical-align:top;font-weight:bold;">' + cli + '</td>' +
      '<td style="padding:8px 10px;border-bottom:1px solid #E5E7EB;vertical-align:top;">' + esc(f.proyecto) + '</td>' +
      celdaCanal(f.correo) + celdaCanal(f.whatsapp) + '</tr>';
  }

  var cuando = String(resumen.desde || '');
  try {
    var dt = new Date(resumen.desde);
    if (!isNaN(dt.getTime())) {
      cuando = dt.toLocaleString('es-CL', { timeZone: 'America/Santiago' } );
    }
  } catch (e) {
    cuando = String(resumen.desde || '');
  }

  var th = 'background:#0B2A4A;padding:8px 10px;text-align:left;color:#FFFFFF;font-size:13px;';
  var mas = '';
  if (total > filas.length) {
    mas = '<div style="padding:10px;font-size:13px;color:#6B7280;">Y ' + (total - filas.length) +
      ' envíos más que no caben en este correo.</div>';
  }
  var html = '<div style="font-family:Arial,Helvetica,sans-serif;color:#111827;">' +
    '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;border-collapse:collapse;">' +
    '<tr><td style="background:#0B2A4A;padding:16px 10px;border-bottom:4px solid #14B8A6;">' +
    '<div style="color:#FFFFFF;font-size:18px;font-weight:bold;">' + esc(asunto) + '</div></td></tr>' +
    '<tr><td><table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:14px;">' +
    '<tr>' +
    '<th style="' + th + '">Cliente</th><th style="' + th + '">Proyecto</th>' +
    '<th style="' + th + '">Correo</th><th style="' + th + '">WhatsApp</th></tr>' +
    cuerpo + '</table>' + mas + '</td></tr>' +
    '<tr><td style="padding:12px 10px;font-size:12px;color:#6B7280;">' +
    'Envíos desde ' + esc(cuando) + '. Correo automático de AutomatizaTech.</td></tr>' +
    '</table></div>';

  return { aviso_a: resumen.aviso_a, asunto: asunto, html: html, enviar: total > 0 };
}

if (typeof module !== 'undefined') module.exports = { armarCorreo };
