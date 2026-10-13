/* Plan de trabajo (inc/plan-trabajo/panel.php): pestaña «🗓️ Plan de trabajo» de la ficha del cliente en el CRM.
   - Abre la pestaña si la URL trae #tab-plan, pt o pt_msg (el JS de pestañas del CRM no mira la URL) y quita
     pt_msg de la barra para que recargar no repita el aviso.
   - Agrega y quita actividades y bloques de la tabla editable.
   - Al guardar, arma el JSON del plan en el campo oculto plan_json (el servidor lo valida entero).
   - Con cambios sin guardar en la tabla, no deja «Pedir cambios» ni «Aprobar» (se perderían).
   - Tras enviar un formulario de la pestaña, desactiva sus botones (evita el doble clic). */
(function () {
  'use strict';

  function abrirPestanaPlan() {
    var boton = document.querySelector('.ficha-tab[data-target="tab-plan"]');
    var panel = document.getElementById('tab-plan');
    var col = boton ? boton.closest('.ficha-col') : null;
    if (!boton || !panel || !col) { return; }
    Array.prototype.forEach.call(col.querySelectorAll('.ficha-tab'), function (t) { t.classList.remove('active'); });
    Array.prototype.forEach.call(col.querySelectorAll('.ficha-tab-content'), function (c) { c.classList.remove('active'); });
    boton.classList.add('active');
    panel.classList.add('active');
  }

  function valor(raiz, selector) {
    var el = raiz.querySelector(selector);
    return el ? String(el.value || '').trim() : '';
  }

  function marcado(raiz, selector) {
    var el = raiz.querySelector(selector);
    return !!(el && el.checked);
  }

  /* Fases → bloques → actividades, en el orden de la pantalla. Se omiten las actividades sin nombre y los
     bloques y fases que quedan vacíos. Los días van como número (0 si no es un número: el servidor lo rechaza). */
  function serializar(form) {
    var fases = [];
    Array.prototype.forEach.call(form.querySelectorAll('.at-pt-fase'), function (f) {
      var bloques = [];
      Array.prototype.forEach.call(f.querySelectorAll('.at-pt-bloque'), function (b) {
        var actividades = [];
        Array.prototype.forEach.call(b.querySelectorAll('.at-pt-act'), function (a) {
          var nombre = valor(a, '.at-pt-a-nombre');
          if (nombre === '') { return; }
          var dias = parseInt(valor(a, '.at-pt-a-dias'), 10);
          actividades.push({
            nombre: nombre,
            detalle: valor(a, '.at-pt-a-detalle'),
            responsable: valor(a, '.at-pt-a-responsable') || 'at',
            dias_habiles: isNaN(dias) ? 0 : dias,
            en_paralelo: marcado(a, '.at-pt-a-paralelo'),
            servicio: a.getAttribute('data-servicio') || '',
            etapa: a.getAttribute('data-etapa') || '',
            origen: a.getAttribute('data-origen') || 'luis'
          });
        });
        if (!actividades.length) { return; }
        bloques.push({
          nombre: valor(b, '.at-pt-b-nombre'),
          entregable: valor(b, '.at-pt-b-entregable'),
          entrega: marcado(b, '.at-pt-b-entrega'),
          actividades: actividades
        });
      });
      if (!bloques.length) { return; }
      fases.push({ clave: f.getAttribute('data-clave') || '', descripcion: valor(f, '.at-pt-f-descripcion'), bloques: bloques });
    });
    return { fases: fases };
  }

  function clonar(id) {
    var t = document.getElementById(id);
    return t && t.content && t.content.firstElementChild ? t.content.firstElementChild.cloneNode(true) : null;
  }

  function iniciarFormulario(form) {
    function marcarSucio() { form.setAttribute('data-sucio', '1'); }
    form.addEventListener('input', marcarSucio);
    form.addEventListener('change', marcarSucio);
    form.addEventListener('click', function (e) {
      var b = e.target.closest ? e.target.closest('button') : null;
      if (!b || !form.contains(b) || b.type !== 'button') { return; }
      if (b.classList.contains('at-pt-agregar-act')) {
        var lista = b.closest('.at-pt-bloque') ? b.closest('.at-pt-bloque').querySelector('.at-pt-acts') : null;
        var nueva = clonar('at-pt-tpl-actividad');
        if (lista && nueva) { lista.appendChild(nueva); marcarSucio(); nueva.querySelector('.at-pt-a-nombre').focus(); }
      } else if (b.classList.contains('at-pt-agregar-bloque')) {
        var bloque = clonar('at-pt-tpl-bloque');
        if (bloque) { b.parentNode.insertBefore(bloque, b); marcarSucio(); bloque.querySelector('.at-pt-b-nombre').focus(); }
      } else if (b.classList.contains('at-pt-quitar-act')) {
        var act = b.closest('.at-pt-act');
        if (act) { act.parentNode.removeChild(act); marcarSucio(); }
      } else if (b.classList.contains('at-pt-quitar-bloque')) {
        var bl = b.closest('.at-pt-bloque');
        if (bl && window.confirm('¿Quitar este bloque con todas sus actividades?')) { bl.parentNode.removeChild(bl); marcarSucio(); }
      }
    });
    form.addEventListener('submit', function () {
      var campo = form.querySelector('.at-pt-plan-json');
      if (campo) { campo.value = JSON.stringify(serializar(form)); }
      form.removeAttribute('data-sucio');
    });
  }

  /* Captura en document: corre antes que el onsubmit (confirm) del formulario y puede detenerlo. */
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (!f.classList || !f.classList.contains('at-pt-requiere-guardado')) { return; }
    if (document.querySelector('.at-pt-form-plan[data-sucio="1"]')) {
      e.preventDefault();
      e.stopPropagation();
      window.alert('Tienes cambios sin guardar en la tabla. Primero usa «Guardar y recalcular».');
    }
  }, true);

  /* Burbuja en document: si el envío siguió (nadie lo canceló), se desactivan los botones de ese formulario. */
  document.addEventListener('submit', function (e) {
    var f = e.target;
    if (e.defaultPrevented || !f.closest || !f.closest('.at-pt')) { return; }
    setTimeout(function () {
      Array.prototype.forEach.call(f.querySelectorAll('button[type="submit"]'), function (b) { b.disabled = true; });
    }, 0);
  });

  var params = new URLSearchParams(window.location.search);
  if (window.location.hash === '#tab-plan' || params.has('pt') || params.has('pt_msg')) {
    abrirPestanaPlan();
  }
  if (params.has('pt_msg') && window.history && window.history.replaceState) {
    params.delete('pt_msg');
    window.history.replaceState(null, '', window.location.pathname + '?' + params.toString() + window.location.hash);
  }
  Array.prototype.forEach.call(document.querySelectorAll('.at-pt-form-plan'), iniciarFormulario);

  window.atPlanTrabajo = { serializar: serializar, abrirPestanaPlan: abrirPestanaPlan };
}());
