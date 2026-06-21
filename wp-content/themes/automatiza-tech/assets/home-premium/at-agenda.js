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

  function el(id) { return document.getElementById(id); }
  function pad(n) { return (n < 10 ? '0' : '') + n; }
  function todayStr() { var d = new Date(); return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); }

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

  function init() {
    var fecha = el('at-fecha');
    if (!fecha) return;
    fecha.min = todayStr();
    fecha.addEventListener('change', function () {
      var v = fecha.value;
      if (!v) { setMsg('Elige una fecha para ver horarios disponibles.'); return; }
      setMsg('Cargando horarios…');
      load(function () { renderSlots(v); });
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
