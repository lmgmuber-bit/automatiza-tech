# Entregables con notas y versiones

Estado: **construido y probado en local (rama `claude/entregables-notas`); sin desplegar a PROD.** Diseño: `Docs/superpowers/specs/2026-10-05-entregables-notas-design.md`. Plan: `Docs/superpowers/plans/2026-10-05-entregables-notas.md`.

## Para qué sirve

Cuando AutomatizaTech entrega algo a un cliente (por ejemplo, propuestas de diseño de un sitio), el cliente tiene una **página propia del entregable**. Ahí ve la versión vigente y deja notas, con fotos si quiere, antes de la reunión. Las notas llegan a la ficha del cliente en el CRM, Luis las responde y el trabajo avanza por **versiones**: v1, observaciones, corrección, v2, y así. Cada nota queda ligada a la versión que se comentaba y todo queda en una línea de tiempo.

## Cómo se usa (Luis)

1. **Activar.** En la ficha del cliente, pestaña **📦 Entregables**, en el entregable que corresponda: botón **Activar notas del cliente**. Aparece el enlace del cliente (cópialo desde ahí).
2. **Nueva versión.** Pega el enlace de la versión (la v1 también es una versión) y escribe un mensaje opcional. Botón **Crear versión y ver vista previa**.
3. **Vista previa.** El correo se dibuja en pantalla tal como lo recibirá el cliente (plantilla fija de AT más tu mensaje). **Mientras la versión no se haya enviado (ni por correo ni por WhatsApp) aparece debajo «Editar esta versión»**, con el enlace y el mensaje ya cargados: corrige y guarda («Versión actualizada. Revisa la vista previa.»). Una vez enviada ya no se puede editar: se crea una versión nueva. El mensaje no puede llevar enlaces de easypanel (Hostinger rechaza esos correos): usa el de `automatizatech.cl` (`ver-presentacion.php`).
4. **Prueba.** **Enviarme una prueba** manda el correo a tu casilla de avisos, con «[PRUEBA]» en el asunto y sin dejar registro en el entregable.
5. **Enviar.** **Enviar al cliente por correo** y/o **Enviar por mi WhatsApp** (abre WhatsApp con el texto listo y además lo muestra en un cuadro para copiar; el mensaje lo envías tú desde tu teléfono). El correo de una misma versión se manda una sola vez, aunque se haga doble clic.
6. **Responder.** Bajo cada nota del cliente: texto, hasta 3 imágenes opcionales y la casilla «Avisar al cliente por correo» (marcada por defecto). Al guardar, la nota pasa a «respondida» y aparece el botón de WhatsApp con el texto listo. La respuesta queda sobre la misma versión de la nota que contesta y se firma siempre «Luis». Si el cliente no tiene un correo válido o el envío falla, la respuesta se guarda igual y el panel avisa que hay que usar WhatsApp; cuando el SMTP informó la causa, el panel la muestra al lado del aviso durante un minuto (solo a quien envió).
7. **Cerrar.** **Cerrar el entregable** (y **Reabrir** si hace falta). Cerrado, el cliente ve la página pero ya no puede escribir.

En la lista de clientes del CRM, un cliente con notas sin responder lleva la marca **🔴**. En el portal del cliente (línea de tiempo) cada entregable con alguna versión ya enviada muestra un resumen («título, versión N, M notas», con la N de la última versión enviada) y el botón «Ver el detalle y las notas →». Si todavía no se envió ninguna versión, no muestra nada. El portal no muestra el texto de las notas ni las imágenes.

## Qué ve el cliente

Una página `ver-entregable.php?id=<código>` (código de 12 letras y números). No se indexa en buscadores ni manda el origen al abrir un enlace (`Referrer-Policy: no-referrer`). Trae el logo de AT, el nombre del entregable, la versión vigente con su botón de apertura, las versiones anteriores, la conversación (sus notas y las respuestas de AT, cada una «sobre la versión N») y el formulario de nota. Con el entregable cerrado muestra un aviso y no hay formulario. Con un código inexistente, mal escrito o **sin ninguna versión enviada**: «Este enlace no está disponible» (404), sin decir si el código existe.

