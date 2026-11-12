# Rastro · Prospección

Estado: **construido y probado en un sitio local de prueba; nada desplegado.** Spec: `Docs/superpowers/specs/2026-11-12-rastro-prospeccion-design.md`. Plan: `Docs/superpowers/plans/2026-11-12-rastro-prospeccion.md`. Módulo: `wp-content/themes/automatiza-tech/inc/prospeccion/`.

## Qué es

Una pestaña en el wp-admin (CRM › 🔎 Prospección) con la lista de negocios que Rastro encuentra, su estado, un enlace personal al formulario de agendar y un enlace de baja. **Nada sale desde el sistema**: Luis copia el mensaje o abre WhatsApp con el texto listo, lo manda él y marca «contactado». El envío automático queda para después de la revisión de Toga.

## Cómo se usa

1. **Cargar prospectos.** «Importar un CSV» (Lead Scout o Sellbird) o «Agregar a mano». El CSV puede traer BOM, UTF-8 o Windows-1252 y separador `,`, `;` o tabulador; se reconocen las columnas nombre/negocio/empresa, web, teléfono, correo, rubro, comuna y señales. Se saltan los repetidos (por dominio o teléfono), nunca se vuelve a cargar a quien pidió baja (por dominio, teléfono o correo) y se informa cuántos entraron, se repetían, eran baja o se rechazaron (sin web, teléfono ni correo, o con un campo que no cabe). El archivo no se guarda.
2. **Contactar.** En cada fila: «Copiar mensaje» (texto con la señal real, el enlace `?r=<token>` y el enlace de baja) o «Abrir WhatsApp» (`wa.me` con el texto prellenado). Después, «Marcar contactado».
3. **Seguir.** «Marcar respondió», «Descartar» o, en masa, marcar contactado/descartar. «Ver en CRM» aparece cuando ya llenó el formulario.
4. **Cuando llena el formulario.** Si la persona entra por su enlace personal y agenda el diagnóstico (o escribe por el formulario de contacto), el prospecto pasa solo a **llenó formulario**, entra a `crm_clientes` como `prospecto` con `origen = prospeccion` (si ya existía, no se toca su tipo ni su origen) y Luis recibe un correo «Prospección: <negocio> llenó el formulario» y, si está configurado, un WhatsApp con plantilla aprobada.
5. **Baja.** `?baja=<token>` muestra «Confirmar baja» (un GET nunca da de baja); al confirmar, el prospecto queda en `baja` y su correo/teléfono en la lista común de bajas de Retomar.

Estados: `por_contactar → contactado → respondio → formulario`, más `descartado` (se puede reabrir) y `baja` (final).

## Cómo detecta que llenó el formulario (sin tocar el formulario)

- `?r=<token>` deja la cookie `at_pro_r` (30 días, HttpOnly, SameSite Lax) y redirige a la misma URL sin `r`.
- `assets/js/prospeccion-ref.js` envuelve `window.atLead` (el aviso que el sitio ya emite al agendar) y avisa al AJAX `at_pro_formulario`; el servidor lee el token de la cookie, comprueba que exista un lead con ese correo creado hace menos de 30 minutos y recién entonces marca. Respaldo: el gancho `save_post_at_demo_request` hace lo mismo para el formulario de contacto.
- Si el aviso llega antes de que el sitio guarde el lead (la portada agenda por n8n), se programa un reintento 2 minutos después (`at_pro_reintentar`, WP-Cron; en PROD confirmar que el cron corre).
- `?r=` y `?baja=` piden no cachear la página (LiteSpeed/CDN); verificar en PROD.
- El AJAX no usa nonce a propósito (la caché de página lo vencería); lo protegen la cookie, el lead real reciente y un límite de 10 por hora por IP.

## Diccionario de datos: `{prefix}at_prospectos` (opción de versión `at_pro_db_version` = `1`)

