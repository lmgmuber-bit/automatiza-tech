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

## Cierre de cliente (EN PROD desde el 2026-09-26 13:36)

Diseño: `Docs/superpowers/specs/2026-09-25-cierre-de-cliente-design.md`; plan:
`Docs/superpowers/plans/2026-09-25-cierre-de-cliente.md`; rama `claude/cierre-cliente`. Código en PROD: commit `547f08d`.

- **Responder la propuesta.** `ver-presentacion.php` lleva abajo una barra con «Acepto la propuesta», «La sigo
  evaluando» y «No, gracias» mientras la propuesta está `sent` o `evaluando`. Si está `rechazada`, solo ofrece
  «Acepto la propuesta»; si está `aceptada`, muestra la confirmación y, mientras el contrato admita datos, «Datos para
  tu contrato». En los demás estados (incluida `archivada`) no hay barra. Al aceptar, el cliente escribe su nombre,
  elige su documento (RUT, que se valida con dígito verificador, DNI o pasaporte), marca lo que acepta y, desde la
  Task 18, deja obligatoriamente los datos del contrato: a nombre de quién va (persona natural o empresa, sin opción
  marcada por defecto), la dirección y, si es empresa, su razón social y RUT. Si algo falta o no es válido, la página
  vuelve con el diálogo abierto y el aviso adentro. El correo de
  la propuesta trae el mismo botón «Aceptar la propuesta». La página no se guarda en caché (formularios con nonce).
- **Al aceptar, todo es automático:** nota en Seguimiento («Aceptó la propuesta», tipo `respuesta_cliente`) con lo
  que el cliente aceptó y la huella SHA-256 del contenido completo de la propuesta; propuesta `aceptada`; prospecto →
  cliente en la ficha única (`wp_automatiza_tech_clients.crm_cliente_id` enlaza con `wp_crm_clientes`); correo de
  bienvenida; contrato de servicios en borrador (`servicios_v1`) con los datos del contrato ya puestos, y correo
  «Revisar y firmar» a Luis. Después de aceptar, el cliente sigue viendo «Datos para tu contrato» (mismas reglas),
  que sirve para las aceptaciones a mano o por WhatsApp y para corregir; se abre solo únicamente si al contrato le falta
  la dirección, y a nombre de quién va tampoco viene marcado. «La sigo evaluando» y «No, gracias» cambian
  el estado y avisan a Luis.
- **Task 18: datos del contrato obligatorios al aceptar en la página (27-sep, LOCAL, rama `claude/cierre-cliente`, sin
  desplegar).** En el primer contrato real el cliente aceptó sin llenar el formulario opcional y hubo que pedirle la
  dirección y el tipo de cliente por privado para poder firmar como AT. Ahora el diálogo «Aceptar la propuesta» los
  pide (campos `tipo`, `direccion`, `razon_social`, `rut_empresa`, los mismos del formulario de datos) y los valida con
  una sola función, `at_cc_datos_contrato_de_post()` (`pagina.php`); un formulario abierto desde antes del cambio vuelve
  con «datos». El cierre los aplica al contrato recién creado con `at_cc_aplicar_datos_contrato()`, la misma que usa
  «Datos para tu contrato» (también completa en la ficha operativa solo lo vacío: dirección de facturación, documento y
  empresa); si fallan, el cierre sigue y queda el aviso «Los datos del contrato que dejó el cliente no se aplicaron».
  Completar un cierre a medias los vuelve a aplicar si el contrato todavía no tiene dirección. Los datos van en la
  metadata de la nota de aceptación (`datos_contrato`) y en el correo a Luis, nunca en la descripción, que el cliente
  puede ver en su portal. El diálogo se desplaza en el celular (`max-height` con `dvh`). La aceptación a mano y la de
  WhatsApp no cambian. Archivos para PROD: `inc/cierre-cliente/pagina.php`, `respuesta.php` y `puras.php`, sin
  migración. Pruebas: `tests/cierre/pagina-wp-test.php`, `pagina-datos-wp-test.php`, `puras-test.php` y
  `archivo-wp-test.php`.
