# Medición con Google Analytics 4 y Google Ads en automatizatech.cl (13-nov-2026)

Pedido de Luis (13-nov): dejar funcionando la medición y la conversión para Google Ads antes de hacer campañas, que quedan para después. Responsables: Eje (plataforma) con Faro (SEO). Estrategia en la bóveda: `10-Projects/2026-11-05-AT-Estrategia-SEO-Google.md`.

## Cómo funciona

- **Módulo** `wp-content/themes/automatiza-tech/inc/medicion/` (`puras.php` + `cargar.php`), cargado desde `functions.php`. Funciona en todas las páginas, incluidas la portada y las landings, porque usa `wp_head` y `wp_footer` y no toca las plantillas.
- **Sin permiso no se carga nada.** La etiqueta de Google (`gtag.js`) solo se carga si existe la cookie `at_consentimiento=si`, la del aviso de `wp-content/mu-plugins/at-consentimiento.php`. La cookie se lee en el navegador porque LiteSpeed guarda la misma página para todos. Al aceptar, el aviso recarga la página y GA4 parte en esa carga.
- **IDs en opciones de WordPress**, no en el código. No son secretos: salen en el código de la página.
  - `at_ga4_measurement_id`: el ID de medición del flujo web de GA4 (`G-…`).
  - `at_google_ads_id`: la etiqueta de Google Ads (`AW-…`), opcional; sirve para remarketing y conversiones mejoradas.
  - Sin opciones válidas no se imprime nada, así que el código puede estar en PROD antes de tener los IDs.
- **Conversión:** el módulo envuelve `window.atLead` (lo llaman la agenda del inicio y los formularios del pie) y manda el evento `generate_lead` a GA4. El aviso a Meta sigue igual, y el envoltorio de Prospección (`assets/js/prospeccion-ref.js`) envuelve a este; los dos llaman al original.
- **Se quitó de `header.php`** la etiqueta fija `AW-17691911857` y el evento «Compra», que salía en **cada carga** de las páginas viejas (entradas, búsquedas, `validar-factura`) y sin permiso de cookies. Inflaba las conversiones de Google Ads con visitas.

## Lo que hace Luis (no se puede hacer sin su cuenta de Google)

1. **Search Console:** Agregar propiedad › **Dominio** › `automatizatech.cl`. Google da un registro TXT, que va en el DNS de Hostinger. Con la propiedad verificada, en Sitemaps enviar `https://automatizatech.cl/wp-sitemap.xml`. En Usuarios y permisos, agregar `contacto@automatizatech.cl` como propietario. La propiedad vieja de `automatizatech.shop` no sirve: ese dominio venció (comprobado el 13-nov: «Your domain is expired»).
2. **GA4:** en su cuenta de Analytics, crear una propiedad (o usar la existente) con un **flujo web** para `https://automatizatech.cl` y pasar el ID de medición (`G-…`). Agregar `contacto@automatizatech.cl` como Administrador de la cuenta.
3. **Google Ads, para dejarlo listo:**
   - vincular la cuenta de Ads con la propiedad de GA4 (en GA4: Administrar › Vinculación de productos › Google Ads);
   - en GA4, marcar `generate_lead` como **evento clave** cuando aparezca (puede tardar hasta 24 h);
   - en Google Ads, Objetivos › Conversiones › Importar › Google Analytics 4 › `generate_lead`;
   - quitar como principal o eliminar la conversión vieja «Compra», que se disparaba en cada carga;
   - antes de cualquier campaña, cambiar la URL final de los anuncios: hoy apuntan a `automatizatech.shop`, que está vencido.

## Antes de encender GA4 en PROD

- **Texto del aviso y política de cookies:** el aviso dice «cookies de medición y publicidad (Meta)» y la página `/cookies/` debe nombrar también Google Analytics y Google Ads. Hay que corregir los dos textos antes de cargar el ID; los dueños de esos archivos son Eje · AT general (aviso) y la plantilla legal. Revisión legal: Toga.

## Despliegue (con el OK de Luis)

1. Cotejar con PROD el md5 sin `\r` de `header.php` e `inc/home-premium-backend.php` contra `main` y respaldarlos.
2. **`functions.php`: no subir el del repo.** PROD tiene una línea más (`inc/prospeccion/cargar.php`, desde el 12-nov). Se baja la copia de PROD, se verifica que solo difiere de `main` en esa línea y se le inserta `require_once get_template_directory() . '/inc/medicion/cargar.php';` después de la de `seo-tecnico`.
3. Subir `inc/medicion/puras.php` y `cargar.php` primero, después `header.php`, `inc/home-premium-backend.php` y al final `functions.php`; `php -l` en cada uno; purgar LiteSpeed.
4. Con el ID de Luis y los textos de cookies corregidos: `wp option update at_ga4_measurement_id G-…` (y `at_google_ads_id` si se usa). Purgar LiteSpeed.
5. **Verificar:** sin cookie, el inicio no pide `googletagmanager.com`; con «Aceptar», sí, y GA4 Tiempo real muestra la visita; una cita de prueba registra `generate_lead`.
6. **Vuelta atrás:** `wp option delete at_ga4_measurement_id` apaga la medición al instante; para el código, restaurar el respaldo.

## Pruebas

```bash
php tests/medicion/puras-test.php
```

31 comprobaciones; si hay Node, ejecuta los scripts generados simulando el navegador: sin permiso no carga `gtag.js` ni manda eventos, con permiso configura GA4 y Ads y manda `generate_lead`, y el aviso a Meta sale una sola vez en los dos casos.
