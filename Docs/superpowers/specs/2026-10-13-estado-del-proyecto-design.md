# Estado del proyecto y correo de cierre de hito — diseño

Pedido de Luis del 13-oct-2026 (brief «correo de cierre de cada hito del plan» + aclaración de las tres presentaciones).
Aprobado por partes en el chat el 13-oct: enfoque A (renderer), carga B (panel + importar archivo), partes 1 a 4.
Rige para todos los clientes con plan de trabajo. Piloto: Orly (Funerarias Amor de Dios).

## 1. Qué se construye y para qué

Cada vez que termina un hito del plan de un cliente, AutomatizaTech deja evidencia formal de que cumplió lo prometido:

1. Un **cierre** que cuenta el hito con su **antes** (cómo encontramos el requerimiento), **durante** (qué hicimos,
   ligado a las actividades del plan) y **después** (cómo quedó, con evidencia medida: capturas, enlaces, cifras con
   fuente y fecha, videos).
2. Una tercera presentación, **«Estado del proyecto»**, viva: dónde va el proyecto, qué se cerró, qué cambió en el plan,
   qué incluye la Fase 1 y qué el servicio mensual, qué sigue y qué necesitamos del cliente. Se rehace sola cada vez que
   cambia el avance.
3. Un **correo de cierre** propio por cada hito, con enlace a esa presentación, y el **texto de WhatsApp** listo para que
   Luis lo mande. Cada envío queda en el historial del CRM.

Las tres presentaciones del cliente:

| # | Presentación | Página | Cambia |
|---|---|---|---|
| 1 | Propuesta | `ver-presentacion.php` | Antes de la venta. No se toca. |
| 2 | Plan de actividades | `ver-plan.php?id=<código>` | Congelada al firmar. Solo gana un botón y el arreglo del cronograma. |
| 3 | Estado del proyecto | `ver-estado.php?id=<código del plan>` | Nueva y viva. |

Éxito: con el piloto de Orly, Luis importa el cierre «Sitio web», lo publica, ve «Estado del proyecto» en
`ver-estado.php?id=AbCdEf123456`, recibe en su correo la prueba exacta del correo de cierre y el texto de WhatsApp, y en
la ficha del CRM queda el registro. Nada le llega a Orly.

## 2. Decisiones

| # | Decisión | Por qué |
|---|---|---|
| D1 | «Estado del proyecto» sale del **mismo renderer** (`document_type: 'estado'`) y del flujo n8n «3 Render» con un modo nuevo. | Mismo estilo que el plan, genera PDF para adjuntar como evidencia formal. Opción A de Luis. |
| D2 | El contenido de cada cierre se carga desde el panel, a mano o con **«Importar cierre»** (archivo JSON que prepara la sesión del proyecto). Sin ruta de API nueva ni clave nueva. | Lo que se publica siempre pasa por Luis. Opción B de Luis. |
| D3 | El avance vive en una **columna nueva `avance`** (JSON) de `wp_automatiza_planes_trabajo`. Sin tablas nuevas. El `payload` del plan firmado **no se modifica nunca**. | El plan inicial es el registro de lo prometido; el avance es aparte. |
| D4 | Un cierre cubre **uno o más bloques** del plan. Los bloques se identifican por su **nombre**. | El «Sitio web» de Orly cierra dos bloques. Los bloques no tienen id y un plan `enviado` ya no se edita. |
| D5 | Un cierre es **borrador** o **publicado**. El cliente solo ve publicados. Publicar no envía nada. Enviar el correo es otro botón. | Nada al cliente sin el OK de Luis. |
| D6 | **Ninguna cifra sin fuente y fecha.** La importación y el panel rechazan una evidencia `cifra` sin `fuente` o sin `fecha`. | Regla del brief: no se inventan cifras. |
| D7 | Las evidencias (capturas y videos) se alojan en `automatizatech.cl`, en `wp-content/uploads/at-estado/<id del plan>/`, con nombres aleatorios, `noindex` y `no-transform`. Las imágenes se limpian (EXIF y GPS) con el mismo pipeline de los entregables. | PROD acepta subidas de hasta 2 GB (medido el 13-oct); los videos de Orly pesan 11 y 13 MB. Sin depender de YouTube ni Drive. |
| D8 | Todo enlace que llega al cliente va por `automatizatech.cl`. El correo se bloquea si alguna evidencia apunta a `*.easypanel.host`. | El SMTP de Hostinger rechaza esos correos (554). |
| D9 | Sin IA en esta versión: ni fotos nuevas ni sugerencias de texto. «Estado del proyecto» reutiliza las fotos del plan. | Costo cero. El «✨ Sugerir» queda para después si hace falta. |
| D10 | El plan (2) gana el botón «Ver detalle del avance» y el arreglo del cronograma, y un botón de panel **«Actualizar la vista del plan»** para rehacer la vista de un plan `enviado` con el mismo contenido y las mismas fotos. El plan sigue `enviado`. | La lámina del plan está congelada; sin rehacer la vista, Orly no ve el botón. |
| D11 | La presentación de estado se publica en el renderer como `/p/<código>-estado/`. El cliente entra siempre por `ver-estado.php`. | `unique_id` acepta guion (`^[A-Za-z0-9_-]{6,64}$`, `renderer/src/schema.js`). No se crea otro código. |
| D12 | Texto en español de Chile, claro, sin jerga, en plural de equipo («Equipo AutomatizaTech», «terminamos», «te enviamos»). | Regla de Luis del 06-oct. |

