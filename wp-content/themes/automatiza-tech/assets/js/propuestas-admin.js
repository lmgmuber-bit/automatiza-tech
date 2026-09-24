/* Módulo Propuestas (wp-admin): pestañas de la ficha, «Siguiente paso», Enter en precios v3, Copiar
   transcripción, confirmación de borrado masivo en la lista y URL limpia tras un borrado. */
(function () {
  'use strict';

  // ---------- Ficha: solo corre si la página tiene el formulario de la ficha ----------
  var form = document.querySelector('.at-pa-form');
  if (form) {
    form.classList.add('at-pa-js');
    var campo = form.querySelector('.at-pa-tab-actual');
    var tabs = form.querySelectorAll('.at-pa-tab');
    var sel = form.querySelector('.at-pa-tabs-movil');
    var guardar = form.querySelector('.at-pa-guardar');

    var mostrar = function (clave) {
      if (!form.querySelector('.at-pa-panel[data-panel="' + clave + '"]')) { clave = 'resumen'; }
      form.querySelectorAll('.at-pa-panel').forEach(function (p) { p.hidden = p.getAttribute('data-panel') !== clave; });
      tabs.forEach(function (t) { t.setAttribute('aria-selected', t.getAttribute('data-tab') === clave ? 'true' : 'false'); });
      if (sel) { sel.value = clave; }
      if (campo) { campo.value = clave; }
      // Decisión de Luis: en Revisión, Guardar no guarda precios; la barra fija se esconde para no confundir.
      if (guardar) { guardar.hidden = clave === 'revision'; }
    };

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
  }

  // ---------- Lista: confirmar el borrado masivo ----------
  // La lista es un único <form method="get"> con buscador, filtros de fecha y los selectores de
  // acción masiva de WP_List_Table (id="bulk-action-selector-top"/"-bottom", name="action"/"action2",
  // botones «Aplicar» id="doaction"/"doaction2"). Buscar y Filtrar comparten ese mismo <form>, así que
  // solo se confirma cuando el botón que disparó el envío (event.submitter) es uno de los «Aplicar».
  var selectorTop = document.getElementById('bulk-action-selector-top');
  var listaForm = selectorTop ? selectorTop.closest('form') : null;
  if (listaForm) {
    listaForm.addEventListener('submit', function (e) {
      var boton = e.submitter;
      if (!boton || (boton.id !== 'doaction' && boton.id !== 'doaction2')) { return; }
      var nombreSelect = boton.id === 'doaction' ? 'action' : 'action2';
      var select = listaForm.elements[nombreSelect];
      if (!select || select.value !== 'borrar') { return; }
      var marcados = listaForm.querySelectorAll('input[name="proposal_ids[]"]:checked');
      if (!marcados.length) { return; }
      if (!window.confirm('¿Borrar ' + marcados.length + ' propuesta(s)? No se puede deshacer.')) {
        e.preventDefault();
      }
    });
  }

  // ---------- Lista: URL limpia tras un borrado (masivo o de una fila) ----------
  // Sin esto, F5 repite ?action=borrar&proposal_ids[]=…&_wpnonce=… (o ?delete_id=…&_wpnonce=…): no borra
  // nada de nuevo porque las filas ya no están, pero vuelve a mostrar el aviso de éxito como si acabara
  // de pasar. Se limpia solo cuando la URL trae evidencia real de un borrado ya hecho.
  if (selectorTop) {
    var params = new URLSearchParams(window.location.search);
    var huboBorrado = params.has('delete_id') || params.get('action') === 'borrar' || params.get('action2') === 'borrar';
    if (huboBorrado) {
      ['action', 'action2', 'proposal_ids[]', '_wpnonce', '_wp_http_referer', 'delete_id', 'bulk_action', 'filtrar'].forEach(function (k) {
        params.delete(k);
      });
      var query = params.toString();
      var limpia = window.location.pathname + (query ? '?' + query : '') + window.location.hash;
      window.history.replaceState(null, '', limpia);
    }
  }
})();
