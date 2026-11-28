# Notas y versiones de los entregables — diseño

Fecha: 2026-10-05. Rama: `claude/entregables-notas` (sale de `main` en `bad6f25`). Estado: diseño aprobado por Luis en
conversación, parte por parte (decisiones abajo); falta su revisión de este documento.

## Objetivo

Cuando AutomatizaTech le entrega algo al cliente (por ejemplo, las propuestas de diseño del sitio de Funerarias Amor de
Dios), el cliente tiene una página propia del entregable donde ve la versión vigente y deja **notas u observaciones**,
con imágenes, antes de la reunión. Esas notas quedan en la ficha del cliente en el CRM, Luis las responde, y el trabajo
avanza por **versiones**: v1 → observaciones → corrección → v2 → … Cada entregable guarda su línea de tiempo completa,
con notas de ambas partes, y cada nota queda ligada a la versión que se comentaba.

## Contexto verificado (05-oct)

- **Entregables hoy:** son filas de `wp_automatiza_clients_details` con `detail_type = 'entregable'`, colgadas de la
  **ficha operativa** (`wp_automatiza_tech_clients`), no del CRM. Ejemplo: el entregable 30 de Orly (ficha operativa 34,
  CRM 4), creado el 05-oct junto con el correo de las propuestas. La tabla la define `client-details-module.php:79`.
- **CRM:** la ficha del cliente es la página `automatiza-crm-ficha` de `wp-content/mu-plugins/crm-ai-completo.php`, que ya
  dibuja la pestaña «🗓️ Plan de trabajo» llamando a `at_pt_render_pestana()` (líneas 1985 y 2244). El paso CRM → fichas
  operativas es `_ids_ficha_operativa()` (línea 1370). El historial es `wp_crm_historial`, escrito con
  `at_cc_historial_crm()` (`inc/cierre-cliente/bienvenida.php:10`).
- **Portal del cliente:** `?crm_view=timeline&cid=&token=` (`crm-ai-completo.php:4383`), que arma una línea de tiempo
  unificada con las filas de `automatiza_clients_details`. El portal usa su propio esquema de enlace (detalle en la bóveda privada).
- **Patrón a copiar:** el módulo `inc/plan-trabajo/` (cargado desde `inc/admin-proposals.php:92` vía `cargar.php`):
  `puras.php` con funciones sin WordPress y pruebas, `datos.php`, `envio.php`, `panel.php` con acciones `admin_post_*`
  (`manage_options` + nonce), la página pública `ver-plan.php` en la raíz (código aleatorio de 12 caracteres,
  `datos.php:155`), y la agenda web con token diario `hash_hmac` + `wp_salt('nonce')` y límite por IP en un transient
  (`agenda.php:76-85`).
- **Correos a clientes:** `wp_mail` desde `SMTP_USER` (contacto@), `Reply-To: at_cc_correo_avisos()`, copia oculta
  `at_cc_cabecera_copia()`. Diseño AT: el del correo a Vitolio del 03-oct y a Orly del 05-oct (encabezado degradado
  `#1e3a8a → #06d6a0`, logo `assets/images/logo-automatiza-tech.png`, botones redondeados). Hostinger rechaza (554) los
  correos que enlazan a `*.easypanel.host`.
- **WhatsApp:** la plataforma no envía WhatsApp al cliente en este flujo; Luis lo manda desde su teléfono con un `wa.me`
  armado (mismo camino que `at_pt_url_whatsapp_cliente()`).

## Decisiones de Luis (05-oct)

1. **Iteraciones = versiones dentro del mismo entregable** (no un hilo sin versiones). Cada nota queda ligada a la versión
   que se comentaba.
2. **Avisos:** cuando el cliente escribe, correo a Luis; cuando Luis responde, correo al cliente si deja marcada «Avisar al
   cliente» (marcada por defecto) **y** botón de WhatsApp con el texto listo. Lo mismo al enviar una versión: correo y/o
   WhatsApp. El WhatsApp lo envía Luis desde su teléfono.