## 3. Datos: la columna `avance`

Migración: `ALTER TABLE {prefix}automatiza_planes_trabajo ADD COLUMN avance LONGTEXT NULL`, opción `at_plan_schema` pasa
de `'1'` a `'2'`, idempotente igual que hoy (`datos.php`). Sin la columna el módulo sigue funcionando y la sección «Avance»
avisa que falta la migración.

Forma del JSON (todas las fechas `Y-m-d`; textos limpios, sin HTML):

```json
{
  "version": 1,
  "bloques": {
    "<nombre del bloque>": { "estado": "listo|en_curso|por_venir", "inicio_real": "2026-10-03", "fin_real": "2026-10-10" }
  },
  "cierres": [
    {
      "id": "c1a2b3",
      "titulo": "Sitio web",
      "bloques": ["Rediseño, visibilidad y Google Ads", "Publicación y campaña activa"],
      "fecha_real": "2026-10-10",
      "estado": "borrador|publicado",
      "publicado_at": "2026-10-13 18:00:00",
      "entregable_id": 0,
      "antes":   { "texto": "…", "puntos": ["…"], "evidencias": [] },
      "durante": { "texto": "…", "actividades": [
                     { "bloque": "…", "nombre": "…", "plan_desde": "2026-10-15", "plan_hasta": "2026-10-26",
                       "real": "2026-10-10", "nota": "" } ] },
      "despues": { "texto": "…", "puntos": ["…"], "evidencias": [] },
      "correo":   { "enviado_at": null, "prueba_at": null, "mensaje": "" },
      "whatsapp": { "abierto_at": null }
    }
  ],
  "evidencias": {
    "<id>": { "tipo": "captura|enlace|cifra|video", "titulo": "…", "url": "…", "valor": "98",
              "unidad": "puntos Lighthouse celular", "fuente": "…", "fecha": "2026-10-13", "poster": "…" }
  },
  "cambios_plan": [ { "fecha": "2026-10-13", "titulo": "…", "detalle": "…", "bloques": ["…"] } ],
  "necesitamos": [ { "texto": "…", "estado": "abierto|resuelto", "fecha": "2026-10-13" } ],
  "alcance": { "fase1": ["…"], "mensual": ["…"] },
  "render": { "estado": "pendiente|listo|error", "view_url": "", "pdf_url": "", "nota": "", "at": "" }
}
```

Reglas:

- `bloques`: solo nombres que existen en `payload.fases[].bloques[].nombre`. Sin entrada = `por_venir`. Un bloque cubierto
  por un cierre publicado queda `listo` con `fin_real` = `fecha_real` del cierre (si no traía uno propio).
