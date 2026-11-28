# Aviso de cookies, Lead de Meta y clic de WhatsApp en automatizatech.cl

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

## Despliegue inicial del aviso — histórico del 22-oct-2026

Estos pasos corresponden a la instalación original. **No aplican a la entrega de GA4 del 21-nov-2026**, cuya lista de subida está al final de esta guía.

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

## GA4: `clic_whatsapp` — entrega local del 21-nov-2026

Pedido de Faro, autorizado por Luis el 21-nov. Responsable: **Forja-Codex**; revisor asignado: **Eje** (revisión pendiente). Rama `claude/ga4-clic-whatsapp`, base `660e11f`. Estado: implementación local; sin despliegue, SSH, push ni merge.

Ruta: C1 · riesgo moderado · incertidumbre baja · reversible → Forja-Codex; perfil previsto `gpt-6.1-sol/medium`; modelo de la sesión fijado por el entorno, sin cambio de perfil disponible. Alcance limitado al mu-plugin, su prueba y esta guía.

### Contrato y decisiones

- Un único listener de `click` en **captura** sobre `document`: funciona con elementos añadidos después de cargar la página y con clics en sus iconos. Se registra una sola vez aunque el script se ejecute dos veces. Un clic en `button+a` anidados produce un evento; dos clics distintos producen dos.
- Busca el `a` o `button` más cercano que tenga destino WhatsApp en `href`, `data-href` o una URL literal en `onclick` (`window.open`, `location`, `location.href`, `location.assign/replace`). No ejecuta ni interpreta funciones arbitrarias del HTML. Un botón cuyo destino se calcule solo dentro de otra función JS necesita validación específica; no se puede descubrir ese destino mediante atributos.
- Destinos: host exacto `wa.me` o `api.whatsapp.com` con HTTP(S), incluidos enlaces relativos al protocolo, o esquema `whatsapp://`. No acepta dominios parecidos, URLs de redirección que solo mencionen WhatsApp ni `javascript:` en el href.
- Relee `at_consentimiento=si` **en cada clic**, y exige que `window.gtag` ya sea una función. Sin decisión, con rechazo, cookie inválida o medidor ausente, no encola nada. No crea otra etiqueta, propiedad, script remoto ni cola de envíos anteriores al permiso.
- Llamada: `gtag('event', 'clic_whatsapp', {ubicacion, servicio, transport_type: 'beacon'})`. Agrega `debug_mode: true` únicamente si `URLSearchParams(location.search).get('ga_debug') === '1'`.
- No usa `preventDefault`, callbacks, promesas, temporizadores ni esperas en el manejador de WhatsApp. El manejador anterior de «Configurar cookies» conserva su comportamiento.
- Los parámetros del evento **no incluyen número, href, texto del enlace, título, datos de contacto ni ruta completa**. `page_location` queda a cargo de GA4. `ubicacion` y `servicio` tienen un vocabulario cerrado para evitar que un atributo, ID, clase o slug arbitrario filtre datos personales. Es preferible el valor genérico a copiar un identificador desconocido.

| Campo | Regla |
|---|---|
| `ubicacion` | Prioridad: `data-ubicacion` conocido en el elemento/ancestro; clase `float`, `floating`, `flotante`, `fixed` o `position:fixed` en el elemento/ancestro; `footer` → `pie`; clase que contenga `hero`, `#hero`, `#top` o primera sección de `main` (en su defecto la primera `section`) → `hero`; sección conocida → `seccion:<nombre>`; resto → `seccion`. |
| Secciones conocidas | `contacto`, `servicios`, `soluciones`, `planes`, `faq`, `dolor`, `rubros`, `metodo`, `portal`, `casos`, `porque`, `quienes`, `otros-servicios`, `guias`. Se coteja el ID y después cada clase exacta de la sección o región más cercana. |
| `data-ubicacion` conocido | `hero`, `pie`, `flotante`, `seccion` o `seccion:<nombre conocido>`. Otros valores se ignoran y se deriva del contexto. |
| `servicio` | `/` → `portada`; `/guias/` y sus artículos → `guias`; las seis rutas de servicios de la tabla → su primer slug; otras rutas → `sitio`. No se incorporan automáticamente slugs nuevos. |
| `transport_type` | `beacon`. La entrega efectiva depende del navegador y del medidor; no se bloquea la apertura de WhatsApp. |
| `debug_mode` | Solo `true` con `?ga_debug=1` (o `&ga_debug=1`); ausente en el uso normal. |

### Páginas y etiquetas esperadas

