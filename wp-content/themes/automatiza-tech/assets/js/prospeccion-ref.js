/* Rastro · Prospección: avisa al servidor que alguien acaba de agendar, para cruzarlo con su enlace personal.
   Envuelve window.atLead sin cambiar lo que hace. El servidor decide con la cookie (HttpOnly) y el lead guardado;
   si no hay cookie o no hay lead reciente, no pasa nada. */
(function () {
  var cfg = window.AtProRef;
  if (!cfg || !cfg.ajax) { return; }
  function enviar(email) {
    try {
      var cuerpo = new URLSearchParams();
      cuerpo.append('action', 'at_pro_formulario');
      cuerpo.append('email', String(email));
      if (navigator.sendBeacon) { navigator.sendBeacon(cfg.ajax, cuerpo); }
      else { fetch(cfg.ajax, { method: 'POST', body: cuerpo, credentials: 'same-origin', keepalive: true }); }
    } catch (e) {}
  }
  function envolver() {
    var original = window.atLead;
    window.atLead = function (origen, datos) {
      if (datos && datos.email) { enviar(datos.email); }
      return typeof original === 'function' ? original.apply(this, arguments) : false;
    };
  }
  // atLead lo define el aviso de consentimiento en el pie: se envuelve cuando ya cargó todo.
  if (document.readyState === 'complete') { envolver(); } else { window.addEventListener('load', envolver); }
})();