3. **El cliente puede adjuntar texto e imágenes** (JPG/PNG).
4. **Correo de versión = plantilla fija de AT + mensaje libre de Luis**, con vista previa y prueba a su correo.
5. **Página propia por entregable** (`ver-entregable.php?id=<código>`), no dentro del portal.
6. **Línea de tiempo en dos lugares, con distinto detalle:** completa en la ficha del CRM; en el portal del cliente, un
   resumen con un botón «Ver el detalle y las notas →» a la página propia.
7. **Arquitectura: módulo nuevo `inc/entregables/` con tablas propias** (opción 1 de 3).
8. **El entregable 30 de Orly no se toca.** La prueba se hace con un cliente y un entregable de prueba.

## Componentes

### 1. Datos (`inc/entregables/datos.php`, migración)

Tres tablas nuevas; `automatiza_clients_details` no cambia.

| Tabla | Columnas |
|---|---|
| `{prefix}at_entregables` | `id`, `detalle_id` (fila de `automatiza_clients_details`, única), `crm_id`, `codigo` (12 alfanuméricos, único), `estado` (`abierto`/`cerrado`), `version_vigente` (int, 0 sin versiones), `creado_at`, `actualizado_at`, `cerrado_at` |
| `{prefix}at_entregable_versiones` | `id`, `entregable_id`, `numero` (1, 2, 3…; único por entregable), `url` (http/https, nunca easypanel), `mensaje` (texto de Luis, máx. 6.000), `enviado_correo_at` (null si no salió), `enviado_whatsapp_at`, `creado_at` |
| `{prefix}at_entregable_notas` | `id`, `entregable_id`, `version_numero`, `autor` (`cliente`/`at`), `nombre` (máx. 80), `texto` (1 a 3.000 caracteres), `imagenes` (JSON con hasta 3 nombres de archivo), `respondida` (0/1, solo notas del cliente), `aviso_ok` (0/1), `ip_hash` (sha256 de IP + sal; nunca la IP en claro), `creado_at` |

- **Activar notas** de un entregable crea su fila en `at_entregables` con un código de `wp_generate_password(12, false)`;
  si el código ya existe, se genera otro. Un entregable se activa una sola vez (índice único en `detalle_id`).
- **Nueva versión:** `numero = MAX + 1` dentro de una transacción con `SELECT … FOR UPDATE` sobre la fila del entregable,
  para que dos clics no creen dos v2. `version_vigente` se actualiza en la misma transacción.
- **Cerrar / reabrir:** cambia `estado`; cerrado = la página muestra todo pero no acepta notas.
- **Historial del CRM:** cada versión enviada, cada nota del cliente y cada respuesta de AT se anota también en
  `wp_crm_historial` (`at_cc_historial_crm`, tipos `entregable_version`, `entregable_nota`, `entregable_respuesta`), con el
  id del entregable en el título o la descripción. El `crm_id` sale de la ficha operativa del detalle (camino inverso de
  `_ids_ficha_operativa`); si no se puede resolver, no se activa (falla cerrada, mensaje en el panel).
- **Migración:** `dbDelta` en una función `at_en_crear_tablas()`; en PROD se corre **antes** de subir el resto del código
  (como las migraciones de CumpleClick). Versión del esquema en la opción `at_en_db_version`.

### 2. Página del cliente (`ver-entregable.php` en la raíz + `inc/entregables/vista.php`)

- **Entrada:** `?id=<código>`. Código con otro formato, inexistente o de un entregable sin versiones → página amable «Este
  enlace no está disponible» (HTTP 404), nunca un error técnico. `noindex, nofollow` y `Cache-Control: private, no-store`.
