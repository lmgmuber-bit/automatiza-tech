/* Huella del evento Lead en el navegador: la misma que calcula el servidor (inc/meta-capi/puras.php), para que Meta
 * junte el Lead del píxel con el de la API de Conversiones. sha256(correo|AAAA-MM-DD|HH:MM), primeros 40 hex. */
(function (w) {
  'use strict';
  function normCorreo(c) { return String(c || '').replace(/\s+/g, '').toLowerCase(); }
  function normFecha(f) {
    var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(f || '').trim());
    if (!m) return '';
    var d = new Date(Date.UTC(+m[1], +m[2] - 1, +m[3]));
    return (d.getUTCFullYear() === +m[1] && d.getUTCMonth() === +m[2] - 1 && d.getUTCDate() === +m[3]) ? m[1] + '-' + m[2] + '-' + m[3] : '';
  }
  function normHora(h) {
    var m = /^(\d{1,2}):(\d{2})(?::\d{2})?$/.exec(String(h || '').trim());
    if (!m || +m[1] > 23 || +m[2] > 59) return '';
    return (m[1].length === 1 ? '0' + m[1] : m[1]) + ':' + m[2];
  }
  function sha256(texto) {
    var sutil = w.crypto && w.crypto.subtle;
    if (!texto || !sutil) return Promise.resolve('');
    return sutil.digest('SHA-256', new TextEncoder().encode(texto)).then(function (buf) {
      var b = new Uint8Array(buf), s = '';
      for (var i = 0; i < b.length; i++) s += (b[i] < 16 ? '0' : '') + b[i].toString(16);
      return s;
    }, function () { return ''; });
  }
  function eventId(correo, fecha, hora) {
    var c = normCorreo(correo), f = normFecha(fecha), h = normHora(hora);
    if (!c || !f || !h) return Promise.resolve('');
    return sha256(c + '|' + f + '|' + h).then(function (s) { return s.slice(0, 40); });
  }
  // sha256 del correo normalizado: la marca de «ya avisó» del navegador nunca guarda el correo en claro.
  function huellaCorreo(correo) { return sha256(normCorreo(correo)); }
  w.AtLeadId = { normCorreo: normCorreo, normFecha: normFecha, normHora: normHora, eventId: eventId, huellaCorreo: huellaCorreo };
})(window);
