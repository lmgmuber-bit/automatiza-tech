# Aviso de cookies y evento Lead de Meta en automatizatech.cl (22-oct-2026)

Punto 9 de la lista de Luis: antes de pagar pauta en Meta, el Pixel tiene que pedir permiso y avisar cuando llega un cliente potencial.

## Cómo funciona

- **Un solo aviso de cookies** para todo el sitio, incluida la portada y las landings. Lo dibuja el mu-plugin `wp-content/mu-plugins/at-consentimiento.php` en `wp_footer`:
  - trae «Aceptar» y «Rechazar» con el mismo peso, y enlaces a `/cookies/` y `/privacidad/`;
  - la decisión se guarda 6 meses en la cookie `at_consentimiento` (`si` o `no`);
  - se respeta la decisión que ya hubieran tomado en los avisos anteriores (localStorage `at_cookie_consent`).
- **Avisos que se retiraron:** el del pie (`footer.php`) y el de la portada (`at-home.js`). Ninguno de los dos conectaba con el Pixel.
- **Sin «Aceptar», PixelYourSite no activa ningún píxel.** El mu-plugin devuelve `true` en el filtro `pys_disable_by_gdpr` mientras no exista `at_consentimiento=si`.
- **La caché no congela la decisión:** con la opción `gdpr_ajax_enabled` de PixelYourSite activa, su JS pide el valor del filtro por AJAX (`pys_get_gdpr_filters_values`) antes de cargar los píxeles. Esa opción vive en la configuración de PixelYourSite y se activa una vez al desplegar. El filtro `pys_gdpr_ajax_enabled` del mu-plugin la mantiene activa si alguien vuelve a guardar los ajustes.
- **Al aceptar, o al retirar un «sí»,** la página se recarga una vez.
- **«Configurar cookies»:** cualquier enlace con el atributo `data-at-cookies` reabre el aviso. Hay uno en el pie.
- **Evento Lead:** `window.atLead(origen)` envía `fbq('track', 'Lead', {content_name: origen})`, solo si hay permiso y el Pixel está cargado. Se llama en tres lugares:
  - la agenda de la portada (`at-home.js`, después de `lead_submit`);
  - la agenda de demo del pie (después de confirmar la cita);
  - el formulario de agenda del pie (`res.success`).
- **El chat (Aria) no envía Lead:** quien guarda el contacto es n8n, y el navegador no se entera. Queda para la API de conversiones del servidor.

## Pruebas

- `tests/consentimiento/consentimiento-test.php`: la regla del Pixel, lo que se dibuja y que no aparezca en wp-admin.
- Página aislada con el mu-plugin real (22-oct, celular 375 px):
  - el aviso aparece y el Pixel queda desactivado;
  - «Aceptar» recarga, el Pixel se activa y `atLead` registra `Lead`;
  - «Configurar cookies» reabre el aviso;
  - «Rechazar» recarga, el Pixel queda desactivado y `atLead` no envía nada;
  - una decisión vieja en localStorage se respeta sin volver a preguntar.

## Despliegue (con el OK de Luis)

1. Cotejar con PROD el md5 de `footer.php` y `assets/home-premium/at-home.js` contra `main` y respaldarlos.
2. Subir en este orden: `wp-content/mu-plugins/at-consentimiento.php`, `footer.php` y `at-home.js`. Aplicar `php -l` y verificar el md5.
3. Activar `gdpr_ajax_enabled` en PixelYourSite. Se puede hacer desde Ajustes → GDPR → «Enable AJAX filter values update», o por WP-CLI con la API de PixelYourSite. Verificar que `pysOptions.gdpr.ajax_enabled` sea `true` en la portada.
4. Purgar LiteSpeed.
5. Verificar:
   - `admin-ajax.php?action=pys_get_gdpr_filters_values` responde `all_disabled_by_api: true` sin la cookie y `false` con `at_consentimiento=si`;
   - la portada muestra un solo aviso;
   - en «Probar eventos» del Administrador de eventos de Meta, `Lead` aparece al agendar una cita de prueba (con permiso).
6. **Rollback:** borrar el mu-plugin y restaurar los dos archivos del respaldo. La opción AJAX puede quedar activa sin efecto.

## Lead por el servidor (CAPI)

Desde el 28-oct-2026 el Lead también puede ir desde el servidor, con el mismo `eventID` que el píxel: ver [2026-10-28-META-CAPI-LEAD.md](2026-10-28-META-CAPI-LEAD.md).