- **Contenido:** logo AT; nombre del entregable (título del detalle) y empresa del cliente; versión vigente con botón
  «Abrir la versión N»; versiones anteriores con fecha y enlace; conversación en orden de fecha (burbujas de cliente y de AT
  en colores distintos, «sobre la versión N», fecha, miniaturas que se abren en grande); formulario.
- **Formulario** (solo si `abierto`): nombre (precargado con el nombre de la ficha, editable), nota, hasta 3 imágenes,
  token oculto, «Enviar nota». Al enviar: «Recibimos tu nota. Te responderemos antes de la reunión.» y la nota aparece en el
  hilo. Si está `cerrado`: «Este entregable ya está cerrado. Si necesitas algo, escríbenos por WhatsApp.»
- **Envío del formulario:** POST a la misma página (o a `admin-post.php?action=at_en_nota` con `nopriv`), con:
  - token `hash_hmac('sha256', 'at-en-nota|' . codigo . '|' . dia, wp_salt('nonce'))` recortado a 24, válido el día de
    emisión y el siguiente (mismo esquema que `at_pt_token_agenda`);
  - límite de **10 notas por hora** por entregable y por IP (transient con la IP hasheada);
  - texto obligatorio de 1 a 3.000 caracteres (se cuenta con `mb_strlen`), nombre de 1 a 80; se guarda tal cual y se
    escapa siempre al mostrar (`esc_html` + `nl2br`).
- **Móvil primero:** la página se prueba a 375 px y en escritorio.

### 3. Imágenes (`inc/entregables/imagenes.php`)

- **Opcionales:** una nota puede llevar de **0 a 3** imágenes (confirmado por Luis el 05-oct); **5 MB** cada una, solo
  **JPG y PNG**.
- Validación en el servidor: `is_uploaded_file`, tamaño, `wp_check_filetype_and_ext` + `getimagesize` sobre el contenido
  real (un PHP renombrado a `.jpg` se rechaza), dimensiones máximas razonables (lado ≤ 12.000 px antes de procesar).
- Cada imagen se **reescribe** con `wp_get_image_editor` (quita EXIF/GPS) y se reduce a un lado máximo de 2.000 px; se
  guarda como `<aleatorio 24>.jpg` o `.png` en `wp-content/uploads/at-entregables/<id entregable>/`.
- La carpeta lleva `.htaccess` `Require all denied` (más `index.php` vacío). Las imágenes solo se sirven por
  `ver-entregable.php?id=<código>&img=<nombre>` (o un PHP propio), que comprueba que la imagen pertenezca a una nota de ese
  entregable, valida el nombre con una expresión estricta (sin rutas), y responde con su tipo, `Cache-Control: private,
  no-store` y `X-Content-Type-Options: nosniff`.
- Luis también puede adjuntar imágenes en sus respuestas (mismas reglas).
- Si una imagen falla la validación, la nota no se guarda y el cliente ve qué imagen y por qué («La imagen 2 no es JPG ni
  PNG»).

### 4. Panel en la ficha del CRM (`inc/entregables/panel.php`)

- Pestaña **«📦 Entregables»** en `automatiza-crm-ficha`, dibujada por `crm-ai-completo.php` con una llamada a
  `at_en_render_pestana($cliente)` junto a la del plan (mismo `function_exists` + `manage_options`).
- **Lista** de los entregables del cliente (filas `entregable` de sus fichas operativas) con su estado, versión vigente y
  **«🔴 N notas sin responder»**. Sin notas activadas: botón «Activar notas del cliente».
- **Detalle** de un entregable: línea de tiempo (versiones con su mensaje plegado y cómo salieron, notas del cliente con
  miniaturas, respuestas de AT) y acciones:
  - **Nueva versión:** enlace + mensaje → **Vista previa** (el correo dibujado en la pantalla) → **Enviarme una prueba**
    (a `at_cc_correo_avisos()`, asunto con «[PRUEBA]», sin registro) → **Enviar al cliente por correo** y/o **Enviar por mi
    WhatsApp** (abre `wa.me` con el texto y además lo muestra en un cuadro para copiar).
  - **Responder** bajo una nota del cliente: texto, imágenes opcionales, casilla «Avisar al cliente por correo» marcada por
    defecto; al guardar, la nota queda `respondida` y aparece el botón de WhatsApp con el texto listo.
  - **Copiar el enlace** de la página del cliente; **Cerrar** / **Reabrir**.
