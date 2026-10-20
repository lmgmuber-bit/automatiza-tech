/* retomar.php: abre el diálogo «Agendemos 20 minutos» y envía la hora elegida a POST automatiza-tech/v1/retomar-agenda.
 * El selector de horarios es at-agenda.js (el de la portada): escribe la hora en #at-scheduled-time.
 * Sin JavaScript, el botón de la barra es un enlace a &agendar=1, que trae el formulario de siempre en <noscript>. */
(function () {
  'use strict';
  var cfg = window.AT_RT_VER || {};
  var dlg = document.getElementById('at-rt-agenda');
  if (!dlg) return;
  function abrir() { if (typeof dlg.showModal === 'function') { if (!dlg.open) dlg.showModal(); } else { dlg.setAttribute('open', ''); } }
  Array.prototype.forEach.call(document.querySelectorAll('[data-at-rt-abrir]'), function (b) {
    b.addEventListener('click', function (e) { e.preventDefault(); abrir(); });
  });
  var cerrar = document.getElementById('at-rt-cerrar');
  if (cerrar) cerrar.addEventListener('click', function () { dlg.close ? dlg.close() : dlg.removeAttribute('open'); });
  if (cfg.abrir) abrir();

  var form = document.getElementById('at-rt-form-agenda');
  var msg = document.getElementById('at-rt-agenda-msg');
  var boton = form.querySelector('button[type=submit]');
  var enviando = false;
  function aviso(tipo, texto) { msg.className = 'at-pt-msg at-pt-msg--' + tipo; msg.textContent = texto; msg.hidden = false; }
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (enviando) return;
    var fecha = document.getElementById('at-fecha').value;
    var hora = (document.getElementById('at-scheduled-time').value || '').slice(0, 5);
    if (!fecha || !hora) { aviso('aviso', 'Elige un día y una hora.'); return; }
    enviando = true;
    boton.disabled = true;
    aviso('aviso', 'Agendando…');
    // Sin cookies: la ruta es pública y valida el token del seguimiento (con la sesión de WordPress, la API exigiría otro nonce).
    fetch(cfg.url, {
      method: 'POST', credentials: 'omit', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ t: cfg.t, token: cfg.token, fecha: fecha, hora: hora, sitio_web: form.elements.sitio_web.value })
    })
      .then(function (r) { return r.json().catch(function () { return {}; }); })
      .then(function (j) {
        if (j && j.ok) {
          aviso('ok', j.mensaje);
          form.querySelector('.at-pt-campos').hidden = true;
          boton.hidden = true;
          Array.prototype.forEach.call(document.querySelectorAll('[data-at-rt-abrir]'), function (b) { b.hidden = true; });
        } else {
          aviso('error', (j && j.mensaje) || 'No pudimos agendar la llamada. Inténtalo de nuevo o escríbenos por WhatsApp.');
          boton.disabled = false;
        }
      })
      .catch(function () { aviso('error', 'Sin conexión. Inténtalo de nuevo.'); boton.disabled = false; })
      .then(function () { enviando = false; });
  });
})();
