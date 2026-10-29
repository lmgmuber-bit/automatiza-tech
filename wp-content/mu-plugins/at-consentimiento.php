<?php
/**
 * Plugin Name: AT · Aviso de cookies y evento Lead
 * Description: Aviso de cookies propio (Aceptar / Rechazar). Sin «Aceptar», PixelYourSite no activa ningún píxel. Expone window.atLead() para avisar a Meta cuando llega un contacto, solo con permiso.
 * Version: 1.0.0
 *
 * Cómo se conecta con PixelYourSite (leído en su código, v11.4):
 * - `pys_disable_by_gdpr` en true apaga todos los píxeles; aquí vale true mientras no exista la cookie `at_consentimiento=si`.
 * - Con la opción «gdpr_ajax_enabled» activa, el JS de PixelYourSite pide ese valor por AJAX
 *   (`pys_get_gdpr_filters_values`) antes de cargar los píxeles, así la caché de LiteSpeed no congela la decisión.
 *   El filtro `pys_gdpr_ajax_enabled` solo actúa al guardar sus ajustes: la opción se activa una vez al desplegar.
 * - Los eventos que PixelYourSite manda desde el servidor pasan por el mismo filtro, con la cookie de la petición.
 */
if (!defined('ABSPATH')) { exit; }

/** Hay permiso solo si la persona pulsó «Aceptar». */
function at_consent_otorgado(): bool {
    $v = $_COOKIE['at_consentimiento'] ?? '';
    return is_string($v) && $v === 'si';
}

add_filter('pys_disable_by_gdpr', function ($desactivado) {
    return (bool) $desactivado || !at_consent_otorgado();
});
add_filter('pys_gdpr_ajax_enabled', '__return_true');

add_action('wp_footer', 'at_consent_dibujar', 99);

