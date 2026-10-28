# Evento Lead por la API de Conversiones de Meta (CAPI) — guía (28-oct-2026)

Diseño: `Docs/superpowers/specs/2026-10-28-meta-capi-lead-design.md` · Plan: `Docs/superpowers/plans/2026-10-28-meta-capi-lead.md` · Rama: `claude/meta-capi`.

## Qué hace

Cada vez que alguien **agenda una reunión** (diagnóstico) por la web, el asistente de Instagram o el bot de WhatsApp, todas terminan en `POST /wp-json/automatiza-tech/v1/leads`. Después de guardar la fila, `/leads` dispara `do_action('automatiza_tech_lead_guardado', $lead_id, $data)` y el módulo `inc/meta-capi/` le manda a Meta un evento **Lead** desde el servidor:

- Solo la **primera agenda de cada correo en 30 días** (marca en un transient con la huella del correo). Reagendar o agendar otra vez no vuelve a contar.
- `action_source`: `chat` para Instagram y WhatsApp; `other` para la web (Meta exige el navegador para `website` y `/leads` no lo recibe).
- Correo y teléfono van **solo cifrados** (SHA-256), con `external_id` y país `cl` cuando el teléfono es chileno. Nunca nombre ni texto de la conversación.
- Las reuniones de seguimiento de clientes (`/followup-meetings`) no cuentan.
- En la web, el píxel (solo si la persona aceptó cookies) manda el mismo `eventID` y Meta junta los dos: no se cuenta doble. Quien rechazó cookies igual queda contado por el servidor (decisión de Luis).
- El envío es no bloqueante: la agenda nunca espera ni falla por Meta. Una falla de red queda en el log como `[at-capi] lead=<id> origen=<o> http=error`, sin datos personales.
- Versión de la Graph API: `v25.0` (`AT_CAPI_GRAPH_VERSION` en `inc/meta-capi/envio.php`; la v24.0 venció el 6-oct-2026). Revisar el changelog de Meta cada año.

**Está apagado hasta que se cumplan las dos cosas:** la opción `at_capi_activo` vale `1` y PixelYourSite tiene píxel y token.

## Cómo se enciende (con Luis)

1. **Token (lo hace Luis, nunca por el chat):** Administrador de eventos de Meta → el píxel → Configuración → API de Conversiones → «Generar token de acceso». Pegarlo en WordPress → PixelYourSite → Meta → campo del token de la API de Conversiones y guardar.
   - Recomendación: dejar **apagado** «Send events directly from your web server» de PixelYourSite. Nuestro módulo solo lee el token; el envío propio del plugin usa un SDK con la Graph API 16.0, que ya venció, y solo manda los eventos del plugin (PageView), no el Lead.
2. **Privacidad (lo aprueba Luis):** agregar a `/privacidad/` la línea propuesta abajo.
3. **Prueba en «Probar eventos»:** Administrador de eventos → Probar eventos → copiar el código de prueba y pegarlo en PixelYourSite (código de prueba de la API). Eje enciende el interruptor por SSH:

   ```bash
   wp option update at_capi_activo 1
   ```

   Una agenda de prueba desde la web (con un correo de prueba de AT, aceptando cookies) debe aparecer **una vez** como Lead (píxel + servidor, deduplicado), y una por WhatsApp como `chat`. Después se borran las citas de prueba y se quita el código de prueba de PixelYourSite.
4. Desde ahí queda encendido. **Apagar:** `wp option update at_capi_activo 0`; apagado del todo: borrar `wp-content/mu-plugins/at-meta-capi.php` (el `do_action` sin oyentes no hace nada).

## Línea propuesta para la política de privacidad (la aprueba Luis)

> Para medir el resultado de nuestros anuncios, cuando agendas una reunión con nosotros compartimos con Meta, de forma cifrada (no legible), tu correo y tu teléfono. Meta solo los usa para saber si la agenda vino de un anuncio; no le enviamos tu nombre ni el contenido de tus mensajes.

## Pruebas

```bash
bash tests/meta-capi/correr.sh
```

- `puras-test.php`: normalización, huella (vector fijo calculado aparte), `action_source`, cuerpo del evento sin datos en claro, huella de respaldo sin fecha.
- `paridad-test.mjs`: el navegador calcula la misma huella que PHP.
- `envio-wp-test.php` (WordPress local): una llamada por primera agenda, token en el cuerpo y no en la URL, segunda agenda del mismo correo no llama, interruptor/token/píxel/correo faltantes no llaman, una falla de red no marca el correo y el log no lleva datos personales, `/leads` dispara la acción y `/followup-meetings` no.
- `atlead-test.mjs`: `atLead` con permiso manda `eventID`, una vez por correo en 30 días, sin permiso no manda, sin `crypto.subtle` o con `localStorage` bloqueado no rompe.
- `plantillas-test.php`: la agenda del aviso y la de la portada pasan los datos; el respaldo de contacto ya no cuenta como Lead.

## Despliegue (solo Eje, con el OK de Luis)

1. Cotejar con PROD el md5 de `inc/api-endpoints.php` (PROD = `16ed868`), `mu-plugins/at-consentimiento.php`, `footer.php` y `assets/home-premium/at-home.js` (iguales a `main` al 28-oct). Si alguno cambió, no subir y rehacer el cambio sobre la versión de PROD.
2. Respaldar en `~/respaldos/` lo que se reemplaza.
3. Subir en orden: `inc/meta-capi/` (puras, envio, cargar) → `mu-plugins/at-meta-capi.php` → `assets/js/at-lead-id.js` → `inc/api-endpoints.php` → `mu-plugins/at-consentimiento.php`, `footer.php`, `assets/home-premium/at-home.js`. `php -l` de cada PHP, md5 y purga de caché.
4. Comprobar por SSH (solo lectura) que `at_capi_activo` sigue en `0`. **En este paso no se manda nada a Meta.**

**Vuelta atrás:** restaurar los respaldos; el mu-plugin y la carpeta `inc/meta-capi/` se pueden borrar sin efectos.