- `cierres[].durante.actividades`: se precargan desde las actividades del plan de los bloques del cierre (nombre, fechas
  planificadas); la importación o Luis completan `real` y `nota`.
- `evidencias`: un mapa por id; los cierres guardan la lista de ids en `antes.evidencias` / `despues.evidencias`.
  `cifra` exige `valor`, `fuente` y `fecha` (D6). `captura` y `video` apuntan a un archivo subido (D7); `enlace` a una
  URL `https` que no sea `easypanel`. `video` lleva `poster` (primer cuadro, generado al subir) para el PDF.
- `alcance`: si está vacío se precarga con `fase1` = entregables de los bloques del plan y `mensual` =
  `payload.soporte.mensuales`. Luis o la importación lo corrigen (por ejemplo, Google Ads pasa a mensual en Orly).
- Topes: 30 cierres, 60 evidencias, 30 cambios de plan, 30 ítems de «necesitamos», textos de 2.000 caracteres,
  puntos de 200, archivos de evidencia de 200 MB (video) y 15 MB (imagen).

## 4. Importar cierre (archivo JSON)

El archivo lo prepara la sesión del proyecto (por ejemplo, Sereno para Orly) con sus notas, CHANGELOG y mediciones.
Formato documentado con el ejemplo completo de Orly en la guía nueva `Docs/METODO_AT/ESTADO-DEL-PROYECTO.md`.

```json
{
  "formato": "at-cierre-v1",
  "plan": "AbCdEf123456",
  "cierre": { "titulo": "…", "bloques": ["…"], "fecha_real": "…", "entregable_id": 0,
              "antes": {…}, "durante": {…}, "despues": {…} },
  "evidencias": [ { "ref": "lh-celular", "tipo": "cifra", … }, { "ref": "reel", "tipo": "video", "archivo": "reel-sitio-nuevo-v1.mp4" } ],
  "bloques": { "<nombre>": { "estado": "…", "inicio_real": "…", "fin_real": "…" } },
  "cambios_plan": [ … ], "necesitamos": [ … ], "alcance": { … }
}
```

- `plan` debe coincidir con el código del plan abierto en la ficha (evita importar el cierre de otro cliente).
- Las evidencias de tipo `captura` o `video` nombran un `archivo`: el panel pide subir esos archivos en el mismo formulario
  (o después, uno por uno). Hasta que estén todos, el cierre no se puede publicar.
- Validación completa antes de guardar; los errores se listan en lenguaje simple («La cifra "Lighthouse celular" no tiene
  fuente»). Nada se guarda a medias.
- Importar crea o reemplaza un cierre en **borrador**. Nunca toca un cierre publicado: para cambiarlo, Luis lo pasa a
  borrador desde el panel (el cliente deja de verlo hasta volver a publicar).

## 5. Panel: sección «Avance»

En CRM › Ficha › «🗓️ Plan de trabajo», debajo del plan, solo con el plan `enviado`. Administrador y nonce por plan.

- **Bloques:** tabla con cada bloque, su estado y fechas planificadas y reales, editable.
- **Cierres:** lista con título, estado y fechas. Por cierre: «Ver vista previa», «Editar», «Publicar» / «Volver a
  borrador», «Enviarme una prueba», «Enviar al cliente», «Enviar por mi WhatsApp», «Descargar el video».
- **Importar cierre:** subir el JSON y sus archivos; vista previa con los avisos de validación; «Guardar como borrador».
- **Cambios de plan, Necesitamos de ti y Alcance:** listas editables.
- **«Estado del proyecto»:** estado del render (pendiente, listo o error con la nota), enlace a `ver-estado.php`,
  «Reintentar».
- **«Actualizar la vista del plan»** (D10).

Cada guardado que cambia algo visible para el cliente (publicar, volver a borrador, cambiar bloques, cambios de plan,
necesitamos, alcance) pide el render de estado (sección 7).

## 6. Plan de actividades (2): cambios mínimos

En `renderer/src/template-plan.js`:

1. **Botón «Ver detalle del avance»** en la lámina «02 · El método AT», junto a «ESTÁS AQUÍ». Enlace a
   `https://automatizatech.cl/ver-estado.php?id=<código>` con `target="_top"` (el plan se ve dentro de un iframe). En el
   PDF queda como enlace. El cuerpo de render del plan agrega `estado_url` (lo arma WordPress); sin `estado_url` el botón
   no se dibuja, así una propuesta o un plan viejo no cambian.
2. **Cronograma sin etiquetas pisadas.** `carrilesHitos()` reparte en 2 carriles; con tres hitos pegados al final (Orly:
   13-nov, 25-nov y 2-dic) el tercero no tiene carril y su etiqueta, alineada a la izquierda (`is-izq`), se monta sobre
   otra. Arreglo: el cálculo considera el tramo real de cada etiqueta (también las `is-izq`) y abre un **tercer carril**
   cuando no hay uno libre; la fila «Hitos» crece de 72 a 104 px solo en ese caso (descontado del alto de las barras en
   `medidasGantt`). Prueba con los 5 hitos de Orly medida en Chromium: ningún par de etiquetas se intersecta.
3. La prueba que compara byte a byte el HTML de las propuestas sigue pasando.

En WordPress, **«Actualizar la vista del plan»**: pide `at_pt_pedir_render($id, 'final', false)` con una marca
`mantener=1` que el flujo «3 Render» respeta para un plan `enviado`: renderiza con el mismo payload y las fotos ya
guardadas (sin generar ni revisar fotos), guarda `view_url`/`pdf_url` (mismos valores) y **no** cambia el estado. Hoy el
flujo no renderiza un plan `enviado`; el cambio es permitirlo solo con `mantener=1` y sin fotos nuevas.

## 7. «Estado del proyecto» (3)

### Renderer

`document_type: 'estado'`, `validateEstadoPayload()` en `schema.js` y `renderEstadoHtml()` en `template-estado.js` (archivo
nuevo, mismos estilos base que el plan). Láminas:

1. **Portada:** proyecto, «Estado del proyecto», «Actualizado el <fecha>», foto de portada del plan.
2. **Dónde vamos:** «Estás aquí» sobre los bloques (listo, en curso, por venir) con «planificado <fecha> · real <fecha>»;
   cuadro «Ver el plan de actividades inicial» → `https://automatizatech.cl/ver-plan.php?id=<código>` (`target="_top"`).
3. **Cronograma planificado y real:** barras del plan en gris y las reales encima, misma escala semanal que el plan.
4. **Una lámina por cierre publicado** (orden cronológico): antes, durante y después en tres columnas; evidencias con
   miniatura (captura), cifra destacada con fuente y fecha, enlace, video `<video controls preload="none" poster=…>`
   (en el PDF, el póster con el enlace). Si no caben, el cierre usa dos láminas. Con `entregable_id`, enlace «Ver la
   entrega y dejar comentarios» a su `ver-entregable.php`.
5. **Cambios de plan acordados**, con fecha (solo si hay).
6. **Qué incluye:** «Fase 1 · pago único» y «Servicio mensual» en dos columnas.
7. **Lo que sigue y lo que necesitamos de ti:** próximos bloques por venir con su fecha planificada; ítems abiertos.
8. **Cierre:** contacto y «Agendar seguimiento» (mismos enlaces que el cierre del plan).

Todas las URL que dibuja vienen de WordPress y son `https://automatizatech.cl/...`. Sin fotos nuevas: usa las del plan
(manifest del plan en el renderer o `render.images`).

### WordPress y n8n

- `GET /plan/{id}/render&modo=estado`: cuerpo para el renderer con `document_type: 'estado'`, `unique_id` =
  `<código>-estado`, el avance publicado (nunca borradores), los datos del plan necesarios y las URL absolutas de las
  evidencias. Misma autorización de hoy (`automatiza_proposals_rest_auth`, cabecera `X-AT-Secret`).
- `POST /plan/{id}/vista` con `modo: 'estado'`: guarda `avance.render` (no toca `view_url`/`pdf_url` del plan).
- Flujo «3 Render»: modo `estado` = pedir cuerpo, render sin fotos nuevas, guardar vista. Sin correo a Luis (el panel lo
  muestra). Un render de estado nunca cambia el estado del plan.