**Verificado en fuentes locales de `660e11f`, no en PROD.** Los seis slugs actuales salen de `inc/at-servicios.php`; no son `/automatizacion/` ni `/asistentes-ia/`. No se cambió ese archivo ni ninguna plantilla.

| Página | WhatsApp localizado en la fuente local | `ubicacion` esperada | `servicio` |
|---|---|---|---|
| `/` | `home-premium/body.html`: `section#top`, `section#contacto`, `footer` | `hero`, `seccion:contacto`, `pie` | `portada` |
| `/asistentes-inteligentes-ia-whatsapp/` | `landing-asistentes-ia/body.html`: `section#top`, `footer` | `hero`, `pie` | `asistentes-inteligentes-ia-whatsapp` |
| `/automatizacion-negocios-whatsapp-crm/` | `landing-automatizacion/body.html`: `section#top`, `footer` | `hero`, `pie` | `automatizacion-negocios-whatsapp-crm` |
| `/sitios-web-premium-chile/` | `landing-sitios-premium/body.html`: `section#top`, `footer` | `hero`, `pie` | `sitios-web-premium-chile` |
| `/aplicaciones-web-a-medida/` | `landing-apps-web/body.html`: `section#top`, `footer` | `hero`, `pie` | `aplicaciones-web-a-medida` |
| `/sistemas-a-medida-chile/` | `landing-sistemas-medida/body.html`: `section#top`, `footer` | `hero`, `pie` | `sistemas-a-medida-chile` |
| `/portal-omnicliente-precios/` | `landing-portal-omnicliente/body.html`: `section#contacto`, `footer` | `seccion:contacto`, `pie` | `portal-omnicliente-precios` |
| `/guias/` y artículos | Shell no presente en esta base local; pendiente inspección pública | Según el DOM: `hero`, `pie`, `flotante` o `seccion[:nombre conocido]`; pendiente confirmar qué enlaces existen | `guias` |
| Flotante, si existe en cualquiera de estas páginas | No acreditado en PROD | `flotante`, si cumple la regla de clase/estilo y no hay `data-ubicacion` conocido | El servicio de la página |

**Inspección pública: NOT_RUN.** Se intentó `curl.exe -fsSL --max-time 25 https://automatizatech.cl/`: falló al conectar con el proxy local `127.0.0.1`. La consulta web tampoco obtuvo `/`, `/automatizacion/` ni `/guias/`. Tanto el navegador principal como el alternativo fallaron al iniciar por un error del sandbox de Windows (`CryptUnprotectData`). No se presenta la tabla local como evidencia del HTML público, que está adelantado respecto de main.

**Dependencia por cotejar:** esta versión local del mu-plugin contiene el consentimiento y `atLead`, pero no inicializa `gtag`. La nueva función reutiliza el `window.gtag` del sitio. Antes de subir, Eje debe cotejar el mu-plugin vigente de PROD con esta base y verificar que hay un `gtag` dirigido al flujo web de la propiedad indicada por Faro. No reemplazar a ciegas una versión de PROD que tenga cambios adicionales. No se inventó un ID de medición a partir del ID de propiedad.

### Pruebas reproducibles

En PowerShell, desde el root del worktree (PHP 8.3.36 y Node 24.18.1):

```powershell
$phpGa4 = 'C:/wamp64/bin/php/php8.3.36/php.exe'
& $phpGa4 -n -l wp-content/mu-plugins/at-consentimiento.php
& $phpGa4 -n -l tests/consentimiento/consentimiento-test.php
& $phpGa4 -n tests/consentimiento/consentimiento-test.php
& $phpGa4 -n tests/consentimiento/consentimiento-test.php --whatsapp-js | node
node tests/meta-capi/atlead-test.mjs
```

La modalidad `--whatsapp-js` genera en stdout una prueba con el **script dibujado por PHP** y lo ejecuta con `node:vm`, `gtag` simulado y DOM acotado, sin dependencias nuevas ni temporales. Verifica consentimiento, retirada, destinos válidos/falsos, iconos, captura sin burbujeo, botones, anidación, doble carga, dos clics separados, etiquetas, privacidad, seis servicios, guías, depuración y errores del medidor. No acredita comportamiento en un navegador real ni recepción de GA4.

TDD observado: antes de implementar, las cinco nuevas comprobaciones PHP fallaron y el primer envío JS consentido falló con `0 !== 1`. Resultados finales reales del 21-nov: **20 comprobaciones PHP PASS, 53 pruebas JS PASS, 15 comprobaciones de regresión de `atLead` PASS**, lint de los dos PHP PASS y `git diff --check` PASS. Todos los comandos terminaron con código 0. El DOM de las pruebas de WhatsApp es simulado; no sustituye la aceptación pública.