**El cliente ve la última versión ENVIADA, no la última creada.** Una versión cuenta como enviada cuando se mandó por correo o por WhatsApp. Si Luis crea la v2 y todavía no la envía, la página sigue mostrando la v1 como vigente, las notas nuevas del cliente se guardan sobre la v1 y las versiones sin enviar no aparecen ni en «anteriores». Al enviar la v2, la página pasa a mostrarla.

**Si algo falla al adjuntar imágenes**, la página dice cuál y por qué («La imagen 2 no es JPG ni PNG.», «pesa más de 5 MB», «demasiado grande…», «no se pudo subir», «no se pudo procesar»). Además, el navegador avisa antes de enviar si hay más de 3 imágenes o alguna pesa más de 5 MB (con JavaScript; sin JavaScript el formulario funciona igual y revisa el servidor) y **guarda el nombre y la nota en esa pestaña** (sessionStorage) para no perderlos si el envío vuelve con un error; el borrador se borra cuando la nota se envía bien.

## Límites

- Nota: de 1 a 3.000 caracteres. Nombre: de 1 a 80. Mensaje de Luis en una versión: hasta 6.000.
- Imágenes: opcionales, **0 a 3 por nota**, solo JPG y PNG (se revisa el contenido real, no la extensión), **5 MB cada una**, máximo 12.000 px por lado y 25 megapíxeles. Las fotos de celular de 12 MP pasan; las de cámaras de 48 o 50 MP en modo completo se rechazan con un mensaje claro.
- Cada imagen se reescribe con el editor GD (se fuerza GD; el editor por defecto de PROD es Imagick, que en imágenes chicas dejaría el EXIF): se aplica la orientación de la cámara, se elimina el EXIF y la ubicación GPS y el lado mayor queda en 2.000 px como máximo.
- Si el envío completo supera el tope del servidor (`post_max_size`), el cliente ve «Las imágenes pesan demasiado en total…» en vez de un error.
- **10 notas por hora** por entregable y por IP.
- Enviar una versión por correo y responder una nota son operaciones atómicas: un doble clic no manda dos correos ni guarda dos respuestas.

## Archivos y tablas

- Código: `wp-content/themes/automatiza-tech/inc/entregables/` (`cargar.php`, `puras.php`, `plantillas.php`, `datos.php`, `imagenes.php`, `envio.php`, `vista.php`, `panel.php`).
- Página pública: `ver-entregable.php` en la raíz del sitio.
- Cableado: una línea en `wp-content/themes/automatiza-tech/inc/admin-proposals.php` (carga el módulo, igual que el plan de trabajo) y cuatro cambios pequeños en `wp-content/mu-plugins/crm-ai-completo.php` (botón y contenido de la pestaña, marca en la lista de clientes y resumen del portal). `functions.php` no se toca.
- Tablas (con el prefijo de WordPress): `at_entregables`, `at_entregable_versiones`, `at_entregable_notas`. Opción de esquema: `at_en_db_version`.
- Imágenes: `wp-content/uploads/at-entregables/<id>/`, con `.htaccess` que niega el acceso directo (`Require all denied`, o `Deny from all` en servidores antiguos) y un `index.php` vacío. Solo se sirven por `ver-entregable.php?id=<código>&img=<nombre>`.
- Pruebas: `bash tests/entregables/correr.sh` (nueve archivos; necesitan el sitio local de pruebas indicado en `AT_WP_LOAD`). La constante `AT_EN_PRUEBAS` solo se define en `tests/entregables/wp-bootstrap.php` y `accion-run.php`: en CLI no hay subida HTTP y con ella un archivo local cuenta como subido; en PROD no existe y rige `is_uploaded_file()`.

## Despliegue a PROD (solo con autorización de Luis)

