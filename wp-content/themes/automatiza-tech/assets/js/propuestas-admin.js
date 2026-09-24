/* Módulo Propuestas (wp-admin): pestañas de la ficha, «Siguiente paso», Enter en precios v3 y Copiar transcripción. */
(function () {
  'use strict';
  var form = document.querySelector('.at-pa-form');
  if (!form) { return; }
  form.classList.add('at-pa-js');
  var campo = form.querySelector('.at-pa-tab-actual');
  var tabs = form.querySelectorAll('.at-pa-tab');
  var sel = form.querySelector('.at-pa-tabs-movil');
  var guardar = form.querySelector('.at-pa-guardar');

  function mostrar(clave) {
    if (!form.querySelector('.at-pa-panel[data-panel="' + clave + '"]')) { clave = 'resumen'; }
    form.querySelectorAll('.at-pa-panel').forEach(function (p) { p.hidden = p.getAttribute('data-panel') !== clave; });
    tabs.forEach(function (t) { t.setAttribute('aria-selected', t.getAttribute('data-tab') === clave ? 'true' : 'false'); });
    if (sel) { sel.value = clave; }
    if (campo) { campo.value = clave; }
    // Decisión de Luis: en Revisión, Guardar no guarda precios; la barra fija se esconde para no confundir.
    if (guardar) { guardar.hidden = clave === 'revision'; }
  }

  tabs.forEach(function (t) { t.addEventListener('click', function () { mostrar(t.getAttribute('data-tab')); }); });
  if (sel) { sel.addEventListener('change', function () { mostrar(sel.value); }); }
  form.querySelectorAll('.at-pa-ir').forEach(function (b) {
    b.addEventListener('click', function () { mostrar(b.getAttribute('data-ir')); window.scrollTo(0, 0); });
  });
  // Un campo obligatorio vacío en una pestaña oculta bloquearía el envío sin mostrar nada: se abre su pestaña.
  // Solo el primer 'invalid' del intento cambia de pestaña; los siguientes (otros campos, otras pestañas)
  // no deben seguir saltando. La bandera se reinicia en el próximo 'submit' o, ya en este mismo intento
  // fallido, apenas termina la ráfaga síncrona de eventos 'invalid' (setTimeout de 0).
  var primerInvalido = true;
  form.addEventListener('submit', function () { primerInvalido = true; });
  form.addEventListener('invalid', function (e) {
    if (!primerInvalido) { return; }
    primerInvalido = false;
    setTimeout(function () { primerInvalido = true; }, 0);
    var panel = e.target.closest('.at-pa-panel');
    if (panel && panel.hidden) { mostrar(panel.getAttribute('data-panel')); }
  }, true);
  // Enter en los campos de la revisión v3 no envía: el guardado normal ignora esos precios (revisión 14b, M3).
  form.querySelectorAll('.v3-section input').forEach(function (el) {
    el.addEventListener('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); } });
  });
  form.querySelectorAll('.at-pa-copiar').forEach(function (b) {
    var original = b.textContent;
    b.addEventListener('click', function () {
      var t = form.querySelector(b.getAttribute('data-copiar'));
      if (!t) { return; }
      var terminar = function (texto) {
        b.textContent = texto;
        setTimeout(function () { b.textContent = original; }, 2000);
      };
      var conExecCommand = function () {
        var ok = false;
        try {
          t.select();
          ok = document.execCommand('copy');
        } catch (err) { ok = false; }
        terminar(ok ? 'Copiado ✓' : 'No se pudo copiar');
      };
      if (navigator.clipboard) {
        navigator.clipboard.writeText(t.value).then(function () { terminar('Copiado ✓'); }, conExecCommand);
      } else {
        conExecCommand();
      }
    });
  });
  mostrar(form.getAttribute('data-tab-inicial') || 'resumen');
})();