- WordPress pide el render con `at_pt_pedir_render($id, 'estado', false)` tras cada cambio visible; si n8n no responde,
  `avance.render.estado = error` con la nota y el panel ofrece «Reintentar».

### `ver-estado.php`

Hermana de `ver-plan.php` (`inc/plan-trabajo/vista.php` gana `at_pt_html_ver_estado()`): solo planes `enviado`; iframe de
`avance.render.view_url`; barra con «Ver el plan de actividades inicial» y «Agendar seguimiento» (el mismo diálogo de
agenda de `ver-plan.php`); `noindex`, `nocache`, `DONOTCACHEPAGE`; código inventado o malformado → 404 «no disponible».
Sin cierres publicados o sin render listo: «Pronto verás aquí el avance de tu proyecto», con el enlace al plan.

## 8. Correo de cierre, WhatsApp y CRM

- **Correo** (`inc/plan-trabajo/cierre-correo.php`, plantilla AT de los correos del plan):
  - Asunto «Terminamos: {título} — {proyecto}».
  - Cuerpo: qué terminamos y cuándo frente a lo planificado («lo teníamos planificado para el 5 de noviembre y quedó listo
    el 10 de octubre»); antes, durante y después cortos (hasta 3 puntos cada uno, de `puntos`); cifras con fuente y fecha;
    mensaje opcional de Luis; botón «Ver el estado del proyecto» → `ver-estado.php`; «Lo que sigue» y «Lo que necesitamos
    de ti».
  - Adjunta el PDF de estado si pesa ≤ 15 MB; si no, solo el enlace y el panel lo avisa.
  - Reply-To y copia oculta de «Ajustes del cierre».
  - «Enviarme una prueba» manda el mismo correo a Luis. «Enviar al cliente» exige un cierre publicado y un render de
    estado listo; reclamo atómico para no enviarlo dos veces; reenviar pide confirmación.
  - Bloqueo D8: si alguna URL del correo o de las evidencias del cierre contiene `easypanel`, no se envía y el panel dice cuál.
- **WhatsApp:** texto armado («Hola {nombre}, terminamos {título} 🎉 {una línea del después}. Te dejamos el detalle y el
  video aquí: {ver-estado}») y botón «Enviar por mi WhatsApp» (`wa.me` al teléfono del cliente). «Descargar el video»
  baja el archivo para adjuntarlo a mano. Funciona aunque el cliente no tenga correo.
- **CRM** (`crm_historial`, con `at_cc_historial_crm`): `hito_cierre_publicado` (al publicar), `hito_cierre_correo` y
  `hito_cierre_whatsapp` (al enviar). En `wp-content/mu-plugins/at-timeline-origen.php`: nombres propios
  («🏁 Cierre de hito publicado / enviado por correo / enviado por WhatsApp») y origen 👥 Equipo AutomatizaTech.

## 9. Seguridad

- Todo el panel exige `manage_options` y nonce por plan; las rutas REST usan la autorización existente.
- Archivos de evidencia: tipo comprobado por contenido (JPEG/PNG/WebP y MP4/WebM), nombre aleatorio, carpeta con
  `.htaccess` que impide ejecutar PHP y agrega `noindex` y `no-transform`. Imágenes reescritas con GD sin EXIF ni GPS.
- La importación nunca evalúa contenido: JSON validado campo por campo, textos limpios, URL solo `https`.
- `ver-estado.php` no expone borradores, notas internas ni datos de otros planes.
- Nada de este documento describe vulnerabilidades: el repositorio es público.

## 10. Pruebas

- **Renderer** (`node --test`): cronograma con los 5 hitos de Orly sin etiquetas que se intersecten (medido en Chromium,
  como se midió el plan); `estado` con el fixture de Orly; validación del payload `estado`; botón del plan solo con
  `estado_url`; la prueba byte a byte de propuestas.