1. Reconocer en modo lectura: confirmar que `crm-ai-completo.php` y `inc/admin-proposals.php` de PROD siguen iguales a los de `main` (cotejar el contenido, no solo la fecha); que `extension_loaded('gd')` es verdadero; que `upload_max_filesize` es al menos 5M y `post_max_size` al menos 16M (3 imágenes de 5 MB más el texto; si no, el cliente vería «Las imágenes pesan demasiado en total» con fotos normales); y que las tres tablas nuevas, cuando existan, son InnoDB (`SHOW TABLE STATUS`: la versión nueva usa transacciones y bloqueo de fila).
2. Respaldo de lo que se sobrescribe: `~/respaldos/entregables-antes-<fecha>.tar.gz` con `inc/admin-proposals.php`, `crm-ai-completo.php` y, si existiera, `ver-entregable.php`.
3. Subir en este orden:
   1. `inc/entregables/` completa (primero, porque la línea de `inc/admin-proposals.php` —el punto 2 de esta misma lista— la necesita; al revés habría un error fatal).
   2. La línea de `inc/admin-proposals.php`.
   3. `ver-entregable.php`.
   4. `crm-ai-completo.php` (al final: es lo que hace visible la pestaña).
4. **Migración:** las tablas se crean solas, una vez, con la primera carga de cualquier página de `wp-admin` después de subir el código (`dbDelta` idempotente; si falla, reintenta a los 5 minutos). Entrar al CRM y confirmar que existe la opción `at_en_db_version` = `1` y las tres tablas.
5. `php -l` y md5 de cada archivo subido, purgar la caché de LiteSpeed y verificar por HTTP desde afuera: la página con un código malo (404 amable), con uno bueno de prueba, y que la carpeta de imágenes bloquee el acceso directo. Para eso se crea **un archivo de sondeo desechable** en `wp-content/uploads/at-entregables/` (un `.txt` con texto inocuo hecho para la prueba; **nunca una imagen real de un cliente**), se pide por HTTP, debe devolver 403, y se borra al terminar. Siempre que se pueda, se comprueba antes por SSH (el `.htaccess` existe con `Require all denied`, permisos de la carpeta) y no se sondean por el CDN archivos privados: un `HEAD` los deja en la caché del CDN.
6. La primera prueba real se hace con un cliente y un entregable de prueba. El entregable 30 de Orly no se toca.

Lista de subida para FTP (rutas relativas a la raíz del sitio, orden de arriba): `wp-content/themes/automatiza-tech/inc/entregables/*.php` (9 archivos, OBLIGATORIO; con `ia.php` y el `cargar.php` que lo incluye) → `wp-content/themes/automatiza-tech/inc/admin-proposals.php` (OBLIGATORIO) → `ver-entregable.php` (OBLIGATORIO) → `wp-content/mu-plugins/crm-ai-completo.php` (OBLIGATORIO). No subir `tests/`, `Docs/` ni `.superpowers/`.

## Sugerencias con IA (botones «✨ Sugerir»)

Dos botones en la pestaña «📦 Entregables» de la ficha del cliente, ambos solo en el panel (el cliente nunca los ve) y ambos **opcionales**: el texto sugerido se pone en el cuadro, Luis lo lee, lo corrige y recién entonces guarda o envía. Nada se envía solo.

