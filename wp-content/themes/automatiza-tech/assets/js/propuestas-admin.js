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

    // C1: aviso en la barra fija cuando Guardar también va a enviar el correo (#send_email marcado
    // y habilitado), y confirmación antes de guardar en ese caso. #client_email y #send_email viven
    // en pestañas que pueden estar ocultas (hidden), pero siguen en el DOM y son consultables.
    var avisoEnvio = guardar ? guardar.querySelector('.at-pa-guardar-aviso') : null;
    var campoSendEmail = form.querySelector('#send_email');
    var campoClientEmail = form.querySelector('#client_email');
    var actualizarAvisoEnvio = function () {
      if (!avisoEnvio) { return; }
      if (campoSendEmail && campoSendEmail.checked && !campoSendEmail.disabled) {
        var correo = campoClientEmail ? campoClientEmail.value : '';
        avisoEnvio.textContent = 'Al guardar también se enviará el correo a ' + correo + '.';
        avisoEnvio.hidden = false;
      } else {
        avisoEnvio.textContent = '';
        avisoEnvio.hidden = true;
      }
    };
    if (campoSendEmail) { campoSendEmail.addEventListener('change', actualizarAvisoEnvio); }
    if (campoClientEmail) { campoClientEmail.addEventListener('input', actualizarAvisoEnvio); }
    actualizarAvisoEnvio();

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
    form.addEventListener('submit', function (e) {
      primerInvalido = true;
      // C1: fuera de los botones de Revisión (name="at_v3_accion"), Guardar reenvía el correo si
      // #send_email sigue marcado y habilitado. Cubre clic en Guardar, Enter (pasa por el botón
      // oculto por defecto, sin name) y el caso sin e.submitter (Safari viejo): ambos se tratan
      // como guardado normal, igual que pide el brief.
      if (!(e.submitter && e.submitter.name === 'at_v3_accion')) {
        if (campoSendEmail && campoSendEmail.checked && !campoSendEmail.disabled) {
          var correoConfirmar = campoClientEmail ? campoClientEmail.value : '';
          if (!window.confirm('Además de guardar, se enviará el correo con la propuesta a ' + correoConfirmar + '. ¿Continuar?')) {
            e.preventDefault();
          }
        }
      }
    });
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
  // botones «Aplicar» id="doaction"/"doaction2"). Buscar y Filtrar comparten ese mismo <form>: cuando
  // el navegador da SubmitEvent.submitter, solo se confirma si ese botón fue uno de los «Aplicar»
  // (Buscar/Filtrar nunca preguntan). Sin submitter (Safari viejo), hay un respaldo más abajo.
  var selectorTop = document.getElementById('bulk-action-selector-top');
  var listaForm = selectorTop ? selectorTop.closest('form') : null;
  if (listaForm) {
    listaForm.addEventListener('submit', function (e) {
      var borrando;
      if (e.submitter) {
        // Camino preciso: sabemos exactamente qué botón envió el formulario, así que Buscar y
        // Filtrar (que comparten este mismo <form>) nunca disparan la confirmación.
        var boton = e.submitter;
        if (boton.id !== 'doaction' && boton.id !== 'doaction2') { return; }
        var nombreSelect = boton.id === 'doaction' ? 'action' : 'action2';
        var select = listaForm.elements[nombreSelect];
        borrando = !!select && select.value === 'borrar';
      } else {
        // Respaldo: Safari < 15.4 y otros navegadores viejos no dan SubmitEvent.submitter, así que
        // no hay forma de saber qué botón se apretó. La regla es «nunca borrar sin confirmar», así
        // que se trata como borrado masivo si cualquiera de los dos selectores de acción (arriba o
        // abajo) está en "borrar" y hay filas marcadas. Efecto secundario aceptado a propósito: en
        // ese caso, si alguien deja «Borrar» elegido y aprieta Buscar o Filtrar, también preguntará;
        // es la dirección segura — preguntar de más nunca borra nada, no preguntar sí puede.
        var accionTop = listaForm.elements['action'];
        var accionBottom = listaForm.elements['action2'];
        borrando = (!!accionTop && accionTop.value === 'borrar') || (!!accionBottom && accionBottom.value === 'borrar');
      }
      if (!borrando) { return; }
      var marcados = listaForm.querySelectorAll('input[name="proposal_ids[]"]:checked');
      if (!marcados.length) { return; }
      if (!window.confirm('¿Borrar ' + marcados.length + ' propuesta(s)? No se puede deshacer.')) {
        e.preventDefault();
      }
    });

    // ---------- Lista: botón «🗑️ Borrar marcadas» (fuera del selector de acción masiva) ----------
    // Corre en el 'click' del botón, antes de que el navegador dispare 'submit' (y existe en todos
    // los navegadores, a diferencia de SubmitEvent.submitter). El listener de submit de arriba no
    // vuelve a preguntar por este botón: su e.submitter.id no es "doaction"/"doaction2", así que
    // retorna enseguida.
    var botonBorrarMarcadas = listaForm.querySelector('.at-borrar-marcadas');
    if (botonBorrarMarcadas) {
      botonBorrarMarcadas.addEventListener('click', function (e) {
        var marcadas = listaForm.querySelectorAll('input[name="proposal_ids[]"]:checked');
        if (!marcadas.length) {
          window.alert('Marca al menos una propuesta para borrar.');
          e.preventDefault();
          return;
        }
        if (!window.confirm('¿Borrar ' + marcadas.length + ' propuesta(s)? No se puede deshacer.')) {
          e.preventDefault();
        }
      });
    }
  }

  // ---------- Lista: URL limpia tras un borrado, Buscar, Filtrar o paginar ----------
  // Sin esto, F5 repite ?action=borrar&proposal_ids[]=…&_wpnonce=… (o ?delete_id=…&_wpnonce=…): no borra
  // nada de nuevo porque las filas ya no están, pero vuelve a mostrar el aviso de éxito como si acabara
  // de pasar. Buscar, Filtrar y paginar dejan su propia basura (_wpnonce, _wp_http_referer y el "-1" de
  // los selectores de acción masiva cuando no se eligió ninguna): se limpia siempre que aparezca algo.
  if (selectorTop) {
    var params = new URLSearchParams(window.location.search);
    var huboBorrado = params.has('delete_id') || params.get('action') === 'borrar' || params.get('action2') === 'borrar' || params.get('bulk_action') === 'borrar_marcadas';
    var cambios = false;
    if (huboBorrado) {
      var fijas = ['action', 'action2', '_wpnonce', '_wp_http_referer', 'delete_id', 'bulk_action', 'filtrar'];
      // El script de URL canónica de WordPress reescribe "proposal_ids[]" a "proposal_ids[0]" antes de
      // que corra este replaceState, así que hay que borrar cualquier clave que empiece con "proposal_ids",
      // sea cual sea su índice o si trae corchetes o no (no alcanza con el nombre literal "proposal_ids[]").
      var claves = [];
      params.forEach(function (_, k) { claves.push(k); });
      claves.forEach(function (k) {
        if (fijas.indexOf(k) !== -1 || k.indexOf('proposal_ids') === 0) { params.delete(k); cambios = true; }
      });
    } else {
      // Sin borrado: Buscar, Filtrar o una página nueva conservan s, grupo, desde, hasta, orderby,
      // order y paged tal cual; solo se quita el nonce, el referer y "-1" (ninguna acción elegida).
      ['_wpnonce', '_wp_http_referer'].forEach(function (k) {
        if (params.has(k)) { params.delete(k); cambios = true; }
      });
      ['action', 'action2'].forEach(function (k) {
        if (params.get(k) === '-1') { params.delete(k); cambios = true; }
      });
    }
    if (cambios) {
      var query = params.toString();
      var limpia = window.location.pathname + (query ? '?' + query : '') + window.location.hash;
      window.history.replaceState(null, '', limpia);
    }
  }
})();