- **WordPress** (`tests/plan/`): migración idempotente; importación (cifra sin fuente o sin fecha, fecha inválida, bloque
  inexistente, plan equivocado, archivo faltante, topes); evidencias (tipo por contenido, EXIF y GPS fuera); borrador
  invisible en el cuerpo de render y en `ver-estado.php`; permisos y nonce; `ver-estado.php` 404, `noindex`, sin caché;
  correo sin `easypanel`, adjunto o solo enlace, reclamo atómico; historial con su origen.
- **De punta a punta en local:** renderer local + WordPress de prueba con el cierre de Orly; capturas de `ver-estado.php`
  y del plan con el botón como evidencia.

## 11. Etapas y despliegue

| Etapa | Contenido |
|---|---|
| 1 | Plan: botón (con `estado_url`), cronograma arreglado, «Actualizar la vista del plan», modo `mantener` en «3 Render» y un `ver-estado.php` mínimo («Pronto verás aquí…»). |
| 2 | Columna `avance`, sección «Avance» (bloques, cierres, importar, cambios, necesitamos, alcance), evidencias, plantilla `estado`, modo `estado` en «3 Render», `ver-estado.php` completo. |
| 3 | Correo de cierre, WhatsApp, historial del CRM y nombres en la línea de tiempo. |
| 4 | Piloto Orly. |

Orden de subida en cada etapa: renderer (zip que sube Luis a Easypanel) → n8n («3 Render») → WordPress por SSH, cada uno con
autorización de Luis, respaldo antes, md5 y verificación desde afuera. Rama, PR y pruebas; sin merge sin permiso.

## 12. Piloto con Orly

1. La sesión del proyecto (Sereno) arma el archivo `at-cierre-v1` «Sitio web» con los datos del brief del 13-oct:
   antes (plantilla «Eternity», fotos del personal antiguo, 3 sucursales cerradas, 23 páginas demo en inglés indexadas,
   Lighthouse celular 56 y 62 del 06-oct, sin Search Console ni política de privacidad, medición de llamadas con un número
   en desuso); durante (actividades del plan con sus fechas planificadas y reales: quitar fotos real 03-oct, diseño y
   desarrollo real 10-oct, Search Console y medición 13-oct, aprobación de Orly 09-oct, publicación real 10-oct); después
   (sitio publicado desde el 10-oct verificado por md5, política de privacidad, Search Console con sitemap correcto y 7
   páginas demo retiradas, Bing, Tag Manager versión 5 con las 4 mediciones probadas, videos de evidencia).
2. **Lighthouse 98/100/100 se midió en local.** Se mide en PROD antes de publicar; si no, la evidencia dice fuente
   «medición local» y su fecha.
3. Cambio de plan del 13-oct (opción B de Luis): Google Ads sale de «Diseño y desarrollo» e «Implementación» y pasa a
   «Servicio mensual: Google Ads y Visibilidad»; la ficha de Google Business queda en la Fase 1; el asistente básico sigue
   en Implementación (16 al 25-nov); la fecha de inicio del mensual la define Luis. Va en `cambios_plan` y `alcance`; el
   plan firmado no se toca.
4. El cierre puede apuntar al entregable versión 3 de Orly («Entrega del sitio web y paso a seguimiento»), que ya se le
   envió el 13-oct a mano. No se le vuelve a enviar nada.
5. Luis importa, revisa la vista previa y publica. «Enviarme una prueba» y el texto de WhatsApp van solo a Luis.
6. «Actualizar la vista del plan» de Orly cambia lo que ella ve en su plan (botón y cronograma): necesita el OK aparte de
   Luis.

## 13. Fuera de alcance

- Sugerencias de texto con IA para los cierres.
- Ruta de API para que los agentes carguen cierres.
- Envío automático (sin clic de Luis) de correos o WhatsApp, y plantillas de WhatsApp de Meta.
- Editar el plan firmado o recalcular su cronograma con los cambios de plan.
- Facturación por hito.

## 14. Pendientes para Luis

- Fecha de inicio del «Servicio mensual: Google Ads y Visibilidad» de Orly (se muestra «por definir» hasta entonces).
- Medir Lighthouse en PROD antes de publicar el cierre de Orly.
- Subir el zip del renderer en las etapas 1 y 2.
