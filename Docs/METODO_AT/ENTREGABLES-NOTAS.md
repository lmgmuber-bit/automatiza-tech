# Entregables con notas y versiones

Estado: **construido y probado en local (rama `claude/entregables-notas`); sin desplegar a PROD.** Diseño: `Docs/superpowers/specs/2026-10-05-entregables-notas-design.md`. Plan: `Docs/superpowers/plans/2026-10-05-entregables-notas.md`.

## Para qué sirve

Cuando AutomatizaTech entrega algo a un cliente (por ejemplo, propuestas de diseño de un sitio), el cliente tiene una **página propia del entregable**. Ahí ve la versión vigente y deja notas, con fotos si quiere, antes de la reunión. Las notas llegan a la ficha del cliente en el CRM, Luis las responde y el trabajo avanza por **versiones**: v1, observaciones, corrección, v2, y así. Cada nota queda ligada a la versión que se comentaba y todo queda en una línea de tiempo.

## Cómo se usa (Luis)

1. **Activar.** En la ficha del cliente, pestaña **📦 Entregables**, en el entregable que corresponda: botón **Activar notas del cliente**. Aparece el enlace del cliente (cópialo desde ahí).
2. **Nueva versión.** Pega el enlace de la versión (la v1 también es una versión) y escribe un mensaje opcional. Botón **Crear versión y ver vista previa**.
3. **Vista previa.** El correo se dibuja en pantalla tal como lo recibirá el cliente (plantilla fija de AT más tu mensaje).
4. **Prueba.** **Enviarme una prueba** manda el correo a tu casilla de avisos, con «[PRUEBA]» en el asunto y sin dejar registro en el entregable.
5. **Enviar.** **Enviar al cliente por correo** y/o **Enviar por mi WhatsApp** (abre WhatsApp con el texto listo y además lo muestra en un cuadro para copiar; el mensaje lo envías tú desde tu teléfono). El correo de una misma versión se manda una sola vez, aunque se haga doble clic.
6. **Responder.** Bajo cada nota del cliente: texto, hasta 3 imágenes opcionales y la casilla «Avisar al cliente por correo» (marcada por defecto). Al guardar, la nota pasa a «respondida» y aparece el botón de WhatsApp con el texto listo. Si el cliente no tiene un correo válido, la respuesta se guarda igual y el panel avisa que hay que usar WhatsApp.
7. **Cerrar.** **Cerrar el entregable** (y **Reabrir** si hace falta). Cerrado, el cliente ve la página pero ya no puede escribir.

En la lista de clientes del CRM, un cliente con notas sin responder lleva la marca **🔴**. En el portal del cliente (línea de tiempo) cada entregable con notas muestra un resumen («título, versión N, M notas») y el botón «Ver el detalle y las notas →». El portal no muestra el texto de las notas ni las imágenes.

## Qué ve el cliente

Una página `ver-entregable.php?id=<código>` (código de 12 letras y números). No se indexa en buscadores. Trae el logo de AT, el nombre del entregable, la versión vigente con su botón de apertura, las versiones anteriores, la conversación (sus notas y las respuestas de AT, cada una «sobre la versión N») y el formulario de nota. Con el entregable cerrado muestra un aviso y no hay formulario. Con un código inexistente, mal escrito o sin versiones: «Este enlace no está disponible» (404), sin decir si el código existe.

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
- Pruebas: `bash tests/entregables/correr.sh` (ocho archivos; necesitan el sitio local de pruebas indicado en `AT_WP_LOAD`).

## Despliegue a PROD (solo con autorización de Luis)

1. Reconocer en modo lectura: confirmar que `crm-ai-completo.php` y `inc/admin-proposals.php` de PROD siguen iguales a los de `main` (cotejar el contenido, no solo la fecha), y que `extension_loaded('gd')` es verdadero.
2. Respaldo de lo que se sobrescribe: `~/respaldos/entregables-antes-<fecha>.tar.gz` con `inc/admin-proposals.php`, `crm-ai-completo.php` y, si existiera, `ver-entregable.php`.
3. Subir en este orden:
   1. `inc/entregables/` completa (primero, porque la línea del paso 3 la necesita; al revés habría un error fatal).
   2. La línea de `inc/admin-proposals.php`.
   3. `ver-entregable.php`.
   4. `crm-ai-completo.php` (al final: es lo que hace visible la pestaña).
4. **Migración:** las tablas se crean solas, una vez, con la primera carga de cualquier página de `wp-admin` después de subir el código (`dbDelta` idempotente; si falla, reintenta a los 5 minutos). Entrar al CRM y confirmar que existe la opción `at_en_db_version` = `1` y las tres tablas.
5. `php -l` y md5 de cada archivo subido, purgar la caché de LiteSpeed y verificar por HTTP desde afuera: la página con un código malo (404 amable), con uno bueno de prueba, y que la carpeta de imágenes devuelva 403 al pedir un archivo directo.
6. La primera prueba real se hace con un cliente y un entregable de prueba. El entregable 30 de Orly no se toca.

Lista de subida para FTP (rutas relativas a la raíz del sitio, orden de arriba): `wp-content/themes/automatiza-tech/inc/entregables/*.php` (8 archivos, OBLIGATORIO) → `wp-content/themes/automatiza-tech/inc/admin-proposals.php` (OBLIGATORIO) → `ver-entregable.php` (OBLIGATORIO) → `wp-content/mu-plugins/crm-ai-completo.php` (OBLIGATORIO). No subir `tests/`, `Docs/` ni `.superpowers/`.

## Reversa

1. Restaurar desde `~/respaldos/entregables-antes-<fecha>.tar.gz` los dos archivos con cableado (`inc/admin-proposals.php` y `crm-ai-completo.php`); con eso el módulo deja de cargarse y desaparece la pestaña.
2. Borrar `ver-entregable.php` y `inc/entregables/` (opcional; sin el cableado no se usan).
3. Las tablas nuevas y la carpeta de imágenes **pueden quedar**: ningún otro módulo las usa y no estorban. Si se vuelve a desplegar, se reutilizan.
4. Los enlaces que ya recibió un cliente dejarían de abrir (404 amable) hasta volver a subir el módulo.

## Qué NO se ha probado todavía

- Nada se ha corrido en PROD real: ni la subida, ni la migración de tablas, ni la página con un código real.
- Fotos reales de celulares (orientación y quitado de ubicación). Las pruebas usan imágenes generadas.
- Que LiteSpeed en Hostinger respete el `.htaccess` que bloquea la carpeta de imágenes (se verifica en el paso 5 del despliegue).
- La entrega real de los correos por SMTP: en local los correos se capturan y no salen. Tampoco se ha visto cómo los muestra Gmail o un celular.
- El correo de prueba, el correo al cliente y el aviso a Luis con la configuración real de PROD.
- El resumen del portal y la marca de la lista de clientes dentro del portal real (se probaron las funciones y la pestaña en el sitio local).
- Los fallos de GitHub Actions: el repositorio no ejecuta los checks (facturación); todas las pruebas se corrieron en local.

## Nota para quien pruebe en local

El sitio local de pruebas necesita `wp-admin` y `contracts` como **copias reales**, no uniones al repositorio principal: con una unión, `admin-post.php` carga otro WordPress (error HTTP 400) y varios archivos de `contracts/` no encuentran `wp-load.php`.