- **Visor de la página de firma del cliente (commit `0765a01`, EN PROD el 27-sep según el cierre de esa subida):**
  `contracts/sign-contract.php` pegaba `?v=` a la URL del PDF, que ya traía `?action=…&token=…`; el token llegaba roto y
  el visor decía «Acceso denegado», así que el cliente no podía leer el contrato antes de firmarlo. Ahora usa
  `add_query_arg()`, como la página de revisión de AT. Prueba `tests/cierre/firma-cliente-visor-wp-test.php`.
- **Panel** (ficha › pestaña Envío › «Respuesta del cliente»): estado y última respuesta; «📧 Pedir respuesta por
  correo» (candado de 60 s contra el doble clic) y «Enviar por mi WhatsApp» (`wa.me` con el mensaje y el enlace
  ya escritos); «Registrar aceptación a mano» para cuando el cliente dijo que sí por otro lado, con documento
  opcional y evidencia privada (hasta 3 imágenes JPG, PNG o WebP de 5 MB cada una, que solo se abren con sesión de
  administrador); «🗄️ Archivar» / «Desarchivar». Registrar a mano no manda correo a Luis: el resultado sale en el
  aviso del panel.
- **Archivada:** el cliente ya no puede responderla (ni por la página ni por WhatsApp) y queda como historial. Luis
  puede desarchivarla (vuelve al estado que tenía) o, si al archivarla estaba `sent`, `evaluando`, `rechazada`,
  `pending` o `lista`, registrar la aceptación a mano desde ahí; una archivada desde borrador, `draft` o `error` hay
  que desarchivarla primero. Una reevaluación se hace con una propuesta nueva. Vista «Archivadas» en la lista.
  Archivadas al desplegar (decisión de Luis): 11, 12, 14, 16, 21, 22 y 26; la 42 y la 43 siguen `sent`.
- **Contrato de servicio:** plantilla `CONTRATO_SERVICIO_DESARROLLO.md`, que en PROD vive en
  `domains/automatizatech.cl/Docs/` (fuera de `public_html`; `ContractService::load_template()` la busca ahí). La
  comparecencia sale según el tipo de cliente y el documento. Luis la ajusta en la revisión de AT
  (`contracts/at-sign-contract.php`) y no se puede firmar con datos esenciales en blanco. Los PDF se bajan por
  `admin-ajax.php?action=at_download_contract` con token o sesión. Las carpetas privadas de contratos y evidencias
  bloquean el acceso directo (403, verificado al desplegar); por eso las imágenes de firma del detalle de un contrato
  en el admin ya no se ven (el PDF sí las trae). 🔴 La plantilla sigue marcada «borrador para revisión de un
  abogado»: que un abogado la revise antes del primer contrato de servicios real, y la versión corregida se sube
  también a `domains/automatizatech.cl/Docs/`.
- **Ajustes del cierre** (Propuestas › Ajustes del cierre): banco, tipo y número de cuenta, titular y su RUT,
  correo para avisar el pago y WhatsApp de AT. Van en la bienvenida: la cuenta para transferir el anticipo (el 50 %
  de lo aceptado), a qué correo avisar la transferencia y el WhatsApp para mandar logo y accesos. Si faltan los datos
  bancarios, el cierre igual corre: la bienvenida dice que los datos de transferencia van por separado y queda la nota
  interna «Revisar datos bancarios»; el recordatorio llega en el correo a Luis cuando el cliente acepta por la página
  o por WhatsApp, y en el aviso del panel cuando Luis registra la aceptación a mano. Sin correo para el pago, la
  bienvenida pide avisar respondiendo el correo; sin WhatsApp de AT, se usa el número público de AT.
- **WhatsApp automático: listo pero apagado** hasta la Task 12 del plan. Se enciende con cuatro cosas juntas:
  plantilla de Meta aprobada, flujo n8n `Ex5wZac9VCc66WOm` activo, constante `AT_N8N_CC_WHATSAPP` en `wp-config.php`
  y opción `at_cc_wa_plantilla_activa` = `1`; además `AT_REST_SECRET` (ya definida en PROD) viaja como `X-AT-Secret`.
  Mientras tanto el panel ofrece «Enviar por mi WhatsApp». Las rutas `POST /wp-json/at/v1/propuesta-respuesta` y
  `/propuesta-contexto` (para el bot) exigen la cabecera `X-AT-Secret` igual a `AT_REST_SECRET`: sin ella responden
  401 (500 si la constante faltara).