function at_consent_dibujar() {
    if (is_admin() || wp_doing_ajax() || is_feed()) {
        return;
    }
    $cookies = esc_url(home_url('/cookies/'));
    $privacidad = esc_url(home_url('/privacidad/'));
    ?>
<div id="at-consent" class="at-consent" role="dialog" aria-modal="false" aria-labelledby="at-consent-titulo" aria-describedby="at-consent-texto" hidden>
  <div class="at-consent__caja">
    <p id="at-consent-titulo" class="at-consent__titulo">Cookies en AutomatizaTech</p>
    <p id="at-consent-texto" class="at-consent__texto">Usamos cookies necesarias para que el sitio funcione y, solo si aceptas, cookies de medición y publicidad (Meta). Puedes cambiar tu elección cuando quieras. <a href="<?php echo $cookies; ?>">Política de Cookies</a> · <a href="<?php echo $privacidad; ?>">Privacidad</a></p>
    <div class="at-consent__botones">
      <button type="button" class="at-consent__btn" data-at-consent="no">Rechazar</button>
      <button type="button" class="at-consent__btn at-consent__btn--si" data-at-consent="si">Aceptar</button>
    </div>
  </div>
</div>
<style>
.at-consent{position:fixed;left:16px;right:16px;bottom:16px;z-index:2147483000;display:flex;justify-content:center;font:15px/1.5 system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
.at-consent[hidden]{display:none}
.at-consent__caja{max-width:720px;width:100%;background:#0f1d33;color:#f1f5f9;border:1px solid rgba(255,255,255,.12);border-radius:14px;padding:16px 18px;box-shadow:0 12px 32px rgba(0,0,0,.35)}
.at-consent__titulo{margin:0 0 4px;font-weight:700;font-size:15px;color:#fff}
.at-consent__texto{margin:0 0 12px;font-size:14px;color:#cbd5e1}
.at-consent__texto a{color:#5eead4;text-decoration:underline}
.at-consent__botones{display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap}
.at-consent__btn{min-height:44px;min-width:120px;padding:10px 18px;border-radius:10px;border:1px solid #5eead4;background:transparent;color:#fff;font-family:inherit;font-size:14px;font-weight:600;line-height:1;cursor:pointer}
.at-consent__btn--si{background:#14b8a6;border-color:#14b8a6;color:#04121f}
.at-consent__btn:focus-visible{outline:3px solid #fde68a;outline-offset:2px}
@media (max-width:480px){.at-consent{left:12px;right:12px;bottom:12px}.at-consent__btn{flex:1}}
/* En pantallas anchas va abajo a la izquierda y más angosto: no tapa el botón del chat («Háblale a Tech») de la derecha. */
@media (min-width:900px){.at-consent{left:24px;right:auto;bottom:24px;width:520px;justify-content:flex-start}.at-consent__btn{min-width:110px}}
</style>
<?php
    // Huella del Lead (la misma que manda el servidor por CAPI): si el archivo no está, atLead avisa sin eventID.
    $at_lead_id = __DIR__ . '/../themes/automatiza-tech/assets/js/at-lead-id.js';
    if (is_readable($at_lead_id)) {
        echo '<script>' . file_get_contents($at_lead_id) . "</script>\n";
    }
    ?>
<script>
(function () {
  var NOMBRE = 'at_consentimiento', SEIS_MESES = 15552000;
  function leer() {
    var m = document.cookie.match(/(?:^|;\s*)at_consentimiento=(si|no)(?:;|$)/);
    return m ? m[1] : null;
  }
  function guardar(v) {
    document.cookie = NOMBRE + '=' + v + '; max-age=' + SEIS_MESES + '; path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
    try { localStorage.setItem('at_cookie_consent', v === 'si' ? 'accepted' : 'rejected'); } catch (e) {}
  }
  // Quien ya decidió en el aviso anterior (localStorage) no vuelve a ver la pregunta.
  if (!leer()) {
    try {
      var previo = localStorage.getItem('at_cookie_consent');
      if (previo === 'accepted') { guardar('si'); } else if (previo === 'rejected') { guardar('no'); }
    } catch (e) {}
  }
  var caja = document.getElementById('at-consent');
  function abrir() { if (caja) { caja.hidden = false; } }
  function decidir(v) {
    var antes = leer();
    guardar(v);
    if (caja) { caja.hidden = true; }
    // El píxel se decide al cargar la página: cambiar a «sí», o retirar un «sí», recarga una vez.
    if (v === 'si' || antes === 'si') { location.reload(); }
  }
  if (caja) {
    caja.addEventListener('click', function (e) {
      var b = e.target.closest ? e.target.closest('[data-at-consent]') : null;
      if (b) { decidir(b.getAttribute('data-at-consent')); }
    });
    if (!leer()) { abrir(); }
  }
  // Cualquier enlace con data-at-cookies reabre el aviso (por ejemplo, en el pie o en la Política de Cookies).
  document.addEventListener('click', function (e) {
    var a = e.target.closest ? e.target.closest('[data-at-cookies]') : null;
    if (a) { e.preventDefault(); abrir(); }
  });
  window.atConsentimiento = { abrir: abrir, estado: leer };
  // Avisa a Meta que llegó un contacto. Sin permiso o sin Pixel cargado, no hace nada.
  // Con datos = {email, fecha, hora} manda el mismo eventID que el servidor (CAPI) y avisa una sola vez por correo
  // en 30 días; la marca se guarda con la huella del correo, nunca con el correo.
  var TREINTA_DIAS = 2592000000;
  window.atLead = function (origen, datos) {
    if (leer() !== 'si' || typeof window.fbq !== 'function') { return false; }
    var params = { content_name: String(origen || 'sitio') };
    function enviar(id) {
      try { if (id) { window.fbq('track', 'Lead', params, { eventID: id }); } else { window.fbq('track', 'Lead', params); } } catch (e) {}
    }
    var L = window.AtLeadId;
    if (!datos || !L) { enviar(''); return true; }
    Promise.all([L.eventId(datos.email, datos.fecha, datos.hora), L.huellaCorreo(datos.email)]).then(function (r) {
      var clave = r[1] ? 'at_lead_' + r[1].slice(0, 32) : '', hasta = 0;
      try { hasta = clave ? Number(localStorage.getItem(clave)) || 0 : 0; } catch (e) {}
      if (hasta > Date.now()) { return; }
      enviar(r[0]);
      try { if (clave) { localStorage.setItem(clave, String(Date.now() + TREINTA_DIAS)); } } catch (e) {}
    }, function () { enviar(''); });
    return true;
  };
})();
</script>
    <?php
}