Graphify: se ejecutó la consulta inicial de navegación. `graphify update .` **NOT_RUN** para respetar la restricción expresa de modificar únicamente estos tres archivos y conservar intacto `graphify-out/`. No se generaron temporales en el repo ni fuera de él; las pruebas se pasan en memoria por stdout/stdin.

Estado Git de entrega: **sin staging ni commit**. `git add` de los tres archivos falló al crear `.git/worktrees/guias/index.lock` con `Permission denied`; se conservaron los cambios y no se retiraron locks ni se alteraron permisos. Aseo: revisión manual de los tres archivos, sin temporales propios; el checkpoint `task-close` con la identidad nativa de la sesión devolvió `preserved` por ruta ausente/ocupada/sin permiso. El checkpoint automatizado queda pendiente; no se afirma limpieza global del equipo.

### Aceptación en GA4 — pendiente de despliegue autorizado

Responsables del siguiente paso: Eje revisa el cambio y el HTML real; Faro verifica la medición; Luis decide el pase. **Tiempo real / DebugView: NOT_RUN en este encargo**, sin envío de clics a la propiedad real.

1. En una sesión nueva, abrir cada página sin consentimiento, pulsar un WhatsApp y comprobar en Red que no se envía `en=clic_whatsapp`. Repetir después de «Rechazar».
2. Abrir la página con `?ga_debug=1`, aceptar cookies y esperar la recarga habitual. Pulsar WhatsApp en hero, CTA final, pie y flotante donde existan; el enlace debe abrirse normalmente. No enviar mensajes al contacto.
3. En Red, comprobar un solo evento `clic_whatsapp` por clic, `ubicacion` y `servicio` esperados, y ausencia del destino/número/texto en sus parámetros propios. Repetir al pulsar un icono dentro del enlace. Rechazar y repetir: cero eventos nuevos de WhatsApp.
4. En la propiedad GA4 indicada por Faro, revisar DebugView y Tiempo real; seleccionar `clic_whatsapp` y sus parámetros. `ga_debug=1` añade la marca de depuración, **no garantiza eludir un filtro de tráfico interno por IP**: si no aparece, Faro debe revisar los filtros efectivos de tráfico interno/desarrollador. No se cambiaron filtros de la cuenta.
5. Para analizar `ubicacion` y `servicio` en informes normales, Faro registra ambos como dimensiones personalizadas de ámbito evento, si aún no existen. [Referencia oficial de parámetros GA4](https://developers.google.com/analytics/devguides/collection/ga4/event-parameters). No se hizo ningún cambio administrativo en GA4.

### Lista exacta para PROD y rollback de esta entrega

| Clasificación | Ruta local (relativa a este worktree) | Destino relativo en PROD | Orden |
|---|---|---|---|
| **OBLIGATORIO**, tras revisión y autorización de pase | `wp-content/mu-plugins/at-consentimiento.php` | `wp-content/mu-plugins/at-consentimiento.php` | Único archivo; respaldar primero el mu-plugin vigente, cotejar diferencias y purgar caché después. |
| **NO SUBIR** (prueba local) | `tests/consentimiento/consentimiento-test.php` | — | — |
| **NO SUBIR** (documentación de ingeniería) | `Docs/MARKETING/2026-10-22-CONSENTIMIENTO-Y-EVENTO-LEAD.md` | — | — |

Root local: `C:/wamp64/www/automatiza-tech/.worktrees/guias/`. No hay archivos opcionales para FTP. No subir `at-home.js`, `body.html`, shell de `/guias/`, plantillas PHP del tema, pruebas, grafo, temporales, respaldos ni secretos. El mu-plugin requiere que las páginas ejecuten `wp_footer`; Eje debe verificarlo también en el shell público de guías. Rollback: restaurar **solo el mu-plugin previo** y purgar caché; no borrar el plugin de consentimiento ni seguir el rollback histórico del 22-oct.

Superficie afectada: sitio público de AT; paneles privados fuera del alcance. SEO/IA: cambio de instrumentación, sin cambios de contenido, navegación, canonical, robots ni indexabilidad; auditoría general de indexación/citas **NO APLICA**. Esta guía contiene el contrato, decisiones, pruebas, operación y rollback; manuales adicionales/PDF/diagramas de datos **NO APLICA** por tratarse de un evento sin tablas ni UI nueva.