- **Código y pruebas:** `inc/cierre-cliente/` (lo carga `inc/admin-proposals.php` vía `cargar.php`; `puras.php` sin
  WordPress), `contracts/`, `lib/contract-pdf-fpdf.php`, `mu-plugins/crm-ai-completo.php` (pestaña «📜 Contratos y
  operación» del CRM) y `ver-presentacion.php`. Pruebas: `tests/cierre/` y `tests/propuestas/`.
- **Despliegue y rollback (26-sep, marca `20260926-133408`):** respaldos en `~/respaldos/`: tema completo,
  `cierre-cliente-antes-20260926-133408.tar.gz` (los 17 archivos reemplazados), `contracts-htaccess.antes-…` y
  `tablas-antes-cierre-20260926-133408.sql` (seis tablas). Rollback, en este orden: (1) desarchivar las siete desde
  el panel (o `UPDATE wp_automatiza_propuestas SET status = 'sent' WHERE id IN (11,12,14,16,21,22,26) AND status =
  'archivada'`), porque el código anterior no conoce ese estado; (2) `cd ~ && tar xzf
  respaldos/cierre-cliente-antes-20260926-133408.tar.gz`; (3) borrar `inc/cierre-cliente/` y la plantilla nueva. La
  columna `crm_cliente_id` puede quedarse (admite nulos) y el `.htaccess` de contratos conviene dejarlo. El script
  de despliegue y reversión está anotado en la bóveda privada (nota del despliegue del 26-sep).
- **Segunda subida (26-sep 20:13, tareas 16 y 17, autorizada por Luis):** 5 archivos (`inc/cierre-cliente/ajustes.php`,
  `bienvenida.php`, `respuesta.php`, `whatsapp.php` y `mu-plugins/crm-ai-completo.php`). El portal y la ficha del CRM leen las notas
  del cliente por su ficha operativa enlazada (`at_cc_techs_de_crm()`), nunca por el id del CRM; y Ajustes del cierre suma
  «Correo principal del cierre» (hoy `contacto@automatizatech.cl`) y «Copia oculta», que va en los avisos a Luis, la bienvenida
  y «Pedir respuesta». Las respuestas de los clientes llegan solo al principal. Respaldo
  `~/respaldos/cierre-t16-t17-antes-20260926-201327.tar.gz` (rollback: `tar xzf` de ese archivo desde `~` y borrar las
  opciones `at_cc_correo_avisos` y `at_cc_correo_copia`). Código en PROD: commit `793e995`.

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

**Claves de las entradas del flujo (desde el 2026-09-25):**
- `POST /render` del renderer exige la cabecera `X-AT-Render-Key` (variable `RENDER_KEY` en Easypanel). En n8n la
  manda la credencial «X-AT-Render-Key» de los nodos «Vista previa (sin fotos)», «Vista previa» y «Render final».
  Sin clave responde 401; `/health` y las presentaciones (`/p/…`) siguen públicas.
- El webhook de «1 Borrador» exige la cabecera `X-AT-Borrador-Key` (credencial «X-AT-Borrador-Key»), que manda el
  nodo «Enviar a Workflow Propuestas» del flujo de Meet. Sin clave responde 403 y no crea ejecución.
- Para cambiar una clave: editar el «Value» de la credencial en n8n y, en el caso del renderer, poner el mismo valor
  en `RENDER_KEY` y redesplegar. El campo «Name» de la credencial es el nombre de la cabecera: debe ser
  exactamente `X-AT-Render-Key` o `X-AT-Borrador-Key` (con otro texto, n8n falla con «Header name must be a valid
  HTTP token»).

Cualquier cambio al workflow de Meet exige antes leer el aviso de abajo sobre reprocesar transcripciones.

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
  Desde el 2026-09-25 ese flujo está **desactivado**: para usarlo hay que activarlo y ponerle a su nodo
  «Renderizar Propuesta» la credencial «X-AT-Render-Key», o su `/render` responderá 401.
- Las transcripciones ya procesadas van a Drive › «Transcripciones procesadas» (fuera de la carpeta que vigila Meet).
