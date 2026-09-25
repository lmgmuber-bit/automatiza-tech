# Propuestas AT — flujo automático v3

Complementa la guía de plantilla única (`Docs/METODO_AT/PROPUESTAS-PLANTILLA-UNICA.md`, PR #36): toda propuesta
se arma con el propuesta-renderer; aquí se describe cómo nace, se ajusta y se envía sin trabajo manual.
Plan de implementación: `Docs/superpowers/plans/2026-09-23-flujo-propuestas-v3.md`.

## Recorrido

1. **Llamada con el cliente** → Google Meet deja la transcripción en Drive › Transcripciones
   (`14Qy7majCZlelxzkyUcjyJZjZ0mgMoGWW`) → workflow «Google Meet → Propuesta» (`FrWZcgbizlipK5pb`) la lee y la
   manda a `POST /webhook/propuesta-v3-borrador` con `{transcript, client_email, drive_file_id}`.
   Si no encuentra el correo del cliente, avisa a Luis y no crea nada.
2. **«Propuestas v3 · 1 Borrador»** (`7hglMG2j17HdOh6U`): GPT-4o redacta con la plantilla, precios «Por confirmar»,
   fotos descritas por rubro (ver abajo), asistente de demo; crea la fila en WordPress (`borrador`, `flujo='v3'`),
   vista previa **sin fotos** con sello Borrador y correo de marca a Luis. Gasto en fotos: cero.
3. **Panel WordPress** (Propuestas › la propuesta › pestaña «Revisión y precios»): Luis escribe precios y comentarios →
   **Pedir cambios** → «2 Cambios» (`25rjGcjDYPDECn6p`) aplica solo los comentarios (nunca toca precios: WordPress
   los restaura), rehace la vista previa y avisa. Se repite las veces que haga falta. Si falla, la propuesta
   queda en `error` con el motivo y llega un correo.
4. **Aprobar** (el botón muestra cuántas fotos y su costo) → «3 Final» (`elReU26Sju1lpdO5`): filtra las
   descripciones, genera las fotos con Soul 2, las guarda en `/p/<id>/img/` junto a la presentación y renderiza.
   Si faltan fotos, **reintenta hasta 3 renders**; el renderer reutiliza las ya guardadas con el mismo prompt
   (`img/manifest.json`), así que una foto ya guardada no se vuelve a pagar. Sí puede pagarse de nuevo una foto
   que Higgsfield cobró pero no alcanzó a entregar (se corta por tiempo y se pide otra vez): el botón muestra el
   costo normal (≈ US$0,0032 por foto) y el peor caso teórico con reintentos es ≈ 6 veces eso. Verifica
   presentación, PDF y chatbot. Queda `lista` o `error` con el detalle, y llega el correo.
5. **Envío**: solo desde `lista`, con la casilla «Enviar correo» del panel. Nunca automático.

Estados: `borrador → ajustando|generando`, `ajustando → borrador|error`, `generando → lista|error`,
`lista → ajustando|sent`, `error → borrador|ajustando|generando`. Las propuestas viejas (`flujo` NULL) siguen igual.

**Si algo se cae.** Los nodos HTTP de Cambios y Final siguen ante un timeout o una conexión cortada y dejan la
propuesta en `error` con el motivo. Si un flujo se cae igual (OpenAI caído, un JSON ilegible), el workflow
«0 Avisar error» (`m7TOfKznVSBGz4Nd`, `settings.errorWorkflow` de los tres) le escribe a Luis. Una propuesta que
haya quedado en `ajustando` o `generando` se destraba desde el panel (pasa a `error`, que es transición válida).

## Panel de propuestas (wp-admin › Propuestas, EN PROD desde el 2026-09-24 15:48)

Diseño: `Docs/superpowers/specs/2026-09-24-modulo-propuestas-admin-design.md`; plan:
`Docs/superpowers/plans/2026-09-24-modulo-propuestas-admin.md`.

- **Lista** (`WP_List_Table`): buscador por empresa, cliente, correo o teléfono; vistas por estado con su conteo
  (Todas, Borrador, Enviadas, Pendiente, Error…); filtro de fechas Desde/Hasta; orden por columna; paginación con
  «Opciones de pantalla» (5 a 200 por página).
- **Borrar:** una sola con el enlace «Borrar» bajo el nombre de la empresa (siempre visible desde el 24-sep 22:30);
  varias marcando las casillas → botón rojo «🗑️ Borrar marcadas», o «Acciones en lote» → «Borrar» → «Aplicar».
  Todo pide confirmación. Buscar, Filtrar o Enter nunca borran. 🔴 El botón se llama `at_borrar_marcadas` a
  propósito: `wp-admin/js/common.js` bloquea cualquier envío con `name="bulk_action"` si el menú de lote está en
  «-1» (así falló la primera versión del botón en la prueba local).
- **Ficha** en pestañas: Resumen (con «Siguiente paso»), Cliente y enlaces, Revisión y precios (solo v3), Contenido,
  Seguimiento y Envío. En el celular las pestañas pasan a un selector.
- **Guardar** es una barra fija, oculta en «Revisión y precios» (ahí guardan «Pedir cambios» y «Aprobar»). En las
  propuestas viejas la casilla «Enviar correo» viene marcada como siempre: la barra lo avisa con el correo del
  cliente y Guardar (o Enter) pide confirmar antes de mandarlo.
- **Correo al cliente precargado (desde el 24-sep 22:30):** la pestaña Envío trae el asunto, la introducción,
  «¿Qué incluye?» y el cierre ya escritos. Los redacta GPT-4o en «1 Borrador» desde la reunión (clave
  `correo_cliente` dentro del contenido, sin montos, saludo ni firma) y «2 Cambios» los ajusta si los comentarios
  cambian la solución, las fases o los próximos pasos. Si una propuesta no los trae, WordPress los arma desde su
  contenido (`at_pa_correo_textos`). Lo que Luis edite se guarda con Guardar, salvo con la propuesta en
  `ajustando` (lo avisa). Guardar sin tocarlos no congela el texto sugerido. Verificado en PROD con una propuesta
  de prueba (fila 52, borrada después).
- **PDF adjunto, en este orden:** el que Luis suba en «Cliente y enlaces» (plan B, siempre gana); el ya guardado en
  WordPress; el de la presentación bajado del renderer (`/p/<id>/presentation.pdf`, solo https y solo ese host,
  sin redirecciones) si pesa hasta 15 MB. Si no se puede, el correo sale con los botones y el aviso dice por qué.
  Medido en local por SMTP real: el PDF de Orly (14,2 MB) da un correo de 18,6 MB, 98 MB de memoria y 4,6 s.
  Un correo de ~19 MB puede rebotar en el servidor de algún cliente; el rebote llega a contacto@.
- **Guardar** no envía dos veces (doble clic bloqueado) y deja 110 px a la derecha para la burbuja ARIA/MAXTECH
  (mu-plugin de PROD `aria-widget-flotante.php`, fija abajo a la derecha en todo el admin).
- **Página clásica** de respaldo: `…/wp-admin/admin.php?page=automatiza-proposals&clasico=1` (su ✏️ abre la ficha
  nueva; para editar en la clásica, agregar `&edit_id=N`). Se retira en un PR posterior.
- **Código:** `inc/propuestas-admin/` (`consultas.php` puras, probadas con `php tests/propuestas/admin-lista-test.php`;
  `acciones.php`, `lista.php`, `ficha.php`, `clasico.php`) y `assets/css|js/propuestas-admin.*`. Solo se cargan en el
  admin (`is_admin()`), nunca en el sitio ni en la API REST que usa n8n. `functions.php` no se tocó.
- **Despliegue y rollback:** respaldos en `~/respaldos/` con marca `20260924-154847` (tema completo, tabla
  `wp_automatiza_propuestas` con sus 19 filas y los dos archivos reemplazados). Rollback:
  `cd ~ && tar xzf respaldos/propuestas-admin-antes-20260924-154847.tar.gz` (restaura `admin-proposals.php` y
  `client-details-module.php`); con eso los archivos nuevos quedan sin uso porque PROD solo incluye
  `inc/admin-proposals.php`. 🔴 En Hostinger `wp db export` sale con código 255 sin mensaje y sin archivo: la tabla
  se respalda con un script PHP con `SHORTINIT` (`SHOW CREATE TABLE` + un `INSERT` por fila).
- **Segunda subida (24-sep 22:30, correo precargado):** 6 archivos (`consultas.php`, `acciones.php`, `ficha.php`,
  `lista.php`, CSS y JS). Respaldos con marca `20260924-222959`: tema completo y
  `~/respaldos/propuestas-correo-antes-20260924-222959.tar.gz` (rollback: `cd ~ && tar xzf` de ese archivo).
  n8n: «1 Borrador» y «2 Cambios» publicados con `deploy.py` después de comprobar que los vivos eran iguales al
  repo; respaldo previo en `C:/Users/luis_/respaldos/n8n/2026-09-24-correo-precargado/` (rollback: volver a
  publicar esos JSON).

## Fotos por rubro (regla de Luis, 2026-09-24)

Las fotos son del rubro de cada cliente, como las de Jeffer (béisbol) y Orly (funeraria): su gente, sus clientes,
sus productos y lugares, **con personas en acción**. Lo medido con Soul 2 a 720p:

| Caso | Resultado |
|---|---|
| Béisbol con estadio o muro de fondo (3 fotos) | 3/3 con texto inventado (marcador, carteles, polera) |
| Portada de funeraria en primer plano, fondo desenfocado (2 fotos) | 2/2 limpias |
| Celular «con la pantalla hacia el otro lado» (Orly) | limpia |

Reglas que aplica `N8N/propuestas-v3/fotos_guard.py` (compartido por Borrador y Final; pruebas en `probar_fotos.py`):
- Se reemplaza toda descripción que pida pantallas con contenido, sitios web, gráficos, documentos, pizarras,
  letreros, carteles, marcadores, menús o texto.
- Productos con etiqueta o pantalla (botellas, latas, cajas, paquetes, celulares, laptops, libros, estantes):
  el filtro los **neutraliza** («plain unlabeled …», «every screen dark and facing away») en vez de perder la
  escena, porque GPT-4o no escribe «sin etiqueta» aunque se le pida. Probado con una botillería ficticia
  (filas 49–51): de 4 de 8 fotos neutras a 8 de 8 del rubro. Falta ver las fotos reales de licores.
- Cada lámina muestra el mundo del cliente, nunca la solución de AutomatizaTech (nada de celulares navegando,
  computadores, oficinas ni reuniones); el prompt de Borrador dice qué mostrar por lámina.
- La portada va siempre en primer plano con el fondo desenfocado; se reemplaza si pide estadio, fachada, calle o muro.
- Los reemplazos son escenas neutras que sirven para cualquier rubro, nunca las de otro cliente.
- Siempre se agrega el cierre `no signs, no labels, no text, no lettering, no logos, no watermarks`.

Revisar una foto siempre **dentro de la plantilla** (la capa oscura tapa detalles en las láminas interiores,
no en la portada), con `renderProposalHtml` y Playwright en local, sin gastar.

## Correos

Los tres flujos envían a Luis un correo de marca (`N8N/propuestas-v3/email_tpl.py` y `correos.py`).
**Nunca enlazar `*.easypanel.host`** en un correo: el SMTP de Hostinger lo rechaza como spam (554 5.7.1).
Se enlaza `automatizatech.cl/ver-presentacion.php?id=`.

## Cómo se publica y cómo se revierte

**Orden obligatorio:** el bucle de reintentos de «3 Final» solo se publica con un renderer que ya reutiliza
fotos (zip `32ccabf` o posterior). Con un renderer anterior, cada reintento vuelve a pagar todas las fotos.

Estado al 2026-09-24 (02:20): renderer `2b92e3d` en Easypanel (manifiesto antes del render, `unique_id` validado;
verificado: responde 400 «unique_id inválido»); Borrador, Cambios y Final con reintentos, fotos por rubro y aviso de
errores publicados en n8n; Meet apuntando a `propuesta-v3-borrador`; WordPress con las reglas v3 cerradas (envío
solo desde `lista` y desde el panel, `/prompts` 409 en v3, `/state` rechaza `sent`, botón Destrabar, Enter guarda,
precios obligatorios para aprobar). Respaldo previo: `~/respaldos/propuestas-antes-14b-20260924-021846.tar.gz`.

Pendiente de endurecimiento del flujo: detalle en la nota privada de la bóveda
(`10-Projects/2026-09-24-Propuestas-v3-y-Panel-Admin.md`). Cualquier cambio al workflow de Meet exige antes leer el
aviso de abajo sobre reprocesar transcripciones.

- Workflows: `python N8N/propuestas-v3/build_N_*.py` genera el JSON y `python N8N/propuestas-v3/deploy.py N-*.json`
  lo publica (crea o actualiza por nombre, filtra por el host de AT; la clave no se imprime).
  Respaldos antes de cada publicación en `C:/Users/luis_/respaldos/n8n/<fecha>...`.
- Renderer: lo despliega Luis soltando el zip (`git archive --format=zip -o x.zip <commit>:renderer`) en Easypanel.
  Rollback = el zip anterior.
- 🔴 **Editar el workflow de Meet reprocesa transcripciones viejas.** El 2026-09-24, al cambiar solo la URL del
  nodo de envío por la API, el disparador de Drive volvió a tomar la transcripción real de Orly que ya estaba en
  la carpeta y creó la fila 48 (borrador, con su correo real; no se le envió nada). Antes de tocar ese workflow,
  sacar de Transcripciones lo ya procesado (por ejemplo a una subcarpeta «Procesadas») o contar con que se
  repetirá el borrador.
- Meet: para volver al flujo viejo, la URL del nodo «Enviar a Workflow Propuestas» vuelve a
  `https://n8n-n8n.kchiba.easypanel.host/webhook/generar-propuesta-v2` (`APuTGmusbjLAJ74w`, queda de respaldo).