- Toda acción: `admin_post_at_en_*`, `manage_options`, nonce por entregable, vuelta a la ficha con `#tab-entregables` y
  un mensaje.
- **Lista de clientes del CRM:** una marca «🔴 notas sin responder» en la fila del cliente que las tenga.

### 5. Resumen en el portal del cliente

- En la línea de tiempo unificada de `?crm_view=timeline`, cada entregable con notas activadas muestra: «<Título>, versión
  N, M notas» y el botón **«Ver el detalle y las notas →»** a su `ver-entregable.php`. El portal **no** muestra el texto de
  las notas ni las imágenes.
- Cambio mínimo en `crm-ai-completo.php`: una llamada a una función del módulo dentro del bucle de detalles. Antes de
  editarlo, se coteja con PROD (la copia local puede estar atrasada).

### 6. Correos y WhatsApp (`inc/entregables/envio.php`, plantillas en `puras.php`)

Todos con el diseño AT del correo a Orly del 05-oct, desde `SMTP_USER`, sin enlaces a easypanel.

| Correo | Para | Contenido | Asunto |
|---|---|---|---|
| Versión enviada | cliente (correo de la ficha del CRM); `Reply-To: at_cc_correo_avisos()`; copia oculta `at_cc_cabecera_copia()` y lgonzalez@ | «Te enviamos la versión N de <entregable>», **mensaje de Luis** (párrafos y listas simples con `-`), botones «Ver la versión N» y «Agregar notas u observaciones», firma de Luis | `<Entregable>: versión N para revisar` |
| Nota nueva del cliente | `at_cc_correo_avisos()` | quién, sobre qué versión, texto, cuántas imágenes, botón «Responder en la ficha» (enlace al admin) | `📝 <Cliente> dejó una nota en <entregable>` |
| Respuesta de AT | cliente, solo con «Avisar» marcada; copia oculta a Luis | «Luis respondió tu nota sobre la versión N», extracto de hasta 400 caracteres, botón «Ver la conversación» | `Respuesta a tu nota sobre <entregable>` |

- **El mensaje de Luis** se escapa y se convierte a HTML solo con reglas simples (párrafos por línea en blanco, líneas que
  empiezan con `- ` → lista, URLs → enlace). No se acepta HTML libre.
- **WhatsApp** (texto para `wa.me` al teléfono de la ficha; sin teléfono, solo el cuadro para copiar):
  - versión: «Hola <nombre>, te escribe Luis de AutomatizaTech. Te envié la versión N de <entregable>. Puedes verla y
    dejarme tus notas aquí: <enlace>»
  - respuesta: «Hola <nombre>, te respondí tu nota sobre la versión N de <entregable>: <enlace>»
- **Fallos:** si `wp_mail` falla al enviar una versión, la versión queda sin `enviado_correo_at` y el panel muestra «No se
  envió el correo» con el motivo (`wp_mail_failed`); se puede reintentar. Si falla el aviso a Luis de una nota nueva, la
  nota se guarda igual con `aviso_ok = 0` y el panel la marca «sin aviso».
- **Nada al cliente sale solo:** cada correo al cliente requiere el clic de Luis en el panel.

## Errores y seguridad

- **Repo público:** sin secretos; las firmas usan `wp_salt('nonce')` del servidor. Ningún fallo explotable se describe en
  commits o PR; los hallazgos sensibles van a la bóveda privada.