| Columna | Tipo | Nota |
|---|---|---|
| id | BIGINT UNSIGNED PK | |
| negocio | VARCHAR(190) | nombre comercial |
| rubro, comuna | VARCHAR(100) | |
| web | VARCHAR(500) | (el spec decía 255; Calibre: una URL con parámetros lo supera) |
| dominio | VARCHAR(190) NULL, UNIQUE | normalizado; NULL (nunca vacío) si no hay web |
| telefono | VARCHAR(30), índice | solo dígitos con 56; es lo que se compara |
| correo | VARCHAR(190), índice | en minúscula; se compara para bajas |
| fuente | VARCHAR(30) | `lead_scout`, `sellbird`, `manual`, `instagram` |
| senales | TEXT | tope de servidor 5000 |
| servicio_sugerido | VARCHAR(100) | |
| estado | VARCHAR(20), índice | los seis estados |
| canal | VARCHAR(20) | `whatsapp`, `correo` o `web` |
| token | CHAR(16) UNIQUE | 16 hexadecimales en minúscula (`random_bytes(8)`) |
| crm_cliente_id | BIGINT UNSIGNED NULL | referencia lógica a `crm_clientes.id` (sin clave foránea, como el resto del CRM) |
| contactado_at, respondio_at, formulario_at, baja_at | DATETIME NULL | se fijan la primera vez |
| notas | TEXT | |
| created_at, updated_at | DATETIME | los fija el código (no hay `ON UPDATE`) |

Regla de largos (Calibre): WordPress corta en silencio al guardar; por eso el servidor **rechaza** lo que no cabe en vez de dejar que se trunque. Pendiente de Calibre-Codex (revisión B) y de confirmar el motor y la collation de PROD.

Retención y rollback: la tabla guarda datos de contacto publicados por negocios; falta definir la retención con Toga (Ley 21.719, desde 1-dic-2026). Rollback: `DROP TABLE {prefix}at_prospectos` y `delete_option('at_pro_db_version')`, **con respaldo previo** (pierde los prospectos); quitar la línea del `require_once` en `functions.php` apaga el módulo sin tocar datos.

## Configuración (sin valores aquí)

- Opción `at_pro_aviso_correo`: destinatario del aviso; si falta, el correo del administrador.
- Constantes en el `wp-config` de PROD (las pone Cimiento): `AT_N8N_RASTRO_AVISO` (URL del webhook de n8n) y `AT_RASTRO_AVISO_KEY` (clave que viaja en `X-AT-Rastro-Key`). Sin ellas, el WhatsApp no sale y el correo sí.
- WhatsApp: flujo n8n y plantilla Meta categoría Utilidad **no existen todavía** (fuera de este plan, los hace Cimiento con el OK de Luis).

## Pruebas

`PHP=/c/wamp64/bin/php/php8.4.15/php.exe`; puras: `"$PHP" tests/prospeccion/puras-test.php`; con WordPress: `AT_WP_LOAD=<ruta a wp-load.php de un sitio local de prueba> "$PHP" tests/prospeccion/<archivo>-wp-test.php` (esquema, datos, admin, publico, crm-avisos, baja). Todas terminan en `TODO OK`. Usan solo datos inventados y capturan `wp_mail` y las peticiones HTTP.

## Lista de subida a PROD (cuando Luis dé el OK; nada subido hoy)

Orden: 1) `wp-content/themes/automatiza-tech/inc/prospeccion/` (esquema, puras, datos, lista, admin, publico, crm-avisos, cargar), 2) `wp-content/themes/automatiza-tech/assets/js/prospeccion-ref.js`, 3) al final `wp-content/themes/automatiza-tech/functions.php` **cotejado contra PROD** (solo se agrega una línea después de la de `seo-tecnico/cargar.php`). La tabla se crea sola en el primer `init` tras subir el código (el usuario de la base de PROD necesita `CREATE`). **No subir:** `tests/`, `fixtures/`, ningún CSV, `.superpowers/`. Respaldo previo de los archivos sobrescritos, verificación desde afuera (el panel pide sesión: comprobar que `?baja=<token inventado>` responde 200 con la página «Darte de baja» (un GET nunca da de baja)) y recién entonces reportar.

## Riesgos abiertos (revisión final)

- El límite de 10 por hora por IP usa `REMOTE_ADDR`: si en Hostinger llega la IP del CDN, todas las visitas comparten el cupo. Confirmar qué IP llega antes de encender.

## Lo que no está probado

Ver el informe de entrega: el paso de punta a punta con un navegador real en PROD (agendar desde el modal con la cookie), la entrega del WhatsApp, el comportamiento con la caché LiteSpeed de PROD y el motor MariaDB/MySQL de Hostinger.
