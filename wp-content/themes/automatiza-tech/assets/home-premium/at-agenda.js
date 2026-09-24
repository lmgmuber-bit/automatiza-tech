/* AutomatizaTech — Home Premium: selector de horarios reales
 * Consume el pipeline existente GET automatiza-tech/v1/appointments-config
 * (horarios por día + fechas bloqueadas + slots ocupados) y rellena la HORA.
 * Escribe el campo oculto scheduled_time (HH:MM:SS, lo usará POST /leads) y
 * franja (compat con el handler actual). NO modifica at-home.js.
 */
(function () {
  'use strict';
  var cfg = window.AT_AGENDA || {};
  var CONFIG_URL = cfg.configUrl || '';
  var DAYS = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
  var data = null, loading = false;

  // Ventana de agendamiento: desde hoy hasta hoy + MAX_DAYS (inclusive).
  var MAX_DAYS = 90;

  function el(id) { return document.getElementById(id); }
  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function ymd(d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }
  function todayStr() { return ymd(new Date()); }
  function maxStr() { var d = new Date(); d.setDate(d.getDate() + MAX_DAYS); return ymd(d); }
  // Comparación por string: 'YYYY-MM-DD' ordena lexicográficamente igual que por fecha.
  function outOfRange(dateStr) {
    if (!/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) return 'invalid';
    if (dateStr < todayStr()) return 'past';
    if (dateStr > maxStr()) return 'far';
    return '';
  }

  function franjaFor(hour) {
    if (hour < 12) return 'Mañana (09-12h)';
    if (hour < 15) return 'Mediodía (12-15h)';
    return 'Tarde (15-19h)';
  }

  function clearChoice() {
    var st = el('at-scheduled-time'), fr = el('at-franja');
    if (st) st.value = ''; if (fr) fr.value = '';
  }

  function setMsg(text) {
    var box = el('at-slots'); if (!box) return;
    box.innerHTML = '<span style="font-size:12.5px;color:var(--text-faint)">' + text + '</span>';
    clearChoice();
  }

  function pick(btn) {
    var box = el('at-slots'); if (!box) return;
    var all = box.querySelectorAll('.at-slot-btn');
    for (var i = 0; i < all.length; i++) all[i].classList.remove('is-active');
    btn.classList.add('is-active');
    var hhmm = btn.getAttribute('data-time');
    var st = el('at-scheduled-time'), fr = el('at-franja');
    if (st) st.value = hhmm + ':00';
    if (fr) fr.value = franjaFor(parseInt(hhmm, 10));
  }

  function renderSlots(dateStr) {
    var box = el('at-slots'); if (!box) return;
    // Red de seguridad: min/max del input pueden saltarse escribiendo la fecha a mano.
    var bad = outOfRange(dateStr);
    if (bad === 'past') { setMsg('Solo puedes agendar desde hoy en adelante.'); return; }
    if (bad === 'far') { setMsg('Solo agendamos hasta ' + MAX_DAYS + ' días hacia adelante.'); return; }
    if (bad) { setMsg('Elige una fecha válida.'); return; }
    if (!data) { setMsg('No se pudo cargar la disponibilidad.'); return; }
    if (data.holidays && data.holidays.indexOf(dateStr) >= 0) { setMsg('Ese día no atendemos. Elige otra fecha.'); return; }
    var d = new Date(dateStr + 'T00:00:00');
    var ws = (data.weekSchedule || {})[DAYS[d.getDay()]];
    if (!ws || !ws.enabled) { setMsg('Ese día no atendemos. Elige otra fecha.'); return; }
    var start = parseInt((ws.start || '09:00').split(':')[0], 10);
    var end = parseInt((ws.end || '18:00').split(':')[0], 10);
    var busy = ((data.busyDates || {})[dateStr] || {}).busySlots || [];
    var now = new Date();
    var isToday = dateStr === todayStr();
    box.innerHTML = '';
    clearChoice();
    var count = 0;
    for (var h = start; h < end; h++) {
      var hhmm = pad(h) + ':00';
      if (busy.indexOf(hhmm) >= 0) continue;
      if (isToday && h <= now.getHours()) continue;
      count++;
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'at-slot-btn';
      b.setAttribute('data-time', hhmm);
      b.textContent = hhmm;
      b.addEventListener('click', function () { pick(this); });
      box.appendChild(b);
    }
    if (count === 0) setMsg('Sin horarios libres ese día. Prueba otra fecha.');
  }

  function load(cb) {
    if (data) { cb && cb(); return; }
    if (!CONFIG_URL) { setMsg('Disponibilidad no configurada.'); return; }
    if (loading) return;
    loading = true;
    fetch(CONFIG_URL, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (j) { data = (j && j.data) || null; loading = false; cb && cb(); })
      .catch(function () { loading = false; setMsg('No se pudo cargar la disponibilidad.'); });
  }

  // Recalcula min/max en cada interacción: la pestaña puede quedar abierta y
  // cruzar medianoche, dejando "hoy" desactualizado.
  function applyBounds(fecha) {
    fecha.min = todayStr();
    fecha.max = maxStr();
  }

  // Descarta un valor que quedó en el pasado por el cambio de día, no por
  // elección del usuario (solo al enfocar/abrir, nunca sobre lo recién elegido).
  function dropStale(fecha) {
    if (fecha.value && outOfRange(fecha.value) === 'past') {
      fecha.value = '';
      setMsg('Elige una fecha para ver horarios disponibles.');
    }
  }

  function init() {
    var fecha = el('at-fecha');
    if (!fecha) return;
    applyBounds(fecha);
    // Abrir el calendario nativo al hacer clic en cualquier parte del campo,
    // no solo en el icono, que es un blanco muy pequeño.
    fecha.addEventListener('click', function () {
      applyBounds(fecha);
      dropStale(fecha);
      if (typeof fecha.showPicker === 'function') {
        try { fecha.showPicker(); } catch (err) { /* gesto no confiable o no soportado */ }
      }
    });
    fecha.addEventListener('focus', function () { applyBounds(fecha); dropStale(fecha); });
    fecha.addEventListener('change', function () {
      applyBounds(fecha);
      var v = fecha.value;
      if (!v) { setMsg('Elige una fecha para ver horarios disponibles.'); return; }
      // renderSlots emite el mensaje concreto (pasada / fuera de ventana / inválida).
      if (outOfRange(v)) { renderSlots(v); return; }
      setMsg('Cargando horarios…');
      load(function () { renderSlots(v); });
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