- **Página pública:** solo código de 12 caracteres alfanuméricos (`/^[A-Za-z0-9]{12}$/`); respuestas iguales para «no
  existe» y «sin versiones»; token de formulario + límite por IP; escapado de todo lo que escribe el cliente; sin listado de
  directorios; imágenes servidas por PHP con verificación de pertenencia.
- **Admin:** `manage_options` + nonce en cada acción; los ids se cruzan siempre con el entregable y el cliente de la ficha
  (una nota o versión de otro entregable se rechaza).
- **CDN de Hostinger:** reprocesa imágenes. Las imágenes de notas no deben pasar por su caché (`no-store`, servidas por
  PHP). Se verifica en PROD por SSH, **sin sondear por el CDN** archivos privados.
- **Fuera de este trabajo, pero ligado:** el esquema de enlace del portal (detalle en la bóveda privada). El portal solo enlazará
  a la página del entregable y no mostrará su contenido.

## Pruebas

Pruebas primero (rojo → verde), carpeta `tests/entregables/`, con el arnés `wp-local-plan` para las de WordPress:

- **Puras:** formato del código; token del formulario (vigente, de ayer, vencido, alterado); validación de nota y nombre
  (vacía, 3.000 / 3.001 caracteres con tildes y emoji); reglas de imagen (tipo real, tamaño, cantidad, nombre de archivo
  con `../`); conversión del mensaje de Luis a HTML (escapado, listas, enlaces, `<script>` queda como texto); los tres
  correos (contienen los botones correctos, no contienen easypanel, escapan el nombre del cliente); textos de WhatsApp.
- **Con WordPress (local, sin red ni correo real: `pre_http_request` y `pre_wp_mail` bloqueados o capturados):** activar
  notas (una sola vez, código único, `crm_id` resuelto); nueva versión (numeración, dos envíos simultáneos no duplican la
  v2); nota del cliente con imágenes (EXIF/GPS borrado, tamaño reducido, archivo fuera de la carpeta pública, `.htaccess`
  presente); código inexistente / cerrado / sin versiones; límite de 10 por hora; respuesta con y sin aviso; historial del
  CRM escrito; resumen del portal sin texto de notas; acciones admin sin permiso o con nonce malo rechazadas; un id de nota
  de otro entregable rechazado.
- Las 27 suites actuales siguen en verde.
- **Prueba real en PROD con Luis** (con su autorización): cliente y entregable de prueba con su correo y teléfono; activar,
  enviar v1 por correo y WhatsApp a sí mismo; nota con 1–2 fotos desde su celular; revisar aviso, ficha, historial y
  portal; responder con aviso; v2; segunda nota; cerrar. Al final se borra todo lo de prueba (filas, historial, imágenes)
  **con respaldo**.

## Despliegue

Con autorización explícita de Luis: rama y PR desde `main`; revisión por tareas y revisión final de la rama; comentario
en el PR con las pruebas; en el servidor, respaldo de lo que se sobrescribe → migración de tablas → subida del código
(`inc/entregables/`, `ver-entregable.php`, la línea en `inc/admin-proposals.php`, el cambio mínimo en
`crm-ai-completo.php`) → `php -l` y md5 → `wp litespeed-purge all` → verificación por HTTP desde afuera (página con código
bueno y malo, carpeta de imágenes bloqueada). No se toca `functions.php`. Merge solo con permiso de Luis.

## Fuera de alcance

- Activar notas en el entregable 30 de Orly (Luis decide después; el correo del 05-oct no se toca).
- Archivos que no sean JPG/PNG (PDF, Word).
- Notificaciones por WhatsApp automáticas (plantillas de Meta).
- Aprobación formal del entregable por el cliente (firma o botón «Apruebo»); hoy el cierre lo hace Luis.
- Cambios al esquema de enlace del portal del cliente (detalle en la bóveda privada).

## Pendientes de Luis

- Revisar este documento.
- Después del plan: autorizar la subida a PROD y la prueba real.