- **«✨ Sugerir mensaje»** (junto al mensaje de «Nueva versión» y de «Editar esta versión»): lee la página del enlace de la versión (texto plano, hasta 8.000 caracteres; si el enlace está vacío avisa «Pega primero el enlace de la versión.») y, desde la v2, las notas y respuestas de la versión anterior. Redacta de 4 a 8 líneas, en español de Chile y de «tú», sin saludo ni firma (los pone la plantilla), sin inventar precios, plazos ni funciones, y sin enlaces a easypanel (además se borra toda línea que mencione easypanel).
- **«✨ Sugerir respuesta»** (bajo cada nota del cliente sin responder): propone la respuesta de Luis en 2 a 6 líneas, a partir de la nota, de las últimas 6 notas del entregable y, si la nota trae fotos (hasta 3), de las fotos.
- **Modelos y costo aproximado** (precios por millón de tokens, entrada/salida, de https://developers.openai.com/api/docs/pricing consultados el 06-oct-2026): `gpt-4o-mini` (US$0,15 / US$0,60) para el mensaje y para respuestas sin fotos, ≈ **US$0,0006** por sugerencia; `gpt-4o` (US$2,50 / US$10,00) cuando la nota trae fotos (detalle «low»), ≈ **US$0,016**. Son estimaciones con ~2.500 tokens de entrada y ~370 de salida (mini) y ~5.000 y ~350 (gpt-4o); el consumo real queda registrado.
- **Privacidad:** el texto de la página, las notas del cliente y sus fotos viajan a OpenAI **solo cuando Luis pulsa el botón**; no hay envío automático ni en segundo plano. Las fotos salen de la carpeta privada del módulo y no se publican.
- **Límite:** 30 sugerencias por hora por usuario (cuenta solo las logradas; transient `at_en_ia_<usuario>`). Al pasarlo avisa «Llegaste al límite de sugerencias por hora.».
- **Consumo:** cada sugerencia lograda se anota en `wp_ai_usage_log` con `client_identifier = 'entregables'` y `request_type` `entregables_mensaje` o `entregables_respuesta` (modelo, tokens y costo estimado); aparece en el panel de consumo de IA del CRM.
- **Clave:** la constante `OPENAI_API_KEY` (variable de entorno o `wp-config-secrets.php`, la misma de los otros módulos). Sin clave el botón avisa «La IA no está configurada.»; si OpenAI falla, «No se pudo sugerir: escribe el texto a mano.». La clave nunca se imprime ni viaja en un error.
- **Código:** `inc/entregables/ia.php` (llamada, consumo, AJAX `wp_ajax_at_en_sugerir`, solo con sesión) y los prompts puros en `inc/entregables/puras.php`. Pruebas: `tests/entregables/ia-puras-test.php` y `ia-wp-test.php` (todo el HTTP simulado: no gastan créditos).

## Reversa

1. Restaurar desde `~/respaldos/entregables-antes-<fecha>.tar.gz` los dos archivos con cableado (`inc/admin-proposals.php` y `crm-ai-completo.php`); con eso el módulo deja de cargarse y desaparece la pestaña.
2. Borrar `ver-entregable.php` y `inc/entregables/` (opcional; sin el cableado no se usan).
3. Las tablas nuevas y la carpeta de imágenes **pueden quedar**: ningún otro módulo las usa y no estorban. Si se vuelve a desplegar, se reutilizan.
4. Los enlaces que ya recibió un cliente dejarían de abrir (404 amable) hasta volver a subir el módulo.

## Riesgos y decisiones pendientes

- Enlace del portal: resuelto el 06-oct-2026 (los enlaces del portal van firmados con una clave del servidor; PR #75). Ya se pueden activar notas para clientes reales.

## Qué NO se ha probado todavía

- Los botones «✨ Sugerir» nunca han llamado a OpenAI de verdad: las pruebas simulan todo el HTTP (no se gastaron créditos). La primera sugerencia real, con la clave de PROD, está por verse (calidad del texto, tiempos, y que `OPENAI_API_KEY` esté definida en PROD).
- Nada se ha corrido en PROD real: ni la subida, ni la migración de tablas, ni la página con un código real.
- Fotos reales de celulares (orientación y quitado de ubicación). Las pruebas usan imágenes generadas.
- Que LiteSpeed en Hostinger respete el `.htaccess` que bloquea la carpeta de imágenes (se verifica en el paso 5 del despliegue).
- La entrega real de los correos por SMTP: en local los correos se capturan y no salen. Tampoco se ha visto cómo los muestra Gmail o un celular.
- El correo de prueba, el correo al cliente y el aviso a Luis con la configuración real de PROD.
- El resumen del portal y la marca de la lista de clientes dentro del portal real (se probaron las funciones y la pestaña en el sitio local).
- Los fallos de GitHub Actions: el repositorio no ejecuta los checks (facturación); todas las pruebas se corrieron en local.

## Nota para quien pruebe en local

El sitio local de pruebas necesita `wp-admin` y `contracts` como **copias reales**, no uniones al repositorio principal: con una unión, `admin-post.php` carga otro WordPress (error HTTP 400) y varios archivos de `contracts/` no encuentran `wp-load.php`.
